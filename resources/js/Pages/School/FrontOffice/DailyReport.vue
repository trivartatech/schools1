<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    date:       { type: String, default: '' },
    visitors:   { type: Object, default: () => ({}) },
    passes:     { type: Object, default: () => ({}) },
    complaints: { type: Object, default: () => ({}) },
    calls:      { type: Object, default: () => ({}) },
    mail:       { type: Object, default: () => ({}) },
});

const selectedDate = ref(props.date || school.today());

const onDateChange = () => {
    router.get('/school/front-office/daily-report', {
        date: selectedDate.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(selectedDate, onDateChange);

const formatDate = (d) => school.fmtDate(d);

const priorityBadge = (priority) => {
    const map = {
        low:      'badge-green',
        medium:   'badge-amber',
        high:     'badge-red',
        critical: 'badge-red',
    };
    return map[priority?.toLowerCase()] || 'badge-gray';
};

const statusBadge = (status) => {
    const map = {
        approved:    'badge-green',
        active:      'badge-green',
        completed:   'badge-green',
        pending:     'badge-amber',
        in_progress: 'badge-amber',
        rejected:    'badge-red',
        expired:     'badge-red',
    };
    return map[status?.toLowerCase()] || 'badge-gray';
};

const entries = (obj) => {
    if (!obj || typeof obj !== 'object') return [];
    return Object.entries(obj);
};

// Compute a simple percentage bar width for purpose breakdown
const purposeTotal = (byPurpose) => {
    if (!byPurpose || typeof byPurpose !== 'object') return 0;
    return Object.values(byPurpose).reduce((sum, v) => sum + (v || 0), 0);
};

const pctWidth = (count, total) => {
    if (!total) return '0%';
    return Math.round((count / total) * 100) + '%';
};

const barColors = [
    '#6366f1', '#22c55e', '#f59e0b', '#3b82f6',
    '#ec4899', '#a855f7', '#f97316', '#14b8a6',
];
</script>

<template>
    <SchoolLayout title="Daily Activity Report">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Daily Activity Report</h1>
                <p class="page-header-sub">{{ formatDate(selectedDate) }}</p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <Button variant="secondary" as="link" href="/school/front-office">
                    Back to Dashboard
                </Button>
            </div>
        </div>

        <!-- Date Picker -->
        <div class="card mb-6">
            <div class="card-body" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div class="form-field" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted);">Report Date</label>
                    <input
                        type="date"
                        v-model="selectedDate"
                        style="width: 14rem;"
                    />
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 1.25rem;">
                    Select a date to view the front office activity summary.
                </div>
            </div>
        </div>

        <div class="report-grid">

            <!-- ── Visitors Section ──────────────────────────────── -->
            <div class="card report-card">
                <div class="card-header">
                    <h3 class="card-title report-card__title report-card__title--indigo">Visitors</h3>
                </div>
                <div class="card-body">
                    <div class="kpi-row">
                        <div class="kpi-box">
                            <div class="kpi-box__value">{{ visitors.total ?? 0 }}</div>
                            <div class="kpi-box__label">Total Visitors</div>
                        </div>
                        <div class="kpi-box">
                            <div class="kpi-box__value kpi-val--green">{{ visitors.signed_out ?? 0 }}</div>
                            <div class="kpi-box__label">Signed Out</div>
                        </div>
                        <div class="kpi-box">
                            <div class="kpi-box__value kpi-val--amber">{{ visitors.still_in ?? 0 }}</div>
                            <div class="kpi-box__label">Still Inside</div>
                        </div>
                    </div>

                    <!-- Purpose Breakdown Bar -->
                    <div v-if="visitors.by_purpose && Object.keys(visitors.by_purpose).length" class="breakdown-section">
                        <div class="breakdown-label">By Purpose</div>
                        <div class="purpose-bar">
                            <div
                                v-for="(count, purpose, idx) in visitors.by_purpose"
                                :key="purpose"
                                class="purpose-bar__seg"
                                :style="{
                                    width: pctWidth(count, purposeTotal(visitors.by_purpose)),
                                    backgroundColor: barColors[idx % barColors.length],
                                }"
                                :title="purpose + ': ' + count"
                            ></div>
                        </div>
                        <div class="breakdown-pairs">
                            <div v-for="(count, purpose, idx) in visitors.by_purpose" :key="purpose" class="breakdown-pair">
                                <span class="breakdown-dot" :style="{ backgroundColor: barColors[idx % barColors.length] }"></span>
                                <span class="breakdown-key">{{ purpose }}</span>
                                <span class="breakdown-val">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Gate Passes Section ───────────────────────────── -->
            <div class="card report-card">
                <div class="card-header">
                    <h3 class="card-title report-card__title report-card__title--orange">Gate Passes</h3>
                </div>
                <div class="card-body">
                    <div class="kpi-row">
                        <div class="kpi-box">
                            <div class="kpi-box__value">{{ passes.total ?? 0 }}</div>
                            <div class="kpi-box__label">Total Passes</div>
                        </div>
                    </div>

                    <div v-if="passes.by_status && Object.keys(passes.by_status).length" class="breakdown-section">
                        <div class="breakdown-label">By Status</div>
                        <div class="breakdown-pairs">
                            <div v-for="(count, status) in passes.by_status" :key="status" class="breakdown-pair">
                                <span class="badge" :class="statusBadge(status)">{{ status }}</span>
                                <span class="breakdown-val">{{ count }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="passes.by_type && Object.keys(passes.by_type).length" class="breakdown-section">
                        <div class="breakdown-label">By Type</div>
                        <div class="breakdown-pairs">
                            <div v-for="(count, type) in passes.by_type" :key="type" class="breakdown-pair">
                                <span class="breakdown-key">{{ type }}</span>
                                <span class="breakdown-val">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Complaints Section ────────────────────────────── -->
            <div class="card report-card">
                <div class="card-header">
                    <h3 class="card-title report-card__title report-card__title--red">Complaints</h3>
                </div>
                <div class="card-body">
                    <div class="kpi-row">
                        <div class="kpi-box">
                            <div class="kpi-box__value">{{ complaints.new ?? 0 }}</div>
                            <div class="kpi-box__label">New Today</div>
                        </div>
                        <div class="kpi-box">
                            <div class="kpi-box__value kpi-val--green">{{ complaints.resolved_today ?? 0 }}</div>
                            <div class="kpi-box__label">Resolved Today</div>
                        </div>
                        <div class="kpi-box">
                            <div class="kpi-box__value kpi-val--red">{{ complaints.sla_breached ?? 0 }}</div>
                            <div class="kpi-box__label">SLA Breached</div>
                        </div>
                    </div>

                    <div v-if="complaints.by_priority && Object.keys(complaints.by_priority).length" class="breakdown-section">
                        <div class="breakdown-label">By Priority</div>
                        <div class="breakdown-pairs">
                            <div v-for="(count, priority) in complaints.by_priority" :key="priority" class="breakdown-pair">
                                <span class="badge" :class="priorityBadge(priority)">{{ priority }}</span>
                                <span class="breakdown-val">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Call Logs Section ──────────────────────────────── -->
            <div class="card report-card">
                <div class="card-header">
                    <h3 class="card-title report-card__title report-card__title--purple">Call Logs</h3>
                </div>
                <div class="card-body">
                    <div class="kpi-row">
                        <div class="kpi-box">
                            <div class="kpi-box__value">{{ calls.total ?? 0 }}</div>
                            <div class="kpi-box__label">Total Calls</div>
                        </div>
                        <div class="kpi-box">
                            <div class="kpi-box__value kpi-val--amber">{{ calls.followups_due ?? 0 }}</div>
                            <div class="kpi-box__label">Follow-ups Due</div>
                        </div>
                    </div>

                    <div v-if="calls.by_type && Object.keys(calls.by_type).length" class="breakdown-section">
                        <div class="breakdown-label">By Type</div>
                        <div class="breakdown-pairs">
                            <div v-for="(count, type) in calls.by_type" :key="type" class="breakdown-pair">
                                <span class="breakdown-key">{{ type }}</span>
                                <span class="breakdown-val">{{ count }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="calls.by_purpose && Object.keys(calls.by_purpose).length" class="breakdown-section">
                        <div class="breakdown-label">By Purpose</div>
                        <div class="breakdown-pairs">
                            <div v-for="(count, purpose) in calls.by_purpose" :key="purpose" class="breakdown-pair">
                                <span class="breakdown-key">{{ purpose }}</span>
                                <span class="breakdown-val">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Correspondence Section ────────────────────────── -->
            <div class="card report-card">
                <div class="card-header">
                    <h3 class="card-title report-card__title report-card__title--blue">Correspondence</h3>
                </div>
                <div class="card-body">
                    <div class="kpi-row">
                        <div class="kpi-box">
                            <div class="kpi-box__value">{{ mail.total ?? 0 }}</div>
                            <div class="kpi-box__label">Total Items</div>
                        </div>
                    </div>

                    <div v-if="mail.by_type && Object.keys(mail.by_type).length" class="breakdown-section">
                        <div class="breakdown-label">By Type</div>
                        <div class="breakdown-pairs">
                            <div v-for="(count, type) in mail.by_type" :key="type" class="breakdown-pair">
                                <span class="breakdown-key">{{ type }}</span>
                                <span class="breakdown-val">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Report Grid ─────────────────────────────────────────── */
.report-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .report-grid { grid-template-columns: 1fr; }
}

.report-card {
    overflow: hidden;
}

/* Colored left accent on section titles */
.report-card__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.report-card__title::before {
    content: '';
    display: inline-block;
    width: 4px;
    height: 1.25rem;
    border-radius: 2px;
    flex-shrink: 0;
}
.report-card__title--indigo::before { background: #6366f1; }
.report-card__title--orange::before { background: #f97316; }
.report-card__title--red::before    { background: #ef4444; }
.report-card__title--purple::before { background: #a855f7; }
.report-card__title--blue::before   { background: #3b82f6; }

/* ── KPI Boxes ───────────────────────────────────────────── */
.kpi-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.kpi-box {
    flex: 1;
    min-width: 6rem;
    padding: 0.875rem 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    text-align: center;
}

.kpi-box__value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.kpi-val--green { color: #16a34a; }
.kpi-val--amber { color: #d97706; }
.kpi-val--red   { color: #dc2626; }

.kpi-box__label {
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 0.375rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* ── Breakdown Sections ──────────────────────────────────── */
.breakdown-section {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
}

.breakdown-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.5rem;
}

/* Purpose stacked bar */
.purpose-bar {
    display: flex;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    background: var(--border);
    margin-bottom: 0.625rem;
}

.purpose-bar__seg {
    min-width: 4px;
    transition: width 0.3s ease;
}

/* Key-value pairs */
.breakdown-pairs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
}

.breakdown-pair {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8rem;
}

.breakdown-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.breakdown-key {
    color: var(--text-primary);
    font-weight: 500;
}

.breakdown-val {
    color: var(--text-muted);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

/* ── Responsive tweaks ───────────────────────────────────── */
@media (max-width: 640px) {
    .kpi-row {
        gap: 0.625rem;
    }
    .kpi-box {
        min-width: 5rem;
        padding: 0.625rem 0.75rem;
    }
    .kpi-box__value {
        font-size: 1.25rem;
    }
}
</style>
