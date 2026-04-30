<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({
    school:   { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
});

const page = usePage();

// ── Settings sidebar nav items ────────────────────────────────────────────
const settingsNav = [
    { id: 'general-config',     label: 'General Config',              route: '/school/settings/general-config',    icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'asset-config',       label: 'Asset Config',                route: '/school/settings/asset-config',      icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'system-config',      label: 'System Config',               route: '/school/settings/system-config',     icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18' },
    { id: 'geofence-config',    label: 'Geofence Config',             route: '/school/settings/geofence-config', icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'admin-contacts',     label: 'Admin Numbers',               route: '/school/settings/admin-contacts',  icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
    { id: 'receipt-print',      label: 'Receipt Print',               route: '/school/settings/receipt-print',   icon: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

// ── Asset definitions ────────────────────────────────────────────────────
const assets = [
    {
        key: 'logo',
        title: 'School Logo',
        size: '400 x 150 px',
        maxFile: '2 MB',
        formats: 'PNG, JPG, SVG',
        hint: 'Used in sidebar, reports, certificates, and mobile app. Transparent PNG recommended.',
        square: false,
        accept: 'image/png,image/jpeg,image/svg+xml,image/webp',
    },
    {
        key: 'icon',
        title: 'App Icon',
        size: '512 x 512 px',
        maxFile: '2 MB',
        formats: 'PNG, JPG',
        hint: 'Square icon for PWA install and mobile app launcher. Must be 1:1 ratio.',
        square: true,
        accept: 'image/png,image/jpeg,image/webp',
    },
    {
        key: 'favicon',
        title: 'Favicon',
        size: '48 x 48 px',
        maxFile: '1 MB',
        formats: 'PNG, ICO',
        hint: 'Small icon shown in browser tabs. Square PNG preferred.',
        square: true,
        accept: 'image/png,image/x-icon,image/vnd.microsoft.icon,image/webp',
    },
];

// —— Form —————————————————————————————————————————————————————————————————
const form = useForm({
    logo:    null,
    icon:    null,
    favicon: null,
});

const previews = ref({
    logo:    props.school.logo ? `/storage/${props.school.logo}` : null,
    icon:    props.settings.icon ? `/storage/${props.settings.icon}` : null,
    favicon: props.school.favicon ? `/storage/${props.school.favicon}` : null,
});

const fileInfo = ref({ logo: null, icon: null, favicon: null });
const dragging = ref({ logo: false, icon: false, favicon: false });

const formatSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const processFile = (file, type) => {
    if (!file) return;
    form[type] = file;
    if (previews.value[type] && previews.value[type].startsWith('blob:')) {
        URL.revokeObjectURL(previews.value[type]);
    }
    previews.value[type] = URL.createObjectURL(file);

    // Read dimensions
    const img = new Image();
    img.onload = () => {
        fileInfo.value[type] = {
            name: file.name,
            size: formatSize(file.size),
            width: img.naturalWidth,
            height: img.naturalHeight,
        };
    };
    img.src = URL.createObjectURL(file);
};

const handleFile = (e, type) => {
    processFile(e.target.files[0], type);
};

const handleDrop = (e, type) => {
    dragging.value[type] = false;
    const file = e.dataTransfer?.files?.[0];
    if (file && file.type.startsWith('image/')) {
        processFile(file, type);
    }
};

const handleDragOver = (e, type) => {
    dragging.value[type] = true;
};

const handleDragLeave = (e, type) => {
    dragging.value[type] = false;
};

const submit = () => {
    form.post('/school/settings/asset-config', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            fileInfo.value = { logo: null, icon: null, favicon: null };
        }
    });
};

const removeAsset = async (type) => {
    const ok = await confirm({
        title: `Remove ${type}?`,
        message: `The current ${type} will be removed from your school's branding.`,
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/settings/asset-config/${type}`, {
        preserveScroll: true,
        onSuccess: () => {
            previews.value[type] = null;
            fileInfo.value[type] = null;
        }
    });
};
</script>

<template>
    <SchoolLayout title="Asset Config">
        <div class="gc-shell">
            <!-- —— Settings Sidebar ————————————————————————————————— -->
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

            <!-- —— Main Content ————————————————————————————————————— -->
            <section class="gc-content">
                <PageHeader
                    title="Asset Configuration"
                    subtitle="Manage your school's visual identity assets."
                />

                <form @submit.prevent="submit" novalidate>
                    <div class="asset-grid">

                        <div v-for="asset in assets" :key="asset.key" class="gc-card">
                            <div class="gc-card-header">
                                <div class="card-header-row">
                                    <h2 class="gc-card-title">{{ asset.title }}</h2>
                                    <span class="badge-size">{{ asset.size }}</span>
                                </div>
                                <p class="gc-card-hint">{{ asset.hint }}</p>
                            </div>
                            <div class="gc-card-body">
                                <div
                                    class="asset-upload-zone"
                                    :class="{ square: asset.square, dragging: dragging[asset.key] }"
                                    @click="$refs[asset.key + 'Input'][0].click()"
                                    @dragover.prevent="handleDragOver($event, asset.key)"
                                    @dragleave="handleDragLeave($event, asset.key)"
                                    @drop.prevent="handleDrop($event, asset.key)"
                                >
                                    <template v-if="previews[asset.key]">
                                        <div class="preview-container" :class="{ 'preview-square': asset.square }">
                                            <img :src="previews[asset.key]" :alt="asset.title" class="preview-img" />
                                            <button type="button" class="remove-btn" @click.stop="removeAsset(asset.key)" title="Remove">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="upload-placeholder">
                                            <svg class="placeholder-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                            </svg>
                                            <p class="upload-text">Click or drag to upload</p>
                                            <p class="upload-formats">{{ asset.formats }} &middot; Max {{ asset.maxFile }}</p>
                                        </div>
                                    </template>
                                    <input type="file" :ref="asset.key + 'Input'" class="hidden" :accept="asset.accept" @change="handleFile($event, asset.key)" />
                                </div>

                                <!-- File info after selection -->
                                <div v-if="fileInfo[asset.key]" class="file-info">
                                    <svg class="file-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="file-info-name">{{ fileInfo[asset.key].name }}</span>
                                    <span class="file-info-meta">{{ fileInfo[asset.key].width }}x{{ fileInfo[asset.key].height }} &middot; {{ fileInfo[asset.key].size }}</span>
                                </div>

                                <span v-if="form.errors[asset.key]" class="gc-error">{{ form.errors[asset.key] }}</span>
                            </div>
                        </div>

                    </div>

                    <!-- Actions -->
                    <div class="gc-actions">
                        <button type="submit" :disabled="form.processing || !form.isDirty" class="gc-btn-save">
                            <svg v-if="form.processing" class="gc-spinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            {{ form.processing ? 'Uploading...' : 'Save Assets' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </SchoolLayout>
</template>

<style scoped>
/* —— Shell —— */
.gc-shell {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 56px);
    margin: -24px -28px;
    background: #f8fafc;
}

/* —— Settings Sidebar —— */
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
.gc-nav-item:hover { background: #f1f5f9; color: #1e293b; }
.gc-nav-item--active { background: #eff6ff !important; color: #1169cd !important; font-weight: 600; }
.gc-nav-icon { width: 15px; height: 15px; opacity: 0.75; }

/* —— Header —— */
.gc-page-header { margin-bottom: 24px; }
.gc-page-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; letter-spacing: -0.02em; margin-bottom: 4px; }
.gc-page-subtitle { font-size: 0.875rem; color: #64748b; }

/* —— Content —— */
.gc-content { flex: 1; padding: 28px 32px; overflow-y: auto; }

/* —— Grid —— */
.asset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* —— Card —— */
.gc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.gc-card-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; }
.card-header-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.gc-card-title { font-size: 0.875rem; font-weight: 700; color: #1e293b; margin: 0; }
.gc-card-hint { font-size: 0.7rem; color: #94a3b8; margin: 4px 0 0; line-height: 1.4; }
.gc-card-body { padding: 20px; }

.badge-size {
    font-size: 0.675rem;
    font-weight: 600;
    color: #1169cd;
    background: #eff6ff;
    padding: 2px 8px;
    border-radius: 4px;
    white-space: nowrap;
}

/* —— Upload Zone —— */
.asset-upload-zone {
    width: 100%;
    min-height: 140px;
    border: 2px dashed #e2e8f0;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fbfcfd;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}
.asset-upload-zone:hover { border-color: #93bbec; background: #f5f9ff; }
.asset-upload-zone.dragging { border-color: #1169cd; background: #edf4ff; box-shadow: 0 0 0 3px rgba(17, 105, 205, 0.1); }
.asset-upload-zone.square { aspect-ratio: 1 / 1; min-height: auto; width: 160px; margin: 0 auto; }

.upload-placeholder { text-align: center; padding: 8px; }
.placeholder-icon { width: 28px; height: 28px; color: #94a3b8; margin: 0 auto 8px; }
.upload-text { font-size: 0.75rem; color: #475569; font-weight: 600; margin: 0 0 4px; }
.upload-formats { font-size: 0.65rem; color: #94a3b8; margin: 0; }

.preview-container {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: repeating-conic-gradient(#f1f5f9 0% 25%, #fff 0% 50%) 50% / 16px 16px;
    padding: 12px;
}
.preview-img { max-width: 100%; max-height: 100%; object-fit: contain; }
.preview-square .preview-img { width: 100%; height: 100%; object-fit: contain; border-radius: 4px; }

.remove-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    backdrop-filter: blur(4px);
}
.remove-btn:hover { background: #ef4444; color: #fff; }

/* —— File Info —— */
.file-info {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
    padding: 8px 10px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
}
.file-info-icon { width: 14px; height: 14px; color: #16a34a; flex-shrink: 0; }
.file-info-name { font-size: 0.7rem; font-weight: 600; color: #15803d; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px; }
.file-info-meta { font-size: 0.65rem; color: #4ade80; margin-left: auto; white-space: nowrap; }

.hidden { display: none; }

/* —— Error —— */
.gc-error { display: block; font-size: 0.7rem; color: #ef4444; margin-top: 6px; }

/* —— Actions —— */
.gc-actions { display: flex; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #f1f5f9; margin-top: 20px; }
.gc-btn-save {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 40px;
    padding: 0 24px;
    border-radius: 8px;
    border: none;
    background: #1169cd;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 1px 2px rgba(17, 105, 205, 0.2);
}
.gc-btn-save:hover:not(:disabled) { background: #0d50a3; box-shadow: 0 4px 6px rgba(17, 105, 205, 0.3); }
.gc-btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

.gc-spinner { width: 16px; height: 16px; animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
