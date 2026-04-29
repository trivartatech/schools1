<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import debounce from 'lodash/debounce';
import { useSchoolStore } from '@/stores/useSchoolStore';
import FilterBar from '@/Components/ui/FilterBar.vue';

const props = defineProps({
    logs: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    availableLogNames: { type: Array, default: () => [] },
});

const page = usePage();

// ── Settings sidebar nav items ────────────────────────────────────────────
const settingsNav = [
    { id: 'general-config',     label: 'General Config',              route: '/school/settings/general-config',    icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'asset-config',       label: 'Asset Config',                route: '/school/settings/asset-config',      icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'system-config',      label: 'System Config',               route: '/school/settings/system-config',     icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18' },
    { id: 'activity-log',       label: 'Activity Log',                route: '/school/settings/activity-log',      icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'receipt-print',      label: 'Receipt Print',               route: '/school/settings/receipt-print',     icon: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

// ── Filters ─────────────────────────────────────────────────────────────
const filterForm = ref({
    search: props.filters.search ?? '',
    log_name: props.filters.log_name ?? '',
    date: props.filters.date ?? '',
});

const applyFilters = debounce(() => {
    router.get('/school/settings/activity-log', filterForm.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 400);

watch(filterForm, () => applyFilters(), { deep: true });

const resetFilters = () => {
    filterForm.value = { search: '', log_name: '', date: '' };
};

// ── Helpers ──────────────────────────────────────────────────────────────
const school = useSchoolStore();

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    return school.fmtDateTime(dateStr);
};

const getEventColor = (logName) => {
    const colors = {
        staff: 'text-purple-600 bg-purple-50',
        student: 'text-blue-600 bg-blue-50',
        finance: 'text-emerald-600 bg-emerald-50',
        security: 'text-rose-600 bg-rose-50',
        system: 'text-slate-600 bg-slate-50',
        academic: 'text-amber-600 bg-amber-50',
    };
    return colors[logName] || 'text-gray-600 bg-gray-50';
};

const selectedLog = ref(null);
const viewDetails = (log) => {
    selectedLog.value = log;
};
</script>

<template>
    <SchoolLayout title="Activity Log">
        <div class="gc-shell">

            <!-- ── Settings Sidebar ─────────────────────────────────── -->
            <aside class="gc-sidebar">
                <nav class="gc-sidebar-nav">
                    <Link
                        v-for="item in settingsNav"
                        :key="item.id"
                        :href="item.route"
                        class="gc-nav-item"
                        :class="{ 'gc-nav-item--active': isActive(item.route) }"
                    >
                        <svg class="gc-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>
            </aside>

            <!-- ── Main Content ─────────────────────────────────────── -->
            <section class="gc-content">
                
                <!-- ── Header ─────────────────────────────── -->
                <div class="al-header">
                    <div>
                        <h1 class="al-title">Activity Log</h1>
                        <p class="al-subtitle">Audit trail of system events and user actions.</p>
                    </div>
                </div>

                <!-- ── Filters ─────────────────────────────── -->
                <FilterBar :active="!!(filterForm.search || filterForm.log_name || filterForm.date)" @clear="resetFilters">
                    <div class="fb-search">
                        <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input v-model="filterForm.search" type="search" placeholder="Search logs..." />
                    </div>
                    <select v-model="filterForm.log_name" style="width:160px;">
                        <option value="">All Categories</option>
                        <option v-for="name in availableLogNames" :key="name" :value="name">
                            {{ name.charAt(0).toUpperCase() + name.slice(1) }}
                        </option>
                    </select>
                    <input v-model="filterForm.date" type="date" style="width:160px;" />
                </FilterBar>

                <!-- ── Logs Table ───────────────────────────────────── -->
                <div class="gc-card">
                    <Table class="al-table" striped size="sm" :empty="logs.data.length === 0">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Action</th>
                                <th>User</th>
                                <th>Subject</th>
                                <th>Time</th>
                                <th class="text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id">
                                <td>
                                    <span class="al-badge" :class="getEventColor(log.log_name)">
                                        {{ log.log_name }}
                                    </span>
                                </td>
                                <td>
                                    <div class="al-desc" :title="log.description">{{ log.description }}</div>
                                </td>
                                <td>
                                    <div class="al-user" v-if="log.causer">
                                        <div class="al-user-name">{{ log.causer.name }}</div>
                                        <div class="al-user-type">{{ log.causer.user_type }}</div>
                                    </div>
                                    <span v-else class="text-gray-400 italic text-xs">System</span>
                                </td>
                                <td>
                                    <div class="al-subject" v-if="log.subject">
                                        {{ log.subject_type.split('\\').pop() }}
                                        <span class="text-gray-400 ml-1">#{{ log.subject_id }}</span>
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="al-time">
                                    {{ formatTime(log.created_at) }}
                                </td>
                                <td class="text-right">
                                    <button @click="viewDetails(log)" class="al-btn-view" v-if="log.properties && Object.keys(log.properties).length">
                                        View Data
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <template #empty>
                            <div class="al-empty-state">
                                <svg class="al-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p>No activity logs found matching your filters.</p>
                            </div>
                        </template>
                    </Table>
                </div>

                <!-- ── Pagination ───────────────────────────────────── -->
                <div v-if="logs.links && logs.links.length > 3" class="al-pagination">
                    <template v-for="(link, k) in logs.links" :key="k">
                        <div v-if="link.url === null" class="al-page-link al-page-disabled" v-html="link.label" />
                        <Link v-else :href="link.url" class="al-page-link" :class="{ 'al-page-active': link.active }" v-html="link.label" />
                    </template>
                </div>

            </section>
        </div>

        <!-- ── Details Modal ────────────────────────────────────────── -->
        <div v-if="selectedLog" class="al-modal-overlay" @click.self="selectedLog = null">
            <div class="al-modal">
                <div class="al-modal-header">
                    <h3 class="al-modal-title">Activity Details</h3>
                    <button @click="selectedLog = null" class="al-modal-close">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="al-modal-body">
                    <div class="al-detail-row">
                        <span class="al-detail-label">Description:</span>
                        <span class="al-detail-val">{{ selectedLog.description }}</span>
                    </div>
                    <div class="al-detail-row">
                        <span class="al-detail-label">Performed By:</span>
                        <span class="al-detail-val">{{ selectedLog.causer?.name || 'System' }}</span>
                    </div>
                    <div class="al-detail-row">
                        <span class="al-detail-label">Subject:</span>
                        <span class="al-detail-val">{{ selectedLog.subject_type }} (#{{ selectedLog.subject_id }})</span>
                    </div>
                    
                    <div class="al-json-label">Extended Properties:</div>
                    <pre class="al-json-box">{{ JSON.stringify(selectedLog.properties, null, 2) }}</pre>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* Reuse Styles from GeneralConfig where applicable */
.gc-shell {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 56px);
    margin: -24px -28px;
    background: #f8fafc;
}

.gc-sidebar {
    width: 220px;
    min-width: 220px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    padding: 16px 0;
    flex-shrink: 0;
}

.gc-sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 0 8px;
}

.gc-nav-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 10px;
    border-radius: 7px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    transition: all 0.13s;
}

.gc-nav-item:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.gc-nav-item--active {
    background: #eff6ff !important;
    color: #1169cd !important;
    font-weight: 600;
}

.gc-nav-icon {
    width: 15px;
    height: 15px;
}

.gc-content {
    flex: 1;
    padding: 28px 32px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.gc-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.gc-input {
    height: 36px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-size: 0.8125rem;
    outline: none;
}

.gc-input:focus {
    border-color: #1169cd;
    box-shadow: 0 0 0 3px rgba(17, 105, 205, 0.1);
}

/* ── Activity Log Specific ── */
.al-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 4px;
}

.al-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.02em;
    margin: 0;
}

.al-subtitle {
    font-size: 0.8125rem;
    color: #64748b;
    margin: 2px 0 0;
}

.al-badge {
    display: inline-flex;
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.al-desc {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 500;
}

.al-user-name {
    font-weight: 600;
    color: #1e293b;
}

.al-user-type {
    font-size: 0.675rem;
    color: #94a3b8;
    text-transform: capitalize;
}

.al-subject {
    color: #64748b;
}

.al-time {
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
}

.al-btn-view {
    font-size: 0.75rem;
    font-weight: 600;
    color: #1169cd;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
}
.al-btn-view:hover {
    background: #eff6ff;
}

.al-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #94a3b8;
}

.al-empty-icon {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
}

.al-pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 10px;
}

.al-page-link {
    padding: 6px 12px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #e2e8f0;
    font-size: 0.8125rem;
    color: #64748b;
    text-decoration: none;
    transition: all 0.15s;
}

.al-page-link:hover {
    border-color: #cbd5e1;
    color: #1e293b;
}

.al-page-active {
    background: #1169cd;
    color: #fff;
    border-color: #1169cd;
}

.al-page-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Modal ── */
.al-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 20px;
}

.al-modal {
    background: #fff;
    width: 100%;
    max-width: 600px;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

.al-modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.al-modal-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

.al-modal-close {
    color: #94a3b8;
    padding: 4px;
    border-radius: 6px;
}
.al-modal-close:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.al-modal-body {
    padding: 20px;
}

.al-detail-row {
    display: flex;
    margin-bottom: 12px;
}

.al-detail-label {
    width: 120px;
    font-weight: 600;
    font-size: 0.8125rem;
    color: #64748b;
}

.al-detail-val {
    flex: 1;
    font-size: 0.875rem;
    color: #1e293b;
}

.al-json-label {
    margin-top: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}

.al-json-box {
    background: #1e293b;
    color: #cbd5e1;
    padding: 16px;
    border-radius: 12px;
    font-family: ui-monospace, monospace;
    font-size: 0.8125rem;
    line-height: 1.5;
    max-height: 300px;
    overflow-y: auto;
}

.text-right { text-align: right; }
.ml-1 { margin-left: 0.25rem; }
</style>
