<script setup>
import Button from '@/Components/ui/Button.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, reactive, computed, watchEffect } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Table from '@/Components/ui/Table.vue';

const { can, isSchoolManagement } = usePermissions();
const hasWriteAccess = computed(() => isSchoolManagement.value || can('create_attendance') || can('edit_attendance'));

const props = defineProps({
    classes:            { type: Array,  required: true },
    sections:           { type: Array,  default: () => [] },
    students:           { type: Array,  default: () => [] },
    existingAttendance: { type: Object, default: () => ({}) },
    selectedClassId:    { type: Number, default: null },
    selectedSectionId:  { type: Number, default: null },
    selectedDate:       { type: String, default: null },
});

// Filter state — initialized from props on each page load
const filter = reactive({
    class_id:   props.selectedClassId  || '',
    section_id: props.selectedSectionId || '',
    date:       props.selectedDate || new Date().toISOString().slice(0, 10),
});

const applyFilter = () => {
    router.get('/school/attendance', filter, { preserveState: true, replace: true });
};

// When class changes, reset section, then navigate (only one navigation call)
const onClassChange = () => {
    filter.section_id = '';
    applyFilter();
};

// Build attendance form
const statusOptions = [
    { value: 'present',  label: 'P',  ariaLabel: 'Present',  color: 'bg-green-100 text-green-700 border-green-300',   active: 'bg-green-500 text-white border-green-500'  },
    { value: 'absent',   label: 'A',  ariaLabel: 'Absent',   color: 'bg-red-100 text-red-700 border-red-300',          active: 'bg-red-500 text-white border-red-500'       },
    { value: 'late',     label: 'L',  ariaLabel: 'Late',     color: 'bg-yellow-100 text-yellow-700 border-yellow-300', active: 'bg-yellow-500 text-white border-yellow-500' },
    { value: 'half_day', label: 'H',  ariaLabel: 'Half Day', color: 'bg-orange-100 text-orange-700 border-orange-300', active: 'bg-orange-500 text-white border-orange-500' },
    { value: 'leave',    label: 'LV', ariaLabel: 'On Leave', color: 'bg-blue-100 text-blue-700 border-blue-300',       active: 'bg-blue-500 text-white border-blue-500'     },
];

// Build row state — watchEffect re-runs whenever props.students or props.existingAttendance changes
// (Inertia updates props on each navigation but doesn't remount the component)
const rows = ref([]);
const alreadySaved = ref(false);

watchEffect(() => {
    rows.value = (props.students || []).map(student => {
        const existing = props.existingAttendance?.[student.id];
        return {
            student_id: student.id,
            name:       student.name,
            roll_no:    student.roll_no,
            photo:      student.photo,
            status:     existing?.status || null,
            remarks:    existing?.remarks || '',
        };
    });
    alreadySaved.value = Object.keys(props.existingAttendance || {}).length > 0;
});

// Mark all
const markAll = (status) => {
    rows.value.forEach(r => r.status = status);
};

const saving = ref(false);
const sendingNotifications = ref(false);

const submit = (withNotifications = false) => {
    if (!filter.class_id || !filter.date) return;

    if (summary.value.not_marked > 0) {
        if (!confirm(`You have ${summary.value.not_marked} students not marked. All students must have a status. Mark them all as Present?`)) {
            return;
        }
        markAll('present');
    }

    if (withNotifications) {
        sendingNotifications.value = true;
    } else {
        saving.value = true;
    }

    router.post('/school/attendance', {
        class_id:   filter.class_id,
        section_id: filter.section_id || null,
        date:       filter.date,
        send_notifications: withNotifications,
        attendance: rows.value.map(r => ({
            student_id: r.student_id,
            status:     r.status,
            remarks:    r.remarks,
        })),
    }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
            sendingNotifications.value = false;
        },
    });
};

// Summary counts
const summary = computed(() => {
    const counts = { present: 0, absent: 0, late: 0, half_day: 0, leave: 0, not_marked: 0 };
    rows.value.forEach(r => {
        if (r.status) counts[r.status]++;
        else counts.not_marked++;
    });
    return counts;
});
</script>

<template>
    <SchoolLayout title="Attendance">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-header-title">Mark Attendance</h1>
                    <p class="page-header-sub">Select class, section and date to begin</p>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <Button variant="secondary" as="link" href="/school/attendance/scanner">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        QR Scanner
                    </Button>
                    <Button variant="secondary" as="link" href="/school/attendance/report">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        View Report
                    </Button>
                </div>
            </div>

            <!-- Filter bar -->
            <FilterBar>
                <div class="form-field">
                    <label>Class</label>
                    <select v-model="filter.class_id" @change="onClassChange">
                        <option value="">Select Class</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Section</label>
                    <select v-model="filter.section_id" @change="applyFilter" :disabled="!filter.class_id || sections.length === 0">
                        <option value="">All Sections</option>
                        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Date</label>
                    <input v-model="filter.date" type="date" @change="applyFilter">
                </div>
                <div v-if="students && students.length && hasWriteAccess" class="mark-all-bar">
                    <span style="font-size:0.75rem;color:var(--text-muted);margin-right:4px;">Mark all:</span>
                    <button v-for="opt in statusOptions" :key="opt.value" type="button"
                            @click="markAll(opt.value)"
                            class="mark-all-btn"
                            :class="opt.color"
                            :aria-label="`Mark all as ${opt.ariaLabel}`">
                        {{ opt.label }}
                    </button>
                </div>
            </FilterBar>

            <!-- Summary bar -->
            <div v-if="students && students.length" class="summary-bar">
                <div class="summary-counts">
                    <span class="summary-item"><span class="summary-dot" style="background:#10b981;"></span>Present: <strong>{{ summary.present }}</strong></span>
                    <span class="summary-item"><span class="summary-dot" style="background:var(--danger);"></span>Absent: <strong>{{ summary.absent }}</strong></span>
                    <span class="summary-item"><span class="summary-dot" style="background:#f59e0b;"></span>Late: <strong>{{ summary.late }}</strong></span>
                    <span class="summary-item"><span class="summary-dot" style="background:#ea580c;"></span>Half Day: <strong>{{ summary.half_day }}</strong></span>
                    <span class="summary-item"><span class="summary-dot" style="background:#2563eb;"></span>Leave: <strong>{{ summary.leave }}</strong></span>
                    <span v-if="summary.not_marked > 0" class="summary-item" style="color:var(--warning);font-weight:600;">
                        <span class="summary-dot" style="background:#fbbf24;"></span> Not Marked: {{ summary.not_marked }}
                    </span>
                </div>
                <div v-if="alreadySaved" class="already-saved-badge">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Attendance already marked — editing will update records
                </div>
            </div>

            <!-- Student list -->
            <div v-if="students && students.length > 0" class="card" style="overflow:hidden;">
                <Table>
                    <thead>
                        <tr>
                            <th style="width:64px;">Roll</th>
                            <th>Student</th>
                            <th style="text-align:center;">Status</th>
                            <th style="width:200px;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.student_id">
                            <td style="font-family:monospace;font-size:0.8125rem;color:var(--text-muted);">{{ row.roll_no || '—' }}</td>
                            <td>
                                <div class="student-cell">
                                    <img v-if="row.photo_url" :src="row.photo_url" class="student-avatar" />
                                    <div v-else class="student-avatar-placeholder">
                                        {{ row.name.charAt(0) }}
                                    </div>
                                    <span style="font-size:0.875rem;font-weight:500;color:var(--text-primary);">{{ row.name }}</span>
                                </div>
                            </td>
                            <td>
                                <div v-if="hasWriteAccess" class="status-buttons">
                                    <button v-for="opt in statusOptions" :key="opt.value"
                                            type="button"
                                            @click="row.status = opt.value"
                                            class="status-btn"
                                            :class="row.status === opt.value ? opt.active : opt.color"
                                            :aria-label="`${row.name}: mark as ${opt.ariaLabel}`"
                                            :aria-pressed="row.status === opt.value">
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <div v-else style="text-align:center;font-weight:700;font-size:0.75rem;text-transform:uppercase;">
                                    {{ statusOptions.find(o => o.value === row.status)?.label || row.status }}
                                </div>
                            </td>
                            <td>
                                <input v-if="hasWriteAccess" v-model="row.remarks" type="text" placeholder="Optional notes..." class="remarks-input" />
                                <span v-else style="font-size:0.75rem;color:var(--text-muted);">{{ row.remarks || '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </Table>

                <div class="table-footer">
                    <span style="font-size:0.875rem;color:var(--text-muted);">{{ rows.length }} students</span>
                    <div v-if="hasWriteAccess" style="display:flex;align-items:center;gap:8px;">
                        <Button @click="submit(false)" :disabled="saving || sendingNotifications">
                            <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ saving ? 'Saving...' : (alreadySaved ? 'Update Attendance' : 'Save Attendance') }}
                        </Button>
                        <button @click="submit(true)" :disabled="saving || sendingNotifications" class="btn-notify">
                            <svg v-if="sendingNotifications" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            {{ sendingNotifications ? 'Sending...' : 'Save & Send Notification' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state: class selected, no students -->
            <div v-else-if="filter.class_id" class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
                <svg class="w-12 h-12" style="margin:0 auto 12px;color:var(--border);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                </svg>
                <p style="font-size:0.875rem;">No students found in this class/section for the current academic year.</p>
            </div>

            <!-- Empty state: no class selected -->
            <div v-else class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
                <svg class="w-12 h-12" style="margin:0 auto 12px;color:var(--border);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p style="font-size:0.875rem;">Select a class and date to begin marking attendance.</p>
            </div>

    </SchoolLayout>
</template>

<style scoped>
/* Filter bar layout */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
}
.filter-bar .form-field { min-width: 140px; }
.mark-all-bar {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
}
.mark-all-btn {
    padding: 5px 10px;
    border-radius: 6px;
    border: 1.5px solid;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}

/* Summary bar between filter and table */
.summary-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}
.summary-counts { display: flex; flex-wrap: wrap; gap: 14px; }
.summary-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    color: var(--text-primary);
}
.summary-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.already-saved-badge {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: var(--warning);
    font-weight: 500;
}

/* Student row */
.student-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}
.student-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border);
    flex-shrink: 0;
}
.student-avatar-placeholder {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e0e7ff;
    color: var(--accent);
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Status toggle buttons */
.status-buttons {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.status-btn {
    width: 36px;
    height: 32px;
    border-radius: 6px;
    border: 1.5px solid;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}

/* Remarks input */
.remarks-input {
    font-size: 0.8125rem;
    padding: 6px 10px;
}

/* Table footer */
.table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-top: 1px solid var(--border);
    background: var(--bg);
}

/* Send Notification button */
.btn-notify {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    border: none;
    border-radius: var(--radius, 8px);
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 1px 3px rgba(124, 58, 237, 0.3);
}
.btn-notify:hover:not(:disabled) {
    background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    box-shadow: 0 3px 8px rgba(124, 58, 237, 0.4);
    transform: translateY(-1px);
}
.btn-notify:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
