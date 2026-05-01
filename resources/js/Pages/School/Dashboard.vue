<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useSchoolStore } from '@/stores/useSchoolStore'

// ── Sandbox primitives (Components/ui/*) ──────────────────────────
import PageHeader from '@/Components/ui/PageHeader.vue'
import StatsRow   from '@/Components/ui/StatsRow.vue'
import Tabs       from '@/Components/ui/Tabs.vue'
import EmptyState from '@/Components/ui/EmptyState.vue'
import Button     from '@/Components/ui/Button.vue'

// ── Dashboard-only (charts + calendar — no kit equivalent) ────────
import TrendChart   from '@/Components/dashboard/TrendChart.vue'
import DonutChart   from '@/Components/dashboard/DonutChart.vue'
import MiniCalendar from '@/Components/dashboard/MiniCalendar.vue'
import RecentList   from '@/Components/dashboard/RecentList.vue'

const props = defineProps({
    school: Object,
    school_dashboard: { type: Object, default: () => ({}) },
})

const school = useSchoolStore()
const d   = computed(() => props.school_dashboard || {})
const k   = computed(() => d.value.kpi || {})
const currency = computed(() => school.currency)

// ── formatting ─────────────────────────────────────────────
const fmtNum = (n) => Number.isFinite(+n) ? (+n).toLocaleString('en-IN') : '—'
const fmtCur = (n) => Number.isFinite(+n) ? school.fmtMoney(n) : '—'
const fmtCompact = (n) => {
    n = Math.round(+n || 0)
    if (n >= 1e7) return currency.value + (n / 1e7).toFixed(1) + 'Cr'
    if (n >= 1e5) return currency.value + (n / 1e5).toFixed(1) + 'L'
    if (n >= 1e3) return currency.value + (n / 1e3).toFixed(1) + 'k'
    return currency.value + n
}
const greeting = computed(() => {
    const h = new Date().getHours()
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'
})
const todayLabel = computed(() => school.fmtDate(school.today()))
const monthLabel = new Date().toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })

// ── view toggles ───────────────────────────────────────────
const financeView    = ref('rvp')
const cashflowView   = ref('income')
const attendanceView = ref('students')
const activityTab    = ref('payments')

// ── chart data ─────────────────────────────────────────────
const fee       = computed(() => d.value.fee_summary || {})
const cstr      = computed(() => d.value.course_strength || [])
const rvp       = computed(() => d.value.receipt_vs_payment || [])
const csum      = computed(() => d.value.course_summary || [])
const income    = computed(() => d.value.month_income || [])
const expense   = computed(() => d.value.month_expense || [])
const classAtt  = computed(() => d.value.class_attendance || [])
const feeMix    = computed(() => d.value.fee_mix_today || {})
const donut     = computed(() => d.value.attendance_donut || {})

const palette = ['#10b981', '#6366f1', '#f59e0b', '#3b82f6', '#ec4899', '#8b5cf6', '#84cc16', '#06b6d4']

const courseStrengthChart = computed(() => ({
    labels: cstr.value.map(c => c.class),
    datasets: [{ label: 'Student strength', data: cstr.value.map(c => c.count), color: '#06b6d4' }],
}))
const receiptVsPaymentChart = computed(() => ({
    labels: rvp.value.map(r => r.short),
    datasets: [
        { label: 'Receipt', data: rvp.value.map(r => r.receipt), color: '#f59e0b' },
        { label: 'Payment', data: rvp.value.map(r => r.payment), color: '#6366f1' },
    ],
}))
const courseSummaryChart = computed(() => ({
    labels: csum.value.map(c => c.class),
    datasets: [
        { label: 'Total',      data: csum.value.map(c => c.total),      color: '#6366f1' },
        { label: 'Paid',       data: csum.value.map(c => c.paid),       color: '#10b981' },
        { label: 'Balance',    data: csum.value.map(c => c.balance),    color: '#ef4444' },
        { label: 'Concession', data: csum.value.map(c => c.concession), color: '#f59e0b' },
    ],
}))
const classAttChart = computed(() => ({
    labels: classAtt.value.map(c => c.class),
    datasets: [{ label: 'Attendance', data: classAtt.value.map(c => c.pct), color: '#10b981' }],
}))

const incomeSegments  = computed(() => income.value.map((row, i)  => ({ label: row.name, value: row.amount, color: palette[i % palette.length] })))
const expenseSegments = computed(() => expense.value.map((row, i) => ({ label: row.name, value: row.amount, color: palette[i % palette.length] })))
const incomeTotal     = computed(() => income.value.reduce((s, x)  => s + (+x.amount || 0), 0))
const expenseTotal    = computed(() => expense.value.reduce((s, x) => s + (+x.amount || 0), 0))

const feeMixSegments = computed(() => {
    const segments = [
        { label: 'Tuition',    value: feeMix.value.tuition    || 0, color: '#6366f1' },
    ]
    if (school.hasFeature('transport')) {
        segments.push({ label: 'Transport', value: feeMix.value.transport || 0, color: '#10b981' })
    }
    if (school.hasFeature('hostel')) {
        segments.push({ label: 'Hostel', value: feeMix.value.hostel || 0, color: '#f59e0b' })
    }
    segments.push({ label: 'Stationary', value: feeMix.value.stationary || 0, color: '#3b82f6' })
    return segments
})
const feeMixTotal   = computed(() => feeMixSegments.value.reduce((s, x) => s + x.value, 0))
const feeMixHasData = computed(() => feeMixTotal.value > 0)

// ── attendance list rows (Student & Staff share the same shape) ─
function buildAttendanceRows(src, total) {
    const t = +total || 0
    const rows = [
        { label: 'Present',  count: +src.present  || 0, color: '#10b981' },
        { label: 'Absent',   count: +src.absent   || 0, color: '#ef4444' },
        { label: 'Half Day', count: +src.half_day || 0, color: '#f97316' },
        { label: 'Late',     count: +src.late     || 0, color: '#f59e0b' },
        { label: 'On Leave', count: +src.leave    || 0, color: '#8b5cf6' },
    ]
    return rows.map(r => ({ ...r, total: t, pct: t > 0 ? Math.min(100, Math.round(r.count / t * 100)) : 0 }))
}
const attendanceRows = computed(() => buildAttendanceRows(donut.value, k.value.total_students))
const attendanceMarked = computed(() => attendanceRows.value.reduce((s, r) => s + r.count, 0))
const staffAtt = computed(() => d.value.staff_attendance || {})
const staffAttendanceRows = computed(() => buildAttendanceRows(staffAtt.value, k.value.total_staff))
const staffAttendanceMarked = computed(() => staffAttendanceRows.value.reduce((s, r) => s + r.count, 0))

// ── KPI rows for <StatsRow> ────────────────────────────────
const heroStats = computed(() => [
    {
        label: "Today's Collection",
        value: fmtCompact(k.value.today_fee || 0),
        sub:   `${fmtCompact(k.value.month_fee || 0)} this month`,
        color: 'success',
        trend: k.value.today_fee_delta_pct ?? null,
    },
    {
        label: 'Attendance Today',
        value: `${k.value.attendance_pct ?? 0}%`,
        sub:   `${fmtNum(k.value.present_today)} of ${fmtNum(k.value.attendance_marked)} marked`,
        color: 'accent',
        trend: k.value.attendance_delta_pp ?? null,
    },
    {
        label: 'New Admissions This Month',
        value: fmtNum(k.value.new_students_month),
        sub:   `${fmtNum(k.value.total_students)} total students`,
        color: 'info',
        trend: k.value.new_students_delta_pct ?? null,
    },
    {
        label: 'Pending Fees',
        value: fmtCompact(k.value.pending_fees || 0),
        sub:   `${fmtNum(k.value.pending_fee_count)} students owe`,
        color: 'danger',
    },
])

const hostelPct = computed(() => {
    const cap = +k.value.hostel_capacity || 0
    if (!cap) return 0
    return Math.round((+k.value.hostel_occupied || 0) / cap * 100)
})

const secondaryStats = computed(() => [
    {
        label: 'Staff Active',
        value: fmtNum(k.value.total_staff),
        sub:   `${fmtNum(k.value.staff_present_today)} present · ${fmtNum(k.value.staff_on_leave)} on leave`,
        color: 'purple',
    },
    {
        label: 'Classes & Sections',
        value: `${fmtNum(k.value.total_classes)} / ${fmtNum(k.value.total_sections)}`,
        sub:   'Class · section count',
        color: 'accent',
    },
    {
        label: 'Active Routes',
        value: fmtNum(k.value.active_routes),
        sub:   'Routes running',
        color: 'warning',
    },
    {
        label: 'Hostel Occupancy',
        value: `${hostelPct.value}%`,
        sub:   `${fmtNum(k.value.hostel_occupied)} of ${fmtNum(k.value.hostel_capacity)} beds`,
        color: 'pink',
    },
])

// ── recent activity & alerts ───────────────────────────────
const recentPayments    = computed(() => d.value.recent_payments    || [])
const recentAdmissions  = computed(() => d.value.recent_admissions  || [])
const todayVisitors     = computed(() => d.value.today_visitors     || [])
const pendingFeeStudents = computed(() => d.value.pending_fee_students || [])
const lowAttendance     = computed(() => d.value.low_attendance || [])
const birthdays         = computed(() => d.value.birthdays_today || [])
const absentStaff       = computed(() => d.value.absent_staff || [])
const announcements     = computed(() => d.value.announcements || [])
const nextExam          = computed(() => d.value.next_exam || null)

const showAlerts = computed(() => pendingFeeStudents.value.length || lowAttendance.value.length || announcements.value.length || absentStaff.value.length || birthdays.value.length)

// ── upcoming events list ───────────────────────────────────
const upcomingEvents = computed(() => {
    const holidays = (d.value.upcoming_holidays || []).map(h => ({ kind: 'holiday', date: h.date, title: h.title }))
    const exams    = (d.value.calendar_exams    || []).map(e => ({ kind: 'exam',    date: e.date, title: e.title }))
    return [...holidays, ...exams]
        .sort((a, b) => (a.date || '').localeCompare(b.date || ''))
        .slice(0, 6)
        .map(e => ({
            ...e,
            dateLabel: school.fmtDate(e.date),
            daysAway: Math.max(0, Math.round((new Date(e.date) - new Date()) / 86400000)),
        }))
})
</script>

<template>
    <div class="dashboard">

        <!-- ─ Greeting / page header ──────────────────────────────── -->
        <PageHeader :subtitle="todayLabel" compact>
            <template #title>
                {{ greeting }},
                <span style="color: var(--accent);">{{ d.admin_name || 'Admin' }}</span>
            </template>
            <template #actions>
                <Button
                    v-if="nextExam"
                    as="link"
                    href="/school/exam-schedules"
                    variant="secondary"
                    size="sm"
                >
                    Next exam: {{ nextExam.title }} · in {{ nextExam.days_left }}d
                </Button>
            </template>
        </PageHeader>

        <!-- ─ Action chips (only-if-data) ─────────────────────────── -->
        <div
            v-if="k.attendance_unmarked > 0 || d.pending_edit_count > 0 || d.pending_leave_count > 0 || k.pending_fee_count > 0 || k.staff_unmarked_today > 0"
            class="dashboard-chips"
        >
            <Button v-if="k.attendance_unmarked > 0"  as="link" href="/school/attendance"            variant="warning" size="sm">{{ k.attendance_unmarked }} students unmarked today</Button>
            <Button v-if="k.staff_unmarked_today > 0" as="link" href="/school/staff-attendance"      variant="secondary" size="sm">{{ k.staff_unmarked_today }} staff unmarked today</Button>
            <Button v-if="d.pending_edit_count > 0"   as="link" href="/school/edit-requests"          variant="warning" size="sm">{{ d.pending_edit_count }} edit requests pending</Button>
            <Button v-if="d.pending_leave_count > 0"  as="link" href="/school/leaves"                 variant="secondary" size="sm">{{ d.pending_leave_count }} leave requests pending</Button>
            <Button v-if="k.pending_fee_count > 0"    as="link" href="/school/finance/due-report"     variant="danger" size="sm">{{ k.pending_fee_count }} students with pending fees</Button>
        </div>

        <!-- ─ Hero KPIs ───────────────────────────────────────────── -->
        <StatsRow :cols="4" :stats="heroStats" />

        <!-- ─ Secondary KPIs ──────────────────────────────────────── -->
        <StatsRow :cols="4" :stats="secondaryStats" />

        <!-- ─ Fee Summary + Course-wise Strength ──────────────────── -->
        <section class="dashboard-grid dashboard-grid-3">

            <!-- Fee Summary -->
            <div class="card span-1">
                <div class="card-header">
                    <span class="card-title">Fee Summary</span>
                    <Link href="/school/finance/due-report" class="card-link">Report →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">Academic year · all fee streams</p>

                    <div class="fee-row">
                        <div class="fee-row-head">
                            <span class="fee-row-label">Paid</span>
                            <span class="fee-row-value">
                                {{ fmtCur(fee.paid || 0) }} <span class="fee-row-total">/ {{ fmtCur(fee.total || 0) }}</span>
                            </span>
                        </div>
                        <div class="fee-bar"><div class="fee-bar-fill" style="background:#f59e0b" :style="{ width: Math.min(100, fee.paid_pct || 0) + '%', background: '#f59e0b' }"></div></div>
                    </div>

                    <div class="fee-row">
                        <div class="fee-row-head">
                            <span class="fee-row-label">Balance</span>
                            <span class="fee-row-value">
                                {{ fmtCur(fee.balance || 0) }} <span class="fee-row-total">/ {{ fmtCur(fee.total || 0) }}</span>
                            </span>
                        </div>
                        <div class="fee-bar"><div class="fee-bar-fill" :style="{ width: Math.min(100, fee.balance_pct || 0) + '%', background: '#3b82f6' }"></div></div>
                    </div>

                    <div class="fee-row">
                        <div class="fee-row-head">
                            <span class="fee-row-label">Concession</span>
                            <span class="fee-row-value">{{ fmtCur(fee.concession || 0) }}</span>
                        </div>
                        <div class="fee-bar"><div class="fee-bar-fill" :style="{ width: Math.min(100, fee.concession_pct || 0) + '%', background: '#10b981' }"></div></div>
                    </div>

                    <div class="fee-period">
                        <div><span>Today's collection</span><b>{{ fmtCur(fee.today_collection || 0) }}</b></div>
                        <div><span>This week</span><b>{{ fmtCur(fee.week_collection || 0) }}</b></div>
                        <div><span>This month</span><b>{{ fmtCur(fee.month_collection || 0) }}</b></div>
                    </div>
                </div>
            </div>

            <!-- Course-wise strength -->
            <div class="card span-2">
                <div class="card-header">
                    <span class="card-title">Course-wise Strength</span>
                    <Link href="/school/students" class="card-link">All students →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">Active students per class, this academic year</p>
                    <TrendChart
                        v-if="cstr.length"
                        type="bar"
                        :labels="courseStrengthChart.labels"
                        :datasets="courseStrengthChart.datasets"
                        :height="260"
                    />
                    <EmptyState v-else variant="compact" tone="muted" title="No class enrolment data yet" />
                </div>
            </div>
        </section>

        <!-- ─ Receipt vs Payment / Course-wise summary toggle ─────── -->
        <div class="card">
            <div class="card-body">
                <Tabs v-model="financeView" :tabs="[
                    { key: 'rvp',  label: 'Receipt vs Payment' },
                    { key: 'csum', label: 'Course-wise Summary' },
                ]">
                    <template #tab-rvp>
                        <p class="dashboard-meta">Money in vs money out, last 12 months</p>
                        <TrendChart
                            v-if="rvp.length"
                            type="bar"
                            :labels="receiptVsPaymentChart.labels"
                            :datasets="receiptVsPaymentChart.datasets"
                            :currency="currency"
                            :legend="true"
                            :height="290"
                        />
                        <EmptyState v-else variant="compact" tone="muted" title="No financial activity yet" />
                        <p class="dashboard-link-row"><Link href="/school/finance/day-book" class="card-link">Day book →</Link></p>
                    </template>

                    <template #tab-csum>
                        <p class="dashboard-meta">Total · Paid · Balance · Concession per class</p>
                        <TrendChart
                            v-if="csum.length"
                            type="bar"
                            :labels="courseSummaryChart.labels"
                            :datasets="courseSummaryChart.datasets"
                            :currency="currency"
                            :legend="true"
                            :height="290"
                        />
                        <EmptyState v-else variant="compact" tone="muted" title="No fee allocations for any class yet" />
                        <p class="dashboard-link-row"><Link href="/school/finance/due-report" class="card-link">Due report →</Link></p>
                    </template>
                </Tabs>
            </div>
        </div>

        <!-- ─ Cash flow + Attendance toggles ──────────────────────── -->
        <section class="dashboard-grid dashboard-grid-2">

            <!-- Cash flow: Income / Expense -->
            <div class="card">
                <div class="card-body">
                    <Tabs v-model="cashflowView" :tabs="[
                        { key: 'income',  label: `Income — ${monthLabel}` },
                        { key: 'expense', label: `Expense — ${monthLabel}` },
                    ]">
                        <template #tab-income>
                            <p class="dashboard-meta">Non-fee income from ledger</p>
                            <DonutChart
                                v-if="incomeSegments.length"
                                :segments="incomeSegments" :semi="true"
                                :height="180"
                                :centerValue="fmtCompact(incomeTotal)"
                                centerLabel="this month"
                            />
                            <EmptyState v-else variant="compact" tone="muted" title="No non-fee income recorded" />
                            <ul v-if="incomeSegments.length" class="dashboard-legend">
                                <li v-for="seg in incomeSegments" :key="seg.label">
                                    <span class="dashboard-legend-dot" :style="{ background: seg.color }" />
                                    <span class="dashboard-legend-label">{{ seg.label }}</span>
                                    <span class="dashboard-legend-value">{{ fmtCompact(seg.value) }}</span>
                                </li>
                            </ul>
                            <p class="dashboard-link-row"><Link href="/school/finance/transactions" class="card-link">Ledger →</Link></p>
                        </template>

                        <template #tab-expense>
                            <p class="dashboard-meta">Spending by category</p>
                            <DonutChart
                                v-if="expenseSegments.length"
                                :segments="expenseSegments" :semi="true"
                                :height="180"
                                :centerValue="fmtCompact(expenseTotal)"
                                centerLabel="this month"
                            />
                            <EmptyState v-else variant="compact" tone="muted" title="No expenses recorded" />
                            <ul v-if="expenseSegments.length" class="dashboard-legend">
                                <li v-for="seg in expenseSegments" :key="seg.label">
                                    <span class="dashboard-legend-dot" :style="{ background: seg.color }" />
                                    <span class="dashboard-legend-label">{{ seg.label }}</span>
                                    <span class="dashboard-legend-value">{{ fmtCompact(seg.value) }}</span>
                                </li>
                            </ul>
                            <p class="dashboard-link-row"><Link href="/school/expenses" class="card-link">All expenses →</Link></p>
                        </template>
                    </Tabs>
                </div>
            </div>

            <!-- Attendance: Students / Staff -->
            <div class="card">
                <div class="card-body">
                    <Tabs v-model="attendanceView" :tabs="[
                        { key: 'students', label: 'Student Attendance' },
                        { key: 'staff',    label: 'Staff Attendance' },
                    ]">
                        <template #tab-students>
                            <p class="dashboard-meta">{{ attendanceMarked }} of {{ k.total_students || 0 }} marked today</p>
                            <ul class="att-list">
                                <li v-for="row in attendanceRows" :key="row.label">
                                    <div class="att-list-head">
                                        <span class="att-list-label">{{ row.label }}</span>
                                        <span class="att-list-count">
                                            <b>{{ row.count }}</b><span class="att-list-total"> / {{ row.total }}</span>
                                        </span>
                                    </div>
                                    <div class="att-list-bar"><div class="att-list-bar-fill" :style="{ width: row.pct + '%', background: row.color }"></div></div>
                                </li>
                            </ul>
                            <p class="dashboard-link-row"><Link href="/school/attendance/report" class="card-link">Report →</Link></p>
                        </template>

                        <template #tab-staff>
                            <p class="dashboard-meta">{{ staffAttendanceMarked }} of {{ k.total_staff || 0 }} marked today</p>
                            <ul class="att-list">
                                <li v-for="row in staffAttendanceRows" :key="row.label">
                                    <div class="att-list-head">
                                        <span class="att-list-label">{{ row.label }}</span>
                                        <span class="att-list-count">
                                            <b>{{ row.count }}</b><span class="att-list-total"> / {{ row.total }}</span>
                                        </span>
                                    </div>
                                    <div class="att-list-bar"><div class="att-list-bar-fill" :style="{ width: row.pct + '%', background: row.color }"></div></div>
                                </li>
                            </ul>
                            <p class="dashboard-link-row"><Link href="/school/staff-attendance/report" class="card-link">Report →</Link></p>
                        </template>
                    </Tabs>
                </div>
            </div>
        </section>

        <!-- ─ Class-wise attendance + Fee mix today ───────────────── -->
        <section class="dashboard-grid dashboard-grid-3">
            <div class="card span-2">
                <div class="card-header">
                    <span class="card-title">Class-wise Attendance</span>
                    <Link href="/school/attendance/report" class="card-link">Report →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">Today, percent present</p>
                    <TrendChart
                        v-if="classAtt.length"
                        type="bar"
                        :labels="classAttChart.labels"
                        :datasets="classAttChart.datasets"
                        :yPercent="true"
                        :height="240"
                    />
                    <EmptyState v-else variant="compact" tone="muted" title="No class attendance yet" />
                </div>
            </div>

            <div class="card span-1">
                <div class="card-header">
                    <span class="card-title">Fee Mix Today</span>
                    <Link href="/school/fee/collect" class="card-link">Collect →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">{{ feeMixHasData ? `${fmtCur(feeMixTotal)} collected` : 'No collections yet today' }}</p>
                    <DonutChart
                        v-if="feeMixHasData"
                        :segments="feeMixSegments"
                        :height="180"
                        :centerValue="fmtCompact(feeMixTotal)"
                        centerLabel="today"
                    />
                    <EmptyState v-else variant="compact" tone="muted" title="No collections yet today" description="Open Fee → Collect to record a payment" />
                    <ul v-if="feeMixHasData" class="dashboard-legend">
                        <li v-for="seg in feeMixSegments" :key="seg.label">
                            <span class="dashboard-legend-dot" :style="{ background: seg.color }" />
                            <span class="dashboard-legend-label">{{ seg.label }}</span>
                            <span class="dashboard-legend-value">{{ fmtCur(seg.value) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ─ Recent activity + Mini calendar ─────────────────────── -->
        <section class="dashboard-grid dashboard-grid-3">
            <div class="card span-2">
                <div class="card-body">
                    <Tabs v-model="activityTab" :tabs="[
                        { key: 'payments',   label: 'Payments',   count: recentPayments.length },
                        { key: 'admissions', label: 'Admissions', count: recentAdmissions.length },
                        { key: 'visitors',   label: 'Visitors',   count: todayVisitors.length },
                    ]">
                        <template #tab-payments>
                            <RecentList :rows="recentPayments" emptyText="No payments today">
                                <template #primary="{ row }">{{ row.student }}</template>
                                <template #secondary="{ row }">{{ row.fee_head }} · {{ row.mode }} · {{ row.paid_at }}</template>
                                <template #right="{ row }">{{ fmtCur(row.amount) }}</template>
                            </RecentList>
                        </template>
                        <template #tab-admissions>
                            <RecentList :rows="recentAdmissions" avatarKey="photo_url" emptyText="No recent admissions">
                                <template #primary="{ row }">{{ row.name }}</template>
                                <template #secondary="{ row }">
                                    Adm. {{ row.admission_no }} · {{ row.class }}{{ row.section ? ' · ' + row.section : '' }} · {{ row.admitted_at }}
                                </template>
                            </RecentList>
                        </template>
                        <template #tab-visitors>
                            <RecentList :rows="todayVisitors" emptyText="No visitors today">
                                <template #primary="{ row }">{{ row.name }}</template>
                                <template #secondary="{ row }">{{ row.purpose || '—' }}</template>
                                <template #right="{ row }">{{ row.in_time }}{{ row.out ? ' → ' + row.out : '' }}</template>
                            </RecentList>
                        </template>
                    </Tabs>
                </div>
            </div>

            <MiniCalendar :holidays="d.upcoming_holidays || []" :exams="d.calendar_exams || []" />
        </section>

        <!-- ─ Alerts + People (auto-fit) ──────────────────────────── -->
        <section v-if="showAlerts" class="dashboard-grid-auto">
            <div v-if="pendingFeeStudents.length" class="card">
                <div class="card-header">
                    <span class="card-title">Top Pending Fees</span>
                    <Link href="/school/finance/due-report" class="card-link">All →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">Largest outstanding balances</p>
                    <RecentList :rows="pendingFeeStudents">
                        <template #primary="{ row }">{{ row.student }}</template>
                        <template #right="{ row }"><span style="color: var(--danger)">{{ fmtCur(row.balance) }}</span></template>
                    </RecentList>
                </div>
            </div>

            <div v-if="lowAttendance.length" class="card">
                <div class="card-header">
                    <span class="card-title">Low Attendance</span>
                    <Link href="/school/attendance/report" class="card-link">Report →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">Students under 75% this year</p>
                    <RecentList :rows="lowAttendance">
                        <template #primary="{ row }">{{ row.student }}</template>
                        <template #right="{ row }">
                            <span :class="row.percentage < 60 ? 'badge badge-red' : 'badge badge-amber'">{{ row.percentage }}%</span>
                        </template>
                    </RecentList>
                </div>
            </div>

            <div v-if="announcements.length" class="card">
                <div class="card-header">
                    <span class="card-title">Recent Announcements</span>
                    <Link href="/school/announcements" class="card-link">All →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">Latest broadcasts</p>
                    <RecentList :rows="announcements">
                        <template #primary="{ row }">{{ row.title }}</template>
                        <template #secondary="{ row }">
                            <span style="text-transform: capitalize;">{{ row.audience || 'all' }}</span> · by {{ row.sender }}
                        </template>
                        <template #right="{ row }">
                            <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 400;">{{ row.sent_at }}</span>
                        </template>
                    </RecentList>
                </div>
            </div>

            <div v-if="absentStaff.length" class="card">
                <div class="card-header">
                    <span class="card-title">Absent / On Leave</span>
                    <Link href="/school/leaves" class="card-link">Leaves →</Link>
                </div>
                <div class="card-body">
                    <p class="dashboard-meta">Staff away today</p>
                    <RecentList :rows="absentStaff" avatarKey="photo">
                        <template #primary="{ row }">{{ row.name }}</template>
                        <template #secondary="{ row }">{{ row.designation }}</template>
                    </RecentList>
                </div>
            </div>

            <div v-if="birthdays.length" class="card">
                <div class="card-header">
                    <span class="card-title">🎂 Birthdays Today</span>
                    <span class="card-meta">{{ birthdays.length }} celebrating</span>
                </div>
                <div class="card-body">
                    <div class="birthday-grid">
                        <div v-for="(b, i) in birthdays" :key="i" class="birthday-card">
                            <img v-if="b.photo" :src="b.photo" class="birthday-avatar" />
                            <div v-else class="birthday-avatar birthday-avatar-fallback">{{ b.name.charAt(0) }}</div>
                            <div class="birthday-meta">
                                <p class="birthday-name" :title="b.name">{{ b.name }}</p>
                                <p class="birthday-class">
                                    <template v-if="b.class && b.class !== '—'">
                                        {{ b.class }}<span v-if="b.section"> · {{ b.section }}</span>
                                    </template>
                                    <template v-else>Adm. {{ b.admission_no || '—' }}</template>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─ Upcoming events ─────────────────────────────────────── -->
        <section v-if="upcomingEvents.length" class="card">
            <div class="card-header">
                <span class="card-title">Upcoming Events</span>
                <Link href="/school/academic/calendar" class="card-link">Calendar →</Link>
            </div>
            <div class="card-body">
                <div class="event-grid">
                    <div v-for="(e, i) in upcomingEvents" :key="i" class="event-card">
                        <span class="event-kind" :class="`event-kind-${e.kind}`">{{ e.kind }}</span>
                        <span class="event-title">{{ e.title }}</span>
                        <div class="event-meta">
                            <span>{{ e.dateLabel }}</span>
                            <span>{{ e.daysAway === 0 ? 'today' : 'in ' + e.daysAway + 'd' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>
</template>

<style scoped>
/* ── Layout ────────────────────────────────────────────────── */
.dashboard {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.dashboard-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.dashboard-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: 1fr;
}
.dashboard-grid-auto {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}
.span-1, .span-2 { grid-column: auto; }

@media (min-width: 1024px) {
    .dashboard-grid-2 { grid-template-columns: repeat(2, 1fr); }
    .dashboard-grid-3 { grid-template-columns: repeat(3, 1fr); }
    .span-1 { grid-column: span 1; }
    .span-2 { grid-column: span 2; }
}

/* ── In-card meta + link styles ───────────────────────────── */
.dashboard-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 12px;
}
.dashboard-link-row {
    margin-top: 12px;
    text-align: right;
}
.card-link {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--accent);
    text-decoration: none;
    white-space: nowrap;
}
.card-link:hover { color: var(--accent-dark); text-decoration: underline; }
.card-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* ── Fee Summary card ─────────────────────────────────────── */
.fee-row { margin-bottom: 14px; }
.fee-row-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 6px;
    font-size: 0.75rem;
}
.fee-row-label { font-weight: 600; color: var(--text-secondary); }
.fee-row-value { font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.fee-row-total { color: var(--text-muted); font-weight: 400; }
.fee-bar {
    height: 6px;
    background: var(--border-light);
    border-radius: 999px;
    overflow: hidden;
}
.fee-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s;
}
.fee-period {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--border-light);
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.fee-period > div {
    display: flex;
    justify-content: space-between;
    font-size: 0.8125rem;
    color: var(--text-secondary);
}
.fee-period > div b { color: var(--text-primary); font-variant-numeric: tabular-nums; }

/* ── Attendance list (Student / Staff toggle bodies) ─────── */
.att-list {
    list-style: none;
    margin: 0; padding: 0;
}
.att-list li {
    padding: 10px 0;
    border-bottom: 1px solid var(--border-light);
}
.att-list li:last-child { border-bottom: none; padding-bottom: 0; }
.att-list-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 6px;
}
.att-list-label { font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary); }
.att-list-count { font-size: 0.75rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.att-list-count b { color: var(--text-primary); font-weight: 700; }
.att-list-total { color: var(--text-muted); }
.att-list-bar { height: 4px; background: var(--border-light); border-radius: 999px; overflow: hidden; }
.att-list-bar-fill { height: 100%; border-radius: 999px; transition: width 0.3s; }

/* ── Donut legend (Income / Expense / Fee Mix) ───────────── */
.dashboard-legend {
    list-style: none;
    margin: 12px 0 0;
    padding: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 12px;
    font-size: 0.75rem;
}
.dashboard-legend li {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}
.dashboard-legend-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dashboard-legend-label {
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dashboard-legend-value {
    margin-left: auto;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* ── Birthday cards ───────────────────────────────────────── */
.birthday-grid {
    display: grid;
    gap: 8px;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}
.birthday-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 10px;
    background: #fdf2f8;
    border: 1px solid #fbcfe8;
    min-width: 0;
}
.birthday-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 0 0 2px #fff;
}
.birthday-avatar-fallback {
    background: #fbcfe8;
    color: #9d174d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 700;
}
.birthday-meta { min-width: 0; flex: 1; }
.birthday-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.birthday-class {
    font-size: 0.7rem;
    font-weight: 500;
    color: #be185d;
    margin: 2px 0 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* ── Upcoming events ──────────────────────────────────────── */
.event-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}
.event-card {
    display: flex;
    flex-direction: column;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid var(--border);
    transition: border-color 0.15s;
}
.event-card:hover { border-color: var(--accent-light); }
.event-kind {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.event-kind-holiday { color: var(--danger); }
.event-kind-exam    { color: var(--accent); }
.event-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-top: 4px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.event-meta {
    display: flex;
    justify-content: space-between;
    align-items: end;
    margin-top: 8px;
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
</style>
