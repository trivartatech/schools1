<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import KpiCard         from '@/Components/dashboard/KpiCard.vue'
import TrendChart      from '@/Components/dashboard/TrendChart.vue'
import DonutChart      from '@/Components/dashboard/DonutChart.vue'
import MiniCalendar    from '@/Components/dashboard/MiniCalendar.vue'
import RecentList      from '@/Components/dashboard/RecentList.vue'
import SectionHeader   from '@/Components/dashboard/SectionHeader.vue'
import ActionChip      from '@/Components/dashboard/ActionChip.vue'
import QuickActionsBar from '@/Components/dashboard/QuickActionsBar.vue'

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

// ── greeting ───────────────────────────────────────────────
const greeting = computed(() => {
    const h = new Date().getHours()
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'
})
const todayLabel = new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

// ── quick actions ──────────────────────────────────────────
const quickActions = [
    { label: 'Collect Fee',     icon: '💰', href: '/school/fee/collect',       accent: 'emerald' },
    { label: 'Mark Attendance', icon: '✅', href: '/school/attendance',        accent: 'indigo' },
    { label: 'Add Student',     icon: '➕', href: '/school/students/create',   accent: 'blue' },
    { label: 'Announcement',    icon: '📢', href: '/school/announcements',     accent: 'violet' },
    { label: 'Day Book',        icon: '📒', href: '/school/finance/day-book',  accent: 'amber' },
    { label: 'Due Report',      icon: '📊', href: '/school/finance/due-report',accent: 'pink' },
]

// ── chart data ─────────────────────────────────────────────
const feeTrend  = computed(() => d.value.fee_trend || [])
const admTrend  = computed(() => d.value.admission_trend || [])
const classAtt  = computed(() => d.value.class_attendance || [])
const feeMix    = computed(() => d.value.fee_mix_today || {})
const donut     = computed(() => d.value.attendance_donut || {})

const feeTrendChart = computed(() => ({
    labels: feeTrend.value.map(x => x.short),
    datasets: [
        { label: 'Tuition',    data: feeTrend.value.map(x => x.tuition),    color: '#6366f1' },
        { label: 'Transport',  data: feeTrend.value.map(x => x.transport),  color: '#10b981' },
        { label: 'Hostel',     data: feeTrend.value.map(x => x.hostel),     color: '#f59e0b' },
        { label: 'Stationary', data: feeTrend.value.map(x => x.stationary), color: '#3b82f6' },
    ],
}))

const admTrendChart = computed(() => ({
    labels: admTrend.value.map(x => x.short),
    datasets: [{ label: 'Admissions', data: admTrend.value.map(x => x.count), color: '#8b5cf6' }],
}))

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
const feeMixTotal = computed(() => feeMixSegments.value.reduce((s, x) => s + x.value, 0))
const feeMixHasData = computed(() => feeMixTotal.value > 0)

const donutSegments = computed(() => [
    { label: 'Present',  value: donut.value.present  || 0, color: '#10b981' },
    { label: 'Absent',   value: donut.value.absent   || 0, color: '#ef4444' },
    { label: 'Late',     value: donut.value.late     || 0, color: '#f59e0b' },
    { label: 'Half Day', value: donut.value.half_day || 0, color: '#6366f1' },
])
const donutTotal = computed(() => donutSegments.value.reduce((s, x) => s + x.value, 0))

// ── secondary KPIs ─────────────────────────────────────────
const hostelPct = computed(() => {
    const cap = +k.value.hostel_capacity || 0
    if (!cap) return 0
    return Math.round((+k.value.hostel_occupied || 0) / cap * 100)
})

// ── activity tabs ──────────────────────────────────────────
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

// ── upcoming events (next 5 holidays + exams, merged & sorted) ─
const upcomingEvents = computed(() => {
    const holidays = (d.value.upcoming_holidays || []).map(h => ({
        kind: 'holiday', date: h.date, title: h.title, color: 'red',
    }))
    const exams = (d.value.calendar_exams || []).map(e => ({
        kind: 'exam', date: e.date, title: e.title, color: 'indigo',
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
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">{{ todayLabel }}</p>
                <h1 class="mt-0.5 text-2xl font-bold text-gray-900 tracking-tight">
                    {{ greeting }}, <span class="text-indigo-600">{{ d.admin_name || 'Admin' }}</span>
                </h1>
            </div>
            <Link
                v-if="nextExam"
                href="/school/exam-schedules"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition"
            >
                <span class="text-xs font-medium">Next exam</span>
                <span class="text-sm font-semibold">{{ nextExam.title }}</span>
                <span class="text-xs opacity-75">in {{ nextExam.days_left }}d</span>
            </Link>
        </header>

        <!-- ─ Quick actions strip (always visible) ────────────────── -->
        <QuickActionsBar :actions="quickActions" />

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
            <KpiCard
                size="hero"
                label="Today's collection"
                :value="fmtCompact(k.today_fee || 0)"
                :delta="k.today_fee_delta_pct"
                :sub="`${fmtCompact(k.month_fee || 0)} this month`"
                :sparkline="spk.fee"
                href="/school/finance/day-book"
                accent="emerald"
                icon="₹"
            />
            <KpiCard
                size="hero"
                label="Attendance today"
                :value="`${k.attendance_pct ?? 0}%`"
                :delta="k.attendance_delta_pp"
                deltaUnit="pp"
                :sub="`${fmtNum(k.present_today)} of ${fmtNum(k.attendance_marked)} marked`"
                :sparkline="spk.attendance"
                href="/school/attendance/report"
                accent="indigo"
                icon="✓"
            />
            <KpiCard
                size="hero"
                label="New admissions this month"
                :value="fmtNum(k.new_students_month)"
                :delta="k.new_students_delta_pct"
                :sub="`${fmtNum(k.total_students)} total students`"
                :sparkline="spk.admissions"
                href="/school/students"
                accent="blue"
                icon="👥"
            />
            <KpiCard
                size="hero"
                label="Pending fees"
                :value="fmtCompact(k.pending_fees || 0)"
                :sub="`${fmtNum(k.pending_fee_count)} students owe`"
                href="/school/finance/due-report"
                accent="red"
                icon="!"
            />
        </section>

        <!-- ─ Secondary KPI strip (4 compact stat tiles) ──────────── -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <KpiCard
                size="compact"
                label="Staff active"
                :value="fmtNum(k.total_staff)"
                :sub="`${fmtNum(k.staff_present_today)} present · ${fmtNum(k.staff_on_leave)} on leave`"
                href="/school/staff"
                accent="violet"
                icon="🧑‍🏫"
            />
            <KpiCard
                size="compact"
                label="Classes & sections"
                :value="`${fmtNum(k.total_classes)} / ${fmtNum(k.total_sections)}`"
                sub="Class · section count"
                href="/school/classes"
                accent="indigo"
                icon="🏫"
            />
            <KpiCard
                size="compact"
                label="Active transport routes"
                :value="fmtNum(k.active_routes)"
                sub="Routes running"
                href="/school/transport"
                accent="amber"
                icon="🚌"
            />
            <KpiCard
                size="compact"
                label="Hostel occupancy"
                :value="`${hostelPct}%`"
                :sub="`${fmtNum(k.hostel_occupied)} of ${fmtNum(k.hostel_capacity)} beds`"
                href="/school/hostel"
                accent="pink"
                icon="🏠"
            />
        </section>

        <!-- ─ Trend charts ────────────────────────────────────────── -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader
                    title="Revenue — last 6 months"
                    subtitle="Stacked by stream: tuition, transport, hostel, stationary"
                    actionLabel="Day book"
                    actionHref="/school/finance/day-book"
                />
                <TrendChart
                    v-if="feeTrend.length"
                    type="stacked-area"
                    :labels="feeTrendChart.labels"
                    :datasets="feeTrendChart.datasets"
                    :currency="currency"
                    :legend="true"
                    :height="260"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No collection data yet</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader
                    title="Admissions — 6 months"
                    subtitle="New students per month"
                    actionLabel="All students"
                    actionHref="/school/students"
                />
                <TrendChart
                    v-if="admTrend.length"
                    type="bar"
                    :labels="admTrendChart.labels"
                    :datasets="admTrendChart.datasets"
                    :height="260"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No admissions yet</p>
            </div>
        </section>

        <!-- ─ Today snapshot: attendance donut + class bars + fee mix ─ -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader
                    title="Attendance breakdown"
                    subtitle="Students marked today"
                    actionLabel="Mark"
                    actionHref="/school/attendance"
                />
                <DonutChart
                    v-if="donutTotal > 0"
                    :segments="donutSegments"
                    :height="180"
                    :centerValue="`${k.attendance_pct ?? 0}%`"
                    centerLabel="present"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No attendance marked today</p>
                <div v-if="donutTotal > 0" class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                    <div v-for="seg in donutSegments" :key="seg.label" class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" :style="{ background: seg.color }" />
                        <span class="text-gray-600">{{ seg.label }}</span>
                        <span class="ml-auto font-semibold text-gray-900 tabular-nums">{{ seg.value }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
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
                    :height="220"
                />
                <p v-else class="text-sm text-gray-400 italic py-12 text-center">No class attendance yet</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
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
                <div v-if="feeMixHasData" class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                    <div v-for="seg in feeMixSegments" :key="seg.label" class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" :style="{ background: seg.color }" />
                        <span class="text-gray-600">{{ seg.label }}</span>
                        <span class="ml-auto font-semibold text-gray-900 tabular-nums">{{ fmtCur(seg.value) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─ Activity & calendar ─────────────────────────────────── -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
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

                <RecentList
                    v-if="activityTab === 'payments'"
                    :rows="recentPayments"
                    emptyText="No payments today"
                >
                    <template #primary="{ row }">{{ row.student }}</template>
                    <template #secondary="{ row }">
                        {{ row.fee_head }} · {{ row.mode }} · {{ row.paid_at }}
                    </template>
                    <template #right="{ row }">{{ fmtCur(row.amount) }}</template>
                </RecentList>

                <RecentList
                    v-else-if="activityTab === 'admissions'"
                    :rows="recentAdmissions"
                    avatarKey="photo_url"
                    emptyText="No recent admissions"
                >
                    <template #primary="{ row }">{{ row.name }}</template>
                    <template #secondary="{ row }">
                        Adm. {{ row.admission_no }} · {{ row.class }}{{ row.section ? ' · ' + row.section : '' }} · {{ row.admitted_at }}
                    </template>
                </RecentList>

                <RecentList
                    v-else
                    :rows="todayVisitors"
                    emptyText="No visitors today"
                >
                    <template #primary="{ row }">{{ row.name }}</template>
                    <template #secondary="{ row }">{{ row.purpose || '—' }}</template>
                    <template #right="{ row }">{{ row.in_time }}{{ row.out ? ' → ' + row.out : '' }}</template>
                </RecentList>
            </div>

            <MiniCalendar
                :holidays="d.upcoming_holidays || []"
                :exams="d.calendar_exams || []"
            />
        </section>

        <!-- ─ Alerts: defaulters + low attendance + announcements ─── -->
        <section v-if="showAlerts" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div v-if="pendingFeeStudents.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader
                    title="Top pending fees"
                    subtitle="Largest outstanding balances"
                    actionLabel="All defaulters"
                    actionHref="/school/finance/due-report"
                />
                <RecentList :rows="pendingFeeStudents" emptyText="No pending fees">
                    <template #primary="{ row }">{{ row.student }}</template>
                    <template #right="{ row }">
                        <span class="text-red-600">{{ fmtCur(row.balance) }}</span>
                    </template>
                </RecentList>
            </div>

            <div v-if="lowAttendance.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader
                    title="Low attendance"
                    subtitle="Students under 75% this year"
                    actionLabel="Report"
                    actionHref="/school/attendance/report"
                />
                <RecentList :rows="lowAttendance" emptyText="No low-attendance students">
                    <template #primary="{ row }">{{ row.student }}</template>
                    <template #right="{ row }">
                        <span :class="row.percentage < 60 ? 'text-red-600' : 'text-amber-600'">
                            {{ row.percentage }}%
                        </span>
                    </template>
                </RecentList>
            </div>

            <div v-if="announcements.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader
                    title="Recent announcements"
                    subtitle="Latest broadcasts"
                    actionLabel="All"
                    actionHref="/school/announcements"
                />
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
        </section>

        <!-- ─ People today ────────────────────────────────────────── -->
        <section v-if="showPeople" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div v-if="birthdays.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader title="🎂 Birthdays today" subtitle="Send a wish" />
                <div class="flex flex-wrap gap-2">
                    <div v-for="(b, i) in birthdays" :key="i" class="flex items-center gap-2 bg-pink-50 text-pink-800 rounded-full pl-1 pr-3 py-1">
                        <img v-if="b.photo" :src="b.photo" class="w-6 h-6 rounded-full object-cover" />
                        <div v-else class="w-6 h-6 rounded-full bg-pink-200 flex items-center justify-center text-[10px] font-semibold">
                            {{ b.name.charAt(0) }}
                        </div>
                        <span class="text-xs font-medium">{{ b.name }}</span>
                    </div>
                </div>
            </div>

            <div v-if="absentStaff.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <SectionHeader
                    title="Absent / on leave"
                    subtitle="Staff away today"
                    actionLabel="Leaves"
                    actionHref="/school/leaves"
                />
                <RecentList :rows="absentStaff" avatarKey="photo">
                    <template #primary="{ row }">{{ row.name }}</template>
                    <template #secondary="{ row }">{{ row.designation }}</template>
                </RecentList>
            </div>
        </section>

        <!-- ─ Upcoming events list (combined holidays + exams) ────── -->
        <section v-if="upcomingEvents.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <SectionHeader
                title="Upcoming events"
                subtitle="Next holidays and exams"
                actionLabel="Calendar"
                actionHref="/school/academic/calendar"
            />
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div
                    v-for="(e, i) in upcomingEvents" :key="i"
                    class="flex flex-col p-3 rounded-lg border border-gray-100 hover:border-indigo-300 transition"
                >
                    <span class="text-[10px] font-semibold uppercase tracking-wide"
                          :class="e.kind === 'holiday' ? 'text-red-500' : 'text-indigo-500'">
                        {{ e.kind }}
                    </span>
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
