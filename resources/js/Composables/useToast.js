/**
 * useToast — global toast notification composable.
 *
 * Usage:
 *   import { useToast } from '@/Composables/useToast';
 *
 *   const toast = useToast();
 *   toast.success('Record saved');
 *   toast.error('Something went wrong');
 *   toast.warning('Unsaved changes');
 *   toast.info('Copied to clipboard');
 *
 * The toast queue is a module-level reactive array so it's shared across every
 * component that calls useToast() — no provide/inject needed.
 */
import { reactive } from 'vue';

/** @type {{ id: number, type: string, message: string, duration: number, timer: number|null }[]} */
const toasts = reactive([]);

let nextId = 0;
const MAX_VISIBLE = 5;

function add(type, message, duration = 4500) {
    const id = nextId++;

    // Cap the stack — remove oldest if at limit
    while (toasts.length >= MAX_VISIBLE) {
        dismiss(toasts[0].id);
    }

    const entry = { id, type, message, duration, startedAt: Date.now(), timer: null };

    entry.timer = setTimeout(() => dismiss(id), duration);
    toasts.push(entry);

    return id;
}

function dismiss(id) {
    const idx = toasts.findIndex((t) => t.id === id);
    if (idx === -1) return;
    clearTimeout(toasts[idx].timer);
    toasts.splice(idx, 1);
}

function clear() {
    toasts.forEach((t) => clearTimeout(t.timer));
    toasts.splice(0, toasts.length);
}

export function useToast() {
    return {
        /** Reactive array of active toasts — bind to <Toast :toasts="toasts" /> */
        toasts,

        /** Show a success toast */
        success: (msg, duration) => add('success', msg, duration),

        /** Show an error toast */
        error: (msg, duration) => add('error', msg, duration ?? 6000),

        /** Show a warning toast */
        warning: (msg, duration) => add('warning', msg, duration),

        /** Show a neutral info toast */
        info: (msg, duration) => add('info', msg, duration),

        /** Dismiss a specific toast by id */
        dismiss,

        /** Clear all toasts */
        clear,
    };
}
