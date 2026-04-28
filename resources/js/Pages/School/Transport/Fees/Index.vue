<script setup>
import { onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';
import FilterBar from '@/Components/ui/FilterBar.vue';

const school = useSchoolStore();
const confirm = useConfirm();

const props = defineProps({
    allocations: Object,
    routes:      Array,
    classes:     Array,
    filters:     Object,
    summary:     Object,
});

const filters = ref({
    search:     props.filters?.search     ?? '',
    status:     props.filters?.status     ?? '',
    route_id:   props.filters?.route_id   ?? '',
    class_id:   props.filters?.class_id   ?? '',
    section_id: props.filters?.section_id ?? '',
});

const sections = ref([]);

function loadSections(classId) {
    if (!classId) {
        sections.value = [];
        filters.value.section_id = '';
        return Promise.resolve();
    }
    return axios.get(`/school/classes/${classId}/sections`)
        .then(res => {
            sections.value = res.data || [];
            if (!sections.value.find(s => s.id == filters.value.section_id)) {
                filters.value.section_id = '';
            }
        });
}

onMounted(() => {
    if (filters.value.class_id) loadSections(filters.value.class_id);
});

watch(() => filters.value.class_id, (v) => { loadSections(v); });

let debounceTimer = null;
watch(filters, (v) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/school/transport/fees', v, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}, { deep: true });

const batchPostingGl = ref(false);
async function batchPostGl() {
    const ok = await confirm({
        title: 'Post to General Ledger?',
        message: 'Post all unsynced transport-fee receipts to the General Ledger?',
        confirmLabel: 'Post All',
    });
    if (!ok) return;
    batchPostingGl.value = true;
    router.post('/school/transport/fees/batch-post-gl', {}, {
        preserveScroll: true,
        onFinish: () => { batchPostingGl.value = false; },
    });
}

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

function termLabel(a) {
    const total = Number(a?.months_opted ?? 0);
    if (!total) return '';
    const m = Math.floor(total);
    const d = Math.round((total - m) * 30);
    if (m && d) return `${m} mo ${d} d`;
    if (m)      return `${m} mo`;
    if (d)      return `${d} d`;
    return '';
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
        <PageHeader title="🚌 Transport Fee Collection" subtitle="Every transport allocation has its own outstanding balance. Receipts are numbered separately from regular fees.">
            <template #actions>
                <Button variant="secondary" @click="batchPostGl" :loading="batchPostingGl" title="Post all unsynced transport-fee receipts to General Ledger">
                    <svg v-if="batchPostingGl" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    Sync All to GL
                </Button>

            </template>
        </PageHeader>

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
        <FilterBar :active="!!(filters.search || filters.status || filters.route_id || filters.class_id || filters.section_id)" @clear="filters.search = ''; filters.status = ''; filters.route_id = ''; filters.class_id = ''; filters.section_id = ''">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="filters.search" type="search" placeholder="Search by name or admission no." />
            </div>
            <select v-model="filters.status" style="width:140px;">
                <option value="">All statuses</option>
                <option value="unpaid">Unpaid</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
                <option value="waived">Waived</option>
            </select>
            <select v-model="filters.route_id" style="width:160px;">
                <option value="">All routes</option>
                <option v-for="r in routes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
            </select>
            <select v-model="filters.class_id" style="width:140px;">
                <option value="">All classes</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-if="filters.class_id && sections.length > 0" v-model="filters.section_id" style="width:140px;">
                <option value="">All sections</option>
                <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </FilterBar>

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
                        <div class="text-xs text-gray-500">{{ studentClassSection(a) }}</div>
                    </td>
                    <td>
                        <div class="text-sm">{{ a.route?.route_name ?? '—' }}</div>
                        <div class="text-xs text-gray-500">{{ a.stop?.stop_name ?? '' }}</div>
                    </td>
                    <td class="text-right font-mono">
                        {{ fmt(a.transport_fee) }}
                        <div v-if="termLabel(a)" class="text-[10px] font-sans text-gray-400 mt-0.5">{{ termLabel(a) }}</div>
                    </td>
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
