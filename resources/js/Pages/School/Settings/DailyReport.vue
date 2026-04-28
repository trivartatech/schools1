<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    settings:             { type: Object, required: true },
    all_sections:         { type: Array,  required: true },
    admin_contacts_count: { type: Number, default: 0 },
});

const page = usePage();

// Settings sub-sidebar (mirrors AdminContacts.vue pattern)
const settingsNav = [
    { id: 'general-config',   label: 'General Config',   route: '/school/settings/general-config' },
    { id: 'asset-config',     label: 'Asset Config',     route: '/school/settings/asset-config' },
    { id: 'system-config',    label: 'System Config',    route: '/school/settings/system-config' },
    { id: 'geofence-config',  label: 'Geofence Config',  route: '/school/settings/geofence-config' },
    { id: 'admin-contacts',   label: 'Admin Numbers',    route: '/school/settings/admin-contacts' },
    { id: 'daily-report',     label: 'Daily Report',     route: '/school/settings/daily-report' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

const SECTION_LABELS = {
    alerts:     'Alerts & Flags',
    highlights: 'Highlights of the Day',
    attendance: 'Attendance (per class+section)',
    fees:       'Fees Collected',
    expenses:   'Expenses',
    cash:       'Cash Flow',
    admissions: 'New Admissions',
    events:     'Day Events (visitors, birthdays, holidays)',
    outlook:    'Tomorrow\'s Outlook',
};

const form = useForm({
    sections_enabled:             [...(props.settings.sections_enabled || [])],
    oversized_expense_threshold:  props.settings.oversized_expense_threshold,
    low_attendance_threshold_pct: props.settings.low_attendance_threshold_pct,
    repeat_absent_days:           props.settings.repeat_absent_days,
    auto_send_time:               props.settings.auto_send_time,
    auto_send_enabled:            props.settings.auto_send_enabled,
    weekly_digest_enabled:        props.settings.weekly_digest_enabled,
});

const toggleSection = (key) => {
    const idx = form.sections_enabled.indexOf(key);
    if (idx >= 0) form.sections_enabled.splice(idx, 1);
    else form.sections_enabled.push(key);
};

const submit = () => {
    form.post('/school/settings/daily-report', { preserveScroll: true });
};
</script>

<template>
    <SchoolLayout title="Daily Report Settings">
        <div class="settings-shell">

            <!-- Settings sub-sidebar -->
            <aside class="settings-sidebar">
                <nav class="settings-sidebar-nav">
                    <Link
                        v-for="item in settingsNav"
                        :key="item.id"
                        :href="item.route"
                        class="settings-nav-item"
                        :class="{ 'settings-nav-item--active': isActive(item.route) }"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <!-- Main content -->
            <section class="settings-content">

                <div v-if="admin_contacts_count === 0" class="warn-banner">
                    ⚠ No admin numbers configured. The daily report will have no recipients.
                    <Link href="/school/settings/admin-contacts">Add admin numbers →</Link>
                </div>

                <form @submit.prevent="submit" novalidate>

                    <!-- Sections enabled -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h2 class="card-title">Sections to include</h2>
                            <p class="card-sub">Toggle which sections appear in the page, PDF, and broadcast message.</p>
                        </div>
                        <div class="card-body">
                            <div class="section-grid">
                                <label v-for="key in all_sections" :key="key" class="section-toggle">
                                    <input
                                        type="checkbox"
                                        :checked="form.sections_enabled.includes(key)"
                                        @change="toggleSection(key)"
                                    />
                                    <span>{{ SECTION_LABELS[key] || key }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Thresholds -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h2 class="card-title">Anomaly thresholds</h2>
                            <p class="card-sub">When to flag classes / students / vouchers as needing attention.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>Low attendance % threshold</label>
                                    <input v-model.number="form.low_attendance_threshold_pct" type="number" min="0" max="100" />
                                    <small>Flag classes whose effective-present % falls below this number.</small>
                                    <div v-if="form.errors.low_attendance_threshold_pct" class="form-error">{{ form.errors.low_attendance_threshold_pct }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Repeat absent days</label>
                                    <input v-model.number="form.repeat_absent_days" type="number" min="2" max="14" />
                                    <small>Flag students absent N days running.</small>
                                    <div v-if="form.errors.repeat_absent_days" class="form-error">{{ form.errors.repeat_absent_days }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Oversized expense threshold (₹)</label>
                                    <input v-model.number="form.oversized_expense_threshold" type="number" min="0" step="100" />
                                    <small>Flag any voucher above this amount.</small>
                                    <div v-if="form.errors.oversized_expense_threshold" class="form-error">{{ form.errors.oversized_expense_threshold }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h2 class="card-title">Auto-send schedule</h2>
                            <p class="card-sub">When the report fires automatically every day. Manual sends from the page work regardless.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>Auto-send time</label>
                                    <input v-model="form.auto_send_time" type="time" />
                                    <small>Default 19:00 (7 PM). School local time.</small>
                                    <div v-if="form.errors.auto_send_time" class="form-error">{{ form.errors.auto_send_time }}</div>
                                </div>
                                <div class="form-field">
                                    <label class="check-label">
                                        <input type="checkbox" v-model="form.auto_send_enabled" />
                                        <span>Enable automatic daily send</span>
                                    </label>
                                </div>
                                <div class="form-field">
                                    <label class="check-label">
                                        <input type="checkbox" v-model="form.weekly_digest_enabled" />
                                        <span>Send weekly digest on Sundays (instead of daily)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;">
                        <Button type="submit" :loading="form.processing">Save Settings</Button>
                        <Link href="/school/reports/daily-master" class="back-link">View Today's Report →</Link>
                    </div>
                </form>

            </section>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.settings-shell {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 56px);
    margin: -24px -28px;
    background: #f8fafc;
}
.settings-sidebar {
    width: 220px;
    min-width: 220px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    padding: 16px 0;
    flex-shrink: 0;
}
.settings-sidebar-nav { display: flex; flex-direction: column; gap: 1px; padding: 0 8px; }
.settings-nav-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    border-radius: 7px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
}
.settings-nav-item:hover { background: #f1f5f9; color: #1e293b; }
.settings-nav-item--active { background: #eff6ff; color: #1169cd; font-weight: 600; }

.settings-content { flex: 1; padding: 28px 32px; overflow-y: auto; }

.warn-banner {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 10px 14px;
    border-radius: 6px;
    margin-bottom: 14px;
    font-size: .875rem;
    color: #78350f;
}
.warn-banner a { color: #92400e; font-weight: 600; margin-left: 6px; }

.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.card-header { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
.card-title { margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a; }
.card-sub { margin: 4px 0 0; font-size: .8125rem; color: #64748b; }
.card-body { padding: 16px; }
.mb-3 { margin-bottom: 14px; }

.section-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 8px;
}
.section-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    cursor: pointer;
    font-size: .875rem;
}
.section-toggle:hover { background: #f8fafc; }
.section-toggle input { width: 16px; height: 16px; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 14px;
}
.form-field label {
    display: block;
    font-size: .8125rem;
    font-weight: 500;
    color: #334155;
    margin-bottom: 4px;
}
.form-field input[type="number"],
.form-field input[type="time"] {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: .875rem;
}
.form-field small { color: #64748b; font-size: .75rem; display: block; margin-top: 3px; }
.form-error { color: #dc2626; font-size: .75rem; margin-top: 3px; }
.check-label { display: flex; align-items: center; gap: 8px; font-weight: 400 !important; cursor: pointer; }
.check-label input { width: 16px; height: 16px; }

.back-link {
    align-self: center;
    color: #1169cd;
    text-decoration: none;
    font-size: .875rem;
    padding: 6px 12px;
}
.back-link:hover { text-decoration: underline; }
</style>
