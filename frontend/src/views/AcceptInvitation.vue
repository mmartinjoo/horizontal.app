<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm p-8 w-full max-w-md">
      <!-- Loading State -->
      <div v-if="isValidating" class="text-center py-8">
        <svg class="animate-spin h-8 w-8 text-sky-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-slate-600">Validating invitation...</p>
      </div>

      <!-- Valid Invitation -->
      <div v-else-if="invitationValid">
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"/>
            </svg>
          </div>
          <h1 class="text-2xl font-semibold text-slate-900 mb-2">
            You've been invited
          </h1>
          <p class="text-slate-500 text-xs mt-2">
            Invitation expires {{ formatExpiresAt(invitation.expires_at) }}
          </p>
        </div>

        <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-100 rounded-lg">
          <p class="text-red-700 text-sm">{{ error }}</p>
        </div>

        <div class="space-y-3">
          <button
            @click="handleAccept('github')"
            :disabled="isAccepting"
            class="w-full flex items-center justify-center gap-3 px-4 py-2.5 bg-slate-900 text-white rounded-md text-sm font-medium hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="!isAccepting || acceptingProvider !== 'github'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
            </svg>
            <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ isAccepting && acceptingProvider === 'github' ? 'Redirecting...' : 'Accept with GitHub' }}</span>
          </button>

          <button
            @click="handleAccept('google')"
            :disabled="isAccepting"
            class="w-full flex items-center justify-center gap-3 px-4 py-2.5 bg-white text-slate-900 border border-slate-300 rounded-md text-sm font-medium hover:bg-slate-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="!isAccepting || acceptingProvider !== 'google'" class="w-5 h-5" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <svg v-else class="w-5 h-5 animate-spin text-slate-900" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ isAccepting && acceptingProvider === 'google' ? 'Redirecting...' : 'Accept with Google' }}</span>
          </button>
        </div>

        <p class="text-center text-slate-500 text-xs mt-6">
          By accepting, you agree to our Terms of Service and Privacy Policy.
        </p>
      </div>

      <!-- Expired Invitation -->
      <div v-else-if="invitationExpired" class="text-center py-4">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900 mb-2">
          Invitation Expired
        </h1>
        <p class="text-slate-600 text-sm mb-6">
          This invitation has expired. Please request a new invitation from your team administrator.
        </p>
        <a
          href="/auth"
          class="inline-block px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700 transition-colors"
        >
          Go to Login
        </a>
      </div>

      <!-- Already Accepted -->
      <div v-else-if="invitationAccepted" class="text-center py-4">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900 mb-2">
          Already Accepted
        </h1>
        <p class="text-slate-600 text-sm mb-6">
          This invitation has already been accepted. You can log in to your account.
        </p>
        <a
          href="/auth"
          class="inline-block px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700 transition-colors"
        >
          Go to Login
        </a>
      </div>

      <!-- Invalid/Not Found -->
      <div v-else-if="invitationNotFound" class="text-center py-4">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900 mb-2">
          Invalid Invitation
        </h1>
        <p class="text-slate-600 text-sm mb-6">
          This invitation link is invalid or has been removed. Please contact your team administrator for assistance.
        </p>
        <a
          href="/auth"
          class="inline-block px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700 transition-colors"
        >
          Go to Login
        </a>
      </div>

      <!-- Network Error -->
      <div v-else-if="networkError" class="text-center py-4">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900 mb-2">
          Connection Error
        </h1>
        <p class="text-slate-600 text-sm mb-6">
          Unable to validate invitation. Please check your connection and try again.
        </p>
        <button
          @click="validateInvitation"
          class="inline-block px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700 transition-colors"
        >
          Try Again
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const route = useRoute()
const { redirectToOAuth } = useAuth()

const token = ref(null)
const invitation = ref(null)
const isValidating = ref(true)
const isAccepting = ref(false)
const acceptingProvider = ref(null)
const error = ref(null)

// States
const invitationValid = ref(false)
const invitationExpired = ref(false)
const invitationAccepted = ref(false)
const invitationNotFound = ref(false)
const networkError = ref(false)

const validateInvitation = async () => {
  isValidating.value = true
  invitationValid.value = false
  invitationExpired.value = false
  invitationAccepted.value = false
  invitationNotFound.value = false
  networkError.value = false
  error.value = null

  try {
    const response = await fetch(`/api/invitations/${token.value}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    if (response.ok) {
      const data = await response.json()
      invitation.value = data.invitation
      invitationValid.value = true
    } else if (response.status === 404) {
      invitationNotFound.value = true
    } else if (response.status === 410) {
      // 410 Gone - expired or accepted
      const data = await response.json()
      if (data.message.includes('expired')) {
        invitationExpired.value = true
      } else {
        invitationAccepted.value = true
      }
    } else {
      throw new Error('Failed to validate invitation')
    }
  } catch (err) {
    console.error('Invitation validation error:', err)
    networkError.value = true
  } finally {
    isValidating.value = false
  }
}

const handleAccept = async (provider) => {
  isAccepting.value = true
  acceptingProvider.value = provider
  error.value = null

  try {
    await redirectToOAuth(provider, token.value)
  } catch (err) {
    console.error('Accept invitation error:', err)
    error.value = err.message || `Failed to accept invitation with ${provider}. Please try again.`
    isAccepting.value = false
    acceptingProvider.value = null
  }
}

const formatExpiresAt = (expiresAt) => {
  const date = new Date(expiresAt)
  const now = new Date()
  const diffMs = date - now
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))

  if (diffDays > 1) {
    return `in ${diffDays} days`
  } else if (diffDays === 1) {
    return 'in 1 day'
  } else if (diffHours > 1) {
    return `in ${diffHours} hours`
  } else if (diffHours === 1) {
    return 'in 1 hour'
  } else if (diffMs > 0) {
    return 'soon'
  } else {
    return 'expired'
  }
}

onMounted(() => {
  token.value = route.query.token

  if (!token.value) {
    invitationNotFound.value = true
    isValidating.value = false
    return
  }

  validateInvitation()
})
</script>
