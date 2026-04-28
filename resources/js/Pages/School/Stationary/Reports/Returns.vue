<script setup>
import { ref, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    returns: Array,
    summary: Object,
    filters: Object,
});

const from = ref(props.filters?.from ?? '');
const to   = ref(props.filters?.to   ?? '');

function applyFilters() {
    router.get('/school/stationary/reports/returns',
        { from: from.value, to: to.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(Number(n || 0));
}
function fmtDate(d) { return d ? new Date(d).toLocaleString() : '—'; }

function studentName(r) {
    return r?.student?.user?.name
        || [r?.student?.first_name, r?.student?.last_name].filter(Boolean).join(' ')
        || '—';
}
</script>

<template>
    <Head title="Stationary Returns" />
    <SchoolLayout title="Stationary Returns Report">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">↩ Returns Report</h1>
                <p class="page-header-sub">All stationary returns within the selected date range</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold">Returns</p>
                <p class="text-xl font-bold mt-1">{{ summary.count }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold">Total Refund</p>
                <p class="text-xl font-bold text-rose-600 mt-1">{{ fmt(summary.refund_total) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold">Items Returned</p>
                <p class="text-xl font-bold mt-1">{{ summary.qty_total }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold">Restocked</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ summary.restock_qty }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs uppercase text-gray-500 font-semibold">Written Off</p>
                <p class="text-xl font-bold text-amber-600 mt-1">{{ summary.writeoff_qty }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">From</label>
                <input v-model="from" type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">To</label>
                <input v-model="to" type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
            </div>
            <Button variant="secondary" @click="applyFilters">Apply</Button>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <Table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Items</th>
                        <th class="text-right">Refund</th>
                        <th>Mode</th>
                        <th>Accepted by</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in returns" :key="r.id">
                        <td class="text-xs">{{ fmtDate(r.returned_at) }}</td>
                        <td>
                            <div class="font-semibold">{{ studentName(r) }}</div>
                            <div class="text-xs text-gray-400 font-mono">{{ r.student?.admission_no }}</div>
                        </td>
                        <td class="text-xs">
                            <ul class="list-disc pl-4">
                                <li v-for="item in r.items" :key="item.id">
                                    {{ item.item?.name }} × {{ item.qty_returned }}
                                    <span class="text-gray-400">[{{ item.condition }}{{ item.restock ? ', restocked' : ', written off' }}]</span>
                                </li>
                            </ul>
                        </td>
                        <td class="text-right text-rose-600 font-bold">{{ fmt(r.refund_amount) }}</td>
                        <td>{{ r.refund_mode }}</td>
                        <td>{{ r.accepted_by?.name || '—' }}</td>
                    </tr>
                    <tr v-if="!returns.length">
                        <td colspan="6" class="text-center py-8 text-gray-400">No returns in this date range.</td>
                    </tr>
                </tbody>
            </Table>
        </div>
    </SchoolLayout>
</template>
