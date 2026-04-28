<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    defaulters: Array,
});

const search = ref('');

const studentName = (a) =>
    a?.student?.user?.name
    || [a?.student?.first_name, a?.student?.last_name].filter(Boolean).join(' ')
    || '';

const filteredDefaulters = computed(() => {
    if (!search.value.trim()) return props.defaulters;
    const q = search.value.toLowerCase().trim();
    return props.defaulters.filter(d => {
        const name = studentName(d.allocation);
        const adm  = d.allocation?.student?.admission_no || '';
        return name.toLowerCase().includes(q) || adm.toLowerCase().includes(q);
    });
});

const totalDefaulters = computed(() => props.defaulters.length);

const totalAmountDue = computed(() => {
    return props.defaulters.reduce((sum, d) => sum + parseFloat(d.total_due || 0), 0);
});

function formatCurrency(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}
</script>

<template>
    <SchoolLayout title="Hostel Fee Defaulters">

        <!-- Header -->
        <PageHeader title="Hostel Fee Defaulters" subtitle="Students with overdue hostel fee payments" />

        <!-- Summary -->
        <div class="summary-grid">
            <div class="card">
                <div class="card-body summary-card">
                    <div class="summary-icon summary-icon--count">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="summary-label">Total Defaulters</p>
                        <p class="summary-value">{{ totalDefaulters }}</p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body summary-card">
                    <div class="summary-icon summary-icon--amount">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="summary-label">Total Amount Due</p>
                        <p class="summary-value">{{ formatCurrency(totalAmountDue) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search / Filter -->
        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-body" style="padding: 0.75rem 1rem;">
                <div class="search-bar">
                    <svg class="w-4 h-4 search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by student name..."
                        class="search-input"
                    />
                </div>
            </div>
        </div>

        <!-- Defaulters Table -->
        <div class="card">
            <div class="card-body" style="padding: 0;">
                <Table v-if="filteredDefaulters.length">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Admission No</th>
                            <th>Hostel</th>
                            <th>Room / Bed</th>
                            <th style="text-align: right;">Total Fee</th>
                            <th style="text-align: right;">Paid</th>
                            <th style="text-align: right;">Outstanding</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(defaulter, idx) in filteredDefaulters" :key="idx">
                            <td>
                                <span style="font-weight: 600; color: #111827;">
                                    {{ studentName(defaulter.allocation) || '--' }}
                                </span>
                            </td>
                            <td style="font-family: monospace; color: var(--text-muted);">
                                {{ defaulter.allocation?.student?.admission_no || '--' }}
                            </td>
                            <td>
                                <span style="font-weight: 500;">{{ defaulter.allocation?.bed?.room?.hostel?.name || '--' }}</span>
                            </td>
                            <td>
                                <span v-if="defaulter.allocation?.bed?.room?.room_number">Rm {{ defaulter.allocation.bed.room.room_number }}</span>
                                <span v-if="defaulter.allocation?.bed?.name"> · {{ defaulter.allocation.bed.name }}</span>
                            </td>
                            <td style="text-align: right; font-family: monospace;">
                                {{ formatCurrency(defaulter.allocation?.hostel_fee || 0) }}
                            </td>
                            <td style="text-align: right; font-family: monospace; color: #059669;">
                                {{ formatCurrency(defaulter.allocation?.amount_paid || 0) }}
                            </td>
                            <td style="text-align: right; font-family: monospace;">
                                <span class="badge badge-red">
                                    {{ formatCurrency(defaulter.total_due) }}
                                </span>
                            </td>
                            <td style="text-align: center; text-transform: capitalize;">
                                {{ defaulter.allocation?.payment_status || '--' }}
                            </td>
                            <td style="text-align: center;">
                                <Link :href="`/school/hostel/fees/${defaulter.allocation?.id}`">
                                    <Button variant="primary" size="xs">Collect</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else class="empty-state">
                    <svg class="w-12 h-12" style="margin: 0 auto 0.75rem; color: #e5e7eb;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p v-if="search.trim()">No defaulters found matching "{{ search }}".</p>
                    <p v-else>No fee defaulters found.</p>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 640px) {
    .summary-grid { grid-template-columns: 1fr; }
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.summary-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.summary-icon--count {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger, #ef4444);
}
.summary-icon--amount {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning, #f59e0b);
}

.summary-label {
    font-size: 0.75rem;
    color: #6b7280;
}
.summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
}

.search-bar {
    position: relative;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 0.75rem;
    color: #9ca3af;
    pointer-events: none;
}
.search-input {
    width: 100%;
    max-width: 20rem;
    padding: 0.5rem 0.75rem 0.5rem 2.25rem;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: var(--surface, #fff);
    color: var(--text-primary, #111827);
    outline: none;
    transition: border-color 0.15s;
}
.search-input:focus {
    border-color: var(--accent, #6366f1);
}
.search-input::placeholder {
    color: #9ca3af;
}

.empty-state {
    text-align: center;
    padding: 4rem 0;
    color: #9ca3af;
    font-size: 0.875rem;
}
</style>
