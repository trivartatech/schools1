<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    organizations: Array,
});

const impersonate = (org) => {
    router.visit(route('org.admin.dashboard'));
};
</script>

<template>
    <Head title="Organization Management" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto py-8">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Organizations</h1>
                    <p class="text-gray-500">Manage all organizations registered on the platform.</p>
                </div>
                <Button as="link" 
                    :href="route('admin.organizations.create')" 
                   
                >
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Add Organization
                </Button>
            </div>

            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="flex flex-col">
                    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="overflow-hidden border-b border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-left">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider sm:pl-6">Name</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Schools</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Contact Email</th>
                                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                                <span class="sr-only">Actions</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="org in organizations" :key="org.id" class="hover:bg-gray-50 transition-colors">
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                                        <span class="text-indigo-600 font-bold">{{ org.name.charAt(0) }}</span>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-semibold text-gray-900">{{ org.name }}</div>
                                                        <div class="text-xs text-gray-400">ID: {{ org.id }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                                <span class="font-bold text-indigo-600">{{ org.schools_count }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ org.email }}
                                            </td>
                                            <td class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                                <Button variant="secondary" @click="impersonate(org)" class="mr-3">Manage</Button>
                                                <button class="text-gray-400 hover:text-gray-600">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="organizations.length === 0">
                                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                                <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                                <p>No organizations found.</p>
                                                <Link :href="route('admin.organizations.create')" class="mt-2 text-indigo-600 font-semibold hover:underline">Register your first organization</Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
