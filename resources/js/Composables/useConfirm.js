/**
 * useConfirm — promise-based confirmation dialog composable.
 *
 * Drop-in replacement for the native browser `confirm()` calls scattered
 * across pages. Returns a Promise<boolean> so call sites read like the
 * native API but get a styled, accessible dialog matching the rest of
 * the ERP UI.
 *
 * Mount the <ConfirmDialog /> component once at app root (SchoolLayout
 * already does this). The composable wires both sides together via
 * module-level reactive state — no provide/inject, no Pinia store.
 *
 * Usage:
 *   import { useConfirm } from '@/Composables/useConfirm';
 *   const confirm = useConfirm();
 *
 *   // simple
 *   if (!await confirm('Delete this record?')) return;
 *
 *   // detailed
 *   const ok = await confirm({
 *     title: 'Delete student',
 *     message: 'This cannot be undone.',
 *     confirmLabel: 'Delete',
 *     cancelLabel: 'Keep',
 *     danger: true,
 *   });
 *   if (!ok) return;
 *
 * The composable returns a callable. Calling it shows the dialog and
 * resolves with `true` (Confirm) or `false` (Cancel / ESC / backdrop).
 */
import { reactive } from 'vue';

const DEFAULTS = {
    title: 'Are you sure?',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    danger: false,
};

/**
 * Reactive state read by <ConfirmDialog />. Module-level so all callers
 * share the same singleton dialog (matches the useToast pattern).
 */
export const confirmState = reactive({
    open: false,
    title: DEFAULTS.title,
    message: DEFAULTS.message,
    confirmLabel: DEFAULTS.confirmLabel,
    cancelLabel: DEFAULTS.cancelLabel,
    danger: DEFAULTS.danger,
    /** @type {((value: boolean) => void) | null} */
    resolver: null,
});

function resolve(value) {
    const r = confirmState.resolver;
    confirmState.open = false;
    confirmState.resolver = null;
    // Reset to defaults so the next call doesn't see stale labels.
    confirmState.title = DEFAULTS.title;
    confirmState.message = DEFAULTS.message;
    confirmState.confirmLabel = DEFAULTS.confirmLabel;
    confirmState.cancelLabel = DEFAULTS.cancelLabel;
    confirmState.danger = DEFAULTS.danger;
    if (r) r(value);
}

export function confirmAccept() {
    resolve(true);
}

export function confirmCancel() {
    resolve(false);
}

/**
 * @returns {(opts?: string | object) => Promise<boolean>}
 */
export function useConfirm() {
    return function show(opts = {}) {
        // If a previous dialog is still open (very unusual — caller queued
        // two prompts), resolve it as cancelled so we don't leak the promise.
        if (confirmState.resolver) {
            confirmState.resolver(false);
            confirmState.resolver = null;
        }

        const config = typeof opts === 'string' ? { message: opts } : (opts || {});

        confirmState.title = config.title ?? DEFAULTS.title;
        confirmState.message = config.message ?? '';
        confirmState.confirmLabel = config.confirmLabel ?? DEFAULTS.confirmLabel;
        confirmState.cancelLabel = config.cancelLabel ?? DEFAULTS.cancelLabel;
        confirmState.danger = !!config.danger;

        return new Promise((res) => {
            confirmState.resolver = res;
            confirmState.open = true;
        });
    };
}
