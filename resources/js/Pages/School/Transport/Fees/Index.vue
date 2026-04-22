<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    allocations: Object,
    routes:      Array,
    filters:     Object,
    summary:     Object,
});

const filters = ref({
    search:   props.filters?.search   ?? '',
    status:   props.filters?.status   ?? '',
    route_id: props.filters?.route_id ?? '',
});

let debounceTimer = null;
watch(filters, (v) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/school/transport/fees', v, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}, { deep: true });

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(Number(n || 0));
}

function studentName(a) {
    return a?.student?.user?.name
        || [a?.student?.first_name, a?.student?.last_name].filter(Boolean).join(' ')
        || '—';
}

const STATUS_COLOURS = {
    paid:    'bg-green-100 text-green-700',
    partial: 'bg-amber-100 text-amber-700',
    unpaid:  'bg-rose-100 text-rose-700',
    waived:  'bg-gray-200 text-gray-600',
};
</script>

<template>
    <Head title="Transport Fee Collection" />
    <SchoolLayout title="Transport Fee Collection">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">🚌 Transport Fee Collection</h1>
                <p class="page-header-sub">Every transport allocation has its own outstanding balance. Receipts are numbered separately from regular fees.</p>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Total Outstanding</p>
                <p class="text-xl font-bold text-rose-600 mt-1">{{ fmt(summary.total_due) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Total Collected</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ fmt(summary.total_paid) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Unpaid</p>
                <p class="text-xl font-bold text-rose-600 mt-1">{{ summary.unpaid_count }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Partial</p>
                <p class="text-xl font-bold text-amber-600 mt-1">{{ summary.partial_count }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Fully Paid</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ summary.paid_count }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 p-3 mb-4 flex flex-wrap gap-3 items-center">
            <input v-model="filters.search" type="text" placeholder="Search by name or admission no."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px]">
            <select v-model="filters.status"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All statuses</option>
                <option value="unpaid">Unpaid</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
                <option value="waived">Waived</option>
            </select>
            <select v-model="filters.route_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All routes</option>
                <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
            </select>
        </div>

        <!-- Table -->
        <Table :empty="allocations.data.length === 0" empty-text="No transport allocations match the current filter.">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Route / Stop</th>
                    <th class="text-right">Fee</th>
                    <th class="text-right">Paid</th>
                    <th class="text-right">Balance</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="a in allocations.data" :key="a.id">
                    <td>
                        <div class="font-medium text-gray-900">{{ studentName(a) }}</div>
                        <div class="text-xs text-gray-500">{{ a.student?.admission_no }}</div>
                    </td>
                    <td>
                        <div class="text-sm">{{ a.route?.route_name ?? '—' }}</div>
                        <div class="text-xs text-gray-500">{{ a.stop?.stop_name ?? '' }}</div>
                    </td>
                    <td class="text-right font-mono">{{ fmt(a.transport_fee) }}</td>
                    <td class="text-right font-mono text-green-600">{{ fmt(a.amount_paid) }}</td>
                    <td class="text-right font-mono" :class="Number(a.balance) > 0 ? 'text-rose-600 font-semibold' : 'text-gray-400'">
                        {{ fmt(a.balance) }}
                    </td>
                    <td>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="STATUS_COLOURS[a.payment_status] || 'bg-gray-100 text-gray-600'">
                            {{ a.payment_status }}
                        </span>
                    </td>
                    <td class="text-right">
                        <Link :href="`/school/transport/fees/${a.id}`">
                            <Button variant="primary" size="sm">Collect / View</Button>
                        </Link>
                    </td>
                </tr>
            </tbody>
        </Table>

        <!-- Pagination -->
        <div v-if="allocations.links && allocations.links.length > 3" class="mt-4 flex flex-wrap gap-1 justify-end">
            <template v-for="(l, i) in allocations.links" :key="i">
                <Link v-if="l.url" :href="l.url"
                      :class="[
                          'px-3 py-1.5 rounded text-sm border',
                          l.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 hover:bg-gray-50'
                      ]"
                      v-html="l.label" preserve-scroll />
                <span v-else class="px-3 py-1.5 rounded text-sm text-gray-400" v-html="l.label"></span>
            </template>
        </div>

    </SchoolLayout>
</template>
