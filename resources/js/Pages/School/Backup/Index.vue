<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useSchoolStore } from '@/stores/useSchoolStore';

const confirm = useConfirm();
const school = useSchoolStore();

const props = defineProps({
    backups: Array,
    stats: Object,
});

const showCreateModal = ref(false);

const form = useForm({ label: '' });

const openCreate = () => {
    form.reset();
    showCreateModal.value = true;
};

const submitCreate = () => {
    form.post('/school/backup', {
        onSuccess: () => { showCreateModal.value = false; form.reset(); },
    });
};

const deleteBackup = async (id) => {
    const ok = await confirm({
        title: 'Delete backup?',
        message: 'This backup will be permanently deleted. This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/backup/${id}`, { preserveScroll: true });
};

const statusBadge = {
    completed: 'badge-green',
    running:   'badge-blue',
    failed:    'badge-red',
};

const statCards = computed(() => [
    { label: 'Total Backups', value: props.stats.total,         color: 'accent' },
    { label: 'Completed',     value: props.stats.completed,     color: 'success' },
    { label: 'Failed',        value: props.stats.failed,        color: 'danger' },
    { label: 'Last Backup',   value: props.stats.last_backup,   color: 'warning' },
    { label: 'Storage Used',  value: props.stats.storage_used,  color: 'purple' },
]);

// ── Table sorting ────────────────────────────────────────────────────────────
const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('created_at', 'desc');
const sortedBackups = computed(() => sortRows(props.backups || [], {
    getValue: (row, key) => {
        if (key === 'size') return row.size_bytes ?? 0;
        return row[key];
    },
}));
</script>

<template>
    <SchoolLayout title="Backup Manager">

        <PageHeader title="Backup Manager" subtitle="Create and manage database backups">
            <template #actions>
                <Button @click="openCreate">+ Create Backup</Button>
            </template>
        </PageHeader>

        <!-- Stats -->
        <StatsRow :cols="4" :stats="statCards" />

        <!-- Backup List -->
        <div class="card" style="overflow:hidden;">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid #f1f5f9;">
                <h3 class="card-title">Backup History</h3>
            </div>
            <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="label">Label</SortableTh>
                        <SortableTh sort-key="status">Status</SortableTh>
                        <SortableTh sort-key="size" align="right">Size</SortableTh>
                        <SortableTh sort-key="duration_seconds" align="right">Duration</SortableTh>
                        <SortableTh sort-key="created_by">Created By</SortableTh>
                        <SortableTh sort-key="created_at">Created At</SortableTh>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in sortedBackups" :key="b.id">
                        <td>
                            <div style="font-weight:600;color:#1e293b;">{{ b.label || 'Manual Backup' }}</div>
                            <div v-if="b.filename" style="font-size:.72rem;color:#94a3b8;margin-top:2px;">{{ b.filename }}</div>
                        </td>
                        <td>
                            <span class="badge" :class="statusBadge[b.status]" style="text-transform:capitalize;">{{ b.status }}</span>
                        </td>
                        <td style="text-align:right;">{{ b.status === 'completed' ? b.formatted_size : '—' }}</td>
                        <td style="text-align:right;">
                            {{ b.duration_seconds != null ? b.duration_seconds + 's' : '—' }}
                        </td>
                        <td>{{ b.created_by || '—' }}</td>
                        <td style="white-space:nowrap;">{{ school.fmtDateTime(b.created_at) }}</td>
                        <td style="text-align:right;">
                            <div style="display:inline-flex;gap:6px;align-items:center;">
                                <a
                                    v-if="b.status === 'completed' && b.file_exists"
                                    :href="`/school/backup/${b.id}/download`"
                                    class="download-btn"
                                    title="Download"
                                >
                                    Download
                                </a>
                                <Button variant="danger" size="xs" @click="deleteBackup(b.id)">Delete</Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!sortedBackups.length">
                        <td colspan="7" style="padding:0;">
                            <EmptyState
                                title="No backups yet"
                                description="Click 'Create Backup' to get started."
                                action-label="+ Create Backup"
                                @action="openCreate"
                            />
                        </td>
                    </tr>
                </tbody>
            </Table>

            <!-- Error rows inline -->
            <template v-if="backups?.some(b => b.status === 'failed' && b.error_message)">
                <div v-for="b in backups.filter(x => x.status === 'failed' && x.error_message)" :key="'e-' + b.id" class="error-row">
                    <span><strong>{{ b.label }}:</strong> {{ b.error_message }}</span>
                </div>
            </template>
        </div>

        <!-- Create Backup Modal -->
        <Modal v-model:open="showCreateModal" title="Create Backup" size="md">
            <form @submit.prevent="submitCreate" id="backup-form">
                <p style="font-size:.78rem;color:#94a3b8;margin:0 0 16px;">A full database dump will be created and stored on the server.</p>
                <div class="form-field">
                    <label>Label <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                    <input v-model="form.label" type="text" placeholder="e.g. Before rollover, Weekly backup…" maxlength="200" />
                    <span v-if="form.errors.label" class="field-error">{{ form.errors.label }}</span>
                </div>
                <div class="backup-note">
                    This operation may take a few minutes depending on database size. Please wait after clicking Create.
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showCreateModal = false">Cancel</Button>
                <Button type="submit" form="backup-form" :loading="form.processing">Create Backup</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* Download link */
.download-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 6px; color: #1d4ed8;
    font-size: .78rem; font-weight: 500;
    text-decoration: none; cursor: pointer;
    transition: background .15s;
}
.download-btn:hover { background: #dbeafe; }

/* Error rows */
.error-row {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 16px;
    background: #fef2f2;
    border-top: 1px solid #fecaca;
    font-size: .8rem; color: #dc2626;
}

/* Form fields — Tailwind preflight workaround */
.form-field { margin-bottom: 16px; }
.form-field label { display: block; font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
.form-field input {
    width: 100%; padding: 8px 12px;
    border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: .9rem; color: #1e293b;
    box-sizing: border-box;
}
.form-field input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.field-error { display: block; font-size: .75rem; color: #dc2626; margin-top: 4px; }

/* Info note */
.backup-note {
    padding: 10px 14px;
    background: #eff6ff; border-radius: 8px;
    font-size: .8rem; color: #1d4ed8;
    line-height: 1.5;
}
</style>
