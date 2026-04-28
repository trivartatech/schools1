<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PrintButton from '@/Components/ui/PrintButton.vue';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import FilterBar from '@/Components/ui/FilterBar.vue';

const school = useSchoolStore();

const props = defineProps({
    feePayments: Array,
    transportPayments: { type: Array, default: () => [] },
    stationaryPayments: { type: Array, default: () => [] },
    hostelPayments: { type: Array, default: () => [] },
    expenses: Array,
    summary: Object,
    classes: Array,
    filters: Object,
});

const filterForm = ref({
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    class_id: props.filters?.class_id || '',
    section_id: props.filters?.section_id || '',
});

const sections = ref([]);

const fetchSections = () => {
    if (!filterForm.value.class_id) {
        sections.value = [];
        filterForm.value.section_id = '';
        return;
    }
    axios.get(route('school.classes.sections', filterForm.value.class_id))
        .then(res => {
            sections.value = res.data;
            if (!sections.value.find(s => s.id == filterForm.value.section_id)) {
                filterForm.value.section_id = '';
            }
        });
};

const fetchDayBook = () => {
    router.get(route('school.finance.day-book'), filterForm.value, {
        preserveState: true,
        replace: true
    });
};

const resetFilter = () => {
    filterForm.value.start_date = '';
    filterForm.value.end_date = '';
    filterForm.value.class_id = '';
    filterForm.value.section_id = '';
    sections.value = [];
    fetchDayBook();
};

onMounted(() => {
    if (filterForm.value.class_id) {
        axios.get(route('school.classes.sections', filterForm.value.class_id))
            .then(res => {
                sections.value = res.data;
            });
    }
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};
</script>

<template>
    <SchoolLayout>
        <!-- Header -->
        <PageHeader title="Day Book Ledger" subtitle="Daily summary of cash inflows (tuition, transport, hostel and stationary fees) and outflows (expenses)">
            <template #actions>
                <PrintButton />

            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar class="print:hidden" :active="!!(filterForm.start_date || filterForm.end_date || filterForm.class_id || filterForm.section_id)" @clear="resetFilter">
            <div class="form-field">
                <label>From Date</label>
                <input type="date" v-model="filterForm.start_date" style="width:160px;" />
            </div>
            <div class="form-field">
                <label>To Date</label>
                <input type="date" v-model="filterForm.end_date" style="width:160px;" />
            </div>
            <div class="form-field">
                <label>Class</label>
                <select v-model="filterForm.class_id" @change="fetchSections" style="width:160px;">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div class="form-field" v-if="filterForm.class_id && sections.length > 0">
                <label>Section</label>
                <select v-model="filterForm.section_id" style="width:160px;">
                    <option value="">All Sections</option>
                    <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <Button size="sm" @click="fetchDayBook">Search</Button>
        </FilterBar>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 print:hidden">
            <div class="card" style="background: #f0fdf4; border-color: #bbf7d0;">
                <div class="card-body">
                    <p class="text-sm font-medium mb-1" style="color: #15803d;">Total Inflow (Receipts)</p>
                    <p class="text-3xl font-bold" style="color: #14532d;">{{ formatCurrency(summary.total_inflow) }}</p>
                </div>
            </div>
            <div class="card" style="background: #fef2f2; border-color: #fecaca;">
                <div class="card-body">
                    <p class="text-sm font-medium mb-1" style="color: #b91c1c;">Total Outflow (Payments)</p>
                    <p class="text-3xl font-bold" style="color: #7f1d1d;">{{ formatCurrency(summary.total_outflow) }}</p>
                </div>
            </div>
            <div class="card" style="background: #eff6ff; border-color: #bfdbfe;">
                <div class="card-body">
                    <p class="text-sm font-medium mb-1" style="color: #1d4ed8;">Net Balance</p>
                    <p class="text-3xl font-bold" style="color: #1e3a8a;">{{ formatCurrency(summary.net_balance) }}</p>
                </div>
            </div>
        </div>

        <!-- Print Header -->
        <div class="hidden print:block mb-6 text-center">
            <h1 class="text-2xl font-bold">Cash Register (Day Book)</h1>
            <p class="text-lg">Period: {{ school.fmtDate(filterForm.start_date) }} to {{ school.fmtDate(filterForm.end_date) }}</p>
            <div class="flex justify-between font-bold border-b-2 border-black mt-4 pb-2">
                <span>Total Receipts: {{ formatCurrency(summary.total_inflow) }}</span>
                <span>Total Payments: {{ formatCurrency(summary.total_outflow) }}</span>
                <span>Closing Balance: {{ formatCurrency(summary.net_balance) }}</span>
            </div>
        </div>

        <!-- Split Ledger View -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Inflows Section -->
            <div class="card overflow-hidden" style="border-color: #bbf7d0;">
                <div class="card-header flex justify-between" style="background: #f0fdf4; border-color: #bbf7d0;">
                    <h2 class="card-title" style="color: #14532d;">Inflows (Fee Collections)</h2>
                    <span class="font-bold" style="color: #14532d;">{{ formatCurrency(summary.total_inflow) }}</span>
                </div>
                <div class="px-4 py-2 text-xs flex flex-wrap gap-4 border-b" style="background:#f7fef9;color:#15803d;border-color:#bbf7d0;">
                    <span>Tuition: <strong>{{ formatCurrency(summary.total_tuition_inflow) }}</strong></span>
                    <span>Transport: <strong>{{ formatCurrency(summary.total_transport_inflow) }}</strong></span>
                    <span>Hostel: <strong>{{ formatCurrency(summary.total_hostel_inflow) }}</strong></span>
                    <span>Stationary: <strong>{{ formatCurrency(summary.total_stationary_inflow) }}</strong></span>
                </div>
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                            <tr>
                                <th>Receipt</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Paid By</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="fp in feePayments" :key="`f-${fp.id}`">
                                <td>
                                    <span class="font-mono text-xs">{{ fp.receipt_no }}</span>
                                    <div class="text-xs" style="color: var(--text-muted)">{{ fp.fee_head?.name }}</div>
                                </td>
                                <td class="font-medium">{{ fp.student?.first_name }} {{ fp.student?.last_name }}</td>
                                <td><span class="badge-type tuition">Tuition</span></td>
                                <td class="capitalize" style="color: var(--text-secondary)">{{ fp.payment_mode }}</td>
                                <td class="text-right font-bold" style="color: var(--success)">{{ formatCurrency(fp.amount_paid) }}</td>
                            </tr>
                            <tr v-for="tp in transportPayments" :key="`t-${tp.id}`">
                                <td>
                                    <span class="font-mono text-xs">{{ tp.receipt_no }}</span>
                                    <div class="text-xs" style="color: var(--text-muted)">{{ tp.allocation?.route?.route_name }}<span v-if="tp.allocation?.stop?.stop_name"> · {{ tp.allocation.stop.stop_name }}</span></div>
                                </td>
                                <td class="font-medium">{{ tp.student?.first_name }} {{ tp.student?.last_name }}</td>
                                <td><span class="badge-type transport">Transport</span></td>
                                <td class="capitalize" style="color: var(--text-secondary)">{{ tp.payment_mode }}</td>
                                <td class="text-right font-bold" style="color: var(--success)">{{ formatCurrency(tp.amount_paid) }}</td>
                            </tr>
                            <tr v-for="sp in stationaryPayments" :key="`s-${sp.id}`">
                                <td>
                                    <span class="font-mono text-xs">{{ sp.receipt_no }}</span>
                                    <div class="text-xs" style="color: var(--text-muted)">Stationary kit</div>
                                </td>
                                <td class="font-medium">{{ sp.student?.first_name }} {{ sp.student?.last_name }}</td>
                                <td><span class="badge-type stationary">Stationary</span></td>
                                <td class="capitalize" style="color: var(--text-secondary)">{{ sp.payment_mode }}</td>
                                <td class="text-right font-bold" style="color: var(--success)">{{ formatCurrency(sp.amount_paid) }}</td>
                            </tr>
                            <tr v-for="hp in hostelPayments" :key="`h-${hp.id}`">
                                <td>
                                    <span class="font-mono text-xs">{{ hp.receipt_no }}</span>
                                    <div class="text-xs" style="color: var(--text-muted)">{{ hp.allocation?.bed?.room?.hostel?.name }}<span v-if="hp.allocation?.bed?.room?.name"> · {{ hp.allocation.bed.room.name }}</span></div>
                                </td>
                                <td class="font-medium">{{ hp.student?.first_name }} {{ hp.student?.last_name }}</td>
                                <td><span class="badge-type hostel">Hostel</span></td>
                                <td class="capitalize" style="color: var(--text-secondary)">{{ hp.payment_mode }}</td>
                                <td class="text-right font-bold" style="color: var(--success)">{{ formatCurrency(hp.amount_paid) }}</td>
                            </tr>
                            <tr v-if="feePayments.length === 0 && transportPayments.length === 0 && stationaryPayments.length === 0 && hostelPayments.length === 0">
                                <td colspan="5" class="py-8 text-center italic" style="color: var(--text-muted)">No receipts for this date.</td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>

            <!-- Outflows Section -->
            <div class="card overflow-hidden" style="border-color: #fecaca;">
                <div class="card-header flex justify-between" style="background: #fef2f2; border-color: #fecaca;">
                    <h2 class="card-title" style="color: #7f1d1d;">Outflows (Expenses)</h2>
                    <span class="font-bold" style="color: #7f1d1d;">{{ formatCurrency(summary.total_outflow) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Particulars</th>
                                <th>Paid Via</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="exp in expenses" :key="exp.id">
                                <td class="font-mono text-xs" style="color: var(--text-muted)">EXP-{{ exp.id }}</td>
                                <td>
                                    <span class="font-medium block" style="color: var(--text-primary)">{{ exp.title }}</span>
                                    <span class="badge badge-gray mt-0.5">{{ exp.category?.name }}</span>
                                </td>
                                <td class="capitalize" style="color: var(--text-secondary)">{{ exp.payment_mode }}</td>
                                <td class="text-right font-bold" style="color: var(--danger)">{{ formatCurrency(exp.amount) }}</td>
                            </tr>
                            <tr v-if="expenses.length === 0">
                                <td colspan="4" class="py-8 text-center italic" style="color: var(--text-muted)">No expenses recorded for this date.</td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>

        </div>
    </SchoolLayout>
</template>

<style scoped>
.badge-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.badge-type.tuition    { background: #d1fae5; color: #047857; }
.badge-type.transport  { background: #cffafe; color: #0891b2; }
.badge-type.hostel     { background: #fef3c7; color: #b45309; }
.badge-type.stationary { background: #ede9fe; color: #6d28d9; }

@media print {
    body { background-color: white !important; }
    .page-header { display: none; }
    .card { box-shadow: none !important; border: none !important; margin-bottom: 2rem; }
    .card-header { padding: 0.5rem 0 !important; border-bottom: 2px solid #e5e7eb !important; background: transparent !important; }
    .erp-table th, .erp-table td { padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb; }
}
</style>
