<script setup>
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

defineProps({
    logs: Object,
});

const getTypeIcon = (type) => {
    switch (type?.toLowerCase()) {
        case 'sms': return 'chat';
        case 'whatsapp': return 'phone';
        case 'voice': return 'phone-call';
        case 'email': return 'mail';
        default: return 'chat';
    }
};

const getTypeColor = (type) => {
    switch (type?.toLowerCase()) {
        case 'sms': return '#1169cd';
        case 'whatsapp': return '#16a34a';
        case 'voice': return '#f59e0b';
        case 'email': return '#7c3aed';
        default: return '#64748b';
    }
};

const getStatusBadge = (status) => {
    switch (status?.toLowerCase()) {
        case 'delivered': return { label: 'Delivered', class: 'badge-green' };
        case 'failed': return { label: 'Failed', class: 'badge-red' };
        case 'pending': return { label: 'Pending', class: 'badge-amber' };
        case 'sent': return { label: 'Sent', class: 'badge-green' };
        default: return { label: status || 'Unknown', class: 'badge-gray' };
    }
};

const relativeTime = (dateStr) => {
    if (!dateStr) return '';
    const now = new Date();
    const date = new Date(dateStr);
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return school.fmtDate(dateStr);
};
</script>

<template>
    <SchoolLayout title="My Notifications">
        <PageHeader title="My Notifications" subtitle="All communications sent to you" />

        <!-- Timeline -->
        <div v-if="logs?.data?.length > 0" class="timeline">
            <div v-for="log in logs.data" :key="log.id" class="timeline-item">
                <div class="timeline-icon" :style="{ background: getTypeColor(log.type) + '18', color: getTypeColor(log.type) }">
                    <!-- SMS / Chat Bubble -->
                    <svg v-if="getTypeIcon(log.type) === 'chat'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                    <!-- Phone -->
                    <svg v-if="getTypeIcon(log.type) === 'phone'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                    <!-- Phone Call / Voice -->
                    <svg v-if="getTypeIcon(log.type) === 'phone-call'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                    </svg>
                    <!-- Mail -->
                    <svg v-if="getTypeIcon(log.type) === 'mail'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                </div>
                <div class="timeline-card">
                    <div class="timeline-card-header">
                        <div class="timeline-meta">
                            <span class="type-label" :style="{ color: getTypeColor(log.type) }">{{ log.type }}</span>
                            <span class="to-label" v-if="log.to">to {{ log.to }}</span>
                        </div>
                        <div class="timeline-right">
                            <span class="badge" :class="getStatusBadge(log.status).class">
                                {{ getStatusBadge(log.status).label }}
                            </span>
                            <span class="timeline-time">{{ relativeTime(log.created_at) }}</span>
                        </div>
                    </div>
                    <p class="timeline-message">{{ log.message }}</p>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state-card">
            <div class="empty-icon">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
            </div>
            <h3 class="empty-title">No notifications yet</h3>
            <p class="empty-text">Communications sent to you will appear here.</p>
        </div>

        <!-- Pagination -->
        <div v-if="logs?.links?.length > 3" class="pagination">
            <template v-for="link in logs.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="page-link"
                    :class="{ active: link.active }"
                    v-html="link.label"
                    :preserve-scroll="true"
                />
                <span
                    v-else
                    class="page-link disabled"
                    v-html="link.label"
                />
            </template>
        </div>
    </SchoolLayout>
</template>

<style scoped>
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

.timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.timeline-item {
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.timeline-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 4px;
}
.timeline-card {
    flex: 1;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 18px;
}
.timeline-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.timeline-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.type-label {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.to-label {
    font-size: .78rem;
    color: #94a3b8;
}
.timeline-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.timeline-time {
    font-size: .72rem;
    color: #94a3b8;
    white-space: nowrap;
}
.timeline-message {
    font-size: .84rem;
    color: #475569;
    line-height: 1.55;
    margin: 0;
}

.badge {
    display: inline-flex;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    white-space: nowrap;
}
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-red { background: #fee2e2; color: #ef4444; }
.badge-amber { background: #fef3c7; color: #f59e0b; }
.badge-gray { background: #f1f5f9; color: #94a3b8; }

.empty-state-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 48px 24px;
    text-align: center;
}
.empty-icon { margin-bottom: 16px; }
.empty-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 6px;
}
.empty-text {
    font-size: .85rem;
    color: #94a3b8;
    margin: 0;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.page-link {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: .82rem;
    color: #475569;
    text-decoration: none;
    border: 1px solid #e2e8f0;
    background: #fff;
    transition: all .15s;
}
.page-link:hover:not(.disabled):not(.active) { border-color: #1169cd; color: #1169cd; }
.page-link.active { background: #1169cd; color: #fff; border-color: #1169cd; }
.page-link.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }
</style>
