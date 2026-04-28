<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement,
    LineElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    historical:          Array,
    forecast:            Array,
    avg_rate:            Number,
    classes:             Array,
    sections:            Array,
    selected_class_id:   Number,
    selected_section_id: Number,
});

const selectedClass   = ref(props.selected_class_id ?? '');
const selectedSection = ref(props.selected_section_id ?? '');

const applyFilter = () => {
    router.get('/school/attendance/forecast', {
        class_id:   selectedClass.value   || undefined,
        section_id: selectedSection.value || undefined,
    }, { preserveScroll: true });
};

const onClassChange = () => {
    selectedSection.value = '';
    applyFilter();
};

// ── Chart data ────────────────────────────────────────────────────────────────
const histLabels = computed(() => props.historical.map(h => h.date.slice(5)));  // MM-DD
const foreLabels = computed(() => props.forecast.map(f => f.date.slice(5)));

const allLabels = computed(() => [
    ...histLabels.value,
    ...foreLabels.value,
]);

const historicalData = computed(() => [
    ...props.historical.map(h => h.rate),
    ...props.forecast.map(() => null),
]);

const forecastData = computed(() => [
    ...props.historical.map((_, i) => i === props.historical.length - 1 ? props.historical[i].rate : null),
    ...props.forecast.map(f => f.projected_rate),
]);

const chartData = computed(() => ({
    labels: allLabels.value,
    datasets: [
        {
            label: 'Actual Attendance %',
            data: historicalData.value,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.08)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
            spanGaps: false,
        },
        {
            label: 'Forecast %',
            data: forecastData.value,
            borderColor: '#f59e0b',
            borderDash: [6, 4],
            backgroundColor: 'rgba(245,158,11,0.06)',
            fill: false,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
            spanGaps: true,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top' },
        tooltip: {
            callbacks: {
                label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y !== null ? ctx.parsed.y + '%' : '—'}`,
            },
        },
    },
    scales: {
        y: {
            min: 0,
            max: 100,
            ticks: { callback: v => v + '%' },
            grid: { color: 'rgba(0,0,0,0.05)' },
        },
        x: { grid: { display: false } },
    },
};

// ── Trend indicator ────────────────────────────────────────────────────────────
const trend = computed(() => {
    if (!props.forecast.length) return null;
    const last  = props.historical.at(-1)?.rate ?? 0;
    const proj  = props.forecast.at(-1)?.projected_rate ?? 0;
    const diff  = (proj - last).toFixed(1);
    return { diff, up: diff >= 0 };
});

const lastSevenAvg = computed(() => {
    const rates = props.historical.slice(-7).map(h => h.rate).filter(r => r !== null);
    return rates.length ? (rates.reduce((a, b) => a + b, 0) / rates.length).toFixed(1) : null;
});
</script>

<template>
    <SchoolLayout title="Attendance Forecast">
        <PageHeader title="Attendance Forecast" />

        <!-- Filters -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-field" style="min-width:160px;margin:0;">
                    <label>Class</label>
                    <select v-model="selectedClass" @change="onClassChange">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="form-field" style="min-width:140px;margin:0;">
                    <label>Section</label>
                    <select v-model="selectedSection" @change="applyFilter" :disabled="!selectedClass">
                        <option value="">All Sections</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">
            <div class="card">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div style="font-size:1.75rem;font-weight:700;color:#6366f1;">
                        {{ avg_rate !== null ? avg_rate + '%' : '—' }}
                    </div>
                    <div style="font-size:.8rem;color:#64748b;margin-top:4px;">30-Day Avg Attendance</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div style="font-size:1.75rem;font-weight:700;color:#0ea5e9;">
                        {{ lastSevenAvg !== null ? lastSevenAvg + '%' : '—' }}
                    </div>
                    <div style="font-size:.8rem;color:#64748b;margin-top:4px;">Last 7 Days Avg</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align:center;padding:20px;">
                    <div v-if="trend" style="font-size:1.75rem;font-weight:700;" :style="{ color: trend.up ? '#10b981' : '#ef4444' }">
                        {{ trend.up ? '▲' : '▼' }} {{ Math.abs(trend.diff) }}%
                    </div>
                    <div v-else style="font-size:1.75rem;font-weight:700;color:#94a3b8;">—</div>
                    <div style="font-size:.8rem;color:#64748b;margin-top:4px;">7-Day Forecast Trend</div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <span class="card-title">30-Day Attendance + 7-Day Forecast</span>
            </div>
            <div class="card-body">
                <div v-if="historical.length === 0" style="text-align:center;padding:60px 0;color:#94a3b8;">
                    No attendance data found for the selected filters.
                </div>
                <div v-else style="height:320px;">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
                <p v-if="forecast.length" style="margin-top:12px;font-size:.78rem;color:#94a3b8;text-align:center;">
                    Dashed line = linear regression projection based on last 7 recorded days.
                </p>
                <p v-else-if="historical.length > 0" style="margin-top:12px;font-size:.78rem;color:#f59e0b;text-align:center;">
                    Need at least 3 days of data to generate a forecast.
                </p>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Daily Breakdown</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                            <th style="padding:10px 16px;text-align:left;color:#475569;font-weight:600;">Date</th>
                            <th style="padding:10px 16px;text-align:right;color:#475569;font-weight:600;">Present</th>
                            <th style="padding:10px 16px;text-align:right;color:#475569;font-weight:600;">Absent</th>
                            <th style="padding:10px 16px;text-align:right;color:#475569;font-weight:600;">Total</th>
                            <th style="padding:10px 16px;text-align:right;color:#475569;font-weight:600;">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="h in [...historical].reverse()" :key="h.date"
                            style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:9px 16px;color:#1e293b;font-weight:500;">{{ h.date }}</td>
                            <td style="padding:9px 16px;text-align:right;color:#16a34a;">{{ h.present }}</td>
                            <td style="padding:9px 16px;text-align:right;color:#dc2626;">{{ h.absent }}</td>
                            <td style="padding:9px 16px;text-align:right;color:#475569;">{{ h.total }}</td>
                            <td style="padding:9px 16px;text-align:right;">
                                <span :style="{
                                    fontWeight: 600,
                                    color: h.rate >= 85 ? '#16a34a' : h.rate >= 70 ? '#d97706' : '#dc2626'
                                }">{{ h.rate !== null ? h.rate + '%' : '—' }}</span>
                            </td>
                        </tr>
                        <tr v-if="historical.length === 0">
                            <td colspan="5" style="padding:40px;text-align:center;color:#94a3b8;">No data</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </SchoolLayout>
</template>
