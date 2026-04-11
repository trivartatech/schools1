<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import { useDelete } from '@/Composables/useDelete';
import Table from '@/Components/ui/Table.vue';

const props = defineProps(['holidays']);
const typeColors = { holiday: 'bg-red-100 text-red-800', event: 'bg-blue-100 text-blue-800', exam: 'bg-purple-100 text-purple-800', other: 'bg-gray-100 text-gray-700' };
const typeIcons = { holiday: '🎉', event: '🎪', exam: '📝', other: '📌' };

const panelOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const form = useForm({ title: '', date: '', end_date: '', type: 'holiday', description: '' });

const openCreate = () => { isEditing.value = false; form.reset(); panelOpen.value = true; };
const openEdit = (h) => {
    isEditing.value = true; editingId.value = h.id;
    form.title = h.title; form.date = h.date ? h.date.split('T')[0] : '';
    form.end_date = h.end_date ? h.end_date.split('T')[0] : ''; form.type = h.type; form.description = h.description || '';
    panelOpen.value = true;
};
const closePanel = () => { panelOpen.value = false; form.reset(); };
const submit = () => {
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/holidays/${editingId.value}`, { onSuccess: () => closePanel(), onError: (e) => form.setError(e) });
    } else {
        form.transform((data) => data).post('/school/holidays', { onSuccess: () => closePanel() });
    }
};
const { del } = useDelete();
const destroy = (id) => {
    if (!id) return;
    del(`/school/holidays/${id}`, 'Delete this entry?');
};
const fmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
</script>

<template>
    <SchoolLayout title="Holidays & Events">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Holidays &amp; Events Calendar</h2>
                <p class="page-header-sub">Track school holidays, events, and exam days.</p>
            </div>
            <Button variant="success" @click="openCreate">+ Add Entry</Button>
        </div>

        <div class="card">
            <Table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>End Date</th>
                        <th>Type</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="h in holidays" :key="h.id">
                        <td style="font-weight:500;color:var(--text-primary);">{{ typeIcons[h.type] }} {{ h.title }}</td>
                        <td style="color:var(--text-secondary);">{{ fmt(h.date) }}</td>
                        <td style="color:var(--text-secondary);">{{ h.end_date ? fmt(h.end_date) : '—' }}</td>
                        <td>
                            <span
                                :class="{
                                    'badge badge-red':    h.type === 'holiday',
                                    'badge badge-blue':   h.type === 'event',
                                    'badge badge-purple': h.type === 'exam',
                                    'badge badge-gray':   h.type === 'other'
                                }"
                                style="text-transform:capitalize;">{{ h.type }}</span>
                        </td>
                        <td style="text-align:right;">
                            <Button variant="secondary" size="sm" @click="openEdit(h)">Edit</Button>
                            <Button variant="danger" size="sm" @click="destroy(h.id)" class="ml-2">Delete</Button>
                        </td>
                    </tr>
                    <tr v-if="holidays.length === 0">
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No holidays or events added yet.</td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Entry' : 'Add Holiday / Event'" @close="closePanel">
            <form @submit.prevent="submit">
                <div class="form-field">
                    <label>Title <span style="color:var(--danger);">*</span></label>
                    <input v-model="form.title" type="text" required />
                </div>
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Date <span style="color:var(--danger);">*</span></label>
                        <input v-model="form.date" type="date" required />
                    </div>
                    <div class="form-field">
                        <label>End Date (optional)</label>
                        <input v-model="form.end_date" type="date" />
                    </div>
                </div>
                <div class="form-field">
                    <label>Type</label>
                    <select v-model="form.type">
                        <option value="holiday">Holiday</option>
                        <option value="event">Event</option>
                        <option value="exam">Exam</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Description (optional)</label>
                    <textarea v-model="form.description" rows="3"></textarea>
                </div>
                <div style="display:flex;gap:0.75rem;padding-top:0.5rem;">
                    <Button variant="success" type="submit" :loading="form.processing" class="flex-1">Save</Button>
                    <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                </div>
            </form>
        </SlidePanel>
    </SchoolLayout>
</template>
