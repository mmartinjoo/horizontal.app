import { ref } from 'vue'
import { useAuth } from './useAuth'

const invitations = ref([])
const isLoading = ref(false)
const error = ref(null)

export function useInvitations() {
  const { getAuthHeaders } = useAuth()

  async function sendInvitation(email) {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch('/api/invitations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...getAuthHeaders()
        },
        credentials: 'include',
        body: JSON.stringify({ email })
      })

      const data = await response.json()

      if (!response.ok) {
        if (response.status === 422) {
          // Validation error
          const validationErrors = data.errors || {}
          throw new Error(validationErrors.email?.[0] || data.message || 'Validation failed')
        }
        throw new Error(data.message || 'Failed to send invitation')
      }

      // Add the new invitation to the list
      invitations.value.unshift(data.invitation)

      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function fetchInvitations() {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch('/api/invitations', {
        method: 'GET',
        headers: getAuthHeaders(),
        credentials: 'include'
      })

      if (!response.ok) {
        throw new Error('Failed to fetch invitations')
      }

      const data = await response.json()
      invitations.value = data.invitations || []

      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      isLoading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  return {
    invitations,
    isLoading,
    error,
    sendInvitation,
    fetchInvitations,
    clearError
  }
}
