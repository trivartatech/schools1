<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    feePayments: Array,
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
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Day Book Ledger</h1>
                <p class="page-header-sub">Daily summary of cash inflows (Fees) and outflows (Expenses)</p>
            </div>
            <div class="flex gap-2">
                <Button variant="secondary" onclick="window.print()">🖨️ Print</Button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-6 print:hidden">
            <div class="card-body flex flex-wrap gap-4 items-end">
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">From Date</label>
                    <input type="date" v-model="filterForm.start_date" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">To Date</label>
                    <input type="date" v-model="filterForm.end_date" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">Filter by Class</label>
                    <select v-model="filterForm.class_id" @change="fetchSections" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-40">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto" v-if="filterForm.class_id && sections.length > 0">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">Filter by Section</label>
                    <select v-model="filterForm.section_id" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-40">
                        <option value="">All Sections</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <Button size="sm" @click="fetchDayBook">🔍 Search</Button>
                <Button variant="secondary" size="sm" v-if="filterForm.start_date || filterForm.end_date || filterForm.class_id" @click="resetFilter">Clear</Button>
            </div>
        </div>

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
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                            <tr>
                                <th>Receipt</th>
                                <th>Student</th>
                                <th>Paid By</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="fp in feePayments" :key="fp.id">
                                <td>
                                    <span class="font-mono text-xs">{{ fp.receipt_no }}</span>
                                    <div class="text-xs" style="color: var(--text-muted)">{{ fp.fee_head?.name }}</div>
                                </td>
                                <td class="font-medium">{{ fp.student?.first_name }} {{ fp.student?.last_name }}</td>
                                <td class="capitalize" style="color: var(--text-secondary)">{{ fp.payment_mode }}</td>
                                <td class="text-right font-bold" style="color: var(--success)">{{ formatCurrency(fp.amount_paid) }}</td>
                            </tr>
                            <tr v-if="feePayments.length === 0">
                                <td colspan="4" class="py-8 text-center italic" style="color: var(--text-muted)">No receipts for this date.</td>
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
@media print {
    body { background-color: white !important; }
    .page-header { display: none; }
    .card { box-shadow: none !important; border: none !important; margin-bottom: 2rem; }
    .card-header { padding: 0.5rem 0 !important; border-bottom: 2px solid #e5e7eb !important; background: transparent !important; }
    .erp-table th, .erp-table td { padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb; }
}
</style>
