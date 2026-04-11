<script setup>
/**
 * Table — the single unified table primitive for the School ERP UI.
 *
 * Drop-in replacement for `<table class="erp-table">`. Owns its own styles
 * (no longer dependent on `.erp-table` in SchoolLayout). Adds built-in
 * responsive wrapper, loading state, empty state, and density variants
 * that used to be re-implemented per page.
 *
 * @prop {('sm'|'md'|'lg')} size      — row/cell density (default 'md' matches old .erp-table)
 * @prop {boolean}         striped    — zebra rows
 * @prop {boolean}         hover      — row hover highlight (default true)
 * @prop {boolean}         bordered   — outer border + cell borders (for financial/report tables)
 * @prop {boolean}         responsive — wraps in overflow-x container (default true)
 * @prop {boolean}         loading    — show spinner overlay
 * @prop {boolean}         empty      — show empty-state slot (caller drives this)
 * @prop {string}          emptyText  — convenience message when `empty` is true
 *
 * Slots:
 *   default  — thead + tbody markup (authored in parent so parent-scoped
 *              CSS on th/td continues to work)
 *   empty    — custom empty state content
 *   loading  — custom loading state content
 *
 * Usage:
 *   <Table>
 *     <thead><tr><th>Name</th></tr></thead>
 *     <tbody><tr v-for="r in rows" :key="r.id"><td>{{ r.name }}</td></tr></tbody>
 *   </Table>
 *
 *   <Table :empty="rows.length === 0" empty-text="No students yet">
 *     ...
 *   </Table>
 *
 *   <Table bordered size="sm">...</Table>
 */
import { computed } from 'vue';

const props = defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    striped: Boolean,
    hover: { type: Boolean, default: true },
    bordered: Boolean,
    responsive: { type: Boolean, default: true },
    loading: Boolean,
    empty: Boolean,
    emptyText: { type: String, default: 'No records found.' },
});

defineOptions({ inheritAttrs: false });

const tableClasses = computed(() => [
    'erp-table',
    `erp-table--${props.size}`,
    {
        'erp-table--striped': props.striped,
        'erp-table--hover': props.hover,
        'erp-table--bordered': props.bordered,
    },
]);
</script>

<template>
    <div :class="['erp-table-wrap', { 'erp-table-wrap--responsive': responsive }]">
        <table :class="tableClasses" v-bind="$attrs">
            <slot />
        </table>

        <div v-if="loading" class="erp-table-overlay" role="status" aria-live="polite">
            <slot name="loading">
                <div class="erp-table-spinner" aria-hidden="true"></div>
                <span class="erp-table-overlay-text">Loading…</span>
            </slot>
        </div>

        <div v-else-if="empty" class="erp-table-empty">
            <slot name="empty">
                <p class="erp-table-empty-text">{{ emptyText }}</p>
            </slot>
        </div>
    </div>
</template>

<style scoped>
/* ─── Wrapper ─────────────────────────────────────────────── */
.erp-table-wrap {
    position: relative;
    width: 100%;
}
.erp-table-wrap--responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* ─── Base table ──────────────────────────────────────────── */
.erp-table {
    width: 100%;
    border-collapse: collapse;
}

/* th/td live in the parent's slot, so we need :deep() to reach them */
.erp-table :deep(th) {
    padding: 11px 14px;
    text-align: left;
    font-size: 0.675rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted);
    background: #fafbfc;
    border-bottom: 1.5px solid var(--border);
    white-space: nowrap;
}
.erp-table :deep(th:first-child) { padding-left: 18px; }

.erp-table :deep(td) {
    padding: 12px 14px;
    font-size: 0.8125rem;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}
.erp-table :deep(td:first-child) { padding-left: 18px; }

.erp-table :deep(tbody tr:last-child td) { border-bottom: none; }

/* ─── Hover ───────────────────────────────────────────────── */
.erp-table--hover :deep(tbody tr) { transition: background 0.12s ease; }
.erp-table--hover :deep(tbody tr:hover) { background: #fafbff; }

/* ─── Striped ─────────────────────────────────────────────── */
.erp-table--striped :deep(tbody tr:nth-child(even)) { background: #fafbfc; }
.erp-table--striped.erp-table--hover :deep(tbody tr:hover) { background: #f1f5ff; }

/* ─── Bordered (financial / report tables) ───────────────── */
.erp-table--bordered {
    border: 1px solid var(--border);
}
.erp-table--bordered :deep(th),
.erp-table--bordered :deep(td) {
    border: 1px solid var(--border-light);
}

/* ─── Size variants ───────────────────────────────────────── */
.erp-table--sm :deep(th) { padding: 7px 10px; font-size: 0.625rem; }
.erp-table--sm :deep(td) { padding: 7px 10px; font-size: 0.75rem; }
.erp-table--sm :deep(th:first-child),
.erp-table--sm :deep(td:first-child) { padding-left: 12px; }

.erp-table--lg :deep(th) { padding: 14px 18px; font-size: 0.75rem; }
.erp-table--lg :deep(td) { padding: 16px 18px; font-size: 0.875rem; }
.erp-table--lg :deep(th:first-child),
.erp-table--lg :deep(td:first-child) { padding-left: 22px; }

/* ─── Loading overlay ─────────────────────────────────────── */
.erp-table-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(1px);
    pointer-events: none;
}
.erp-table-overlay-text {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
}
.erp-table-spinner {
    width: 28px;
    height: 28px;
    border: 2.5px solid var(--border);
    border-top-color: var(--accent, #4f46e5);
    border-radius: 50%;
    animation: erp-table-spin 0.7s linear infinite;
}
@keyframes erp-table-spin {
    to { transform: rotate(360deg); }
}

/* ─── Empty state ─────────────────────────────────────────── */
.erp-table-empty {
    padding: 48px 24px;
    text-align: center;
}
.erp-table-empty-text {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin: 0;
}

/* ─── Print ───────────────────────────────────────────────── */
@media print {
    .erp-table-wrap { overflow: visible !important; }
    .erp-table-overlay { display: none !important; }
    .erp-table--hover :deep(tbody tr:hover) { background: transparent !important; }
}
</style>
