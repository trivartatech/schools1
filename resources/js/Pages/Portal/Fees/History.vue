<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    payments:          { type: Object, default: () => ({ data: [] }) },
    orders:            { type: Object, default: () => ({ data: [] }) },
    transportPayments:  { type: Object, default: () => ({ data: [] }) },
    hostelPayments:     { type: Object, default: () => ({ data: [] }) },
    stationaryPayments: { type: Object, default: () => ({ data: [] }) },
    students:           { type: Array,  default: () => [] },
});

const activeTab = ref('receipts');

const fmtMoney = (n) => school.fmtMoney(n, { fixed: true });

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
    cash:   'bg-green-50 text-green-700',
    cheque: 'bg-blue-50 text-blue-700',
    online: 'bg-indigo-50 text-indigo-700',
    upi:    'bg-purple-50 text-purple-700',
    dd:     'bg-cyan-50 text-cyan-700',
    card:   'bg-pink-50 text-pink-700',
}[mode] ?? 'bg-gray-50 text-gray-600');
</script>

<template>
    <SchoolLayout title="Payment History">
        <Head title="Payment History" />

        <div class="max-w-4xl mx-auto p-4 sm:p-6 space-y-5">

            <!-- Header -->
            <PageHeader
                title="Payment History"
                subtitle="All fee receipts and online payment transactions"
                back-href="/portal/fees"
                back-label="← Back to Fees"
            />

            <!-- Tab Switcher -->
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
                <button @click="activeTab = 'receipts'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === 'receipts' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Fee Receipts
                        <span v-if="payments.total" class="text-xs px-1.5 py-0.5 rounded-full"
                            :class="activeTab === 'receipts' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600'">
                            {{ payments.total }}
                        </span>
                    </span>
                </button>
                <button @click="activeTab = 'transport'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === 'transport' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    <span class="flex items-center justify-center gap-2">
                        🚌 Transport
                        <span v-if="transportPayments.total" class="text-xs px-1.5 py-0.5 rounded-full"
                            :class="activeTab === 'transport' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600'">
                            {{ transportPayments.total }}
                        </span>
                    </span>
                </button>
                <button @click="activeTab = 'hostel'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === 'hostel' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    <span class="flex items-center justify-center gap-2">
                        🏠 Hostel
                        <span v-if="hostelPayments.total" class="text-xs px-1.5 py-0.5 rounded-full"
                            :class="activeTab === 'hostel' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600'">
                            {{ hostelPayments.total }}
                        </span>
                    </span>
                </button>
                <button @click="activeTab = 'stationary'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === 'stationary' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    <span class="flex items-center justify-center gap-2">
                        📚 Stationary
                        <span v-if="stationaryPayments.total" class="text-xs px-1.5 py-0.5 rounded-full"
                            :class="activeTab === 'stationary' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600'">
                            {{ stationaryPayments.total }}
                        </span>
                    </span>
                </button>
                <button @click="activeTab = 'orders'"
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-all"
                    :class="activeTab === 'orders' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Online Transactions
                        <span v-if="orders.total" class="text-xs px-1.5 py-0.5 rounded-full"
                            :class="activeTab === 'orders' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600'">
                            {{ orders.total }}
                        </span>
                    </span>
                </button>
            </div>

            <!-- Fee Receipts -->
            <div v-if="activeTab === 'receipts'" class="bg-white rounded-2xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <Table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Receipt #</th>
                                <th v-if="students.length > 1" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fee Head</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Mode</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="p in payments.data" :key="p.id" class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ p.receipt_no }}</td>
                                <td v-if="students.length > 1" class="px-4 py-3 text-gray-700">
                                    {{ p.student?.first_name }} {{ p.student?.last_name }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ p.fee_head?.name ?? 'Fee' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ school.fmtDate(p.payment_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="modeBadge(p.payment_mode)">
                                        {{ p.payment_mode }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 text-xs">
                                    {{ school.fmtMoney(p.amount_paid, { fixed: true }) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusBadge(p.status)">
                                        {{ p.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!payments.data?.length">
                                <td :colspan="students.length > 1 ? 7 : 6" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2 text-gray-400">
                                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="text-sm">No payment receipts found.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
                <div v-if="payments.last_page > 1" class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                    <span>Showing {{ payments.from }}–{{ payments.to }} of {{ payments.total }}</span>
                    <div class="flex gap-1">
                        <Link v-for="link in payments.links" :key="link.label"
                            :href="link.url" v-html="link.label"
                            class="px-3 py-1 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>

            <!-- Transport Receipts -->
            <div v-if="activeTab === 'transport'" class="bg-white rounded-2xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <Table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Receipt #</th>
                                <th v-if="students.length > 1" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Route / Stop</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Mode</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="p in transportPayments.data" :key="p.id" class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ p.receipt_no }}</td>
                                <td v-if="students.length > 1" class="px-4 py-3 text-gray-700">
                                    {{ p.student?.first_name }} {{ p.student?.last_name }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ p.allocation?.route?.route_name ?? '—' }}
                                    <span v-if="p.allocation?.stop?.stop_name" class="text-xs text-gray-500"> · {{ p.allocation.stop.stop_name }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ school.fmtDate(p.payment_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="modeBadge(p.payment_mode)">
                                        {{ p.payment_mode }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 text-xs">
                                    {{ fmtMoney(p.amount_paid) }}
                                </td>
                            </tr>
                            <tr v-if="!transportPayments.data?.length">
                                <td :colspan="students.length > 1 ? 6 : 5" class="px-4 py-12 text-center text-gray-400 text-sm">
                                    No transport fee receipts found.
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
                <div v-if="transportPayments.last_page > 1" class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                    <span>Showing {{ transportPayments.from }}–{{ transportPayments.to }} of {{ transportPayments.total }}</span>
                    <div class="flex gap-1">
                        <Link v-for="link in transportPayments.links" :key="link.label"
                            :href="link.url" v-html="link.label"
                            class="px-3 py-1 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>

            <!-- Hostel Receipts -->
            <div v-if="activeTab === 'hostel'" class="bg-white rounded-2xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <Table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Receipt #</th>
                                <th v-if="students.length > 1" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Hostel / Room</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Mode</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="p in hostelPayments.data" :key="p.id" class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ p.receipt_no }}</td>
                                <td v-if="students.length > 1" class="px-4 py-3 text-gray-700">
                                    {{ p.student?.first_name }} {{ p.student?.last_name }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ p.allocation?.bed?.room?.hostel?.name ?? '—' }}
                                    <span v-if="p.allocation?.bed?.room?.room_number" class="text-xs text-gray-500"> · Rm {{ p.allocation.bed.room.room_number }}</span>
                                    <span v-if="p.allocation?.bed?.name" class="text-xs text-gray-500"> · {{ p.allocation.bed.name }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ school.fmtDate(p.payment_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="modeBadge(p.payment_mode)">
                                        {{ p.payment_mode }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 text-xs">
                                    {{ fmtMoney(p.amount_paid) }}
                                </td>
                            </tr>
                            <tr v-if="!hostelPayments.data?.length">
                                <td :colspan="students.length > 1 ? 6 : 5" class="px-4 py-12 text-center text-gray-400 text-sm">
                                    No hostel fee receipts found.
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
                <div v-if="hostelPayments.last_page > 1" class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                    <span>Showing {{ hostelPayments.from }}–{{ hostelPayments.to }} of {{ hostelPayments.total }}</span>
                    <div class="flex gap-1">
                        <Link v-for="link in hostelPayments.links" :key="link.label"
                            :href="link.url" v-html="link.label"
                            class="px-3 py-1 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>

            <!-- Stationary Receipts -->
            <div v-if="activeTab === 'stationary'" class="bg-white rounded-2xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <Table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Receipt #</th>
                                <th v-if="students.length > 1" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Allocation</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Mode</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="p in stationaryPayments.data" :key="p.id" class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ p.receipt_no }}</td>
                                <td v-if="students.length > 1" class="px-4 py-3 text-gray-700">
                                    {{ p.student?.first_name }} {{ p.student?.last_name }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    Stationary kit
                                    <span v-if="p.allocation" class="text-xs text-gray-500">
                                        · Total {{ fmtMoney(p.allocation.total_amount) }} · Balance {{ fmtMoney(p.allocation.balance) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ school.fmtDate(p.payment_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="modeBadge(p.payment_mode)">
                                        {{ p.payment_mode }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 text-xs">
                                    {{ fmtMoney(p.amount_paid) }}
                                </td>
                            </tr>
                            <tr v-if="!stationaryPayments.data?.length">
                                <td :colspan="students.length > 1 ? 6 : 5" class="px-4 py-12 text-center text-gray-400 text-sm">
                                    No stationary fee receipts found.
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
                <div v-if="stationaryPayments.last_page > 1" class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                    <span>Showing {{ stationaryPayments.from }}–{{ stationaryPayments.to }} of {{ stationaryPayments.total }}</span>
                    <div class="flex gap-1">
                        <Link v-for="link in stationaryPayments.links" :key="link.label"
                            :href="link.url" v-html="link.label"
                            class="px-3 py-1 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>

            <!-- Online Transactions -->
            <div v-if="activeTab === 'orders'" class="bg-white rounded-2xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <Table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Order ID</th>
                                <th v-if="students.length > 1" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Student</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Payment ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="o in orders.data" :key="o.id" class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ o.gateway_order_id }}</td>
                                <td v-if="students.length > 1" class="px-4 py-3 text-gray-700">
                                    {{ o.student?.first_name }} {{ o.student?.last_name }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 text-xs">
                                    {{ school.fmtMoney(o.amount_paise / 100, { fixed: true }) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusBadge(o.status)">
                                        {{ o.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ school.fmtDate(o.created_at) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ o.gateway_payment_id ?? '—' }}</td>
                            </tr>
                            <tr v-if="!orders.data?.length">
                                <td :colspan="students.length > 1 ? 6 : 5" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2 text-gray-400">
                                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                        <span class="text-sm">No online transactions found.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
                <div v-if="orders.last_page > 1" class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                    <span>Showing {{ orders.from }}–{{ orders.to }} of {{ orders.total }}</span>
                    <div class="flex gap-1">
                        <Link v-for="link in orders.links" :key="link.label"
                            :href="link.url" v-html="link.label"
                            class="px-3 py-1 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>

        </div>
    </SchoolLayout>
</template>
