<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    defaulters: Array,
    classes: Array,
    filters: Object,
});

const filterForm = ref({
    class_id: props.filters.class_id || '',
    section_id: props.filters.section_id || '',
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

const fetchReport = () => {
    router.get(route('school.finance.due-report'), filterForm.value, {
        preserveState: true,
        replace: true
    });
};

const resetFilter = () => {
    filterForm.value.class_id = '';
    filterForm.value.section_id = '';
    sections.value = [];
    fetchReport();
};

import { onMounted } from 'vue';
import axios from 'axios';
import Table from '@/Components/ui/Table.vue';

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
                <h1 class="page-header-title">Due Report &amp; Defaulter List</h1>
                <p class="page-header-sub">Students with outstanding fee balances for the current academic year.</p>
            </div>
            <div class="flex items-center gap-3">
                <Button variant="secondary" onclick="window.print()">🖨️ Print List</Button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-6 print:hidden">
            <div class="card-body flex flex-wrap gap-4 items-end">
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">Filter by Class</label>
                    <select v-model="filterForm.class_id" @change="fetchSections" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-48">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto" v-if="filterForm.class_id && sections.length > 0">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--text-secondary)">Filter by Section</label>
                    <select v-model="filterForm.section_id" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 w-48">
                        <option value="">All Sections</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <Button size="sm" @click="fetchReport">🔍 Search</Button>
                <Button variant="secondary" size="sm" @click="resetFilter" v-if="filterForm.class_id">Clear Filter</Button>
            </div>
        </div>

        <!-- Print Header -->
        <div class="hidden print:block mb-6 text-center">
            <h1 class="text-2xl font-bold">Fee Defaulters List</h1>
            <p class="text-lg">Class Filter: {{ classes.find(c => c.id == filterForm.class_id)?.name || 'All Classes' }}</p>
            <p class="text-sm">As of {{ new Date().toLocaleDateString('en-GB') }}</p>
        </div>

        <!-- Defaulters Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <Table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Adm No.</th>
                            <th>Class</th>
                            <th>Contact</th>
                            <th class="text-right">Total Due</th>
                            <th class="text-right">Total Paid</th>
                            <th class="text-right" style="background: #fef2f2; color: #991b1b;">Balance</th>
                            <th class="text-center print:hidden">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="student in defaulters" :key="student.student_id">
                            <td class="font-medium" style="color: var(--text-primary)">{{ student.name }}</td>
                            <td class="text-sm" style="color: var(--text-secondary)">{{ student.admission_no }}</td>
                            <td class="text-sm" style="color: var(--text-secondary)">{{ student.class }}</td>
                            <td class="text-sm font-mono" style="color: var(--text-secondary)">{{ student.contact_no }}</td>
                            <td class="text-right text-sm">{{ formatCurrency(student.total_due) }}</td>
                            <td class="text-right text-sm" style="color: var(--success)">{{ formatCurrency(student.total_paid) }}</td>
                            <td class="text-right font-bold" style="background: #fef2f2; color: var(--danger)">
                                {{ formatCurrency(student.balance_due) }}
                            </td>
                            <td class="text-center print:hidden">
                                <Button size="xs" as="a" :href="`/school/fee/collect?student_id=${student.student_id}`">Pay Now</Button>
                            </td>
                        </tr>
                        <tr v-if="defaulters.length === 0">
                            <td colspan="8" class="p-8 text-center font-medium" style="color: var(--success)">
                                🎉 Excellent! No fee defaulters found matching the criteria.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="defaulters.length > 0">
                        <tr class="font-bold" style="background: var(--bg); border-top: 2px solid var(--border);">
                            <td colspan="4" class="p-3 text-right pr-4" style="color: var(--text-secondary)">Grand Total:</td>
                            <td class="p-3 text-right">{{ formatCurrency(defaulters.reduce((acc, curr) => acc + curr.total_due, 0)) }}</td>
                            <td class="p-3 text-right" style="color: var(--success)">{{ formatCurrency(defaulters.reduce((acc, curr) => acc + curr.total_paid, 0)) }}</td>
                            <td class="p-3 text-right" style="color: var(--danger)">{{ formatCurrency(defaulters.reduce((acc, curr) => acc + curr.balance_due, 0)) }}</td>
                            <td class="p-3 print:hidden"></td>
                        </tr>
                    </tfoot>
                </Table>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
@media print {
    body { background-color: white !important; }
    .page-header { display: none; }
    .card { box-shadow: none !important; border: 1px solid #e5e7eb !important; margin-bottom: 2rem; }
    .erp-table th, .erp-table td { padding: 0.5rem; border: 1px solid #e5e7eb; }
}
</style>
