import { ref } from 'vue'
import { useAuth } from './useAuth'

const { getAuthHeaders } = useAuth()

// Shared state across all components
const isIndexingComplete = ref(null)
const isLoading = ref(false)
const error = ref(null)

export function useIndexingStatus() {
  /**
   * Check if indexing workflow has completed
   * @returns {Promise<boolean>} True if completed, false otherwise
   */
  const checkIndexingComplete = async () => {
    try {
      isLoading.value = true
      error.value = null

      const response = await fetch('/api/indexing-workflow/completed', {
        headers: getAuthHeaders()
      })

      if (!response.ok) {
        throw new Error(`Failed to check indexing status: ${response.statusText}`)
      }

      const data = await response.json()
      isIndexingComplete.value = data.result === true

      return isIndexingComplete.value
    } catch (err) {
      error.value = err.message
      console.error('Error checking indexing status:', err)
      // Return true on error to allow access (fail open)
      return true
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Reset the cached status (useful when you want to force a fresh check)
   */
  const resetStatus = () => {
    isIndexingComplete.value = null
    error.value = null
  }

  return {
    isIndexingComplete,
    isLoading,
    error,
    checkIndexingComplete,
    resetStatus
  }
}
