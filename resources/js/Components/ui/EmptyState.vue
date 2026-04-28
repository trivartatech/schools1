<script setup>
/**
 * EmptyState — unified empty-state component.
 *
 * Replaces inline empty-state divs and bare "No data" text scattered
 * across index pages. Visual baseline: Students/Index card view —
 * tinted icon tile + heading + sub-line + optional CTA button.
 *
 * @prop {string} title       — main message (e.g. "No students found")
 * @prop {string} description — supporting text below the title
 * @prop {string} actionLabel — primary CTA button label
 * @prop {string} actionHref  — Inertia link href for the CTA
 * @prop {string} variant     — 'default' | 'compact' (compact = inline within tables)
 * @prop {string} tone        — 'accent' | 'muted' (default 'accent')
 *
 * Emits:
 *   action — fired when the default CTA button is clicked
 *
 * Slots:
 *   icon    — custom icon (defaults to a sad-folder svg)
 *   action  — custom action area (overrides actionLabel/actionHref)
 *   default — extra content below the description (e.g. links, secondary tips)
 *
 * Examples:
 *   <EmptyState title="No students found" description="Try adjusting your filters." />
 *   <EmptyState title="No ledgers yet" action-label="+ New Ledger" action-href="/school/finance/ledgers/create" />
 *   <EmptyState variant="compact" title="No records" />
 */
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
    actionHref: { type: String, default: '' },
    variant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'compact'].includes(v),
    },
    tone: {
        type: String,
        default: 'accent',
        validator: (v) => ['accent', 'muted'].includes(v),
    },
});

defineEmits(['action']);
</script>

<template>
    <div :class="['ui-empty', `ui-empty--${variant}`, `ui-empty--${tone}`]">
        <div class="ui-empty__icon">
            <slot name="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    <path d="M9 14h6"/>
                </svg>
            </slot>
        </div>
        <p class="ui-empty__title">{{ title }}</p>
        <p v-if="description" class="ui-empty__desc">{{ description }}</p>
        <slot />
        <div v-if="$slots.action || (actionLabel && actionHref) || actionLabel" class="ui-empty__cta">
            <slot name="action">
                <Link
                    v-if="actionLabel && actionHref"
                    :href="actionHref"
                    class="ui-empty__btn"
                >
                    {{ actionLabel }}
                </Link>
                <button
                    v-else-if="actionLabel"
                    type="button"
                    class="ui-empty__btn"
                    @click="$emit('action')"
                >
                    {{ actionLabel }}
                </button>
            </slot>
        </div>
    </div>
</template>

<style>
.ui-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 48px 24px;
    color: var(--text-muted, #94a3b8);
}
.ui-empty--compact { padding: 24px 16px; }

.ui-empty__icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}
.ui-empty--accent .ui-empty__icon {
    background: #f5f3ff;
    color: #a5b4fc;
}
.ui-empty--muted .ui-empty__icon {
    background: var(--border-light, #f1f5f9);
    color: var(--text-muted, #94a3b8);
}
.ui-empty--compact .ui-empty__icon {
    width: 44px;
    height: 44px;
    border-radius: 11px;
    margin-bottom: 8px;
}
.ui-empty__icon :deep(svg),
.ui-empty__icon svg {
    width: 28px;
    height: 28px;
}
.ui-empty--compact .ui-empty__icon :deep(svg),
.ui-empty--compact .ui-empty__icon svg {
    width: 22px;
    height: 22px;
}

.ui-empty__title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--text-primary, #0f172a);
    margin: 0;
    line-height: 1.3;
}
.ui-empty--compact .ui-empty__title { font-size: 0.875rem; }

.ui-empty__desc {
    font-size: 0.8125rem;
    color: var(--text-muted, #94a3b8);
    margin: 4px 0 0;
    max-width: 380px;
    line-height: 1.5;
}
.ui-empty--compact .ui-empty__desc { font-size: 0.75rem; }

.ui-empty__cta { margin-top: 14px; }
.ui-empty--compact .ui-empty__cta { margin-top: 10px; }

.ui-empty__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: var(--accent, #6366f1);
    color: #fff;
    border: none;
    border-radius: var(--radius, 10px);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
    box-shadow: 0 1px 3px var(--accent-glow, rgba(99,102,241,0.35));
    font-family: inherit;
}
.ui-empty__btn:hover {
    background: var(--accent-dark, #4f46e5);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px var(--accent-glow, rgba(99,102,241,0.35));
}
</style>
