/**
 * useClassSections — shared composable for fetching sections when a class is selected.
 *
 * Replaces copy-pasted fetch logic in Students/Create, Students/Index, Examinations/Marks/Index.
 *
 * Usage:
 *   const { sections, isFetching, fetchError, fetchSections, reset } = useClassSections()
 *   // In template: @change="fetchSections(form.class_id)"
 *   // Show error: <span v-if="fetchError">{{ fetchError }}</span>
 */
import { ref } from 'vue'

export function useClassSections() {
    const sections   = ref([])
    const isFetching = ref(false)
    const fetchError = ref(null)

    const fetchSections = async (classId) => {
        if (!classId) {
            sections.value   = []
            fetchError.value = null
            return
        }

        isFetching.value = true
        fetchError.value = null

        try {
            const response = await fetch(`/school/classes/${classId}/sections`)
            if (!response.ok) throw new Error(`HTTP ${response.status}`)
            sections.value = await response.json()
        } catch (err) {
            console.error('[useClassSections] Failed to fetch sections:', err)
            fetchError.value = 'Failed to load sections. Please try again.'
            sections.value   = []
        } finally {
            isFetching.value = false
        }
    }

    const reset = () => {
        sections.value   = []
        fetchError.value = null
    }

    return { sections, isFetching, fetchError, fetchSections, reset }
}
