<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

const confirm = useConfirm();
const toast = useToast();

const props = defineProps({
    schedules:      Array,
    examTypes:      Array,
    classes:        Array,
    gradingSystems: Array,
    assessments:    Array,
});

// ─── View ───────────────────────────────────────────────
const view = ref('list'); // 'list' | 'create' | 'edit'
const editingSchedule = ref(null);
const processing = ref(false);
const errors = ref({});
const loadingSubjects = ref(false);

// ─── List filters + sort (client-side; data isn't paginated) ───────────
const search           = ref('');
const filterExamTypeId = ref('');
const filterClassId    = ref('');
const filterSectionId  = ref('');
const filterStatus     = ref('');

const filtersActive = computed(() =>
    !!(search.value || filterExamTypeId.value || filterClassId.value
        || filterSectionId.value || filterStatus.value)
);
function clearFilters() {
    search.value = '';
    filterExamTypeId.value = '';
    filterClassId.value = '';
    filterSectionId.value = '';
    filterStatus.value = '';
}

// Reset section whenever the class changes — sections are class-scoped.
watch(filterClassId, () => { filterSectionId.value = ''; });

// Sections available in the dropdown for the currently picked class.
const filterSections = computed(() => {
    if (!filterClassId.value) return [];
    const cls = props.classes.find(c => String(c.id) === String(filterClassId.value));
    return cls?.sections || [];
});

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('', 'asc');

const filteredSchedules = computed(() => {
    let out = props.schedules || [];

    if (filterExamTypeId.value) {
        out = out.filter(s => String(s.exam_type_id) === String(filterExamTypeId.value));
    }
    if (filterClassId.value) {
        out = out.filter(s => String(s.course_class_id) === String(filterClassId.value));
    }
    if (filterSectionId.value) {
        // Each schedule can target multiple sections (many-to-many) — match
        // when the picked section appears in that list.
        out = out.filter(s =>
            (s.sections || []).some(sec => String(sec.id) === String(filterSectionId.value))
        );
    }
    if (filterStatus.value) {
        out = out.filter(s => s.status === filterStatus.value);
    }
    if (search.value) {
        const q = search.value.toLowerCase();
        out = out.filter(s =>
            (s.exam_type?.name    || '').toLowerCase().includes(q) ||
            (s.course_class?.name || '').toLowerCase().includes(q)
        );
    }

    return sortRows(out, {
        getValue: (row, key) => {
            if (key === 'exam_type')    return row.exam_type?.name;
            if (key === 'course_class') return row.course_class?.name;
            if (key === 'weightage')    return Number(row.weightage ?? 0);
            return row[key];
        },
    });
});

// ─── Form state ─────────────────────────────────────────
const form = ref({
    exam_type_id: '',
    course_class_id: '',
    section_ids: [],
    weightage: 100,
    has_co_scholastic: false,
    scholastic_grading_system_id: '',
    co_scholastic_grading_system_id: '',
    subjects: [],
});

// ─── Computed ────────────────────────────────────────────
const selectedClass = computed(() =>
    props.classes.find(c => c.id == form.value.course_class_id)
);

const availableSections = computed(() =>
    selectedClass.value ? (selectedClass.value.sections || []) : []
);

const scholasticSystems = computed(() =>
    props.gradingSystems.filter(g => g.type === 'scholastic')
);

const coScholasticSystems = computed(() =>
    props.gradingSystems.filter(g => g.type === 'co_scholastic')
);

const scholasticSubjects = computed(() =>
    form.value.subjects.filter(s => !s.is_co_scholastic)
);

const coScholasticSubjects = computed(() =>
    form.value.subjects.filter(s => s.is_co_scholastic)
);

// ─── Watchers ────────────────────────────────────────────
// Suppressed during programmatic populate (openEdit) so we don't wipe
// fields the edit handler just set (section_ids, subjects with their
// marks/dates). The watcher's reset behaviour is correct for *user*
// class changes — just not for the initial form population.
let suppressClassWatcher = false;

watch(() => form.value.course_class_id, () => {
    if (suppressClassWatcher) return;
    form.value.section_ids = [];
    loadSubjects();
});

watch(() => form.value.has_co_scholastic, () => {
    if (suppressClassWatcher) return;
    if (form.value.course_class_id) loadSubjects();
});

// ─── Subjects loader ────────────────────────────────────
async function loadSubjects() {
    if (!form.value.course_class_id) {
        form.value.subjects = [];
        return;
    }
    loadingSubjects.value = true;
    try {
        const res = await axios.get('/school/exam-schedules-subjects', {
            params: {
                class_id: form.value.course_class_id,
                with_co_scholastic: form.value.has_co_scholastic ? 1 : 0,
            }
        });
        const incoming = res.data;
        // Preserve existing subject data if already filled
        form.value.subjects = incoming.map(sub => {
            const existing = form.value.subjects.find(s => s.subject_id === sub.id);
            return existing || {
                subject_id:       sub.id,
                subject_name:     sub.name,
                subject_code:     sub.code,
                is_co_scholastic: sub.is_co_scholastic,
                is_enabled:       true,
                exam_assessment_id: '',
                marks:            [],
                exam_date:        '',
                exam_time:        '',
                duration_minutes: '',
            };
        });
    } catch (e) {
        console.error('Failed to load subjects', e);
        toast.error('Could not load subjects for the selected class. Try again.');
    } finally {
        loadingSubjects.value = false;
    }
}

// ─── Section multi-select ───────────────────────────────
function toggleSection(id) {
    const arr = form.value.section_ids;
    const idx = arr.indexOf(id);
    if (idx === -1) arr.push(id);
    else arr.splice(idx, 1);
}

function allSectionsSelected() {
    return availableSections.value.length > 0 &&
        availableSections.value.every(s => form.value.section_ids.includes(s.id));
}

function toggleAllSections() {
    if (allSectionsSelected()) {
        form.value.section_ids = [];
    } else {
        form.value.section_ids = availableSections.value.map(s => s.id);
    }
}

// ─── Create / Edit ───────────────────────────────────────
function openCreate() {
    editingSchedule.value = null;
    form.value = {
        exam_type_id: '', course_class_id: '', section_ids: [],
        weightage: 100,
        has_co_scholastic: false,
        scholastic_grading_system_id: '',
        co_scholastic_grading_system_id: '',
        subjects: [],
    };
    errors.value = {};
    view.value = 'create';
}

function openEdit(schedule) {
    // Suppress watchers while we populate the form, then re-enable on the
    // next tick. Without this the course_class_id watcher would fire
    // (because it changed from '' to the schedule's class) and immediately
    // wipe section_ids + replace `subjects` with the API's class-level
    // list, losing the per-subject marks / dates / durations the user set
    // on the schedule.
    suppressClassWatcher = true;
    editingSchedule.value = schedule;
    form.value = {
        exam_type_id: schedule.exam_type_id,
        course_class_id: schedule.course_class_id,
        section_ids: schedule.sections.map(s => s.id),
        weightage: Number(schedule.weightage ?? 100),
        has_co_scholastic: schedule.has_co_scholastic,
        scholastic_grading_system_id: schedule.scholastic_grading_system_id || '',
        co_scholastic_grading_system_id: schedule.co_scholastic_grading_system_id || '',
        subjects: schedule.schedule_subjects.map(ss => ({
            subject_id:       ss.subject_id,
            subject_name:     ss.subject?.name || '',
            subject_code:     ss.subject?.code || '',
            is_co_scholastic: ss.is_co_scholastic,
            is_enabled:       ss.is_enabled,
            exam_assessment_id: ss.exam_assessment_id || '',
            marks:            (ss.mark_configs || []).map(m => ({
                exam_assessment_item_id: m.exam_assessment_item_id,
                max_marks:               m.max_marks,
                passing_marks:           m.passing_marks,
            })),
            exam_date:        ss.exam_date || '',
            exam_time:        ss.exam_time || '',
            duration_minutes: ss.duration_minutes || '',
        })),
    };
    errors.value = {};
    view.value = 'edit';
    // Re-enable watchers on the next microtask so any FUTURE user-driven
    // class change (uncommon on edit, but possible) still resets sections.
    nextTick(() => { suppressClassWatcher = false; });
}

function handleAssessmentChange(sub) {
    if (!sub.exam_assessment_id) {
        sub.marks = [];
        return;
    }
    const assessment = props.assessments.find(a => a.id == sub.exam_assessment_id);
    if (assessment && assessment.items) {
        sub.marks = assessment.items.map(item => {
            const existing = sub.marks?.find(m => m.exam_assessment_item_id === item.id);
            return existing || {
                exam_assessment_item_id: item.id,
                _item_name: item.name,
                max_marks: '',
                passing_marks: '',
            };
        });
    } else {
        sub.marks = [];
    }
}

// Friendly labels for the most common nested validation paths so the
// toast shows the user *which* row is bad, not the raw Laravel key.
function humanizeErrorKey(key, payload) {
    // Match: subjects.<i>.marks.<j>.<field>
    const subMark = key.match(/^subjects\.(\d+)\.marks\.(\d+)\.(\w+)/);
    if (subMark) {
        const [, subIdx, markIdx, field] = subMark;
        const subj = payload?.subjects?.[+subIdx];
        const subName = subj?.subject_name || subj?.subject_code || `subject #${+subIdx + 1}`;
        const fieldNice = ({
            max_marks: 'max marks',
            passing_marks: 'passing marks',
            exam_assessment_item_id: 'assessment item',
        })[field] || field;
        return `${subName} → assessment item #${+markIdx + 1}: ${fieldNice} is required or invalid`;
    }
    // Match: subjects.<i>.<field>
    const subFld = key.match(/^subjects\.(\d+)\.(\w+)/);
    if (subFld) {
        const [, subIdx, field] = subFld;
        const subj = payload?.subjects?.[+subIdx];
        const subName = subj?.subject_name || subj?.subject_code || `subject #${+subIdx + 1}`;
        const fieldNice = ({
            exam_assessment_id: 'assessment / mark scheme',
            exam_date: 'exam date',
            exam_time: 'exam time (must be HH:MM)',
            duration_minutes: 'duration (minutes)',
        })[field] || field;
        return `${subName}: ${fieldNice} is invalid`;
    }
    // Top-level
    return ({
        exam_type_id:    'Exam Type is required',
        course_class_id: 'Class is required',
        section_ids:     'Pick at least one section',
        weightage:       'Weightage must be 0–100',
        scholastic_grading_system_id:    'Scholastic Grading Scale is invalid',
        co_scholastic_grading_system_id: 'Co-Scholastic Grading Scale is invalid',
    })[key] || `${key} is invalid`;
}

function submit() {
    if (processing.value) return; // prevent double-submit while a request is in flight
    const payload = JSON.parse(JSON.stringify(form.value));
    processing.value = true;
    errors.value = {};
    const onError = (e) => {
        errors.value = e;
        if (e?.subjects && typeof e.subjects === 'string') {
            toast.error(e.subjects);
            return;
        }
        const keys = Object.keys(e || {});
        if (!keys.length) return;
        // Show up to 3 specific messages so user knows what to fix without
        // being buried in error spam from a 20-row form.
        const messages = keys.slice(0, 3).map(k => humanizeErrorKey(k, payload));
        if (keys.length > 3) {
            messages.push(`…and ${keys.length - 3} more`);
        }
        toast.error(messages.join('\n'));
    };
    if (view.value === 'edit') {
        router.put(`/school/exam-schedules/${editingSchedule.value.id}`, payload, {
            onSuccess: () => { view.value = 'list'; },
            onError,
            onFinish:  () => { processing.value = false; },
        });
    } else {
        router.post('/school/exam-schedules', payload, {
            onSuccess: () => { view.value = 'list'; },
            onError,
            onFinish:  () => { processing.value = false; },
        });
    }
}

async function deleteSchedule(id) {
    const ok = await confirm({
        title: 'Delete exam schedule?',
        message: 'This exam schedule will be permanently removed.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/exam-schedules/${id}`, {
        preserveScroll: true,
        onError: (errs) => {
            const msg = errs.error || 'Could not delete exam schedule.';
            toast.error(typeof msg === 'string' ? msg : 'Could not delete exam schedule.');
        },
    });
}

async function togglePublish(id, currentStatus) {
    const action = currentStatus === 'published' ? 'Unpublish' : 'Publish';
    const ok = await confirm({
        title: `${action} schedule?`,
        message: `Are you sure you want to ${action.toLowerCase()} this schedule?`,
        confirmLabel: action,
    });
    if (!ok) return;
    router.post(`/school/exam-schedules/${id}/toggle-publish`, {}, {
        preserveScroll: true,
        onError: () => toast.error(`Could not ${action.toLowerCase()} the schedule.`),
    });
}
</script>

<template>
    <SchoolLayout title="Exam Schedule">

        <!-- ══════════ LIST ══════════ -->
        <div v-if="view === 'list'">
            <PageHeader title="Exam Schedules" subtitle="Manage exam timetables per class, section and exam type.">
                <template #actions>
                    <Button @click="openCreate">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Exam Schedule
                    </Button>
                </template>
            </PageHeader>

            <!-- Filters -->
            <FilterBar :active="filtersActive" @clear="clearFilters">
                <div class="fb-search fb-grow">
                    <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input v-model="search" type="search" placeholder="Search by exam type or class…">
                </div>
                <select v-model="filterExamTypeId" style="width:170px;">
                    <option value="">All Exam Types</option>
                    <option v-for="t in examTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <select v-model="filterClassId" style="width:160px;">
                    <option value="">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="filterSectionId" :disabled="!filterClassId" style="width:160px;">
                    <option value="">{{ filterClassId ? 'All Sections' : 'Pick a class first' }}</option>
                    <option v-for="sec in filterSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                </select>
                <select v-model="filterStatus" style="width:140px;">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </FilterBar>

            <div class="card">
                <div class="card-body" style="padding:0; overflow-x:auto;">
                    <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                        <thead>
                            <tr>
                                <SortableTh sort-key="exam_type">Exam Type</SortableTh>
                                <SortableTh sort-key="course_class">Class</SortableTh>
                                <th>Sections</th>
                                <SortableTh sort-key="weightage" align="center">Weightage</SortableTh>
                                <th>Subjects</th>
                                <SortableTh sort-key="status">Status</SortableTh>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in filteredSchedules" :key="s.id">
                                <td><strong>{{ s.exam_type?.name }}</strong></td>
                                <td>{{ s.course_class?.name }}</td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <span v-for="sec in s.sections" :key="sec.id" class="badge badge-blue">{{ sec.name }}</span>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:600;color:#5b21b6;">{{ Number(s.weightage ?? 0) }}%</td>
                                <td>{{ s.schedule_subjects?.length || 0 }} subjects</td>
                                <td>
                                    <span class="badge" :class="s.status === 'published' ? 'badge-green' : 'badge-gray'">
                                        {{ s.status }}
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <Button size="sm" :variant="s.status === 'published' ? 'secondary' : 'primary'" @click="togglePublish(s.id, s.status)">
                                        {{ s.status === 'published' ? 'Unpublish' : 'Publish' }}
                                    </Button>
                                    <Button variant="secondary" size="sm" @click="openEdit(s)" class="ml-1.5">Edit</Button>
                                    <Button variant="danger" size="sm" @click="deleteSchedule(s.id)" class="ml-1.5">Delete</Button>
                                </td>
                            </tr>
                            <tr v-if="!filteredSchedules.length">
                                <td colspan="7" style="text-align:center;padding:32px;color:#94a3b8;">
                                    {{ filtersActive ? 'No exam schedules match the filters.' : 'No exam schedules yet.' }}
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- ══════════ FORM ══════════ -->
        <div v-else>
            <PageHeader>
                <template #title>
                    <h1 class="page-header-title">{{ view === 'edit' ? 'Edit Exam Schedule' : 'Create Exam Schedule' }}</h1>
                </template>
                <template #actions>
                    <Button variant="secondary" size="sm" type="button" @click="view = 'list'">
                        ← All Schedules
                    </Button>
                </template>
            </PageHeader>

            <form @submit.prevent="submit">

                <!-- ── STEP 1: Exam Type + Class ── -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <span class="card-title">Basic Configuration</span>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-3">
                            <div class="form-field">
                                <label>Exam Type <span style="color:#ef4444">*</span></label>
                                <select v-model="form.exam_type_id" required>
                                    <option value="">Select Exam Type</option>
                                    <option v-for="t in examTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <div class="form-error" v-if="errors.exam_type_id">{{ errors.exam_type_id }}</div>
                            </div>
                            <div class="form-field">
                                <label>Class <span style="color:#ef4444">*</span></label>
                                <select v-model="form.course_class_id" required>
                                    <option value="">Select Class</option>
                                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <div class="form-error" v-if="errors.course_class_id">{{ errors.course_class_id }}</div>
                            </div>
                            <div class="form-field">
                                <label>Sections <span style="color:#ef4444">*</span></label>
                                <div v-if="!form.course_class_id" class="section-placeholder">Select a class first</div>
                                <div v-else class="section-chips-wrap">
                                    <button type="button"
                                        :class="['section-chip', allSectionsSelected() ? 'section-chip--all' : '']"
                                        @click="toggleAllSections">All</button>
                                    <button type="button"
                                        v-for="sec in availableSections" :key="sec.id"
                                        :class="['section-chip', form.section_ids.includes(sec.id) ? 'section-chip--on' : '']"
                                        @click="toggleSection(sec.id)">{{ sec.name }}</button>
                                </div>
                                <div class="form-error" v-if="errors.section_ids">{{ errors.section_ids }}</div>
                            </div>
                        </div>

                        <div class="form-row" style="grid-template-columns:240px 1fr;align-items:start;margin-top:12px;">
                            <div class="form-field">
                                <label>Weightage (%) <span style="color:#ef4444">*</span></label>
                                <input v-model.number="form.weightage" type="number" step="0.01" min="0" max="100" required />
                                <div class="form-error" v-if="errors.weightage">{{ errors.weightage }}</div>
                            </div>
                            <div class="form-field" style="align-self:end;">
                                <p style="font-size:0.75rem;color:#64748b;margin:0 0 8px;line-height:1.4;">
                                    Share of the cumulative report this exam contributes for this class.
                                    E.g. PT1 = 10%, Half-Yearly = 30%. Set per class so seniors and juniors can weight differently.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 2: Grading + Co-Scholastic Toggle ── -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                        <span class="card-title">Grading Configuration</span>
                        <label class="toggle-label">
                            <span>Include Co-Scholastic Subjects</span>
                            <div class="toggle-wrap">
                                <input type="checkbox" v-model="form.has_co_scholastic" class="sr-only peer" />
                                <div class="toggle-track peer-checked:bg-indigo-600"></div>
                                <div class="toggle-thumb peer-checked:translate-x-5"></div>
                            </div>
                        </label>
                    </div>
                    <div class="card-body">
                        <div class="form-row form-row-2">
                            <div class="form-field">
                                <label>Scholastic Grading Scale</label>
                                <select v-model="form.scholastic_grading_system_id">
                                    <option value="">None</option>
                                    <option v-for="g in scholasticSystems" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                            </div>
                            <div class="form-field" v-if="form.has_co_scholastic">
                                <label>Co-Scholastic Grading Scale</label>
                                <select v-model="form.co_scholastic_grading_system_id">
                                    <option value="">None</option>
                                    <option v-for="g in coScholasticSystems" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 3: Subjects ── -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <span class="card-title">Subject Configuration</span>
                    </div>
                    <div class="card-body">

                        <div v-if="!form.course_class_id" class="empty-msg">
                            Please select a class to load subjects.
                        </div>
                        <div v-else-if="loadingSubjects" class="empty-msg">
                            <svg class="animate-spin w-5 h-5" style="color:#6366f1;margin:0 auto 4px;" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                            Loading subjects...
                        </div>
                        <div v-else-if="form.subjects.length === 0" class="empty-msg">
                            No subjects found for this class.
                        </div>

                        <template v-else>
                            <!-- Scholastic -->
                            <div class="section-heading" style="margin-bottom:8px;">Scholastic Subjects</div>
                            <div style="overflow-x:auto;margin-bottom:24px;">
                                <Table class="subject-table">
                                    <thead>
                                        <tr>
                                            <th style="width:20%">Subject</th>
                                            <th style="width:18%">Assessment Pattern</th>
                                            <th style="width:25%">Marks Configuration</th>
                                            <th style="width:13%">Exam Date</th>
                                            <th style="width:10%">Time</th>
                                            <th style="width:8%">Duration (m)</th>
                                            <th style="width:6%;text-align:center;">Enable</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(sub, sidx) in scholasticSubjects" :key="sub.subject_id"
                                            :style="!sub.is_enabled ? 'opacity:0.4' : ''">
                                            <td>
                                                <div style="font-weight:600;">{{ sub.subject_name }}</div>
                                                <div style="font-size:.75rem;color:#94a3b8;">{{ sub.subject_code }}</div>
                                            </td>
                                            <td>
                                                <select v-model="sub.exam_assessment_id" :disabled="!sub.is_enabled" class="sub-input" @change="handleAssessmentChange(sub)">
                                                    <option value="">No Assessment</option>
                                                    <option v-for="a in assessments" :key="a.id" :value="a.id">{{ a.name }}</option>
                                                </select>
                                                <div class="form-error" style="margin-top:4px;" v-if="errors[`subjects.${sidx}.exam_assessment_id`]">{{ errors[`subjects.${sidx}.exam_assessment_id`] }}</div>
                                            </td>
                                            <td>
                                                <div v-if="sub.marks && sub.marks.length > 0" style="display:flex;flex-direction:column;gap:6px;">
                                                    <div v-for="mark in sub.marks" :key="mark.exam_assessment_item_id" style="display:flex;gap:6px;align-items:center;font-size:.75rem;">
                                                        <span style="width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;" :title="mark._item_name || 'Item'">{{ mark._item_name || 'Item' }}</span>
                                                        <input type="number" v-model="mark.max_marks" :disabled="!sub.is_enabled" placeholder="Max" min="0" step="0.5" class="sub-input" style="width:60px;padding:3px 6px;" title="Maximum Marks" required />
                                                        <input type="number" v-model="mark.passing_marks" :disabled="!sub.is_enabled" placeholder="Pass" min="0" step="0.5" class="sub-input" style="width:60px;padding:3px 6px;" title="Passing Marks" required />
                                                    </div>
                                                </div>
                                                <div v-else style="font-size:.75rem;color:#94a3b8;font-style:italic;">Select pattern to configure marks</div>
                                            </td>
                                            <td><input type="date" v-model="sub.exam_date" :disabled="!sub.is_enabled" class="sub-input" /></td>
                                            <td><input type="time" v-model="sub.exam_time" :disabled="!sub.is_enabled" class="sub-input" /></td>
                                            <td><input type="number" v-model="sub.duration_minutes" :disabled="!sub.is_enabled" placeholder="180" min="1" class="sub-input" /></td>
                                            <td style="text-align:center;">
                                                <button type="button" @click="sub.is_enabled = !sub.is_enabled"
                                                    :class="['tog', sub.is_enabled ? 'tog--on' : 'tog--off']">
                                                    {{ sub.is_enabled ? 'ON' : 'OFF' }}
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="scholasticSubjects.length === 0">
                                            <td colspan="7" style="text-align:center;padding:16px;color:#94a3b8;font-size:.875rem;">No scholastic subjects.</td>
                                        </tr>
                                    </tbody>
                                </Table>
                            </div>

                            <!-- Co-Scholastic -->
                            <template v-if="form.has_co_scholastic && coScholasticSubjects.length > 0">
                                <div class="section-heading" style="margin-bottom:8px;">Co-Scholastic Subjects</div>
                                <div style="overflow-x:auto;">
                                    <Table class="subject-table">
                                        <thead>
                                            <tr>
                                                <th style="width:20%">Subject</th>
                                                <th style="width:18%">Assessment Pattern</th>
                                                <th style="width:25%">Marks Configuration</th>
                                                <th style="width:13%">Exam Date</th>
                                                <th style="width:10%">Time</th>
                                                <th style="width:8%">Duration (m)</th>
                                                <th style="width:6%;text-align:center;">Enable</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(sub, sidx) in coScholasticSubjects" :key="sub.subject_id"
                                                :style="!sub.is_enabled ? 'opacity:0.4' : ''">
                                                <td>
                                                    <div style="font-weight:600;">{{ sub.subject_name }}</div>
                                                    <div style="font-size:.75rem;color:#94a3b8;">{{ sub.subject_code }}</div>
                                                </td>
                                                <td>
                                                    <select v-model="sub.exam_assessment_id" :disabled="!sub.is_enabled" class="sub-input" @change="handleAssessmentChange(sub)">
                                                        <option value="">No Assessment</option>
                                                        <option v-for="a in assessments" :key="a.id" :value="a.id">{{ a.name }}</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div v-if="sub.marks && sub.marks.length > 0" style="display:flex;flex-direction:column;gap:6px;">
                                                        <div v-for="mark in sub.marks" :key="mark.exam_assessment_item_id" style="display:flex;gap:6px;align-items:center;font-size:.75rem;">
                                                            <span style="width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;" :title="mark._item_name || 'Item'">{{ mark._item_name || 'Item' }}</span>
                                                            <input type="number" v-model="mark.max_marks" :disabled="!sub.is_enabled" placeholder="Max" min="0" step="0.5" class="sub-input" style="width:60px;padding:3px 6px;" title="Maximum Marks" required />
                                                            <input type="number" v-model="mark.passing_marks" :disabled="!sub.is_enabled" placeholder="Pass" min="0" step="0.5" class="sub-input" style="width:60px;padding:3px 6px;" title="Passing Marks" required />
                                                        </div>
                                                    </div>
                                                    <div v-else style="font-size:.75rem;color:#94a3b8;font-style:italic;">Select pattern to configure marks</div>
                                                </td>
                                                <td><input type="date" v-model="sub.exam_date" :disabled="!sub.is_enabled" class="sub-input" /></td>
                                                <td><input type="time" v-model="sub.exam_time" :disabled="!sub.is_enabled" class="sub-input" /></td>
                                                <td><input type="number" v-model="sub.duration_minutes" :disabled="!sub.is_enabled" placeholder="180" min="1" class="sub-input" /></td>
                                                <td style="text-align:center;">
                                                    <button type="button" @click="sub.is_enabled = !sub.is_enabled"
                                                        :class="['tog', sub.is_enabled ? 'tog--on' : 'tog--off']">
                                                        {{ sub.is_enabled ? 'ON' : 'OFF' }}
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </Table>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- Footer -->
                <div class="form-footer">
                    <Button variant="secondary" type="button" @click="view = 'list'">Cancel</Button>
                    <Button type="submit" :loading="processing">
                        Save Schedule
                    </Button>
                </div>

            </form>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* Form page header */
.form-page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid #e2e8f0; }
.form-page-title  { font-size:1.1rem; font-weight:800; color:#0f172a; }

/* Section cards */
.section-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px 24px; margin-bottom:16px; }
.section-card-title { font-size:.875rem; font-weight:700; color:#374151; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; }
.req { color:#ef4444; }

/* Section chip selector */
.section-chips-wrap { display:flex; flex-wrap:wrap; gap:6px; }
.section-chip {
    padding:4px 12px; border-radius:20px; font-size:.8125rem; font-weight:600;
    border:1.5px solid #cbd5e1; background:#f8fafc; color:#475569;
    cursor:pointer; transition:all .15s;
}
.section-chip--on, .section-chip--all {
    background:#1169cd; color:#fff; border-color:#1169cd;
}
.section-placeholder { font-size:.8rem; color:#94a3b8; padding:8px 0; }

/* Toggle */
.toggle-label { display:flex; align-items:center; gap:10px; cursor:pointer; font-size:.8125rem; font-weight:600; color:#374151; }
.toggle-wrap { position:relative; width:40px; height:22px; }
.toggle-track {
    position:absolute; inset:0; background:#cbd5e1; border-radius:999px; transition:background .2s;
}
.toggle-thumb {
    position:absolute; top:2px; left:2px; width:18px; height:18px;
    background:#fff; border-radius:50%; transition:transform .2s;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
input:checked ~ .toggle-track { background:#4f46e5; }
input:checked ~ .toggle-thumb { transform:translateX(18px); }
.peer:checked ~ .toggle-track { background:#4f46e5; }
.peer:checked ~ .toggle-thumb { transform:translateX(18px); }

/* Subject table */
.subject-group-title { font-size:.8rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.04em; margin-bottom:8px; }
.subject-table td, .subject-table th { padding:8px 10px !important; vertical-align:middle; }
.sub-input {
    width:100%; padding:6px 8px; font-size:.8125rem; color:#1e293b;
    background:#fff; border:1.5px solid #cbd5e1; border-radius:6px; outline:none;
    transition:border-color .15s; font-family:inherit;
}
.sub-input:focus  { border-color:#4f46e5; box-shadow:0 0 0 2px rgba(79,70,229,.15); }
.sub-input:disabled { background:#f8fafc; color:#94a3b8; cursor:not-allowed; }

/* Enable toggle button */
.tog {
    padding:3px 10px; font-size:.7rem; font-weight:700; border-radius:20px;
    border:none; cursor:pointer; transition:all .15s; letter-spacing:.03em;
}
.tog--on  { background:#10b981; color:#fff; }
.tog--off { background:#e2e8f0; color:#94a3b8; }

/* Empty */
.empty-msg { text-align:center; padding:32px; color:#94a3b8; font-size:.875rem; }

/* Footer */
.form-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0; }
</style>
