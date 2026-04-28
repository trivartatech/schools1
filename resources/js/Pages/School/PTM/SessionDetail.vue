<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
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
        <div class="page-header">
            <div>
                <Link href="/school/ptm" style="font-size:.8rem;color:#94a3b8;">← Back to Sessions</Link>
                <h1 class="page-header-title" style="margin-top:4px;">{{ session.title }}</h1>
                <div style="font-size:.85rem;color:#64748b;">{{ fmt(session.date) }} · {{ session.start_time?.slice(0,5) }}–{{ session.end_time?.slice(0,5) }}</div>
            </div>
            <span class="badge" :class="{ 'badge-gray': session.status === 'draft', 'badge-green': session.status === 'open', 'badge-amber': session.status === 'closed' }" style="font-size:.9rem;padding:6px 14px;">{{ session.status }}</span>
        </div>

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
        <Teleport to="body">
            <div v-if="showNotes" class="modal-backdrop" @click.self="showNotes = null">
                <div class="modal" style="max-width:420px;width:100%;">
                    <div class="modal-header">
                        <h3 class="modal-title">Meeting Notes</h3>
                        <button @click="showNotes = null" class="modal-close">&times;</button>
                    </div>
                    <form @submit.prevent="saveNotes">
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
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
                        </div>
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="showNotes = null">Cancel</Button>
                            <Button type="submit" :loading="notesForm.processing">Save Notes</Button>
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
