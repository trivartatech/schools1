<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    overdue: { type: Array, default: () => [] },
    today: { type: Array, default: () => [] },
    upcoming: { type: Array, default: () => [] },
    recentCompleted: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ overdue_count: 0, today_count: 0, upcoming_count: 0 }) },
});

const activeTab = ref('overdue');
const showCompleted = ref(false);

const tabs = [
    { key: 'overdue', label: 'Overdue', count: computed(() => props.stats.overdue_count) },
    { key: 'today', label: 'Today', count: computed(() => props.stats.today_count) },
    { key: 'upcoming', label: 'Upcoming', count: computed(() => props.stats.upcoming_count) },
];

const activeList = computed(() => {
    if (activeTab.value === 'overdue') return props.overdue;
    if (activeTab.value === 'today') return props.today;
    return props.upcoming;
});

const markComplete = (log) => {
    router.put(`/school/front-office/call-logs/${log.id}`, {
        follow_up_completed: true,
    }, { preserveScroll: true });
};

const formatDate = (dt) => {
    if (!dt) return '--';
    return new Date(dt).toLocaleDateString([], { dateStyle: 'medium' });
};

const studentName = (log) => {
    if (!log.related_student) return '--';
    return `${log.related_student.first_name} ${log.related_student.last_name}`;
};

const handlerName = (log) => {
    return log.handled_by?.user?.name || '--';
};
</script>

<template>
    <SchoolLayout title="Follow-Up Dashboard">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Follow-Up Dashboard</h1>
                <p class="page-header-sub">Track and manage pending call log follow-ups across all timelines.</p>
            </div>
            <Button variant="secondary" as="link" href="/school/front-office/call-logs">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Call Logs
            </Button>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="card stat-card stat-card--red">
                <div class="card-body">
                    <div class="stat-icon stat-icon--red">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number">{{ stats.overdue_count }}</span>
                        <span class="stat-label">Overdue</span>
                    </div>
                </div>
            </div>
            <div class="card stat-card stat-card--amber">
                <div class="card-body">
                    <div class="stat-icon stat-icon--amber">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number">{{ stats.today_count }}</span>
                        <span class="stat-label">Due Today</span>
                    </div>
                </div>
            </div>
            <div class="card stat-card stat-card--blue">
                <div class="card-body">
                    <div class="stat-icon stat-icon--blue">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number">{{ stats.upcoming_count }}</span>
                        <span class="stat-label">Upcoming</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Bar -->
        <div class="tab-bar">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="tab-item"
                :class="{ 'tab-active': activeTab === tab.key }"
            >
                {{ tab.label }}
                <span v-if="tab.count.value > 0" class="tab-count" :class="`tab-count--${tab.key}`">{{ tab.count.value }}</span>
            </button>
        </div>

        <!-- Active Section Table -->
        <div class="card" style="overflow:hidden;">
            <div class="card-header">
                <span class="card-title">
                    {{ activeTab === 'overdue' ? 'Overdue Follow-Ups' : activeTab === 'today' ? "Today's Follow-Ups" : 'Upcoming Follow-Ups' }}
                </span>
                <span v-if="activeList.length" class="badge" :class="activeTab === 'overdue' ? 'badge-red' : activeTab === 'today' ? 'badge-amber' : 'badge-green'">
                    {{ activeList.length }} item{{ activeList.length !== 1 ? 's' : '' }}
                </span>
            </div>
            <div style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Caller</th>
                            <th>Phone</th>
                            <th>Purpose</th>
                            <th>Handler</th>
                            <th>Student</th>
                            <th>Follow-up Date</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="activeList.length === 0">
                            <td colspan="7" class="empty-state">
                                No follow-ups in this category.
                            </td>
                        </tr>
                        <tr
                            v-for="log in activeList"
                            :key="log.id"
                            :class="{ 'row-overdue': activeTab === 'overdue' }"
                        >
                            <td>
                                <div class="caller-cell">
                                    <span class="caller-name">{{ log.caller_name }}</span>
                                    <span class="badge badge-gray" style="font-size:.65rem;">{{ log.call_type }}</span>
                                </div>
                            </td>
                            <td>
                                <a :href="`tel:${log.phone_number}`" class="phone-link">{{ log.phone_number }}</a>
                            </td>
                            <td>{{ log.purpose }}</td>
                            <td>{{ handlerName(log) }}</td>
                            <td>{{ studentName(log) }}</td>
                            <td>
                                <span class="followup-date" :class="{ 'followup-date--overdue': activeTab === 'overdue' }">
                                    {{ formatDate(log.follow_up_date) }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <Button size="xs" @click="markComplete(log)">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Mark Complete
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Recently Completed (Collapsible) -->
        <div class="card completed-card">
            <div class="card-header completed-header" @click="showCompleted = !showCompleted" style="cursor:pointer;">
                <span class="card-title" style="display:flex;align-items:center;gap:8px;">
                    <svg class="w-5 h-5" style="color:var(--success, #16a34a);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Recently Completed
                    <span class="badge badge-gray">{{ recentCompleted.length }}</span>
                </span>
                <svg
                    class="w-5 h-5 chevron-icon"
                    :class="{ 'chevron-icon--open': showCompleted }"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-show="showCompleted" style="overflow-x:auto;">
                    <Table>
                        <thead>
                            <tr>
                                <th>Caller</th>
                                <th>Phone</th>
                                <th>Purpose</th>
                                <th>Handler</th>
                                <th>Student</th>
                                <th>Follow-up Date</th>
                                <th style="text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="recentCompleted.length === 0">
                                <td colspan="7" class="empty-state">
                                    No completed follow-ups yet.
                                </td>
                            </tr>
                            <tr v-for="log in recentCompleted" :key="log.id" class="row-completed">
                                <td>
                                    <span class="caller-name">{{ log.caller_name }}</span>
                                </td>
                                <td>{{ log.phone_number }}</td>
                                <td>{{ log.purpose }}</td>
                                <td>{{ handlerName(log) }}</td>
                                <td>{{ studentName(log) }}</td>
                                <td>{{ formatDate(log.follow_up_date) }}</td>
                                <td style="text-align:center;">
                                    <span class="badge badge-green">Completed</span>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </Transition>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* Stats row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 640px) {
    .stats-row { grid-template-columns: 1fr; }
}

.stat-card .card-body {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 3rem;
    height: 3rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon--red { background: #fee2e2; color: #dc2626; }
.stat-icon--amber { background: #fef3c7; color: #d97706; }
.stat-icon--blue { background: #dbeafe; color: #2563eb; }

.stat-card--red { border-left: 3px solid #dc2626; }
.stat-card--amber { border-left: 3px solid #d97706; }
.stat-card--blue { border-left: 3px solid #2563eb; }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
    color: var(--text-primary);
}

.stat-label {
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted);
    margin-top: .25rem;
}

/* Tab bar */
.tab-bar {
    display: flex;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    font-size: .875rem;
    font-weight: 500;
    gap: .25rem;
}

.tab-item {
    padding: .75rem 1.25rem;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
    cursor: pointer;
    color: var(--text-muted);
    transition: color .15s;
    display: flex;
    align-items: center;
    gap: .5rem;
}

.tab-active {
    border-bottom-color: var(--accent);
    color: var(--accent);
}

.tab-count {
    font-size: .6875rem;
    font-weight: 700;
    padding: .125rem .5rem;
    border-radius: 9999px;
    color: #fff;
}

.tab-count--overdue { background: #dc2626; }
.tab-count--today { background: #d97706; }
.tab-count--upcoming { background: #2563eb; }

/* Table rows */
.empty-state {
    text-align: center;
    padding: 2.5rem;
    color: var(--text-muted);
}

.row-overdue {
    background: #fef2f2;
}

.row-completed {
    opacity: .7;
}

/* Caller cell */
.caller-cell {
    display: flex;
    flex-direction: column;
    gap: .25rem;
}

.caller-name {
    font-weight: 600;
    color: var(--text-primary);
}

.phone-link {
    font-family: monospace;
    font-size: .8125rem;
    color: var(--accent);
    text-decoration: none;
}
.phone-link:hover {
    text-decoration: underline;
}

/* Follow-up date */
.followup-date {
    font-size: .8125rem;
    font-weight: 600;
}

.followup-date--overdue {
    color: #dc2626;
}

/* Completed section */
.completed-card {
    margin-top: 1.5rem;
}

.completed-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chevron-icon {
    color: var(--text-muted);
    transition: transform .2s;
    flex-shrink: 0;
}

.chevron-icon--open {
    transform: rotate(180deg);
}
</style>
