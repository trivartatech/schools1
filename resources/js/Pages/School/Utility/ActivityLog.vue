<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import debounce from 'lodash/debounce';
import { useSchoolStore } from '@/stores/useSchoolStore';

const props = defineProps({
    logs: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    availableLogNames: { type: Array, default: () => [] },
});

// ── Filters ─────────────────────────────────────────────────────────────
const filterForm = ref({
    search: props.filters.search ?? '',
    log_name: props.filters.log_name ?? '',
    date: props.filters.date ?? '',
});

const applyFilters = debounce(() => {
    router.get('/school/utility/activity-log', filterForm.value, {
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
    <SchoolLayout title="Activity Log Utility">
        <div class="utl-page">
            
            <!-- ── Page Header ──────────────────────────────────────── -->
            <PageHeader
                title="Activity Log Utility"
                subtitle="Advanced audit trail for all system and user operations."
            />

            <!-- ── Filters ─────────────────────────────────────────── -->
            <FilterBar :active="!!(filterForm.search || filterForm.date || filterForm.log_name)" @clear="resetFilters">
                <select v-model="filterForm.log_name" style="width:160px;">
                    <option value="">All Categories</option>
                    <option v-for="name in availableLogNames" :key="name" :value="name">
                        {{ name.charAt(0).toUpperCase() + name.slice(1) }}
                    </option>
                </select>
                <input v-model="filterForm.date" type="date" style="width:160px;">
                <div class="fb-search">
                    <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input v-model="filterForm.search" type="text" placeholder="Search operations...">
                </div>
            </FilterBar>

            <!-- ── Activity Table ───────────────────────────────────── -->
            <div class="al-card">
                <Table class="al-table" striped size="sm" :empty="logs.data.length === 0">
                    <thead>
                        <tr>
                            <th class="w-24">Type</th>
                            <th class="w-fit">Description</th>
                            <th class="w-48">Performed By</th>
                            <th class="w-40">Subject / Target</th>
                            <th class="w-44">Timestamp</th>
                            <th class="w-20 text-center">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="al-tr">
                            <td>
                                <span class="al-badge" :class="getEventColor(log.log_name)">
                                    {{ log.log_name }}
                                </span>
                            </td>
                            <td>
                                <div class="al-desc">{{ log.description }}</div>
                            </td>
                            <td>
                                <div class="al-user" v-if="log.causer">
                                    <span class="al-user-name">{{ log.causer.name }}</span>
                                    <span class="al-user-type">{{ log.causer.user_type }}</span>
                                </div>
                                <span v-else class="al-system-tag">System Event</span>
                            </td>
                            <td>
                                <div class="al-subject" v-if="log.subject">
                                    {{ log.subject_type.split('\\').pop() }}
                                    <span class="subject-id">#{{ log.subject_id }}</span>
                                </div>
                                <span v-else class="text-gray-400 italic">No Target</span>
                            </td>
                            <td class="al-time">
                                {{ formatTime(log.created_at) }}
                            </td>
                            <td class="text-center">
                                <button
                                    @click="viewDetails(log)"
                                    class="al-icon-btn"
                                    v-if="log.properties && Object.keys(log.properties).length"
                                    title="View Properties"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m1 5l4-4m0 0l4 4m-4-4v12" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <template #empty>
                        <EmptyState
                            tone="muted"
                            title="No Activity Records"
                            description="Adjust your filters or check back later for new events."
                        />
                    </template>
                </Table>
            </div>

            <!-- ── Pagination ───────────────────────────────────── -->
            <div v-if="logs.links.length > 3" class="al-pg-container">
                <template v-for="(link, k) in logs.links" :key="k">
                    <div v-if="link.url === null" class="al-pg-link al-pg-disabled" v-html="link.label" />
                    <Link v-else :href="link.url" class="al-pg-link" :class="{ 'al-pg-active': link.active }" v-html="link.label" />
                </template>
            </div>

        </div>

        <!-- ── Details Side Modal ─────────────────────────────────────── -->
        <Transition 
            enter-active-class="transition duration-300 ease-out" 
            enter-from-class="translate-x-full" 
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div v-if="selectedLog" class="al-side-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Activity Snapshot</h2>
                    <button @click="selectedLog = null" class="panel-close">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="panel-content">
                    <div class="panel-section">
                        <label>Operation Context</label>
                        <div class="panel-val high">{{ selectedLog.description }}</div>
                    </div>
                    
                    <div class="panel-grid">
                        <div class="panel-section">
                            <label>Model Class</label>
                            <div class="panel-val">{{ selectedLog.subject_type }}</div>
                        </div>
                        <div class="panel-section">
                            <label>Record ID</label>
                            <div class="panel-val">#{{ selectedLog.subject_id }}</div>
                        </div>
                    </div>

                    <div class="panel-section">
                        <label>Extended Properties (JSON)</label>
                        <pre class="panel-json">{{ JSON.stringify(selectedLog.properties, null, 2) }}</pre>
                    </div>

                    <div class="panel-section">
                        <label>Timeline Metadata</label>
                        <div class="panel-timeline">
                            <div class="timeline-item">
                                <span class="dot"></span>
                                <div class="tl-content">
                                    <span class="tl-label">Logged At</span>
                                    <span class="tl-val">{{ formatTime(selectedLog.created_at) }}</span>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <span class="dot"></span>
                                <div class="tl-content">
                                    <span class="tl-label">Causer UUID</span>
                                    <span class="tl-val text-xs">{{ selectedLog.causer_id || 'System Process' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
        <div v-if="selectedLog" class="al-panel-overlay" @click="selectedLog = null"></div>

    </SchoolLayout>
</template>

<style scoped>
.utl-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 4px 0;
}

/* ── Header ── */
.al-header {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.al-title { font-size: 1.5rem; font-weight: 800; color: #1e293b; letter-spacing: -0.03em; margin: 0; }
.al-subtitle { font-size: 0.875rem; color: #64748b; margin: 4px 0 0; }

/* ── Filters ── */
.al-filters { display: flex; align-items: center; gap: 12px; }
.al-input { height: 40px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; font-size: 0.875rem; color: #1e293b; outline: none; transition: all 0.2s; }
.al-input:focus { border-color: #1169cd; box-shadow: 0 0 0 3px rgba(17, 105, 205, 0.1); }

.al-filter-select { width: 160px; padding: 0 12px; }
.al-filter-date { width: 160px; padding: 0 12px; }

.al-search-wrapper { position: relative; width: 280px; }
.al-search { width: 100%; padding: 0 12px 0 36px; }
.al-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8; }

.al-btn-reset { height: 40px; padding: 0 16px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; font-size: 0.8125rem; font-weight: 600; color: #64748b; cursor: pointer; }
.al-btn-reset:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }

/* ── Card & Table ── */
.al-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; }
.al-table :deep(table) { table-layout: fixed; }

.al-badge { display: inline-flex; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; text-transform: capitalize; border: 1px solid rgba(0,0,0,0.05); }

.al-desc { font-weight: 600; color: #1e293b; line-height: 1.5; white-space: normal; }

.al-user { display: flex; flex-direction: column; }
.al-user-name { font-weight: 700; color: #1e293b; font-size: 0.8125rem; }
.al-user-type { font-size: 0.75rem; color: #94a3b8; letter-spacing: 0.01em; }
.al-system-tag { font-size: 0.75rem; font-weight: 700; color: #1169cd; background: #eff6ff; padding: 2px 8px; border-radius: 6px; }

.al-subject { color: #64748b; font-weight: 500; }
.subject-id { font-size: 0.75rem; font-weight: 700; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; margin-left: 4px; }

.al-time { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.75rem; color: #94a3b8; }

.al-icon-btn { width: 32px; height: 32px; border-radius: 8px; background: #f1f5f9; border: none; color: #475569; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
.al-icon-btn:hover { background: #e2e8f0; color: #1169cd; transform: translateY(-1px); }

/* ── Pagination ── */
.al-pg-container { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; }
.al-pg-link { height: 36px; padding: 0 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #475569; text-decoration: none; display: flex; align-items: center; justify-content: center; }
.al-pg-link:hover { border-color: #cbd5e1; background: #f8fafc; color: #1e293b; }
.al-pg-active { background: #1169cd; color: #fff; border-color: #1169cd; }
.al-pg-disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Side Panel ── */
.al-side-panel { position: fixed; right: 0; top: 0; bottom: 0; width: 440px; background: #fff; z-index: 100; box-shadow: -10px 0 50px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
.panel-header { padding: 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
.panel-title { font-size: 1.25rem; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin: 0; }
.panel-close { background: #f1f5f9; border: none; border-radius: 12px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: #64748b; cursor: pointer; }
.panel-close:hover { background: #fee2e2; color: #ef4444; }

.panel-content { flex: 1; padding: 28px; overflow-y: auto; display: flex; flex-direction: column; gap: 24px; }
.panel-section { display: flex; flex-direction: column; gap: 8px; }
.panel-section label { font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; }
.panel-val { font-size: 0.9375rem; color: #1e293b; font-weight: 600; line-height: 1.6; }
.panel-val.high { font-size: 1.125rem; font-weight: 700; color: #1169cd; }

.panel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

.panel-json { background: #0f172a; color: #94a3b8; padding: 20px; border-radius: 14px; font-family: ui-monospace, monospace; font-size: 0.8125rem; line-height: 1.7; overflow-x: auto; max-height: 360px; border: 4px solid #1e293b; }

.panel-timeline { display: flex; flex-direction: column; gap: 16px; margin-top: 8px; }
.timeline-item { display: flex; gap: 12px; position: relative; }
.dot { width: 8px; height: 8px; background: #1169cd; border-radius: 50%; margin-top: 6px; flex-shrink: 0; }
.tl-content { display: flex; flex-direction: column; }
.tl-label { font-size: 0.75rem; color: #94a3b8; font-weight: 500; }
.tl-val { font-size: 0.875rem; color: #1e293b; font-weight: 600; }

.al-panel-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 90; }

.w-24 { width: 96px; }
.w-48 { width: 192px; }
.w-40 { width: 160px; }
.w-44 { width: 176px; }
.w-20 { width: 80px; }
.text-center { text-align: center; }

</style>
