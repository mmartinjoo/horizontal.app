<template>
  <nav aria-label="Progress">
    <ol role="list" class="flex items-center">
      <li
        v-for="(step, stepIdx) in steps"
        :key="step.name"
        :class="[stepIdx !== steps.length - 1 ? 'pr-8 sm:pr-20' : '', 'relative']"
      >
        <template v-if="step.status === 'complete'">
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="h-0.5 w-full bg-indigo-600"></div>
          </div>
          <a
            href="#"
            class="relative flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 hover:bg-indigo-900"
          >
            <svg
              class="h-5 w-5 text-white"
              viewBox="0 0 20 20"
              fill="currentColor"
              aria-hidden="true"
            >
              <path
                fill-rule="evenodd"
                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                clip-rule="evenodd"
              />
            </svg>
            <span class="sr-only">{{ step.name }}</span>
          </a>
        </template>
        <template v-else-if="step.status === 'current'">
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="h-0.5 w-full bg-gray-200"></div>
          </div>
          <a
            href="#"
            class="relative flex h-8 w-8 items-center justify-center rounded-full border-2 border-indigo-600 bg-white"
            aria-current="step"
          >
            <span
              class="h-2.5 w-2.5 rounded-full bg-indigo-600"
              aria-hidden="true"
            ></span>
            <span class="sr-only">{{ step.name }}</span>
          </a>
        </template>
        <template v-else>
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="h-0.5 w-full bg-gray-200"></div>
          </div>
          <a
            href="#"
            class="group relative flex h-8 w-8 items-center justify-center rounded-full border-2 border-gray-300 bg-white hover:border-gray-400"
          >
            <span
              class="h-2.5 w-2.5 rounded-full bg-transparent group-hover:bg-gray-300"
              aria-hidden="true"
            ></span>
            <span class="sr-only">{{ step.name }}</span>
          </a>
        </template>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentStep: {
    type: Number,
    required: true,
  },
  totalSteps: {
    type: Number,
    required: true,
  },
  stepNames: {
    type: Array,
    default: () => ['Communication', 'Task Management', 'Storage', 'Code Repository'],
  },
})

const steps = computed(() => {
  return props.stepNames.map((name, index) => ({
    name,
    status:
      index < props.currentStep ? 'complete' : index === props.currentStep ? 'current' : 'upcoming',
  }))
})
</script>
