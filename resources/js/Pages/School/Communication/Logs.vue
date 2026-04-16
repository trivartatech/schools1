<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const props = defineProps({
    logs: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    filters: { type: Object, default: () => ({}) },
});

// ── Filter Form ────────────────────────────────────────────────────────
const filterForm = ref({
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
    from: props.filters.from ?? '',
    to_date: props.filters.to_date ?? '',
});

const applyFilters = () => {
    router.get(window.location.pathname, filterForm.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.value = { type: '', status: '', search: '', from: '', to_date: '' };
    applyFilters();
};

const hasActive = () => !!(filterForm.value.type || filterForm.value.status || filterForm.value.search || filterForm.value.from || filterForm.value.to_date);

// ── Helpers ─────────────────────────────────────────────────────────────
const school = useSchoolStore();

const truncate = (str, len = 60) => {
    if (!str) return '';
    return str.length > len ? str.substring(0, len) + '...' : str;
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '';
    return school.fmtDateTime(dateStr);
};

const typeBadgeClass = (type) => {
    const map = {
        sms: 'cl-badge--blue',
        whatsapp: 'cl-badge--green',
        voice: 'cl-badge--purple',
        email: 'cl-badge--amber',
    };
    return map[(type || '').toLowerCase()] || 'cl-badge--gray';
};

const statusBadgeClass = (status) => {
    const map = {
        sent: 'cl-badge--green',
        failed: 'cl-badge--red',
        pending: 'cl-badge--amber',
    };
    return map[(status || '').toLowerCase()] || 'cl-badge--gray';
};
</script>

<template>
    <SchoolLayout title="Communication Logs">
        <div class="cl-page">

            <!-- ── Page Header ─────────────────────────────────────────── -->
            <div class="cl-header">
                <div>
                    <h1 class="cl-title">Communication Logs</h1>
                    <p class="cl-subtitle">View all sent messages across all channels</p>
                </div>
            </div>

            <!-- ── Filter Bar ──────────────────────────────────────────── -->
            <FilterBar :active="hasActive()" @clear="resetFilters">
                <select v-model="filterForm.type" style="width:130px;">
                    <option value="">All Types</option>
                    <option value="sms">SMS</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="voice">Voice</option>
                    <option value="email">Email</option>
                </select>
                <select v-model="filterForm.status" style="width:120px;">
                    <option value="">All Status</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
                <div class="fb-search">
                    <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input v-model="filterForm.search" type="text" placeholder="Recipient or message...">
                </div>
                <input v-model="filterForm.from" type="date" style="width:150px;">
                <input v-model="filterForm.to_date" type="date" style="width:150px;">
                <button class="cl-btn-apply" @click="applyFilters">
                    <svg class="cl-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Apply
                </button>
            </FilterBar>

            <!-- ── Results Table ────────────────────────────────────────── -->
            <div class="cl-card">
                <Table class="cl-table" striped :empty="!logs.data || logs.data.length === 0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Sent By</th>
                            <th>Status</th>
                            <th>Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="cl-row">
                            <td>
                                <span class="cl-badge" :class="typeBadgeClass(log.type)">
                                    {{ log.type }}
                                </span>
                            </td>
                            <td>
                                <span class="cl-recipient">{{ log.to }}</span>
                            </td>
                            <td>
                                <span class="cl-message" :title="log.message">{{ truncate(log.message, 60) }}</span>
                            </td>
                            <td>
                                <span class="cl-sender">{{ log.user?.name || '-' }}</span>
                            </td>
                            <td>
                                <span class="cl-badge" :class="statusBadgeClass(log.status)">
                                    {{ log.status }}
                                </span>
                            </td>
                            <td class="cl-datetime">
                                {{ formatDateTime(log.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                    <template #empty>
                        <div class="cl-empty-state">
                            <div class="cl-empty-icon-box">
                                <svg class="cl-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                            </div>
                            <h3 class="cl-empty-title">No Communication Logs</h3>
                            <p class="cl-empty-text">There are no messages matching your current filters. Try adjusting the criteria above.</p>
                        </div>
                    </template>
                </Table>
            </div>

            <!-- ── Pagination ───────────────────────────────────────────── -->
            <div v-if="logs.links && logs.links.length > 3" class="cl-pagination">
                <template v-for="(link, k) in logs.links" :key="k">
                    <span
                        v-if="!link.url"
                        class="cl-page-link cl-page-disabled"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        class="cl-page-link"
                        :class="{ 'cl-page-active': link.active }"
                        v-html="link.label"
                        :preserve-scroll="true"
                    />
                </template>
            </div>

        </div>
    </SchoolLayout>
</template>

<style scoped>
/* ── Page Shell ────────────────────────────────────────────────────────── */
.cl-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 4px 0;
}

/* ── Header ────────────────────────────────────────────────────────────── */
.cl-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.cl-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.02em;
    margin: 0;
}

.cl-subtitle {
    font-size: 0.8125rem;
    color: #64748b;
    margin: 2px 0 0;
}

/* ── Card ──────────────────────────────────────────────────────────────── */
.cl-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

/* ── Filter Bar ────────────────────────────────────────────────────────── */
.cl-filter-card {
    padding: 16px 20px;
}

.cl-filter-bar {
    display: flex;
    align-items: flex-end;
    gap: 14px;
    flex-wrap: wrap;
}

.cl-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cl-filter-group--search {
    flex: 1;
    min-width: 180px;
}

.cl-filter-group--btn {
    justify-content: flex-end;
}

.cl-filter-label {
    font-size: 0.6875rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.cl-input {
    height: 36px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-size: 0.8125rem;
    color: #1e293b;
    background: #fff;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.cl-input:focus {
    border-color: #1169cd;
    box-shadow: 0 0 0 3px rgba(17, 105, 205, 0.1);
}

.cl-select {
    width: 140px;
    cursor: pointer;
}

.cl-date {
    width: 150px;
}

.cl-search-wrapper {
    position: relative;
    width: 100%;
}

.cl-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 14px;
    height: 14px;
    color: #94a3b8;
    pointer-events: none;
}

.cl-search-input {
    width: 100%;
    padding-left: 32px !important;
}

.cl-btn-apply {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 18px;
    background: #1169cd;
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

.cl-btn-apply:hover {
    background: #0e56a8;
}

.cl-btn-icon {
    width: 14px;
    height: 14px;
}

/* ── Badges ─────────────────────────────────────────────────────────────── */
.cl-badge {
    display: inline-flex;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.cl-badge--blue {
    color: #1d4ed8;
    background: #eff6ff;
}

.cl-badge--green {
    color: #15803d;
    background: #f0fdf4;
}

.cl-badge--purple {
    color: #7e22ce;
    background: #faf5ff;
}

.cl-badge--amber {
    color: #b45309;
    background: #fffbeb;
}

.cl-badge--red {
    color: #dc2626;
    background: #fef2f2;
}

.cl-badge--gray {
    color: #64748b;
    background: #f1f5f9;
}

/* ── Cell Styles ────────────────────────────────────────────────────────── */
.cl-recipient {
    font-weight: 600;
    color: #1e293b;
}

.cl-message {
    color: #475569;
    line-height: 1.4;
}

.cl-sender {
    font-weight: 500;
    color: #64748b;
}

.cl-datetime {
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* ── Empty State ────────────────────────────────────────────────────────── */
.cl-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.cl-empty-icon-box {
    width: 56px;
    height: 56px;
    background: #f1f5f9;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}

.cl-empty-icon {
    width: 28px;
    height: 28px;
    color: #94a3b8;
}

.cl-empty-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px;
}

.cl-empty-text {
    font-size: 0.8125rem;
    color: #64748b;
    margin: 0;
    max-width: 320px;
}

/* ── Pagination ─────────────────────────────────────────────────────────── */
.cl-pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 4px;
}

.cl-page-link {
    padding: 6px 12px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #e2e8f0;
    font-size: 0.8125rem;
    color: #64748b;
    text-decoration: none;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cl-page-link:hover {
    border-color: #cbd5e1;
    color: #1e293b;
}

.cl-page-active {
    background: #1169cd;
    color: #fff;
    border-color: #1169cd;
}

.cl-page-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
