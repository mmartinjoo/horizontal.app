<template>
  <div class="space-y-3">
    <div v-if="loading" class="flex justify-center py-8">
      <svg
        class="h-8 w-8 animate-spin text-indigo-600"
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
    </div>

    <div v-else-if="resources && resources.length > 0" class="space-y-2">
      <!-- Select All -->
      <div class="flex items-center rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
        <input
          :id="`select-all-${provider}`"
          type="checkbox"
          :checked="allSelected"
          @change="toggleAll"
          class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
        />
        <label
          :for="`select-all-${provider}`"
          class="ml-3 text-sm font-medium text-gray-700 cursor-pointer"
        >
          Select All ({{ resources.length }})
        </label>
      </div>

      <!-- Resource List -->
      <div class="max-h-96 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3">
        <div
          v-for="resource in resources"
          :key="resource.id"
          class="flex items-center rounded-md px-3 py-2 hover:bg-gray-50"
        >
          <input
            :id="`resource-${resource.id}`"
            type="checkbox"
            :value="resource.id"
            v-model="selectedIds"
            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
          />
          <label
            :for="`resource-${resource.id}`"
            class="ml-3 flex-1 text-sm text-gray-900 cursor-pointer"
          >
            <div class="font-medium">{{ resource.name }}</div>
            <div v-if="resource.description" class="text-xs text-gray-500">
              {{ resource.description }}
            </div>
          </label>
        </div>
      </div>

      <!-- Selection Summary -->
      <div class="text-sm text-gray-500">
        {{ selectedIds.length }} of {{ resources.length }} selected
      </div>
    </div>

    <div v-else class="py-8 text-center text-sm text-gray-500">
      No resources found
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  resources: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  provider: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const selectedIds = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const allSelected = computed(() => {
  return props.resources.length > 0 && selectedIds.value.length === props.resources.length
})

const toggleAll = () => {
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = props.resources.map((r) => r.id)
  }
}
</script>
