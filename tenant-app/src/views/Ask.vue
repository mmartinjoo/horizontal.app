<script setup>
import { ref, onUnmounted, computed } from 'vue'
import { useAuth } from '../composables/useAuth'

const question = ref('')
const answer = ref(null)
const displayedAnswer = ref('')
const targetAnswer = ref('')
const isTyping = ref(false)
const relevantDocuments = ref([])
const isLoading = ref(false)
const error = ref(null)
const pollingInterval = ref(null)
const typewriterInterval = ref(null)

const API_BASE_URL = '/api'
const { getAuthHeaders, logout } = useAuth()

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

const stopTypewriter = () => {
  if (typewriterInterval.value) {
    clearInterval(typewriterInterval.value)
    typewriterInterval.value = null
    isTyping.value = false
  }
}

const startTypewriter = () => {
  // Stop any existing typewriter animation
  stopTypewriter()

  // If target matches displayed, nothing to do
  if (targetAnswer.value === displayedAnswer.value) {
    return
  }

  isTyping.value = true

  // Character-by-character animation
  const CHAR_DELAY = 10 // 20ms per character for smooth effect

  typewriterInterval.value = setInterval(() => {
    if (displayedAnswer.value.length < targetAnswer.value.length) {
      // Add one more character
      displayedAnswer.value = targetAnswer.value.substring(0, displayedAnswer.value.length + 1)
    } else {
      // Animation complete
      stopTypewriter()
    }
  }, CHAR_DELAY)
}

const pollQuestionStatus = async (questionId) => {
  try {
    const response = await fetch(`${API_BASE_URL}/questions/${questionId}`, {
      method: 'GET',
      headers: getAuthHeaders(),
      credentials: 'include'
    })

    if (!response.ok) {
      if (response.status === 401) {
        error.value = 'Your session has expired. Please log in again.'
        stopPolling()
        setTimeout(() => {
          logout()
        }, 2000)
        return
      }
      throw new Error(`Error: ${response.status} ${response.statusText}`)
    }

    const data = await response.json()

    if (data.relevant_documents && data.relevant_documents.length > 0) {
      relevantDocuments.value = data.relevant_documents
    }

    // Update answer with streaming behavior
    if (data.answer) {
      answer.value = data.answer

      // Check if streaming is complete (answered_at is set)
      if (data.answered_at) {
        // Streaming is complete - show the full answer immediately
        stopTypewriter()
        displayedAnswer.value = data.answer
        targetAnswer.value = data.answer
        isLoading.value = false
        stopPolling()
      } else {
        // Still streaming - only update targetAnswer if it's actually different (new content)
        if (targetAnswer.value !== data.answer) {
          targetAnswer.value = data.answer
          startTypewriter()
        }
      }
    }
  } catch (err) {
    console.error('Error polling question:', err)
    error.value = err.message || 'Failed to get an answer. Please try again.'
    isLoading.value = false
    stopPolling()
  }
}

const askQuestion = async () => {
  if (!question.value.trim()) return

  // Stop any existing polling and typewriter animation
  stopPolling()
  stopTypewriter()

  isLoading.value = true
  error.value = null
  answer.value = null
  displayedAnswer.value = ''
  targetAnswer.value = ''
  relevantDocuments.value = []

  try {
    const response = await fetch(`${API_BASE_URL}/questions/ask`, {
      method: 'POST',
      headers: getAuthHeaders(),
      credentials: 'include',
      body: JSON.stringify({
        question: question.value
      })
    })

    if (!response.ok) {
      // Handle authentication errors
      if (response.status === 401) {
        error.value = 'Your session has expired. Please log in again.'
        setTimeout(() => {
          logout()
        }, 2000)
        return
      }
      throw new Error(`Error: ${response.status} ${response.statusText}`)
    }

    const data = await response.json()

    // Start polling for the answer
    if (data.question.id) {
      pollingInterval.value = setInterval(() => {
        pollQuestionStatus(data.question.id)
      }, 1000)
    } else {
      throw new Error('No question ID returned from server')
    }
  } catch (err) {
    console.error('Error asking question:', err)
    error.value = err.message || 'Failed to get an answer. Please try again.'
    isLoading.value = false
  }
}

// Cleanup polling and typewriter on component unmount
onUnmounted(() => {
  stopPolling()
  stopTypewriter()
})

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

const loadingStatusText = computed(() => {
  if (!isLoading.value) return ''

  if (answer.value) {
    return 'Crafting your answer...'
  } else if (relevantDocuments.value.length > 0) {
    return 'Understanding your documents...'
  } else {
    return 'Finding the most important documents...'
  }
})
</script>

<template>
  <div class="min-h-screen bg-white">
    <!-- Navigation Bar -->
    <nav class="border-b border-slate-200">
      <div class="max-w-4xl mx-auto px-4 lg:px-6">
        <div class="flex justify-between items-center h-14">
          <div class="flex-shrink-0">
            <a href="/" class="text-lg font-semibold text-slate-900">Horizontal</a>
          </div>
          <div class="flex items-center space-x-6">
            <router-link
              to="/invitations"
              class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
            >
              Invitations
            </router-link>
            <button
              @click="logout"
              class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-3xl mx-auto px-4 lg:px-6 py-8">
      <!-- Search Section -->
      <div class="mb-8">
        <h1 class="text-2xl font-medium text-slate-900 mb-6 text-center">
          Ask your knowledge base
        </h1>

        <!-- Search Form -->
        <form @submit="handleSubmit" class="w-full">
          <div class="relative">
            <input
              type="text"
              v-model="question"
              :disabled="isLoading"
              placeholder="Ask anything"
              class="w-full px-4 py-3 pr-24 bg-white border border-slate-200 rounded-full text-base focus:outline-none focus:border-slate-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
            <button
              type="submit"
              :disabled="isLoading || !question.trim()"
              class="absolute right-2 top-1/2 -translate-y-1/2 bg-sky-600 text-white px-4 py-1.5 rounded-full text-sm font-medium hover:bg-sky-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-sky-600"
            >
              {{ isLoading ? '...' : 'Ask' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-100 rounded-lg">
        <p class="text-red-700 text-sm">{{ error }}</p>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="py-8 flex items-center gap-3">
        <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-slate-200 border-t-slate-600"></div>
        <p class="text-sm text-slate-600">{{ loadingStatusText }}</p>
      </div>

      <!-- Results -->
      <div v-if="displayedAnswer || isLoading" class="space-y-6">
        <!-- Answer Section -->
        <div v-if="displayedAnswer" class="border border-slate-200 rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-200">
            <h2 class="text-sm font-medium text-slate-700">Answer</h2>
          </div>
          <div class="px-5 py-4">
            <div class="prose prose-sm max-w-none text-slate-700 leading-relaxed" v-html="formatAnswer(displayedAnswer)"></div>
            <span v-if="isTyping" class="inline-block w-2 h-4 bg-slate-600 ml-1 animate-pulse"></span>
          </div>
        </div>

        <!-- Relevant Documents Section -->
        <div v-if="relevantDocuments.length > 0" class="border border-slate-200 rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-200">
            <h2 class="text-sm font-medium text-slate-700">Sources ({{ relevantDocuments.length }})</h2>
          </div>
          <div class="px-5 py-4 space-y-3">
            <div
              v-for="doc in relevantDocuments"
              :key="doc.id"
              class="p-4 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <h3 class="font-medium text-slate-900 text-sm mb-1">{{ doc.title }}</h3>
                  <p v-if="doc.preview" class="text-slate-600 text-xs mb-2 line-clamp-2">
                    {{ doc.preview }}
                  </p>
                  <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="capitalize">{{ doc.source.replace('_', ' ') }}</span>
                    <span v-if="doc.source_type">· {{ doc.source_type }}</span>
                  </div>
                </div>
                <a
                  v-if="doc.source_url"
                  :href="doc.source_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex-shrink-0 text-slate-600 hover:text-slate-900 text-xs underline transition-colors"
                >
                  View
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.prose p {
  margin-bottom: 0.75rem;
}

.prose p:last-child {
  margin-bottom: 0;
}
</style>
