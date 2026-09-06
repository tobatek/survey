import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/features/auth/store/auth'

// Guard router: admin vs responden vs unauthenticated (FSD §6). Auth store
// masih stub Fase 0 — pengecekan sesi nyata via GET /api/me dibangun di Fase 1.
const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/questionnaires',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/features/auth/LoginRedirectPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/questionnaires',
      name: 'questionnaires',
      component: () => import('@/features/questionnaires/QuestionnaireListPage.vue'),
      meta: { requiresAdmin: true },
    },
    {
      path: '/students',
      name: 'students',
      component: () => import('@/features/students/StudentListPage.vue'),
      meta: { requiresAdmin: true },
    },
    {
      path: '/employees',
      name: 'employees',
      component: () => import('@/features/employees/EmployeeListPage.vue'),
      meta: { requiresAdmin: true },
    },
    {
      path: '/analytics',
      name: 'analytics',
      component: () => import('@/features/analytics/AnalyticsListPage.vue'),
      meta: { requiresAdmin: true },
    },
    {
      path: '/settings/templates',
      name: 'settings.templates',
      component: () => import('@/features/settings/TemplateImportPage.vue'),
      meta: { requiresAdmin: true },
    },
    {
      path: '/my-surveys',
      name: 'my-surveys',
      component: () => import('@/features/respondent/MySurveysPage.vue'),
      meta: { requiresRespondent: true },
    },
    {
      path: '/_style-guide',
      name: 'style-guide',
      component: () => import('@/features/shared/StyleGuidePage.vue'),
    },
  ],
})

router.beforeEach((to) => {
  // Fase 0: guard belum menegakkan redirect nyata (auth store selalu kosong),
  // hanya menyiapkan kerangka meta.requiresAdmin/requiresRespondent/guest
  // supaya Fase 1 tinggal mengisi pengecekan session.
  const auth = useAuthStore()

  if (to.meta.requiresAdmin && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.requiresRespondent && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  return true
})

export default router
