<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    editRequest: Object,
    requestType: String,
    diff: Object
});

const form = useForm({
    rejection_reason: ''
});

const school = useSchoolStore();
const confirm = useConfirm();

const showRejectModal = ref(false);

const approve = async () => {
    const ok = await confirm({
        title: 'Approve changes?',
        message: 'Are you sure you want to approve these changes? They will be applied immediately.',
        confirmLabel: 'Approve',
    });
    if (!ok) return;
    form.post(`/school/edit-requests/${props.editRequest.id}/approve`);
};

const reject = () => {
    form.post(`/school/edit-requests/${props.editRequest.id}/reject`, {
        onSuccess: () => showRejectModal.value = false
    });
};
</script>

<template>
    <Head title="Review Edit Request" />
    <SchoolLayout title="Review Edit Request">

        <PageHeader
            title="Review Edit Request"
            :subtitle="`Submitted by ${editRequest.user.name} on ${school.fmtDateTime(editRequest.created_at)}`"
        >
            <template #actions>
                <template v-if="editRequest.status === 'pending'">
                    <Button variant="danger" @click="showRejectModal = true">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reject
                    </Button>
                    <Button @click="approve" :loading="form.processing">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Approve Changes
                    </Button>
                </template>
            </template>
        </PageHeader>

        <div class="review-layout">

            <!-- Sidebar -->
            <aside class="review-sidebar">

                <!-- Status -->
                <div class="card">
                    <div class="card-body">
                        <p class="meta-label">Request Status</p>
                        <span class="badge"
                            :class="{
                                'badge-amber': editRequest.status === 'pending',
                                'badge-green': editRequest.status === 'approved',
                                'badge-red':   editRequest.status === 'rejected'
                            }">
                            {{ editRequest.status.toUpperCase() }}
                        </span>
                    </div>
                </div>

                <!-- User Context -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--accent)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            User Context
                        </h3>
                    </div>
                    <div class="card-body">
                        <dl class="meta-list">
                            <div class="meta-row">
                                <dt class="meta-label">Profile Type</dt>
                                <dd class="meta-value">{{ requestType }}</dd>
                            </div>
                            <div class="meta-row">
                                <dt class="meta-label">Email</dt>
                                <dd class="meta-value">{{ editRequest.user.email }}</dd>
                            </div>
                            <div class="meta-row">
                                <dt class="meta-label">Phone</dt>
                                <dd class="meta-value">{{ editRequest.user.phone }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Requester Note -->
                <div class="card note-card note-card--info" v-if="editRequest.reason">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Requester's Note
                        </h3>
                    </div>
                    <div class="card-body">
                        <blockquote class="note-quote note-quote--info">"{{ editRequest.reason }}"</blockquote>
                    </div>
                </div>

                <!-- Rejection Reason -->
                <div class="card note-card note-card--danger" v-if="editRequest.status === 'rejected'">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--danger)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            Rejection Reason
                        </h3>
                    </div>
                    <div class="card-body">
                        <blockquote class="note-quote note-quote--danger">{{ editRequest.rejection_reason }}</blockquote>
                    </div>
                </div>

            </aside>

            <!-- Diff Viewer -->
            <main class="review-main">
                <div class="card diff-card">
                    <div class="card-header diff-card-header">
                        <h3 class="card-title">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--accent)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            Requested Changes
                        </h3>
                        <span class="badge badge-indigo">{{ Object.keys(diff).length }} field{{ Object.keys(diff).length === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="diff-table-wrap">
                        <Table class="diff-table">
                            <thead>
                                <tr>
                                    <th class="col-field">Field</th>
                                    <th class="col-old">
                                        <span class="diff-col-label diff-col-label--old">
                                            <span class="diff-pill diff-pill--old">−</span>
                                            Current Value
                                        </span>
                                    </th>
                                    <th class="col-new">
                                        <span class="diff-col-label diff-col-label--new">
                                            <span class="diff-pill diff-pill--new">+</span>
                                            Requested Value
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(change, field) in diff" :key="field" class="diff-row">
                                    <td class="cell-field">{{ field }}</td>
                                    <td class="cell-old" :class="{ 'cell-blank': !change.old }">
                                        <div class="diff-cell-inner">
                                            <span class="diff-pill diff-pill--old">−</span>
                                            <span>{{ change.old || '— blank —' }}</span>
                                        </div>
                                    </td>
                                    <td class="cell-new" :class="{ 'cell-blank': !change.new }">
                                        <div class="diff-cell-inner">
                                            <span class="diff-pill diff-pill--new">+</span>
                                            <span class="cell-new-text">{{ change.new || '— blank —' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </Table>
                    </div>
                </div>
            </main>
        </div>

        <!-- Reject Modal -->
        <Modal v-model:open="showRejectModal" title="Reject Request" size="sm">
            <form @submit.prevent="reject" id="reject-edit-request-form">
                <div class="form-field">
                    <label class="modal-field-label">
                        Reason for rejection
                        <span class="required-star">*</span>
                    </label>
                    <textarea
                        v-model="form.rejection_reason"
                        rows="4"
                        required
                        placeholder="Please explain why this change is being rejected…"
                        class="modal-textarea"
                    ></textarea>
                    <p v-if="form.errors.rejection_reason" class="form-error">{{ form.errors.rejection_reason }}</p>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showRejectModal = false">Cancel</Button>
                <Button variant="danger" type="submit" form="reject-edit-request-form" :loading="form.processing">
                    <svg v-if="!form.processing" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    Confirm Rejection
                </Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
/* ── Two-column layout ── */
.review-layout {
    display: grid;
    grid-template-columns: 256px 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 768px) {
    .review-layout { grid-template-columns: 1fr; }
}
.review-sidebar { display: flex; flex-direction: column; gap: 1rem; }
.review-main    { min-width: 0; }

/* ── Sidebar card titles ── */
.card-title {
    display: flex;
    align-items: center;
    gap: .4rem;
}

/* ── Meta list ── */
.meta-list { margin: 0; padding: 0; display: flex; flex-direction: column; gap: .875rem; }
.meta-row  { display: flex; flex-direction: column; gap: .15rem; }
.meta-label {
    font-size: .6875rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.meta-value { font-size: .875rem; font-weight: 500; color: #1e293b; }

/* ── Note cards ── */
.note-card--info   { border-left: 3px solid #6366f1; }
.note-card--danger { border-left: 3px solid var(--danger); }
.note-quote {
    margin: 0;
    font-size: .8125rem;
    line-height: 1.65;
    font-style: italic;
    padding-left: .75rem;
    border-left: 3px solid;
}
.note-quote--info   { border-color: #6366f1; color: #4338ca; }
.note-quote--danger { border-color: var(--danger); color: #b91c1c; }

/* ── Diff table ── */
.diff-card { overflow: hidden; }
.diff-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.diff-table-wrap { overflow-x: auto; }
.diff-table {
    font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
    font-size: .8125rem;
}

.col-field { width: 20%; }
.col-old, .col-new { width: 40%; }

.diff-col-label {
    display: inline-flex;
    align-items: center;
    gap: .375rem;
    font-size: .75rem;
    font-weight: 700;
    font-family: inherit;
}
.diff-col-label--old { color: #dc2626; }
.diff-col-label--new { color: #16a34a; }

.diff-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 3px;
    font-size: .75rem;
    font-weight: 900;
    line-height: 1;
    flex-shrink: 0;
}
.diff-pill--old { background: #fee2e2; color: #dc2626; }
.diff-pill--new { background: #dcfce7; color: #16a34a; }

.diff-cell-inner {
    display: flex;
    align-items: flex-start;
    gap: .5rem;
}

.diff-row { transition: background .1s; }
.diff-row:hover .cell-old { background: #fef2f2; }
.diff-row:hover .cell-new { background: #f0fdf4; }

.cell-field {
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    vertical-align: top;
    white-space: nowrap;
}
.cell-old {
    background: #fff8f8;
    color: #dc2626;
    word-break: break-word;
    vertical-align: top;
}
.cell-new {
    background: #f8fef9;
    color: #16a34a;
    word-break: break-word;
    vertical-align: top;
}
.cell-new-text { font-weight: 700; }
.cell-blank { opacity: .5; font-style: italic; }

/* ── Modal field styles (kept for textarea) ── */
.modal-field-label {
    display: block;
    font-size: .8125rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: .4rem;
}
.required-star { color: var(--danger); margin-left: .15rem; }

.modal-textarea {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .5rem .75rem;
    font-size: .875rem;
    resize: vertical;
    font-family: inherit;
    line-height: 1.6;
    transition: border-color .15s, box-shadow .15s;
    box-sizing: border-box;
    background: #fff;
}
.modal-textarea:focus {
    outline: none;
    border-color: var(--danger);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
}
</style>
