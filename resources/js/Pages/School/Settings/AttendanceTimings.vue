<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    school:   { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
});

const page = usePage();

// ── Settings sidebar nav items ────────────────────────────────────────────
const settingsNav = [
    { id: 'general-config',     label: 'General Config',              route: '/school/settings/general-config',     icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'asset-config',       label: 'Asset Config',                route: '/school/settings/asset-config',       icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'system-config',      label: 'System Config',               route: '/school/settings/system-config',      icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18' },
    { id: 'geofence-config',    label: 'Geofence Config',             route: '/school/settings/geofence-config',    icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'attendance-timings', label: 'Attendance Timings',          route: '/school/settings/attendance-timings', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'admin-contacts',     label: 'Admin Numbers',               route: '/school/settings/admin-contacts',     icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
    { id: 'receipt-print',      label: 'Receipt Print',               route: '/school/settings/receipt-print',      icon: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

// ── Day-of-week labels (0 = Sunday in JS / Carbon) ────────────────────────
const DAYS = [
    { value: 0, label: 'Sun' },
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
];

// ── Form ──────────────────────────────────────────────────────────────────
const s = props.settings;
const form = useForm({
    weekend_days:    [...(s.weekend_days || [0])],
    staff_weekday:   { ...(s.staff_weekday   || { working: true,  late_after: '09:30' }) },
    staff_weekend:   { ...(s.staff_weekend   || { working: false, late_after: '09:30' }) },
    student_weekday: { ...(s.student_weekday || { working: true,  late_after: '08:30' }) },
    student_weekend: { ...(s.student_weekend || { working: false, late_after: '08:30' }) },
});

function toggleWeekendDay(day) {
    const i = form.weekend_days.indexOf(day);
    if (i === -1) form.weekend_days.push(day);
    else form.weekend_days.splice(i, 1);
    form.weekend_days.sort((a, b) => a - b);
}

const submit = () => form.post('/school/settings/attendance-timings', { preserveScroll: true });
</script>

<template>
    <SchoolLayout title="Attendance Timings">
        <div class="settings-shell">

            <!-- ── Settings Sidebar ─────────────────────────────────── -->
            <aside class="settings-sidebar">
                <nav class="settings-sidebar-nav">
                    <Link v-for="item in settingsNav" :key="item.id" :href="item.route"
                          class="settings-nav-item"
                          :class="{ 'settings-nav-item--active': isActive(item.route) }">
                        <svg class="settings-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>
            </aside>

            <!-- ── Main Content ─────────────────────────────────────── -->
            <section class="settings-content">
                <PageHeader
                    title="Attendance Timings"
                    subtitle="Configure when staff and student attendance is marked late, separately for weekday and weekend."
                />

                <form @submit.prevent="submit" novalidate>

                    <!-- ── Weekend Days ─────────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">Weekend Days</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">
                                Days marked here use the <strong>Weekend</strong> thresholds below. Default: Sunday only — many Indian schools also include Saturday.
                            </p>
                        </div>
                        <div class="card-body">
                            <div class="day-pills">
                                <button v-for="d in DAYS" :key="d.value" type="button"
                                    @click="toggleWeekendDay(d.value)"
                                    :class="['day-pill', { 'day-pill--on': form.weekend_days.includes(d.value) }]">
                                    {{ d.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Staff Timings ─────────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">Staff Timings</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">
                                Used by every staff QR / app punch (web + mobile). Disabled days reject the punch outright.
                            </p>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-row form-row-2">
                                <!-- Staff · Weekday -->
                                <div class="bucket">
                                    <div class="bucket-title">Weekday</div>
                                    <label class="bucket-toggle">
                                        <input type="checkbox" v-model="form.staff_weekday.working" />
                                        <span>Working day for staff</span>
                                    </label>
                                    <div class="form-field">
                                        <label>Mark late after</label>
                                        <input type="time" v-model="form.staff_weekday.late_after"
                                               :disabled="!form.staff_weekday.working" required />
                                    </div>
                                </div>
                                <!-- Staff · Weekend -->
                                <div class="bucket">
                                    <div class="bucket-title">Weekend</div>
                                    <label class="bucket-toggle">
                                        <input type="checkbox" v-model="form.staff_weekend.working" />
                                        <span>Working day for staff</span>
                                    </label>
                                    <div class="form-field">
                                        <label>Mark late after</label>
                                        <input type="time" v-model="form.staff_weekend.late_after"
                                               :disabled="!form.staff_weekend.working" required />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Student Timings ───────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">Student Timings</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">
                                Used by the QR-scan attendance flow — tapping <em>Present</em> after the threshold auto-records as <em>Late</em>.
                            </p>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-row form-row-2">
                                <!-- Student · Weekday -->
                                <div class="bucket">
                                    <div class="bucket-title">Weekday</div>
                                    <label class="bucket-toggle">
                                        <input type="checkbox" v-model="form.student_weekday.working" />
                                        <span>Working day for students</span>
                                    </label>
                                    <div class="form-field">
                                        <label>Mark late after</label>
                                        <input type="time" v-model="form.student_weekday.late_after"
                                               :disabled="!form.student_weekday.working" required />
                                    </div>
                                </div>
                                <!-- Student · Weekend -->
                                <div class="bucket">
                                    <div class="bucket-title">Weekend</div>
                                    <label class="bucket-toggle">
                                        <input type="checkbox" v-model="form.student_weekend.working" />
                                        <span>Working day for students</span>
                                    </label>
                                    <div class="form-field">
                                        <label>Mark late after</label>
                                        <input type="time" v-model="form.student_weekend.late_after"
                                               :disabled="!form.student_weekend.working" required />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Actions ──────────────────────────────────── -->
                    <div style="display:flex;align-items:center;gap:10px;padding:16px 0 8px;">
                        <Button type="submit" :loading="form.processing">Save Timings</Button>
                    </div>

                </form>
            </section>
        </div>
    </SchoolLayout>
</template>

<style scoped>
/* ── Shell (mirrors GeneralConfig.vue / SystemConfig.vue) ───────────── */
.settings-shell {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 56px);
    margin: -24px -28px;
    background: #f8fafc;
}

/* ── Settings Sidebar ── */
.settings-sidebar {
    width: 220px;
    min-width: 220px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    padding: 16px 0;
    flex-shrink: 0;
    overflow-y: auto;
}

.settings-sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 0 8px;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 10px;
    border-radius: 7px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    transition: background 0.13s, color 0.13s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}
.settings-nav-item:hover { background: #f1f5f9; color: #1e293b; }
.settings-nav-item--active {
    background: #eff6ff !important;
    color: #1169cd !important;
    font-weight: 600;
}
.settings-nav-icon { width: 15px; height: 15px; flex-shrink: 0; opacity: 0.75; }
.settings-nav-item--active .settings-nav-icon { opacity: 1; }

/* ── Content ── */
.settings-content {
    flex: 1;
    padding: 28px 32px;
    overflow-y: auto;
}

/* ── Day pill picker (matches ExamSchedule's section-chip pattern) ── */
.day-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.day-pill {
    padding: 5px 14px;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    border: 1.5px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s;
}
.day-pill:hover { border-color: #94a3b8; }
.day-pill--on {
    background: #1169cd;
    color: #fff;
    border-color: #1169cd;
}

/* ── Bucket (Weekday / Weekend column inside a card) ── */
.bucket {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}
.bucket-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #6366f1;
}
.bucket-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 0.8125rem;
    color: #334155;
}
.bucket-toggle input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #4f46e5;
    cursor: pointer;
}

/* Time input — keep it compact next to the label */
.bucket .form-field input[type="time"] { max-width: 180px; }
.bucket .form-field input[type="time"]:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
}
</style>
