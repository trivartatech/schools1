<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';

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
const paymentResult = ref(null); // { success, message }
const error = ref(null);

const feeHeads = computed(() => activeStudent.value?.fee_summary?.fee_heads ?? []);
const summary = computed(() => activeStudent.value?.fee_summary ?? {});

const pendingFees = computed(() => feeHeads.value.filter(h => h.balance > 0));

const totalSelected = computed(() => {
    return pendingFees.value
        .filter(h => selectedItems.value[itemKey(h)])
        .reduce((sum, h) => sum + h.balance, 0);
});

const hasSelection = computed(() => totalSelected.value > 0);

function itemKey(h) {
    return `${h.head_name}-${h.term}`;
}

function toggleAll(checked) {
    pendingFees.value.forEach(h => {
        selectedItems.value[itemKey(h)] = checked;
    });
}

function findFeeHeadId(headName) {
    // The fee_heads array from FeeService doesn't include fee_head_id directly,
    // but the PortalFeeController passes the data with it. We use head_name + term.
    // The controller will resolve fee_head_id server-side from the items data.
    return null;
}

async function initiatePayment() {
    if (!hasSelection.value || paying.value) return;
    paying.value = true;
    error.value = null;
    paymentResult.value = null;

    // Build fee items from selected heads
    const feeItems = pendingFees.value
        .filter(h => selectedItems.value[itemKey(h)])
        .map(h => ({
            fee_head_id: h.fee_head_id,
            term: h.term,
            amount: h.balance,
        }));

    try {
        // Create order on server
        const { data } = await axios.post('/portal/fees/create-order', {
            student_id: activeStudent.value.id,
            fee_items: feeItems,
        });

        // Open Razorpay Checkout
        const options = {
            key: data.key,
            amount: data.amount_paise,
            currency: data.currency,
            name: data.name,
            description: data.description,
            order_id: data.order_id,
            prefill: data.prefill,
            theme: { color: '#4f46e5' },
            handler: async function (response) {
                try {
                    const verify = await axios.post('/portal/fees/verify-payment', {
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                    });
                    paymentResult.value = { success: true, message: verify.data.message };
                    selectedItems.value = {};
                    // Reload page data
                    setTimeout(() => router.reload(), 1500);
                } catch (e) {
                    paymentResult.value = {
                        success: false,
                        message: e.response?.data?.message ?? 'Payment verification failed.',
                    };
                }
                paying.value = false;
            },
            modal: {
                ondismiss: function () {
                    paying.value = false;
                },
            },
        };

        if (typeof window.Razorpay === 'undefined') {
            // Load Razorpay script dynamically
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
            paymentResult.value = {
                success: false,
                message: response.error?.description ?? 'Payment failed. Please try again.',
            };
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
</script>

<template>
    <SchoolLayout title="Fee Payment">
        <Head title="Fee Payment" />

        <div class="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Online Fee Payment</h1>
                    <p class="text-sm text-gray-500 mt-0.5">View fee details and pay online securely</p>
                </div>
                <a href="/portal/fees/history"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Payment History
                </a>
            </div>

            <!-- Student Switcher -->
            <div v-if="students.length > 1" class="flex gap-2 flex-wrap">
                <button
                    v-for="s in students" :key="s.id"
                    @click="activeStudent = s; selectedItems = {}"
                    class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all"
                    :class="activeStudent?.id === s.id
                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                        : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300'"
                >
                    <div class="w-5 h-5 rounded-full bg-indigo-200 text-indigo-800 text-xs flex items-center justify-center font-bold flex-shrink-0">
                        {{ s.name?.charAt(0) }}
                    </div>
                    {{ s.name }}
                </button>
            </div>

            <!-- No students -->
            <div v-if="!students.length" class="bg-white rounded-xl border shadow-sm p-12 text-center">
                <p class="text-gray-500 text-sm">No student linked to this account.</p>
            </div>

            <template v-if="activeStudent">
                <!-- Fee Summary Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
                        <p class="text-xs text-gray-400 font-medium uppercase">Total Due</p>
                        <p class="text-xl font-bold text-gray-900 mt-1">{{ Number(summary.total_due).toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
                        <p class="text-xs text-gray-400 font-medium uppercase">Paid</p>
                        <p class="text-xl font-bold text-emerald-600 mt-1">{{ Number(summary.paid).toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
                        <p class="text-xs text-gray-400 font-medium uppercase">Discount</p>
                        <p class="text-xl font-bold text-blue-600 mt-1">{{ Number(summary.discount).toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) }}</p>
                    </div>
                    <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
                        <p class="text-xs text-gray-400 font-medium uppercase">Balance</p>
                        <p class="text-xl font-bold mt-1" :class="summary.balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                            {{ Number(summary.balance).toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) }}
                        </p>
                    </div>
                </div>

                <!-- Alerts -->
                <div v-if="paymentResult" class="rounded-lg p-4 text-sm font-medium"
                    :class="paymentResult.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                    <div class="flex items-center gap-2">
                        <svg v-if="paymentResult.success" class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg v-else class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ paymentResult.message }}
                    </div>
                </div>

                <div v-if="error" class="rounded-lg p-4 text-sm font-medium bg-red-50 text-red-800 border border-red-200">
                    {{ error }}
                </div>

                <!-- Fee Breakdown Table -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Fee Details</h3>
                        <span class="text-xs text-gray-400">{{ activeStudent.class_name }} - {{ activeStudent.section_name }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-gray-50/50">
                                    <th v-if="payment_enabled && pendingFees.length" class="px-4 py-3 text-left w-10">
                                        <input type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            @change="toggleAll($event.target.checked)"
                                        />
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fee Head</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Term</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Amount Due</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Paid</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Balance</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="h in feeHeads" :key="itemKey(h)"
                                    class="hover:bg-gray-50/50 transition-colors"
                                    :class="{ 'bg-indigo-50/30': selectedItems[itemKey(h)] }">
                                    <td v-if="payment_enabled && pendingFees.length" class="px-4 py-3">
                                        <input v-if="h.balance > 0"
                                            type="checkbox"
                                            v-model="selectedItems[itemKey(h)]"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ h.head_name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ h.term }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 font-mono">{{ Number(h.amount_due).toLocaleString('en-IN') }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 font-mono">{{ Number(h.amount_paid).toLocaleString('en-IN') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold" :class="h.balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ Number(h.balance).toLocaleString('en-IN') }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statusCls(h.status)">
                                            {{ statusLabel(h.status) }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="!feeHeads.length">
                                    <td :colspan="payment_enabled ? 7 : 6" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        No fee structure found for this student.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pay Button -->
                <div v-if="payment_enabled && pendingFees.length"
                    class="bg-white rounded-xl border shadow-sm p-5 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Selected Amount</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ totalSelected.toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) }}
                        </p>
                    </div>
                    <Button
                        @click="initiatePayment"
                        :disabled="!hasSelection || paying"
                       
                    >
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
                    class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Online payment is not enabled. Please contact the school office to pay fees.
                    </div>
                </div>
            </template>
        </div>
    </SchoolLayout>
</template>
