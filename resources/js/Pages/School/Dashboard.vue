<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useSchoolStore } from '@/stores/useSchoolStore'

const schoolStore = useSchoolStore()

const props = defineProps({
    school: Object,
    school_dashboard: { type: Object, default: () => ({}) },
})

const d = computed(() => props.school_dashboard || {})
const kpi = computed(() => d.value.kpi || {})

// ── Calendar ────────────────────────────────────────────────
const today = new Date()
const calendarView = ref('month')   // month | week | list
const calendarDate = ref(new Date(today.getFullYear(), today.getMonth(), 1))

const calendarYear  = computed(() => calendarDate.value.getFullYear())
const calendarMonth = computed(() => calendarDate.value.getMonth())
const monthLabel    = computed(() => calendarDate.value.toLocaleString('default', { month: 'long', year: 'numeric' }))

function prevMonth() {
    calendarDate.value = new Date(calendarYear.value, calendarMonth.value - 1, 1)
}
function nextMonth() {
    calendarDate.value = new Date(calendarYear.value, calendarMonth.value + 1, 1)
}

const daysInMonth = computed(() => new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate())
const firstDayOfMonth = computed(() => new Date(calendarYear.value, calendarMonth.value, 1).getDay())

// Build the 6-week grid (42 cells)
const calendarGrid = computed(() => {
    const cells = []
    const offset = firstDayOfMonth.value
    const total  = daysInMonth.value

    // Pad start
    for (let i = 0; i < offset; i++) cells.push(null)
    // Fill days
    for (let d = 1; d <= total; d++) cells.push(d)
    // Pad end to full 42
    while (cells.length < 42) cells.push(null)
    return cells
})

// Build event map: date-string → array of events
const eventMap = computed(() => {
    const map = {}
    const addEvent = (dateStr, event) => {
        if (!map[dateStr]) map[dateStr] = []
        map[dateStr].push(event)
    }

    const holidays = d.value.upcoming_holidays || []
    const exams = d.value.calendar_exams || []

    holidays.forEach(h => addEvent(h.date, { ...h, color: 'red' }))
    exams.forEach(e => addEvent(e.date, { ...e, color: 'blue' }))

    return map
})

function eventsOnDay(day) {
    if (!day) return []
    const dateStr = `${calendarYear.value}-${String(calendarMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    return eventMap.value[dateStr] || []
}

function isToday(day) {
    return day === today.getDate() &&
        calendarMonth.value === today.getMonth() &&
        calendarYear.value === today.getFullYear()
}

// ── Charts (simple canvas bar charts) ───────────────────────
const feeCanvas = ref(null)
const attendanceCanvas = ref(null)

function drawBarChart(canvas, data, labels, color1, color2) {
    if (!canvas || !data?.length) return
    const ctx = canvas.getContext('2d')
    const W = canvas.width
    const H = canvas.height
    const max = Math.max(...data, 1)
    const barW = Math.floor((W - 60) / data.length * 0.65)
    const gap   = Math.floor((W - 60) / data.length * 0.35)
    const bottomPad = 32
    const topPad    = 20
    const chartH    = H - bottomPad - topPad

    ctx.clearRect(0, 0, W, H)

    // Grid lines
    ctx.strokeStyle = 'rgba(226,232,240,0.8)'
    ctx.lineWidth = 1
    for (let i = 0; i <= 4; i++) {
        const y = topPad + (chartH / 4) * i
        ctx.beginPath()
        ctx.moveTo(40, y)
        ctx.lineTo(W - 10, y)
        ctx.stroke()

        // Y labels
        const val = Math.round(max - (max / 4) * i)
        ctx.fillStyle = '#94a3b8'
        ctx.font = '10px Inter, sans-serif'
        ctx.textAlign = 'right'
        ctx.fillText(val, 36, y + 3)
    }

    data.forEach((val, idx) => {
        const x = 40 + idx * ((W - 60) / data.length) + gap / 2
        const barH = (val / max) * chartH
        const y = topPad + chartH - barH

        // Bar shadow
        ctx.shadowColor = color1
        ctx.shadowBlur = 6
        ctx.shadowOffsetY = 2

        const grad = ctx.createLinearGradient(x, y, x, y + barH)
        grad.addColorStop(0, color1)
        grad.addColorStop(1, color2)
        ctx.fillStyle = grad
        ctx.beginPath()
        ctx.roundRect(x, y, barW, barH, [4, 4, 0, 0])
        ctx.fill()

        ctx.shadowColor = 'transparent'
        ctx.shadowBlur = 0

        // Value label on top
        if (val > 0) {
            ctx.fillStyle = '#475569'
            ctx.font = 'bold 10px Inter, sans-serif'
            ctx.textAlign = 'center'
            ctx.fillText(val, x + barW / 2, y - 4)
        }

        // X label
        ctx.fillStyle = '#94a3b8'
        ctx.font = '9px Inter, sans-serif'
        ctx.textAlign = 'center'
        const label = (labels[idx] || '').split(' ')[0]
        ctx.fillText(label, x + barW / 2, H - 6)
    })
}

function drawLineChart(canvas, data, labels, color) {
    if (!canvas || !data?.length) return
    const ctx = canvas.getContext('2d')
    const W = canvas.width
    const H = canvas.height
    const max = Math.max(...data, 1)
    const bottomPad = 32
    const topPad    = 20
    const leftPad   = 55
    const chartH    = H - bottomPad - topPad
    const chartW    = W - leftPad - 10
    const step      = chartW / (data.length - 1)

    ctx.clearRect(0, 0, W, H)

    // Grid
    ctx.strokeStyle = 'rgba(226,232,240,0.8)'
    ctx.lineWidth = 1
    for (let i = 0; i <= 4; i++) {
        const y = topPad + (chartH / 4) * i
        ctx.beginPath()
        ctx.moveTo(leftPad, y)
        ctx.lineTo(W - 10, y)
        ctx.stroke()

        const val = Math.round(max - (max / 4) * i)
        ctx.fillStyle = '#94a3b8'
        ctx.font = '10px Inter, sans-serif'
        ctx.textAlign = 'right'
        ctx.fillText('₹' + (val >= 1000 ? (val / 1000).toFixed(0) + 'K' : val), leftPad - 4, y + 3)
    }

    // Area fill
    const points = data.map((v, i) => ({
        x: leftPad + i * step,
        y: topPad + chartH - (v / max) * chartH,
    }))

    const areaGrad = ctx.createLinearGradient(0, topPad, 0, topPad + chartH)
    areaGrad.addColorStop(0, color + '40')
    areaGrad.addColorStop(1, color + '00')

    ctx.beginPath()
    ctx.moveTo(points[0].x, topPad + chartH)
    points.forEach(p => ctx.lineTo(p.x, p.y))
    ctx.lineTo(points[points.length - 1].x, topPad + chartH)
    ctx.closePath()
    ctx.fillStyle = areaGrad
    ctx.fill()

    // Line
    ctx.beginPath()
    ctx.moveTo(points[0].x, points[0].y)
    for (let i = 1; i < points.length; i++) {
        const cp1x = points[i - 1].x + step / 2
        const cp1y = points[i - 1].y
        const cp2x = points[i].x - step / 2
        const cp2y = points[i].y
        ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, points[i].x, points[i].y)
    }
    ctx.strokeStyle = color
    ctx.lineWidth = 2.5
    ctx.stroke()

    // Dots & labels
    points.forEach((p, i) => {
        ctx.beginPath()
        ctx.arc(p.x, p.y, 4, 0, Math.PI * 2)
        ctx.fillStyle = '#fff'
        ctx.fill()
        ctx.strokeStyle = color
        ctx.lineWidth = 2
        ctx.stroke()

        ctx.fillStyle = '#94a3b8'
        ctx.font = '9px Inter, sans-serif'
        ctx.textAlign = 'center'
        ctx.fillText((labels[i] || '').split(' ')[0], p.x, H - 6)
    })
}

onMounted(() => {
    const feeTrend = d.value.fee_trend || []

    drawLineChart(
        feeCanvas.value,
        feeTrend.map(x => x.amount),
        feeTrend.map(x => x.month),
        '#10b981'
    )

    const donut = d.value.attendance_donut || {}
    drawDonut(donutCanvas.value, [
        { value: donut.present  || 0, color: '#10b981' },
        { value: donut.absent   || 0, color: '#ef4444' },
        { value: donut.late     || 0, color: '#f59e0b' },
        { value: donut.half_day || 0, color: '#6366f1' },
    ])

    const admissionTrend = d.value.admission_trend || []
    drawBarChart(attendanceCanvas.value, admissionTrend.map(x=>x.count), admissionTrend.map(x=>x.month), '#6366f1', '#818cf8')
})

// ── Helpers ─────────────────────────────────────────────────
const fmt = (n) => typeof n === 'number' ? n.toLocaleString('en-IN') : '—'
const fmtCur = (n) => typeof n === 'number' ? usePage().props.school.currency + n.toLocaleString('en-IN', { maximumFractionDigits: 0 }) : '—'

const attendancePct = computed(() => {
    const k = kpi.value
    if (!k.attendance_marked) return 0
    return Math.round((k.present_today / k.attendance_marked) * 100)
})

const hostelPct = computed(() => {
    const k = kpi.value
    if (!k.hostel_capacity) return 0
    return Math.round((k.hostel_occupied / k.hostel_capacity) * 100)
})

// ── New computed helpers ────────────────────────────────────
const adminName = computed(() => d.value.admin_name || '')
const greeting  = computed(() => {
    const h = new Date().getHours()
    return h < 12 ? 'Good Morning' : h < 17 ? 'Good Afternoon' : 'Good Evening'
})

const nextExam        = computed(() => d.value.next_exam || null)
const birthdaysToday  = computed(() => d.value.birthdays_today || [])
const absentStaff     = computed(() => d.value.absent_staff || [])
const attendanceDonut = computed(() => d.value.attendance_donut || {})
const feeProgress     = computed(() => d.value.fee_progress || {})
const classAttendance = computed(() => d.value.class_attendance || [])
const pendingEditList = computed(() => d.value.pending_edit_list || [])
const pendingEditCount= computed(() => d.value.pending_edit_count || 0)

// ── Donut chart canvas ──────────────────────────────────────
const donutCanvas = ref(null)

function drawDonut(canvas, segments) {
    if (!canvas) return
    const ctx = canvas.getContext('2d')
    const size = canvas.width
    const cx = size / 2, cy = size / 2
    const R = size * 0.38, r = size * 0.24
    const total = segments.reduce((s, seg) => s + seg.value, 0)
    ctx.clearRect(0, 0, size, size)
    if (total === 0) {
        ctx.beginPath()
        ctx.arc(cx, cy, R, 0, Math.PI * 2)
        ctx.fillStyle = '#f1f5f9'
        ctx.fill()
        ctx.beginPath()
        ctx.arc(cx, cy, r, 0, Math.PI * 2)
        ctx.fillStyle = '#fff'
        ctx.fill()
        return
    }
    let startAngle = -Math.PI / 2
    segments.forEach(seg => {
        if (!seg.value) return
        const slice = (seg.value / total) * Math.PI * 2
        ctx.beginPath()
        ctx.moveTo(cx, cy)
        ctx.arc(cx, cy, R, startAngle, startAngle + slice)
        ctx.closePath()
        ctx.fillStyle = seg.color
        ctx.fill()
        startAngle += slice
    })
    // Center hole
    ctx.beginPath()
    ctx.arc(cx, cy, r, 0, Math.PI * 2)
    ctx.fillStyle = '#fff'
    ctx.fill()
    // Center text
    const pct = total > 0 ? Math.round(((segments[0]?.value || 0) / total) * 100) : 0
    ctx.fillStyle = '#1e293b'
    ctx.font = `bold ${size * 0.16}px Inter, sans-serif`
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(pct + '%', cx, cy - size * 0.04)
    ctx.fillStyle = '#94a3b8'
    ctx.font = `${size * 0.09}px Inter, sans-serif`
    ctx.fillText('Present', cx, cy + size * 0.1)
}

// Active dashboard tab
const activeTab = ref('admissions')
</script>

<template>
    <div class="school-dashboard">

        <!-- ═══════════════════════════════════════════════════
             SECTION 1 — DARK HERO BANNER
        ════════════════════════════════════════════════════ -->
        <div class="hero-banner">
            <!-- Decorative background elements -->
            <div class="hero-orb hero-orb--1"></div>
            <div class="hero-orb hero-orb--2"></div>
            <div class="hero-grid-lines"></div>

            <!-- Left col: School identity -->
            <div class="hero-identity">
                <div class="hero-school-tag">COMMAND CENTER</div>
                <h1 class="hero-school-name">{{ props.school?.name ?? 'School Dashboard' }}</h1>
                <p class="hero-greeting">{{ greeting }}, <strong>{{ adminName || 'Administrator' }}</strong></p>
                <div class="hero-date-time">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="hero-icon"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                    {{ schoolStore.fmtDate(schoolStore.today()) }}
                </div>
            </div>

            <!-- Center col: Pulse stat chips -->
            <div class="hero-chips">
                <div class="pulse-chip pulse-chip--indigo">
                    <div class="pulse-chip-dot pulse-chip-dot--indigo"></div>
                    <div class="pulse-chip-body">
                        <span class="pulse-chip-val">{{ fmt(kpi.total_students) }}</span>
                        <span class="pulse-chip-label">STUDENTS</span>
                    </div>
                </div>
                <div class="pulse-chip pulse-chip--emerald">
                    <div class="pulse-chip-dot pulse-chip-dot--emerald"></div>
                    <div class="pulse-chip-body">
                        <span class="pulse-chip-val">{{ fmt(kpi.total_staff) }}</span>
                        <span class="pulse-chip-label">STAFF</span>
                    </div>
                </div>
                <div class="pulse-chip" :class="attendancePct >= 85 ? 'pulse-chip--emerald' : attendancePct >= 75 ? 'pulse-chip--amber' : 'pulse-chip--red'">
                    <div class="pulse-chip-dot" :class="attendancePct >= 85 ? 'pulse-chip-dot--emerald' : attendancePct >= 75 ? 'pulse-chip-dot--amber' : 'pulse-chip-dot--red'"></div>
                    <div class="pulse-chip-body">
                        <span class="pulse-chip-val">{{ attendancePct }}%</span>
                        <span class="pulse-chip-label">ATTENDANCE</span>
                    </div>
                </div>
            </div>

            <!-- Right col: Exam countdown + today fee -->
            <div class="hero-right-col">
                <div v-if="nextExam" class="hero-exam-box" :class="nextExam.days_left <= 3 ? 'hero-exam-box--urgent' : ''">
                    <div class="hero-exam-tag">NEXT EXAM</div>
                    <div class="hero-exam-title">{{ nextExam.title }}</div>
                    <div class="hero-exam-countdown">
                        <span class="hero-exam-days" v-if="nextExam.days_left > 0">{{ nextExam.days_left }}</span>
                        <span class="hero-exam-days" v-else>!</span>
                        <span class="hero-exam-unit" v-if="nextExam.days_left > 0">days left</span>
                        <span class="hero-exam-unit" v-else>TODAY</span>
                    </div>
                    <div class="hero-exam-date">{{ schoolStore.fmtDate(nextExam.date) }}</div>
                </div>
                <div class="hero-fee-box">
                    <div class="hero-fee-tag">FEE COLLECTED TODAY</div>
                    <div class="hero-fee-amount">{{ fmtCur(kpi.today_fee) }}</div>
                    <div class="hero-fee-sub">{{ fmtCur(kpi.month_fee) }} this month</div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             SECTION 2 — BENTO GRID
        ════════════════════════════════════════════════════ -->
        <div class="section-label">OVERVIEW</div>
        <div class="bento-grid">

            <!-- Students tile -->
            <div class="bento-tile bento-tile--students" style="grid-area:students">
                <div class="bento-tag bento-tag--indigo">STUDENTS</div>
                <div class="bento-big-num" style="color:#6366f1">{{ fmt(kpi.total_students) }}</div>
                <div class="bento-desc">
                    <span class="bento-badge bento-badge--green">+{{ fmt(kpi.new_students_month) }}</span> this month
                </div>
                <div class="bento-sub-row">
                    <span class="bento-sub-item">{{ fmt(kpi.total_classes) }} classes</span>
                    <span class="bento-dot-sep"></span>
                    <span class="bento-sub-item">{{ fmt(kpi.total_sections) }} sections</span>
                </div>
            </div>

            <!-- Staff tile -->
            <div class="bento-tile bento-tile--staff" style="grid-area:staff">
                <div class="bento-tag bento-tag--violet">STAFF</div>
                <div class="bento-big-num" style="color:#8b5cf6">{{ fmt(kpi.total_staff) }}</div>
                <div class="bento-desc">
                    <span class="bento-badge" :class="kpi.staff_on_leave > 0 ? 'bento-badge--amber' : 'bento-badge--green'">
                        {{ fmt(kpi.staff_on_leave) }}
                    </span> on leave today
                </div>
                <div class="bento-sub-row">
                    <template v-if="(kpi.staff_marked_today ?? 0) === 0">
                        <span class="bento-sub-item" style="color:#b45309">Attendance not marked yet</span>
                    </template>
                    <template v-else>
                        <span class="bento-sub-item">{{ fmt(kpi.staff_present_today ?? 0) }} present · {{ fmt(kpi.staff_unmarked_today ?? 0) }} unmarked</span>
                    </template>
                </div>
            </div>

            <!-- Attendance tile -->
            <div class="bento-tile bento-tile--attend" style="grid-area:attend">
                <div class="bento-tag bento-tag--amber">ATTENDANCE</div>
                <!-- SVG Ring -->
                <div class="bento-ring-wrap">
                    <svg viewBox="0 0 80 80" class="bento-ring-svg">
                        <circle cx="40" cy="40" r="32" fill="none" stroke="#f1f5f9" stroke-width="8"/>
                        <circle cx="40" cy="40" r="32" fill="none"
                            :stroke="attendancePct >= 85 ? '#10b981' : attendancePct >= 75 ? '#f59e0b' : '#ef4444'"
                            stroke-width="8"
                            stroke-linecap="round"
                            :stroke-dasharray="`${attendancePct * 2.011} 201.1`"
                            stroke-dashoffset="50.3"
                            style="transition: stroke-dasharray 0.7s ease"/>
                        <text x="40" y="36" text-anchor="middle" font-size="13" font-weight="700" fill="#0f172a">{{ attendancePct }}%</text>
                        <text x="40" y="48" text-anchor="middle" font-size="6.5" fill="#94a3b8">PRESENT</text>
                    </svg>
                </div>
                <div class="bento-attend-row">
                    <template v-if="(kpi.attendance_marked ?? 0) === 0">
                        <span class="bento-attend-item" style="color:#b45309">Not marked yet</span>
                    </template>
                    <template v-else>
                        <span class="bento-attend-item bento-attend-item--green">{{ fmt(kpi.present_today) }} in</span>
                        <span class="bento-attend-item bento-attend-item--red">{{ fmt(kpi.absent_today) }} out</span>
                        <span v-if="(kpi.student_unmarked_today ?? 0) > 0" class="bento-attend-item" style="color:#b45309">{{ fmt(kpi.student_unmarked_today) }} unmarked</span>
                    </template>
                </div>
            </div>

            <!-- Fee tile -->
            <div class="bento-tile bento-tile--fee" style="grid-area:fee">
                <div class="bento-tag bento-tag--emerald">FEE</div>
                <div class="bento-big-num" style="color:#10b981; font-size:1.5rem">{{ fmtCur(kpi.today_fee) }}</div>
                <div class="bento-desc" style="color:#64748b">Today's collection</div>
                <div class="bento-sub-row">
                    <span class="bento-sub-item" style="color:#ef4444; font-weight:600">{{ fmtCur(kpi.pending_fees) }} pending</span>
                </div>
            </div>

            <!-- Fee chart tile (spans 2 columns) -->
            <div class="bento-tile bento-tile--feechart" style="grid-area:feechart">
                <div class="bento-tag bento-tag--emerald">FEE TREND</div>
                <div class="bento-chart-label">6-MONTH COLLECTION</div>
                <!-- SVG Line Chart -->
                <div class="svg-chart-wrap" style="height:120px">
                    <svg viewBox="0 0 400 100" preserveAspectRatio="none" class="fee-svg-chart" v-if="d.fee_trend?.length">
                        <defs>
                            <linearGradient id="feeGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.02"/>
                            </linearGradient>
                        </defs>
                        <!-- Grid lines -->
                        <line x1="0" y1="25" x2="400" y2="25" stroke="#e2e8f0" stroke-width="0.5"/>
                        <line x1="0" y1="50" x2="400" y2="50" stroke="#e2e8f0" stroke-width="0.5"/>
                        <line x1="0" y1="75" x2="400" y2="75" stroke="#e2e8f0" stroke-width="0.5"/>
                        <!-- Filled area -->
                        <path
                            :d="(() => {
                                const arr = d.fee_trend || []
                                if (arr.length < 2) return ''
                                const max = Math.max(...arr.map(x=>x.amount), 1)
                                const pts = arr.map((x,i) => ({ x: (i/(arr.length-1))*380+10, y: 90 - (x.amount/max)*80 }))
                                let path = `M ${pts[0].x} 90 L ${pts[0].x} ${pts[0].y}`
                                for(let i=1;i<pts.length;i++){
                                    const cpx1 = pts[i-1].x + (pts[i].x-pts[i-1].x)/2
                                    path += ` C ${cpx1} ${pts[i-1].y} ${cpx1} ${pts[i].y} ${pts[i].x} ${pts[i].y}`
                                }
                                path += ` L ${pts[pts.length-1].x} 90 Z`
                                return path
                            })()"
                            fill="url(#feeGrad)"
                        />
                        <!-- Line -->
                        <path
                            :d="(() => {
                                const arr = d.fee_trend || []
                                if (arr.length < 2) return ''
                                const max = Math.max(...arr.map(x=>x.amount), 1)
                                const pts = arr.map((x,i) => ({ x: (i/(arr.length-1))*380+10, y: 90 - (x.amount/max)*80 }))
                                let path = `M ${pts[0].x} ${pts[0].y}`
                                for(let i=1;i<pts.length;i++){
                                    const cpx1 = pts[i-1].x + (pts[i].x-pts[i-1].x)/2
                                    path += ` C ${cpx1} ${pts[i-1].y} ${cpx1} ${pts[i].y} ${pts[i].x} ${pts[i].y}`
                                }
                                return path
                            })()"
                            fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round"
                        />
                        <!-- Dots -->
                        <template v-if="d.fee_trend?.length">
                            <circle
                                v-for="(pt, i) in (() => {
                                    const arr = d.fee_trend || []
                                    const max = Math.max(...arr.map(x=>x.amount), 1)
                                    return arr.map((x,i) => ({ x: (i/(arr.length-1))*380+10, y: 90 - (x.amount/max)*80 }))
                                })()"
                                :key="i"
                                :cx="pt.x" :cy="pt.y" r="3"
                                fill="#fff" stroke="#10b981" stroke-width="1.5"
                            />
                        </template>
                    </svg>
                    <div v-else class="bento-empty-chart">No fee data</div>
                </div>
                <!-- Month labels -->
                <div class="svg-chart-labels" v-if="d.fee_trend?.length">
                    <span v-for="(f, i) in d.fee_trend" :key="i" class="svg-chart-label">{{ (f.month||'').split(' ')[0] }}</span>
                </div>
            </div>

            <!-- Pending fees tile -->
            <div class="bento-tile bento-tile--pend" style="grid-area:pend">
                <div class="bento-tag bento-tag--red">PENDING</div>
                <div class="bento-big-num" style="color:#ef4444; font-size:1.4rem">{{ fmtCur(kpi.pending_fees) }}</div>
                <div class="bento-desc" style="color:#64748b">Outstanding balance</div>
                <div class="bento-sub-row">
                    <span class="bento-sub-item">{{ (d.pending_fee_students||[]).length }} students</span>
                </div>
            </div>

            <!-- Admission chart tile (spans 2 columns) -->
            <div class="bento-tile bento-tile--admchart" style="grid-area:admchart">
                <div class="bento-tag bento-tag--indigo">ADMISSIONS</div>
                <div class="bento-chart-label">6-MONTH TREND</div>
                <!-- SVG Bar Chart -->
                <div class="svg-chart-wrap" style="height:120px">
                    <svg viewBox="0 0 400 100" preserveAspectRatio="none" class="adm-svg-chart" v-if="d.admission_trend?.length">
                        <defs>
                            <linearGradient id="admGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1"/>
                                <stop offset="100%" stop-color="#818cf8"/>
                            </linearGradient>
                        </defs>
                        <line x1="0" y1="33" x2="400" y2="33" stroke="#e2e8f0" stroke-width="0.5"/>
                        <line x1="0" y1="66" x2="400" y2="66" stroke="#e2e8f0" stroke-width="0.5"/>
                        <template v-if="d.admission_trend?.length">
                            <rect
                                v-for="(bar, i) in (() => {
                                    const arr = d.admission_trend || []
                                    const max = Math.max(...arr.map(x=>x.count), 1)
                                    const slotW = 400 / arr.length
                                    const barW = slotW * 0.5
                                    return arr.map((x,i) => ({
                                        x: i * slotW + (slotW - barW) / 2,
                                        y: 90 - (x.count / max) * 80,
                                        w: barW,
                                        h: (x.count / max) * 80,
                                        val: x.count
                                    }))
                                })()"
                                :key="i"
                                :x="bar.x" :y="bar.y" :width="bar.w" :height="bar.h"
                                fill="url(#admGrad)" rx="2"
                            />
                        </template>
                    </svg>
                    <div v-else class="bento-empty-chart">No admission data</div>
                </div>
                <div class="svg-chart-labels" v-if="d.admission_trend?.length">
                    <span v-for="(a, i) in d.admission_trend" :key="i" class="svg-chart-label">{{ (a.month||'').split(' ')[0] }}</span>
                </div>
            </div>

            <!-- Hostel tile -->
            <div class="bento-tile bento-tile--hostel" style="grid-area:hostel">
                <div class="bento-tag bento-tag--rose">HOSTEL</div>
                <div class="bento-big-num" style="color:#f43f5e">{{ fmt(kpi.hostel_occupied) }}<span class="bento-big-denom">/{{ fmt(kpi.hostel_capacity) }}</span></div>
                <div class="bento-desc" style="color:#64748b">Beds occupied</div>
                <div class="bento-progress-track">
                    <div class="bento-progress-fill" :style="{ width: hostelPct + '%', background: hostelPct > 90 ? '#ef4444' : hostelPct > 70 ? '#f59e0b' : '#10b981' }"></div>
                </div>
                <div class="bento-progress-label">{{ hostelPct }}% full</div>
            </div>

            <!-- Routes tile -->
            <div class="bento-tile bento-tile--routes" style="grid-area:routes">
                <div class="bento-tag bento-tag--blue">TRANSPORT</div>
                <div class="bento-big-num" style="color:#3b82f6">{{ fmt(kpi.active_routes) }}</div>
                <div class="bento-desc" style="color:#64748b">Active routes today</div>
                <div class="bento-sub-row">
                    <span class="bento-sub-item">Running now</span>
                </div>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════
             SECTION 3 — DATA TABLES ROW
        ════════════════════════════════════════════════════ -->
        <div class="h-divider"></div>
        <div class="section-label">RECENT ACTIVITY</div>
        <div class="tables-row">

            <!-- Recent Admissions -->
            <div class="surf-card surf-card--left-indigo">
                <div class="surf-card-hd">
                    <span class="surf-card-title">RECENT ADMISSIONS</span>
                    <Link href="/school/students" class="surf-card-link">View All →</Link>
                </div>
                <div v-if="!d.recent_admissions?.length" class="surf-empty">No recent admissions</div>
                <div v-for="s in (d.recent_admissions||[])" :key="s.id" class="surf-row">
                    <div class="surf-avatar surf-avatar--indigo">{{ s.name?.charAt(0)?.toUpperCase() }}</div>
                    <div class="surf-row-body">
                        <div class="surf-row-name">{{ s.name }}</div>
                        <div class="surf-row-meta">{{ s.class }} – {{ s.section }}</div>
                    </div>
                    <div class="surf-row-right">
                        <div class="surf-row-mono">{{ s.admission_no }}</div>
                        <div class="surf-row-date">{{ s.admitted_at }}</div>
                    </div>
                </div>
            </div>

            <!-- Recent Fee Payments -->
            <div class="surf-card surf-card--left-emerald">
                <div class="surf-card-hd">
                    <span class="surf-card-title">RECENT PAYMENTS</span>
                    <Link href="/school/finance/day-book" class="surf-card-link">View All →</Link>
                </div>
                <div v-if="!d.recent_payments?.length" class="surf-empty">No recent payments</div>
                <div v-for="p in (d.recent_payments||[])" :key="p.id" class="surf-row">
                    <div class="surf-avatar surf-avatar--emerald" style="font-size:0.75rem">₹</div>
                    <div class="surf-row-body">
                        <div class="surf-row-name">{{ p.student?.trim() || '—' }}</div>
                        <div class="surf-row-meta surf-row-mono" style="font-size:0.7rem">{{ p.receipt_no }}</div>
                    </div>
                    <div class="surf-row-right">
                        <div class="surf-pay-amount">{{ fmtCur(p.amount) }}</div>
                        <span class="surf-mode-badge">{{ p.mode }}</span>
                    </div>
                </div>
            </div>

            <!-- Pending Edit Requests -->
            <div class="surf-card surf-card--left-orange">
                <div class="surf-card-hd">
                    <span class="surf-card-title">EDIT REQUESTS</span>
                    <span v-if="pendingEditCount > 0" class="surf-count-badge">{{ pendingEditCount }}</span>
                    <Link href="/school/edit-requests" class="surf-card-link">Review All →</Link>
                </div>
                <div v-if="!pendingEditList.length" class="surf-empty">No pending requests</div>
                <div v-for="r in pendingEditList" :key="r.id" class="surf-row">
                    <div class="surf-avatar surf-avatar--orange">{{ r.name?.charAt(0)?.toUpperCase() }}</div>
                    <div class="surf-row-body">
                        <div class="surf-row-name">{{ r.name }}</div>
                        <div class="surf-row-meta">{{ r.type }} · {{ r.fields }}</div>
                    </div>
                    <div class="surf-row-right">
                        <div class="surf-row-date">{{ r.ago }}</div>
                        <span class="surf-review-link">Review →</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════
             SECTION 4 — PEOPLE STRIP
        ════════════════════════════════════════════════════ -->
        <div class="h-divider"></div>
        <div class="section-label">PEOPLE TODAY</div>
        <div class="people-strip">

            <!-- Birthday cards -->
            <template v-if="birthdaysToday.length">
                <div v-for="(b, i) in birthdaysToday" :key="'bd'+i" class="person-card person-card--birthday">
                    <div class="person-card-icon">🎂</div>
                    <div class="person-card-avatar">{{ b.name?.charAt(0)?.toUpperCase() }}</div>
                    <div class="person-card-name">{{ b.name }}</div>
                    <div class="person-card-sub">{{ b.type }}</div>
                </div>
            </template>
            <div v-else class="person-card-empty person-card-empty--amber">
                <span style="font-size:1.4rem">🎂</span>
                <span>No birthdays today</span>
            </div>

            <!-- Divider line -->
            <div class="people-strip-sep"></div>

            <!-- Absent staff cards -->
            <template v-if="absentStaff.length">
                <div v-for="(s, i) in absentStaff" :key="'as'+i" class="person-card person-card--absent">
                    <div class="person-card-icon" style="background:#fef2f2;color:#ef4444">✗</div>
                    <div class="person-card-avatar person-card-avatar--red">{{ s.name?.charAt(0)?.toUpperCase() }}</div>
                    <div class="person-card-name">{{ s.name }}</div>
                    <div class="person-card-sub">{{ s.designation }}</div>
                </div>
            </template>
            <div v-else-if="(kpi.staff_marked_today ?? 0) === 0" class="person-card-empty person-card-empty--amber">
                <span style="font-size:1.4rem">⏳</span>
                <span>Staff attendance not marked yet</span>
            </div>
            <div v-else class="person-card-empty person-card-empty--green">
                <span style="font-size:1.4rem">✓</span>
                <span>All staff present</span>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════
             SECTION 5 — ALERTS + ACTIVITY
        ════════════════════════════════════════════════════ -->
        <div class="h-divider"></div>
        <div class="section-label">ALERTS</div>
        <div class="alerts-duo">

            <!-- Low Attendance Students -->
            <div class="surf-card surf-card--left-red">
                <div class="surf-card-hd">
                    <span class="surf-card-title">LOW ATTENDANCE</span>
                    <Link href="/school/attendance/report" class="surf-card-link">Report →</Link>
                </div>
                <div v-if="!d.low_attendance?.length" class="surf-empty">All students above 75%</div>
                <div v-for="(a, i) in (d.low_attendance||[])" :key="i" class="alert-row">
                    <div class="alert-row-name">{{ a.student?.trim() || '—' }}</div>
                    <div class="alert-row-bar-wrap">
                        <div class="alert-row-bar-track">
                            <div class="alert-row-bar-fill"
                                :style="{ width: a.percentage + '%', background: a.percentage < 60 ? '#ef4444' : '#f59e0b' }">
                            </div>
                        </div>
                        <span class="alert-pct-badge" :class="a.percentage < 60 ? 'alert-pct-badge--red' : 'alert-pct-badge--amber'">{{ a.percentage }}%</span>
                    </div>
                </div>
            </div>

            <!-- Pending Fee Students -->
            <div class="surf-card surf-card--left-orange">
                <div class="surf-card-hd">
                    <span class="surf-card-title">PENDING FEE STUDENTS</span>
                    <Link href="/school/finance/due-report" class="surf-card-link">View →</Link>
                </div>
                <div v-if="!d.pending_fee_students?.length" class="surf-empty">No pending fees</div>
                <div v-for="(f, i) in (d.pending_fee_students||[])" :key="i" class="alert-row">
                    <div class="alert-row-left">
                        <div class="alert-row-name">{{ f.student?.trim() || '—' }}</div>
                    </div>
                    <span class="alert-balance-badge">{{ fmtCur(f.balance) }}</span>
                </div>
            </div>

        </div>

        <!-- ═══════════════════════════════════════════════════
             SECTION 6 — BOTTOM ROW: Announcements + Calendar
        ════════════════════════════════════════════════════ -->
        <div class="h-divider"></div>
        <div class="section-label">COMMUNICATIONS & SCHEDULE</div>
        <div class="bottom-duo">

            <!-- Announcements (60%) -->
            <div class="surf-card" style="border-left: 4px solid #8b5cf6">
                <div class="surf-card-hd">
                    <span class="surf-card-title">ANNOUNCEMENTS</span>
                    <Link href="/school/announcements" class="surf-card-link">View All →</Link>
                </div>
                <div v-if="!d.announcements?.length" class="surf-empty">No recent announcements</div>
                <div v-for="a in (d.announcements||[])" :key="a.id" class="ann-item"
                    :style="{
                        borderLeft: a.audience === 'All' ? '3px solid #6366f1' :
                                    a.audience === 'Staff' ? '3px solid #f59e0b' :
                                    a.audience === 'Students' ? '3px solid #10b981' : '3px solid #94a3b8'
                    }">
                    <div class="ann-item-title">{{ a.title }}</div>
                    <div class="ann-item-meta">
                        <span class="ann-item-sender">{{ a.sender }}</span>
                        <span class="ann-item-sep">·</span>
                        <span class="ann-item-time">{{ a.sent_at }}</span>
                        <span class="ann-item-sep">·</span>
                        <span class="ann-item-audience"
                            :style="{
                                color: a.audience === 'All' ? '#6366f1' :
                                       a.audience === 'Staff' ? '#d97706' :
                                       a.audience === 'Students' ? '#059669' : '#64748b'
                            }">{{ a.audience }}</span>
                    </div>
                </div>
            </div>

            <!-- Mini Calendar (40%) -->
            <div class="surf-card" style="border-left: 4px solid #6366f1">
                <div class="surf-card-hd" style="flex-wrap:wrap;gap:8px">
                    <span class="surf-card-title">CALENDAR</span>
                    <div class="cal-nav-row">
                        <button class="cal-nav-btn" @click="prevMonth">
                            <svg viewBox="0 0 16 16" fill="currentColor"><path d="M10 12L6 8l4-4"/></svg>
                        </button>
                        <span class="cal-month-label">{{ monthLabel }}</span>
                        <button class="cal-nav-btn" @click="nextMonth">
                            <svg viewBox="0 0 16 16" fill="currentColor"><path d="M6 12l4-4-4-4"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Legend -->
                <div class="cal-legend-row">
                    <span class="cal-leg"><span class="cal-leg-dot" style="background:#ef4444"></span>Holiday</span>
                    <span class="cal-leg"><span class="cal-leg-dot" style="background:#3b82f6"></span>Exam</span>
                    <span class="cal-leg"><span class="cal-leg-dot" style="background:#6366f1"></span>Today</span>
                </div>

                <!-- Day headers -->
                <div class="cal-head-row">
                    <span v-for="wd in ['S','M','T','W','T','F','S']" :key="wd" class="cal-head-cell">{{ wd }}</span>
                </div>

                <!-- Grid -->
                <div class="cal-body-grid">
                    <div
                        v-for="(cell, idx) in calendarGrid"
                        :key="idx"
                        :class="[
                            'cal-day-cell',
                            !cell && 'cal-day-cell--empty',
                            cell && isToday(cell) && 'cal-day-cell--today',
                        ]"
                    >
                        <span v-if="cell" class="cal-day-num">{{ cell }}</span>
                        <div v-if="cell && eventsOnDay(cell).length" class="cal-day-dots">
                            <span
                                v-for="(ev, ei) in eventsOnDay(cell).slice(0,2)"
                                :key="ei"
                                class="cal-day-dot"
                                :style="{ background: ev.color === 'red' ? '#ef4444' : '#3b82f6' }"
                            ></span>
                        </div>
                    </div>
                </div>

                <!-- Upcoming events -->
                <div class="cal-upcoming-section">
                    <div class="cal-upcoming-label">UPCOMING EVENTS</div>
                    <div v-for="(h, i) in (d.upcoming_holidays||[]).slice(0,4)" :key="'h'+i" class="cal-event-row">
                        <span class="cal-event-dot" style="background:#ef4444"></span>
                        <span class="cal-event-date">{{ schoolStore.fmtDate(h.date) }}</span>
                        <span class="cal-event-title">{{ h.title }}</span>
                        <span class="cal-event-type" style="color:#ef4444">{{ h.type }}</span>
                    </div>
                    <div v-for="(e, i) in (d.calendar_exams||[]).slice(0,3)" :key="'e'+i" class="cal-event-row">
                        <span class="cal-event-dot" style="background:#3b82f6"></span>
                        <span class="cal-event-date">{{ schoolStore.fmtDate(e.date) }}</span>
                        <span class="cal-event-title">{{ e.title }}</span>
                        <span class="cal-event-type" style="color:#3b82f6">Exam</span>
                    </div>
                    <div v-if="!d.upcoming_holidays?.length && !d.calendar_exams?.length" class="surf-empty">No upcoming events</div>
                </div>
            </div>

        </div>

        <!-- Hidden canvases kept for script compatibility -->
        <canvas ref="feeCanvas" width="500" height="200" style="display:none"></canvas>
        <canvas ref="attendanceCanvas" width="340" height="180" style="display:none"></canvas>
        <canvas ref="donutCanvas" width="160" height="160" style="display:none"></canvas>

    </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════════
   ROOT & CSS CUSTOM PROPERTIES
════════════════════════════════════════════════════════════ */
.school-dashboard {
    --accent: #6366f1;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --bg: #f1f5f9;
    --surface: #fff;
    --border: #e2e8f0;
    --radius: 10px;

    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: #0f172a;
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 0 0 48px;
}

/* ═══════════════════════════════════════════════════════════
   UTILITIES
════════════════════════════════════════════════════════════ */
.h-divider {
    height: 1px;
    background: var(--border);
    margin: 0;
}

.section-label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: #94a3b8;
    text-transform: uppercase;
    padding: 0 2px;
}

/* ═══════════════════════════════════════════════════════════
   SECTION 1 — DARK HERO BANNER
════════════════════════════════════════════════════════════ */
.hero-banner {
    position: relative;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    border-radius: var(--radius);
    padding: 32px 32px 28px;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 32px;
    align-items: center;
    overflow: hidden;
    color: #fff;
}

.hero-orb {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}
.hero-orb--1 {
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
    top: -80px;
    right: 120px;
}
.hero-orb--2 {
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(16,185,129,0.1) 0%, transparent 70%);
    bottom: -60px;
    left: 200px;
}
.hero-grid-lines {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}

/* Left col */
.hero-identity {
    position: relative;
    z-index: 1;
}
.hero-school-tag {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: #6366f1;
    background: rgba(99,102,241,0.15);
    border: 1px solid rgba(99,102,241,0.3);
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    margin-bottom: 10px;
}
.hero-school-name {
    font-size: 1.6rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 6px;
    line-height: 1.2;
}
.hero-greeting {
    font-size: 0.9rem;
    color: #94a3b8;
    margin: 0 0 12px;
}
.hero-greeting strong {
    color: #e2e8f0;
}
.hero-date-time {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    color: #64748b;
}
.hero-icon {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}

/* Center col — pulse chips */
.hero-chips {
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
    z-index: 1;
}

.pulse-chip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-radius: 50px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(4px);
    min-width: 160px;
}
.pulse-chip--indigo {
    border-color: rgba(99,102,241,0.4);
    background: rgba(99,102,241,0.12);
}
.pulse-chip--emerald {
    border-color: rgba(16,185,129,0.4);
    background: rgba(16,185,129,0.12);
}
.pulse-chip--amber {
    border-color: rgba(245,158,11,0.4);
    background: rgba(245,158,11,0.12);
}
.pulse-chip--red {
    border-color: rgba(239,68,68,0.4);
    background: rgba(239,68,68,0.12);
}

.pulse-chip-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    animation: chipPulse 2s infinite;
}
.pulse-chip-dot--indigo  { background: #6366f1; box-shadow: 0 0 0 0 rgba(99,102,241,0.5); }
.pulse-chip-dot--emerald { background: #10b981; box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
.pulse-chip-dot--amber   { background: #f59e0b; box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
.pulse-chip-dot--red     { background: #ef4444; box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }

@keyframes chipPulse {
    0%   { box-shadow: 0 0 0 0 rgba(99,102,241,0.5); }
    70%  { box-shadow: 0 0 0 6px rgba(99,102,241,0); }
    100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
}

.pulse-chip-body {
    display: flex;
    flex-direction: column;
}
.pulse-chip-val {
    font-size: 1.05rem;
    font-weight: 700;
    color: #f1f5f9;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.pulse-chip-label {
    font-size: 0.58rem;
    letter-spacing: 0.1em;
    color: #64748b;
    font-weight: 600;
}

/* Right col */
.hero-right-col {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: flex-end;
    position: relative;
    z-index: 1;
}

.hero-exam-box {
    background: rgba(99,102,241,0.15);
    border: 1px solid rgba(99,102,241,0.35);
    border-radius: var(--radius);
    padding: 14px 18px;
    text-align: right;
    min-width: 180px;
}
.hero-exam-box--urgent {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.35);
}
.hero-exam-tag {
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: #818cf8;
    margin-bottom: 4px;
}
.hero-exam-box--urgent .hero-exam-tag { color: #fca5a5; }
.hero-exam-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 6px;
}
.hero-exam-countdown {
    display: flex;
    align-items: baseline;
    gap: 4px;
    justify-content: flex-end;
}
.hero-exam-days {
    font-size: 2rem;
    font-weight: 800;
    color: #818cf8;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}
.hero-exam-box--urgent .hero-exam-days { color: #fca5a5; }
.hero-exam-unit {
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 500;
}
.hero-exam-date {
    font-size: 0.72rem;
    color: #475569;
    margin-top: 2px;
}

.hero-fee-box {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.3);
    border-radius: var(--radius);
    padding: 14px 18px;
    text-align: right;
    min-width: 180px;
}
.hero-fee-tag {
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: #34d399;
    margin-bottom: 4px;
}
.hero-fee-amount {
    font-size: 1.3rem;
    font-weight: 800;
    color: #10b981;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.hero-fee-sub {
    font-size: 0.72rem;
    color: #475569;
    margin-top: 3px;
}

/* ═══════════════════════════════════════════════════════════
   SECTION 2 — BENTO GRID
════════════════════════════════════════════════════════════ */
.bento-grid {
    display: grid;
    grid-template-areas:
        "students  staff    attend   fee"
        "feechart  feechart attend   pend"
        "admchart  admchart hostel   routes";
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: auto auto auto;
    gap: 14px;
}

.bento-tile {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 18px 20px 16px;
    border: 1px solid var(--border);
    position: relative;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.bento-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

/* Colored left borders per tile type */
.bento-tile--students { border-left: 4px solid #6366f1; }
.bento-tile--staff    { border-left: 4px solid #8b5cf6; }
.bento-tile--attend   { border-left: 4px solid #f59e0b; }
.bento-tile--fee      { border-left: 4px solid #10b981; }
.bento-tile--feechart { border-left: 4px solid #10b981; }
.bento-tile--pend     { border-left: 4px solid #ef4444; }
.bento-tile--admchart { border-left: 4px solid #6366f1; }
.bento-tile--hostel   { border-left: 4px solid #f43f5e; }
.bento-tile--routes   { border-left: 4px solid #3b82f6; }

.bento-tag {
    display: inline-block;
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    padding: 2px 7px;
    border-radius: 4px;
    margin-bottom: 10px;
    position: absolute;
    top: 14px;
    right: 14px;
}
.bento-tag--indigo  { background: #eef2ff; color: #4338ca; }
.bento-tag--violet  { background: #f5f3ff; color: #7c3aed; }
.bento-tag--amber   { background: #fffbeb; color: #b45309; }
.bento-tag--emerald { background: #ecfdf5; color: #065f46; }
.bento-tag--red     { background: #fef2f2; color: #b91c1c; }
.bento-tag--rose    { background: #fff1f2; color: #be123c; }
.bento-tag--blue    { background: #eff6ff; color: #1d4ed8; }

.bento-big-num {
    font-size: 2rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
    margin-top: 4px;
}
.bento-big-denom {
    font-size: 1rem;
    font-weight: 500;
    color: #94a3b8;
}
.bento-desc {
    font-size: 0.78rem;
    color: #64748b;
    margin: 4px 0;
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}
.bento-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    font-variant-numeric: tabular-nums;
}
.bento-badge--green  { background: #ecfdf5; color: #059669; }
.bento-badge--amber  { background: #fffbeb; color: #d97706; }
.bento-sub-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
}
.bento-sub-item {
    font-size: 0.7rem;
    color: #94a3b8;
}
.bento-dot-sep {
    width: 3px;
    height: 3px;
    background: #cbd5e1;
    border-radius: 50%;
}

/* Attendance ring tile */
.bento-ring-wrap {
    display: flex;
    justify-content: center;
    margin: 6px 0;
}
.bento-ring-svg {
    width: 80px;
    height: 80px;
    overflow: visible;
}
.bento-attend-row {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-top: 4px;
}
.bento-attend-item {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    font-variant-numeric: tabular-nums;
}
.bento-attend-item--green { background: #ecfdf5; color: #059669; }
.bento-attend-item--red   { background: #fef2f2; color: #dc2626; }

/* Chart tiles */
.bento-chart-label {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #94a3b8;
    margin-bottom: 6px;
}
.svg-chart-wrap {
    width: 100%;
    overflow: hidden;
    border-radius: 6px;
}
.fee-svg-chart,
.adm-svg-chart {
    width: 100%;
    height: 100%;
    display: block;
}
.svg-chart-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
}
.svg-chart-label {
    font-size: 0.65rem;
    color: #94a3b8;
    text-align: center;
    flex: 1;
}
.bento-empty-chart {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-size: 0.8rem;
    color: #cbd5e1;
}

/* Progress bar in hostel tile */
.bento-progress-track {
    height: 5px;
    background: #f1f5f9;
    border-radius: 10px;
    margin-top: 8px;
    overflow: hidden;
}
.bento-progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.6s ease;
}
.bento-progress-label {
    font-size: 0.68rem;
    color: #94a3b8;
    margin-top: 4px;
    font-variant-numeric: tabular-nums;
}

/* ═══════════════════════════════════════════════════════════
   SURFACE CARDS (shared base for sections 3/5/6)
════════════════════════════════════════════════════════════ */
.surf-card {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 18px 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.surf-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}
.surf-card--left-indigo  { border-left: 4px solid #6366f1; }
.surf-card--left-emerald { border-left: 4px solid #10b981; }
.surf-card--left-orange  { border-left: 4px solid #f97316; }
.surf-card--left-red     { border-left: 4px solid #ef4444; }

.surf-card-hd {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
}
.surf-card-title {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #475569;
    flex: 1;
}
.surf-card-link {
    font-size: 0.72rem;
    color: #6366f1;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.15s;
}
.surf-card-link:hover { color: #4338ca; }
.surf-count-badge {
    background: #fef2f2;
    color: #dc2626;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
}
.surf-empty {
    font-size: 0.8rem;
    color: #cbd5e1;
    padding: 12px 0;
    text-align: center;
}

/* Surf rows */
.surf-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.12s;
}
.surf-row:last-child { border-bottom: none; }
.surf-row:hover { background: #f8fafc; margin: 0 -20px; padding: 8px 20px; border-radius: 6px; }

.surf-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 700;
    flex-shrink: 0;
}
.surf-avatar--indigo  { background: #eef2ff; color: #4338ca; }
.surf-avatar--emerald { background: #ecfdf5; color: #065f46; }
.surf-avatar--orange  { background: #fff7ed; color: #c2410c; }

.surf-row-body {
    flex: 1;
    min-width: 0;
}
.surf-row-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.surf-row-meta {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 1px;
}
.surf-row-mono {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.68rem;
    color: #94a3b8;
}
.surf-row-right {
    text-align: right;
    flex-shrink: 0;
}
.surf-row-date {
    font-size: 0.68rem;
    color: #94a3b8;
}
.surf-pay-amount {
    font-size: 0.9rem;
    font-weight: 700;
    color: #10b981;
    font-variant-numeric: tabular-nums;
}
.surf-mode-badge {
    font-size: 0.62rem;
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 1px 6px;
    border-radius: 4px;
    font-weight: 600;
    margin-top: 2px;
    display: inline-block;
}
.surf-review-link {
    font-size: 0.7rem;
    color: #f97316;
    font-weight: 600;
    cursor: pointer;
    display: block;
    margin-top: 2px;
}
.surf-review-link:hover { color: #ea580c; }

/* ═══════════════════════════════════════════════════════════
   SECTION 3 — TABLES ROW
════════════════════════════════════════════════════════════ */
.tables-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}

/* ═══════════════════════════════════════════════════════════
   SECTION 4 — PEOPLE STRIP
════════════════════════════════════════════════════════════ */
.people-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    padding: 4px 0;
}
.people-strip-sep {
    width: 1px;
    height: 60px;
    background: var(--border);
    flex-shrink: 0;
}

.person-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 12px 16px 10px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    min-width: 96px;
    transition: transform 0.15s, box-shadow 0.15s;
    cursor: default;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.person-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.1);
}
.person-card--birthday {
    border-color: #fde68a;
    background: linear-gradient(135deg, #fffbeb 0%, #fff 100%);
}
.person-card--absent {
    border-color: #fecaca;
    background: linear-gradient(135deg, #fef2f2 0%, #fff 100%);
}

.person-card-icon {
    font-size: 1rem;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fef3c7;
    margin-bottom: 2px;
}
.person-card-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fde68a;
    color: #92400e;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
}
.person-card-avatar--red {
    background: #fecaca;
    color: #991b1b;
}
.person-card-name {
    font-size: 0.75rem;
    font-weight: 600;
    color: #1e293b;
    text-align: center;
    max-width: 80px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.person-card-sub {
    font-size: 0.65rem;
    color: #94a3b8;
    text-align: center;
}

.person-card-empty {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    border-radius: var(--radius);
    border: 1px dashed var(--border);
    font-size: 0.8rem;
    color: #94a3b8;
}
.person-card-empty--amber { border-color: #fde68a; color: #b45309; }
.person-card-empty--green { border-color: #bbf7d0; color: #059669; }

/* ═══════════════════════════════════════════════════════════
   SECTION 5 — ALERTS DUO
════════════════════════════════════════════════════════════ */
.alerts-duo {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.alert-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f8fafc;
}
.alert-row:last-child { border-bottom: none; }

.alert-row-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 100px;
    max-width: 140px;
}
.alert-row-bar-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}
.alert-row-bar-track {
    flex: 1;
    height: 5px;
    background: #f1f5f9;
    border-radius: 10px;
    overflow: hidden;
}
.alert-row-bar-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s ease;
}
.alert-pct-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    font-variant-numeric: tabular-nums;
    flex-shrink: 0;
}
.alert-pct-badge--red   { background: #fef2f2; color: #dc2626; }
.alert-pct-badge--amber { background: #fffbeb; color: #d97706; }

.alert-row-left {
    flex: 1;
    min-width: 0;
}
.alert-row-meta {
    font-size: 0.68rem;
    color: #94a3b8;
}
.alert-balance-badge {
    font-size: 0.82rem;
    font-weight: 700;
    color: #ef4444;
    font-variant-numeric: tabular-nums;
    background: #fef2f2;
    padding: 2px 8px;
    border-radius: 4px;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════════════
   SECTION 6 — BOTTOM DUO
════════════════════════════════════════════════════════════ */
.bottom-duo {
    display: grid;
    grid-template-columns: 60fr 40fr;
    gap: 14px;
}

/* Announcement item */
.ann-item {
    padding: 10px 12px;
    border-radius: 6px;
    background: #f8fafc;
    margin-bottom: 8px;
    transition: background 0.15s;
}
.ann-item:last-child { margin-bottom: 0; }
.ann-item:hover { background: #f1f5f9; }

.ann-item-title {
    font-size: 0.83rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}
.ann-item-meta {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: #94a3b8;
}
.ann-item-sender { font-weight: 500; color: #64748b; }
.ann-item-sep    { color: #cbd5e1; }
.ann-item-time   { font-size: 0.68rem; }
.ann-item-audience {
    font-weight: 600;
    font-size: 0.68rem;
}

/* Calendar */
.cal-nav-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
}
.cal-nav-btn {
    width: 22px;
    height: 22px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.12s;
    padding: 0;
}
.cal-nav-btn:hover { background: #f1f5f9; }
.cal-nav-btn svg {
    width: 12px;
    height: 12px;
    stroke: #475569;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.cal-month-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
}

.cal-legend-row {
    display: flex;
    gap: 12px;
    margin-bottom: 10px;
}
.cal-leg {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.65rem;
    color: #64748b;
}
.cal-leg-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.cal-head-row {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    margin-bottom: 4px;
}
.cal-head-cell {
    font-size: 0.62rem;
    font-weight: 600;
    color: #94a3b8;
    text-align: center;
    padding: 2px 0;
}

.cal-body-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
}
.cal-day-cell {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
    border-radius: 4px;
    cursor: default;
    position: relative;
    transition: background 0.12s;
}
.cal-day-cell:hover:not(.cal-day-cell--empty) {
    background: #f1f5f9;
}
.cal-day-cell--today {
    background: #6366f1 !important;
}
.cal-day-cell--today .cal-day-num {
    color: #fff !important;
}
.cal-day-cell--empty { opacity: 0; pointer-events: none; }
.cal-day-num {
    font-size: 0.72rem;
    font-weight: 500;
    color: #334155;
    font-variant-numeric: tabular-nums;
}
.cal-day-dots {
    display: flex;
    gap: 2px;
}
.cal-day-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
}

.cal-upcoming-section {
    margin-top: 14px;
    border-top: 1px solid var(--border);
    padding-top: 12px;
}
.cal-upcoming-label {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #94a3b8;
    margin-bottom: 8px;
}
.cal-event-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 0;
    border-bottom: 1px solid #f8fafc;
}
.cal-event-row:last-child { border-bottom: none; }
.cal-event-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.cal-event-date {
    font-size: 0.68rem;
    font-weight: 600;
    color: #475569;
    font-variant-numeric: tabular-nums;
    min-width: 36px;
}
.cal-event-title {
    font-size: 0.72rem;
    color: #334155;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cal-event-type {
    font-size: 0.62rem;
    font-weight: 600;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE — 768px
════════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
    .hero-banner {
        grid-template-columns: 1fr;
        grid-template-rows: auto auto auto;
    }
    .hero-chips {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: center;
    }
    .hero-right-col {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
    }
    .hero-exam-box,
    .hero-fee-box {
        min-width: 0;
        flex: 1;
        text-align: left;
    }

    .bento-grid {
        grid-template-areas:
            "students  staff"
            "attend    fee"
            "feechart  feechart"
            "pend      pend"
            "admchart  admchart"
            "hostel    routes";
        grid-template-columns: 1fr 1fr;
    }

    .tables-row {
        grid-template-columns: 1fr;
    }
    .alerts-duo {
        grid-template-columns: 1fr;
    }
    .bottom-duo {
        grid-template-columns: 1fr;
    }
    .people-strip-sep {
        display: none;
    }
}

@media (max-width: 480px) {
    .bento-grid {
        grid-template-areas:
            "students"
            "staff"
            "attend"
            "fee"
            "feechart"
            "pend"
            "admchart"
            "hostel"
            "routes";
        grid-template-columns: 1fr;
    }
    .hero-chips {
        flex-direction: column;
    }
}
</style>
