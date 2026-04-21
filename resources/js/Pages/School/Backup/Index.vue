<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const deleteBackup = (id) => {
    if (!confirm('Delete this backup? This cannot be undone.')) return;
    router.delete(`/school/backup/${id}`, { preserveScroll: true });
};

const statusBadge = {
    completed: 'badge-green',
    running:   'badge-blue',
    failed:    'badge-red',
};
</script>

<template>
    <SchoolLayout title="Backup Manager">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Backup Manager</h1>
                <p class="page-header-sub">Create and manage database backups</p>
            </div>
            <Button @click="openCreate">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Backup
            </Button>
        </div>

        <!-- Stats -->
        <div class="backup-stats">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff;color:#1d4ed8;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                </div>
                <div>
                    <div class="stat-value" style="color:#1d4ed8;">{{ stats.total }}</div>
                    <div class="stat-label">Total Backups</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-value" style="color:#16a34a;">{{ stats.completed }}</div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef2f2;color:#dc2626;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-value" style="color:#dc2626;">{{ stats.failed }}</div>
                    <div class="stat-label">Failed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fffbeb;color:#d97706;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-value" style="color:#d97706;font-size:1rem;">{{ stats.last_backup }}</div>
                    <div class="stat-label">Last Backup</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#faf5ff;color:#7c3aed;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582 4 8 4s8-1.79 8-4"/></svg>
                </div>
                <div>
                    <div class="stat-value" style="color:#7c3aed;font-size:1rem;">{{ stats.storage_used }}</div>
                    <div class="stat-label">Storage Used</div>
                </div>
            </div>
        </div>

        <!-- Backup List -->
        <div class="card" style="overflow:hidden;">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid #f1f5f9;">
                <h3 class="card-title">Backup History</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Duration</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in backups" :key="b.id">
                            <td>
                                <div style="font-weight:600;color:#1e293b;">{{ b.label || 'Manual Backup' }}</div>
                                <div v-if="b.filename" style="font-size:.72rem;color:#94a3b8;margin-top:2px;">{{ b.filename }}</div>
                            </td>
                            <td>
                                <span class="badge" :class="statusBadge[b.status]" style="text-transform:capitalize;">{{ b.status }}</span>
                            </td>
                            <td style="color:#475569;">{{ b.status === 'completed' ? b.formatted_size : '—' }}</td>
                            <td style="color:#64748b;font-size:.85rem;">
                                {{ b.duration_seconds != null ? b.duration_seconds + 's' : '—' }}
                            </td>
                            <td style="font-size:.85rem;color:#64748b;">{{ b.created_by || '—' }}</td>
                            <td style="white-space:nowrap;color:#64748b;font-size:.85rem;">{{ b.created_at }}</td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;align-items:center;">
                                    <a
                                        v-if="b.status === 'completed' && b.file_exists"
                                        :href="`/school/backup/${b.id}/download`"
                                        class="download-btn"
                                        title="Download"
                                    >
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Download
                                    </a>
                                    <Button variant="danger" size="xs" @click="deleteBackup(b.id)">Delete</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!backups?.length">
                            <td colspan="7" style="text-align:center;padding:48px;color:#94a3b8;font-size:.9rem;">
                                No backups yet. Click "Create Backup" to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Error rows inline -->
                <template v-if="backups?.some(b => b.status === 'failed' && b.error_message)">
                    <div v-for="b in backups.filter(x => x.status === 'failed' && x.error_message)" :key="'e-' + b.id" class="error-row">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#dc2626"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><strong>{{ b.label }}:</strong> {{ b.error_message }}</span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Create Backup Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="modal-backdrop" @click.self="showCreateModal = false">
                <div class="modal create-modal">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">Create Backup</h3>
                            <p style="font-size:.78rem;color:#94a3b8;margin:2px 0 0;">A full database dump will be created and stored on the server.</p>
                        </div>
                        <button @click="showCreateModal = false" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submitCreate">
                        <div class="modal-body">
                            <div class="form-field">
                                <label>Label <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                                <input v-model="form.label" type="text" placeholder="e.g. Before rollover, Weekly backup…" maxlength="200" />
                                <span v-if="form.errors.label" class="field-error">{{ form.errors.label }}</span>
                            </div>
                            <div class="backup-note">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#3b82f6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                This operation may take a few minutes depending on database size. Please wait after clicking Create.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showCreateModal = false">Cancel</Button>
                            <Button type="submit" :loading="form.processing">Create Backup</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
/* Stats */
.backup-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
.stat-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
.stat-label { font-size: .75rem; color: #94a3b8; margin-top: 3px; }

/* Table */
.backup-table { width: 100%; border-collapse: collapse; }
.backup-table th {
    padding: 11px 16px;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    font-size: .72rem; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .04em;
    text-align: left; white-space: nowrap;
}
.backup-table td {
    padding: 13px 16px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.backup-table tbody tr:hover { background: #f8fafc; }
.backup-table tbody tr:last-child td { border-bottom: none; }

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

/* Modal */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, .45);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
}
.create-modal {
    background: #fff;
    border-radius: 14px;
    width: 480px;
    max-width: calc(100vw - 32px);
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
}
.modal-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 20px 24px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.modal-title { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0; }
.modal-close {
    background: none; border: none; font-size: 1.4rem; color: #94a3b8;
    cursor: pointer; padding: 0; line-height: 1;
}
.modal-close:hover { color: #475569; }
.modal-body { padding: 20px 24px; }
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    display: flex; justify-content: flex-end; gap: 10px;
}

/* Form fields */
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
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 14px;
    background: #eff6ff; border-radius: 8px;
    font-size: .8rem; color: #1d4ed8;
    line-height: 1.5;
}

@media (max-width: 900px) {
    .backup-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .backup-stats { grid-template-columns: 1fr; }
}
</style>
