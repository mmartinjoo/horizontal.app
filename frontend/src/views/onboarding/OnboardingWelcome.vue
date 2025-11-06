<template>
  <OnboardingLayout>
    <div class="mx-auto max-w-3xl text-center">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100">
        <svg
          class="h-10 w-10 text-indigo-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M13 10V3L4 14h7v7l9-11h-7z"
          />
        </svg>
      </div>

      <h2 class="mt-6 text-2xl font-bold text-gray-900">
        Let's get you set up
      </h2>

      <p class="mt-4 text-lg text-gray-600">
        Horizontal helps you search across all your tools in one place. To get started, you'll
        need to connect at least one integration.
      </p>

      <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-6 text-left">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100">
            <svg
              class="h-6 w-6 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
              />
            </svg>
          </div>
          <h3 class="mt-4 font-semibold text-gray-900">Communication</h3>
          <p class="mt-2 text-sm text-gray-600">
            Connect Slack, Google Chat, and other messaging tools
          </p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 text-left">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100">
            <svg
              class="h-6 w-6 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
              />
            </svg>
          </div>
          <h3 class="mt-4 font-semibold text-gray-900">Task Management</h3>
          <p class="mt-2 text-sm text-gray-600">
            Connect Linear, Jira, and project management tools
          </p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 text-left">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-yellow-100">
            <svg
              class="h-6 w-6 text-yellow-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
              />
            </svg>
          </div>
          <h3 class="mt-4 font-semibold text-gray-900">File Storage</h3>
          <p class="mt-2 text-sm text-gray-600">
            Connect Google Drive and other file storage services
          </p>
        </div>
      </div>

      <div class="mt-10 rounded-lg bg-blue-50 p-4">
        <p class="text-sm text-blue-900">
          <strong>Note:</strong> You must connect at least one integration to use Horizontal.
          Don't worry, you can always add more integrations later from your settings.
        </p>
      </div>
    </div>
  </OnboardingLayout>
</template>

<script setup>
import OnboardingLayout from './OnboardingLayout.vue'
</script>
