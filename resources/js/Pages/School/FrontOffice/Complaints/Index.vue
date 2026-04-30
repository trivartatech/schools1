<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Modal from '@/Components/ui/Modal.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import FilterBar from '@/Components/ui/FilterBar.vue';

const school = useSchoolStore();

const props = defineProps({
    complaints: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const form = useForm({
    type: 'Academic',
    description: '',
    priority: 'Medium',
    assigned_department_id: '',
});

const showForm = ref(false);

const submit = () => {
    form.post('/school/front-office/complaints', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        }
    });
};

const activeTab = ref('All');
const tabs = ['All', 'Open', 'In Progress', 'Resolved', 'Closed'];

const filteredComplaints = computed(() => {
    if (activeTab.value === 'All') return props.complaints;
    return props.complaints.filter(c => c.status === activeTab.value);
});

// Resolution-notes modal (replaces native prompt)
const notesOpen = ref(false);
const notesValue = ref('');
const notesContext = ref({ complaintId: null, status: '' });
function askNotes(complaint, newStatus) {
    notesContext.value = { complaintId: complaint.id, status: newStatus };
    notesValue.value = complaint.resolution_notes || '';
    notesOpen.value = true;
}
function submitNotes() {
    const { complaintId, status } = notesContext.value;
    router.put(`/school/front-office/complaints/${complaintId}`, {
        status,
        resolution_notes: notesValue.value,
    }, { preserveScroll: true });
    notesOpen.value = false;
}

const updateStatus = (complaint, newStatus) => {
    if (newStatus === 'Resolved' || newStatus === 'Closed') {
        askNotes(complaint, newStatus);
    } else {
        router.put(`/school/front-office/complaints/${complaint.id}`, { status: newStatus }, { preserveScroll: true });
    }
};

</script>

<template>
    <SchoolLayout title="Complaint Management">

        <PageHeader title="Complaint Tracking" subtitle="Register, assign, and resolve school grievances.">
            <template #actions>
                <Button @click="showForm = !showForm">
                                {{ showForm ? 'Cancel Entry' : '+ Log Complaint' }}
                            </Button>
            </template>
        </PageHeader>

        <!-- NEW COMPLAINT FORM -->
        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-[-20px] opacity-0"
                    enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-[-20px]">
        <div v-show="showForm" class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Log New Grievance / Complaint</h2>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Complaint Issue Category</label>
                            <select v-model="form.type" required>
                                <option value="Academic">Academic / Scholastic</option>
                                <option value="Transport">Bus / Transport</option>
                                <option value="Hostel">Hostel / Mess</option>
                                <option value="Facility">Campus Facility</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Assign to Department</label>
                            <select v-model="form.assigned_department_id">
                                <option value="">-- Unassigned --</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Priority Level</label>
                            <select v-model="form.priority" required>
                                <option value="Low">Low (Standard)</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High (Urgent)</option>
                                <option value="Critical">Critical (Immediate Action)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row" style="margin-top: 1rem;">
                        <div class="form-field">
                            <label>Detailed Description</label>
                            <textarea v-model="form.description" rows="3" placeholder="Describe the grievance in detail..." required></textarea>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        <Button variant="secondary" type="button" @click="showForm = false">Cancel</Button>
                        <Button type="submit" :loading="form.processing">Submit Complaint</Button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- STATUS FILTER -->
        <FilterBar :active="activeTab !== 'All'" @clear="activeTab = 'All'">
            <select v-model="activeTab" style="width:180px;">
                <option v-for="tab in tabs" :key="tab" :value="tab">{{ tab }}</option>
            </select>
        </FilterBar>

        <!-- COMPLAINT LIST -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div v-if="filteredComplaints.length === 0" class="card" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No complaints found for the selected status.
            </div>

            <div v-for="complaint in filteredComplaints" :key="complaint.id" class="card" style="position: relative; overflow: hidden;">
                <!-- Corner Category Badge -->
                <span class="badge badge-gray" style="position: absolute; top: 0; right: 0; border-radius: 0 0 0 0.5rem;">
                    {{ complaint.type }} Category
                </span>

                <div class="card-body" style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                            <h3 style="font-weight: 700; font-size: 1.125rem;">CM#{{ complaint.id }}</h3>
                            <span class="badge"
                                  :class="{
                                    'badge-blue': complaint.priority === 'Low',
                                    'badge-amber': complaint.priority === 'Medium',
                                    'badge-red': complaint.priority === 'High' || complaint.priority === 'Critical'
                                  }">
                                {{ complaint.priority }} Priority
                            </span>
                            <span v-if="complaint.sla_breached" class="badge badge-red" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-weight:700;">
                                SLA BREACHED
                            </span>
                            <span v-else-if="complaint.status === 'Open' || complaint.status === 'In Progress'" class="badge badge-green" style="font-size:.65rem;">
                                SLA: {{ complaint.sla_hours }}h
                            </span>
                        </div>

                        <p style="color: var(--text-secondary); font-size: 0.875rem; line-height: 1.6; white-space: pre-line; margin-bottom: 1rem; padding-right: 3rem;">
                            {{ complaint.description }}
                        </p>

                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                            <span>{{ school.fmtDate(complaint.created_at) }}</span>
                            <span v-if="complaint.assigned_department_id" class="badge badge-blue">
                                Dept: {{ complaint.assigned_department?.name || 'Assigned' }}
                            </span>
                        </div>

                        <div v-if="complaint.resolution_notes" style="margin-top: 1rem; background: #ecfdf5; border-left: 4px solid #10b981; padding: 0.75rem 1rem; border-radius: 0 0.375rem 0.375rem 0; font-size: 0.875rem; color: #065f46;">
                            <strong>Resolution Note:</strong> {{ complaint.resolution_notes }}
                        </div>
                    </div>

                    <!-- Actions Panel -->
                    <div style="width: 14rem; padding: 1rem; background: var(--surface-muted); border-radius: 0.75rem; border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; text-align: center;">
                        <span style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Status</span>

                        <span v-if="complaint.status === 'Open'" class="badge badge-red">Open &amp; Unresolved</span>
                        <span v-else-if="complaint.status === 'In Progress'" class="badge badge-amber">In Progress</span>
                        <span v-else-if="complaint.status === 'Resolved'" class="badge badge-green">Resolved</span>
                        <span v-else class="badge badge-gray">Archived / Closed</span>

                        <div style="display: flex; flex-direction: column; gap: 0.5rem; width: 100%; margin-top: 0.75rem;">
                            <Button variant="secondary" size="sm" v-if="complaint.status === 'Open'" @click="updateStatus(complaint, 'In Progress')" block>
                                Start Progress
                            </Button>
                            <Button variant="success" size="sm" v-if="complaint.status === 'Open' || complaint.status === 'In Progress'" @click="updateStatus(complaint, 'Resolved')" block>
                                Mark Resolved
                            </Button>
                            <Button variant="secondary" size="sm" v-if="complaint.status === 'Resolved'" @click="updateStatus(complaint, 'Closed')" block>
                                Close Case
                            </Button>
                            <Button variant="danger" size="xs" v-if="complaint.status === 'Resolved' || complaint.status === 'Closed'" @click="updateStatus(complaint, 'Open')" block class="mt-2">
                                Re-open Grievance
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resolution-notes modal (replaces native prompt) -->
        <Modal v-model:open="notesOpen" :title="`${notesContext.status} — resolution notes`" size="md">
            <div class="form-field">
                <label>Notes</label>
                <textarea v-model="notesValue" rows="4" placeholder="Describe what was done…"
                          class="form-input"
                          style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:0.85rem;"></textarea>
            </div>
            <template #footer>
                <Button variant="secondary" @click="notesOpen = false">Cancel</Button>
                <Button @click="submitNotes">Save &amp; mark {{ notesContext.status }}</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

