<script setup>
import { ref, onUnmounted, computed } from 'vue'
import { useAuth } from '../composables/useAuth'
import { StreamMarkdown } from 'streamdown-vue'

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

// StreamMarkdown configuration
const markdownConfig = {
  shikiTheme: 'github-light',
  allowedLinkPrefixes: ['https://github.com', 'https://gitlab.com', 'https://stackoverflow.com', window.location.origin],
  parseIncompleteMarkdown: true
}

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

const getSourceType = (source) => {
  return source
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
            <div class="prose prose-sm max-w-none text-slate-700 leading-relaxed">
              <StreamMarkdown
                :content="displayedAnswer"
                :shiki-theme="markdownConfig.shikiTheme"
                :allowed-link-prefixes="markdownConfig.allowedLinkPrefixes"
                :parse-incomplete-markdown="markdownConfig.parseIncompleteMarkdown"
              />
              <span v-if="isTyping" class="inline-block w-2 h-4 bg-slate-600 ml-1 animate-pulse"></span>
            </div>
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
              class="group relative p-4 bg-white border border-slate-200 rounded-lg hover:border-slate-300 hover:shadow-sm transition-all"
            >
              <div class="flex items-start gap-4">
                <!-- Icon -->
                <div class="flex-shrink-0">
                  <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 group-hover:bg-slate-50 transition-colors">
                    <!-- GitHub Icon -->
                    <img
                      v-if="doc.source === 'github'"
                      src="https://upload.wikimedia.org/wikipedia/commons/9/91/Octicons-mark-github.svg"
                      alt="GitHub"
                      class="h-5 w-5"
                    />
                    <!-- Slack Icon -->
                    <img
                      v-else-if="doc.source === 'slack'"
                      src="https://upload.wikimedia.org/wikipedia/commons/d/d5/Slack_icon_2019.svg"
                      alt="Slack"
                      class="h-5 w-5"
                    />
                    <!-- Linear Icon -->
                    <svg
                      v-else-if="doc.source === 'linear'"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      width="20"
                      height="20"
                      viewBox="0 0 100 100"
                    >
                      <path fill="#222326" d="M1.22541 61.5228c-.2225-.9485.90748-1.5459 1.59638-.857L39.3342 97.1782c.6889.6889.0915 1.8189-.857 1.5964C20.0515 94.4522 5.54779 79.9485 1.22541 61.5228ZM.00189135 46.8891c-.01764375.2833.08887215.5599.28957165.7606L52.3503 99.7085c.2007.2007.4773.3075.7606.2896 2.3692-.1476 4.6938-.46 6.9624-.9259.7645-.157 1.0301-1.0963.4782-1.6481L2.57595 39.4485c-.55186-.5519-1.49117-.2863-1.648174.4782-.465915 2.2686-.77832 4.5932-.92588465 6.9624ZM4.21093 29.7054c-.16649.3738-.08169.8106.20765 1.1l64.77602 64.776c.2894.2894.7262.3742 1.1.2077 1.7861-.7956 3.5171-1.6927 5.1855-2.684.5521-.328.6373-1.0867.1832-1.5407L8.43566 24.3367c-.45409-.4541-1.21271-.3689-1.54074.1832-.99132 1.6684-1.88843 3.3994-2.68399 5.1855ZM12.6587 18.074c-.3701-.3701-.393-.9637-.0443-1.3541C21.7795 6.45931 35.1114 0 49.9519 0 77.5927 0 100 22.4073 100 50.0481c0 14.8405-6.4593 28.1724-16.7199 37.3375-.3903.3487-.984.3258-1.3542-.0443L12.6587 18.074Z"/>
                    </svg>
                    <!-- Google Drive Icon -->
                    <img
                      v-else-if="doc.source === 'google_drive'"
                      src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg"
                      alt="Google Drive"
                      class="h-5 w-5"
                    />
                    <!-- Default Document Icon -->
                    <svg
                      v-else
                      class="h-5 w-5 text-slate-400"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <h3 class="font-medium text-slate-900 text-sm mb-1 leading-snug">{{ doc.title }}</h3>
                  <p v-if="doc.preview" class="text-slate-600 text-xs mb-2 line-clamp-2 leading-relaxed">
                    {{ doc.preview }}
                  </p>
                  <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="capitalize font-medium">{{ doc.source.replace('_', ' ') }}</span>
                    <span v-if="doc.source_type" class="text-slate-400">·</span>
                    <span v-if="doc.source_type" class="text-slate-500">{{ doc.source_type }}</span>
                  </div>
                </div>

                <!-- View Link -->
                <a
                  v-if="doc.source_url"
                  :href="doc.source_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex-shrink-0 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 rounded-md transition-colors"
                >
                  <span>View</span>
                  <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
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

/* Fix list alignment and spacing */
.prose :deep(ul),
.prose :deep(ol) {
  padding-left: 1.5rem;
  margin-top: 0.75rem;
  margin-bottom: 0.75rem;
}

.prose :deep(li) {
  margin-top: 0.25rem;
  margin-bottom: 0.25rem;
}

.prose :deep(ul ul),
.prose :deep(ol ul),
.prose :deep(ul ol),
.prose :deep(ol ol) {
  margin-top: 0.25rem;
  margin-bottom: 0.25rem;
}
</style>
