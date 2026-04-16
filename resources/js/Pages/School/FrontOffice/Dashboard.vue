<script setup>
import Button from '@/Components/ui/Button.vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    activity: { type: Array, default: () => [] },
});

const statCards = [
    { key: 'today_visitors',     label: "Today's Visitors",    color: 'indigo' },
    { key: 'visitors_in',        label: 'Currently Inside',    color: 'green' },
    { key: 'expected_visitors',  label: 'Expected Visitors',   color: 'blue' },
    { key: 'pending_passes',     label: 'Pending Passes',      color: 'orange' },
    { key: 'active_passes',      label: 'Active Passes',       color: 'amber' },
    { key: 'open_complaints',    label: 'Open Complaints',     color: 'red' },
    { key: 'sla_breached',       label: 'SLA Breached',        color: 'red' },
    { key: 'overdue_followups',  label: 'Overdue Follow-ups',  color: 'pink' },
    { key: 'today_followups',    label: "Today's Follow-ups",  color: 'purple' },
    { key: 'pending_mail',       label: 'Pending Mail',        color: 'blue' },
];

const quickActions = [
    { label: 'Visitor Log',     href: '/school/front-office/visitors',          icon: 'visitor' },
    { label: 'Gate Passes',     href: '/school/front-office/gate-passes',       icon: 'pass' },
    { label: 'Complaints',      href: '/school/front-office/complaints',        icon: 'complaint' },
    { label: 'Call Logs',       href: '/school/front-office/call-logs',         icon: 'call' },
    { label: 'Correspondence',  href: '/school/front-office/correspondence',    icon: 'mail' },
    { label: 'Pass Scanner',    href: '/school/front-office/gate-passes/scanner', icon: 'scanner' },
    { label: 'Follow-ups',     href: '/school/front-office/call-logs-follow-ups', icon: 'followup' },
];

const iconMap = {
    visitor:   '👤',
    pass:      '🎫',
    complaint: '📋',
    call:      '📞',
    mail:      '📨',
    scanner:   '📱',
    followup:  '🔔',
};

const typeConfig = {
    visitor:   { badge: 'badge-blue',   icon: '👤' },
    gate_pass: { badge: 'badge-amber',  icon: '🎫' },
    complaint: { badge: 'badge-red',    icon: '📋' },
};

const statusBadge = (status) => {
    const map = {
        'active':      'badge-green',
        'completed':   'badge-green',
        'resolved':    'badge-green',
        'signed_out':  'badge-green',
        'pending':     'badge-amber',
        'in_progress': 'badge-amber',
        'open':        'badge-red',
        'expired':     'badge-red',
        'overdue':     'badge-red',
    };
    return map[status?.toLowerCase()] || 'badge-gray';
};

const school = useSchoolStore();

const formatTime = (t) => {
    if (!t) return '';
    return school.fmtDateTime(t);
};
</script>

<template>
    <SchoolLayout title="Front Office Dashboard">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Front Office Dashboard</h1>
                <p class="page-header-sub">Real-time overview of visitors, passes, complaints, and correspondence</p>
            </div>
            <Button as="link" href="/school/front-office/daily-report">
                Daily Report
            </Button>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div
                v-for="s in statCards"
                :key="s.key"
                class="stat-card"
                :class="'stat-card--' + s.color"
            >
                <div class="stat-card__bar" :class="'stat-bar--' + s.color"></div>
                <div class="stat-card__body">
                    <div class="stat-card__value">{{ stats[s.key] ?? 0 }}</div>
                    <div class="stat-card__label">{{ s.label }}</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="actions-grid">
                    <Link
                        v-for="a in quickActions"
                        :key="a.href"
                        :href="a.href"
                        class="action-btn"
                    >
                        <span class="action-btn__icon">{{ iconMap[a.icon] }}</span>
                        <span class="action-btn__label">{{ a.label }}</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Activity</h3>
            </div>

            <div v-if="activity.length === 0" class="card-body" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                No recent activity to display.
            </div>

            <div v-else class="activity-list">
                <div
                    v-for="(item, idx) in activity"
                    :key="idx"
                    class="activity-item"
                >
                    <!-- Type Icon -->
                    <span
                        class="activity-item__icon badge"
                        :class="typeConfig[item.type]?.badge || 'badge-gray'"
                    >
                        {{ typeConfig[item.type]?.icon || '📌' }}
                    </span>

                    <!-- Content -->
                    <div class="activity-item__content">
                        <div class="activity-item__title">{{ item.title }}</div>
                        <div class="activity-item__detail">{{ item.detail }}</div>
                    </div>

                    <!-- Meta -->
                    <div class="activity-item__meta">
                        <span class="activity-item__time">{{ formatTime(item.time) }}</span>
                        <span class="badge" :class="statusBadge(item.status)">
                            {{ item.status }}
                        </span>
                        <span v-if="item.sla_breached" class="badge badge-red">
                            SLA Breached
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Stats Grid ──────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: box-shadow 0.15s, transform 0.15s;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}

.stat-card__bar {
    height: 4px;
    width: 100%;
}

.stat-bar--indigo  { background: #6366f1; }
.stat-bar--green   { background: #22c55e; }
.stat-bar--blue    { background: #3b82f6; }
.stat-bar--orange  { background: #f97316; }
.stat-bar--amber   { background: #f59e0b; }
.stat-bar--red     { background: #ef4444; }
.stat-bar--pink    { background: #ec4899; }
.stat-bar--purple  { background: #a855f7; }

.stat-card__body {
    padding: 1rem 1.25rem;
}

.stat-card__value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-card__label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 0.375rem;
}

/* ── Quick Actions ───────────────────────────────────────── */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
    gap: 0.75rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-weight: 500;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.action-btn:hover {
    background: var(--surface);
    border-color: var(--accent);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.action-btn__icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.action-btn__label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Activity List ───────────────────────────────────────── */
.activity-list {
    display: flex;
    flex-direction: column;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--border);
    transition: background 0.12s;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item:hover {
    background: var(--bg);
}

.activity-item__icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    font-size: 1rem;
}

.activity-item__content {
    flex: 1;
    min-width: 0;
}

.activity-item__title {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.activity-item__detail {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.125rem;
}

.activity-item__meta {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.activity-item__time {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-family: monospace;
    white-space: nowrap;
}

@media (max-width: 640px) {
    .activity-item {
        flex-wrap: wrap;
    }
    .activity-item__meta {
        width: 100%;
        justify-content: flex-start;
        margin-top: 0.5rem;
        padding-left: 3.125rem;
    }
}
</style>
