import { createRouter, createWebHistory } from '@ionic/vue-router'

import HomePage from '../views/HomePage.vue'
import PrimaryScreening from '../views/PrimaryScreening.vue'
import POEs from '../views/POEs.vue'
import DiseaseInteligence from '../views/DiseaseInteligence.vue'

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  POE Sentinel Router — index.js                                      ║
 * ╠══════════════════════════════════════════════════════════════════════╣
 * ║  ORDERING LAW — NEVER VIOLATE:                                       ║
 * ║                                                                      ║
 * ║  SPECIFIC paths must be declared BEFORE wildcard /:param paths in    ║
 * ║  the same segment. Vue Router matches top-to-bottom.                 ║
 * ║                                                                      ║
 * ║  /secondary-screening/records   ← MUST come before                  ║
 * ║  /secondary-screening/:notificationId  ← wildcard catches "records"  ║
 * ║                                                                      ║
 * ║  Same applies to /primary-screening/dashboard and /records.          ║
 * ║                                                                      ║
 * ║  LAW 1: Navigate with server integer id for most routes.             ║
 * ║  EXCEPTION: /secondary-screening/:notificationId uses client_uuid   ║
 * ║  (the IDB primary key) — SecondaryScreening reads IDB by this key.  ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

const routes = [

  // ── Root redirects ─────────────────────────────────────────────────────────
  {
    path: '/',
    redirect: '/home',
  },
  {
    path: '/dashboard',
    redirect: '/home',
  },

  // ── Home Dashboard ─────────────────────────────────────────────────────────
  {
    path: '/home',
    name: 'Home',
    component: HomePage,
  },
  {
    path: '/DiseaseInteligence',
    name: 'DiseaseInteligence',
    component: DiseaseInteligence,
  },

  // ── Primary Screening (capture screen) ────────────────────────────────────
  {
    path: '/PrimaryScreening',
    name: 'PrimaryScreening',
    component: PrimaryScreening,
  },

  // ── Screening Intelligence Dashboard (primary + secondary analytics) ────────
  // ⚑ Specific paths declared BEFORE any wildcard in the same segment
  {
    path: '/screening-dashboard',
    name: 'ScreeningDashboard',
    component: () => import('@/views/PrimaryScreeningDashboard.vue'),
  },
  // Legacy route redirect — keep old bookmarks working
  {
    path: '/primary-screening/dashboard',
    redirect: '/screening-dashboard',
  },

  // ── Primary Screening Records (officer case register) ──────────────────────
  {
    path: '/primary-screening/records',
    name: 'PrimaryScreeningRecords',
    component: () => import('@/views/PrimaryScreeningRecords.vue'),
  },

  // ── Notifications Centre (referral queue for secondary officers) ───────────
  {
    path: '/NotificationsCenter',
    name: 'NotificationsCenter',
    component: () => import('@/views/NotificationsCenter.vue'),
  },

  // ── Secondary Screening Records ────────────────────────────────────────────
  // ⚑ MUST be declared BEFORE /secondary-screening/:notificationId
  //   Vue Router reads top-to-bottom — the string "records" matches the
  //   :notificationId wildcard if the wildcard comes first → blank page.
  {
    path: '/secondary-screening/records',
    name: 'SecondaryRecords',
    component: () => import('@/views/SecondaryRecords.vue'),
  },

  // ── Secondary Screening case view (opened from NotificationsCenter) ─────────
  {
    path: '/secondary-screening/:notificationId',
    name: 'SecondaryScreening',
    component: () => import('@/views/SecondaryScreening.vue'),
  },

  // ── Active Alerts (DISTRICT_SUPERVISOR / PHEOC_OFFICER / NATIONAL_ADMIN) ───
  // ⚑ Specific paths declared BEFORE /alerts/:id wildcards (none exist now,
  //   but keep ordering law for future expansion)
  {
    path: '/alerts/history',
    name: 'AlertHistory',
    component: () => import('@/views/AlertHistory.vue'),
  },
  {
    path: '/alerts/intelligence',
    name: 'AlertIntelligence',
    component: () => import('@/views/AlertIntelligence.vue'),
  },
  {
    path: '/alerts/matrix',
    name: 'AlertMatrix',
    component: () => import('@/views/AlertMatrix.vue'),
  },
  {
    path: '/alerts',
    name: 'ActiveAlerts',
    component: () => import('@/views/ActiveAlerts.vue'),
  },

  // ── Admin: aggregated template + POE contacts (NATIONAL_ADMIN / POE_ADMIN) ──
  {
    path: '/admin/aggregated-templates',
    name: 'AggregatedTemplateAdmin',
    component: () => import('@/views/AggregatedTemplateAdmin.vue'),
  },
  {
    path: '/admin/aggregated-wizard',
    name: 'AggregatedWizard',
    component: () => import('@/views/AggregatedWizard.vue'),
  },
  {
    path: '/admin/poe-contacts',
    name: 'PoeContactsAdmin',
    component: () => import('@/views/PoeContactsAdmin.vue'),
  },

  // ── Aggregated Data (POE users + supervisors) ─────────────────────────────
  // Landing hub at /aggregated-data lists every PUBLISHED template available.
  // /aggregated-data/new/:templateId opens the dynamic submission wizard
  // for that specific template. History keeps its own route.
  {
    path: '/aggregated-data',
    name: 'AggregatedHub',
    component: () => import('@/views/AggregatedHub.vue'),
  },
  {
    path: '/aggregated-data/history',
    name: 'AggregatedHistory',
    component: () => import('@/views/AggregatedHistory.vue'),
  },
  {
    path: '/aggregated-data/new/:templateId',
    name: 'AggregatedDataNew',
    component: () => import('@/views/AggregatedData.vue'),
  },
  // Back-compat: /aggregated-data/new (no id) + /aggregated both route to hub.
  { path: '/aggregated-data/new', redirect: '/aggregated-data' },
  { path: '/aggregated', redirect: '/aggregated-data' },

  // ── Sync Management (offline queue status + manual push) ──────────────────
  // SyncManagement handles all sync tabs internally
  {
    path: '/sync/queue',
    redirect: '/sync',
  },
  {
    path: '/sync/history',
    redirect: '/sync',
  },
  {
    path: '/sync/failed',
    redirect: '/sync',
  },
  {
    path: '/sync',
    name: 'SyncManagement',
    component: () => import('@/views/SyncManagement.vue'),
  },

  // ── System admin route — redirects to settings for now ────────────────────
  {
    path: '/admin/system',
    redirect: '/settings',
  },

  // ── POE Management ─────────────────────────────────────────────────────────
  {
    path: '/POEs',
    name: 'POEs',
    component: POEs,
  },

  // ── User Management ────────────────────────────────────────────────────────
  {
    path: '/Users',
    name: 'Users',
    component: () => import('../views/UsersList.vue'),
  },

  // ── My Profile ─────────────────────────────────────────────────────────────
  {
    path: '/profile',
    name: 'MyProfile',
    component: () => import('@/views/MyProfile.vue'),
  },

  // ── App Settings ───────────────────────────────────────────────────────────
  {
    path: '/settings',
    name: 'AppSettings',
    component: () => import('@/views/AppSettings.vue'),
  },

  // ── 404 fallback ───────────────────────────────────────────────────────────
  {
    path: '/:pathMatch(.*)*',
    redirect: '/home',
  },

]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router