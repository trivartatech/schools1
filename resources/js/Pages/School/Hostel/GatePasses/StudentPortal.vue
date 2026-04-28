<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({
    passes:       { type: Array, default: () => [] },
    hostelActive: { type: Boolean, default: false },
    student:      { type: Object, default: null },
});

const showForm = ref(false);
const form = useForm({
    leave_type:      'Day Out',
    from_date:       '',
    to_date:         '',
    reason:          '',
    destination:     '',
    escort_name:     '',
    escort_relation: '',
    escort_phone:    '',
    parent_name:     '',
});

const submit = () => {
    form.post('/school/hostel/my-gate-passes', {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
};

const cancel = async (id) => {
    const ok = await confirm({
        title: 'Cancel request?',
        message: 'This gate pass request will be cancelled.',
        confirmLabel: 'Cancel Request',
        danger: true,
    });
    if (!ok) return;
    useForm({}).patch(`/school/hostel/my-gate-passes/${id}/cancel`, { preserveScroll: true });
};

const statusColor = {
    Pending:  'badge-amber',
    Approved: 'badge-green',
    Rejected: 'badge-red',
    Out:      'badge-blue',
    Returned: 'badge-gray',
};

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();
</script>

<template>
    <SchoolLayout title="My Gate Passes">
        <PageHeader
            title="My Gate Pass Requests"
            :subtitle="student ? `${student.first_name} ${student.last_name} · ${student.admission_no}` : ''"
        >
            <template #actions>
                <Button v-if="hostelActive" @click="showForm = true">+ New Request</Button>
            </template>
        </PageHeader>

        <!-- Not in hostel notice -->
        <EmptyState
            v-if="!hostelActive"
            title="You are not currently enrolled in the hostel"
            description="Gate pass requests are only available for active hostel students."
        />

        <!-- Request list -->
        <EmptyState
            v-else-if="passes.length === 0"
            title="No gate pass requests yet"
            description="Click '+ New Request' to apply."
            action-label="+ New Request"
            @action="showForm = true"
        />

        <div v-else>
            <div v-for="pass in passes" :key="pass.id" class="card" style="margin-bottom:12px;padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <span style="font-weight:600;">{{ pass.leave_type }}</span>
                            <span class="badge" :class="statusColor[pass.status] ?? 'badge-gray'" style="font-size:.75rem;">{{ pass.status }}</span>
                        </div>
                        <div style="font-size:.85rem;color:#64748b;">
                            {{ fmt(pass.from_date) }} → {{ fmt(pass.to_date) }}
                        </div>
                        <div style="font-size:.85rem;margin-top:4px;">{{ pass.reason }}</div>
                        <div v-if="pass.destination" style="font-size:.8rem;color:#94a3b8;margin-top:2px;">Destination: {{ pass.destination }}</div>
                        <div v-if="pass.approver" style="font-size:.75rem;color:#94a3b8;margin-top:4px;">
                            {{ pass.status === 'Approved' ? 'Approved' : 'Reviewed' }} by {{ pass.approver.name || (pass.approver.first_name + ' ' + pass.approver.last_name) }}
                        </div>
                    </div>
                    <Button v-if="pass.status === 'Pending'" size="xs" variant="danger" @click="cancel(pass.id)">Cancel</Button>
                </div>
            </div>
        </div>

        <!-- New Request Modal -->
        <Modal v-model:open="showForm" title="New Gate Pass Request" size="md">
            <form @submit.prevent="submit" id="gate-pass-form">
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div class="form-field">
                        <label>Leave Type *</label>
                        <select v-model="form.leave_type" required>
                            <option>Day Out</option>
                            <option>Night Out</option>
                            <option>Home Time</option>
                            <option>Emergency</option>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="form-field" style="margin:0;">
                            <label>From Date *</label>
                            <input v-model="form.from_date" type="date" required :min="new Date().toISOString().slice(0,10)" />
                        </div>
                        <div class="form-field" style="margin:0;">
                            <label>To Date *</label>
                            <input v-model="form.to_date" type="date" required :min="form.from_date || new Date().toISOString().slice(0,10)" />
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Reason *</label>
                        <textarea v-model="form.reason" rows="3" required placeholder="Reason for leave..."></textarea>
                    </div>
                    <div class="form-field">
                        <label>Destination</label>
                        <input v-model="form.destination" placeholder="Where are you going?" />
                    </div>

                    <div style="border-top:1px solid #e2e8f0;padding-top:12px;">
                        <div style="font-size:.8rem;color:#64748b;font-weight:600;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Escort / Parent Details (optional)</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div class="form-field" style="margin:0;">
                                <label>Escort Name</label>
                                <input v-model="form.escort_name" />
                            </div>
                            <div class="form-field" style="margin:0;">
                                <label>Relation</label>
                                <input v-model="form.escort_relation" placeholder="Father, Mother..." />
                            </div>
                            <div class="form-field" style="margin:0;">
                                <label>Escort Phone</label>
                                <input v-model="form.escort_phone" />
                            </div>
                            <div class="form-field" style="margin:0;">
                                <label>Parent Name</label>
                                <input v-model="form.parent_name" />
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showForm = false">Cancel</Button>
                <Button type="submit" form="gate-pass-form" :loading="form.processing">Submit Request</Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
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
.form-field textarea { min-height: 80px; resize: vertical; }
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
</style>
