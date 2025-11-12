<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const handleSignUp = () => {
  router.push('/signup')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center px-4">
    <div class="max-w-4xl w-full">
      <div class="text-center">
        <!-- Logo/Brand -->
        <h1 class="text-5xl font-bold text-slate-900 mb-4">
          Horizontal
        </h1>

        <!-- Tagline -->
        <p class="text-xl text-slate-600 mb-12 max-w-2xl mx-auto">
          Your team's knowledge, searchable in seconds. Connect your tools and find what you need, when you need it.
        </p>

        <!-- Sign Up Button -->
        <button
          @click="handleSignUp"
          class="inline-flex items-center justify-center bg-sky-600 hover:bg-sky-700 text-white font-semibold px-8 py-3.5 rounded-lg transition-colors shadow-sm text-lg"
        >
          Get Started
        </button>

        <!-- Features Preview (Simple) -->
        <div class="mt-20 grid gap-8 sm:grid-cols-3">
          <div class="text-center">
            <div class="flex h-12 w-12 mx-auto items-center justify-center rounded-lg bg-slate-100 border border-slate-200 mb-4">
              <svg class="h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <h3 class="font-semibold text-slate-900 mb-2">Universal Search</h3>
            <p class="text-sm text-slate-600">Search across all your tools from one place</p>
          </div>

          <div class="text-center">
            <div class="flex h-12 w-12 mx-auto items-center justify-center rounded-lg bg-slate-100 border border-slate-200 mb-4">
              <svg class="h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="font-semibold text-slate-900 mb-2">Lightning Fast</h3>
            <p class="text-sm text-slate-600">Get results in milliseconds, not minutes</p>
          </div>

          <div class="text-center">
            <div class="flex h-12 w-12 mx-auto items-center justify-center rounded-lg bg-slate-100 border border-slate-200 mb-4">
              <svg class="h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 class="font-semibold text-slate-900 mb-2">Team Collaboration</h3>
            <p class="text-sm text-slate-600">Keep your team in sync with shared knowledge</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
