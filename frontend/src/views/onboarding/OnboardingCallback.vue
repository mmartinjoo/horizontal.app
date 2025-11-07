<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="max-w-md w-full">
      <div v-if="error" class="rounded-lg bg-red-50 p-6">
        <div class="flex">
          <svg
            class="h-6 w-6 text-red-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">OAuth Error</h3>
            <p class="mt-2 text-sm text-red-700">{{ error }}</p>
            <button
              @click="returnToOnboarding"
              class="mt-4 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            >
              Return to Onboarding
            </button>
          </div>
        </div>
      </div>

      <!-- Configuration Modal -->
      <IntegrationConfigModal
        :open="showConfigModal"
        :integration-name="integrationName"
        :description="`Select which ${resourceType} you want to index`"
        :loading="saving"
        @close="handleCloseModal"
        @save="handleSaveConfiguration"
      >
        <template #content>
          <ResourceSelector
            v-model="selectedResources"
            :resources="availableResources"
            :loading="loadingResources"
            :provider="provider"
          />
        </template>
      </IntegrationConfigModal>

      <div v-if="!error && !showConfigModal" class="rounded-lg bg-white p-6 shadow-sm">
        <div class="flex justify-center">
          <svg
            class="h-12 w-12 animate-spin text-indigo-600"
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
        <p class="mt-4 text-center text-gray-600">Processing OAuth callback...</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import IntegrationConfigModal from '@/components/integrations/IntegrationConfigModal.vue'
import ResourceSelector from '@/components/integrations/ResourceSelector.vue'

const router = useRouter()
const route = useRoute()
const { getAuthHeaders } = useAuth()

const error = ref(null)
const showConfigModal = ref(false)
const provider = ref(null)
const integrationName = ref('')
const resourceType = ref('resources')
const availableResources = ref([])
const selectedResources = ref([])
const loadingResources = ref(false)
const saving = ref(false)
const returnStep = ref('communication')

const PROVIDER_NAMES = {
  slack: 'Slack',
  google_chat: 'Google Chat',
  google_drive: 'Google Drive',
  linear: 'Linear',
  jira: 'Jira',
  github: 'GitHub',
}

const RESOURCE_TYPES = {
  slack: 'channels',
  google_chat: 'spaces',
  google_drive: 'folders',
  linear: 'projects',
  jira: 'projects',
  github: 'repositories',
}

onMounted(async () => {
  provider.value = route.query.provider
  returnStep.value = route.query.step

  if (!provider.value) {
    error.value = 'Missing integration provider information'
    return
  }

  integrationName.value = PROVIDER_NAMES[provider.value] || provider.value
  resourceType.value = RESOURCE_TYPES[provider.value] || 'resources'

  // Check for OAuth errors
  if (route.query.error) {
    error.value = route.query.error_description || route.query.error
    return
  }

  try {
    // Check integration status
    const statusResponse = await fetch(
      `/api/integrations/${provider.value}/oauth/status`,
      {
        headers: getAuthHeaders(),
      }
    )

    if (!statusResponse.ok) {
      throw new Error('Failed to check integration status')
    }

    const statusData = await statusResponse.json()

    if (!statusData.connected) {
      throw new Error('Integration not connected')
    }

    // Fetch available resources
    await fetchResources()

    // Show configuration modal
    showConfigModal.value = true
  } catch (err) {
    error.value = err.message
  }
})

const fetchResources = async () => {
  loadingResources.value = true
  try {
    const response = await fetch(
      `/api/integrations/${provider.value}/resources`,
      {
        headers: getAuthHeaders(),
      }
    )

    if (!response.ok) {
      throw new Error('Failed to fetch resources')
    }

    const data = await response.json()
    availableResources.value = data.resources || []

    // Select all by default
    selectedResources.value = availableResources.value.map((r) => r.id)
  } catch (err) {
    console.error('Failed to fetch resources:', err)
    error.value = err.message
  } finally {
    loadingResources.value = false
  }
}

const handleSaveConfiguration = async () => {
  saving.value = true
  try {
    const response = await fetch(
      `/api/integrations/${provider.value}/configure`,
      {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          selected_resources: selectedResources.value,
        }),
      }
    )

    if (!response.ok) {
      throw new Error('Failed to save configuration')
    }

    // Redirect back to onboarding step
    router.push(`/onboarding/${returnStep.value}`)
  } catch (err) {
    console.error('Failed to save configuration:', err)
    error.value = err.message
  } finally {
    saving.value = false
  }
}

const handleCloseModal = () => {
  // Return to onboarding step
  router.push(`/onboarding/${returnStep.value}`)
}

const returnToOnboarding = () => {
  router.push(`/onboarding/${returnStep.value}`)
}
</script>
