<script setup>
import { ref, computed } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useConfirm } from '@/Composables/useConfirm';
import FilterBar from '@/Components/ui/FilterBar.vue';

const confirm = useConfirm();

const props = defineProps({
    classes: Array,
    classSubjects: Array,
    staff: Array,
});

// ── Drag state ──────────────────────────────────────
const dragging = ref(null);     // { id, name, employee_id, photo, designation }
const dragOver = ref(null);     // 'class-{id}' | 'section-{id}' | 'subject-{id}'
const submitting = ref(null);

// ── Active tab ───────────────────────────────────────
const activeTab = ref('class-section');  // 'class-section' | 'subject'

// ── Search filter ────────────────────────────────────
const staffSearch = ref('');
const filteredStaff = computed(() =>
    props.staff.filter(s =>
        s.name.toLowerCase().includes(staffSearch.value.toLowerCase()) ||
        (s.employee_id || '').toLowerCase().includes(staffSearch.value.toLowerCase())
    )
);

// ── Section filter for subject view ─────────────────
const selectedClass = ref('');
const selectedSection = ref('');

const filteredClassSubjects = computed(() => {
    return props.classSubjects.filter(cs => {
        const matchClass = !selectedClass.value || cs.course_class_id == selectedClass.value;
        const matchSection = !selectedSection.value || cs.section_id == selectedSection.value;
        return matchClass && matchSection;
    });
});

const sectionsForSelectedClass = computed(() => {
    if (!selectedClass.value) return [];
    const cls = props.classes.find(c => c.id == selectedClass.value);
    return cls?.sections || [];
});

// ── Drag handlers ─────────────────────────────────────
const onDragStart = (staffMember) => {
    dragging.value = staffMember;
};
const onDragEnd = () => {
    dragging.value = null;
    dragOver.value = null;
};

// ── Drop: Class ───────────────────────────────────────
const onDropClass = (cls) => {
    dragOver.value = null;
    if (!dragging.value) return;
    const key = `class-${cls.id}`;
    if (submitting.value) return;  // prevent double-submission while a request is in flight
    submitting.value = key;
    router.post(`/school/incharge/class/${cls.id}`, { staff_id: dragging.value.id }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = null; dragging.value = null; }
    });
};

// ── Drop: Section ─────────────────────────────────────
const onDropSection = (section) => {
    dragOver.value = null;
    if (!dragging.value) return;
    const key = `section-${section.id}`;
    if (submitting.value) return;  // prevent double-submission while a request is in flight
    submitting.value = key;
    router.post(`/school/incharge/section/${section.id}`, { staff_id: dragging.value.id }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = null; dragging.value = null; }
    });
};

// ── Drop: Subject ─────────────────────────────────────
const onDropSubject = (cs) => {
    dragOver.value = null;
    if (!dragging.value) return;
    const key = `subject-${cs.id}`;
    if (submitting.value) return;  // prevent double-submission while a request is in flight
    submitting.value = key;
    router.post(`/school/incharge/subject/${cs.id}`, { staff_id: dragging.value.id }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = null; dragging.value = null; }
    });
};

// ── Remove incharge ───────────────────────────────────
const removeClassIncharge = async (cls) => {
    const ok = await confirm({
        title: 'Remove class incharge?',
        message: `Remove incharge from ${cls.name}?`,
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.post(`/school/incharge/class/${cls.id}`, { staff_id: null }, { preserveScroll: true });
};
const removeSectionIncharge = async (section) => {
    const ok = await confirm({
        title: 'Remove section incharge?',
        message: `Remove incharge from ${section.name}?`,
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.post(`/school/incharge/section/${section.id}`, { staff_id: null }, { preserveScroll: true });
};
const removeSubjectIncharge = async (cs) => {
    const ok = await confirm({
        title: 'Remove subject incharge?',
        message: 'Remove subject incharge?',
        confirmLabel: 'Remove',
        danger: true,
    });
    if (!ok) return;
    router.post(`/school/incharge/subject/${cs.id}`, { staff_id: null }, { preserveScroll: true });
};

// Compute grouped subject view (by class name)
const subjectsByClass = computed(() => {
    const map = {};
    filteredClassSubjects.value.forEach(cs => {
        const className = cs.course_class?.name || 'Unknown';
        if (!map[className]) map[className] = [];
        map[className].push(cs);
    });
    return map;
});
</script>

<template>
    <Head title="Incharge Assignment" />
    <SchoolLayout title="Incharge Assignment">

        <!-- Page Header -->
        <PageHeader title="Incharge Assignment" subtitle="Drag a staff member from the left panel and drop on a class, section, or subject slot" />

        <div class="incharge-shell">

            <!-- ── LEFT PANEL: Staff Roster ── -->
            <aside class="staff-panel">
                <div class="staff-panel-header">
                    <h3 class="staff-panel-title">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Staff Roster
                    </h3>
                    <span class="staff-count-badge">{{ filteredStaff.length }}</span>
                </div>

                <div class="staff-search-wrap">
                    <svg class="staff-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                    <input v-model="staffSearch" type="text" placeholder="Search staff..." class="staff-search-input">
                </div>

                <div class="staff-hint">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    Drag & drop to assign
                </div>

                <div class="staff-list">
                    <div v-for="s in filteredStaff" :key="s.id"
                        class="staff-card"
                        :class="{ 'staff-card--dragging': dragging?.id === s.id }"
                        draggable="true"
                        @dragstart="onDragStart(s)"
                        @dragend="onDragEnd">
                        <!-- Avatar -->
                        <div v-if="s.photo" class="staff-avatar">
                            <img :src="`/storage/${s.photo}`" class="w-full h-full object-cover">
                        </div>
                        <div v-else class="staff-avatar staff-avatar--initials">
                            {{ s.name.substring(0, 2).toUpperCase() }}
                        </div>
                        <div class="staff-card-info">
                            <span class="staff-card-name">{{ s.name }}</span>
                            <span class="staff-card-meta">{{ s.employee_id }} · {{ s.designation || 'Staff' }}</span>
                        </div>
                        <svg class="drag-handle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                    </div>
                    <div v-if="filteredStaff.length === 0" class="staff-empty">No staff found</div>
                </div>
            </aside>

            <!-- ── RIGHT PANEL: Assignment Board ── -->
            <div class="assign-board">

                <!-- Tab Switcher -->
                <div class="board-tabs">
                    <button @click="activeTab = 'class-section'"
                        class="board-tab" :class="{ 'board-tab--active': activeTab === 'class-section' }">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Class & Section Incharge
                    </button>
                    <button @click="activeTab = 'subject'"
                        class="board-tab" :class="{ 'board-tab--active': activeTab === 'subject' }">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        Subject Incharge
                    </button>
                </div>

                <!-- ── Tab 1: Class & Section ── -->
                <div v-if="activeTab === 'class-section'" class="tab-content">
                    <div v-if="classes.length === 0" class="empty-board">
                        No classes found. Add classes first.
                    </div>

                    <div v-for="cls in classes" :key="cls.id" class="class-block">
                        <!-- Class Row -->
                        <div class="class-row">
                            <div class="class-label">
                                <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
                                Class {{ cls.name }}
                            </div>

                            <!-- Class incharge drop zone -->
                            <div class="drop-zone"
                                :class="{
                                    'drop-zone--hover': dragOver === `class-${cls.id}`,
                                    'drop-zone--filled': cls.incharge_staff,
                                    'drop-zone--loading': submitting === `class-${cls.id}`
                                }"
                                @dragover.prevent="dragOver = `class-${cls.id}`"
                                @dragleave="dragOver = null"
                                @drop.prevent="onDropClass(cls)">

                                <div v-if="submitting === `class-${cls.id}`" class="drop-loading">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                                <template v-else-if="cls.incharge_staff">
                                    <div class="assigned-badge">
                                        <img v-if="cls.incharge_staff?.photo" :src="`/storage/${cls.incharge_staff.photo}`" class="assigned-avatar-img">
                                        <div v-else class="assigned-avatar-initials">{{ cls.incharge_staff?.user?.name?.substring(0,2).toUpperCase() }}</div>
                                        <span class="assigned-name">{{ cls.incharge_staff?.user?.name }}</span>
                                        <button @click="removeClassIncharge(cls)" class="remove-btn" title="Remove incharge">×</button>
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="drop-placeholder">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        Class Incharge
                                    </span>
                                </template>
                            </div>
                        </div>

                        <!-- Sections -->
                        <div class="sections-grid">
                            <div v-for="section in cls.sections" :key="section.id" class="section-slot">
                                <div class="section-label">Section {{ section.name }}</div>
                                <div class="drop-zone drop-zone--section"
                                    :class="{
                                        'drop-zone--hover': dragOver === `section-${section.id}`,
                                        'drop-zone--filled': section.incharge_staff,
                                        'drop-zone--loading': submitting === `section-${section.id}`
                                    }"
                                    @dragover.prevent="dragOver = `section-${section.id}`"
                                    @dragleave="dragOver = null"
                                    @drop.prevent="onDropSection(section)">

                                    <div v-if="submitting === `section-${section.id}`" class="drop-loading">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    </div>
                                    <template v-else-if="section.incharge_staff">
                                        <div class="assigned-badge assigned-badge--sm">
                                            <img v-if="section.incharge_staff?.photo" :src="`/storage/${section.incharge_staff.photo}`" class="assigned-avatar-img assigned-avatar-img--sm">
                                            <div v-else class="assigned-avatar-initials assigned-avatar-initials--sm">{{ section.incharge_staff?.user?.name?.substring(0,2).toUpperCase() }}</div>
                                            <span class="assigned-name assigned-name--sm">{{ section.incharge_staff?.user?.name }}</span>
                                            <button @click="removeSectionIncharge(section)" class="remove-btn" title="Remove">×</button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="drop-placeholder drop-placeholder--sm">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Drop here
                                        </span>
                                    </template>
                                </div>
                            </div>
                            <div v-if="!cls.sections?.length" class="no-sections">No sections configured</div>
                        </div>
                    </div>
                </div>

                <!-- ── Tab 2: Subject Incharge ── -->
                <div v-if="activeTab === 'subject'" class="tab-content">
                    <!-- Filters -->
                    <FilterBar :active="!!(selectedClass || selectedSection)" @clear="selectedClass = ''; selectedSection = ''">
                        <div class="form-field">
                            <label>Class</label>
                            <select v-model="selectedClass" @change="selectedSection = ''" style="width:200px;">
                                <option value="">All Classes</option>
                                <option v-for="cls in classes" :key="cls.id" :value="cls.id">Class {{ cls.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Section</label>
                            <select v-model="selectedSection" :disabled="!selectedClass" style="width:160px;">
                                <option value="">All Sections</option>
                                <option v-for="sec in sectionsForSelectedClass" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                            </select>
                        </div>
                    </FilterBar>

                    <div v-if="filteredClassSubjects.length === 0" class="empty-board">
                        No subjects found. Assign subjects to classes first via Academic Structure → Assign Subjects.
                    </div>

                    <div v-for="(subjects, className) in subjectsByClass" :key="className" class="class-block">
                        <div class="class-row">
                            <div class="class-label">
                                <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
                                Class {{ className }}
                            </div>
                        </div>

                        <div class="subjects-grid">
                            <div v-for="cs in subjects" :key="cs.id" class="subject-slot">
                                <div class="subject-info">
                                    <span class="subject-name">{{ cs.subject?.name }}</span>
                                    <span class="section-pill">{{ cs.section?.name }}</span>
                                </div>
                                <div class="drop-zone drop-zone--subject"
                                    :class="{
                                        'drop-zone--hover': dragOver === `subject-${cs.id}`,
                                        'drop-zone--filled': cs.incharge_staff,
                                        'drop-zone--loading': submitting === `subject-${cs.id}`
                                    }"
                                    @dragover.prevent="dragOver = `subject-${cs.id}`"
                                    @dragleave="dragOver = null"
                                    @drop.prevent="onDropSubject(cs)">

                                    <div v-if="submitting === `subject-${cs.id}`" class="drop-loading">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    </div>
                                    <template v-else-if="cs.incharge_staff">
                                        <div class="assigned-badge assigned-badge--sm">
                                            <img v-if="cs.incharge_staff?.photo" :src="`/storage/${cs.incharge_staff.photo}`" class="assigned-avatar-img assigned-avatar-img--sm">
                                            <div v-else class="assigned-avatar-initials assigned-avatar-initials--sm">{{ cs.incharge_staff?.user?.name?.substring(0,2).toUpperCase() }}</div>
                                            <span class="assigned-name assigned-name--sm">{{ cs.incharge_staff?.user?.name }}</span>
                                            <button @click="removeSubjectIncharge(cs)" class="remove-btn">×</button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="drop-placeholder drop-placeholder--sm">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Drop teacher
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- end assign-board -->
        </div> <!-- end incharge-shell -->

    </SchoolLayout>
</template>

<style scoped>
/* ── Shell ── */
.incharge-shell {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    min-height: calc(100vh - 200px);
}

/* ── Staff Panel ── */
.staff-panel {
    width: 260px;
    min-width: 260px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 160px);
}
.staff-panel-header {
    padding: 14px 16px 12px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.staff-panel-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 6px;
}
.staff-count-badge {
    background: #eff6ff;
    color: #1169cd;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    border: 1px solid #bfdbfe;
}
.staff-search-wrap {
    position: relative;
    padding: 10px 12px 6px;
}
.staff-search-icon {
    position: absolute;
    left: 22px;
    top: 50%;
    transform: translateY(-40%);
    width: 14px;
    height: 14px;
    color: #94a3b8;
}
.staff-search-input {
    width: 100% !important;
    padding: 6px 10px 6px 30px !important;
    font-size: 0.8125rem !important;
    border: 1.5px solid #e2e8f0 !important;
    border-radius: 7px !important;
}
.staff-hint {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 2px 12px 6px;
    font-size: 0.7rem;
    color: #94a3b8;
}
.staff-list {
    flex: 1;
    overflow-y: auto;
    padding: 0 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.staff-list::-webkit-scrollbar { width: 4px; }
.staff-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }

.staff-card {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: grab;
    border: 1.5px solid #e2e8f0;
    background: #fafafa;
    transition: all 0.12s;
    user-select: none;
}
.staff-card:hover { background: #fff; border-color: #1169cd; box-shadow: 0 2px 8px rgba(17,105,205,0.1); transform: translateY(-1px); }
.staff-card:active { cursor: grabbing; }
.staff-card--dragging {
    opacity: 0.45;
    transform: scale(0.97);
}
.staff-avatar {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    border: 1.5px solid #e0edff;
}
.staff-avatar--initials {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1169cd, #0d50a3);
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
}
.staff-card-info { flex: 1; min-width: 0; }
.staff-card-name { display: block; font-size: 0.8rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.staff-card-meta { display: block; font-size: 0.7rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.drag-handle-icon { width: 14px; height: 14px; color: #cbd5e1; flex-shrink: 0; }
.staff-empty { text-align: center; font-size: 0.8rem; color: #94a3b8; padding: 20px; }

/* ── Assignment Board ── */
.assign-board {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0;
    min-width: 0;
}
.board-tabs {
    display: flex;
    gap: 4px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 4px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.board-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s;
}
.board-tab:hover { color: #1e293b; background: #f8fafc; }
.board-tab--active { background: #1169cd; color: #fff; font-weight: 600; box-shadow: 0 2px 8px rgba(17,105,205,0.25); }

/* ── Tab content ── */
.tab-content { display: flex; flex-direction: column; gap: 12px; }
.empty-board {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 48px;
    text-align: center;
    font-size: 0.8125rem;
    color: #94a3b8;
}

/* ── Class block ── */
.class-block {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.class-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}
.class-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.875rem;
    font-weight: 700;
    color: #1e293b;
}

/* ── Sections grid ── */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
    padding: 12px 16px;
}
.section-slot { display: flex; flex-direction: column; gap: 5px; }
.section-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.no-sections { font-size: 0.775rem; color: #cbd5e1; padding: 12px 16px; }

/* ── Subjects grid ── */
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 10px;
    padding: 12px 16px;
}
.subject-slot { display: flex; flex-direction: column; gap: 5px; }
.subject-info { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.subject-name { font-size: 0.8125rem; font-weight: 600; color: #1e293b; }
.section-pill {
    font-size: 0.65rem;
    padding: 2px 7px;
    border-radius: 12px;
    background: #eff6ff;
    color: #1169cd;
    font-weight: 700;
    border: 1px solid #bfdbfe;
}

/* ── Drop zone ── */
.drop-zone {
    min-height: 46px;
    border: 2px dashed #e2e8f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    transition: all 0.15s;
    background: #fafafa;
    cursor: default;
}
.drop-zone--hover {
    border-color: #1169cd;
    background: #eff6ff;
    box-shadow: 0 0 0 3px rgba(17,105,205,0.1);
    transform: scale(1.01);
}
.drop-zone--filled {
    border-style: solid;
    border-color: #bfdbfe;
    background: #fff;
}
.drop-zone--loading { opacity: 0.7; }
.drop-zone--section, .drop-zone--subject { min-height: 40px; }

.drop-loading { display: flex; align-items: center; justify-content: center; color: #1169cd; }

.drop-placeholder {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.775rem;
    color: #cbd5e1;
    pointer-events: none;
}
.drop-placeholder--sm { font-size: 0.725rem; }

/* Assigned badge */
.assigned-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    width: 100%;
}
.assigned-badge--sm { gap: 5px; }
.assigned-avatar-img { width: 28px; height: 28px; border-radius: 7px; object-fit: cover; flex-shrink: 0; }
.assigned-avatar-img--sm { width: 22px; height: 22px; border-radius: 5px; }
.assigned-avatar-initials {
    width: 28px; height: 28px; border-radius: 7px;
    background: linear-gradient(135deg, #1169cd, #0d50a3);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.65rem; font-weight: 700; color: #fff; flex-shrink: 0;
}
.assigned-avatar-initials--sm { width: 22px; height: 22px; border-radius: 5px; font-size: 0.6rem; }
.assigned-name { font-size: 0.8rem; font-weight: 600; color: #1e293b; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.assigned-name--sm { font-size: 0.725rem; }
.remove-btn {
    width: 20px; height: 20px; border-radius: 50%;
    background: #fee2e2; color: #fc4336; border: none;
    font-size: 0.9rem; line-height: 1; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.12s; font-weight: 700; padding: 0;
}
.remove-btn:hover { background: #fc4336; color: #fff; }
</style>
