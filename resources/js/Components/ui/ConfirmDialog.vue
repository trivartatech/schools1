<script setup>
/**
 * ConfirmDialog — global singleton dialog wired to useConfirm().
 *
 * Mount ONCE at app root (SchoolLayout). All useConfirm() calls
 * share this single instance via module-level reactive state.
 *
 * Visual matches Modal.vue (same backdrop/sizing/animation) so the
 * confirmation UX is indistinguishable from any other modal in the app.
 */
import { confirmState, confirmAccept, confirmCancel } from '@/Composables/useConfirm';
import Modal from '@/Components/ui/Modal.vue';
import Button from '@/Components/ui/Button.vue';
</script>

<template>
    <Modal
        :open="confirmState.open"
        :title="confirmState.title"
        size="sm"
        @close="confirmCancel"
    >
        <div class="ui-confirm">
            <div :class="['ui-confirm__icon', confirmState.danger ? 'ui-confirm__icon--danger' : 'ui-confirm__icon--accent']" aria-hidden="true">
                <svg v-if="confirmState.danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <p v-if="confirmState.message" class="ui-confirm__msg">{{ confirmState.message }}</p>
        </div>

        <template #footer>
            <Button variant="secondary" @click="confirmCancel">{{ confirmState.cancelLabel }}</Button>
            <Button :variant="confirmState.danger ? 'danger' : 'primary'" @click="confirmAccept">{{ confirmState.confirmLabel }}</Button>
        </template>
    </Modal>
</template>

<style>
.ui-confirm {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 12px;
    padding: 4px 0 0;
}

.ui-confirm__icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ui-confirm__icon--accent { background: #eef2ff; color: #6366f1; }
.ui-confirm__icon--danger { background: #fee2e2; color: #dc2626; }
.ui-confirm__icon svg { width: 24px; height: 24px; }

.ui-confirm__msg {
    font-size: 0.875rem;
    color: var(--text-secondary, #475569);
    line-height: 1.5;
    margin: 0;
    max-width: 320px;
}
</style>
