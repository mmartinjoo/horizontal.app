<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

// Form fields
const companyName = ref('')
const subdomain = ref('')
const teamSize = ref('')
const country = ref('')
const hasNativeContent = ref(null)

// Loading state
const isLoading = ref(false)

// Error state
const error = ref('')

// Available team sizes
const teamSizes = [
  { value: '1-10', label: '1-10 people' },
  { value: '11-50', label: '11-50 people' },
  { value: '51-200', label: '51-200 people' },
  { value: '201-500', label: '201-500 people' },
  { value: '500+', label: '500+ people' }
]

// Popular countries
const countries = [
  { value: 'US', label: 'United States' },
  { value: 'GB', label: 'United Kingdom' },
  { value: 'CA', label: 'Canada' },
  { value: 'AU', label: 'Australia' },
  { value: 'DE', label: 'Germany' },
  { value: 'FR', label: 'France' },
  { value: 'ES', label: 'Spain' },
  { value: 'IT', label: 'Italy' },
  { value: 'NL', label: 'Netherlands' },
  { value: 'SE', label: 'Sweden' },
  { value: 'NO', label: 'Norway' },
  { value: 'DK', label: 'Denmark' },
  { value: 'FI', label: 'Finland' },
  { value: 'PL', label: 'Poland' },
  { value: 'BR', label: 'Brazil' },
  { value: 'MX', label: 'Mexico' },
  { value: 'AR', label: 'Argentina' },
  { value: 'JP', label: 'Japan' },
  { value: 'KR', label: 'South Korea' },
  { value: 'CN', label: 'China' },
  { value: 'IN', label: 'India' },
  { value: 'SG', label: 'Singapore' },
  { value: 'OTHER', label: 'Other' }
]

// Check if we should show the native content question
const shouldShowNativeContentQuestion = computed(() => {
  return country.value && country.value !== 'US' && country.value !== 'GB'
})

// Auto-generate subdomain from company name
const handleCompanyNameInput = () => {
  if (!subdomain.value || subdomain.value === generateSubdomain(companyName.value.slice(0, -1))) {
    subdomain.value = generateSubdomain(companyName.value)
  }
}

const generateSubdomain = (name) => {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
}

// Validate subdomain
const isSubdomainValid = computed(() => {
  if (!subdomain.value) return true
  return /^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/.test(subdomain.value)
})

// Form submission
const handleSubmit = async () => {
  error.value = ''

  if (!companyName.value.trim()) {
    error.value = 'Please enter your company name'
    return
  }

  if (!subdomain.value.trim()) {
    error.value = 'Please enter a subdomain'
    return
  }

  if (!isSubdomainValid.value) {
    error.value = 'Subdomain can only contain lowercase letters, numbers, and hyphens'
    return
  }

  if (!teamSize.value) {
    error.value = 'Please select your team size'
    return
  }

  if (!country.value) {
    error.value = 'Please select your country'
    return
  }

  if (shouldShowNativeContentQuestion.value && hasNativeContent.value === null) {
    error.value = 'Please answer whether you have content in your native language'
    return
  }

  isLoading.value = true

  try {
    // TODO: Make API call to create tenant
    console.log('Creating tenant...', {
      companyName: companyName.value,
      subdomain: subdomain.value,
      teamSize: teamSize.value,
      country: country.value,
      hasNativeContent: hasNativeContent.value
    })

    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500))

    // Redirect to tenant app
    // window.location.href = `https://${subdomain.value}.horizontal.app`
  } catch (err) {
    error.value = err.message || 'Something went wrong. Please try again.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 py-12 px-4">
    <div class="max-w-2xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-slate-900 mb-2">
          Welcome to Horizontal
        </h1>
        <p class="text-slate-600">
          Let's get your team set up in just a few steps
        </p>
      </div>

      <!-- Form Card -->
      <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-8">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Company Name -->
          <div>
            <label for="company-name" class="block text-sm font-medium text-slate-700 mb-2">
              Company Name
            </label>
            <input
              id="company-name"
              v-model="companyName"
              @input="handleCompanyNameInput"
              type="text"
              required
              placeholder="Acme Inc."
              class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
              :disabled="isLoading"
            />
          </div>

          <!-- Subdomain -->
          <div>
            <label for="subdomain" class="block text-sm font-medium text-slate-700 mb-2">
              Choose your subdomain
            </label>
            <div class="flex items-center">
              <input
                id="subdomain"
                v-model="subdomain"
                type="text"
                required
                placeholder="acme"
                class="flex-1 px-4 py-2.5 border border-slate-300 rounded-l-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
                :disabled="isLoading"
                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': !isSubdomainValid }"
              />
              <span class="inline-flex items-center px-4 py-2.5 border border-l-0 border-slate-300 rounded-r-lg bg-slate-50 text-slate-600 text-sm">
                .horizontal.app
              </span>
            </div>
            <p v-if="!isSubdomainValid" class="mt-2 text-sm text-red-600">
              Subdomain can only contain lowercase letters, numbers, and hyphens
            </p>
            <p v-else class="mt-2 text-sm text-slate-500">
              Your team will access Horizontal at <span class="font-medium text-slate-700">{{ subdomain || 'your-company' }}.horizontal.app</span>
            </p>
          </div>

          <!-- Team Size -->
          <div>
            <label for="team-size" class="block text-sm font-medium text-slate-700 mb-2">
              Team Size
            </label>
            <select
              id="team-size"
              v-model="teamSize"
              required
              class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
              :disabled="isLoading"
            >
              <option value="" disabled>Select team size</option>
              <option v-for="size in teamSizes" :key="size.value" :value="size.value">
                {{ size.label }}
              </option>
            </select>
          </div>

          <!-- Country -->
          <div>
            <label for="country" class="block text-sm font-medium text-slate-700 mb-2">
              Country
            </label>
            <select
              id="country"
              v-model="country"
              required
              class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
              :disabled="isLoading"
            >
              <option value="" disabled>Select country</option>
              <option v-for="countryOption in countries" :key="countryOption.value" :value="countryOption.value">
                {{ countryOption.label }}
              </option>
            </select>
          </div>

          <!-- Native Content Question (Conditional) -->
          <div v-if="shouldShowNativeContentQuestion" class="bg-slate-50 border border-slate-200 rounded-lg p-6">
            <label class="block text-sm font-medium text-slate-900 mb-4">
              Do you have content (messages, docs, tasks) written in your native language?
            </label>
            <div class="space-y-3">
              <label class="flex items-center cursor-pointer">
                <input
                  type="radio"
                  :value="true"
                  v-model="hasNativeContent"
                  class="h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-600"
                  :disabled="isLoading"
                />
                <span class="ml-3 text-sm text-slate-700">Yes, we have content in our native language</span>
              </label>
              <label class="flex items-center cursor-pointer">
                <input
                  type="radio"
                  :value="false"
                  v-model="hasNativeContent"
                  class="h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-600"
                  :disabled="isLoading"
                />
                <span class="ml-3 text-sm text-slate-700">No, all our content is in English</span>
              </label>
            </div>
            <p class="mt-3 text-xs text-slate-500">
              This helps us optimize search for your language
            </p>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ error }}
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isLoading || !isSubdomainValid"
            class="w-full bg-sky-600 hover:bg-sky-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
          >
            <svg v-if="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isLoading ? 'Creating your workspace...' : 'Create Workspace' }}
          </button>

          <!-- Back to Landing -->
          <div class="text-center">
            <button
              type="button"
              @click="router.push('/')"
              class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
              :disabled="isLoading"
            >
              Back to home
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
