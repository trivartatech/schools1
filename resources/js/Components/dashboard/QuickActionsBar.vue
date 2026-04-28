<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    actions: { type: Array, required: true },   // [{label, icon, href, accent}]
})

const accentMap = {
    indigo:  'bg-indigo-50 text-indigo-700 hover:bg-indigo-100 ring-indigo-200',
    emerald: 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 ring-emerald-200',
    blue:    'bg-blue-50 text-blue-700 hover:bg-blue-100 ring-blue-200',
    violet:  'bg-violet-50 text-violet-700 hover:bg-violet-100 ring-violet-200',
    amber:   'bg-amber-50 text-amber-700 hover:bg-amber-100 ring-amber-200',
    pink:    'bg-pink-50 text-pink-700 hover:bg-pink-100 ring-pink-200',
}
function clsFor(accent) { return accentMap[accent] ?? accentMap.indigo }
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3">
        <Link
            v-for="(a, i) in actions" :key="i"
            :href="a.href"
            :class="[
                'group flex flex-col items-start gap-1.5 p-3 rounded-xl border border-transparent ring-1 transition',
                clsFor(a.accent)
            ]"
        >
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white/70 text-base group-hover:scale-105 transition-transform">
                {{ a.icon }}
            </span>
            <span class="text-xs font-semibold leading-tight">{{ a.label }}</span>
        </Link>
    </div>
</template>
