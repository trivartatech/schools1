<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import { ref, computed, watch, nextTick } from 'vue';
import QRCode from 'qrcode';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { usePermissions } from '@/Composables/usePermissions';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school   = useSchoolStore();
const confirm  = useConfirm();
const toast    = useToast();
const { canDo, canRequestEditStaff, canViewStaffSalary } = usePermissions();

const props = defineProps({
    staff:             { type: Object, required: true },
    leaveStats:        { type: Object, default: () => ({}) },
    recentLeaves:      { type: Array,  default: () => [] },
    payrolls:          { type: Array,  default: () => [] },
    careerHistory:     { type: Array,  default: () => [] },
    attendanceSummary: { type: Object, default: () => ({total:0,present:0,absent:0,late:0,half_day:0,leave:0,holiday:0}) },
    monthlyAttendance: { type: Object, default: () => ({}) },
    inchargeStats:     { type: Object, default: () => ({classes:0,sections:0,subjects:0}) },
});

// CSRF helper for fetch() password reset
const csrfHeader = () => {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m
        ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) }
        : { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' };
};

const formatMoney = (amount) => {
    if (amount === null || amount === undefined || amount === '') return '₹0.00';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
};

const formatDate = (dateString) => dateString ? school.fmtDate(dateString) : '—';

const getMonthName = (monthNum) => {
    if (!monthNum) return '';
    const date = new Date();
    date.setMonth(monthNum - 1);
    return new Intl.DateTimeFormat('en-US', { month: 'long' }).format(date);
};

// ── Photo fallback ────────────────────────────────────────────────────────────
const photoFailed = ref(false);
watch(() => props.staff?.id, () => { photoFailed.value = false; });

// ── Active Tab ────────────────────────────────────────────────────────────────
const activeTab = ref('basic');

const tabs = [
    { key: 'basic',       label: 'Basic',       icon: '👤' },
    { key: 'contact',     label: 'Contact',     icon: '📞' },
    { key: 'employment',  label: 'Employment',  icon: '💼' },
    { key: 'financial',   label: 'Financial',   icon: '🏦' },
    { key: 'attendance',  label: 'Attendance',  icon: '📊' },
    { key: 'leaves',      label: 'Leaves',      icon: '🌴' },
    { key: 'payroll',     label: 'Payroll',     icon: '💰' },
    { key: 'history',     label: 'History',     icon: '📜' },
    { key: 'incharge',    label: 'Incharge',    icon: '🎯' },
    { key: 'credentials', label: 'Credentials', icon: '🔐' },
];

// ── Status Map ────────────────────────────────────────────────────────────────
const STATUS_MAP = {
    active:     { label: 'Active',      cls: 'badge-green' },
    inactive:   { label: 'Inactive',    cls: 'badge-gray'  },
    on_leave:   { label: 'On Leave',    cls: 'badge-amber' },
    resigned:   { label: 'Resigned',    cls: 'badge-amber' },
    terminated: { label: 'Terminated',  cls: 'badge-red'   },
};

const staffStatus = computed(() => {
    const s = props.staff.status;
    return STATUS_MAP[s] ?? (props.staff.user?.is_active ? STATUS_MAP.active : STATUS_MAP.inactive);
});

// ── Leave Totals ──────────────────────────────────────────────────────────────
const totalLeaves = computed(() => {
    let total = 0;
    for (const key in props.leaveStats) {
        total += parseInt(props.leaveStats[key] || 0);
    }
    return total;
});

const leaveStatusBadge = (status) => {
    if (status === 'approved') return 'badge-green';
    if (status === 'rejected') return 'badge-red';
    return 'badge-amber';
};

// ── Attendance Helpers ────────────────────────────────────────────────────────
const attPct = computed(() => {
    const s = props.attendanceSummary;
    if (!s.total) return 0;
    return Math.round(((s.present + (s.late ?? 0) * 0.5 + (s.half_day ?? 0) * 0.5) / s.total) * 100);
});
const attColor = computed(() => {
    if (attPct.value >= 85) return { ring: '#22c55e', text: 'text-green-600', label: 'Excellent' };
    if (attPct.value >= 75) return { ring: '#f59e0b', text: 'text-amber-600', label: 'Satisfactory' };
    return { ring: '#ef4444', text: 'text-red-600', label: 'Low Attendance' };
});
const monthLabels   = computed(() => Object.keys(props.monthlyAttendance));
const maxMonthTotal = computed(() => Math.max(1, ...monthLabels.value.map(m => props.monthlyAttendance[m].total)));
const CIRC          = 2 * Math.PI * 44;
const ringDash      = computed(() => (attPct.value / 100) * CIRC);

// ── Career Event Labels ───────────────────────────────────────────────────────
const EVENT_LABELS = {
    joining:            { label: 'Joined',             cls: 'badge-green'  },
    promotion:          { label: 'Promotion',          cls: 'badge-indigo' },
    transfer:           { label: 'Transfer',           cls: 'badge-blue'   },
    demotion:           { label: 'Demotion',           cls: 'badge-amber'  },
    salary_revision:    { label: 'Salary Revision',    cls: 'badge-purple' },
    department_change:  { label: 'Department Change',  cls: 'badge-blue'   },
    designation_change: { label: 'Designation Change', cls: 'badge-indigo' },
    increment:          { label: 'Increment',          cls: 'badge-green'  },
    confirmation:       { label: 'Confirmation',       cls: 'badge-green'  },
    termination:        { label: 'Termination',        cls: 'badge-red'    },
    other:              { label: 'Other',              cls: 'badge-gray'   },
};
const eventBadge = (type) => EVENT_LABELS[type] ?? EVENT_LABELS.other;

// ── ID Card Modal ─────────────────────────────────────────────────────────────
const showIdModal = ref(false);
const qrCanvas    = ref(null);

const staffQrTarget = computed(() =>
    props.staff.user?.id ? `${window.location.origin}/q/staff/${props.staff.id}` : null
);

async function renderIdQr() {
    await nextTick();
    if (!qrCanvas.value || !staffQrTarget.value) return;
    await QRCode.toCanvas(qrCanvas.value, staffQrTarget.value, {
        width: 200, margin: 2, errorCorrectionLevel: 'M',
    });
}

function downloadIdQr() {
    if (!qrCanvas.value) return;
    const link = document.createElement('a');
    link.download = `${props.staff.employee_id || 'staff'}-qr.png`;
    link.href = qrCanvas.value.toDataURL('image/png');
    link.click();
}

watch(showIdModal, (open) => { if (open) renderIdQr(); });

// ── Credentials Tab ───────────────────────────────────────────────────────────
const staffUser = computed(() => props.staff.user || null);
const showPwd   = ref({ staff: false });
const togglePwd = (target) => { showPwd.value[target] = !showPwd.value[target]; };
const resettingFor = ref(null);

const handleCopyUsername = (username) => {
    if (!username) return;
    navigator.clipboard.writeText(username);
    toast.info('Username copied to clipboard.');
};

const handleResetPassword = async () => {
    const user = staffUser.value;
    if (!user) return;
    const ok = await confirm({
        title: 'Reset password?',
        message: `${user.name}'s password will be reset to the default ("password"). They must change it after their next login.`,
        confirmLabel: 'Reset Password',
        danger: true,
    });
    if (!ok) return;
    resettingFor.value = 'staff';
    try {
        const response = await fetch(`/school/users/${user.id}/reset-password`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...csrfHeader() },
        });
        const result = await response.json();
        if (result.success || response.ok) {
            toast.success(result.message || 'Password reset to "password".');
        } else {
            toast.error(result.message || 'Could not reset password.');
        }
    } catch (e) {
        console.error('Error resetting password:', e);
        toast.error('Could not reset password. Try again.');
    } finally {
        resettingFor.value = null;
    }
};

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteStaff = async () => {
    const ok = await confirm({
        title: 'Delete staff?',
        message: 'Are you sure you want to delete this staff member? This action cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/staff/${props.staff.id}`);
};
</script>

<template>
    <SchoolLayout :title="`${staff.user?.name || 'Staff'} — Profile`">
        <div class="staff-show-wrap">

            <!-- ══ HERO CARD ═════════════════════════════════════════════════ -->
            <PageHeader>
                <template #title>
                    <div class="hero-card">
                        <div class="hero-top">
                            <div class="hero-avatar-wrap">
                                <img v-if="staff.photo_url && !photoFailed"
                                     :src="staff.photo_url"
                                     alt=""
                                     class="hero-avatar"
                                     @error="photoFailed = true" />
                                <div v-else class="hero-avatar hero-avatar-fallback">
                                    {{ staff.user?.name?.charAt(0)?.toUpperCase() || 'S' }}
                                </div>
                                <span class="hero-status-dot" :class="staff.user?.is_active ? '' : 'hero-status-dot--off'"></span>
                            </div>

                            <div class="hero-info">
                                <h1 class="hero-name">{{ staff.user?.name }}</h1>

                                <div class="hero-subtitle">
                                    <svg class="hero-subtitle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span class="hero-subtitle-class">
                                        {{ staff.designation?.name || staff.user?.user_type?.replace('_', ' ') || 'Staff Member' }}
                                    </span>
                                    <span v-if="staff.department?.name" class="hero-subtitle-part">
                                        {{ staff.department.name }}
                                    </span>
                                    <span v-if="staff.joining_date" class="hero-subtitle-part">
                                        Joined {{ formatDate(staff.joining_date) }}
                                    </span>
                                </div>

                                <div class="hero-meta">
                                    <span class="badge" :class="staffStatus.cls">{{ staffStatus.label }}</span>
                                    <span v-for="role in staff.user?.roles" :key="role.id" class="badge badge-indigo" style="text-transform:capitalize;">
                                        {{ role.name.replace(/_/g, ' ') }}
                                    </span>
                                    <span class="hero-emp-id">EMP {{ staff.employee_id || '—' }}</span>
                                </div>
                            </div>

                            <div class="hero-actions">
                                <Button size="sm" as="link" v-if="canDo('edit', 'staff')" :href="`/school/staff/${staff.id}/edit`">
                                    ✏️ Edit
                                </Button>
                                <Button variant="secondary" size="sm" @click="showIdModal = true">
                                    🪪 ID Card
                                </Button>
                                <Button variant="secondary" size="sm" as="link" v-if="canViewStaffSalary" :href="`/school/staff/${staff.id}/salary`">
                                    💰 Salary
                                </Button>
                                <Button variant="secondary" size="sm" as="link" v-if="canRequestEditStaff" :href="`/school/staff/${staff.id}/request-edit`">
                                    📝 Request Update
                                </Button>
                                <Button variant="secondary" size="sm" as="link" href="/school/staff">
                                    ← Back
                                </Button>
                                <Button variant="danger" size="sm" v-if="canDo('delete', 'staff')" @click="deleteStaff">
                                    🗑️ Delete
                                </Button>
                            </div>
                        </div>

                        <div v-if="canRequestEditStaff" class="hero-notice">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Submit a request to edit any information, or use Edit Profile.
                        </div>
                    </div>
                </template>
            </PageHeader>

            <!-- ══ TAB BAR ═══════════════════════════════════════════════════ -->
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

            <!-- ══ TAB CONTENT ═══════════════════════════════════════════════ -->
            <div class="tab-content">

                <!-- ─── TAB: BASIC ───────────────────────────────────────── -->
                <div v-if="activeTab === 'basic'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Basic Information</span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-field">
                                    <p class="info-label">Employee ID</p>
                                    <span class="info-value info-mono">{{ staff.employee_id || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Full Name</p>
                                    <span class="info-value">{{ staff.user?.name || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">User Type</p>
                                    <span class="info-value" style="text-transform:capitalize;">{{ staff.user?.user_type?.replace('_', ' ') || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Status</p>
                                    <span class="badge" :class="staffStatus.cls">{{ staffStatus.label }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Department</p>
                                    <span class="info-value">{{ staff.department?.name || 'Unassigned' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Designation</p>
                                    <span class="info-value">{{ staff.designation?.name || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Joining Date</p>
                                    <span class="info-value">{{ formatDate(staff.joining_date) }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Account Active</p>
                                    <span class="badge" :class="staff.user?.is_active ? 'badge-green' : 'badge-red'">
                                        {{ staff.user?.is_active ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Roles sub-card -->
                    <div class="card" style="margin-top: 16px;">
                        <div class="card-header">
                            <span class="card-title">Assigned Roles</span>
                        </div>
                        <div class="card-body">
                            <div v-if="staff.user?.roles?.length" style="display:flex;flex-wrap:wrap;gap:8px;">
                                <span v-for="role in staff.user.roles" :key="role.id"
                                      class="badge badge-indigo" style="text-transform:capitalize;font-size:12px;padding:4px 10px;">
                                    {{ role.name.replace(/_/g, ' ') }}
                                </span>
                            </div>
                            <EmptyState v-else tone="muted" title="No roles assigned.">
                                <template #icon>
                                    <span style="font-size:1.5rem;line-height:1;">🎭</span>
                                </template>
                            </EmptyState>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: CONTACT ─────────────────────────────────────── -->
                <div v-if="activeTab === 'contact'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Contact Information</span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid info-grid--2">
                                <div class="info-field">
                                    <p class="info-label">Email</p>
                                    <span class="info-value">{{ staff.user?.email || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Phone</p>
                                    <span class="info-value info-mono">{{ staff.user?.phone || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Username</p>
                                    <span class="info-value info-mono">{{ staff.user?.username || '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: EMPLOYMENT ──────────────────────────────────── -->
                <div v-if="activeTab === 'employment'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Employment Details</span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid info-grid--2">
                                <div class="info-field">
                                    <p class="info-label">Department</p>
                                    <span class="info-value">{{ staff.department?.name || 'Unassigned' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Designation</p>
                                    <span class="info-value">{{ staff.designation?.name || '—' }}</span>
                                </div>
                                <div v-if="staff.designation?.parent" class="info-field">
                                    <p class="info-label">Reporting To (Designation)</p>
                                    <span class="info-value">{{ staff.designation.parent.name }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Joining Date</p>
                                    <span class="info-value">{{ formatDate(staff.joining_date) }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Qualification</p>
                                    <span class="info-value">{{ staff.qualification || '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Experience</p>
                                    <span class="info-value">{{ staff.experience_years ? staff.experience_years + ' Years' : '—' }}</span>
                                </div>
                                <div class="info-field">
                                    <p class="info-label">Employment Status</p>
                                    <span class="badge" :class="staffStatus.cls">{{ staffStatus.label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="staff.signature_url" class="card" style="margin-top: 16px;">
                        <div class="card-header">
                            <span class="card-title">Signature</span>
                        </div>
                        <div class="card-body">
                            <img :src="staff.signature_url" alt="Signature"
                                 style="max-height:80px;max-width:300px;object-fit:contain;background:#f8fafc;border:1px solid var(--border, #e2e8f0);border-radius:8px;padding:8px;" />
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: FINANCIAL ───────────────────────────────────── -->
                <div v-if="activeTab === 'financial'">
                    <div v-if="!canViewStaffSalary" class="card">
                        <div class="card-body">
                            <EmptyState tone="muted" title="You don't have permission to view financial details.">
                                <template #icon>
                                    <span style="font-size:1.5rem;line-height:1;">🔒</span>
                                </template>
                            </EmptyState>
                        </div>
                    </div>

                    <template v-else>
                        <div class="card">
                            <div class="card-header">
                                <span class="card-title">Salary &amp; Compensation</span>
                                <Link :href="`/school/staff/${staff.id}/salary`" class="card-header-link">Manage Salary →</Link>
                            </div>
                            <div class="card-body">
                                <div class="salary-highlight">
                                    <div class="salary-highlight-label">Basic Salary (per month)</div>
                                    <div class="salary-highlight-value">{{ formatMoney(staff.basic_salary) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card" style="margin-top: 16px;">
                            <div class="card-header">
                                <span class="card-title">Bank &amp; Tax Details</span>
                            </div>
                            <div class="card-body">
                                <div class="info-grid info-grid--2">
                                    <div class="info-field">
                                        <p class="info-label">Bank Name</p>
                                        <span class="info-value">{{ staff.bank_name || '—' }}</span>
                                    </div>
                                    <div class="info-field">
                                        <p class="info-label">Account Number</p>
                                        <span class="info-value info-mono">{{ staff.bank_account_no || '—' }}</span>
                                    </div>
                                    <div class="info-field">
                                        <p class="info-label">IFSC Code</p>
                                        <span class="info-value info-mono" style="text-transform:uppercase;">{{ staff.ifsc_code || '—' }}</span>
                                    </div>
                                    <div class="info-field">
                                        <p class="info-label">PAN Number</p>
                                        <span class="info-value info-mono" style="text-transform:uppercase;">{{ staff.pan_no || '—' }}</span>
                                    </div>
                                    <div class="info-field">
                                        <p class="info-label">EPF Number</p>
                                        <span class="info-value info-mono">{{ staff.epf_no || '—' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ─── TAB: ATTENDANCE ──────────────────────────────────── -->
                <div v-if="activeTab === 'attendance'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Attendance Report</span>
                            <Link href="/school/staff-attendance" class="card-header-link">Attendance Register →</Link>
                        </div>
                        <div class="card-body">
                            <EmptyState
                                v-if="!attendanceSummary.total"
                                tone="muted"
                                title="No attendance records found for this academic year."
                            >
                                <template #icon>
                                    <span style="font-size:1.5rem;line-height:1;">📭</span>
                                </template>
                            </EmptyState>

                            <template v-else>
                                <div class="att-summary">
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

                                <div v-if="monthLabels.length" class="att-monthly">
                                    <p class="att-monthly-title">Monthly Breakdown</p>
                                    <div class="att-bars-scroll">
                                        <div class="att-bars-row">
                                            <div v-for="month in monthLabels" :key="month" class="att-bar-col">
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

                <!-- ─── TAB: LEAVES ──────────────────────────────────────── -->
                <div v-if="activeTab === 'leaves'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Leave Summary</span>
                        </div>
                        <div class="card-body">
                            <div class="leave-stats-grid">
                                <div class="leave-stat leave-stat--amber">
                                    <div class="leave-stat-val">{{ leaveStats?.pending || 0 }}</div>
                                    <div class="leave-stat-lbl">Pending</div>
                                </div>
                                <div class="leave-stat leave-stat--green">
                                    <div class="leave-stat-val">{{ leaveStats?.approved || 0 }}</div>
                                    <div class="leave-stat-lbl">Approved</div>
                                </div>
                                <div class="leave-stat leave-stat--red">
                                    <div class="leave-stat-val">{{ leaveStats?.rejected || 0 }}</div>
                                    <div class="leave-stat-lbl">Rejected</div>
                                </div>
                                <div class="leave-stat leave-stat--gray">
                                    <div class="leave-stat-val">{{ totalLeaves }}</div>
                                    <div class="leave-stat-lbl">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 16px;">
                        <div class="card-header">
                            <span class="card-title">Recent Leave Requests</span>
                        </div>
                        <div v-if="!recentLeaves.length" class="card-body">
                            <EmptyState tone="muted" title="No leave requests recorded.">
                                <template #icon>
                                    <span style="font-size:1.5rem;line-height:1;">📭</span>
                                </template>
                            </EmptyState>
                        </div>
                        <div v-else class="table-wrap">
                            <Table>
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Reason</th>
                                        <th style="text-align:center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="lv in recentLeaves" :key="lv.id">
                                        <td>
                                            <span class="badge badge-indigo">
                                                {{ lv.type_name || '—' }}
                                            </span>
                                        </td>
                                        <td>{{ formatDate(lv.start_date) }}</td>
                                        <td>{{ formatDate(lv.end_date) }}</td>
                                        <td class="truncate-cell">{{ lv.reason || '—' }}</td>
                                        <td style="text-align:center">
                                            <span class="badge" :class="leaveStatusBadge(lv.status)" style="text-transform:capitalize;">
                                                {{ lv.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </Table>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: PAYROLL ─────────────────────────────────────── -->
                <div v-if="activeTab === 'payroll'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Payroll History (Last 6 Months)</span>
                            <Link v-if="canViewStaffSalary" :href="`/school/staff/${staff.id}/salary`" class="card-header-link">Manage →</Link>
                        </div>
                        <div v-if="!payrolls?.length" class="card-body">
                            <EmptyState tone="muted" title="No payroll records found.">
                                <template #icon>
                                    <span style="font-size:1.5rem;line-height:1;">💼</span>
                                </template>
                                <p style="font-size:0.8125rem;color:#94a3b8;margin-top:6px;">Salary slips will appear here once generated by HR.</p>
                            </EmptyState>
                        </div>
                        <div v-else class="table-wrap">
                            <Table>
                                <thead>
                                    <tr>
                                        <th>Month / Payslip</th>
                                        <th style="text-align:right;">Gross</th>
                                        <th style="text-align:right;">Deductions</th>
                                        <th style="text-align:right;">Net Pay</th>
                                        <th style="text-align:center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="pay in payrolls" :key="pay.id">
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div class="payslip-month-badge">
                                                    <span>{{ getMonthName(pay.month).substring(0, 3) }}</span>
                                                    <span style="font-size:0.6rem;opacity:0.7;">{{ pay.year }}</span>
                                                </div>
                                                <div>
                                                    <p style="font-weight:600;font-size:0.8125rem;color:#0f172a;margin:0;">Payslip #{{ pay.id }}</p>
                                                    <p style="font-size:0.7rem;color:#94a3b8;margin:0;">{{ pay.payment_date ? formatDate(pay.payment_date) : 'Pending' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:right;font-weight:600;">{{ formatMoney(parseFloat(pay.basic_pay || 0) + parseFloat(pay.allowances_total || 0)) }}</td>
                                        <td style="text-align:right;color:#ef4444;font-weight:600;">−{{ formatMoney(pay.deductions_total || 0) }}</td>
                                        <td style="text-align:right;color:#059669;font-weight:700;font-size:0.9375rem;">{{ formatMoney(pay.net_salary) }}</td>
                                        <td style="text-align:center;">
                                            <span class="badge" :class="pay.status === 'paid' ? 'badge-green' : 'badge-amber'" style="text-transform:capitalize;">{{ pay.status }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </Table>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: HISTORY ─────────────────────────────────────── -->
                <div v-if="activeTab === 'history'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Career History</span>
                            <Link :href="`/school/staff/${staff.id}/history`" class="card-header-link">Manage Events →</Link>
                        </div>
                        <div v-if="!careerHistory.length" class="card-body">
                            <EmptyState tone="muted" title="No career events recorded yet.">
                                <template #icon>
                                    <span style="font-size:1.5rem;line-height:1;">📜</span>
                                </template>
                                <p style="font-size:0.8125rem;color:#94a3b8;margin-top:6px;">Joining, promotions, transfers, and salary revisions will appear here.</p>
                            </EmptyState>
                        </div>
                        <div v-else class="card-body">
                            <ul class="timeline">
                                <li v-for="ev in careerHistory" :key="ev.id" class="timeline-item">
                                    <div class="timeline-dot" :class="`timeline-dot--${eventBadge(ev.event_type).cls.replace('badge-','')}`"></div>
                                    <div class="timeline-card">
                                        <div class="timeline-head">
                                            <span class="badge" :class="eventBadge(ev.event_type).cls">
                                                {{ eventBadge(ev.event_type).label }}
                                            </span>
                                            <span class="timeline-date">{{ formatDate(ev.effective_date) }}</span>
                                        </div>
                                        <div class="timeline-body">
                                            <div v-if="ev.from_designation || ev.to_designation" class="timeline-row">
                                                <span class="timeline-row-label">Designation:</span>
                                                <span class="timeline-row-value">
                                                    <span v-if="ev.from_designation">{{ ev.from_designation.name }}</span>
                                                    <span v-else class="cell-muted">(none)</span>
                                                    <span class="timeline-arrow">→</span>
                                                    <span v-if="ev.to_designation"><strong>{{ ev.to_designation.name }}</strong></span>
                                                    <span v-else class="cell-muted">(none)</span>
                                                </span>
                                            </div>
                                            <div v-if="ev.from_department || ev.to_department" class="timeline-row">
                                                <span class="timeline-row-label">Department:</span>
                                                <span class="timeline-row-value">
                                                    <span v-if="ev.from_department">{{ ev.from_department.name }}</span>
                                                    <span v-else class="cell-muted">(none)</span>
                                                    <span class="timeline-arrow">→</span>
                                                    <span v-if="ev.to_department"><strong>{{ ev.to_department.name }}</strong></span>
                                                    <span v-else class="cell-muted">(none)</span>
                                                </span>
                                            </div>
                                            <div v-if="ev.from_salary !== null || ev.to_salary !== null" class="timeline-row">
                                                <span class="timeline-row-label">Salary:</span>
                                                <span class="timeline-row-value">
                                                    <span>{{ formatMoney(ev.from_salary) }}</span>
                                                    <span class="timeline-arrow">→</span>
                                                    <strong>{{ formatMoney(ev.to_salary) }}</strong>
                                                </span>
                                            </div>
                                            <div v-if="ev.order_no" class="timeline-row">
                                                <span class="timeline-row-label">Order #:</span>
                                                <span class="timeline-row-value info-mono">{{ ev.order_no }}</span>
                                            </div>
                                            <div v-if="ev.remarks" class="timeline-remarks">{{ ev.remarks }}</div>
                                        </div>
                                        <div v-if="ev.recorded_by" class="timeline-footer">
                                            Recorded by {{ ev.recorded_by.name }}
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: INCHARGE ────────────────────────────────────── -->
                <div v-if="activeTab === 'incharge'">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Incharge Responsibilities</span>
                        </div>
                        <div class="card-body">
                            <div class="incharge-grid">
                                <div class="incharge-stat incharge-stat--blue">
                                    <div class="incharge-stat-icon">🏫</div>
                                    <div class="incharge-stat-val">{{ inchargeStats.classes || 0 }}</div>
                                    <div class="incharge-stat-lbl">Classes as Incharge</div>
                                </div>
                                <div class="incharge-stat incharge-stat--purple">
                                    <div class="incharge-stat-icon">📚</div>
                                    <div class="incharge-stat-val">{{ inchargeStats.sections || 0 }}</div>
                                    <div class="incharge-stat-lbl">Sections as Incharge</div>
                                </div>
                                <div class="incharge-stat incharge-stat--green">
                                    <div class="incharge-stat-icon">📖</div>
                                    <div class="incharge-stat-val">{{ inchargeStats.subjects || 0 }}</div>
                                    <div class="incharge-stat-lbl">Subjects as Incharge</div>
                                </div>
                            </div>

                            <p v-if="!inchargeStats.classes && !inchargeStats.sections && !inchargeStats.subjects"
                               class="incharge-hint">
                                This staff member is not currently assigned as incharge for any class, section, or subject.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ─── TAB: CREDENTIALS ─────────────────────────────────── -->
                <div v-if="activeTab === 'credentials'">
                    <div class="cred-grid">
                        <div class="cred-card">
                            <div class="cred-card-header">
                                <div class="cred-card-icon cred-card-icon--staff">👨‍🏫</div>
                                <div>
                                    <div class="cred-card-title">Staff Login</div>
                                    <div class="cred-card-subtitle">{{ staff.user?.name }}</div>
                                </div>
                                <span v-if="staffUser"
                                      class="badge"
                                      :class="staffUser.is_active ? 'badge-green' : 'badge-red'"
                                      style="margin-left:auto;">
                                    {{ staffUser.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </div>

                            <div v-if="!staffUser" class="cred-empty">
                                <div class="cred-empty-icon">🚫</div>
                                <p class="cred-empty-title">No login account linked.</p>
                                <p class="cred-empty-hint">Create one from User Management → Create missing logins.</p>
                            </div>

                            <div v-else class="cred-card-body">
                                <div class="cred-row">
                                    <span class="cred-row-label">Email</span>
                                    <div class="cred-row-value">
                                        <span class="cred-row-text info-mono">{{ staffUser.email || '—' }}</span>
                                    </div>
                                </div>
                                <div class="cred-row">
                                    <span class="cred-row-label">Username</span>
                                    <div class="cred-row-value">
                                        <span class="cred-row-text info-mono">{{ staffUser.username || '—' }}</span>
                                        <button v-if="staffUser.username"
                                                type="button"
                                                class="cred-icon-btn"
                                                title="Copy username"
                                                @click="handleCopyUsername(staffUser.username)">📋</button>
                                    </div>
                                </div>
                                <div class="cred-row">
                                    <span class="cred-row-label">Password</span>
                                    <div class="cred-row-value">
                                        <span class="cred-row-text info-mono">
                                            {{ showPwd.staff ? 'password' : '••••••••' }}
                                        </span>
                                        <button type="button"
                                                class="cred-icon-btn"
                                                :title="showPwd.staff ? 'Hide hint' : 'Show default hint'"
                                                @click="togglePwd('staff')">
                                            {{ showPwd.staff ? '🙈' : '👁️' }}
                                        </button>
                                    </div>
                                </div>
                                <p class="cred-hint">
                                    Reset returns the password to the default <code>password</code>.
                                    The actual current password is hashed and not visible — what's shown is only what users would have after a reset.
                                </p>
                                <div class="cred-actions">
                                    <Button variant="danger"
                                            size="sm"
                                            :loading="resettingFor === 'staff'"
                                            @click="handleResetPassword">
                                        🔄 Reset to default
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-content -->
        </div><!-- /staff-show-wrap -->

        <!-- ══ ID CARD MODAL ═══════════════════════════════════════════════════ -->
        <Modal v-model:open="showIdModal" title="Virtual ID Card" size="sm">
            <div class="id-card-body">
                <div class="id-card-banner"></div>
                <div class="id-card-content">
                    <img v-if="staff.photo_url && !photoFailed" :src="staff.photo_url" class="id-card-photo" alt="" @error="photoFailed = true" />
                    <div v-else class="id-card-photo id-card-photo-fallback">
                        {{ staff.user?.name?.charAt(0)?.toUpperCase() || 'S' }}
                    </div>
                    <h4 class="id-card-name">{{ staff.user?.name }}</h4>
                    <p class="id-card-adm">EMP {{ staff.employee_id || '—' }}</p>
                    <p style="font-size:12px;color:#6366f1;font-weight:600;margin:0 0 8px;text-transform:capitalize;">
                        {{ staff.designation?.name || staff.user?.user_type?.replace('_', ' ') || 'Staff' }}
                    </p>
                    <div class="id-card-qr-wrap">
                        <canvas v-if="staffQrTarget" ref="qrCanvas" class="id-card-qr" />
                        <p v-else class="id-card-qr-hint" style="color:#ef4444">No QR (missing user)</p>
                    </div>
                    <p class="id-card-qr-hint">Scan for Profile / Attendance</p>
                    <Button variant="secondary" block class="mt-2" @click="downloadIdQr" :disabled="!staffQrTarget">
                        Download QR
                    </Button>
                </div>
            </div>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* ── Page wrapper ─────────────────────────────────────────────────────────── */
.staff-show-wrap {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ── Hero Card ────────────────────────────────────────────────────────────── */
.hero-card {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
    border-radius: var(--radius-lg, 14px);
    padding: 18px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    box-shadow: 0 4px 24px rgba(99,102,241,0.18);
}

.hero-top {
    display: flex;
    align-items: flex-start;
    gap: 22px;
    flex-wrap: wrap;
}

.hero-avatar-wrap {
    position: relative;
    flex-shrink: 0;
    align-self: center;
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
.hero-status-dot--off { background: #94a3b8; }

.hero-info {
    flex: 1;
    min-width: 0;
}

.hero-name {
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    margin: 0 0 6px;
    line-height: 1.2;
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.hero-subtitle {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 14px;
    margin: 0 0 8px;
    color: rgba(255,255,255,0.92);
    font-size: 14px;
    font-weight: 600;
}

.hero-subtitle-icon {
    width: 16px;
    height: 16px;
    color: rgba(255,255,255,0.85);
    flex-shrink: 0;
}

.hero-subtitle-class {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    text-transform: capitalize;
}

.hero-subtitle-part {
    color: rgba(255,255,255,0.85);
    font-weight: 500;
    position: relative;
    padding-left: 14px;
}

.hero-subtitle-part::before {
    content: '·';
    position: absolute;
    left: 0;
    color: rgba(255,255,255,0.5);
    font-weight: 700;
}

.hero-emp-id {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: rgba(255,255,255,0.85);
    background: rgba(0,0,0,0.18);
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 700;
    letter-spacing: 0.03em;
}

.hero-notice {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: rgba(255,255,255,0.78);
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 7px 12px;
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

/* ── Card Header Link ─────────────────────────────────────────────────────── */
.card-header-link {
    font-size: 0.8125rem;
    color: #6366f1;
    font-weight: 600;
    text-decoration: none;
}
.card-header-link:hover { color: #4338ca; }

/* ── Info Grid ────────────────────────────────────────────────────────────── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
}

.info-grid--2 {
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}

.info-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
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

/* ── Salary Highlight ─────────────────────────────────────────────────────── */
.salary-highlight {
    padding: 20px 24px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #a7f3d0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-start;
}
.salary-highlight-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #065f46;
}
.salary-highlight-value {
    font-size: 28px;
    font-weight: 800;
    color: #047857;
    line-height: 1;
}

/* ── Leave Stats ──────────────────────────────────────────────────────────── */
.leave-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}
.leave-stat {
    border-radius: 10px;
    padding: 16px 12px;
    text-align: center;
}
.leave-stat-val { font-size: 1.75rem; font-weight: 800; line-height: 1; }
.leave-stat-lbl { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: 6px; }
.leave-stat--amber { background: #fffbeb; }
.leave-stat--amber .leave-stat-val { color: #d97706; }
.leave-stat--amber .leave-stat-lbl { color: #92400e; }
.leave-stat--green { background: #f0fdf4; }
.leave-stat--green .leave-stat-val { color: #059669; }
.leave-stat--green .leave-stat-lbl { color: #065f46; }
.leave-stat--red { background: #fef2f2; }
.leave-stat--red .leave-stat-val { color: #dc2626; }
.leave-stat--red .leave-stat-lbl { color: #7f1d1d; }
.leave-stat--gray { background: #f8fafc; }
.leave-stat--gray .leave-stat-val { color: #475569; }
.leave-stat--gray .leave-stat-lbl { color: #334155; }

/* ── Table Wrap ───────────────────────────────────────────────────────────── */
.table-wrap {
    overflow-x: auto;
    border-radius: 0 0 var(--radius, 10px) var(--radius, 10px);
}
.cell-muted { color: #94a3b8; }
.truncate-cell { max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Attendance ───────────────────────────────────────────────────────────── */
.att-summary {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 24px;
    margin-bottom: 24px;
}
.att-ring-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.att-ring-label { font-size: 12px; font-weight: 700; }
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
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; flex-shrink: 0;
}
.att-stat--green  .att-stat-count { background: #dcfce7; color: #16a34a; }
.att-stat--red    .att-stat-count { background: #fee2e2; color: #dc2626; }
.att-stat--amber  .att-stat-count { background: #fef3c7; color: #d97706; }
.att-stat--blue   .att-stat-count { background: #dbeafe; color: #2563eb; }
.att-stat--purple .att-stat-count { background: #ede9fe; color: #7c3aed; }
.att-stat--gray   .att-stat-count { background: #e2e8f0; color: #475569; }
.att-stat-label-sm { font-size: 11px; color: #94a3b8; margin: 0; }
.att-stat-pct { font-size: 14px; font-weight: 700; margin: 0; }
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
.att-bars-scroll { overflow-x: auto; padding-bottom: 8px; }
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
.att-bar-segment { width: 100%; flex-shrink: 0; }
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
.att-bar-col:hover .att-bar-tooltip { opacity: 1; }
.att-bar-month { font-size: 11px; color: #94a3b8; font-family: 'Courier New', monospace; }
.att-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
.att-legend-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #64748b; }
.att-legend-dot { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
.att-legend--green { background: #4ade80; }
.att-legend--red   { background: #f87171; }
.att-legend--amber { background: #fbbf24; }
.att-legend--blue  { background: #60a5fa; }

/* ── Payroll ──────────────────────────────────────────────────────────────── */
.payslip-month-badge {
    width: 42px; height: 42px; border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #fff; font-size: 0.6875rem; font-weight: 700; line-height: 1.2;
    flex-shrink: 0;
}

/* ── Career History Timeline ──────────────────────────────────────────────── */
.timeline {
    list-style: none;
    padding: 0;
    margin: 0;
    position: relative;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 14px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: #e2e8f0;
}
.timeline-item {
    position: relative;
    padding-left: 40px;
    padding-bottom: 18px;
}
.timeline-item:last-child { padding-bottom: 0; }
.timeline-dot {
    position: absolute;
    left: 6px;
    top: 8px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #6366f1;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #e2e8f0;
}
.timeline-dot--green  { background: #22c55e; }
.timeline-dot--red    { background: #ef4444; }
.timeline-dot--amber  { background: #f59e0b; }
.timeline-dot--blue   { background: #3b82f6; }
.timeline-dot--indigo { background: #6366f1; }
.timeline-dot--purple { background: #8b5cf6; }
.timeline-dot--gray   { background: #94a3b8; }

.timeline-card {
    background: #f8fafc;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 10px;
    padding: 12px 14px;
    transition: background 0.15s;
}
.timeline-card:hover { background: #f1f5f9; }
.timeline-head {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.timeline-date {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
}
.timeline-body {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
}
.timeline-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: baseline; }
.timeline-row-label { color: #64748b; font-weight: 600; min-width: 90px; }
.timeline-row-value { display: flex; gap: 6px; align-items: baseline; flex-wrap: wrap; color: #1e293b; }
.timeline-arrow { color: #cbd5e1; font-weight: 700; }
.timeline-remarks {
    margin-top: 4px;
    padding: 6px 10px;
    font-size: 12px;
    color: #475569;
    background: #fff;
    border-left: 3px solid #c7d2fe;
    border-radius: 4px;
}
.timeline-footer {
    margin-top: 8px;
    font-size: 11px;
    color: #94a3b8;
    font-style: italic;
}

/* ── Incharge Stats ───────────────────────────────────────────────────────── */
.incharge-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}
.incharge-stat {
    border-radius: 12px;
    padding: 18px 14px;
    text-align: center;
    border: 1px solid transparent;
}
.incharge-stat-icon { font-size: 24px; margin-bottom: 4px; }
.incharge-stat-val { font-size: 2rem; font-weight: 800; line-height: 1; margin: 4px 0; }
.incharge-stat-lbl { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.incharge-stat--blue   { background: #eff6ff; border-color: #bfdbfe; }
.incharge-stat--blue   .incharge-stat-val { color: #2563eb; }
.incharge-stat--blue   .incharge-stat-lbl { color: #1e40af; }
.incharge-stat--purple { background: #faf5ff; border-color: #e9d5ff; }
.incharge-stat--purple .incharge-stat-val { color: #7c3aed; }
.incharge-stat--purple .incharge-stat-lbl { color: #5b21b6; }
.incharge-stat--green  { background: #f0fdf4; border-color: #bbf7d0; }
.incharge-stat--green  .incharge-stat-val { color: #059669; }
.incharge-stat--green  .incharge-stat-lbl { color: #065f46; }
.incharge-hint {
    margin: 16px 0 0;
    font-size: 13px;
    color: #94a3b8;
    text-align: center;
    font-style: italic;
}

/* ── Credentials ──────────────────────────────────────────────────────────── */
.cred-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 16px;
}
.cred-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: var(--radius-lg, 14px);
    overflow: hidden;
}
.cred-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border, #e2e8f0);
    background: #f8fafc;
}
.cred-card-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.cred-card-icon--staff { background: #ede9fe; }
.cred-card-title { font-size: 14px; font-weight: 700; color: #1e293b; }
.cred-card-subtitle { font-size: 12px; color: #64748b; margin-top: 2px; }

.cred-empty { padding: 28px 16px; text-align: center; }
.cred-empty-icon { font-size: 28px; margin-bottom: 6px; }
.cred-empty-title { font-size: 13px; font-weight: 600; color: #1e293b; margin: 0; }
.cred-empty-hint { font-size: 12px; color: #94a3b8; margin: 4px 0 0; }

.cred-card-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
.cred-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.cred-row-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
.cred-row-value { display: flex; align-items: center; gap: 6px; }
.cred-row-text { font-size: 13px; color: #1e293b; }
.cred-icon-btn {
    background: none;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 6px;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.12s;
}
.cred-icon-btn:hover { background: #f1f5f9; }
.cred-hint {
    font-size: 11px;
    color: #94a3b8;
    margin: 0;
    line-height: 1.5;
    padding: 8px 10px;
    background: #f8fafc;
    border-radius: 6px;
}
.cred-hint code {
    background: #e2e8f0;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 11px;
}
.cred-actions { display: flex; justify-content: flex-end; }

/* ── ID Card Modal ────────────────────────────────────────────────────────── */
.id-card-body { padding: 0; gap: 0; }
.id-card-banner {
    height: 52px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
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
    width: 80px; height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 4px 14px rgba(0,0,0,0.14);
    margin-top: -40px;
    margin-bottom: 12px;
    display: flex; align-items: center; justify-content: center;
}
.id-card-photo-fallback {
    background: #ede9fe;
    color: #6366f1;
    font-size: 26px;
    font-weight: 800;
}
.id-card-name { font-size: 18px; font-weight: 800; color: #1e293b; text-align: center; margin: 0 0 4px; }
.id-card-adm {
    font-size: 13px;
    font-family: 'Courier New', monospace;
    background: #f1f5f9;
    color: #475569;
    padding: 2px 10px;
    border-radius: 4px;
    margin: 0 0 8px;
}
.id-card-qr-wrap {
    margin: 8px 0 4px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid var(--border, #e2e8f0);
}
.id-card-qr { display: block; }
.id-card-qr-hint { font-size: 11px; color: #64748b; text-align: center; margin: 4px 0 0; }
</style>
