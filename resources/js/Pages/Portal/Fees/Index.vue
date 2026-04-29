<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    students: { type: Array, default: () => [] },
    payment_enabled: { type: Boolean, default: false },
    razorpay_key: { type: String, default: null },
    school_name: { type: String, default: 'School' },
    school_logo: { type: String, default: null },
});

const activeStudent = ref(props.students?.[0] ?? null);
const selectedItems = ref({});
const paying = ref(false);
const paymentResult = ref(null);
const error = ref(null);

const feeHeads = computed(() => activeStudent.value?.fee_summary?.fee_heads ?? []);
const summary = computed(() => activeStudent.value?.fee_summary ?? {});
const pendingFees = computed(() => feeHeads.value.filter(h => h.balance > 0));

const totalSelected = computed(() =>
    pendingFees.value.filter(h => selectedItems.value[itemKey(h)]).reduce((sum, h) => sum + h.balance, 0)
);
const hasSelection = computed(() => totalSelected.value > 0);

function itemKey(h) { return `${h.head_name}-${h.term}`; }

function toggleAll(checked) {
    pendingFees.value.forEach(h => { selectedItems.value[itemKey(h)] = checked; });
}

async function initiatePayment() {
    if (!hasSelection.value || paying.value) return;
    paying.value = true;
    error.value = null;
    paymentResult.value = null;

    const feeItems = pendingFees.value
        .filter(h => selectedItems.value[itemKey(h)])
        .map(h => ({ fee_head_id: h.fee_head_id, term: h.term, amount: h.balance }));

    try {
        const { data } = await axios.post('/portal/fees/create-order', {
            student_id: activeStudent.value.id,
            fee_items: feeItems,
        });

        const options = {
            key: data.key, amount: data.amount_paise, currency: data.currency,
            name: data.name, description: data.description, order_id: data.order_id,
            prefill: data.prefill, theme: { color: '#4f46e5' },
            handler: async function (response) {
                try {
                    const verify = await axios.post('/portal/fees/verify-payment', {
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                    });
                    paymentResult.value = { success: true, message: verify.data.message };
                    selectedItems.value = {};
                    setTimeout(() => router.reload(), 1500);
                } catch (e) {
                    paymentResult.value = { success: false, message: e.response?.data?.message ?? 'Payment verification failed.' };
                }
                paying.value = false;
            },
            modal: { ondismiss: function () { paying.value = false; } },
        };

        if (typeof window.Razorpay === 'undefined') {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://checkout.razorpay.com/v1/checkout.js';
                script.onload = resolve;
                script.onerror = () => reject(new Error('Failed to load Razorpay'));
                document.head.appendChild(script);
            });
        }

        const rzp = new window.Razorpay(options);
        rzp.on('payment.failed', function (response) {
            paymentResult.value = { success: false, message: response.error?.description ?? 'Payment failed. Please try again.' };
            paying.value = false;
        });
        rzp.open();
    } catch (e) {
        error.value = e.response?.data?.message ?? e.response?.data?.errors?.error?.[0] ?? 'Something went wrong.';
        paying.value = false;
    }
}

const statusCls = (status) => {
    if (status === 'paid') return 'bg-emerald-100 text-emerald-700';
    if (status === 'partial') return 'bg-amber-100 text-amber-700';
    return 'bg-red-100 text-red-700';
};
const statusLabel = (status) => {
    if (status === 'paid') return 'Paid';
    if (status === 'partial') return 'Partial';
    return 'Unpaid';
};

const fmt = (n) => school.fmtMoney(n, { fixed: true });
</script>

<template>
    <SchoolLayout title="Fee Payment">
        <Head title="Fee Payment" />

        <div class="max-w-4xl mx-auto p-4 sm:p-6 space-y-5">

            <!-- Header -->
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 leading-tight">Online Fee Payment</h1>
                        <p class="text-sm text-gray-500">View fee details and pay online securely</p>
                    </div>
                </div>
                <a href="/portal/fees/history"
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-indigo-600 bg-white border border-indigo-200 hover:bg-indigo-50 rounded-lg transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Payment History
                </a>
            </div>

            <!-- No students empty state -->
            <div v-if="!students.length" class="bg-white rounded-2xl border shadow-sm p-14 text-center">
                <div class="mx-auto w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="text-gray-800 font-semibold text-base">No student linked</p>
                <p class="text-gray-400 text-sm mt-1">Contact the school to link a student to your account.</p>
            </div>

            <template v-if="students.length">
                <!-- Student Switcher -->
                <div v-if="students.length > 1" class="flex gap-2 flex-wrap">
                    <button
                        v-for="s in students" :key="s.id"
                        @click="activeStudent = s; selectedItems = {}"
                        class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all"
                        :class="activeStudent?.id === s.id
                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300 hover:text-indigo-600'"
                    >
                        <div class="w-5 h-5 rounded-full bg-indigo-200 text-indigo-800 text-xs flex items-center justify-center font-bold flex-shrink-0"
                             :class="activeStudent?.id === s.id ? '!bg-white/30 !text-white' : ''">
                            {{ s.name?.charAt(0) }}
                        </div>
                        {{ s.name }}
                    </button>
                </div>

                <template v-if="activeStudent">
                    <!-- Student identity strip -->
                    <div class="bg-indigo-600 rounded-2xl px-5 py-4 flex items-center gap-4 text-white shadow">
                        <div class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                            {{ activeStudent.name?.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-base leading-tight truncate">{{ activeStudent.name }}</p>
                            <p class="text-indigo-200 text-sm">{{ activeStudent.class_name }} &mdash; {{ activeStudent.section_name }}</p>
                        </div>
                        <div class="ml-auto text-right flex-shrink-0">
                            <p class="text-indigo-200 text-xs uppercase tracking-wide">Balance Due</p>
                            <p class="font-bold text-xl" :class="summary.balance > 0 ? 'text-red-300' : 'text-emerald-300'">
                                {{ fmt(summary.balance ?? 0) }}
                            </p>
                        </div>
                    </div>

                    <!-- Fee Summary Cards -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Due</p>
                            <p class="text-lg font-bold text-gray-900 mt-1">{{ fmt(summary.total_due ?? 0) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Paid</p>
                            <p class="text-lg font-bold text-emerald-600 mt-1">{{ fmt(summary.paid ?? 0) }}</p>
                        </div>
                        <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Discount</p>
                            <p class="text-lg font-bold text-blue-600 mt-1">{{ fmt(summary.discount ?? 0) }}</p>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <div v-if="paymentResult" class="rounded-xl p-4 text-sm font-medium flex items-center gap-2.5"
                        :class="paymentResult.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                        <svg v-if="paymentResult.success" class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg v-else class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ paymentResult.message }}
                    </div>

                    <div v-if="error" class="rounded-xl p-4 text-sm font-medium bg-red-50 text-red-800 border border-red-200 flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ error }}
                    </div>

                    <!-- Fee Breakdown Table -->
                    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Fee Breakdown</h3>
                            <span v-if="pendingFees.length"
                                class="text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700">
                                {{ pendingFees.length }} pending
                            </span>
                            <span v-else class="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                                All clear
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-gray-50">
                                        <th v-if="payment_enabled && pendingFees.length" class="px-4 py-3 w-10">
                                            <input type="checkbox"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                @change="toggleAll($event.target.checked)"
                                            />
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fee Head</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Term</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Due</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Paid</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Balance</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="h in feeHeads" :key="itemKey(h)"
                                        class="hover:bg-gray-50/60 transition-colors"
                                        :class="{ 'bg-indigo-50/40': selectedItems[itemKey(h)] }">
                                        <td v-if="payment_enabled && pendingFees.length" class="px-4 py-3">
                                            <input v-if="h.balance > 0" type="checkbox"
                                                v-model="selectedItems[itemKey(h)]"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ h.head_name }}</td>
                                        <td class="px-4 py-3 text-gray-500 text-xs">{{ h.term }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 font-mono text-xs">{{ school.fmtMoney(h.amount_due) }}</td>
                                        <td class="px-4 py-3 text-right text-emerald-600 font-mono text-xs">{{ school.fmtMoney(h.amount_paid) }}</td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold text-xs" :class="h.balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                                            {{ school.fmtMoney(h.balance) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="statusCls(h.status)">
                                                {{ statusLabel(h.status) }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="!feeHeads.length">
                                        <td :colspan="payment_enabled ? 7 : 6" class="px-4 py-10 text-center text-gray-400 text-sm">
                                            No fee structure found for this student.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pay Bar -->
                    <div v-if="payment_enabled && pendingFees.length"
                        class="bg-white rounded-2xl border shadow-sm p-5 flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Selected Amount</p>
                            <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ school.fmtMoney(totalSelected, { fixed: true }) }}</p>
                        </div>
                        <Button @click="initiatePayment" :disabled="!hasSelection || paying">
                            <svg v-if="paying" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            {{ paying ? 'Processing...' : 'Pay Now' }}
                        </Button>
                    </div>

                    <!-- Payment not enabled notice -->
                    <div v-if="!payment_enabled && pendingFees.length"
                        class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Online payment is not enabled. Please contact the school office to pay fees.
                    </div>
                </template>
            </template>

        </div>
    </SchoolLayout>
</template>
