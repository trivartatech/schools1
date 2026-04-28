<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { useForm, router } from '@inertiajs/vue3';
import { ref, reactive, computed, watchEffect } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({
    records: Object,
    students: Array,
    classes: Array,
    sections: Array,
    categories: Array,
    summary: Object,
    filters: Object,
});

const CONSEQUENCES = ['none', 'warning', 'detention', 'parent_call', 'suspension', 'expulsion'];
const TODAY = new Date().toISOString().split('T')[0];

// ── Page view toggle ───────────────────────────────────────────────
const view = ref('list');

// ── List filters ───────────────────────────────────────────────────
const filterStatus   = ref(props.filters?.status ?? '');
const filterSeverity = ref(props.filters?.severity ?? '');
const filterStudent  = ref(props.filters?.student_id ?? '');

const applyFilters = () => {
    router.get('/school/disciplinary', {
        status: filterStatus.value,
        severity: filterSeverity.value,
        student_id: filterStudent.value,
    }, { preserveScroll: true, replace: true });
};

const clearFilters = () => {
    filterStatus.value = '';
    filterSeverity.value = '';
    filterStudent.value = '';
    applyFilters();
};

// ── Category management ────────────────────────────────────────────
const showCatModal  = ref(false);
const editingCatId  = ref(null);
const editingCatName = ref('');

const catAddForm = useForm({ name: '', short_code: '' });
const catEditForm = useForm({ name: '', short_code: '' });

const startEditCat = (cat) => {
    editingCatId.value = cat.id;
    editingCatName.value = cat.name;
    catEditForm.name = cat.name;
    catEditForm.short_code = cat.short_code || '';
};
const cancelEditCat = () => { editingCatId.value = null; catEditForm.reset(); };

const saveCatEdit = (cat) => {
    catEditForm.put(`/school/disciplinary/categories/${cat.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingCatId.value = null; catEditForm.reset(); },
    });
};

const addCat = () => {
    catAddForm.post('/school/disciplinary/categories', {
        preserveScroll: true,
        onSuccess: () => catAddForm.reset(),
    });
};

const deleteCat = async (id) => {
    const ok = await confirm({
        title: 'Delete category?',
        message: 'This category will be removed permanently.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/disciplinary/categories/${id}`, { preserveScroll: true });
};

// ── Add-by-class state ─────────────────────────────────────────────
const browseClass   = ref('');
const browseSection = ref('');

const filteredSections = computed(() =>
    browseClass.value ? props.sections.filter(s => s.course_class_id == browseClass.value) : []
);

const browsedStudents = computed(() => {
    if (!browseClass.value) return [];
    return props.students.filter(s => {
        const h = s.current_academic_history;
        if (!h || h.class_id != browseClass.value) return false;
        if (browseSection.value && h.section_id != browseSection.value) return false;
        return true;
    });
});

const bulkForm = useForm({
    assignments: [],   // [{ student_id, category }] (severity/consequence are merged in on submit)
    incident_date: TODAY,
    action_taken: '',
});

const studentMeta = reactive({}); // { [student_id]: { severity, consequence } }

watchEffect(() => {
    browsedStudents.value.forEach(s => {
        if (!studentMeta[s.id]) studentMeta[s.id] = { severity: 'minor', consequence: '' };
    });
});

const isAssigned = (sid, cat) =>
    bulkForm.assignments.some(a => a.student_id === sid && a.category === cat);

const toggleAssignment = (sid, cat) => {
    const idx = bulkForm.assignments.findIndex(a => a.student_id === sid && a.category === cat);
    if (idx >= 0) bulkForm.assignments.splice(idx, 1);
    else bulkForm.assignments.push({ student_id: sid, category: cat });
};

const toggleCategoryForAll = (cat) => {
    const allHave = browsedStudents.value.length > 0 &&
        browsedStudents.value.every(s => isAssigned(s.id, cat));
    if (allHave) {
        bulkForm.assignments = bulkForm.assignments.filter(a => a.category !== cat
            || !browsedStudents.value.some(s => s.id === a.student_id));
    } else {
        browsedStudents.value.forEach(s => {
            if (!isAssigned(s.id, cat)) bulkForm.assignments.push({ student_id: s.id, category: cat });
        });
    }
};

const allHaveCategory = (cat) =>
    browsedStudents.value.length > 0 &&
    browsedStudents.value.every(s => isAssigned(s.id, cat));

const recordsPreviewCount = computed(() => bulkForm.assignments.length);
const distinctStudentCount = computed(() =>
    new Set(bulkForm.assignments.map(a => a.student_id)).size);

const openAdd = () => {
    browseClass.value = ''; browseSection.value = '';
    bulkForm.reset();
    view.value = 'add';
};

const backToList = () => { view.value = 'list'; bulkForm.reset(); };

const submitBulk = () => {
    bulkForm
        .transform(data => ({
            ...data,
            assignments: data.assignments.map(a => ({
                ...a,
                severity:    studentMeta[a.student_id]?.severity    ?? 'minor',
                consequence: studentMeta[a.student_id]?.consequence ?? '',
            })),
        }))
        .post('/school/disciplinary/bulk', {
            preserveScroll: true,
            onSuccess: () => { bulkForm.reset(); view.value = 'list'; },
        });
};

// ── Edit record modal ──────────────────────────────────────────────
const showEdit   = ref(false);
const editRecord = ref(null);

const form = useForm({
    student_id: '', incident_date: TODAY,
    category: '', severity: 'minor', description: '', action_taken: '',
    status: 'open', consequence: '', consequence_from: '', consequence_to: '',
    parent_notified: false, student_statement: '', notes: '',
});

const openEdit = (r) => {
    editRecord.value = r;
    Object.assign(form, {
        student_id: r.student_id, incident_date: r.incident_date?.slice(0, 10),
        category: r.category, severity: r.severity, description: r.description,
        action_taken: r.action_taken ?? '', status: r.status,
        consequence: r.consequence ?? '',
        consequence_from: r.consequence_from?.slice(0, 10) ?? '',
        consequence_to: r.consequence_to?.slice(0, 10) ?? '',
        parent_notified: r.parent_notified,
        student_statement: r.student_statement ?? '', notes: r.notes ?? '',
    });
    showEdit.value = true;
};
const closeEdit = () => { showEdit.value = false; editRecord.value = null; };
const submitEdit = () => {
    form.put(`/school/disciplinary/${editRecord.value.id}`, { preserveScroll: true, onSuccess: closeEdit });
};

const deleteRecord = async (id) => {
    const ok = await confirm({
        title: 'Delete record?',
        message: 'This disciplinary record will be permanently removed.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/disciplinary/${id}`, { preserveScroll: true });
};

const severityColor = { minor: '#d97706', moderate: '#f97316', major: '#dc2626' };
const statusBadge   = { open: 'badge-amber', under_review: 'badge-blue', resolved: 'badge-green', escalated: 'badge-red' };
import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmtDate } = useFormat();

const categoryByName = computed(() => {
    const m = {};
    (props.categories || []).forEach(c => { m[c.name] = c; });
    return m;
});
const fmtCategory = (name) => {
    if (!name) return '—';
    const c = categoryByName.value[name];
    return c?.short_code ? `${name} (${c.short_code})` : name;
};

const statCards = computed(() => [
    { label: 'Total Records',    value: props.summary.total,      color: 'accent' },
    { label: 'Open Cases',       value: props.summary.open,       color: 'warning' },
    { label: 'This Month',       value: props.summary.this_month, color: 'purple' },
    { label: 'Major Incidents',  value: props.summary.major,      color: 'danger' },
]);

// ── Table sorting ────────────────────────────────────────────────────────────
const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('incident_date', 'desc');
const sortedRecords = computed(() => sortRows(props.records.data || [], {
    getValue: (row, key) => {
        if (key === 'student_name') return `${row.student?.first_name ?? ''} ${row.student?.last_name ?? ''}`.trim();
        if (key === 'reported_by') return row.reported_by?.name ?? '';
        return row[key];
    },
}));
</script>

<template>
    <SchoolLayout title="Disciplinary Records">

        <!-- LIST VIEW -->
        <template v-if="view === 'list'">
            <PageHeader title="Disciplinary Records" subtitle="Track and manage student disciplinary incidents.">
                <template #actions>
                    <Button variant="secondary" @click="showCatModal = true">Manage Categories</Button>
                    <Button @click="openAdd">+ Add Record</Button>
                </template>
            </PageHeader>

            <!-- Summary cards -->
            <StatsRow :cols="4" :stats="statCards" />

            <!-- Filter bar -->
            <FilterBar
                :active="!!(filterStatus || filterSeverity || filterStudent)"
                @clear="clearFilters"
            >
                <select v-model="filterStatus" @change="applyFilters" style="width:140px;">
                    <option value="">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="under_review">Under Review</option>
                    <option value="resolved">Resolved</option>
                    <option value="escalated">Escalated</option>
                </select>
                <select v-model="filterSeverity" @change="applyFilters" style="width:140px;">
                    <option value="">All Severities</option>
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="major">Major</option>
                </select>
                <select v-model="filterStudent" @change="applyFilters" style="width:200px;">
                    <option value="">All Students</option>
                    <option v-for="s in students" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }}</option>
                </select>
            </FilterBar>

            <!-- Records table -->
            <div class="card" style="overflow:hidden;">
                <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                    <thead>
                        <tr>
                            <SortableTh sort-key="student_name">Student</SortableTh>
                            <SortableTh sort-key="incident_date">Date</SortableTh>
                            <SortableTh sort-key="category">Category</SortableTh>
                            <SortableTh sort-key="severity">Severity</SortableTh>
                            <SortableTh sort-key="status">Status</SortableTh>
                            <SortableTh sort-key="consequence">Consequence</SortableTh>
                            <SortableTh sort-key="reported_by">Reported By</SortableTh>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in sortedRecords" :key="r.id">
                            <td>
                                <div style="font-weight:600;color:#1e293b;">{{ r.student?.first_name }} {{ r.student?.last_name }}</div>
                                <div style="font-size:.72rem;color:#94a3b8;">{{ r.student?.admission_no }}</div>
                            </td>
                            <td style="white-space:nowrap;">{{ fmtDate(r.incident_date) }}</td>
                            <td>{{ fmtCategory(r.category) }}</td>
                            <td>
                                <span class="sev-badge" :style="{ background: severityColor[r.severity] + '18', color: severityColor[r.severity] }">{{ r.severity }}</span>
                            </td>
                            <td><span class="badge" :class="statusBadge[r.status]" style="text-transform:capitalize;">{{ r.status?.replace('_', ' ') }}</span></td>
                            <td style="text-transform:capitalize;">{{ r.consequence ? r.consequence.replace('_', ' ') : '—' }}</td>
                            <td>{{ r.reported_by?.name || '—' }}</td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;">
                                    <Button variant="secondary" size="xs" @click="openEdit(r)">Edit</Button>
                                    <Button variant="danger" size="xs" @click="deleteRecord(r.id)">Del</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sortedRecords.length">
                            <td colspan="8" style="padding:0;">
                                <EmptyState
                                    title="No records found"
                                    description="No disciplinary records match the selected filters."
                                    action-label="+ Add Record"
                                    @action="openAdd"
                                />
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </template>

        <!-- ADD VIEW -->
        <template v-else-if="view === 'add'">
            <PageHeader
                title="Add Disciplinary Incident"
                back-href="#"
                back-label="Back to Records"
                @back="backToList"
            />
            <div style="margin-bottom:16px;">
                <Button variant="secondary" size="sm" @click="backToList">← Back to Records</Button>
            </div>

            <!-- Class / Section filter -->
            <div class="card" style="margin-bottom:16px;">
                <div class="card-body add-filter-bar">
                    <div class="add-filter-field">
                        <label class="add-filter-label">Class</label>
                        <select v-model="browseClass" @change="browseSection = ''; bulkForm.assignments = [];">
                            <option value="">— Select Class —</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="add-filter-field">
                        <label class="add-filter-label">Section</label>
                        <select v-model="browseSection" :disabled="!browseClass" @change="bulkForm.assignments = [];">
                            <option value="">All Sections</option>
                            <option v-for="s in filteredSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div v-if="browseClass && browsedStudents.length" class="student-pill">
                        {{ browsedStudents.length }} student{{ browsedStudents.length !== 1 ? 's' : '' }}
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <EmptyState
                v-if="!browseClass"
                variant="compact"
                title="Select a class to view students"
                description="Choose a class above to begin adding incidents."
            />
            <EmptyState
                v-else-if="!browsedStudents.length"
                variant="compact"
                title="No students found"
                description="No students match the selected class / section."
            />

            <template v-else>
                <!-- Common incident fields -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-body" style="padding:14px 18px;">
                        <div class="bulk-section-label">Incident Details (applied to every record)</div>
                        <div class="bulk-grid">
                            <div class="form-field">
                                <label>Date *</label>
                                <input v-model="bulkForm.incident_date" type="date" required />
                                <span v-if="bulkForm.errors.incident_date" class="field-error">{{ bulkForm.errors.incident_date }}</span>
                            </div>
                            <div class="form-field bulk-grid-full">
                                <label>Action Taken</label>
                                <input v-model="bulkForm.action_taken" type="text" placeholder="Optional — what action was taken?" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student list with inline severity / consequence / category chips -->
                <div class="card" style="overflow:hidden;">
                    <div class="student-list-header">
                        <span class="col-num">#</span>
                        <span class="col-student">Student</span>
                        <span class="col-sev">Severity *</span>
                        <span class="col-cons">Consequence</span>
                        <span class="col-chips">
                            <span class="chips-header-label">Categories — click a code to apply / clear for all visible students</span>
                            <span class="chips-header-row">
                                <button v-for="c in categories" :key="c.id" type="button"
                                        class="cat-chip cat-chip-header"
                                        :class="{ 'cat-chip-selected': allHaveCategory(c.name) }"
                                        @click="toggleCategoryForAll(c.name)">
                                    {{ c.short_code || c.name }}
                                </button>
                            </span>
                        </span>
                    </div>
                    <div class="student-list">
                        <div v-for="(s, idx) in browsedStudents" :key="s.id"
                             :class="['student-item', { 'item-active': bulkForm.assignments.some(a => a.student_id === s.id) }]">
                            <span class="col-num item-num">{{ idx + 1 }}</span>
                            <span class="col-student item-info">
                                <span class="item-name">{{ s.first_name }} {{ s.last_name }}</span>
                                <span class="item-adm">{{ s.admission_no }}</span>
                            </span>
                            <span class="col-sev">
                                <select v-if="studentMeta[s.id]" v-model="studentMeta[s.id].severity" class="row-select">
                                    <option value="minor">Minor</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="major">Major</option>
                                </select>
                            </span>
                            <span class="col-cons">
                                <select v-if="studentMeta[s.id]" v-model="studentMeta[s.id].consequence" class="row-select">
                                    <option value="">— None —</option>
                                    <option v-for="c in CONSEQUENCES" :key="c" :value="c" style="text-transform:capitalize;">{{ c.replace('_', ' ') }}</option>
                                </select>
                            </span>
                            <span class="col-chips">
                                <button v-for="c in categories" :key="c.id" type="button"
                                        class="cat-chip"
                                        :class="{ 'cat-chip-selected': isAssigned(s.id, c.name) }"
                                        @click="toggleAssignment(s.id, c.name)">
                                    {{ c.short_code || c.name }}
                                </button>
                            </span>
                        </div>
                    </div>
                    <span v-if="bulkForm.errors.assignments" class="field-error" style="display:block;padding:10px 18px;">{{ bulkForm.errors.assignments }}</span>
                </div>

                <!-- Sticky save bar -->
                <div class="bulk-save-bar">
                    <div class="bulk-summary">
                        <strong>{{ distinctStudentCount }}</strong> student{{ distinctStudentCount !== 1 ? 's' : '' }}
                        · <strong>{{ recordsPreviewCount }}</strong> record{{ recordsPreviewCount !== 1 ? 's' : '' }} to create
                    </div>
                    <div style="display:flex;gap:10px;">
                        <Button variant="secondary" type="button" @click="backToList">Cancel</Button>
                        <Button @click="submitBulk"
                                :loading="bulkForm.processing"
                                :disabled="!recordsPreviewCount">
                            Save Records
                        </Button>
                    </div>
                </div>
            </template>
        </template>

        <!-- CATEGORY MANAGEMENT MODAL -->
        <Modal v-model:open="showCatModal" title="Manage Categories" size="md">
            <p style="font-size:.78rem;color:#94a3b8;margin:0 0 14px;">Add, rename or remove incident categories for your school.</p>
            <!-- Category list -->
            <div class="cat-list">
                <div v-for="cat in categories" :key="cat.id" class="cat-row">
                    <template v-if="editingCatId === cat.id">
                        <input
                            v-model="catEditForm.name"
                            class="cat-edit-input cat-edit-name"
                            placeholder="Category name"
                            @keyup.enter="saveCatEdit(cat)"
                            @keyup.escape="cancelEditCat"
                            autofocus
                        />
                        <input
                            v-model="catEditForm.short_code"
                            class="cat-edit-input cat-edit-code"
                            placeholder="CODE"
                            maxlength="20"
                            @keyup.enter="saveCatEdit(cat)"
                            @keyup.escape="cancelEditCat"
                        />
                        <button class="cat-save-btn" @click="saveCatEdit(cat)" :disabled="catEditForm.processing">Save</button>
                        <button class="cat-icon-btn" @click="cancelEditCat" title="Cancel">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </template>
                    <template v-else>
                        <span class="cat-dot"></span>
                        <span class="cat-name">{{ cat.name }}</span>
                        <span v-if="cat.short_code" class="cat-code">{{ cat.short_code }}</span>
                        <div class="cat-actions">
                            <button class="cat-icon-btn" @click="startEditCat(cat)" title="Rename">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button class="cat-icon-btn cat-delete-btn" @click="deleteCat(cat.id)" title="Delete">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <div v-if="!categories.length" style="text-align:center;padding:24px;color:#94a3b8;font-size:.85rem;">No categories yet.</div>
            </div>

            <!-- Add new category -->
            <div class="cat-add-row">
                <input
                    v-model="catAddForm.name"
                    class="cat-add-input cat-add-name"
                    placeholder="New category name…"
                    @keyup.enter="addCat"
                />
                <input
                    v-model="catAddForm.short_code"
                    class="cat-add-input cat-add-code"
                    placeholder="CODE"
                    maxlength="20"
                    @keyup.enter="addCat"
                />
                <Button size="sm" @click="addCat" :loading="catAddForm.processing">Add</Button>
            </div>
            <span v-if="catAddForm.errors.name" class="field-error">{{ catAddForm.errors.name }}</span>
            <span v-if="catAddForm.errors.short_code" class="field-error">{{ catAddForm.errors.short_code }}</span>
            <template #footer>
                <Button variant="secondary" @click="showCatModal = false">Done</Button>
            </template>
        </Modal>

        <!-- Edit Record Modal -->
        <Modal v-model:open="showEdit" :title="`Edit Record — ${editRecord?.student?.first_name ?? ''} ${editRecord?.student?.last_name ?? ''}`" size="lg">
            <form @submit.prevent="submitEdit" id="edit-record-form">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-field">
                        <label>Incident Date *</label>
                        <input v-model="form.incident_date" type="date" required />
                    </div>
                    <div class="form-field">
                        <label>Category *</label>
                        <select v-model="form.category" required>
                            <option value="">Select</option>
                            <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}{{ c.short_code ? ` (${c.short_code})` : '' }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Severity *</label>
                        <select v-model="form.severity" required>
                            <option value="minor">Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="major">Major</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Status *</label>
                        <select v-model="form.status">
                            <option value="open">Open</option>
                            <option value="under_review">Under Review</option>
                            <option value="resolved">Resolved</option>
                            <option value="escalated">Escalated</option>
                        </select>
                    </div>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Description *</label>
                        <textarea v-model="form.description" rows="3" required></textarea>
                    </div>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Action Taken</label>
                        <textarea v-model="form.action_taken" rows="2"></textarea>
                    </div>
                    <div class="form-field">
                        <label>Consequence</label>
                        <select v-model="form.consequence">
                            <option value="">— None —</option>
                            <option v-for="c in CONSEQUENCES" :key="c" :value="c" style="text-transform:capitalize;">{{ c.replace('_', ' ') }}</option>
                        </select>
                    </div>
                    <div v-if="form.consequence === 'suspension' || form.consequence === 'detention'" class="form-field">
                        <label>From — To</label>
                        <div style="display:flex;gap:6px;">
                            <input v-model="form.consequence_from" type="date" style="flex:1;" />
                            <input v-model="form.consequence_to" type="date" style="flex:1;" />
                        </div>
                    </div>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" v-model="form.parent_notified" />
                            Parent Notified
                        </label>
                    </div>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Student Statement</label>
                        <textarea v-model="form.student_statement" rows="2"></textarea>
                    </div>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Internal Notes</label>
                        <textarea v-model="form.notes" rows="2"></textarea>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="closeEdit">Cancel</Button>
                <Button type="submit" form="edit-record-form" :loading="form.processing">Update Record</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* ── Records table ── */
.sev-badge {
    display:inline-block;padding:3px 10px;border-radius:20px;
    font-size:.75rem;font-weight:600;text-transform:capitalize;
}

/* ── Add-view filter bar ── */
.add-filter-bar { display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap; }
.add-filter-field { display:flex;flex-direction:column;gap:5px; }
.add-filter-field select { width:200px; }
.add-filter-label { font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em; }
.student-pill {
    margin-left:auto;padding:6px 16px;background:#eff6ff;
    border:1px solid #bfdbfe;border-radius:20px;
    font-size:.8rem;font-weight:600;color:#1d4ed8;align-self:center;
}

/* ── Student list ── */
.col-num     { width:32px;text-align:center;flex-shrink:0; }
.col-student { width:180px;flex-shrink:0; }
.col-sev     { width:110px;flex-shrink:0; }
.col-cons    { width:140px;flex-shrink:0; }
.col-chips   { flex:1;min-width:0; }
.row-select {
    width:100%;padding:5px 8px;border:1px solid #e2e8f0;border-radius:6px;
    font-size:.78rem;color:#1e293b;background:#fff;cursor:pointer;
    text-transform:capitalize;
}
.row-select:focus { outline:none;border-color:#3b82f6; }

.student-list-header {
    display:flex;align-items:flex-start;gap:14px;padding:12px 20px;
    border-bottom:2px solid #e2e8f0;background:#f8fafc;
    font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;
}
.student-item {
    display:flex;align-items:center;gap:14px;padding:12px 20px;
    border-bottom:1px solid #f1f5f9;transition:background .12s;
}
.student-item .col-chips { display:flex;flex-wrap:wrap;gap:5px; }
.student-item:hover { background:#f8fafc; }
.student-item.item-active { background:#eff6ff;border-left:3px solid #3b82f6;padding-left:17px; }
.item-num  { font-size:.8rem;color:#cbd5e1;font-weight:600; }
.item-info { display:flex;flex-direction:column;gap:2px; }
.item-name { font-size:.95rem;font-weight:600;color:#1e293b; }
.item-adm  { font-size:.72rem;color:#94a3b8; }

/* ── Bulk add ── */
.bulk-section-label {
    font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;
    letter-spacing:.05em;margin-bottom:10px;
}
.cat-chip {
    display:inline-flex;align-items:center;justify-content:center;
    padding:4px 9px;border-radius:6px;border:1px solid #e2e8f0;
    background:#fff;cursor:pointer;user-select:none;transition:all .1s;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size:.7rem;font-weight:700;letter-spacing:.04em;color:#475569;
    line-height:1.2;white-space:nowrap;
}
.cat-chip:hover { border-color:#bfdbfe;background:#f8fafc;color:#1e293b; }
.cat-chip-selected,
.cat-chip-selected:hover { background:#3b82f6;border-color:#3b82f6;color:#fff; }
.cat-chip-header {
    background:#f8fafc;color:#64748b;border-color:#e2e8f0;
}
.chips-header-label {
    display:block;font-size:.65rem;font-weight:600;color:#94a3b8;
    text-transform:none;letter-spacing:0;margin-bottom:4px;
}
.chips-header-row { display:flex;flex-wrap:wrap;gap:5px; }

.bulk-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:14px; }
.bulk-grid-full { grid-column:1/-1; }
@media (max-width: 700px) { .bulk-grid { grid-template-columns:1fr; } }

.bulk-save-bar {
    position:sticky;bottom:0;margin-top:16px;
    display:flex;justify-content:space-between;align-items:center;
    padding:14px 20px;background:#fff;
    border:1px solid #e2e8f0;border-radius:12px;
    box-shadow:0 -2px 12px rgba(15,23,42,.06);
}
.bulk-summary { font-size:.85rem;color:#64748b; }
.bulk-summary strong { color:#1e293b;font-weight:700; }

.field-error { color:#dc2626;font-size:.72rem;margin-top:3px;display:block; }

/* ── Category modal ── */
.cat-list { border:1px solid #f1f5f9;border-radius:8px;overflow-y:auto;max-height:280px;margin-bottom:14px; }
.cat-row {
    display:flex;align-items:center;gap:10px;padding:11px 14px;
    border-bottom:1px solid #f8fafc;background:#fff;
}
.cat-row:last-child { border-bottom:none; }
.cat-dot { width:7px;height:7px;border-radius:50%;background:#e2e8f0;flex-shrink:0; }
.cat-name { flex:1;font-size:.88rem;color:#1e293b; }
.cat-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: .72rem; font-weight: 700;
    background: #eff6ff; color: #1d4ed8;
    border: 1px solid #bfdbfe; border-radius: 6px;
    padding: 2px 8px; letter-spacing: .04em;
    flex-shrink: 0;
}
.cat-actions { display:flex;gap:4px;opacity:0;transition:opacity .15s; }
.cat-row:hover .cat-actions { opacity:1; }
.cat-icon-btn { padding:4px;background:none;border:none;cursor:pointer;color:#94a3b8;border-radius:4px;display:flex;align-items:center; }
.cat-icon-btn:hover { background:#f1f5f9;color:#475569; }
.cat-delete-btn:hover { background:#fef2f2;color:#dc2626; }
.cat-edit-input { padding:5px 10px;border:1px solid #3b82f6;border-radius:6px;font-size:.88rem;outline:none; }
.cat-edit-name { flex:2; }
.cat-edit-code { flex:1;max-width:110px;text-transform:uppercase;letter-spacing:.04em; }
.cat-save-btn { padding:5px 12px;background:#3b82f6;color:#fff;border:none;border-radius:6px;font-size:.82rem;font-weight:600;cursor:pointer; }
.cat-save-btn:hover { background:#2563eb; }
.cat-add-row { display:flex;gap:8px;align-items:center; }
.cat-add-input { padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:.88rem;outline:none; }
.cat-add-input:focus { border-color:#3b82f6; }
.cat-add-name { flex:2; }
.cat-add-code { flex:1;max-width:110px;text-transform:uppercase;letter-spacing:.04em; }

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 5px; }
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
</style>
