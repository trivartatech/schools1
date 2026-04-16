<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    routes: Array,
    date: String,
});

// ── Filter state ────────────────────────────────────────────────────────────
const filter = reactive({
    route_id: '',
    trip_type: 'pickup',
    date: props.date || school.today(),
});

// ── Student list fetched via API ────────────────────────────────────────────
const students = ref([]);
const loading = ref(false);
const fetchError = ref('');
const hasFetched = ref(false);

async function fetchStudents() {
    if (!filter.route_id || !filter.trip_type) return;
    loading.value = true;
    fetchError.value = '';
    hasFetched.value = false;
    try {
        const params = new URLSearchParams({
            route_id: filter.route_id,
            date: filter.date,
            trip_type: filter.trip_type,
        });
        const res = await fetch(`/school/transport/attendance/students?${params}`);
        if (!res.ok) throw new Error('Failed to load students');
        const data = await res.json();
        students.value = (data || []).map(s => ({
            student_id: s.id,
            name: s.name,
            admission_no: s.admission_no || '',
            stop_name: s.stop_name || '',
            stop_order: s.stop_order ?? 999,
            status: s.existing_status || null,
            boarded_at: s.boarded_at || '',
            notes: s.notes || '',
        }));
        hasFetched.value = true;
    } catch (e) {
        fetchError.value = e.message || 'Something went wrong';
        students.value = [];
    } finally {
        loading.value = false;
    }
}

// Re-fetch when route or trip type changes
watch(
    () => [filter.route_id, filter.trip_type, filter.date],
    ([routeId, tripType]) => {
        if (routeId && tripType) fetchStudents();
        else { students.value = []; hasFetched.value = false; }
    }
);

// ── Sorted by stop order ────────────────────────────────────────────────────
const sortedStudents = computed(() => {
    return [...students.value].sort((a, b) => a.stop_order - b.stop_order);
});

// ── Status helpers ──────────────────────────────────────────────────────────
function setStatus(student, status) {
    student.status = status;
    if (status === 'present' && !student.boarded_at) {
        const now = new Date();
        student.boarded_at = now.toTimeString().slice(0, 5);
    }
    if (status !== 'present') {
        student.boarded_at = '';
    }
}

function markAllPresent() {
    const now = new Date().toTimeString().slice(0, 5);
    students.value.forEach(s => {
        s.status = 'present';
        if (!s.boarded_at) s.boarded_at = now;
    });
}

// ── Summary ─────────────────────────────────────────────────────────────────
const summary = computed(() => {
    const counts = { present: 0, absent: 0, late: 0, not_marked: 0 };
    students.value.forEach(s => {
        if (s.status && counts[s.status] !== undefined) counts[s.status]++;
        else counts.not_marked++;
    });
    counts.total = students.value.length;
    return counts;
});

// ── Selected route info ─────────────────────────────────────────────────────
const selectedRoute = computed(() => {
    return (props.routes || []).find(r => r.id == filter.route_id) || null;
});

// ── Submit ──────────────────────────────────────────────────────────────────
const saving = ref(false);
const submitSuccess = ref(false);

function submit() {
    if (!filter.route_id || !filter.trip_type) return;

    const unmarked = students.value.filter(s => !s.status).length;
    if (unmarked > 0) {
        if (!confirm(`${unmarked} student(s) have no status. Mark them all as Present before saving?`)) {
            return;
        }
        markAllPresent();
    }

    saving.value = true;
    submitSuccess.value = false;

    router.post('/school/transport/attendance', {
        route_id: filter.route_id,
        date: filter.date,
        trip_type: filter.trip_type,
        records: students.value.map(s => ({
            student_id: s.student_id,
            status: s.status,
            boarded_at: s.boarded_at || null,
            notes: s.notes || '',
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => { submitSuccess.value = true; setTimeout(() => { submitSuccess.value = false; }, 3000); },
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <SchoolLayout title="Bus Roll Call">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Bus Roll Call</h1>
                <p class="page-header-sub">Mark student attendance for bus pickup and drop</p>
            </div>
        </div>

        <!-- Filter card -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div class="rc-filters">
                    <div class="form-field">
                        <label>Route</label>
                        <select v-model="filter.route_id">
                            <option value="">Select Route</option>
                            <option v-for="r in routes" :key="r.id" :value="r.id">
                                {{ r.route_name }} ({{ r.route_code }})
                            </option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Trip Type</label>
                        <select v-model="filter.trip_type">
                            <option value="pickup">Pickup</option>
                            <option value="drop">Drop</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Date</label>
                        <input v-model="filter.date" type="date" />
                    </div>
                </div>

                <!-- Vehicle info -->
                <div v-if="selectedRoute && selectedRoute.vehicles && selectedRoute.vehicles.length" class="rc-vehicle-info">
                    <svg class="w-4 h-4" style="color:var(--text-muted);flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span style="font-size:0.8125rem;color:var(--text-muted);">
                        Vehicle: <strong style="color:var(--text-primary);">{{ selectedRoute.vehicles[0].vehicle_number }}</strong>
                        <template v-if="selectedRoute.vehicles[0].vehicle_name"> — {{ selectedRoute.vehicles[0].vehicle_name }}</template>
                    </span>
                </div>
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
            <svg class="rc-spin" style="width:32px;height:32px;margin:0 auto 12px;color:var(--accent);" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p style="font-size:0.875rem;">Loading students...</p>
        </div>

        <!-- Fetch error -->
        <div v-else-if="fetchError" class="card" style="text-align:center;padding:48px;">
            <svg class="w-12 h-12" style="margin:0 auto 12px;color:var(--danger);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p style="font-size:0.875rem;color:var(--danger);margin-bottom:12px;">{{ fetchError }}</p>
            <Button variant="secondary" @click="fetchStudents">Retry</Button>
        </div>

        <!-- Student list -->
        <template v-else-if="hasFetched && students.length > 0">

            <!-- Summary + Mark All bar -->
            <div class="rc-action-bar">
                <div class="rc-summary">
                    <span class="rc-summary-item">
                        <span class="rc-dot" style="background:#10b981;"></span>
                        Present: <strong>{{ summary.present }}</strong>
                    </span>
                    <span class="rc-summary-item">
                        <span class="rc-dot" style="background:var(--danger);"></span>
                        Absent: <strong>{{ summary.absent }}</strong>
                    </span>
                    <span class="rc-summary-item">
                        <span class="rc-dot" style="background:#f59e0b;"></span>
                        Late: <strong>{{ summary.late }}</strong>
                    </span>
                    <span class="rc-summary-item" style="color:var(--text-muted);">
                        Total: <strong>{{ summary.total }}</strong>
                    </span>
                </div>
                <Button variant="secondary" @click="markAllPresent" type="button" class="rc-mark-all-btn">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Mark All Present
                </Button>
            </div>

            <!-- Success toast -->
            <div v-if="submitSuccess" class="rc-toast-success">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Attendance saved successfully.
            </div>

            <!-- Student cards (mobile-friendly single column) -->
            <div class="rc-student-list">
                <div v-for="student in sortedStudents" :key="student.student_id" class="rc-student-card">
                    <div class="rc-student-info">
                        <div class="rc-student-avatar">
                            {{ student.name.charAt(0) }}
                        </div>
                        <div class="rc-student-details">
                            <div class="rc-student-name">{{ student.name }}</div>
                            <div class="rc-student-meta">
                                <span v-if="student.admission_no" class="badge badge-gray" style="font-size:0.6875rem;">{{ student.admission_no }}</span>
                                <span v-if="student.stop_name" class="rc-stop-label">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    </svg>
                                    {{ student.stop_name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Status toggle buttons -->
                    <div class="rc-status-row">
                        <button type="button"
                                @click="setStatus(student, 'present')"
                                class="rc-status-btn rc-status-present"
                                :class="{ 'rc-status-active': student.status === 'present' }">
                            P
                        </button>
                        <button type="button"
                                @click="setStatus(student, 'absent')"
                                class="rc-status-btn rc-status-absent"
                                :class="{ 'rc-status-active': student.status === 'absent' }">
                            A
                        </button>
                        <button type="button"
                                @click="setStatus(student, 'late')"
                                class="rc-status-btn rc-status-late"
                                :class="{ 'rc-status-active': student.status === 'late' }">
                            L
                        </button>
                    </div>

                    <!-- Notes row (collapsed by default, shown if notes exist or status is late/absent) -->
                    <div v-if="student.status === 'late' || student.status === 'absent' || student.notes"
                         class="rc-notes-row">
                        <input v-model="student.notes" type="text" placeholder="Add a note..."
                               class="rc-notes-input" />
                    </div>
                </div>
            </div>

            <!-- Submit footer -->
            <div class="rc-submit-bar">
                <span style="font-size:0.875rem;color:var(--text-muted);">
                    {{ summary.present }} present, {{ summary.absent }} absent, {{ summary.late }} late
                    of {{ summary.total }}
                </span>
                <Button @click="submit" :loading="saving" class="rc-submit-btn">
                    <svg v-if="saving" class="rc-spin" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Save Attendance
                </Button>
            </div>
        </template>

        <!-- Empty state: fetched but no students -->
        <div v-else-if="hasFetched && students.length === 0" class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
            <svg class="w-12 h-12" style="margin:0 auto 12px;color:var(--border);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
            </svg>
            <p style="font-size:0.875rem;">No students found on this route for the selected trip.</p>
        </div>

        <!-- Initial empty state -->
        <div v-else-if="!filter.route_id" class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
            <svg class="w-12 h-12" style="margin:0 auto 12px;color:var(--border);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            <p style="font-size:0.875rem;">Select a route and trip type to begin roll call.</p>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Filters ─────────────────────────────────────────────────────────────── */
.rc-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
}
.rc-filters .form-field {
    flex: 1;
    min-width: 140px;
}
.rc-vehicle-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

/* ── Action bar (summary + mark all) ─────────────────────────────────────── */
.rc-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.rc-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
}
.rc-summary-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    color: var(--text-primary);
}
.rc-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.rc-mark-all-btn {
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ── Success toast ───────────────────────────────────────────────────────── */
.rc-toast-success {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    margin-bottom: 16px;
    border-radius: var(--radius, 8px);
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
    font-size: 0.875rem;
    font-weight: 500;
}

/* ── Student list (single column, mobile-friendly) ───────────────────────── */
.rc-student-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.rc-student-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius, 8px);
    padding: 14px 16px;
    transition: border-color 0.15s;
}
.rc-student-card:has(.rc-status-present.rc-status-active) {
    border-left: 3px solid #10b981;
}
.rc-student-card:has(.rc-status-absent.rc-status-active) {
    border-left: 3px solid var(--danger, #ef4444);
}
.rc-student-card:has(.rc-status-late.rc-status-active) {
    border-left: 3px solid #f59e0b;
}

/* Student info row */
.rc-student-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.rc-student-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e0e7ff;
    color: var(--accent, #6366f1);
    font-size: 0.8125rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.rc-student-details {
    flex: 1;
    min-width: 0;
}
.rc-student-name {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--text-primary, #111827);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rc-student-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 3px;
    flex-wrap: wrap;
}
.rc-stop-label {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: 0.75rem;
    color: var(--text-muted, #6b7280);
}

/* ── Status buttons (large touch targets) ────────────────────────────────── */
.rc-status-row {
    display: flex;
    gap: 8px;
}
.rc-status-btn {
    flex: 1;
    height: 44px;
    min-width: 60px;
    border-radius: 8px;
    border: 2px solid;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Present */
.rc-status-present {
    background: #f0fdf4;
    color: #16a34a;
    border-color: #86efac;
}
.rc-status-present.rc-status-active {
    background: #16a34a;
    color: #fff;
    border-color: #16a34a;
}

/* Absent */
.rc-status-absent {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fca5a5;
}
.rc-status-absent.rc-status-active {
    background: #dc2626;
    color: #fff;
    border-color: #dc2626;
}

/* Late */
.rc-status-late {
    background: #fffbeb;
    color: #d97706;
    border-color: #fcd34d;
}
.rc-status-late.rc-status-active {
    background: #d97706;
    color: #fff;
    border-color: #d97706;
}

/* ── Notes row ───────────────────────────────────────────────────────────── */
.rc-notes-row {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border, #e5e7eb);
}
.rc-notes-input {
    width: 100%;
    font-size: 0.8125rem;
    padding: 8px 12px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 6px;
    background: var(--bg, #f9fafb);
    color: var(--text-primary, #111827);
}
.rc-notes-input:focus {
    outline: none;
    border-color: var(--accent, #6366f1);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

/* ── Submit bar ──────────────────────────────────────────────────────────── */
.rc-submit-bar {
    position: sticky;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 18px;
    margin-top: 16px;
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius, 8px);
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
    z-index: 10;
}
.rc-submit-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    min-height: 44px;
    padding-left: 24px;
    padding-right: 24px;
    font-size: 0.9375rem;
}

/* ── Spinner ─────────────────────────────────────────────────────────────── */
.rc-spin {
    animation: rc-spin 0.8s linear infinite;
}
@keyframes rc-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ── Mobile responsiveness ───────────────────────────────────────────────── */
@media (max-width: 640px) {
    .rc-filters {
        flex-direction: column;
    }
    .rc-filters .form-field {
        min-width: 100%;
    }
    .rc-action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .rc-mark-all-btn {
        justify-content: center;
    }
    .rc-submit-bar {
        flex-direction: column;
        text-align: center;
    }
    .rc-submit-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
