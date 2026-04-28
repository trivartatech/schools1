<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useFormat } from '@/Composables/useFormat';

const { formatDate: fmt } = useFormat();

const props = defineProps({ session: Object });

const slots = computed(() => props.session.slots ?? []);

// Group slots by teacher
const byTeacher = computed(() => {
    const map = {};
    slots.value.forEach(s => {
        const key = s.staff_id;
        if (!map[key]) map[key] = { staff: s.staff, slots: [] };
        map[key].slots.push(s);
    });
    return Object.values(map);
});

const notesForm = useForm({ meeting_notes: '', status: 'completed' });
const showNotes  = ref(null);
const showNotesModal = computed({
    get: () => showNotes.value !== null,
    set: (v) => { if (!v) showNotes.value = null; },
});

const openNotes = (booking) => {
    showNotes.value = booking;
    notesForm.meeting_notes = booking.meeting_notes ?? '';
    notesForm.status = booking.status === 'completed' ? 'completed' : 'no_show';
};

const saveNotes = () => {
    notesForm.patch(`/school/ptm/bookings/${showNotes.value.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => { showNotes.value = null; },
    });
};

const statusBadge = { booked: 'badge-amber', completed: 'badge-green', cancelled: 'badge-gray', no_show: 'badge-red' };
</script>

<template>
    <SchoolLayout :title="`PTM — ${session.title}`">
        <PageHeader
            :title="session.title"
            back-href="/school/ptm"
            back-label="Back to Sessions"
        >
            <template #subtitle>
                <div style="font-size:.85rem;color:#64748b;">{{ fmt(session.date) }} · {{ session.start_time?.slice(0,5) }}–{{ session.end_time?.slice(0,5) }}</div>
            </template>
            <template #actions>
                <span class="badge" :class="{ 'badge-gray': session.status === 'draft', 'badge-green': session.status === 'open', 'badge-amber': session.status === 'closed' }" style="font-size:.9rem;padding:6px 14px;">{{ session.status }}</span>
            </template>
        </PageHeader>

        <!-- Teacher-wise slot grids -->
        <div v-for="teacher in byTeacher" :key="teacher.staff?.id" style="margin-bottom:24px;">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">{{ teacher.staff?.user?.name }}</span>
                    <span style="font-size:.8rem;color:#94a3b8;">{{ teacher.slots.filter(s=>s.is_booked).length }} / {{ teacher.slots.length }} booked</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;padding:16px;">
                    <div v-for="slot in teacher.slots" :key="slot.id"
                         :style="{ background: slot.is_booked ? '#f0fdf4' : '#f8fafc', border: `1px solid ${slot.is_booked ? '#86efac' : '#e2e8f0'}`, borderRadius: '8px', padding: '12px' }">
                        <div style="font-weight:600;font-size:.9rem;margin-bottom:4px;">{{ slot.slot_time?.slice(0,5) }}</div>
                        <div v-if="slot.booking">
                            <div style="font-size:.8rem;font-weight:500;">{{ slot.booking.student?.first_name }} {{ slot.booking.student?.last_name }}</div>
                            <span class="badge" :class="statusBadge[slot.booking.status]" style="font-size:.7rem;margin-top:4px;">{{ slot.booking.status }}</span>
                            <Button v-if="slot.booking.status === 'booked'" size="xs" variant="secondary" style="margin-top:6px;" @click="openNotes(slot.booking)">Add Notes</Button>
                            <div v-if="slot.booking.meeting_notes" style="font-size:.7rem;color:#64748b;margin-top:4px;font-style:italic;">{{ slot.booking.meeting_notes.slice(0,60) }}...</div>
                        </div>
                        <div v-else style="font-size:.75rem;color:#94a3b8;">Available</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!byTeacher.length" style="text-align:center;padding:40px;color:#94a3b8;">
            No slots generated for this session yet.
        </div>

        <!-- Notes Modal -->
        <Modal v-model:open="showNotesModal" title="Meeting Notes" size="sm">
            <form @submit.prevent="saveNotes" id="ptm-notes-form" style="display:flex;flex-direction:column;gap:14px;">
                <div class="form-field">
                    <label>Outcome *</label>
                    <select v-model="notesForm.status" required>
                        <option value="completed">Completed</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Meeting Notes *</label>
                    <textarea v-model="notesForm.meeting_notes" rows="4" required placeholder="Summary of the meeting..."></textarea>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="showNotes = null">Cancel</Button>
                <Button type="submit" form="ptm-notes-form" :loading="notesForm.processing">Save Notes</Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
</style>
