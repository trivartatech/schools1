<script setup>
import Button from '@/Components/ui/Button.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const props = defineProps({
    onlineClasses:     Object,
    learningMaterials: Object,
    courseClasses:     Array,
    filters:           Object,
});

const { can } = usePermissions();

// ── Active Tab ────────────────────────────────────────────
const activeTab = ref('classes'); // 'classes' | 'materials'

// ── Modals ────────────────────────────────────────────────
const showClassModal    = ref(false);
const showMaterialModal = ref(false);
const recordingTarget   = ref(null);

// ── Filter bar ────────────────────────────────────────────
const filterForm = ref({
    class_id:   props.filters?.class_id   || '',
    subject_id: props.filters?.subject_id || '',
    type:       props.filters?.type       || '',
});

const materialSearch = ref('');

const applyFilters = () => {
    router.get(route('school.academic.resources.index'), filterForm.value, { preserveState: true, replace: true });
};

const clearFilter = (key) => {
    filterForm.value[key] = '';
    applyFilters();
};

const clearAllFilters = () => {
    filterForm.value = { class_id: '', subject_id: '', type: '' };
    applyFilters();
};

const activeFilters = computed(() => {
    const f = [];
    if (filterForm.value.class_id) {
        const c = props.courseClasses.find(c => c.id == filterForm.value.class_id);
        if (c) f.push({ key: 'class_id', label: c.name });
    }
    if (filterForm.value.subject_id) {
        const s = allSubjects.value.find(s => s.id == filterForm.value.subject_id);
        if (s) f.push({ key: 'subject_id', label: s.name });
    }
    if (filterForm.value.type) f.push({ key: 'type', label: typeLabel(filterForm.value.type) });
    return f;
});

// ── Forms ─────────────────────────────────────────────────
const classForm = useForm({
    class_id:       '',
    section_ids:    [],
    subject_id:     '',
    start_time:     '',
    end_time:       '',
    meeting_link:   '',
    platform:       'Google Meet',
    recording_link: '',
});

const materialForm = useForm({
    class_id:     '',
    section_ids:  [],
    subject_id:   '',
    title:        '',
    type:         'pdf',
    file:         null,
    chapter_name: '',
});

const recordingForm = useForm({ recording_link: '' });

// ── Drag-and-drop state ───────────────────────────────────
const isDragging      = ref(false);
const selectedFileName = ref('');

const onDrop = (e) => {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) { materialForm.file = file; selectedFileName.value = file.name; }
};
const onFileInput = (e) => {
    const file = e.target.files?.[0];
    if (file) { materialForm.file = file; selectedFileName.value = file.name; }
};

// ── Class/section/subject helpers ─────────────────────────
const classForForm = (form) => props.courseClasses.find(c => c.id === parseInt(form.class_id));

const sectionsFor = (form) => classForForm(form)?.sections ?? [];

const subjectsFor = (form) => {
    const cls = classForForm(form);
    if (!cls) return [];
    const map = new Map();
    (cls.subjects ?? []).forEach(s => map.set(s.id, s));
    sectionsFor(form)
        .filter(s => form.section_ids.includes(s.id))
        .forEach(sec => (sec.subjects ?? []).forEach(s => map.set(s.id, s)));
    return [...map.values()];
};

const sectionsForOnline   = computed(() => sectionsFor(classForm));
const subjectsForOnline   = computed(() => subjectsFor(classForm));
const sectionsForMaterial = computed(() => sectionsFor(materialForm));
const subjectsForMaterial = computed(() => subjectsFor(materialForm));

const allSubjects = computed(() => {
    const map = new Map();
    props.courseClasses.forEach(c => (c.subjects ?? []).forEach(s => map.set(s.id, s)));
    return [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
});

// ── File viewer modal ────────────────────────────────────
const viewingMaterial = ref(null);

const openViewer = (m) => { viewingMaterial.value = m; };
const closeViewer = () => { viewingMaterial.value = null; };

const fileUrl = (m) => {
    if (m.external_url) return m.external_url;
    if (!m.file_path) return null;
    // Route through /api/media?p= proxy — nginx can 403 on /storage/*
    // because of its image-extension location block.
    const clean = String(m.file_path).replace(/^\/+/, '').replace(/^(?:storage|public)\//i, '');
    return `/api/media?p=${encodeURIComponent(clean)}`;
};

const canPreviewInline = (m) => {
    return ['pdf', 'image', 'video'].includes(m.type) && !m.external_url;
};

// ── Copy link helper ──────────────────────────────────────
const copiedId = ref(null);
const copyLink = async (id, url) => {
    try { await navigator.clipboard.writeText(url); copiedId.value = id; setTimeout(() => copiedId.value = null, 2000); } catch {}
};

// ── Actions ───────────────────────────────────────────────
const storeClass = () => {
    classForm.post(route('school.academic.resources.store-online-class'), {
        forceFormData: true,
        onSuccess: () => { showClassModal.value = false; classForm.reset(); },
    });
};

const storeMaterial = () => {
    materialForm.post(route('school.academic.resources.store-material'), {
        forceFormData: true,
        onSuccess: () => { showMaterialModal.value = false; materialForm.reset(); selectedFileName.value = ''; },
    });
};

const deleteMaterial = (id) => {
    if (confirm('Delete this material?')) useForm({}).delete(route('school.academic.resources.destroy-material', id));
};
const deleteClass = (id) => {
    if (confirm('Cancel this online class?')) useForm({}).delete(route('school.academic.resources.destroy-online-class', id));
};

const openRecordingModal = (cls) => {
    recordingTarget.value = cls;
    recordingForm.recording_link = cls.recording_link || '';
};
const saveRecording = () => {
    recordingForm.post(route('school.academic.resources.add-recording', recordingTarget.value.id), {
        onSuccess: () => { recordingTarget.value = null; recordingForm.reset(); },
    });
};

// ── Real-time clock for countdown ─────────────────────────
const now = ref(Date.now());
let ticker = null;
onMounted(() => { ticker = setInterval(() => { now.value = Date.now(); }, 30000); });
onUnmounted(() => clearInterval(ticker));

// ── Class status helpers ──────────────────────────────────
const classStatus = (c) => {
    const start = new Date(c.start_time).getTime();
    const end   = c.end_time ? new Date(c.end_time).getTime() : start + 60 * 60 * 1000;
    if (now.value >= start && now.value < end) return 'live';
    if (start > now.value) return 'upcoming';
    return 'past';
};

const timeUntil = (dateStr) => {
    const diff = new Date(dateStr).getTime() - now.value;
    if (diff <= 0) return null;
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    if (h >= 24) { const d = Math.floor(h / 24); return `in ${d}d ${h % 24}h`; }
    return h > 0 ? `in ${h}h ${m}m` : `in ${m}m`;
};

// Group classes: live first, then upcoming, then past
const groupedClasses = computed(() => {
    const live = [], upcoming = [], past = [];
    (props.onlineClasses.data ?? []).forEach(c => {
        const s = classStatus(c);
        if (s === 'live') live.push(c);
        else if (s === 'upcoming') upcoming.push(c);
        else past.push(c);
    });
    return { live, upcoming, past };
});

// ── Material helpers ──────────────────────────────────────
const filteredMaterials = computed(() => {
    const q = materialSearch.value.toLowerCase().trim();
    if (!q) return props.learningMaterials.data;
    return (props.learningMaterials.data ?? []).filter(m =>
        m.title?.toLowerCase().includes(q) ||
        m.subject?.name?.toLowerCase().includes(q) ||
        m.chapter_name?.toLowerCase().includes(q)
    );
});

// ── Display helpers ───────────────────────────────────────
const school = useSchoolStore();

const formatDate     = (d) => school.fmtDateTime(d);
const formatDateFull = (d) => school.fmtDateTime(d);

const typeLabel = (t) => ({ pdf:'PDF', ppt:'PowerPoint', video:'Video', image:'Image', doc:'Document' })[t] ?? t;

const typeMeta = (type) => ({
    pdf:   { color: '#dc2626', bg: '#fef2f2', label: 'PDF' },
    ppt:   { color: '#ea580c', bg: '#fff7ed', label: 'PPT' },
    video: { color: '#7c3aed', bg: '#f5f3ff', label: 'Video' },
    image: { color: '#0284c7', bg: '#f0f9ff', label: 'Image' },
    doc:   { color: '#059669', bg: '#f0fdf4', label: 'Doc' },
}[type] ?? { color: '#64748b', bg: '#f8fafc', label: type });

const platformMeta = (p) => ({
    'Google Meet':      { color: '#1a73e8', icon: 'M' },
    'Zoom':             { color: '#2D8CFF', icon: 'Z' },
    'Microsoft Teams':  { color: '#5b5ea6', icon: 'T' },
    'Jitsi':            { color: '#1d4ed8', icon: 'J' },
}[p] ?? { color: '#64748b', icon: p?.[0] ?? '?' });
</script>

<template>
    <SchoolLayout title="Digital Resources">

        <!-- ── Page Header ─────────────────────────────────── -->
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Digital Resources</h2>
                <p class="page-header-sub">Online classes &amp; study materials in one place</p>
            </div>
            <div v-if="can('create_academic')" class="flex gap-2">
                <Button variant="secondary" @click="showClassModal = true">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                    Schedule Class
                </Button>
                <Button as="link" :href="route('school.academic.resources.create-material')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Material
                </Button>
            </div>
        </div>

        <!-- ── Stats row ───────────────────────────────────── -->
        <div class="stats-row">
            <div class="stat-pill">
                <span class="stat-dot live-dot"></span>
                <strong>{{ groupedClasses.live.length }}</strong> Live Now
            </div>
            <div class="stat-pill">
                <span class="stat-dot upcoming-dot"></span>
                <strong>{{ groupedClasses.upcoming.length }}</strong> Upcoming
            </div>
            <div class="stat-pill">
                <span class="stat-dot past-dot"></span>
                <strong>{{ groupedClasses.past.length }}</strong> Past Classes
            </div>
            <div class="stat-pill" style="margin-left:auto;">
                <strong>{{ learningMaterials.total ?? learningMaterials.data?.length ?? 0 }}</strong> Materials
            </div>
        </div>

        <!-- ── Filter bar ──────────────────────────────────── -->
        <FilterBar :active="activeFilters.length > 0" @clear="clearAllFilters">
            <select v-model="filterForm.class_id" @change="applyFilters" style="width:150px;">
                <option value="">All Classes</option>
                <option v-for="c in courseClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filterForm.subject_id" @change="applyFilters" style="width:160px;">
                <option value="">All Subjects</option>
                <option v-for="s in allSubjects" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="filterForm.type" @change="applyFilters" style="width:130px;">
                <option value="">All Types</option>
                <option value="pdf">PDF</option>
                <option value="ppt">PowerPoint</option>
                <option value="video">Video</option>
                <option value="image">Image</option>
                <option value="doc">Document</option>
            </select>
            <div class="active-filters">
                <span v-for="f in activeFilters" :key="f.key" class="filter-pill">
                    {{ f.label }}
                    <button @click="clearFilter(f.key)" class="pill-x">×</button>
                </span>
            </div>
        </FilterBar>

        <!-- ── Tabs ────────────────────────────────────────── -->
        <div class="tab-bar">
            <Button variant="tab" :active="activeTab === 'classes'" @click="activeTab = 'classes'">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                Online Classes
                <span class="tab-count">{{ onlineClasses.total ?? onlineClasses.data?.length ?? 0 }}</span>
            </Button>
            <Button variant="tab" :active="activeTab === 'materials'" @click="activeTab = 'materials'">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                Learning Materials
                <span class="tab-count">{{ learningMaterials.total ?? learningMaterials.data?.length ?? 0 }}</span>
            </Button>
        </div>

        <!-- ══════════════════════════════════════════════════
             TAB 1 — ONLINE CLASSES
        ══════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'classes'" class="tab-content">

            <!-- LIVE NOW -->
            <div v-if="groupedClasses.live.length > 0" class="class-section">
                <div class="class-section-header live-header">
                    <span class="live-indicator"></span> Live Right Now
                </div>
                <div class="class-grid">
                    <div v-for="c in groupedClasses.live" :key="c.id" class="class-card live-card">
                        <ClassCard :c="c" :can-edit="can('edit_academic')" :can-delete="can('delete_academic')"
                            :copied-id="copiedId" :status="'live'"
                            @copy="copyLink(c.id, c.meeting_link)"
                            @recording="openRecordingModal(c)"
                            @delete="deleteClass(c.id)" />
                    </div>
                </div>
            </div>

            <!-- UPCOMING -->
            <div v-if="groupedClasses.upcoming.length > 0" class="class-section">
                <div class="class-section-header upcoming-header">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Upcoming ({{ groupedClasses.upcoming.length }})
                </div>
                <div class="class-grid">
                    <div v-for="c in groupedClasses.upcoming" :key="c.id" class="class-card upcoming-card">
                        <div class="cc-header">
                            <div class="cc-platform" :style="`background:${platformMeta(c.platform).color}20;color:${platformMeta(c.platform).color}`">
                                {{ platformMeta(c.platform).icon }}
                            </div>
                            <div class="cc-meta">
                                <div class="cc-class">{{ c.course_class?.name }} <span v-if="c.section">· {{ c.section?.name }}</span></div>
                                <div class="cc-subject">{{ c.subject?.name }}</div>
                            </div>
                            <div class="cc-countdown">{{ timeUntil(c.start_time) }}</div>
                            <button v-if="can('delete_academic')" @click="deleteClass(c.id)" class="cc-del" title="Cancel class">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="cc-time">
                            <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ formatDateFull(c.start_time) }}
                            <span v-if="c.end_time" class="cc-end">→ {{ formatDate(c.end_time) }}</span>
                        </div>
                        <div class="cc-actions">
                            <Button size="sm" as="a" :href="c.meeting_link" target="_blank">Join Class</Button>
                            <Button variant="secondary" size="sm" @click="copyLink(c.id, c.meeting_link)" class="copy-btn">
                                <svg v-if="copiedId !== c.id" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <svg v-else class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ copiedId === c.id ? 'Copied!' : 'Copy Link' }}
                            </Button>
                            <Button variant="secondary" size="sm" v-if="can('edit_academic') && !c.recording_link" @click="openRecordingModal(c)">+ Recording</Button>
                            <Button variant="secondary" size="sm" as="a" v-if="c.recording_link" :href="c.recording_link" target="_blank">🎬 Recording</Button>
                        </div>
                        <div class="cc-teacher">By {{ c.teacher?.user?.name ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <!-- PAST -->
            <div v-if="groupedClasses.past.length > 0" class="class-section">
                <div class="class-section-header past-header">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Past Classes ({{ groupedClasses.past.length }})
                </div>
                <div class="overflow-x-auto card">
                    <Table>
                        <thead>
                            <tr>
                                <th>Class / Subject</th>
                                <th>Platform</th>
                                <th>Date</th>
                                <th>Teacher</th>
                                <th>Recording</th>
                                <th v-if="can('edit_academic') || can('delete_academic')"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in groupedClasses.past" :key="c.id" class="past-row">
                                <td>
                                    <div class="font-semibold text-slate-700">{{ c.course_class?.name }}</div>
                                    <div class="text-xs text-slate-400">{{ c.subject?.name }}</div>
                                </td>
                                <td>
                                    <span class="platform-chip" :style="`color:${platformMeta(c.platform).color};background:${platformMeta(c.platform).color}15`">
                                        {{ c.platform }}
                                    </span>
                                </td>
                                <td class="text-xs text-slate-500">{{ formatDateFull(c.start_time) }}</td>
                                <td class="text-xs text-slate-500">{{ c.teacher?.user?.name ?? '—' }}</td>
                                <td>
                                    <a v-if="c.recording_link" :href="c.recording_link" target="_blank" class="text-indigo-600 text-xs font-bold hover:underline">🎬 Watch</a>
                                    <Button variant="secondary" size="xs" v-else-if="can('edit_academic')" @click="openRecordingModal(c)">+ Add</Button>
                                    <span v-else class="text-slate-300 text-xs">—</span>
                                </td>
                                <td v-if="can('edit_academic') || can('delete_academic')">
                                    <Button variant="danger" size="xs" v-if="can('delete_academic')" @click="deleteClass(c.id)">Delete</Button>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="onlineClasses.data?.length === 0" class="empty-state">
                <div class="empty-icon">
                    <svg class="w-12 h-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                </div>
                <p class="empty-title">No classes scheduled</p>
                <p class="empty-sub">Use "Schedule Class" to add an online class for a section.</p>
            </div>

            <!-- Pagination -->
            <div v-if="onlineClasses.last_page > 1" class="pagination">
                <Button v-for="link in onlineClasses.links" :key="link.label"
                   variant="tab" size="sm" :active="link.active"
                   :disabled="!link.url"
                   @click="link.url && router.get(link.url, {}, { preserveState: true })"
                   v-html="link.label" />
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════
             TAB 2 — LEARNING MATERIALS
        ══════════════════════════════════════════════════ -->
        <div v-show="activeTab === 'materials'" class="tab-content">

            <!-- Search within materials -->
            <div class="material-search-bar">
                <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="materialSearch" type="text" placeholder="Search by title, subject or chapter…" class="search-input" />
                <button v-if="materialSearch" @click="materialSearch = ''" class="search-clear">×</button>
            </div>

            <!-- Material grid -->
            <div v-if="filteredMaterials.length > 0" class="material-grid">
                <div v-for="m in filteredMaterials" :key="m.id" class="material-card">
                    <!-- Type badge -->
                    <div class="mat-type-badge"
                         :style="`background:${typeMeta(m.type).bg};color:${typeMeta(m.type).color};`">
                        <span class="mat-type-icon">
                            <svg v-if="m.type==='pdf'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8 17h8v1H8v-1zm0-3h8v1H8v-1zm0-3h5v1H8v-1z"/></svg>
                            <svg v-else-if="m.type==='video'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4V6.5l-4 4z"/></svg>
                            <svg v-else-if="m.type==='image'" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2zm-8.5-5.5l-2.5 3.01L7 14l-3 4h16l-5-6.5-2.5 3z"/></svg>
                            <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8 14h8v1H8v-1zm0-3h8v1H8v-1zm0 6h5v1H8v-1z"/></svg>
                        </span>
                        <span class="mat-type-label">{{ typeMeta(m.type).label }}</span>
                    </div>

                    <!-- Title + meta -->
                    <div class="mat-title">{{ m.title }}</div>
                    <div class="mat-meta">
                        <span>{{ m.course_class?.name }}</span>
                        <span v-if="m.section" class="meta-sep">·</span>
                        <span v-if="m.section">{{ m.section?.name }}</span>
                        <span class="meta-sep">·</span>
                        <span>{{ m.subject?.name }}</span>
                    </div>
                    <div v-if="m.chapter_name" class="mat-chapter">📖 {{ m.chapter_name }}</div>

                    <!-- Actions -->
                    <div class="mat-footer">
                        <span class="mat-teacher">By {{ m.teacher?.user?.name ?? '—' }}</span>
                        <div class="mat-actions">
                            <Button variant="secondary" size="xs" @click="openViewer(m)">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                View
                            </Button>
                            <Button variant="danger" size="xs" v-if="can('delete_academic')" @click="deleteMaterial(m.id)">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="empty-state">
                <div class="empty-icon">
                    <svg class="w-12 h-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
                </div>
                <p class="empty-title">{{ materialSearch ? 'No results found' : 'No materials uploaded yet' }}</p>
                <p class="empty-sub">{{ materialSearch ? 'Try a different search term.' : 'Upload notes, slides and videos for your students.' }}</p>
            </div>

            <!-- Pagination -->
            <div v-if="learningMaterials.last_page > 1" class="pagination">
                <Button v-for="link in learningMaterials.links" :key="link.label"
                   variant="tab" size="sm" :active="link.active"
                   :disabled="!link.url"
                   @click="link.url && router.get(link.url, {}, { preserveState: true })"
                   v-html="link.label" />
            </div>
        </div>


        <!-- ══════════════════════════════════════════════════
             MODALS
        ══════════════════════════════════════════════════ -->

        <!-- Schedule Class Modal -->
        <div v-if="showClassModal" class="modal-backdrop" @mousedown.self="showClassModal = false">
            <div class="modal-box">
                <div class="modal-hdr">
                    <div>
                        <h3 class="modal-title">Schedule Online Class</h3>
                        <p class="modal-sub">Set up a virtual session for your students</p>
                    </div>
                    <button @click="showClassModal = false" class="modal-x">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="storeClass" class="modal-body space-y-4">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Class <span class="req">*</span></label>
                            <select v-model="classForm.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in courseClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Platform</label>
                            <select v-model="classForm.platform">
                                <option>Google Meet</option>
                                <option>Zoom</option>
                                <option>Microsoft Teams</option>
                                <option>Jitsi</option>
                                <option>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Sections</label>
                        <div class="checkbox-group" :class="{'disabled': !classForm.class_id}">
                            <label v-for="s in sectionsForOnline" :key="s.id" class="checkbox-row">
                                <input type="checkbox" :value="s.id" v-model="classForm.section_ids" :disabled="!classForm.class_id" />
                                <span>{{ s.name }}</span>
                            </label>
                            <span v-if="!classForm.class_id" class="hint">Select a class first.</span>
                            <span v-else-if="sectionsForOnline.length === 0" class="hint">No sections.</span>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Subject <span class="req">*</span></label>
                        <select v-model="classForm.subject_id" required :disabled="!classForm.class_id">
                            <option value="">Select Subject</option>
                            <option v-for="s in subjectsForOnline" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="classForm.errors.subject_id" class="form-error">{{ classForm.errors.subject_id }}</p>
                    </div>
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Start Time <span class="req">*</span></label>
                            <input type="datetime-local" v-model="classForm.start_time" required />
                            <p v-if="classForm.errors.start_time" class="form-error">{{ classForm.errors.start_time }}</p>
                        </div>
                        <div class="form-field">
                            <label>End Time</label>
                            <input type="datetime-local" v-model="classForm.end_time" />
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Meeting Link <span class="req">*</span></label>
                        <input type="url" v-model="classForm.meeting_link" placeholder="https://meet.google.com/..." required />
                        <p v-if="classForm.errors.meeting_link" class="form-error">{{ classForm.errors.meeting_link }}</p>
                    </div>
                    <div class="form-field">
                        <label>Recording Link <span class="text-slate-400 font-normal">(optional — can add later)</span></label>
                        <input type="url" v-model="classForm.recording_link" placeholder="https://..." />
                    </div>
                    <div class="modal-footer">
                        <Button variant="secondary" type="button" @click="showClassModal = false">Cancel</Button>
                        <Button type="submit" :loading="classForm.processing">
                            Schedule Class
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Upload Material Modal -->
        <div v-if="showMaterialModal" class="modal-backdrop" @mousedown.self="showMaterialModal = false">
            <div class="modal-box">
                <div class="modal-hdr">
                    <div>
                        <h3 class="modal-title">Upload Learning Material</h3>
                        <p class="modal-sub">Share notes, slides, videos and more</p>
                    </div>
                    <button @click="showMaterialModal = false" class="modal-x">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="storeMaterial" class="modal-body space-y-4">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Class <span class="req">*</span></label>
                            <select v-model="materialForm.class_id" required>
                                <option value="">Select Class</option>
                                <option v-for="c in courseClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Type <span class="req">*</span></label>
                            <select v-model="materialForm.type" required>
                                <option value="pdf">PDF</option>
                                <option value="ppt">PowerPoint</option>
                                <option value="video">Video</option>
                                <option value="image">Image</option>
                                <option value="doc">Document</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Sections</label>
                        <div class="checkbox-group" :class="{'disabled': !materialForm.class_id}">
                            <label v-for="s in sectionsForMaterial" :key="s.id" class="checkbox-row">
                                <input type="checkbox" :value="s.id" v-model="materialForm.section_ids" :disabled="!materialForm.class_id" />
                                <span>{{ s.name }}</span>
                            </label>
                            <span v-if="!materialForm.class_id" class="hint">Select a class first.</span>
                            <span v-else-if="sectionsForMaterial.length === 0" class="hint">No sections.</span>
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Subject <span class="req">*</span></label>
                        <select v-model="materialForm.subject_id" required :disabled="!materialForm.class_id">
                            <option value="">Select Subject</option>
                            <option v-for="s in subjectsForMaterial" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Title <span class="req">*</span></label>
                            <input type="text" v-model="materialForm.title" placeholder="e.g. Chapter 1 Notes" required />
                            <p v-if="materialForm.errors.title" class="form-error">{{ materialForm.errors.title }}</p>
                        </div>
                        <div class="form-field">
                            <label>Chapter <span class="text-slate-400 font-normal">(optional)</span></label>
                            <input type="text" v-model="materialForm.chapter_name" placeholder="e.g. Chapter 3" />
                        </div>
                    </div>

                    <!-- Drag & Drop Upload Zone -->
                    <div class="form-field">
                        <label>File <span class="req">*</span></label>
                        <div class="drop-zone"
                             :class="{ 'drop-active': isDragging, 'drop-filled': !!selectedFileName }"
                             @dragover.prevent="isDragging = true"
                             @dragleave.prevent="isDragging = false"
                             @drop.prevent="onDrop">
                            <input type="file" class="drop-input" @change="onFileInput"
                                   accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.mov,.avi,.jpg,.jpeg,.png,.gif" />
                            <div v-if="!selectedFileName" class="drop-placeholder">
                                <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                <span class="text-sm font-medium text-slate-500">Drop file here or <span class="text-indigo-600">browse</span></span>
                                <span class="text-xs text-slate-400 mt-1">PDF, PPT, DOCX, MP4, JPG, PNG · max 20 MB</span>
                            </div>
                            <div v-else class="drop-filled-info">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm font-medium text-slate-700 truncate">{{ selectedFileName }}</span>
                                <button type="button" @click="materialForm.file = null; selectedFileName = ''" class="drop-clear">×</button>
                            </div>
                        </div>
                        <p v-if="materialForm.errors.file" class="form-error">{{ materialForm.errors.file }}</p>
                    </div>

                    <div class="modal-footer">
                        <Button variant="secondary" type="button" @click="showMaterialModal = false">Cancel</Button>
                        <Button type="submit" :loading="materialForm.processing" :disabled="!materialForm.file">
                            <svg v-if="materialForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                            {{ materialForm.processing ? 'Uploading…' : 'Upload Material' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recording Link Modal -->
        <div v-if="recordingTarget" class="modal-backdrop" @mousedown.self="recordingTarget = null">
            <div class="modal-box" style="max-width:440px;">
                <div class="modal-hdr">
                    <div>
                        <h3 class="modal-title">Add Recording Link</h3>
                        <p class="modal-sub">{{ recordingTarget.course_class?.name }} · {{ recordingTarget.subject?.name }}</p>
                    </div>
                    <button @click="recordingTarget = null" class="modal-x">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="saveRecording" class="modal-body space-y-4">
                    <div class="form-field">
                        <label>Recording URL <span class="req">*</span></label>
                        <input type="url" v-model="recordingForm.recording_link" placeholder="https://drive.google.com/…" required autofocus />
                        <p v-if="recordingForm.errors.recording_link" class="form-error">{{ recordingForm.errors.recording_link }}</p>
                    </div>
                    <div class="modal-footer">
                        <Button variant="secondary" type="button" @click="recordingTarget = null">Cancel</Button>
                        <Button type="submit" :loading="recordingForm.processing">Save Recording</Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- File Viewer Modal -->
        <div v-if="viewingMaterial" class="modal-backdrop" @mousedown.self="closeViewer">
            <div class="viewer-box">
                <div class="viewer-hdr">
                    <div class="viewer-hdr-info">
                        <span class="mat-type-badge" :style="`background:${typeMeta(viewingMaterial.type).bg};color:${typeMeta(viewingMaterial.type).color};`">
                            <span class="mat-type-label">{{ typeMeta(viewingMaterial.type).label }}</span>
                        </span>
                        <div>
                            <h3 class="modal-title">{{ viewingMaterial.title }}</h3>
                            <p class="modal-sub">
                                {{ viewingMaterial.course_class?.name }}
                                <span v-if="viewingMaterial.section"> · {{ viewingMaterial.section?.name }}</span>
                                · {{ viewingMaterial.subject?.name }}
                                <span v-if="viewingMaterial.chapter_name"> · {{ viewingMaterial.chapter_name }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="viewer-hdr-actions">
                        <Button size="sm" as="a" v-if="fileUrl(viewingMaterial)" :href="fileUrl(viewingMaterial)" download>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download
                        </Button>
                        <button @click="closeViewer" class="modal-x">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="viewer-body">
                    <!-- PDF -->
                    <iframe v-if="viewingMaterial.type === 'pdf' && !viewingMaterial.external_url"
                        :src="fileUrl(viewingMaterial)"
                        class="viewer-iframe" />

                    <!-- Image -->
                    <img v-else-if="viewingMaterial.type === 'image' && !viewingMaterial.external_url"
                        :src="fileUrl(viewingMaterial)"
                        :alt="viewingMaterial.title"
                        class="viewer-image" />

                    <!-- Video -->
                    <video v-else-if="viewingMaterial.type === 'video' && !viewingMaterial.external_url"
                        :src="fileUrl(viewingMaterial)"
                        controls
                        class="viewer-video" />

                    <!-- External URL or non-previewable types -->
                    <div v-else class="viewer-fallback">
                        <div class="viewer-fallback-icon">
                            <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" :style="`color:${typeMeta(viewingMaterial.type).color}`">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 2v6h6"/>
                            </svg>
                        </div>
                        <p class="viewer-fallback-title">{{ viewingMaterial.title }}</p>
                        <p class="viewer-fallback-sub">This file type cannot be previewed in the browser.</p>
                        <div class="viewer-fallback-actions">
                            <Button as="a" v-if="fileUrl(viewingMaterial)" :href="fileUrl(viewingMaterial)" download>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download File
                            </Button>
                            <Button variant="secondary" as="a" v-if="viewingMaterial.external_url" :href="viewingMaterial.external_url" target="_blank">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                Open Link
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Stats row ─────────────────────────────────────────── */
.stats-row {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    margin-bottom: 16px;
}
.stat-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; background: #fff; border: 1px solid #e2e8f0;
    border-radius: 999px; font-size: 0.8125rem; color: #475569;
}
.stat-pill strong { color: #1e293b; }
.stat-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.live-dot { background: #22c55e; box-shadow: 0 0 0 3px #22c55e30; animation: pulse 2s infinite; }
.upcoming-dot { background: #6366f1; }
.past-dot { background: #94a3b8; }

/* ── Filter bar ────────────────────────────────────────── */
.active-filters { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.filter-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; background: #eef2ff; color: #4338ca;
    border-radius: 999px; font-size: 0.75rem; font-weight: 600;
}
.pill-x {
    background: none; border: none; cursor: pointer; color: #818cf8;
    font-size: 1rem; line-height: 1; padding: 0 0 0 2px;
}
.pill-x:hover { color: #dc2626; }

/* ── Tabs ──────────────────────────────────────────────── */
.tab-bar {
    display: flex; gap: 4px; padding: 4px;
    background: #f1f5f9; border-radius: 12px; margin-bottom: 20px;
    width: fit-content;
}
.tab-count {
    background: #e2e8f0; color: #475569; border-radius: 999px;
    padding: 1px 8px; font-size: 0.72rem; font-weight: 700;
}
.ui-btn--active .tab-count { background: #eef2ff; color: #6366f1; }
.tab-content { animation: fadeIn 0.15s ease; }

/* ── Class sections ────────────────────────────────────── */
.class-section { margin-bottom: 24px; }
.class-section-header {
    display: flex; align-items: center; gap: 8px; font-size: 0.8125rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 12px; padding: 6px 0;
}
.live-header { color: #16a34a; }
.upcoming-header { color: #4f46e5; }
.past-header { color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 16px; margin-top: 8px; }
.live-indicator {
    display: inline-block; width: 10px; height: 10px; border-radius: 50%;
    background: #22c55e; box-shadow: 0 0 0 4px #22c55e30;
    animation: pulse 2s infinite;
}

/* ── Class grid ────────────────────────────────────────── */
.class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px,1fr)); gap: 14px; }

.class-card {
    background: #fff; border-radius: 14px; overflow: hidden;
    border: 1.5px solid #e2e8f0; transition: box-shadow 0.15s, transform 0.15s;
}
.class-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-2px); }
.live-card { border-color: #22c55e; box-shadow: 0 0 0 3px #22c55e18; }
.upcoming-card {}

.cc-header { display: flex; align-items: center; gap: 10px; padding: 14px 14px 10px; }
.cc-platform {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 1rem;
}
.cc-meta { flex: 1; min-width: 0; }
.cc-class { font-size: 0.8125rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-subject { font-size: 0.72rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-countdown {
    font-size: 0.72rem; font-weight: 700; color: #4f46e5;
    background: #eef2ff; padding: 3px 8px; border-radius: 6px; white-space: nowrap;
}
.live-card .cc-countdown { background: #dcfce7; color: #16a34a; }
.cc-del { padding: 4px; color: #94a3b8; background: none; border: none; cursor: pointer; border-radius: 6px; }
.cc-del:hover { color: #dc2626; background: #fef2f2; }

.cc-time {
    display: flex; align-items: center; gap: 5px; padding: 0 14px 10px;
    font-size: 0.78rem; color: #475569; font-weight: 500;
}
.cc-end { color: #94a3b8; }

.cc-actions { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 14px 10px; }
.cc-teacher { padding: 8px 14px; font-size: 0.72rem; color: #94a3b8; border-top: 1px solid #f1f5f9; }

.copy-btn { transition: all 0.15s; }
.platform-chip {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    font-size: 0.72rem; font-weight: 700;
}
.past-row td { opacity: 0.75; }

/* ── Material search ───────────────────────────────────── */
.material-search-bar {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px; background: #fff; border: 1.5px solid #e2e8f0;
    border-radius: 10px; margin-bottom: 16px;
}
.search-input { flex: 1; border: none; outline: none; font-size: 0.875rem; color: #334155; background: transparent; }
.search-clear { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; line-height: 1; }
.search-clear:hover { color: #475569; }

/* ── Material grid ─────────────────────────────────────── */
.material-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px;
}
.material-card {
    background: #fff; border-radius: 14px; border: 1.5px solid #e2e8f0;
    padding: 16px; display: flex; flex-direction: column; gap: 8px;
    transition: box-shadow 0.15s, transform 0.15s;
}
.material-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-2px); }

.mat-type-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 8px; width: fit-content;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
}
.mat-type-icon { display: flex; }
.mat-type-label {}
.mat-title { font-size: 0.9375rem; font-weight: 700; color: #1e293b; line-height: 1.35; }
.mat-meta { font-size: 0.72rem; color: #64748b; display: flex; flex-wrap: wrap; align-items: center; gap: 3px; }
.meta-sep { color: #cbd5e1; }
.mat-chapter { font-size: 0.72rem; color: #6366f1; font-weight: 600; }
.mat-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 8px; border-top: 1px solid #f1f5f9; }
.mat-teacher { font-size: 0.72rem; color: #94a3b8; }
.mat-actions { display: flex; gap: 6px; }

/* ── Drag & Drop zone ──────────────────────────────────── */
.drop-zone {
    position: relative; border: 2px dashed #cbd5e1; border-radius: 10px;
    background: #f8fafc; transition: border-color 0.15s, background 0.15s;
    min-height: 90px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; overflow: hidden;
}
.drop-zone:hover, .drop-active { border-color: #6366f1; background: #eef2ff; }
.drop-filled { border-style: solid; border-color: #22c55e; background: #f0fdf4; }
.drop-input {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.drop-placeholder { display: flex; flex-direction: column; align-items: center; pointer-events: none; }
.drop-filled-info {
    display: flex; align-items: center; gap: 10px; padding: 12px 16px; width: 100%;
}
.drop-clear {
    background: none; border: none; color: #94a3b8; cursor: pointer;
    font-size: 1.2rem; line-height: 1; margin-left: auto; z-index: 10; position: relative;
}
.drop-clear:hover { color: #dc2626; }

/* ── Modals ────────────────────────────────────────────── */
.modal-backdrop {
    position: fixed; inset: 0; z-index: 100; display: flex;
    align-items: center; justify-content: center;
    padding: 16px; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px);
}
.modal-box {
    background: #fff; border-radius: 16px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.2);
    width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto;
    animation: slideUp 0.2s ease;
}
.modal-hdr {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 20px 24px 16px; border-bottom: 1px solid #f1f5f9;
}
.modal-title { font-size: 1.0625rem; font-weight: 700; color: #1e293b; }
.modal-sub { font-size: 0.78rem; color: #64748b; margin-top: 2px; }
.modal-x {
    padding: 4px; color: #94a3b8; background: none; border: none;
    cursor: pointer; border-radius: 6px; flex-shrink: 0;
}
.modal-x:hover { color: #475569; background: #f1f5f9; }
.modal-body { padding: 20px 24px; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; padding-top: 16px; border-top: 1px solid #f1f5f9; }

/* ── Empty state ───────────────────────────────────────── */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { margin-bottom: 12px; }
.empty-title { font-size: 1rem; font-weight: 600; color: #475569; }
.empty-sub { font-size: 0.83rem; color: #94a3b8; margin-top: 4px; }

/* ── Pagination ────────────────────────────────────────── */
.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 16px; }

/* ── Shared form helpers ───────────────────────────────── */
.req { color: #ef4444; }
.hint { font-size: 0.75rem; color: #94a3b8; font-style: italic; }
.form-error { font-size: 0.75rem; color: #ef4444; margin-top: 4px; }
.checkbox-group {
    display: flex; flex-wrap: wrap; gap: 8px 16px; padding: 10px;
    background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px;
    min-height: 42px; align-items: center;
}
.checkbox-group.disabled { opacity: 0.5; pointer-events: none; }
.checkbox-row { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.84rem; color: #334155; }

/* ── File Viewer Modal ────────────────────────────────── */
.viewer-box {
    background: #fff; border-radius: 16px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.25);
    width: 95%; max-width: 1000px; height: 90vh;
    display: flex; flex-direction: column;
    animation: slideUp 0.2s ease; overflow: hidden;
}
.viewer-hdr {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 20px; border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0; gap: 12px;
}
.viewer-hdr-info {
    display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1;
}
.viewer-hdr-info .modal-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.viewer-hdr-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.viewer-body {
    flex: 1; overflow: auto; display: flex; align-items: center; justify-content: center;
    background: #f8fafc;
}
.viewer-iframe { width: 100%; height: 100%; border: none; }
.viewer-image { max-width: 100%; max-height: 100%; object-fit: contain; padding: 16px; }
.viewer-video { max-width: 100%; max-height: 100%; outline: none; }
.viewer-fallback {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 12px; padding: 40px 20px; text-align: center;
}
.viewer-fallback-icon { opacity: 0.6; }
.viewer-fallback-title { font-size: 1.125rem; font-weight: 700; color: #1e293b; }
.viewer-fallback-sub { font-size: 0.875rem; color: #94a3b8; }
.viewer-fallback-actions { display: flex; gap: 10px; margin-top: 8px; }

/* ── Animations ────────────────────────────────────────── */
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.5;} }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
</style>
