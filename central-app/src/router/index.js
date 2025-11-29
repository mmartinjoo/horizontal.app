import { createRouter, createWebHistory } from 'vue-router'
import Landing from '../views/Landing.vue'
import SignUp from '../views/SignUp.vue'
import RegistrationClosed from '../views/RegistrationClosed.vue'
import { fetchFeatures, isRegistrationOpen } from '../services/features'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: Landing
    },
    {
      path: '/signup',
      name: 'signup',
      component: SignUp,
      beforeEnter: async (to, from, next) => {
        await fetchFeatures()
        if (isRegistrationOpen()) {
          next()
        } else {
          next('/registration-closed')
        }
      }
    },
    {
      path: '/registration-closed',
      name: 'registration-closed',
      component: RegistrationClosed
    }
  ]
})

export default router
