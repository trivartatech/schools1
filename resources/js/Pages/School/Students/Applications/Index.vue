<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

const school = useSchoolStore();
const confirm = useConfirm();

const props = defineProps({
    applications: { type: Object, required: true }, // paginated: { data, total, links, ... }
    activeStatus: { type: String, default: 'pending' },
    counts: { type: Object, required: true },
});

const tabs = [
    { key: 'pending',  label: 'Pending',  icon: '🕐', color: 'yellow' },
    { key: 'approved', label: 'Approved', icon: '✅', color: 'green'  },
    { key: 'rejected', label: 'Rejected', icon: '❌', color: 'red'    },
];

const switchTab = (status) => {
    router.get('/school/registrations', { status }, { preserveState: true, replace: true });
};

// Reject modal
const showRejectModal = ref(false);
const rejectTarget    = ref(null);
const rejectForm = useForm({ rejection_reason: '' });

const openRejectModal = (app) => {
    rejectTarget.value = app;
    rejectForm.reset();
    showRejectModal.value = true;
};

const submitReject = () => {
    rejectForm.post(`/school/registrations/${rejectTarget.value.id}/reject`, {
        onSuccess: () => { showRejectModal.value = false; }
    });
};

const doApprove = async (app) => {
    const ok = await confirm({
        title: 'Approve application?',
        message: `Approve ${app.first_name} ${app.last_name || ''}? This will create a student record.`,
        confirmLabel: 'Approve',
    });
    if (!ok) return;
    router.post(`/school/registrations/${app.id}/approve`);
};

const tabColor = computed(() => {
    const c = { pending: 'yellow', approved: 'green', rejected: 'red' };
    return c[props.activeStatus] ?? 'gray';
});

const statusBadge = (status) => {
    const map = {
        pending:  'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <Head title="Student Registrations" />
    <SchoolLayout title="Student Registrations">

        <!-- Header -->
        <PageHeader title="Student Registration Queue" subtitle="Review and approve new student applications before they enter the directory.">
            <template #actions>
                <Button as="link" href="/school/registrations/create">
                                + New Application
                            </Button>
            </template>
        </PageHeader>

        <!-- Status Tabs -->
        <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;">
            <Button v-for="tab in tabs" :key="tab.key"
                    @click="switchTab(tab.key)"
                    :variant="activeStatus === tab.key ? 'primary' : 'secondary'"
                    style="display:flex;align-items:center;gap:0.5rem;">
                <span>{{ tab.icon }}</span>
                <span>{{ tab.label }}</span>
                <span class="badge" :class="activeStatus === tab.key ? 'badge-indigo' : 'badge-gray'" style="margin-left:0.25rem;">
                    {{ counts[tab.key] ?? 0 }}
                </span>
            </Button>
        </div>

        <!-- Applications Table -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <Table v-if="applications.data?.length > 0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg No</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Phone</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(app, i) in applications.data" :key="app.id">
                            <td style="font-family:monospace;color:#9ca3af;">{{ i + 1 }}</td>
                            <td>
                                <span class="badge badge-indigo" style="font-family:monospace;">
                                    {{ app.reg_no || '—' }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.75rem;">
                                    <div style="width:2rem;height:2rem;border-radius:9999px;background:rgba(99,102,241,0.1);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:0.875rem;font-weight:700;flex-shrink:0;">
                                        {{ app.first_name?.charAt(0) }}
                                    </div>
                                    <div>
                                        <p style="font-size:0.875rem;font-weight:600;color:#111827;">{{ app.first_name }} {{ app.last_name }}</p>
                                        <p style="font-size:0.75rem;color:#9ca3af;">{{ app.gender }} · {{ school.fmtDate(app.dob) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                {{ app.course_class?.name || '—' }}
                                <span v-if="app.section" style="color:#9ca3af;">/ {{ app.section.name }}</span>
                            </td>
                            <td style="font-family:monospace;">{{ app.primary_phone }}</td>
                            <td>{{ school.fmtDate(app.submitted_at) || '—' }}</td>
                            <td>
                                <span :class="{
                                    'badge badge-amber': app.status === 'pending',
                                    'badge badge-green': app.status === 'approved',
                                    'badge badge-red':   app.status === 'rejected',
                                }" style="text-transform:capitalize;">
                                    {{ app.status }}
                                </span>
                                <p v-if="app.status === 'rejected' && app.rejection_reason" style="font-size:0.75rem;color:var(--danger);margin-top:0.25rem;max-width:16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    {{ app.rejection_reason }}
                                </p>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.375rem;">
                                    <Button variant="secondary" size="xs" as="link" :href="`/school/registrations/${app.id}`">View</Button>
                                    <template v-if="app.status === 'pending'">
                                        <Button variant="warning" size="xs" as="link" :href="`/school/registrations/${app.id}/edit`">Edit</Button>
                                        <Button variant="success" size="xs" @click="doApprove(app)">Approve</Button>
                                        <Button variant="danger" size="xs" @click="openRejectModal(app)">Reject</Button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </Table>

                <!-- Pagination -->
                <div v-if="applications.data?.length > 0 && applications.links?.length > 3"
                     style="display:flex;justify-content:center;gap:4px;padding:14px 18px;border-top:1px solid var(--border);">
                    <Link v-for="(link, k) in applications.links" :key="k"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :style="[
                            'display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border-radius:7px;font-size:0.8125rem;border:1px solid #e2e8f0;text-decoration:none;transition:all .13s;',
                            link.active ? 'background:#6366f1;border-color:#6366f1;color:#fff;font-weight:700;' : 'background:#fff;color:#475569;',
                            !link.url   ? 'opacity:.4;pointer-events:none;' : '',
                        ]" />
                </div>

                <!-- Empty State -->
                <div v-else-if="!applications.data?.length" style="padding:4rem 0;text-align:center;color:#9ca3af;">
                    <p style="font-size:2rem;margin-bottom:0.75rem;">📭</p>
                    <p style="font-size:0.875rem;font-weight:500;">No {{ activeStatus }} applications found.</p>
                    <Link v-if="activeStatus === 'pending'" href="/school/registrations/create"
                          style="display:inline-block;margin-top:1rem;color:var(--accent);font-size:0.875rem;">
                        Submit the first application →
                    </Link>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="card" style="width:100%;max-width:28rem;">
                <div class="card-header">
                    <span class="card-title">Reject Application</span>
                </div>
                <div class="card-body">
                    <p style="font-size:0.875rem;color:#6b7280;margin-bottom:1rem;">
                        Rejecting <strong>{{ rejectTarget?.first_name }} {{ rejectTarget?.last_name }}</strong>.
                        Please provide a reason.
                    </p>
                    <div class="form-field">
                        <label>Rejection Reason</label>
                        <textarea v-model="rejectForm.rejection_reason" rows="3"
                                  placeholder="e.g. Incomplete documents / class is full..."
                                  style="width:100%;border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.5rem 0.75rem;font-size:0.875rem;resize:none;"></textarea>
                        <span v-if="rejectForm.errors.rejection_reason" class="form-error">{{ rejectForm.errors.rejection_reason }}</span>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
                        <Button variant="secondary" @click="showRejectModal = false">Cancel</Button>
                        <Button variant="danger" @click="submitReject" :loading="rejectForm.processing">
                            Reject Application
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
