import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * School store — current school and academic year context.
 *
 * Data sourced from Inertia shared props (injected by HandleInertiaRequests).
 *
 * Usage:
 *   const school = useSchoolStore();
 *   school.current       // current school object
 *   school.academicYear  // current academic year
 *   school.schoolId      // current school ID (number)
 */
export const useSchoolStore = defineStore('school', () => {
    const page = usePage();

    const current      = computed(() => page.props.school ?? null);
    const academicYear = computed(() => page.props.academicYear ?? null);
    const schoolId     = computed(() => current.value?.id ?? null);
    const schoolName   = computed(() => current.value?.name ?? '');
    const academicYearId = computed(() => academicYear.value?.id ?? null);
    const academicYearName = computed(() => academicYear.value?.name ?? '');

    const settings     = computed(() => current.value?.settings ?? {});

    /** Currency symbol from school settings, default INR */
    const currency     = computed(() => settings.value?.currency_symbol ?? '₹');

    /** Whether a GL/ledger module is active for this school */
    const glEnabled    = computed(() => !!settings.value?.gl_enabled);

    return {
        current,
        academicYear,
        schoolId,
        schoolName,
        academicYearId,
        academicYearName,
        settings,
        currency,
        glEnabled,
    };
});
