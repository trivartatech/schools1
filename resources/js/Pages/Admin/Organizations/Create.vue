<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const form = useForm({
    name: '',
    email: '',
});

const submit = () => {
    form.post(route('admin.organizations.store'));
};
</script>

<template>
    <Head title="Register Organization" />

    <AdminLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Register New Organization</h2>
                    <p class="mt-1 text-sm text-gray-500">Create a new organizational group to manage schools under a trust.</p>
                </div>
                <Link :href="route('admin.organizations.index')" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                    Back to List
                </Link>
            </div>

            <form @submit.prevent="submit" class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="p-6 sm:p-8 space-y-6">

                    <div class="space-y-1">
                        <label class="block text-sm font-semibold text-gray-700">Organization Name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" required class="w-full h-10 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow outline-none text-sm" placeholder="e.g. KV Sangathan, DPS Society">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-sm font-semibold text-gray-700">Primary Email <span class="text-red-500">*</span></label>
                        <input v-model="form.email" type="email" required class="w-full h-10 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow outline-none text-sm" placeholder="admin@org.com">
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 sm:px-8 border-t border-gray-100 flex items-center justify-end">
                    <Button type="submit" :loading="form.processing">
                        {{ form.processing ? 'Registering...' : 'Register Organization' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
