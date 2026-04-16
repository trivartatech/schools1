<script setup>
import Button from '@/Components/ui/Button.vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const props = defineProps({
    channels:             Object,   // { sms: bool, whatsapp: bool, voice: bool, email: bool }
    stats:                Object,   // { totalSent, delivered, failed, pending }
    channelBreakdown:     Object,   // keyed by type → { total, delivered, failed }
    recentLogs:           Array,    // [{ id, type, to, message, status, created_at, user: { name } }]
    pendingAnnouncements: Array,    // [{ id, title, delivery_method, audience_type, scheduled_at }]
    templateCount:        Number,
});

/* ── helpers ─────────────────────────────────────────────────── */
const school = useSchoolStore();

const deliveryRate = (ch) => {
    if (!ch || ch.total === 0) return 0;
    return Math.round((ch.delivered / ch.total) * 100);
};

const timeAgo = (dateStr) => {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)   return `${diff}s ago`;
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
};

const truncate = (str, len = 50) => {
    if (!str) return '';
    return str.length > len ? str.slice(0, len) + '...' : str;
};

const channelLabel = (type) => {
    const map = { sms: 'SMS', whatsapp: 'WhatsApp', voice: 'Voice', email: 'Email' };
    return map[type] || type;
};

const channelColor = (type) => {
    const map = { sms: '#1169cd', whatsapp: '#16a34a', voice: '#f59e0b', email: '#64748b' };
    return map[type] || '#64748b';
};

const statusColor = (status) => {
    const map = { delivered: '#16a34a', sent: '#1169cd', failed: '#ef4444', pending: '#f59e0b', queued: '#f59e0b' };
    return map[status] || '#64748b';
};

const methodBadgeColor = (method) => {
    const map = { sms: '#1169cd', whatsapp: '#16a34a', voice: '#f59e0b', email: '#64748b' };
    return map[method] || '#64748b';
};

const formatSchedule = (dateStr) => {
    if (!dateStr) return 'Not scheduled';
    return school.fmtDateTime(dateStr);
};

const channelList = computed(() => [
    { key: 'sms',      label: 'SMS',      configured: props.channels?.sms },
    { key: 'whatsapp',  label: 'WhatsApp', configured: props.channels?.whatsapp },
    { key: 'voice',     label: 'Voice',    configured: props.channels?.voice },
    { key: 'email',     label: 'Email',    configured: props.channels?.email },
]);

const breakdownEntries = computed(() => {
    if (!props.channelBreakdown) return [];
    return Object.entries(props.channelBreakdown).map(([type, data]) => ({
        type,
        ...data,
        rate: deliveryRate(data),
    }));
});
</script>

<template>
    <SchoolLayout title="Communication Dashboard">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Communication Dashboard</h2>
                <p class="page-header-sub">Overview of messaging channels, delivery stats, and recent activity</p>
            </div>
            <Button variant="secondary" as="link" :href="route('school.communication.logs')">
                View All Logs
            </Button>
        </div>

        <!-- ── 1. Stat Cards ───────────────────────────────────────── -->
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-value" style="color:#1169cd;">{{ stats?.totalSent ?? 0 }}</div>
                <div class="stat-label">Total Sent <span class="stat-sub">(last 30d)</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#16a34a;">{{ stats?.delivered ?? 0 }}</div>
                <div class="stat-label">Delivered</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#ef4444;">{{ stats?.failed ?? 0 }}</div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#f59e0b;">{{ stats?.pending ?? 0 }}</div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- ── 2. Channel Health ───────────────────────────────────── -->
        <div class="card section-card">
            <div class="card-header">
                <h3 class="card-title">Channel Health</h3>
            </div>
            <div class="card-body">
                <div class="channel-pills">
                    <div v-for="ch in channelList" :key="ch.key" class="channel-pill">
                        <span class="channel-dot" :style="{ background: ch.configured ? '#16a34a' : '#ef4444' }"></span>
                        <span class="channel-pill-label">{{ ch.label }}</span>
                        <span class="channel-pill-status" :style="{ color: ch.configured ? '#16a34a' : '#ef4444' }">
                            {{ ch.configured ? 'Active' : 'Not configured' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 3. Channel Breakdown ────────────────────────────────── -->
        <div v-if="breakdownEntries.length" class="breakdown-grid">
            <div v-for="ch in breakdownEntries" :key="ch.type" class="card section-card">
                <div class="card-header">
                    <h3 class="card-title" :style="{ color: channelColor(ch.type) }">{{ channelLabel(ch.type) }}</h3>
                    <span class="rate-badge" :style="{ background: channelColor(ch.type) + '14', color: channelColor(ch.type) }">
                        {{ ch.rate }}% delivered
                    </span>
                </div>
                <div class="card-body">
                    <div class="breakdown-stats">
                        <div class="breakdown-item">
                            <span class="breakdown-num">{{ ch.total }}</span>
                            <span class="breakdown-lbl">Total</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-num" style="color:#16a34a;">{{ ch.delivered }}</span>
                            <span class="breakdown-lbl">Delivered</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-num" style="color:#ef4444;">{{ ch.failed }}</span>
                            <span class="breakdown-lbl">Failed</span>
                        </div>
                    </div>
                    <div class="rate-bar-track">
                        <div class="rate-bar-fill" :style="{ width: ch.rate + '%', background: channelColor(ch.type) }"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="two-col">
            <!-- ── 4. Recent Activity ──────────────────────────────── -->
            <div class="card section-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Activity</h3>
                    <Link :href="route('school.communication.logs')" class="header-link">All Logs &rarr;</Link>
                </div>
                <Table v-if="recentLogs && recentLogs.length" class="mini-table" size="sm">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in recentLogs.slice(0, 10)" :key="log.id">
                            <td>
                                <span class="type-badge" :style="{ background: channelColor(log.type) + '14', color: channelColor(log.type) }">
                                    {{ channelLabel(log.type) }}
                                </span>
                            </td>
                            <td class="cell-recipient">{{ log.to }}</td>
                            <td class="cell-message">{{ truncate(log.message) }}</td>
                            <td>
                                <span class="status-badge" :style="{ background: statusColor(log.status) + '14', color: statusColor(log.status) }">
                                    {{ log.status }}
                                </span>
                            </td>
                            <td class="cell-time">{{ timeAgo(log.created_at) }}</td>
                        </tr>
                    </tbody>
                </Table>
                <div v-else class="empty-state">No recent communication logs.</div>
            </div>

            <!-- ── 5. Pending Announcements ────────────────────────── -->
            <div class="card section-card">
                <div class="card-header">
                    <h3 class="card-title">Pending Announcements</h3>
                    <Link :href="route('school.communication.announcements.index')" class="header-link">All Announcements &rarr;</Link>
                </div>
                <div v-if="pendingAnnouncements && pendingAnnouncements.length" class="announcement-list">
                    <div v-for="ann in pendingAnnouncements" :key="ann.id" class="announcement-item">
                        <div class="announcement-info">
                            <div class="announcement-title">{{ ann.title }}</div>
                            <div class="announcement-meta">
                                <span class="type-badge" :style="{ background: methodBadgeColor(ann.delivery_method) + '14', color: methodBadgeColor(ann.delivery_method) }">
                                    {{ channelLabel(ann.delivery_method) }}
                                </span>
                                <span class="meta-sep">&middot;</span>
                                <span class="meta-text">{{ ann.audience_type }}</span>
                                <span class="meta-sep">&middot;</span>
                                <span class="meta-text">{{ formatSchedule(ann.scheduled_at) }}</span>
                            </div>
                        </div>
                        <div class="announcement-actions">
                            <Link :href="route('school.communication.announcements.index')" class="action-link">View</Link>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-state">No pending announcements.</div>
            </div>
        </div>

        <!-- ── 6. Quick Actions ────────────────────────────────────── -->
        <div class="card section-card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <Link :href="route('school.communication.logs')" class="quick-action-btn">
                        <span class="qa-icon">&#128196;</span>
                        <span class="qa-label">Logs</span>
                    </Link>
                    <Link :href="route('school.communication.analytics')" class="quick-action-btn">
                        <span class="qa-icon">&#128202;</span>
                        <span class="qa-label">Analytics</span>
                    </Link>
                    <Link :href="route('school.communication.emergency')" class="quick-action-btn qa-emergency">
                        <span class="qa-icon">&#9888;</span>
                        <span class="qa-label">Emergency</span>
                    </Link>
                    <Link :href="route('school.communication.email-templates')" class="quick-action-btn">
                        <span class="qa-icon">&#9993;</span>
                        <span class="qa-label">Email Templates</span>
                    </Link>
                    <Link :href="route('school.communication.scheduled')" class="quick-action-btn">
                        <span class="qa-icon">&#128339;</span>
                        <span class="qa-label">Scheduled Queue</span>
                    </Link>
                    <Link :href="route('school.communication.announcements.index')" class="quick-action-btn">
                        <span class="qa-icon">&#128227;</span>
                        <span class="qa-label">Announcements</span>
                    </Link>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Stat Row ────────────────────────────────────────────────── */
.stat-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 6px;
}
.stat-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #64748b;
}
.stat-sub {
    font-weight: 400;
    font-size: 0.6875rem;
    color: #94a3b8;
}

/* ── Section Card ────────────────────────────────────────────── */
.section-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 20px;
}
.section-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid #e2e8f0;
}
.section-card .card-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}
.section-card .card-body {
    padding: 16px 18px;
}

/* ── Channel Health Pills ────────────────────────────────────── */
.channel-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.channel-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
}
.channel-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.channel-pill-label {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #1e293b;
}
.channel-pill-status {
    font-size: 0.6875rem;
    font-weight: 600;
}

/* ── Channel Breakdown ───────────────────────────────────────── */
.breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.breakdown-stats {
    display: flex;
    gap: 24px;
    margin-bottom: 14px;
}
.breakdown-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.breakdown-num {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}
.breakdown-lbl {
    font-size: 0.6875rem;
    color: #94a3b8;
    margin-top: 2px;
}
.rate-badge {
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
}
.rate-bar-track {
    width: 100%;
    height: 6px;
    background: #f1f5f9;
    border-radius: 3px;
    overflow: hidden;
}
.rate-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.4s ease;
}

/* ── Two Column Layout ───────────────────────────────────────── */
.two-col {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.cell-recipient {
    font-weight: 600;
    white-space: nowrap;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cell-message {
    color: #64748b;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cell-time {
    white-space: nowrap;
    color: #94a3b8;
    font-size: 0.75rem;
}

/* ── Badges ──────────────────────────────────────────────────── */
.type-badge,
.status-badge {
    display: inline-block;
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    text-transform: capitalize;
    white-space: nowrap;
}

/* ── Announcements ───────────────────────────────────────────── */
.announcement-list {
    padding: 0;
}
.announcement-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-bottom: 1px solid #f1f5f9;
}
.announcement-item:last-child {
    border-bottom: none;
}
.announcement-info {
    min-width: 0;
}
.announcement-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 280px;
}
.announcement-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.meta-sep {
    color: #cbd5e1;
    font-size: 0.75rem;
}
.meta-text {
    font-size: 0.6875rem;
    color: #94a3b8;
    text-transform: capitalize;
}
.announcement-actions {
    flex-shrink: 0;
    margin-left: 12px;
}
.action-link {
    font-size: 0.75rem;
    font-weight: 600;
    color: #1169cd;
    text-decoration: none;
}
.action-link:hover {
    text-decoration: underline;
}

/* ── Header Link ─────────────────────────────────────────────── */
.header-link {
    font-size: 0.75rem;
    font-weight: 600;
    color: #1169cd;
    text-decoration: none;
}
.header-link:hover {
    text-decoration: underline;
}

/* ── Quick Actions ───────────────────────────────────────────── */
.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    text-decoration: none;
    transition: all 0.15s ease;
}
.quick-action-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #1169cd;
}
.quick-action-btn.qa-emergency {
    border-color: #fecaca;
    background: #fef2f2;
    color: #ef4444;
}
.quick-action-btn.qa-emergency:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}
.qa-icon {
    font-size: 1rem;
    line-height: 1;
}
.qa-label {
    line-height: 1;
}

/* ── Empty State ─────────────────────────────────────────────── */
.empty-state {
    padding: 32px 18px;
    text-align: center;
    font-size: 0.8125rem;
    color: #94a3b8;
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .two-col {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .stat-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .breakdown-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 480px) {
    .stat-row {
        grid-template-columns: 1fr;
    }
    .channel-pills {
        flex-direction: column;
    }
    .quick-actions {
        flex-direction: column;
    }
}
</style>
