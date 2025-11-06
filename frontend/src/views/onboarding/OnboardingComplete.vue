<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="max-w-2xl mx-auto px-4 py-12 text-center">
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
        <svg
          class="h-12 w-12 text-green-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M5 13l4 4L19 7"
          />
        </svg>
      </div>

      <h1 class="mt-6 text-3xl font-bold text-gray-900">You're all set!</h1>

      <p class="mt-4 text-lg text-gray-600">
        Your integrations are connected and ready to use. You can now search across all your
        connected tools.
      </p>

      <div class="mt-10 rounded-lg bg-blue-50 p-6">
        <h3 class="text-sm font-semibold text-blue-900">What's next?</h3>
        <ul class="mt-4 space-y-3 text-left text-sm text-blue-800">
          <li class="flex items-start">
            <svg
              class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
              />
            </svg>
            <span class="ml-3">
              Start searching across your connected tools using the search bar
            </span>
          </li>
          <li class="flex items-start">
            <svg
              class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
              />
            </svg>
            <span class="ml-3">
              Add more integrations from your settings to expand your search
            </span>
          </li>
          <li class="flex items-start">
            <svg
              class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
              />
            </svg>
            <span class="ml-3">
              Configure which channels, projects, and folders to index in your settings
            </span>
          </li>
        </ul>
      </div>

      <div class="mt-10">
        <button
          @click="goToApp"
          class="inline-flex items-center rounded-md bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
          Go to Horizontal
          <svg
            class="ml-2 h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M13 7l5 5m0 0l-5 5m5-5H6"
            />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useOnboarding } from '@/composables/useOnboarding'

const router = useRouter()
const { clearOnboardingState } = useOnboarding()

const goToApp = () => {
  clearOnboardingState()
  router.push('/ask')
}
</script>
