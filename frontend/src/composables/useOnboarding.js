import { ref, computed } from 'vue'
import { useAuth } from './useAuth'

const ONBOARDING_STATE_KEY = 'onboarding_state'

// Shared state across all instances
const currentStep = ref(localStorage.getItem(ONBOARDING_STATE_KEY) || 'welcome')
const integrations = ref({})
const onboardingStatus = ref(null)

const STEPS = {
  WELCOME: 'welcome',
  COMMUNICATION: 'communication',
  TASK_MANAGEMENT: 'task_management',
  STORAGE: 'storage',
  CODE_REPOSITORY: 'code_repository',
  COMPLETE: 'complete',
}

const STEP_ORDER = [
  STEPS.WELCOME,
  STEPS.COMMUNICATION,
  STEPS.TASK_MANAGEMENT,
  STEPS.STORAGE,
  STEPS.CODE_REPOSITORY,
  STEPS.COMPLETE,
]

export function useOnboarding() {
  const { getAuthHeaders } = useAuth()

  const isOnboardingComplete = computed(() => {
    return onboardingStatus.value?.completed || false
  })

  const hasAnyIntegration = computed(() => {
    return Object.values(integrations.value).some((category) =>
      category.some((integration) => integration.connected)
    )
  })

  const currentStepIndex = computed(() => {
    return STEP_ORDER.indexOf(currentStep.value)
  })

  const totalSteps = computed(() => {
    return STEP_ORDER.length - 1 // Exclude 'complete' from count
  })

  const canProceed = computed(() => {
    // User can proceed if they have at least one integration connected
    // or if they're on the welcome step
    return currentStep.value === STEPS.WELCOME || hasAnyIntegration.value
  })

  const saveCurrentStep = (step) => {
    currentStep.value = step
    localStorage.setItem(ONBOARDING_STATE_KEY, step)
  }

  const clearOnboardingState = () => {
    localStorage.removeItem(ONBOARDING_STATE_KEY)
    currentStep.value = STEPS.WELCOME
  }

  const fetchIntegrations = async () => {
    try {
      const response = await fetch(`/api/integrations`, {
        headers: getAuthHeaders(),
      })

      if (!response.ok) {
        throw new Error('Failed to fetch integrations')
      }

      const data = await response.json()
      integrations.value = data

      return data
    } catch (error) {
      console.error('Error fetching integrations:', error)
      throw error
    }
  }

  const fetchOnboardingStatus = async () => {
    try {
      const response = await fetch(`${baseUrl}/api/onboarding/status`, {
        headers: getAuthHeaders(),
      })

      if (!response.ok) {
        throw new Error('Failed to fetch onboarding status')
      }

      const data = await response.json()
      onboardingStatus.value = data

      if (data.current_step) {
        currentStep.value = data.current_step
      }

      return data
    } catch (error) {
      console.error('Error fetching onboarding status:', error)
      throw error
    }
  }

  const updateOnboardingStep = async (step) => {
    try {
      const response = await fetch(`${baseUrl}/api/onboarding/update`, {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ current_step: step }),
      })

      if (!response.ok) {
        throw new Error('Failed to update onboarding step')
      }

      saveCurrentStep(step)
      const data = await response.json()
      onboardingStatus.value = data

      return data
    } catch (error) {
      console.error('Error updating onboarding step:', error)
      throw error
    }
  }

  const completeOnboarding = async () => {
    try {
      const response = await fetch(`${baseUrl}/api/onboarding/complete`, {
        method: 'POST',
        headers: getAuthHeaders(),
      })

      if (!response.ok) {
        throw new Error('Failed to complete onboarding')
      }

      clearOnboardingState()
      const data = await response.json()
      onboardingStatus.value = data

      return data
    } catch (error) {
      console.error('Error completing onboarding:', error)
      throw error
    }
  }

  const nextStep = async () => {
    const nextIndex = currentStepIndex.value + 1
    if (nextIndex < STEP_ORDER.length) {
      const nextStepValue = STEP_ORDER[nextIndex]
      await updateOnboardingStep(nextStepValue)
      return nextStepValue
    }
    return null
  }

  const previousStep = async () => {
    const prevIndex = currentStepIndex.value - 1
    if (prevIndex >= 0) {
      const prevStepValue = STEP_ORDER[prevIndex]
      await updateOnboardingStep(prevStepValue)
      return prevStepValue
    }
    return null
  }

  const goToStep = async (step) => {
    if (STEP_ORDER.includes(step)) {
      await updateOnboardingStep(step)
      return step
    }
    return null
  }

  return {
    // State
    currentStep,
    integrations,
    onboardingStatus,
    STEPS,

    // Computed
    isOnboardingComplete,
    hasAnyIntegration,
    currentStepIndex,
    totalSteps,
    canProceed,

    // Methods
    fetchIntegrations,
    fetchOnboardingStatus,
    updateOnboardingStep,
    completeOnboarding,
    nextStep,
    previousStep,
    goToStep,
    saveCurrentStep,
    clearOnboardingState,
  }
}
