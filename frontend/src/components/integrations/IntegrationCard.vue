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
          <svg v-if="integration.provider === 'jira'" fill="none" height="32" viewBox="0 0 32 32" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
            <path fill="#3266D4" d="M27.545 24.378 16.96 3.208c-.208-.458-.417-.541-.667-.541-.208 0-.458.083-.708.5-1.5 2.375-2.167 5.125-2.167 8 0 4.001 2.042 7.752 5.042 13.795.334.666.584.791 1.167.791h7.335c.541 0 .833-.208.833-.625 0-.208-.042-.333-.25-.75M12.168 14.377c-.834-1.25-1.084-1.334-1.292-1.334s-.333.083-.708.834L4.875 24.46c-.167.334-.208.459-.208.625 0 .334.291.667.916.667h7.46c.5 0 .875-.416 1.083-1.208.25-1 .334-1.876.334-2.917 0-2.917-1.292-5.751-2.292-7.251"></path>
          </svg>
          <svg v-else-if="integration.provider === 'linear'" xmlns="http://www.w3.org/2000/svg" fill="none" width="200" height="200" viewBox="0 0 100 100"><path fill="#222326" d="M1.22541 61.5228c-.2225-.9485.90748-1.5459 1.59638-.857L39.3342 97.1782c.6889.6889.0915 1.8189-.857 1.5964C20.0515 94.4522 5.54779 79.9485 1.22541 61.5228ZM.00189135 46.8891c-.01764375.2833.08887215.5599.28957165.7606L52.3503 99.7085c.2007.2007.4773.3075.7606.2896 2.3692-.1476 4.6938-.46 6.9624-.9259.7645-.157 1.0301-1.0963.4782-1.6481L2.57595 39.4485c-.55186-.5519-1.49117-.2863-1.648174.4782-.465915 2.2686-.77832 4.5932-.92588465 6.9624ZM4.21093 29.7054c-.16649.3738-.08169.8106.20765 1.1l64.77602 64.776c.2894.2894.7262.3742 1.1.2077 1.7861-.7956 3.5171-1.6927 5.1855-2.684.5521-.328.6373-1.0867.1832-1.5407L8.43566 24.3367c-.45409-.4541-1.21271-.3689-1.54074.1832-.99132 1.6684-1.88843 3.3994-2.68399 5.1855ZM12.6587 18.074c-.3701-.3701-.393-.9637-.0443-1.3541C21.7795 6.45931 35.1114 0 49.9519 0 77.5927 0 100 22.4073 100 50.0481c0 14.8405-6.4593 28.1724-16.7199 37.3375-.3903.3487-.984.3258-1.3542-.0443L12.6587 18.074Z"/></svg>
          <img v-else-if="integration.provider === 'google_drive'" src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg" alt="Drive" />
          <img v-else-if="integration.provider === 'github'" src="https://upload.wikimedia.org/wikipedia/commons/9/91/Octicons-mark-github.svg" alt="GitHub" />
          <img v-else-if="integration.provider === 'slack'" src="https://upload.wikimedia.org/wikipedia/commons/d/d5/Slack_icon_2019.svg" alt="Slack" />
          <img v-else-if="integration.provider === 'google_chat'" src="https://upload.wikimedia.org/wikipedia/commons/d/d6/Google_Chat_icon_%282023%29.svg" alt="Google Chat" />
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
            @click="onConnectClick(integration)"
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

const emit = defineEmits(['connect', 'configure', 'disconnect'])

const onConnectClick = function (integration) {
  console.log(integration);
  const extraData = {};
  if (integration.provider === 'jira') {
    const jiraBaseUrl = prompt('Please provider your Jira base URL. This is where you can access your Jira instance.', 'https://your-company.atlassian.net/');
    extraData.jira_base_url = jiraBaseUrl;
  }
  emit('connect', integration.provider, extraData)
}
</script>
