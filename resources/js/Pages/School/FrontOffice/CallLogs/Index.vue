<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    callLogs: { type: Array, default: () => [] },
    staffs: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
});

const form = useForm({
    caller_name: '',
    phone_number: '',
    call_type: 'Incoming',
    purpose: 'Enquiry',
    handled_by_id: '',
    related_student_id: '',
    notes: '',
    follow_up_date: '',
});

const showForm = ref(false);
const filterDate = ref('');

const submit = () => {
    form.post('/school/front-office/call-logs', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        }
    });
};

const toggleFollowUp = (log) => {
    router.put(`/school/front-office/call-logs/${log.id}`, { follow_up_completed: !log.follow_up_completed }, { preserveScroll: true });
};

const deleteLog = (id) => {
    if (confirm('Are you sure you want to delete this log?')) {
        router.delete(`/school/front-office/call-logs/${id}`, { preserveScroll: true });
    }
};

const filteredLogs = computed(() => {
    if (!filterDate.value) return props.callLogs;
    return props.callLogs.filter(log => new Date(log.created_at).toISOString().split('T')[0] === filterDate.value);
});
</script>

<template>
    <SchoolLayout title="Call Logs">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Call Register</h1>
                <p class="page-header-sub">Track incoming and outgoing communication and follow-ups.</p>
            </div>
            <Button @click="showForm = !showForm">
                {{ showForm ? 'Close Directory' : '+ Record New Call' }}
            </Button>
        </div>

        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-[-20px] opacity-0"
                    enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-[-20px]">
        <div v-show="showForm" class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Record New Call</h2>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="form-row-2">
                        <div class="form-field">
                            <label>Call Direction</label>
                            <select v-model="form.call_type" required>
                                <option value="Incoming">Incoming Call</option>
                                <option value="Outgoing">Outgoing Call</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Subject / Purpose</label>
                            <select v-model="form.purpose" required>
                                <option value="Enquiry">General Enquiry</option>
                                <option value="Admission">Admission Enquiry</option>
                                <option value="Complaint">Complaint</option>
                                <option value="Follow-up">Follow-up Call</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Caller / Recipient Name</label>
                            <input v-model="form.caller_name" type="text" placeholder="John Doe" required>
                        </div>
                        <div class="form-field">
                            <label>Phone Number</label>
                            <input v-model="form.phone_number" type="text" placeholder="+91 xxxxxxxxxx" required>
                        </div>
                        <div class="form-field">
                            <label>Assigned Staff (Handled By)</label>
                            <select v-model="form.handled_by_id">
                                <option value="">-- Receptionist / Self --</option>
                                <option v-for="staff in staffs" :key="staff.id" :value="staff.id">{{ staff.user?.name || staff.first_name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Related Student</label>
                            <select v-model="form.related_student_id">
                                <option value="">-- None / N.A --</option>
                                <option v-for="student in students" :key="student.id" :value="student.id">{{ student.first_name }} {{ student.last_name }} ({{ student.admission_no }})</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row" style="margin-top: 1rem;">
                        <div class="form-field">
                            <label>Conversation Notes</label>
                            <textarea v-model="form.notes" rows="2" placeholder="Key points discussed..."></textarea>
                        </div>
                    </div>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--surface-muted); border: 1px solid var(--border); border-radius: 0.5rem;">
                        <p class="section-heading" style="margin-bottom: 0.75rem;">Follow-Up Actions Required?</p>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Next Follow-Up Date</label>
                                <input v-model="form.follow_up_date" type="date">
                            </div>
                            <div class="form-field" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                                Leaving date blank implies no follow-up is necessary.
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                        <Button type="submit" :loading="form.processing">Save Communication Log</Button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- COMMUNICATION TIMELINE -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h3 class="card-title">Communication Timeline</h3>
                <div class="filter-date">
                    <span class="filter-date-label">Filter By Date:</span>
                    <input v-model="filterDate" type="date" class="filter-date-input">
                </div>
            </div>

            <div>
                <div v-if="filteredLogs.length === 0" class="timeline-empty">
                    No communication records available for this query.
                </div>

                <div v-for="log in filteredLogs" :key="log.id" class="timeline-item">

                    <!-- Direction Indicator -->
                    <div class="timeline-icon" :class="log.call_type === 'Incoming' ? 'timeline-icon--in' : 'timeline-icon--out'">
                        <svg v-if="log.call_type === 'Incoming'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    </div>

                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-caller">{{ log.caller_name }}</span>
                            <a :href="`tel:${log.phone_number}`" class="badge badge-blue" style="font-family:monospace;">{{ log.phone_number }}</a>
                            <span class="badge badge-gray">{{ log.purpose }}</span>
                            <span class="timeline-date">
                                {{ new Date(log.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }}
                            </span>
                        </div>

                        <p class="timeline-notes">{{ log.notes || 'No summary provided.' }}</p>

                        <div class="timeline-meta">
                            <span v-if="log.handled_by_id">
                                Handled By: <span style="color:var(--text-primary);">{{ log.handled_by?.user?.name || log.handled_by?.first_name }}</span>
                            </span>
                            <span v-if="log.related_student_id" class="badge badge-blue">
                                Student: {{ log.related_student?.first_name }} {{ log.related_student?.last_name }}
                            </span>

                            <!-- Follow-up Block -->
                            <div v-if="log.follow_up_date" class="followup-badge" :class="log.follow_up_completed ? 'followup--done' : 'followup--pending'">
                                <svg v-if="log.follow_up_completed" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Due: {{ new Date(log.follow_up_date).toLocaleDateString() }}</span>
                                <button @click="toggleFollowUp(log)" class="followup-btn">
                                    {{ log.follow_up_completed ? 'Re-open' : 'Mark Done' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <Button variant="danger" size="xs" @click="deleteLog(log.id)" class="timeline-delete">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </Button>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* Filter date */
.filter-date { display: flex; align-items: center; gap: .5rem; }
.filter-date-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--text-muted); }
.filter-date-input { height: 2rem; font-size: .8rem; padding: 0 .5rem; }

/* Timeline */
.timeline-empty { padding: 2.5rem; text-align: center; color: var(--text-muted); }
.timeline-item { padding: 1.25rem 1.5rem; display: flex; gap: 1.25rem; border-bottom: 1px solid var(--border); position: relative; }
.timeline-icon { flex-shrink: 0; width: 2.5rem; height: 2.5rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; color: white; }
.timeline-icon--in { background: var(--success); }
.timeline-icon--out { background: #3b82f6; }
.timeline-content { flex: 1; min-width: 0; }
.timeline-header { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .25rem; }
.timeline-caller { font-weight: 700; font-size: 1rem; }
.timeline-date { font-size: .75rem; color: var(--text-muted); margin-left: auto; }
.timeline-notes { font-size: .875rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: .75rem; }
.timeline-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; font-size: .75rem; font-weight: 600; color: var(--text-muted); }
.timeline-delete { position: absolute; top: 1rem; right: 1rem; }

/* Follow-up badge */
.followup-badge { margin-left: auto; display: flex; align-items: center; gap: .5rem; padding: .375rem .75rem; border-radius: .5rem; border: 1px solid; font-size: .75rem; }
.followup--done { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.followup--pending { background: #fff7ed; border-color: #fdba74; color: #9a3412; }
.followup-btn { margin-left: .5rem; font-weight: 700; background: none; border: none; cursor: pointer; text-decoration: underline; color: inherit; }
</style>
