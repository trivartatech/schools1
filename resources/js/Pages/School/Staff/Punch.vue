<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    staff: Object,
    today: String,
    attendance: Object,   // null | { status, check_in, check_out }
    history: Array,
    geoFence: Object,     // { lat, lng, radius, enabled }
    noStaffRecord: { type: Boolean, default: false },
});

const page = usePage();

// ── Live clock ──────────────────────────────────────────────
const currentTime = ref(new Date());
let clockTimer = null;
onMounted(() => { clockTimer = setInterval(() => currentTime.value = new Date(), 1000); });
onUnmounted(() => clearInterval(clockTimer));

const timeStr = computed(() => {
    const t = currentTime.value;
    const hh = String(t.getHours()).padStart(2, '0');
    const mm = String(t.getMinutes()).padStart(2, '0');
    const ss = String(t.getSeconds()).padStart(2, '0');
    return school.fmtTime(`${hh}:${mm}:${ss}`);
});
const dateStr = computed(() => currentTime.value.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));

// ── Geolocation ─────────────────────────────────────────────
const geoStatus = ref('idle');   // idle | loading | success | error | denied
const geoError  = ref('');
const coords    = ref({ lat: null, lng: null });
const distance  = ref(null);     // metres from school centre

function getLocation() {
    if (!navigator.geolocation) {
        geoStatus.value = 'error';
        geoError.value  = 'Geolocation is not supported by your browser.';
        return Promise.reject(geoError.value);
    }

    geoStatus.value = 'loading';
    geoError.value  = '';

    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                coords.value = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                geoStatus.value = 'success';

                // Calculate distance from school
                if (props.geoFence.enabled) {
                    distance.value = haversine(
                        coords.value.lat, coords.value.lng,
                        props.geoFence.lat, props.geoFence.lng
                    );
                }
                resolve(coords.value);
            },
            (err) => {
                geoStatus.value = err.code === 1 ? 'denied' : 'error';
                geoError.value  = err.code === 1
                    ? 'Location permission denied. Please allow location access in your browser settings.'
                    : 'Unable to retrieve your location. Please try again.';
                reject(geoError.value);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    });
}

function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

const insideFence = computed(() => {
    if (!props.geoFence.enabled) return true;
    if (distance.value === null) return null;
    return distance.value <= props.geoFence.radius;
});

// ── Punch state ─────────────────────────────────────────────
const hasClockedIn  = computed(() => !!props.attendance?.check_in);
const hasClockedOut = computed(() => !!props.attendance?.check_out);
const punching      = ref(false);

const errors = computed(() => page.props.errors || {});

async function clockIn() {
    punching.value = true;
    try {
        await getLocation();
        router.post(route('school.staff-punch.clock-in'), {
            latitude:  coords.value.lat,
            longitude: coords.value.lng,
        }, {
            preserveScroll: true,
            onFinish: () => punching.value = false,
        });
    } catch {
        punching.value = false;
    }
}

async function clockOut() {
    punching.value = true;
    try {
        await getLocation();
        router.post(route('school.staff-punch.clock-out'), {
            latitude:  coords.value.lat,
            longitude: coords.value.lng,
        }, {
            preserveScroll: true,
            onFinish: () => punching.value = false,
        });
    } catch {
        punching.value = false;
    }
}

// Fetch location on mount so the user sees their status immediately
onMounted(() => {
    getLocation().catch(() => {});
});

// ── Helpers ─────────────────────────────────────────────────
const statusColors = {
    present:  { bg: 'bg-green-100', text: 'text-green-800', dot: 'bg-green-500' },
    absent:   { bg: 'bg-red-100',   text: 'text-red-800',   dot: 'bg-red-500' },
    late:     { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500' },
    half_day: { bg: 'bg-orange-100',text: 'text-orange-800',dot: 'bg-orange-500' },
    leave:    { bg: 'bg-blue-100',  text: 'text-blue-800',  dot: 'bg-blue-500' },
    holiday:  { bg: 'bg-indigo-100',text: 'text-indigo-800',dot: 'bg-indigo-500' },
};

function formatTime(t) {
    if (!t) return '--:--';
    return school.fmtTime(t);
}

function workingHours(checkIn, checkOut) {
    if (!checkIn || !checkOut) return null;
    const [h1, m1] = checkIn.split(':').map(Number);
    const [h2, m2] = checkOut.split(':').map(Number);
    const diff = (h2 * 60 + m2) - (h1 * 60 + m1);
    if (diff <= 0) return null;
    const hrs = Math.floor(diff / 60);
    const mins = diff % 60;
    return `${hrs}h ${mins}m`;
}
</script>

<template>
    <SchoolLayout title="Punch Attendance">
        <div class="max-w-2xl mx-auto space-y-6">

            <!-- No staff record -->
            <div v-if="noStaffRecord" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <h2 class="text-lg font-semibold text-gray-800 mb-2">No Staff Record Found</h2>
                <p class="text-sm text-gray-500">Your account is not linked to a staff profile. Please contact the administrator to set up your staff record before you can punch attendance.</p>
            </div>

            <template v-else>

            <!-- Validation error messages -->
            <div v-if="errors.geofence || errors.punch" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                {{ errors.geofence || errors.punch }}
            </div>

            <!-- Staff info + Live clock card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-8 text-center text-white">
                    <div class="flex justify-center mb-3">
                        <img v-if="staff.photo" :src="staff.photo" class="w-20 h-20 rounded-full border-4 border-white/30 object-cover" />
                        <div v-else class="w-20 h-20 rounded-full border-4 border-white/30 bg-white/20 flex items-center justify-center text-2xl font-bold">
                            {{ staff.name?.charAt(0) }}
                        </div>
                    </div>
                    <h2 class="text-xl font-semibold">{{ staff.name }}</h2>
                    <p class="text-blue-100 text-sm mt-1">{{ staff.employee_id }}</p>

                    <div class="mt-6">
                        <p class="text-4xl font-mono font-bold tracking-wide">{{ timeStr }}</p>
                        <p class="text-blue-200 text-sm mt-1">{{ dateStr }}</p>
                    </div>
                </div>

                <!-- Geofence status bar -->
                <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-3 text-sm">
                    <!-- Loading -->
                    <template v-if="geoStatus === 'loading'">
                        <svg class="animate-spin h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="text-gray-500">Detecting your location...</span>
                    </template>
                    <!-- Inside fence -->
                    <template v-else-if="geoStatus === 'success' && insideFence">
                        <span class="flex h-3 w-3 rounded-full bg-green-500"></span>
                        <span class="text-green-700">Inside school campus</span>
                        <span v-if="distance !== null" class="text-gray-400 ml-auto">{{ Math.round(distance) }}m from centre</span>
                    </template>
                    <!-- Outside fence -->
                    <template v-else-if="geoStatus === 'success' && insideFence === false">
                        <span class="flex h-3 w-3 rounded-full bg-red-500"></span>
                        <span class="text-red-700">Outside school campus</span>
                        <span class="text-gray-400 ml-auto">{{ distance >= 1000 ? (distance / 1000).toFixed(1) + ' km' : Math.round(distance) + 'm' }} away</span>
                    </template>
                    <!-- No geofence configured -->
                    <template v-else-if="geoStatus === 'success' && !geoFence.enabled">
                        <span class="flex h-3 w-3 rounded-full bg-green-500"></span>
                        <span class="text-green-700">Location acquired</span>
                    </template>
                    <!-- Error / denied -->
                    <template v-else-if="geoStatus === 'denied' || geoStatus === 'error'">
                        <span class="flex h-3 w-3 rounded-full bg-red-500"></span>
                        <span class="text-red-700">{{ geoError }}</span>
                        <button @click="getLocation().catch(() => {})" class="ml-auto text-blue-600 hover:underline text-xs">Retry</button>
                    </template>
                    <!-- Idle -->
                    <template v-else>
                        <span class="flex h-3 w-3 rounded-full bg-gray-300"></span>
                        <span class="text-gray-400">Location not yet acquired</span>
                    </template>
                </div>

                <!-- Punch buttons -->
                <div class="px-6 py-6">
                    <!-- Not clocked in yet -->
                    <div v-if="!hasClockedIn" class="text-center">
                        <Button variant="success"
                            @click="clockIn"
                            :disabled="punching || geoStatus === 'denied'"
                            class="max-w-xs"
                         size="lg" block>
                            <svg v-if="punching" class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                            {{ punching ? 'Punching...' : 'Clock In' }}
                        </Button>
                        <p class="text-gray-400 text-xs mt-3">Tap to record your arrival</p>
                    </div>

                    <!-- Clocked in, not yet out -->
                    <div v-else-if="hasClockedIn && !hasClockedOut" class="space-y-4">
                        <div class="flex items-center justify-between bg-green-50 rounded-xl p-4">
                            <div>
                                <p class="text-xs text-green-600 font-medium uppercase tracking-wide">Clocked In</p>
                                <p class="text-2xl font-semibold text-green-800">{{ formatTime(attendance.check_in) }}</p>
                            </div>
                            <span :class="[statusColors[attendance.status]?.bg, statusColors[attendance.status]?.text]" class="px-3 py-1 rounded-full text-xs font-medium capitalize">
                                {{ attendance.status }}
                            </span>
                        </div>

                        <div class="text-center">
                            <Button variant="danger"
                                @click="clockOut"
                                :disabled="punching || geoStatus === 'denied'"
                                class="max-w-xs"
                             size="lg" block>
                                <svg v-if="punching" class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                {{ punching ? 'Punching...' : 'Clock Out' }}
                            </Button>
                            <p class="text-gray-400 text-xs mt-3">Tap to record your departure</p>
                        </div>
                    </div>

                    <!-- Fully done for the day -->
                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-green-50 rounded-xl p-4 text-center">
                                <p class="text-xs text-green-600 font-medium uppercase tracking-wide">Clock In</p>
                                <p class="text-xl font-semibold text-green-800 mt-1">{{ formatTime(attendance.check_in) }}</p>
                            </div>
                            <div class="bg-red-50 rounded-xl p-4 text-center">
                                <p class="text-xs text-red-600 font-medium uppercase tracking-wide">Clock Out</p>
                                <p class="text-xl font-semibold text-red-800 mt-1">{{ formatTime(attendance.check_out) }}</p>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Working Hours</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ workingHours(attendance.check_in, attendance.check_out) || '--' }}</p>
                        </div>
                        <div class="text-center">
                            <span :class="[statusColors[attendance.status]?.bg, statusColors[attendance.status]?.text]" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium capitalize">
                                <span :class="statusColors[attendance.status]?.dot" class="w-2 h-2 rounded-full"></span>
                                {{ attendance.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent History -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Recent Attendance</h3>
                </div>
                <div v-if="history.length === 0" class="px-6 py-8 text-center text-gray-400 text-sm">
                    No attendance records yet.
                </div>
                <div v-else class="divide-y divide-gray-50">
                    <div v-for="rec in history" :key="rec.date" class="px-6 py-3 flex items-center gap-4">
                        <div class="flex-shrink-0 w-12 text-center">
                            <p class="text-xs text-gray-400">{{ rec.day }}</p>
                            <p class="text-sm font-semibold text-gray-700">{{ rec.date.split('-')[2] }}</p>
                        </div>
                        <span :class="[statusColors[rec.status]?.bg, statusColors[rec.status]?.text]" class="flex-shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium capitalize">
                            {{ rec.status }}
                        </span>
                        <div class="flex-1 text-sm text-gray-500 flex items-center gap-3">
                            <span v-if="rec.check_in">In: {{ formatTime(rec.check_in) }}</span>
                            <span v-if="rec.check_out">Out: {{ formatTime(rec.check_out) }}</span>
                            <span v-if="rec.check_in && rec.check_out" class="text-gray-400">
                                ({{ workingHours(rec.check_in, rec.check_out) }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Geofence info -->
            <div v-if="geoFence.enabled" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-2">Geofence Information</h3>
                <div class="text-sm text-gray-500 space-y-1">
                    <p>You must be within <span class="font-medium text-gray-700">{{ geoFence.radius }}m</span> of the school campus to punch attendance.</p>
                    <p class="text-xs text-gray-400">School coordinates: {{ geoFence.lat.toFixed(5) }}, {{ geoFence.lng.toFixed(5) }}</p>
                </div>
            </div>

            </template><!-- end v-else (has staff record) -->
        </div>
    </SchoolLayout>
</template>
