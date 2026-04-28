<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ sessions: Array });

const showCreate = ref(false);

const form = useForm({
    title: '', date: '', start_time: '09:00', end_time: '13:00',
    slot_duration_minutes: 15, description: '', staff_ids: [],
});

const submit = () => {
    form.post('/school/ptm', { preserveScroll: true, onSuccess: () => { showCreate.value = false; form.reset(); } });
};

const updateStatus = (id, status) => {
    router.patch(`/school/ptm/${id}/status`, { status }, { preserveScroll: true });
};

import { useFormat } from '@/Composables/useFormat';
const { formatDate: fmt } = useFormat();

const statusConfig = {
    draft:  { label: 'Draft',  color: '#64748b', bg: '#f1f5f9', dot: '#94a3b8' },
    open:   { label: 'Open',   color: '#059669', bg: '#d1fae5', dot: '#10b981' },
    closed: { label: 'Closed', color: '#b45309', bg: '#fef3c7', dot: '#f59e0b' },
};

const stats = computed(() => ({
    total:  props.sessions?.length ?? 0,
    open:   props.sessions?.filter(s => s.status === 'open').length ?? 0,
    totalBookings: props.sessions?.reduce((a, s) => a + (s.bookings_count ?? 0), 0) ?? 0,
}));

const statCards = computed(() => [
    { label: 'Total Sessions',   value: stats.value.total,         color: 'purple' },
    { label: 'Open for Booking', value: stats.value.open,          color: 'success' },
    { label: 'Total Bookings',   value: stats.value.totalBookings, color: 'accent' },
]);
</script>

<template>
    <SchoolLayout title="Parent-Teacher Meetings">

        <!-- Header -->
        <PageHeader title="Parent-Teacher Meetings" subtitle="Schedule sessions, manage slots and track bookings">
            <template #actions>
                <Button @click="showCreate = true">+ New Session</Button>
            </template>
        </PageHeader>

        <!-- KPI strip -->
        <StatsRow :cols="3" :stats="statCards" />

        <!-- Session Cards -->
        <div v-if="sessions?.length" style="display:flex;flex-direction:column;gap:12px;">
            <div v-for="s in sessions" :key="s.id" class="card ptm-card">
                <div class="card-body" style="padding:20px 24px;">
                    <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">

                        <!-- Date badge -->
                        <div style="flex-shrink:0;width:52px;text-align:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:8px 4px;line-height:1.2;">
                            <div style="font-size:.65rem;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:.05em;">
                                {{ new Date(s.date).toLocaleDateString('en-IN',{month:'short'}) }}
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#1e293b;">
                                {{ new Date(s.date).getDate() }}
                            </div>
                            <div style="font-size:.6rem;color:#94a3b8;">
                                {{ new Date(s.date).toLocaleDateString('en-IN',{weekday:'short'}) }}
                            </div>
                        </div>

                        <!-- Main info -->
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                <Link :href="`/school/ptm/${s.id}`"
                                      style="font-size:1rem;font-weight:700;color:#1e293b;text-decoration:none;">
                                    {{ s.title }}
                                </Link>
                                <span :style="{
                                    display:'inline-flex', alignItems:'center', gap:'5px',
                                    padding:'2px 10px', borderRadius:'20px', fontSize:'.72rem', fontWeight:700,
                                    background: statusConfig[s.status]?.bg,
                                    color: statusConfig[s.status]?.color,
                                }">
                                    <span :style="{ width:'6px',height:'6px',borderRadius:'50%',background:statusConfig[s.status]?.dot,display:'inline-block' }"></span>
                                    {{ statusConfig[s.status]?.label }}
                                </span>
                            </div>

                            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                                <div style="display:flex;align-items:center;gap:5px;font-size:.8rem;color:#475569;">
                                    {{ s.start_time?.slice(0,5) }} – {{ s.end_time?.slice(0,5) }}
                                </div>
                                <div style="display:flex;align-items:center;gap:5px;font-size:.8rem;color:#475569;">
                                    {{ s.slot_duration_minutes }} min slots
                                </div>
                                <div style="display:flex;align-items:center;gap:5px;font-size:.8rem;color:#475569;">
                                    {{ s.bookings_count ?? 0 }} / {{ s.slots_count ?? 0 }} booked
                                </div>
                            </div>

                            <!-- Booking progress bar -->
                            <div v-if="s.slots_count > 0" style="margin-top:10px;">
                                <div style="height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                                    <div :style="{
                                        height:'100%', borderRadius:'3px', transition:'width .3s',
                                        background: s.status === 'open' ? '#6366f1' : '#94a3b8',
                                        width: Math.round((s.bookings_count / s.slots_count) * 100) + '%'
                                    }"></div>
                                </div>
                                <div style="font-size:.68rem;color:#94a3b8;margin-top:3px;">
                                    {{ Math.round((s.bookings_count / s.slots_count) * 100) }}% slots filled
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
                            <Button v-if="s.status === 'draft'" variant="success" size="sm" @click="updateStatus(s.id, 'open')">
                                Open Bookings
                            </Button>
                            <Button v-if="s.status === 'open'" variant="warning" size="sm" @click="updateStatus(s.id, 'closed')">
                                Close
                            </Button>
                            <Button variant="secondary" size="sm" as="link" :href="`/school/ptm/${s.id}`">
                                View Details →
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-else
            title="No PTM Sessions Yet"
            description="Create your first parent-teacher meeting session to get started."
            action-label="+ Create First Session"
            @action="showCreate = true"
        />

        <!-- Create Modal -->
        <Modal v-model:open="showCreate" title="New PTM Session" size="md">
            <form @submit.prevent="submit" id="ptm-form">
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="form-field">
                        <label>Session Title *</label>
                        <input v-model="form.title" required placeholder="e.g. Mid-Term PTM 2026" />
                        <span v-if="form.errors.title" class="form-error">{{ form.errors.title }}</span>
                    </div>
                    <div class="form-field">
                        <label>Date *</label>
                        <input v-model="form.date" type="date" required />
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div class="form-field" style="margin:0;">
                            <label>Start Time *</label>
                            <input v-model="form.start_time" type="time" required />
                        </div>
                        <div class="form-field" style="margin:0;">
                            <label>End Time *</label>
                            <input v-model="form.end_time" type="time" required />
                        </div>
                        <div class="form-field" style="margin:0;">
                            <label>Slot (min) *</label>
                            <input v-model="form.slot_duration_minutes" type="number" min="5" max="60" required />
                        </div>
                    </div>
                    <div class="form-field">
                        <label>Description</label>
                        <textarea v-model="form.description" rows="2" placeholder="Optional notes for parents..."></textarea>
                    </div>
                    <div style="font-size:.78rem;color:#6366f1;background:#eef2ff;padding:10px 14px;border-radius:8px;border-left:3px solid #6366f1;">
                        After creating the session, open it and add teacher slots from the detail page.
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showCreate = false">Cancel</Button>
                <Button type="submit" form="ptm-form" :loading="form.processing">Create Session</Button>
            </template>
        </Modal>

    </SchoolLayout>
</template>

<style scoped>
.ptm-card { transition: box-shadow .15s, transform .15s; }
.ptm-card:hover { box-shadow: 0 4px 20px rgba(99,102,241,.1); transform: translateY(-1px); }
.form-error { font-size:.75rem;color:#ef4444;margin-top:3px;display:block; }

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
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
</style>
