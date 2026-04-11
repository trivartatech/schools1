<script setup>
import Button from '@/Components/ui/Button.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Form' },
    width: { type: String, default: 'w-96' },
});
const emit = defineEmits(['close']);
</script>

<template>
    <!-- Backdrop (no dark overlay - just a subtle separator) -->
    <Transition name="panel">
        <div v-if="open"
            class="fixed inset-y-0 right-0 z-40 flex flex-col shadow-2xl bg-white border-l border-gray-200"
            :class="width"
            style="top: 0;">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 bg-gray-50 flex-shrink-0">
                <h3 class="font-semibold text-gray-800 text-base">{{ title }}</h3>
                <Button variant="secondary" @click="$emit('close')"
                   >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </Button>
            </div>
            <!-- Content / Form -->
            <div class="flex-1 overflow-y-auto px-5 py-5">
                <slot />
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.panel-enter-active,
.panel-leave-active {
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s;
}
.panel-enter-from,
.panel-leave-to {
    transform: translateX(100%);
    opacity: 0;
}
</style>
