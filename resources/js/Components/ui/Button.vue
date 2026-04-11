<script setup>
/**
 * Button — the single unified button/link primitive for the School ERP UI.
 *
 * Owns its own styles (no longer dependent on `.btn` classes in SchoolLayout).
 * Supports every variant that used to live as a bespoke class in scoped
 * <style> blocks across 100+ pages.
 *
 * @prop {('primary'|'secondary'|'danger'|'success'|'warning'|'save'|'cancel'|'ghost'|'icon'|'tab')} variant
 * @prop {('xs'|'sm'|'md'|'lg')} size
 * @prop {('button'|'link'|'a')} as — render target
 * @prop {string} type — native button type (default 'button' — safer than HTML default 'submit')
 * @prop {boolean} disabled
 * @prop {boolean} loading — shows spinner + auto-disables
 * @prop {string} href — required when as='link' or as='a'
 * @prop {boolean} active — for variant='tab', marks the button as selected
 * @prop {boolean} block — full-width button
 *
 * Slots:
 *   default — button label
 *   icon    — leading icon (sized automatically)
 *   iconRight — trailing icon
 *
 * Examples:
 *   <Button>Save</Button>
 *   <Button variant="secondary" size="sm">Cancel</Button>
 *   <Button variant="danger" :loading="deleting">Delete</Button>
 *   <Button as="link" href="/students">View all</Button>
 *   <Button variant="tab" :active="status === 'pending'" @click="status='pending'">Pending</Button>
 *   <Button variant="icon" size="sm" aria-label="Close"><template #icon><XIcon/></template></Button>
 */
import { computed, useSlots } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) =>
            ['primary', 'secondary', 'danger', 'success', 'warning', 'save', 'cancel', 'ghost', 'icon', 'tab'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
    },
    as: {
        type: String,
        default: 'button',
        validator: (v) => ['button', 'link', 'a'].includes(v),
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: Boolean,
    loading: Boolean,
    href: { type: String, default: undefined },
    active: Boolean,
    block: Boolean,
});

const slots = useSlots();

const tag = computed(() => {
    if (props.as === 'link') return Link;
    if (props.as === 'a') return 'a';
    return 'button';
});

const classes = computed(() => [
    'ui-btn',
    `ui-btn--${props.variant}`,
    `ui-btn--${props.size}`,
    {
        'ui-btn--loading': props.loading,
        'ui-btn--active': props.active,
        'ui-btn--block': props.block,
        'ui-btn--icon-only': props.variant === 'icon' && !slots.default,
    },
]);

const isInteractiveDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <component
        :is="tag"
        :class="classes"
        :type="as === 'button' ? type : undefined"
        :disabled="as === 'button' ? isInteractiveDisabled : undefined"
        :href="as !== 'button' ? href : undefined"
        :aria-busy="loading ? 'true' : undefined"
    >
        <!-- Loading spinner replaces leading icon when active -->
        <svg
            v-if="loading"
            class="ui-btn__spinner"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle class="ui-btn__spinner-track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path
                class="ui-btn__spinner-head"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>

        <span v-else-if="$slots.icon" class="ui-btn__icon">
            <slot name="icon" />
        </span>

        <slot />

        <span v-if="$slots.iconRight" class="ui-btn__icon">
            <slot name="iconRight" />
        </span>
    </component>
</template>

<style>
/* ──────────────────────────────────────────────────────────────────────────
   Button — base
   Unscoped so the class names stay predictable for the codemod and for the
   one-off pages that may still pass extra Tailwind utilities via class=.
   ────────────────────────────────────────────────────────────────────────── */
.ui-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 8.5px 16px;
    border-radius: var(--radius, 10px);
    font-size: 0.8125rem;
    font-weight: 600;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    line-height: 1.4;
    white-space: nowrap;
    font-family: inherit;
    user-select: none;
}
.ui-btn:disabled,
.ui-btn[aria-busy='true'] {
    opacity: 0.55;
    cursor: not-allowed;
    pointer-events: none;
}
.ui-btn--block {
    display: flex;
    width: 100%;
}

/* Leading / trailing icon wrappers — auto-size SVGs inside */
.ui-btn__icon {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}
.ui-btn__icon :deep(svg),
.ui-btn__icon svg {
    width: 1em;
    height: 1em;
    font-size: 1.1em;
}

/* Spinner */
.ui-btn__spinner {
    width: 1em;
    height: 1em;
    font-size: 1.1em;
    animation: ui-btn-spin 0.8s linear infinite;
    flex-shrink: 0;
}
.ui-btn__spinner-track { opacity: 0.25; }
.ui-btn__spinner-head  { opacity: 0.75; }
@keyframes ui-btn-spin { to { transform: rotate(360deg); } }

/* ──────────────────────────────────────────────────────────────────────────
   Sizes
   ────────────────────────────────────────────────────────────────────────── */
.ui-btn--xs { padding: 3.5px 9px; font-size: 0.6875rem; border-radius: 6px; gap: 4px; }
.ui-btn--sm { padding: 5.5px 11px; font-size: 0.75rem; border-radius: 7px; gap: 5px; }
.ui-btn--md { /* default */ }
.ui-btn--lg { padding: 11px 22px; font-size: 0.9375rem; }

/* ──────────────────────────────────────────────────────────────────────────
   Variants
   ────────────────────────────────────────────────────────────────────────── */

/* primary */
.ui-btn--primary {
    background: var(--accent, #6366f1);
    color: #fff;
    border-color: var(--accent, #6366f1);
    box-shadow: 0 1px 3px var(--accent-glow, rgba(99,102,241,0.35));
}
.ui-btn--primary:hover:not(:disabled) {
    background: var(--accent-dark, #4f46e5);
    border-color: var(--accent-dark, #4f46e5);
    box-shadow: 0 4px 14px var(--accent-glow, rgba(99,102,241,0.35));
    transform: translateY(-1px);
}

/* secondary */
.ui-btn--secondary {
    background: var(--surface, #fff);
    color: var(--text-secondary, #4b5563);
    border-color: var(--border, #e5e7eb);
}
.ui-btn--secondary:hover:not(:disabled) {
    background: #f9fafb;
    border-color: #9ca3af;
    color: var(--text-primary, #111827);
}

/* danger */
.ui-btn--danger {
    background: #fff5f5;
    color: var(--danger, #ef4444);
    border-color: #fecaca;
}
.ui-btn--danger:hover:not(:disabled) { background: #fee2e2; border-color: #fca5a5; }

/* success */
.ui-btn--success {
    background: var(--success, #10b981);
    color: #fff;
    border-color: var(--success, #10b981);
}
.ui-btn--success:hover:not(:disabled) {
    background: #059669;
    box-shadow: 0 4px 14px rgba(16,185,129,0.35);
    transform: translateY(-1px);
}

/* warning */
.ui-btn--warning {
    background: #fffbeb;
    color: #92400e;
    border-color: #fde68a;
}
.ui-btn--warning:hover:not(:disabled) { background: #fef3c7; }

/* save — modal submit; gradient flavour */
.ui-btn--save {
    padding: 11px 22px;
    background: linear-gradient(135deg, var(--accent, #6366f1), var(--accent-dark, #4f46e5));
    color: #fff;
    border-color: transparent;
    border-radius: 11px;
    font-weight: 700;
    box-shadow: 0 2px 8px var(--accent-glow, rgba(99,102,241,0.35));
}
.ui-btn--save:hover:not(:disabled) {
    box-shadow: 0 6px 20px var(--accent-glow, rgba(99,102,241,0.35));
    transform: translateY(-1px);
}

/* cancel — modal dismiss */
.ui-btn--cancel {
    padding: 11px 22px;
    background: #f3f4f6;
    color: #6b7280;
    border: 1.5px solid #e5e7eb;
    border-radius: 11px;
    font-weight: 600;
}
.ui-btn--cancel:hover:not(:disabled) { background: #f8fafc; border-color: #9ca3af; }

/* ghost — borderless, subtle */
.ui-btn--ghost {
    background: transparent;
    color: var(--text-secondary, #4b5563);
    border-color: transparent;
}
.ui-btn--ghost:hover:not(:disabled) {
    background: var(--accent-subtle, rgba(99,102,241,0.12));
    color: var(--accent, #6366f1);
}

/* icon — square button, icon only */
.ui-btn--icon {
    padding: 6px;
    background: transparent;
    color: var(--text-secondary, #4b5563);
    border-color: transparent;
    border-radius: 7px;
}
.ui-btn--icon:hover:not(:disabled) {
    background: var(--accent-subtle, rgba(99,102,241,0.12));
    color: var(--accent, #6366f1);
}
.ui-btn--icon.ui-btn--xs { padding: 3px; }
.ui-btn--icon.ui-btn--sm { padding: 5px; }
.ui-btn--icon.ui-btn--lg { padding: 9px; }

/* tab — pagination / tab-bar pill */
.ui-btn--tab {
    background: transparent;
    color: var(--text-secondary, #4b5563);
    border-color: transparent;
    font-weight: 500;
}
.ui-btn--tab:hover:not(:disabled) {
    background: #f3f4f6;
    color: var(--text-primary, #111827);
}
.ui-btn--tab.ui-btn--active {
    background: var(--accent-subtle, rgba(99,102,241,0.12));
    color: var(--accent, #6366f1);
    font-weight: 600;
}

</style>
