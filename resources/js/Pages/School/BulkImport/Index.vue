<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useToast } from '@/Composables/useToast';

const page = usePage();
const toast = useToast();

const props = defineProps({
    importTypes: Object,
    selectedType: String,
});

const activeType = ref(props.selectedType || 'students');

const file = ref(null);
const fileError = ref('');
const uploading = ref(false);
const validateOnly = ref(false);
const dragOver = ref(false);
const fileInputRef = ref(null);

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];

const flash = computed(() => page.props.flash || {});
const importErrors = computed(() => flash.value.import_errors || []);
const errorLogPath = computed(() => flash.value.error_log_path || null);

const typeIcons = {
    users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    pencil: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    briefcase: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
};

const switchType = (type) => {
    activeType.value = type;
    file.value = null;
    fileError.value = '';
    uploading.value = false;
    validateOnly.value = false;
};

// ── Excel file handling ──
const onDragOver = (e) => { e.preventDefault(); dragOver.value = true; };
const onDragLeave = () => { dragOver.value = false; };
const validateFile = (f) => {
    fileError.value = '';
    if (!f) return false;
    const ext = f.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        fileError.value = `Invalid file type ".${ext}". Only .xlsx, .xls, .csv allowed.`;
        toast.error(fileError.value);
        return false;
    }
    if (f.size > MAX_FILE_SIZE) {
        fileError.value = `File is too large (${(f.size / 1024 / 1024).toFixed(1)}MB). Maximum is 5MB.`;
        toast.error(fileError.value);
        return false;
    }
    if (f.size === 0) {
        fileError.value = 'File is empty.';
        toast.error(fileError.value);
        return false;
    }
    return true;
};
const setFile = (f) => {
    if (validateFile(f)) { file.value = f; } else { file.value = null; }
};
const onDrop = (e) => {
    e.preventDefault();
    dragOver.value = false;
    if (e.dataTransfer.files.length) setFile(e.dataTransfer.files[0]);
};
const onFileSelect = (e) => { if (e.target.files.length) setFile(e.target.files[0]); };
const removeFile = () => { file.value = null; fileError.value = ''; if (fileInputRef.value) fileInputRef.value.value = ''; };

const submitImport = (dryRun = false) => {
    if (!file.value || uploading.value) return;
    uploading.value = true;
    validateOnly.value = dryRun;
    const label = props.importTypes[activeType.value]?.label || activeType.value;
    toast.info(dryRun ? `Validating ${label} file…` : `Importing ${label}…`);
    const formData = new FormData();
    formData.append('type', activeType.value);
    formData.append('file', file.value);
    if (dryRun) formData.append('validate_only', '1');
    router.post('/school/bulk-import', formData, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            const first = Object.values(errors)[0];
            toast.error(typeof first === 'string'
                ? first
                : 'Server rejected the upload. Check that the file is .xlsx/.xls/.csv and under 5MB.');
        },
        onSuccess: (resp) => {
            const errs = resp.props.flash?.import_errors || [];
            if (errs.length) {
                const sample = errs[0];
                const more = errs.length > 1 ? ` (+${errs.length - 1} more — see table below)` : '';
                toast.warning(`Row ${sample.row} • ${sample.column}: ${sample.message}${more}`, 8000);
            }
        },
        onFinish: () => { uploading.value = false; validateOnly.value = false; },
    });
};

</script>

<template>
    <SchoolLayout title="Bulk Import">

        <PageHeader title="Bulk Import" subtitle="Import students or staff in bulk using Excel files." />

        <div class="bi-layout">
            <!-- Sidebar -->
            <div class="bi-sidebar">
                <button v-for="(info, key) in importTypes" :key="key"
                    @click="switchType(key)"
                    class="bi-type-btn"
                    :class="{ 'bi-type-btn--active': activeType === key }">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="typeIcons[info.icon]" />
                    </svg>
                    <div>
                        <div class="bi-type-label">{{ info.label }}</div>
                        <div class="bi-type-desc">{{ info.description }}</div>
                    </div>
                </button>
            </div>

            <!-- Main content -->
            <div class="bi-main">

                <!-- Step 1: Download Template -->
                    <div class="card bi-step">
                        <div class="bi-step-header">
                            <span class="bi-step-num">1</span>
                            <div>
                                <h3 class="bi-step-title">Download Template</h3>
                                <p class="bi-step-sub">Download the Excel template, fill in your data, then upload it below.</p>
                            </div>
                        </div>
                        <div class="bi-step-body">
                            <Button size="sm" as="a" :href="`/school/bulk-import/template/${activeType}`">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download {{ importTypes[activeType]?.label }} Template
                            </Button>
                        </div>
                    </div>

                    <!-- Step 2: Upload File -->
                    <div class="card bi-step">
                        <div class="bi-step-header">
                            <span class="bi-step-num">2</span>
                            <div>
                                <h3 class="bi-step-title">Upload File</h3>
                                <p class="bi-step-sub">Drag and drop your completed Excel file, or click to browse.</p>
                            </div>
                        </div>
                        <div class="bi-step-body">
                            <div class="bi-dropzone"
                                :class="{ 'bi-dropzone--hover': dragOver, 'bi-dropzone--has-file': file, 'bi-dropzone--error': fileError }"
                                @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop"
                                @click="fileInputRef?.click()">
                                <input ref="fileInputRef" type="file" accept=".xlsx,.xls,.csv" @change="onFileSelect" style="display:none;" />
                                <template v-if="!file && !fileError">
                                    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#94a3b8"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <p style="margin-top:8px;font-weight:600;color:#475569;">Drop your file here or click to browse</p>
                                    <p style="font-size:0.75rem;color:#94a3b8;margin-top:4px;">Supports .xlsx, .xls, .csv (max 5MB, 1000 rows)</p>
                                </template>
                                <template v-else-if="fileError">
                                    <div class="bi-file-error" @click.stop>
                                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#ef4444"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>{{ fileError }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="bi-file-info" @click.stop>
                                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#10b981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <div>
                                            <div style="font-weight:600;color:#0f172a;">{{ file.name }}</div>
                                            <div style="font-size:0.75rem;color:#94a3b8;">{{ (file.size / 1024).toFixed(1) }} KB</div>
                                        </div>
                                        <button @click.stop="removeFile" class="bi-file-remove" title="Remove file">&times;</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Import -->
                    <div class="card bi-step">
                        <div class="bi-step-header">
                            <span class="bi-step-num">3</span>
                            <div>
                                <h3 class="bi-step-title">Validate & Import</h3>
                                <p class="bi-step-sub">All rows are validated before import. If errors are found, nothing is imported.</p>
                            </div>
                        </div>
                        <div class="bi-step-body">
                            <div class="bi-action-row">
                                <Button variant="secondary" @click="submitImport(true)" :disabled="!file || uploading">
                                    <svg v-if="uploading && validateOnly" class="bi-spinner" width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/></circle></svg>
                                    <svg v-else width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Validate Only
                                </Button>
                                <Button @click="submitImport(false)" :disabled="!file || uploading">
                                    <svg v-if="uploading && !validateOnly" class="bi-spinner" width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/></circle></svg>
                                    <svg v-else width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    {{ uploading ? 'Processing...' : 'Validate & Import' }}
                                </Button>
                            </div>
                            <p class="bi-action-hint">Use "Validate Only" to check your file for errors without importing any data.</p>
                        </div>
                    </div>

                <!-- ═══ ERROR TABLE ═══ -->
                <div v-if="importErrors.length" class="card bi-step">
                    <div class="bi-step-header">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#ef4444"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div style="flex:1;">
                            <h3 class="bi-step-title" style="color:#ef4444;">Validation Errors</h3>
                            <p class="bi-step-sub">Fix these errors in your file and re-upload.</p>
                        </div>
                        <Button variant="secondary" size="xs" as="a" v-if="errorLogPath" :href="`/school/bulk-import/errors?path=${encodeURIComponent(errorLogPath)}`">
                            Download Full Error Log
                        </Button>
                    </div>
                    <div class="bi-step-body" style="padding-top:0;">
                        <div style="overflow-x:auto;">
                            <Table style="font-size:0.8125rem;">
                                <thead>
                                    <tr>
                                        <th style="width:60px;">Row</th>
                                        <th style="width:130px;">Column</th>
                                        <th>Error Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(err, i) in importErrors" :key="i">
                                        <td><span class="badge badge-red" style="font-size:0.7rem;">{{ err.row }}</span></td>
                                        <td style="font-family:monospace;color:#6366f1;font-weight:600;">{{ err.column }}</td>
                                        <td>{{ err.message }}</td>
                                    </tr>
                                </tbody>
                            </Table>
                        </div>
                    </div>
                </div>

                <!-- Guidelines -->
                <div class="card bi-step" style="border-left:3px solid #e0e7ff;">
                    <div class="bi-step-body" style="padding:16px 20px;">
                        <h4 style="font-size:0.8125rem;font-weight:700;color:#475569;margin-bottom:8px;">Import Guidelines</h4>
                        <ul class="bi-guidelines">
                            <li>Maximum <strong>1,000 rows</strong> per import file.</li>
                            <li>All rows are validated <strong>before</strong> any data is saved.</li>
                            <li>If any row has an error, <strong>nothing is imported</strong> &mdash; fix errors and re-upload.</li>
                            <li>Delete the sample rows from the template before uploading.</li>
                            <li v-if="activeType === 'student_update'">Leave cells <strong>empty</strong> to keep the existing value unchanged.</li>
                            <li v-if="activeType === 'staff'">A random password is generated for each staff account. Share login credentials separately.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.bi-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 800px) { .bi-layout { grid-template-columns: 1fr; } }

/* Sidebar */
.bi-sidebar {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px;
}
.bi-type-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: none;
    background: transparent;
    border-radius: 8px;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s;
    color: #64748b;
}
.bi-type-btn:hover { background: #f8fafc; color: #334155; }
.bi-type-btn--active {
    background: #f0f0ff;
    color: #4f46e5;
    box-shadow: inset 0 0 0 1px #c7d2fe;
}
.bi-type-btn--active svg { color: #6366f1; }
.bi-type-label { font-size: 0.8125rem; font-weight: 600; line-height: 1.3; }
.bi-type-desc { font-size: 0.7rem; color: #94a3b8; margin-top: 1px; }
.bi-type-btn--active .bi-type-label { color: #4338ca; }

/* Steps */
.bi-step { margin-bottom: 16px; }
.bi-step-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px 0;
}
.bi-step-num {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: #6366f1;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}
.bi-step-title { font-size: 0.875rem; font-weight: 700; color: #0f172a; }
.bi-step-sub { font-size: 0.775rem; color: #94a3b8; margin-top: 2px; }
.bi-step-body { padding: 14px 20px 16px; }

/* Dropzone */
.bi-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 32px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #fafbfc;
}
.bi-dropzone:hover { border-color: #a5b4fc; background: #f8f8ff; }
.bi-dropzone--hover { border-color: #6366f1; background: #eef2ff; }
.bi-dropzone--has-file { border-style: solid; border-color: #10b981; background: #f0fdf4; cursor: default; }

.bi-file-info {
    display: flex;
    align-items: center;
    gap: 12px;
    justify-content: center;
}
.bi-file-remove {
    width: 24px; height: 24px;
    border: none; background: #fee2e2;
    color: #ef4444;
    border-radius: 50%;
    font-size: 1.1rem; font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    line-height: 1;
}
.bi-file-remove:hover { background: #fca5a5; }

/* File error */
.bi-dropzone--error { border-color: #fca5a5; background: #fef2f2; }
.bi-file-error {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #dc2626;
    font-size: 0.8125rem;
    font-weight: 500;
}

/* Action row */
.bi-action-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.bi-action-hint { font-size: 0.7rem; color: #94a3b8; margin-top: 8px; }

/* Spinner */
.bi-spinner { animation: bi-spin 0.8s linear infinite; }
@keyframes bi-spin { to { transform: rotate(360deg); } }

/* Guidelines */
.bi-guidelines {
    list-style: none;
    padding: 0;
    margin: 0;
}
.bi-guidelines li {
    position: relative;
    padding-left: 16px;
    font-size: 0.775rem;
    color: #64748b;
    line-height: 1.7;
}
.bi-guidelines li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 9px;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #a5b4fc;
}
</style>
