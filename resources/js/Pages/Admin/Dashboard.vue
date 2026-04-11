<template>
    <div>
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Platform Overview</h2>
            <p class="mt-1 text-sm text-gray-500">View overall statistics and manage multi-tenant architecture.</p>
        </div>

        <!-- KPI Stats -->
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3 lg:grid-cols-4">
            <Link :href="route('admin.organizations.index')" class="relative bg-white pt-5 px-4 pb-6 sm:pt-6 sm:px-6 shadow-sm rounded-xl overflow-hidden border border-gray-100 group hover:border-indigo-500 transition-all ring-offset-2 hover:ring-2 hover:ring-indigo-500/20">
                <dt>
                    <div class="absolute bg-indigo-50 rounded-lg p-3 group-hover:bg-indigo-600 transition-colors">
                        <svg class="h-6 w-6 text-indigo-600 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <p class="ml-16 text-sm font-medium text-gray-500 truncate">Total Organizations</p>
                </dt>
                <dd class="ml-16 pb-2 flex items-baseline sm:pb-3">
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.total_organizations }}</p>
                    <span class="ml-auto text-xs font-medium text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity">Manage &rarr;</span>
                </dd>
            </Link>

            <Link href="/admin/organization/dashboard" class="relative bg-white pt-5 px-4 pb-6 sm:pt-6 sm:px-6 shadow-sm rounded-xl overflow-hidden border border-gray-100 group hover:border-emerald-500 transition-all ring-offset-2 hover:ring-2 hover:ring-emerald-500/20">
                <dt>
                    <div class="absolute bg-emerald-50 rounded-lg p-3 group-hover:bg-emerald-600 transition-colors">
                        <svg class="h-6 w-6 text-emerald-600 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                        </svg>
                    </div>
                    <p class="ml-16 text-sm font-medium text-gray-500 truncate">Total Schools</p>
                </dt>
                <dd class="ml-16 pb-2 flex items-baseline sm:pb-3">
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.total_schools }}</p>
                    <span class="ml-auto text-xs font-medium text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity">Manage &rarr;</span>
                </dd>
            </Link>

            <div class="relative bg-white pt-5 px-4 pb-6 sm:pt-6 sm:px-6 shadow-sm rounded-xl overflow-hidden border border-gray-100 group">
                <dt>
                    <div class="absolute bg-blue-50 rounded-lg p-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <p class="ml-16 text-sm font-medium text-gray-500 truncate">System Users</p>
                </dt>
                <dd class="ml-16 pb-2 flex items-baseline sm:pb-3">
                    <p class="text-2xl font-semibold text-gray-900">{{ stats.total_users }}</p>
                </dd>
            </div>

            <div class="relative bg-white pt-5 px-4 pb-6 sm:pt-6 sm:px-6 shadow-sm rounded-xl overflow-hidden border border-gray-100 group">
                <dt>
                    <div class="absolute bg-amber-50 rounded-lg p-3">
                        <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <p class="ml-16 text-sm font-medium text-gray-500 truncate">Active Sessions</p>
                </dt>
                <dd class="ml-16 pb-2 flex items-baseline sm:pb-3">
                    <p class="text-2xl font-semibold text-gray-900">1</p>
                </dd>
            </div>
        </dl>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- School Management -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            Organization & Schools
                        </h3>
                        <Button as="link" variant="success" href="/admin/organization/dashboard">
                            Register School
                        </Button>
                    </div>
                    <div class="mt-2 text-sm text-gray-500">
                        <p>Manage your ecosystem. Add new campus locations, configure multi-tenant regional settings, and manage branding across all registered schools.</p>
                    </div>
                    <div class="mt-6 border rounded-lg border-gray-100 overflow-hidden bg-gray-50 space-y-px">
                        <Link :href="route('admin.organizations.index')" class="flex items-center justify-between p-4 hover:bg-white transition-colors group">
                           <span class="text-sm font-medium text-gray-700 group-hover:text-indigo-600">View All Organizations</span>
                           <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                        <Link href="/admin/organization/dashboard" class="flex items-center justify-between p-4 hover:bg-white transition-colors group">
                           <span class="text-sm font-medium text-gray-700 group-hover:text-indigo-600">View All Registered Schools</span>
                           <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Permission Matrix -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            Role & Permission Matrix
                        </h3>
                        <Button as="link" href="/admin/roles-permissions">
                            Manage Roles
                        </Button>
                    </div>
                    <div class="mt-2 text-sm text-gray-500">
                        <p>Platform-wide security governance. Standardize granular access for users, accountants, and staff members across the entire ERP ecosystem.</p>
                    </div>
                    <div class="mt-6 border rounded-lg border-gray-100 overflow-hidden bg-gray-50 space-y-px">
                        <Link href="/admin/roles-permissions" class="flex items-center justify-between p-4 hover:bg-white transition-colors group">
                           <span class="text-sm font-medium text-gray-700 group-hover:text-indigo-600">Configure Platform-Level Matrix</span>
                           <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Button from '@/Components/ui/Button.vue';

defineProps({
    stats: {
        type: Object,
        required: true
    }
})
</script>
