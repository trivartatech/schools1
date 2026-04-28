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

const STATUS_BADGE = {
    paid:    'badge-green',
    partial: 'badge-amber',
    unpaid:  'badge-red',
    waived:  'badge-gray',
};
</script>

<template>
    <Head title="Stationary Fee Collection" />
    <SchoolLayout title="Stationary Fee Collection">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">💰 Stationary Fee Collection</h1>
                <p class="page-header-sub">Each kit allocation has its own outstanding balance. Receipts are numbered separately from regular fees.</p>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Total Outstanding</p>
                <p class="stat-value" style="color:#dc2626;">{{ fmt(summary.total_due) }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Total Collected</p>
                <p class="stat-value" style="color:#059669;">{{ fmt(summary.total_paid) }}</p>
            </div>
            <div class="stat-card" :class="{ 'stat-card--alert': summary.unpaid_count > 0 }">
                <p class="stat-label">Unpaid</p>
                <p class="stat-value" style="color:#dc2626;">{{ summary.unpaid_count }}</p>
            </div>
            <div class="stat-card" :class="{ 'stat-card--alert': summary.partial_count > 0 }">
                <p class="stat-label">Partial</p>
                <p class="stat-value" style="color:#b45309;">{{ summary.partial_count }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Paid</p>
                <p class="stat-value" style="color:#059669;">{{ summary.paid_count }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-body" style="padding: 0.75rem 1rem;">
                <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:0.625rem;">
                    <input v-model="filters.search" type="text" placeholder="Search by name or admission no…"
                           style="border: 1px solid var(--border, #e5e7eb); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem;" />
                    <select v-model="filters.status"
                            style="border: 1px solid var(--border, #e5e7eb); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; background: white;">
                        <option value="">All Status</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partial">Partial</option>
                        <option value="paid">Paid</option>
                        <option value="waived">Waived</option>
                    </select>
                    <select v-model="filters.class_id"
                            style="border: 1px solid var(--border, #e5e7eb); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; background: white;">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="filters.section_id" :disabled="!sections.length"
                            style="border: 1px solid var(--border, #e5e7eb); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; background: white;">
                        <option value="">{{ sections.length ? 'All Sections' : '—' }}</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <Table v-if="allocations.data.length">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:right;">Paid</th>
                            <th style="text-align:right;">Balance</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in allocations.data" :key="a.id">
                            <td>
                                <div style="font-weight:500;">{{ studentName(a) }}</div>
                                <div style="font-size:0.74rem;color:#94a3b8;font-family:monospace;">{{ a.student?.admission_no || '—' }}</div>
                            </td>
                            <td>{{ studentClassSection(a) }}</td>
                            <td style="text-align:right;">{{ fmt(a.total_amount) }}</td>
                            <td style="text-align:right;color:#059669;">{{ fmt(a.amount_paid) }}</td>
                            <td style="text-align:right;" :style="parseFloat(a.balance) > 0 ? 'color:#dc2626;font-weight:700;' : ''">{{ fmt(a.balance) }}</td>
                            <td><span :class="['badge', STATUS_BADGE[a.payment_status] || 'badge-gray']">{{ a.payment_status }}</span></td>
                            <td style="text-align:right;">
                                <Link :href="`/school/stationary/fees/${a.id}`">
                                    <Button size="xs">Collect →</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="text-align: center; padding: 4rem 1rem; color: #9ca3af;">
                    <div style="font-size: 2.4rem; margin-bottom: 0.5rem;">💰</div>
                    <p style="font-size: 0.95rem; color: #475569; font-weight: 500;">No allocations found.</p>
                </div>
            </div>

            <div v-if="allocations.last_page > 1" style="padding: 0.75rem 1rem; display: flex; gap: 0.375rem; flex-wrap: wrap; border-top: 1px solid var(--border, #e5e7eb);">
                <a v-for="link in allocations.links" :key="link.label"
                   :href="link.url || '#'" v-html="link.label"
                   :class="link.active ? 'pgn pgn-active' : 'pgn'"
                   :style="!link.url ? 'pointer-events:none;opacity:0.4' : ''"></a>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.875rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 640px)  { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.625rem;
    padding: 0.875rem 1rem;
}
.stat-card--alert {
    border-color: rgba(245, 158, 11, 0.4);
    background: rgba(245, 158, 11, 0.04);
}
.stat-label {
    font-size: 0.7rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
    margin: 0 0 0.375rem 0;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #111827;
    margin: 0;
    line-height: 1.1;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: capitalize;
}
.badge-green { background: #d1fae5; color: #059669; }
.badge-amber { background: #fef3c7; color: #b45309; }
.badge-red   { background: #fee2e2; color: #dc2626; }
.badge-gray  { background: #f1f5f9; color: #64748b; }

@media (max-width: 768px) {
    .card-body > div { grid-template-columns: 1fr 1fr !important; }
}

.pgn { padding: 0.25rem 0.625rem; border: 1px solid var(--border, #e5e7eb); border-radius: 0.375rem; font-size: 0.78rem; color: #475569; text-decoration: none; background: white; }
.pgn-active { background: var(--accent, #6366f1); color: white; border-color: var(--accent, #6366f1); }
</style>
