const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api'

let featuresCache = null

export async function fetchFeatures() {
  if (featuresCache) {
    return featuresCache
  }

  try {
    const response = await fetch(`${API_BASE_URL}/features/public`)
    if (!response.ok) {
      throw new Error('Failed to fetch features')
    }
    const data = await response.json()
    featuresCache = data.features
    return featuresCache
  } catch (error) {
    console.error('Failed to fetch feature flags:', error)
    // Safe default: registration closed if we can't fetch
    return {
      registration_open: false
    }
  }
}

export function isRegistrationOpen() {
  return featuresCache?.registration_open ?? false
}
