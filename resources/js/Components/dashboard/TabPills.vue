<script setup>
defineProps({
    modelValue: { type: [String, Number], required: true },
    options:    { type: Array, required: true },     // [{ id, label }]
})
defineEmits(['update:modelValue'])
</script>

<template>
    <div class="inline-flex bg-gray-100 rounded-lg p-1 text-xs font-medium">
        <button
            v-for="t in options" :key="t.id"
            type="button"
            @click="$emit('update:modelValue', t.id)"
            :class="[
                'px-3 py-1 rounded-md transition whitespace-nowrap',
                modelValue === t.id
                    ? 'bg-white text-indigo-600 shadow-sm'
                    : 'text-gray-600 hover:text-gray-900'
            ]"
        >{{ t.label }}</button>
    </div>
</template>
