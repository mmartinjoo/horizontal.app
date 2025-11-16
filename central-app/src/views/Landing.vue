<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const handleSignUp = () => {
  router.push('/signup')
}

const integrations = [
  'Slack', 'Jira', 'Drive', 'GitHub',
  'Linear', 'Google Chat', 
  'GitHub Projects', 'Confluence',
]

const features = [
  {
    icon: 'search',
    title: 'Universal Search',
    description: 'Search across 8+ tools using natural language. No more tab switching or wondering where that conversation was.',
    highlight: 'Search in seconds, not hours'
  },
  {
    icon: 'graph',
    title: 'Connected Insights',
    description: 'See how your GitHub PRs, Slack threads, Linear tickets, and docs connect. Understand the full context instantly.',
    highlight: 'Visual relationship mapping'
  },
  {
    icon: 'history',
    title: 'Recall Past Decisions',
    description: 'Remember why you built that workaround? Horizontal shows you the conversations, docs, and tickets that led to every decision.',
    highlight: 'Never lose context again'
  }
]

const painPoints = [
  {
    problem: 'Switching between 5+ different apps to find information',
    solution: 'One search bar for everything'
  },
  {
    problem: 'Spending 20+ minutes recreating context for bugs',
    solution: 'Instant access to related tickets, PRs, and conversations'
  },
  {
    problem: 'Asking "where did we discuss this?" on Slack',
    solution: 'Smart search that knows where to look'
  },
  {
    problem: 'New developers taking weeks to ramp up',
    solution: 'AI-powered knowledge base that answers questions'
  }
]
</script>

<template>
  <div class="min-h-screen bg-white">
    <!-- Navigation -->
    <nav class="border-b border-slate-800 bg-slate-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center">
            <span class="text-2xl font-bold text-white">
              Horizontal
            </span>
          </div>
          <div class="flex items-center space-x-4">
            <button
              @click="handleSignUp"
              class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg transition-colors"
            >
              Get Started
            </button>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
          <!-- Left Side - Content -->
          <div>
            <!-- Main Headline -->
            <h1 class="text-5xl lg:text-6xl font-bold text-slate-900 mb-6 leading-tight">
              Search <span class="text-blue-600">all your apps</span> with one question
            </h1>

            <!-- Subheadline -->            
            <p class="text-lg lg:text-xl text-slate-600 mb-2 leading-relaxed">
              "<span class="font-bold">Where to search</span>" and <span class="font-bold">context switching</span> are past problems.
            </p>
            <p class="text-lg lg:text-xl text-slate-600 mb-8 leading-relaxed">
              Give your engineering team a unified search platform. Stop switching between 5+ tools to find what you need.
            </p>
            
            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 mb-8">
              <button
                @click="handleSignUp"
                class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-lg transition-colors text-base"
              >
                Get Started Free
              </button>
              <a
                href="#features"
                class="inline-flex items-center justify-center border-2 border-slate-300 hover:border-slate-400 text-slate-700 hover:text-slate-900 font-semibold px-8 py-3.5 rounded-lg transition-colors text-base"
              >
                Watch Demo
              </a>
            </div>

            <!-- Social Proof / Integrations -->
            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
              <span class="font-medium">Integrates with:</span>
              <div class="flex flex-wrap gap-3">
                <span v-for="integration in integrations.slice(0, 5)" :key="integration" class="px-3 py-1 bg-slate-100 rounded-md font-medium text-slate-700">
                  {{ integration }}
                </span>
                <span class="px-3 py-1 bg-slate-100 rounded-md font-medium text-slate-700">+3 more</span>
              </div>
            </div>
          </div>

          <!-- Right Side - Visual -->
          <div class="relative">
            <div class="relative bg-gradient-to-br from-blue-50 to-slate-50 rounded-2xl border border-slate-200 p-8 lg:p-12">
              <!-- Search Interface Mockup -->
              <div class="bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden">
                <!-- Search Bar -->
                <div class="p-6 border-b border-slate-200">
                  <div class="flex items-center space-x-3 px-4 py-3 bg-slate-50 rounded-lg border border-slate-200">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span class="text-slate-400 text-sm">What caused the MySQL timeout last month?</span>
                  </div>
                </div>

                <!-- Results -->
                <div class="p-6 space-y-4">
                  <!-- Result Item 1 -->
                  <div class="flex items-start space-x-3 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors">
                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center flex-shrink-0">
                      <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center space-x-2 mb-1">
                        <span class="text-xs font-semibold text-blue-600">GitHub PR #247</span>
                        <span class="text-xs text-slate-400">• 2 months ago</span>
                      </div>
                      <p class="text-sm text-slate-900 font-medium mb-1">Refactor bulk create API</p>
                      <p class="text-xs text-slate-600">Fixed MySQL max_allowed_packet exceeded error...</p>
                    </div>
                  </div>

                  <!-- Result Item 2 -->
                  <div class="flex items-start space-x-3 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors">
                    <div class="w-8 h-8 rounded bg-purple-100 flex items-center justify-center flex-shrink-0">
                      <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center space-x-2 mb-1">
                        <span class="text-xs font-semibold text-purple-600">Slack #engineering</span>
                        <span class="text-xs text-slate-400">• 2 months ago</span>
                      </div>
                      <p class="text-sm text-slate-900 font-medium mb-1">Database error discussion</p>
                      <p class="text-xs text-slate-600">Tom: The max_allowed_packet value needs to be increased...</p>
                    </div>
                  </div>

                  <!-- Result Item 3 -->
                  <div class="flex items-start space-x-3 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors">
                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center flex-shrink-0">
                      <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.5 0L2.5 6v12l10 6 10-6V6l-10-6zm6.9 17.5l-6.9 4.2-6.9-4.2V9.3l6.9 4.2 6.9-4.2v8.2z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center space-x-2 mb-1">
                        <span class="text-xs font-semibold text-blue-600">Linear DEV-238</span>
                        <span class="text-xs text-slate-400">• 2 months ago</span>
                      </div>
                      <p class="text-sm text-slate-900 font-medium mb-1">Refactor bulk creation INSERT query</p>
                      <p class="text-xs text-slate-600">Closed by Peter • Merged with PR #247</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Floating integration badges -->
              <div class="absolute -top-4 -right-4 bg-white rounded-lg shadow-lg border border-slate-200 px-4 py-2 flex items-center space-x-2">
                <div class="flex -space-x-2">
                  <div class="w-6 h-6 rounded-full bg-blue-100 border-2 border-white flex items-center justify-center">
                    <svg class="w-3 h-3 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                  </div>
                  <div class="w-6 h-6 rounded-full bg-purple-100 border-2 border-white flex items-center justify-center">
                    <svg class="w-3 h-3 text-purple-600" fill="currentColor" viewBox="0 0 24 24"><path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52z"/></svg>
                  </div>
                  <div class="w-6 h-6 rounded-full bg-blue-100 border-2 border-white flex items-center justify-center">
                    <svg class="w-3 h-3 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.5 0L2.5 6v12l10 6 10-6V6l-10-6z"/></svg>
                  </div>
                </div>
                <span class="text-xs font-semibold text-slate-700">8+ apps</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pain Points Section -->
    <section class="py-24 bg-slate-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl lg:text-5xl font-bold text-slate-900 mb-4">
            Sound familiar?
          </h2>
          <p class="text-xl text-slate-600 max-w-3xl mx-auto">
            Engineering teams waste hours every week hunting for information scattered across tools
          </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 max-w-5xl mx-auto">
          <div
            v-for="(point, index) in painPoints"
            :key="index"
            class="bg-white rounded-xl p-6 border border-slate-200 hover:border-blue-300 transition-all hover:shadow-lg"
          >
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0 mt-1">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </div>
              <div class="flex-1">
                <p class="text-slate-700 mb-2">{{ point.problem }}</p>
                <div class="flex items-start space-x-2">
                  <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <p class="text-emerald-700 font-medium">{{ point.solution }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-20">
          <h2 class="text-3xl lg:text-5xl font-bold text-slate-900 mb-4">
            Built for developers who value their time
          </h2>
          <p class="text-xl text-slate-600 max-w-3xl mx-auto">
            Horizontal understands your entire codebase, conversations, and documentation
          </p>
        </div>

        <div class="space-y-24">
          <div
            v-for="(feature, index) in features"
            :key="index"
            class="grid lg:grid-cols-2 gap-12 items-center"
            :class="index % 2 === 1 ? 'lg:flex-row-reverse' : ''"
          >
            <!-- Feature Content -->
            <div :class="index % 2 === 1 ? 'lg:order-2' : ''">
              <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-600 mb-6">
                <svg v-if="feature.icon === 'search'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <svg v-else-if="feature.icon === 'graph'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <svg v-else class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>

              <h3 class="text-3xl font-bold text-slate-900 mb-4">{{ feature.title }}</h3>
              <p class="text-lg text-slate-600 mb-6">{{ feature.description }}</p>
              <div class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-50 border border-blue-200">
                <span class="text-sm font-semibold text-blue-700">{{ feature.highlight }}</span>
              </div>
            </div>

            <!-- Feature Visual Placeholder -->
            <div :class="index % 2 === 1 ? 'lg:order-1' : ''">
              <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-2xl border border-slate-200 p-8 lg:p-12 h-80 flex items-center justify-center">
                <div class="text-center">
                  <div class="w-20 h-20 mx-auto mb-4 rounded-xl bg-blue-600 flex items-center justify-center">
                    <svg v-if="feature.icon === 'search'" class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <svg v-else-if="feature.icon === 'graph'" class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <svg v-else class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <p class="text-slate-400 text-sm font-medium">Feature visualization</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Use Cases Section -->
    <section class="py-24 bg-slate-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl lg:text-5xl font-bold text-slate-900 mb-4">
            Real scenarios, real solutions
          </h2>
          <p class="text-xl text-slate-600 max-w-3xl mx-auto">
            See how Horizontal solves everyday engineering challenges
          </p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
          <!-- Use Case 1 -->
          <div class="bg-white rounded-xl p-8 border border-slate-200">
            <div class="mb-4">
              <span class="inline-block px-3 py-1 bg-amber-100 text-amber-700 text-sm font-semibold rounded-lg">
                Bug Investigation
              </span>
            </div>
            <h4 class="text-xl font-bold text-slate-900 mb-3">
              "What caused the MySQL timeout last month?"
            </h4>
            <p class="text-slate-600 mb-4">
              Instantly find the related GitHub PR, Slack discussion, and Linear ticket that fixed the issue.
            </p>
            <div class="text-sm text-slate-500">
              <span class="font-medium text-emerald-600">Saved:</span> 60+ minutes of investigation
            </div>
          </div>

          <!-- Use Case 2 -->
          <div class="bg-white rounded-xl p-8 border border-slate-200">
            <div class="mb-4">
              <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 text-sm font-semibold rounded-lg">
                Onboarding
              </span>
            </div>
            <h4 class="text-xl font-bold text-slate-900 mb-3">
              "Why do we have this workaround?"
            </h4>
            <p class="text-slate-600 mb-4">
              New developers get instant context about architectural decisions and legacy code.
            </p>
            <div class="text-sm text-slate-500">
              <span class="font-medium text-emerald-600">Saved:</span> Days of onboarding time
            </div>
          </div>

          <!-- Use Case 3 -->
          <div class="bg-white rounded-xl p-8 border border-slate-200">
            <div class="mb-4">
              <span class="inline-block px-3 py-1 bg-slate-100 text-slate-700 text-sm font-semibold rounded-lg">
                Context Recovery
              </span>
            </div>
            <h4 class="text-xl font-bold text-slate-900 mb-3">
              "Where's our authentication guide?"
            </h4>
            <p class="text-slate-600 mb-4">
              Search once across Drive, Notion, Confluence, and Slack instead of checking each separately.
            </p>
            <div class="text-sm text-slate-500">
              <span class="font-medium text-emerald-600">Saved:</span> 20+ minutes daily
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Integrations Section -->
    <section class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl lg:text-5xl font-bold text-slate-900 mb-4">
            Connects to your entire stack
          </h2>
          <p class="text-xl text-slate-600 max-w-3xl mx-auto">
            One search bar for 8+ tools. More integrations added every month.
          </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-w-5xl mx-auto">
          <div
            v-for="integration in integrations"
            :key="integration"
            class="bg-white border-2 border-slate-200 rounded-xl p-6 text-center hover:border-blue-400 hover:shadow-lg transition-all"
          >
            <div class="w-12 h-12 mx-auto mb-3 rounded-lg bg-blue-50 flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <span class="text-sm font-semibold text-slate-700">{{ integration }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 bg-blue-600">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl lg:text-6xl font-bold text-white mb-6">
          Ready to stop searching?
        </h2>
        <p class="text-xl text-blue-100 mb-10 max-w-2xl mx-auto">
          Join engineering teams who found their sanity with Horizontal
        </p>
        <button
          @click="handleSignUp"
          class="bg-white hover:bg-slate-50 text-blue-600 font-bold px-10 py-5 rounded-lg transition-colors shadow-xl text-lg"
        >
          Get Started Free
        </button>
        <p class="text-blue-100 text-sm mt-6">
          No credit card required. Set up in minutes.
        </p>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <div class="text-2xl font-bold text-slate-100 mb-4">
            Horizontal
          </div>
          <p class="text-sm">
            The search platform for engineering teams
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>
