<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-500 via-purple-600 to-blue-500 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md text-center">
      <div v-if="error" class="mb-6">
        <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Authentication Failed</h2>
        <p class="text-gray-600 mb-6">{{ error }}</p>
        <button
          @click="redirectToAuth"
          class="px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg font-medium hover:from-purple-700 hover:to-blue-700 transition-colors"
        >
          Try Again
        </button>
      </div>

      <div v-else>
        <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-600 mx-auto mb-4"></div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Completing Sign In</h2>
        <p class="text-gray-600">Please wait while we log you in...</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { setToken } = useAuth()
const error = ref(null)

const redirectToAuth = () => {
  router.push('/auth')
}

onMounted(async () => {
  try {
    // Get the token from the URL query parameter
    const urlParams = new URLSearchParams(window.location.search)
    const token = urlParams.get('token')

    if (!token) {
      error.value = 'Authentication failed. Please try logging in again.'
      return
    }

    // Store the token
    setToken(token)

    // Clear the token from the URL for security
    window.history.replaceState({}, document.title, '/after-login')

    // Check if user needs onboarding
    const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'
    try {
      const response = await fetch(`${baseUrl}/api/integrations`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        }
      })

      if (response.ok) {
        const integrations = await response.json()
        const hasAnyIntegration = Object.values(integrations).some(category =>
          category.some(integration => integration.connected)
        )

        // Redirect to onboarding if no integrations are connected
        if (!hasAnyIntegration) {
          setTimeout(() => {
            router.push('/onboarding')
          }, 500)
          return
        }
      }
    } catch (err) {
      console.error('Error checking integrations:', err)
      // Continue to /ask even if integration check fails
    }

    // Redirect to the ask page
    setTimeout(() => {
      router.push('/ask')
    }, 500)
  } catch (err) {
    console.error('Error processing authentication:', err)
    error.value = 'An error occurred while processing your login. Please try again.'
  }
})
</script>
