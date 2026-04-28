<script setup>
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ defaulters: Array });

const search = ref('');

const filtered = computed(() => {
    if (!search.value.trim()) return props.defaulters;
    const q = search.value.toLowerCase().trim();
    return props.defaulters.filter(d => {
        const name = studentName(d);
        const adm  = d?.student?.admission_no || '';
        return name.toLowerCase().includes(q) || adm.toLowerCase().includes(q);
    });
});

const totalDue = computed(() => filtered.value.reduce((s, d) => s + parseFloat(d.balance || 0), 0));

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

function fmt(n) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(Number(n || 0));
}
</script>

<template>
    <Head title="Stationary Fee Defaulters" />
    <SchoolLayout title="Stationary Fee Defaulters">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">💰 Stationary Fee Defaulters</h1>
                <p class="page-header-sub">Active allocations with outstanding balance.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Total Defaulters</p>
                <p class="stat-value" style="color:#dc2626;">{{ filtered.length }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Total Amount Due</p>
                <p class="stat-value" style="color:#dc2626;">{{ fmt(totalDue) }}</p>
            </div>
        </div>

        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-body" style="padding: 0.75rem 1rem;">
                <input v-model="search" type="text" placeholder="Search by name or admission no…"
                       style="width: 100%; max-width: 24rem; border: 1px solid var(--border, #e5e7eb); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem;" />
            </div>
        </div>

        <div class="card">
            <div style="overflow-x:auto;">
                <Table v-if="filtered.length">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:right;">Paid</th>
                            <th style="text-align:right;">Balance</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in filtered" :key="d.id">
                            <td>
                                <div style="font-weight:500;">{{ studentName(d) }}</div>
                                <div style="font-size:0.74rem;color:#94a3b8;font-family:monospace;">{{ d.student?.admission_no }}</div>
                            </td>
                            <td>{{ classSection(d) }}</td>
                            <td style="text-align:right;">{{ fmt(d.total_amount) }}</td>
                            <td style="text-align:right;color:#059669;">{{ fmt(d.amount_paid) }}</td>
                            <td style="text-align:right;color:#dc2626;font-weight:700;">{{ fmt(d.balance) }}</td>
                            <td><span class="badge badge-red">{{ d.payment_status }}</span></td>
                            <td style="text-align:right;">
                                <Link :href="`/school/stationary/fees/${d.id}`">
                                    <Button size="xs">Collect →</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else style="text-align:center;padding:4rem 1rem;color:#9ca3af;">
                    <div style="font-size:2rem;margin-bottom:0.5rem;">🎉</div>
                    <p style="font-size:0.92rem;color:#475569;font-weight:500;">No defaulters!</p>
                    <p v-if="search.trim()" style="font-size:0.78rem;color:#94a3b8;margin-top:0.25rem;">No matches for "{{ search }}"</p>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.875rem;
    margin-bottom: 1.25rem;
    max-width: 32rem;
}
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.625rem;
    padding: 0.875rem 1rem;
}
.stat-label {
    font-size: 0.7rem; color: #64748b; text-transform: uppercase;
    letter-spacing: 0.04em; font-weight: 600; margin: 0 0 0.375rem 0;
}
.stat-value {
    font-size: 1.5rem; font-weight: 800; color: #111827; margin: 0; line-height: 1.1;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: capitalize;
}
.badge-red { background: #fee2e2; color: #dc2626; }
</style>
