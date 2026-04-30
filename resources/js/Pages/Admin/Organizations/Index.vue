<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useTableSort } from '@/Composables/useTableSort';
import { computed } from 'vue';

const props = defineProps({
    organizations: Array,
});

const impersonate = (org) => {
    router.visit(route('org.admin.dashboard'));
};

const { sortKey, sortDir, toggleSort, sortRows } = useTableSort('name', 'asc');
const sortedOrgs = computed(() => sortRows(props.organizations || []));
</script>

<template>
    <Head title="Organization Management" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto py-8">
            <PageHeader
                title="Organizations"
                subtitle="Manage all organizations registered on the platform."
            >
                <template #actions>
                    <Button as="link" :href="route('admin.organizations.create')">
                        <template #icon>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        </template>
                        Add Organization
                    </Button>
                </template>
            </PageHeader>

            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <Table :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                    <thead>
                        <tr>
                            <SortableTh sort-key="name">Name</SortableTh>
                            <SortableTh sort-key="schools_count" align="center">Schools</SortableTh>
                            <SortableTh sort-key="email">Contact Email</SortableTh>
                            <th style="text-align:right;">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="org in sortedOrgs" :key="org.id">
                            <td class="whitespace-nowrap">
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
                            <td class="whitespace-nowrap text-sm" style="text-align:center;">
                                <span class="font-bold text-indigo-600">{{ org.schools_count }}</span>
                            </td>
                            <td class="whitespace-nowrap text-sm text-gray-500">
                                {{ org.email }}
                            </td>
                            <td class="whitespace-nowrap text-sm font-medium" style="text-align:right;">
                                <Button variant="secondary" @click="impersonate(org)" class="mr-3">Manage</Button>
                                <button class="text-gray-400 hover:text-gray-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="sortedOrgs.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                <p>No organizations found.</p>
                                <Link :href="route('admin.organizations.create')" class="mt-2 text-indigo-600 font-semibold hover:underline">Register your first organization</Link>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>
    </AdminLayout>
</template>
