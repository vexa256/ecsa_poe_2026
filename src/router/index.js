import { createRouter, createWebHistory } from '@ionic/vue-router'

import HomePage from '../views/HomePage.vue'
import PrimaryScreening from '../views/PrimaryScreening.vue'
import POEs from '../views/POEs.vue'

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
 * ║  LAW 1: Navigate with server integer id ONLY — never client_uuid.    ║
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

  // ── Primary Screening (capture screen) ────────────────────────────────────
  {
    path: '/PrimaryScreening',
    name: 'PrimaryScreening',
    component: PrimaryScreening,
  },

  // ── Primary Screening Dashboard (analytics) ────────────────────────────────
  // ⚑ Specific paths declared BEFORE any wildcard in the same segment
  {
    path: '/primary-screening/dashboard',
    name: 'PrimaryScreeningDashboard',
    component: () => import('@/views/PrimaryScreeningDashboard.vue'),
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
  {
    path: '/alerts',
    name: 'ActiveAlerts',
    component: () => import('@/views/ActiveAlerts.vue'),
  },

  // ── Aggregated Data Submission (POE_DATA_OFFICER / POE_ADMIN) ─────────────
  {
    path: '/aggregated',
    name: 'AggregatedData',
    component: () => import('@/views/AggregatedData.vue'),
  },

  // ── Sync Management (offline queue status + manual push) ──────────────────
  {
    path: '/sync',
    name: 'SyncManagement',
    component: () => import('@/views/SyncManagement.vue'),
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