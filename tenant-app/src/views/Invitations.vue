<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Navigation Bar -->
    <nav class="bg-white border-b border-slate-200">
      <div class="max-w-4xl mx-auto px-4 lg:px-6">
        <div class="flex justify-between items-center h-14">
          <div class="flex-shrink-0">
            <a href="/" class="text-lg font-semibold text-slate-900">Horizontal</a>
          </div>
          <div class="flex items-center space-x-6">
            <router-link
              to="/ask"
              class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
            >
              Ask
            </router-link>
            <router-link
              to="/invitations"
              class="text-sm text-slate-900 font-medium"
            >
              Invitations
            </router-link>
            <button
              @click="logout"
              class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900">Team Invitations</h1>
        <p class="mt-2 text-slate-600">
          Invite new members to join your team and collaborate on your knowledge base
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <InvitationForm />
        <InvitationList />
      </div>

      <div class="mt-8 bg-slate-50 border border-slate-200 rounded-lg p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-sky-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-sky-800">About Invitations</h3>
            <div class="mt-2 text-sm text-sky-700">
              <ul class="list-disc list-inside space-y-1">
                <li>Invitations expire after 7 days</li>
                <li>Each email address can only have one pending invitation</li>
                <li>Users who already have an account cannot be invited again</li>
                <li>Invited users will receive an email with a secure link to join</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import InvitationForm from '@/components/invitations/InvitationForm.vue'
import InvitationList from '@/components/invitations/InvitationList.vue'
import { useAuth } from '@/composables/useAuth'

const { logout } = useAuth()
</script>
