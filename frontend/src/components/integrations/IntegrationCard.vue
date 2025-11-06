<template>
  <div
    class="relative rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md"
  >
    <!-- Status Badge -->
    <div v-if="integration.connected" class="absolute right-4 top-4">
      <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="integration.configured ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'"
      >
        <span
          class="mr-1.5 h-2 w-2 rounded-full"
          :class="integration.configured ? 'bg-green-400' : 'bg-yellow-400'"
        ></span>
        {{ integration.configured ? 'Connected' : 'Setup required' }}
      </span>
    </div>

    <!-- Icon -->
    <div class="flex items-start space-x-4">
      <div class="flex-shrink-0">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100">
          <img
            v-if="integration.icon"
            :src="integration.icon"
            :alt="`${integration.name} icon`"
            class="h-8 w-8"
          />
          <svg
            v-else
            class="h-8 w-8 text-gray-400"
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
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <h3 class="text-lg font-semibold text-gray-900">{{ integration.name }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ integration.description }}</p>

        <!-- Actions -->
        <div class="mt-4 flex items-center space-x-3">
          <!-- Connect Button -->
          <button
            v-if="!integration.connected"
            @click="$emit('connect', integration.provider)"
            :disabled="loading"
            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg
              v-if="loading"
              class="mr-2 h-4 w-4 animate-spin"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
            Connect
          </button>

          <!-- Configure Button -->
          <button
            v-if="integration.connected && !integration.configured"
            @click="$emit('configure', integration.provider)"
            class="inline-flex items-center rounded-md bg-yellow-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-yellow-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-600"
          >
            Configure
          </button>

          <!-- Reconfigure Button -->
          <button
            v-if="integration.connected && integration.configured"
            @click="$emit('configure', integration.provider)"
            class="inline-flex items-center rounded-md bg-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-400"
          >
            Reconfigure
          </button>

          <!-- Disconnect Button -->
          <button
            v-if="integration.connected"
            @click="$emit('disconnect', integration.provider)"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-400"
          >
            Disconnect
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'

defineProps({
  integration: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['connect', 'configure', 'disconnect'])
</script>
