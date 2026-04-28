<script setup>
import { ref } from 'vue';
import { Link, router, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';

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

const refundBadge = (m) => ({
    cash:   'badge-green',
    cheque: 'badge-blue',
    adjust: 'badge-amber',
    none:   'badge-gray',
})[m] || 'badge-gray';
</script>

<template>
    <Head title="Stationary Returns" />
    <SchoolLayout title="Stationary Returns Report">
        <PageHeader title="↩ Returns Report" subtitle="All stationary returns within the selected date range. To accept a new return, open the student's allocation page.">
            <template #actions>
                <Link href="/school/stationary/allocations">
                                <Button variant="secondary">📋 All Allocations</Button>
                            </Link>
            </template>
        </PageHeader>

        <!-- Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Returns</p>
                <p class="stat-value">{{ summary.count }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Total Refund</p>
                <p class="stat-value" style="color:#dc2626;">{{ fmt(summary.refund_total) }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Items Returned</p>
                <p class="stat-value">{{ summary.qty_total }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Restocked</p>
                <p class="stat-value" style="color:#059669;">{{ summary.restock_qty }}</p>
            </div>
            <div class="stat-card" :class="{ 'stat-card--alert': summary.writeoff_qty > 0 }">
                <p class="stat-label">Written Off</p>
                <p class="stat-value" style="color:#b45309;">{{ summary.writeoff_qty }}</p>
            </div>
        </div>

        <!-- Filters -->
        <FilterBar :active="!!(from || to)" @clear="from = ''; to = ''; applyFilters()">
            <div class="form-field">
                <label>From</label>
                <input v-model="from" type="date" style="width:160px;" />
            </div>
            <div class="form-field">
                <label>To</label>
                <input v-model="to" type="date" style="width:160px;" />
            </div>
            <Button variant="secondary" size="sm" @click="applyFilters">Apply</Button>
        </FilterBar>

        <!-- Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <Table v-if="returns.length">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Items</th>
                            <th style="text-align:right;">Refund</th>
                            <th>Mode</th>
                            <th>Accepted by</th>
                            <th style="text-align:right;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in returns" :key="r.id">
                            <td style="font-size:0.78rem;color:#475569;">{{ fmtDate(r.returned_at) }}</td>
                            <td>
                                <div style="font-weight:500;">{{ studentName(r) }}</div>
                                <div style="font-size:0.74rem;color:#94a3b8;font-family:monospace;">{{ r.student?.admission_no }}</div>
                            </td>
                            <td style="font-size:0.82rem;">
                                <ul style="margin:0;padding-left:1rem;list-style:disc;color:#475569;">
                                    <li v-for="item in r.items" :key="item.id">
                                        {{ item.item?.name }} × {{ item.qty_returned }}
                                        <span style="color:#94a3b8;font-size:0.74rem;">[{{ item.condition }}{{ item.restock ? ', restocked' : ', written off' }}]</span>
                                    </li>
                                </ul>
                            </td>
                            <td style="text-align:right;font-weight:600;" :style="parseFloat(r.refund_amount) > 0 ? 'color:#dc2626;' : 'color:#94a3b8;'">{{ fmt(r.refund_amount) }}</td>
                            <td><span :class="['badge', refundBadge(r.refund_mode)]">{{ r.refund_mode }}</span></td>
                            <td style="font-size:0.82rem;">{{ r.accepted_by?.name || '—' }}</td>
                            <td style="text-align:right;">
                                <Link :href="`/school/stationary/allocations/${r.allocation_id}`">
                                    <Button size="xs" variant="secondary">View</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="text-align:center;padding:4rem 1rem;color:#9ca3af;">
                    <div style="font-size:2rem;margin-bottom:0.5rem;">↩</div>
                    <p style="font-size:0.92rem;color:#475569;font-weight:500;">No returns in this date range.</p>
                    <p style="font-size:0.78rem;color:#94a3b8;margin-top:0.25rem;">
                        To record a new return, open the student's allocation and click <strong>"↩ Accept Return"</strong>.
                    </p>
                </div>
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
.stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; margin: 0 0 0.375rem 0; }
.stat-value { font-size: 1.5rem; font-weight: 800; color: #111827; margin: 0; line-height: 1.1; }

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
.badge-blue  { background: #dbeafe; color: #1d4ed8; }
.badge-gray  { background: #f1f5f9; color: #64748b; }
</style>
