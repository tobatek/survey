import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/features/auth/store/auth'

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
      path: '/questionnaires/:id/builder',
      name: 'questionnaires.builder',
      component: () => import('@/features/questionnaires/QuestionnaireBuilderPage.vue'),
      meta: { requiresAdmin: true },
    },
    {
      path: '/campaigns/:campaignId',
      name: 'campaigns.detail',
      component: () => import('@/features/campaigns/CampaignPage.vue'),
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
      path: '/analytics/:campaignId',
      name: 'analytics.detail',
      component: () => import('@/features/analytics/AnalyticsDetailPage.vue'),
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
      path: '/survei/mulai',
      name: 'survey.token-start',
      component: () => import('@/features/respondent/SurveyTokenStartPage.vue'),
    },
    {
      path: '/survei/isi/:sessionId',
      name: 'survey.fill',
      component: () => import('@/features/respondent/SurveyFillPage.vue'),
    },
    {
      path: '/_style-guide',
      name: 'style-guide',
      component: () => import('@/features/shared/StyleGuidePage.vue'),
    },
  ],
})

// Sesi disimpan sebagai cookie Sanctum di apps/api — satu-satunya cara SPA
// tahu status login adalah bertanya via GET /api/me (fetchMe), sekali per
// page load, sebelum navigasi pertama diselesaikan.
router.beforeEach(async (to) => {
  if (to.name === 'style-guide') return true

  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchMe()
  }

  if (to.meta.guest) {
    return true
  }

  if (to.meta.requiresAdmin) {
    if (!auth.isAuthenticated) return { name: 'login' }
    if (!auth.isAdmin) return { name: 'my-surveys' }
    return true
  }

  if (to.meta.requiresRespondent) {
    if (!auth.isAuthenticated) return { name: 'login' }
    if (!auth.isRespondent) return { name: 'questionnaires' }
    return true
  }

  return true
})

export default router
