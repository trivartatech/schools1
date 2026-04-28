<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

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

const deleteCat = (id) => {
    if (confirm('Delete this category?')) {
        router.delete(`/school/disciplinary/categories/${id}`, { preserveScroll: true });
    }
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
    student_ids: [], categories: [],
    incident_date: TODAY, severity: 'minor', description: '',
    action_taken: '', consequence: '', consequence_from: '', consequence_to: '',
});

const allBrowsedSelected = computed(() =>
    browsedStudents.value.length > 0 &&
    browsedStudents.value.every(s => bulkForm.student_ids.includes(s.id))
);

const toggleSelectAll = () => {
    if (allBrowsedSelected.value) {
        const visibleIds = new Set(browsedStudents.value.map(s => s.id));
        bulkForm.student_ids = bulkForm.student_ids.filter(id => !visibleIds.has(id));
    } else {
        const next = new Set(bulkForm.student_ids);
        browsedStudents.value.forEach(s => next.add(s.id));
        bulkForm.student_ids = [...next];
    }
};

const recordsPreviewCount = computed(() =>
    bulkForm.student_ids.length * bulkForm.categories.length
);

const openAdd = () => {
    browseClass.value = ''; browseSection.value = '';
    bulkForm.reset();
    view.value = 'add';
};

const backToList = () => { view.value = 'list'; bulkForm.reset(); };

const submitBulk = () => {
    bulkForm.post('/school/disciplinary/bulk', {
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

const deleteRecord = (id) => {
    if (confirm('Delete this record?')) router.delete(`/school/disciplinary/${id}`, { preserveScroll: true });
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
</script>

<template>
    <SchoolLayout title="Disciplinary Records">

        <!-- ═══════════════════════════════════════════════════════════
             LIST VIEW
        ════════════════════════════════════════════════════════════ -->
        <template v-if="view === 'list'">
            <div class="page-header">
                <h1 class="page-header-title">Disciplinary Records</h1>
                <div style="display:flex;gap:10px;">
                    <button class="cat-btn" @click="showCatModal = true">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        Manage Categories
                    </button>
                    <Button @click="openAdd">+ Add Record</Button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="disc-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#eff6ff;color:#1d4ed8;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <div class="stat-value" style="color:#1d4ed8;">{{ summary.total }}</div>
                        <div class="stat-label">Total Records</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fffbeb;color:#d97706;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="stat-value" style="color:#d97706;">{{ summary.open }}</div>
                        <div class="stat-label">Open Cases</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div class="stat-value" style="color:#7c3aed;">{{ summary.this_month }}</div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef2f2;color:#dc2626;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <div class="stat-value" style="color:#dc2626;">{{ summary.major }}</div>
                        <div class="stat-label">Major Incidents</div>
                    </div>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="filter-bar card">
                <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:12px 16px;">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#94a3b8"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
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
                    <button v-if="filterStatus || filterSeverity || filterStudent" class="clear-btn" @click="filterStatus='';filterSeverity='';filterStudent='';applyFilters()">✕ Clear</button>
                </div>
            </div>

            <!-- Records table -->
            <div class="card" style="overflow:hidden;">
                <div style="overflow-x:auto;">
                    <table class="disc-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Consequence</th>
                                <th>Reported By</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in records.data" :key="r.id">
                                <td>
                                    <div style="font-weight:600;color:#1e293b;">{{ r.student?.first_name }} {{ r.student?.last_name }}</div>
                                    <div style="font-size:.72rem;color:#94a3b8;">{{ r.student?.admission_no }}</div>
                                </td>
                                <td style="white-space:nowrap;color:#475569;">{{ fmtDate(r.incident_date) }}</td>
                                <td style="color:#475569;">{{ fmtCategory(r.category) }}</td>
                                <td>
                                    <span class="sev-badge" :style="{ background: severityColor[r.severity] + '18', color: severityColor[r.severity] }">{{ r.severity }}</span>
                                </td>
                                <td><span class="badge" :class="statusBadge[r.status]" style="text-transform:capitalize;">{{ r.status?.replace('_', ' ') }}</span></td>
                                <td style="text-transform:capitalize;font-size:.82rem;color:#64748b;">{{ r.consequence ? r.consequence.replace('_', ' ') : '—' }}</td>
                                <td style="font-size:.82rem;color:#64748b;">{{ r.reported_by?.name || '—' }}</td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex;gap:6px;">
                                        <Button variant="secondary" size="xs" @click="openEdit(r)">Edit</Button>
                                        <Button variant="danger" size="xs" @click="deleteRecord(r.id)">Del</Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!records.data?.length">
                                <td colspan="8" class="empty-td">No records found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- ═══════════════════════════════════════════════════════════
             ADD VIEW
        ════════════════════════════════════════════════════════════ -->
        <template v-else-if="view === 'add'">
            <div class="page-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="back-btn" @click="backToList">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back to Records
                    </button>
                    <h1 class="page-header-title" style="margin:0;">Add Disciplinary Incident</h1>
                </div>
            </div>

            <!-- Class / Section filter -->
            <div class="card" style="margin-bottom:16px;">
                <div class="card-body add-filter-bar">
                    <div class="add-filter-field">
                        <label class="add-filter-label">Class</label>
                        <select v-model="browseClass" @change="browseSection = ''; bulkForm.student_ids = [];">
                            <option value="">— Select Class —</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="add-filter-field">
                        <label class="add-filter-label">Section</label>
                        <select v-model="browseSection" :disabled="!browseClass" @change="bulkForm.student_ids = [];">
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
            <div v-if="!browseClass" class="card">
                <div class="card-body" style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:56px 20px;">
                    <svg width="44" height="44" fill="none" viewBox="0 0 24 24" stroke="#cbd5e1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p style="margin:0;color:#94a3b8;font-size:.9rem;">Select a class to view students</p>
                </div>
            </div>
            <div v-else-if="!browsedStudents.length" class="card">
                <div class="card-body" style="text-align:center;padding:40px;color:#94a3b8;">No students found for the selected class / section.</div>
            </div>

            <template v-else>
                <!-- Categories chip selector -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-body" style="padding:14px 18px;">
                        <div class="bulk-section-label">Categories *</div>
                        <div class="cat-chip-row">
                            <label v-for="c in categories" :key="c.id" class="cat-chip"
                                   :class="{ 'cat-chip-selected': bulkForm.categories.includes(c.name) }">
                                <input type="checkbox" :value="c.name" v-model="bulkForm.categories" class="cat-chip-input" />
                                <span class="cat-chip-code">{{ c.short_code || c.name }}</span>
                            </label>
                        </div>
                        <span v-if="bulkForm.errors.categories" class="field-error">{{ bulkForm.errors.categories }}</span>
                    </div>
                </div>

                <!-- Common incident fields -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-body" style="padding:14px 18px;">
                        <div class="bulk-section-label">Incident Details</div>
                        <div class="bulk-grid">
                            <div class="form-field">
                                <label>Date *</label>
                                <input v-model="bulkForm.incident_date" type="date" required />
                                <span v-if="bulkForm.errors.incident_date" class="field-error">{{ bulkForm.errors.incident_date }}</span>
                            </div>
                            <div class="form-field">
                                <label>Severity *</label>
                                <select v-model="bulkForm.severity" required>
                                    <option value="minor">Minor</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="major">Major</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Consequence</label>
                                <select v-model="bulkForm.consequence">
                                    <option value="">— None —</option>
                                    <option v-for="c in CONSEQUENCES" :key="c" :value="c" style="text-transform:capitalize;">{{ c.replace('_', ' ') }}</option>
                                </select>
                            </div>
                            <div v-if="bulkForm.consequence === 'suspension' || bulkForm.consequence === 'detention'" class="form-field bulk-grid-full">
                                <label>Consequence Period</label>
                                <div style="display:flex;gap:10px;align-items:center;">
                                    <input v-model="bulkForm.consequence_from" type="date" style="flex:1;" />
                                    <span style="color:#94a3b8;font-size:.8rem;">to</span>
                                    <input v-model="bulkForm.consequence_to" type="date" style="flex:1;" />
                                </div>
                            </div>
                            <div class="form-field bulk-grid-full">
                                <label>Description *</label>
                                <textarea v-model="bulkForm.description" rows="2" required placeholder="Describe the incident in detail…"></textarea>
                                <span v-if="bulkForm.errors.description" class="field-error">{{ bulkForm.errors.description }}</span>
                            </div>
                            <div class="form-field bulk-grid-full">
                                <label>Action Taken</label>
                                <input v-model="bulkForm.action_taken" type="text" placeholder="Optional — what action was taken?" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student list with checkboxes -->
                <div class="card" style="overflow:hidden;">
                    <div class="student-list-header">
                        <span class="col-check">
                            <input type="checkbox" :checked="allBrowsedSelected" @change="toggleSelectAll" />
                        </span>
                        <span class="col-num">#</span>
                        <span class="col-student">Student</span>
                        <span class="col-roll">Roll No</span>
                    </div>
                    <div class="student-list">
                        <label v-for="(s, idx) in browsedStudents" :key="s.id"
                               :class="['student-item', 'student-item-row', { 'item-active': bulkForm.student_ids.includes(s.id) }]">
                            <span class="col-check">
                                <input type="checkbox" :value="s.id" v-model="bulkForm.student_ids" />
                            </span>
                            <span class="col-num item-num">{{ idx + 1 }}</span>
                            <span class="col-student item-info">
                                <span class="item-name">{{ s.first_name }} {{ s.last_name }}</span>
                                <span class="item-adm">{{ s.admission_no }}</span>
                            </span>
                            <span class="col-roll" style="font-size:.85rem;color:#64748b;">{{ s.current_academic_history?.roll_no || '—' }}</span>
                        </label>
                    </div>
                    <span v-if="bulkForm.errors.student_ids" class="field-error" style="display:block;padding:10px 18px;">{{ bulkForm.errors.student_ids }}</span>
                </div>

                <!-- Sticky save bar -->
                <div class="bulk-save-bar">
                    <div class="bulk-summary">
                        <strong>{{ bulkForm.student_ids.length }}</strong> student{{ bulkForm.student_ids.length !== 1 ? 's' : '' }}
                        × <strong>{{ bulkForm.categories.length }}</strong> categor{{ bulkForm.categories.length !== 1 ? 'ies' : 'y' }}
                        = <strong>{{ recordsPreviewCount }}</strong> record{{ recordsPreviewCount !== 1 ? 's' : '' }}
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" class="btn-outline" @click="backToList">Cancel</button>
                        <Button @click="submitBulk"
                                :loading="bulkForm.processing"
                                :disabled="!recordsPreviewCount || !bulkForm.description">
                            Save Records
                        </Button>
                    </div>
                </div>
            </template>
        </template>

        <!-- ═══════════════════════════════════════════════════════════
             CATEGORY MANAGEMENT MODAL
        ════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showCatModal" class="modal-backdrop" @click.self="showCatModal = false">
                <div class="modal cat-modal">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">Manage Categories</h3>
                            <p style="font-size:.78rem;color:#94a3b8;margin:2px 0 0;">Add, rename or remove incident categories for your school.</p>
                        </div>
                        <button @click="showCatModal = false" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
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
                    </div>
                    <div class="modal-footer" style="justify-content:flex-end;">
                        <Button variant="secondary" @click="showCatModal = false">Done</Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Edit Record Modal ───────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showEdit" class="modal-backdrop" @click.self="closeEdit">
                <div class="modal" style="max-width:580px;width:100%;max-height:90vh;overflow-y:auto;">
                    <div class="modal-header">
                        <h3 class="modal-title">Edit Record — {{ editRecord?.student?.first_name }} {{ editRecord?.student?.last_name }}</h3>
                        <button @click="closeEdit" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submitEdit">
                        <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
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
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="closeEdit">Cancel</Button>
                            <Button type="submit" :loading="form.processing">Update Record</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<style scoped>
/* ── Stat cards ── */
.disc-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px; }
.stat-card {
    background:#fff;border-radius:12px;border:1px solid #f1f5f9;
    padding:16px 20px;display:flex;align-items:center;gap:14px;
    box-shadow:0 1px 3px rgba(0,0,0,.04);
}
.stat-icon { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.stat-value { font-size:1.6rem;font-weight:700;line-height:1; }
.stat-label { font-size:.75rem;color:#94a3b8;margin-top:3px; }

/* ── Manage categories button ── */
.cat-btn {
    display:inline-flex;align-items:center;gap:7px;padding:8px 16px;
    background:#fff;border:1px solid #e2e8f0;border-radius:8px;
    font-size:.85rem;font-weight:500;color:#475569;cursor:pointer;
    transition:background .15s,border-color .15s;
}
.cat-btn:hover { background:#f8fafc;border-color:#cbd5e1;color:#1e293b; }

/* ── Filter bar ── */
.filter-bar { margin-bottom:16px; }
.clear-btn {
    padding:6px 12px;background:#fef2f2;border:1px solid #fecaca;
    border-radius:6px;color:#dc2626;font-size:.8rem;cursor:pointer;
}

/* ── Records table ── */
.disc-table { width:100%;border-collapse:collapse; }
.disc-table th {
    padding:11px 16px;background:#f8fafc;border-bottom:2px solid #e2e8f0;
    font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;
    letter-spacing:.04em;text-align:left;white-space:nowrap;
}
.disc-table td {
    padding:13px 16px;border-bottom:1px solid #f1f5f9;
    vertical-align:middle;
}
.disc-table tbody tr:hover { background:#f8fafc; }
.disc-table tbody tr:last-child td { border-bottom:none; }
.empty-td { text-align:center;padding:40px;color:#94a3b8;font-size:.9rem; }

.sev-badge {
    display:inline-block;padding:3px 10px;border-radius:20px;
    font-size:.75rem;font-weight:600;text-transform:capitalize;
}

/* ── Back button ── */
.back-btn {
    display:inline-flex;align-items:center;gap:6px;padding:7px 16px;
    background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;
    font-size:.85rem;font-weight:500;color:#475569;cursor:pointer;
    transition:background .15s;white-space:nowrap;
}
.back-btn:hover { background:#e2e8f0;color:#1e293b; }

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
.col-check   { width:36px;flex-shrink:0;display:flex;align-items:center;justify-content:center; }
.col-num     { width:44px;text-align:center;flex-shrink:0; }
.col-student { flex:1; }
.col-roll    { width:100px;flex-shrink:0; }
.col-action  { width:150px;flex-shrink:0; }

.student-list-header {
    display:flex;align-items:center;padding:10px 20px;
    border-bottom:2px solid #e2e8f0;background:#f8fafc;
    font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;
}
.student-item {
    display:flex;align-items:center;padding:14px 20px;
    border-bottom:1px solid #f1f5f9;transition:background .12s;
}
.student-item-row { cursor:pointer; }
.student-item:hover { background:#f8fafc; }
.student-item.item-active { background:#eff6ff;border-left:3px solid #3b82f6;padding-left:17px; }
.item-num  { font-size:.8rem;color:#cbd5e1;font-weight:600; }
.item-info { display:flex;flex-direction:column;gap:2px; }
.item-name { font-size:.95rem;font-weight:600;color:#1e293b; }
.item-adm  { font-size:.72rem;color:#94a3b8; }

.incident-btn { padding:7px 16px;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;transition:background .15s; }
.btn-add    { background:#3b82f6;color:#fff; }
.btn-add:hover    { background:#2563eb; }
.btn-cancel { background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0; }
.btn-cancel:hover { background:#e2e8f0;color:#1e293b; }

/* ── Bulk add ── */
.bulk-section-label {
    font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;
    letter-spacing:.05em;margin-bottom:10px;
}
.cat-chip-row { display:flex;flex-wrap:wrap;gap:8px; }
.cat-chip {
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 12px;border-radius:8px;border:1px solid #e2e8f0;
    background:#fff;cursor:pointer;user-select:none;transition:all .12s;
}
.cat-chip:hover { border-color:#bfdbfe;background:#f8fafc; }
.cat-chip-input { margin:0;cursor:pointer; }
.cat-chip-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size:.75rem;font-weight:700;letter-spacing:.04em;color:#1e293b;
}
.cat-chip-selected { background:#eff6ff;border-color:#3b82f6; }
.cat-chip-selected .cat-chip-code { color:#1d4ed8; }

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
.btn-outline { padding:7px 18px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#64748b;font-size:.85rem;font-weight:500;cursor:pointer; }
.btn-outline:hover { background:#f1f5f9;color:#1e293b; }

/* ── Category modal ── */
.cat-modal { width:min(480px,95vw);max-height:80vh;display:flex;flex-direction:column; }
.cat-modal .modal-body { flex:1;overflow-y:auto;padding:16px 20px; }
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

/* ── Modals ── */
.modal-backdrop { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;z-index:1000; }
.modal { background:#fff;border-radius:12px;box-shadow:0 20px 25px -5px rgba(0,0,0,.1);width:100%;max-height:90vh;overflow-y:auto; }
.modal-header { padding:18px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:flex-start; }
.modal-title { font-size:1rem;font-weight:700;color:#1e293b;margin:0; }
.modal-close { background:none;border:none;font-size:1.5rem;line-height:1;color:#94a3b8;cursor:pointer; }
.modal-body { padding:20px; }
.modal-footer { padding:14px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 12px 12px;display:flex;gap:10px; }
</style>
