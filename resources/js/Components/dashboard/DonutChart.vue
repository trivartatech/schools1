<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js'

ChartJS.register(ArcElement, Tooltip)

const props = defineProps({
    segments: { type: Array, required: true },     // [{label, value, color}]
    height:   { type: Number, default: 180 },
    centerLabel: { type: String, default: null },
    centerValue: { type: [String, Number], default: null },
    semi:        { type: Boolean, default: false }, // true → top-facing half-donut
})

const total = computed(() => props.segments.reduce((s, x) => s + (x.value || 0), 0))

const data = computed(() => ({
    labels: props.segments.map(s => s.label),
    datasets: [{
        data: props.segments.map(s => s.value),
        backgroundColor: props.segments.map(s => s.color),
        borderWidth: 0,
        spacing: 2,
    }],
}))

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: props.semi ? '65%' : '70%',
    rotation: props.semi ? -90 : 0,
    circumference: props.semi ? 180 : 360,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed
                    const pct = total.value ? Math.round(v / total.value * 100) : 0
                    return `${ctx.label}: ${v} (${pct}%)`
                },
            },
            backgroundColor: '#0f172a', padding: 8, cornerRadius: 6,
        },
    },
}))
</script>

<template>
    <div class="relative w-full" :style="{ height: height + 'px' }">
        <Doughnut :data="data" :options="options" />
        <div
            v-if="centerValue !== null"
            :class="[
                'absolute inset-x-0 flex flex-col items-center pointer-events-none',
                semi ? 'bottom-2' : 'inset-y-0 justify-center'
            ]"
        >
            <span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ centerValue }}</span>
            <span v-if="centerLabel" class="text-xs text-gray-500 mt-0.5">{{ centerLabel }}</span>
        </div>
    </div>
</template>
