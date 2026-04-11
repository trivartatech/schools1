<script setup>
/**
 * Toast — renders the global toast notification stack.
 *
 * Mount once in your layout:
 *   <Toast />
 *
 * Then from any component:
 *   import { useToast } from '@/Composables/useToast';
 *   const toast = useToast();
 *   toast.success('Saved!');
 *
 * Supports: success, error, warning, info
 * Features: stacking (max 5), auto-dismiss with progress bar, manual dismiss
 */
import { useToast } from '@/Composables/useToast';

const { toasts, dismiss } = useToast();
</script>

<template>
    <Teleport to="body">
        <TransitionGroup
            tag="div"
            class="toast-stack"
            enter-active-class="toast-enter-active"
            leave-active-class="toast-leave-active"
            enter-from-class="toast-enter-from"
            leave-to-class="toast-leave-to"
            move-class="toast-move"
        >
            <div
                v-for="t in toasts"
                :key="t.id"
                :class="['toast', `toast--${t.type}`]"
                role="alert"
                aria-live="assertive"
            >
                <!-- Icon -->
                <span class="toast__icon">
                    <!-- Success ✓ -->
                    <svg v-if="t.type === 'success'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <!-- Error ✕ -->
                    <svg v-else-if="t.type === 'error'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-width="2.5" d="M15 9l-6 6M9 9l6 6"/>
                    </svg>
                    <!-- Warning ⚠ -->
                    <svg v-else-if="t.type === 'warning'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86l-8.6 14.86A1 1 0 002.54 20h16.92a1 1 0 00.85-1.47L12.72 3.68a1 1 0 00-1.72 0z"/>
                    </svg>
                    <!-- Info ℹ -->
                    <svg v-else fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-width="2.5" d="M12 16v-4M12 8h.01"/>
                    </svg>
                </span>

                <!-- Message -->
                <span class="toast__msg">{{ t.message }}</span>

                <!-- Dismiss -->
                <button class="toast__close" @click="dismiss(t.id)" aria-label="Dismiss">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <!-- Progress bar — shrinks over the toast lifetime -->
                <span
                    class="toast__progress"
                    :style="{ animationDuration: t.duration + 'ms' }"
                />
            </div>
        </TransitionGroup>
    </Teleport>
</template>

<style>
/* ── Stack container ────────────────────────────────────────────── */
.toast-stack {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    flex-direction: column-reverse;   /* newest on bottom, oldest on top */
    gap: 10px;
    pointer-events: none;             /* click-through the container */
    max-width: 420px;
    width: calc(100vw - 40px);
}

/* ── Individual toast ───────────────────────────────────────────── */
.toast {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 0.82rem;
    font-weight: 500;
    line-height: 1.4;
    color: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,0.16), 0 2px 8px rgba(0,0,0,0.08);
    pointer-events: auto;             /* make toasts clickable */
    overflow: hidden;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* Variants */
.toast--success { background: #059669; }
.toast--error   { background: #dc2626; }
.toast--warning { background: #d97706; }
.toast--info    { background: #2563eb; }

/* ── Icon ───────────────────────────────────────────────────────── */
.toast__icon {
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    opacity: 0.9;
}
.toast__icon svg {
    width: 100%;
    height: 100%;
}

/* ── Message ────────────────────────────────────────────────────── */
.toast__msg {
    flex: 1;
    min-width: 0;
    word-break: break-word;
}

/* ── Close button ───────────────────────────────────────────────── */
.toast__close {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: none;
    background: transparent;
    color: inherit;
    opacity: 0.6;
    cursor: pointer;
    border-radius: 6px;
    padding: 0;
    transition: opacity 0.15s, background 0.15s;
}
.toast__close:hover { opacity: 1; background: rgba(255,255,255,0.15); }
.toast__close svg { width: 13px; height: 13px; }

/* ── Progress bar ───────────────────────────────────────────────── */
.toast__progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    width: 100%;
    background: rgba(255,255,255,0.35);
    transform-origin: left;
    animation: toast-shrink linear forwards;
    border-radius: 0 0 12px 12px;
}
@keyframes toast-shrink {
    from { transform: scaleX(1); }
    to   { transform: scaleX(0); }
}

/* ── Transitions ────────────────────────────────────────────────── */
.toast-enter-active {
    transition: all 0.3s cubic-bezier(0.21, 1.02, 0.73, 1);
}
.toast-leave-active {
    transition: all 0.2s ease-in;
    position: absolute;
    width: 100%;
}
.toast-enter-from {
    transform: translateY(20px) scale(0.95);
    opacity: 0;
}
.toast-leave-to {
    transform: translateX(100%) scale(0.95);
    opacity: 0;
}
.toast-move {
    transition: transform 0.25s ease;
}

/* ── Mobile ─────────────────────────────────────────────────────── */
@media (max-width: 480px) {
    .toast-stack {
        bottom: 12px;
        right: 12px;
        left: 12px;
        width: auto;
        max-width: none;
    }
}

/* ── Print ──────────────────────────────────────────────────────── */
@media print {
    .toast-stack { display: none !important; }
}
</style>
