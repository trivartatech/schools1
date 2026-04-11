/**
 * useTableFilters — standardized debounced filter navigation for index pages.
 *
 * Replaces the inconsistent setTimeout / watch patterns across Staff, Students, Attendance.
 *
 * Usage:
 *   const { navigate } = useTableFilters('/school/staff', filters)
 *   // watch reactive filters and call navigate(); OR call navigate() from button handlers
 *
 *   import { reactive, watch } from 'vue'
 *   const filters = reactive({ search: '', status: 'current' })
 *   const { navigate } = useTableFilters('/school/staff', filters)
 *   watch(filters, navigate)
 */
import { router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'

export function useTableFilters(url, filters, options = {}) {
    const { debounceMs = 300 } = options

    const navigate = debounce(() => {
        router.get(url, filters, {
            preserveState: true,
            replace: true,
        })
    }, debounceMs)

    return { navigate }
}
