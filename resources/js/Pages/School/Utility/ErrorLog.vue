<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import debounce from 'lodash/debounce';
import { useSchoolStore } from '@/stores/useSchoolStore';

const props = defineProps({
    logs:            { type: Object, default: () => ({}) },
    filters:         { type: Object, default: () => ({}) },
    availableLevels: { type: Array,  default: () => [] },
    logSize:         { type: String, default: '0 B' },
    totalEntries:    { type: Number, default: 0 },
});

// ── Filters ───────────────────────────────────────────────────────────────
const filterForm = ref({
    search: props.filters.search ?? '',
    level:  props.filters.level  ?? '',
    date:   props.filters.date   ?? '',
});

const applyFilters = debounce(() => {
    router.get('/school/utility/error-log', filterForm.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 400);

watch(filterForm, () => applyFilters(), { deep: true });

const resetFilters = () => {
    filterForm.value = { search: '', level: '', date: '' };
};

const hasFilters = () =>
    filterForm.value.search || filterForm.value.level || filterForm.value.date;

// ── Detail Panel ─────────────────────────────────────────────────────────
const selectedLog = ref(null);

// ── Helpers ───────────────────────────────────────────────────────────────
const school = useSchoolStore();

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    return school.fmtDateTime(dateStr);
};

const getLevelStyle = (level) => {
    const map = {
        EMERGENCY: 'text-rose-800 bg-rose-100',
        ALERT:     'text-rose-700 bg-rose-50',
        CRITICAL:  'text-rose-700 bg-rose-50',
        ERROR:     'text-red-700 bg-red-50',
        WARNING:   'text-amber-700 bg-amber-50',
        NOTICE:    'text-yellow-700 bg-yellow-50',
        INFO:      'text-blue-700 bg-blue-50',
        DEBUG:     'text-slate-500 bg-slate-100',
    };
    return map[level] || 'text-gray-600 bg-gray-50';
};

const getLevelDot = (level) => {
    const map = {
        EMERGENCY: '#9f1239',
        ALERT:     '#be123c',
        CRITICAL:  '#be123c',
        ERROR:     '#dc2626',
        WARNING:   '#d97706',
        NOTICE:    '#ca8a04',
        INFO:      '#2563eb',
        DEBUG:     '#94a3b8',
    };
    return map[level] || '#6b7280';
};

const isErrorLevel = (level) =>
    ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'].includes(level);
</script>

<template>
    <SchoolLayout title="Error Log">
        <div class="el-page">

            <!-- ── Page Header ──────────────────────────────────────── -->
            <PageHeader title="Error Log">
                <template #subtitle>
                    <p class="el-subtitle page-header-sub">
                        Laravel application log &mdash;
                        <span class="el-meta">{{ totalEntries.toLocaleString() }} entries</span>
                        <span class="el-meta-sep">·</span>
                        <span class="el-meta">{{ logSize }}</span>
                    </p>
                </template>
            </PageHeader>

            <!-- ── Filters ─────────────────────────────────────────── -->
            <FilterBar :active="hasFilters()" @clear="resetFilters">
                <select v-model="filterForm.level" style="width:150px;">
                    <option value="">All Levels</option>
                    <option v-for="lvl in availableLevels" :key="lvl" :value="lvl">{{ lvl }}</option>
                </select>
                <input v-model="filterForm.date" type="date" style="width:160px;">
                <div class="fb-search">
                    <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input v-model="filterForm.search" type="text" placeholder="Search messages…">
                </div>
            </FilterBar>

            <!-- ── Table ───────────────────────────────────────────── -->
            <div class="el-card">
                <Table class="el-table" size="sm" :empty="!logs.data || logs.data.length === 0">
                    <thead>
                        <tr>
                            <th style="width:110px">Level</th>
                            <th>Message</th>
                            <th style="width:80px">Env</th>
                            <th style="width:180px">Timestamp</th>
                            <th style="width:72px; text-align:center">Trace</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id"
                            class="el-tr" :class="{ 'el-tr-error': isErrorLevel(log.level) }">
                            <td>
                                <span class="el-badge" :class="getLevelStyle(log.level)">
                                    <span class="el-dot" :style="{ background: getLevelDot(log.level) }"></span>
                                    {{ log.level }}
                                </span>
                            </td>
                            <td>
                                <div class="el-message">{{ log.message }}</div>
                            </td>
                            <td>
                                <span class="el-env">{{ log.env }}</span>
                            </td>
                            <td class="el-time">{{ formatTime(log.datetime) }}</td>
                            <td class="text-center">
                                <button v-if="log.stack_trace"
                                    @click="selectedLog = log"
                                    class="el-trace-btn"
                                    title="View stack trace">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6h16M4 10h16M4 14h10" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <template #empty>
                        <EmptyState
                            tone="muted"
                            title="No Log Entries Found"
                            description="Try adjusting your filters or check back later."
                        />
                    </template>
                </Table>
            </div>

            <!-- ── Pagination ──────────────────────────────────────── -->
            <div v-if="logs.links && logs.links.length > 3" class="el-pg">
                <template v-for="(link, k) in logs.links" :key="k">
                    <div v-if="link.url === null" class="el-pg-item el-pg-disabled" v-html="link.label" />
                    <Link v-else :href="link.url" class="el-pg-item"
                        :class="{ 'el-pg-active': link.active }" v-html="link.label" />
                </template>
            </div>

        </div>

        <!-- ── Stack Trace Side Panel ─────────────────────────────────── -->
        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div v-if="selectedLog" class="el-panel">
                <div class="panel-head">
                    <div class="panel-head-left">
                        <span class="el-badge" :class="getLevelStyle(selectedLog.level)">
                            <span class="el-dot" :style="{ background: getLevelDot(selectedLog.level) }"></span>
                            {{ selectedLog.level }}
                        </span>
                        <h2 class="panel-title">Stack Trace</h2>
                    </div>
                    <button @click="selectedLog = null" class="panel-close">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="panel-body">
                    <div class="panel-section">
                        <label>Message</label>
                        <div class="panel-message">{{ selectedLog.message }}</div>
                    </div>

                    <div class="panel-row">
                        <div class="panel-section">
                            <label>Timestamp</label>
                            <div class="panel-val">{{ formatTime(selectedLog.datetime) }}</div>
                        </div>
                        <div class="panel-section">
                            <label>Environment</label>
                            <div class="panel-val">{{ selectedLog.env }}</div>
                        </div>
                    </div>

                    <div class="panel-section">
                        <label>Stack Trace</label>
                        <pre class="panel-trace">{{ selectedLog.stack_trace }}</pre>
                    </div>
                </div>
            </div>
        </Transition>

        <div v-if="selectedLog" class="el-overlay" @click="selectedLog = null" />

    </SchoolLayout>
</template>

<style scoped>
.el-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 4px 0;
}

/* ── Header ── */
.el-header {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.el-title    { font-size: 1.5rem; font-weight: 800; color: #1e293b; letter-spacing: -0.03em; margin: 0; }
.el-subtitle { font-size: 0.875rem; color: #64748b; margin: 4px 0 0; }
.el-meta     { font-weight: 600; color: #475569; }
.el-meta-sep { margin: 0 6px; color: #cbd5e1; }

/* ── Filters ── */
.el-filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.el-input {
    height: 40px;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #1e293b;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.el-input:focus { border-color: #1169cd; box-shadow: 0 0 0 3px rgba(17,105,205,0.1); }

.el-select { width: 150px; padding: 0 12px; }
.el-date   { width: 160px; padding: 0 12px; }

.el-search-wrap { position: relative; width: 260px; }
.el-search      { width: 100%; padding: 0 12px 0 36px; }
.el-search-icon {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    width: 16px; height: 16px; color: #94a3b8; pointer-events: none;
}

.el-btn-reset {
    height: 40px; padding: 0 16px;
    border: 1px solid #e2e8f0; background: #fff; border-radius: 10px;
    font-size: 0.8125rem; font-weight: 600; color: #64748b; cursor: pointer;
    transition: all 0.15s;
}
.el-btn-reset:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }

/* ── Card / Table ── */
.el-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }

.el-tr-error > td:first-child { border-left: 3px solid #fca5a5; }
.el-tr-error:hover > td { background: #fff9f9; }

/* ── Level Badge ── */
.el-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 7px;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    border: 1px solid rgba(0,0,0,0.05);
    white-space: nowrap;
}
.el-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Table cells ── */
.el-message {
    font-size: 0.875rem;
    color: #1e293b;
    font-weight: 500;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.el-env {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 5px;
}
.el-time {
    font-family: ui-monospace, monospace;
    font-size: 0.75rem !important;
    color: #94a3b8 !important;
}

.el-trace-btn {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: #f1f5f9;
    border: none;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.el-trace-btn svg { width: 16px; height: 16px; }
.el-trace-btn:hover { background: #e2e8f0; color: #1169cd; transform: translateY(-1px); }

.text-center { text-align: center; }

/* ── Pagination ── */
.el-pg { display: flex; justify-content: flex-end; gap: 6px; }
.el-pg-item {
    height: 36px; min-width: 36px; padding: 0 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.875rem; font-weight: 600; color: #475569;
    text-decoration: none;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.el-pg-item:hover     { border-color: #cbd5e1; background: #f8fafc; color: #1e293b; }
.el-pg-active         { background: #1169cd; color: #fff; border-color: #1169cd; }
.el-pg-disabled       { opacity: 0.45; cursor: not-allowed; }

/* ── Side Panel ── */
.el-panel {
    position: fixed;
    right: 0; top: 0; bottom: 0;
    width: 520px;
    background: #fff;
    z-index: 100;
    box-shadow: -12px 0 60px rgba(0,0,0,0.14);
    display: flex;
    flex-direction: column;
}

.panel-head {
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.panel-head-left { display: flex; align-items: center; gap: 12px; }
.panel-title     { font-size: 1.125rem; font-weight: 800; color: #1e293b; margin: 0; letter-spacing: -0.02em; }

.panel-close {
    width: 36px; height: 36px;
    background: #f1f5f9;
    border: none; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #64748b; cursor: pointer; flex-shrink: 0;
    transition: all 0.15s;
}
.panel-close svg { width: 20px; height: 20px; }
.panel-close:hover { background: #fee2e2; color: #ef4444; }

.panel-body {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.panel-section { display: flex; flex-direction: column; gap: 6px; }
.panel-section label {
    font-size: 0.68rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.panel-message { font-size: 0.9375rem; font-weight: 600; color: #1e293b; line-height: 1.6; }
.panel-val     { font-size: 0.875rem; font-weight: 600; color: #334155; }

.panel-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.panel-trace {
    background: #0f172a;
    color: #94a3b8;
    padding: 18px 20px;
    border-radius: 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.775rem;
    line-height: 1.7;
    overflow-x: auto;
    overflow-y: auto;
    max-height: 480px;
    white-space: pre;
    border: 3px solid #1e293b;
}

/* ── Overlay ── */
.el-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.4);
    backdrop-filter: blur(3px);
    z-index: 90;
}
</style>
