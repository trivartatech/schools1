<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const cancel = (id) => {
    if (!confirm('Cancel this request?')) return;
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
        <div class="page-header">
            <div>
                <h1 class="page-header-title">My Gate Pass Requests</h1>
                <p v-if="student" style="color:#64748b;font-size:.9rem;">{{ student.first_name }} {{ student.last_name }} · {{ student.admission_no }}</p>
            </div>
            <Button v-if="hostelActive" @click="showForm = true">+ New Request</Button>
        </div>

        <!-- Not in hostel notice -->
        <div v-if="!hostelActive" class="card" style="padding:32px;text-align:center;color:#94a3b8;">
            <div style="font-size:2rem;margin-bottom:8px;">🏠</div>
            <div style="font-weight:600;margin-bottom:4px;">You are not currently enrolled in the hostel.</div>
            <div style="font-size:.9rem;">Gate pass requests are only available for active hostel students.</div>
        </div>

        <!-- Request list -->
        <div v-else-if="passes.length === 0" class="card" style="padding:32px;text-align:center;color:#94a3b8;">
            No gate pass requests yet. Click <strong>+ New Request</strong> to apply.
        </div>

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
        <Teleport to="body">
            <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
                <div class="modal" style="max-width:500px;width:100%;">
                    <div class="modal-header">
                        <h3 class="modal-title">New Gate Pass Request</h3>
                        <button @click="showForm = false" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submit">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
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
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showForm = false">Cancel</Button>
                            <Button type="submit" :loading="form.processing">Submit Request</Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.modal-backdrop { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;z-index:1000; }
.modal { background:#fff;border-radius:12px;box-shadow:0 20px 25px -5px rgba(0,0,0,.1); }
.modal-header { padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center; }
.modal-title { font-size:1rem;font-weight:700;color:#1e293b; }
.modal-close { background:none;border:none;font-size:1.5rem;line-height:1;color:#94a3b8;cursor:pointer; }
.modal-body { padding:20px; }
.modal-footer { padding:16px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 12px 12px;display:flex;justify-content:flex-end;gap:10px; }
</style>
