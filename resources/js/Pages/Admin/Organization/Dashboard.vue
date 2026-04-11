<template>
    <Head title="Organization Dashboard" />
    
    <SchoolLayout>
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ organization.name }}</h2>
            <p class="mt-1 text-sm text-gray-500">Manage all schools and consolidated reports for your organization.</p>
        </div>

        <!-- KPI Stats -->
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="relative bg-white pt-5 px-4 pb-6 sm:pt-6 sm:px-6 shadow-sm rounded-xl border border-gray-100">
                <dt>
                    <div class="absolute bg-indigo-50 rounded-lg p-3">
                        <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <p class="ml-16 text-sm font-medium text-gray-500 truncate">Managed Schools</p>
                </dt>
                <dd class="ml-16 pb-2 flex items-baseline sm:pb-3">
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.total_schools }}</p>
                </dd>
            </div>

            <div class="relative bg-white pt-5 px-4 pb-6 sm:pt-6 sm:px-6 shadow-sm rounded-xl border border-gray-100">
                <dt>
                    <div class="absolute bg-emerald-50 rounded-lg p-3">
                        <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <p class="ml-16 text-sm font-medium text-gray-500 truncate">Total Students</p>
                </dt>
                <dd class="ml-16 pb-2 flex items-baseline sm:pb-3">
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.total_students }}</p>
                </dd>
            </div>

            <div class="relative bg-white pt-5 px-4 pb-6 sm:pt-6 sm:px-6 shadow-sm rounded-xl border border-gray-100">
                <dt>
                    <div class="absolute bg-green-50 rounded-lg p-3">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="ml-16 text-sm font-medium text-gray-500 truncate">Total Revenue</p>
                </dt>
                <dd class="ml-16 pb-2 flex items-baseline sm:pb-3">
                    <p class="text-2xl font-semibold text-gray-900">₹{{ stats.total_revenue }}</p>
                </dd>
            </div>
        </dl>

        <!-- Schools List -->
        <div class="flex items-center justify-between mt-8 mb-4">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Schools Overview</h3>
            <Button as="link" :href="route('org.admin.schools.create')">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Add New School
            </Button>
        </div>
        <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
            <ul role="list" class="divide-y divide-gray-200">
                <li v-for="school in schools" :key="school.id" class="px-6 py-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center">
                                <span class="text-indigo-600 font-bold text-lg">{{ school.name.charAt(0) }}</span>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-indigo-600">{{ school.name }}</h4>
                                <p class="text-sm text-gray-500">Code: {{ school.code }} • {{ school.board }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="text-sm text-gray-500 text-right">
                                <span class="block font-medium text-gray-900">{{ school.students_count }}</span>
                                <span class="text-xs">Students</span>
                            </div>
                            <div class="text-sm text-gray-500 text-right">
                                <span v-if="school.status === 'active'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Disabled
                                </span>
                            </div>
                            <Link :href="route('org.admin.schools.manage', { school: school.id })" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                Manage &rarr;
                            </Link>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </SchoolLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import Button from '@/Components/ui/Button.vue';

defineProps({
    organization: Object,
    schools: Array,
    stats: Object
})
</script>
