<script setup>
import { computed } from 'vue'

const props = defineProps({
    points: { type: Array, default: () => [] },   // numbers; null entries are skipped
    color:  { type: String, default: '#6366f1' },
    height: { type: Number, default: 28 },
    width:  { type: Number, default: 88 },
})

const path = computed(() => {
    const pts = props.points
    const valid = pts.map((v, i) => ({ v: Number.isFinite(v) ? v : null, i }))
    const nums = valid.map(p => p.v).filter(v => v !== null)
    if (nums.length < 2) return null

    const min = Math.min(...nums)
    const max = Math.max(...nums)
    const range = max - min || 1
    const w = props.width, h = props.height
    const stepX = w / (pts.length - 1)

    let started = false
    let d = ''
    valid.forEach(({ v, i }) => {
        if (v === null) return
        const x = i * stepX
        const y = h - ((v - min) / range) * h
        d += (started ? ' L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1)
        started = true
    })
    return d
})

const last = computed(() => {
    const pts = props.points
    for (let i = pts.length - 1; i >= 0; i--) if (Number.isFinite(pts[i])) {
        const min = Math.min(...pts.filter(v => Number.isFinite(v)))
        const max = Math.max(...pts.filter(v => Number.isFinite(v)))
        const range = (max - min) || 1
        const x = i * (props.width / (pts.length - 1))
        const y = props.height - ((pts[i] - min) / range) * props.height
        return { x, y }
    }
    return null
})
</script>

<template>
    <svg :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" class="overflow-visible">
        <path v-if="path" :d="path" :stroke="color" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round" />
        <circle v-if="last" :cx="last.x" :cy="last.y" r="2.2" :fill="color" />
    </svg>
</template>
