import { ref, computed } from 'vue'

const TOKEN_KEY = 'auth_token'

// Shared state across all instances
const token = ref(localStorage.getItem(TOKEN_KEY))

export function useAuth() {
  const isAuthenticated = computed(() => !!token.value)

  const getToken = () => {
    return token.value
  }

  const setToken = (newToken) => {
    token.value = newToken
    localStorage.setItem(TOKEN_KEY, newToken)
  }

  const clearToken = () => {
    token.value = null
    localStorage.removeItem(TOKEN_KEY)
  }

  const getAuthHeaders = () => {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    }

    if (token.value) {
      headers['Authorization'] = `Bearer ${token.value}`
    }

    return headers
  }

  const logout = () => {
    clearToken()
    window.location.href = '/auth'
  }

  const redirectToOAuth = async (provider, invitationToken = null) => {
    try {
      const url = new URL(`/api/auth/${provider}/redirect`, window.location.origin)

      if (invitationToken) {
        url.searchParams.append('invitation_token', invitationToken)
      }

      const response = await fetch(url.toString(), {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include',
      })

      if (!response.ok) {
        throw new Error(`Failed to initiate ${provider} login`)
      }

      const data = await response.json()

      if (data.url) {
        window.location.href = data.url
      } else {
        throw new Error('No redirect URL received from server')
      }
    } catch (err) {
      console.error('OAuth redirect error:', err)
      throw err
    }
  }

  return {
    isAuthenticated,
    getToken,
    setToken,
    clearToken,
    getAuthHeaders,
    logout,
    redirectToOAuth,
  }
}
