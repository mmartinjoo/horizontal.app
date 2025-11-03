<script setup>
import { ref } from 'vue'

const email = ref('')
const isSubmitting = ref(false)
const showSuccess = ref(false)
const showError = ref(false)

// Replace these with your actual Google Form details
// Instructions:
// 1. Create a Google Form with one email field
// 2. Get the form URL and replace 'viewform' with 'formResponse'
// 3. Inspect the email field to find the entry number (e.g., entry.123456789)
const GOOGLE_FORM_ACTION = 'https://docs.google.com/forms/d/e/1FAIpQLScvh4EpaNhkV5fabMGkxOxJeVg6guD3b3X1jKgBJN1tiERsaA/formResponse'
const EMAIL_FIELD_NAME = 'entry.161718733' // e.g., 'entry.123456789'

const submitToGoogleForms = async () => {
  if (!email.value) return

  isSubmitting.value = true
  showError.value = false
  showSuccess.value = false

  try {
    // Create form data
    const formData = new FormData()
    formData.append(EMAIL_FIELD_NAME, email.value)

    // Submit to Google Forms using no-cors mode
    await fetch(GOOGLE_FORM_ACTION, {
      method: 'POST',
      mode: 'no-cors', // This is key for Google Forms
      body: formData
    })

    // Since no-cors doesn't return response, we assume success
    showSuccess.value = true
    email.value = ''

    // Track the conversion (optional)
    console.log('Waitlist signup successful')

  } catch (error) {
    console.error('Error submitting to Google Forms:', error)
    showError.value = true
  } finally {
    isSubmitting.value = false
  }
}

const handleSubmit = (e) => {
  e.preventDefault()
  submitToGoogleForms()
}

const goToWaitlist = () => {
  window.location.hash = 'waitlist'
}
</script>

<template>
  <div class="min-h-screen bg-white">
    <!-- Navigation Bar -->
    <nav class="relative bg-gradient-to-r from-purple-50 via-white to-blue-50 border-b border-purple-200/60 backdrop-blur-sm">
      <!-- Enhanced decorative elements for nav -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-0 right-1/4 w-40 h-40 bg-gradient-to-br from-purple-300/20 to-blue-300/20 rounded-full blur-3xl"></div>
        <div class="absolute top-0 left-1/3 w-32 h-32 bg-gradient-to-br from-blue-300/15 to-purple-300/15 rounded-full blur-2xl"></div>
      </div>
      <div class="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
        <div class="flex justify-between items-center h-20">
          <!-- Logo -->
          <div class="flex-shrink-0">
            <h1 class="text-2xl font-bold text-black">Horizontal</h1>
          </div>

          <!-- Navigation Links -->
          <div class="hidden md:block">
            <div class="ml-10 flex items-center space-x-12">
              <a href="#problem" class="text-gray-500 hover:text-gray-900 text-base font-medium transition-colors">
                Problem
              </a>
              <a href="#solution" class="text-gray-500 hover:text-gray-900 text-base font-medium transition-colors">
                Solution
              </a>
              <a href="/ask" class="text-purple-600 hover:text-purple-700 text-base font-medium transition-colors">
                Try It
              </a>
              <a href="#waitlist" class="text-gray-500 hover:text-gray-900 text-base font-medium transition-colors">
                Join Waitlist
              </a>
            </div>
          </div>

          <!-- CTA Button -->
          <div class="flex-shrink-0">
            <a href="#waitlist" class="bg-gray-900 text-white px-6 py-2.5 rounded-lg text-base font-medium hover:bg-gray-800 transition-colors inline-block">
              Join Waitlist
            </a>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-br from-purple-50 via-blue-50 to-white">
      <!-- Decorative background elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-gradient-to-br from-purple-200/30 to-blue-200/30 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-gradient-to-tr from-blue-200/20 to-purple-200/20 rounded-full blur-3xl"></div>
        <div class="absolute top-20 left-1/4 w-32 h-32 bg-gradient-to-br from-purple-300/20 to-blue-300/20 rounded-full blur-2xl"></div>
      </div>

      <main class="max-w-6xl mx-auto px-6 lg:px-8 pt-24 pb-16 relative z-10">
      <div class="text-center">
        <!-- Main Headline -->
        <h1 class="text-6xl lg:text-7xl font-bold text-black mb-8 leading-tight">
          The <span class="bg-gradient-to-r from-purple-500 via-purple-600 to-blue-500 bg-clip-text text-transparent">everything app</span>
          <br>
          for developer teams
        </h1>

        <!-- Subheading -->
        <div class="max-w-4xl mx-auto mb-16 space-y-2">
          <p class="text-xl lg:text-2xl text-gray-600">
            Stop wasting time switching between 5+ different apps.
          </p>
          <p class="text-xl lg:text-2xl text-gray-600">
            Connect all your tools and <span class="bg-gradient-to-r from-purple-500 to-purple-600 bg-clip-text text-transparent font-semibold">search everything</span> in one place.
          </p>
        </div>

        <!-- CTA Button -->
        <div class="mb-16">
          <button @click="goToWaitlist" class="bg-gradient-to-r from-purple-500 to-purple-600 text-white px-10 py-4 rounded-2xl text-xl font-semibold hover:from-purple-600 hover:to-purple-700 transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
            Join the Waitlist →
          </button>
        </div>

        <!-- Social Proof -->
        <div class="flex items-center justify-center space-x-4 text-gray-500 mb-20">
          <span class="text-base font-medium">Join 200+ developers waiting</span>
        </div>
      </div>

      <!-- Problem Illustration -->
      <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <img
            src="/images/lots_of_shitty_apps_in_one_place.png"
            alt="Too many scattered apps and tools"
            class="w-3/4 h-auto rounded-2xl mx-auto"
          />
        </div>
      </div>
      </main>
    </section>

    <!-- Problem Section -->
    <section class="py-24 bg-gray-50" id="problem">
      <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-20">
          <h2 class="text-5xl lg:text-6xl font-bold text-black mb-6">
            The <span class="text-red-500">problem</span> with too many tools
          </h2>
          <p class="text-xl lg:text-2xl text-gray-600 max-w-4xl mx-auto leading-relaxed">
            Your team's knowledge is scattered across dozens of apps. Finding information is like searching for a needle in a haystack.
          </p>
        </div>

        <!-- Problem Points -->
        <div class="grid lg:grid-cols-3 gap-16 lg:gap-12">
          <!-- Time Wasted -->
          <div class="text-center">
            <div class="w-24 h-24 bg-red-50 rounded-full mx-auto mb-8 flex items-center justify-center">
              <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <h3 class="text-2xl lg:text-3xl font-bold text-black mb-6">Time Wasted</h3>
            <p class="text-lg lg:text-xl text-gray-600 leading-relaxed">
              Developers spend 30% of their day just searching for information across different tools
            </p>
          </div>

          <!-- Lost Context -->
          <div class="text-center">
            <div class="w-24 h-24 bg-red-50 rounded-full mx-auto mb-8 flex items-center justify-center">
              <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </div>
            <h3 class="text-2xl lg:text-3xl font-bold text-black mb-6">Lost Context</h3>
            <p class="text-lg lg:text-xl text-gray-600 leading-relaxed">
              Important decisions and discussions are buried in Slack threads, emails, and documents
            </p>
          </div>

          <!-- Knowledge Silos -->
          <div class="text-center">
            <div class="w-24 h-24 bg-red-50 rounded-full mx-auto mb-8 flex items-center justify-center">
              <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <h3 class="text-2xl lg:text-3xl font-bold text-black mb-6">Knowledge Silos</h3>
            <p class="text-lg lg:text-xl text-gray-600 leading-relaxed">
              Team knowledge lives in isolated apps, making collaboration inefficient
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Data Visualization Section -->
    <section class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-20">
          <h2 class="text-5xl lg:text-6xl font-bold text-black mb-6">
            The <span class="text-red-500">hidden cost</span> of scattered tools
          </h2>
          <p class="text-xl lg:text-2xl text-gray-600 max-w-4xl mx-auto leading-relaxed mb-4">
            Based on the 2024 StackOverflow Developer Survey
          </p>
          <p class="text-lg text-gray-500">
            Over 90,000 developers worldwide shared their daily struggles
          </p>
        </div>

        <!-- Charts Container -->
        <div class="grid lg:grid-cols-2 gap-16 lg:gap-20 max-w-6xl mx-auto">
          <!-- Searching Chart -->
          <div class="text-center">
            <div class="mb-8">
              <svg viewBox="0 0 200 200" class="w-64 h-64 mx-auto drop-shadow-lg">
                <!-- Background circle -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#f3f4f6" stroke-width="12"/>

                <!-- Data segments -->
                <!-- 15-30 min: 27% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#fbbf24" stroke-width="12"
                        stroke-dasharray="135.1 366.5" stroke-dashoffset="0"
                        transform="rotate(-90 100 100)" class="animate-draw-1"/>

                <!-- 30-60 min: 37.9% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#f59e0b" stroke-width="12"
                        stroke-dasharray="190.3 311.3" stroke-dashoffset="-135.1"
                        transform="rotate(-90 100 100)" class="animate-draw-2"/>

                <!-- 60-120 min: 18.3% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#dc2626" stroke-width="12"
                        stroke-dasharray="91.9 409.7" stroke-dashoffset="-325.4"
                        transform="rotate(-90 100 100)" class="animate-draw-3"/>

                <!-- 120+ min: 7.6% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#991b1b" stroke-width="12"
                        stroke-dasharray="38.2 463.4" stroke-dashoffset="-417.3"
                        transform="rotate(-90 100 100)" class="animate-draw-4"/>

                <!-- Center text -->
                <text x="100" y="95" text-anchor="middle" class="text-2xl font-bold fill-gray-800">65%</text>
                <text x="100" y="115" text-anchor="middle" class="text-sm fill-gray-600">spend 30+</text>
                <text x="100" y="130" text-anchor="middle" class="text-sm fill-gray-600">minutes</text>
              </svg>
            </div>

            <h3 class="text-2xl lg:text-3xl font-bold text-black mb-6">
              Time Spent <span class="text-red-500">Searching</span>
            </h3>
            <p class="text-lg text-gray-600 mb-8">Daily time developers spend searching for answers</p>

            <!-- Legend -->
            <div class="space-y-3">
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-yellow-400 rounded-full"></div>
                  <span>15-30 minutes</span>
                </div>
                <span class="font-semibold">27%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-yellow-500 rounded-full"></div>
                  <span>30-60 minutes</span>
                </div>
                <span class="font-semibold">38%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-red-600 rounded-full"></div>
                  <span>60-120 minutes</span>
                </div>
                <span class="font-semibold">18%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-red-800 rounded-full"></div>
                  <span>120+ minutes</span>
                </div>
                <span class="font-semibold">8%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-white rounded-full"></div>
                  <span>less then 15 minutes</span>
                </div>
                <span class="font-semibold">9%</span>
              </div>
            </div>
          </div>

          <!-- Answering Chart -->
          <div class="text-center">
            <div class="mb-8">
              <svg viewBox="0 0 200 200" class="w-64 h-64 mx-auto drop-shadow-lg">
                <!-- Background circle -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#f3f4f6" stroke-width="12"/>

                <!-- Data segments -->
                <!-- 15-30 min: 32.4% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#a855f7" stroke-width="12"
                        stroke-dasharray="162.9 339.8" stroke-dashoffset="0"
                        transform="rotate(-90 100 100)" class="animate-draw-a1"/>

                <!-- 30-60 min: 30% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#7c3aed" stroke-width="12"
                        stroke-dasharray="150.8 351.9" stroke-dashoffset="-162.9"
                        transform="rotate(-90 100 100)" class="animate-draw-a2"/>

                <!-- 60-120 min: 12.8% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#5b21b6" stroke-width="12"
                        stroke-dasharray="64.3 438.4" stroke-dashoffset="-313.7"
                        transform="rotate(-90 100 100)" class="animate-draw-a3"/>

                <!-- 120+ min: 4.3% -->
                <circle cx="100" cy="100" r="80" fill="none" stroke="#3730a3" stroke-width="12"
                        stroke-dasharray="21.6 481.1" stroke-dashoffset="-378.0"
                        transform="rotate(-90 100 100)" class="animate-draw-a4"/>

                <!-- Center text -->
                <text x="100" y="95" text-anchor="middle" class="text-2xl font-bold fill-gray-800">47%</text>
                <text x="100" y="115" text-anchor="middle" class="text-sm fill-gray-600">spend 30+</text>
                <text x="100" y="130" text-anchor="middle" class="text-sm fill-gray-600">minutes</text>
              </svg>
            </div>

            <h3 class="text-2xl lg:text-3xl font-bold text-black mb-6">
              Time Spent <span class="text-purple-500">Answering</span>
            </h3>
            <p class="text-lg text-gray-600 mb-8">Daily time developers spend answering questions</p>

            <!-- Legend -->
            <div class="space-y-3">
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-purple-400 rounded-full"></div>
                  <span>15-30 minutes</span>
                </div>
                <span class="font-semibold">32%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-purple-600 rounded-full"></div>
                  <span>30-60 minutes</span>
                </div>
                <span class="font-semibold">30%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-purple-800 rounded-full"></div>
                  <span>60-120 minutes</span>
                </div>
                <span class="font-semibold">13%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-indigo-800 rounded-full"></div>
                  <span>120+ minutes</span>
                </div>
                <span class="font-semibold">4%</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-3">
                  <div class="w-4 h-4 bg-white rounded-full"></div>
                  <span>Less than 15 minutes</span>
                </div>
                <span class="font-semibold">20%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Key Insight -->
        <div class="mt-20 text-center">
          <div class="bg-gradient-to-r from-red-50 to-purple-50 rounded-3xl p-12 border border-red-100">
            <h3 class="text-3xl lg:text-4xl font-bold text-black mb-6">
              That's <span class="text-red-500">1-2 hours</span> of productivity lost every day
            </h3>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
              Developers are spending up to <strong>25% of their workday</strong> just searching for information and answering repetitive questions.
              <span class="text-purple-600 font-semibold">Horizontal eliminates this waste.</span>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Story Section -->
    <section class="py-24 bg-white" id="solution">
      <div class="max-w-5xl mx-auto px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-20">
          <h2 class="text-5xl lg:text-6xl font-bold text-black mb-8">
            Sound familiar?
          </h2>
        </div>

        <!-- Story Points -->
        <div class="space-y-8">
          <!-- Calendar Event -->
          <div class="flex items-start space-x-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                  <!-- Custom Bug SVG -->
                  <!-- Bug body -->
                  <ellipse cx="12" cy="13" rx="6" ry="7" fill="currentColor" opacity="0.9"/>

                  <!-- Bug head -->
                  <circle cx="12" cy="7" r="3" fill="currentColor"/>

                  <!-- Bug eyes -->
                  <circle cx="10.5" cy="6.5" r="0.8" fill="white"/>
                  <circle cx="13.5" cy="6.5" r="0.8" fill="white"/>
                  <circle cx="10.5" cy="6.5" r="0.4" fill="black"/>
                  <circle cx="13.5" cy="6.5" r="0.4" fill="black"/>

                  <!-- Bug antennae -->
                  <path d="M9 4.5 L8 2.5 M15 4.5 L16 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="8" cy="2.5" r="0.8" fill="currentColor"/>
                  <circle cx="16" cy="2.5" r="0.8" fill="currentColor"/>

                  <!-- Bug legs -->
                  <path d="M6 10 L3 9 M6 13 L3 13 M6 16 L3 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <path d="M18 10 L21 9 M18 13 L21 13 M18 16 L21 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>

                  <!-- Bug spots -->
                  <circle cx="10" cy="12" r="0.8" fill="white" opacity="0.7"/>
                  <circle cx="14" cy="14" r="0.6" fill="white" opacity="0.7"/>
                  <circle cx="12" cy="16" r="0.5" fill="white" opacity="0.7"/>
                </svg>
              </div>
            </div>
            <p class="text-xl lg:text-2xl text-gray-600 leading-relaxed">
              A critical bug appears in production
            </p>
          </div>

          <!-- Confusion -->
          <div class="flex items-start space-x-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
            <p class="text-xl lg:text-2xl text-gray-600 leading-relaxed">
              You need to find similar issues from the past
            </p>
          </div>

          <!-- Frantic Search -->
          <div class="flex items-start space-x-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
              </div>
            </div>
            <p class="text-xl lg:text-2xl text-gray-600 leading-relaxed">
              You dig through Jira tickets, GitHub issues, and Slack conversations
            </p>
          </div>

          <!-- Time Wasted -->
          <div class="flex items-start space-x-6">
            <div class="flex-shrink-0">
              <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
            <p class="text-xl lg:text-2xl text-gray-600 leading-relaxed">
              You spend an hour recreating context that already existed
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Solution Demo Section -->
    <section class="py-24 bg-gray-50">
      <div class="max-w-6xl mx-auto px-6 lg:px-8">
        <!-- Chat Interface Demo -->
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
          <!-- Chat Header -->
          <div class="bg-gray-50 px-8 py-6 border-b border-gray-100">
            <div class="flex items-center space-x-3">
              <div class="w-3 h-3 bg-red-400 rounded-full"></div>
              <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
              <div class="w-3 h-3 bg-green-400 rounded-full"></div>
              <span class="ml-4 text-sm text-gray-500 font-medium">Horizontal Chat</span>
            </div>
          </div>

          <!-- Chat Messages -->
          <div class="p-8 space-y-6">
            <!-- User Message -->
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                  <span class="text-white font-semibold text-sm">You</span>
                </div>
              </div>
              <div class="flex-1">
                <div class="bg-purple-500 text-white rounded-2xl rounded-tl-md px-6 py-4 inline-block max-w-4xl">
                  <p class="text-lg">What was the root cause of the previous 'mysql server has gone away' error in prod?</p>
                </div>
              </div>
            </div>

            <!-- Horizontal Response -->
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                  <span class="text-white font-bold text-sm">H</span>
                </div>
              </div>
              <div class="flex-1">
                <div class="bg-gray-50 rounded-2xl rounded-tl-md px-6 py-6 max-w-5xl">
                  <p class="text-lg text-gray-800 mb-6">
                    Your team had this issue on the 15th of April. The `max_allowed_packet` value was exceeded bacause of a very large `INSERT` in the bulk create API.
                  </p>
                  <p class="text-lg text-gray-800 mb-1">
                    Participants:
                  </p>
                  <ul class="list-disc mb-6">
                    <li class="ml-4">Ben created the issues</li>
                    <li class="ml-4">Tom contributed the fixes</li>
                    <li class="ml-4">Peter merged the pull request</li>
                  </ul>


                  <!-- Source Links -->
                  <div class="space-y-4">
                    <!-- GitHub PR -->
                    <div class="flex items-center space-x-4 p-4 bg-white rounded-xl border border-gray-100">
                      <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                      <span class="text-gray-800 font-medium">GitHub PR #247 - "Refactor bulk create API"</span>
                      <a @click.prevent="" class="text-blue-400 cursor-pointer">Open PR</a>
                    </div>

                    <!-- Slack Thread -->
                    <div class="flex items-center space-x-4 p-4 bg-white rounded-xl border border-gray-100">
                      <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                      <span class="text-gray-800 font-medium">Slack #engineering - Thread from April 15</span>
                      <a @click.prevent="" class="text-blue-400 cursor-pointer">Go to conversation</a>
                    </div>

                    <!-- Linear Ticket -->
                    <div class="flex items-center space-x-4 p-4 bg-white rounded-xl border border-gray-100">
                      <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                      <span class="text-gray-800 font-medium">Linear ticket DEV-238 - Refactor bulk creation INSERT query</span>
                      <a @click.prevent="" class="text-blue-400 cursor-pointer">Open issue</a>
                    </div>
                    <div class="flex items-center space-x-4 p-4 bg-white rounded-xl border border-gray-100">
                      <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                      <span class="text-gray-800 font-medium">Linear ticket DEV-240 - Increase MySQL `max_allowed_packet`</span>
                      <a @click.prevent="" class="text-blue-400 cursor-pointer">Open issue</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section id="waitlist" class="py-24 bg-white">
      <div class="max-w-6xl mx-auto px-6 lg:px-8">
        <!-- Main CTA -->
        <div class="text-center mb-16">
          <h2 class="text-5xl lg:text-6xl font-bold text-black mb-8">
            Join the <span class="bg-gradient-to-r from-purple-500 to-purple-600 bg-clip-text text-transparent">revolution</span>
          </h2>
          <p class="text-xl lg:text-2xl text-gray-600 max-w-4xl mx-auto mb-12 leading-relaxed">
            Be among the first development teams to experience the future of connected productivity.
          </p>

          <!-- Signup Form -->
          <div class="max-w-2xl mx-auto mb-12">
            <div class="bg-gray-50 rounded-2xl p-8 border border-gray-100">
              <form @submit="handleSubmit" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                  <div class="relative">
                    <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                    </svg>
                    <input
                      type="email"
                      v-model="email"
                      placeholder="your.email@company.com"
                      required
                      :disabled="isSubmitting"
                      class="w-full pl-12 pr-4 py-4 bg-white border border-gray-200 rounded-xl text-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition-all disabled:opacity-50"
                    >
                  </div>
                </div>
                <button
                  type="submit"
                  :disabled="isSubmitting || !email"
                  class="bg-gradient-to-r from-purple-500 to-purple-600 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:from-purple-600 hover:to-purple-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                >
                  {{ isSubmitting ? 'Joining...' : 'Join Waitlist' }}
                </button>
              </form>

              <!-- Success Message -->
              <div v-if="showSuccess" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                <p class="text-green-800 text-center font-medium">
                  🎉 Thanks for joining the waitlist! We'll be in touch soon.
                </p>
              </div>

              <!-- Error Message -->
              <div v-if="showError" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                <p class="text-red-800 text-center font-medium">
                  ❌ Something went wrong. Please try again or contact us directly.
                </p>
              </div>
            </div>
          </div>

          <!-- Trust Indicators -->
          <div class="flex items-center justify-center space-x-8 text-gray-500 text-base">
            <div class="flex items-center space-x-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
              <span>200+ developers waiting</span>
            </div>
            <span>•</span>
            <span>No spam, ever</span>
            <span>•</span>
            <span>Early access priority</span>
          </div>
        </div>

        <!-- Benefits Grid -->
        <div class="grid lg:grid-cols-3 gap-12 lg:gap-8">
          <!-- Early Access -->
          <div class="text-center">
            <h3 class="text-2xl lg:text-3xl font-bold bg-gradient-to-r from-purple-500 to-purple-600 bg-clip-text text-transparent mb-6">
              Early Access
            </h3>
            <p class="text-lg lg:text-xl text-gray-600 leading-relaxed">
              Be first to try Horizontal when it launches
            </p>
          </div>

          <!-- Special Pricing -->
          <div class="text-center">
            <h3 class="text-2xl lg:text-3xl font-bold bg-gradient-to-r from-purple-500 to-purple-600 bg-clip-text text-transparent mb-6">
              Special Pricing
            </h3>
            <p class="text-lg lg:text-xl text-gray-600 leading-relaxed">
              30% off your first 3 months
            </p>
          </div>

          <!-- Shape the Product -->
          <div class="text-center">
            <h3 class="text-2xl lg:text-3xl font-bold bg-gradient-to-r from-purple-500 to-purple-600 bg-clip-text text-transparent mb-6">
              Shape the Product
            </h3>
            <p class="text-lg lg:text-xl text-gray-600 leading-relaxed">
              Your feedback will guide our development
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-800 text-white py-16">
      <div class="max-w-6xl mx-auto px-6 lg:px-8">
        <!-- Main Footer Content -->
        <div class="text-center mb-12">
          <!-- Company Logo -->
          <div class="flex justify-center mb-6">
            <img src="/images/logo_light.png" alt="Horizontal" class="h-32 w-auto">
          </div>

          <!-- Company Description -->
          <p class="text-xl text-gray-300 max-w-3xl mx-auto leading-relaxed mb-12">
            The everything app for development teams. Connect all your tools, search everything, work faster.
          </p>
        </div>

        <!-- Copyright -->
        <div class="border-t border-slate-700 pt-8">
          <p class="text-center text-gray-400 text-base">
            © 2025 Horizontal. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* Ensure gradients work properly */
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
}

/* Custom gradient animations */
@keyframes gradient {
  0% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0% 50%;
  }
}

.animate-gradient {
  background-size: 200% 200%;
  animation: gradient 3s ease infinite;
}

/* Smooth hover transitions */
button {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Fix border-3 class */
.border-3 {
  border-width: 3px;
}

/* Chart animations */
.animate-draw-1 {
  stroke-dasharray: 0 501.6;
  animation: draw-1 2s ease-out 0.5s forwards;
}

.animate-draw-2 {
  stroke-dasharray: 0 501.6;
  animation: draw-2 2s ease-out 1s forwards;
}

.animate-draw-3 {
  stroke-dasharray: 0 501.6;
  animation: draw-3 2s ease-out 1.5s forwards;
}

.animate-draw-4 {
  stroke-dasharray: 0 501.6;
  animation: draw-4 2s ease-out 2s forwards;
}

@keyframes draw-1 {
  to {
    stroke-dasharray: 135.1 366.5; /* 27% */
  }
}

@keyframes draw-2 {
  to {
    stroke-dasharray: 190.3 311.3; /* 37.9% */
  }
}

@keyframes draw-3 {
  to {
    stroke-dasharray: 91.9 409.7; /* 18.3% */
  }
}

@keyframes draw-4 {
  to {
    stroke-dasharray: 38.2 463.4; /* 7.6% */
  }
}

/* Chart hover effects */
svg circle:hover {
  stroke-width: 14;
  transition: stroke-width 0.3s ease;
}

/* Drop shadow for charts */
.drop-shadow-lg {
  filter: drop-shadow(0 10px 15px -3px rgb(0 0 0 / 0.1)) drop-shadow(0 4px 6px -4px rgb(0 0 0 / 0.1));
}


/* Answering chart animations */
.animate-draw-a1 {
  stroke-dasharray: 0 502.7;
  animation: draw-a1 2s ease-out 0.5s forwards;
}

.animate-draw-a2 {
  stroke-dasharray: 0 502.7;
  animation: draw-a2 2s ease-out 1s forwards;
}

.animate-draw-a3 {
  stroke-dasharray: 0 502.7;
  animation: draw-a3 2s ease-out 1.5s forwards;
}

.animate-draw-a4 {
  stroke-dasharray: 0 502.7;
  animation: draw-a4 2s ease-out 2s forwards;
}

@keyframes draw-a1 {
  to {
    stroke-dasharray: 162.9 339.8; /* 32.4% */
  }
}

@keyframes draw-a2 {
  to {
    stroke-dasharray: 150.8 351.9; /* 30% */
  }
}

@keyframes draw-a3 {
  to {
    stroke-dasharray: 64.3 438.4; /* 12.8% */
  }
}

@keyframes draw-a4 {
  to {
    stroke-dasharray: 21.6 481.1; /* 4.3% */
  }
}
</style>
