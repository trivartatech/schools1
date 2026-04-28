<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    holidays: { type: Array, default: () => [] },   // [{date: 'YYYY-MM-DD', title, type}]
    exams:    { type: Array, default: () => [] },   // [{date, title, type}]
})

const today = new Date()
const calendarDate = ref(new Date(today.getFullYear(), today.getMonth(), 1))

const calendarYear  = computed(() => calendarDate.value.getFullYear())
const calendarMonth = computed(() => calendarDate.value.getMonth())
const monthLabel = computed(() =>
    calendarDate.value.toLocaleString('default', { month: 'long', year: 'numeric' })
)

function prevMonth() { calendarDate.value = new Date(calendarYear.value, calendarMonth.value - 1, 1) }
function nextMonth() { calendarDate.value = new Date(calendarYear.value, calendarMonth.value + 1, 1) }

const daysInMonth = computed(() => new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate())
const firstDay    = computed(() => new Date(calendarYear.value, calendarMonth.value, 1).getDay())

const cells = computed(() => {
    const out = []
    for (let i = 0; i < firstDay.value; i++) out.push(null)
    for (let d = 1; d <= daysInMonth.value; d++) out.push(d)
    while (out.length % 7 !== 0) out.push(null)
    return out
})

const eventMap = computed(() => {
    const map = {}
    const add = (k, ev) => { (map[k] = map[k] || []).push(ev) }
    props.holidays.forEach(h => add(h.date, { ...h, color: 'red' }))
    props.exams.forEach(e => add(e.date, { ...e, color: 'indigo' }))
    return map
})

function dayKey(d) {
    return `${calendarYear.value}-${String(calendarMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
}
function dotsFor(d) { return d ? (eventMap.value[dayKey(d)] || []) : [] }
function isToday(d) {
    return d === today.getDate()
        && calendarMonth.value === today.getMonth()
        && calendarYear.value === today.getFullYear()
}

const hoveredDay = ref(null)
const hoveredEvents = computed(() => hoveredDay.value ? dotsFor(hoveredDay.value) : [])
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ monthLabel }}</h3>
            <div class="flex gap-1">
                <button @click="prevMonth" class="w-7 h-7 rounded-md hover:bg-gray-100 flex items-center justify-center text-gray-500 transition">‹</button>
                <button @click="nextMonth" class="w-7 h-7 rounded-md hover:bg-gray-100 flex items-center justify-center text-gray-500 transition">›</button>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-y-1 text-center">
            <div v-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d"
                 class="text-[10px] font-medium uppercase tracking-wide text-gray-400 py-1">{{ d }}</div>

            <button
                v-for="(c, idx) in cells" :key="idx"
                @mouseenter="hoveredDay = c" @mouseleave="hoveredDay = null"
                :class="[
                    'aspect-square text-sm rounded-md flex items-center justify-center relative transition',
                    !c ? 'opacity-0 cursor-default' : 'hover:bg-gray-50',
                    isToday(c) ? 'bg-indigo-600 text-white font-semibold hover:bg-indigo-600' : 'text-gray-700',
                ]"
                :disabled="!c"
            >
                <span>{{ c }}</span>
                <span v-if="dotsFor(c).length" class="absolute bottom-0.5 left-1/2 -translate-x-1/2 flex gap-0.5">
                    <span v-for="(e, i) in dotsFor(c).slice(0, 3)" :key="i"
                          :class="[
                            'w-1 h-1 rounded-full',
                            e.color === 'red' ? 'bg-red-500' : 'bg-indigo-500',
                            isToday(c) ? 'bg-white' : ''
                          ]" />
                </span>
            </button>
        </div>

        <div v-if="hoveredEvents.length" class="mt-3 pt-3 border-t border-gray-100 space-y-1">
            <div v-for="(e, i) in hoveredEvents" :key="i" class="flex items-center gap-2 text-xs">
                <span :class="['w-1.5 h-1.5 rounded-full', e.color === 'red' ? 'bg-red-500' : 'bg-indigo-500']" />
                <span class="text-gray-700 truncate">{{ e.title }}</span>
            </div>
        </div>
        <div v-else class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-400 text-center">
            Hover a date to see events
        </div>
    </div>
</template>
