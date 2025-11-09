<template>
  <OnboardingLayout>
    <div class="mx-auto max-w-3xl text-center">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-sky-50 border border-sky-200">
        <svg
          class="h-8 w-8 text-sky-600"
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

      <h2 class="mt-6 text-2xl font-bold text-slate-900">
        Let's get you set up
      </h2>

      <p class="mt-4 text-lg text-slate-600">
        Horizontal helps you search across all your tools in one place. To get started, you'll
        need to connect at least one integration.
      </p>

      <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex h-10 w-10 mx-auto items-center justify-center rounded-lg bg-sky-50 border border-sky-200">
            <svg
              class="h-5 w-5 text-sky-600"
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
          <h3 class="mt-4 font-semibold text-slate-900 text-center">Communication</h3>
          <p class="mt-2 text-sm text-slate-600 text-center">
            Connect Slack, Google Chat, and other messaging tools
          </p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex h-10 w-10 mx-auto items-center justify-center rounded-lg bg-emerald-50 border border-emerald-200">
            <svg
              class="h-5 w-5 text-emerald-600"
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
          <h3 class="mt-4 font-semibold text-slate-900 text-center">Task Management</h3>
          <p class="mt-2 text-sm text-slate-600 text-center">
            Connect Linear, Jira, and project management tools
          </p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex h-10 w-10 mx-auto items-center justify-center rounded-lg bg-amber-50 border border-amber-200">
            <svg
              class="h-5 w-5 text-amber-600"
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
          <h3 class="mt-4 font-semibold text-slate-900 text-center">File Storage</h3>
          <p class="mt-2 text-sm text-slate-600 text-center">
            Connect Google Drive and other file storage services
          </p>
        </div>
      </div>

      <div class="mt-12 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <p class="text-sm text-slate-700">
          <strong class="text-slate-900">Note:</strong> You must connect at least one integration to use Horizontal.
          Don't worry, you can always add more integrations later from your settings.
        </p>
      </div>
    </div>
  </OnboardingLayout>
</template>

<script setup>
import OnboardingLayout from './OnboardingLayout.vue'
</script>
