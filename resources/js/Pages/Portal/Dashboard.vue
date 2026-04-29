<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

// ── This component is a CONTENT-ONLY sub-dashboard.
// ── It is rendered inside Dashboard.vue which already provides SchoolLayout.
// ── DO NOT wrap this in SchoolLayout.

const props = defineProps({
    students: { type: Array, default: () => [] },
    is_parent: { type: Boolean, default: false },
});

const activeStudent = ref(props.students?.[0] ?? null);

const attendanceInfo = (pct) => {
    if (pct >= 85) return { bar: '#10b981', text: 'text-emerald-600', bg: 'bg-emerald-50', label: 'Good' };
    if (pct >= 70) return { bar: '#f59e0b', text: 'text-amber-600', bg: 'bg-amber-50', label: 'Average' };
    return { bar: '#ef4444', text: 'text-red-600', bg: 'bg-red-50', label: 'Low' };
};

const feeStatus = (balance) => {
    if (balance <= 0) return { label: 'Paid', cls: 'bg-emerald-100 text-emerald-700' };
    if (balance < 5000) return { label: 'Partial Due', cls: 'bg-amber-100 text-amber-700' };
    return { label: 'Due', cls: 'bg-red-100 text-red-700' };
};
</script>

<template>
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ is_parent ? 'Parent Portal' : 'My Portal' }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ is_parent ? "Overview of your children's academic progress" : 'Your academic summary' }}
                </p>
            </div>
            <Button as="link" href="/portal/student"
               >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                View Full Profile
            </Button>
        </div>

        <!-- Student switcher (parent with multiple children) -->
        <div v-if="students.length > 1" class="flex gap-2 flex-wrap">
            <button
                v-for="s in students" :key="s.id"
                @click="activeStudent = s"
                class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all"
                :class="activeStudent?.id === s.id
                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300'"
            >
                <div class="w-5 h-5 rounded-full bg-indigo-200 text-indigo-800 text-xs flex items-center justify-center font-bold flex-shrink-0">
                    {{ s.name?.charAt(0) }}
                </div>
                {{ s.name }}
            </button>
        </div>

        <!-- No students linked -->
        <div v-if="!students.length" class="bg-white rounded-xl border shadow-sm p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
            </svg>
            <p class="text-gray-500 text-sm">No student linked to this account yet.</p>
            <p class="text-gray-400 text-xs mt-1">Contact the school admin to link your child.</p>
        </div>

        <template v-if="activeStudent">

            <!-- Student Identity Card -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="h-20 bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600"></div>
                <div class="px-6 pb-6 -mt-10 flex items-end gap-4">
                    <div v-if="activeStudent.photo_url"
                        class="w-16 h-16 rounded-full border-4 border-white shadow-md overflow-hidden flex-shrink-0 bg-white">
                        <img :src="activeStudent.photo_url" class="w-full h-full object-cover">
                    </div>
                    <div v-else
                        class="w-16 h-16 rounded-full border-4 border-white shadow-md flex-shrink-0 bg-indigo-600 flex items-center justify-center text-white font-bold text-xl">
                        {{ activeStudent.name?.charAt(0) }}
                    </div>
                    <div class="flex-1 pb-1">
                        <h2 class="text-lg font-bold text-gray-900 leading-tight">{{ activeStudent.name }}</h2>
                        <p class="text-sm text-gray-500">
                            Class {{ activeStudent.class_name }} — {{ activeStudent.section_name }}
                            <span class="mx-1.5 text-gray-300">·</span>
                            <span class="font-mono text-xs text-gray-400">{{ activeStudent.admission_no }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                <!-- Attendance -->
                <div class="bg-white rounded-xl border shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-700">Attendance</span>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full font-medium"
                            :class="attendanceInfo(activeStudent.attendance_percentage).bg + ' ' + attendanceInfo(activeStudent.attendance_percentage).text">
                            {{ attendanceInfo(activeStudent.attendance_percentage).label }}
                        </span>
                    </div>
                    <div class="flex items-baseline gap-1 mb-3">
                        <span class="text-3xl font-bold" :class="attendanceInfo(activeStudent.attendance_percentage).text">
                            {{ activeStudent.attendance_percentage }}%
                        </span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700"
                            :style="{ width: activeStudent.attendance_percentage + '%', background: attendanceInfo(activeStudent.attendance_percentage).bar }">
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Minimum 85% required</p>
                </div>

                <!-- Fee Balance -->
                <Link href="/portal/fees" class="block bg-white rounded-xl border shadow-sm p-5 hover:border-indigo-300 transition-colors group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-700">Fee Balance</span>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full font-medium" :class="feeStatus(activeStudent.fee_balance).cls">
                            {{ feeStatus(activeStudent.fee_balance).label }}
                        </span>
                    </div>
                    <div class="flex items-baseline gap-1 mb-3">
                        <span class="text-3xl font-bold" :class="activeStudent.fee_balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                            {{ school.fmtMoney(Math.abs(activeStudent.fee_balance)) }}
                        </span>
                    </div>
                    <p class="text-xs" :class="activeStudent.fee_balance > 0 ? 'text-indigo-500 group-hover:text-indigo-700 font-medium' : 'text-gray-400'">
                        {{ activeStudent.fee_balance > 0 ? 'Click to view details & pay online →' : 'All fees cleared for this term' }}
                    </p>
                </Link>

            </div>

            <!-- Quick Links -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Quick Access</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <Button as="link" variant="secondary" href="/portal/fees"
                        class="text-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-xs font-semibold text-indigo-800">Pay Fees</span>
                    </Button>
                    <Link href="/portal/student?tab=homework"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl bg-violet-50 hover:bg-violet-100 transition-colors text-center">
                        <span class="text-2xl">📝</span>
                        <span class="text-xs font-semibold text-violet-800">Homework</span>
                    </Link>
                    <Link href="/portal/student?tab=diary"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl bg-blue-50 hover:bg-blue-100 transition-colors text-center">
                        <span class="text-2xl">📔</span>
                        <span class="text-xs font-semibold text-blue-800">Student Diary</span>
                    </Link>
                    <Link href="/portal/student?tab=syllabus"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl bg-amber-50 hover:bg-amber-100 transition-colors text-center">
                        <span class="text-2xl">📚</span>
                        <span class="text-xs font-semibold text-amber-800">Syllabus</span>
                    </Link>
                    <Link href="/portal/student?tab=materials"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl bg-emerald-50 hover:bg-emerald-100 transition-colors text-center">
                        <span class="text-2xl">📁</span>
                        <span class="text-xs font-semibold text-emerald-800">Materials</span>
                    </Link>
                </div>
            </div>

        </template>
    </div>
</template>
