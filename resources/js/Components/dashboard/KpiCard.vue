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
    deltaInverted: { type: Boolean, default: false },           // true → up is bad (e.g. absent count)
    href:     { type: String, default: null },
    sparkline:{ type: Array, default: null },
    accent:   { type: String, default: 'indigo' },              // indigo|emerald|amber|red|blue
    icon:     { type: String, default: null },                  // emoji or single char
})

const accentMap = {
    indigo:  { tile: 'bg-indigo-50 text-indigo-600',   spark: '#6366f1' },
    emerald: { tile: 'bg-emerald-50 text-emerald-600', spark: '#10b981' },
    amber:   { tile: 'bg-amber-50 text-amber-600',     spark: '#f59e0b' },
    red:     { tile: 'bg-red-50 text-red-600',         spark: '#ef4444' },
    blue:    { tile: 'bg-blue-50 text-blue-600',       spark: '#3b82f6' },
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
</script>

<template>
    <component
        :is="Wrapper"
        :href="href || undefined"
        :class="[
            'block bg-white rounded-xl border border-gray-100 shadow-sm p-5 transition',
            href ? 'hover:border-indigo-300 hover:shadow-md cursor-pointer' : ''
        ]"
    >
        <div class="flex items-start justify-between mb-3">
            <div :class="['inline-flex items-center justify-center w-9 h-9 rounded-lg text-base font-semibold', accentClasses.tile]">
                {{ icon || label.charAt(0) }}
            </div>
            <span
                v-if="deltaState"
                :class="['inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs font-semibold', deltaState.cls]"
            >
                {{ deltaState.arrow }} {{ deltaState.sign }}{{ Math.abs(delta) }}{{ deltaUnit }}
            </span>
        </div>

        <div class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-900 leading-tight tabular-nums">
            {{ value }}
        </div>

        <div class="mt-1 flex items-end justify-between gap-2 min-h-[20px]">
            <p class="text-xs font-medium text-gray-500 truncate">{{ label }}</p>
            <Sparkline v-if="sparkline?.length" :points="sparkline" :color="accentClasses.spark" />
        </div>

        <p v-if="sub" class="mt-2 text-xs text-gray-400 truncate">{{ sub }}</p>
    </component>
</template>
