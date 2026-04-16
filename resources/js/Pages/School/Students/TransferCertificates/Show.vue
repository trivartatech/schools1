<script setup>
import Button from '@/Components/ui/Button.vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { ref } from 'vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    tc: Object,
});

// Approve
const approveForm = useForm({ remarks: '' });
const showApproveModal = ref(false);
const doApprove = () => {
    approveForm.patch(route('school.transfer-certificates.approve', props.tc.id), {
        onSuccess: () => { showApproveModal.value = false; },
    });
};

// Reject
const rejectForm = useForm({ remarks: '' });
const showRejectModal = ref(false);
const doReject = () => {
    rejectForm.patch(route('school.transfer-certificates.reject', props.tc.id), {
        onSuccess: () => { showRejectModal.value = false; },
    });
};

// Issue
const issueForm = useForm({ remarks: '' });
const showIssueModal = ref(false);
const doIssue = () => {
    issueForm.patch(route('school.transfer-certificates.issue', props.tc.id), {
        onSuccess: () => { showIssueModal.value = false; },
    });
};

const statusBadge = (s) => ({
    requested: 'bg-amber-100 text-amber-700 border-amber-200',
    approved:  'bg-blue-100 text-blue-700 border-blue-200',
    issued:    'bg-emerald-100 text-emerald-700 border-emerald-200',
    rejected:  'bg-red-100 text-red-700 border-red-200',
}[s] ?? 'bg-slate-100 text-slate-600');

const formatDate = (d) => d ? school.fmtDate(d) : '—';
const formatDT   = (d) => d ? school.fmtDateTime(d) : '—';
</script>

<template>
    <SchoolLayout :title="`TC — ${tc.student?.first_name} ${tc.student?.last_name}`">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Transfer Certificate</h2>
                <p class="page-header-sub">
                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border', statusBadge(tc.status)]">
                        {{ tc.status.toUpperCase() }}
                    </span>
                    <span v-if="tc.certificate_no" class="ml-2 font-mono text-slate-600">{{ tc.certificate_no }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <!-- Print (only when issued) -->
                <Button variant="secondary" as="a" v-if="tc.status === 'issued'" :href="route('school.transfer-certificates.print', tc.id)" target="_blank">
                    🖨 Print TC
                </Button>
                <Button variant="secondary" as="link" :href="route('school.transfer-certificates.index')">← Back</Button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Student info + TC details -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Student Card -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Student Information</h3></div>
                    <div class="card-body flex gap-5 items-start">
                        <div class="shrink-0">
                            <img v-if="tc.student?.photo_url"
                                 :src="tc.student.photo_url"
                                 class="w-20 h-20 rounded-xl object-cover border border-slate-200">
                            <div v-else class="w-20 h-20 rounded-xl bg-indigo-100 flex items-center justify-center text-2xl font-bold text-indigo-500">
                                {{ tc.student?.first_name?.[0] }}
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 flex-1">
                            <div>
                                <div class="text-xs text-slate-400 font-medium uppercase tracking-wide">Name</div>
                                <div class="font-bold text-slate-800">{{ tc.student?.first_name }} {{ tc.student?.last_name }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-400 font-medium uppercase tracking-wide">Admission No</div>
                                <div class="font-mono text-slate-700">{{ tc.student?.admission_no }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-400 font-medium uppercase tracking-wide">Class</div>
                                <div class="text-slate-700">
                                    {{ tc.student?.current_academic_history?.course_class?.name || '—' }}
                                    {{ tc.student?.current_academic_history?.section ? '/ ' + tc.student.current_academic_history.section.name : '' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-400 font-medium uppercase tracking-wide">Date of Birth</div>
                                <div class="text-slate-700">{{ formatDate(tc.student?.dob) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-400 font-medium uppercase tracking-wide">Admission Date</div>
                                <div class="text-slate-700">{{ formatDate(tc.student?.admission_date) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-400 font-medium uppercase tracking-wide">Student Status</div>
                                <div :class="['text-sm font-bold', tc.student?.status === 'tc' ? 'text-red-600' : tc.student?.status === 'active' ? 'text-emerald-600' : 'text-slate-600']">
                                    {{ tc.student?.status?.toUpperCase() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TC Details -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Transfer Certificate Details</h3></div>
                    <div class="card-body grid grid-cols-2 gap-x-8 gap-y-4">
                        <div>
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Date of Leaving</div>
                            <div class="font-semibold text-slate-800">{{ formatDate(tc.leaving_date) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Last Class Studied</div>
                            <div class="text-slate-700">{{ tc.last_class_studied || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Conduct</div>
                            <div :class="['font-semibold', tc.conduct === 'Good' ? 'text-emerald-600' : tc.conduct === 'Poor' ? 'text-red-600' : 'text-amber-600']">
                                {{ tc.conduct }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Fee Paid Up To</div>
                            <div class="text-slate-700">{{ formatDate(tc.fee_paid_upto) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Pending Dues</div>
                            <div :class="tc.has_dues ? 'text-red-600 font-bold' : 'text-emerald-600'">
                                {{ tc.has_dues ? 'Yes — dues pending' : 'No dues' }}
                            </div>
                        </div>
                        <div v-if="tc.certificate_no">
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Certificate No</div>
                            <div class="font-mono font-bold text-indigo-700">{{ tc.certificate_no }}</div>
                        </div>
                        <div class="col-span-2" v-if="tc.reason">
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Reason for Leaving</div>
                            <p class="text-slate-700 text-sm">{{ tc.reason }}</p>
                        </div>
                        <div class="col-span-2" v-if="tc.remarks">
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wide mb-0.5">Remarks</div>
                            <p class="text-slate-600 text-sm italic">{{ tc.remarks }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Workflow -->
            <div class="space-y-5">

                <!-- Timeline -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title text-sm">Workflow Timeline</h3></div>
                    <div class="card-body space-y-4">
                        <!-- Step: Requested -->
                        <div class="flex gap-3 items-start">
                            <div :class="['w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs font-bold', 'bg-amber-100 text-amber-600']">1</div>
                            <div>
                                <div class="text-sm font-semibold text-slate-700">Requested</div>
                                <div class="text-xs text-slate-400">by {{ tc.requested_by?.name }}</div>
                                <div class="text-xs text-slate-400">{{ formatDT(tc.created_at) }}</div>
                            </div>
                        </div>
                        <!-- Step: Approved/Rejected -->
                        <div class="flex gap-3 items-start">
                            <div :class="['w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs font-bold',
                                tc.status === 'rejected' ? 'bg-red-100 text-red-600' :
                                ['approved','issued'].includes(tc.status) ? 'bg-blue-100 text-blue-600' :
                                'bg-slate-100 text-slate-400']">2</div>
                            <div>
                                <div :class="['text-sm font-semibold', ['approved','issued'].includes(tc.status) ? 'text-blue-700' : tc.status === 'rejected' ? 'text-red-700' : 'text-slate-400']">
                                    {{ tc.status === 'rejected' ? 'Rejected' : 'Approved' }}
                                </div>
                                <div v-if="tc.approved_by" class="text-xs text-slate-400">by {{ tc.approved_by?.name }}</div>
                                <div v-if="tc.approved_at" class="text-xs text-slate-400">{{ formatDT(tc.approved_at) }}</div>
                                <div v-else class="text-xs text-slate-300">Pending</div>
                            </div>
                        </div>
                        <!-- Step: Issued -->
                        <div class="flex gap-3 items-start">
                            <div :class="['w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs font-bold',
                                tc.status === 'issued' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400']">3</div>
                            <div>
                                <div :class="['text-sm font-semibold', tc.status === 'issued' ? 'text-emerald-700' : 'text-slate-400']">
                                    Issued
                                </div>
                                <div v-if="tc.issued_at" class="text-xs text-slate-400">{{ formatDT(tc.issued_at) }}</div>
                                <div v-else class="text-xs text-slate-300">Not yet issued</div>
                                <div v-if="tc.certificate_no" class="font-mono text-xs text-indigo-600 font-bold mt-0.5">{{ tc.certificate_no }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card" v-if="tc.status !== 'issued'">
                    <div class="card-header"><h3 class="card-title text-sm">Actions</h3></div>
                    <div class="card-body space-y-2">
                        <!-- Approve -->
                        <Button v-if="tc.status === 'requested'" @click="showApproveModal = true" class="w-full">
                            ✓ Approve TC Request
                        </Button>
                        <!-- Issue -->
                        <Button v-if="tc.status === 'approved'" @click="showIssueModal = true" class="w-full bg-emerald-600 hover:bg-emerald-700 border-emerald-600">
                            🎓 Issue Transfer Certificate
                        </Button>
                        <!-- Reject -->
                        <Button variant="secondary" v-if="['requested','approved'].includes(tc.status)" @click="showRejectModal = true" class="w-full text-red-600 hover:bg-red-50 border-red-200">
                            ✕ Reject
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Approve Modal ── -->
        <div v-if="showApproveModal" class="modal-overlay" @mousedown.self="showApproveModal = false">
            <div class="modal-box">
                <h3 class="modal-title text-blue-700">Approve TC Request</h3>
                <p class="text-sm text-slate-500 mb-4">
                    Approving will move this TC to the next stage. The certificate will be issued after a final confirmation.
                </p>
                <div class="form-field mb-4">
                    <label>Remarks (optional)</label>
                    <textarea v-model="approveForm.remarks" rows="3" class="input" placeholder="Any notes…"></textarea>
                </div>
                <div class="flex gap-3 justify-end">
                    <Button variant="secondary" @click="showApproveModal = false">Cancel</Button>
                    <Button @click="doApprove" :loading="approveForm.processing">
                        Approve
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── Issue Modal ── -->
        <div v-if="showIssueModal" class="modal-overlay" @mousedown.self="showIssueModal = false">
            <div class="modal-box">
                <h3 class="modal-title text-emerald-700">Issue Transfer Certificate</h3>
                <p class="text-sm text-slate-500 mb-2">
                    This will <strong>permanently issue</strong> the TC and mark the student's status as <span class="font-bold text-red-600">TC</span>.
                    A unique certificate number will be generated.
                </p>
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-700 mb-4">
                    ⚠️ This action cannot be undone. Ensure all details are correct before proceeding.
                </div>
                <div class="form-field mb-4">
                    <label>Final Remarks (optional)</label>
                    <textarea v-model="issueForm.remarks" rows="3" class="input" placeholder="Any final notes for the certificate…"></textarea>
                </div>
                <div class="flex gap-3 justify-end">
                    <Button variant="secondary" @click="showIssueModal = false">Cancel</Button>
                    <Button @click="doIssue" :loading="issueForm.processing" class="bg-emerald-600 hover:bg-emerald-700 border-emerald-600">
                        Issue TC
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── Reject Modal ── -->
        <div v-if="showRejectModal" class="modal-overlay" @mousedown.self="showRejectModal = false">
            <div class="modal-box">
                <h3 class="modal-title text-red-700">Reject TC Request</h3>
                <p class="text-sm text-slate-500 mb-4">Please provide a reason for rejection.</p>
                <div class="form-field mb-4">
                    <label class="required">Reason for Rejection</label>
                    <textarea v-model="rejectForm.remarks" rows="3" class="input"
                              :class="rejectForm.errors.remarks ? 'border-red-400' : ''"
                              placeholder="e.g. Pending dues, incomplete documentation…"></textarea>
                    <p v-if="rejectForm.errors.remarks" class="form-error">{{ rejectForm.errors.remarks }}</p>
                </div>
                <div class="flex gap-3 justify-end">
                    <Button variant="secondary" @click="showRejectModal = false">Cancel</Button>
                    <Button @click="doReject" :loading="rejectForm.processing" class="bg-red-600 hover:bg-red-700 border-red-600">
                        Reject
                    </Button>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
