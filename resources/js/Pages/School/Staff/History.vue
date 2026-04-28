<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    staff:        Object,
    history:      Array,
    designations: Array,
    departments:  Array,
});

const showForm  = ref(false);
const form = useForm({
    event_type:          'promotion',
    from_designation_id: props.staff.designation_id ?? '',
    to_designation_id:   '',
    from_department_id:  props.staff.department_id ?? '',
    to_department_id:    '',
    from_salary:         props.staff.basic_salary ?? '',
    to_salary:           '',
    effective_date:      new Date().toISOString().slice(0, 10),
    order_no:            '',
    remarks:             '',
});

const submit = () => {
    form.post(`/school/staff/${props.staff.id}/history`, {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
};

const eventTypeColor = {
    joining:             'badge-green',
    promotion:           'badge-blue',
    transfer:            'badge-amber',
    demotion:            'badge-red',
    salary_revision:     'badge-green',
    department_change:   'badge-amber',
    designation_change:  'badge-blue',
    increment:           'badge-green',
    confirmation:        'badge-green',
    termination:         'badge-red',
    other:               'badge-gray',
};

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();
const fmtSal = (n) => n ? '₹' + Number(n).toLocaleString('en-IN') : '—';

const needsDesignation = (et) => ['promotion', 'demotion', 'designation_change', 'confirmation'].includes(et);
const needsDepartment  = (et) => ['transfer', 'department_change'].includes(et);
const needsSalary      = (et) => ['salary_revision', 'increment', 'promotion', 'joining'].includes(et);
</script>

<template>
    <SchoolLayout :title="`Staff History — ${staff.user?.name ?? staff.user?.first_name}`">
        <div class="page-header">
            <div>
                <Link href="/school/staff" style="font-size:.8rem;color:#94a3b8;">← Back to Staff</Link>
                <h1 class="page-header-title" style="margin-top:4px;">
                    {{ staff.user?.name ?? `${staff.user?.first_name} ${staff.user?.last_name}` }}
                </h1>
                <p style="color:#64748b;font-size:.9rem;">
                    {{ staff.designation?.name }} · {{ staff.department?.name }} · {{ staff.employee_id }}
                </p>
            </div>
            <Button @click="showForm = true">+ Record Event</Button>
        </div>

        <!-- Current profile summary -->
        <div class="card" style="margin-bottom:20px;padding:16px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;">
                <div>
                    <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Designation</div>
                    <div style="font-weight:600;margin-top:2px;">{{ staff.designation?.name ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Department</div>
                    <div style="font-weight:600;margin-top:2px;">{{ staff.department?.name ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Basic Salary</div>
                    <div style="font-weight:600;margin-top:2px;">{{ fmtSal(staff.basic_salary) }}</div>
                </div>
                <div>
                    <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Joining Date</div>
                    <div style="font-weight:600;margin-top:2px;">{{ fmt(staff.joining_date) }}</div>
                </div>
                <div>
                    <div style="font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Events Logged</div>
                    <div style="font-weight:700;font-size:1.4rem;color:#3b82f6;margin-top:2px;">{{ history.length }}</div>
                </div>
            </div>
        </div>

        <!-- History Timeline -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Career History</span>
            </div>

            <div v-if="!history.length" style="padding:32px;text-align:center;color:#94a3b8;">
                No history events recorded yet.
            </div>

            <div v-else style="padding:16px;position:relative;">
                <div style="position:absolute;left:36px;top:0;bottom:0;width:2px;background:#e2e8f0;z-index:0;"></div>

                <div v-for="event in history" :key="event.id" style="display:flex;gap:16px;margin-bottom:20px;position:relative;z-index:1;">
                    <div style="width:40px;height:40px;border-radius:50%;background:#f1f5f9;border:2px solid #e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem;">
                        {{ event.event_type === 'promotion' ? '⬆️' : event.event_type === 'transfer' ? '➡️' : event.event_type === 'salary_revision' || event.event_type === 'increment' ? '💰' : event.event_type === 'termination' ? '🚪' : '📋' }}
                    </div>
                    <div style="flex:1;background:#f8fafc;border-radius:8px;padding:12px 16px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:4px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="badge" :class="eventTypeColor[event.event_type]" style="font-size:.75rem;text-transform:capitalize;">{{ event.event_type.replace('_', ' ') }}</span>
                                <span v-if="event.order_no" style="font-size:.75rem;color:#94a3b8;">Order: {{ event.order_no }}</span>
                            </div>
                            <span style="font-size:.8rem;color:#94a3b8;">{{ fmt(event.effective_date) }}</span>
                        </div>
                        <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:12px;font-size:.85rem;">
                            <span v-if="event.from_designation?.name || event.to_designation?.name">
                                <span v-if="event.from_designation" style="color:#94a3b8;">{{ event.from_designation.name }}</span>
                                <span v-if="event.from_designation && event.to_designation"> → </span>
                                <span v-if="event.to_designation" style="font-weight:600;color:#1e293b;">{{ event.to_designation.name }}</span>
                            </span>
                            <span v-if="event.from_department?.name || event.to_department?.name">
                                <span v-if="event.from_department" style="color:#94a3b8;">{{ event.from_department.name }}</span>
                                <span v-if="event.from_department && event.to_department"> → </span>
                                <span v-if="event.to_department" style="font-weight:600;color:#1e293b;">{{ event.to_department.name }}</span>
                            </span>
                            <span v-if="event.from_salary || event.to_salary">
                                <span v-if="event.from_salary" style="color:#94a3b8;">{{ fmtSal(event.from_salary) }}</span>
                                <span v-if="event.from_salary && event.to_salary"> → </span>
                                <span v-if="event.to_salary" style="font-weight:600;color:#10b981;">{{ fmtSal(event.to_salary) }}</span>
                            </span>
                        </div>
                        <div v-if="event.remarks" style="margin-top:4px;font-size:.8rem;color:#64748b;font-style:italic;">{{ event.remarks }}</div>
                        <div v-if="event.recorded_by" style="margin-top:4px;font-size:.75rem;color:#94a3b8;">
                            Recorded by {{ event.recorded_by.name ?? `${event.recorded_by.first_name} ${event.recorded_by.last_name}` }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Record Event Modal -->
        <Teleport to="body">
            <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
                <div class="modal" style="max-width:500px;width:100%;">
                    <div class="modal-header">
                        <h3 class="modal-title">Record Career Event</h3>
                        <button @click="showForm = false" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="submit">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
                            <div class="form-field">
                                <label>Event Type *</label>
                                <select v-model="form.event_type" required>
                                    <option value="promotion">Promotion</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="demotion">Demotion</option>
                                    <option value="salary_revision">Salary Revision</option>
                                    <option value="increment">Increment</option>
                                    <option value="department_change">Department Change</option>
                                    <option value="designation_change">Designation Change</option>
                                    <option value="confirmation">Confirmation (after probation)</option>
                                    <option value="joining">Joining</option>
                                    <option value="termination">Termination</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div v-if="needsDesignation(form.event_type)" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div class="form-field" style="margin:0;">
                                    <label>From Designation</label>
                                    <select v-model="form.from_designation_id">
                                        <option value="">—</option>
                                        <option v-for="d in designations" :key="d.id" :value="d.id">{{ d.name }}</option>
                                    </select>
                                </div>
                                <div class="form-field" style="margin:0;">
                                    <label>To Designation</label>
                                    <select v-model="form.to_designation_id">
                                        <option value="">—</option>
                                        <option v-for="d in designations" :key="d.id" :value="d.id">{{ d.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="needsDepartment(form.event_type)" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div class="form-field" style="margin:0;">
                                    <label>From Department</label>
                                    <select v-model="form.from_department_id">
                                        <option value="">—</option>
                                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                                    </select>
                                </div>
                                <div class="form-field" style="margin:0;">
                                    <label>To Department</label>
                                    <select v-model="form.to_department_id">
                                        <option value="">—</option>
                                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="needsSalary(form.event_type)" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div class="form-field" style="margin:0;">
                                    <label>From Salary (₹)</label>
                                    <input v-model="form.from_salary" type="number" min="0" step="0.01" />
                                </div>
                                <div class="form-field" style="margin:0;">
                                    <label>To Salary (₹)</label>
                                    <input v-model="form.to_salary" type="number" min="0" step="0.01" />
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div class="form-field" style="margin:0;">
                                    <label>Effective Date *</label>
                                    <input v-model="form.effective_date" type="date" required />
                                </div>
                                <div class="form-field" style="margin:0;">
                                    <label>Order / Reference No</label>
                                    <input v-model="form.order_no" placeholder="e.g. HR/2026/045" />
                                </div>
                            </div>
                            <div class="form-field">
                                <label>Remarks</label>
                                <textarea v-model="form.remarks" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showForm = false">Cancel</Button>
                            <Button type="submit" :loading="form.processing">Record Event</Button>
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
