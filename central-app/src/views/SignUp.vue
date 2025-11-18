<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

// Form fields
const adminUserName = ref('')
const adminUserEmail = ref('')
const companyName = ref('')
const subdomain = ref('')
const country = ref('')
const hasNativeContent = ref(null)

// Loading state
const isLoading = ref(false)

// Error state
const error = ref('')

// All countries (ISO 3166-1 alpha-2)
const countries = [
  { value: 'AF', label: 'Afghanistan' },
  { value: 'AX', label: 'Åland Islands' },
  { value: 'AL', label: 'Albania' },
  { value: 'DZ', label: 'Algeria' },
  { value: 'AS', label: 'American Samoa' },
  { value: 'AD', label: 'Andorra' },
  { value: 'AO', label: 'Angola' },
  { value: 'AI', label: 'Anguilla' },
  { value: 'AQ', label: 'Antarctica' },
  { value: 'AG', label: 'Antigua and Barbuda' },
  { value: 'AR', label: 'Argentina' },
  { value: 'AM', label: 'Armenia' },
  { value: 'AW', label: 'Aruba' },
  { value: 'AU', label: 'Australia' },
  { value: 'AT', label: 'Austria' },
  { value: 'AZ', label: 'Azerbaijan' },
  { value: 'BS', label: 'Bahamas' },
  { value: 'BH', label: 'Bahrain' },
  { value: 'BD', label: 'Bangladesh' },
  { value: 'BB', label: 'Barbados' },
  { value: 'BY', label: 'Belarus' },
  { value: 'BE', label: 'Belgium' },
  { value: 'BZ', label: 'Belize' },
  { value: 'BJ', label: 'Benin' },
  { value: 'BM', label: 'Bermuda' },
  { value: 'BT', label: 'Bhutan' },
  { value: 'BO', label: 'Bolivia' },
  { value: 'BQ', label: 'Bonaire, Sint Eustatius and Saba' },
  { value: 'BA', label: 'Bosnia and Herzegovina' },
  { value: 'BW', label: 'Botswana' },
  { value: 'BV', label: 'Bouvet Island' },
  { value: 'BR', label: 'Brazil' },
  { value: 'IO', label: 'British Indian Ocean Territory' },
  { value: 'BN', label: 'Brunei Darussalam' },
  { value: 'BG', label: 'Bulgaria' },
  { value: 'BF', label: 'Burkina Faso' },
  { value: 'BI', label: 'Burundi' },
  { value: 'CV', label: 'Cabo Verde' },
  { value: 'KH', label: 'Cambodia' },
  { value: 'CM', label: 'Cameroon' },
  { value: 'CA', label: 'Canada' },
  { value: 'KY', label: 'Cayman Islands' },
  { value: 'CF', label: 'Central African Republic' },
  { value: 'TD', label: 'Chad' },
  { value: 'CL', label: 'Chile' },
  { value: 'CN', label: 'China' },
  { value: 'CX', label: 'Christmas Island' },
  { value: 'CC', label: 'Cocos (Keeling) Islands' },
  { value: 'CO', label: 'Colombia' },
  { value: 'KM', label: 'Comoros' },
  { value: 'CG', label: 'Congo' },
  { value: 'CD', label: 'Congo, Democratic Republic of the' },
  { value: 'CK', label: 'Cook Islands' },
  { value: 'CR', label: 'Costa Rica' },
  { value: 'CI', label: 'Côte d\'Ivoire' },
  { value: 'HR', label: 'Croatia' },
  { value: 'CU', label: 'Cuba' },
  { value: 'CW', label: 'Curaçao' },
  { value: 'CY', label: 'Cyprus' },
  { value: 'CZ', label: 'Czechia' },
  { value: 'DK', label: 'Denmark' },
  { value: 'DJ', label: 'Djibouti' },
  { value: 'DM', label: 'Dominica' },
  { value: 'DO', label: 'Dominican Republic' },
  { value: 'EC', label: 'Ecuador' },
  { value: 'EG', label: 'Egypt' },
  { value: 'SV', label: 'El Salvador' },
  { value: 'GQ', label: 'Equatorial Guinea' },
  { value: 'ER', label: 'Eritrea' },
  { value: 'EE', label: 'Estonia' },
  { value: 'SZ', label: 'Eswatini' },
  { value: 'ET', label: 'Ethiopia' },
  { value: 'FK', label: 'Falkland Islands (Malvinas)' },
  { value: 'FO', label: 'Faroe Islands' },
  { value: 'FJ', label: 'Fiji' },
  { value: 'FI', label: 'Finland' },
  { value: 'FR', label: 'France' },
  { value: 'GF', label: 'French Guiana' },
  { value: 'PF', label: 'French Polynesia' },
  { value: 'TF', label: 'French Southern Territories' },
  { value: 'GA', label: 'Gabon' },
  { value: 'GM', label: 'Gambia' },
  { value: 'GE', label: 'Georgia' },
  { value: 'DE', label: 'Germany' },
  { value: 'GH', label: 'Ghana' },
  { value: 'GI', label: 'Gibraltar' },
  { value: 'GR', label: 'Greece' },
  { value: 'GL', label: 'Greenland' },
  { value: 'GD', label: 'Grenada' },
  { value: 'GP', label: 'Guadeloupe' },
  { value: 'GU', label: 'Guam' },
  { value: 'GT', label: 'Guatemala' },
  { value: 'GG', label: 'Guernsey' },
  { value: 'GN', label: 'Guinea' },
  { value: 'GW', label: 'Guinea-Bissau' },
  { value: 'GY', label: 'Guyana' },
  { value: 'HT', label: 'Haiti' },
  { value: 'HM', label: 'Heard Island and McDonald Islands' },
  { value: 'VA', label: 'Holy See' },
  { value: 'HN', label: 'Honduras' },
  { value: 'HK', label: 'Hong Kong' },
  { value: 'HU', label: 'Hungary' },
  { value: 'IS', label: 'Iceland' },
  { value: 'IN', label: 'India' },
  { value: 'ID', label: 'Indonesia' },
  { value: 'IR', label: 'Iran' },
  { value: 'IQ', label: 'Iraq' },
  { value: 'IE', label: 'Ireland' },
  { value: 'IM', label: 'Isle of Man' },
  { value: 'IL', label: 'Israel' },
  { value: 'IT', label: 'Italy' },
  { value: 'JM', label: 'Jamaica' },
  { value: 'JP', label: 'Japan' },
  { value: 'JE', label: 'Jersey' },
  { value: 'JO', label: 'Jordan' },
  { value: 'KZ', label: 'Kazakhstan' },
  { value: 'KE', label: 'Kenya' },
  { value: 'KI', label: 'Kiribati' },
  { value: 'KP', label: 'Korea, Democratic People\'s Republic of' },
  { value: 'KR', label: 'Korea, Republic of' },
  { value: 'KW', label: 'Kuwait' },
  { value: 'KG', label: 'Kyrgyzstan' },
  { value: 'LA', label: 'Lao People\'s Democratic Republic' },
  { value: 'LV', label: 'Latvia' },
  { value: 'LB', label: 'Lebanon' },
  { value: 'LS', label: 'Lesotho' },
  { value: 'LR', label: 'Liberia' },
  { value: 'LY', label: 'Libya' },
  { value: 'LI', label: 'Liechtenstein' },
  { value: 'LT', label: 'Lithuania' },
  { value: 'LU', label: 'Luxembourg' },
  { value: 'MO', label: 'Macao' },
  { value: 'MG', label: 'Madagascar' },
  { value: 'MW', label: 'Malawi' },
  { value: 'MY', label: 'Malaysia' },
  { value: 'MV', label: 'Maldives' },
  { value: 'ML', label: 'Mali' },
  { value: 'MT', label: 'Malta' },
  { value: 'MH', label: 'Marshall Islands' },
  { value: 'MQ', label: 'Martinique' },
  { value: 'MR', label: 'Mauritania' },
  { value: 'MU', label: 'Mauritius' },
  { value: 'YT', label: 'Mayotte' },
  { value: 'MX', label: 'Mexico' },
  { value: 'FM', label: 'Micronesia, Federated States of' },
  { value: 'MD', label: 'Moldova' },
  { value: 'MC', label: 'Monaco' },
  { value: 'MN', label: 'Mongolia' },
  { value: 'ME', label: 'Montenegro' },
  { value: 'MS', label: 'Montserrat' },
  { value: 'MA', label: 'Morocco' },
  { value: 'MZ', label: 'Mozambique' },
  { value: 'MM', label: 'Myanmar' },
  { value: 'NA', label: 'Namibia' },
  { value: 'NR', label: 'Nauru' },
  { value: 'NP', label: 'Nepal' },
  { value: 'NL', label: 'Netherlands' },
  { value: 'NC', label: 'New Caledonia' },
  { value: 'NZ', label: 'New Zealand' },
  { value: 'NI', label: 'Nicaragua' },
  { value: 'NE', label: 'Niger' },
  { value: 'NG', label: 'Nigeria' },
  { value: 'NU', label: 'Niue' },
  { value: 'NF', label: 'Norfolk Island' },
  { value: 'MK', label: 'North Macedonia' },
  { value: 'MP', label: 'Northern Mariana Islands' },
  { value: 'NO', label: 'Norway' },
  { value: 'OM', label: 'Oman' },
  { value: 'PK', label: 'Pakistan' },
  { value: 'PW', label: 'Palau' },
  { value: 'PS', label: 'Palestine, State of' },
  { value: 'PA', label: 'Panama' },
  { value: 'PG', label: 'Papua New Guinea' },
  { value: 'PY', label: 'Paraguay' },
  { value: 'PE', label: 'Peru' },
  { value: 'PH', label: 'Philippines' },
  { value: 'PN', label: 'Pitcairn' },
  { value: 'PL', label: 'Poland' },
  { value: 'PT', label: 'Portugal' },
  { value: 'PR', label: 'Puerto Rico' },
  { value: 'QA', label: 'Qatar' },
  { value: 'RE', label: 'Réunion' },
  { value: 'RO', label: 'Romania' },
  { value: 'RU', label: 'Russian Federation' },
  { value: 'RW', label: 'Rwanda' },
  { value: 'BL', label: 'Saint Barthélemy' },
  { value: 'SH', label: 'Saint Helena, Ascension and Tristan da Cunha' },
  { value: 'KN', label: 'Saint Kitts and Nevis' },
  { value: 'LC', label: 'Saint Lucia' },
  { value: 'MF', label: 'Saint Martin (French part)' },
  { value: 'PM', label: 'Saint Pierre and Miquelon' },
  { value: 'VC', label: 'Saint Vincent and the Grenadines' },
  { value: 'WS', label: 'Samoa' },
  { value: 'SM', label: 'San Marino' },
  { value: 'ST', label: 'Sao Tome and Principe' },
  { value: 'SA', label: 'Saudi Arabia' },
  { value: 'SN', label: 'Senegal' },
  { value: 'RS', label: 'Serbia' },
  { value: 'SC', label: 'Seychelles' },
  { value: 'SL', label: 'Sierra Leone' },
  { value: 'SG', label: 'Singapore' },
  { value: 'SX', label: 'Sint Maarten (Dutch part)' },
  { value: 'SK', label: 'Slovakia' },
  { value: 'SI', label: 'Slovenia' },
  { value: 'SB', label: 'Solomon Islands' },
  { value: 'SO', label: 'Somalia' },
  { value: 'ZA', label: 'South Africa' },
  { value: 'GS', label: 'South Georgia and the South Sandwich Islands' },
  { value: 'SS', label: 'South Sudan' },
  { value: 'ES', label: 'Spain' },
  { value: 'LK', label: 'Sri Lanka' },
  { value: 'SD', label: 'Sudan' },
  { value: 'SR', label: 'Suriname' },
  { value: 'SJ', label: 'Svalbard and Jan Mayen' },
  { value: 'SE', label: 'Sweden' },
  { value: 'CH', label: 'Switzerland' },
  { value: 'SY', label: 'Syrian Arab Republic' },
  { value: 'TW', label: 'Taiwan' },
  { value: 'TJ', label: 'Tajikistan' },
  { value: 'TZ', label: 'Tanzania' },
  { value: 'TH', label: 'Thailand' },
  { value: 'TL', label: 'Timor-Leste' },
  { value: 'TG', label: 'Togo' },
  { value: 'TK', label: 'Tokelau' },
  { value: 'TO', label: 'Tonga' },
  { value: 'TT', label: 'Trinidad and Tobago' },
  { value: 'TN', label: 'Tunisia' },
  { value: 'TR', label: 'Turkey' },
  { value: 'TM', label: 'Turkmenistan' },
  { value: 'TC', label: 'Turks and Caicos Islands' },
  { value: 'TV', label: 'Tuvalu' },
  { value: 'UG', label: 'Uganda' },
  { value: 'UA', label: 'Ukraine' },
  { value: 'AE', label: 'United Arab Emirates' },
  { value: 'GB', label: 'United Kingdom' },
  { value: 'UM', label: 'United States Minor Outlying Islands' },
  { value: 'US', label: 'United States' },
  { value: 'UY', label: 'Uruguay' },
  { value: 'UZ', label: 'Uzbekistan' },
  { value: 'VU', label: 'Vanuatu' },
  { value: 'VE', label: 'Venezuela' },
  { value: 'VN', label: 'Vietnam' },
  { value: 'VG', label: 'Virgin Islands (British)' },
  { value: 'VI', label: 'Virgin Islands (U.S.)' },
  { value: 'WF', label: 'Wallis and Futuna' },
  { value: 'EH', label: 'Western Sahara' },
  { value: 'YE', label: 'Yemen' },
  { value: 'ZM', label: 'Zambia' },
  { value: 'ZW', label: 'Zimbabwe' }
]

// Check if we should show the native content question
const shouldShowNativeContentQuestion = computed(() => {
  return country.value && country.value !== 'US' && country.value !== 'GB' && country.value !== 'CA'
})

// Auto-generate subdomain from company name
const handleCompanyNameInput = () => {
  subdomain.value = generateSubdomain(companyName.value)
}

const generateSubdomain = (name) => {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
}

// Validate subdomain
const isSubdomainValid = computed(() => {
  if (!subdomain.value) return true
  return /^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/.test(subdomain.value)
})

// Form submission
const handleSubmit = async () => {
  error.value = ''

  if (!adminUserName.value.trim()) {
    error.value = 'Please enter your name'
    return
  }

  if (!adminUserEmail.value.trim()) {
    error.value = 'Please enter your email'
    return
  }

  // Basic email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(adminUserEmail.value)) {
    error.value = 'Please enter a valid email address'
    return
  }

  if (!companyName.value.trim()) {
    error.value = 'Please enter your company name'
    return
  }

  if (!subdomain.value.trim()) {
    error.value = 'Please enter a subdomain'
    return
  }

  if (!isSubdomainValid.value) {
    error.value = 'Subdomain can only contain lowercase letters, numbers, and hyphens'
    return
  }

  if (!route.query.number_of_seats) {
    error.value = 'Please select your team size'
    return
  }

  if (!country.value) {
    error.value = 'Please select your country'
    return
  }

  if (shouldShowNativeContentQuestion.value && hasNativeContent.value === null) {
    error.value = 'Please answer whether you have content in your native language'
    return
  }

  isLoading.value = true

  try {
    const response = await fetch(
      '/api/tenants',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          admin_user_name: adminUserName.value,
          admin_user_email: adminUserEmail.value,
          company_name: companyName.value,
          subdomain: subdomain.value,
          number_of_seats: route.query.number_of_seats,
          questions_per_month: route.query.questions_per_month,
          data_retention: route.query.data_retention,
          country: country.value,
          has_native_content: hasNativeContent.value ?? false,
        })
      }    
    )

    if (response.status !== 201) {
      error.value = 'Something went wrong while creating your instance. Please contact us at hello@horizontal.app'
      return
    }

    if (window.location.href.includes('horizontal.app')) {
      window.location.href = `https://${subdomain.value}.horizontal.app/auth`  
    } else {
      window.location.href = `http://${subdomain.value}.localhost:9996/auth`  
    }
  } catch (err) {
    error.value = 'Something went wrong while creating your instance. Please contact us at hello@horizontal.app'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 py-12 px-4">
    <div class="max-w-2xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-slate-900 mb-2">
          Welcome to Horizontal
        </h1>
        <p class="text-slate-600">
          Let's get your team set up in just a few steps
        </p>
      </div>

      <!-- Form Card -->
      <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-8">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Your Name -->
          <div>
            <label for="admin-name" class="block text-sm font-medium text-slate-700 mb-2">
              Your name
            </label>
            <input
              id="admin-name"
              v-model="adminUserName"
              type="text"
              required
              placeholder="John Doe"
              class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
              :disabled="isLoading"
            />
          </div>

          <!-- Your Email -->
          <div>
            <label for="admin-email" class="block text-sm font-medium text-slate-700 mb-2">
              Your email
            </label>
            <input
              id="admin-email"
              v-model="adminUserEmail"
              type="email"
              required
              placeholder="john@your-company.com"
              class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
              :disabled="isLoading"
            />
          </div>

          <!-- Company Name -->
          <div>
            <label for="company-name" class="block text-sm font-medium text-slate-700 mb-2">
              Company Name
            </label>
            <input
              id="company-name"
              v-model="companyName"
              @input="handleCompanyNameInput"
              type="text"
              required
              placeholder="Your Company Inc."
              class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
              :disabled="isLoading"
            />
          </div>

          <!-- Subdomain -->
          <div>
            <label for="subdomain" class="block text-sm font-medium text-slate-700 mb-2">
              Choose your subdomain
            </label>
            <div class="flex items-center">
              <input
                id="subdomain"
                v-model="subdomain"
                type="text"
                required
                placeholder="your-company"
                class="flex-1 px-4 py-2.5 border border-slate-300 rounded-l-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
                :disabled="isLoading"
                :class="{ 'border-red-300 focus:ring-red-500 focus:border-red-500': !isSubdomainValid }"
              />
              <span class="inline-flex items-center px-4 py-2.5 border border-l-0 border-slate-300 rounded-r-lg bg-slate-50 text-slate-600 text-sm">
                .horizontal.app
              </span>
            </div>
            <p v-if="!isSubdomainValid" class="mt-2 text-sm text-red-600">
              Subdomain can only contain lowercase letters, numbers, and hyphens
            </p>
            <p v-else class="mt-2 text-sm text-slate-500">
              Your team will access Horizontal at <span class="font-medium text-slate-700">{{ subdomain || 'your-company' }}.horizontal.app</span>
            </p>
          </div>

          <!-- Country -->
          <div>
            <label for="country" class="block text-sm font-medium text-slate-700 mb-2">
              Country
            </label>
            <select
              id="country"
              v-model="country"
              required
              class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors"
              :disabled="isLoading"
            >
              <option value="" disabled>Select country</option>
              <option v-for="countryOption in countries" :key="countryOption.value" :value="countryOption.value">
                {{ countryOption.label }}
              </option>
            </select>
          </div>

          <!-- Native Content Question (Conditional) -->
          <div v-if="shouldShowNativeContentQuestion" class="bg-slate-50 border border-slate-200 rounded-lg p-6">
            <label class="block text-sm font-medium text-slate-900 mb-4">
              Do you have content (messages, docs, tasks) written in your native language?
            </label>
            <div class="space-y-3">
              <label class="flex items-center cursor-pointer">
                <input
                  type="radio"
                  :value="true"
                  v-model="hasNativeContent"
                  class="h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-600"
                  :disabled="isLoading"
                />
                <span class="ml-3 text-sm text-slate-700">Yes, we have content in our native language</span>
              </label>
              <label class="flex items-center cursor-pointer">
                <input
                  type="radio"
                  :value="false"
                  v-model="hasNativeContent"
                  class="h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-600"
                  :disabled="isLoading"
                />
                <span class="ml-3 text-sm text-slate-700">No, all our content is in English</span>
              </label>
            </div>
            <p class="mt-3 text-xs text-slate-500">
              This helps us optimize search for your language
            </p>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ error }}
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isLoading || !isSubdomainValid"
            class="w-full bg-sky-600 hover:bg-sky-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
          >
            <svg v-if="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isLoading ? 'Creating your instance...' : 'Create your instance' }}
          </button>

          <!-- Back to Landing -->
          <div class="text-center">
            <button
              type="button"
              @click="router.push('/')"
              class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
              :disabled="isLoading"
            >
              Back to home
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
