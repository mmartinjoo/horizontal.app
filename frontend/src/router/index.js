import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../views/Home.vue')
    },
    {
      path: '/ask',
      name: 'ask',
      component: () => import('../views/Ask.vue')
    },
    {
      path: '/auth',
      name: 'auth',
      component: () => import('../views/Auth.vue')
    },
    {
      path: '/after-login',
      name: 'after-login',
      component: () => import('../views/AfterLogin.vue')
    },
    // Onboarding routes
    {
      path: '/onboarding',
      redirect: '/onboarding/welcome'
    },
    {
      path: '/onboarding/welcome',
      name: 'onboarding-welcome',
      component: () => import('../views/onboarding/OnboardingWelcome.vue'),
      meta: { requiresAuth: true, onboarding: true }
    },
    {
      path: '/onboarding/communication',
      name: 'onboarding-communication',
      component: () => import('../views/onboarding/OnboardingCommunication.vue'),
      meta: { requiresAuth: true, onboarding: true }
    },
    {
      path: '/onboarding/task_management',
      name: 'onboarding-task-management',
      component: () => import('../views/onboarding/OnboardingTaskManagement.vue'),
      meta: { requiresAuth: true, onboarding: true }
    },
    {
      path: '/onboarding/storage',
      name: 'onboarding-storage',
      component: () => import('../views/onboarding/OnboardingStorage.vue'),
      meta: { requiresAuth: true, onboarding: true }
    },
    {
      path: '/onboarding/code_repository',
      name: 'onboarding-code-repository',
      component: () => import('../views/onboarding/OnboardingCodeRepository.vue'),
      meta: { requiresAuth: true, onboarding: true }
    },
    {
      path: '/onboarding/callback',
      name: 'onboarding-callback',
      component: () => import('../views/onboarding/OnboardingCallback.vue'),
      meta: { requiresAuth: true, onboarding: true }
    },
    {
      path: '/onboarding/complete',
      name: 'onboarding-complete',
      component: () => import('../views/onboarding/OnboardingComplete.vue'),
      meta: { requiresAuth: true, onboarding: true }
    }
  ],
})

// Navigation guard to protect authenticated routes and check onboarding
router.beforeEach(async (to, from, next) => {
  const { isAuthenticated } = useAuth()

  // Check if route requires authentication
  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return next({ name: 'auth' })
  }

  // Routes that require authentication (backwards compatibility)
  const protectedRoutes = ['ask']
  if (protectedRoutes.includes(to.name) && !isAuthenticated.value) {
    return next({ name: 'auth' })
  }

  // TODO: Add onboarding check here
  // If user is authenticated and trying to access /ask but hasn't completed onboarding
  // redirect them to /onboarding
  // This will be implemented after adding backend endpoint to check onboarding status

  next()
})

export default router
