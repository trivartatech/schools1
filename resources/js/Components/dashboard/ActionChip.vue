<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    count: { type: Number, default: 0 },
    label: { type: String, required: true },
    href:  { type: String, default: null },
    severity: { type: String, default: 'amber' },   // amber|red|blue
})

const palette = {
    amber: 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100',
    red:   'bg-red-50 text-red-800 border-red-200 hover:bg-red-100',
    blue:  'bg-blue-50 text-blue-800 border-blue-200 hover:bg-blue-100',
}
const cls = computed(() => palette[props.severity] ?? palette.amber)
</script>

<template>
    <component
        :is="href ? Link : 'span'"
        v-if="count > 0"
        :href="href || undefined"
        :class="['inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium border transition', cls]"
    >
        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-white/70 font-bold tabular-nums">
            {{ count }}
        </span>
        <span>{{ label }}</span>
        <span v-if="href" class="opacity-60">→</span>
    </component>
</template>
