<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  XMarkIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  CheckIcon,
  DocumentTextIcon,
  CodeBracketIcon,
  CloudIcon,
  WrenchScrewdriverIcon,
  ShieldCheckIcon,
  PencilSquareIcon,
  CommandLineIcon,
} from '@heroicons/vue/24/outline'
import FieldTooltip from '@/components/FieldTooltip.vue'
import TipTapEditor from '@/components/TipTapEditor.vue'
import api from '@/core/api/axios'

const props = defineProps({
  show: Boolean,
  clients: { type: Array, default: () => [] },
  templates: { type: Array, default: () => [] },
  senderSettings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'save'])

const { t } = useI18n()

const currentStep = ref(1)
const totalSteps = 5

// Form data
const form = ref({
  // Step 1
  contract_type: 'license',
  language: 'de',
  is_b2c: 0,
  // Step 2
  client_id: null,
  party_a_name: '',
  party_a_company: '',
  party_a_address: '',
  party_a_email: '',
  party_a_vat_id: '',
  party_b_name: '',
  party_b_company: '',
  party_b_address: '',
  party_b_email: '',
  party_b_vat_id: '',
  // Step 3 - dynamic based on type
  title: '',
  variables_data: {},
  // Step 4
  start_date: '',
  end_date: '',
  auto_renewal: 0,
  renewal_period: 'yearly',
  notice_period_days: 30,
  min_term_months: 0,
  cancellation_type: 'both',
  total_value: 0,
  currency: 'EUR',
  payment_schedule: 'one-time',
  payment_due_days: 14,
  early_payment_discount: 0,
  // Step 5
  governing_law: 'DE',
  jurisdiction: '',
  include_nda_clause: 1,
  exclude_cisg: true,
  as_is_clause: false,
  warranty_duration_months: 12,
  liability_cap_factor: 1,
  customer_country: 'DE',
  // Custom contract
  content_html: '',
  save_as_template: false,
  template_name: '',
})

const contractTypes = computed(() => [
  { value: 'license', label: t('contracts.softwareLicenseLabel'), description: t('contracts.softwareLicenseAgreement'), icon: DocumentTextIcon, color: 'text-blue-300', bg: 'bg-blue-500/10 border-blue-500/30' },
  { value: 'development', label: t('contracts.developmentLabel'), description: t('contracts.customSoftwareDevelopment'), icon: CodeBracketIcon, color: 'text-purple-300', bg: 'bg-purple-500/10 border-purple-500/30' },
  { value: 'saas', label: 'SaaS', description: 'Software as a Service', icon: CloudIcon, color: 'text-cyan-300', bg: 'bg-cyan-500/10 border-cyan-500/30' },
  { value: 'maintenance', label: t('contracts.maintenanceLabelShort'), description: t('contracts.maintenanceSupportDesc'), icon: WrenchScrewdriverIcon, color: 'text-amber-300', bg: 'bg-amber-500/10 border-amber-500/30' },
  { value: 'nda', label: t('contracts.ndaLabel'), description: t('contracts.ndaDescription'), icon: ShieldCheckIcon, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30' },
  { value: 'source_code_purchase', label: t('contracts.sourceCodePurchaseLabel'), description: t('contracts.purchaseSourceCodeIp'), icon: CommandLineIcon, color: 'text-orange-300', bg: 'bg-orange-500/10 border-orange-500/30' },
  { value: 'custom', label: t('contracts.custom'), description: t('contracts.customContractDescription'), icon: PencilSquareIcon, color: 'text-rose-300', bg: 'bg-rose-500/10 border-rose-500/30' },
])

// Custom templates
const customTemplates = ref([])
const selectedTemplateId = ref(null)

async function loadCustomTemplates() {
  try {
    const res = await api.get('/api/v1/contract-templates?contract_type=custom')
    customTemplates.value = res.data?.data?.items || []
  } catch { /* ignore */ }
}

function selectCustomTemplate(templateId) {
  selectedTemplateId.value = templateId
  if (templateId) {
    const tpl = customTemplates.value.find(t => t.id === templateId)
    if (tpl) {
      form.value.content_html = tpl.content_html || ''
      if (!form.value.title) form.value.title = tpl.name || ''
    }
  } else {
    form.value.content_html = ''
  }
}

const languages = computed(() => [
  { value: 'de', label: t('contracts.germanLanguage') },
  { value: 'en', label: 'English' },
])

const customerTypes = computed(() => [
  { value: 0, label: t('contracts.b2bCustomer') },
  { value: 1, label: t('contracts.b2cPrivateCustomer') },
])

const paymentSchedules = computed(() => [
  { value: 'one-time', label: t('contracts.paymentOneTime') },
  { value: 'monthly', label: t('contracts.paymentMonthly') },
  { value: 'quarterly', label: t('contracts.paymentQuarterly') },
  { value: 'yearly', label: t('contracts.paymentYearly') },
])

const governingLaws = computed(() => [
  { value: 'DE', label: t('contracts.germanLaw') },
  { value: 'AT', label: t('contracts.austrianLaw') },
  { value: 'CH', label: t('contracts.swissLaw') },
  { value: 'DK', label: t('contracts.danishLaw') },
  { value: 'EU', label: t('contracts.euLaw') },
])

const renewalPeriods = [
  { value: 'monthly', label: t('cron.monthly') },
  { value: 'yearly', label: t('contracts.yearly') },
]

// Step labels
const stepLabels = computed(() => [t('contracts.contractType'), t('contracts.stepParties'), t('contracts.stepDetails'), t('contracts.stepTermPayment'), t('contracts.stepLegal')])

// Populate sender data from settings
function initForm() {
  currentStep.value = 1
  const today = new Date().toISOString().split('T')[0]
  form.value = {
    contract_type: 'license',
    language: 'de',
    is_b2c: 0,
    client_id: null,
    party_a_name: props.senderSettings?.sender_name || '',
    party_a_company: props.senderSettings?.sender_company || '',
    party_a_address: props.senderSettings?.sender_address || '',
    party_a_email: props.senderSettings?.sender_email || '',
    party_a_vat_id: props.senderSettings?.sender_vat_id || '',
    party_b_name: '',
    party_b_company: '',
    party_b_address: '',
    party_b_email: '',
    party_b_vat_id: '',
    title: '',
    variables_data: getDefaultVariables('license'),
    start_date: today,
    end_date: '',
    auto_renewal: 0,
    renewal_period: 'yearly',
    notice_period_days: 30,
    total_value: 0,
    currency: 'EUR',
    payment_schedule: 'one-time',
    governing_law: 'DE',
    jurisdiction: '',
    include_nda_clause: 1,
    content_html: '',
    save_as_template: false,
    template_name: '',
  }
  selectedTemplateId.value = null
}

function getDefaultVariables(type) {
  switch (type) {
    case 'license':
      return {
        software_name: '',
        software_version: '',
        license_type: 'simple',
        license_model: 'perpetual',
        installation_type: 'on_premise',
        max_users: 1,
        territory: 'worldwide',
        source_code_access: false,
        modification_rights: false,
        updates_included: true,
        updates_duration_months: 12,
        support_level: 'basic',
        backup_copies: true,
        affiliate_use: false,
        api_access: false,
        data_processing: false,
        audit_rights: true,
        open_source_included: false,
      }
    case 'development':
      return {
        project_description: '',
        milestones: [{ name: '', date: '', amount: 0 }],
        pricing_model: 'fixed',
        hourly_rate: 0,
        acceptance_procedure: '',
        warranty_months: 12,
        documentation_required: true,
        deployment_support: false,
      }
    case 'saas':
      return {
        service_description: '',
        sla_uptime: 99.5,
        sla_credit: true,
        subscription_model: 'monthly',
        price_per_period: 0,
        max_users: 0,
        storage_gb: 0,
        data_location: 'DE',
        support_included: true,
        support_level: 'basic',
      }
    case 'maintenance':
      return {
        maintained_software: '',
        support_hours_monthly: 10,
        response_time: '24h',
        included_patches: true,
        included_minor_updates: true,
        included_major_updates: false,
        remote_access_required: false,
        emergency_support: false,
      }
    case 'nda':
      return {
        nda_type: 'mutual',
        confidential_info_description: '',
        duration_years: 3,
        penalty_amount: 0,
      }
    case 'source_code_purchase':
      return {
        software_name: '',
        software_version: '',
        source_code_scope: '',
        delivery_type: 'repository',
        repository_platform: 'github',
        repository_access_type: 'transfer',
        includes_documentation: true,
        includes_deployment_support: false,
        ip_transfer_type: 'exclusive',
        open_source_included: false,
        warranty_months: 6,
      }
    default:
      return {}
  }
}

watch(() => props.show, (val) => {
  if (val) initForm()
})

watch(() => form.value.contract_type, (newType) => {
  form.value.variables_data = getDefaultVariables(newType)
  // Auto-set title
  const typeLabels = { license: t('contracts.softwareLicenseContract'), development: t('contracts.developmentContract'), saas: t('contracts.saasContract'), maintenance: t('contracts.maintenanceContract'), nda: t('contracts.ndaContract'), source_code_purchase: t('contracts.sourceCodePurchaseContract'), custom: t('contracts.individualContract') }
  if (!form.value.title || Object.values(typeLabels).includes(form.value.title)) {
    form.value.title = typeLabels[newType] || ''
  }
  // Load custom templates when custom type is selected
  if (newType === 'custom') {
    loadCustomTemplates()
    form.value.content_html = ''
    selectedTemplateId.value = null
  }
})

// Client selection auto-fill
watch(() => form.value.client_id, (clientId) => {
  if (clientId) {
    const client = props.clients.find(c => c.id === clientId)
    if (client) {
      form.value.party_b_name = client.name || ''
      form.value.party_b_company = client.company || ''
      form.value.party_b_email = client.email || ''
      form.value.party_b_vat_id = client.vat_id || ''
      const addr = [client.address_line1, client.address_line2, `${client.postal_code || ''} ${client.city || ''}`.trim(), client.country].filter(Boolean).join('\n')
      form.value.party_b_address = addr
    }
  }
})

const canNext = computed(() => {
  switch (currentStep.value) {
    case 1: return !!form.value.contract_type
    case 2: return !!form.value.party_a_name && !!form.value.party_b_name
    case 3: return !!form.value.title
    case 4: return !!form.value.start_date
    case 5: return true
    default: return true
  }
})

function nextStep() {
  if (currentStep.value < totalSteps && canNext.value) currentStep.value++
}

function prevStep() {
  if (currentStep.value > 1) currentStep.value--
}

function addMilestone() {
  if (form.value.variables_data.milestones) {
    form.value.variables_data.milestones.push({ name: '', date: '', amount: 0 })
  }
}

function removeMilestone(index) {
  if (form.value.variables_data.milestones?.length > 1) {
    form.value.variables_data.milestones.splice(index, 1)
  }
}

function handleSubmit() {
  // Merge form-level fields into variables_data so backend template resolver can use them
  const merged = {
    ...form.value,
    variables_data: {
      ...form.value.variables_data,
      is_b2c: form.value.is_b2c,
      governing_law: form.value.governing_law,
      payment_schedule: form.value.payment_schedule,
      cancellation_type: form.value.cancellation_type,
      min_term_months: form.value.min_term_months,
      payment_due_days: form.value.payment_due_days,
      early_payment_discount: form.value.early_payment_discount,
      exclude_cisg: form.value.exclude_cisg,
      as_is_clause: form.value.as_is_clause && !form.value.is_b2c,
      warranty_duration_months: form.value.warranty_duration_months,
      liability_cap_factor: form.value.liability_cap_factor,
      customer_country: form.value.customer_country,
    },
  }
  // For custom contracts, include content_html directly
  if (form.value.contract_type === 'custom') {
    merged.content_html = form.value.content_html
  }
  emit('save', merged)
}

const isLicense = computed(() => form.value.contract_type === 'license')
const isDevelopment = computed(() => form.value.contract_type === 'development')
const isSaas = computed(() => form.value.contract_type === 'saas')
const isMaintenance = computed(() => form.value.contract_type === 'maintenance')
const isNda = computed(() => form.value.contract_type === 'nda')
const isSourceCodePurchase = computed(() => form.value.contract_type === 'source_code_purchase')
const isCustom = computed(() => form.value.contract_type === 'custom')
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div v-if="show" class="modal w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <!-- Header -->
            <div class="sticky top-0 bg-white/[0.04] px-6 py-4 border-b border-white/[0.06] flex items-center justify-between z-10">
              <div>
                <h2 class="text-lg font-bold text-white">{{ $t('contracts.newContract') }}</h2>
                <div class="text-xs text-gray-400 mt-0.5">{{ $t('contracts.stepOf', { current: currentStep, total: totalSteps }) }} — {{ stepLabels[currentStep - 1] }}</div>
              </div>
              <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <!-- Progress Bar -->
            <div class="px-6 pt-4">
              <div class="flex gap-1.5">
                <div
                  v-for="step in totalSteps"
                  :key="step"
                  class="flex-1 h-1.5 rounded-full transition-colors"
                  :class="step <= currentStep ? 'bg-primary-500' : 'bg-white/[0.06]'"
                ></div>
              </div>
            </div>

            <div class="p-6 space-y-5">
              <!-- Step 1: Contract Type & Language -->
              <div v-if="currentStep === 1">
                <!-- Contract Type -->
                <div>
                  <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                    {{ $t('contracts.contractType') }}
                    <FieldTooltip>{{ $t('contracts.hintContractType') }}</FieldTooltip>
                  </label>
                  <div class="grid grid-cols-4 gap-2">
                    <button
                      v-for="ct in contractTypes"
                      :key="ct.value"
                      type="button"
                      @click="form.contract_type = ct.value"
                      class="flex flex-col items-center gap-1.5 p-3 rounded-xl border text-center transition-all"
                      :class="form.contract_type === ct.value
                        ? ct.bg + ' ' + ct.color + ' ring-2 ring-offset-1 ring-offset-black ring-' + ct.color.replace('text-', '').replace('-300', '-400')
                        : 'bg-white/[0.04] border-white/[0.06] text-gray-500 hover:text-gray-300 hover:border-white/[0.08]'"
                    >
                      <component :is="ct.icon" class="w-5 h-5" />
                      <span class="text-[11px] font-semibold leading-tight">{{ ct.label }}</span>
                    </button>
                  </div>

                  <!-- Custom template picker -->
                  <div v-if="isCustom && customTemplates.length > 0" class="mt-4">
                    <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                      {{ $t('contracts.useTemplate') }}
                      <FieldTooltip>{{ $t('contracts.hintTemplate') }}</FieldTooltip>
                    </label>
                    <select
                      :value="selectedTemplateId"
                      @change="selectCustomTemplate($event.target.value || null)"
                      class="input"
                    >
                      <option value="">{{ $t('contracts.startEmpty') }}</option>
                      <option v-for="tpl in customTemplates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
                    </select>
                  </div>
                </div>

                <!-- Language -->
                <div class="mt-5">
                  <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                    {{ $t('contracts.languageLabel') }}
                    <FieldTooltip>{{ $t('contracts.hintLanguage') }}</FieldTooltip>
                  </label>
                  <div class="flex gap-2">
                    <button
                      v-for="lang in languages"
                      :key="lang.value"
                      type="button"
                      @click="form.language = lang.value"
                      class="flex-1 py-2.5 rounded-lg text-sm font-medium transition-colors border"
                      :class="form.language === lang.value
                        ? 'bg-primary-600 border-primary-500 text-white'
                        : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white hover:border-white/[0.08]'"
                    >{{ lang.label }}</button>
                  </div>
                </div>

                <!-- Customer Type -->
                <div class="mt-5">
                  <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                    {{ $t('contracts.customerTypeLabel') }}
                    <FieldTooltip>{{ $t('contracts.hintCustomerType') }}</FieldTooltip>
                  </label>
                  <div class="flex gap-2">
                    <button
                      v-for="ct in customerTypes"
                      :key="ct.value"
                      type="button"
                      @click="form.is_b2c = ct.value"
                      class="flex-1 py-2.5 rounded-lg text-sm font-medium transition-colors border"
                      :class="form.is_b2c === ct.value
                        ? 'bg-primary-600 border-primary-500 text-white'
                        : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white hover:border-white/[0.08]'"
                    >{{ ct.label }}</button>
                  </div>
                  <p v-if="form.is_b2c" class="text-xs text-amber-400 mt-2">{{ $t('contracts.noteB2cProtection') }}</p>
                </div>
              </div>

              <!-- Step 2: Contract Parties -->
              <div v-if="currentStep === 2">
                <!-- Party A (Sender) -->
                <div class="mb-6">
                  <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-1.5">
                    {{ $t('contracts.contractorYou') }}
                    <FieldTooltip>{{ $t('contracts.hintSenderData') }}</FieldTooltip>
                  </h3>
                  <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="label">{{ $t('contracts.nameRequired') }}</label>
                        <input v-model="form.party_a_name" type="text" class="input" :placeholder="$t('contracts.yourName')" required />
                      </div>
                      <div>
                        <label class="label">{{ $t('contracts.companyLabel') }}</label>
                        <input v-model="form.party_a_company" type="text" class="input" :placeholder="$t('contracts.companyNamePlaceholder')" />
                      </div>
                    </div>
                    <div>
                      <label class="label">{{ $t('contracts.addressLabel') }}</label>
                      <textarea v-model="form.party_a_address" class="input" rows="2" :placeholder="$t('contracts.addressPlaceholder')"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="label">{{ $t('contracts.emailLabel') }}</label>
                        <input v-model="form.party_a_email" type="email" class="input" />
                      </div>
                      <div>
                        <label class="label flex items-center gap-1.5">
                          {{ $t('contracts.vatIdLabel') }}
                          <FieldTooltip>{{ $t('contracts.hintSenderVatId') }}</FieldTooltip>
                        </label>
                        <input v-model="form.party_a_vat_id" type="text" class="input" placeholder="DE123456789" />
                      </div>
                    </div>
                  </div>
                </div>

                <hr class="border-white/[0.06]" />

                <!-- Party B (Client) -->
                <div class="mt-6">
                  <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-1.5">
                    {{ $t('contracts.clientCustomer') }}
                    <FieldTooltip>{{ $t('contracts.hintClientData') }}</FieldTooltip>
                  </h3>
                  <div class="mb-3">
                    <label class="label">{{ $t('contracts.selectFromClients') }}</label>
                    <select v-model="form.client_id" class="input">
                      <option :value="null">{{ $t('contracts.enterManually') }}</option>
                      <option v-for="c in clients" :key="c.id" :value="c.id">
                        {{ c.name }}{{ c.company ? ' — ' + c.company : '' }}
                      </option>
                    </select>
                  </div>
                  <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="label">{{ $t('contracts.nameRequired') }}</label>
                        <input v-model="form.party_b_name" type="text" class="input" :placeholder="$t('contracts.clientNamePlaceholder')" required />
                      </div>
                      <div>
                        <label class="label">{{ $t('contracts.companyLabel') }}</label>
                        <input v-model="form.party_b_company" type="text" class="input" />
                      </div>
                    </div>
                    <div>
                      <label class="label">{{ $t('contracts.addressLabel') }}</label>
                      <textarea v-model="form.party_b_address" class="input" rows="2" :placeholder="$t('contracts.addressPlaceholder')"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="label">{{ $t('contracts.emailLabel') }}</label>
                        <input v-model="form.party_b_email" type="email" class="input" />
                      </div>
                      <div>
                        <label class="label flex items-center gap-1.5">
                          {{ $t('contracts.vatIdLabel') }}
                          <FieldTooltip>{{ $t('contracts.hintClientVatId') }}</FieldTooltip>
                        </label>
                        <input v-model="form.party_b_vat_id" type="text" class="input" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 3: Contract Details (type-specific) -->
              <div v-if="currentStep === 3">
                <div class="mb-4">
                  <label class="label">{{ $t('contracts.contractTitleRequired') }}</label>
                  <input v-model="form.title" type="text" class="input" :placeholder="$t('contracts.hintContractTitle')" required />
                </div>

                <!-- Custom contract editor -->
                <div v-if="isCustom">
                  <label class="label flex items-center gap-1.5 mb-2">
                    {{ $t('contracts.contractText') }}
                    <FieldTooltip>{{ $t('contracts.customContractHint') }}</FieldTooltip>
                  </label>
                  <TipTapEditor v-model="form.content_html" :placeholder="$t('contracts.enterContractText')" min-height="300px" />
                </div>

                <!-- License specific -->
                <div v-if="isLicense" class="space-y-3">
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label">{{ $t('contracts.softwareNameLabel') }}</label>
                      <input v-model="form.variables_data.software_name" type="text" class="input" :placeholder="$t('contracts.softwareNamePlaceholder')" />
                    </div>
                    <div>
                      <label class="label">{{ $t('contracts.versionLabel') }}</label>
                      <input v-model="form.variables_data.software_version" type="text" class="input" placeholder="z.B. 2.1.0" />
                    </div>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.licenseTypeLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintLicenseExclusivity') }}</FieldTooltip>
                    </label>
                    <div class="flex gap-2">
                      <button type="button" @click="form.variables_data.license_type = 'simple'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="form.variables_data.license_type === 'simple' ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ $t('contracts.simpleLicense') }}
                      </button>
                      <button type="button" @click="form.variables_data.license_type = 'exclusive'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="form.variables_data.license_type === 'exclusive' ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ $t('contracts.exclusiveLicense') }}
                      </button>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.maxUsersLabel') }}
                        <FieldTooltip>{{ $t('contracts.hintMaxUsers') }}</FieldTooltip>
                      </label>
                      <input v-model.number="form.variables_data.max_users" type="number" min="1" class="input" />
                    </div>
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.territoryRights') }}
                        <FieldTooltip>{{ $t('contracts.hintLicenseTerritory') }}</FieldTooltip>
                      </label>
                      <select v-model="form.variables_data.territory" class="input">
                        <option value="worldwide">{{ $t('contracts.worldwide') }}</option>
                        <option value="eu">{{ $t('contracts.euTerritory') }}</option>
                        <option value="dach">{{ $t('contracts.dachTerritory') }}</option>
                        <option value="de">{{ $t('contracts.germanyTerritory') }}</option>
                      </select>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.installationTypeLabel') }}
                        <FieldTooltip>{{ $t('contracts.hintHostingLocation') }}</FieldTooltip>
                      </label>
                      <div class="flex gap-2">
                        <button v-for="it in [{v:'on_premise',l:'On-Premise'},{v:'cloud',l:'Cloud'},{v:'hybrid',l:'Hybrid'}]" :key="it.v"
                          type="button" @click="form.variables_data.installation_type = it.v"
                          class="flex-1 py-2 rounded-lg text-xs font-medium transition-colors border"
                          :class="form.variables_data.installation_type === it.v ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                          {{ it.l }}
                        </button>
                      </div>
                    </div>
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.licenseModelLabel') }}
                        <FieldTooltip>{{ $t('contracts.hintLicenseModel') }}</FieldTooltip>
                      </label>
                      <select v-model="form.variables_data.license_model" class="input">
                        <option value="perpetual">{{ $t('contracts.perpetualLicense') }}</option>
                        <option value="subscription">{{ $t('contracts.subscriptionLicense') }}</option>
                        <option value="named_user">Named-User</option>
                        <option value="concurrent">Concurrent</option>
                        <option value="site">{{ $t('contracts.siteLicense') }}</option>
                      </select>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.source_code_access" type="checkbox" class="checkbox" />
                      {{ $t('contracts.sourceCodeAccess') }}
                      <FieldTooltip>{{ $t('contracts.hintSourceCodeAccess') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.modification_rights" type="checkbox" class="checkbox" />
                      {{ $t('contracts.modificationRights') }}
                      <FieldTooltip>{{ $t('contracts.hintModificationRights') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.updates_included" type="checkbox" class="checkbox" />
                      {{ $t('contracts.updatesIncluded') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.backup_copies" type="checkbox" class="checkbox" />
                      {{ $t('contracts.backupCopiesAllowed') }}
                      <FieldTooltip>{{ $t('contracts.hintBackupRights') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.affiliate_use" type="checkbox" class="checkbox" />
                      {{ $t('contracts.subsidiaryCompanies') }}
                      <FieldTooltip>{{ $t('contracts.subsidiaryTooltip') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.api_access" type="checkbox" class="checkbox" />
                      {{ $t('contracts.apiAccessLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintApiAccess') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.data_processing" type="checkbox" class="checkbox" />
                      {{ $t('contracts.personalDataLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintGdprClause') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.audit_rights" type="checkbox" class="checkbox" />
                      {{ $t('contracts.auditRightLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintAuditRight') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.open_source_included" type="checkbox" class="checkbox" />
                      {{ $t('contracts.openSourceComponents') }}
                      <FieldTooltip>{{ $t('contracts.hintOssComplianceLicense') }}</FieldTooltip>
                    </label>
                  </div>
                  <div v-if="form.variables_data.updates_included">
                    <label class="label">{{ $t('contracts.updateDuration') }}</label>
                    <input v-model.number="form.variables_data.updates_duration_months" type="number" min="1" class="input" />
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.supportLevelLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintSupportLevel') }}</FieldTooltip>
                    </label>
                    <select v-model="form.variables_data.support_level" class="input">
                      <option value="none">{{ $t('contracts.noSupport') }}</option>
                      <option value="basic">Basic</option>
                      <option value="premium">Premium</option>
                      <option value="enterprise">Enterprise</option>
                    </select>
                  </div>
                  <p v-if="form.variables_data.data_processing" class="text-xs text-amber-400">{{ $t('contracts.noteGdprAutoClause') }}</p>
                </div>

                <!-- Development specific -->
                <div v-if="isDevelopment" class="space-y-3">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.projectDescriptionLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintProjectDescription') }}</FieldTooltip>
                    </label>
                    <textarea v-model="form.variables_data.project_description" class="input" rows="3" :placeholder="$t('contracts.describeProject')"></textarea>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.compensationModel') }}
                      <FieldTooltip>{{ $t('contracts.hintPricingModel') }}</FieldTooltip>
                    </label>
                    <div class="flex gap-2">
                      <button v-for="pm in [{v:'fixed',l:$t('contracts.fixedPrice')},{v:'hourly',l:$t('contracts.hourlyRateLabel')},{v:'milestone',l:$t('contracts.milestonesLabel')}]" :key="pm.v"
                        type="button" @click="form.variables_data.pricing_model = pm.v"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="form.variables_data.pricing_model === pm.v ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ pm.l }}
                      </button>
                    </div>
                  </div>
                  <div v-if="form.variables_data.pricing_model === 'hourly'">
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.hourlyRateEur') }}
                      <FieldTooltip>{{ $t('contracts.hintHourlyRate') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.variables_data.hourly_rate" type="number" min="0" step="0.01" class="input" />
                  </div>
                  <!-- Milestones -->
                  <div v-if="form.variables_data.pricing_model === 'milestone'">
                    <label class="label flex items-center gap-1.5 mb-2">
                      {{ $t('contracts.milestonesLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintMilestones') }}</FieldTooltip>
                    </label>
                    <div v-for="(ms, idx) in form.variables_data.milestones" :key="idx" class="flex gap-2 mb-2">
                      <input v-model="ms.name" type="text" class="input flex-1" :placeholder="$t('contracts.milestonePlaceholder')" />
                      <input v-model="ms.date" type="date" class="input w-36" />
                      <input v-model.number="ms.amount" type="number" min="0" step="0.01" class="input w-28" placeholder="EUR" />
                      <button v-if="form.variables_data.milestones.length > 1" type="button" @click="removeMilestone(idx)"
                        class="p-2 text-red-400 hover:text-red-300"><XMarkIcon class="w-4 h-4" /></button>
                    </div>
                    <button type="button" @click="addMilestone" class="text-sm text-primary-400 hover:text-primary-300">{{ $t('contracts.addMilestone') }}</button>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.acceptanceProcedure') }}
                      <FieldTooltip>{{ $t('contracts.hintAcceptanceProcedure') }}</FieldTooltip>
                    </label>
                    <textarea v-model="form.variables_data.acceptance_procedure" class="input" rows="2" :placeholder="$t('contracts.descriptionAcceptanceProcedure')"></textarea>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.warrantyPeriodMonths') }}
                      <FieldTooltip>{{ $t('contracts.hintWarrantyRepair') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.variables_data.warranty_months" type="number" min="1" class="input" />
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.documentation_required" type="checkbox" class="checkbox" />
                      {{ $t('contracts.technicalDocumentation') }}
                      <FieldTooltip>{{ $t('contracts.hintDeliverables') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.deployment_support" type="checkbox" class="checkbox" />
                      {{ $t('contracts.deploymentSupport') }}
                      <FieldTooltip>{{ $t('contracts.goLiveSupport') }}</FieldTooltip>
                    </label>
                  </div>
                </div>

                <!-- SaaS specific -->
                <div v-if="isSaas" class="space-y-3">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.serviceDescription') }}
                      <FieldTooltip>{{ $t('contracts.hintSaasDescription') }}</FieldTooltip>
                    </label>
                    <textarea v-model="form.variables_data.service_description" class="input" rows="3" :placeholder="$t('contracts.descriptionSaasService')"></textarea>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.slaAvailability') }}
                        <FieldTooltip>{{ $t('contracts.hintSlaUptime') }}</FieldTooltip>
                      </label>
                      <input v-model.number="form.variables_data.sla_uptime" type="number" min="90" max="100" step="0.1" class="input" />
                    </div>
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.subscriptionModel') }}
                        <FieldTooltip>{{ $t('contracts.hintBillingCycle') }}</FieldTooltip>
                      </label>
                      <select v-model="form.variables_data.subscription_model" class="input">
                        <option value="monthly">{{ $t('contracts.monthly') }}</option>
                        <option value="yearly">{{ $t('contracts.yearly') }}</option>
                      </select>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label">{{ $t('contracts.pricePerPeriod') }}</label>
                      <input v-model.number="form.variables_data.price_per_period" type="number" min="0" step="0.01" class="input" />
                    </div>
                    <div>
                      <label class="label">{{ $t('contracts.maxUsersLabel') }}</label>
                      <input v-model.number="form.variables_data.max_users" type="number" min="0" class="input" :placeholder="$t('contracts.zeroUnlimited')" />
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label">{{ $t('contracts.storageGb') }}</label>
                      <input v-model.number="form.variables_data.storage_gb" type="number" min="0" class="input" />
                    </div>
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.dataLocation') }}
                        <FieldTooltip>{{ $t('contracts.hintDataLocation') }}</FieldTooltip>
                      </label>
                      <select v-model="form.variables_data.data_location" class="input">
                        <option value="DE">{{ $t('contracts.germanyTerritory') }}</option>
                        <option value="EU">{{ $t('contracts.euTerritory') }}</option>
                        <option value="INT">{{ $t('contracts.international') }}</option>
                      </select>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.sla_credit" type="checkbox" class="checkbox" />
                      {{ $t('contracts.slaCredits') }}
                      <FieldTooltip>{{ $t('contracts.hintSlaCredits') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.support_included" type="checkbox" class="checkbox" />
                      {{ $t('contracts.supportIncluded') }}
                    </label>
                  </div>
                  <div v-if="form.variables_data.support_included">
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.supportLevelLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintSupportLevel') }}</FieldTooltip>
                    </label>
                    <select v-model="form.variables_data.support_level" class="input">
                      <option value="basic">Basic</option>
                      <option value="premium">Premium</option>
                      <option value="enterprise">Enterprise</option>
                    </select>
                  </div>
                </div>

                <!-- Maintenance specific -->
                <div v-if="isMaintenance" class="space-y-3">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.maintainedSoftware') }}
                      <FieldTooltip>{{ $t('contracts.hintSoftwareName') }}</FieldTooltip>
                    </label>
                    <input v-model="form.variables_data.maintained_software" type="text" class="input" :placeholder="$t('contracts.softwareName')" />
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.supportHoursMonth') }}
                        <FieldTooltip>{{ $t('contracts.supportHoursTooltip') }}</FieldTooltip>
                      </label>
                      <input v-model.number="form.variables_data.support_hours_monthly" type="number" min="0" class="input" />
                    </div>
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.responseTime') }}
                        <FieldTooltip>{{ $t('contracts.responseTimeTooltip') }}</FieldTooltip>
                      </label>
                      <select v-model="form.variables_data.response_time" class="input">
                        <option value="4h">{{ $t('contracts.hours4') }}</option>
                        <option value="8h">{{ $t('contracts.hours8') }}</option>
                        <option value="24h">{{ $t('contracts.hours24') }}</option>
                        <option value="48h">{{ $t('contracts.hours48') }}</option>
                      </select>
                    </div>
                  </div>
                  <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.included_patches" type="checkbox" class="checkbox" />
                      {{ $t('contracts.patchesIncluded') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.included_minor_updates" type="checkbox" class="checkbox" />
                      {{ $t('contracts.minorUpdatesIncluded') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.included_major_updates" type="checkbox" class="checkbox" />
                      {{ $t('contracts.majorUpdatesIncluded') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.remote_access_required" type="checkbox" class="checkbox" />
                      {{ $t('contracts.remoteAccessRequired') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.emergency_support" type="checkbox" class="checkbox" />
                      {{ $t('contracts.emergencySupport') }}
                      <FieldTooltip>{{ $t('contracts.hintSupport247') }}</FieldTooltip>
                    </label>
                  </div>
                </div>

                <!-- NDA specific -->
                <div v-if="isNda" class="space-y-3">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.ndaType') }}
                      <FieldTooltip>{{ $t('contracts.hintNdaType') }}</FieldTooltip>
                    </label>
                    <div class="flex gap-2">
                      <button type="button" @click="form.variables_data.nda_type = 'unilateral'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="form.variables_data.nda_type === 'unilateral' ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ $t('contracts.unilateral') }}
                      </button>
                      <button type="button" @click="form.variables_data.nda_type = 'mutual'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="form.variables_data.nda_type === 'mutual' ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ $t('contracts.mutual') }}
                      </button>
                    </div>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.confidentialInfo') }}
                      <FieldTooltip>{{ $t('contracts.confidentialInfoTooltip') }}</FieldTooltip>
                    </label>
                    <textarea v-model="form.variables_data.confidential_info_description" class="input" rows="3" :placeholder="$t('contracts.descriptionConfidentialInfo')"></textarea>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.validityPeriodYears') }}
                        <FieldTooltip>{{ $t('contracts.hintConfidentialityPeriod') }}</FieldTooltip>
                      </label>
                      <input v-model.number="form.variables_data.duration_years" type="number" min="1" max="10" class="input" />
                    </div>
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.penalty') }}
                        <FieldTooltip>{{ $t('contracts.hintPenaltyClause') }}</FieldTooltip>
                      </label>
                      <input v-model.number="form.variables_data.penalty_amount" type="number" min="0" step="100" class="input" />
                    </div>
                  </div>
                </div>

                <!-- Source Code Purchase specific -->
                <div v-if="isSourceCodePurchase" class="space-y-3">
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label">{{ $t('contracts.softwareNameLabel') }}</label>
                      <input v-model="form.variables_data.software_name" type="text" class="input" :placeholder="$t('contracts.softwareNamePlaceholder')" />
                    </div>
                    <div>
                      <label class="label">{{ $t('contracts.versionLabel') }}</label>
                      <input v-model="form.variables_data.software_version" type="text" class="input" placeholder="z.B. 2.1.0" />
                    </div>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.sourceCodeScope') }}
                      <FieldTooltip>{{ $t('contracts.sourceCodeScopeTooltip') }}</FieldTooltip>
                    </label>
                    <textarea v-model="form.variables_data.source_code_scope" class="input" rows="3" :placeholder="$t('contracts.hintSourceCodeScope')"></textarea>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.ipTransfer') }}
                      <FieldTooltip>{{ $t('contracts.hintExclusiveRights') }}</FieldTooltip>
                    </label>
                    <div class="flex gap-2">
                      <button type="button" @click="form.variables_data.ip_transfer_type = 'exclusive'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="form.variables_data.ip_transfer_type === 'exclusive' ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ $t('contracts.exclusiveFullTransfer') }}
                      </button>
                      <button type="button" @click="form.variables_data.ip_transfer_type = 'non_exclusive'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                        :class="form.variables_data.ip_transfer_type === 'non_exclusive' ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ $t('contracts.nonExclusive') }}
                      </button>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.delivery') }}
                        <FieldTooltip>{{ $t('contracts.hintCodeDelivery') }}</FieldTooltip>
                      </label>
                      <select v-model="form.variables_data.delivery_type" class="input">
                        <option value="repository">{{ $t('contracts.repositoryAccess') }}</option>
                        <option value="zip">{{ $t('contracts.zipArchive') }}</option>
                        <option value="both">{{ $t('contracts.repositoryPlusZip') }}</option>
                      </select>
                    </div>
                    <div v-if="form.variables_data.delivery_type !== 'zip'">
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.platform') }}
                        <FieldTooltip>{{ $t('contracts.platformTooltip') }}</FieldTooltip>
                      </label>
                      <select v-model="form.variables_data.repository_platform" class="input">
                        <option value="github">GitHub</option>
                        <option value="gitlab">GitLab</option>
                        <option value="bitbucket">Bitbucket</option>
                        <option value="self_hosted">Self-Hosted</option>
                      </select>
                    </div>
                  </div>
                  <div v-if="form.variables_data.delivery_type !== 'zip'">
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.repoAccessAfterPurchase') }}
                      <FieldTooltip>{{ $t('contracts.hintTransferMethod') }}</FieldTooltip>
                    </label>
                    <div class="flex gap-2">
                      <button v-for="rt in [{v:'transfer',l:'Transfer'},{v:'goodwill',l:$t('contracts.kulanz')},{v:'contractual',l:$t('contracts.contractual')}]" :key="rt.v"
                        type="button" @click="form.variables_data.repository_access_type = rt.v"
                        class="flex-1 py-2 rounded-lg text-xs font-medium transition-colors border"
                        :class="form.variables_data.repository_access_type === rt.v ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                        {{ rt.l }}
                      </button>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="label flex items-center gap-1.5">
                        {{ $t('contracts.warrantyMonths') }}
                        <FieldTooltip>{{ $t('contracts.hintSellerWarranty') }}</FieldTooltip>
                      </label>
                      <input v-model.number="form.variables_data.warranty_months" type="number" min="0" class="input" />
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.includes_documentation" type="checkbox" class="checkbox" />
                      {{ $t('contracts.technicalDocumentation') }}
                      <FieldTooltip>{{ $t('contracts.hintDocumentation') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.includes_deployment_support" type="checkbox" class="checkbox" />
                      {{ $t('contracts.deploymentSupportLabel') }}
                      <FieldTooltip>{{ $t('contracts.hintSetupSupport') }}</FieldTooltip>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                      <input v-model="form.variables_data.open_source_included" type="checkbox" class="checkbox" />
                      {{ $t('contracts.openSourceComponents') }}
                      <FieldTooltip>{{ $t('contracts.hintOssCompliance') }}</FieldTooltip>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Step 4: Duration & Payment -->
              <div v-if="currentStep === 4">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.startDate') }} *
                      <FieldTooltip>{{ $t('contracts.hintStartDate') }}</FieldTooltip>
                    </label>
                    <input v-model="form.start_date" type="date" class="input" required />
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.endDate') }}
                      <FieldTooltip>{{ $t('contracts.hintEndDate') }}</FieldTooltip>
                    </label>
                    <input v-model="form.end_date" type="date" class="input" />
                  </div>
                </div>

                <div class="mt-4">
                  <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                    <input v-model="form.auto_renewal" type="checkbox" :true-value="1" :false-value="0" class="checkbox" />
                    {{ $t('contracts.autoRenewal') }}
                    <FieldTooltip>{{ $t('contracts.hintAutoRenewal') }}</FieldTooltip>
                  </label>
                </div>
                <div v-if="form.auto_renewal" class="mt-3">
                  <label class="label">{{ $t('contracts.renewalPeriod') }}</label>
                  <select v-model="form.renewal_period" class="input">
                    <option v-for="rp in renewalPeriods" :key="rp.value" :value="rp.value">{{ rp.label }}</option>
                  </select>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.noticePeriodDays') }}
                      <FieldTooltip>{{ $t('contracts.noticePeriodHint') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.notice_period_days" type="number" min="0" class="input" />
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.minTermMonths') }}
                      <FieldTooltip>{{ $t('contracts.hintMinTerm') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.min_term_months" type="number" min="0" class="input" />
                  </div>
                </div>

                <div class="mt-4">
                  <label class="label flex items-center gap-1.5">
                    {{ $t('contracts.terminationType') }}
                    <FieldTooltip>{{ $t('contracts.hintTerminationType') }}</FieldTooltip>
                  </label>
                  <div class="flex gap-2">
                    <button v-for="ct in [{v:'ordinary',l:$t('contracts.ordinary')},{v:'extraordinary',l:$t('contracts.extraordinary')},{v:'both',l:$t('contracts.both')}]" :key="ct.v"
                      type="button" @click="form.cancellation_type = ct.v"
                      class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                      :class="form.cancellation_type === ct.v ? 'bg-primary-600 border-primary-500 text-white' : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'">
                      {{ ct.l }}
                    </button>
                  </div>
                </div>

                <hr class="border-white/[0.06] my-5" />

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.totalValue') }}
                      <FieldTooltip>{{ $t('contracts.hintTotalValue') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.total_value" type="number" min="0" step="0.01" class="input" />
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.currency') }}
                      <FieldTooltip>{{ $t('contracts.hintCurrency') }}</FieldTooltip>
                    </label>
                    <select v-model="form.currency" class="input">
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                      <option value="GBP">GBP</option>
                      <option value="CHF">CHF</option>
                      <option value="DKK">DKK</option>
                    </select>
                  </div>
                </div>

                <div class="mt-4">
                  <label class="label flex items-center gap-1.5">
                    {{ $t('contracts.paymentPlan') }}
                    <FieldTooltip>{{ $t('contracts.hintPaymentType') }}</FieldTooltip>
                  </label>
                  <div class="grid grid-cols-4 gap-2">
                    <button
                      v-for="ps in paymentSchedules"
                      :key="ps.value"
                      type="button"
                      @click="form.payment_schedule = ps.value"
                      class="py-2 rounded-lg text-sm font-medium transition-colors border"
                      :class="form.payment_schedule === ps.value
                        ? 'bg-primary-600 border-primary-500 text-white'
                        : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white hover:border-white/[0.08]'"
                    >{{ ps.label }}</button>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.paymentDeadlineDays') }}
                      <FieldTooltip>{{ $t('contracts.hintPaymentDeadline') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.payment_due_days" type="number" min="0" class="input" />
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.earlyPaymentDiscount') }}
                      <FieldTooltip>{{ $t('contracts.earlyPaymentTooltip') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.early_payment_discount" type="number" min="0" max="10" step="0.5" class="input" placeholder="0" />
                  </div>
                </div>
              </div>

              <!-- Step 5: Legal -->
              <div v-if="currentStep === 5">
                <div>
                  <label class="label flex items-center gap-1.5">
                    {{ $t('contracts.applicableLaw') }}
                    <FieldTooltip>{{ $t('contracts.hintGoverningLaw') }}</FieldTooltip>
                  </label>
                  <select v-model="form.governing_law" class="input">
                    <option v-for="gl in governingLaws" :key="gl.value" :value="gl.value">{{ gl.label }}</option>
                  </select>
                </div>

                <div class="mt-4">
                  <label class="label flex items-center gap-1.5">
                    {{ $t('contracts.jurisdiction') }}
                    <FieldTooltip>{{ $t('contracts.hintJurisdiction') }}</FieldTooltip>
                  </label>
                  <input v-model="form.jurisdiction" type="text" class="input" :placeholder="$t('contracts.jurisdictionPlaceholder')" />
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.customerCountry') }}
                      <FieldTooltip>{{ $t('contracts.hintClientCountry') }}</FieldTooltip>
                    </label>
                    <select v-model="form.customer_country" class="input">
                      <option value="DE">{{ $t('contracts.germanyTerritory') }}</option>
                      <option value="AT">{{ $t('contracts.austria') }}</option>
                      <option value="CH">{{ $t('contracts.switzerland') }}</option>
                      <option value="DK">{{ $t('contracts.denmark') }}</option>
                      <option value="NL">{{ $t('contracts.netherlands') }}</option>
                      <option value="FR">{{ $t('contracts.france') }}</option>
                      <option value="US">USA</option>
                      <option value="GB">{{ $t('contracts.unitedKingdom') }}</option>
                      <option value="OTHER">{{ $t('contracts.other') }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.warrantyDurationMonths') }}
                      <FieldTooltip>{{ $t('contracts.hintWarrantyPeriod') }}</FieldTooltip>
                    </label>
                    <input v-model.number="form.warranty_duration_months" type="number" min="0" class="input" />
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4">
                  <div>
                    <label class="label flex items-center gap-1.5">
                      {{ $t('contracts.liabilityCap') }}
                      <FieldTooltip>{{ $t('contracts.hintLiabilityFactor') }}</FieldTooltip>
                    </label>
                    <select v-model.number="form.liability_cap_factor" class="input">
                      <option :value="0.5">{{ $t('contracts.contractValueHalf') }}</option>
                      <option :value="1">{{ $t('contracts.contractValue1x') }}</option>
                      <option :value="2">{{ $t('contracts.contractValue2x') }}</option>
                      <option :value="5">{{ $t('contracts.contractValue5x') }}</option>
                      <option :value="0">{{ $t('contracts.unlimited') }}</option>
                    </select>
                  </div>
                </div>

                <div class="mt-4 space-y-3">
                  <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                    <input v-model="form.include_nda_clause" type="checkbox" :true-value="1" :false-value="0" class="checkbox" />
                    {{ $t('contracts.ndaClauseInclude') }}
                    <FieldTooltip>{{ $t('contracts.hintNdaClause') }}</FieldTooltip>
                  </label>
                  <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                    <input v-model="form.exclude_cisg" type="checkbox" class="checkbox" />
                    {{ $t('contracts.excludeCisg') }}
                    <FieldTooltip>{{ $t('contracts.hintCisgExclusion') }}</FieldTooltip>
                  </label>
                  <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                    <input v-model="form.as_is_clause" type="checkbox" class="checkbox" />
                    {{ $t('contracts.asIsWarrantyExclusion') }}
                    <FieldTooltip>{{ $t('contracts.hintAsIsClause') }}</FieldTooltip>
                  </label>
                </div>

                <div v-if="form.as_is_clause && form.is_b2c" class="mt-2 p-2 rounded-lg bg-red-500/10 border border-red-500/30">
                  <p class="text-xs text-red-300">{{ $t('contracts.noteAsIsClause') }}</p>
                </div>

                <div v-if="form.is_b2c" class="mt-4 p-3 rounded-lg bg-amber-500/10 border border-amber-500/30">
                  <p class="text-sm text-amber-300 font-medium">{{ $t('contracts.b2cAutoClausesTitle') }}</p>
                  <ul class="text-xs text-amber-200/70 mt-1 space-y-0.5">
                    <li>{{ $t('contracts.hintWithdrawalNotice') }}</li>
                    <li>{{ $t('contracts.consumerProtectionWarranty') }}</li>
                    <li v-if="form.governing_law !== 'DE'">{{ $t('contracts.b2cMandatoryConsumerProtection') }}</li>
                  </ul>
                </div>

                <div class="mt-4">
                  <label class="label flex items-center gap-1.5">
                    {{ $t('contracts.notesOptional') }}
                    <FieldTooltip>{{ $t('contracts.notesInternalTooltip') }} {{ $t('contracts.contractText') }}.</FieldTooltip>
                  </label>
                  <textarea v-model="form.notes" class="input" rows="3" :placeholder="$t('contracts.hintInternalNotes')"></textarea>
                </div>

                <!-- Save as Template (custom contracts only) -->
                <div v-if="isCustom" class="mt-5 pt-5 border-t border-white/[0.06]">
                  <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                    <input v-model="form.save_as_template" type="checkbox" class="checkbox" />
                    {{ $t('contracts.saveAsTemplate') }}
                    <FieldTooltip>{{ $t('contracts.saveAsTemplateHint') }}</FieldTooltip>
                  </label>
                  <div v-if="form.save_as_template" class="mt-3">
                    <label class="label">{{ $t('contracts.templateNameRequired') }}</label>
                    <input v-model="form.template_name" type="text" class="input" :placeholder="$t('contracts.hintCustomTitle')" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer Navigation -->
            <div class="sticky bottom-0 bg-white/[0.02] px-6 py-4 border-t border-white/[0.06] flex items-center justify-between">
              <button
                v-if="currentStep > 1"
                type="button"
                @click="prevStep"
                class="flex items-center gap-1 px-4 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
              >
                <ChevronLeftIcon class="w-4 h-4" />{{ $t('common.back') }}</button>
              <div v-else></div>

              <div class="flex gap-2">
                <button
                  type="button"
                  @click="$emit('close')"
                  class="px-4 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
                >{{ $t('common.cancel') }}</button>
                <button
                  v-if="currentStep < totalSteps"
                  type="button"
                  @click="nextStep"
                  :disabled="!canNext"
                  class="flex items-center gap-1 px-5 py-2 rounded-lg text-sm font-semibold bg-primary-600 text-white hover:bg-primary-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                  {{ $t('contracts.next') }}
                  <ChevronRightIcon class="w-4 h-4" />
                </button>
                <button
                  v-else
                  type="button"
                  @click="handleSubmit"
                  class="flex items-center gap-1 px-5 py-2 rounded-lg text-sm font-semibold bg-green-600 text-white hover:bg-green-500 transition-colors"
                >
                  <CheckIcon class="w-4 h-4" />
                  {{ $t('contracts.createContract') }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
