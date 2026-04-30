<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    timezones: Array,
    boards: Array,
    organizations: Array,
});

const form = useForm({
    organization_id: props.organizations?.length > 0 ? props.organizations[0].id : '',
    name: '',
    code: '',
    board: 'CBSE',
    email: '',
    phone: '',
    address: '',
    timezone: 'Asia/Kolkata',
    currency: 'INR',
    // Admin Account
    admin_name: '',
    admin_email: '',
    admin_password: '',
});

const submit = () => {
    form.post(route('org.admin.schools.store'));
};
</script>

<template>
    <Head title="Register New School" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <PageHeader
                title="Register New School"
                subtitle="Create a new school campus and assign its primary administrator."
            />

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Configuration -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                            <h2 class="text-lg font-bold text-gray-900">Campus Details</h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Organization Selection (Superadmin only) -->
                            <div v-if="$page.props.auth.user.user_type === 'super_admin'" class="col-span-2 space-y-1">
                                <label class="block text-sm font-semibold text-gray-700">Organization <span class="text-red-500">*</span></label>
                                <select v-model="form.organization_id" required class="w-full h-11 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 transition-all outline-none bg-white font-medium">
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-sm font-semibold text-gray-700">School Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" required placeholder="e.g. DPS North Campus" class="w-full h-11 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 transition-all outline-none" />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-sm font-semibold text-gray-700">School Code <span class="text-red-500">*</span></label>
                                <input v-model="form.code" type="text" required placeholder="e.g. DPSN001" class="w-full h-11 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 transition-all outline-none" />
                                <p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-sm font-semibold text-gray-700">Education Board <span class="text-red-500">*</span></label>
                                <select v-model="form.board" required class="w-full h-11 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 transition-all outline-none bg-white">
                                    <option v-for="board in boards" :key="board" :value="board">{{ board }}</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-sm font-semibold text-gray-700">Official Email <span class="text-red-500">*</span></label>
                                <input v-model="form.email" type="email" required placeholder="contact@school.com" class="w-full h-11 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 transition-all outline-none" />
                            </div>
                        </div>
                    </div>

                    <!-- Localization -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                            <h2 class="text-lg font-bold text-gray-900">Localization & Region</h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-1">
                                <label class="block text-sm font-semibold text-gray-700">Timezone <span class="text-red-500">*</span></label>
                                <select v-model="form.timezone" required class="w-full h-11 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 transition-all outline-none bg-white">
                                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-sm font-semibold text-gray-700">Currency <span class="text-red-500">*</span></label>
                                <input v-model="form.currency" type="text" required class="w-full h-11 px-4 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 transition-all outline-none" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin & Sidebar -->
                <div class="space-y-6">
                    <div class="bg-indigo-600 shadow-lg rounded-xl border border-indigo-700 overflow-hidden text-white">
                        <div class="p-6 border-b border-indigo-500/50">
                            <h2 class="text-lg font-bold">Initial Administrator</h2>
                            <p class="text-xs text-indigo-100 mt-1">This user will have full control over the new school campus.</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-indigo-100">Full Name</label>
                                <input v-model="form.admin_name" type="text" required placeholder="e.g. John Doe" class="w-full h-11 px-4 rounded-lg border-0 bg-white/10 text-white placeholder-white/40 focus:ring-2 focus:ring-white transition-all outline-none" />
                                <p v-if="form.errors.admin_name" class="mt-1 text-xs text-red-200">{{ form.errors.admin_name }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-indigo-100">Login Email</label>
                                <input v-model="form.admin_email" type="email" required placeholder="admin@school.com" class="w-full h-11 px-4 rounded-lg border-0 bg-white/10 text-white placeholder-white/40 focus:ring-2 focus:ring-white transition-all outline-none" />
                                <p v-if="form.errors.admin_email" class="mt-1 text-xs text-red-200">{{ form.errors.admin_email }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-indigo-100">Temporary Password</label>
                                <input v-model="form.admin_password" type="password" required placeholder="••••••••" class="w-full h-11 px-4 rounded-lg border-0 bg-white/10 text-white placeholder-white/40 focus:ring-2 focus:ring-white transition-all outline-none" />
                                <p v-if="form.errors.admin_password" class="mt-1 text-xs text-red-200">{{ form.errors.admin_password }}</p>
                            </div>
                        </div>
                    </div>

                    <Button variant="success" type="submit" :loading="form.processing" class="space-x-2" block>
                        <span v-if="!form.processing">Register & Create Admin</span>
                        <span v-else>Registering...</span>
                        <svg v-if="!form.processing" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        <svg v-else class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </Button>
                    
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <p class="text-[11px] text-gray-500 leading-relaxed text-center">
                            By clicking register, the system will automatically initialize core modules, academic years, and permissions for the new campus.
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
