<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center">
    <div class="max-w-2xl mx-auto px-4 py-12 text-center">
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50 border-2 border-emerald-200">
        <svg
          class="h-10 w-10 text-emerald-600"
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

      <h1 class="mt-6 text-3xl font-bold text-slate-900">You're all set!</h1>

      <p class="mt-4 text-lg text-slate-600">
        Your integrations are connected. The indexing process is already started.
      </p>

      <div class="mt-10 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900">What's next?</h3>
        <ul class="mt-4 space-y-3 text-left text-sm text-slate-700">
          <li class="flex items-start">
            <svg
              class="mt-0.5 h-5 w-5 flex-shrink-0 text-emerald-600"
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
              Check the status of the indexing process
            </span>
          </li>
          <li class="flex items-start">
            <svg
              class="mt-0.5 h-5 w-5 flex-shrink-0 text-emerald-600"
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
              class="mt-0.5 h-5 w-5 flex-shrink-0 text-emerald-600"
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
        </ul>
      </div>

      <div class="mt-10">
        <button
          @click="goToApp"
          class="inline-flex items-center rounded-md bg-sky-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
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
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../composables/useAuth'

const router = useRouter()
const { getAuthHeaders } = useAuth()

const goToApp = () => {
  router.push('/ask')
}

// Start the indexing workflow when component mounts
onMounted(async () => {
  try {
    const response = await fetch('/api/workflows/start', {
      method: 'POST',
      headers: getAuthHeaders()
    })
  } catch (error) {
    alert('Something went wrong while starting your indexing process. Please reload the page.')
  }
})
</script>
