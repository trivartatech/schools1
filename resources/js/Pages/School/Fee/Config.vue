<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    feeConfig: Object,
    currentCount: Number,
    academicYearName: String,
});

const form = useForm({
    prefix:     props.feeConfig?.prefix     ?? 'FEE-',
    suffix:     props.feeConfig?.suffix     ?? '',
    start_no:   props.feeConfig?.start_no   ?? 1,
    pad_length: props.feeConfig?.pad_length ?? 5,
});

// Available tokens for the picker
const tokens = [
    { token: '{YEAR}',  desc: '4-digit year',      example: new Date().getFullYear().toString() },
    { token: '{YY}',    desc: '2-digit year',       example: String(new Date().getFullYear()).slice(2) },
    { token: '{MONTH}', desc: '2-digit month',      example: String(new Date().getMonth() + 1).padStart(2, '0') },
    { token: '{MM}',    desc: 'Same as {MONTH}',    example: String(new Date().getMonth() + 1).padStart(2, '0') },
    { token: '{MON}',   desc: '3-letter month (caps)', example: new Date().toLocaleString('en', {month:'short'}).toUpperCase() },
    { token: '{DD}',    desc: '2-digit day',        example: String(new Date().getDate()).padStart(2, '0') },
    { token: '{AY}',    desc: 'Academic year',      example: props.academicYearName ?? '25-26' },
];

// Resolve tokens in a string (client-side mirror of backend resolveTokens)
function resolveTokens(template) {
    const now = new Date();
    const yy   = String(now.getFullYear()).slice(2);
    const year = now.getFullYear();
    const month= String(now.getMonth() + 1).padStart(2, '0');
    const mon  = now.toLocaleString('en', {month:'short'}).toUpperCase();
    const dd   = String(now.getDate()).padStart(2, '0');
    const ay   = props.academicYearName ?? '??-??';

    return (template ?? '')
        .replace(/{YEAR}/g, year)
        .replace(/{YY}/g,   yy)
        .replace(/{MONTH}/g, month)
        .replace(/{MM}/g,   month)
        .replace(/{MON}/g,  mon)
        .replace(/{DD}/g,   dd)
        .replace(/{AY}/g,   ay);
}

// Live preview of next receipt number
const nextReceiptPreview = computed(() => {
    const prefix  = resolveTokens(form.prefix);
    const suffix  = resolveTokens(form.suffix);
    const nextNo  = Number(form.start_no) + Number(props.currentCount);
    const padded  = String(nextNo).padStart(Number(form.pad_length), '0');
    return prefix + padded + suffix;
});

// Click a token to insert it into the last focused field
const activeField = ref('prefix');
function insertToken(token) {
    if (activeField.value === 'prefix') {
        form.prefix = (form.prefix ?? '') + token;
    } else {
        form.suffix = (form.suffix ?? '') + token;
    }
}

const submit = () => {
    form.post('/school/fee/config', { preserveScroll: true });
};
</script>

<template>
    <Head title="Fee Receipt Settings" />
    <SchoolLayout title="Fee Receipt Settings">
        <div class="max-w-3xl mx-auto space-y-6">

            <!-- Header -->
            <PageHeader>
                <template #title>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center text-xl">🧾</div>
                        <h1 class="page-header-title">Fee Receipt Configuration</h1>
                    </div>
                </template>
                <template #subtitle>
                    <p class="page-header-sub">Customise the auto-generated fee receipt number format using fixed text and dynamic variables.</p>
                </template>
            </PageHeader>

            <!-- Live Preview -->
            <div class="card bg-green-50 border-green-200">
                <div class="card-body flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-green-600 uppercase tracking-wider mb-1">Next Receipt Number Preview</p>
                        <p class="text-3xl font-mono font-bold text-green-700 tracking-widest">{{ nextReceiptPreview }}</p>
                        <p class="text-xs text-green-500 mt-1">Based on {{ currentCount }} existing payment(s)</p>
                    </div>
                    <div class="text-5xl opacity-30">🎫</div>
                </div>
            </div>

            <!-- Token Reference -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Available Variables — Click to Insert</h3>
                </div>
                <div class="card-body">
                    <p class="text-xs text-gray-400 mb-3">Click a variable to insert it into whichever field was last active (Prefix or Suffix).</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="t in tokens" :key="t.token" type="button"
                                @click="insertToken(t.token)"
                                class="group flex flex-col items-start px-3 py-2 bg-gray-50 hover:bg-blue-50 border border-gray-200 hover:border-blue-300 rounded-lg transition cursor-pointer text-left">
                            <span class="font-mono text-sm font-bold text-blue-700">{{ t.token }}</span>
                            <span class="text-xs text-gray-500 group-hover:text-blue-600">{{ t.desc }}</span>
                            <span class="text-xs font-mono text-gray-400 mt-0.5">→ {{ t.example }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Config Form -->
            <form @submit.prevent="submit" class="card">
                <div class="card-header">
                    <h3 class="card-title">Receipt Number Format</h3>
                </div>
                <div class="card-body space-y-6">
                    <div class="form-row-2">
                        <!-- Prefix -->
                        <div class="form-field">
                            <label>
                                Prefix
                                <span class="text-xs text-gray-400 font-normal ml-1">(click field then pick a variable above)</span>
                            </label>
                            <input v-model="form.prefix" type="text"
                                   placeholder="e.g. DPS-{YEAR}- or REC-{MM}/{YY}/"
                                   @focus="activeField = 'prefix'"
                                   :class="activeField === 'prefix' ? 'ring-2 ring-blue-400 border-blue-400' : ''"
                                   class="font-mono" />
                            <p class="text-xs text-gray-400 mt-1">Resolved: <span class="font-mono text-blue-600">{{ resolveTokens(form.prefix) }}</span></p>
                            <p v-if="form.errors.prefix" class="form-error">{{ form.errors.prefix }}</p>
                        </div>

                        <!-- Suffix -->
                        <div class="form-field">
                            <label>
                                Suffix
                                <span class="text-xs text-gray-400 font-normal ml-1">(click field then pick a variable above)</span>
                            </label>
                            <input v-model="form.suffix" type="text"
                                   placeholder="e.g. /{AY} or -{MON}"
                                   @focus="activeField = 'suffix'"
                                   :class="activeField === 'suffix' ? 'ring-2 ring-purple-400 border-purple-400' : ''"
                                   class="font-mono" />
                            <p class="text-xs text-gray-400 mt-1">Resolved: <span class="font-mono text-purple-600">{{ resolveTokens(form.suffix) }}</span></p>
                            <p v-if="form.errors.suffix" class="form-error">{{ form.errors.suffix }}</p>
                        </div>

                        <!-- Starting Number -->
                        <div class="form-field">
                            <label>Starting Number</label>
                            <input v-model="form.start_no" type="number" min="1"
                                   placeholder="e.g. 1 or 1000"
                                   class="font-mono" />
                            <p class="text-xs text-gray-400 mt-1">The first receipt number. Subsequent receipts auto-increment.</p>
                            <p v-if="form.errors.start_no" class="form-error">{{ form.errors.start_no }}</p>
                        </div>

                        <!-- Pad Length -->
                        <div class="form-field">
                            <label>Number Pad Length</label>
                            <select v-model="form.pad_length">
                                <option v-for="n in [3,4,5,6,7,8]" :key="n" :value="n">
                                    {{ n }} digits (e.g. {{ String(Number(form.start_no)).padStart(n, '0') }})
                                </option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">The number will be zero-padded to this length.</p>
                            <p v-if="form.errors.pad_length" class="form-error">{{ form.errors.pad_length }}</p>
                        </div>
                    </div>

                    <!-- Format Breakdown -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="section-heading mb-3">Format Breakdown</p>
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="text-center">
                                <div class="px-3 py-1.5 bg-blue-100 text-blue-800 rounded font-mono text-sm font-bold border border-blue-200">{{ resolveTokens(form.prefix) || '∅' }}</div>
                                <p class="text-xs text-gray-400 mt-1">Prefix</p>
                            </div>
                            <span class="text-gray-400 text-lg">+</span>
                            <div class="text-center">
                                <div class="px-3 py-1.5 bg-green-100 text-green-800 rounded font-mono text-sm font-bold border border-green-200">{{ String(Number(form.start_no) + currentCount).padStart(Number(form.pad_length), '0') }}</div>
                                <p class="text-xs text-gray-400 mt-1">Number (auto)</p>
                            </div>
                            <span class="text-gray-400 text-lg">+</span>
                            <div class="text-center">
                                <div class="px-3 py-1.5 bg-purple-100 text-purple-800 rounded font-mono text-sm font-bold border border-purple-200">{{ resolveTokens(form.suffix) || '∅' }}</div>
                                <p class="text-xs text-gray-400 mt-1">Suffix</p>
                            </div>
                            <span class="text-gray-400 text-lg">=</span>
                            <div class="text-center">
                                <div class="px-3 py-1.5 bg-yellow-100 text-yellow-800 rounded font-mono text-sm font-bold border border-yellow-200 text-base">{{ nextReceiptPreview }}</div>
                                <p class="text-xs text-gray-400 mt-1">Final Receipt</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <Button type="submit" :loading="form.processing" class="flex items-center gap-2">
                            <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            💾 Save Configuration
                        </Button>
                    </div>
                </div>
            </form>

        </div>
    </SchoolLayout>
</template>
