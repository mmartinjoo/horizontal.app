<template>
  <div class="min-h-screen bg-slate-50 px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl">
      <!-- Header -->
      <div class="text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-sky-50 border border-sky-200">
          <svg
            class="h-8 w-8 text-sky-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
        </div>
        <h1 class="mt-6 text-2xl font-bold text-slate-900">Indexing Status</h1>
        <p class="mt-2 text-sm text-slate-600">
          Track the progress of your knowledge base indexing workflow
        </p>
      </div>

      <!-- Loading State -->
      <div v-if="loading && !workflowStatus" class="mt-12 text-center">
        <div class="inline-flex items-center space-x-2 text-slate-600">
          <svg
            class="h-5 w-5 animate-spin"
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
          <span>Loading status...</span>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="mt-12">
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg
                class="h-5 w-5 text-red-400"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">Error loading status</h3>
              <p class="mt-1 text-sm text-red-700">{{ error }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Workflow Status -->
      <div v-else-if="workflowStatus" class="mt-12 space-y-6">
        <!-- Overall Status Card -->
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Workflow Status</h2>
              <p class="mt-1 text-sm text-slate-600">
                Started at {{ formatDate(workflowStatus.started_at) }}
              </p>
            </div>
            <span
              class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
              :class="getStatusClasses(workflowStatus.status)"
            >
              <span
                class="mr-2 h-2 w-2 rounded-full"
                :class="getStatusDotClasses(workflowStatus.status)"
              ></span>
              {{ formatStatus(workflowStatus.status) }}
            </span>
          </div>
        </div>

        <!-- Steps -->
        <div class="space-y-4">
          <h3 class="text-sm font-medium text-slate-700">Progress</h3>

          <div
            v-for="(step, index) in workflowStatus.steps"
            :key="step.name"
            class="rounded-lg border bg-white shadow-sm transition-all"
            :class="getStepBorderClasses(step.status)"
          >
            <div class="p-6">
              <div class="flex items-start justify-between">
                <div class="flex items-start space-x-4 flex-1">
                  <!-- Step Number/Icon -->
                  <div class="flex-shrink-0">
                    <div
                      class="flex h-10 w-10 items-center justify-center rounded-full border-2"
                      :class="getStepIconClasses(step.status)"
                    >
                      <!-- Completed Icon -->
                      <svg
                        v-if="step.status === 'completed'"
                        class="h-5 w-5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      <!-- Processing Icon -->
                      <svg
                        v-else-if="step.status === 'processing' || step.status === 'ready_for_next_step'"
                        class="h-5 w-5 animate-spin"
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
                      <!-- Warning Icon -->
                      <svg
                        v-else-if="step.status === 'completed_with_errors'"
                        class="h-5 w-5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                          clip-rule="evenodd"
                        />
                      </svg>
                      <!-- Starting/Default Icon -->
                      <span v-else class="text-sm font-semibold">
                        {{ index + 1 }}
                      </span>
                    </div>
                  </div>

                  <!-- Step Details -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                      <h4 class="text-base font-semibold text-slate-900">
                        {{ step.display_name }}
                      </h4>
                      <span
                        class="ml-4 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="getStatusClasses(step.status)"
                      >
                        <span
                          class="mr-1.5 h-1.5 w-1.5 rounded-full"
                          :class="getStatusDotClasses(step.status)"
                        ></span>
                        {{ formatStatus(step.status) }}
                      </span>
                    </div>

                    <!-- Progress Bar -->
                    <div v-if="step.overall_items > 0" class="mt-4">
                      <div class="flex items-center justify-between text-sm text-slate-600 mb-2">
                        <span>{{ step.processed_items }} of {{ step.overall_items }} items</span>
                        <span class="font-medium">{{ getProgressPercentage(step) }}%</span>
                      </div>
                      <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                        <div
                          class="h-full rounded-full transition-all duration-500"
                          :class="getProgressBarClasses(step.status)"
                          :style="{ width: `${getProgressPercentage(step)}%` }"
                        ></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Auto-refresh indicator -->
        <div v-if="isWorkflowInProgress" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm text-slate-700 flex items-center">
            <svg
              class="mr-2 h-4 w-4 animate-spin text-slate-600"
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
            <strong class="text-slate-900">Live updates:</strong>&nbsp;This page refreshes automatically every 5 seconds while indexing is in progress.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useAuth } from '../composables/useAuth'

const { getAuthHeaders } = useAuth()

const workflowStatus = ref(null)
const loading = ref(true)
const error = ref(null)
let refreshInterval = null

// Check if workflow is in progress
const isWorkflowInProgress = computed(() => {
  if (!workflowStatus.value) return false
  const inProgressStatuses = ['processing', 'starting', 'ready_for_next_step']

  // Check overall status
  if (inProgressStatuses.includes(workflowStatus.value.status)) return true

  // Check if any step is in progress
  return workflowStatus.value.steps.some(step =>
    inProgressStatuses.includes(step.status)
  )
})

// Fetch workflow status from API
const fetchStatus = async () => {
  try {
    loading.value = true
    error.value = null

    const response = await fetch('/api/indexing-workflow/status', {
      headers: getAuthHeaders()
    })

    if (!response.ok) {
      throw new Error(`Failed to fetch status: ${response.statusText}`)
    }

    workflowStatus.value = await response.json()
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

// Format date
const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Format status text
const formatStatus = (status) => {
  const statusMap = {
    'starting': 'Starting',
    'processing': 'Processing',
    'completed': 'Completed',
    'completed_with_errors': 'Completed with errors',
    'ready_for_next_step': 'Ready for next step'
  }
  return statusMap[status] || status
}

// Get status badge classes
const getStatusClasses = (status) => {
  const classMap = {
    'starting': 'bg-slate-100 text-slate-700',
    'processing': 'bg-blue-50 text-blue-700',
    'completed': 'bg-emerald-50 text-emerald-700',
    'completed_with_errors': 'bg-amber-50 text-amber-700',
    'ready_for_next_step': 'bg-blue-50 text-blue-700'
  }
  return classMap[status] || 'bg-slate-100 text-slate-700'
}

// Get status dot classes
const getStatusDotClasses = (status) => {
  const classMap = {
    'starting': 'bg-slate-400',
    'processing': 'bg-blue-500',
    'completed': 'bg-emerald-500',
    'completed_with_errors': 'bg-amber-500',
    'ready_for_next_step': 'bg-blue-500'
  }
  return classMap[status] || 'bg-slate-400'
}

// Get step border classes
const getStepBorderClasses = (status) => {
  const classMap = {
    'starting': 'border-slate-200',
    'processing': 'border-blue-200',
    'completed': 'border-emerald-200',
    'completed_with_errors': 'border-amber-200',
    'ready_for_next_step': 'border-blue-200'
  }
  return classMap[status] || 'border-slate-200'
}

// Get step icon classes
const getStepIconClasses = (status) => {
  const classMap = {
    'starting': 'border-slate-300 bg-slate-50 text-slate-600',
    'processing': 'border-blue-500 bg-blue-50 text-blue-600',
    'completed': 'border-emerald-500 bg-emerald-50 text-emerald-600',
    'completed_with_errors': 'border-amber-500 bg-amber-50 text-amber-600',
    'ready_for_next_step': 'border-blue-500 bg-blue-50 text-blue-600'
  }
  return classMap[status] || 'border-slate-300 bg-slate-50 text-slate-600'
}

// Get progress bar classes
const getProgressBarClasses = (status) => {
  const classMap = {
    'starting': 'bg-slate-400',
    'processing': 'bg-blue-500',
    'completed': 'bg-emerald-500',
    'completed_with_errors': 'bg-amber-500',
    'ready_for_next_step': 'bg-blue-500'
  }
  return classMap[status] || 'bg-slate-400'
}

// Calculate progress percentage
const getProgressPercentage = (step) => {
  if (step.overall_items === 0) return 0
  return Math.round((step.processed_items / step.overall_items) * 100)
}

// Setup auto-refresh when workflow is in progress
const setupAutoRefresh = () => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }

  if (isWorkflowInProgress.value) {
    refreshInterval = setInterval(() => {
      fetchStatus()
    }, 5000) // Refresh every 5 seconds
  }
}

onMounted(async () => {
  await fetchStatus()
  setupAutoRefresh()
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})

// Watch for changes in workflow progress to update auto-refresh
watch(isWorkflowInProgress, () => {
  setupAutoRefresh()
})
</script>
