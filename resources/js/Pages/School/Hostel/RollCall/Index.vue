<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';

const props = defineProps({
    hostels: Array, students: Array, attendance: Object, stats: Object,
    date: String, hostelId: Number, slot: String,
});

const selHostel = ref(props.hostelId || '');
const selDate   = ref(props.date);
const selSlot   = ref(props.slot || 'night');
const search    = ref('');
const saving    = ref(false);

const records = ref({});

// Initialize from existing attendance
if (props.students?.length) {
    props.students.forEach(s => {
        const existing = props.attendance?.[s.id];
        records.value[s.id] = {
            status:  existing?.status || '',
            remarks: existing?.remarks || '',
        };
    });
}

const filtered = computed(() => {
    if (!search.value) return props.students;
    const q = search.value.toLowerCase();
    return props.students.filter(s =>
        `${s.first_name} ${s.last_name}`.toLowerCase().includes(q) ||
        s.admission_no?.toLowerCase().includes(q)
    );
});

const apply = () => {
    router.get(route('school.hostel.roll-call.index'), {
        hostel_id: selHostel.value, date: selDate.value, slot: selSlot.value
    }, { preserveState: true, replace: true });
};

const markAll = (status) => {
    props.students.forEach(s => { records.value[s.id].status = status; });
};

const markedCount = computed(() => Object.values(records.value).filter(r => r.status).length);

const save = () => {
    saving.value = true;
    const payload = {
        hostel_id: selHostel.value,
        date: selDate.value,
        slot: selSlot.value,
        records: props.students.filter(s => records.value[s.id]?.status).map(s => ({
            student_id: s.id,
            status:     records.value[s.id].status,
            remarks:    records.value[s.id].remarks || null,
        })),
    };
    router.post(route('school.hostel.roll-call.store'), payload, {
        preserveScroll: true,
        onFinish: () => saving.value = false,
    });
};

const statusBtns = [
    { key: 'present', label: 'P', color: '#22c55e' },
    { key: 'absent',  label: 'A', color: '#ef4444' },
    { key: 'leave',   label: 'LV', color: '#3b82f6' },
    { key: 'medical', label: 'M', color: '#f97316' },
];
</script>

<template>
<SchoolLayout title="Hostel Roll Call">
    <PageHeader title="Hostel Roll Call" subtitle="Nightly / morning bed check for hostel residents">
        <template #actions>
            <Button variant="secondary" as="a" :href="route('school.hostel.roll-call.report')">Monthly Report</Button>
        </template>
    </PageHeader>

    <!-- Filters -->
    <FilterBar :active="false">
        <div class="form-field">
            <label>Hostel</label>
            <select v-model="selHostel" @change="apply" style="width:200px;">
                <option value="">Select Hostel</option>
                <option v-for="h in hostels" :key="h.id" :value="h.id">{{ h.name }} ({{ h.type }})</option>
            </select>
        </div>
        <div class="form-field">
            <label>Date</label>
            <input type="date" v-model="selDate" @change="apply" style="width:160px;" />
        </div>
        <div class="form-field">
            <label>Slot</label>
            <select v-model="selSlot" @change="apply" style="width:160px;">
                <option value="night">Night Check</option>
                <option value="morning">Morning Check</option>
            </select>
        </div>
    </FilterBar>

    <template v-if="selHostel && students.length > 0">
        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-label">Total</div><div class="stat-value">{{ stats.total }}</div></div>
            <div class="stat-card stat-green"><div class="stat-label">Present</div><div class="stat-value">{{ stats.present }}</div></div>
            <div class="stat-card stat-red"><div class="stat-label">Absent</div><div class="stat-value">{{ stats.absent }}</div></div>
            <div class="stat-card stat-blue"><div class="stat-label">Leave</div><div class="stat-value">{{ stats.leave }}</div></div>
            <div class="stat-card stat-amber"><div class="stat-label">Medical</div><div class="stat-value">{{ stats.medical }}</div></div>
        </div>

        <div class="card">
            <div class="toolbar">
                <input v-model="search" placeholder="Search student..." class="search-input" />
                <div style="margin-left:auto;display:flex;gap:6px;">
                    <button v-for="b in statusBtns" :key="b.key" @click="markAll(b.key)" class="mark-all-btn" :style="{background: b.color}">
                        All {{ b.label }}
                    </button>
                </div>
            </div>

            <Table>
                <thead><tr>
                    <th style="width:50px;">#</th>
                    <th>Student</th>
                    <th style="width:200px;text-align:center;">Status</th>
                    <th style="width:200px;">Remarks</th>
                </tr></thead>
                <tbody>
                    <tr v-for="(s, i) in filtered" :key="s.id">
                        <td style="color:var(--text-muted);font-size:.8rem;">{{ i+1 }}</td>
                        <td>
                            <div style="font-weight:600;">{{ s.first_name }} {{ s.last_name }}</div>
                            <div style="font-size:.72rem;color:var(--text-muted);">{{ s.admission_no }}</div>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;gap:4px;justify-content:center;">
                                <button v-for="b in statusBtns" :key="b.key"
                                    @click="records[s.id].status = b.key"
                                    :style="{
                                        background: records[s.id]?.status === b.key ? b.color : '#f1f5f9',
                                        color: records[s.id]?.status === b.key ? '#fff' : '#64748b',
                                    }"
                                    class="status-btn">{{ b.label }}</button>
                            </div>
                        </td>
                        <td>
                            <input v-model="records[s.id].remarks" placeholder="Optional" class="remarks-input" />
                        </td>
                    </tr>
                </tbody>
            </Table>

            <div class="save-bar">
                <span style="font-size:.82rem;color:var(--text-muted);">{{ markedCount }} of {{ students.length }} marked</span>
                <Button @click="save" :disabled="saving || markedCount === 0">
                    {{ saving ? 'Saving...' : 'Save Roll Call' }}
                </Button>
            </div>
        </div>
    </template>

    <div v-else-if="selHostel" class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
        No active hostel students found for this hostel.
    </div>
    <div v-else class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
        Select a hostel to begin roll call.
    </div>
</SchoolLayout>
</template>

<style scoped>
.stats-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(130px,1fr)); gap:10px; margin-bottom:16px; }
.stat-card { background:#fff; border-radius:10px; padding:12px 14px; border:1.5px solid #e2e8f0; }
.stat-label { font-size:.7rem; color:#64748b; font-weight:600; text-transform:uppercase; }
.stat-value { font-size:1.3rem; font-weight:800; color:#1e293b; margin-top:2px; }
.stat-green { border-left:4px solid #22c55e; } .stat-red { border-left:4px solid #ef4444; }
.stat-blue { border-left:4px solid #3b82f6; } .stat-amber { border-left:4px solid #f97316; }
.toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:12px 16px; border-bottom:1px solid #f1f5f9; }
.search-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:7px 12px; font-size:.84rem; outline:none; min-width:180px; font-family:inherit; }
.search-input:focus { border-color:#6366f1; }
.mark-all-btn { border:none; border-radius:6px; padding:5px 12px; color:#fff; font-size:.72rem; font-weight:700; cursor:pointer; }
.status-btn { border:none; border-radius:6px; padding:6px 14px; font-size:.75rem; font-weight:700; cursor:pointer; transition:all .15s; min-width:38px; }
.remarks-input { width:100%; border:1px solid #e2e8f0; border-radius:6px; padding:5px 8px; font-size:.78rem; font-family:inherit; }
.save-bar { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-top:1px solid #f1f5f9; background:#fafbfc; }
</style>
