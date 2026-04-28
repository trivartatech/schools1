<script setup>
import { computed } from 'vue'
import PageHeader from '@/Components/ui/PageHeader.vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue'

const props = defineProps({
    run:   { type: Object, required: true },
    items: { type: Object, required: true },
})

const stats = computed(() => props.run.stats || {})

const phaseLabel = (state) => ({
    draft:              'Draft',
    structure_running:  'Cloning structure',
    structure_done:     'Structure cloned',
    students_running:   'Promoting students',
    students_done:      'Students promoted',
    fees_running:       'Carrying forward fees',
    fees_done:          'Fees carried',
    finalized:          'Finalized',
    failed:             'Failed',
    cancelled:          'Cancelled',
}[state] || state)

const statusClass = (s) => ({
    success: 'status-success',
    skipped: 'status-skipped',
    failed:  'status-failed',
    pending: 'status-pending',
}[s] || 'status-pending')
</script>

<template>
    <SchoolLayout :title="`Rollover Run #${run.id}`">
        <PageHeader>
            <template #title>
                <h1 class="page-header-title">Rollover Run #{{ run.id }}</h1>
            </template>
            <template #subtitle>
                <p class="page-header-sub">{{ run.source_year?.name }} → {{ run.target_year?.name }}
                    · Started by {{ run.started_by?.name || '—' }}
                    · State: <strong>{{ phaseLabel(run.state) }}</strong></p>
            </template>
            <template #actions>
                <a href="/school/settings/rollover" class="back-link">← Back to Wizard</a>
            </template>
        </PageHeader>

        <div v-if="run.error" class="error-box">{{ run.error }}</div>

        <div class="stats-grid" v-if="stats && Object.keys(stats).length">
            <div v-for="(val, key) in stats" :key="key" class="stat-card">
                <div class="stat-key">{{ key }}</div>
                <pre class="stat-val">{{ JSON.stringify(val, null, 2) }}</pre>
            </div>
        </div>

        <div class="items-wrap">
            <h3>Items ({{ items.total }})</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th><th>Phase</th><th>Type</th><th>Source</th><th>Target</th><th>Status</th><th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items.data" :key="item.id">
                        <td>{{ item.id }}</td>
                        <td>{{ item.phase }}</td>
                        <td>{{ item.item_type }}</td>
                        <td>{{ item.source_id || '—' }}</td>
                        <td>{{ item.target_id || '—' }}</td>
                        <td><span :class="['status-chip', statusClass(item.status)]">{{ item.status }}</span></td>
                        <td>{{ item.note || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="pagination" v-if="items.last_page > 1">
                <a v-for="link in items.links" :key="link.label"
                   :href="link.url"
                   :class="['page-link', link.active ? 'page-link--active' : '', !link.url ? 'page-link--disabled' : '']"
                   v-html="link.label"></a>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.back-link { font-size: 0.85rem; font-weight: 700; color: var(--accent); text-decoration: none; }
.back-link:hover { text-decoration: underline; }

.error-box { background: #fef2f2; color: #b91c1c; padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1.25rem; font-family: ui-monospace, monospace; font-size: 0.85rem; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
.stat-card { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0.85rem 1rem; }
.stat-key { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin-bottom: 0.35rem; }
.stat-val { font-family: ui-monospace, monospace; font-size: 0.78rem; margin: 0; white-space: pre-wrap; color: #1e293b; }

.items-wrap { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 1rem 1.25rem; }
.items-wrap h3 { margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; }
.items-table { width: 100%; border-collapse: collapse; }
.items-table th, .items-table td { padding: 0.5rem 0.7rem; font-size: 0.8rem; text-align: left; border-bottom: 1px solid var(--border); }
.items-table th { background: #f8fafc; font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.05em; }

.status-chip { padding: 0.15rem 0.55rem; border-radius: 10px; font-size: 0.72rem; font-weight: 700; }
.status-success { background: #dcfce7; color: #166534; }
.status-skipped { background: #e0f2fe; color: #0369a1; }
.status-failed  { background: #fef2f2; color: #b91c1c; }
.status-pending { background: #f1f5f9; color: #475569; }

.pagination { display: flex; gap: 0.35rem; margin-top: 1rem; justify-content: center; flex-wrap: wrap; }
.page-link { padding: 0.35rem 0.7rem; border: 1px solid var(--border); border-radius: calc(var(--radius) - 2px); font-size: 0.8rem; color: #334155; text-decoration: none; background: var(--surface); }
.page-link--active { background: var(--accent); color: #fff; border-color: var(--accent); }
.page-link--disabled { opacity: 0.4; pointer-events: none; }
</style>
