<script setup>
import { ref } from 'vue'

const question = ref('')
const answer = ref(null)
const relevantDocuments = ref([])
const isLoading = ref(false)
const error = ref(null)

const API_BASE_URL = '/api'

const askQuestion = async () => {
  if (!question.value.trim()) return

  isLoading.value = true
  error.value = null
  answer.value = null
  relevantDocuments.value = []

  try {
    const response = await fetch(`${API_BASE_URL}/questions/ask`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Add authentication header if needed
        'Authorization': `Bearer 6|bAORRJTgLH5ruAnK6Yf8r7a21VijximHYA4Uv0BP61828d28`
      },
      credentials: 'include',
      body: JSON.stringify({
        question: question.value
      })
    })

    if (!response.ok) {
      throw new Error(`Error: ${response.status} ${response.statusText}`)
    }

    const data = await response.json()
    answer.value = data.answer
    relevantDocuments.value = data.relevant_documents || []
  } catch (err) {
    console.error('Error asking question:', err)
    error.value = err.message || 'Failed to get an answer. Please try again.'
  } finally {
    isLoading.value = false
  }
}

const handleSubmit = (e) => {
  e.preventDefault()
  askQuestion()
}

const getSourceIcon = (source) => {
  const icons = {
    github: '📦',
    slack: '💬',
    linear: '📋',
    google_drive: '📄',
  }
  return icons[source] || '📄'
}

const formatAnswer = (text) => {
  // Simple markdown-like formatting
  return text
    .split('\n\n')
    .map(para => `<p class="mb-4">${para.replace(/\n/g, '<br>')}</p>`)
    .join('')
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-50 via-blue-50 to-white">
    <!-- Navigation Bar -->
    <nav class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex-shrink-0">
            <a href="/" class="text-2xl font-bold text-black">Horizontal</a>
          </div>
          <div class="text-sm text-gray-500">Ask anything about your team's knowledge</div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-6 lg:px-8 py-12">
      <!-- Search Section -->
      <div class="mb-12">
        <h1 class="text-4xl lg:text-5xl font-bold text-black mb-4 text-center">
          Ask a <span class="bg-gradient-to-r from-purple-500 to-blue-500 bg-clip-text text-transparent">question</span>
        </h1>
        <p class="text-lg text-gray-600 text-center mb-8">
          Search across all your team's knowledge in one place
        </p>

        <!-- Search Form -->
        <form @submit="handleSubmit" class="w-full">
          <div class="relative">
            <input
              type="text"
              v-model="question"
              :disabled="isLoading"
              placeholder="What was the root cause of the last production bug?"
              class="w-full px-6 py-4 pr-32 bg-white border-2 border-gray-200 rounded-2xl text-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
            >
            <button
              type="submit"
              :disabled="isLoading || !question.trim()"
              class="absolute right-2 top-1/2 -translate-y-1/2 bg-gradient-to-r from-purple-500 to-purple-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:from-purple-600 hover:to-purple-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:from-purple-500 disabled:hover:to-purple-600"
            >
              {{ isLoading ? 'Searching...' : 'Ask' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="mb-8 p-6 bg-red-50 border border-red-200 rounded-2xl">
        <div class="flex items-start space-x-3">
          <svg class="w-6 h-6 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <p class="text-red-800 font-medium">{{ error }}</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
        <p class="mt-4 text-gray-600">Searching through your knowledge base...</p>
      </div>

      <!-- Results -->
      <div v-if="answer && !isLoading" class="space-y-8">
        <!-- Answer Section -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
          <div class="bg-gradient-to-r from-purple-50 to-blue-50 px-6 py-4 border-b border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 flex items-center space-x-2">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <span>Answer</span>
            </h2>
          </div>
          <div class="px-6 py-6">
            <div class="prose max-w-none text-gray-800 leading-relaxed" v-html="formatAnswer(answer)"></div>
          </div>
        </div>

        <!-- Relevant Documents Section -->
        <div v-if="relevantDocuments.length > 0" class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
          <div class="bg-gradient-to-r from-blue-50 to-purple-50 px-6 py-4 border-b border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 flex items-center space-x-2">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <span>Relevant Documents ({{ relevantDocuments.length }})</span>
            </h2>
          </div>
          <div class="px-6 py-6 space-y-4">
            <div
              v-for="doc in relevantDocuments"
              :key="doc.id"
              class="p-5 bg-gray-50 rounded-xl border border-gray-200 hover:border-purple-300 hover:shadow-md transition-all duration-200"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center space-x-3 mb-2">
                    <span class="text-2xl">{{ getSourceIcon(doc.source) }}</span>
                    <h3 class="font-semibold text-gray-900 text-lg">{{ doc.title }}</h3>
                  </div>
                  <p v-if="doc.preview" class="text-gray-600 text-sm mb-3 line-clamp-2">
                    {{ doc.preview }}
                  </p>
                  <div class="flex items-center space-x-4 text-sm text-gray-500">
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-white border border-gray-200">
                      <span class="capitalize">{{ doc.source.replace('_', ' ') }}</span>
                    </span>
                    <span v-if="doc.source_type" class="inline-flex items-center px-3 py-1 rounded-full bg-white border border-gray-200">
                      {{ doc.source_type }}
                    </span>
                  </div>
                </div>
                <a
                  v-if="doc.source_url"
                  :href="doc.source_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="ml-4 flex-shrink-0 text-purple-600 hover:text-purple-700 font-medium text-sm flex items-center space-x-1 transition-colors"
                >
                  <span>View</span>
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!answer && !isLoading && !error" class="text-center py-16">
        <div class="w-24 h-24 bg-gradient-to-br from-purple-100 to-blue-100 rounded-full mx-auto mb-6 flex items-center justify-center">
          <svg class="w-12 h-12 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </div>
        <h3 class="text-2xl font-bold text-gray-900 mb-2">Ask anything</h3>
        <p class="text-gray-600 max-w-md mx-auto">
          Search across all your documents, issues, conversations, and code to find the information you need.
        </p>
      </div>
    </main>
  </div>
</template>

<style scoped>
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.prose p:last-child {
  margin-bottom: 0;
}
</style>
