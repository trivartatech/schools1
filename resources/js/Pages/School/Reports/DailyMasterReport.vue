<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useConfirm } from '@/Composables/useConfirm';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const confirm = useConfirm();
const school = useSchoolStore();

const props = defineProps({
    report:         { type: Object, required: true },
    settings:       { type: Object, default: () => ({}) },
    admin_contacts: { type: Array,  default: () => [] },
    last_sent_at:   { type: String, default: null },
    deliveries:     { type: Array,  default: () => [] },
});

// ── Date / mode controls ─────────────────────────────────────────────
const selectedDate = ref(props.report?.meta?.date || new Date().toISOString().slice(0, 10));
const selectedMode = ref(props.report?.meta?.mode || 'daily');

const reload = () => {
    router.get('/school/reports/daily-master', {
        date: selectedDate.value,
        mode: selectedMode.value,
    }, { preserveState: false, replace: true });
};

watch(selectedDate, reload);
watch(selectedMode, reload);

// ── Formatters ───────────────────────────────────────────────────────
const fmtMoney = (n) => {
    const num = Number(n || 0);
    const sym = school.currency;
    if (Math.abs(num) >= 100000) return sym + (num / 100000).toFixed(1) + ' L';
    if (Math.abs(num) >= 1000)   return sym + (num / 1000).toFixed(1) + 'k';
    return sym + num.toFixed(0);
};
const fmtMoneyFull = (n) => school.fmtMoney(n);
const fmtPct   = (n, suffix = '%') => Number(n || 0).toFixed(1) + suffix;
const fmtCount = (n) => Number(n || 0).toLocaleString('en-IN');

const deltaClass = (n) => Number(n) > 0 ? 'delta-up' : Number(n) < 0 ? 'delta-down' : 'delta-flat';
const deltaSign  = (n) => Number(n) > 0 ? '↑' : Number(n) < 0 ? '↓' : '·';

// ── Send to admins ───────────────────────────────────────────────────
const sendForm = useForm({ date: selectedDate.value, mode: selectedMode.value });

const sendNow = async () => {
    const ok = await confirm({
        title: 'Send daily master report?',
        message: `Send report for ${props.report.meta.date_label} to all ${props.admin_contacts.length} admin number(s)?`,
        confirmLabel: 'Send',
    });
    if (!ok) return;
    sendForm.date = selectedDate.value;
    sendForm.mode = selectedMode.value;
    sendForm.post('/school/reports/daily-master/send', { preserveScroll: true });
};

const downloadPdf = () => {
    window.open(`/school/reports/daily-master/pdf?date=${selectedDate.value}&mode=${selectedMode.value}`, '_blank');
};

// ── Section visibility based on settings ─────────────────────────────
const sectionsEnabled = computed(() => props.report?.meta?.sections_enabled || []);
const showSection = (key) => sectionsEnabled.value.includes(key);

// ── Convenience derived values ───────────────────────────────────────
const kpi = computed(() => props.report?.kpi || {});
const alerts = computed(() => props.report?.alerts || []);
const highlights = computed(() => props.report?.highlights || {});
const attendance = computed(() => props.report?.attendance || {});
const fees = computed(() => props.report?.fees || {});
const expenses = computed(() => props.report?.expenses || {});
const cash = computed(() => props.report?.cash || {});
const admissions = computed(() => props.report?.admissions || {});
const events = computed(() => props.report?.events || {});
const outlook = computed(() => props.report?.outlook || {});

const isWeekly = computed(() => (props.report?.meta?.mode || 'daily') === 'weekly');

const pctBarColor = (pct) => {
    if (pct >= 90) return '#16a34a';
    if (pct >= 75) return '#22c55e';
    if (pct >= 60) return '#f59e0b';
    return '#dc2626';
};

const lastSentAtLabel = computed(() => {
    if (!props.last_sent_at) return null;
    try {
        return school.fmtDateTime(props.last_sent_at);
    } catch {
        return props.last_sent_at;
    }
});

const channelBadge = (channel) => ({
    whatsapp: 'channel-whatsapp',
    sms:      'channel-sms',
    failed:   'channel-failed',
}[channel] || 'channel-failed');
</script>

<template>
    <SchoolLayout :title="isWeekly ? 'Weekly Digest' : 'Daily Master Report'">
        <!-- Page header -->
        <div class="dmr-header">
            <div>
                <h1 class="dmr-title">{{ isWeekly ? 'Weekly Digest' : 'Daily Master Report' }}</h1>
                <p class="dmr-sub">{{ report.meta.date_label }}</p>
            </div>
            <div class="dmr-actions">
                <Button variant="secondary" type="button" as="link" href="/school/settings/daily-report">
                    Settings
                </Button>
                <Button variant="secondary" type="button" @click="downloadPdf">
                    Download PDF
                </Button>
                <Button type="button" :disabled="admin_contacts.length === 0" :loading="sendForm.processing" @click="sendNow">
                    Send to admins now
                </Button>
            </div>
        </div>

        <!-- Date + mode picker -->
        <FilterBar :active="false">
            <div class="form-field">
                <label>Date</label>
                <input v-model="selectedDate" type="date" style="width:160px;" />
            </div>
            <div class="form-field">
                <label>Mode</label>
                <select v-model="selectedMode" style="width:160px;">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly digest</option>
                </select>
            </div>
            <div style="margin-left:auto;font-size:.8125rem;color:#64748b;">
                <template v-if="admin_contacts.length === 0">
                    ⚠ No admin numbers configured —
                    <Link href="/school/settings/admin-contacts">add at Admin Numbers settings</Link>
                </template>
                <template v-else-if="lastSentAtLabel">
                    Last sent: {{ lastSentAtLabel }}
                </template>
            </div>
        </FilterBar>

        <!-- KPI strip -->
        <div class="kpi-strip">
            <div class="kpi-card" v-if="kpi.attendance_pct">
                <div class="kpi-label">Attendance</div>
                <div class="kpi-value">{{ fmtPct(kpi.attendance_pct.value) }}</div>
                <div class="kpi-deltas">
                    <span :class="deltaClass(kpi.attendance_pct.vs_yesterday_delta)">
                        {{ deltaSign(kpi.attendance_pct.vs_yesterday_delta) }} {{ Math.abs(kpi.attendance_pct.vs_yesterday_delta || 0) }}pp vs yesterday
                    </span>
                </div>
            </div>
            <div class="kpi-card" v-if="kpi.fee_total">
                <div class="kpi-label">Fees Collected</div>
                <div class="kpi-value">{{ fmtMoney(kpi.fee_total.value) }}</div>
                <div class="kpi-deltas">
                    <span :class="deltaClass(kpi.fee_total.vs_yesterday_delta)">
                        {{ deltaSign(kpi.fee_total.vs_yesterday_delta) }} {{ Math.abs(kpi.fee_total.vs_yesterday_delta || 0) }}% vs yesterday
                    </span>
                </div>
            </div>
            <div class="kpi-card" v-if="kpi.expense_total">
                <div class="kpi-label">Expenses</div>
                <div class="kpi-value">{{ fmtMoney(kpi.expense_total.value) }}</div>
                <div class="kpi-deltas">
                    <span :class="deltaClass(kpi.expense_total.vs_yesterday_delta)">
                        {{ deltaSign(kpi.expense_total.vs_yesterday_delta) }} {{ Math.abs(kpi.expense_total.vs_yesterday_delta || 0) }}% vs yesterday
                    </span>
                </div>
            </div>
            <div class="kpi-card kpi-net" v-if="kpi.net_position">
                <div class="kpi-label">Net Cash</div>
                <div class="kpi-value" :class="{ 'net-pos': kpi.net_position.is_positive, 'net-neg': !kpi.net_position.is_positive }">
                    {{ kpi.net_position.is_positive ? '+' : '−' }}{{ fmtMoney(Math.abs(kpi.net_position.value)) }}
                </div>
                <div class="kpi-deltas">Today's collection minus expenses</div>
            </div>
            <div class="kpi-card" v-if="kpi.new_admissions">
                <div class="kpi-label">New Admissions</div>
                <div class="kpi-value">{{ fmtCount(kpi.new_admissions.value) }}</div>
                <div class="kpi-deltas">
                    <span :class="deltaClass(kpi.new_admissions.vs_yesterday_delta)">
                        {{ deltaSign(kpi.new_admissions.vs_yesterday_delta) }} vs yesterday
                    </span>
                </div>
            </div>
            <div class="kpi-card" v-if="kpi.visitors">
                <div class="kpi-label">Visitors</div>
                <div class="kpi-value">{{ fmtCount(kpi.visitors.value) }}</div>
            </div>
        </div>

        <!-- Alerts -->
        <div v-if="showSection('alerts') && alerts.length > 0" class="card mb-3">
            <div class="card-header"><h2 class="card-title">⚠ Alerts &amp; Flags</h2></div>
            <div class="card-body" style="padding:0;">
                <div v-for="(alert, idx) in alerts" :key="idx" class="alert-row" :class="`alert-${alert.severity}`">
                    <div class="alert-head">
                        <strong>{{ alert.label }}</strong>
                        <span class="alert-count">{{ alert.count }}</span>
                    </div>
                    <div v-if="alert.items?.length" class="alert-items">
                        <template v-if="alert.type === 'low_attendance_classes'">
                            <span v-for="(it, i) in alert.items" :key="i" class="chip chip-amber">
                                {{ it.class }}{{ it.section ? ' - ' + it.section : '' }} ({{ it.pct }}%)
                            </span>
                        </template>
                        <template v-else-if="alert.type === 'repeat_absentees'">
                            <span v-for="(it, i) in alert.items" :key="i" class="chip chip-red">
                                {{ it.name }} <small>({{ it.class }}{{ it.section ? ' - ' + it.section : '' }})</small>
                            </span>
                        </template>
                        <template v-else-if="alert.type === 'oversized_expenses'">
                            <span v-for="(it, i) in alert.items" :key="i" class="chip chip-amber">
                                {{ it.title }} — {{ fmtMoneyFull(it.amount) }}
                            </span>
                        </template>
                        <template v-else-if="alert.type === 'stale_visitors'">
                            <span v-for="(it, i) in alert.items" :key="i" class="chip chip-amber">
                                {{ it.name }} ({{ it.in_time }})
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Highlights -->
        <div v-if="showSection('highlights') && (highlights.student_of_the_day || highlights.top_class || highlights.staff_shoutout)" class="card mb-3">
            <div class="card-header"><h2 class="card-title">★ Highlights of the Day</h2></div>
            <div class="card-body highlight-grid">
                <div v-if="highlights.student_of_the_day" class="highlight">
                    <div class="highlight-label">Student of the Day</div>
                    <div class="highlight-name">{{ highlights.student_of_the_day.name }}</div>
                    <div class="highlight-sub">
                        {{ highlights.student_of_the_day.class }}{{ highlights.student_of_the_day.section ? ' - ' + highlights.student_of_the_day.section : '' }}
                        · {{ highlights.student_of_the_day.streak }}-day streak
                    </div>
                </div>
                <div v-if="highlights.top_class" class="highlight">
                    <div class="highlight-label">Top Class</div>
                    <div class="highlight-name">
                        {{ highlights.top_class.class }}{{ highlights.top_class.section ? ' - ' + highlights.top_class.section : '' }}
                    </div>
                    <div class="highlight-sub">
                        {{ highlights.top_class.pct }}% — {{ highlights.top_class.present }}/{{ highlights.top_class.enrolled }} present
                    </div>
                </div>
                <div v-if="highlights.staff_shoutout" class="highlight">
                    <div class="highlight-label">Staff Shoutout</div>
                    <div class="highlight-name">{{ highlights.staff_shoutout.name }}</div>
                    <div class="highlight-sub">
                        <span v-if="highlights.staff_shoutout.designation">{{ highlights.staff_shoutout.designation }} · </span>
                        {{ highlights.staff_shoutout.streak }}-day perfect streak
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance -->
        <div v-if="showSection('attendance') && attendance.class_section_table" class="card mb-3">
            <div class="card-header"><h2 class="card-title">Attendance — Class &amp; Section</h2></div>
            <div class="card-body" style="padding:0;">
                <table class="dmr-table" v-if="attendance.class_section_table.length">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Section</th>
                            <th class="num">Enrolled</th>
                            <th class="num">Present</th>
                            <th class="num">Absent</th>
                            <th class="num">Unmarked</th>
                            <th class="num">%</th>
                            <th>Bar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in attendance.class_section_table" :key="idx">
                            <td>{{ row.class }}</td>
                            <td>{{ row.section || '—' }}</td>
                            <td class="num">{{ row.enrolled }}</td>
                            <td class="num"><strong>{{ row.present }}</strong></td>
                            <td class="num">{{ row.absent }}</td>
                            <td class="num" :class="{ 'unmarked-warn': row.unmarked > 0 }">{{ row.unmarked }}</td>
                            <td class="num"><strong>{{ row.pct }}%</strong></td>
                            <td>
                                <div class="pct-bar-wrap">
                                    <div class="pct-bar" :style="{ width: Math.min(100, row.pct) + '%', background: pctBarColor(row.pct) }"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else style="padding:20px;text-align:center;color:#64748b;">
                    No attendance recorded for this date yet.
                </div>
            </div>
        </div>

        <!-- Unmarked classes -->
        <div v-if="showSection('attendance') && attendance.unmarked_classes?.length" class="card mb-3">
            <div class="card-header"><h2 class="card-title">Classes still pending attendance</h2></div>
            <div class="card-body" style="padding:0;">
                <table class="dmr-table">
                    <thead><tr><th>Class</th><th>Section</th><th class="num">Enrolled</th><th>Class Teacher</th></tr></thead>
                    <tbody>
                        <tr v-for="(u, i) in attendance.unmarked_classes" :key="i">
                            <td>{{ u.class }}</td>
                            <td>{{ u.section }}</td>
                            <td class="num">{{ u.enrolled }}</td>
                            <td>{{ u.teacher || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Staff attendance compact -->
        <div v-if="showSection('attendance') && attendance.staff" class="card mb-3">
            <div class="card-header"><h2 class="card-title">Staff Attendance</h2></div>
            <div class="card-body">
                <div class="staff-grid">
                    <div><div class="kpi-label">Total</div><div class="kpi-value">{{ attendance.staff.total }}</div></div>
                    <div><div class="kpi-label">Present</div><div class="kpi-value net-pos">{{ attendance.staff.present }}</div></div>
                    <div><div class="kpi-label">Absent</div><div class="kpi-value net-neg">{{ attendance.staff.absent }}</div></div>
                    <div><div class="kpi-label">Leave</div><div class="kpi-value">{{ attendance.staff.leave }}</div></div>
                    <div><div class="kpi-label">Unmarked</div><div class="kpi-value">{{ attendance.staff.unmarked }}</div></div>
                </div>

                <div class="staff-lists">
                    <div v-if="attendance.staff.absent_list?.length" class="staff-list">
                        <h3 class="card-subtitle">Absent / On Leave Today ({{ attendance.staff.absent_list.length }})</h3>
                        <ul class="staff-name-list">
                            <li v-for="(s, i) in attendance.staff.absent_list" :key="'a' + i">
                                <strong>{{ s.name }}</strong>
                                <span v-if="s.designation" class="muted"> · {{ s.designation }}</span>
                                <span class="status-badge" :class="`status-${s.status}`">{{ s.status }}</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="attendance.staff.unmarked_list?.length" class="staff-list">
                        <h3 class="card-subtitle">Unmarked Staff ({{ attendance.staff.unmarked_list.length }})</h3>
                        <ul class="staff-name-list">
                            <li v-for="(s, i) in attendance.staff.unmarked_list" :key="'u' + i">
                                <strong>{{ s.name }}</strong>
                                <span v-if="s.designation" class="muted"> · {{ s.designation }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fees -->
        <div v-if="showSection('fees') && fees.streams" class="card mb-3">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Fees Collected (Money In)</h2>
                <strong style="font-size:1.1rem;">{{ fmtMoneyFull(fees.total) }}</strong>
            </div>
            <div class="card-body">
                <div class="stream-grid">
                    <div class="stream-card" v-for="(s, key) in fees.streams" :key="key">
                        <div class="kpi-label">{{ key }}</div>
                        <div class="kpi-value">{{ fmtMoney(s.amount) }}</div>
                        <div class="muted">{{ s.count }} receipts</div>
                    </div>
                </div>

                <div v-if="fees.by_payment_mode?.length" style="margin-top:14px;">
                    <h3 class="card-subtitle">By Payment Mode</h3>
                    <table class="dmr-table">
                        <thead><tr><th>Mode</th><th class="num">Receipts</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            <tr v-for="(m, i) in fees.by_payment_mode" :key="i">
                                <td>{{ m.mode }}</td>
                                <td class="num">{{ m.count }}</td>
                                <td class="num">{{ fmtMoneyFull(m.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="fees.by_class?.length" style="margin-top:14px;">
                    <h3 class="card-subtitle">Top Classes</h3>
                    <table class="dmr-table">
                        <thead><tr><th>Class</th><th class="num">Receipts</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            <tr v-for="(c, i) in fees.by_class" :key="i">
                                <td>{{ c.class }}</td>
                                <td class="num">{{ c.count }}</td>
                                <td class="num">{{ fmtMoneyFull(c.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="fees.top_collectors?.length" style="margin-top:14px;">
                    <h3 class="card-subtitle">Top Collectors</h3>
                    <table class="dmr-table">
                        <thead><tr><th>Staff</th><th class="num">Receipts</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            <tr v-for="(t, i) in fees.top_collectors" :key="i">
                                <td>{{ t.name }}</td>
                                <td class="num">{{ t.count }}</td>
                                <td class="num">{{ fmtMoneyFull(t.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="fees.pending_dues?.amount" class="muted" style="margin-top:14px;">
                    Outstanding dues: <strong>{{ fmtMoneyFull(fees.pending_dues.amount) }}</strong>
                    across {{ fees.pending_dues.students }} students.
                </p>
            </div>
        </div>

        <!-- Expenses -->
        <div v-if="showSection('expenses') && expenses.by_category" class="card mb-3">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Expenses (Money Out)</h2>
                <strong style="font-size:1.1rem;">{{ fmtMoneyFull(expenses.total) }}</strong>
            </div>
            <div class="card-body">
                <div v-if="expenses.by_category.length">
                    <h3 class="card-subtitle">By Category</h3>
                    <table class="dmr-table">
                        <thead><tr><th>Category</th><th class="num">Vouchers</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            <tr v-for="(c, i) in expenses.by_category" :key="i">
                                <td>{{ c.category }}</td>
                                <td class="num">{{ c.count }}</td>
                                <td class="num">{{ fmtMoneyFull(c.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="muted" style="padding:8px 0;">No expenses recorded for this date.</div>

                <div v-if="expenses.top_vouchers?.length" style="margin-top:14px;">
                    <h3 class="card-subtitle">Top Vouchers</h3>
                    <table class="dmr-table">
                        <thead><tr><th>Title</th><th>Category</th><th>Mode</th><th class="num">Amount</th><th>Recorded By</th></tr></thead>
                        <tbody>
                            <tr v-for="(v, i) in expenses.top_vouchers" :key="i">
                                <td>{{ v.title }}</td>
                                <td>{{ v.category }}</td>
                                <td>{{ v.mode }}</td>
                                <td class="num">{{ fmtMoneyFull(v.amount) }}</td>
                                <td>{{ v.recorded_by }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cash flow -->
        <div v-if="showSection('cash') && cash" class="card mb-3">
            <div class="card-header"><h2 class="card-title">Cash Flow Today</h2></div>
            <div class="card-body cash-grid">
                <div>
                    <div class="kpi-label">Cash In</div>
                    <div class="kpi-value net-pos">{{ fmtMoneyFull(cash.cash_in) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Cash Out</div>
                    <div class="kpi-value net-neg">{{ fmtMoneyFull(cash.cash_out) }}</div>
                </div>
                <div>
                    <div class="kpi-label">Net Drawer Movement</div>
                    <div class="kpi-value" :class="cash.net >= 0 ? 'net-pos' : 'net-neg'">
                        {{ cash.net >= 0 ? '+' : '−' }}{{ fmtMoneyFull(Math.abs(cash.net)) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Admissions / Events -->
        <div class="dmr-row mb-3" v-if="showSection('admissions') || showSection('events')">
            <div v-if="showSection('admissions')" class="card" style="flex:1;">
                <div class="card-header"><h2 class="card-title">New Admissions Today</h2></div>
                <div class="card-body">
                    <p><strong>{{ admissions.count || 0 }}</strong> admission{{ admissions.count === 1 ? '' : 's' }}.</p>
                    <table v-if="admissions.students?.length" class="dmr-table" style="margin-top:8px;">
                        <thead><tr><th>Name</th><th>Adm #</th><th>Class</th><th>Section</th></tr></thead>
                        <tbody>
                            <tr v-for="(s, i) in admissions.students" :key="i">
                                <td>{{ s.name }}</td>
                                <td>{{ s.admission_no }}</td>
                                <td>{{ s.class }}</td>
                                <td>{{ s.section || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="showSection('events')" class="card" style="flex:1;">
                <div class="card-header"><h2 class="card-title">Day Events</h2></div>
                <div class="card-body">
                    <p v-if="events.visitors">
                        <strong>Visitors:</strong> {{ events.visitors.total }} total ·
                        {{ events.visitors.signed_out }} signed out ·
                        <span :class="{ 'net-neg': events.visitors.still_in > 0 }">{{ events.visitors.still_in }} still inside</span>
                    </p>
                    <p v-if="events.birthdays?.length"><strong>Birthdays:</strong>
                        {{ events.birthdays.map(b => b.name).join(', ') }}
                    </p>
                    <p v-if="events.holidays?.length"><strong>Holidays:</strong>
                        {{ events.holidays.map(h => h.title).join(', ') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Tomorrow's outlook -->
        <div v-if="showSection('outlook') && outlook" class="card mb-3">
            <div class="card-header"><h2 class="card-title">Tomorrow's Outlook · {{ outlook.date_label }}</h2></div>
            <div class="card-body">
                <p v-if="outlook.holidays?.length"><strong>Holidays:</strong> {{ outlook.holidays.join(', ') }}</p>
                <p v-if="outlook.birthdays > 0"><strong>Birthdays tomorrow:</strong> {{ outlook.birthdays }}</p>
                <p v-if="!(outlook.holidays?.length) && !outlook.birthdays" class="muted">Nothing special scheduled.</p>
            </div>
        </div>

        <!-- Delivery log -->
        <div v-if="deliveries.length" class="card mb-3">
            <div class="card-header"><h2 class="card-title">Delivery Log</h2></div>
            <div class="card-body" style="padding:0;">
                <table class="dmr-table">
                    <thead><tr><th>Recipient</th><th>Number</th><th>Channel</th><th>Sent At</th><th>Error</th></tr></thead>
                    <tbody>
                        <tr v-for="(d, i) in deliveries" :key="i">
                            <td>{{ d.admin_contact?.name || '—' }}</td>
                            <td>{{ d.to_number || '—' }}</td>
                            <td><span class="channel-pill" :class="channelBadge(d.channel_used)">{{ d.channel_used }}</span></td>
                            <td>{{ d.sent_at ? school.fmtDateTime(d.sent_at) : '—' }}</td>
                            <td class="muted">{{ d.error || '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.dmr-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
.dmr-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0; }
.dmr-sub { color: #64748b; margin: 4px 0 0; font-size: .9rem; }
.dmr-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.kpi-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 16px; }
.kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
.kpi-card.kpi-net { background: linear-gradient(135deg, #f0fdf4, #ffffff); }
.kpi-label { font-size: .7rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
.kpi-value { font-size: 1.4rem; font-weight: 700; color: #0f172a; margin-top: 2px; }
.kpi-deltas { font-size: .75rem; color: #64748b; margin-top: 4px; }
.delta-up { color: #16a34a; }
.delta-down { color: #dc2626; }
.delta-flat { color: #94a3b8; }
.net-pos { color: #16a34a; }
.net-neg { color: #dc2626; }

.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.card-header { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
.card-title { margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a; }
.card-subtitle { margin: 0 0 6px; font-size: .825rem; font-weight: 600; color: #475569; }
.card-body { padding: 14px 16px; }
.mb-3 { margin-bottom: 14px; }

.dmr-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.dmr-table thead th { text-align: left; padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: .72rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: .04em; }
.dmr-table tbody td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
.dmr-table tbody tr:last-child td { border-bottom: none; }
.dmr-table .num { text-align: right; }
.unmarked-warn { color: #dc2626; font-weight: 600; }
.pct-bar-wrap { width: 80px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; }
.pct-bar { height: 100%; }

.alert-row { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
.alert-row.alert-red { background: #fef2f2; border-left: 4px solid #dc2626; }
.alert-row.alert-amber { background: #fffbeb; border-left: 4px solid #f59e0b; }
.alert-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.alert-count { background: #fff; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 600; }
.alert-items { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { padding: 3px 9px; border-radius: 12px; font-size: .75rem; font-weight: 500; }
.chip-amber { background: #fef3c7; color: #92400e; }
.chip-red { background: #fee2e2; color: #991b1b; }

.highlight-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
.highlight { padding: 12px; border-radius: 8px; background: linear-gradient(135deg, #fef3c7, #fffbeb); border: 1px solid #fde68a; }
.highlight-label { font-size: .7rem; color: #92400e; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.highlight-name { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 4px 0; }
.highlight-sub { font-size: .8125rem; color: #78350f; }

.stream-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.stream-card { padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-transform: capitalize; }

.staff-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
.staff-lists { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f5f9; }
.staff-list .card-subtitle { margin-bottom: 6px; }
.staff-name-list { list-style: none; padding: 0; margin: 0; max-height: 240px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 6px; }
.staff-name-list li { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; font-size: .82rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.staff-name-list li:last-child { border-bottom: none; }
.status-badge { margin-left: auto; padding: 2px 8px; border-radius: 10px; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.status-absent { background: #fee2e2; color: #991b1b; }
.status-leave  { background: #fef3c7; color: #92400e; }

.cash-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-items: center; }

.dmr-row { display: flex; gap: 14px; flex-wrap: wrap; }
.muted { color: #64748b; font-size: .85rem; }

.channel-pill { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: .72rem; font-weight: 600; text-transform: uppercase; }
.channel-whatsapp { background: #d1fae5; color: #065f46; }
.channel-sms { background: #dbeafe; color: #1e40af; }
.channel-failed { background: #fee2e2; color: #991b1b; }
</style>
