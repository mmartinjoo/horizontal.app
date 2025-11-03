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
    }
  ],
})

// Navigation guard to protect authenticated routes
router.beforeEach((to, from, next) => {
  const { isAuthenticated } = useAuth()

  // Routes that require authentication
  const protectedRoutes = ['ask']

  if (protectedRoutes.includes(to.name) && !isAuthenticated.value) {
    // Redirect to auth page if not authenticated
    next({ name: 'auth' })
  } else {
    next()
  }
})

export default router
