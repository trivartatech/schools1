<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const page = usePage();
const bulkResults = computed(() => page.props.bulk_results || null);

const form = useForm({
    photos: []
});

const fileInput = ref(null);
const previewImages = ref([]);

const handleFileChange = (e) => {
    const files = Array.from(e.target.files);
    form.photos = files;

    // Clear previous previews
    previewImages.value = [];

    // Generate previews (limit to first 20 for performance)
    files.slice(0, 20).forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImages.value.push({
                name: file.name,
                url: e.target.result
            });
        };
        reader.readAsDataURL(file);
    });
};

const submit = () => {
    form.post(route('school.students.bulk-photo.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success logic if needed
        }
    });
};
</script>

<template>
    <SchoolLayout title="Bulk Student Photo Upload">

        <!-- Page header -->
        <PageHeader subtitle="Update multiple student photos at once using admission numbers.">
            <template #title>
                <div class="ph-title-row">
                    <Link href="/school/students" class="back-btn" aria-label="Back to students">
                        <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </Link>
                    <h1 class="page-header-title">Bulk Photo Upload</h1>
                </div>
            </template>
        </PageHeader>

        <div class="upload-layout">

            <!-- Instructions card -->
            <div class="card instructions-card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-num">1</span>
                        Naming Convention &amp; Requirements
                    </h3>
                </div>
                <div class="card-body">
                    <div class="instruction-grid">
                        <div class="instruction-item">
                            <div class="instruction-icon" style="background:#e0e7ff; color:#4338ca">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                            </div>
                            <div>
                                <p class="instruction-title">File Naming</p>
                                <p class="instruction-desc">Name files as <code>AdmissionNumber.ext</code> — e.g. <code>ADM001.jpg</code> or <code>2024001.png</code></p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-icon" style="background:#dcfce7; color:#15803d">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h14a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="instruction-title">Supported Formats</p>
                                <p class="instruction-desc">JPG, JPEG, PNG — max <strong>10 MB</strong> per image</p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-icon" style="background:#fef3c7; color:#b45309">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div>
                                <p class="instruction-title">Bulk Upload</p>
                                <p class="instruction-desc">Select hundreds of images at once and upload in a single batch</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload form -->
            <form @submit.prevent="submit" class="card">
                <div class="card-header">
                    <h3 class="card-title section-title">
                        <span class="section-num">2</span>
                        Select Photos
                    </h3>
                    <span v-if="form.photos.length > 0" class="badge badge-indigo">
                        {{ form.photos.length }} file{{ form.photos.length === 1 ? '' : 's' }} selected
                    </span>
                </div>

                <div class="card-body">

                    <!-- Drop zone -->
                    <div
                        class="drop-zone"
                        :class="{ 'drop-zone--active': form.photos.length > 0 }"
                        @click="$refs.fileInput.click()"
                    >
                        <input
                            type="file"
                            ref="fileInput"
                            multiple
                            accept="image/*"
                            class="hidden-input"
                            @change="handleFileChange"
                        />

                        <div class="drop-zone-icon-wrap">
                            <svg v-if="form.photos.length === 0" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <svg v-else width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>

                        <p class="drop-zone-title">
                            {{ form.photos.length > 0 ? `${form.photos.length} photos selected` : 'Click to choose files' }}
                        </p>
                        <p class="drop-zone-sub">
                            {{ form.photos.length > 0 ? 'Click again to change selection' : 'or drag and drop images here' }}
                        </p>
                    </div>

                    <!-- Preview grid -->
                    <div v-if="previewImages.length > 0" class="preview-section">
                        <div class="preview-header">
                            <p class="preview-label">Preview</p>
                            <span class="preview-note">Showing first {{ previewImages.length }} of {{ form.photos.length }}</span>
                        </div>
                        <div class="preview-grid">
                            <div v-for="(img, idx) in previewImages" :key="idx" class="preview-item">
                                <img :src="img.url" :title="img.name" class="preview-img" />
                                <div class="preview-overlay">
                                    <span class="preview-name">{{ img.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error -->
                    <div v-if="form.errors.photos" class="upload-error">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ form.errors.photos }}
                    </div>
                </div>

                <div class="card-footer">
                    <Button type="submit" :loading="form.processing" :disabled="form.photos.length === 0">
                        <svg v-if="form.processing" class="spin-icon" width="15" height="15" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <svg v-else width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        {{ form.processing ? 'Processing…' : 'Upload and Update Photos' }}
                    </Button>
                </div>
            </form>

            <!-- Results section -->
            <div v-if="bulkResults" class="results-section">

                <!-- Summary row -->
                <div class="results-summary">
                    <div class="result-summary-item result-summary-item--success" v-if="bulkResults.success.length > 0">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <strong>{{ bulkResults.success.length }}</strong> updated successfully
                    </div>
                    <div class="result-summary-item result-summary-item--failed" v-if="bulkResults.failed.length > 0">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <strong>{{ bulkResults.failed.length }}</strong> failed
                    </div>
                </div>

                <!-- Success list -->
                <div v-if="bulkResults.success.length > 0" class="card result-card result-card--success">
                    <div class="card-header result-header">
                        <div class="result-header-left">
                            <span class="result-icon result-icon--success">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <h3 class="card-title">Successfully Updated</h3>
                        </div>
                        <span class="badge badge-green">{{ bulkResults.success.length }}</span>
                    </div>
                    <div class="result-table-wrap">
                        <Table>
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Admission #</th>
                                    <th>Student Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, i) in bulkResults.success" :key="i">
                                    <td class="mono">{{ item.file }}</td>
                                    <td><strong>{{ item.admission_no }}</strong></td>
                                    <td>{{ item.name }}</td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>

                <!-- Failed list -->
                <div v-if="bulkResults.failed.length > 0" class="card result-card result-card--failed">
                    <div class="card-header result-header">
                        <div class="result-header-left">
                            <span class="result-icon result-icon--failed">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </span>
                            <h3 class="card-title">Failed Records</h3>
                        </div>
                        <span class="badge badge-red">{{ bulkResults.failed.length }}</span>
                    </div>
                    <div class="result-table-wrap">
                        <Table>
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Admission #</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, i) in bulkResults.failed" :key="i">
                                    <td class="mono">{{ item.file }}</td>
                                    <td class="text-danger"><strong>{{ item.admission_no }}</strong></td>
                                    <td class="text-danger italic">{{ item.reason }}</td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>

            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Header ── */
.ph-title-row { display: flex; align-items: center; gap: .875rem; }
.back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    color: #64748b;
    text-decoration: none;
    transition: background .15s, color .15s;
    flex-shrink: 0;
}
.back-btn:hover { background: #f1f5f9; color: #1e293b; }

/* ── Layout ── */
.upload-layout { display: flex; flex-direction: column; gap: 1.125rem; }

/* ── Section number badge ── */
.section-title { display: flex; align-items: center; gap: .5rem; }
.section-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 5px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: .6875rem;
    font-weight: 800;
    flex-shrink: 0;
}

/* ── Instructions grid ── */
.instructions-card { border-top: 3px solid var(--accent); }
.instruction-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 720px) { .instruction-grid { grid-template-columns: 1fr; } }
.instruction-item {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
}
.instruction-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    flex-shrink: 0;
}
.instruction-title {
    font-size: .8125rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 .2rem;
}
.instruction-desc {
    font-size: .8125rem;
    color: #64748b;
    margin: 0;
    line-height: 1.55;
}
code {
    background: #f1f5f9;
    color: #db2777;
    padding: .1em .4em;
    border-radius: 4px;
    font-size: .8125rem;
    font-family: ui-monospace, monospace;
}

/* ── Card footer ── */
.card-footer {
    display: flex;
    justify-content: flex-end;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border);
    background: #f8fafc;
}

/* ── Drop zone ── */
.hidden-input { display: none; }
.drop-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 3rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    user-select: none;
}
.drop-zone:hover { border-color: var(--accent); background: #eef2ff; }
.drop-zone--active {
    border-color: var(--accent);
    background: #eef2ff;
    border-style: solid;
}
.drop-zone-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: #e0e7ff;
    color: var(--accent);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: .75rem;
    transition: transform .2s;
}
.drop-zone--active .drop-zone-icon-wrap { background: #c7d2fe; color: #4338ca; }
.drop-zone:hover .drop-zone-icon-wrap { transform: translateY(-2px); }
.drop-zone-title { font-size: .9375rem; font-weight: 700; color: #1e293b; margin: 0 0 .25rem; }
.drop-zone-sub { font-size: .8125rem; color: #94a3b8; margin: 0; }

/* ── Preview ── */
.preview-section { margin-top: 1.5rem; }
.preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .625rem;
}
.preview-label {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #475569;
    margin: 0;
}
.preview-note { font-size: .75rem; color: #94a3b8; }
.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(64px, 1fr));
    gap: .5rem;
}
.preview-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border);
}
.preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.preview-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, .6);
    opacity: 0;
    transition: opacity .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
}
.preview-item:hover .preview-overlay { opacity: 1; }
.preview-name {
    font-size: .5rem;
    color: #fff;
    font-family: ui-monospace, monospace;
    word-break: break-all;
    text-align: center;
    line-height: 1.3;
}

/* ── Upload error ── */
.upload-error {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-top: 1rem;
    padding: .625rem .875rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--radius);
    color: #dc2626;
    font-size: .8125rem;
    font-weight: 500;
}

/* ── Results ── */
.results-section { display: flex; flex-direction: column; gap: 1rem; }

.results-summary {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}
.result-summary-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem 1rem;
    border-radius: var(--radius);
    font-size: .875rem;
}
.result-summary-item--success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.result-summary-item--failed  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.result-card--success { border-top: 3px solid var(--success); }
.result-card--failed  { border-top: 3px solid var(--danger); }

.result-header { display: flex; align-items: center; justify-content: space-between; }
.result-header-left { display: flex; align-items: center; gap: .625rem; }

.result-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    flex-shrink: 0;
}
.result-icon--success { background: #dcfce7; color: #15803d; }
.result-icon--failed  { background: #fee2e2; color: #dc2626; }

.result-table-wrap { max-height: 260px; overflow-y: auto; }

.mono { font-family: ui-monospace, monospace; font-size: .8125rem; }
.text-danger { color: var(--danger); }
.italic { font-style: italic; }

/* ── Spinner ── */
.spin-icon { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
