<template>
  <div class="bg-white rounded-lg border border-slate-200 p-6">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Invite Team Members</h2>
    <p class="text-sm text-slate-600 mb-6">
      Send an invitation to a new team member. They'll receive an email with instructions to join.
    </p>

    <form @submit.prevent="handleSubmit" class="space-y-4">
      <div>
        <label for="email" class="block text-sm font-medium text-slate-700 mb-2">
          Email Address
        </label>
        <input
          id="email"
          v-model="email"
          type="email"
          required
          placeholder="colleague@example.com"
          class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
          :disabled="isLoading"
        />
      </div>

      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        {{ error }}
      </div>

      <div v-if="successMessage" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
        {{ successMessage }}
      </div>

      <button
        type="submit"
        :disabled="isLoading || !email"
        class="w-full bg-sky-600 hover:bg-sky-700 text-white font-medium py-2.5 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
      >
        <svg v-if="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        {{ isLoading ? 'Sending Invitation...' : 'Send Invitation' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useInvitations } from '@/composables/useInvitations'

const { sendInvitation, isLoading, error, clearError } = useInvitations()

const email = ref('')
const successMessage = ref('')

const handleSubmit = async () => {
  clearError()
  successMessage.value = ''

  try {
    await sendInvitation(email.value)
    successMessage.value = `Invitation sent successfully to ${email.value}`
    email.value = ''

    // Clear success message after 5 seconds
    setTimeout(() => {
      successMessage.value = ''
    }, 5000)
  } catch (err) {
    // Error is already set in the composable
  }
}
</script>
