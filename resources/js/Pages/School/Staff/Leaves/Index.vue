<script setup>
import Button from '@/Components/ui/Button.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useDelete } from '@/Composables/useDelete';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';
import FilterBar from '@/Components/ui/FilterBar.vue';

const school = useSchoolStore();
const confirm = useConfirm();

const props = defineProps({
    leaves: Object,   // paginated
    leaveTypes: { type: Array, default: () => [] },
    filters: Object,
});

// ── Apply for Leave Form ─────────────────────────────────────────────────────
const showApplyForm = ref(false);
const form = useForm({
    leave_type_id: '',
    start_date: '',
    end_date: '',
    reason: ''
});

const submit = () => {
    form.post('/school/leaves', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showApplyForm.value = false;
        }
    });
};

// ── Filters ──────────────────────────────────────────────────────────────────
const statusFilter = ref(props.filters?.status || '');
const typeFilter   = ref(props.filters?.leave_type_id || '');

const applyFilters = () => {
    router.get('/school/leaves', {
        status: statusFilter.value,
        leave_type_id: typeFilter.value
    }, { preserveState: true, replace: true });
};

// ── Approve / Reject ─────────────────────────────────────────────────────────
const approvingId = ref(null);

const approveLeave = (id) => {
    approvingId.value = id;
    router.patch(`/school/leaves/${id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => { approvingId.value = null; }
    });
};

const rejectLeave = (id) => {
    router.patch(`/school/leaves/${id}/reject`, {}, { preserveScroll: true });
};

const revertLeave = async (id) => {
    const ok = await confirm({
        title: 'Revert leave?',
        message: 'Revert this leave back to pending?',
        confirmLabel: 'Revert',
    });
    if (!ok) return;
    router.patch(`/school/leaves/${id}/revert`, {}, { preserveScroll: true });
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const statusColors = {
    pending: 'badge-amber',
    approved: 'badge-green',
    rejected: 'badge-red'
};

// Fallback for legacy leave_type strings (before LeaveType model linkage)
const leaveTypeLabels = {
    casual: 'Casual Leave',
    sick: 'Sick Leave',
    earned: 'Earned Leave',
    maternity: 'Maternity Leave',
    paternity: 'Paternity Leave',
    unpaid: 'Unpaid Leave',
    other: 'Other'
};

const getLeaveLabel = (leave) => {
    if (leave.leave_type_id && leave.leave_type) {
        return leave.leave_type.name;
    }
    return leaveTypeLabels[leave.leave_type] || leave.leave_type || '—';
};

const getLeaveColor = (leave) => {
    if (leave.leave_type_id && leave.leave_type?.color) {
        return leave.leave_type.color;
    }
    return '#94a3b8';
};

const daysBetween = (s, e) => {
    const ms = new Date(e) - new Date(s);
    return Math.round(ms / (1000 * 60 * 60 * 24)) + 1;
};

// ── Summary Cards ─────────────────────────────────────────────────────────────
const pending  = computed(() => (props.leaves?.data || []).filter(l => l.status === 'pending').length);
const approved = computed(() => (props.leaves?.data || []).filter(l => l.status === 'approved').length);
const rejected = computed(() => (props.leaves?.data || []).filter(l => l.status === 'rejected').length);
</script>

<template>
    <SchoolLayout title="Leave Management">

        <!-- Header -->
        <PageHeader title="Staff Leave Requests" subtitle="Manage and review staff leave applications.">
            <template #actions>
                <Button @click="showApplyForm = !showApplyForm">
                                {{ showApplyForm ? 'Cancel' : 'Apply for Leave' }}
                            </Button>
            </template>
        </PageHeader>

        <!-- Apply Form (collapsible) -->
        <transition name="slide">
            <div v-if="showApplyForm" class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="card-title">New Leave Application</span>
                </div>
                <div class="card-body">
                    <form @submit.prevent="submit">
                        <div class="form-row" style="grid-template-columns:repeat(4,1fr);">
                            <div class="form-field">
                                <label>Leave Type *</label>
                                <select v-model="form.leave_type_id" required>
                                    <option value="">Select Type</option>
                                    <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }} ({{ lt.days_allowed }}d{{ lt.is_paid ? ', Paid' : '' }})</option>
                                </select>
                                <div v-if="form.errors.leave_type_id" class="form-error">{{ form.errors.leave_type_id }}</div>
                            </div>
                            <div class="form-field">
                                <label>From Date *</label>
                                <input v-model="form.start_date" type="date" required />
                                <div v-if="form.errors.start_date" class="form-error">{{ form.errors.start_date }}</div>
                            </div>
                            <div class="form-field">
                                <label>To Date *</label>
                                <input v-model="form.end_date" type="date" required />
                                <div v-if="form.errors.end_date" class="form-error">{{ form.errors.end_date }}</div>
                            </div>
                            <div class="form-field">
                                <label>Reason *</label>
                                <input v-model="form.reason" type="text" placeholder="Brief reason" required />
                                <div v-if="form.errors.reason" class="form-error">{{ form.errors.reason }}</div>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;padding-top:12px;border-top:1px solid #f1f5f9;margin-top:12px;">
                            <Button type="submit" :loading="form.processing">
                                Submit Application
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- Summary Stats -->
        <StatsRow :cols="3" :stats="[
            { label: 'Total Requests', value: leaves.total, color: 'warning' },
            { label: 'Approved (This Page)', value: approved, color: 'success' },
            { label: 'Pending (This Page)', value: pending, color: 'danger' },
        ]" />

        <!-- Filters -->
        <FilterBar :active="!!(statusFilter || typeFilter)" @clear="statusFilter = ''; typeFilter = ''; applyFilters()">
            <div class="form-field">
                <label>Status</label>
                <select v-model="statusFilter" @change="applyFilters" style="width:160px;">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="form-field">
                <label>Type</label>
                <select v-model="typeFilter" @change="applyFilters" style="width:180px;">
                    <option value="">All Types</option>
                    <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
                </select>
            </div>
        </FilterBar>

        <!-- Leave Table -->
        <div class="card">
            <div v-if="leaves.data.length === 0" class="card-body" style="text-align:center;padding:40px;color:#94a3b8;">
                No leave requests found.
            </div>

            <div v-else style="overflow-x:auto;">
                <Table>
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="leave in leaves.data" :key="leave.id">
                            <td>
                                <div style="font-weight:500;">{{ leave.user?.name || '—' }}</div>
                                <div style="font-size:.75rem;color:#94a3b8;">{{ leave.user?.email }}</div>
                            </td>
                            <td>
                                <span class="badge" :style="{ background: getLeaveColor(leave) + '22', color: getLeaveColor(leave), border: '1px solid ' + getLeaveColor(leave) + '44' }">
                                    {{ getLeaveLabel(leave) }}
                                </span>
                            </td>
                            <td>
                                <div>{{ school.fmtDate(leave.start_date) }} — {{ school.fmtDate(leave.end_date) }}</div>
                                <div style="font-size:.75rem;color:#94a3b8;">{{ daysBetween(leave.start_date, leave.end_date) }} day(s)</div>
                            </td>
                            <td style="max-width:200px;">
                                <span style="font-size:.8125rem;color:#475569;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ leave.reason }}</span>
                            </td>
                            <td>
                                <span class="badge"
                                    :class="{
                                        'badge-amber': leave.status === 'pending',
                                        'badge-green': leave.status === 'approved',
                                        'badge-red':   leave.status === 'rejected'
                                    }">
                                    {{ leave.status }}
                                </span>
                            </td>
                            <td style="font-size:.8125rem;color:#64748b;">{{ leave.approver?.name || '—' }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <template v-if="leave.status === 'pending'">
                                        <Button variant="success" size="xs" @click="approveLeave(leave.id)">Approve</Button>
                                        <Button variant="danger" size="xs" @click="rejectLeave(leave.id)">Reject</Button>
                                    </template>
                                    <template v-else>
                                        <Button variant="secondary" size="xs" @click="revertLeave(leave.id)" title="Revert to Pending">Undo</Button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>
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
        </div>

    </SchoolLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }

/* Pagination */
.pagination-bar { display: flex; justify-content: center; padding: 16px; border-top: 1px solid var(--border-light); }
.pagination-links { display: flex; gap: 4px; }
.pagination-item { padding: 6px 12px; font-size: .875rem; border-radius: 6px; font-weight: 500; text-decoration: none; transition: all .15s; color: var(--text-primary); }
.pagination-item:hover { background: var(--bg); }
.pagination-active { background: var(--accent) !important; color: #fff !important; }
.pagination-disabled { color: var(--text-muted); background: #f8fafc; cursor: default; }
</style>
