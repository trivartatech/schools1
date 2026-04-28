<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    title:    { type: String, default: null },
    subtitle: { type: String, default: null },
    actionLabel: { type: String, default: null },
    actionHref:  { type: String, default: null },
})
</script>

<template>
    <div class="flex items-start justify-between mb-3 flex-wrap gap-2">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-gray-900 tracking-tight flex items-center gap-1.5">
                <slot name="title">{{ title }}</slot>
            </h2>
            <p v-if="subtitle || $slots.subtitle" class="mt-0.5 text-xs text-gray-500">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions || (actionLabel && actionHref)" class="flex items-center gap-2 flex-wrap">
            <slot name="actions" />
            <Link
                v-if="actionLabel && actionHref"
                :href="actionHref"
                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition whitespace-nowrap"
            >
                {{ actionLabel }} →
            </Link>
        </div>
    </div>
</template>
