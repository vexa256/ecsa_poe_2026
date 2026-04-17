import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { IonicVue } from '@ionic/vue'

/* Core CSS required for Ionic components to work properly */
import '@ionic/vue/css/core.css'

/* Basic CSS for apps built with Ionic */
import '@ionic/vue/css/normalize.css'
import '@ionic/vue/css/structure.css'
import '@ionic/vue/css/typography.css'

/* Optional CSS utils */
import '@ionic/vue/css/padding.css'
import '@ionic/vue/css/float-elements.css'
import '@ionic/vue/css/text-alignment.css'
import '@ionic/vue/css/text-transformation.css'
import '@ionic/vue/css/flex-utils.css'
import '@ionic/vue/css/display.css'

/**
 * DARK MODE — PERMANENTLY DISABLED per project requirements.
 */

/* Reference data — load order is CRITICAL ──────────────────────────────────
 *
 *  1. Diseases.js              Base scoring engine (41 diseases, scoreDiseases)
 *  2. Diseases_intelligence.js Intelligence layer — extends window.DISEASES with:
 *                                 endemic country oracle, WHO case definitions,
 *                                 syndrome classification, IHR escalation rules,
 *                                 generateClinicalReport(), getEnhancedScoreResult()
 *                              MUST load after Diseases.js — reads window.DISEASES
 *  3. exposures.js             Exposure catalog with engine-code mapping
 *                              MUST load after Diseases_intelligence.js (checks for it)
 *  4. POEs.js                  Point of Entry hierarchy reference data
 *  5. countries.js             ISO country code reference data
 *  6. poeDB.js                 IndexedDB layer — Dexie singleton, all DB operations
 *
 *  NEVER import Dexie directly in views — always go through poeDB.js.
 *  NEVER put clinical logic in Vue files — always call window.DISEASES.* functions.
 */
import '/src/Diseases.js'
import '/src/Diseases_intelligence.js'
import '/src/exposures.js'
import '/src/POEs.js'
import '/src/countries.js'
import '/src/services/poeDB.js'

/**
 * Server URL — set via .env:
 *   VITE_SERVER_URL=http://your-server/api
 * Defaults to localhost for development.
 */
window.SERVER_URL = import.meta.env.VITE_SERVER_URL || 'http://localhost:8000/api'

/* Project theme variables */
import './theme/variables.css'

import VueApexCharts from 'vue3-apexcharts'

const app = createApp(App)
  .use(IonicVue)
  .use(router)
  .use(VueApexCharts)

router.isReady().then(() => {
  app.mount('#app')
})