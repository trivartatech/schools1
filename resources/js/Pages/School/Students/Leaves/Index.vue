<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed, onMounted } from 'vue';
import { useForm, router, usePage, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    leaves:         Object,
    leaveTypes:     Array,
    classes:        Array,
    sections:       Array,
    students:       Array,
    summary:        Object,
    filters:        Object,
    own_student_id: { type: Number, default: null },  // pre-filled for student role
    own_children:   { type: Array,  default: () => [] }, // child list for parent role
});

const { can, userType } = usePermissions();

const isManagement = computed(() =>
    ['admin', 'super_admin', 'school_admin', 'principal', 'teacher'].includes(userType.value)
);
const isParent  = computed(() => userType.value === 'parent');
const isStudent = computed(() => userType.value === 'student');
const canApprove = computed(() => can('approve_student_leaves'));
const canApply   = computed(() => can('apply_student_leave') || can('create_student_leaves'));

// Show the student-selector block for management AND for parents (who must pick a child)
const showStudentSelector = computed(() => isManagement.value || isParent.value);

// ── Apply Form ───────────────────────────────────────────────────────────────
const showApplyForm   = ref(false);
const selectedClassId   = ref('');
const selectedSectionId = ref('');

// File upload state
const fileInput       = ref(null);
const selectedFile    = ref(null);
const fileError       = ref('');
// 'image/jpg' is not a real MIME type — browsers always report 'image/jpeg' for .jpg files.
const ALLOWED_TYPES   = ['application/pdf', 'image/jpeg', 'image/png'];
const MAX_SIZE_MB      = 5;

const onFileChange = (e) => {
    const file = e.target.files[0];
    fileError.value = '';
    selectedFile.value = null;

    if (!file) return;

    if (!ALLOWED_TYPES.includes(file.type)) {
        fileError.value = 'Only PDF, JPG, and PNG files are allowed.';
        e.target.value = '';
        return;
    }
    if (file.size > MAX_SIZE_MB * 1024 * 1024) {
        fileError.value = `File must be under ${MAX_SIZE_MB} MB.`;
        e.target.value = '';
        return;
    }
    selectedFile.value = file;
};

const clearFile = () => {
    selectedFile.value = null;
    fileError.value    = '';
    if (fileInput.value) fileInput.value.value = '';
};

const fileIcon = (file) => {
    if (!file) return '📎';
    if (file.type === 'application/pdf') return '📄';
    return '🖼️';
};

const fileSizeLabel = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

// Cascading class/section filter
const filteredSections = computed(() => {
    if (!selectedClassId.value) return [];
    return (props.sections || []).filter(s => s.course_class_id == selectedClassId.value);
});

const filteredStudents = computed(() => {
    let list = props.students || [];
    if (selectedClassId.value)   list = list.filter(s => s.class_id   == selectedClassId.value);
    if (selectedSectionId.value) list = list.filter(s => s.section_id == selectedSectionId.value);
    return list;
});

const onClassChange   = () => { selectedSectionId.value = ''; form.student_id = ''; };
const onSectionChange = () => { form.student_id = ''; };

const form = useForm({
    student_id:    '',
    leave_type_id: '',
    start_date:    '',
    end_date:      '',
    reason:        '',
    document:      null,
});

// For student role: auto-fill their own student_id on mount so the hidden field is set.
onMounted(() => {
    if (props.own_student_id) {
        form.student_id = props.own_student_id;
    }
});

// Selected leave type — used to show "document required" hint
const selectedLeaveType = computed(() =>
    props.leaveTypes?.find(lt => lt.id == form.leave_type_id) ?? null
);

const submit = () => {
    // Attach file to form manually (Inertia useForm supports FormData via transform)
    form.transform(data => {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') fd.append(k, v);
        });
        if (selectedFile.value) fd.append('document', selectedFile.value);
        return fd;
    }).post('/school/student-leaves', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            clearFile();
            selectedClassId.value   = '';
            selectedSectionId.value = '';
            showApplyForm.value     = false;
            // Restore own student_id after reset (student role needs it pre-filled)
            if (props.own_student_id) form.student_id = props.own_student_id;
        },
    });
};

// ── Table Filters ─────────────────────────────────────────────────────────────
const filterStatus      = ref(props.filters?.status || '');
const filterStudentId   = ref(props.filters?.student_id || '');
const filterLeaveTypeId = ref(props.filters?.leave_type_id || '');
const filterFrom        = ref(props.filters?.from || '');
const filterTo          = ref(props.filters?.to || '');

const applyFilters = () => {
    router.get('/school/student-leaves', {
        status:        filterStatus.value,
        student_id:    filterStudentId.value,
        leave_type_id: filterLeaveTypeId.value,
        from:          filterFrom.value,
        to:            filterTo.value,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    filterStatus.value = filterStudentId.value = filterLeaveTypeId.value = '';
    filterFrom.value   = filterTo.value = '';
    applyFilters();
};

// ── Approve / Reject Modal ────────────────────────────────────────────────────
const actionModal = ref({ show: false, leaveId: null, action: '', remarks: '' });

const openAction = (id, action) => {
    actionModal.value = { show: true, leaveId: id, action, remarks: '' };
};

const submitAction = () => {
    const { leaveId, action, remarks } = actionModal.value;
    router.patch(`/school/student-leaves/${leaveId}/${action}`, { remarks }, {
        preserveScroll: true,
        onSuccess: () => { actionModal.value.show = false; },
    });
};

const revertLeave = (id) => {
    if (!confirm('Revert this leave back to pending?')) return;
    router.patch(`/school/student-leaves/${id}/revert`, {}, { preserveScroll: true });
};

// ── Document preview modal ───────────────────────────────────────────────────
const docModal = ref({ show: false, url: '', name: '', isPdf: false });

const openDoc = (leave) => {
    const url = `/school/student-leaves/${leave.id}/document`;
    docModal.value = {
        show:  true,
        url,
        name:  leave.document_original_name || 'Document',
        isPdf: leave.document_mime === 'application/pdf',
    };
};

// ── Helpers ──────────────────────────────────────────────────────────────────
const statusColors = {
    pending:  'bg-amber-100 text-amber-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

const daysBetween = (s, e) =>
    Math.round((new Date(e) - new Date(s)) / (1000 * 60 * 60 * 24)) + 1;

const studentName = (s) => s ? `${s.first_name} ${s.last_name}` : '—';

const docTypeIcon = (mime) => {
    if (mime === 'application/pdf') return '📄';
    if (mime?.startsWith('image/'))  return '🖼️';
    return '📎';
};
</script>

<template>
    <SchoolLayout title="Student Leave Management">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-header-title">Student Leave Requests</h1>
                    <p class="page-header-sub">Manage and track student leave applications</p>
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <Button variant="secondary" as="link" v-if="isManagement" href="/school/student-leave-types">
                        Leave Types
                    </Button>
                    <Button v-if="canApply" @click="showApplyForm = !showApplyForm">
                        {{ showApplyForm ? 'Cancel' : 'Apply for Leave' }}
                    </Button>
                </div>
            </div>

            <!-- Apply Form -->
            <transition name="slide">
                <div v-if="showApplyForm" class="card mb-5">
                    <div class="card-header">
                        <span class="card-title">New Leave Application</span>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submit" enctype="multipart/form-data">

                            <!-- Student selector -->
                            <div v-if="showStudentSelector" style="margin-bottom:1.25rem;">
                                <p class="section-heading">Select Student</p>

                                <!-- Management: cascading Class → Section → Student -->
                                <div v-if="isManagement" class="form-row-3">
                                    <div class="form-field">
                                        <label>Class</label>
                                        <select v-model="selectedClassId" @change="onClassChange">
                                            <option value="">All Classes</option>
                                            <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label>Section</label>
                                        <select v-model="selectedSectionId" @change="onSectionChange" :disabled="!selectedClassId">
                                            <option value="">All Sections</option>
                                            <option v-for="sec in filteredSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label>Student *</label>
                                        <select v-model="form.student_id" required :class="{ 'border-red-400': form.errors.student_id }">
                                            <option value="">{{ filteredStudents.length === 0 ? 'No students found' : 'Select Student' }}</option>
                                            <option v-for="s in filteredStudents" :key="s.id" :value="s.id">
                                                {{ s.first_name }} {{ s.last_name }} ({{ s.admission_no }})
                                            </option>
                                        </select>
                                        <span v-if="form.errors.student_id" class="form-error">{{ form.errors.student_id }}</span>
                                    </div>
                                </div>

                                <!-- Parent: child picker -->
                                <div v-else-if="isParent" style="max-width:20rem;">
                                    <div class="form-field">
                                        <label>Child *</label>
                                        <select v-model="form.student_id" required :class="{ 'border-red-400': form.errors.student_id }">
                                            <option value="">Select Child</option>
                                            <option v-for="c in own_children" :key="c.id" :value="c.id">
                                                {{ c.first_name }} {{ c.last_name }} ({{ c.admission_no }})
                                            </option>
                                        </select>
                                        <span v-if="form.errors.student_id" class="form-error">{{ form.errors.student_id }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Leave details -->
                            <div class="form-row" style="grid-template-columns:repeat(4,1fr);">
                                <div class="form-field">
                                    <label>Leave Type</label>
                                    <select v-model="form.leave_type_id">
                                        <option value="">— General / No type —</option>
                                        <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">
                                            {{ lt.name }} ({{ lt.code }})
                                        </option>
                                    </select>
                                    <div v-if="selectedLeaveType?.requires_document"
                                        style="margin-top:0.25rem;font-size:0.75rem;color:var(--warning);background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);border-radius:0.375rem;padding:0.25rem 0.5rem;">
                                        This leave type requires a supporting document
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label>From Date *</label>
                                    <input v-model="form.start_date" type="date" required :class="{ 'border-red-400': form.errors.start_date }">
                                    <span v-if="form.errors.start_date" class="form-error">{{ form.errors.start_date }}</span>
                                </div>
                                <div class="form-field">
                                    <label>To Date *</label>
                                    <input v-model="form.end_date" type="date" required :class="{ 'border-red-400': form.errors.end_date }">
                                    <span v-if="form.errors.end_date" class="form-error">{{ form.errors.end_date }}</span>
                                </div>
                                <div class="form-field">
                                    <label>Reason *</label>
                                    <input v-model="form.reason" type="text" placeholder="Brief reason..." required maxlength="1000" :class="{ 'border-red-400': form.errors.reason }">
                                    <span v-if="form.errors.reason" class="form-error">{{ form.errors.reason }}</span>
                                </div>
                            </div>

                            <!-- Document Upload -->
                            <div class="form-field" style="margin-bottom:1rem;">
                                <label>
                                    Supporting Document
                                    <span style="color:#9ca3af;font-weight:400;margin-left:0.25rem;">(PDF, JPG, PNG — max 5 MB)</span>
                                    <span v-if="selectedLeaveType?.requires_document" style="color:var(--warning);font-weight:600;margin-left:0.25rem;">*Required</span>
                                </label>

                                <div v-if="!selectedFile"
                                    style="border:2px dashed #e5e7eb;border-radius:0.5rem;padding:1.5rem;text-align:center;cursor:pointer;transition:border-color 0.2s;"
                                    @click="fileInput.click()"
                                    @dragover.prevent
                                    @drop.prevent="e => { fileInput.files = e.dataTransfer.files; onFileChange({ target: fileInput }) }">
                                    <div style="font-size:1.75rem;margin-bottom:0.5rem;">📎</div>
                                    <p style="font-size:0.875rem;color:#374151;font-weight:500;">Click to upload or drag & drop</p>
                                    <p style="font-size:0.75rem;color:#9ca3af;margin-top:0.25rem;">PDF · JPG · PNG | Max 5 MB</p>
                                    <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" @change="onFileChange">
                                </div>

                                <div v-else style="display:flex;align-items:center;gap:0.75rem;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.2);border-radius:0.5rem;padding:0.75rem 1rem;">
                                    <span style="font-size:1.5rem;">{{ fileIcon(selectedFile) }}</span>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:0.875rem;font-weight:500;color:#1f2937;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ selectedFile.name }}</div>
                                        <div style="font-size:0.75rem;color:#6b7280;">{{ fileSizeLabel(selectedFile.size) }}</div>
                                    </div>
                                    <Button variant="danger" size="xs" type="button" @click="clearFile" title="Remove file">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </Button>
                                </div>

                                <span v-if="fileError" class="form-error">{{ fileError }}</span>
                                <span v-if="form.errors.document" class="form-error">{{ form.errors.document }}</span>
                            </div>

                            <div style="display:flex;justify-content:flex-end;padding-top:0.75rem;border-top:1px solid #f3f4f6;">
                                <Button type="submit" :loading="form.processing">
                                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Submit Application
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="card">
                    <div class="card-body" style="text-align:center;">
                        <div style="font-size:1.5rem;font-weight:700;color:#374151;">{{ summary.total }}</div>
                        <div style="font-size:0.75rem;color:#6b7280;font-weight:500;margin-top:0.25rem;">Total</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align:center;">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--warning);">{{ summary.pending }}</div>
                        <div style="font-size:0.75rem;color:var(--warning);font-weight:500;margin-top:0.25rem;">Pending</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align:center;">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--success);">{{ summary.approved }}</div>
                        <div style="font-size:0.75rem;color:var(--success);font-weight:500;margin-top:0.25rem;">Approved</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align:center;">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--danger);">{{ summary.rejected }}</div>
                        <div style="font-size:0.75rem;color:var(--danger);font-weight:500;margin-top:0.25rem;">Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-5">
                <div class="card-body">
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
                        <div class="form-field" style="margin-bottom:0;min-width:8rem;">
                            <label>Status</label>
                            <select v-model="filterStatus" @change="applyFilters">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div v-if="isManagement && students.length" class="form-field" style="margin-bottom:0;min-width:10rem;">
                            <label>Student</label>
                            <select v-model="filterStudentId" @change="applyFilters">
                                <option value="">All Students</option>
                                <option v-for="s in students" :key="s.id" :value="s.id">
                                    {{ s.first_name }} {{ s.last_name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="leaveTypes.length" class="form-field" style="margin-bottom:0;min-width:10rem;">
                            <label>Leave Type</label>
                            <select v-model="filterLeaveTypeId" @change="applyFilters">
                                <option value="">All Types</option>
                                <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
                            </select>
                        </div>
                        <div class="form-field" style="margin-bottom:0;">
                            <label>From</label>
                            <input v-model="filterFrom" type="date" @change="applyFilters">
                        </div>
                        <div class="form-field" style="margin-bottom:0;">
                            <label>To</label>
                            <input v-model="filterTo" type="date" @change="applyFilters">
                        </div>
                        <Button variant="secondary" size="sm" @click="clearFilters">Clear</Button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card mb-5">
                <div class="card-body" style="padding:0;">
                    <div v-if="leaves.data.length === 0" style="padding:2.5rem;text-align:center;color:#6b7280;">
                        <div style="font-size:1.75rem;margin-bottom:0.5rem;">📋</div>
                        No leave requests found.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <Table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Leave Type</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Document</th>
                                    <th>Applied By</th>
                                    <th>Status</th>
                                    <th v-if="canApprove">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="leave in leaves.data" :key="leave.id">

                                    <!-- Student -->
                                    <td>
                                        <div style="font-weight:500;color:#111827;">{{ studentName(leave.student) }}</div>
                                        <div style="font-size:0.75rem;color:#9ca3af;">{{ leave.student?.admission_no }}</div>
                                    </td>

                                    <!-- Leave Type -->
                                    <td>
                                        <span v-if="leave.leave_type"
                                            style="display:inline-flex;align-items:center;font-size:0.75rem;font-weight:500;padding:0.125rem 0.5rem;border-radius:0.25rem;"
                                            :style="{ backgroundColor: leave.leave_type.color + '22', color: leave.leave_type.color }">
                                            {{ leave.leave_type.name }}
                                        </span>
                                        <span v-else style="font-size:0.75rem;color:#9ca3af;">General</span>
                                    </td>

                                    <!-- Duration -->
                                    <td style="white-space:nowrap;">
                                        <div style="font-size:0.75rem;color:#1f2937;">
                                            {{ new Date(leave.start_date).toLocaleDateString('en-GB') }}
                                            <span v-if="leave.start_date !== leave.end_date">
                                                → {{ new Date(leave.end_date).toLocaleDateString('en-GB') }}
                                            </span>
                                        </div>
                                        <div style="font-size:0.75rem;color:#9ca3af;">{{ daysBetween(leave.start_date, leave.end_date) }} day(s)</div>
                                    </td>

                                    <!-- Reason -->
                                    <td style="max-width:16rem;">
                                        <span style="font-size:0.75rem;color:#4b5563;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ leave.reason }}</span>
                                        <div v-if="leave.remarks" style="font-size:0.75rem;color:var(--accent);margin-top:0.125rem;font-style:italic;">
                                            Remark: {{ leave.remarks }}
                                        </div>
                                    </td>

                                    <!-- Document -->
                                    <td>
                                        <button v-if="leave.document_path"
                                            @click="openDoc(leave)"
                                            :class="leave.document_mime === 'application/pdf' ? 'badge badge-red' : 'badge badge-blue'"
                                            style="cursor:pointer;border:none;display:inline-flex;align-items:center;gap:0.375rem;">
                                            <span>{{ docTypeIcon(leave.document_mime) }}</span>
                                            <span style="max-width:5rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ leave.document_original_name }}</span>
                                        </button>
                                        <span v-else style="color:#d1d5db;">—</span>
                                    </td>

                                    <!-- Applied By -->
                                    <td style="font-size:0.75rem;color:#6b7280;">
                                        {{ leave.applied_by?.name || '—' }}
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <span :class="{
                                            'badge badge-amber': leave.status === 'pending',
                                            'badge badge-green': leave.status === 'approved',
                                            'badge badge-red':   leave.status === 'rejected',
                                        }" style="text-transform:capitalize;">
                                            {{ leave.status }}
                                        </span>
                                        <div v-if="leave.approver" style="font-size:0.75rem;color:#9ca3af;margin-top:0.125rem;">
                                            by {{ leave.approver.name }}
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td v-if="canApprove">
                                        <div style="display:flex;align-items:center;gap:0.25rem;">
                                            <template v-if="leave.status === 'pending'">
                                                <Button variant="success" size="xs" @click="openAction(leave.id, 'approve')">Approve</Button>
                                                <Button variant="danger" size="xs" @click="openAction(leave.id, 'reject')">Reject</Button>
                                            </template>
                                            <template v-else>
                                                <Button variant="secondary" size="xs" @click="revertLeave(leave.id)">Undo</Button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="leaves.links && leaves.links.length > 3" class="pagination-bar">
                <div class="pagination-links">
                    <template v-for="(link, key) in leaves.links" :key="key">
                        <span v-if="link.url === null" class="pagination-item pagination-disabled" v-html="link.label" />
                        <Link v-else :href="link.url"
                            class="pagination-item"
                            :class="{ 'pagination-active': link.active }"
                            v-html="link.label" />
                    </template>
                </div>
            </div>

        <!-- Approve / Reject Modal -->
        <transition name="fade">
            <div v-if="actionModal.show"
                class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
                @click.self="actionModal.show = false">
                <div class="card" style="width:100%;max-width:28rem;">
                    <div class="card-header">
                        <span class="card-title" style="text-transform:capitalize;">{{ actionModal.action }} Leave Application</span>
                    </div>
                    <div class="card-body">
                        <div class="form-field">
                            <label>Remarks <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                            <textarea v-model="actionModal.remarks" rows="3"
                                style="width:100%;border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.5rem 0.75rem;font-size:0.875rem;resize:none;"
                                placeholder="Add a remark for the student/parent..."></textarea>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
                            <Button variant="secondary" @click="actionModal.show = false">Cancel</Button>
                            <Button :variant="actionModal.action === 'approve' ? 'success' : 'danger'"
                                    @click="submitAction">
                                Confirm {{ actionModal.action === 'approve' ? 'Approval' : 'Rejection' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Document Preview Modal -->
        <transition name="fade">
            <div v-if="docModal.show"
                class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
                @click.self="docModal.show = false">
                <div class="card" style="width:100%;max-width:48rem;display:flex;flex-direction:column;max-height:90vh;">
                    <div class="card-header" style="justify-content:space-between;flex-shrink:0;">
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <span style="font-size:1.125rem;">{{ docModal.isPdf ? '📄' : '🖼️' }}</span>
                            <span class="card-title" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:16rem;">{{ docModal.name }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <Button size="sm" as="a" :href="docModal.url" target="_blank">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </Button>
                            <Button variant="secondary" size="sm" @click="docModal.show = false">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </Button>
                        </div>
                    </div>

                    <div style="flex:1;overflow:hidden;background:#f3f4f6;border-radius:0 0 0.75rem 0.75rem;">
                        <iframe v-if="docModal.isPdf"
                            :src="docModal.url"
                            style="width:100%;height:100%;border:0;min-height:70vh;border-radius:0 0 0.75rem 0.75rem;">
                        </iframe>
                        <div v-else style="display:flex;align-items:center;justify-content:center;padding:1rem;height:100%;min-height:60vh;">
                            <img :src="docModal.url" :alt="docModal.name"
                                style="max-width:100%;max-height:100%;object-fit:contain;border-radius:0.5rem;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                </div>
            </div>
        </transition>

    </SchoolLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Pagination */
.pagination-bar { display: flex; justify-content: center; margin-bottom: 1.5rem; }
.pagination-links { display: flex; gap: 4px; background: #fff; padding: 4px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.pagination-item { padding: 6px 12px; font-size: .875rem; border-radius: 6px; font-weight: 500; text-decoration: none; transition: background .15s; color: var(--text-primary); }
.pagination-active { background: var(--accent) !important; color: #fff !important; }
.pagination-disabled { color: var(--text-muted); background: #f9fafb; }
</style>
