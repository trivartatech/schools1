<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import KpiCard       from '@/Components/dashboard/KpiCard.vue'
import TrendChart    from '@/Components/dashboard/TrendChart.vue'
import DonutChart    from '@/Components/dashboard/DonutChart.vue'
import MiniCalendar  from '@/Components/dashboard/MiniCalendar.vue'
import RecentList    from '@/Components/dashboard/RecentList.vue'
import SectionHeader from '@/Components/dashboard/SectionHeader.vue'
import ActionChip    from '@/Components/dashboard/ActionChip.vue'

const props = defineProps({
    school: Object,
    school_dashboard: { type: Object, default: () => ({}) },
})

const d   = computed(() => props.school_dashboard || {})
const k   = computed(() => d.value.kpi || {})
const spk = computed(() => d.value.sparklines || {})
const currency = computed(() => usePage().props.school?.currency || '₹')

// ── formatting ─────────────────────────────────────────────
const fmtNum = (n) => Number.isFinite(+n) ? (+n).toLocaleString('en-IN') : '—'
const fmtCur = (n) => Number.isFinite(+n) ? currency.value + (+n).toLocaleString('en-IN', { maximumFractionDigits: 0 }) : '—'
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
const todayLabel = new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const monthLabel = new Date().toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })

// ── chart palettes ─────────────────────────────────────────
const palette = ['#10b981', '#6366f1', '#f59e0b', '#3b82f6', '#ec4899', '#8b5cf6', '#84cc16', '#06b6d4']

// ── chart data ─────────────────────────────────────────────
const fee     = computed(() => d.value.fee_summary || {})
const cstr    = computed(() => d.value.course_strength || [])
const rvp     = computed(() => d.value.receipt_vs_payment || [])
const csum    = computed(() => d.value.course_summary || [])
const income  = computed(() => d.value.month_income || [])
const expense = computed(() => d.value.month_expense || [])
const classAtt = computed(() => d.value.class_attendance || [])
const feeMix   = computed(() => d.value.fee_mix_today || {})
const donut    = computed(() => d.value.attendance_donut || {})

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

const incomeSegments = computed(() =>
    income.value.map((row, i) => ({ label: row.name, value: row.amount, color: palette[i % palette.length] }))
)
const expenseSegments = computed(() =>
    expense.value.map((row, i) => ({ label: row.name, value: row.amount, color: palette[i % palette.length] }))
)
const incomeTotal  = computed(() => income.value.reduce((s, x) => s + (+x.amount || 0), 0))
const expenseTotal = computed(() => expense.value.reduce((s, x) => s + (+x.amount || 0), 0))

const classAttChart = computed(() => ({
    labels: classAtt.value.map(c => c.class),
    datasets: [{ label: 'Attendance', data: classAtt.value.map(c => c.pct), color: '#10b981' }],
}))

const feeMixSegments = computed(() => [
    { label: 'Tuition',    value: feeMix.value.tuition    || 0, color: '#6366f1' },
    { label: 'Transport',  value: feeMix.value.transport  || 0, color: '#10b981' },
    { label: 'Hostel',     value: feeMix.value.hostel     || 0, color: '#f59e0b' },
    { label: 'Stationary', value: feeMix.value.stationary || 0, color: '#3b82f6' },
])
const feeMixTotal   = computed(() => feeMixSegments.value.reduce((s, x) => s + x.value, 0))
const feeMixHasData = computed(() => feeMixTotal.value > 0)

// Attendance breakdown — list view (replaces the donut)
const attendanceRows = computed(() => {
    const total = +k.value.total_students || 0
    const rows = [
        { label: 'Present',  count: +donut.value.present  || 0, color: '#10b981' },
        { label: 'Absent',   count: +donut.value.absent   || 0, color: '#ef4444' },
        { label: 'Half Day', count: +donut.value.half_day || 0, color: '#f97316' },
        { label: 'Late',     count: +donut.value.late     || 0, color: '#f59e0b' },
        { label: 'On Leave', count: +donut.value.leave    || 0, color: '#8b5cf6' },
    ]
    return rows.map(r => ({
        ...r,
        total,
        pct: total > 0 ? Math.min(100, Math.round(r.count / total * 100)) : 0,
    }))
})
const attendanceMarked = computed(() => attendanceRows.value.reduce((s, r) => s + r.count, 0))

const hostelPct = computed(() => {
    const cap = +k.value.hostel_capacity || 0
    if (!cap) return 0
    return Math.round((+k.value.hostel_occupied || 0) / cap * 100)
})

// ── view toggles ───────────────────────────────────────────
const financeView = ref('rvp')          // 'rvp' (Receipt vs Payment) | 'csum' (Course-wise summary)
const activityTab = ref('payments')
const recentPayments  = computed(() => d.value.recent_payments || [])
const recentAdmissions = computed(() => d.value.recent_admissions || [])
const todayVisitors   = computed(() => d.value.today_visitors || [])

// ── alerts & people ────────────────────────────────────────
const pendingFeeStudents = computed(() => d.value.pending_fee_students || [])
const lowAttendance      = computed(() => d.value.low_attendance || [])
const birthdays          = computed(() => d.value.birthdays_today || [])
const absentStaff        = computed(() => d.value.absent_staff || [])
const announcements      = computed(() => d.value.announcements || [])
const nextExam           = computed(() => d.value.next_exam || null)

const showAlerts = computed(() => pendingFeeStudents.value.length || lowAttendance.value.length || announcements.value.length)
const showPeople = computed(() => birthdays.value.length || absentStaff.value.length)

// ── upcoming events (next 6 holidays + exams, merged & sorted) ─
const upcomingEvents = computed(() => {
    const holidays = (d.value.upcoming_holidays || []).map(h => ({
        kind: 'holiday', date: h.date, title: h.title,
    }))
    const exams = (d.value.calendar_exams || []).map(e => ({
        kind: 'exam', date: e.date, title: e.title,
    }))
    return [...holidays, ...exams]
        .sort((a, b) => (a.date || '').localeCompare(b.date || ''))
        .slice(0, 6)
        .map(e => ({
            ...e,
            dateLabel: new Date(e.date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }),
            daysAway: Math.max(0, Math.round((new Date(e.date) - new Date()) / 86400000)),
        }))
})
</script>

<template>
    <div class="space-y-5">

        <!-- ─ Greeting & date ─────────────────────────────────────── -->
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs text-gray-500">{{ todayLabel }}</p>
                <h1 class="mt-0.5 text-xl md:text-2xl font-bold text-gray-900 tracking-tight">
                    {{ greeting }}, <span class="text-indigo-600">{{ d.admin_name || 'Admin' }}</span>
                </h1>
            </div>
            <Link
                v-if="nextExam"
                href="/school/exam-schedules"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition"
            >
                <span class="text-[11px] font-medium">Next exam</span>
                <span class="text-sm font-semibold">{{ nextExam.title }}</span>
                <span class="text-[11px] opacity-75">in {{ nextExam.days_left }}d</span>
            </Link>
        </header>

        <!-- ─ Action chips (only-if-data) ─────────────────────────── -->
        <div
            v-if="k.attendance_unmarked > 0 || d.pending_edit_count > 0 || d.pending_leave_count > 0 || k.pending_fee_count > 0 || k.staff_unmarked_today > 0"
            class="flex flex-wrap gap-2"
        >
            <ActionChip :count="k.attendance_unmarked" label="students unmarked today" href="/school/attendance" severity="amber" />
            <ActionChip :count="k.staff_unmarked_today" label="staff unmarked today" href="/school/staff-attendance" severity="blue" />
            <ActionChip :count="d.pending_edit_count" label="edit requests pending" href="/school/edit-requests" severity="amber" />
            <ActionChip :count="d.pending_leave_count" label="leave requests pending" href="/school/leaves" severity="blue" />
            <ActionChip :count="k.pending_fee_count" label="students with pending fees" href="/school/finance/due-report" severity="red" />
        </div>

        <!-- ─ Hero KPI grid (4 cards, larger) ─────────────────────── -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <KpiCard size="hero" label="Today's collection"
                :value="fmtCompact(k.today_fee || 0)" :delta="k.today_fee_delta_pct"
                :sub="`${fmtCompact(k.month_fee || 0)} this month`" :sparkline="spk.fee"
                href="/school/finance/day-book" accent="emerald" icon="₹" />
            <KpiCard size="hero" label="Attendance today"
                :value="`${k.attendance_pct ?? 0}%`" :delta="k.attendance_delta_pp" deltaUnit="pp"
                :sub="`${fmtNum(k.present_today)} of ${fmtNum(k.attendance_marked)} marked`" :sparkline="spk.attendance"
                href="/school/attendance/report" accent="indigo" icon="✓" />
            <KpiCard size="hero" label="New admissions this month"
                :value="fmtNum(k.new_students_month)" :delta="k.new_students_delta_pct"
                :sub="`${fmtNum(k.total_students)} total students`" :sparkline="spk.admissions"
                href="/school/students" accent="blue" icon="👥" />
            <KpiCard size="hero" label="Pending fees"
                :value="fmtCompact(k.pending_fees || 0)"
                :sub="`${fmtNum(k.pending_fee_count)} students owe`"
                href="/school/finance/due-report" accent="red" icon="!" />
        </section>

        <!-- ─ Secondary KPI strip ─────────────────────────────────── -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <KpiCard size="compact" label="Staff active" :value="fmtNum(k.total_staff)"
                :sub="`${fmtNum(k.staff_present_today)} present · ${fmtNum(k.staff_on_leave)} on leave`"
                href="/school/staff" accent="violet" icon="🧑‍🏫" />
            <KpiCard size="compact" label="Classes & sections"
                :value="`${fmtNum(k.total_classes)} / ${fmtNum(k.total_sections)}`" sub="Class · section count"
                href="/school/classes" accent="indigo" icon="🏫" />
            <KpiCard size="compact" label="Active transport routes"
                :value="fmtNum(k.active_routes)" sub="Routes running"
                href="/school/transport" accent="amber" icon="🚌" />
            <KpiCard size="compact" label="Hostel occupancy"
                :value="`${hostelPct}%`"
                :sub="`${fmtNum(k.hostel_occupied)} of ${fmtNum(k.hostel_capacity)} beds`"
                href="/school/hostel" accent="pink" icon="🏠" />
        </section>

        <!-- ─ Fee Summary + Course Wise Strength ──────────────────── -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <!-- Fee Summary card with progress bars -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 tracking-tight">Fee Summary</h2>
                        <p class="text-xs text-gray-500">Academic year · all fee streams</p>
                    </div>
                    <Link href="/school/finance/due-report" class="inline-flex items-center px-2.5 py-1 rounded-full bg-indigo-600 text-white text-[11px] font-medium hover:bg-indigo-700">
                        Report
                    </Link>
                </div>

                <!-- Paid bar -->
                <div class="mb-4">
                    <div class="flex items-baseline justify-between text-xs mb-1.5">
                        <span class="font-medium text-gray-700">Paid</span>
                        <span class="text-gray-900 tabular-nums font-semibold">
                            {{ fmtCur(fee.paid || 0) }} <span class="text-gray-400 font-normal">/ {{ fmtCur(fee.total || 0) }}</span>
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full bg-amber-400 rounded-full transition-all" :style="{ width: Math.min(100, fee.paid_pct || 0) + '%' }"></div>
                    </div>
                </div>

                <!-- Balance bar -->
                <div class="mb-4">
                    <div class="flex items-baseline justify-between text-xs mb-1.5">
                        <span class="font-medium text-gray-700">Balance</span>
                        <span class="text-gray-900 tabular-nums font-semibold">
                            {{ fmtCur(fee.balance || 0) }} <span class="text-gray-400 font-normal">/ {{ fmtCur(fee.total || 0) }}</span>
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full transition-all" :style="{ width: Math.min(100, fee.balance_pct || 0) + '%' }"></div>
                    </div>
                </div>

                <!-- Concession bar -->
                <div class="mb-5">
                    <div class="flex items-baseline justify-between text-xs mb-1.5">
                        <span class="font-medium text-gray-700">Concession</span>
                        <span class="text-gray-900 tabular-nums font-semibold">
                            {{ fmtCur(fee.concession || 0) }}
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all" :style="{ width: Math.min(100, fee.concession_pct || 0) + '%' }"></div>
                    </div>
                </div>

                <!-- Period collections -->
                <div class="space-y-2 pt-3 border-t border-gray-100">
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="text-gray-700">Today's collection</span>
                        <span class="font-semibold text-gray-900 tabular-nums">{{ fmtCur(fee.today_collection || 0) }}</span>
                    </div>
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="text-gray-700">This week</span>
                        <span class="font-semibold text-gray-900 tabular-nums">{{ fmtCur(fee.week_collection || 0) }}</span>
                    </div>
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="text-gray-700">This month</span>
                        <span class="font-semibold text-gray-900 tabular-nums">{{ fmtCur(fee.month_collection || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- Course Wise Strength chart -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader
                    title="Course-wise strength"
                    subtitle="Active students per class, this academic year"
                    actionLabel="All students"
                    actionHref="/school/students"
                />
                <TrendChart
                    v-if="cstr.length"
                    type="bar"
                    :labels="courseStrengthChart.labels"
                    :datasets="courseStrengthChart.datasets"
                    :height="260"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No class enrolment data yet</p>
            </div>
        </section>

        <!-- ─ Receipt vs Payment / Course-wise fee summary (toggle) ─── -->
        <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <div class="flex items-start justify-between mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 tracking-tight">
                        {{ financeView === 'rvp' ? 'Receipt vs Payment' : 'Course-wise fee summary' }}
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ financeView === 'rvp'
                            ? 'Money in vs money out, last 12 months'
                            : 'Total · Paid · Balance · Concession per class' }}
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="inline-flex bg-gray-100 rounded-lg p-1 text-xs font-medium">
                        <button
                            v-for="t in [
                                { id: 'rvp',  label: 'Receipt vs Payment' },
                                { id: 'csum', label: 'Course-wise summary' },
                            ]"
                            :key="t.id"
                            @click="financeView = t.id"
                            :class="[
                                'px-3 py-1 rounded-md transition whitespace-nowrap',
                                financeView === t.id ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'
                            ]"
                        >{{ t.label }}</button>
                    </div>
                    <Link
                        :href="financeView === 'rvp' ? '/school/finance/day-book' : '/school/finance/due-report'"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        {{ financeView === 'rvp' ? 'Day book' : 'Due report' }} →
                    </Link>
                </div>
            </div>

            <template v-if="financeView === 'rvp'">
                <TrendChart
                    v-if="rvp.length"
                    type="bar"
                    :labels="receiptVsPaymentChart.labels"
                    :datasets="receiptVsPaymentChart.datasets"
                    :currency="currency"
                    :legend="true"
                    :height="300"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No financial activity yet</p>
            </template>

            <template v-else>
                <TrendChart
                    v-if="csum.length"
                    type="bar"
                    :labels="courseSummaryChart.labels"
                    :datasets="courseSummaryChart.datasets"
                    :currency="currency"
                    :legend="true"
                    :height="300"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No fee allocations for any class yet</p>
            </template>
        </section>

        <!-- ─ Income / Expense / Attendance donuts ────────────────── -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <!-- Income semi-donut -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader
                    :title="`Income — ${monthLabel}`"
                    subtitle="Non-fee income from ledger"
                    actionLabel="Ledger"
                    actionHref="/school/finance/transactions"
                />
                <DonutChart
                    v-if="incomeSegments.length"
                    :segments="incomeSegments" :semi="true"
                    :height="180"
                    :centerValue="fmtCompact(incomeTotal)"
                    centerLabel="this month"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No non-fee income recorded</p>
                <div v-if="incomeSegments.length" class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                    <div v-for="seg in incomeSegments" :key="seg.label" class="flex items-center gap-1.5 min-w-0">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ background: seg.color }" />
                        <span class="text-gray-600 truncate">{{ seg.label }}</span>
                        <span class="ml-auto font-semibold text-gray-900 tabular-nums whitespace-nowrap">{{ fmtCompact(seg.value) }}</span>
                    </div>
                </div>
            </div>

            <!-- Expense semi-donut -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader
                    :title="`Expense — ${monthLabel}`"
                    subtitle="Spending by category"
                    actionLabel="All expenses"
                    actionHref="/school/expenses"
                />
                <DonutChart
                    v-if="expenseSegments.length"
                    :segments="expenseSegments" :semi="true"
                    :height="180"
                    :centerValue="fmtCompact(expenseTotal)"
                    centerLabel="this month"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No expenses recorded</p>
                <div v-if="expenseSegments.length" class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                    <div v-for="seg in expenseSegments" :key="seg.label" class="flex items-center gap-1.5 min-w-0">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ background: seg.color }" />
                        <span class="text-gray-600 truncate">{{ seg.label }}</span>
                        <span class="ml-auto font-semibold text-gray-900 tabular-nums whitespace-nowrap">{{ fmtCompact(seg.value) }}</span>
                    </div>
                </div>
            </div>

            <!-- Student Attendance — list breakdown -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 tracking-tight flex items-center gap-1.5">
                            Student Attendance
                            <span class="text-gray-400 text-sm">👥</span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ attendanceMarked }} of {{ k.total_students || 0 }} marked today
                        </p>
                    </div>
                    <Link
                        href="/school/attendance/report"
                        class="w-7 h-7 inline-flex items-center justify-center rounded-md bg-gray-50 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 transition"
                        title="Open report"
                    >↗</Link>
                </div>

                <ul class="divide-y divide-gray-100">
                    <li v-for="row in attendanceRows" :key="row.label" class="py-2.5 first:pt-1 last:pb-1">
                        <div class="flex items-baseline justify-between gap-2 mb-1">
                            <span class="text-sm font-medium text-gray-700">{{ row.label }}</span>
                            <span class="text-xs text-gray-500 tabular-nums whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ row.count }}</span>
                                <span class="text-gray-400"> / {{ row.total }}</span>
                            </span>
                        </div>
                        <div class="h-1 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 :style="{ width: row.pct + '%', background: row.color }"></div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ─ Class-wise attendance + Fee Mix Today ───────────────── -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader
                    title="Class-wise attendance"
                    subtitle="Today, percent present"
                    actionLabel="Report"
                    actionHref="/school/attendance/report"
                />
                <TrendChart
                    v-if="classAtt.length"
                    type="bar"
                    :labels="classAttChart.labels"
                    :datasets="classAttChart.datasets"
                    :yPercent="true"
                    :height="240"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No class attendance yet</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader
                    title="Fee mix today"
                    :subtitle="feeMixHasData ? `${fmtCur(feeMixTotal)} collected` : 'No collections yet today'"
                    actionLabel="Collect"
                    actionHref="/school/fee/collect"
                />
                <DonutChart
                    v-if="feeMixHasData"
                    :segments="feeMixSegments"
                    :height="180"
                    :centerValue="fmtCompact(feeMixTotal)"
                    centerLabel="today"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">Open Fee → Collect to record a payment</p>
                <div v-if="feeMixHasData" class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                    <div v-for="seg in feeMixSegments" :key="seg.label" class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :style="{ background: seg.color }" />
                        <span class="text-gray-600">{{ seg.label }}</span>
                        <span class="ml-auto font-semibold text-gray-900 tabular-nums">{{ fmtCur(seg.value) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─ Activity & calendar ─────────────────────────────────── -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                    <h2 class="text-base font-semibold text-gray-900 tracking-tight">Recent activity</h2>
                    <div class="inline-flex bg-gray-100 rounded-lg p-1 text-xs font-medium">
                        <button
                            v-for="t in [
                                { id: 'payments',   label: 'Payments' },
                                { id: 'admissions', label: 'Admissions' },
                                { id: 'visitors',   label: 'Visitors' },
                            ]"
                            :key="t.id"
                            @click="activityTab = t.id"
                            :class="[
                                'px-3 py-1 rounded-md transition',
                                activityTab === t.id ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'
                            ]"
                        >{{ t.label }}</button>
                    </div>
                </div>

                <RecentList v-if="activityTab === 'payments'" :rows="recentPayments" emptyText="No payments today">
                    <template #primary="{ row }">{{ row.student }}</template>
                    <template #secondary="{ row }">{{ row.fee_head }} · {{ row.mode }} · {{ row.paid_at }}</template>
                    <template #right="{ row }">{{ fmtCur(row.amount) }}</template>
                </RecentList>

                <RecentList v-else-if="activityTab === 'admissions'" :rows="recentAdmissions" avatarKey="photo_url" emptyText="No recent admissions">
                    <template #primary="{ row }">{{ row.name }}</template>
                    <template #secondary="{ row }">
                        Adm. {{ row.admission_no }} · {{ row.class }}{{ row.section ? ' · ' + row.section : '' }} · {{ row.admitted_at }}
                    </template>
                </RecentList>

                <RecentList v-else :rows="todayVisitors" emptyText="No visitors today">
                    <template #primary="{ row }">{{ row.name }}</template>
                    <template #secondary="{ row }">{{ row.purpose || '—' }}</template>
                    <template #right="{ row }">{{ row.in_time }}{{ row.out ? ' → ' + row.out : '' }}</template>
                </RecentList>
            </div>

            <MiniCalendar :holidays="d.upcoming_holidays || []" :exams="d.calendar_exams || []" />
        </section>

        <!-- ─ Alerts + People today (auto-fit grid, no empty cells) ── -->
        <section
            v-if="showAlerts || showPeople"
            class="grid gap-4"
            style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));"
        >
            <div v-if="pendingFeeStudents.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader title="Top pending fees" subtitle="Largest outstanding balances"
                    actionLabel="All defaulters" actionHref="/school/finance/due-report" />
                <RecentList :rows="pendingFeeStudents" emptyText="No pending fees">
                    <template #primary="{ row }">{{ row.student }}</template>
                    <template #right="{ row }"><span class="text-red-600">{{ fmtCur(row.balance) }}</span></template>
                </RecentList>
            </div>

            <div v-if="lowAttendance.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader title="Low attendance" subtitle="Students under 75% this year"
                    actionLabel="Report" actionHref="/school/attendance/report" />
                <RecentList :rows="lowAttendance" emptyText="No low-attendance students">
                    <template #primary="{ row }">{{ row.student }}</template>
                    <template #right="{ row }">
                        <span :class="row.percentage < 60 ? 'text-red-600' : 'text-amber-600'">{{ row.percentage }}%</span>
                    </template>
                </RecentList>
            </div>

            <div v-if="announcements.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader title="Recent announcements" subtitle="Latest broadcasts"
                    actionLabel="All" actionHref="/school/announcements" />
                <RecentList :rows="announcements" emptyText="No announcements yet">
                    <template #primary="{ row }">{{ row.title }}</template>
                    <template #secondary="{ row }">
                        <span class="capitalize">{{ row.audience || 'all' }}</span> · by {{ row.sender }}
                    </template>
                    <template #right="{ row }">
                        <span class="text-xs font-normal text-gray-400">{{ row.sent_at }}</span>
                    </template>
                </RecentList>
            </div>

            <div v-if="absentStaff.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader title="Absent / on leave" subtitle="Staff away today"
                    actionLabel="Leaves" actionHref="/school/leaves" />
                <RecentList :rows="absentStaff" avatarKey="photo">
                    <template #primary="{ row }">{{ row.name }}</template>
                    <template #secondary="{ row }">{{ row.designation }}</template>
                </RecentList>
            </div>

            <div v-if="birthdays.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <SectionHeader title="🎂 Birthdays today"
                    :subtitle="`${birthdays.length} student${birthdays.length === 1 ? '' : 's'} celebrating`" />
                <div class="grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                    <div
                        v-for="(b, i) in birthdays" :key="i"
                        class="flex items-center gap-2.5 p-2 rounded-lg bg-pink-50/70 border border-pink-100 min-w-0"
                    >
                        <img v-if="b.photo" :src="b.photo" class="w-10 h-10 rounded-full object-cover flex-shrink-0 ring-2 ring-white" />
                        <div v-else class="w-10 h-10 rounded-full bg-pink-200 text-pink-700 flex items-center justify-center text-sm font-bold flex-shrink-0 ring-2 ring-white">
                            {{ b.name.charAt(0) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate leading-tight" :title="b.name">{{ b.name }}</p>
                            <p class="text-[11px] text-pink-700 font-medium truncate mt-0.5"
                               :title="(b.class || '') + (b.section ? ' · ' + b.section : '')">
                                <template v-if="b.class && b.class !== '—'">
                                    {{ b.class }}<span v-if="b.section"> · {{ b.section }}</span>
                                </template>
                                <template v-else>
                                    Adm. {{ b.admission_no || '—' }}
                                </template>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─ Upcoming events list ────────────────────────────────── -->
        <section v-if="upcomingEvents.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <SectionHeader title="Upcoming events" subtitle="Next holidays and exams"
                actionLabel="Calendar" actionHref="/school/academic/calendar" />
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div v-for="(e, i) in upcomingEvents" :key="i"
                    class="flex flex-col p-3 rounded-lg border border-gray-100 hover:border-indigo-300 transition">
                    <span class="text-[10px] font-semibold uppercase tracking-wide"
                          :class="e.kind === 'holiday' ? 'text-red-500' : 'text-indigo-500'">{{ e.kind }}</span>
                    <span class="text-sm font-medium text-gray-900 mt-1 line-clamp-2 leading-tight">{{ e.title }}</span>
                    <div class="mt-auto pt-2 flex items-end justify-between">
                        <span class="text-xs text-gray-500">{{ e.dateLabel }}</span>
                        <span class="text-[10px] font-semibold text-gray-400 tabular-nums">
                            {{ e.daysAway === 0 ? 'today' : 'in ' + e.daysAway + 'd' }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

    </div>
</template>
