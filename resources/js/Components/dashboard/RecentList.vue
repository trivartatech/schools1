<script setup>
defineProps({
    rows:     { type: Array, default: () => [] },
    emptyText:{ type: String, default: 'No items yet' },
    avatarKey:{ type: String, default: null },         // when set, render avatar from row[avatarKey]
})
</script>

<template>
    <div v-if="rows.length" class="divide-y divide-gray-100">
        <div v-for="(row, i) in rows" :key="i" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
            <template v-if="avatarKey">
                <img v-if="row[avatarKey]" :src="row[avatarKey]" class="w-8 h-8 rounded-full object-cover bg-gray-100 flex-shrink-0" />
                <div v-else class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-semibold flex-shrink-0">
                    {{ (row.name || row.student || row.title || '?').charAt(0).toUpperCase() }}
                </div>
            </template>
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-sm font-medium text-gray-900 truncate">
                        <slot name="primary" :row="row">{{ row.name || row.student || row.title }}</slot>
                    </p>
                    <p v-if="$slots.right" class="text-sm font-semibold text-gray-900 tabular-nums whitespace-nowrap">
                        <slot name="right" :row="row" />
                    </p>
                </div>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                    <slot name="secondary" :row="row">{{ row.subtitle || '' }}</slot>
                </p>
            </div>
        </div>
    </div>
    <p v-else class="text-sm text-gray-400 italic py-6 text-center">{{ emptyText }}</p>
</template>
