<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    school: { type: Object, default: () => ({}) },
});

const page = usePage();

// Settings sidebar nav
const settingsNav = [
    { id: 'general-config',  label: 'General Config',  route: '/school/settings/general-config',  icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'asset-config',    label: 'Asset Config',    route: '/school/settings/asset-config',    icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'system-config',   label: 'System Config',   route: '/school/settings/system-config',   icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18' },
    { id: 'geofence-config', label: 'Geofence Config', route: '/school/settings/geofence-config', icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'attendance-timings', label: 'Attendance Timings', route: '/school/settings/attendance-timings', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'admin-contacts',  label: 'Admin Numbers',   route: '/school/settings/admin-contacts',  icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
    { id: 'receipt-print',   label: 'Receipt Print',   route: '/school/settings/receipt-print',   icon: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

const form = useForm({
    geo_fence_lat:    props.school.geo_fence_lat ?? '',
    geo_fence_lng:    props.school.geo_fence_lng ?? '',
    geo_fence_radius: props.school.geo_fence_radius ?? 200,
});

const submit = () => {
    form.post('/school/settings/geofence', { preserveScroll: true });
};

// Use browser location to set school coordinates
const detecting = ref(false);
function useCurrentLocation() {
    if (!navigator.geolocation) return;
    detecting.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            form.geo_fence_lat = pos.coords.latitude.toFixed(7);
            form.geo_fence_lng = pos.coords.longitude.toFixed(7);
            detecting.value = false;
        },
        () => { detecting.value = false; },
        { enableHighAccuracy: true, timeout: 15000 }
    );
}
</script>

<template>
    <SchoolLayout title="Geofence Configuration">
        <div class="flex gap-8 max-w-5xl mx-auto">
            <!-- Sidebar -->
            <aside class="hidden md:block w-56 flex-shrink-0 space-y-1">
                <Link v-for="item in settingsNav" :key="item.id" :href="item.route"
                      :class="[isActive(item.route) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900']"
                      class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                    </svg>
                    {{ item.label }}
                </Link>
            </aside>

            <!-- Content -->
            <div class="flex-1 space-y-6">
                <PageHeader
                    title="Geofence Configuration"
                    subtitle="Set the school campus centre and radius. Staff can only punch attendance within this boundary."
                />

                <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100">
                    <!-- Coordinates -->
                    <div class="p-6 space-y-4">
                        <h2 class="text-sm font-medium text-gray-700">School Campus Centre</h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Latitude</label>
                                <input v-model="form.geo_fence_lat" type="number" step="any" min="-90" max="90" placeholder="e.g. 12.9716"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                <p v-if="form.errors.geo_fence_lat" class="text-xs text-red-500 mt-1">{{ form.errors.geo_fence_lat }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Longitude</label>
                                <input v-model="form.geo_fence_lng" type="number" step="any" min="-180" max="180" placeholder="e.g. 77.5946"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                <p v-if="form.errors.geo_fence_lng" class="text-xs text-red-500 mt-1">{{ form.errors.geo_fence_lng }}</p>
                            </div>
                        </div>

                        <button type="button" @click="useCurrentLocation" :disabled="detecting"
                                class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 transition">
                            <svg v-if="detecting" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ detecting ? 'Detecting...' : 'Use my current location' }}
                        </button>
                    </div>

                    <!-- Radius -->
                    <div class="p-6 space-y-4">
                        <h2 class="text-sm font-medium text-gray-700">Allowed Radius</h2>
                        <div class="max-w-xs">
                            <label class="block text-sm font-medium text-gray-600 mb-1">Radius (metres)</label>
                            <input v-model="form.geo_fence_radius" type="number" min="50" max="5000" step="10"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                            <p class="text-xs text-gray-400 mt-1">Staff must be within this distance to punch attendance (50m - 5000m).</p>
                            <p v-if="form.errors.geo_fence_radius" class="text-xs text-red-500 mt-1">{{ form.errors.geo_fence_radius }}</p>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="px-6 py-4 bg-gray-50 flex justify-end">
                        <Button type="submit" :loading="form.processing"
                               >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Save Geofence
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </SchoolLayout>
</template>
