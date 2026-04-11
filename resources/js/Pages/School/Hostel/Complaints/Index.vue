<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    complaints: Object, hostels: Array, students: Array, staff: Array, filters: Object,
});

const showModal = ref(false);
const editItem  = ref(null);
const showResolve = ref(false);
const resolveItem = ref(null);

const form = reactive({
    hostel_id: '', student_id: '', category: 'maintenance', title: '', description: '',
    location: '', priority: 'medium', assigned_to: '',
});

const resolveForm = reactive({ status: '', assigned_to: '', priority: '', resolution_notes: '' });

const categories = [
    { key: 'maintenance', label: 'Maintenance' }, { key: 'electrical', label: 'Electrical' },
    { key: 'plumbing', label: 'Plumbing' }, { key: 'furniture', label: 'Furniture' },
    { key: 'cleanliness', label: 'Cleanliness' }, { key: 'pest_control', label: 'Pest Control' },
    { key: 'other', label: 'Other' },
];

const priorities = [
    { key: 'low', label: 'Low', color: '#22c55e' }, { key: 'medium', label: 'Medium', color: '#f59e0b' },
    { key: 'high', label: 'High', color: '#f97316' }, { key: 'urgent', label: 'Urgent', color: '#ef4444' },
];

const statuses = ['all', 'open', 'in_progress', 'resolved', 'closed', 'rejected'];

const statusColor = (s) => ({ open:'#3b82f6', in_progress:'#f59e0b', resolved:'#22c55e', closed:'#64748b', rejected:'#ef4444' })[s] || '#94a3b8';
const prioColor = (p) => priorities.find(x => x.key === p)?.color || '#94a3b8';

const filterStatus = ref(props.filters?.status || 'all');
const filterHostel = ref(props.filters?.hostel_id || '');

const applyFilter = () => {
    router.get(route('school.hostel.complaints.index'), {
        status: filterStatus.value, hostel_id: filterHostel.value
    }, { preserveState: true, replace: true });
};

const openNew = () => {
    Object.assign(form, { hostel_id: '', student_id: '', category: 'maintenance', title: '', description: '', location: '', priority: 'medium', assigned_to: '' });
    showModal.value = true;
};

const submit = () => {
    router.post(route('school.hostel.complaints.store'), form, {
        preserveScroll: true, onSuccess: () => showModal.value = false,
    });
};

const openResolve = (c) => {
    resolveItem.value = c;
    Object.assign(resolveForm, { status: c.status, assigned_to: c.assigned_to || '', priority: c.priority, resolution_notes: c.resolution_notes || '' });
    showResolve.value = true;
};

const submitResolve = () => {
    router.put(route('school.hostel.complaints.update', resolveItem.value.id), resolveForm, {
        preserveScroll: true, onSuccess: () => showResolve.value = false,
    });
};

const del = (c) => {
    if (!confirm('Delete this complaint?')) return;
    router.delete(route('school.hostel.complaints.destroy', c.id), { preserveScroll: true });
};

const studentName = (s) => s ? `${s.first_name} ${s.last_name}` : '—';
const timeAgo = (d) => {
    const diff = Math.floor((Date.now() - new Date(d).getTime()) / 60000);
    if (diff < 60) return `${diff}m ago`;
    if (diff < 1440) return `${Math.floor(diff/60)}h ago`;
    return `${Math.floor(diff/1440)}d ago`;
};
</script>

<template>
<SchoolLayout title="Hostel Complaints">
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Complaints & Maintenance</h1>
            <p class="page-header-sub">Track and resolve hostel issues</p>
        </div>
        <Button @click="openNew">+ New Complaint</Button>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-field" style="min-width:140px;">
                <label>Status</label>
                <select v-model="filterStatus" @change="applyFilter">
                    <option v-for="s in statuses" :key="s" :value="s">{{ s === 'all' ? 'All' : s.replace('_',' ') }}</option>
                </select>
            </div>
            <div class="form-field" style="min-width:160px;">
                <label>Hostel</label>
                <select v-model="filterHostel" @change="applyFilter">
                    <option value="">All Hostels</option>
                    <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                </select>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="card" v-if="complaints.data.length">
        <div style="overflow-x:auto;">
            <Table>
                <thead><tr>
                    <th>Complaint</th>
                    <th>Category</th>
                    <th>Hostel</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Reported</th>
                    <th style="width:120px;">Actions</th>
                </tr></thead>
                <tbody>
                    <tr v-for="c in complaints.data" :key="c.id">
                        <td>
                            <div style="font-weight:600;color:#1e293b;">{{ c.title }}</div>
                            <div style="font-size:.72rem;color:#94a3b8;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ c.description }}</div>
                            <div v-if="c.location" style="font-size:.7rem;color:#64748b;">{{ c.location }}</div>
                        </td>
                        <td><span class="badge badge-outline">{{ c.category.replace('_',' ') }}</span></td>
                        <td style="font-size:.82rem;">{{ c.hostel?.name }}</td>
                        <td><span class="prio-badge" :style="{background: prioColor(c.priority) + '18', color: prioColor(c.priority), borderColor: prioColor(c.priority)}">{{ c.priority }}</span></td>
                        <td><span class="status-badge" :style="{background: statusColor(c.status) + '18', color: statusColor(c.status)}">{{ c.status.replace('_',' ') }}</span></td>
                        <td style="font-size:.78rem;color:#64748b;">
                            {{ c.reporter?.name }}<br>
                            <span style="font-size:.7rem;">{{ timeAgo(c.created_at) }}</span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <Button variant="secondary" size="sm" @click="openResolve(c)">Update</Button>
                                <Button variant="danger" size="sm" @click="del(c)">Del</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>
    </div>
    <div v-else class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
        No complaints found.
    </div>

    <!-- New Complaint Modal -->
    <teleport to="body">
        <div v-if="showModal" class="modal-overlay" @click.self="showModal=false">
            <div class="modal-box" style="max-width:560px;">
                <h3 class="modal-title">New Complaint</h3>
                <div class="form-row form-row-2">
                    <div class="form-field">
                        <label>Hostel *</label>
                        <select v-model="form.hostel_id" required>
                            <option value="">Select</option>
                            <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Student (optional)</label>
                        <select v-model="form.student_id">
                            <option value="">N/A</option>
                            <option v-for="s in students" :key="s.id" :value="s.id">{{ s.first_name }} {{ s.last_name }}</option>
                        </select>
                    </div>
                </div>
                <div class="form-row form-row-2">
                    <div class="form-field">
                        <label>Category *</label>
                        <select v-model="form.category">
                            <option v-for="c in categories" :key="c.key" :value="c.key">{{ c.label }}</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Priority *</label>
                        <select v-model="form.priority">
                            <option v-for="p in priorities" :key="p.key" :value="p.key">{{ p.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="form-field"><label>Title *</label><input v-model="form.title" required /></div>
                <div class="form-field"><label>Description *</label><textarea v-model="form.description" rows="3" required></textarea></div>
                <div class="form-row form-row-2">
                    <div class="form-field"><label>Location</label><input v-model="form.location" placeholder="e.g. Room 201, Block A" /></div>
                    <div class="form-field">
                        <label>Assign To</label>
                        <select v-model="form.assigned_to">
                            <option value="">Unassigned</option>
                            <option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <Button variant="secondary" @click="showModal=false">Cancel</Button>
                    <Button @click="submit" :disabled="!form.hostel_id || !form.title || !form.description">Submit</Button>
                </div>
            </div>
        </div>
    </teleport>

    <!-- Update/Resolve Modal -->
    <teleport to="body">
        <div v-if="showResolve" class="modal-overlay" @click.self="showResolve=false">
            <div class="modal-box" style="max-width:480px;">
                <h3 class="modal-title">Update Complaint</h3>
                <div class="form-row form-row-2">
                    <div class="form-field">
                        <label>Status *</label>
                        <select v-model="resolveForm.status">
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Priority</label>
                        <select v-model="resolveForm.priority">
                            <option v-for="p in priorities" :key="p.key" :value="p.key">{{ p.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="form-field">
                    <label>Assign To</label>
                    <select v-model="resolveForm.assigned_to">
                        <option value="">Unassigned</option>
                        <option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="form-field"><label>Resolution Notes</label><textarea v-model="resolveForm.resolution_notes" rows="3" placeholder="Describe the resolution..."></textarea></div>
                <div class="modal-actions">
                    <Button variant="secondary" @click="showResolve=false">Cancel</Button>
                    <Button @click="submitResolve">Save</Button>
                </div>
            </div>
        </div>
    </teleport>
</SchoolLayout>
</template>

<style scoped>
.prio-badge, .status-badge { display:inline-block; padding:3px 10px; border-radius:6px; font-size:.72rem; font-weight:600; text-transform:capitalize; }
.prio-badge { border:1px solid; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1000; display:flex; align-items:center; justify-content:center; }
.modal-box { background:#fff; border-radius:12px; padding:24px; width:90%; box-shadow:0 8px 30px rgba(0,0,0,.15); }
.modal-title { font-size:1.1rem; font-weight:700; margin-bottom:16px; color:#1e293b; }
.modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:16px; }
</style>
