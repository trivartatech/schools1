<script setup>
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';

const props = defineProps({ pending: Array });

const search = ref('');

const filtered = computed(() => {
    if (!search.value.trim()) return props.pending;
    const q = search.value.toLowerCase().trim();
    return props.pending.filter(p => {
        const name = studentName(p);
        const adm  = p?.student?.admission_no || '';
        return name.toLowerCase().includes(q) || adm.toLowerCase().includes(q);
    });
});

const summary = computed(() => {
    const totalEntitled = filtered.value.reduce((s, a) => s + totalEntitledQty(a), 0);
    const totalCollected = filtered.value.reduce((s, a) => s + totalCollectedQty(a), 0);
    return {
        students: filtered.value.length,
        items_pending: totalEntitled - totalCollected,
        total_entitled: totalEntitled,
    };
});

function studentName(a) {
    return a?.student?.user?.name
        || [a?.student?.first_name, a?.student?.last_name].filter(Boolean).join(' ')
        || '—';
}

function classSection(a) {
    const h = a?.student?.current_academic_history;
    if (!h) return '—';
    return [h.course_class?.name, h.section?.name].filter(Boolean).join(' - ') || '—';
}

function totalEntitledQty(a)  { return (a.line_items || []).reduce((s, l) => s + (l.qty_entitled  || 0), 0); }
function totalCollectedQty(a) { return (a.line_items || []).reduce((s, l) => s + (l.qty_collected || 0), 0); }
function pctCollected(a) {
    const e = totalEntitledQty(a);
    if (!e) return 0;
    return Math.round((totalCollectedQty(a) / e) * 100);
}

const collectionBadge = (s) => ({
    none: 'badge-red', partial: 'badge-amber', complete: 'badge-green',
})[s] || 'badge-gray';
</script>

<template>
    <Head title="Stationary Collection Pending" />
    <SchoolLayout title="Stationary Collection Pending">
        <PageHeader title="📦 Collection Pending" subtitle="Students who haven't fully picked up their stationary kit." />

        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Students Pending</p>
                <p class="stat-value" style="color:#b45309;">{{ summary.students }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Items Yet to Collect</p>
                <p class="stat-value" style="color:#b45309;">{{ summary.items_pending }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Total Items Entitled</p>
                <p class="stat-value">{{ summary.total_entitled }}</p>
            </div>
        </div>

        <FilterBar :active="!!search" @clear="search = ''">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="search" type="search" placeholder="Search by name or admission no…" />
            </div>
        </FilterBar>

        <div class="card">
            <div style="overflow-x:auto;">
                <Table v-if="filtered.length">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th style="text-align:right;">Entitled</th>
                            <th style="text-align:right;">Collected</th>
                            <th style="text-align:right;">% Done</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in filtered" :key="a.id">
                            <td>
                                <div style="font-weight:500;">{{ studentName(a) }}</div>
                                <div style="font-size:0.74rem;color:#94a3b8;font-family:monospace;">{{ a.student?.admission_no }}</div>
                            </td>
                            <td>{{ classSection(a) }}</td>
                            <td style="text-align:right;">{{ totalEntitledQty(a) }}</td>
                            <td style="text-align:right;">{{ totalCollectedQty(a) }}</td>
                            <td style="text-align:right;font-weight:700;" :style="pctCollected(a) === 0 ? 'color:#dc2626;' : 'color:#b45309;'">{{ pctCollected(a) }}%</td>
                            <td><span :class="['badge', collectionBadge(a.collection_status)]">{{ a.collection_status }}</span></td>
                            <td style="text-align:right;">
                                <Link :href="`/school/stationary/allocations/${a.id}`">
                                    <Button size="xs">Issue →</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="text-align:center;padding:4rem 1rem;color:#9ca3af;">
                    <div style="font-size:2rem;margin-bottom:0.5rem;">🎉</div>
                    <p style="font-size:0.92rem;color:#475569;font-weight:500;">All allocations fully collected.</p>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.875rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.625rem;
    padding: 0.875rem 1rem;
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
.badge-red   { background: #fee2e2; color: #dc2626; }
.badge-gray  { background: #f1f5f9; color: #94a3b8; }
</style>
