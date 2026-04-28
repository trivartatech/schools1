<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    title:    { type: String, required: true },
    subtitle: { type: String, default: null },
    actionLabel: { type: String, default: null },
    actionHref:  { type: String, default: null },
})
</script>

<template>
    <div class="flex items-end justify-between mb-4">
        <div>
            <h2 class="text-base font-semibold text-gray-900 tracking-tight">{{ title }}</h2>
            <p v-if="subtitle" class="mt-0.5 text-xs text-gray-500">{{ subtitle }}</p>
        </div>
        <Link
            v-if="actionLabel && actionHref"
            :href="actionHref"
            class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition"
        >
            {{ actionLabel }} →
        </Link>
    </div>
</template>
