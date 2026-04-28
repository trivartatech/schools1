<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useConfirm } from '@/Composables/useConfirm';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const confirm = useConfirm();
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
const showRecordingModal = ref(false);
const showViewerModal = ref(false);
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

const openViewer = (m) => { viewingMaterial.value = m; showViewerModal.value = true; };
const closeViewer = () => { showViewerModal.value = false; viewingMaterial.value = null; };

const fileUrl = (m) => {
    if (m.external_url) return m.external_url;
    if (!m.file_path) return null;
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

const deleteMaterial = async (id) => {
    const ok = await confirm({
        title: 'Delete material?',
        message: 'This material will be permanently deleted.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    useForm({}).delete(route('school.academic.resources.destroy-material', id));
};
const deleteClass = async (id) => {
    const ok = await confirm({
        title: 'Cancel online class?',
        message: 'This class will be removed.',
        confirmLabel: 'Cancel Class',
        danger: true,
    });
    if (!ok) return;
    useForm({}).delete(route('school.academic.resources.destroy-online-class', id));
};

const openRecordingModal = (cls) => {
    recordingTarget.value = cls;
    recordingForm.recording_link = cls.recording_link || '';
    showRecordingModal.value = true;
};
const saveRecording = () => {
    recordingForm.post(route('school.academic.resources.add-recording', recordingTarget.value.id), {
        onSuccess: () => { showRecordingModal.value = false; recordingTarget.value = null; recordingForm.reset(); },
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

        <!-- Page Header -->
        <PageHeader title="Digital Resources" subtitle="Online classes & study materials in one place">
            <template #actions>
                <template v-if="can('create_academic')">
                    <Button variant="secondary" @click="showClassModal = true">Schedule Class</Button>
                    <Button as="link" :href="route('school.academic.resources.create-material')">Upload Material</Button>
                </template>
            </template>
        </PageHeader>

        <!-- Stats row -->
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

        <!-- Filter bar -->
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

        <!-- Tabs -->
        <div class="tab-bar">
            <Button variant="tab" :active="activeTab === 'classes'" @click="activeTab = 'classes'">
                Online Classes
                <span class="tab-count">{{ onlineClasses.total ?? onlineClasses.data?.length ?? 0 }}</span>
            </Button>
            <Button variant="tab" :active="activeTab === 'materials'" @click="activeTab = 'materials'">
                Learning Materials
                <span class="tab-count">{{ learningMaterials.total ?? learningMaterials.data?.length ?? 0 }}</span>
            </Button>
        </div>

        <!-- TAB 1 — ONLINE CLASSES -->
        <div v-show="activeTab === 'classes'" class="tab-content">

            <!-- LIVE NOW -->
            <div v-if="groupedClasses.live.length > 0" class="class-section">
                <div class="class-section-header live-header">
                    <span class="live-indicator"></span> Live Right Now
                </div>
                <div class="class-grid">
                    <div v-for="c in groupedClasses.live" :key="c.id" class="class-card live-card">
                        <div class="cc-header">
                            <div class="cc-platform" :style="`background:${platformMeta(c.platform).color}20;color:${platformMeta(c.platform).color}`">
                                {{ platformMeta(c.platform).icon }}
                            </div>
                            <div class="cc-meta">
                                <div class="cc-class">{{ c.course_class?.name }} <span v-if="c.section">· {{ c.section?.name }}</span></div>
                                <div class="cc-subject">{{ c.subject?.name }}</div>
                            </div>
                            <div class="cc-countdown">LIVE</div>
                            <button v-if="can('delete_academic')" @click="deleteClass(c.id)" class="cc-del" title="Cancel class">×</button>
                        </div>
                        <div class="cc-time">
                            {{ formatDateFull(c.start_time) }}
                            <span v-if="c.end_time" class="cc-end">→ {{ formatDate(c.end_time) }}</span>
                        </div>
                        <div class="cc-actions">
                            <Button size="sm" as="a" :href="c.meeting_link" target="_blank">Join Class</Button>
                            <Button variant="secondary" size="sm" @click="copyLink(c.id, c.meeting_link)">{{ copiedId === c.id ? 'Copied!' : 'Copy Link' }}</Button>
                            <Button variant="secondary" size="sm" v-if="can('edit_academic') && !c.recording_link" @click="openRecordingModal(c)">+ Recording</Button>
                            <Button variant="secondary" size="sm" as="a" v-if="c.recording_link" :href="c.recording_link" target="_blank">Recording</Button>
                        </div>
                        <div class="cc-teacher">By {{ c.teacher?.user?.name ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <!-- UPCOMING -->
            <div v-if="groupedClasses.upcoming.length > 0" class="class-section">
                <div class="class-section-header upcoming-header">
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
                            <button v-if="can('delete_academic')" @click="deleteClass(c.id)" class="cc-del" title="Cancel class">×</button>
                        </div>
                        <div class="cc-time">
                            {{ formatDateFull(c.start_time) }}
                            <span v-if="c.end_time" class="cc-end">→ {{ formatDate(c.end_time) }}</span>
                        </div>
                        <div class="cc-actions">
                            <Button size="sm" as="a" :href="c.meeting_link" target="_blank">Join Class</Button>
                            <Button variant="secondary" size="sm" @click="copyLink(c.id, c.meeting_link)">{{ copiedId === c.id ? 'Copied!' : 'Copy Link' }}</Button>
                            <Button variant="secondary" size="sm" v-if="can('edit_academic') && !c.recording_link" @click="openRecordingModal(c)">+ Recording</Button>
                            <Button variant="secondary" size="sm" as="a" v-if="c.recording_link" :href="c.recording_link" target="_blank">Recording</Button>
                        </div>
                        <div class="cc-teacher">By {{ c.teacher?.user?.name ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <!-- PAST -->
            <div v-if="groupedClasses.past.length > 0" class="class-section">
                <div class="class-section-header past-header">
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
                                    <a v-if="c.recording_link" :href="c.recording_link" target="_blank" class="text-indigo-600 text-xs font-bold hover:underline">Watch</a>
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
            <EmptyState
                v-if="onlineClasses.data?.length === 0"
                title="No classes scheduled"
                description="Use 'Schedule Class' to add an online class for a section."
                :action-label="can('create_academic') ? 'Schedule Class' : ''"
                @action="showClassModal = true"
            />

            <!-- Pagination -->
            <div v-if="onlineClasses.last_page > 1" class="pagination">
                <Button v-for="link in onlineClasses.links" :key="link.label"
                   variant="tab" size="sm" :active="link.active"
                   :disabled="!link.url"
                   @click="link.url && router.get(link.url, {}, { preserveState: true })"
                   v-html="link.label" />
            </div>
        </div>

        <!-- TAB 2 — LEARNING MATERIALS -->
        <div v-show="activeTab === 'materials'" class="tab-content">

            <!-- Search within materials -->
            <div class="material-search-bar">
                <input v-model="materialSearch" type="text" placeholder="Search by title, subject or chapter…" class="search-input" />
                <button v-if="materialSearch" @click="materialSearch = ''" class="search-clear">×</button>
            </div>

            <!-- Material grid -->
            <div v-if="filteredMaterials.length > 0" class="material-grid">
                <div v-for="m in filteredMaterials" :key="m.id" class="material-card">
                    <div class="mat-type-badge"
                         :style="`background:${typeMeta(m.type).bg};color:${typeMeta(m.type).color};`">
                        <span class="mat-type-label">{{ typeMeta(m.type).label }}</span>
                    </div>

                    <div class="mat-title">{{ m.title }}</div>
                    <div class="mat-meta">
                        <span>{{ m.course_class?.name }}</span>
                        <span v-if="m.section" class="meta-sep">·</span>
                        <span v-if="m.section">{{ m.section?.name }}</span>
                        <span class="meta-sep">·</span>
                        <span>{{ m.subject?.name }}</span>
                    </div>
                    <div v-if="m.chapter_name" class="mat-chapter">{{ m.chapter_name }}</div>

                    <div class="mat-footer">
                        <span class="mat-teacher">By {{ m.teacher?.user?.name ?? '—' }}</span>
                        <div class="mat-actions">
                            <Button variant="secondary" size="xs" @click="openViewer(m)">View</Button>
                            <Button variant="danger" size="xs" v-if="can('delete_academic')" @click="deleteMaterial(m.id)">×</Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <EmptyState
                v-else
                :title="materialSearch ? 'No results found' : 'No materials uploaded yet'"
                :description="materialSearch ? 'Try a different search term.' : 'Upload notes, slides and videos for your students.'"
            />

            <!-- Pagination -->
            <div v-if="learningMaterials.last_page > 1" class="pagination">
                <Button v-for="link in learningMaterials.links" :key="link.label"
                   variant="tab" size="sm" :active="link.active"
                   :disabled="!link.url"
                   @click="link.url && router.get(link.url, {}, { preserveState: true })"
                   v-html="link.label" />
            </div>
        </div>


        <!-- Schedule Class Modal -->
        <Modal v-model:open="showClassModal" title="Schedule Online Class" size="md">
            <form @submit.prevent="storeClass" id="class-form">
                <p style="font-size:.78rem;color:#64748b;margin:0 0 14px;">Set up a virtual session for your students</p>
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
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showClassModal = false">Cancel</Button>
                <Button type="submit" form="class-form" :loading="classForm.processing">Schedule Class</Button>
            </template>
        </Modal>

        <!-- Upload Material Modal -->
        <Modal v-model:open="showMaterialModal" title="Upload Learning Material" size="md">
            <form @submit.prevent="storeMaterial" id="material-form">
                <p style="font-size:.78rem;color:#64748b;margin:0 0 14px;">Share notes, slides, videos and more</p>
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
                            <span class="text-sm font-medium text-slate-500">Drop file here or <span class="text-indigo-600">browse</span></span>
                            <span class="text-xs text-slate-400 mt-1">PDF, PPT, DOCX, MP4, JPG, PNG · max 20 MB</span>
                        </div>
                        <div v-else class="drop-filled-info">
                            <span class="text-sm font-medium text-slate-700 truncate">{{ selectedFileName }}</span>
                            <button type="button" @click="materialForm.file = null; selectedFileName = ''" class="drop-clear">×</button>
                        </div>
                    </div>
                    <p v-if="materialForm.errors.file" class="form-error">{{ materialForm.errors.file }}</p>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showMaterialModal = false">Cancel</Button>
                <Button type="submit" form="material-form" :loading="materialForm.processing" :disabled="!materialForm.file">
                    {{ materialForm.processing ? 'Uploading…' : 'Upload Material' }}
                </Button>
            </template>
        </Modal>

        <!-- Recording Link Modal -->
        <Modal v-model:open="showRecordingModal" title="Add Recording Link" size="sm">
            <form @submit.prevent="saveRecording" id="recording-form" v-if="recordingTarget">
                <p style="font-size:.78rem;color:#64748b;margin:0 0 14px;">{{ recordingTarget.course_class?.name }} · {{ recordingTarget.subject?.name }}</p>
                <div class="form-field">
                    <label>Recording URL <span class="req">*</span></label>
                    <input type="url" v-model="recordingForm.recording_link" placeholder="https://drive.google.com/…" required autofocus />
                    <p v-if="recordingForm.errors.recording_link" class="form-error">{{ recordingForm.errors.recording_link }}</p>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showRecordingModal = false">Cancel</Button>
                <Button type="submit" form="recording-form" :loading="recordingForm.processing">Save Recording</Button>
            </template>
        </Modal>

        <!-- File Viewer Modal -->
        <Modal v-model:open="showViewerModal" :title="viewingMaterial?.title ?? ''" size="xl">
            <div v-if="viewingMaterial">
                <p style="font-size:.78rem;color:#64748b;margin:0 0 14px;">
                    {{ viewingMaterial.course_class?.name }}
                    <span v-if="viewingMaterial.section"> · {{ viewingMaterial.section?.name }}</span>
                    · {{ viewingMaterial.subject?.name }}
                    <span v-if="viewingMaterial.chapter_name"> · {{ viewingMaterial.chapter_name }}</span>
                </p>
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

                    <!-- External URL or non-previewable -->
                    <div v-else class="viewer-fallback">
                        <p class="viewer-fallback-title">{{ viewingMaterial.title }}</p>
                        <p class="viewer-fallback-sub">This file type cannot be previewed in the browser.</p>
                        <div class="viewer-fallback-actions">
                            <Button as="a" v-if="fileUrl(viewingMaterial)" :href="fileUrl(viewingMaterial)" download>Download File</Button>
                            <Button variant="secondary" as="a" v-if="viewingMaterial.external_url" :href="viewingMaterial.external_url" target="_blank">Open Link</Button>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button v-if="viewingMaterial && fileUrl(viewingMaterial)" as="a" :href="fileUrl(viewingMaterial)" download>Download</Button>
                <Button variant="secondary" @click="closeViewer">Close</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* Stats row */
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

/* Filter bar */
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

/* Tabs */
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

/* Class sections */
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

/* Class grid */
.class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px,1fr)); gap: 14px; }

.class-card {
    background: #fff; border-radius: 14px; overflow: hidden;
    border: 1.5px solid #e2e8f0; transition: box-shadow 0.15s, transform 0.15s;
}
.class-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-2px); }
.live-card { border-color: #22c55e; box-shadow: 0 0 0 3px #22c55e18; }

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
.cc-del { padding: 4px; color: #94a3b8; background: none; border: none; cursor: pointer; border-radius: 6px; font-size: 1.25rem; line-height: 1; }
.cc-del:hover { color: #dc2626; background: #fef2f2; }

.cc-time {
    display: flex; align-items: center; gap: 5px; padding: 0 14px 10px;
    font-size: 0.78rem; color: #475569; font-weight: 500;
}
.cc-end { color: #94a3b8; }

.cc-actions { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 14px 10px; }
.cc-teacher { padding: 8px 14px; font-size: 0.72rem; color: #94a3b8; border-top: 1px solid #f1f5f9; }

.platform-chip {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    font-size: 0.72rem; font-weight: 700;
}
.past-row td { opacity: 0.75; }

/* Material search */
.material-search-bar {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px; background: #fff; border: 1.5px solid #e2e8f0;
    border-radius: 10px; margin-bottom: 16px;
}
.search-input { flex: 1; border: none; outline: none; font-size: 0.875rem; color: #334155; background: transparent; }
.search-clear { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; line-height: 1; }
.search-clear:hover { color: #475569; }

/* Material grid */
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
.mat-title { font-size: 0.9375rem; font-weight: 700; color: #1e293b; line-height: 1.35; }
.mat-meta { font-size: 0.72rem; color: #64748b; display: flex; flex-wrap: wrap; align-items: center; gap: 3px; }
.meta-sep { color: #cbd5e1; }
.mat-chapter { font-size: 0.72rem; color: #6366f1; font-weight: 600; }
.mat-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 8px; border-top: 1px solid #f1f5f9; }
.mat-teacher { font-size: 0.72rem; color: #94a3b8; }
.mat-actions { display: flex; gap: 6px; }

/* Drag & Drop zone */
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

/* Pagination */
.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 16px; }

/* Form helpers */
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

/* File Viewer */
.viewer-body {
    height: 70vh; display: flex; align-items: center; justify-content: center;
    background: #f8fafc; border-radius: 8px; overflow: auto;
}
.viewer-iframe { width: 100%; height: 100%; border: none; }
.viewer-image { max-width: 100%; max-height: 100%; object-fit: contain; padding: 16px; }
.viewer-video { max-width: 100%; max-height: 100%; outline: none; }
.viewer-fallback {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 12px; padding: 40px 20px; text-align: center;
}
.viewer-fallback-title { font-size: 1.125rem; font-weight: 700; color: #1e293b; }
.viewer-fallback-sub { font-size: 0.875rem; color: #94a3b8; }
.viewer-fallback-actions { display: flex; gap: 10px; margin-top: 8px; }

/* Animations */
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.5;} }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: #fff;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
</style>
