<script setup>
/**
 * SortableTh — sortable header cell for use inside <Table>.
 *
 * Pairs with <Table>'s sort-key/sort-dir props and `sort` event.
 * Reads the active sort key/direction from the parent <Table> via
 * provide/inject, and delegates click handling back to the Table.
 *
 * Usage:
 *   <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
 *     <thead>
 *       <tr>
 *         <SortableTh sort-key="name">Name</SortableTh>
 *         <SortableTh sort-key="email">Email</SortableTh>
 *         <th>Actions</th>  <!-- not sortable -->
 *       </tr>
 *     </thead>
 *     ...
 *   </Table>
 *
 * Standalone (without <Table>): pass `:current-key` and `:current-dir`
 * directly and listen to `@sort` on this component.
 *
 * @prop {string}  sortKey      — the key this column sorts by
 * @prop {string}  align        — 'left' | 'center' | 'right' (default 'left')
 * @prop {string}  currentKey   — overrides Table-injected sort key
 * @prop {string}  currentDir   — overrides Table-injected sort dir
 *
 * Emits:
 *   sort (key) — fired on click (only if no <Table> ancestor)
 */
import { computed, inject } from 'vue';

const props = defineProps({
    sortKey: { type: String, required: true },
    align: {
        type: String,
        default: 'left',
        validator: (v) => ['left', 'center', 'right'].includes(v),
    },
    currentKey: { type: String, default: undefined },
    currentDir: { type: String, default: undefined },
});

const emit = defineEmits(['sort']);

const tableCtx = inject('erpTableSort', null);

const activeKey = computed(() => props.currentKey ?? tableCtx?.sortKey?.value ?? null);
const activeDir = computed(() => props.currentDir ?? tableCtx?.sortDir?.value ?? 'asc');

const isActive = computed(() => activeKey.value === props.sortKey);

function onClick() {
    if (tableCtx) {
        tableCtx.triggerSort(props.sortKey);
    } else {
        emit('sort', props.sortKey);
    }
}
</script>

<template>
    <th
        class="ui-sortable-th"
        :class="[`ui-sortable-th--${align}`, { 'ui-sortable-th--active': isActive }]"
        :aria-sort="isActive ? (activeDir === 'asc' ? 'ascending' : 'descending') : 'none'"
        @click="onClick"
    >
        <span class="ui-sortable-th__inner">
            <span class="ui-sortable-th__label"><slot /></span>
            <span class="ui-sortable-th__arrows" aria-hidden="true">
                <svg viewBox="0 0 12 14" class="ui-sortable-th__arrow ui-sortable-th__arrow--up"
                     :class="{ 'is-active': isActive && activeDir === 'asc' }">
                    <path d="M6 1 L11 6 L1 6 Z" fill="currentColor"/>
                </svg>
                <svg viewBox="0 0 12 14" class="ui-sortable-th__arrow ui-sortable-th__arrow--down"
                     :class="{ 'is-active': isActive && activeDir === 'desc' }">
                    <path d="M6 13 L11 8 L1 8 Z" fill="currentColor"/>
                </svg>
            </span>
        </span>
    </th>
</template>

<style scoped>
.ui-sortable-th {
    cursor: pointer;
    user-select: none;
    transition: background 0.12s, color 0.12s;
}
.ui-sortable-th:hover {
    background: #f1f5f9 !important;
    color: var(--text-secondary, #475569) !important;
}
.ui-sortable-th--active {
    color: var(--accent, #6366f1) !important;
    background: var(--accent-subtle, rgba(99, 102, 241, 0.08)) !important;
}

.ui-sortable-th__inner {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ui-sortable-th--center .ui-sortable-th__inner { justify-content: center; }
.ui-sortable-th--right .ui-sortable-th__inner  { justify-content: flex-end; }

.ui-sortable-th__label {
    flex: 1;
    min-width: 0;
}

.ui-sortable-th__arrows {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    flex-shrink: 0;
    line-height: 0;
}
.ui-sortable-th__arrow {
    width: 8px;
    height: 6px;
    color: #cbd5e1;
    transition: color 0.12s;
}
.ui-sortable-th__arrow.is-active {
    color: var(--accent, #6366f1);
}

/* When a column isn't active, dim both arrows slightly more on hover */
.ui-sortable-th:hover .ui-sortable-th__arrow:not(.is-active) {
    color: #94a3b8;
}

/* Right-align numeric / action columns visually preserve text-align */
.ui-sortable-th--right  { text-align: right; }
.ui-sortable-th--center { text-align: center; }
</style>
