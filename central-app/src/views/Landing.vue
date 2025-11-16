<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const handleSignUp = () => {
  router.push('/signup')
}

const integrations = [
  'GitHub', 'Slack', 'Linear', 'Jira', 'Gmail',
  'Google Drive', 'Notion', 'Confluence', 'Dropbox',
  'Calendar', 'Trello', 'AWS S3'
]

const features = [
  {
    icon: 'search',
    title: 'Universal Search',
    description: 'Search across 10+ tools using natural language. No more tab switching or wondering where that conversation was.',
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
    <section class="relative overflow-hidden bg-slate-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
        <div class="text-center max-w-4xl mx-auto">
          <!-- Main Headline -->
          <h1 class="text-5xl lg:text-7xl font-bold text-white mb-6 leading-tight">
            Search <span class="text-sky-400">all your apps</span>
            <br />
            with one question
          </h1>

          <!-- Subheadline -->
          <p class="text-xl lg:text-2xl text-slate-300 mb-12 max-w-3xl mx-auto leading-relaxed">
            One search bar for <span class="font-semibold text-white">Slack, GitHub, Jira, Drive</span> and 10+ more tools.
            <br />
            Find what you need in seconds, not hours.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-16">
            <button
              @click="handleSignUp"
              class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-lg transition-colors shadow-lg text-lg"
            >
              Get Started Free
            </button>
            <a
              href="#features"
              class="w-full sm:w-auto border-2 border-slate-600 hover:border-slate-500 text-slate-300 hover:text-white font-semibold px-8 py-4 rounded-lg transition-colors text-lg"
            >
              See How It Works
            </a>
          </div>

          <!-- Social Proof / Integrations Preview -->
          <div class="flex flex-wrap justify-center items-center gap-6 text-slate-400 text-sm">
            <span class="text-slate-400 font-medium">Integrates with:</span>
            <div class="flex flex-wrap justify-center gap-4">
              <span v-for="integration in integrations.slice(0, 6)" :key="integration" class="text-slate-400 font-medium">
                {{ integration }}
              </span>
              <span class="text-slate-400 font-medium">and more...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Gradient Background -->
      <div class="absolute top-0 left-0 right-0 h-full -z-10 overflow-hidden">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10"></div>
        <div class="absolute top-0 right-1/4 w-96 h-96 bg-slate-700 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
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
            One search bar for 10+ tools. More integrations added every month.
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
