<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Line, Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement, LineElement,
    BarElement, ArcElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';

ChartJS.register(
    CategoryScale, LinearScale, PointElement, LineElement,
    BarElement, ArcElement, Title, Tooltip, Legend, Filler,
);

const props = defineProps({
    attendanceTrend:   Array,
    feeCollection:     Array,
    enrollmentByClass: Array,
    examPerformance:   Array,
    staffLeaveHeatmap: Array,
    summary:           Object,
});

// ── Attendance trend chart ────────────────────────────────────────────────
const attendanceData = {
    labels: props.attendanceTrend.map(d => {
        const dt = new Date(d.date);
        return `${dt.getDate()} ${dt.toLocaleString('en', { month: 'short' })}`;
    }),
    datasets: [{
        label: 'Attendance %',
        data: props.attendanceTrend.map(d => d.rate),
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,.1)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        spanGaps: true,
    }],
};

const attendanceOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
        x: { ticks: { maxTicksLimit: 10 } },
    },
};

// ── Fee collection chart ──────────────────────────────────────────────────
const feeData = {
    labels: props.feeCollection.map(d => d.month),
    datasets: [
        {
            label: 'Collected (₹)',
            data: props.feeCollection.map(d => d.collected),
            backgroundColor: 'rgba(16,185,129,.75)',
            borderRadius: 4,
        },
        {
            label: 'Target (₹)',
            data: props.feeCollection.map(d => d.target),
            backgroundColor: 'rgba(226,232,240,.8)',
            borderRadius: 4,
        },
    ],
};

const feeOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
        y: { ticks: { callback: v => '₹' + (v >= 100000 ? (v / 100000).toFixed(1) + 'L' : (v / 1000).toFixed(0) + 'K') } },
    },
};

// ── Enrollment doughnut ───────────────────────────────────────────────────
const colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16','#ec4899','#14b8a6'];

const enrollmentData = {
    labels: props.enrollmentByClass.map(d => d.class),
    datasets: [{
        data: props.enrollmentByClass.map(d => d.count),
        backgroundColor: colors,
        borderWidth: 2,
        borderColor: '#fff',
    }],
};

const enrollmentOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'right' } },
};

// ── Exam performance bar ──────────────────────────────────────────────────
const examData = {
    labels: props.examPerformance.map(d => d.class),
    datasets: [
        {
            label: 'Avg %',
            data: props.examPerformance.map(d => d.avg_pct),
            backgroundColor: 'rgba(99,102,241,.7)',
            borderRadius: 4,
        },
        {
            label: 'Pass Rate %',
            data: props.examPerformance.map(d => d.pass_rate),
            backgroundColor: 'rgba(16,185,129,.7)',
            borderRadius: 4,
        },
    ],
};

const examOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
        y: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
    },
};

// ── Staff leave heatmap bar ───────────────────────────────────────────────
const leaveData = {
    labels: props.staffLeaveHeatmap.map(d => d.month),
    datasets: [{
        label: 'Leave Days',
        data: props.staffLeaveHeatmap.map(d => d.days),
        backgroundColor: props.staffLeaveHeatmap.map(d => {
            const v = d.days;
            if (v === 0) return '#f1f5f9';
            if (v <= 5)  return '#fde68a';
            if (v <= 15) return '#fbbf24';
            return '#ef4444';
        }),
        borderRadius: 4,
    }],
};

const leaveOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } },
};

const fmt = (n) => new Intl.NumberFormat('en-IN').format(n);
const fmtCurrency = (n) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);
</script>

<template>
    <SchoolLayout title="Analytics Dashboard">
        <PageHeader title="Analytics Dashboard">
            <template #subtitle>
                <p style="color:#64748b;font-size:.9rem;">School-wide performance overview for the current academic year.</p>
            </template>
        </PageHeader>

        <!-- Summary KPI Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px;">
            <div class="card" style="padding:16px;">
                <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Active Students</div>
                <div style="font-size:1.8rem;font-weight:700;color:#1e293b;">{{ fmt(summary.total_students) }}</div>
            </div>
            <div class="card" style="padding:16px;">
                <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Today's Attendance</div>
                <div style="font-size:1.8rem;font-weight:700;" :style="{ color: summary.attendance_pct >= 90 ? '#10b981' : summary.attendance_pct >= 75 ? '#f59e0b' : '#ef4444' }">
                    {{ summary.attendance_pct != null ? summary.attendance_pct + '%' : '—' }}
                </div>
                <div style="font-size:.75rem;color:#94a3b8;">
                    {{ summary.present_today }} / {{ summary.total_students }} total
                    <span v-if="summary.unmarked_today > 0" style="color:#f59e0b;"> · {{ summary.unmarked_today }} unmarked</span>
                </div>
            </div>
            <div class="card" style="padding:16px;">
                <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">This Month Fee</div>
                <div style="font-size:1.6rem;font-weight:700;color:#10b981;">{{ fmtCurrency(summary.this_month_fee) }}</div>
            </div>
            <div class="card" style="padding:16px;">
                <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Pending Leave Req.</div>
                <div style="font-size:1.8rem;font-weight:700;" :style="{ color: summary.pending_leaves > 5 ? '#ef4444' : '#1e293b' }">{{ summary.pending_leaves }}</div>
            </div>
        </div>

        <!-- Row 1: Attendance Trend + Fee Collection -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Attendance Trend (Last 30 Days)</span>
                </div>
                <div style="padding:16px;height:240px;">
                    <Line :data="attendanceData" :options="attendanceOptions" />
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Fee Collection vs Target (Monthly)</span>
                </div>
                <div style="padding:16px;height:240px;">
                    <Bar :data="feeData" :options="feeOptions" />
                </div>
            </div>
        </div>

        <!-- Row 2: Enrollment + Exam Performance -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Enrollment by Class</span>
                </div>
                <div style="padding:16px;height:260px;display:flex;align-items:center;justify-content:center;">
                    <div v-if="enrollmentByClass.length" style="width:100%;height:100%;">
                        <Doughnut :data="enrollmentData" :options="enrollmentOptions" />
                    </div>
                    <div v-else style="color:#94a3b8;font-size:.9rem;">No enrollment data available.</div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Exam Performance by Class</span>
                </div>
                <div style="padding:16px;height:260px;">
                    <div v-if="examPerformance.length">
                        <Bar :data="examData" :options="examOptions" />
                    </div>
                    <div v-else style="display:flex;align-items:center;justify-content:center;height:220px;color:#94a3b8;font-size:.9rem;">No exam marks recorded yet.</div>
                </div>
            </div>
        </div>

        <!-- Row 3: Staff Leave Heatmap -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Staff Leave Heatmap ({{ new Date().getFullYear() }})</span>
            </div>
            <div style="padding:16px;height:200px;">
                <Bar :data="leaveData" :options="leaveOptions" />
            </div>
            <div style="padding:0 16px 12px;display:flex;gap:16px;flex-wrap:wrap;font-size:.75rem;color:#64748b;">
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#f1f5f9;border-radius:2px;display:inline-block;"></span> 0 days</span>
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#fde68a;border-radius:2px;display:inline-block;"></span> 1–5 days</span>
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#fbbf24;border-radius:2px;display:inline-block;"></span> 6–15 days</span>
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#ef4444;border-radius:2px;display:inline-block;"></span> 16+ days</span>
            </div>
        </div>
    </SchoolLayout>
</template>
