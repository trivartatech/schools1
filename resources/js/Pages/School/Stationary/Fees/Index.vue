<script setup>
import { onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    allocations: Object,
    classes:     Array,
    filters:     Object,
    summary:     Object,
});

const filters = ref({
    search:     props.filters?.search     ?? '',
    status:     props.filters?.status     ?? '',
    class_id:   props.filters?.class_id   ?? '',
    section_id: props.filters?.section_id ?? '',
});

const sections = ref([]);

function loadSections(classId) {
    if (!classId) { sections.value = []; filters.value.section_id = ''; return Promise.resolve(); }
    return axios.get(`/school/classes/${classId}/sections`).then(res => {
        sections.value = res.data || [];
        if (!sections.value.find(s => s.id == filters.value.section_id)) filters.value.section_id = '';
    });
}

onMounted(() => { if (filters.value.class_id) loadSections(filters.value.class_id); });
watch(() => filters.value.class_id, (v) => { loadSections(v); });

let debounceTimer = null;
watch(filters, (v) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/school/stationary/fees', v, { preserveState: true, preserveScroll: true, replace: true });
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

function studentClassSection(a) {
    const h = a?.student?.current_academic_history;
    if (!h) return '—';
    const cls = h.course_class?.name ?? '';
    const sec = h.section?.name ?? '';
    return [cls, sec].filter(Boolean).join(' - ') || '—';
}

const STATUS_COLOURS = {
    paid:    'bg-green-100 text-green-700',
    partial: 'bg-amber-100 text-amber-700',
    unpaid:  'bg-rose-100 text-rose-700',
    waived:  'bg-gray-200 text-gray-600',
};
</script>

<template>
    <Head title="Stationary Fee Collection" />
    <SchoolLayout title="Stationary Fee Collection">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">📚 Stationary Fee Collection</h1>
                <p class="page-header-sub">Every kit allocation has its own outstanding balance. Receipts are numbered separately.</p>
            </div>
        </div>

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
                <p class="text-[11px] uppercase tracking-wider text-gray-500">Paid</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ summary.paid_count }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <input v-model="filters.search" type="text" placeholder="Search by name or admission no…"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                <select v-model="filters.status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Status</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="waived">Waived</option>
                </select>
                <select v-model="filters.class_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="filters.section_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" :disabled="!sections.length">
                    <option value="">{{ sections.length ? 'All Sections' : '—' }}</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <Table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in allocations.data" :key="a.id">
                        <td>
                            <div class="font-semibold">{{ studentName(a) }}</div>
                            <div class="text-xs text-gray-400 font-mono">{{ a.student?.admission_no || '—' }}</div>
                        </td>
                        <td>{{ studentClassSection(a) }}</td>
                        <td class="text-right">{{ fmt(a.total_amount) }}</td>
                        <td class="text-right">{{ fmt(a.amount_paid) }}</td>
                        <td class="text-right" :class="parseFloat(a.balance) > 0 ? 'text-rose-600 font-bold' : ''">{{ fmt(a.balance) }}</td>
                        <td>
                            <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-medium', STATUS_COLOURS[a.payment_status]]">
                                {{ a.payment_status }}
                            </span>
                        </td>
                        <td>
                            <Link :href="`/school/stationary/fees/${a.id}`" class="text-indigo-600 text-sm">Collect →</Link>
                        </td>
                    </tr>
                    <tr v-if="!allocations.data.length">
                        <td colspan="7" class="text-center py-8 text-gray-400">No allocations found.</td>
                    </tr>
                </tbody>
            </Table>
            <div v-if="allocations.last_page > 1" class="px-4 py-3 flex gap-1 flex-wrap">
                <a v-for="link in allocations.links" :key="link.label"
                   :href="link.url || '#'" v-html="link.label"
                   :class="link.active ? 'px-3 py-1 rounded bg-indigo-600 text-white text-xs' : 'px-3 py-1 rounded border border-gray-200 bg-white text-xs text-gray-600'"
                   :style="!link.url ? 'pointer-events:none;opacity:0.4' : ''"></a>
            </div>
        </div>
    </SchoolLayout>
</template>
