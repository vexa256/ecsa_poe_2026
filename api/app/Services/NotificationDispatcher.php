<?php

declare (strict_types = 1);

namespace App\Services;

use App\Services\CaseContextBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * NotificationDispatcher
 *
 * Thin fire-and-forget helper used by controllers (AlertsController,
 * PrimaryScreeningController, AggregatedController, etc.) to trigger emails
 * WITHOUT the ceremony of going through the HTTP /notifications endpoints.
 *
 * Every public method catches all exceptions — a failed notification NEVER
 * fails the primary operation (create alert, create screening, etc.).
 *
 * DESIGN CONTRACT
 *   • Uses the same notification_templates + notification_log tables as the
 *     HTTP controller, so admin visibility is identical.
 *   • Uses the SAME {{token}} mustache render pipeline as the HTTP
 *     controller — templates work identically regardless of trigger path.
 *   • Level fan-out: for a DISTRICT alert, notifies contacts registered at
 *     DISTRICT, PHEOC and NATIONAL (the IHR escalation ladder). For a
 *     PHEOC alert, notifies PHEOC + NATIONAL. For NATIONAL, notifies
 *     NATIONAL + WHO. This is why the 19 Uganda NATIONAL-level contacts
 *     receive alerts routed to any level.
 */
final class NotificationDispatcher
{
    /**
     * Per-template suppression windows in MINUTES. The same (template,
     * entity, contact) triple cannot send more than once inside this window.
     * Tuned for low-noise operation — escalations beat the dedup window
     * by definition (different template_code).
     */
    private const SUPPRESSION_MINUTES = [
        'ALERT_CRITICAL'   => 30,    // half-hour cooldown — repeated CRITICAL = retry only
        'ALERT_HIGH'       => 120,   // 2-hour cooldown
        'ALERT_CASE_FILE'  => 360,   // 6 hours — case-file is a one-shot per disposition
        'TIER1_ADVISORY'   => 30,
        'ANNEX2_HIT'       => 360,
        'BREACH_717'       => 240,   // 4 hours — same alert may breach multiple stages
        'FOLLOWUP_DUE'     => 180,   // 3 hours
        'FOLLOWUP_OVERDUE' => 240,
        'DAILY_REPORT'     => 60,    // 1 hour — should never overlap; cron at 07:00
        'WEEKLY_REPORT'    => 60,
        'ESCALATION'       => 30,
        'PHEIC_ADVISORY'   => 60,
        'ALERT_CLOSED'     => 60,
        'NATIONAL_INTELLIGENCE' => 60,
        'RESPONDER_INFO_REQUEST' => 1440, // 24h — request once per case per responder
    ];
    private const DEFAULT_SUPPRESSION_MINUTES = 60;


    /**
     * Fire emails for a newly-created alert. Picks the right template
     * (Tier 1 → TIER1_ADVISORY, otherwise risk-based) and fans out across
     * the escalation ladder.
     *
     * @param object $alert      alerts row (freshly inserted)
     * @param int    $userId     who triggered (for notification_log.triggered_by)
     */
    public static function dispatchAlertCreated(object $alert, int $userId = 0): array
    {
        return static::safely(function () use ($alert, $userId) {
            $tierOne = is_string($alert->ihr_tier ?? null) && str_contains($alert->ihr_tier, 'TIER_1');
            $tierTwo = is_string($alert->ihr_tier ?? null) && str_contains($alert->ihr_tier, 'TIER_2');

            $templateCode = $tierOne
                ? 'TIER1_ADVISORY'
                : (($alert->risk_level ?? 'HIGH') === 'CRITICAL' ? 'ALERT_CRITICAL' : 'ALERT_HIGH');

            // Build the full decision-grade payload (demographics, vitals,
            // travel, symptoms, exposures, differential, disease intel, etc.)
            $vars = CaseContextBuilder::forAlert($alert);

            // Fan out across the IHR ladder (level + all levels above)
            $levels = static::ladderFrom((string) ($alert->routed_to_level ?? 'DISTRICT'));
            $contacts = static::resolveContactsForAlert($alert, $levels);

            $sent = 0; $skipped = 0; $failed = 0;
            foreach ($contacts as $c) {
                $out = static::send($c, $templateCode, $vars, $alert, 'USER:' . $userId, 'ALERT', (int) $alert->id);
                $out['status'] === 'SENT' ? $sent++ : ($out['status'] === 'SKIPPED' ? $skipped++ : $failed++);
            }

            // Extra: if Tier 1 also send PHEIC advisory to NATIONAL + WHO
            if ($tierOne) {
                $pheicContacts = DB::table('poe_notification_contacts')
                    ->whereIn('level', ['NATIONAL', 'WHO'])
                    ->where('country_code', $alert->country_code)
                    ->where('is_active', 1)->whereNull('deleted_at')
                    ->where('receives_tier1', 1)
                    ->get();
                foreach ($pheicContacts as $c) {
                    $out = static::send($c, 'PHEIC_ADVISORY', $vars, $alert, 'USER:' . $userId, 'ALERT', (int) $alert->id);
                    $out['status'] === 'SENT' ? $sent++ : ($out['status'] === 'SKIPPED' ? $skipped++ : $failed++);
                }
            }

            return ['template' => $templateCode, 'sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
        }, 'dispatchAlertCreated');
    }

    /**
     * Fire email when an alert is closed. Informational only — goes to the
     * same ladder that received the creation notification.
     */
    public static function dispatchAlertClosed(object $alert, int $userId, string $closedByName, string $closeReason): array
    {
        return static::safely(function () use ($alert, $userId, $closedByName, $closeReason) {
            $vars = CaseContextBuilder::forAlert($alert, [
                'closed_by_name'     => $closedByName,
                'close_reason'       => mb_substr($closeReason, 0, 500),
                'close_reason_short' => mb_substr($closeReason, 0, 80),
            ]);
            $levels = static::ladderFrom((string) ($alert->routed_to_level ?? 'DISTRICT'));
            $contacts = static::resolveContactsForAlert($alert, $levels);
            $sent = 0; $skipped = 0; $failed = 0;
            foreach ($contacts as $c) {
                $out = static::send($c, 'ALERT_CLOSED', $vars, $alert, 'USER:' . $userId, 'ALERT', (int) $alert->id);
                $out['status'] === 'SENT' ? $sent++ : ($out['status'] === 'SKIPPED' ? $skipped++ : $failed++);
            }
            return ['template' => 'ALERT_CLOSED', 'sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
        }, 'dispatchAlertClosed');
    }

    /**
     * Fire email when a primary screening flags a referral. Uses the
     * HIGH-risk template so contacts see it in the same visual language.
     * Notifies the POE + DISTRICT + PHEOC + NATIONAL ladder.
     */
    public static function dispatchScreeningReferral(object $screening, ?object $notification, int $userId = 0): array
    {
        return static::safely(function () use ($screening, $notification, $userId) {
            // Only fire if a notification was actually created with HIGH/CRITICAL priority
            if (! $notification) {
                return ['skipped_no_notification' => true];
            }
            $priority = (string) ($notification->priority ?? 'NORMAL');
            if (! in_array($priority, ['HIGH', 'CRITICAL'], true)) {
                return ['skipped_low_priority' => true];
            }

            $vars = CaseContextBuilder::forScreening($screening, $notification);
            $templateCode = $priority === 'CRITICAL' ? 'ALERT_CRITICAL' : 'ALERT_HIGH';
            $levels = ['POE', 'DISTRICT', 'PHEOC', 'NATIONAL'];
            $contacts = static::resolveContactsByScope(
                $screening->country_code ?? null,
                $screening->district_code ?? null,
                $screening->poe_code ?? null,
                $levels,
                $priority === 'CRITICAL' ? 'receives_critical' : 'receives_high',
            );

            $sent = 0; $skipped = 0; $failed = 0;
            foreach ($contacts as $c) {
                $out = static::send($c, $templateCode, $vars, $screening, 'USER:' . $userId, 'SCREENING', (int) ($screening->id ?? 0));
                $out['status'] === 'SENT' ? $sent++ : ($out['status'] === 'SKIPPED' ? $skipped++ : $failed++);
            }
            return ['template' => $templateCode, 'priority' => $priority, 'sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
        }, 'dispatchScreeningReferral');
    }

    /**
     * The 14 RTSL early response actions per Resolve to Save Lives + WHO
     * 7-1-7. Auto-seeded against every newly created alert so the response
     * checklist is ready the moment operators open the case file.
     *
     * Format: [code, label, due_offset_hours, blocks_closure].
     */
    public const RTSL_14_ACTIONS = [
        ['CASE_INVESTIGATION',     'Case investigation started',                       4,  true ],
        ['ISOLATION',              'Index case isolated / treatment initiated',        4,  true ],
        ['CONTACT_LISTING',        'Close contacts identified and listed',             24, true ],
        ['CONTACT_TRACING',        'Contact tracing and follow-up operational',        24, true ],
        ['LAB_SPECIMENS',          'Laboratory specimens collected and transported',   48, false],
        ['LAB_CONFIRMATION',       'Laboratory confirmation obtained',                 48, false],
        ['LINE_LIST',              'Epidemiological line list maintained',             48, false],
        ['RISK_COMMS',             'Risk communication to the public initiated',       72, false],
        ['IPC',                    'Infection prevention & control (IPC) in facilities',72, false],
        ['VECTOR_CONTROL',         'Vector control measures (if applicable)',          72, false],
        ['POE_SURVEILLANCE',       'Cross-border / POE surveillance strengthened',     168, false],
        ['EOC_ACTIVATION',         'Coordination structure activated (EOC / PHEOC)',   24, true ],
        ['RESOURCE_MOBILISATION',  'Response resources mobilised',                     168, false],
        ['WHO_NOTIFICATION',       'WHO and partners notified per IHR Article 6',      24, true ],
    ];

    /**
     * Auto-seed the 14 RTSL early response actions against an alert. Idempotent
     * — re-runs add only the missing rows so it is safe to call repeatedly.
     */
    public static function seedRtsl14Followups(object $alert, int $userId): array
    {
        return static::safely(function () use ($alert, $userId) {
            $existing = DB::table('alert_followups')
                ->where('alert_id', $alert->id)
                ->whereNull('deleted_at')
                ->pluck('action_code')->all();
            $existingSet = array_flip($existing);
            $createdAt = strtotime((string) ($alert->created_at ?? now()));
            $created = 0;
            foreach (self::RTSL_14_ACTIONS as $row) {
                [$code, $label, $hours, $blocks] = $row;
                if (isset($existingSet[$code])) continue;
                $dueAt = date('Y-m-d H:i:s', $createdAt + $hours * 3600);
                DB::table('alert_followups')->insert([
                    'client_uuid'       => static::genUuid(),
                    'alert_id'          => $alert->id,
                    'alert_client_uuid' => $alert->client_uuid ?? null,
                    'action_code'       => $code,
                    'action_label'      => $label,
                    'status'            => 'PENDING',
                    'due_at'            => $dueAt,
                    'blocks_closure'    => $blocks ? 1 : 0,
                    'country_code'      => $alert->country_code ?? 'UG',
                    'district_code'     => $alert->district_code ?? '',
                    'poe_code'          => $alert->poe_code ?? '',
                    'created_by_user_id' => $userId,
                    'device_id'         => 'server',
                    'platform'          => 'WEB',
                    'record_version'    => 1,
                    'sync_status'       => 'SYNCED',
                    'synced_at'         => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
                $created++;
            }
            return ['seeded' => $created, 'already_present' => count($existing)];
        }, 'seedRtsl14Followups');
    }

    private static function genUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
    }

    /**
     * Rich case-file dispatch — used when a secondary screening reaches a
     * disposition that is NOT a confirmed non-case. Pulls the full case
     * context (traveller, exposures, samples, actions, suspected diseases)
     * and renders the ALERT_CASE_FILE template with all 12 contextual
     * fields the spec demands (what / where / when / who / why / status /
     * actions taken / next required / owner / deadline / case IDs).
     *
     * @param object $secondaryCase  secondary_screenings row
     * @param int    $userId         operator who triggered (for log)
     * @param ?object $alertOverride optional alert to attach the case file to
     */
    public static function dispatchCaseFile(object $secondaryCase, int $userId = 0, ?object $alertOverride = null): array
    {
        return static::safely(function () use ($secondaryCase, $userId, $alertOverride) {
            // Skip non-cases — the spec is explicit: only fire when the
            // case is something other than a confirmed non-case.
            $disp = strtoupper((string) ($secondaryCase->final_disposition ?? ''));
            if (in_array($disp, ['NON_CASE', 'NOT_A_CASE', 'NONE'], true)) {
                return ['skipped_non_case' => true];
            }

            // Locate (or create a virtual) alert for the case file
            $alert = $alertOverride
                ?? DB::table('alerts')
                    ->where('secondary_screening_id', $secondaryCase->id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->first();

            // Render-time vars — CaseContextBuilder produces the full
            // decision-grade payload. Fall back to a virtual alert object if
            // no alerts row exists yet (case file dispatched from secondary
            // screening before any alert has been raised).
            $virtualAlert = $alert ?? (object) [
                'id'              => 0,
                'alert_code'      => 'CASEFILE-' . ($secondaryCase->id ?? '?'),
                'alert_title'     => 'Case file dispatch · ' . ($secondaryCase->traveler_full_name ?? 'Anonymous traveller'),
                'alert_details'   => 'Full case file dispatched from secondary screening.',
                'risk_level'      => (string) ($secondaryCase->risk_level ?? 'HIGH'),
                'routed_to_level' => (string) ($secondaryCase->followup_assigned_level ?? 'DISTRICT'),
                'ihr_tier'        => 'none',
                'country_code'    => (string) ($secondaryCase->country_code ?? ''),
                'district_code'   => (string) ($secondaryCase->district_code ?? ''),
                'poe_code'        => (string) ($secondaryCase->poe_code ?? ''),
                'secondary_screening_id' => (int) ($secondaryCase->id ?? 0),
                'status'          => 'OPEN',
                'created_at'      => $secondaryCase->created_at ?? now(),
            ];
            $vars = CaseContextBuilder::forAlert($virtualAlert);
            $relatedEntityId = (int) ($alert->id ?? $secondaryCase->id);

            // Country-aware contact resolution: only contacts in the same
            // country as the case. Routes across the IHR ladder.
            $levels = ['POE', 'DISTRICT', 'PHEOC', 'NATIONAL'];
            $contacts = static::resolveContactsByScope(
                $secondaryCase->country_code ?? null,
                $secondaryCase->district_code ?? null,
                $secondaryCase->poe_code ?? null,
                $levels,
                'receives_high', // case files always go to high+
            );

            $sent = 0; $skipped = 0; $failed = 0;
            foreach ($contacts as $c) {
                $out = static::send($c, 'ALERT_CASE_FILE', $vars, $alert ?? $secondaryCase,
                    'USER:' . $userId, 'CASE_FILE', $relatedEntityId);
                if ($out['status'] === 'SENT')      $sent++;
                elseif ($out['status'] === 'SKIPPED') $skipped++;
                else                                 $failed++;
            }
            return ['template' => 'ALERT_CASE_FILE', 'sent' => $sent, 'skipped' => $skipped, 'failed' => $failed,
                    'recipients_resolved' => $contacts->count()];
        }, 'dispatchCaseFile');
    }

    /**
     * Send a structured info-request to an external responder (hospital, lab,
     * partner agency) about a specific case. Persists a one-time token so a
     * future inbound endpoint can match the response back to the case.
     */
    public static function requestExternalResponderInfo(int $responderId, int $alertId, int $userId, string $requestBody, ?string $subjectOverride = null): array
    {
        return static::safely(function () use ($responderId, $alertId, $userId, $requestBody, $subjectOverride) {
            $responder = DB::table('external_responders')->where('id', $responderId)->whereNull('deleted_at')->first();
            if (! $responder) return ['error' => 'External responder not found'];
            $alert = DB::table('alerts')->where('id', $alertId)->whereNull('deleted_at')->first();
            if (! $alert) return ['error' => 'Alert not found'];

            $token = bin2hex(random_bytes(24)); // 48-char hex
            DB::table('responder_info_requests')->insert([
                'responder_id'           => $responderId,
                'alert_id'               => $alertId,
                'secondary_screening_id' => $alert->secondary_screening_id,
                'requested_by_user_id'   => $userId,
                'request_token'          => $token,
                'request_subject'        => $subjectOverride ?? "POE Sentinel · Information request · {$alert->alert_code}",
                'request_body'           => $requestBody,
                'status'                 => 'SENT',
                'expires_at'             => now()->addDays(7),
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            // Hand-craft a contact-shaped object so we can reuse send()
            $virtualContact = (object) [
                'id'             => null,
                'email'          => $responder->email,
                'phone'          => $responder->phone,
                'country_code'   => $responder->country_code,
                'district_code'  => $responder->district_code,
                'poe_code'       => null,
                'alternate_email' => null,
            ];
            $vars = CaseContextBuilder::forAlert($alert, [
                'responder_name' => (string) $responder->name,
                'request_body'   => $requestBody,
                'request_token'  => $token,
            ]);
            $out = static::send($virtualContact, 'RESPONDER_INFO_REQUEST', $vars, $alert,
                'USER:' . $userId, 'RESPONDER_REQUEST', (int) $alertId);
            return array_merge($out, ['token' => $token]);
        }, 'requestExternalResponderInfo');
    }

    /**
     * Fire email when a 7-1-7 breach is detected on an alert.
     */
    public static function dispatchBreach717(object $alert, string $bottleneckPhase, float $elapsedHours, int $targetHours, int $userId = 0): array
    {
        return static::safely(function () use ($alert, $bottleneckPhase, $elapsedHours, $targetHours, $userId) {
            $vars = CaseContextBuilder::forAlert($alert, [
                'bottleneck_phase' => strtoupper($bottleneckPhase),
                'elapsed_hours'    => (string) round($elapsedHours, 1),
                'target_hours'     => (string) $targetHours,
            ]);
            $levels = ['DISTRICT', 'PHEOC', 'NATIONAL'];
            $contacts = static::resolveContactsForAlert($alert, $levels, 'receives_breach_alerts');
            $sent = 0; $skipped = 0; $failed = 0;
            foreach ($contacts as $c) {
                $out = static::send($c, 'BREACH_717', $vars, $alert, 'USER:' . $userId, 'ALERT', (int) $alert->id);
                $out['status'] === 'SENT' ? $sent++ : ($out['status'] === 'SKIPPED' ? $skipped++ : $failed++);
            }
            return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
        }, 'dispatchBreach717');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  SCHEDULED JOBS — called from Laravel console scheduler
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Compute the last-24h digest for each country-scope + fan out to every
     * contact subscribed to receives_daily_report=1.
     */
    public static function sendDailyDigest(string $triggeredBy = 'CRON:daily'): array
    {
        return static::safely(function () use ($triggeredBy) {
            $countries = DB::table('poe_notification_contacts')
                ->where('receives_daily_report', 1)
                ->where('is_active', 1)->whereNull('deleted_at')
                ->distinct()->pluck('country_code');

            $totalSent = 0; $totalFailed = 0; $totalSkipped = 0;
            foreach ($countries as $cc) {
                $stats = static::buildDailyDigestVars((string) $cc);
                $contacts = DB::table('poe_notification_contacts')
                    ->where('country_code', $cc)
                    ->where('receives_daily_report', 1)
                    ->where('is_active', 1)->whereNull('deleted_at')
                    ->get();
                foreach ($contacts as $c) {
                    $out = static::send($c, 'DAILY_REPORT', $stats, null, $triggeredBy, 'REPORT');
                    if ($out['status'] === 'SENT') $totalSent++;
                    elseif ($out['status'] === 'SKIPPED') $totalSkipped++;
                    else $totalFailed++;
                }
            }
            return ['sent' => $totalSent, 'skipped' => $totalSkipped, 'failed' => $totalFailed];
        }, 'sendDailyDigest');
    }

    /**
     * Assemble the full DAILY_REPORT variable payload for a single country.
     * Includes aggregate counts, top/silent POEs, disposition breakdown, and
     * the syndromes-HTML fragment the template consumes.
     */
    public static function buildDailyDigestVars(string $cc): array
    {
        $since = now()->subDay()->format('Y-m-d H:i:s');
        $today = now()->format('Y-m-d');
        $eod   = now()->endOfDay()->format('Y-m-d H:i:s');

        $alertsCount = fn(?string $risk) => (string) DB::table('alerts')
            ->where('country_code', $cc)
            ->when($risk, fn($q) => $q->where('risk_level', $risk))
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')->count();

        $dispositionCount = fn(array $d) => (string) DB::table('secondary_screenings')
            ->where('country_code', $cc)
            ->whereIn('final_disposition', $d)
            ->where('dispositioned_at', '>=', $since)
            ->whereNull('deleted_at')->count();

        // Top POEs by screening volume in window
        $topPoes = DB::table('primary_screenings')
            ->selectRaw('poe_code, COUNT(*) AS n, SUM(symptoms_present) AS symp')
            ->where('country_code', $cc)
            ->where('captured_at', '>=', $since)
            ->whereNull('deleted_at')
            ->groupBy('poe_code')
            ->orderByDesc('n')
            ->limit(5)->get();

        // Silent POEs — registered POEs with zero submissions in window
        $silent = DB::table('poe_notification_contacts')
            ->where('country_code', $cc)
            ->whereNotNull('poe_code')
            ->where('is_active', 1)->whereNull('deleted_at')
            ->whereNotIn('poe_code', DB::table('primary_screenings')
                ->select('poe_code')
                ->where('country_code', $cc)
                ->where('captured_at', '>=', $since)
                ->whereNull('deleted_at'))
            ->distinct()->pluck('poe_code')->take(10)->all();

        // Syndromes in window
        $syndromes = DB::table('secondary_screenings')
            ->selectRaw('COALESCE(syndrome_classification, "(none)") AS k, COUNT(*) AS n')
            ->where('country_code', $cc)
            ->where('opened_at', '>=', $since)
            ->whereNull('deleted_at')
            ->groupBy('k')->orderByDesc('n')->limit(8)->get();

        $topPoesHtml = $topPoes->isEmpty()
            ? '<p style="margin:0;color:#64748B;font-size:12px;">No primary screening activity captured.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . $topPoes->map(fn($r) =>
                '<li style="margin:3px 0;"><strong>' . htmlspecialchars((string) $r->poe_code, ENT_QUOTES, 'UTF-8') . '</strong> · ' . (int) $r->n . ' screenings (' . (int) $r->symp . ' symptomatic)</li>'
            )->implode('') . '</ul>';

        $silentPoesHtml = empty($silent)
            ? '<p style="margin:0;color:#047857;font-size:12px;">No silent POEs — every registered POE has reported.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . implode('', array_map(fn($p) =>
                '<li style="margin:3px 0;color:#B91C1C;">' . htmlspecialchars((string) $p, ENT_QUOTES, 'UTF-8') . '</li>', $silent)) . '</ul>';

        $syndromesHtml = $syndromes->isEmpty()
            ? '<p style="margin:0;color:#64748B;font-size:12px;">No secondary screenings in the window.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . $syndromes->map(fn($s) =>
                '<li style="margin:3px 0;">' . htmlspecialchars((string) $s->k, ENT_QUOTES, 'UTF-8') . ' — <strong>' . (int) $s->n . '</strong></li>'
            )->implode('') . '</ul>';

        return [
            'country_code' => $cc,
            'country_name' => match (strtoupper($cc)) {
                'UG' => 'Uganda', 'RW' => 'Rwanda', 'ZM' => 'Zambia',
                'MW' => 'Malawi', 'ST', 'STP' => 'São Tomé and Príncipe',
                default => $cc,
            },
            'report_date' => $today,
            'now'         => now()->format('Y-m-d H:i'),
            'now_date'    => $today,
            'console_url'   => \App\Services\AdminLinks::alertsHub(),
            'action_url'    => \App\Services\AdminLinks::dashboard(),
            'dashboard_url' => \App\Services\AdminLinks::dashboard(),
            'hub_url'       => \App\Services\AdminLinks::alertsHub(),
            'app_url'       => \App\Services\AdminLinks::base(),

            'primary_screenings_24h'  => (string) DB::table('primary_screenings')->where('country_code', $cc)->where('captured_at', '>=', $since)->whereNull('deleted_at')->count(),
            'primary_symptomatic_24h' => (string) DB::table('primary_screenings')->where('country_code', $cc)->where('captured_at', '>=', $since)->where('symptoms_present', 1)->whereNull('deleted_at')->count(),
            'alerts_24h'              => $alertsCount(null),
            'alerts_critical_24h'     => $alertsCount('CRITICAL'),
            'alerts_high_24h'         => $alertsCount('HIGH'),
            'alerts_medium_24h'       => $alertsCount('MEDIUM'),
            'alerts_low_24h'          => $alertsCount('LOW'),
            'alerts_stuck_open'       => (string) DB::table('alerts')
                ->where('country_code', $cc)->where('status', 'OPEN')
                ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, NOW()) > 24')
                ->whereNull('deleted_at')->count(),

            'followups_overdue_total' => (string) DB::table('alert_followups')
                ->where('country_code', $cc)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['COMPLETED', 'NOT_APPLICABLE'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
            'followups_due_today' => (string) DB::table('alert_followups')
                ->where('country_code', $cc)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['COMPLETED', 'NOT_APPLICABLE'])
                ->whereNotNull('due_at')
                ->where('due_at', '>=', now())
                ->where('due_at', '<=', $eod)
                ->count(),

            'disposition_released' => $dispositionCount(['RELEASED']),
            'disposition_referred' => $dispositionCount(['REFERRED', 'TRANSFERRED']),
            'disposition_isolated' => $dispositionCount(['ISOLATED', 'QUARANTINED']),
            'disposition_delayed'  => $dispositionCount(['DELAYED', 'DENIED_BOARDING']),

            'top_poes_html'    => $topPoesHtml,
            'silent_poes_html' => $silentPoesHtml,
            'syndromes_html'   => $syndromesHtml,
        ];
    }

    /**
     * Assemble NATIONAL_INTELLIGENCE vars for a country. Produces the HTML
     * fragments (silent_poes_html, stuck_alerts_html, etc.) the template
     * consumes, plus the counts and the narrative string.
     */
    public static function buildNationalIntelligenceVars(string $cc): array
    {
        $since24 = now()->subDay()->format('Y-m-d H:i:s');
        $since3d = now()->subDays(3)->format('Y-m-d H:i:s');
        $since14 = now()->subDays(14)->format('Y-m-d H:i:s');

        // Silent POEs (active in 7d, none in 24h)
        $activeRecent = DB::table('primary_screenings')
            ->where('country_code', $cc)
            ->where('captured_at', '>=', now()->subDays(7)->format('Y-m-d H:i:s'))
            ->whereNull('deleted_at')
            ->distinct()->pluck('poe_code')->all();
        $silent = [];
        foreach ($activeRecent as $poe) {
            $hadRecent = DB::table('primary_screenings')
                ->where('country_code', $cc)->where('poe_code', $poe)
                ->where('captured_at', '>=', $since24)->whereNull('deleted_at')->exists();
            if (! $hadRecent) $silent[] = $poe;
        }

        // Unsubmitted POEs (> 3d offline)
        $regulars = DB::table('aggregated_submissions')
            ->where('country_code', $cc)->where('created_at', '>=', now()->subDays(14)->format('Y-m-d H:i:s'))
            ->whereNull('deleted_at')->distinct()->pluck('poe_code')->all();
        $unsubmitted = [];
        foreach ($regulars as $poe) {
            $recent = DB::table('aggregated_submissions')
                ->where('country_code', $cc)->where('poe_code', $poe)
                ->where('created_at', '>=', $since3d)->whereNull('deleted_at')->exists();
            if (! $recent) $unsubmitted[] = $poe;
        }

        // Dormant accounts (no login in 14d)
        $dormant = DB::table('users')
            ->where('country_code', $cc)
            ->where('is_active', 1)
            ->where(function ($q) use ($since14) {
                $q->whereNull('last_login_at')->orWhere('last_login_at', '<', $since14);
            })
            ->select('full_name', 'email', 'last_login_at', 'role_key')
            ->limit(20)->get();

        // Stuck alerts (open past SLA)
        $stuck = DB::table('alerts')
            ->where('country_code', $cc)
            ->where('status', 'OPEN')
            ->whereNull('deleted_at')
            ->whereRaw("TIMESTAMPDIFF(HOUR, created_at, NOW()) > (CASE risk_level WHEN 'CRITICAL' THEN 4 WHEN 'HIGH' THEN 24 ELSE 48 END)")
            ->select('alert_code', 'risk_level', 'poe_code', 'alert_title', 'created_at')
            ->orderBy('created_at')
            ->limit(15)->get();

        // Overdue followups
        $overdueFu = DB::table('alert_followups as f')
            ->leftJoin('alerts as a', 'a.id', '=', 'f.alert_id')
            ->where('f.country_code', $cc)
            ->whereNull('f.deleted_at')
            ->whereNotIn('f.status', ['COMPLETED', 'NOT_APPLICABLE'])
            ->whereNotNull('f.due_at')
            ->where('f.due_at', '<', now())
            ->select('f.action_code', 'f.action_label', 'f.due_at', 'f.status', 'a.alert_code', 'f.poe_code', 'f.blocks_closure')
            ->orderBy('f.due_at')->limit(20)->get();

        // Case spikes — disease × district with >2x the 14d rolling daily average
        $spikes = DB::select("
            SELECT sd.disease_code, s.district_code, COUNT(*) AS n24,
                   (SELECT COUNT(*) FROM secondary_suspected_diseases sd2
                    INNER JOIN secondary_screenings s2 ON s2.id = sd2.secondary_screening_id
                    WHERE sd2.disease_code = sd.disease_code AND s2.district_code = s.district_code
                      AND s2.country_code = ? AND s2.opened_at >= ? AND s2.opened_at < ?) AS n14
            FROM secondary_suspected_diseases sd
            INNER JOIN secondary_screenings s ON s.id = sd.secondary_screening_id
            WHERE s.country_code = ? AND s.opened_at >= ?
            GROUP BY sd.disease_code, s.district_code
            HAVING n24 >= 2 AND (n14 = 0 OR n24 > (n14 / 14) * 2)
            ORDER BY n24 DESC
            LIMIT 10
        ", [$cc, now()->subDays(14)->format('Y-m-d H:i:s'), $since24, $cc, $since24]);

        // HTML fragments
        $silentHtml = empty($silent)
            ? '<p style="margin:0;color:#047857;font-size:12px;">No silent POEs.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . implode('', array_map(fn($p) =>
                '<li style="margin:3px 0;color:#B91C1C;">' . htmlspecialchars((string) $p, ENT_QUOTES, 'UTF-8') . '</li>', $silent)) . '</ul>';

        $unsubmittedHtml = empty($unsubmitted)
            ? '<p style="margin:0;color:#047857;font-size:12px;">All pipelines synced.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . implode('', array_map(fn($p) =>
                '<li style="margin:3px 0;color:#B45309;">' . htmlspecialchars((string) $p, ENT_QUOTES, 'UTF-8') . '</li>', $unsubmitted)) . '</ul>';

        $dormantHtml = $dormant->isEmpty()
            ? '<p style="margin:0;color:#047857;font-size:12px;">All officers logged in recently.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . $dormant->map(fn($u) =>
                '<li style="margin:3px 0;"><strong>' . htmlspecialchars((string) ($u->full_name ?? '—'), ENT_QUOTES, 'UTF-8') . '</strong> · ' . htmlspecialchars((string) ($u->role_key ?? ''), ENT_QUOTES, 'UTF-8') . ' · last login: <em>' . htmlspecialchars((string) ($u->last_login_at ?? 'never'), ENT_QUOTES, 'UTF-8') . '</em></li>'
            )->implode('') . '</ul>';

        $stuckHtml = $stuck->isEmpty()
            ? '<p style="margin:0;color:#047857;font-size:12px;">No stuck alerts.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . $stuck->map(fn($a) =>
                '<li style="margin:4px 0;"><strong>' . htmlspecialchars((string) $a->alert_code, ENT_QUOTES, 'UTF-8') . '</strong> · ' . htmlspecialchars((string) $a->risk_level, ENT_QUOTES, 'UTF-8') . ' · POE ' . htmlspecialchars((string) $a->poe_code, ENT_QUOTES, 'UTF-8') . '<br><span style="color:#64748B;font-size:11px;">' . htmlspecialchars((string) $a->alert_title, ENT_QUOTES, 'UTF-8') . ' · opened ' . htmlspecialchars((string) $a->created_at, ENT_QUOTES, 'UTF-8') . '</span></li>'
            )->implode('') . '</ul>';

        $overdueHtml = $overdueFu->isEmpty()
            ? '<p style="margin:0;color:#047857;font-size:12px;">No overdue follow-ups.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . $overdueFu->map(fn($f) =>
                '<li style="margin:4px 0;"><strong>[' . htmlspecialchars((string) $f->action_code, ENT_QUOTES, 'UTF-8') . ']</strong> ' . htmlspecialchars((string) $f->action_label, ENT_QUOTES, 'UTF-8') . ' · alert ' . htmlspecialchars((string) $f->alert_code, ENT_QUOTES, 'UTF-8') . ' · due ' . htmlspecialchars((string) $f->due_at, ENT_QUOTES, 'UTF-8') . (((int) $f->blocks_closure) === 1 ? ' · <span style="color:#B91C1C;">blocks closure</span>' : '') . '</li>'
            )->implode('') . '</ul>';

        $spikesHtml = empty($spikes)
            ? '<p style="margin:0;color:#047857;font-size:12px;">No cluster signals above baseline.</p>'
            : '<ul style="margin:0;padding-left:18px;font-size:13px;">' . implode('', array_map(fn($s) =>
                '<li style="margin:4px 0;"><strong>' . htmlspecialchars((string) $s->disease_code, ENT_QUOTES, 'UTF-8') . '</strong> in district <strong>' . htmlspecialchars((string) $s->district_code, ENT_QUOTES, 'UTF-8') . '</strong> — ' . (int) $s->n24 . ' cases/24h vs. ' . number_format(((int) $s->n14) / 14, 2) . '/day 14d baseline</li>', $spikes)) . '</ul>';

        $narrative = static::buildIntelNarrative($cc, count($silent), count($unsubmitted),
            $dormant->count(), $stuck->count(), $overdueFu->count(), count($spikes));

        return [
            'country_code' => $cc,
            'country_name' => match (strtoupper($cc)) {
                'UG' => 'Uganda', 'RW' => 'Rwanda', 'ZM' => 'Zambia',
                'MW' => 'Malawi', 'ST', 'STP' => 'São Tomé and Príncipe',
                default => $cc,
            },
            'now'          => now()->format('Y-m-d H:i'),
            'now_date'     => now()->format('Y-m-d'),
            'console_url'   => \App\Services\AdminLinks::dashboard(),
            'action_url'    => \App\Services\AdminLinks::dashboard(),
            'dashboard_url' => \App\Services\AdminLinks::dashboard(),
            'hub_url'       => \App\Services\AdminLinks::alertsHub(),
            'app_url'       => \App\Services\AdminLinks::base(),
            'narrative'    => $narrative,

            'silent_poes_count'       => (string) count($silent),
            'silent_poes_html'        => $silentHtml,
            'unsubmitted_poes_count'  => (string) count($unsubmitted),
            'unsubmitted_poes_html'   => $unsubmittedHtml,
            'dormant_accounts_count'  => (string) $dormant->count(),
            'dormant_accounts_html'   => $dormantHtml,
            'stuck_alerts_count'      => (string) $stuck->count(),
            'stuck_alerts_html'       => $stuckHtml,
            'overdue_followups_count' => (string) $overdueFu->count(),
            'overdue_followups_html'  => $overdueHtml,
            'case_spikes_count'       => (string) count($spikes),
            'case_spikes_html'        => $spikesHtml,
        ];
    }

    private static function buildIntelNarrative(string $cc, int $silent, int $unsub, int $dormant, int $stuck, int $overdue, int $spikes): string
    {
        $total = $silent + $unsub + $dormant + $stuck + $overdue + $spikes;
        if ($total === 0) {
            return "Country {$cc} is running clean: every active POE reported in the last 24h, every officer logged in within 14 days, no alerts are stuck past SLA, no follow-ups are overdue, and no cluster signal exceeded the 14-day baseline. Maintain posture.";
        }
        $parts = [];
        if ($silent > 0)  $parts[] = "$silent POE(s) have gone silent in the last 24h";
        if ($unsub > 0)   $parts[] = "$unsub POE(s) have offline data older than 3 days";
        if ($dormant > 0) $parts[] = "$dormant officer account(s) have not logged in for 14+ days";
        if ($stuck > 0)   $parts[] = "$stuck alert(s) are open past the acknowledgement SLA";
        if ($overdue > 0) $parts[] = "$overdue follow-up action(s) are overdue, some blocking closure";
        if ($spikes > 0)  $parts[] = "$spikes disease-by-district cluster signal(s) exceed the 14-day baseline";
        $list = count($parts) > 1
            ? implode('; ', array_slice($parts, 0, -1)) . '; and ' . end($parts)
            : $parts[0];
        return "{$cc} national surveillance brief — {$list}. Each section below is actionable today.";
    }

    /**
     * Scan alert_followups for DUE-SOON (<= 4h remaining) and OVERDUE items
     * and fan out FOLLOWUP_DUE / FOLLOWUP_OVERDUE emails.
     */
    public static function sendFollowupReminders(string $triggeredBy = 'CRON:followups'): array
    {
        return static::safely(function () use ($triggeredBy) {
            $now = now();
            $sent = 0; $skipped = 0; $failed = 0;

            // Overdue
            $overdue = DB::table('alert_followups')
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['COMPLETED', 'NOT_APPLICABLE'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', $now)
                ->get();
            foreach ($overdue as $f) {
                $alert = DB::table('alerts')->where('id', $f->alert_id)->whereNull('deleted_at')->first();
                if (! $alert) continue;
                $contacts = DB::table('poe_notification_contacts')
                    ->where('country_code', $alert->country_code)
                    ->where('receives_followup_reminders', 1)
                    ->where('is_active', 1)->whereNull('deleted_at')
                    ->get();
                $vars = CaseContextBuilder::forFollowup($f, $alert);
                foreach ($contacts as $c) {
                    $out = static::send($c, 'FOLLOWUP_OVERDUE', $vars, $alert, $triggeredBy, 'FOLLOWUP', (int) $f->id);
                    $out['status'] === 'SENT' ? $sent++ : ($out['status'] === 'SKIPPED' ? $skipped++ : $failed++);
                }
            }

            // Due soon (≤ 4h)
            $soon = DB::table('alert_followups')
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['COMPLETED', 'NOT_APPLICABLE'])
                ->whereNotNull('due_at')
                ->where('due_at', '>=', $now)
                ->where('due_at', '<=', $now->copy()->addHours(4))
                ->get();
            foreach ($soon as $f) {
                $alert = DB::table('alerts')->where('id', $f->alert_id)->whereNull('deleted_at')->first();
                if (! $alert) continue;
                $hoursRemaining = max(0, (int) round((strtotime((string) $f->due_at) - $now->timestamp) / 3600));
                $contacts = DB::table('poe_notification_contacts')
                    ->where('country_code', $alert->country_code)
                    ->where('receives_followup_reminders', 1)
                    ->where('is_active', 1)->whereNull('deleted_at')
                    ->get();
                $vars = CaseContextBuilder::forFollowup($f, $alert, [
                    'followup_due_in_hours' => (string) $hoursRemaining,
                ]);
                foreach ($contacts as $c) {
                    $out = static::send($c, 'FOLLOWUP_DUE', $vars, $alert, $triggeredBy, 'FOLLOWUP', (int) $f->id);
                    $out['status'] === 'SENT' ? $sent++ : ($out['status'] === 'SKIPPED' ? $skipped++ : $failed++);
                }
            }

            return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed,
                    'overdue_count' => $overdue->count(), 'due_soon_count' => $soon->count()];
        }, 'sendFollowupReminders');
    }

    /**
     * National Intelligence triennial digest (every 3 days).
     *
     * Runs the IntelligenceEngine for each distinct country with a
     * NATIONAL-tier subscriber, then emails the digest ONLY to
     * NATIONAL_ADMIN-tier contacts (priority_order 1) of that country —
     * the operational roster (priority 2-19) is intentionally excluded
     * to avoid spamming during a low-signal period.
     *
     * Country isolation: Uganda's digest goes only to Uganda's NATIONAL
     * contacts; future Rwanda / Zambia / Malawi / STP rosters get their
     * own digest scoped to their country.
     */
    public static function sendNationalIntelligenceDigest(string $triggeredBy = 'CRON:national-intel'): array
    {
        return static::safely(function () use ($triggeredBy) {
            $countries = DB::table('poe_notification_contacts')
                ->where('level', 'NATIONAL')
                ->where('is_active', 1)->whereNull('deleted_at')
                ->distinct()->pluck('country_code');

            $totalSent = 0; $totalSkipped = 0; $totalFailed = 0; $countriesProcessed = 0;
            foreach ($countries as $cc) {
                if (! $cc) continue;
                $vars = static::buildNationalIntelligenceVars((string) $cc);
                $countriesProcessed++;

                // Recipients — NATIONAL tier only, priority 1-3 (the
                // strategic recipients), to keep this email signal-rich.
                $contacts = DB::table('poe_notification_contacts')
                    ->where('country_code', $cc)
                    ->where('level', 'NATIONAL')
                    ->where('is_active', 1)->whereNull('deleted_at')
                    ->where('priority_order', '<=', 3)
                    ->get();

                foreach ($contacts as $c) {
                    $out = static::send(
                        $c, 'NATIONAL_INTELLIGENCE', $vars,
                        null, $triggeredBy, 'INTEL_REPORT', null,
                    );
                    if ($out['status'] === 'SENT')      $totalSent++;
                    elseif ($out['status'] === 'SKIPPED') $totalSkipped++;
                    else                                 $totalFailed++;
                }
            }
            return [
                'countries_processed' => $countriesProcessed,
                'sent'    => $totalSent,
                'skipped' => $totalSkipped,
                'failed'  => $totalFailed,
            ];
        }, 'sendNationalIntelligenceDigest');
    }

    /**
     * Retry FAILED notification_log rows that still have retry_count < 4.
     */
    public static function retryFailed(string $triggeredBy = 'CRON:retry'): array
    {
        return static::safely(function () use ($triggeredBy) {
            $rows = DB::table('notification_log')
                ->where('status', 'FAILED')
                ->where('retry_count', '<', 4)
                ->orderBy('created_at')
                ->limit(100)
                ->get();

            $retried = 0; $stillFailed = 0;
            foreach ($rows as $row) {
                if (empty($row->to_email) || empty($row->body_full)) {
                    DB::table('notification_log')->where('id', $row->id)->update([
                        'status' => 'SKIPPED',
                        'error_message' => 'Missing to_email or body for retry',
                        'updated_at' => now(),
                    ]);
                    continue;
                }
                try {
                    $subject = (string) $row->subject;
                    $body = (string) $row->body_full;
                    $textBody = static::htmlToText($body);
                    Mail::send([], [], function ($m) use ($row, $subject, $body, $textBody) {
                        $m->to($row->to_email)
                            ->subject($subject)
                            ->html($body)
                            ->text($textBody);
                        try {
                            $headers = $m->getHeaders();
                            $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
                            $headers->addTextHeader('Auto-Submitted', 'auto-generated');
                        } catch (\Throwable $he) { /* best-effort */ }
                    });
                    DB::table('notification_log')->where('id', $row->id)->update([
                        'status'        => 'SENT',
                        'sent_at'       => now(),
                        'error_message' => null,
                        'retry_count'   => (int) $row->retry_count + 1,
                        'triggered_by'  => $triggeredBy,
                        'updated_at'    => now(),
                    ]);
                    $retried++;
                } catch (Throwable $e) {
                    DB::table('notification_log')->where('id', $row->id)->update([
                        'retry_count'   => (int) $row->retry_count + 1,
                        'error_message' => mb_substr($e->getMessage(), 0, 500),
                        'updated_at'    => now(),
                    ]);
                    $stillFailed++;
                }
            }
            return ['retried' => $retried, 'still_failed' => $stillFailed, 'candidates' => $rows->count()];
        }, 'retryFailed');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  INTERNAL — resolution + rendering + send + log
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Expand a routing level into the ladder that should receive the event.
     *   DISTRICT → [DISTRICT, PHEOC, NATIONAL]
     *   PHEOC    → [PHEOC, NATIONAL]
     *   NATIONAL → [NATIONAL, WHO]
     *   WHO      → [WHO]
     */
    private static function ladderFrom(string $level): array
    {
        switch (strtoupper($level)) {
            case 'POE':      return ['POE', 'DISTRICT', 'PHEOC', 'NATIONAL'];
            case 'DISTRICT': return ['DISTRICT', 'PHEOC', 'NATIONAL'];
            case 'PHEOC':    return ['PHEOC', 'NATIONAL'];
            case 'NATIONAL': return ['NATIONAL', 'WHO'];
            case 'WHO':      return ['WHO'];
            default:         return ['DISTRICT', 'PHEOC', 'NATIONAL'];
        }
    }

    /**
     * Pull active contacts across a ladder of levels that match the alert's
     * geographic scope AND the appropriate receives_* flag.
     *
     * @param array $levels  list of levels to union across
     * @param string|null $flagOverride  specific receives_* column to require
     */
    private static function resolveContactsForAlert(object $alert, array $levels, ?string $flagOverride = null)
    {
        $risk = strtolower((string) ($alert->risk_level ?? 'high'));
        $flag = $flagOverride ?? "receives_{$risk}";
        $tierOne = is_string($alert->ihr_tier ?? null) && str_contains($alert->ihr_tier, 'TIER_1');
        $tierTwo = is_string($alert->ihr_tier ?? null) && str_contains($alert->ihr_tier, 'TIER_2');

        $q = DB::table('poe_notification_contacts')
            ->whereIn('level', $levels)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where('country_code', $alert->country_code);

        if (in_array($flag, ['receives_critical', 'receives_high', 'receives_medium', 'receives_low',
                             'receives_breach_alerts', 'receives_followup_reminders'], true)) {
            $q->where($flag, 1);
        }
        if ($tierOne) $q->where('receives_tier1', 1);
        if ($tierTwo) $q->where('receives_tier2', 1);

        return $q->orderByRaw("FIELD(level, 'POE','DISTRICT','PHEOC','NATIONAL','WHO')")
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Scope-aware contact resolution when we don't have an alert object
     * (e.g. screening referral triggers).
     */
    private static function resolveContactsByScope(?string $countryCode, ?string $districtCode, ?string $poeCode, array $levels, string $flag)
    {
        $q = DB::table('poe_notification_contacts')
            ->whereIn('level', $levels)
            ->where('is_active', 1)
            ->whereNull('deleted_at');
        if ($countryCode) $q->where('country_code', $countryCode);
        if (in_array($flag, ['receives_critical', 'receives_high', 'receives_medium', 'receives_low'], true)) {
            $q->where($flag, 1);
        }
        return $q->orderByRaw("FIELD(level, 'POE','DISTRICT','PHEOC','NATIONAL','WHO')")
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Render + send + log. Never throws.
     *
     * Anti-spam: checks notification_suppressions BEFORE send. If the same
     * (template, entity, contact) was sent within the suppression window
     * the call returns SKIPPED with reason — no SMTP traffic, no log row
     * other than the SKIPPED record so operators can audit it.
     */
    private static function send(object $contact, string $templateCode, array $vars, ?object $relatedEntity, string $triggeredBy, string $entityType = 'ALERT', ?int $entityId = null): array
    {
        try {
            $tpl = DB::table('notification_templates')
                ->where('template_code', $templateCode)
                ->where('channel', 'EMAIL')
                ->where('is_active', 1)
                ->first();
            if (! $tpl) {
                return static::log($contact, $templateCode, '(missing template)', '', 'SKIPPED',
                    "Template '$templateCode' not found", $relatedEntity, $triggeredBy, $entityType, $entityId);
            }

            $to = $contact->email ?: ($contact->alternate_email ?? null);
            if (! $to) {
                return static::log($contact, $templateCode, (string) $tpl->subject_template, '', 'SKIPPED',
                    'Contact has no email address', $relatedEntity, $triggeredBy, $entityType, $entityId);
            }

            // TEST-MODE whitelist gate. Controlled by NOTIFICATIONS_TEST_MODE +
            // NOTIFICATIONS_TEST_WHITELIST env vars. While active, any recipient
            // not in the comma-separated whitelist is dropped to SKIPPED so the
            // real national roster is never spammed during template iteration.
            if ((int) env('NOTIFICATIONS_TEST_MODE', 0) === 1) {
                $allow = array_filter(array_map('trim', array_map('strtolower',
                    explode(',', (string) env('NOTIFICATIONS_TEST_WHITELIST', '')))));
                if (! in_array(strtolower((string) $to), $allow, true)) {
                    return static::log($contact, $templateCode, (string) $tpl->subject_template, '', 'SKIPPED',
                        'TEST_MODE: recipient not in whitelist', $relatedEntity, $triggeredBy, $entityType, $entityId);
                }
            }

            // Anti-spam suppression check
            if ($entityId !== null && ($contact->id ?? null) !== null) {
                $supp = static::wasRecentlySent($templateCode, $entityType, (int) $entityId, (int) $contact->id);
                if ($supp !== null) {
                    return static::log($contact, $templateCode, (string) $tpl->subject_template, '', 'SKIPPED',
                        "Suppressed — last sent {$supp} min ago (window " . static::suppressionMinutes($templateCode) . " min)",
                        $relatedEntity, $triggeredBy, $entityType, $entityId);
                }
            }

            // Ensure the admin-panel URL bundle is available to every template.
            // CaseContextBuilder already injects these for alert-tied flows;
            // this covers the rest (digests, responder pings, etc.).
            $vars = array_merge(\App\Services\AdminLinks::generalVars(), $vars);

            $subject = static::render((string) $tpl->subject_template, $vars);
            $body    = static::render((string) $tpl->body_html_template, $vars);
            $textTpl = (string) ($tpl->body_text_template ?? '');
            $textBody = $textTpl !== ''
                ? static::render($textTpl, $vars)
                : static::htmlToText($body);

            // Append the standard "Open Command Centre" CTA if the template
            // body doesn't already link to the admin panel. The URL is the
            // most specific one available: War Room for alert-scoped emails,
            // Hub for digests and tripwires, dashboard as the fallback.
            $ctaUrl = (string) ($vars['action_url'] ?? $vars['warroom_url'] ?? $vars['hub_url'] ?? \App\Services\AdminLinks::dashboard());
            $ctaLabel = $entityType === 'ALERT' && $entityId ? 'Open War Room' : 'Open Command Centre';
            $body = \App\Services\AdminLinks::ensureCtaAppended($body, $ctaUrl, $ctaLabel);
            $textBody = $textBody . "\n\n" . $ctaLabel . ': ' . $ctaUrl;
            $replyAddr = config('mail.reply_to.address') ?: env('MAIL_REPLY_TO_ADDRESS');
            $replyName = config('mail.reply_to.name')    ?: env('MAIL_REPLY_TO_NAME', '');

            try {
                // Multipart/alternative (html + text) gives Gmail a proper
                // plain-text fallback so it stops downgrading/stripping the
                // HTML styling. Transactional headers only — no Precedence:list
                // or List-Unsubscribe (those mark the mail as bulk list traffic
                // and cause Gmail to strip inline CSS).
                Mail::send([], [], function ($m) use ($to, $subject, $body, $textBody, $replyAddr, $replyName) {
                    $m->to($to)
                        ->subject($subject)
                        ->html($body)
                        ->text($textBody);
                    if ($replyAddr) {
                        $m->replyTo($replyAddr, $replyName ?: null);
                    }
                    try {
                        $headers = $m->getHeaders();
                        $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
                        $headers->addTextHeader('Auto-Submitted', 'auto-generated');
                        $headers->addTextHeader('X-Entity-Ref-ID', 'poe-sentinel-' . bin2hex(random_bytes(8)));
                    } catch (\Throwable $he) { /* best-effort */ }
                });
                $row = static::log($contact, $templateCode, $subject, $body, 'SENT', null,
                    $relatedEntity, $triggeredBy, $entityType, $entityId);
                if ($contact->id ?? null) {
                    DB::table('poe_notification_contacts')->where('id', $contact->id)
                        ->update(['last_notified_at' => now(), 'updated_at' => now()]);
                    if ($entityId !== null) {
                        static::recordSuppression($templateCode, $entityType, (int) $entityId, (int) $contact->id);
                    }
                }
                return $row;
            } catch (Throwable $e) {
                // In dev / no-smtp, swallow + log; in prod, still log a FAILED row
                Log::info("[Mail:fallback] to={$to} subject={$subject} msg=" . $e->getMessage());
                return static::log($contact, $templateCode, $subject, $body, 'FAILED',
                    mb_substr($e->getMessage(), 0, 500),
                    $relatedEntity, $triggeredBy, $entityType, $entityId);
            }
        } catch (Throwable $e) {
            Log::error('[NotificationDispatcher::send] ' . $e->getMessage());
            return ['status' => 'FAILED', 'error' => $e->getMessage()];
        }
    }

    /**
     * Returns minutes since the last send if the (template, entity, contact)
     * triple is inside its suppression window; null if eligible to send.
     */
    private static function wasRecentlySent(string $templateCode, string $entityType, int $entityId, int $contactId): ?int
    {
        $row = DB::table('notification_suppressions')
            ->where('template_code', $templateCode)
            ->where('related_entity_type', $entityType)
            ->where('related_entity_id', $entityId)
            ->where('contact_id', $contactId)
            ->orderByDesc('last_sent_at')
            ->first();
        if (! $row) return null;
        $minutesSince = (int) round((time() - strtotime((string) $row->last_sent_at)) / 60);
        $window = static::suppressionMinutes($templateCode);
        return $minutesSince < $window ? $minutesSince : null;
    }

    private static function suppressionMinutes(string $templateCode): int
    {
        return self::SUPPRESSION_MINUTES[$templateCode] ?? self::DEFAULT_SUPPRESSION_MINUTES;
    }

    /**
     * Upsert the suppression row after a successful send.
     */
    private static function recordSuppression(string $templateCode, string $entityType, int $entityId, int $contactId): void
    {
        try {
            $now = now();
            DB::statement(
                'INSERT INTO notification_suppressions
                  (template_code, related_entity_type, related_entity_id, contact_id, last_sent_at, send_count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE last_sent_at = VALUES(last_sent_at), send_count = send_count + 1, updated_at = VALUES(updated_at)',
                [$templateCode, $entityType, $entityId, $contactId, $now, $now, $now],
            );
        } catch (Throwable $e) {
            Log::warning('[NotificationDispatcher::recordSuppression] ' . $e->getMessage());
        }
    }

    /**
     * Build the variable bag for the ALERT_CASE_FILE template. Walks the
     * full secondary case context (case row, alerts row, primary screening,
     * actions, samples, suspected diseases, exposures) and produces 12+
     * structured fields the spec requires.
     */
    private static function buildCaseFileVars(object $case, ?object $alert): array
    {
        $opener = $case->opened_by_user_id
            ? DB::table('users')->where('id', $case->opened_by_user_id)->first()
            : null;

        $actions = DB::table('secondary_actions')
            ->where('secondary_screening_id', $case->id)
            ->orderByDesc('id')->limit(8)->get();
        $actionsList = $actions->isEmpty()
            ? 'No actions logged yet.'
            : $actions->map(fn ($a) => '• ' . ($a->action_label ?? $a->action_code ?? 'action'))->implode("\n");

        $topDisease = DB::table('secondary_suspected_diseases')
            ->where('secondary_screening_id', $case->id)
            ->orderBy('rank_order')->first();
        $samplesCount = DB::table('secondary_samples')
            ->where('secondary_screening_id', $case->id)
            ->whereNull('deleted_at')->count();

        $tier = is_string($alert->ihr_tier ?? null) ? $alert->ihr_tier : 'none';
        $whyParts = [];
        if (str_contains((string) $tier, 'TIER_1')) $whyParts[] = 'IHR Tier 1 always-notifiable disease — single case requires WHO notification within 24h.';
        if (str_contains((string) $tier, 'TIER_2')) $whyParts[] = 'IHR Tier 2 — Annex 2 4-criteria assessment required (notify WHO if ≥ 2/4 met).';
        if ($topDisease) $whyParts[] = 'Top suspected: ' . (string) ($topDisease->disease_name ?? $topDisease->disease_code ?? 'unknown') . '.';
        if (($alert->risk_level ?? '') === 'CRITICAL') $whyParts[] = 'Risk level CRITICAL — full clinical response + contact tracing required.';
        if (empty($whyParts)) $whyParts[] = 'Case has reached an actionable disposition that requires stakeholder awareness and structured follow-up per IDSR.';

        $owner = (string) ($case->case_owner_role ?? $alert->routed_to_level ?? 'DISTRICT');
        $deadlineHrs = ($alert->risk_level ?? '') === 'CRITICAL' ? 4 : 24;
        $deadline = now()->addHours($deadlineHrs)->format('Y-m-d H:i') . ' UTC';

        $nextActionLabel = match (strtoupper((string) ($case->final_disposition ?? ''))) {
            'CONFIRMED_CASE'  => 'Confirm laboratory diagnosis + notify WHO IHR focal point.',
            'PROBABLE_CASE'   => 'Collect lab samples + isolate + start contact tracing.',
            'SUSPECTED_CASE'  => 'Hold for medical review + collect lab samples.',
            'REFERRED'        => 'Confirm receiving facility + transfer custody.',
            default           => 'Acknowledge in POE Sentinel + assign clinical review.',
        };

        return [
            'alert_title'       => (string) ($alert->alert_title ?? 'Secondary case requires attention'),
            'alert_code'        => (string) ($alert->alert_code ?? 'CASE-' . $case->id),
            'poe_code'          => (string) ($case->poe_code ?? ''),
            'district_code'     => (string) ($case->district_code ?? ''),
            'country_code'      => (string) ($case->country_code ?? ''),
            'opened_at'         => (string) ($case->opened_at ?? ''),
            'alert_created_at'  => (string) ($alert->created_at ?? ''),
            'risk_level'        => (string) ($alert->risk_level ?? $case->risk_level ?? 'HIGH'),
            'ihr_tier'          => $tier,
            'routed_to_level'   => (string) ($alert->routed_to_level ?? 'DISTRICT'),
            'case_status'       => (string) ($case->case_status ?? 'OPEN'),
            'final_disposition' => (string) ($case->final_disposition ?? 'PENDING'),
            'secondary_case_id'   => (string) ($case->id ?? ''),
            'secondary_case_uuid' => (string) ($case->client_uuid ?? ''),
            'traveler_label'   => trim(($case->traveler_full_name ? (string) $case->traveler_full_name : 'Anonymous')
                                     . ($case->traveler_gender ? ' · ' . (string) $case->traveler_gender : '')),
            'opened_by_name'   => $opener ? ((string) ($opener->full_name ?? $opener->username ?? ('user#' . $opener->id))) : 'Unknown',
            'owner_role'       => $owner,
            'summary_what'     => 'Secondary screening case ' . ($case->id ? '#' . $case->id : '') . ' has reached disposition '
                                  . (string) ($case->final_disposition ?? 'IN_PROGRESS') . ' at ' . (string) ($case->poe_code ?? 'unknown POE')
                                  . '. ' . ($topDisease ? 'Top suspected disease: ' . (string) ($topDisease->disease_name ?? $topDisease->disease_code) . '.' : '')
                                  . ($samplesCount > 0 ? ' ' . $samplesCount . ' lab sample(s) collected.' : ''),
            'why_it_matters'   => implode(' ', $whyParts),
            'actions_taken'    => $actionsList,
            'next_action_label' => $nextActionLabel,
            'next_action_body' => 'Coordinate with ' . $owner . '-level health authorities. Document acknowledgement + activate the relevant RTSL early-response actions in the POE Sentinel intelligence hub.',
            'next_action_owner' => $owner,
            'next_action_deadline' => $deadline,
        ];
    }

    /**
     * Render mustache tokens. Two forms:
     *   {{{html_key}}}  → inserts the value verbatim (for pre-rendered HTML
     *                      fragments produced by CaseContextBuilder).
     *   {{key}}         → HTML-escaped substitution (default, XSS-safe).
     * Triple-brace MUST be processed first so double-brace does not consume it.
     */
    private static function render(string $tpl, array $vars): string
    {
        $out = preg_replace_callback('/\{\{\{\s*([a-z0-9_]+)\s*\}\}\}/i', function ($m) use ($vars) {
            return (string) ($vars[$m[1]] ?? '');
        }, $tpl) ?? $tpl;
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars) {
            $v = $vars[$m[1]] ?? '';
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        }, $out) ?? $out;
    }

    /**
     * Cheap HTML→text fallback used when a template has no body_text_template.
     * Gmail requires a text/plain alternative in the multipart payload or it
     * downgrades the HTML and strips inline CSS.
     */
    private static function htmlToText(string $html): string
    {
        $s = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $s = preg_replace('/<br\s*\/?>/i', "\n", $s) ?? $s;
        $s = preg_replace('/<\/(p|div|tr|li|h[1-6])>/i', "\n", $s) ?? $s;
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace("/[ \t]+/", ' ', $s) ?? $s;
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
        return trim($s);
    }

    /**
     * Persist send attempt to notification_log. Returns minimal shape.
     */
    private static function log(object $contact, string $code, string $subject, string $body, string $status,
                                ?string $error, ?object $related, string $triggeredBy,
                                string $entityType, ?int $entityId): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $id = DB::table('notification_log')->insertGetId([
            'contact_id'          => $contact->id ?? null,
            'to_email'            => $contact->email ?? null,
            'to_phone'            => $contact->phone ?? null,
            'channel'             => 'EMAIL',
            'template_code'       => $code,
            'subject'             => mb_substr($subject, 0, 240),
            'body_preview'        => mb_substr(strip_tags($body), 0, 500),
            'body_full'           => $body,
            'related_entity_type' => $entityType,
            'related_entity_id'   => $entityId ?? ($related->id ?? null),
            'country_code'        => $related->country_code ?? ($contact->country_code ?? null),
            'district_code'       => $related->district_code ?? ($contact->district_code ?? null),
            'poe_code'            => $related->poe_code ?? ($contact->poe_code ?? null),
            'status'              => $status,
            'error_message'       => $error,
            'retry_count'         => 0,
            'sent_at'             => $status === 'SENT' ? $now : null,
            'failed_at'           => $status === 'FAILED' ? $now : null,
            'triggered_by'        => mb_substr($triggeredBy, 0, 40),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
        return ['log_id' => $id, 'status' => $status, 'to' => $contact->email ?? null, 'error' => $error];
    }

    /**
     * Wrap a callable in a try/catch so a failing notification never breaks
     * the caller. Every error is logged to Laravel's main log.
     */
    private static function safely(callable $fn, string $ctx): array
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            Log::error("[NotificationDispatcher::{$ctx}] " . $e->getMessage(), [
                'file' => basename($e->getFile()), 'line' => $e->getLine(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }
}
