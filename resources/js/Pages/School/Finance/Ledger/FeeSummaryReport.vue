<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    reports: Array,
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
    router.get(route('school.finance.fee-summary'), filterForm.value, {
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

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const totals = computed(() => {
    return props.reports.reduce((acc, curr) => {
        acc.total_fee += curr.total_fee;
        acc.concession += curr.concession;
        acc.payable += curr.payable;
        acc.paid += curr.paid;
        acc.balance += curr.balance;
        return acc;
    }, { total_fee: 0, concession: 0, payable: 0, paid: 0, balance: 0 });
});
</script>

<template>
    <SchoolLayout title="Fee Summary Report">
        <div class="page-header print:hidden">
            <div>
                <h1 class="page-header-title">Fee Summary Report</h1>
                <p class="page-header-sub">Detailed breakdown of student-wise fee structure, payments, and balances.</p>
            </div>
            <div class="flex gap-2">
                <Button variant="secondary" onclick="window.print()" class="print:hidden">🖨️ Print Report</Button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-6 print:hidden">
            <div class="card-body flex flex-wrap gap-4 items-end">
                <div class="form-field">
                    <label>Filter by Class</label>
                    <select v-model="filterForm.class_id" @change="fetchSections" class="w-48">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div v-if="filterForm.class_id && sections.length > 0" class="form-field">
                    <label>Filter by Section</label>
                    <select v-model="filterForm.section_id" class="w-48">
                        <option value="">All Sections</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <Button size="sm" @click="fetchReport">🔍 Search</Button>
                <Button variant="secondary" size="sm" v-if="filterForm.class_id" @click="resetFilter">Clear Filter</Button>
            </div>
        </div>

        <div class="hidden print:block mb-6 text-center">
            <h1 class="text-2xl font-bold">Fee Summary Report</h1>
            <p class="text-lg">Filter: {{ classes.find(c => c.id == filterForm.class_id)?.name || 'All Classes' }} {{ filterForm.section_id ? ` - ${sections.find(s => s.id == filterForm.section_id)?.name}` : '' }}</p>
            <p class="text-sm">Generated on {{ school.fmtDate(school.today()) }}</p>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <Table class="w-full whitespace-nowrap">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Adm No.</th>
                            <th>Student Name</th>
                            <th>Father Name</th>
                            <th>Contact</th>
                            <th>Class &amp; Sec</th>
                            <th class="text-right">Total Fee</th>
                            <th class="text-right">Concession</th>
                            <th class="text-right">Payable</th>
                            <th class="text-right text-green-700">Paid</th>
                            <th class="text-right bg-red-50 text-red-800">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(student, index) in reports" :key="student.student_id">
                            <td class="text-gray-600">{{ index + 1 }}</td>
                            <td class="text-gray-600 font-mono">{{ student.admission_no }}</td>
                            <td class="font-medium text-gray-900">{{ student.name }}</td>
                            <td class="text-gray-600">{{ student.father_name }}</td>
                            <td class="text-gray-600 font-mono">{{ student.contact_no }}</td>
                            <td class="text-gray-600">{{ student.class }}</td>
                            <td class="text-right">{{ formatCurrency(student.total_fee) }}</td>
                            <td class="text-right">{{ formatCurrency(student.concession) }}</td>
                            <td class="text-right font-semibold">{{ formatCurrency(student.payable) }}</td>
                            <td class="text-right text-green-700 font-semibold">{{ formatCurrency(student.paid) }}</td>
                            <td class="text-right font-bold bg-red-50 text-red-700">{{ formatCurrency(student.balance) }}</td>
                        </tr>
                        <tr v-if="reports.length === 0">
                            <td colspan="11" class="p-8 text-center text-gray-500">
                                No fee summaries found.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="reports.length > 0">
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td colspan="6" class="p-3 text-right">Grand Total:</td>
                            <td class="p-3 text-right">{{ formatCurrency(totals.total_fee) }}</td>
                            <td class="p-3 text-right">{{ formatCurrency(totals.concession) }}</td>
                            <td class="p-3 text-right">{{ formatCurrency(totals.payable) }}</td>
                            <td class="p-3 text-right text-green-700">{{ formatCurrency(totals.paid) }}</td>
                            <td class="p-3 text-right text-red-700">{{ formatCurrency(totals.balance) }}</td>
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
    .erp-table th, .erp-table td { padding: 0.5rem; border: 1px solid #e5e7eb; font-size: 11px; }
    .bg-red-50, .bg-gray-100 { background-color: transparent !important; }
    .text-red-700, .text-red-800, .text-green-700 { color: #000 !important; }
}
</style>
