<script setup>
/**
 * Modal — the unified modal/dialog primitive for the School ERP UI.
 *
 * Replaces the 120 hand-rolled modal variants across pages. Owns its own
 * styles unscoped (same pattern as Button.vue / Toast.vue) so modal class
 * names are predictable and don't collide with per-page scoped CSS.
 *
 * Class names match the existing convention used by Hostel/Houses/
 * Communication modals: `.modal-backdrop` + `.modal`.
 *
 * @prop {boolean} open                       — v-model:open
 * @prop {string}  title                      — header title; pass empty to omit header
 * @prop {('sm'|'md'|'lg'|'xl')} size         — width token (default 'md')
 * @prop {boolean} closeOnBackdrop            — backdrop click closes (default true)
 * @prop {boolean} closeOnEsc                 — ESC closes (default true)
 * @prop {boolean} hideClose                  — hide the × close button
 * @prop {boolean} persistent                 — convenience: closeOnBackdrop=false + closeOnEsc=false
 * @prop {string}  bodyClass                  — extra class on the body wrapper
 * @prop {string}  ariaLabel                  — fallback aria-label when there's no title
 *
 * Emits:
 *   update:open  — v-model:open update
 *   close        — fired right before close animation (regardless of trigger)
 *
 * Slots:
 *   default        — modal body (already padded)
 *   header-actions — extra controls in the header (left of the × button)
 *   footer         — sticky footer; wrap your buttons here
 *
 * Sizes (max-width): sm=400, md=520, lg=720, xl=960
 *
 * Examples:
 *   <Modal v-model:open="showCreate" title="New Ledger" size="md">
 *     <form>…</form>
 *     <template #footer>
 *       <Button variant="secondary" @click="showCreate = false">Cancel</Button>
 *       <Button :loading="form.processing" @click="save">Create</Button>
 *     </template>
 *   </Modal>
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

// Module-level counter — tracks how many Modals are currently open so we
// only release the body scroll lock when the last one closes. Without this,
// nested modals (e.g. ConfirmDialog opened from inside a regular Modal)
// would re-enable body scroll while the outer modal is still visible.
let openModalCount = 0;
function lockBody() {
    if (openModalCount === 0) document.body.style.overflow = 'hidden';
    openModalCount++;
}
function unlockBody() {
    openModalCount = Math.max(0, openModalCount - 1);
    if (openModalCount === 0) document.body.style.overflow = '';
}

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
    },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEsc: { type: Boolean, default: true },
    hideClose: { type: Boolean, default: false },
    persistent: { type: Boolean, default: false },
    bodyClass: { type: String, default: '' },
    ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:open', 'close']);

const dialogRef = ref(null);
let lastFocused = null;

const allowBackdrop = computed(() => !props.persistent && props.closeOnBackdrop);
const allowEsc = computed(() => !props.persistent && props.closeOnEsc);

function close() {
    emit('close');
    emit('update:open', false);
}

function onBackdropMousedown(e) {
    if (!allowBackdrop.value) return;
    // Only close if the press started on the backdrop itself — prevents
    // dragging-to-select inside the dialog from accidentally dismissing it.
    if (e.target === e.currentTarget) close();
}

function onKeydown(e) {
    if (!props.open) return;
    if (e.key === 'Escape' && allowEsc.value) {
        e.stopPropagation();
        close();
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            lastFocused = document.activeElement;
            await nextTick();
            // Focus the dialog so ESC works without the user having to click first.
            dialogRef.value?.focus();
            document.addEventListener('keydown', onKeydown, true);
            lockBody();
        } else {
            document.removeEventListener('keydown', onKeydown, true);
            unlockBody();
            // Restore focus to whatever opened the modal.
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
            lastFocused = null;
        }
    },
    { immediate: false }
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown, true);
    if (props.open) unlockBody();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="open"
                class="modal-backdrop"
                @mousedown="onBackdropMousedown"
                role="presentation"
            >
                <div
                    ref="dialogRef"
                    :class="['modal', `modal--${size}`]"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title || ariaLabel || undefined"
                    tabindex="-1"
                >
                    <header v-if="title || $slots['header-actions'] || !hideClose" class="modal-header">
                        <h3 v-if="title" class="modal-title">{{ title }}</h3>
                        <span v-else />
                        <div class="modal-header-right">
                            <slot name="header-actions" />
                            <button
                                v-if="!hideClose"
                                type="button"
                                class="modal-close"
                                aria-label="Close"
                                @click="close"
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </header>
                    <div :class="['modal-body', bodyClass]">
                        <slot />
                    </div>
                    <footer v-if="$slots.footer" class="modal-footer">
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
/* Unscoped — class names exposed for legacy callers and codemod. */

.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}

.modal {
    background: var(--surface, #fff);
    border-radius: var(--radius-lg, 14px);
    width: 100%;
    max-height: calc(100vh - 40px);
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.22);
    overflow: hidden;
    outline: none;
}

.modal--sm { max-width: 400px; }
.modal--md { max-width: 520px; }
.modal--lg { max-width: 720px; }
.modal--xl { max-width: 960px; }

/* Header */
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-light, #f1f5f9);
    background: var(--surface, #fff);
    flex-shrink: 0;
}
.modal-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--text-primary, #0f172a);
    margin: 0;
    line-height: 1.3;
}
.modal-header-right {
    display: flex;
    align-items: center;
    gap: 6px;
}
.modal-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    border: none;
    background: transparent;
    color: var(--text-muted, #94a3b8);
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
}
.modal-close:hover {
    background: #fee2e2;
    color: var(--danger, #ef4444);
}

/* Body */
.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

/* Footer */
.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid var(--border-light, #f1f5f9);
    background: #f8fafc;
    flex-shrink: 0;
}

/* Transition */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.18s ease;
}
.modal-enter-active .modal,
.modal-leave-active .modal {
    transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.18s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-from .modal,
.modal-leave-to .modal {
    transform: translateY(16px) scale(0.96);
    opacity: 0;
}

@media (max-width: 480px) {
    .modal-backdrop { padding: 10px; }
    .modal-header { padding: 14px 16px; }
    .modal-body { padding: 16px; }
    .modal-footer { padding: 12px 16px; }
}

@media print {
    .modal-backdrop { display: none !important; }
}
</style>
