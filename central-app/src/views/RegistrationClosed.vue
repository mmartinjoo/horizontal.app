<script setup>
const handleBookDemo = () => {
  window.open('https://cal.com/martin-joo-horizontal/horizontal-demo', '_blank')
}

const handleGoHome = () => {
  window.location.href = '/'
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center px-4">
    <div class="max-w-2xl w-full">
      <!-- Card -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 md:p-12 text-center">
        <!-- Icon -->
        <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-blue-100 flex items-center justify-center">
          <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>

        <!-- Heading -->
        <h1 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">
          Registration is temporarily closed
        </h1>

        <!-- Message -->
        <p class="text-lg text-slate-600 mb-8 leading-relaxed">
          We're currently in soft-launch mode and onboarding teams on a case-by-case basis.
          We'd love to show you Horizontal and discuss how it can help your team.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-6">
          <button
            @click="handleBookDemo"
            class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-lg transition-colors text-base cursor-pointer shadow-sm"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Book a demo
          </button>

          <button
            @click="handleGoHome"
            class="inline-flex items-center justify-center bg-white hover:bg-slate-50 text-slate-700 font-semibold px-8 py-3.5 rounded-lg transition-colors text-base cursor-pointer border border-slate-300"
          >
            Back to home
          </button>
        </div>

        <!-- Additional info -->
        <p class="text-sm text-slate-500">
          Questions? Email us at
          <a href="mailto:hello@horizontal.app" class="text-blue-600 hover:text-blue-700 font-medium">
            hello@horizontal.app
          </a>
        </p>
      </div>
    </div>
  </div>
</template>
