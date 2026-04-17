import { createRouter, createWebHistory } from '@ionic/vue-router'

import HomePage from '../views/HomePage.vue'
import PrimaryScreening from '../views/PrimaryScreening.vue'
import POEs from '../views/POEs.vue'

const routes = [
  {
    path: '/',
    redirect: '/home',
  },
  {
    path: '/dashboard',
    redirect: '/home',
  },
  // ── Active Alerts (supervisors — DISTRICT / PHEOC / NATIONAL) ─────────────
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

  // ── Sync Management (IDB-only — offline queue status + manual push) ────────
  {
    path: '/sync',
    name: 'SyncManagement',
    component: () => import('@/views/SyncManagement.vue'),
  },

  // ── Core Dashboard ─────────────────────────────────────────────────────────
  {
    path: '/home',
    name: 'Home',
    component: HomePage,
  },

  // ── Primary Screening ──────────────────────────────────────────────────────
  {
    path: '/PrimaryScreening',
    name: 'PrimaryScreening',
    component: PrimaryScreening,
  },

  { path: '/NotificationsCenter', component: () => import('@/views/NotificationsCenter.vue') },


  // ── Secondary Screening ────────────────────────────────────────────────────
  // {
  //   path: '/SecondaryScreening/:notifId',
  //   name: 'SecondaryScreening',
  //   component: () => import('../views/SecondaryScreening.vue'),
  //   props: true,
  // },


  // ── POEs ───────────────────────────────────────────────────────────────────
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

  {
    path: '/secondary-screening/records',
    name: 'SecondaryRecords',
    component: () => import('@/views/SecondaryRecords.vue'),
  },
  {
    path: '/primary-screening/dashboard',
    name: 'PrimaryScreeningDashboard',
    component: () => import('@/views/PrimaryScreeningDashboard.vue'),
  },
  {
    path: '/primary-screening/records',
    name: 'PrimaryScreeningRecords',
    component: () => import('@/views/PrimaryScreeningRecords.vue'),
  },

  {
    path: '/secondary-screening/:notificationId',
    name: 'SecondaryScreening',
    component: () => import('@/views/SecondaryScreening.vue'),
  },


]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router