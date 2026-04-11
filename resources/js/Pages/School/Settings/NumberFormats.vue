<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    admConfig: Object,
    regConfig: Object,
    feeConfig: Object,
    tcConfig:  Object,
    admissionCount: Number,
    registrationCount: Number,
    feeCount: Number,
    tcCount:  Number,
    academicYearName: String,
});

const tokens = [
    { token: '{YEAR}',  desc: '4-digit year',           example: new Date().getFullYear().toString() },
    { token: '{YY}',    desc: '2-digit year',           example: String(new Date().getFullYear()).slice(2) },
    { token: '{MONTH}', desc: '2-digit month',          example: String(new Date().getMonth()+1).padStart(2,'0') },
    { token: '{MON}',   desc: '3-letter month (caps)',  example: new Date().toLocaleString('en',{month:'short'}).toUpperCase() },
    { token: '{DD}',    desc: '2-digit day',            example: String(new Date().getDate()).padStart(2,'0') },
    { token: '{AY}',    desc: 'Academic year',          example: props.academicYearName ?? '25-26' },
];

function resolveTokens(t) {
    if (!t) return '';
    const now = new Date();
    const ay  = props.academicYearName ?? '??-??';
    return t
        .replace(/{YEAR}/g,  now.getFullYear())
        .replace(/{YY}/g,    String(now.getFullYear()).slice(2))
        .replace(/{MONTH}/g, String(now.getMonth()+1).padStart(2,'0'))
        .replace(/{MM}/g,    String(now.getMonth()+1).padStart(2,'0'))
        .replace(/{MON}/g,   now.toLocaleString('en',{month:'short'}).toUpperCase())
        .replace(/{DD}/g,    String(now.getDate()).padStart(2,'0'))
        .replace(/{AY}/g,    ay);
}

const form = useForm({
    // Admission
    adm_prefix:     props.admConfig?.prefix     ?? 'ADM',
    adm_suffix:     props.admConfig?.suffix     ?? '',
    adm_start_no:   props.admConfig?.start_no   ?? 1,
    adm_pad_length: props.admConfig?.pad_length ?? 4,
    // Registration
    reg_prefix:     props.regConfig?.prefix     ?? 'REG-',
    reg_suffix:     props.regConfig?.suffix     ?? '',
    reg_start_no:   props.regConfig?.start_no   ?? 1,
    reg_pad_length: props.regConfig?.pad_length ?? 4,
    // Fee Receipt
    fee_prefix:     props.feeConfig?.prefix     ?? 'FEE-',
    fee_suffix:     props.feeConfig?.suffix     ?? '',
    fee_start_no:   props.feeConfig?.start_no   ?? 1,
    fee_pad_length: props.feeConfig?.pad_length ?? 5,
    // Transfer Certificate
    tc_prefix:      props.tcConfig?.prefix     ?? 'TC/',
    tc_suffix:      props.tcConfig?.suffix     ?? '/{YEAR}',
    tc_start_no:    props.tcConfig?.start_no   ?? 1,
    tc_pad_length:  props.tcConfig?.pad_length ?? 4,
});

// Computed previews
function preview(prefix, suffix, startNo, count, pad) {
    const p = resolveTokens(prefix);
    const s = resolveTokens(suffix);
    const n = String(Number(startNo) + Number(count)).padStart(Number(pad), '0');
    return p + n + s;
}
const admNextPreview = computed(() => preview(form.adm_prefix, form.adm_suffix, form.adm_start_no, props.admissionCount,    form.adm_pad_length));
const regNextPreview = computed(() => preview(form.reg_prefix, form.reg_suffix, form.reg_start_no, props.registrationCount, form.reg_pad_length));
const feeNextPreview = computed(() => preview(form.fee_prefix, form.fee_suffix, form.fee_start_no, props.feeCount,          form.fee_pad_length));
const tcNextPreview  = computed(() => preview(form.tc_prefix,  form.tc_suffix,  form.tc_start_no,  props.tcCount,           form.tc_pad_length));

// Token insertion target
const activeField = ref('adm_prefix');
function insertToken(token) { form[activeField.value] = (form[activeField.value] ?? '') + token; }

// ── Duplicate / Overlap Warning Logic ────────────────────────────────────
// If start_no is low enough that "prefix + startNo + suffix" was already issued,
// warn the user that re-using this format may produce duplicate numbers.
const warnings = computed(() => {
    const list = [];
    // Admission: if startNo ≤ current count, numbers from startNo..count are already issued
    if (Number(form.adm_start_no) <= props.admissionCount) {
        list.push(`⚠️ Admission: Starting number ${form.adm_start_no} is ≤ ${props.admissionCount} already issued — the range ${form.adm_start_no}–${props.admissionCount} may produce duplicate numbers. Increase the starting number to avoid conflicts.`);
    }
    if (Number(form.reg_start_no) <= props.registrationCount) {
        list.push(`⚠️ Registration: Starting number ${form.reg_start_no} is ≤ ${props.registrationCount} already issued — may produce duplicate numbers.`);
    }
    if (Number(form.fee_start_no) <= props.feeCount) {
        list.push(`⚠️ Fee Receipt: Starting number ${form.fee_start_no} is ≤ ${props.feeCount} already issued — may produce duplicate numbers.`);
    }
    if (Number(form.tc_start_no) <= props.tcCount) {
        list.push(`⚠️ Transfer Certificate: Starting number ${form.tc_start_no} is ≤ ${props.tcCount} already issued — may produce duplicate certificate numbers.`);
    }
    // If prefix/suffix changed vs saved config (different resolved value), also warn
    const savedAdmPrefix = resolveTokens(props.admConfig?.prefix ?? 'ADM');
    const newAdmPrefix   = resolveTokens(form.adm_prefix);
    if (savedAdmPrefix !== newAdmPrefix && props.admissionCount > 0) {
        list.push(`ℹ️ Admission prefix changed from "${savedAdmPrefix}" → "${newAdmPrefix}". Numbers previously issued with the old prefix will not conflict, but ensure the new format is unique.`);
    }
    const savedFeePrefix = resolveTokens(props.feeConfig?.prefix ?? 'FEE-');
    const newFeePrefix   = resolveTokens(form.fee_prefix);
    if (savedFeePrefix !== newFeePrefix && props.feeCount > 0) {
        list.push(`ℹ️ Fee Receipt prefix changed from "${savedFeePrefix}" → "${newFeePrefix}". Previously issued receipts keep their old numbers.`);
    }
    return list;
});

const submit = () => form.post('/school/settings/number-formats', { preserveScroll: true });
</script>

<template>
    <Head title="Number Format Settings" />
    <SchoolLayout title="Number Format Settings">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center text-xl">🔢</div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Number Format Settings</h2>
                    <p class="text-sm text-gray-500">Configure auto-generated number formats for Admissions, Registrations, and Fee Receipts.</p>
                </div>
            </div>

            <!-- Live Previews -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                    <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-1">🎓 Next Admission No.</p>
                    <p class="text-xl font-mono font-bold text-indigo-700 tracking-widest truncate">{{ admNextPreview }}</p>
                    <p class="text-xs text-indigo-400 mt-1">After {{ admissionCount }} student(s)</p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider mb-1">📋 Next Registration No.</p>
                    <p class="text-xl font-mono font-bold text-blue-700 tracking-widest truncate">{{ regNextPreview }}</p>
                    <p class="text-xs text-blue-400 mt-1">After {{ registrationCount }} application(s)</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <p class="text-xs font-semibold text-green-600 uppercase tracking-wider mb-1">🧾 Next Fee Receipt No.</p>
                    <p class="text-xl font-mono font-bold text-green-700 tracking-widest truncate">{{ feeNextPreview }}</p>
                    <p class="text-xs text-green-400 mt-1">After {{ feeCount }} payment(s)</p>
                </div>
                <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
                    <p class="text-xs font-semibold text-rose-600 uppercase tracking-wider mb-1">📜 Next TC No.</p>
                    <p class="text-xl font-mono font-bold text-rose-700 tracking-widest truncate">{{ tcNextPreview }}</p>
                    <p class="text-xs text-rose-400 mt-1">After {{ tcCount }} TC(s) issued</p>
                </div>
            </div>

            <!-- Token Picker -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Dynamic Variables — Click to Insert</p>
                <p class="text-xs text-gray-400 mb-3">Click any input field first (it glows), then click a variable below to insert it.</p>
                <div class="flex flex-wrap gap-2">
                    <Button variant="secondary" v-for="t in tokens" :key="t.token" type="button" @click="insertToken(t.token)"
                            class="group items-start text-left">
                        <span class="font-mono text-sm font-bold text-indigo-700">{{ t.token }}</span>
                        <span class="text-xs text-gray-500">{{ t.desc }}</span>
                        <span class="text-xs font-mono text-gray-400 mt-0.5">→ {{ t.example }}</span>
                    </Button>
                </div>
            </div>

            <!-- Duplicate / Conflict Warnings -->
            <div v-if="warnings.length" class="space-y-2">
                <div v-for="(w, i) in warnings" :key="i"
                     :class="w.startsWith('⚠') ? 'bg-amber-50 border-amber-300 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-700'"
                     class="border rounded-xl px-4 py-3 text-sm flex items-start gap-2">
                    <span class="text-base mt-0.5">{{ w.charAt(0) }}</span>
                    <span>{{ w.slice(2) }}</span>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Section builder helper component inline -->
                <template v-for="section in [
                    { emoji: '🎓', title: 'Admission Number',    pk: 'adm', count: admissionCount,    preview: admNextPreview,  color: 'indigo' },
                    { emoji: '📋', title: 'Registration Number', pk: 'reg', count: registrationCount, preview: regNextPreview,  color: 'blue'   },
                    { emoji: '🧾', title: 'Fee Receipt Number',  pk: 'fee', count: feeCount,          preview: feeNextPreview,  color: 'green'  },
                    { emoji: '📜', title: 'Transfer Certificate Number', pk: 'tc', count: tcCount,   preview: tcNextPreview,   color: 'rose'   },
                ]" :key="section.pk">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex items-center gap-2 mb-5 pb-3 border-b">
                            <span class="text-lg">{{ section.emoji }}</span>
                            <h3 class="text-base font-bold text-gray-800">{{ section.title }} Format</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Prefix -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prefix</label>
                                <input v-model="form[`${section.pk}_prefix`]" type="text"
                                       :placeholder="`e.g. ${section.pk.toUpperCase()}-{YEAR}/`"
                                       @focus="activeField = `${section.pk}_prefix`"
                                       :class="activeField === `${section.pk}_prefix` ? 'ring-2 ring-indigo-400 border-indigo-400' : ''"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none font-mono transition" />
                                <p class="text-xs text-gray-400 mt-1">→ <span class="font-mono text-indigo-600">{{ resolveTokens(form[`${section.pk}_prefix`]) || '(empty)' }}</span></p>
                                <p v-if="form.errors[`${section.pk}_prefix`]" class="text-xs text-red-500 mt-1">{{ form.errors[`${section.pk}_prefix`] }}</p>
                            </div>
                            <!-- Suffix -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                <input v-model="form[`${section.pk}_suffix`]" type="text"
                                       placeholder="e.g. /{AY} or -{MON}"
                                       @focus="activeField = `${section.pk}_suffix`"
                                       :class="activeField === `${section.pk}_suffix` ? 'ring-2 ring-purple-400 border-purple-400' : ''"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none font-mono transition" />
                                <p class="text-xs text-gray-400 mt-1">→ <span class="font-mono text-purple-600">{{ resolveTokens(form[`${section.pk}_suffix`]) || '(empty)' }}</span></p>
                            </div>
                            <!-- Start No -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Starting Number</label>
                                <input v-model="form[`${section.pk}_start_no`]" type="number" min="1"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none font-mono" />
                            </div>
                            <!-- Pad Length -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pad Length</label>
                                <select v-model="form[`${section.pk}_pad_length`]"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none bg-white">
                                    <option v-for="n in [3,4,5,6,7,8]" :key="n" :value="n">
                                        {{ n }} digits ({{ String(Number(form[`${section.pk}_start_no`])).padStart(n,'0') }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Format Breakdown -->
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 mt-4 flex items-center gap-2 flex-wrap text-sm">
                            <div class="text-center">
                                <div class="px-3 py-1 bg-blue-100 text-blue-800 rounded font-mono font-bold border border-blue-200 text-xs">{{ resolveTokens(form[`${section.pk}_prefix`]) || '∅' }}</div>
                                <p class="text-xs text-gray-400 mt-1">Prefix</p>
                            </div>
                            <span class="text-gray-400">+</span>
                            <div class="text-center">
                                <div class="px-3 py-1 bg-green-100 text-green-800 rounded font-mono font-bold border border-green-200 text-xs">
                                    {{ String(Number(form[`${section.pk}_start_no`]) + section.count).padStart(Number(form[`${section.pk}_pad_length`]),'0') }}
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Number</p>
                            </div>
                            <span class="text-gray-400">+</span>
                            <div class="text-center">
                                <div class="px-3 py-1 bg-purple-100 text-purple-800 rounded font-mono font-bold border border-purple-200 text-xs">{{ resolveTokens(form[`${section.pk}_suffix`]) || '∅' }}</div>
                                <p class="text-xs text-gray-400 mt-1">Suffix</p>
                            </div>
                            <span class="text-gray-400">=</span>
                            <div class="text-center">
                                <div class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded font-mono font-bold border border-yellow-200">{{ section.preview }}</div>
                                <p class="text-xs text-gray-400 mt-1">Final</p>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Save -->
                <div class="flex justify-end">
                    <Button type="submit" :loading="form.processing"
                           >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        💾 Save All Formats
                    </Button>
                </div>
            </form>

        </div>
    </SchoolLayout>
</template>
