<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    payments: { type: Object, default: () => ({ data: [] }) },
    orders: { type: Object, default: () => ({ data: [] }) },
    students: { type: Array, default: () => [] },
});

const activeTab = ref('receipts');

const statusBadge = (status) => ({
    paid:      'bg-emerald-100 text-emerald-700',
    partial:   'bg-amber-100 text-amber-700',
    due:       'bg-red-100 text-red-700',
    waived:    'bg-blue-100 text-blue-700',
    created:   'bg-gray-100 text-gray-600',
    processed: 'bg-emerald-100 text-emerald-700',
    failed:    'bg-red-100 text-red-700',
    expired:   'bg-gray-100 text-gray-500',
}[status] ?? 'bg-gray-100 text-gray-600');

const modeBadge = (mode) => ({
    cash:    'bg-green-50 text-green-700',
    cheque:  'bg-blue-50 text-blue-700',
    online:  'bg-indigo-50 text-indigo-700',
    upi:     'bg-purple-50 text-purple-700',
    dd:      'bg-cyan-50 text-cyan-700',
    card:    'bg-pink-50 text-pink-700',
}[mode] ?? 'bg-gray-50 text-gray-600');
</script>

<template>
    <SchoolLayout title="Payment History">
        <Head title="Payment History" />

        <div class="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Payment History</h1>
                    <p class="text-sm text-gray-500 mt-0.5">All fee receipts and online payment transactions</p>
                </div>
                <Button as="link" variant="secondary" href="/portal/fees"
                   >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Fees
                </Button>
            </div>

            <!-- Tab Switcher -->
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                <button @click="activeTab = 'receipts'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all"
                    :class="activeTab === 'receipts' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    Fee Receipts
                </button>
                <button @click="activeTab = 'orders'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all"
                    :class="activeTab === 'orders' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    Online Transactions
                </button>
            </div>

            <!-- Fee Receipts -->
            <div v-if="activeTab === 'receipts'" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Receipt #</th>
                                <th v-if="students.length > 1" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fee Head</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Mode</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in payments.data" :key="p.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ p.receipt_no }}</td>
                                <td v-if="students.length > 1" class="px-4 py-3 text-gray-700">
                                    {{ p.student?.first_name }} {{ p.student?.last_name }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ p.fee_head?.name ?? 'Fee' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ p.payment_date }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="modeBadge(p.payment_mode)">
                                        {{ p.payment_mode }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900">
                                    {{ Number(p.amount_paid).toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusBadge(p.status)">
                                        {{ p.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!payments.data?.length">
                                <td :colspan="students.length > 1 ? 7 : 6" class="px-4 py-8 text-center text-gray-400 text-sm">
                                    No payment receipts found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="payments.last_page > 1" class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-sm text-gray-500">
                    <span>Showing {{ payments.from }}-{{ payments.to }} of {{ payments.total }}</span>
                    <div class="flex gap-1">
                        <Link v-for="link in payments.links" :key="link.label"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1 rounded border text-xs"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>

            <!-- Online Transactions -->
            <div v-if="activeTab === 'orders'" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Order ID</th>
                                <th v-if="students.length > 1" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Student</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Payment ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="o in orders.data" :key="o.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ o.gateway_order_id }}</td>
                                <td v-if="students.length > 1" class="px-4 py-3 text-gray-700">
                                    {{ o.student?.first_name }} {{ o.student?.last_name }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900">
                                    {{ (o.amount_paise / 100).toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusBadge(o.status)">
                                        {{ o.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ new Date(o.created_at).toLocaleDateString('en-IN') }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ o.gateway_payment_id ?? '—' }}</td>
                            </tr>
                            <tr v-if="!orders.data?.length">
                                <td :colspan="students.length > 1 ? 6 : 5" class="px-4 py-8 text-center text-gray-400 text-sm">
                                    No online transactions found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="orders.last_page > 1" class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-sm text-gray-500">
                    <span>Showing {{ orders.from }}-{{ orders.to }} of {{ orders.total }}</span>
                    <div class="flex gap-1">
                        <Link v-for="link in orders.links" :key="link.label"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1 rounded border text-xs"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
