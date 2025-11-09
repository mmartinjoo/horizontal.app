<template>
  <OnboardingLayout>
    <div class="mx-auto max-w-5xl">
      <div class="text-center">
        <h2 class="text-2xl font-bold text-slate-900">Connect Task Management Tools</h2>
        <p class="mt-2 text-slate-600">
          Connect your project management tools to search issues and tasks
        </p>
      </div>

      <div v-if="loading" class="mt-12 flex justify-center">
        <svg
          class="h-12 w-12 animate-spin text-slate-400"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
      </div>

      <div v-else class="mt-12 grid gap-6 md:grid-cols-2">
        <IntegrationCard
          v-for="integration in taskManagementIntegrations"
          :key="integration.provider"
          :integration="integration"
          :loading="connectingProvider === integration.provider"
          @connect="handleConnect"
          @configure="handleConfigure"
          @disconnect="handleDisconnect"
        />
      </div>
    </div>
  </OnboardingLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useOnboarding } from '@/composables/useOnboarding'
import { useAuth } from '@/composables/useAuth'
import OnboardingLayout from './OnboardingLayout.vue'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import { useRouter } from 'vue-router'

const { integrations, fetchIntegrations } = useOnboarding()
const { getAuthHeaders } = useAuth()
const router = useRouter()

const loading = ref(true)
const connectingProvider = ref(null)
const taskManagementIntegrations = ref([])

onMounted(async () => {
  try {
    await fetchIntegrations()
    taskManagementIntegrations.value = integrations.value.task_management || []
  } catch (error) {
    console.error('Failed to load integrations:', error)
  } finally {
    loading.value = false
  }
})

const handleConnect = async (provider, extraData) => {
  try {
    console.log(extraData);
    connectingProvider.value = provider

    const response = await fetch(`/api/integrations/${provider}/oauth/authorize`, {
      method: 'POST',
      headers: getAuthHeaders(),
      body: JSON.stringify(extraData),
    })

    if (!response.ok) {
      throw new Error('Failed to initiate OAuth')
    }

    const data = await response.json()
    window.location.href = data.authorization_url
  } catch (error) {
    console.error('Failed to connect:', error)
    connectingProvider.value = null
  }
}

const handleConfigure = (provider) => {
  router.push({ 
    name: 'onboarding-callback',
    query: {
      provider: provider,
      step: 'task-management',
    },
  })
}

const handleDisconnect = async (provider) => {
  if (!confirm('Are you sure you want to disconnect this integration?')) {
    return
  }

  try {
    const response = await fetch(`/api/integrations/${provider}/oauth/disconnect`, {
      method: 'DELETE',
      headers: getAuthHeaders(),
    })

    if (!response.ok) {
      throw new Error('Failed to disconnect')
    }

    await fetchIntegrations()
    taskManagementIntegrations.value = integrations.value.task_management || []
  } catch (error) {
    console.error('Failed to disconnect:', error)
  }
}
</script>
