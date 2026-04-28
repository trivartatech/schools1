<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed } from 'vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import FilterBar from '@/Components/ui/FilterBar.vue';

const props = defineProps({
    materials: Object,     // paginated LearningMaterial[]
    onlineClasses: Object, // paginated OnlineClass[]
    filters: Object,
    subjects: Array,
});

// ── Tabs ─────────────────────────────────────────────────
const activeTab = ref('materials');

// ── Filters ───────────────────────────────────────────────
const filterForm = ref({
    subject_id: props.filters?.subject_id || '',
    type:       props.filters?.type       || '',
});

const applyFilters = () => {
    router.get(route('school.academic.resources.student'), filterForm.value, { preserveState: true });
};

// ── Inline video player ────────────────────────────────────
const videoModal = ref(null);

const openVideo = (material) => {
    videoModal.value = material;
};

// YouTube embed URL helper
const youtubeEmbed = (url) => {
    const match = url.match(/(?:v=|youtu\.be\/)([^&?]+)/);
    return match ? `https://www.youtube.com/embed/${match[1]}?autoplay=1` : null;
};

const vimeoEmbed = (url) => {
    const match = url.match(/vimeo\.com\/(\d+)/);
    return match ? `https://player.vimeo.com/video/${match[1]}?autoplay=1` : null;
};

const getEmbedUrl = (material) => {
    if (!material.external_url) return null;
    if (material.embed_type === 'youtube') return youtubeEmbed(material.external_url);
    if (material.embed_type === 'vimeo')   return vimeoEmbed(material.external_url);
    return null;
};

// ── Material meta ──────────────────────────────────────────
const typeMeta = (type) => ({
    pdf:   { bg: 'bg-red-100',    color: 'text-red-600',    label: 'PDF', icon: '📄' },
    ppt:   { bg: 'bg-orange-100', color: 'text-orange-600', label: 'PPT', icon: '📊' },
    video: { bg: 'bg-purple-100', color: 'text-purple-600', label: 'Video', icon: '🎬' },
    image: { bg: 'bg-green-100',  color: 'text-green-600',  label: 'Image', icon: '🖼️' },
    doc:   { bg: 'bg-blue-100',   color: 'text-blue-600',   label: 'Doc', icon: '📝' },
    link:  { bg: 'bg-slate-100',  color: 'text-slate-600',  label: 'Link', icon: '🔗' },
}[type] ?? { bg: 'bg-slate-100', color: 'text-slate-600', label: type, icon: '📎' });

// Group materials by chapter_name
const groupedMaterials = computed(() => {
    const map = {};
    (props.materials?.data ?? []).forEach(m => {
        const ch = m.chapter_name || 'General';
        if (!map[ch]) map[ch] = [];
        map[ch].push(m);
    });
    return map;
});

// ── Online class helpers ───────────────────────────────────
const now = ref(Date.now());
const classStatus = (c) => {
    const start = new Date(c.start_time).getTime();
    const end   = c.end_time ? new Date(c.end_time).getTime() : start + 3600000;
    if (now.value >= start && now.value <= end) return 'live';
    if (start - now.value <= 15 * 60 * 1000 && start > now.value) return 'upcoming';
    if (start > now.value) return 'scheduled';
    return 'past';
};

const school = useSchoolStore();

const formatDT = (d) => school.fmtDateTime(d);

const platformIcon = (p) => {
    if (!p) return '🎥';
    const name = p.toLowerCase();
    if (name.includes('meet'))  return '🟢';
    if (name.includes('zoom'))  return '🔵';
    if (name.includes('teams')) return '🟣';
    return '🎥';
};

// Serve attachments through the Laravel /api/media proxy so we don't depend
// on the /storage symlink being readable by nginx.
const getFileUrl = (p) => {
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    const clean = String(p).replace(/^\/+/, '').replace(/^(?:storage|public)\//i, '');
    return `/api/media?p=${encodeURIComponent(clean)}`;
};

// ── File viewer modal ────────────────────────────────────
const viewingMaterial = ref(null);

const openViewer = (material) => {
    // For videos with embeddable URLs, use the video modal
    if (material.type === 'video' && material.external_url && getEmbedUrl(material)) {
        openVideo(material);
        return;
    }
    viewingMaterial.value = material;
};

const closeViewer = () => { viewingMaterial.value = null; };

const fileUrl = (m) => {
    if (m.external_url) return m.external_url;
    if (!m.file_path) return null;
    const clean = String(m.file_path).replace(/^\/+/, '').replace(/^(?:storage|public)\//i, '');
    return `/api/media?p=${encodeURIComponent(clean)}`;
};

const typeMetaViewer = (type) => ({
    pdf:   { color: '#dc2626', bg: '#fef2f2', label: 'PDF' },
    ppt:   { color: '#ea580c', bg: '#fff7ed', label: 'PPT' },
    video: { color: '#7c3aed', bg: '#f5f3ff', label: 'Video' },
    image: { color: '#0284c7', bg: '#f0f9ff', label: 'Image' },
    doc:   { color: '#059669', bg: '#f0fdf4', label: 'Doc' },
    link:  { color: '#64748b', bg: '#f8fafc', label: 'Link' },
}[type] ?? { color: '#64748b', bg: '#f8fafc', label: type });
</script>

<template>
    <SchoolLayout title="Study Materials">
        <PageHeader title="Study Materials" subtitle="Access learning materials and join online classes" />

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 border-b border-slate-200">
            <button @click="activeTab = 'materials'"
                    :class="['px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors',
                             activeTab === 'materials' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                📚 Materials
                <span v-if="materials?.total" class="ml-1 text-xs bg-slate-100 text-slate-600 rounded-full px-2 py-0.5">{{ materials.total }}</span>
            </button>
            <button @click="activeTab = 'classes'"
                    :class="['px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors',
                             activeTab === 'classes' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']">
                🎥 Online Classes
                <span v-if="onlineClasses?.total" class="ml-1 text-xs bg-slate-100 text-slate-600 rounded-full px-2 py-0.5">{{ onlineClasses.total }}</span>
            </button>
        </div>

        <!-- ── Materials Tab ── -->
        <div v-show="activeTab === 'materials'">
            <!-- Filters -->
            <FilterBar :active="!!(filterForm.subject_id || filterForm.type)" @clear="filterForm.subject_id = ''; filterForm.type = ''; applyFilters()">
                <div class="form-field">
                    <label>Subject</label>
                    <select v-model="filterForm.subject_id" @change="applyFilters" style="width:180px;">
                        <option value="">All Subjects</option>
                        <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Type</label>
                    <select v-model="filterForm.type" @change="applyFilters" style="width:140px;">
                        <option value="">All Types</option>
                        <option value="pdf">PDF</option>
                        <option value="ppt">PPT</option>
                        <option value="video">Video</option>
                        <option value="image">Image</option>
                        <option value="doc">Document</option>
                        <option value="link">Link</option>
                    </select>
                </div>
            </FilterBar>

            <!-- Grouped by Chapter -->
            <div v-if="Object.keys(groupedMaterials).length > 0" class="space-y-6">
                <div v-for="(mats, chapter) in groupedMaterials" :key="chapter">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        {{ chapter }}
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="m in mats" :key="m.id"
                             class="card hover:shadow-md transition-shadow cursor-pointer"
                             @click="openViewer(m)">
                            <div class="card-body flex items-start gap-4">
                                <div :class="['w-12 h-12 rounded-xl flex items-center justify-center text-2xl shrink-0', typeMeta(m.type).bg]">
                                    {{ typeMeta(m.type).icon }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-slate-800 text-sm truncate">{{ m.title }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5">{{ m.subject?.name }}</div>
                                    <div v-if="m.description" class="text-xs text-slate-400 mt-1 line-clamp-2">{{ m.description }}</div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span :class="['badge text-[10px] px-1.5', typeMeta(m.type).bg, typeMeta(m.type).color]">
                                            {{ typeMeta(m.type).label }}
                                        </span>
                                        <span v-if="m.downloads_count" class="text-[10px] text-slate-400">
                                            {{ m.downloads_count }} views
                                        </span>
                                    </div>
                                </div>
                                <div class="text-slate-300 shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="card py-16 text-center">
                <p class="text-slate-500">No materials available yet.</p>
            </div>
        </div>

        <!-- ── Online Classes Tab ── -->
        <div v-show="activeTab === 'classes'">
            <div v-if="onlineClasses?.data?.length > 0" class="space-y-4">
                <div v-for="cls in onlineClasses.data" :key="cls.id"
                     class="card"
                     :class="classStatus(cls) === 'live' ? 'border-l-4 border-emerald-500' : ''">
                    <div class="card-body">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">{{ platformIcon(cls.platform) }}</span>
                                    <span class="font-bold text-slate-800">{{ cls.subject?.name }}</span>
                                    <span v-if="classStatus(cls) === 'live'"
                                          class="badge bg-emerald-500 text-white animate-pulse text-[10px]">LIVE</span>
                                    <span v-else-if="classStatus(cls) === 'upcoming'"
                                          class="badge badge-amber text-[10px]">Starting soon</span>
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ formatDT(cls.start_time) }}
                                    <span v-if="cls.end_time"> — {{ formatDT(cls.end_time) }}</span>
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">by {{ cls.teacher?.user?.name ?? 'Teacher' }}</div>
                            </div>
                            <div class="flex gap-2 flex-wrap">
                                <Button size="sm" as="a" v-if="classStatus(cls) !== 'past'" :href="cls.meeting_link" target="_blank">
                                    {{ classStatus(cls) === 'live' ? '🔴 Join Now' : '🔗 Join Class' }}
                                </Button>
                                <Button variant="secondary" size="sm" as="a" v-if="cls.recording_link" :href="cls.recording_link" target="_blank" class="text-purple-600 border-purple-200">
                                    📹 Recording
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="card py-16 text-center">
                <p class="text-slate-500">No online classes scheduled.</p>
            </div>
        </div>

        <!-- ── Video Modal ── -->
        <div v-if="videoModal"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm"
             @mousedown.self="videoModal = null">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="font-bold text-slate-800">{{ videoModal.title }}</h3>
                    <button @click="videoModal = null" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Embedded YouTube/Vimeo -->
                <div v-if="getEmbedUrl(videoModal)" class="aspect-video bg-black">
                    <iframe :src="getEmbedUrl(videoModal)"
                            class="w-full h-full" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen />
                </div>

                <!-- Uploaded video file -->
                <div v-else-if="videoModal.file_path" class="aspect-video bg-black">
                    <video :src="getFileUrl(videoModal.file_path)"
                           class="w-full h-full" controls autoplay />
                </div>

                <!-- Fallback: external link -->
                <div v-else class="p-6 text-center">
                    <Button as="a" :href="videoModal.external_url" target="_blank">
                        Open External Link
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── File Viewer Modal ── -->
        <div v-if="viewingMaterial"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm"
             @mousedown.self="closeViewer">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col" style="height: 90vh;">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider"
                              :style="`background:${typeMetaViewer(viewingMaterial.type).bg};color:${typeMetaViewer(viewingMaterial.type).color}`">
                            {{ typeMetaViewer(viewingMaterial.type).label }}
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-800 truncate">{{ viewingMaterial.title }}</h3>
                            <p class="text-xs text-slate-500">
                                {{ viewingMaterial.subject?.name }}
                                <span v-if="viewingMaterial.chapter_name"> · {{ viewingMaterial.chapter_name }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <Button size="sm" as="a" v-if="fileUrl(viewingMaterial)" :href="fileUrl(viewingMaterial)" download>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download
                        </Button>
                        <button @click="closeViewer" class="text-slate-400 hover:text-slate-600 p-1">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-auto bg-slate-50 flex items-center justify-center">
                    <!-- PDF -->
                    <iframe v-if="viewingMaterial.type === 'pdf' && !viewingMaterial.external_url"
                        :src="fileUrl(viewingMaterial)"
                        class="w-full h-full border-none" />

                    <!-- Image -->
                    <img v-else-if="viewingMaterial.type === 'image' && !viewingMaterial.external_url"
                        :src="fileUrl(viewingMaterial)"
                        :alt="viewingMaterial.title"
                        class="max-w-full max-h-full object-contain p-4" />

                    <!-- Video (uploaded file) -->
                    <video v-else-if="viewingMaterial.type === 'video' && !viewingMaterial.external_url"
                        :src="fileUrl(viewingMaterial)"
                        controls
                        class="max-w-full max-h-full" />

                    <!-- Non-previewable / external URL fallback -->
                    <div v-else class="flex flex-col items-center justify-center gap-4 p-10 text-center">
                        <div class="opacity-50">
                            <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                 :style="`color:${typeMetaViewer(viewingMaterial.type).color}`">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 2v6h6"/>
                            </svg>
                        </div>
                        <p class="text-lg font-bold text-slate-700">{{ viewingMaterial.title }}</p>
                        <p class="text-sm text-slate-500">This file type cannot be previewed in the browser.</p>
                        <div class="flex gap-3 mt-2">
                            <Button as="a" v-if="fileUrl(viewingMaterial)" :href="fileUrl(viewingMaterial)" download>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download File
                            </Button>
                            <Button variant="secondary" as="a" v-if="viewingMaterial.external_url" :href="viewingMaterial.external_url" target="_blank">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Open Link
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
