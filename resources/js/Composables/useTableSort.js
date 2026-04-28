/**
 * useTableSort — sort state + sort helper for any client-side table.
 *
 * Usage:
 *   import { useTableSort } from '@/Composables/useTableSort';
 *
 *   const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('name', 'asc');
 *   const sortedRows = computed(() => sortRows(rows.value));
 *
 *   <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
 *     <thead>
 *       <tr>
 *         <SortableTh sort-key="name">Name</SortableTh>
 *         <SortableTh sort-key="email">Email</SortableTh>
 *       </tr>
 *     </thead>
 *     <tbody>
 *       <tr v-for="r in sortedRows" :key="r.id">…</tr>
 *     </tbody>
 *   </Table>
 *
 * For nested keys, pass dot-paths: 'student.name', 'meta.created_at'.
 *
 * For server-side sorting, use just the state + toggleSort, then watch
 * sortKey/sortDir and re-fetch via Inertia router.get():
 *
 *   const { sortKey, sortDir, toggleSort } = useTableSort();
 *   watch([sortKey, sortDir], () => {
 *     router.get(window.location.pathname, {
 *       sort: sortKey.value, dir: sortDir.value,
 *     }, { preserveState: true });
 *   });
 *
 * @param {string|null} initialKey — column to start sorted on (null = unsorted)
 * @param {'asc'|'desc'} initialDir — initial direction (default 'asc')
 *
 * @returns {{
 *   sortKey: Ref<string|null>,
 *   sortDir: Ref<'asc'|'desc'>,
 *   toggleSort: (key: string) => void,
 *   isSorted: (key: string) => boolean,
 *   sortRows: (rows: any[], opts?: { getValue?: (row, key) => any }) => any[]
 * }}
 */
import { ref } from 'vue';

function defaultGetValue(row, key) {
    if (row == null || !key) return null;
    if (!key.includes('.')) return row[key];
    return key.split('.').reduce((o, p) => (o == null ? o : o[p]), row);
}

export function useTableSort(initialKey = null, initialDir = 'asc') {
    const sortKey = ref(initialKey);
    const sortDir = ref(initialDir === 'desc' ? 'desc' : 'asc');

    /** Click a header — toggle dir if same key, otherwise switch key + asc. */
    function toggleSort(key) {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = key;
            sortDir.value = 'asc';
        }
    }

    /** Is this column currently the active sort? */
    function isSorted(key) {
        return sortKey.value === key;
    }

    /**
     * Returns a NEW sorted array (does not mutate input).
     * - null/undefined values sink to the bottom regardless of direction.
     * - Strings sort with localeCompare (case-insensitive when same length).
     * - Numbers / Dates sort numerically.
     * - Booleans: true > false.
     * Pass `opts.getValue(row, key)` for custom value extraction.
     */
    function sortRows(rows, opts = {}) {
        if (!Array.isArray(rows)) return rows;
        const k = sortKey.value;
        if (!k) return rows;
        const dir = sortDir.value === 'asc' ? 1 : -1;
        const getValue = opts.getValue || defaultGetValue;

        const copy = [...rows];
        copy.sort((a, b) => {
            const av = getValue(a, k);
            const bv = getValue(b, k);

            // Nulls always sink to bottom.
            if (av == null && bv == null) return 0;
            if (av == null) return 1;
            if (bv == null) return -1;

            // Date objects
            if (av instanceof Date && bv instanceof Date) {
                return (av.getTime() - bv.getTime()) * dir;
            }

            // ISO date strings ("2024-01-15", "2024-01-15T10:30")
            if (typeof av === 'string' && /^\d{4}-\d{2}-\d{2}/.test(av) &&
                typeof bv === 'string' && /^\d{4}-\d{2}-\d{2}/.test(bv)) {
                return av.localeCompare(bv) * dir;
            }

            // Numeric strings
            const an = typeof av === 'string' ? Number(av) : av;
            const bn = typeof bv === 'string' ? Number(bv) : bv;
            if (typeof an === 'number' && typeof bn === 'number' && !isNaN(an) && !isNaN(bn)) {
                return (an - bn) * dir;
            }

            // Generic string sort (case-insensitive)
            const as = String(av).toLowerCase();
            const bs = String(bv).toLowerCase();
            return as.localeCompare(bs) * dir;
        });
        return copy;
    }

    return { sortKey, sortDir, toggleSort, isSorted, sortRows };
}
