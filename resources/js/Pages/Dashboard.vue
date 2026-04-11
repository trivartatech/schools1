<script setup>
import { computed, defineAsyncComponent } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';

// Lazy-load sub-dashboards for performance and to isolate module compilation scopes
const AdminDashboard = defineAsyncComponent(() => import('@/Pages/Admin/Dashboard.vue'));
const TeacherDashboard = defineAsyncComponent(() => import('@/Pages/Teacher/Dashboard.vue'));
const StudentDashboard = defineAsyncComponent(() => import('@/Pages/Portal/Dashboard.vue'));
const SchoolDashboard = defineAsyncComponent(() => import('@/Pages/School/Dashboard.vue'));

const props = defineProps({
    stats:            { type: Object,  default: () => ({}) },
    school:           { type: Object,  default: null },
    students:         { type: Array,   default: () => [] },
    is_parent:        { type: Boolean, default: false },
    school_dashboard: { type: Object,  default: () => ({}) },
    teacher_schedule: { type: Object,  default: null },
});

const { isSuperAdmin, isTeacher, isStudent, isParent, isAdmin, isAccountant, isDriver } = usePermissions();

const title = computed(() => {
    if (isSuperAdmin.value) return 'System Dashboard';
    if (isTeacher.value) return 'Teacher Dashboard';
    if (isStudent.value || isParent.value) return 'Portal Dashboard';
    return 'School Dashboard';
});
</script>

<template>
    <SchoolLayout :title="title">
        <AdminDashboard v-if="isSuperAdmin" :stats="stats" />
        <TeacherDashboard v-else-if="isTeacher" :teacher_schedule="teacher_schedule" />
        <StudentDashboard v-else-if="isStudent || isParent" :students="students" :is_parent="is_parent" />
        <!-- Fallback for School Admin, Accountant, Driver, etc. -->
        <SchoolDashboard v-else :school="school" :school_dashboard="school_dashboard" />
    </SchoolLayout>
</template>
