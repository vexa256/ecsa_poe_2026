<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * AuditController
 * ─────────────────────────────────────────────────────────────────────────
 * Unified read view over every audit surface:
 *   · auth_events               — every login / 2FA / lockout / MFA change
 *   · user_audit_log            — admin mutations (CREATE/UPDATE/SUSPEND/…)
 *   · alert_timeline_events     — war-room + response events
 *   · notification_log          — every email sent/skipped/failed
 *
 *   GET /admin/audit/feed      — merged chronological feed
 *   GET /admin/audit/auth
 *   GET /admin/audit/users
 *   GET /admin/audit/alerts
 *   GET /admin/audit/notifications
 *   GET /admin/audit/stats
 */
final class AuditController extends Controller
{
    public function feed(Request $r): JsonResponse
    {
        try {
            $limit = min(500, max(10, (int) $r->query('limit', 100)));
            $since = $r->query('since');

            $auth = DB::table('auth_events as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->select(
                    DB::raw("'AUTH' as source"),
                    'a.id','a.event_type as event','a.severity',
                    'a.user_id','u.full_name','u.email',
                    'a.ip','a.user_agent','a.payload_json','a.created_at',
                );
            if ($since) $auth->where('a.created_at', '>', $since);

            $users = DB::table('user_audit_log as l')
                ->leftJoin('users as u', 'u.id', '=', 'l.target_user_id')
                ->select(
                    DB::raw("'USER_MUTATION' as source"),
                    'l.id','l.action as event',
                    DB::raw("'INFO' as severity"),
                    'l.target_user_id as user_id','u.full_name','u.email',
                    'l.ip','l.user_agent',
                    DB::raw("JSON_OBJECT('actor_user_id',l.actor_user_id,'before',l.before_json,'after',l.after_json) as payload_json"),
                    'l.created_at',
                );
            if ($since) $users->where('l.created_at', '>', $since);

            $merged = $auth->unionAll($users);
            $rows = DB::query()->fromSub($merged, 'x')
                ->orderByDesc('created_at')->limit($limit)->get();
            return response()->json(['ok' => true, 'data' => ['events' => $rows, 'count' => $rows->count()]]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function auth(Request $r): JsonResponse
    {
        $q = DB::table('auth_events');
        foreach (['event_type','severity','user_id'] as $f) if ($v = $r->query($f)) $q->where($f, $v);
        if ($r->query('since')) $q->where('created_at', '>', $r->query('since'));
        return response()->json(['ok' => true, 'data' => ['events' => $q->orderByDesc('created_at')
            ->limit((int) $r->query('limit', 200))->get()]]);
    }

    public function users(Request $r): JsonResponse
    {
        $q = DB::table('user_audit_log');
        foreach (['actor_user_id','target_user_id','action'] as $f) if ($v = $r->query($f)) $q->where($f, $v);
        return response()->json(['ok' => true, 'data' => ['events' => $q->orderByDesc('created_at')
            ->limit((int) $r->query('limit', 200))->get()]]);
    }

    public function alerts(Request $r): JsonResponse
    {
        $q = DB::table('alert_timeline_events');
        foreach (['event_category','event_code','alert_id'] as $f) if ($v = $r->query($f)) $q->where($f, $v);
        return response()->json(['ok' => true, 'data' => ['events' => $q->orderByDesc('created_at')
            ->limit((int) $r->query('limit', 200))->get()]]);
    }

    public function notifications(Request $r): JsonResponse
    {
        $q = DB::table('notification_log');
        foreach (['template_code','status','to_email'] as $f) if ($v = $r->query($f)) $q->where($f, $v);
        return response()->json(['ok' => true, 'data' => ['events' => $q->orderByDesc('created_at')
            ->limit((int) $r->query('limit', 200))->get()]]);
    }

    public function stats(): JsonResponse
    {
        try {
            $since = now()->subDays(7);
            return response()->json(['ok' => true, 'data' => [
                'last_7d' => [
                    'auth_events' => DB::table('auth_events')->where('created_at','>=',$since)->count(),
                    'login_ok'    => DB::table('auth_events')->where('event_type','LOGIN_OK')->where('created_at','>=',$since)->count(),
                    'login_fail'  => DB::table('auth_events')->where('event_type','LOGIN_FAIL')->where('created_at','>=',$since)->count(),
                    'lockouts'    => DB::table('auth_events')->where('event_type','LOCKED')->where('created_at','>=',$since)->count(),
                    'user_mutations' => DB::table('user_audit_log')->where('created_at','>=',$since)->count(),
                    'alert_events'   => DB::table('alert_timeline_events')->where('created_at','>=',$since)->count(),
                    'emails_sent' => DB::table('notification_log')->where('status','SENT')->where('created_at','>=',$since)->count(),
                ],
                'by_event_7d' => DB::table('auth_events')->where('created_at','>=',$since)
                    ->selectRaw('event_type, COUNT(*) AS n')->groupBy('event_type')->orderByDesc('n')->get(),
                'by_severity_7d' => DB::table('auth_events')->where('created_at','>=',$since)
                    ->selectRaw('severity, COUNT(*) AS n')->groupBy('severity')->get(),
            ]]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
