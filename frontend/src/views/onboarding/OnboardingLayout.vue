<template>
  <div class="min-h-screen bg-gray-50">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">
          Welcome to Horizontal
        </h1>
        <p class="mt-2 text-lg text-gray-600">
          Connect your tools to get started
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mx-auto mt-12 max-w-2xl">
        <ProgressIndicator
          :current-step="currentStepIndex - 1"
          :total-steps="totalSteps"
          :step-names="stepNames"
        />
      </div>

      <!-- Main Content -->
      <div class="mt-12">
        <slot />
      </div>

      <!-- Navigation -->
      <div class="mt-12 flex items-center justify-between border-t border-gray-200 pt-6">
        <button
          v-if="showBackButton"
          @click="handleBack"
          type="button"
          class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
          <svg
            class="mr-2 h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 19l-7-7 7-7"
            />
          </svg>
          Back
        </button>
        <div v-else></div>

        <div class="flex items-center space-x-3">
          <button
            v-if="showSkipButton"
            @click="handleSkip"
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          >
            Skip for now
          </button>

          <button
            @click="handleNext"
            type="button"
            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isLastStep ? 'Finish' : 'Next' }}
            <svg
              v-if="!isLastStep"
              class="ml-2 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5l7 7-7 7"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useOnboarding } from '@/composables/useOnboarding'
import ProgressIndicator from '@/components/integrations/ProgressIndicator.vue'

const router = useRouter()
const route = useRoute()
const {
  currentStep,
  currentStepIndex,
  totalSteps,
  nextStep,
  previousStep,
  saveCurrentStep,
  STEPS,
} = useOnboarding()

// Sync currentStep with route
const syncStepFromRoute = () => {
  const pathParts = route.path.split('/')
  const routeStep = pathParts[pathParts.length - 1] // Get last part of path

  // Only update if it's a valid step and different from current
  if (routeStep && Object.values(STEPS).includes(routeStep) && currentStep.value !== routeStep) {
    saveCurrentStep(routeStep)
  }
}

onMounted(() => {
  syncStepFromRoute()
})

watch(() => route.path, () => {
  syncStepFromRoute()
})

const stepNames = ['Communication', 'Task Management', 'Storage', 'Code Repository']

const showBackButton = computed(() => {
  return currentStep.value !== STEPS.WELCOME
})

const showSkipButton = computed(() => {
  return currentStep.value !== STEPS.WELCOME && currentStep.value !== STEPS.COMPLETE
})

const isLastStep = computed(() => {
  return currentStep.value === STEPS.CODE_REPOSITORY
})

const handleBack = async () => {
  const prevStepValue = await previousStep()
  if (prevStepValue) {
    router.push(`/onboarding/${prevStepValue}`)
  }
}

const handleNext = async () => {
  if (isLastStep.value) {
    router.push('/onboarding/complete')
  } else {
    const nextStepValue = await nextStep()
    if (nextStepValue) {
      router.push(`/onboarding/${nextStepValue}`)
    }
  }
}

const handleSkip = () => {
  handleNext()
}
</script>
