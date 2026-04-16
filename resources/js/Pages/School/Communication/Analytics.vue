<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    dailyTrend: Array,
    channelStats: Array,
    topFailures: Array,
    summary: Object,
    days: Number,
});

const periods = [7, 14, 30, 60];

const maxDailyTotal = computed(() => {
    if (!props.dailyTrend || props.dailyTrend.length === 0) return 1;
    return Math.max(...props.dailyTrend.map(d => d.total), 1);
});

const channelGroups = computed(() => {
    const map = {};
    (props.channelStats || []).forEach(s => {
        if (!map[s.type]) map[s.type] = { type: s.type, delivered: 0, failed: 0 };
        if (s.status === 'sent') map[s.type].delivered = s.total;
        else if (s.status === 'failed') map[s.type].failed = s.total;
    });
    return Object.values(map).map(ch => {
        const total = ch.delivered + ch.failed;
        return { ...ch, total, rate: total > 0 ? ((ch.delivered / total) * 100).toFixed(1) : '0.0' };
    });
});

const formatDate = (dateStr) => school.fmtDate(dateStr);
</script>

<template>
    <SchoolLayout title="Delivery Analytics">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Delivery Analytics</h1>
                <p class="page-header-sub">Message delivery performance overview</p>
            </div>
            <div class="period-selector">
                <Link
                    v-for="p in periods"
                    :key="p"
                    :href="`/school/communication/analytics?days=${p}`"
                    class="period-btn"
                    :class="{ active: days === p }"
                    :preserve-state="true"
                >
                    {{ p }}d
                </Link>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Messages</div>
                <div class="summary-value">{{ summary?.total?.toLocaleString() ?? 0 }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Delivered</div>
                <div class="summary-value summary-green">{{ summary?.delivered?.toLocaleString() ?? 0 }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Delivery Rate</div>
                <div class="summary-value summary-blue">{{ summary?.rate ?? 0 }}%</div>
            </div>
        </div>

        <!-- Daily Trend Chart -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <h3 class="card-title">Daily Trend</h3>
            </div>
            <div class="card-body">
                <div v-if="dailyTrend && dailyTrend.length > 0" class="chart-container">
                    <div class="chart-bars">
                        <div v-for="day in dailyTrend" :key="day.date" class="chart-bar-group">
                            <div class="chart-bar-stack" :style="{ height: `${(day.total / maxDailyTotal) * 160}px` }">
                                <div
                                    class="chart-bar-segment bar-failed"
                                    :style="{ height: day.total > 0 ? `${(day.failed / day.total) * 100}%` : '0%' }"
                                    :title="`Failed: ${day.failed}`"
                                ></div>
                                <div
                                    class="chart-bar-segment bar-delivered"
                                    :style="{ height: day.total > 0 ? `${(day.delivered / day.total) * 100}%` : '0%' }"
                                    :title="`Delivered: ${day.delivered}`"
                                ></div>
                            </div>
                            <div class="chart-bar-label">{{ formatDate(day.date) }}</div>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="legend-dot dot-green"></span> Delivered</span>
                        <span class="legend-item"><span class="legend-dot dot-red"></span> Failed</span>
                    </div>
                </div>
                <div v-else class="empty-state">No trend data available for this period.</div>
            </div>
        </div>

        <!-- Channel Performance -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <h3 class="card-title">Channel Performance</h3>
            </div>
            <div class="card-body">
                <div v-if="channelGroups.length > 0" class="channel-grid">
                    <div v-for="ch in channelGroups" :key="ch.type" class="channel-card">
                        <div class="channel-card-header">
                            <span class="channel-type">{{ ch.type }}</span>
                            <span class="channel-rate">{{ ch.rate }}%</span>
                        </div>
                        <div class="channel-bar-track">
                            <div class="channel-bar-fill" :style="{ width: `${ch.rate}%` }"></div>
                        </div>
                        <div class="channel-card-stats">
                            <span class="stat-green">{{ ch.delivered }} delivered</span>
                            <span class="stat-red">{{ ch.failed }} failed</span>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-state">No channel data available.</div>
            </div>
        </div>

        <!-- Top Failure Channels -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top Failure Channels</h3>
            </div>
            <div class="card-body">
                <div v-if="topFailures && topFailures.length > 0">
                    <div v-for="(f, i) in topFailures" :key="i" class="failure-row">
                        <span class="failure-type">{{ f.type }}</span>
                        <span class="failure-count">{{ f.total }} failures</span>
                    </div>
                </div>
                <div v-else class="empty-state">No failures recorded.</div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 16px;
}
@media (max-width: 640px) {
    .summary-grid { grid-template-columns: 1fr; }
}
.summary-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.summary-label {
    font-size: .8rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 6px;
    font-weight: 600;
}
.summary-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
}
.summary-green { color: #16a34a; }
.summary-blue { color: #1169cd; }

.period-selector {
    display: flex;
    gap: 6px;
}
.period-btn {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 600;
    color: #64748b;
    background: #fff;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    transition: all .15s;
}
.period-btn:hover { border-color: #1169cd; color: #1169cd; }
.period-btn.active {
    background: #1169cd;
    color: #fff;
    border-color: #1169cd;
}

.card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
}
.card-title {
    font-size: .95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}
.card-body {
    padding: 20px;
}

.chart-container { overflow-x: auto; }
.chart-bars {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    min-height: 180px;
    padding-bottom: 8px;
}
.chart-bar-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 28px;
}
.chart-bar-stack {
    width: 100%;
    max-width: 36px;
    display: flex;
    flex-direction: column;
    border-radius: 4px 4px 0 0;
    overflow: hidden;
    min-height: 2px;
}
.chart-bar-segment { width: 100%; }
.bar-delivered { background: #16a34a; }
.bar-failed { background: #ef4444; }
.chart-bar-label {
    font-size: .65rem;
    color: #94a3b8;
    margin-top: 6px;
    white-space: nowrap;
}
.chart-legend {
    display: flex;
    gap: 16px;
    margin-top: 14px;
    justify-content: center;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .78rem;
    color: #64748b;
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.dot-green { background: #16a34a; }
.dot-red { background: #ef4444; }

.channel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
}
.channel-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
}
.channel-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.channel-type {
    font-weight: 700;
    font-size: .85rem;
    color: #1e293b;
    text-transform: capitalize;
}
.channel-rate {
    font-weight: 700;
    font-size: .85rem;
    color: #1169cd;
}
.channel-bar-track {
    width: 100%;
    height: 8px;
    background: #fee2e2;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}
.channel-bar-fill {
    height: 100%;
    background: #16a34a;
    border-radius: 4px;
    transition: width .3s ease;
}
.channel-card-stats {
    display: flex;
    justify-content: space-between;
    font-size: .75rem;
}
.stat-green { color: #16a34a; font-weight: 600; }
.stat-red { color: #ef4444; font-weight: 600; }

.failure-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.failure-row:last-child { border-bottom: none; }
.failure-type {
    font-weight: 600;
    font-size: .85rem;
    color: #1e293b;
    text-transform: capitalize;
}
.failure-count {
    font-size: .8rem;
    color: #ef4444;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 32px 16px;
    color: #94a3b8;
    font-size: .88rem;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.page-header-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}
.page-header-sub {
    font-size: .82rem;
    color: #64748b;
    margin: 2px 0 0;
}
</style>
