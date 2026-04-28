<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed, watchEffect } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    attendance: Array,
    date: String,
    stats: Object,
});

const selectedDate = ref(props.date);
const search = ref('');
const saving = ref(false);

const statusOptions = [
    { value: 'present',  label: 'P',  color: '#dcfce7', active: '#22c55e', text: '#166534' },
    { value: 'absent',   label: 'A',  color: '#fee2e2', active: '#ef4444', text: '#991b1b' },
    { value: 'late',     label: 'L',  color: '#fef9c3', active: '#eab308', text: '#854d0e' },
    { value: 'half_day', label: 'HD', color: '#ffedd5', active: '#f97316', text: '#9a3412' },
    { value: 'leave',    label: 'LV', color: '#dbeafe', active: '#3b82f6', text: '#1e40af' },
    { value: 'holiday',  label: 'H',  color: '#e0e7ff', active: '#6366f1', text: '#3730a3' },
];

// Build local editable rows
const rows = ref([]);

watchEffect(() => {
    rows.value = (props.attendance || []).map(a => ({
        staff_id:        a.staff_id,
        name:            a.name,
        employee_id:     a.employee_id,
        department:      a.department,
        designation:     a.designation,
        photo:           a.photo,
        status:          a.status || null,
        check_in:        a.check_in || '',
        check_out:       a.check_out || '',
        remarks:         a.remarks || '',
        on_leave:        a.on_leave || false,
        leave_type_name: a.leave_type_name || null,
    }));
});

const filtered = computed(() => {
    if (!search.value) return rows.value;
    const q = search.value.toLowerCase();
    return rows.value.filter(r =>
        r.name.toLowerCase().includes(q) ||
        r.employee_id?.toLowerCase().includes(q) ||
        r.department?.toLowerCase().includes(q)
    );
});

const summary = computed(() => {
    const all = rows.value;
    return {
        total:    all.length,
        present:  all.filter(r => r.status === 'present' || r.status === 'late').length,
        absent:   all.filter(r => r.status === 'absent').length,
        leave:    all.filter(r => r.status === 'leave' || r.on_leave).length,
        half_day: all.filter(r => r.status === 'half_day').length,
        unmarked: all.filter(r => !r.status && !r.on_leave).length,
    };
});

const changeDate = () => {
    router.get(route('school.staff-attendance.index'), { date: selectedDate.value }, { preserveState: true, replace: true });
};

const markAll = (status) => {
    rows.value.forEach(r => {
        if (!r.on_leave) r.status = status;
    });
};

const submit = () => {
    const unmarkedNonLeave = rows.value.filter(r => !r.status && !r.on_leave).length;
    if (unmarkedNonLeave > 0) {
        // Don't auto-mark unmarked staff — that has caused confusion in the
        // past where everyone got silently marked Present. Instead warn and
        // skip them so they stay unmarked in the DB.
        if (!confirm(`${unmarkedNonLeave} staff are still not marked. Save the marked ones only? (Unmarked will stay unmarked.)`)) return;
    }
    saving.value = true;
    // Only send rows that actually have a status. Backend forces on_leave
    // staff to 'leave' so we don't need to send those either.
    const records = rows.value
        .filter(r => r.status && !r.on_leave)
        .map(r => ({
            staff_id:  r.staff_id,
            status:    r.status,
            check_in:  r.check_in || null,
            check_out: r.check_out || null,
            remarks:   r.remarks || null,
        }));

    if (records.length === 0) {
        saving.value = false;
        alert('No staff have been marked yet. Click P / A / L / etc. on each row first.');
        return;
    }

    router.post(route('school.staff-attendance.store'), {
        date: selectedDate.value,
        records,
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

const getOpt = (val) => statusOptions.find(o => o.value === val);
const fmt = (n) => String(n).padStart(2, '0');
</script>

<template>
    <SchoolLayout title="Staff Attendance">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Staff Attendance</h1>
                <p class="page-header-sub">Mark daily attendance for all staff members</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <Button variant="secondary" as="a" :href="route('school.staff-attendance.report')">Monthly Report</Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-label">Total Staff</div><div class="stat-value">{{ summary.total }}</div></div>
            <div class="stat-card stat-green"><div class="stat-label">Present</div><div class="stat-value">{{ summary.present }}</div></div>
            <div class="stat-card stat-red"><div class="stat-label">Absent</div><div class="stat-value">{{ summary.absent }}</div></div>
            <div class="stat-card stat-blue"><div class="stat-label">On Leave</div><div class="stat-value">{{ summary.leave }}</div></div>
            <div class="stat-card stat-amber"><div class="stat-label">Unmarked</div><div class="stat-value">{{ summary.unmarked }}</div></div>
        </div>

        <div class="card">
            <!-- Toolbar -->
            <div class="toolbar">
                <input type="date" v-model="selectedDate" @change="changeDate" class="date-input" />
                <input v-model="search" type="text" placeholder="Search staff..." class="search-input" />
                <div class="mark-all-group">
                    <span class="mark-all-label">Mark All:</span>
                    <button v-for="opt in statusOptions.slice(0, 4)" :key="opt.value"
                            class="mark-all-btn" :style="{ background: opt.active, color: '#fff' }"
                            @click="markAll(opt.value)">{{ opt.label }}</button>
                </div>
            </div>

            <!-- Table -->
            <Table class="att-table" size="sm" :empty="filtered.length === 0" empty-text="No staff found.">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Staff Member</th>
                        <th>Department</th>
                        <th style="width:280px;">Status</th>
                        <th style="width:100px;">Check In</th>
                        <th style="width:100px;">Check Out</th>
                        <th style="width:160px;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in filtered" :key="row.staff_id" :class="{ 'row-absent': row.status === 'absent', 'row-leave': row.on_leave }">
                        <td class="sno">{{ i + 1 }}</td>
                        <td>
                            <div class="staff-cell">
                                <div class="staff-avatar" v-if="row.photo">
                                    <img :src="row.photo" :alt="row.name" />
                                </div>
                                <div class="staff-avatar staff-avatar-placeholder" v-else>{{ row.name.charAt(0) }}</div>
                                <div>
                                    <div class="staff-name">{{ row.name }}</div>
                                    <div class="staff-meta">{{ row.employee_id }} <span v-if="row.designation">/ {{ row.designation }}</span></div>
                                    <div v-if="row.on_leave" class="leave-badge">Approved Leave: {{ row.leave_type_name }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="dept-chip">{{ row.department || '-' }}</span></td>
                        <td>
                            <div v-if="row.on_leave" class="leave-locked">
                                <span class="leave-locked-badge">On Leave</span>
                            </div>
                            <div v-else class="status-btns">
                                <span v-if="!row.status" class="not-marked-pill">Not marked</span>
                                <button v-for="opt in statusOptions" :key="opt.value"
                                        class="status-btn"
                                        :class="{ active: row.status === opt.value }"
                                        :style="row.status === opt.value
                                            ? { background: opt.active, color: '#fff', borderColor: opt.active }
                                            : { background: '#f1f5f9', color: '#64748b', borderColor: '#e2e8f0' }"
                                        @click="row.status = opt.value">
                                    {{ opt.label }}
                                </button>
                            </div>
                        </td>
                        <td><input type="time" v-model="row.check_in" class="time-input" /></td>
                        <td><input type="time" v-model="row.check_out" class="time-input" /></td>
                        <td><input type="text" v-model="row.remarks" class="remarks-input" placeholder="..." /></td>
                    </tr>
                </tbody>
            </Table>

            <!-- Submit -->
            <div class="submit-bar">
                <div class="submit-info">
                    <span class="chip chip-green">P: {{ summary.present }}</span>
                    <span class="chip chip-red">A: {{ summary.absent }}</span>
                    <span class="chip chip-blue">LV: {{ summary.leave }}</span>
                    <span class="chip chip-amber">HD: {{ summary.half_day }}</span>
                </div>
                <Button @click="submit" :loading="saving">
                    Save Attendance
                </Button>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.stats-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:12px; margin-bottom:18px; }
.stat-card { background:#fff; border-radius:10px; padding:14px 16px; border:1.5px solid #e2e8f0; }
.stat-label { font-size:0.72rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
.stat-value { font-size:1.5rem; font-weight:800; color:#1e293b; margin-top:4px; }
.stat-green { border-left:4px solid #22c55e; }
.stat-red   { border-left:4px solid #ef4444; }
.stat-blue  { border-left:4px solid #3b82f6; }
.stat-amber { border-left:4px solid #f59e0b; }

.toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; padding:14px 18px; border-bottom:1px solid #f1f5f9; }
.date-input, .search-input { border:1.5px solid #e2e8f0; border-radius:8px; padding:7px 12px; font-size:.84rem; outline:none; font-family:inherit; }
.date-input:focus, .search-input:focus { border-color:#6366f1; }
.search-input { flex:1; min-width:180px; }
.mark-all-group { display:flex; align-items:center; gap:6px; margin-left:auto; }
.mark-all-label { font-size:.75rem; font-weight:600; color:#64748b; }
.mark-all-btn { border:none; border-radius:6px; padding:5px 10px; font-size:.72rem; font-weight:700; cursor:pointer; transition:opacity .15s; }
.mark-all-btn:hover { opacity:.85; }

.row-absent td { background:#fef2f2 !important; }
.row-leave td { background:#eff6ff !important; }
.leave-badge { font-size:.65rem; color:#1d4ed8; background:#dbeafe; padding:1px 6px; border-radius:8px; margin-top:2px; display:inline-block; font-weight:600; }
.leave-locked { display:flex; align-items:center; }
.leave-locked-badge { background:#3b82f6; color:#fff; padding:4px 14px; border-radius:6px; font-size:.75rem; font-weight:700; }
.sno { color:#94a3b8; font-weight:600; }

.staff-cell { display:flex; align-items:center; gap:10px; }
.staff-avatar { width:34px; height:34px; border-radius:50%; overflow:hidden; flex-shrink:0; }
.staff-avatar img { width:100%; height:100%; object-fit:cover; }
.staff-avatar-placeholder { background:#ede9fe; color:#6366f1; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.8rem; }
.staff-name { font-weight:600; color:#1e293b; font-size:.84rem; }
.staff-meta { font-size:.7rem; color:#94a3b8; }
.dept-chip { background:#f1f5f9; padding:2px 8px; border-radius:12px; font-size:.72rem; color:#475569; }

.status-btns { display:flex; gap:4px; align-items:center; flex-wrap:wrap; }
.status-btn { border:1.5px solid transparent; border-radius:6px; padding:4px 9px; font-size:.72rem; font-weight:700; cursor:pointer; transition:all .15s; }
.status-btn:hover { opacity:.85; transform:translateY(-1px); }
.status-btn.active { box-shadow:0 1px 3px rgba(0,0,0,.15); }
.not-marked-pill {
    background:#fef3c7; color:#92400e; font-size:.65rem; font-weight:700;
    padding:2px 8px; border-radius:10px; text-transform:uppercase;
    letter-spacing:.04em; margin-right:4px;
}

.time-input, .remarks-input { width:100%; border:1.5px solid #e2e8f0; border-radius:6px; padding:5px 8px; font-size:.78rem; outline:none; font-family:inherit; }
.time-input:focus, .remarks-input:focus { border-color:#6366f1; }

.submit-bar { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-top:1px solid #f1f5f9; background:#f8fafc; }
.submit-info { display:flex; gap:8px; flex-wrap:wrap; }
.chip { padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; }
.chip-green { background:#dcfce7; color:#166534; }
.chip-red   { background:#fee2e2; color:#991b1b; }
.chip-blue  { background:#dbeafe; color:#1e40af; }
.chip-amber { background:#ffedd5; color:#9a3412; }
</style>
