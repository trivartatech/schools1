<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Sparkline from './Sparkline.vue'

const props = defineProps({
    label:    { type: String, required: true },
    value:    { type: [String, Number], required: true },
    sub:      { type: String, default: null },
    delta:    { type: [Number, null], default: null },          // percent change vs prev period
    deltaUnit:{ type: String, default: '%' },                   // '%' or 'pp'
    deltaInverted: { type: Boolean, default: false },           // true → up is bad
    href:     { type: String, default: null },
    sparkline:{ type: Array, default: null },
    accent:   { type: String, default: 'indigo' },              // indigo|emerald|amber|red|blue|violet|pink
    icon:     { type: String, default: null },                  // emoji or single char
    size:     { type: String, default: 'default' },             // 'default' | 'compact' | 'hero'
})

const accentMap = {
    indigo:  { tile: 'bg-indigo-50 text-indigo-600',   spark: '#6366f1', ringColor: '#6366f1' },
    emerald: { tile: 'bg-emerald-50 text-emerald-600', spark: '#10b981', ringColor: '#10b981' },
    amber:   { tile: 'bg-amber-50 text-amber-600',     spark: '#f59e0b', ringColor: '#f59e0b' },
    red:     { tile: 'bg-red-50 text-red-600',         spark: '#ef4444', ringColor: '#ef4444' },
    blue:    { tile: 'bg-blue-50 text-blue-600',       spark: '#3b82f6', ringColor: '#3b82f6' },
    violet:  { tile: 'bg-violet-50 text-violet-600',   spark: '#8b5cf6', ringColor: '#8b5cf6' },
    pink:    { tile: 'bg-pink-50 text-pink-600',       spark: '#ec4899', ringColor: '#ec4899' },
}
const accentClasses = computed(() => accentMap[props.accent] ?? accentMap.indigo)

const deltaState = computed(() => {
    if (props.delta === null || props.delta === undefined) return null
    const positive = props.delta > 0
    const goodWhenUp = !props.deltaInverted
    const isGood = positive ? goodWhenUp : !goodWhenUp
    if (props.delta === 0) return { cls: 'bg-gray-100 text-gray-500', sign: '', arrow: '→' }
    return isGood
        ? { cls: 'bg-emerald-50 text-emerald-700', sign: positive ? '+' : '', arrow: positive ? '↑' : '↓' }
        : { cls: 'bg-red-50 text-red-700',         sign: positive ? '+' : '', arrow: positive ? '↑' : '↓' }
})

const Wrapper = computed(() => props.href ? Link : 'div')

const containerCls = computed(() => {
    const base = 'relative block bg-white border border-gray-100 shadow-sm transition overflow-hidden'
    const radius = props.size === 'hero' ? 'rounded-2xl' : 'rounded-xl'
    const padding = props.size === 'compact' ? 'p-3.5' : (props.size === 'hero' ? 'p-4' : 'p-4')
    const hover = props.href ? 'hover:border-indigo-300 hover:shadow-md cursor-pointer' : ''
    return [base, radius, padding, hover].filter(Boolean).join(' ')
})

const iconBoxCls = computed(() => {
    const sz = props.size === 'compact' ? 'w-7 h-7 text-sm' : (props.size === 'hero' ? 'w-9 h-9 text-base' : 'w-8 h-8 text-sm')
    return `inline-flex items-center justify-center rounded-lg font-semibold ${sz} ${accentClasses.value.tile}`
})

const valueCls = computed(() => {
    if (props.size === 'compact') return 'text-xl font-semibold tracking-tight text-gray-900 leading-tight tabular-nums'
    if (props.size === 'hero')    return 'text-2xl md:text-3xl font-bold tracking-tight text-gray-900 leading-tight tabular-nums'
    return 'text-xl md:text-2xl font-semibold tracking-tight text-gray-900 leading-tight tabular-nums'
})
</script>

<template>
    <component
        :is="Wrapper"
        :href="href || undefined"
        :class="containerCls"
    >
        <!-- subtle accent corner glow on hero -->
        <div
            v-if="size === 'hero'"
            class="pointer-events-none absolute -top-16 -right-16 w-48 h-48 rounded-full"
            :style="{ background: `radial-gradient(closest-side, ${accentClasses.ringColor}1f, transparent)` }"
            aria-hidden
        />

        <div :class="['flex items-start justify-between', size === 'compact' ? 'mb-1.5' : 'mb-2']">
            <div :class="iconBoxCls">{{ icon || label.charAt(0) }}</div>
            <span
                v-if="deltaState"
                :class="['inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[11px] font-semibold', deltaState.cls]"
            >
                {{ deltaState.arrow }} {{ deltaState.sign }}{{ Math.abs(delta) }}{{ deltaUnit }}
            </span>
        </div>

        <div :class="valueCls">{{ value }}</div>

        <div class="mt-0.5 flex items-end justify-between gap-2">
            <p :class="['font-medium text-gray-500 truncate', size === 'compact' ? 'text-[11px]' : 'text-xs']">{{ label }}</p>
            <Sparkline v-if="sparkline?.length && size !== 'compact'" :points="sparkline" :color="accentClasses.spark" :height="22" :width="72" />
        </div>

        <p v-if="sub" :class="['mt-1 text-gray-400 truncate', size === 'compact' ? 'text-[11px]' : 'text-xs']">{{ sub }}</p>
    </component>
</template>
