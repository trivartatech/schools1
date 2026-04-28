<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed, watch, nextTick } from 'vue';
import QRCode from 'qrcode';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useDelete } from '@/Composables/useDelete';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const { canDo, canRequestEditStudent } = usePermissions();
const school = useSchoolStore();

const deleteStudent = (id) => {
    if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        router.delete(`/school/students/${id}`);
    }
};

const props = defineProps({
    student: Object,
    attendanceSummary:  { type: Object, default: () => ({total:0,present:0,absent:0,late:0,half_day:0,leave:0}) },
    monthlyAttendance:  { type: Object, default: () => ({}) },
    examMarks:          { type: Array, default: () => [] },
    siblings:           { type: Array, default: () => [] },
    classes:            { type: Array, default: () => [] },
    sections:           { type: Array, default: () => [] },
    academicYears:      { type: Array, default: () => [] },
    feePayments:        { type: Array, default: () => [] },
    transportRoutes:    { type: Array,  default: () => [] },
    standardMonths:     { type: Number, default: 10 },
    availableHostelBeds:{ type: Array,  default: () => [] },
});

// ── Date Formatting ───────────────────────────────────────────────────────────
const formatDate = (dateString) => dateString ? school.fmtDate(dateString) : '—';

// ── Active Tab ────────────────────────────────────────────────────────────────
const activeTab = ref('basic');

const tabs = [
    { key: 'basic',       label: 'Basic',       icon: '👤' },
    { key: 'contact',     label: 'Contact',     icon: '📞' },
    { key: 'guardian',    label: 'Guardian',    icon: '👨‍👩‍👧' },
    { key: 'sibling',     label: 'Sibling',     icon: '🧑‍🤝‍🧑' },
    { key: 'record',      label: 'Record',      icon: '📋' },
    { key: 'fee',         label: 'Fee',         icon: '💰' },
    { key: 'attendance',  label: 'Attendance',  icon: '📊' },
    { key: 'exam',        label: 'Exam Report', icon: '📝' },
    { key: 'documents',   label: 'Document',    icon: '📄' },
    { key: 'transport',   label: 'Transport',   icon: '🚌' },
    { key: 'hostel',      label: 'Hostel',      icon: '🏠' },
];

// ── Inline Admission No Edit ──────────────────────────────────────────────────
const editingAdmNo = ref(false);
const admNoForm    = useForm({ admission_no: props.student.admission_no ?? '' });
const saveAdmNo    = () => admNoForm.patch(`/school/students/${props.student.id}/admission-no`, {
    preserveScroll: true,
    onSuccess: () => { editingAdmNo.value = false; },
});

// ── Defaulter Flag (manual toggle) ────────────────────────────────────────────
const defaulterForm = useForm({ is_defaulter: !!props.student.is_defaulter });
const toggleDefaulter = () => {
    const next = !defaulterForm.is_defaulter;
    if (next && !confirm(`Mark ${props.student.first_name} as a fee defaulter?`)) return;
    defaulterForm.is_defaulter = next;
    defaulterForm.patch(`/school/students/${props.student.id}/defaulter`, {
        preserveScroll: true,
    });
};

// ── Attendance Helpers ────────────────────────────────────────────────────────
const attPct = computed(() => {
    const s = props.attendanceSummary;
    if (!s.total) return 0;
    return Math.round(((s.present + (s.late ?? 0) * 0.5 + (s.half_day ?? 0) * 0.5) / s.total) * 100);
});
const attColor = computed(() => {
    if (attPct.value >= 85) return { ring: '#22c55e', text: 'text-green-600', bg: 'bg-green-50', border: 'border-green-200', label: 'Excellent' };
    if (attPct.value >= 75) return { ring: '#f59e0b', text: 'text-amber-600', bg: 'bg-amber-50', border: 'border-amber-200', label: 'Satisfactory' };
    return { ring: '#ef4444', text: 'text-red-600', bg: 'bg-red-50', border: 'border-red-200', label: 'Low Attendance' };
});
const monthLabels    = computed(() => Object.keys(props.monthlyAttendance));
const maxMonthTotal  = computed(() => Math.max(1, ...monthLabels.value.map(m => props.monthlyAttendance[m].total)));
const CIRC           = 2 * Math.PI * 44;
const ringDash       = computed(() => (attPct.value / 100) * CIRC);

// ── Record Detail Modal ────────────────────────────────────────────────────────
const showRecordModal = ref(false);
const currentHistory  = computed(() => props.student.current_academic_history);

const recordForm = useForm({
    edit_admission_no:  false,
    edit_course:        false,
    admission_no:       props.student.admission_no ?? '',
    class_id:           currentHistory.value?.class_id ?? '',
    section_id:         currentHistory.value?.section_id ?? '',
    enrollment_type:    currentHistory.value?.enrollment_type ?? 'Regular',
    student_type:       currentHistory.value?.student_type ?? 'Old Student',
    status:             currentHistory.value?.status ?? 'current',
    remarks:            currentHistory.value?.remarks ?? '',
});

const filteredSections = computed(() =>
    props.sections.filter(s => !recordForm.class_id || s.course_class_id == recordForm.class_id)
);

const openRecordModal = () => {
    recordForm.edit_admission_no = false;
    recordForm.edit_course       = false;
    recordForm.admission_no      = props.student.admission_no ?? '';
    recordForm.class_id          = currentHistory.value?.class_id ?? '';
    recordForm.section_id        = currentHistory.value?.section_id ?? '';
    recordForm.enrollment_type   = currentHistory.value?.enrollment_type ?? 'Regular';
    recordForm.student_type      = currentHistory.value?.student_type ?? 'Old Student';
    recordForm.status            = currentHistory.value?.status ?? 'current';
    recordForm.remarks           = currentHistory.value?.remarks ?? '';
    showRecordModal.value = true;
};

const saveRecord = () => {
    recordForm.patch(`/school/students/${props.student.id}/record`, {
        preserveScroll: true,
        onSuccess: () => { showRecordModal.value = false; },
    });
};

const resetRecord = () => {
    recordForm.reset();
    recordForm.edit_admission_no = false;
    recordForm.edit_course       = false;
};

// ── Document Upload ───────────────────────────────────────────────────────────
const documentTypes = [
    'Birth Certificate','School Leaving Certificate (TC)','Aadhaar Card',
    'Caste Certificate','Income Certificate','Medical Certificate',
    'Previous Marksheet','Migration Certificate','Character Certificate',
    'Photograph','Passport','Other',
];
const showDocumentModal = ref(false);
const docForm = useForm({ document_type: '', title: '', is_original_submitted: false, original_file_location: '', file: null });
const submitDocument = () => {
    docForm.post(`/school/students/${props.student.id}/documents`, {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => { showDocumentModal.value = false; docForm.reset(); },
    });
};
const { del } = useDelete();
const deleteDocument = (docId) => del(`/school/students/${props.student.id}/documents/${docId}`, 'Delete this document?');

// ── ID Card Modal ─────────────────────────────────────────────────────────────
const showIdModal = ref(false);
const qrCanvas    = ref(null);

const studentQrTarget = computed(() =>
    props.student.uuid ? `${window.location.origin}/q/${props.student.uuid}` : null
);

async function renderIdQr() {
    await nextTick();
    if (!qrCanvas.value || !studentQrTarget.value) return;
    await QRCode.toCanvas(qrCanvas.value, studentQrTarget.value, {
        width:  200,
        margin: 2,
        errorCorrectionLevel: 'M',
    });
}

function downloadIdQr() {
    if (!qrCanvas.value) return;
    const link = document.createElement('a');
    link.download = `${props.student.admission_no || 'student'}-qr.png`;
    link.href = qrCanvas.value.toDataURL('image/png');
    link.click();
}

watch(showIdModal, (open) => { if (open) renderIdQr(); });

// ── Inline Assign Transport (shown when student has no allocation) ────────────
const showAssignTransport = ref(false);
const assignForm = useForm({
    student_ids:  [props.student.id],
    route_id:     '',
    stop_id:      '',
    vehicle_id:   '',
    pickup_type:  'both',
    months:       Math.floor(props.standardMonths || 10),
    days:         0,
    start_date:   new Date().toISOString().slice(0, 10),
    end_date:     '',
    status:       'active',
});

const assignRouteStops = computed(() => {
    if (!assignForm.route_id) return [];
    const r = props.transportRoutes.find(r => r.id == assignForm.route_id);
    return r?.stops ?? [];
});
const assignSelectedStop = computed(() => assignRouteStops.value.find(s => s.id == assignForm.stop_id));

const assignMonthsOpted = computed(() => {
    const m = Math.max(0, Math.min(24, Number(assignForm.months) || 0));
    const d = Math.max(0, Math.min(30, Number(assignForm.days)   || 0));
    return Math.round((m + d / 30) * 100) / 100;
});
const assignComputedFee = computed(() => {
    if (!assignSelectedStop.value?.fee) return 0;
    const std = Number(props.standardMonths) > 0 ? Number(props.standardMonths) : 10;
    return Math.round(((Number(assignSelectedStop.value.fee) / std) * assignMonthsOpted.value) * 100) / 100;
});
const assignTermTooShort = computed(() => assignMonthsOpted.value > 0 && assignMonthsOpted.value < 0.5);

function onAssignRouteChange() { assignForm.stop_id = ''; }

function submitAssignTransport() {
    assignForm.post('/school/transport/allocations', {
        preserveScroll: true,
        onSuccess: () => { showAssignTransport.value = false; },
    });
}

// ── Inline Assign Hostel (shown when student has no allocation) ───────────────
const showAssignHostel = ref(false);
const assignHostelForm = useForm({
    student_id:        props.student.id,
    hostel_bed_id:     '',
    admission_date:    new Date().toISOString().slice(0, 10),
    guardian_name:     '',
    guardian_phone:    '',
    guardian_relation: '',
    medical_info:      '',
    mess_type:         'Veg',
    months_opted:      '',
});

const selectedHostelBed = computed(() =>
    props.availableHostelBeds.find(b => b.id == assignHostelForm.hostel_bed_id)
);

const computedHostelFee = computed(() => {
    const cost   = Number(selectedHostelBed.value?.cost_per_month || 0);
    const months = Number(assignHostelForm.months_opted || 0);
    if (!cost || !months) return 0;
    return Math.round(cost * months * 100) / 100;
});

function openAssignHostel() {
    // Pre-fill guardian info from the parent record if available.
    const p = props.student.student_parent;
    if (p) {
        assignHostelForm.guardian_name = p.guardian_name || p.father_name || p.mother_name || '';
        assignHostelForm.guardian_phone = p.primary_phone || p.father_phone || '';
        assignHostelForm.guardian_relation = p.guardian_name ? 'Guardian' : (p.father_name ? 'Father' : 'Mother');
    }
    showAssignHostel.value = true;
}

function submitAssignHostel() {
    assignHostelForm
        .transform((data) => ({
            ...data,
            // Empty input would fail `nullable|numeric` validation; send null instead.
            months_opted: data.months_opted === '' || data.months_opted === null
                ? null
                : Number(data.months_opted),
        }))
        .post('/school/hostel/allocations', {
            preserveScroll: true,
            onSuccess: () => { showAssignHostel.value = false; },
        });
}
</script>

<template>
    <SchoolLayout :title="`${student.first_name} ${student.last_name || ''}'s Profile`">
        <div class="student-show-wrap">

            <!-- ══ HERO CARD ═══════════════════════════════════════════════════ -->
            <div class="hero-card">
                <div class="hero-avatar-wrap">
                    <img v-if="student.photo"
                         :src="`/storage/${student.photo}`"
                         alt="Student Photo"
                         class="hero-avatar" />
                    <div v-else class="hero-avatar hero-avatar-fallback">
                        {{ student.first_name?.charAt(0) }}{{ student.last_name?.charAt(0) ?? '' }}
                    </div>
                    <span class="hero-status-dot"></span>
                </div>

                <div class="hero-info">
                    <h1 class="hero-name">{{ student.first_name }} {{ student.last_name }}</h1>
                    <div class="hero-meta">
                        <span v-if="student.current_academic_history" class="hero-class-badge">
                            {{ student.current_academic_history.course_class?.name }}
                            <template v-if="student.current_academic_history.section">
                                &middot; {{ student.current_academic_history.section?.name }}
                            </template>
                        </span>
                        <span class="badge badge-green">{{ student.status ?? 'Active' }}</span>
                        <button
                            v-if="canDo('edit', 'students')"
                            type="button"
                            class="defaulter-toggle"
                            :class="{ 'defaulter-toggle--on': defaulterForm.is_defaulter, 'defaulter-toggle--busy': defaulterForm.processing }"
                            :disabled="defaulterForm.processing"
                            :title="defaulterForm.is_defaulter ? 'Click to unflag' : 'Flag as fee defaulter'"
                            @click="toggleDefaulter">
                            <span class="defaulter-dot"></span>
                            <span>{{ defaulterForm.is_defaulter ? 'Defaulter' : 'Not Defaulter' }}</span>
                        </button>
                        <span v-else-if="student.is_defaulter" class="badge badge-red">Defaulter</span>
                        <span v-if="student.erp_no" class="hero-erp-no">{{ student.erp_no }}</span>
                        <span class="hero-adm-no">{{ student.admission_no }}</span>
                    </div>
                    <div v-if="canRequestEditStudent" class="hero-notice">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Submit a request to edit any information, or use Edit Profile.
                    </div>
                </div>

                <div class="hero-actions">
                    <Button size="sm" as="link" v-if="canDo('edit', 'students')" :href="`/school/students/${student.id}/edit`">
                        ✏️ Edit
                    </Button>
                    <Button variant="secondary" size="sm" @click="showIdModal = true">
                        🪪 ID Card
                    </Button>
                    <Button variant="secondary" size="sm" as="link" v-if="canRequestEditStudent" :href="`/school/students/${student.id}/request-edit`">
                        📝 Request Update
                    </Button>
                    <Button variant="secondary" size="sm" as="link" href="/school/students">
                        ← Back
                    </Button>
                    <Button variant="danger" size="sm" v-if="canDo('delete', 'students')" @click="deleteStudent(student.id)">
                        🗑️ Delete
                    </Button>
                </div>
            </div>

            <!-- ══ TAB BAR ═════════════════════════════════════════════════════ -->
            <div class="tab-bar">
                <Button
                    v-for="tab in tabs"
                    :key="tab.key"
                    variant="tab"
                    :active="activeTab === tab.key"
                    @click="activeTab = tab.key"
                >
                    <span class="tab-icon">{{ tab.icon }}</span>
                    <span class="tab-label">{{ tab.label }}</span>
                </Button>
            </div>

            <!-- ══ TAB CONTENT ═════════════════════════════════════════════════ -->
            <div class="tab-content">

                <!-- ─── TAB: BASIC ─────────────────────────────────────────── -->
                <div v-if="activeTab === 'basic'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Basic Information</span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <!-- Admission No with inline edit -->
                                <div class="info-field">
                                    <p class="info-label">Registration Number</p>
                                    <div class="inline-edit-row">
                                        <template v-if="!editingAdmNo">
                                            <span class="info-value info-mono">{{ student.admission_no }}</span>
                                            <button @click="() => { admNoForm.admission_no = student.admission_no; editingAdmNo = true; }"
                                                    class="inline-edit-btn" title="Edit">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <input v-model="admNoForm.admission_no" type="text"
                                                   class="inline-edit-input"
                                                   @keyup.enter="saveAdmNo" @keyup.esc="editingAdmNo = false" />
                                            <Button size="xs" @click="saveAdmNo" :loading="admNoForm.processing">Save</Button>
                                            <Button variant="secondary" size="xs" @click="editingAdmNo = false">✕</Button>
                                        </template>
                                    </div>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Student Type</p>
                                    <span class="info-value">{{ student.student_type ?? 'Old Student' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Father Name</p>
                                    <span class="info-value">{{ student.student_parent?.father_name || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Mother Name</p>
                                    <span class="info-value">{{ student.student_parent?.mother_name || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Birth Date</p>
                                    <span class="info-value">{{ formatDate(student.dob) }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Gender</p>
                                    <span class="info-value">{{ student.gender || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Birth Place</p>
                                    <span class="info-value">{{ student.birth_place || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Nationality</p>
                                    <span class="info-value">{{ student.nationality || 'India' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Mother Tongue</p>
                                    <span class="info-value">{{ student.mother_tongue || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Blood Group</p>
                                    <span class="info-value">{{ student.blood_group || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Religion</p>
                                    <span class="info-value">{{ student.religion || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Category</p>
                                    <span class="info-value">{{ student.category || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Caste</p>
                                    <span class="info-value">{{ student.caste || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Aadhaar No</p>
                                    <span class="info-value info-mono">{{ student.aadhaar_no || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Admission Date</p>
                                    <span class="info-value">{{ student.admission_date || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Status</p>
                                    <span class="badge badge-green">{{ student.status ?? 'Active' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic History sub-card -->
                    <div class="card" style="margin-top: 16px;">
                        <div class="card-header">
                            <span class="card-title">Academic History</span>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-wrap">
                                <Table>
                                    <thead>
                                        <tr>
                                            <th>Year</th>
                                            <th>Class</th>
                                            <th>Section</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="history in student.academic_histories" :key="history.id">
                                            <td>{{ history.academic_year?.name }}</td>
                                            <td>{{ history.course_class?.name }}</td>
                                            <td>{{ history.section?.name || '—' }}</td>
                                            <td>
                                                <span class="badge badge-indigo">{{ history.status }}</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!student.academic_histories?.length">
                                            <td colspan="4" class="empty-cell">No academic history found</td>
                                        </tr>
                                    </tbody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: CONTACT ───────────────────────────────────────── -->
                <div v-if="activeTab === 'contact'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Contact Information</span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid info-grid--2">
                                <div class="info-field">
                                    <p class="info-label">Primary Phone</p>
                                    <span class="info-value info-mono">{{ student.student_parent?.primary_phone || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Father Phone</p>
                                    <span class="info-value info-mono">{{ student.student_parent?.father_phone || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Mother Phone</p>
                                    <span class="info-value info-mono">{{ student.student_parent?.mother_phone || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Student Address</p>
                                    <span class="info-value">{{ student.address || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Parent Address</p>
                                    <span class="info-value">{{ student.student_parent?.address || '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: GUARDIAN ──────────────────────────────────────── -->
                <div v-if="activeTab === 'guardian'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Guardian / Parent Details</span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid info-grid--3">
                                <div class="info-field">
                                    <p class="info-label">Father Name</p>
                                    <span class="info-value">{{ student.student_parent?.father_name || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Father Occupation</p>
                                    <span class="info-value">{{ student.student_parent?.father_occupation || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Father Phone</p>
                                    <span class="info-value info-mono">{{ student.student_parent?.father_phone || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Father Qualification</p>
                                    <span class="info-value">{{ student.student_parent?.father_qualification || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Mother Name</p>
                                    <span class="info-value">{{ student.student_parent?.mother_name || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Mother Occupation</p>
                                    <span class="info-value">{{ student.student_parent?.mother_occupation || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Mother Phone</p>
                                    <span class="info-value info-mono">{{ student.student_parent?.mother_phone || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Mother Qualification</p>
                                    <span class="info-value">{{ student.student_parent?.mother_qualification || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Guardian Name</p>
                                    <span class="info-value">{{ student.student_parent?.guardian_name || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Guardian Email</p>
                                    <span class="info-value info-mono">{{ student.student_parent?.guardian_email || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Guardian Phone</p>
                                    <span class="info-value info-mono">{{ student.student_parent?.guardian_phone || '—' }}</span>
                                </div>
                                <div class="info-field info-field--full">
                                    <p class="info-label">Address</p>
                                    <span class="info-value">{{ student.student_parent?.address || '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: SIBLING ───────────────────────────────────────── -->
                <div v-if="activeTab === 'sibling'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Siblings</span>
                            <span class="badge badge-blue">{{ siblings.length }} found</span>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div v-if="siblings.length === 0" class="empty-state">
                                <div class="empty-state-icon">🧑‍🤝‍🧑</div>
                                <p class="empty-state-title">No siblings found</p>
                                <p class="empty-state-sub">No other students are registered under the same parent.</p>
                            </div>
                            <div v-else class="sibling-list">
                                <Link
                                    v-for="sibling in siblings"
                                    :key="sibling.id"
                                    :href="`/school/students/${sibling.id}`"
                                    class="sibling-row"
                                >
                                    <div class="sibling-avatar-wrap">
                                        <img v-if="sibling.photo"
                                             :src="`/storage/${sibling.photo}`"
                                             class="sibling-avatar" />
                                        <div v-else class="sibling-avatar sibling-avatar-fallback">
                                            {{ sibling.first_name?.charAt(0) }}{{ sibling.last_name?.charAt(0) ?? '' }}
                                        </div>
                                    </div>
                                    <div class="sibling-info">
                                        <p class="sibling-name">{{ sibling.first_name }} {{ sibling.last_name }}</p>
                                        <div class="sibling-meta">
                                            <span class="info-mono" style="font-size:12px;color:var(--muted)">{{ sibling.admission_no }}</span>
                                            <span v-if="sibling.current_academic_history" class="badge badge-blue">
                                                {{ sibling.current_academic_history.course_class?.name }}
                                                <template v-if="sibling.current_academic_history.section"> &middot; {{ sibling.current_academic_history.section?.name }}</template>
                                            </span>
                                            <span class="badge"
                                                  :class="sibling.gender === 'Male' ? 'badge-indigo' : 'badge-rose'">
                                                {{ sibling.gender ?? '—' }}
                                            </span>
                                        </div>
                                    </div>
                                    <svg class="sibling-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: RECORD ────────────────────────────────────────── -->
                <div v-if="activeTab === 'record'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Academic Record</span>
                            <Button size="sm" @click="openRecordModal">
                                ✏️ Record Detail
                            </Button>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-wrap">
                                <Table>
                                    <thead>
                                        <tr>
                                            <th>Year</th>
                                            <th>Class</th>
                                            <th>Section</th>
                                            <th>Enrollment Type</th>
                                            <th>Student Type</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="history in student.academic_histories" :key="history.id">
                                            <td>{{ history.academic_year?.name }}</td>
                                            <td>{{ history.course_class?.name }}</td>
                                            <td>{{ history.section?.name || '—' }}</td>
                                            <td>{{ history.enrollment_type || 'Regular' }}</td>
                                            <td>{{ history.student_type || 'Old Student' }}</td>
                                            <td>
                                                <span class="badge"
                                                      :class="{
                                                          'badge-green':  history.status === 'current',
                                                          'badge-blue':   history.status === 'promoted',
                                                          'badge-red':    history.status === 'detained',
                                                          'badge-purple': history.status === 'graduated',
                                                      }">{{ history.status }}</span>
                                            </td>
                                            <td class="cell-muted truncate-cell">{{ history.remarks || '—' }}</td>
                                        </tr>
                                        <tr v-if="!student.academic_histories?.length">
                                            <td colspan="7" class="empty-cell">No academic history found</td>
                                        </tr>
                                    </tbody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: FEE ───────────────────────────────────────────── -->
                <div v-if="activeTab === 'fee'">
                    <!-- Fee Summary -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <span class="card-title">Fee Summary</span>
                            <Button variant="success" as="link" :href="`/school/fee/collect?student_id=${student.id}`" style="margin-left:auto;">
                                💰 Collect Fee
                            </Button>
                        </div>
                        <div class="card-body">
                            <div v-if="student.fee_total !== undefined" class="fee-grid">
                                <div class="stat-card">
                                    <div class="stat-card-icon" style="background:var(--indigo-light,#e0e7ff);color:#6366f1;">₹</div>
                                    <div>
                                        <div class="stat-card-value">₹{{ Number(student.fee_total).toLocaleString('en-IN') }}</div>
                                        <div class="stat-card-label">Total Fees</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-card-icon" style="background:#d1fae5;color:#059669;">✓</div>
                                    <div>
                                        <div class="stat-card-value" style="color:var(--success)">₹{{ Number(student.fee_paid).toLocaleString('en-IN') }}</div>
                                        <div class="stat-card-label">Total Paid</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-card-icon" style="background:#ede9fe;color:#7c3aed;">%</div>
                                    <div>
                                        <div class="stat-card-value" style="color:#7c3aed;">₹{{ Number(student.fee_discount ?? 0).toLocaleString('en-IN') }}</div>
                                        <div class="stat-card-label">Discount</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-card-icon"
                                         :style="student.fee_balance > 0 ? 'background:#fee2e2;color:var(--danger)' : 'background:#d1fae5;color:var(--success)'">
                                        {{ student.fee_balance > 0 ? '!' : '✓' }}
                                    </div>
                                    <div>
                                        <div class="stat-card-value"
                                             :style="student.fee_balance > 0 ? 'color:var(--danger)' : 'color:var(--success)'">
                                            {{ student.fee_balance > 0 ? '₹' + Number(student.fee_balance).toLocaleString('en-IN') : 'Paid' }}
                                        </div>
                                        <div class="stat-card-label">Balance Due</div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="empty-state">
                                <div class="empty-state-icon">💰</div>
                                <p class="empty-state-title">No fee structure found for this academic year.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Payment History</span>
                            <span v-if="feePayments.length" class="badge-count" style="margin-left:8px;">{{ feePayments.length }}</span>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <div v-if="feePayments.length === 0" class="empty-state" style="padding:32px;">
                                <div class="empty-state-icon">🧾</div>
                                <p class="empty-state-title">No payments recorded for this academic year.</p>
                            </div>
                            <div v-else class="payment-table-wrap">
                                <table class="payment-table">
                                    <thead>
                                        <tr>
                                            <th>Receipt No.</th>
                                            <th>Date</th>
                                            <th>Fee Head</th>
                                            <th>Mode</th>
                                            <th style="text-align:right;">Amount</th>
                                            <th style="text-align:right;">Balance</th>
                                            <th style="text-align:center;">Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="p in feePayments" :key="p.id">
                                            <td>
                                                <span class="receipt-no">{{ p.receipt_no ?? '—' }}</span>
                                            </td>
                                            <td>{{ p.payment_date ? school.fmtDate(p.payment_date) : '—' }}</td>
                                            <td>
                                                <div style="font-weight:600;color:#1e293b;">{{ p.fee_head ?? '—' }}</div>
                                                <div v-if="p.fee_group" style="font-size:11px;color:#94a3b8;">{{ p.fee_group }}</div>
                                            </td>
                                            <td>
                                                <span class="mode-badge">{{ p.payment_mode ? p.payment_mode.replace(/_/g,' ').toUpperCase() : '—' }}</span>
                                            </td>
                                            <td style="text-align:right;font-weight:700;color:#16a34a;">
                                                ₹{{ Number(p.amount_paid).toLocaleString('en-IN') }}
                                            </td>
                                            <td style="text-align:right;" :style="p.balance > 0 ? 'color:#dc2626;font-weight:600;' : 'color:#16a34a;'">
                                                {{ p.balance > 0 ? '₹' + Number(p.balance).toLocaleString('en-IN') : 'Nil' }}
                                            </td>
                                            <td style="text-align:center;">
                                                <a :href="`/school/fee/collect/${p.id}/receipt`" target="_blank" class="receipt-link">
                                                    🖨 Print
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: ATTENDANCE ────────────────────────────────────── -->
                <div v-if="activeTab === 'attendance'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Attendance Report</span>
                        </div>
                        <div class="card-body">
                            <div v-if="!attendanceSummary.total" class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <p class="empty-state-title">No attendance records found for this academic year.</p>
                            </div>

                            <template v-else>
                                <div class="att-summary">
                                    <!-- SVG Ring Chart -->
                                    <div class="att-ring-wrap">
                                        <svg width="120" height="120" viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="44" fill="none" stroke="#f3f4f6" stroke-width="10" />
                                            <circle cx="50" cy="50" r="44" fill="none"
                                                    :stroke="attColor.ring" stroke-width="10"
                                                    stroke-linecap="round"
                                                    :stroke-dasharray="`${ringDash} ${CIRC}`"
                                                    stroke-dashoffset="0"
                                                    transform="rotate(-90 50 50)"
                                                    style="transition: stroke-dasharray 0.6s ease" />
                                            <text x="50" y="46" text-anchor="middle" :fill="attColor.ring" style="font-size:18px;font-weight:800;">{{ attPct }}%</text>
                                            <text x="50" y="62" text-anchor="middle" fill="#9ca3af" style="font-size:8px;">Attendance</text>
                                        </svg>
                                        <span class="att-ring-label" :class="attColor.text">{{ attColor.label }}</span>
                                    </div>

                                    <!-- Stat cards -->
                                    <div class="att-stats-grid">
                                        <div v-for="[cls, lbl, key] in [['green','Present','present'],['red','Absent','absent'],['amber','Late','late'],['blue','Half Day','half_day'],['purple','Leave','leave'],['gray','Total','total']]"
                                             :key="key"
                                             class="att-stat-item"
                                             :class="`att-stat--${cls}`">
                                            <div class="att-stat-count">{{ attendanceSummary[key] }}</div>
                                            <div>
                                                <p class="att-stat-label-sm">{{ lbl }}</p>
                                                <p class="att-stat-pct">
                                                    {{ attendanceSummary.total ? Math.round(attendanceSummary[key] / attendanceSummary.total * 100) : 0 }}%
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Monthly bars -->
                                <div v-if="monthLabels.length" class="att-monthly">
                                    <p class="att-monthly-title">Monthly Breakdown</p>
                                    <div class="att-bars-scroll">
                                        <div class="att-bars-row">
                                            <div v-for="month in monthLabels" :key="month" class="att-bar-col group">
                                                <div class="att-bar-stack" :title="month">
                                                    <div class="att-bar-segment att-bar--green" :style="{height: (monthlyAttendance[month].present / maxMonthTotal * 80) + 'px'}"></div>
                                                    <div class="att-bar-segment att-bar--amber" :style="{height: (monthlyAttendance[month].late / maxMonthTotal * 80) + 'px'}"></div>
                                                    <div class="att-bar-segment att-bar--blue" :style="{height: (monthlyAttendance[month].half_day / maxMonthTotal * 80) + 'px'}"></div>
                                                    <div class="att-bar-segment att-bar--red" :style="{height: (monthlyAttendance[month].absent / maxMonthTotal * 80) + 'px'}"></div>
                                                    <div class="att-bar-tooltip">
                                                        <p style="font-weight:600;">{{ month }}</p>
                                                        <p>✅ {{ monthlyAttendance[month].present }} Present</p>
                                                        <p>❌ {{ monthlyAttendance[month].absent }} Absent</p>
                                                    </div>
                                                </div>
                                                <span class="att-bar-month">{{ month.slice(0,3) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="att-legend">
                                        <span v-for="[color, label] in [['att-legend--green','Present'],['att-legend--red','Absent'],['att-legend--amber','Late'],['att-legend--blue','Half Day']]"
                                              :key="label" class="att-legend-item">
                                            <span :class="['att-legend-dot', color]"></span> {{ label }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: EXAM REPORT ───────────────────────────────────── -->
                <div v-if="activeTab === 'exam'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Exam Performance</span>
                            <Button variant="secondary" size="sm" as="link" v-if="examMarks.length" :href="`/school/report-cards?class_id=${student.current_academic_history?.class_id || ''}&section_id=${student.current_academic_history?.section_id || ''}`">
                                Generate Report Card →
                            </Button>
                        </div>
                        <div class="card-body">
                            <div v-if="!examMarks.length" class="empty-state">
                                <div class="empty-state-icon">📄</div>
                                <p class="empty-state-title">No exam marks recorded for this academic year.</p>
                            </div>

                            <div v-else class="exam-list">
                                <div v-for="exam in examMarks" :key="exam.id" class="exam-block">
                                    <div class="exam-block-header">
                                        <span class="badge badge-indigo exam-name-badge">{{ exam.exam_name }}</span>
                                        <Button variant="secondary" size="sm" as="a" :href="`/school/report-cards/print?exam_schedule_id=${exam.id}&section_id=${student.current_academic_history?.section_id}&student_ids=${student.id}`" target="_blank">
                                            🖨️ Print Report Card
                                        </Button>
                                    </div>
                                    <div class="table-wrap">
                                        <Table>
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th style="text-align:center">Marks</th>
                                                    <th style="text-align:center">Percentage</th>
                                                    <th style="text-align:center">Grade</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="subject in exam.subjects" :key="subject.name">
                                                    <td>{{ subject.name }}</td>
                                                    <td style="text-align:center">
                                                        <span v-if="subject.is_absent" class="badge badge-red">ABS</span>
                                                        <span v-else>
                                                            <strong>{{ subject.obtained }}</strong>
                                                            <span style="color:var(--muted)"> / {{ subject.max }}</span>
                                                        </span>
                                                    </td>
                                                    <td style="text-align:center">
                                                        <div class="pct-bar-wrap">
                                                            <div class="pct-bar-track">
                                                                <div class="pct-bar-fill"
                                                                     :class="subject.percentage >= 75 ? 'pct-bar--green' : (subject.percentage >= 40 ? 'pct-bar--blue' : 'pct-bar--red')"
                                                                     :style="{width: Math.min(100,subject.percentage) + '%'}"></div>
                                                            </div>
                                                            <span class="pct-val" :class="subject.percentage >= 40 ? '' : 'pct-val--danger'">{{ subject.percentage }}%</span>
                                                        </div>
                                                    </td>
                                                    <td style="text-align:center">
                                                        <span class="badge"
                                                              :class="subject.grade === 'ABS' ? 'badge-red' : 'badge-gray'">{{ subject.grade }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </Table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: DOCUMENTS ─────────────────────────────────────── -->
                <div v-if="activeTab === 'documents'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Documents</span>
                            <Button size="sm" @click="showDocumentModal = true">
                                + Add Document
                            </Button>
                        </div>
                        <div class="card-body">
                            <div v-if="student.documents?.length" class="doc-list">
                                <div v-for="doc in student.documents" :key="doc.id" class="doc-row">
                                    <div class="doc-icon" :class="doc.file_path ? 'doc-icon--blue' : 'doc-icon--gray'">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div class="doc-info">
                                        <p class="doc-title">{{ doc.title }}</p>
                                        <div class="doc-meta">
                                            <span class="badge badge-gray">{{ doc.document_type }}</span>
                                            <span v-if="doc.is_original_submitted" class="badge badge-green">Original Submitted</span>
                                        </div>
                                    </div>
                                    <div class="doc-actions">
                                        <a v-if="doc.file_path" :href="`/storage/${doc.file_path}`" target="_blank" class="doc-action-btn doc-action-btn--view">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                        <button @click="deleteDocument(doc.id)" class="doc-action-btn doc-action-btn--del">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="empty-state">
                                <svg class="w-12 h-12 empty-state-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h4a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                </svg>
                                <p class="empty-state-title">No documents added yet.</p>
                                <Button variant="secondary" size="sm" @click="showDocumentModal = true" class="mt-2">Add the first document</Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: TRANSPORT ─────────────────────────────────────── -->
                <div v-if="activeTab === 'transport'">
                    <div class="card">
                        <div class="card-header" style="gap:0.5rem;flex-wrap:wrap;">
                            <span class="card-title">Transport Details</span>
                            <div style="display:flex;gap:0.5rem;margin-left:auto;flex-wrap:wrap;">
                                <Button v-if="student.transport_allocation" size="sm" as="a"
                                        :href="`/school/transport/fees/${student.transport_allocation.id}`">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                    </svg>
                                    Collect Fee
                                </Button>
                                <Button variant="secondary" size="sm" as="a" href="/school/transport/allocations">Manage Allocations</Button>
                            </div>
                        </div>
                        <div class="card-body" v-if="student.transport_allocation">
                            <div class="transport-banner">
                                <div class="transport-banner-icon">
                                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-3M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M8 6h8"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="transport-route-name">{{ student.transport_allocation.route?.route_name ?? '—' }}</div>
                                    <div class="transport-route-meta">
                                        {{ student.transport_allocation.route?.start_location }}
                                        <span v-if="student.transport_allocation.route?.end_location"> → {{ student.transport_allocation.route.end_location }}</span>
                                    </div>
                                </div>
                                <div style="margin-left:auto;display:flex;gap:0.375rem;align-items:center;flex-wrap:wrap;">
                                    <span :class="['badge', student.transport_allocation.status === 'active' ? 'badge-green' : 'badge-gray']">
                                        {{ student.transport_allocation.status }}
                                    </span>
                                    <span :class="[
                                        'badge',
                                        student.transport_allocation.payment_status === 'paid'    ? 'badge-green' :
                                        student.transport_allocation.payment_status === 'partial' ? 'badge-yellow' :
                                        student.transport_allocation.payment_status === 'waived'  ? 'badge-gray'   : 'badge-red'
                                    ]" style="text-transform:capitalize;">
                                        {{ student.transport_allocation.payment_status }}
                                    </span>
                                </div>
                            </div>

                            <div class="transport-grid">
                                <div class="transport-field">
                                    <div class="transport-field-label">Boarding Stop</div>
                                    <div class="transport-field-value">{{ student.transport_allocation.stop?.stop_name ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Pickup Time</div>
                                    <div class="transport-field-value">{{ student.transport_allocation.stop?.pickup_time ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Drop Time</div>
                                    <div class="transport-field-value">{{ student.transport_allocation.stop?.drop_time ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Pickup Type</div>
                                    <div class="transport-field-value" style="text-transform:capitalize;">{{ student.transport_allocation.pickup_type ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Vehicle</div>
                                    <div class="transport-field-value">{{ student.transport_allocation.vehicle?.vehicle_no ?? 'Not assigned' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Transport Fee</div>
                                    <div class="transport-field-value">₹{{ student.transport_allocation.transport_fee ?? '0' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Paid</div>
                                    <div class="transport-field-value" style="color:#059669;">₹{{ student.transport_allocation.amount_paid ?? '0' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Outstanding</div>
                                    <div class="transport-field-value" :style="Number(student.transport_allocation.balance) > 0 ? 'color:#dc2626;font-weight:600;' : 'color:#6b7280;'">
                                        ₹{{ student.transport_allocation.balance ?? '0' }}
                                    </div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Start Date</div>
                                    <div class="transport-field-value">{{ student.transport_allocation.start_date ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Distance from School</div>
                                    <div class="transport-field-value">
                                        {{ student.transport_allocation.stop?.distance_from_school ? student.transport_allocation.stop.distance_from_school + ' km' : '—' }}
                                    </div>
                                </div>
                            </div>

                            <!-- All stops on the route -->
                            <div v-if="student.transport_allocation.route?.stops?.length" style="margin-top:20px;">
                                <div class="section-label" style="margin-bottom:8px;">Route Stops</div>
                                <div class="route-stops-timeline">
                                    <div
                                        v-for="(stop, i) in student.transport_allocation.route.stops"
                                        :key="stop.id"
                                        :class="['route-stop', stop.id === student.transport_allocation.stop_id ? 'route-stop--active' : '']"
                                    >
                                        <div class="route-stop-dot"></div>
                                        <div class="route-stop-info">
                                            <span class="route-stop-name">{{ stop.stop_name }}</span>
                                            <span v-if="stop.pickup_time" class="route-stop-meta">{{ stop.pickup_time }}</span>
                                            <span v-if="stop.fee" class="route-stop-meta">₹{{ stop.fee }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" v-else>
                            <!-- Empty state (before user clicks "Assign Transport") -->
                            <div v-if="!showAssignTransport" class="empty-state">
                                <svg class="w-12 h-12 empty-state-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 6H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-3M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M8 6h8" />
                                </svg>
                                <p class="empty-state-title">No transport allocated</p>
                                <p class="empty-state-sub">This student is not assigned to any transport route.</p>
                                <Button v-if="transportRoutes.length" size="sm" @click="showAssignTransport = true" class="mt-2.5">
                                    Assign Transport
                                </Button>
                                <p v-else class="empty-state-sub" style="margin-top:8px;">
                                    No active transport routes. Add routes in the Transport module first.
                                </p>
                            </div>

                            <!-- Inline Assign Transport form -->
                            <form v-else @submit.prevent="submitAssignTransport" class="assign-transport-form">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                                    <h4 style="font-size:0.95rem;font-weight:600;color:#111827;margin:0;">Assign Transport</h4>
                                    <Button variant="secondary" size="xs" type="button" @click="showAssignTransport = false">Cancel</Button>
                                </div>

                                <div v-if="Object.keys(assignForm.errors).length" style="background:#fef2f2;border:1px solid #fecaca;border-radius:0.5rem;padding:0.65rem 0.9rem;margin-bottom:12px;">
                                    <p v-for="(msg, key) in assignForm.errors" :key="key" style="font-size:0.8rem;color:#dc2626;margin:0.1rem 0;">{{ Array.isArray(msg) ? msg[0] : msg }}</p>
                                </div>

                                <div class="form-row form-row-3">
                                    <div class="form-field">
                                        <label>Route *</label>
                                        <select v-model="assignForm.route_id" @change="onAssignRouteChange" required>
                                            <option value="">— Select Route —</option>
                                            <option v-for="r in transportRoutes" :key="r.id" :value="r.id">{{ r.route_name }}</option>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label>Boarding Stop *</label>
                                        <select v-model="assignForm.stop_id" :disabled="!assignForm.route_id" required>
                                            <option value="">Select Stop</option>
                                            <option v-for="s in assignRouteStops" :key="s.id" :value="s.id">
                                                {{ s.stop_name }}{{ s.fee ? ' — ₹' + s.fee : '' }}
                                            </option>
                                        </select>
                                        <span v-if="assignSelectedStop?.fee" class="field-hint">
                                            Stop full-term fee: <strong>₹{{ assignSelectedStop.fee }}</strong>
                                            <span style="color:#94a3b8;">(for {{ standardMonths }} months)</span>
                                        </span>
                                    </div>
                                    <div class="form-field">
                                        <label>Pickup Type *</label>
                                        <select v-model="assignForm.pickup_type" required>
                                            <option value="both">Both (Pickup &amp; Drop)</option>
                                            <option value="pickup">Pickup Only</option>
                                            <option value="drop">Drop Only</option>
                                        </select>
                                    </div>
                                </div>

                                <div v-if="assignForm.route_id" class="form-row form-row-3" style="margin-top:0.75rem;">
                                    <div class="form-field">
                                        <label>Months Opted *</label>
                                        <input v-model.number="assignForm.months" type="number" min="0" max="24" step="1" required>
                                        <span class="field-hint" style="color:#94a3b8;">Whole months (0–24)</span>
                                    </div>
                                    <div class="form-field">
                                        <label>Extra Days</label>
                                        <input v-model.number="assignForm.days" type="number" min="0" max="30" step="1">
                                        <span class="field-hint" style="color:#94a3b8;">0–30 days</span>
                                    </div>
                                    <div class="form-field">
                                        <label>Transport Fee (auto)</label>
                                        <div style="padding:0.5rem 0.75rem;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:0.5rem;font-size:0.9rem;color:#065f46;line-height:1.45;">
                                            <div><strong style="font-size:1rem;">₹{{ assignComputedFee }}</strong></div>
                                            <div style="font-size:0.75rem;color:#047857;">
                                                {{ assignForm.months || 0 }} mo{{ assignForm.days ? ' + ' + assignForm.days + ' d' : '' }}
                                                = {{ assignMonthsOpted }} months
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="assignTermTooShort" style="padding:0.5rem 0.75rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:0.5rem;font-size:0.8125rem;color:#92400e;margin-top:0.5rem;">
                                    Minimum term is 15 days (0.5 months).
                                </div>

                                <div class="form-row form-row-2" style="margin-top:0.75rem;">
                                    <div class="form-field">
                                        <label>Start Date</label>
                                        <input v-model="assignForm.start_date" type="date">
                                    </div>
                                    <div class="form-field">
                                        <label>End Date</label>
                                        <input v-model="assignForm.end_date" type="date">
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:14px;padding-top:12px;border-top:1px solid #e5e7eb;">
                                    <Button variant="secondary" type="button" @click="showAssignTransport = false">Cancel</Button>
                                    <Button type="submit"
                                            :loading="assignForm.processing"
                                            :disabled="assignForm.processing || assignTermTooShort || assignMonthsOpted === 0 || !assignForm.route_id || !assignForm.stop_id">
                                        {{ assignForm.processing ? 'Assigning…' : 'Assign Transport' }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: HOSTEL ────────────────────────────────────────── -->
                <div v-if="activeTab === 'hostel'">
                    <div class="card">
                        <div class="card-header" style="gap:0.5rem;flex-wrap:wrap;">
                            <span class="card-title">Hostel Details</span>
                            <div style="display:flex;gap:0.5rem;margin-left:auto;flex-wrap:wrap;">
                                <Button v-if="student.hostel_allocation" size="sm" as="a"
                                        :href="`/school/hostel/fees/${student.hostel_allocation.id}`">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                    </svg>
                                    Collect Fee
                                </Button>
                                <Button variant="secondary" size="sm" as="a" href="/school/hostel/allocations">Manage Allocations</Button>
                            </div>
                        </div>
                        <div class="card-body" v-if="student.hostel_allocation">
                            <div class="transport-banner">
                                <div class="transport-banner-icon">
                                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="transport-route-name">{{ student.hostel_allocation.bed?.room?.hostel?.name ?? '—' }}</div>
                                    <div class="transport-route-meta">
                                        <span v-if="student.hostel_allocation.bed?.room?.room_number">Room {{ student.hostel_allocation.bed.room.room_number }}</span>
                                        <span v-if="student.hostel_allocation.bed?.name"> · {{ student.hostel_allocation.bed.name }}</span>
                                    </div>
                                </div>
                                <div style="margin-left:auto;display:flex;gap:0.375rem;align-items:center;flex-wrap:wrap;">
                                    <span :class="['badge', student.hostel_allocation.status === 'Active' ? 'badge-green' : 'badge-gray']">
                                        {{ student.hostel_allocation.status }}
                                    </span>
                                    <span :class="[
                                        'badge',
                                        student.hostel_allocation.payment_status === 'paid'    ? 'badge-green' :
                                        student.hostel_allocation.payment_status === 'partial' ? 'badge-yellow' :
                                        student.hostel_allocation.payment_status === 'waived'  ? 'badge-gray'   : 'badge-red'
                                    ]" style="text-transform:capitalize;">
                                        {{ student.hostel_allocation.payment_status }}
                                    </span>
                                </div>
                            </div>

                            <div class="transport-grid">
                                <div class="transport-field">
                                    <div class="transport-field-label">Hostel</div>
                                    <div class="transport-field-value">{{ student.hostel_allocation.bed?.room?.hostel?.name ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Room Number</div>
                                    <div class="transport-field-value">{{ student.hostel_allocation.bed?.room?.room_number ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Bed</div>
                                    <div class="transport-field-value">{{ student.hostel_allocation.bed?.name ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Mess Type</div>
                                    <div class="transport-field-value">{{ student.hostel_allocation.mess_type ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Cost / Month</div>
                                    <div class="transport-field-value">₹{{ student.hostel_allocation.bed?.room?.cost_per_month ?? '0' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Hostel Fee Total</div>
                                    <div class="transport-field-value">₹{{ student.hostel_allocation.hostel_fee ?? '0' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Paid</div>
                                    <div class="transport-field-value" style="color:#059669;">₹{{ student.hostel_allocation.amount_paid ?? '0' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Outstanding</div>
                                    <div class="transport-field-value" :style="Number(student.hostel_allocation.balance) > 0 ? 'color:#dc2626;font-weight:600;' : 'color:#6b7280;'">
                                        ₹{{ student.hostel_allocation.balance ?? '0' }}
                                    </div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Months Opted</div>
                                    <div class="transport-field-value">{{ student.hostel_allocation.months_opted ?? '—' }}</div>
                                </div>
                                <div class="transport-field">
                                    <div class="transport-field-label">Admission Date</div>
                                    <div class="transport-field-value">{{ student.hostel_allocation.admission_date ?? '—' }}</div>
                                </div>
                                <div class="transport-field" v-if="student.hostel_allocation.guardian_name">
                                    <div class="transport-field-label">Guardian</div>
                                    <div class="transport-field-value">
                                        {{ student.hostel_allocation.guardian_name }}
                                        <span v-if="student.hostel_allocation.guardian_phone" style="color:#6b7280;font-size:0.78rem;">
                                            · {{ student.hostel_allocation.guardian_phone }}
                                        </span>
                                    </div>
                                </div>
                                <div class="transport-field" v-if="student.hostel_allocation.vacate_date">
                                    <div class="transport-field-label">Vacate Date</div>
                                    <div class="transport-field-value">{{ student.hostel_allocation.vacate_date }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" v-else>
                            <!-- Empty state (before user clicks "Assign Hostel") -->
                            <div v-if="!showAssignHostel" class="empty-state">
                                <svg class="w-12 h-12 empty-state-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <p class="empty-state-title">No hostel allocated</p>
                                <p class="empty-state-sub">This student is not assigned to any hostel bed.</p>
                                <Button v-if="availableHostelBeds.length" size="sm" @click="openAssignHostel" class="mt-2.5">
                                    Assign Hostel Bed
                                </Button>
                                <p v-else class="empty-state-sub" style="margin-top:8px;">
                                    No available beds. Add hostels and rooms in the Hostel module first.
                                </p>
                            </div>

                            <!-- Inline Assign Hostel form -->
                            <form v-else @submit.prevent="submitAssignHostel" class="assign-transport-form">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                                    <h4 style="font-size:0.95rem;font-weight:600;color:#111827;margin:0;">Assign Hostel Bed</h4>
                                    <Button variant="secondary" size="xs" type="button" @click="showAssignHostel = false">Cancel</Button>
                                </div>

                                <div v-if="Object.keys(assignHostelForm.errors).length" style="background:#fef2f2;border:1px solid #fecaca;border-radius:0.5rem;padding:0.65rem 0.9rem;margin-bottom:12px;">
                                    <p v-for="(msg, key) in assignHostelForm.errors" :key="key" style="font-size:0.8rem;color:#dc2626;margin:0.1rem 0;">{{ Array.isArray(msg) ? msg[0] : msg }}</p>
                                </div>

                                <div class="form-row form-row-3">
                                    <div class="form-field">
                                        <label>Available Bed *</label>
                                        <select v-model="assignHostelForm.hostel_bed_id" required>
                                            <option value="">— Select Bed —</option>
                                            <option v-for="b in availableHostelBeds" :key="b.id" :value="b.id">
                                                {{ b.hostel_name }} / Rm {{ b.room_number }} / {{ b.name }}{{ b.cost_per_month ? ' — ₹' + b.cost_per_month + '/mo' : '' }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label>Mess Type *</label>
                                        <select v-model="assignHostelForm.mess_type" required>
                                            <option value="Veg">Veg</option>
                                            <option value="Non-Veg">Non-Veg</option>
                                            <option value="Custom">Custom</option>
                                            <option value="None">None</option>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label>Months Opted</label>
                                        <input v-model.number="assignHostelForm.months_opted" type="number" min="0" max="24" step="0.5" placeholder="auto">
                                        <span class="field-hint" style="color:#94a3b8;">Blank = until academic year end</span>
                                    </div>
                                </div>

                                <div class="form-row form-row-2" style="margin-top:0.75rem;">
                                    <div class="form-field">
                                        <label>Admission Date *</label>
                                        <input v-model="assignHostelForm.admission_date" type="date" required>
                                    </div>
                                    <div class="form-field" v-if="selectedHostelBed">
                                        <label>Estimated Hostel Fee</label>
                                        <div style="padding:0.5rem 0.75rem;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:0.5rem;font-size:0.9rem;color:#065f46;line-height:1.45;">
                                            <div><strong style="font-size:1rem;">₹{{ computedHostelFee || 'auto' }}</strong></div>
                                            <div style="font-size:0.75rem;color:#047857;">
                                                {{ selectedHostelBed.cost_per_month }} × {{ assignHostelForm.months_opted || '?' }} months
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row form-row-2" style="margin-top:0.75rem;">
                                    <div class="form-field">
                                        <label>Guardian Name</label>
                                        <input v-model="assignHostelForm.guardian_name">
                                    </div>
                                    <div class="form-field">
                                        <label>Guardian Phone</label>
                                        <input v-model="assignHostelForm.guardian_phone">
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:14px;padding-top:12px;border-top:1px solid #e5e7eb;">
                                    <Button variant="secondary" type="button" @click="showAssignHostel = false">Cancel</Button>
                                    <Button type="submit"
                                            :loading="assignHostelForm.processing"
                                            :disabled="assignHostelForm.processing || !assignHostelForm.hostel_bed_id">
                                        {{ assignHostelForm.processing ? 'Assigning…' : 'Assign Hostel Bed' }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-content -->
        </div><!-- /student-show-wrap -->

        <!-- ══ RECORD DETAIL MODAL ═══════════════════════════════════════════ -->
        <div v-if="showRecordModal" class="modal-overlay">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 class="modal-title">Record Detail</h3>
                    <button @click="showRecordModal = false" class="modal-close">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Toggle: Edit Admission Number -->
                    <div class="modal-field">
                        <label class="toggle-row">
                            <span class="toggle-label">Edit Admission Number</span>
                            <div class="toggle-wrap" @click="recordForm.edit_admission_no = !recordForm.edit_admission_no">
                                <div class="toggle-track" :class="recordForm.edit_admission_no ? 'toggle-track--on' : ''"></div>
                                <div class="toggle-thumb" :class="recordForm.edit_admission_no ? 'toggle-thumb--on' : ''"></div>
                            </div>
                        </label>
                        <div v-if="recordForm.edit_admission_no" class="modal-sub-field">
                            <input v-model="recordForm.admission_no" type="text" placeholder="Admission Number"
                                   class="modal-input" />
                            <p v-if="recordForm.errors.admission_no" class="form-error">{{ recordForm.errors.admission_no }}</p>
                        </div>
                    </div>

                    <!-- Toggle: Edit Course -->
                    <div class="modal-field">
                        <label class="toggle-row">
                            <span class="toggle-label">Edit Course</span>
                            <div class="toggle-wrap" @click="recordForm.edit_course = !recordForm.edit_course">
                                <div class="toggle-track" :class="recordForm.edit_course ? 'toggle-track--on' : ''"></div>
                                <div class="toggle-thumb" :class="recordForm.edit_course ? 'toggle-thumb--on' : ''"></div>
                            </div>
                        </label>
                        <div v-if="recordForm.edit_course" class="modal-two-col">
                            <div>
                                <label class="modal-field-label">Class</label>
                                <select v-model="recordForm.class_id" @change="recordForm.section_id = ''" class="modal-select">
                                    <option value="">Select Class</option>
                                    <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="modal-field-label">Section</label>
                                <select v-model="recordForm.section_id" class="modal-select">
                                    <option value="">Select Section</option>
                                    <option v-for="sec in filteredSections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Enrollment Type + Student Type + Status -->
                    <div class="modal-three-col">
                        <div>
                            <label class="modal-field-label">Enrollment Type</label>
                            <select v-model="recordForm.enrollment_type" class="modal-select">
                                <option value="Regular">Regular</option>
                                <option value="Transfer">Transfer</option>
                                <option value="Lateral">Lateral</option>
                                <option value="Re-admission">Re-admission</option>
                            </select>
                        </div>
                        <div>
                            <label class="modal-field-label">Student Type</label>
                            <select v-model="recordForm.student_type" class="modal-select">
                                <option value="New Student">New Student</option>
                                <option value="Old Student">Old Student</option>
                            </select>
                        </div>
                        <div>
                            <label class="modal-field-label">Enrollment Status</label>
                            <select v-model="recordForm.status" class="modal-select">
                                <option value="current">Current</option>
                                <option value="promoted">Promoted</option>
                                <option value="detained">Detained</option>
                                <option value="graduated">Graduated</option>
                            </select>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="modal-field">
                        <label class="modal-field-label" style="font-size:14px;font-weight:500;">Remarks</label>
                        <textarea v-model="recordForm.remarks" rows="3" placeholder="Remarks" class="modal-input modal-textarea"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <Button variant="secondary" type="button" @click="resetRecord">Reset</Button>
                    <div style="display:flex;gap:8px;">
                        <Button variant="danger" type="button" @click="showRecordModal = false">Cancel</Button>
                        <Button type="button" @click="saveRecord" :loading="recordForm.processing">
                            <svg v-if="recordForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Save
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ DOCUMENT UPLOAD MODAL ══════════════════════════════════════════ -->
        <div v-if="showDocumentModal" class="modal-overlay">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 class="modal-title">Add Document</h3>
                    <button @click="showDocumentModal = false" class="modal-close">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitDocument" class="modal-body">
                    <div class="modal-field">
                        <label class="modal-field-label">Document Type <span style="color:var(--danger)">*</span></label>
                        <select v-model="docForm.document_type" required class="modal-select">
                            <option value="" disabled>Select type...</option>
                            <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label">Title <span style="color:var(--danger)">*</span></label>
                        <input v-model="docForm.title" type="text" required placeholder="e.g. Birth Certificate 2015" class="modal-input" />
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label">Upload Scanned Copy (optional)</label>
                        <input @input="docForm.file = $event.target.files[0]" type="file" accept=".pdf,.jpg,.jpeg,.png"
                               class="doc-file-input" />
                    </div>
                    <div class="modal-footer" style="border-top:none;padding-top:0;">
                        <div></div>
                        <div style="display:flex;gap:8px;">
                            <Button variant="secondary" type="button" @click="showDocumentModal = false">Cancel</Button>
                            <Button type="submit" :loading="docForm.processing">
                                Save Document
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ ID CARD MODAL ══════════════════════════════════════════════════ -->
        <div v-if="showIdModal" class="modal-overlay">
            <div class="modal-card modal-card--sm">
                <div class="modal-header">
                    <h3 class="modal-title">Virtual ID Card</h3>
                    <button @click="showIdModal = false" class="modal-close">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="modal-body id-card-body">
                    <div class="id-card-banner"></div>
                    <div class="id-card-content">
                        <img v-if="student.photo" :src="`/storage/${student.photo}`" class="id-card-photo" />
                        <div v-else class="id-card-photo id-card-photo-fallback">
                            {{ student.first_name?.charAt(0) }}
                        </div>
                        <h4 class="id-card-name">{{ student.first_name }} {{ student.last_name }}</h4>
                        <p class="id-card-adm">{{ student.admission_no }}</p>
                        <div class="id-card-qr-wrap">
                            <canvas v-if="studentQrTarget" ref="qrCanvas" class="id-card-qr" />
                            <p v-else class="id-card-qr-hint" style="color:#ef4444">No QR (missing UUID)</p>
                        </div>
                        <p class="id-card-qr-hint">Scan for Profile / Attendance</p>
                        <Button variant="secondary" block class="mt-2" @click="downloadIdQr" :disabled="!studentQrTarget">
                            Download QR
                        </Button>
                    </div>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Page wrapper ─────────────────────────────────────────────────────────── */
.student-show-wrap {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ── Hero Card ────────────────────────────────────────────────────────────── */
.hero-card {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border-radius: var(--radius-lg, 14px);
    padding: 28px 32px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    box-shadow: 0 4px 24px rgba(99,102,241,0.18);
}

.hero-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.hero-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.9);
    box-shadow: 0 2px 12px rgba(0,0,0,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-avatar-fallback {
    background: rgba(255,255,255,0.2);
    color: #fff;
    font-size: 28px;
    font-weight: 800;
}

.hero-status-dot {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 14px;
    height: 14px;
    background: #22c55e;
    border: 2px solid #fff;
    border-radius: 50%;
}

.hero-info {
    flex: 1;
    min-width: 0;
}

.hero-name {
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    margin: 0 0 8px;
    line-height: 1.2;
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.hero-class-badge {
    background: rgba(255,255,255,0.18);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.25);
}

.hero-erp-no {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    font-weight: 700;
    color: #ccfbf1;
    background: rgba(13,148,136,0.35);
    padding: 2px 10px;
    border-radius: 6px;
    letter-spacing: 0.03em;
}
.hero-adm-no {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: rgba(255,255,255,0.7);
    background: rgba(0,0,0,0.15);
    padding: 2px 8px;
    border-radius: 6px;
}

.hero-notice {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: rgba(255,255,255,0.75);
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 6px 12px;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    flex-shrink: 0;
}

/* ── Tab Bar ──────────────────────────────────────────────────────────────── */
.tab-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: var(--radius-lg, 14px);
    padding: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}


.tab-icon {
    font-size: 14px;
    line-height: 1;
}

.tab-label {
    line-height: 1;
}

/* ── Tab Content ──────────────────────────────────────────────────────────── */
.tab-content {
    /* children are .card blocks */
}

/* ── Info Grid ────────────────────────────────────────────────────────────── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
}

.info-grid--2 {
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}

.info-grid--3 {
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}

.info-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-field--full {
    grid-column: 1 / -1;
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin: 0;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    color: #1e293b;
}

.info-mono {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

/* ── Inline Edit ──────────────────────────────────────────────────────────── */
.inline-edit-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.inline-edit-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #cbd5e1;
    padding: 2px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    transition: color 0.15s;
}

.inline-edit-btn:hover {
    color: var(--accent, #6366f1);
}

.inline-edit-input {
    border: 1px solid #a5b4fc;
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 13px;
    font-family: 'Courier New', monospace;
    width: 130px;
    outline: none;
    transition: box-shadow 0.15s;
}

.inline-edit-input:focus {
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}

/* ── Table Wrap ───────────────────────────────────────────────────────────── */
.table-wrap {
    overflow-x: auto;
    border-radius: 0 0 var(--radius, 10px) var(--radius, 10px);
}

.empty-cell {
    text-align: center;
    padding: 28px 16px;
    color: #94a3b8;
    font-size: 14px;
}

.cell-muted {
    color: #94a3b8;
}

.truncate-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Empty State ──────────────────────────────────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 48px 16px;
    color: #94a3b8;
}

.empty-state-icon {
    font-size: 44px;
    margin-bottom: 12px;
}

.empty-state-svg {
    color: #cbd5e1;
    margin-bottom: 12px;
}

.empty-state-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 4px;
    color: #64748b;
}

.empty-state-sub {
    font-size: 12px;
    margin: 0;
    text-align: center;
}

/* ── Sibling List ─────────────────────────────────────────────────────────── */
.sibling-list {
    display: flex;
    flex-direction: column;
}

.sibling-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border, #e2e8f0);
    text-decoration: none;
    transition: background 0.12s;
}

.sibling-row:last-child {
    border-bottom: none;
}

.sibling-row:hover {
    background: #f8faff;
}

.sibling-avatar-wrap {
    flex-shrink: 0;
}

.sibling-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
}

.sibling-avatar-fallback {
    background: #e0e7ff;
    color: var(--accent, #6366f1);
    font-weight: 700;
    font-size: 15px;
}

.sibling-info {
    flex: 1;
    min-width: 0;
}

.sibling-name {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 4px;
}

.sibling-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.sibling-arrow {
    width: 16px;
    height: 16px;
    color: #cbd5e1;
    flex-shrink: 0;
    transition: color 0.12s;
}

.sibling-row:hover .sibling-arrow {
    color: var(--accent, #6366f1);
}

/* ── Fee Grid ─────────────────────────────────────────────────────────────── */
.fee-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
}

/* ── Payment History Table ────────────────────────────────────────────────── */
.payment-table-wrap {
    overflow-x: auto;
}
.payment-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.payment-table thead tr {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
.payment-table th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}
.payment-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.1s;
}
.payment-table tbody tr:last-child { border-bottom: none; }
.payment-table tbody tr:hover { background: #f8fafc; }
.payment-table td {
    padding: 12px 14px;
    color: #374151;
    vertical-align: middle;
}
.receipt-no {
    font-family: monospace;
    font-size: 12px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 4px;
    padding: 2px 7px;
    font-weight: 600;
}
.mode-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 4px;
    padding: 2px 7px;
    letter-spacing: 0.4px;
}
.receipt-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    color: #6366f1;
    text-decoration: none;
    background: #eef2ff;
    border-radius: 6px;
    padding: 4px 10px;
    transition: background 0.15s;
}
.receipt-link:hover { background: #e0e7ff; color: #4338ca; }
.badge-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #6366f1;
    color: #fff;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
}

/* ── Attendance ───────────────────────────────────────────────────────────── */
.att-summary {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 24px;
    margin-bottom: 24px;
}

.att-ring-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.att-ring-label {
    font-size: 12px;
    font-weight: 700;
}

.att-stats-grid {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
}

.att-stat-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid transparent;
}

.att-stat--green  { background: #f0fdf4; border-color: #bbf7d0; }
.att-stat--red    { background: #fff1f2; border-color: #fecdd3; }
.att-stat--amber  { background: #fffbeb; border-color: #fde68a; }
.att-stat--blue   { background: #eff6ff; border-color: #bfdbfe; }
.att-stat--purple { background: #faf5ff; border-color: #e9d5ff; }
.att-stat--gray   { background: #f8fafc; border-color: #e2e8f0; }

.att-stat-count {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}

.att-stat--green  .att-stat-count  { background: #dcfce7; color: #16a34a; }
.att-stat--red    .att-stat-count  { background: #fee2e2; color: #dc2626; }
.att-stat--amber  .att-stat-count  { background: #fef3c7; color: #d97706; }
.att-stat--blue   .att-stat-count  { background: #dbeafe; color: #2563eb; }
.att-stat--purple .att-stat-count  { background: #ede9fe; color: #7c3aed; }
.att-stat--gray   .att-stat-count  { background: #e2e8f0; color: #475569; }

.att-stat-label-sm {
    font-size: 11px;
    color: #94a3b8;
    margin: 0;
}

.att-stat-pct {
    font-size: 14px;
    font-weight: 700;
    margin: 0;
}

.att-stat--green  .att-stat-pct { color: #16a34a; }
.att-stat--red    .att-stat-pct { color: #dc2626; }
.att-stat--amber  .att-stat-pct { color: #d97706; }
.att-stat--blue   .att-stat-pct { color: #2563eb; }
.att-stat--purple .att-stat-pct { color: #7c3aed; }
.att-stat--gray   .att-stat-pct { color: #475569; }

.att-monthly-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin: 0 0 12px;
}

.att-bars-scroll {
    overflow-x: auto;
    padding-bottom: 8px;
}

.att-bars-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    min-width: max-content;
    padding-bottom: 4px;
    min-height: 110px;
}

.att-bar-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    position: relative;
}

.att-bar-stack {
    position: relative;
    display: flex;
    flex-direction: column-reverse;
    justify-content: flex-start;
    width: 40px;
    height: 80px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--border, #e2e8f0);
}

.att-bar-segment {
    width: 100%;
    flex-shrink: 0;
}

.att-bar--green { background: #4ade80; }
.att-bar--amber { background: #fbbf24; }
.att-bar--blue  { background: #60a5fa; }
.att-bar--red   { background: #f87171; }

.att-bar-tooltip {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: #fff;
    font-size: 11px;
    border-radius: 6px;
    padding: 6px 10px;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.15s;
    white-space: nowrap;
    z-index: 10;
}

.att-bar-col:hover .att-bar-tooltip {
    opacity: 1;
}

.att-bar-month {
    font-size: 11px;
    color: #94a3b8;
    font-family: 'Courier New', monospace;
}

.att-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 12px;
}

.att-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #64748b;
}

.att-legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    display: inline-block;
}

.att-legend--green { background: #4ade80; }
.att-legend--red   { background: #f87171; }
.att-legend--amber { background: #fbbf24; }
.att-legend--blue  { background: #60a5fa; }

/* ── Exam List ────────────────────────────────────────────────────────────── */
.exam-list {
    display: flex;
    flex-direction: column;
    gap: 28px;
}

.exam-block {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.exam-block-header {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.exam-name-badge {
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

/* Percentage bar in exam table */
.pct-bar-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.pct-bar-track {
    width: 60px;
    height: 6px;
    background: #f1f5f9;
    border-radius: 99px;
    overflow: hidden;
    flex-shrink: 0;
}

.pct-bar-fill {
    height: 100%;
    border-radius: 99px;
}

.pct-bar--green { background: #22c55e; }
.pct-bar--blue  { background: #3b82f6; }
.pct-bar--red   { background: #ef4444; }

.pct-val {
    font-size: 12px;
    font-family: 'Courier New', monospace;
    color: #475569;
}

.pct-val--danger {
    color: var(--danger, #ef4444);
}

/* ── Document List ────────────────────────────────────────────────────────── */
.doc-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.doc-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: var(--bg, #f1f5f9);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: var(--radius, 10px);
    transition: background 0.12s;
}

.doc-row:hover {
    background: #f0f4ff;
}

.doc-row:hover .doc-actions {
    opacity: 1;
}

.doc-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.doc-icon--blue { background: #dbeafe; color: #2563eb; }
.doc-icon--gray { background: #e2e8f0; color: #94a3b8; }

.doc-info {
    flex: 1;
    min-width: 0;
}

.doc-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 4px;
}

.doc-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.doc-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.15s;
}

.doc-action-btn {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: background 0.12s;
    text-decoration: none;
}

.doc-action-btn--view { color: #2563eb; background: transparent; }
.doc-action-btn--view:hover { background: #dbeafe; }
.doc-action-btn--del  { color: #ef4444; background: transparent; }
.doc-action-btn--del:hover  { background: #fee2e2; }

.doc-file-input {
    width: 100%;
    font-size: 13px;
    color: #64748b;
}

.doc-file-input::file-selector-button {
    margin-right: 12px;
    padding: 6px 14px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    background: #e0e7ff;
    color: #4f46e5;
    cursor: pointer;
    transition: background 0.12s;
}

.doc-file-input::file-selector-button:hover {
    background: #c7d2fe;
}

/* ── Modals ───────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.5);
    padding: 16px;
}

.modal-card {
    background: var(--surface, #fff);
    border-radius: var(--radius-lg, 14px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.modal-card--sm {
    max-width: 380px;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border, #e2e8f0);
    flex-shrink: 0;
}

.modal-title {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.modal-close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #94a3b8;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
}

.modal-close:hover {
    background: #f1f5f9;
    color: #475569;
}

.modal-body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    flex: 1;
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-top: 1px solid var(--border, #e2e8f0);
    background: #f8fafc;
    border-radius: 0 0 var(--radius-lg, 14px) var(--radius-lg, 14px);
    flex-shrink: 0;
}

.modal-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.modal-field-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin: 0;
    display: block;
}

.modal-input {
    width: 100%;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
    color: #1e293b;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    box-sizing: border-box;
}

.modal-input:focus {
    border-color: var(--accent, #6366f1);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}

.modal-textarea {
    resize: vertical;
    min-height: 80px;
}

.modal-select {
    width: 100%;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
    color: #1e293b;
    outline: none;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
    box-sizing: border-box;
}

.modal-select:focus {
    border-color: var(--accent, #6366f1);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}

.modal-sub-field {
    margin-top: 4px;
}

.modal-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.modal-three-col {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

/* Toggle Switch */
.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
}

.toggle-label {
    font-size: 14px;
    font-weight: 500;
    color: #475569;
}

.toggle-wrap {
    position: relative;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.toggle-track {
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: #e2e8f0;
    transition: background 0.2s;
}

.toggle-track--on {
    background: var(--accent, #6366f1);
}

.toggle-thumb {
    position: absolute;
    top: 4px;
    left: 4px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.18);
    transition: transform 0.2s;
}

.toggle-thumb--on {
    transform: translateX(20px);
}

/* ── ID Card ──────────────────────────────────────────────────────────────── */
.id-card-body {
    padding: 0;
    gap: 0;
}

.id-card-banner {
    height: 52px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: var(--radius-lg, 14px) var(--radius-lg, 14px) 0 0;
}

.id-card-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 28px 24px;
    border: 1px solid #e0e7ff;
    border-top: none;
    border-radius: 0 0 var(--radius-lg, 14px) var(--radius-lg, 14px);
}

.id-card-photo {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 4px 14px rgba(0,0,0,0.14);
    margin-top: -40px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.id-card-photo-fallback {
    background: #e0e7ff;
    color: var(--accent, #6366f1);
    font-size: 26px;
    font-weight: 800;
}

.id-card-name {
    font-size: 18px;
    font-weight: 800;
    color: #1e293b;
    text-align: center;
    margin: 0 0 4px;
}

.id-card-adm {
    font-size: 13px;
    font-weight: 600;
    color: var(--accent, #6366f1);
    margin: 0 0 16px;
    font-family: 'Courier New', monospace;
}

.id-card-qr-wrap {
    padding: 8px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid var(--border, #e2e8f0);
    display: inline-block;
    margin-bottom: 8px;
}

.id-card-qr {
    width: 128px;
    height: 128px;
    display: block;
}

.id-card-qr-hint {
    font-size: 10px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    text-align: center;
    margin: 0 0 12px;
}

/* ── CSS variable shim for muted ──────────────────────────────────────────── */
:root {
    --muted: #94a3b8;
}

/* ── Transport Tab ─────────────────────────────────────────────────────────── */
.transport-banner {
    display: flex;
    align-items: center;
    gap: 14px;
    background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
}
.transport-banner-icon {
    width: 48px;
    height: 48px;
    background: #dbeafe;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    flex-shrink: 0;
}
.transport-route-name {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #1e293b;
}
.transport-route-meta {
    font-size: 0.775rem;
    color: #64748b;
    margin-top: 2px;
}
.transport-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
.transport-field {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
}
.transport-field-label {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin-bottom: 4px;
}
.transport-field-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
}

/* Route stops timeline */
.route-stops-timeline {
    display: flex;
    flex-direction: column;
    gap: 0;
    border-left: 2px solid #e2e8f0;
    padding-left: 16px;
    margin-left: 6px;
}
.route-stop {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 6px 0;
    position: relative;
}
.route-stop-dot {
    width: 10px;
    height: 10px;
    background: #cbd5e1;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 4px;
    margin-left: -21px;
    border: 2px solid #fff;
    outline: 2px solid #cbd5e1;
}
.route-stop--active .route-stop-dot {
    background: #3b82f6;
    outline-color: #3b82f6;
}
.route-stop-info {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.route-stop-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
}
.route-stop--active .route-stop-name {
    color: #2563eb;
}
.route-stop-meta {
    font-size: 0.75rem;
    color: #94a3b8;
}

@media (max-width: 768px) {
    .transport-grid { grid-template-columns: repeat(2, 1fr); }
}

.defaulter-toggle {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.22rem 0.6rem; border-radius: 999px;
    background: #f1f5f9; border: 1.5px solid #e2e8f0; color: #64748b;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
    cursor: pointer; transition: all 0.18s ease;
    font-family: inherit;
}
.defaulter-toggle:hover { background: #fff1f2; color: #b91c1c; border-color: #fecaca; }
.defaulter-toggle--on {
    background: #fef2f2; color: #b91c1c; border-color: #fca5a5;
}
.defaulter-toggle--on:hover { background: #fee2e2; }
.defaulter-toggle--busy { opacity: 0.55; cursor: wait; }
.defaulter-dot {
    width: 7px; height: 7px; border-radius: 50%; background: #cbd5e1;
    transition: background 0.18s;
}
.defaulter-toggle--on .defaulter-dot { background: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.18); }
</style>
