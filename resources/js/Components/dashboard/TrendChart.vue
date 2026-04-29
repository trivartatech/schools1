<script setup>
import { computed } from 'vue'
import { Line, Bar } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement, LineElement,
    BarElement, Tooltip, Legend, Filler,
} from 'chart.js'
import { useSchoolStore } from '@/stores/useSchoolStore'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler)

const school = useSchoolStore()

const props = defineProps({
    type:     { type: String, default: 'line' },        // 'line' | 'bar' | 'stacked-bar' | 'stacked-area'
    labels:   { type: Array, required: true },
    datasets: { type: Array, required: true },          // [{label, data, color}]
    height:   { type: Number, default: 220 },
    currency: { type: String, default: '' },            // legacy prop — present indicates money tooltip; symbol now sourced from store
    yPercent: { type: Boolean, default: false },        // y-axis as %
    legend:   { type: Boolean, default: false },
})

const isStackedArea = computed(() => props.type === 'stacked-area')
const isStackedBar  = computed(() => props.type === 'stacked-bar')
const baseType      = computed(() => (isStackedArea.value ? 'line' : (isStackedBar.value ? 'bar' : props.type)))

const data = computed(() => ({
    labels: props.labels,
    datasets: props.datasets.map(ds => {
        if (baseType.value === 'line') {
            return {
                label: ds.label,
                data: ds.data,
                borderColor: ds.color,
                backgroundColor: isStackedArea.value
                    ? hexAlpha(ds.color, 0.55)
                    : hexAlpha(ds.color, 0.10),
                fill: isStackedArea.value ? true : (props.datasets.length === 1),
                tension: 0.35,
                pointRadius: isStackedArea.value ? 0 : 3,
                pointHoverRadius: 5,
                borderWidth: 2,
            }
        }
        // bar / stacked-bar
        return {
            label: ds.label,
            data: ds.data,
            backgroundColor: hexAlpha(ds.color, 0.85),
            borderColor: ds.color,
            borderRadius: 4,
            borderSkipped: false,
            barPercentage: 0.7,
            categoryPercentage: 0.7,
        }
    }),
}))

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: props.legend, position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } } },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed.y ?? ctx.parsed
                    if (v === null) return null
                    if (props.yPercent) return `${ctx.dataset.label}: ${v}%`
                    if (props.currency) return `${ctx.dataset.label}: ${school.fmtMoney(v)}`
                    return `${ctx.dataset.label}: ${Number(v).toLocaleString('en-IN')}`
                },
            },
            backgroundColor: '#0f172a',
            padding: 10, cornerRadius: 8, titleFont: { weight: '600' },
        },
    },
    scales: {
        x: {
            stacked: isStackedArea.value || isStackedBar.value,
            grid: { display: false },
            ticks: { font: { size: 11 }, color: '#64748b' },
        },
        y: {
            stacked: isStackedArea.value || isStackedBar.value,
            beginAtZero: true,
            min: props.yPercent ? 0 : undefined,
            max: props.yPercent ? 100 : undefined,
            grid: { color: 'rgba(226, 232, 240, .6)', drawBorder: false },
            ticks: {
                font: { size: 11 },
                color: '#94a3b8',
                callback: (val) => props.yPercent ? val + '%' : compactNumber(val),
            },
        },
    },
}))

function hexAlpha(hex, a) {
    if (!hex || !hex.startsWith('#')) return hex
    const h = hex.replace('#', '')
    const r = parseInt(h.substring(0, 2), 16)
    const g = parseInt(h.substring(2, 4), 16)
    const b = parseInt(h.substring(4, 6), 16)
    return `rgba(${r}, ${g}, ${b}, ${a})`
}
function compactNumber(v) {
    const n = Math.abs(v)
    if (n >= 1e7) return (v / 1e7).toFixed(1) + 'Cr'
    if (n >= 1e5) return (v / 1e5).toFixed(1) + 'L'
    if (n >= 1e3) return (v / 1e3).toFixed(0) + 'k'
    return v
}
</script>

<template>
    <div :style="{ height: height + 'px' }" class="w-full">
        <component
            :is="baseType === 'bar' ? Bar : Line"
            :data="data"
            :options="options"
        />
    </div>
</template>
