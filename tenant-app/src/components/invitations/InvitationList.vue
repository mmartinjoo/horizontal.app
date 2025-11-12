<template>
  <div class="bg-white rounded-lg border border-slate-200 p-6">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Recent Invitations</h2>

    <div v-if="isLoading" class="flex items-center justify-center py-8">
      <svg class="animate-spin h-8 w-8 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div v-else-if="invitations.length === 0" class="text-center py-8">
      <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
      </svg>
      <p class="mt-4 text-sm text-slate-600">No invitations sent yet</p>
      <p class="mt-1 text-xs text-slate-500">Invitations you send will appear here</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="invitation in invitations"
        :key="invitation.id"
        class="flex items-center justify-between p-4 border border-slate-200 rounded-lg hover:border-slate-300 transition-colors"
      >
        <div class="flex-1">
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <div class="h-10 w-10 rounded-full bg-sky-100 flex items-center justify-center">
                <svg class="h-5 w-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-900">{{ invitation.email }}</p>
              <p class="text-xs text-slate-500">
                Expires {{ formatExpiresAt(invitation.expires_at) }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex-shrink-0">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
            Pending
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useInvitations } from '@/composables/useInvitations'

const { invitations, isLoading } = useInvitations()

function formatExpiresAt(expiresAt) {
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
</script>
