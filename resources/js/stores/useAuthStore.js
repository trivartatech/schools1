import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Auth store — mirrors Inertia's page.props.auth for reactive access.
 *
 * Inertia already passes auth data on every page. This store provides
 * a single reactive interface so components don't need to call usePage()
 * directly — making future migration (e.g. to a non-Inertia flow) easy.
 *
 * Usage:
 *   const auth = useAuthStore();
 *   auth.user        // current user object
 *   auth.isAdmin     // boolean
 *   auth.can('view_students')
 */
export const useAuthStore = defineStore('auth', () => {
    const page = usePage();

    const auth        = computed(() => page.props.auth ?? {});
    const user        = computed(() => auth.value.user ?? null);
    const roles       = computed(() => auth.value.roles ?? []);
    const permissions = computed(() => auth.value.permissions ?? []);
    const userType    = computed(() => user.value?.user_type ?? null);

    // ── Permission helpers ───────────────────────────────────────────────

    function can(permission) {
        if (permissions.value.includes('*')) return true; // super admin bypass
        return permissions.value.includes(permission);
    }

    function canAny(permissionList) {
        return permissionList.some(p => can(p));
    }

    function canAll(permissionList) {
        return permissionList.every(p => can(p));
    }

    function hasRole(role) {
        return roles.value.includes(role);
    }

    function hasAnyRole(roleList) {
        return roleList.some(r => roles.value.includes(r));
    }

    // ── Role flags ────────────────────────────────────────────────────────
    const isSuperAdmin = computed(() => userType.value === 'super_admin');
    const isAdmin      = computed(() =>
        ['admin', 'school_admin', 'principal'].includes(userType.value)
    );
    const isTeacher    = computed(() => userType.value === 'teacher');
    const isStudent    = computed(() => userType.value === 'student');
    const isParent     = computed(() => userType.value === 'parent');
    const isAccountant = computed(() => userType.value === 'accountant');
    const isDriver     = computed(() => userType.value === 'driver');

    const isSchoolManagement = computed(() => isAdmin.value || isSuperAdmin.value);

    return {
        // state
        user, roles, permissions, userType,
        // helpers
        can, canAny, canAll, hasRole, hasAnyRole,
        // role flags
        isSuperAdmin, isAdmin, isTeacher, isStudent,
        isParent, isAccountant, isDriver, isSchoolManagement,
    };
});
