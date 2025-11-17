<script setup>
import { ref, computed } from 'vue'

// Pricing plans data
const plans = [
  { maxSeats: 1, minSeats: 1, name: 'Solo', price: 29 },
  { maxSeats: 5, minSeats: 2, name: 'Indie', price: 159 },
  { maxSeats: 10, minSeats: 6, name: 'Team', price: 319 },
  { maxSeats: 15, minSeats: 11, name: 'Squad', price: 499 },
  { maxSeats: 25, minSeats: 16, name: 'Startup', price: 859 },
  { maxSeats: 50, minSeats: 26, name: 'Business', price: 1790 },
]

// Additional questions pricing per plan
const additionalQuestions = {
  Solo: [
    { questions: 150, price: 0 },
    { questions: 250, price: 10 },
    { questions: 500, price: 17 },
  ],
  Indie: [
    { questions: 150, price: 0 },
    { questions: 250, price: 50 },
    { questions: 500, price: 85 },
  ],
  Team: [
    { questions: 150, price: 0 },
    { questions: 250, price: 100 },
    { questions: 500, price: 170 },
  ],
  Squad: [
    { questions: 150, price: 0 },
    { questions: 250, price: 150 },
    { questions: 500, price: 255 },
  ],
  Startup: [
    { questions: 150, price: 0 },
    { questions: 250, price: 250 },
    { questions: 500, price: 425 },
  ],
  Business: [
    { questions: 150, price: 0 },
    { questions: 250, price: 500 },
    { questions: 500, price: 850 },
  ],
}

// Data retention pricing per plan
const dataRetention = {
  Solo: [
    { months: 3, price: 0 },
    { months: 6, price: 29 },
    { months: 12, price: 79 },
  ],
  Indie: [
    { months: 3, price: 0 },
    { months: 6, price: 145 },
    { months: 12, price: 395 },
  ],
  Team: [
    { months: 3, price: 0 },
    { months: 6, price: 290 },
    { months: 12, price: 790 },
  ],
  Squad: [
    { months: 3, price: 0 },
    { months: 6, price: 435 },
    { months: 12, price: 1185 },
  ],
  Startup: [
    { months: 3, price: 0 },
    { months: 6, price: 725 },
    { months: 12, price: 1975 },
  ],
  Business: [
    { months: 3, price: 0 },
    { months: 6, price: 1450 },
    { months: 12, price: 3950 },
  ],
}

// State
const selectedSeats = ref(5)
const selectedAdditionalQuestions = ref(0) // Index: 0 = 150 questions (default)
const selectedDataRetention = ref(0) // Index: 0 = 3 months (default)
const selectedHosting = ref('cloud') // 'cloud' or 'on-premise'

// Computed values
const currentPlan = computed(() => {
  return plans.find(plan => selectedSeats.value >= plan.minSeats && selectedSeats.value <= plan.maxSeats) || plans[0]
})

const availableAdditionalQuestions = computed(() => {
  return additionalQuestions[currentPlan.value.name] || []
})

const availableDataRetention = computed(() => {
  return dataRetention[currentPlan.value.name] || []
})

const basePlanPrice = computed(() => {
  return currentPlan.value.price
})

const additionalQuestionsPrice = computed(() => {
  const selected = availableAdditionalQuestions.value[selectedAdditionalQuestions.value]
  return selected ? selected.price : 0
})

const dataRetentionPrice = computed(() => {
  const selected = availableDataRetention.value[selectedDataRetention.value]
  return selected ? selected.price : 0
})

const totalPrice = computed(() => {
  return basePlanPrice.value + additionalQuestionsPrice.value + dataRetentionPrice.value
})

// Methods
const selectAdditionalQuestions = (index) => {
  selectedAdditionalQuestions.value = index
}

const selectDataRetention = (index) => {
  selectedDataRetention.value = index
}

const selectHosting = (type) => {
  selectedHosting.value = type
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(price)
}

const handleBookingCall = () => {
  window.open('https://cal.com/martin-joo-horizontal/horizontal-demo', '_blank');
}
</script>

<template>
  <section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-16">
        <h2 class="text-3xl lg:text-5xl font-bold text-slate-900 mb-4">
          Customize your plan
        </h2>
        <p class="text-xl text-slate-600 max-w-3xl mx-auto">
          Choose the plan that fits your team size. Scale up or down anytime.
        </p>
      </div>

      <div class="max-w-5xl mx-auto">
        <!-- Seats Selector -->
        <div class="bg-slate-50 rounded-2xl p-8 mb-8">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h3 class="text-2xl font-bold text-slate-900">{{ currentPlan.name }}</h3>
              <p class="text-slate-600 mt-1">{{ selectedSeats }} {{ selectedSeats === 1 ? 'seat' : 'seats' }}</p>
            </div>
            <div class="text-right">
              <div class="text-4xl font-bold text-slate-900">{{ formatPrice(basePlanPrice) }}</div>
              <div class="text-sm text-slate-600 mt-1">per month</div>
            </div>
          </div>

          <div class="space-y-4">
            <label class="block text-sm font-semibold text-slate-700">Number of seats</label>
            <input
              type="range"
              v-model.number="selectedSeats"
              min="1"
              max="50"
              class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
            />
            <div class="flex justify-between text-xs text-slate-500">
              <span>1 seat</span>
              <span>50 seats</span>
            </div>
          </div>

          <div class="mt-6 pt-6 border-t border-slate-200">
            <p class="text-sm text-slate-600">
              <span class="font-semibold text-slate-900">Includes:</span> 150 questions per user/month • 3 months data retention • All integrations
            </p>
          </div>
        </div>

        <!-- Questions per user/month -->
        <div class="mb-8">
          <h3 class="text-xl font-bold text-slate-900 mb-4">Questions per user/month</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-for="(option, index) in availableAdditionalQuestions"
              :key="index"
              @click="selectAdditionalQuestions(index)"
              class="relative cursor-pointer rounded-xl p-6 border-2 transition-all"
              :class="selectedAdditionalQuestions === index
                ? 'border-blue-600 bg-blue-50'
                : 'border-slate-200 bg-white hover:border-blue-300'"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="text-3xl font-bold text-slate-900">{{ option.questions }}</div>
                <div
                  v-if="selectedAdditionalQuestions === index"
                  class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center"
                >
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
              </div>
              <div class="text-sm text-slate-600 mb-3">questions/user/month</div>
              <div class="text-2xl font-bold text-slate-900">
                {{ option.price === 0 ? 'Included' : `+${formatPrice(option.price)}/mo` }}
              </div>
            </div>
          </div>
        </div>

        <!-- Data Retention -->
        <div class="mb-8">
          <h3 class="text-xl font-bold text-slate-900 mb-4">Data retention</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-for="(option, index) in availableDataRetention"
              :key="index"
              @click="selectDataRetention(index)"
              class="relative cursor-pointer rounded-xl p-6 border-2 transition-all"
              :class="selectedDataRetention === index
                ? 'border-blue-600 bg-blue-50'
                : 'border-slate-200 bg-white hover:border-blue-300'"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="text-3xl font-bold text-slate-900">{{ option.months }}</div>
                <div
                  v-if="selectedDataRetention === index"
                  class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center"
                >
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
              </div>
              <div class="text-sm text-slate-600 mb-3">months</div>
              <div class="text-2xl font-bold text-slate-900">
                {{ option.price === 0 ? 'Included' : `+${formatPrice(option.price)}/mo` }}
              </div>
            </div>
          </div>
        </div>

        <!-- Hosting -->
        <div class="mb-8">
          <h3 class="text-xl font-bold text-slate-900 mb-4">Hosting</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              @click="selectHosting('cloud')"
              class="relative cursor-pointer rounded-xl p-6 border-2 transition-all"
              :class="selectedHosting === 'cloud'
                ? 'border-blue-600 bg-blue-50'
                : 'border-slate-200 bg-white hover:border-blue-300'"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="text-2xl font-bold text-slate-900">Cloud</div>
                <div
                  v-if="selectedHosting === 'cloud'"
                  class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center"
                >
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
              </div>
              <div class="text-sm text-slate-600">Hosted and managed by us</div>
            </div>

            <div
              @click="selectHosting('on-premise')"
              class="relative cursor-pointer rounded-xl p-6 border-2 transition-all"
              :class="selectedHosting === 'on-premise'
                ? 'border-blue-600 bg-blue-50'
                : 'border-slate-200 bg-white hover:border-blue-300'"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="text-2xl font-bold text-slate-900">On-premise</div>
                <div
                  v-if="selectedHosting === 'on-premise'"
                  class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center"
                >
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
              </div>
              <div class="text-sm text-slate-600">Self-hosted in your infrastructure</div>
            </div>
          </div>
        </div>

        <!-- Pricing Summary -->
        <div v-if="selectedHosting === 'cloud'" class="bg-slate-900 rounded-2xl p-8 text-white">
          <h3 class="text-2xl font-bold mb-6">Pricing summary</h3>

          <div class="space-y-3 mb-6">
            <div class="flex justify-between items-center py-2">
              <span class="text-slate-300">{{ currentPlan.name }} plan ({{ selectedSeats }} seats)</span>
              <span class="font-semibold text-xl">{{ formatPrice(basePlanPrice) }}</span>
            </div>

            <div v-if="additionalQuestionsPrice > 0" class="flex justify-between items-center py-2">
              <span class="text-slate-300">
                Questions ({{ availableAdditionalQuestions[selectedAdditionalQuestions].questions }}/user/month)
              </span>
              <span class="font-semibold text-xl">{{ formatPrice(additionalQuestionsPrice) }}</span>
            </div>

            <div v-if="dataRetentionPrice > 0" class="flex justify-between items-center py-2">
              <span class="text-slate-300">
                Data retention ({{ availableDataRetention[selectedDataRetention].months }} months)
              </span>
              <span class="font-semibold text-xl">{{ formatPrice(dataRetentionPrice) }}</span>
            </div>
          </div>

          <div class="pt-6 border-t border-slate-700">
            <div class="flex justify-between items-center">
              <span class="text-2xl font-bold">Total</span>
              <span class="text-4xl font-bold">{{ formatPrice(totalPrice) }}</span>
            </div>
            <div class="text-slate-400 text-sm mt-1 text-right">per month</div>
          </div>

          <button class="w-full mt-8 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-lg transition-colors text-lg">
            Get Started
          </button>

          <p class="text-center text-slate-400 text-sm mt-4">
            30-day free trial • Cancel anytime
          </p>
        </div>

        <!-- Enterprise -->
        <div class="mt-8 text-center p-8 bg-slate-50 rounded-2xl">
          <h3 class="text-xl font-bold text-slate-900 mb-4">For on-premise hosting or enterprise plans, please contact us</h3>
          <button @click="handleBookingCall" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-8 py-3 rounded-lg transition-colors">
            Book a quick call
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
