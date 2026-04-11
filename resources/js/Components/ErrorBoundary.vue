<script setup>
import { ref, onErrorCaptured } from 'vue';

/**
 * ErrorBoundary — catches any Vue runtime error thrown in child components.
 *
 * Usage:
 *   <ErrorBoundary>
 *     <SomeRiskyComponent />
 *     <template #fallback="{ error, reset }">
 *       <p>Something went wrong: {{ error.message }}</p>
 *       <button @click="reset">Try again</button>
 *     </template>
 *   </ErrorBoundary>
 *
 * If no #fallback slot is provided, a default error card is shown.
 */

const error = ref(null);

function reset() {
    error.value = null;
}

onErrorCaptured((err) => {
    error.value = err;
    // Return false to stop propagation — we're handling it here
    return false;
});
</script>

<template>
    <template v-if="error">
        <slot name="fallback" :error="error" :reset="reset">
            <div class="rounded-lg border border-red-200 bg-red-50 p-6 text-center">
                <div class="mb-2 text-red-600">
                    <svg class="mx-auto h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                </div>
                <h3 class="mb-1 text-sm font-semibold text-red-700">Something went wrong</h3>
                <p class="mb-4 text-xs text-red-600">{{ error.message }}</p>
                <button
                    type="button"
                    class="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700"
                    @click="reset"
                >
                    Try again
                </button>
            </div>
        </slot>
    </template>
    <slot v-else />
</template>
