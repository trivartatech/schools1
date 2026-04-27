<script setup>
import Button from '@/Components/ui/Button.vue';
import { Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend } from 'chart.js';
import { Bar } from 'vue-chartjs';
import { computed, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Table from '@/Components/ui/Table.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend);

const props = defineProps({
    metrics: Object,
    chartData: Object,
    feesByHead: Array,
    topExpenseCategories: Array,
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

const fetchReport = () => {
    router.get(route('school.finance.reports.index'), filterForm.value, {
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
    fetchReport();
};

onMounted(() => {
    if (filterForm.value.class_id) {
        axios.get(route('school.classes.sections', filterForm.value.class_id))
            .then(res => {
                sections.value = res.data;
            });
    }
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(value);
};

// Chart options
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: { family: 'Inter', size: 12 }
            }
        },
        tooltip: {
            backgroundColor: 'rgba(17, 24, 39, 0.9)',
            titleFont: { family: 'Inter', size: 13 },
            bodyFont: { family: 'Inter', size: 13 },
            padding: 12,
            cornerRadius: 8,
            callbacks: {
                label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed.y !== null) {
                        label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(context.parsed.y);
                    }
                    return label;
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { color: '#f1f5f9', drawBorder: false },
            ticks: {
                font: { family: 'Inter', size: 11 },
                color: '#64748b',
                callback: function(value) {
                    if (value >= 100000) return '₹' + (value / 100000) + 'L';
                    if (value >= 1000) return '₹' + (value / 1000) + 'k';
                    return '₹' + value;
                }
            }
        },
        x: {
            grid: { display: false, drawBorder: false },
            ticks: { font: { family: 'Inter', size: 11 }, color: '#64748b' }
        }
    },
    interaction: {
        mode: 'index',
        intersect: false,
    },
};

const barChartData = computed(() => {
    return {
        labels: props.chartData.labels,
        datasets: [
            {
                label: 'Income (Fees)',
                backgroundColor: '#1169cd',
                borderRadius: 4,
                data: props.chartData.income,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            },
            {
                label: 'Expenses (Ops + Payroll)',
                backgroundColor: '#fc4336',
                borderRadius: 4,
                data: props.chartData.expense,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            }
        ]
    };
});

</script>

<template>
    <SchoolLayout title="Financial Reports">
        <div class="max-w-7xl mx-auto pb-10">

            <div class="page-header">
                <div>
                    <h1 class="page-header-title">Financial Dashboard</h1>
                    <p class="page-header-sub">Overview of school revenue, expenses, and payroll for the academic year.</p>
                </div>
                <div class="flex gap-3">
                    <Button variant="secondary" onclick="window.print()" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Report
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-6 print:hidden">
                <div class="card-body flex flex-wrap gap-4 items-end">
                    <div class="form-field">
                        <label>From Date</label>
                        <input type="date" v-model="filterForm.start_date" />
                    </div>
                    <div class="form-field">
                        <label>To Date</label>
                        <input type="date" v-model="filterForm.end_date" />
                    </div>
                    <div class="form-field">
                        <label>Filter by Class</label>
                        <select v-model="filterForm.class_id" @change="fetchSections" class="w-40">
                            <option value="">All Classes</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div v-if="filterForm.class_id && sections.length > 0" class="form-field">
                        <label>Filter by Section</label>
                        <select v-model="filterForm.section_id" class="w-40">
                            <option value="">All Sections</option>
                            <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <Button size="sm" @click="fetchReport">🔍 Search</Button>
                    <Button variant="secondary" size="sm" v-if="filterForm.start_date || filterForm.end_date || filterForm.class_id" @click="resetFilter">
                        Clear Filters
                    </Button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <!-- Total Fee Collected -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Total Fee Collected</p>
                        <h3 class="kpi-value text-blue-700">{{ formatCurrency(metrics.total_fees_collected) }}</h3>
                        <p v-if="metrics.total_transport_fees_collected !== undefined" class="kpi-sub">
                            Tuition {{ formatCurrency(metrics.total_tuition_fees_collected) }} · Transport {{ formatCurrency(metrics.total_transport_fees_collected) }}
                        </p>
                    </div>
                </div>

                <!-- Total Expenses -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-orange-100 text-orange-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Total Expenses</p>
                        <h3 class="kpi-value text-orange-700">{{ formatCurrency(metrics.total_expenses) }}</h3>
                    </div>
                </div>

                <!-- Total Payroll -->
                <div class="kpi-card">
                    <div class="kpi-icon bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Total Payroll Paid</p>
                        <h3 class="kpi-value text-purple-700">{{ formatCurrency(metrics.total_payroll) }}</h3>
                    </div>
                </div>

                <!-- Net Revenue -->
                <div class="kpi-card">
                    <div class="kpi-icon" :class="metrics.net_revenue >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'">
                        <svg v-if="metrics.net_revenue >= 0" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"/></svg>
                    </div>
                    <div class="kpi-content">
                        <p class="kpi-label">Net Revenue</p>
                        <h3 class="kpi-value" :class="metrics.net_revenue >= 0 ? 'text-green-700' : 'text-red-700'">
                            {{ formatCurrency(metrics.net_revenue) }}
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Main Chart -->
            <div class="card mb-8">
                <div class="card-header">
                    <h3 class="card-title">Income vs Expenses (Monthly)</h3>
                    <span class="badge badge-gray uppercase tracking-wider">Current Academic Year</span>
                </div>
                <div class="card-body">
                    <div v-if="chartData.labels.length > 0" class="h-[350px] w-full">
                        <Bar :data="barChartData" :options="chartOptions" />
                    </div>
                    <div v-else class="h-[350px] w-full flex flex-col items-center justify-center text-gray-400 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                        <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <p class="font-medium">No financial data available for this year</p>
                    </div>
                </div>
            </div>

            <!-- Breakdowns -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Fee Heads Breakdown -->
                <div class="card section-print-avoid overflow-hidden">
                    <div class="card-header">
                        <h3 class="card-title">Fee Collection by Head</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Fee Head</th>
                                    <th class="text-right">Collection</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="fee in feesByHead" :key="fee.name">
                                    <td class="font-medium text-gray-700">{{ fee.name }}</td>
                                    <td class="text-right font-semibold">{{ formatCurrency(fee.total) }}</td>
                                </tr>
                                <tr v-if="feesByHead.length === 0">
                                    <td colspan="2" class="text-center py-6 text-gray-500">No fee collections found</td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>

                <!-- Expense Categories Breakdown -->
                <div class="card section-print-avoid overflow-hidden">
                    <div class="card-header">
                        <h3 class="card-title">Top Operating Expenses</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-right">Amount Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="exp in topExpenseCategories" :key="exp.name">
                                    <td class="font-medium text-gray-700 truncate max-w-[200px]" :title="exp.name">{{ exp.name }}</td>
                                    <td class="text-right text-orange-600 font-semibold">{{ formatCurrency(exp.total) }}</td>
                                </tr>
                                <tr v-if="topExpenseCategories.length === 0">
                                    <td colspan="2" class="text-center py-6 text-gray-500">No expenses recorded</td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>
            </div>

        </div>
    </SchoolLayout>
</template>

<style scoped>
.kpi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.kpi-content {
    flex: 1;
    min-width: 0;
}
.kpi-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 4px;
}
.kpi-value {
    font-size: 1.375rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kpi-sub {
    margin-top: 4px;
    font-size: 0.7rem;
    color: #64748b;
    line-height: 1.3;
}

@media print {
    body { background: white !important; }
    .erp-sidebar, .erp-topbar, .btn { display: none !important; }
    .erp-main, .erp-content { padding: 0 !important; margin: 0 !important; overflow: visible !important; height: auto !important; }
    .kpi-card { border: 2px solid #e2e8f0; break-inside: avoid; }
    .section-print-avoid { break-inside: avoid; }
}
</style>
