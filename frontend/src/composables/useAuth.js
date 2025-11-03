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

  return {
    isAuthenticated,
    getToken,
    setToken,
    clearToken,
    getAuthHeaders,
    logout,
  }
}
