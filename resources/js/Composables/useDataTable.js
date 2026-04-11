/**
 * useDataTable — full-featured composable for paginated, filtered index pages.
 *
 * Combines filter management, URL sync, pagination, per-page selection,
 * and loading state — eliminating the boilerplate duplicated across every
 * listing page in the app.
 *
 * Usage:
 *   const props = defineProps({ students: Object, filters: Object })
 *
 *   const {
 *     filters, paginator, perPage,
 *     resetFilters, navigate,
 *     isFiltered,
 *   } = useDataTable({
 *     url: '/school/students',
 *     initialFilters: props.filters,
 *     paginator: computed(() => props.students),
 *   })
 *
 * Filter watching is automatic — any change triggers a debounced Inertia GET.
 */
import { reactive, computed, watch, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';

const ALLOWED_PER_PAGE = [20, 40, 60, 100];

/**
 * @param {Object}  options
 * @param {string}  options.url             - Route URL for Inertia requests
 * @param {Object}  [options.initialFilters]- Seed from Inertia props.filters
 * @param {import('vue').Ref} [options.paginator] - The paginator object from Inertia props
 * @param {number}  [options.debounceMs]    - Debounce delay (default 300ms)
 * @param {string[]}[options.allowedPerPage]- Allowed page size values
 */
export function useDataTable({
    url,
    initialFilters = {},
    paginator      = null,
    debounceMs     = 300,
    allowedPerPage = ALLOWED_PER_PAGE,
} = {}) {

    // ── Filters ───────────────────────────────────────────────────────────
    const filters = reactive({ per_page: 20, ...initialFilters });

    const defaultFilters = { per_page: 20, ...initialFilters };

    // ── Per-page ──────────────────────────────────────────────────────────
    const perPage = computed({
        get: () => filters.per_page ?? 20,
        set: (val) => {
            const safe = allowedPerPage.includes(Number(val)) ? Number(val) : 20;
            filters.per_page = safe;
        },
    });

    // ── Navigation ────────────────────────────────────────────────────────
    const loading = ref(false);

    const _navigate = debounce(() => {
        loading.value = true;
        router.get(url, filters, {
            preserveState: true,
            replace:       true,
            onFinish:      () => { loading.value = false; },
        });
    }, debounceMs);

    function navigate() {
        _navigate();
    }

    // Auto-watch all filter changes
    watch(filters, () => _navigate(), { deep: true });

    // ── Reset ─────────────────────────────────────────────────────────────
    function resetFilters() {
        Object.assign(filters, defaultFilters);
        // Clear keys not in defaults
        for (const key of Object.keys(filters)) {
            if (!(key in defaultFilters)) {
                filters[key] = '';
            }
        }
    }

    // ── isFiltered — true if any non-default filter is active ─────────────
    const isFiltered = computed(() => {
        return Object.entries(filters).some(([key, val]) => {
            if (key === 'per_page') return false; // per_page is not a "filter"
            const def = defaultFilters[key];
            return val !== def && val !== '' && val !== null && val !== undefined;
        });
    });

    // ── Pagination helpers ────────────────────────────────────────────────
    function goToPage(page) {
        router.get(url, { ...filters, page }, {
            preserveState: true,
            replace:       true,
        });
    }

    return {
        filters,
        perPage,
        paginator,
        loading,
        isFiltered,
        navigate,
        resetFilters,
        goToPage,
        allowedPerPage,
    };
}
