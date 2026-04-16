<script setup>
import { computed, ref, watch, watchEffect, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage, router, useForm } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from '@/Composables/useToast';
import { useSchoolStore } from '@/stores/useSchoolStore';

const schoolStore = useSchoolStore();
import ChatWidget from '@/Components/ChatWidget.vue';
import AiChatbot from '@/Components/AiChatbot.vue';
import Button from '@/Components/ui/Button.vue';
import Toast from '@/Components/ui/Toast.vue';

const props = defineProps({
    title: { type: String, default: 'School ERP' },
    school: { type: Object, default: null }
});

const page = usePage();
const { can, canAccess, isTeacher, isStudent, isParent, isAdmin, isAccountant, isDriver, isSchoolManagement, userType, filteredSidebarMenu } = usePermissions();

// User Profile Modal
const showProfileModal = ref(false);
const profileForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    profileForm.post('/profile/password', {
        preserveScroll: true,
        onSuccess: () => {
            showProfileModal.value = false;
            profileForm.reset();
        },
    });
};

// ── Punch Attendance (topbar panel) ──────────────────────────────
const showPunchPanel = ref(false);
const punchData = computed(() => page.props.punch);
const punchAtt  = computed(() => punchData.value?.attendance);
const hasClockedIn  = computed(() => !!punchAtt.value?.check_in);
const hasClockedOut = computed(() => !!punchAtt.value?.check_out);
const punching = ref(false);
const punchGeoStatus = ref('idle'); // idle | loading | success | error
const punchGeoError  = ref('');
const punchCoords    = ref({ lat: null, lng: null });
const punchDistance   = ref(null); // metres from school

const punchGeoFence = computed(() => punchData.value?.geoFence);
const punchInsideFence = computed(() => {
    if (!punchGeoFence.value?.enabled) return true; // no fence = always ok
    if (punchDistance.value === null) return null;   // unknown
    return punchDistance.value <= punchGeoFence.value.radius;
});
const punchDistanceLabel = computed(() => {
    if (punchDistance.value === null) return '';
    return punchDistance.value >= 1000
        ? (punchDistance.value / 1000).toFixed(1) + ' km'
        : Math.round(punchDistance.value) + 'm';
});

function haversineDistance(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function getPunchLocation() {
    if (!navigator.geolocation) {
        punchGeoStatus.value = 'error';
        punchGeoError.value = 'Geolocation is not supported by your browser.';
        return Promise.reject();
    }
    punchGeoStatus.value = 'loading';
    punchGeoError.value = '';
    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                punchCoords.value = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                punchGeoStatus.value = 'success';
                // Calculate distance from school
                if (punchGeoFence.value?.enabled) {
                    punchDistance.value = haversineDistance(
                        punchCoords.value.lat, punchCoords.value.lng,
                        punchGeoFence.value.lat, punchGeoFence.value.lng
                    );
                }
                resolve();
            },
            (err) => {
                punchGeoStatus.value = 'error';
                punchGeoError.value = err.code === 1
                    ? 'Location permission denied. Please allow location access in your browser settings to punch attendance.'
                    : 'Unable to retrieve your location. Please check your GPS and try again.';
                reject();
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    });
}

// Auto-detect location when panel opens
watch(showPunchPanel, (v) => {
    if (v && punchData.value && punchGeoStatus.value === 'idle') {
        getPunchLocation().catch(() => {});
    }
});

function punchClockIn() {
    punching.value = true;
    getPunchLocation().then(() => {
        router.post('/school/staff-punch/clock-in', {
            latitude: punchCoords.value.lat, longitude: punchCoords.value.lng,
        }, { preserveScroll: true, onFinish: () => punching.value = false });
    }).catch(() => punching.value = false);
}

function punchClockOut() {
    punching.value = true;
    getPunchLocation().then(() => {
        router.post('/school/staff-punch/clock-out', {
            latitude: punchCoords.value.lat, longitude: punchCoords.value.lng,
        }, { preserveScroll: true, onFinish: () => punching.value = false });
    }).catch(() => punching.value = false);
}

function formatPunchTime(t) {
    return t ? schoolStore.fmtTime(t) : '--:--';
}

function punchWorkingHours(checkIn, checkOut) {
    if (!checkIn || !checkOut) return null;
    const [h1, m1] = checkIn.split(':').map(Number);
    const [h2, m2] = checkOut.split(':').map(Number);
    const diff = (h2 * 60 + m2) - (h1 * 60 + m1);
    if (diff <= 0) return null;
    return `${Math.floor(diff / 60)}h ${diff % 60}m`;
}

const punchStatusColors = {
    present: 'bg-green-100 text-green-800',
    late:    'bg-amber-100 text-amber-800',
    absent:  'bg-red-100 text-red-800',
    half_day:'bg-orange-100 text-orange-800',
    leave:   'bg-blue-100 text-blue-800',
    holiday: 'bg-indigo-100 text-indigo-800',
};

// Close panel on click outside
function closePunchPanel(e) {
    const panel = document.getElementById('punch-panel');
    if (panel && !panel.contains(e.target)) showPunchPanel.value = false;
}
watch(showPunchPanel, (v) => {
    if (v) setTimeout(() => document.addEventListener('click', closePunchPanel), 0);
    else document.removeEventListener('click', closePunchPanel);
});

// Human-readable role label for the sidebar footer
const roleLabel = computed(() => ({
    super_admin: 'Super Admin',
    admin:       'Admin',
    school_admin:'Admin',
    principal:   'Admin',
    teacher:     'Teacher',
    student:     'Student',
    parent:      'Parent',
    accountant:  'Accountant',
    driver:      'Driver',
})[userType.value] ?? 'User');
const currentUrl = computed(() => page.url);

const isActive = (path) => {
    const url = currentUrl.value;
    if (url === path) return true;
    return url.startsWith(path) && (url[path.length] === '/' || url[path.length] === '?' || url[path.length] === undefined);
};

const navCls = (path) => isActive(path) ? 'nav-item nav-item--active' : 'nav-item';

const schoolName = computed(() => {
    const s = props.school ?? page.props.school ?? null;
    return s ? s.name : 'School Panel';
});

const activeMenu = ref('');

// Auto-detect active group from current URL by matching against config routes
const detectActiveMenu = () => {
    const u = currentUrl.value;
    
    // Find the first group whose route or any child route matches the current URL
    for (const item of filteredSidebarMenu.value) {
        if (!item.children) continue;
        
        const matchesChild = item.children.some(c => {
            if (!c.route) return false;
            const path = c.route.split('?')[0];
            // Match exact or as a parent segment
            return u === path || (u.startsWith(path) && (u[path.length] === '/' || u[path.length] === '?' || u[path.length] === undefined));
        });

        if (matchesChild) { 
            activeMenu.value = item.id; 
            return; 
        }

        // Also match the group root route if available (non-collapsible items that might have sub-routes)
        if (item.route) {
            const path = item.route;
            if (u === path || (u.startsWith(path) && (u[path.length] === '/' || u[path.length] === '?' || u[path.length] === undefined))) {
                activeMenu.value = item.id; 
                return; 
            }
        }
    }
};

// Reactively update active menu on navigation
watchEffect(() => {
    detectActiveMenu();
});

onMounted(() => {
    detectActiveMenu();

    if (impersonation.value?.active) {
        window.__impStartTs = Math.floor(Date.now() / 1000);
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 1000);
    }
});

const toggleMenu = (menuId) => { activeMenu.value = activeMenu.value === menuId ? '' : menuId; };

// Track last-seen group to inject dividers between groups
const seenGroups = ref(new Set());

// Toast — global composable, auto-triggers from Inertia flash props.
//
// We MUST NOT use watchEffect here: the middleware rebuilds `flash` on every
// response (so its object reference changes on each navigation), and
// SchoolLayout is a non-persistent layout that re-mounts on every page visit
// which re-fires `immediate`-style reads. Together with Inertia's polling
// partial reloads (notifications, chat) that also rebuild page.props, this
// caused the same flash message to re-toast over and over.
//
// Fix: watch the flash ref explicitly, dedupe against the last-shown tuple,
// and mutate-to-null after showing so even a re-triggered watcher with a
// stale reference can't fire twice.
const toast = useToast();
const lastFlashKey = ref('');

const showFlash = (f) => {
    if (!f) return;
    const key = `${f.success || ''}|${f.error || ''}|${f.warning || ''}`;
    if (!key || key === '||') return;
    if (key === lastFlashKey.value) return;
    lastFlashKey.value = key;

    if (f.success) { toast.success(f.success); f.success = null; }
    else if (f.error) { toast.error(f.error); f.error = null; }
    else if (f.warning) { toast.warning(f.warning); f.warning = null; }
};

// Initial page load — flash is already in props when the layout mounts.
showFlash(page.props.flash);

// Subsequent navigations — fire only when the flash reference actually changes.
watch(() => page.props.flash, showFlash);

// ── Impersonation Banner ──────────────────────────────────────────────
const impersonation = computed(() => page.props.impersonation);
const exitingImpersonation = ref(false);

// Countdown timer
const impersonationCountdown = ref('');
let countdownInterval = null;

const updateCountdown = () => {
    const imp = impersonation.value;
    if (!imp?.active) { impersonationCountdown.value = ''; return; }
    const elapsed = imp.elapsed_seconds + Math.floor((Date.now() / 1000) - window.__impStartTs);
    const remaining = Math.max(0, imp.timeout_seconds - elapsed);
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    impersonationCountdown.value = `${m}:${s.toString().padStart(2, '0')}`;
    if (remaining === 0) clearInterval(countdownInterval);
};

onMounted(() => {
    if (impersonation.value?.active) {
        window.__impStartTs = Math.floor(Date.now() / 1000);
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 1000);
    }
});

onUnmounted(() => { clearInterval(countdownInterval); });

watchEffect(() => {
    if (impersonation.value?.active) {
        window.__impStartTs = Math.floor(Date.now() / 1000);
        clearInterval(countdownInterval);
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 1000);
    } else {
        clearInterval(countdownInterval);
    }
});

const exitImpersonation = () => {
    if (exitingImpersonation.value) return;
    exitingImpersonation.value = true;
    router.post('/impersonate/exit', {}, {
        onFinish: () => { exitingImpersonation.value = false; }
    });
};

// Is the current user elevated (allowed to see User Management)
const canSeeUserManagement = computed(() => {
    const t = page.props.auth?.user?.user_type;
    return ['super_admin', 'admin', 'school_admin', 'principal'].includes(t);
});
</script>

<template>
    <Head :title="title" />

    <!-- Toast -->
    <!-- Global toast notifications -->
    <Toast />

    <div class="erp-shell">

        <!-- ═══════════════ SIDEBAR ═══════════════ -->
        <aside class="erp-sidebar">

            <!-- Brand -->
            <div class="sidebar-brand">
                <div class="brand-logo">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/></svg>
                </div>
                <div class="brand-text">
                    <span class="brand-name">{{ schoolName }}</span>
                    <span class="brand-sub">School ERP</span>
                </div>
            </div>

            <!-- Nav -->
            <nav class="sidebar-nav">
                <template v-for="(item, idx) in filteredSidebarMenu" :key="item.id">

                    <!-- Group divider label: render when group changes -->
                    <div
                        v-if="item.group && (idx === 0 || filteredSidebarMenu[idx - 1]?.group !== item.group)"
                        class="nav-group-label"
                    >{{ item.group }}</div>

                    <!-- ── Leaf item (no children) ── -->
                    <Link v-if="!item.children" :href="item.route" :class="navCls(item.route)">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" v-html="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>

                    <!-- ── Collapsible group ── -->
                    <div v-else class="nav-group">
                        <button
                            @click="toggleMenu(item.id)"
                            class="nav-group-btn"
                            :class="{ 'nav-group-btn--open': activeMenu === item.id }"
                        >
                            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" v-html="item.icon" />
                            <span>{{ item.title }}</span>
                            <svg class="nav-chevron" :class="{ 'rotate-180': activeMenu === item.id }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div v-show="activeMenu === item.id" class="nav-submenu">
                            <Link
                                v-for="child in item.children"
                                :key="child.route"
                                :href="child.route"
                                :class="navCls(child.route.split('?')[0])"
                            >{{ child.title }}</Link>
                        </div>
                    </div>

                </template>
            </nav>


            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <button @click="showProfileModal = true" class="sidebar-user w-full border-none cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500/20 group">
                    <div class="user-avatar shadow-sm group-hover:scale-105 transition-transform">{{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() || 'A' }}</div>
                    <div class="user-info text-left">
                        <span class="user-name">{{ page.props.auth?.user?.name || 'Admin' }}</span>
                        <div class="flex items-center gap-2">
                            <span class="user-role">{{ roleLabel }}</span>
                            <span class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-bold text-gray-400 group-hover:text-blue-400 transition-colors uppercase tracking-tighter">v2.4.0</span>
                        </div>
                    </div>
                </button>
            </div>
        </aside>

        <!-- ═══════════════ MAIN ═══════════════ -->
        <div class="erp-main">

            <!-- Topbar -->
            <header class="erp-topbar">
                <div class="topbar-left">
                    <div class="topbar-title-wrap">
                        <h1 class="page-title">{{ title }}</h1>
                        <span class="topbar-breadcrumb">{{ schoolName }}</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <!-- Super Admin School Switcher -->
                    <div class="relative group" v-if="page.props.auth?.user?.user_type === 'super_admin' && page.props.all_schools?.length > 0">
                        <button class="ay-switcher">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span>{{ page.props.school?.name || 'Select School' }}</span>
                            <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="ay-dropdown">
                            <p class="ay-dropdown-label">Switch School</p>
                            <div class="max-h-60 overflow-y-auto">
                                <Link v-for="s in page.props.all_schools" :key="s.id"
                                    href="/school/switch-school" method="post" as="button"
                                    :data="{ school_id: s.id }"
                                    class="ay-option" :class="page.props.school?.id === s.id ? 'ay-option--active' : ''">
                                    <span>{{ s.name }}</span>
                                    <svg v-if="page.props.school?.id === s.id" class="w-3.5 h-3.5 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Year Switcher -->
                    <div class="relative group" v-if="page.props.academic_year && page.props.all_academic_years?.length > 0">
                        <button class="ay-switcher" :class="!page.props.academic_year.is_active ? 'ay-switcher--archive' : ''">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>{{ page.props.academic_year.name }}</span>
                            <span v-if="!page.props.academic_year.is_active" class="ay-badge">Archive</span>
                            <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="ay-dropdown">
                            <p class="ay-dropdown-label">Switch Academic Year</p>
                            <div class="max-h-60 overflow-y-auto">
                                <Link v-for="year in page.props.all_academic_years" :key="year.id"
                                    href="/school/switch-academic-year" method="post" as="button"
                                    :data="{ academic_year_id: year.id }"
                                    class="ay-option" :class="page.props.academic_year.id === year.id ? 'ay-option--active' : ''">
                                    <span>{{ year.name }}</span>
                                    <span v-if="year.is_active" class="ay-active-pill">Active</span>
                                    <svg v-if="page.props.academic_year.id === year.id" class="w-3.5 h-3.5 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Punch Attendance Panel -->
                    <div v-if="punchData" class="relative" id="punch-panel">
                        <button @click.stop="showPunchPanel = !showPunchPanel" class="punch-topbar-btn" :class="{ 'punch-topbar-btn--active': hasClockedIn && !hasClockedOut }" title="Punch Attendance">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="hidden sm:inline">{{ hasClockedIn && !hasClockedOut ? 'In' : hasClockedOut ? 'Done' : 'Punch' }}</span>
                            <span v-if="hasClockedIn && !hasClockedOut" class="punch-live-dot"></span>
                        </button>

                        <!-- Dropdown Panel -->
                        <Transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 scale-95 translate-y-1" enter-to-class="opacity-100 scale-100 translate-y-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="showPunchPanel" class="punch-dropdown">
                                <!-- Today Status -->
                                <div class="punch-dropdown-header">
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Today's Attendance</span>
                                    <span v-if="punchAtt" :class="punchStatusColors[punchAtt.status]" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ punchAtt.status }}</span>
                                </div>

                                <!-- Clock Summary -->
                                <div v-if="punchAtt" class="punch-times">
                                    <div class="punch-time-block">
                                        <span class="punch-time-label">In</span>
                                        <span class="punch-time-value text-green-700">{{ formatPunchTime(punchAtt.check_in) }}</span>
                                    </div>
                                    <div class="punch-time-block">
                                        <span class="punch-time-label">Out</span>
                                        <span class="punch-time-value" :class="punchAtt.check_out ? 'text-red-700' : 'text-gray-300'">{{ formatPunchTime(punchAtt.check_out) }}</span>
                                    </div>
                                    <div v-if="punchAtt.check_in && punchAtt.check_out" class="punch-time-block">
                                        <span class="punch-time-label">Hours</span>
                                        <span class="punch-time-value text-blue-700">{{ punchWorkingHours(punchAtt.check_in, punchAtt.check_out) }}</span>
                                    </div>
                                </div>

                                <!-- Geofence Status Banner -->
                                <div v-if="punchGeoStatus === 'loading'" class="punch-geo-bar punch-geo-bar--loading">
                                    <svg class="animate-spin h-3.5 w-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span>Detecting your location...</span>
                                </div>
                                <div v-else-if="punchGeoStatus === 'success' && punchInsideFence === true" class="punch-geo-bar punch-geo-bar--inside">
                                    <span class="punch-geo-dot punch-geo-dot--green"></span>
                                    <span>Inside school campus</span>
                                    <span v-if="punchDistance !== null" class="ml-auto text-green-500 font-medium">{{ punchDistanceLabel }}</span>
                                </div>
                                <div v-else-if="punchGeoStatus === 'success' && punchInsideFence === false" class="punch-geo-bar punch-geo-bar--outside">
                                    <svg class="w-4 h-4 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    <div>
                                        <p class="font-semibold text-red-700">Outside school campus</p>
                                        <p class="text-red-500">You are <strong>{{ punchDistanceLabel }}</strong> away. Must be within {{ punchGeoFence.radius }}m to punch.</p>
                                    </div>
                                </div>
                                <div v-else-if="punchGeoStatus === 'error'" class="punch-geo-bar punch-geo-bar--error">
                                    <svg class="w-4 h-4 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    <div>
                                        <p class="font-semibold text-red-700">Location Error</p>
                                        <p class="text-red-500">{{ punchGeoError }}</p>
                                    </div>
                                    <button @click="getPunchLocation().catch(() => {})" class="ml-auto text-xs text-blue-600 hover:underline whitespace-nowrap">Retry</button>
                                </div>
                                <!-- Server-side geofence error -->
                                <div v-if="page.props.errors?.geofence" class="punch-geo-bar punch-geo-bar--outside">
                                    <svg class="w-4 h-4 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    <p class="text-red-600">{{ page.props.errors.geofence }}</p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="punch-actions">
                                    <button v-if="!hasClockedIn" @click="punchClockIn" :disabled="punching || punchInsideFence === false || punchGeoStatus === 'error'" class="punch-action-btn punch-action-btn--in">
                                        <svg v-if="punching" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                        Clock In
                                    </button>
                                    <button v-else-if="hasClockedIn && !hasClockedOut" @click="punchClockOut" :disabled="punching || punchInsideFence === false || punchGeoStatus === 'error'" class="punch-action-btn punch-action-btn--out">
                                        <svg v-if="punching" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        Clock Out
                                    </button>
                                    <div v-else class="px-4 py-3 text-center text-xs text-gray-400">Attendance complete for today</div>
                                </div>

                                <!-- Recent History -->
                                <div v-if="punchData.history?.length" class="punch-history">
                                    <p class="px-4 pt-3 pb-1 text-xs font-medium text-gray-400 uppercase tracking-wide">Recent</p>
                                    <div v-for="rec in punchData.history.slice(0, 5)" :key="rec.date" class="punch-history-row">
                                        <span class="text-xs text-gray-400 w-8">{{ rec.day }}</span>
                                        <span :class="punchStatusColors[rec.status]" class="px-1.5 py-0.5 rounded text-xs font-medium capitalize">{{ rec.status }}</span>
                                        <span class="text-xs text-gray-500 ml-auto">
                                            {{ rec.check_in ? formatPunchTime(rec.check_in) : '' }}
                                            <template v-if="rec.check_out"> — {{ formatPunchTime(rec.check_out) }}</template>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Divider -->
                    <div class="topbar-divider"></div>

                    <!-- User area (clickable → profile modal) -->
                    <button @click="showProfileModal = true" class="topbar-user-btn">
                        <div class="topbar-avatar">{{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() || 'A' }}</div>
                        <div class="topbar-user-info">
                            <span class="topbar-user-name">{{ page.props.auth?.user?.name || 'User' }}</span>
                            <span class="topbar-user-role">{{ roleLabel }}</span>
                        </div>
                        <svg class="w-3.5 h-3.5 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- Sign Out -->
                    <button class="signout-btn" title="Sign out" type="button" @click="router.post('/logout')">
                        <svg style="width:18px;height:18px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </div>
            </header>

            <!-- ═══════════════ IMPERSONATION BANNER ═══════════════ -->
            <!-- Impersonation Banner (Global) -->
            <Transition
                enter-active-class="transition duration-400 ease-out"
                enter-from-class="translate-y-[-100%] opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-250 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="impersonation?.active" class="impersonation-banner" id="impersonation-banner">
                    <div class="imp-banner-inner">
                        <div class="imp-icon-wrap">
                            <svg class="imp-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="imp-text">
                            <span class="imp-badge">Impersonation Active</span>
                            <span class="imp-message">
                                You are currently logged in as
                                <strong>{{ impersonation.impersonated_name }}</strong>.
                                Original account: <strong>{{ impersonation.original_name }}</strong>.
                            </span>
                        </div>
                        <div class="imp-timer" :class="{ 'imp-timer--warning': impersonationCountdown && impersonationCountdown < '5:00' }">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ impersonationCountdown }}</span>
                        </div>
                        <button
                            id="exit-impersonation-btn"
                            @click="exitImpersonation"
                            :disabled="exitingImpersonation"
                            class="imp-exit-btn">
                            <svg v-if="!exitingImpersonation" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            {{ exitingImpersonation ? 'Returning...' : 'Exit & Return to My Account' }}
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- ── User Profile Modal ── -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="showProfileModal" class="profile-modal-overlay">
                    <div class="profile-modal" @click.stop>
                        <div class="profile-modal-header">
                            <h3 class="profile-modal-title">My Profile</h3>
                            <button @click="showProfileModal = false" class="close-modal-btn">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="profile-modal-body">
                            <!-- User Identity -->
                            <div class="profile-identity">
                                <div class="identity-avatar">{{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() }}</div>
                                <div class="identity-details">
                                    <span class="identity-name">{{ page.props.auth?.user?.name }}</span>
                                    <div class="identity-username">
                                        <span class="label">Username:</span>
                                        <span class="value">{{ page.props.auth?.user?.username || page.props.auth?.user?.email }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Change Password Form -->
                            <form @submit.prevent="updatePassword" class="password-form">
                                <div class="form-section-title">Change Password</div>
                                
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input v-model="profileForm.current_password" type="password" required placeholder="Enter current password" />
                                    <span v-if="profileForm.errors.current_password" class="form-error">{{ profileForm.errors.current_password }}</span>
                                </div>

                                <div class="form-group">
                                    <label>New Password</label>
                                    <input v-model="profileForm.password" type="password" required placeholder="Min. 8 characters" />
                                    <span v-if="profileForm.errors.password" class="form-error">{{ profileForm.errors.password }}</span>
                                </div>

                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input v-model="profileForm.password_confirmation" type="password" required placeholder="Repeat new password" />
                                </div>

                                <div class="form-actions">
                                    <Button variant="cancel" type="button" @click="showProfileModal = false">Cancel</Button>
                                    <Button variant="save" type="submit" :loading="profileForm.processing">
                                        {{ profileForm.processing ? 'Saving...' : 'Update Password' }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Archive Banner -->
            <div v-if="page.props.academic_year && !page.props.academic_year.is_active"
                class="archive-banner">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span><strong>Archive Mode:</strong> Viewing {{ page.props.academic_year.name }} — Data is read-only.</span>
            </div>

            <!-- Page Content -->
            <main class="erp-content">
                <slot />
            </main>

            <!-- ═══════════════ FOOTER ═══════════════ -->
            <footer class="erp-footer">
                <div class="max-w-7xl mx-auto flex items-center justify-between">
                    <span class="footer-credit">
                        {{ page.props.school?.settings?.footer_credit || `© ${new Date().getFullYear()} ${schoolName}. All rights reserved.` }}
                    </span>
                    <div class="footer-links">
                        <span class="footer-version">Release v2.4.0</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- ── Floating Chat Widget ── -->
    <ChatWidget />

    <!-- ── AI Assistant Chatbot ── -->
    <AiChatbot />
</template>

<style>
/* ══════════════════════════════════════════════════════════════
   ERP DESIGN SYSTEM v3 — Modern Premium UI
   Palette: Deep Navy sidebar · Indigo/Violet accent · Soft backgrounds
   ══════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; }

:root {
    --sidebar-bg:      #0d1117;
    --sidebar-border:  rgba(255,255,255,0.06);
    --sidebar-w:       256px;
    --accent:          #6366f1;
    --accent-dark:     #4f46e5;
    --accent-light:    #818cf8;
    --accent-glow:     rgba(99,102,241,0.35);
    --accent-subtle:   rgba(99,102,241,0.12);
    --success:         #10b981;
    --danger:          #ef4444;
    --warning:         #f59e0b;
    --bg:              #f1f5f9;
    --surface:         #ffffff;
    --border:          #e2e8f0;
    --border-light:    #f1f5f9;
    --text-primary:    #0f172a;
    --text-secondary:  #475569;
    --text-muted:      #94a3b8;
    --shadow-sm:       0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md:       0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.04);
    --shadow-lg:       0 12px 40px rgba(0,0,0,0.12), 0 4px 16px rgba(0,0,0,0.06);
    --radius:          10px;
    --radius-lg:       14px;
    --radius-xl:       18px;
}

body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
}

/* ══════ SHELL ══════ */
.erp-shell {
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: var(--bg);
}

/* ══════════════════════════════════════════════════════════════
   SIDEBAR
   ══════════════════════════════════════════════════════════════ */
.erp-sidebar {
    width: var(--sidebar-w);
    min-width: var(--sidebar-w);
    background: var(--sidebar-bg);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    border-right: 1px solid rgba(255,255,255,0.04);
    position: relative;
}
/* Subtle top gradient accent */
.erp-sidebar::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, var(--accent), #8b5cf6, transparent);
}
.erp-sidebar::-webkit-scrollbar { width: 3px; }
.erp-sidebar::-webkit-scrollbar-track { background: transparent; }
.erp-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

/* ── Brand ── */
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 20px 16px 18px;
    border-bottom: 1px solid var(--sidebar-border);
    flex-shrink: 0;
}
.brand-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
    flex-shrink: 0;
    box-shadow: 0 4px 14px var(--accent-glow);
}
.brand-text { display: flex; flex-direction: column; min-width: 0; }
.brand-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.01em;
}
.brand-sub {
    font-size: 0.6875rem;
    color: rgba(148,163,184,0.55);
    font-weight: 500;
    letter-spacing: 0.02em;
    margin-top: 1px;
}

/* ── Nav ── */
.sidebar-nav {
    flex: 1;
    padding: 10px 10px 6px;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

/* Nav item (leaf) */
.nav-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7.5px 10px;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgba(148,163,184,0.85);
    transition: background 0.14s, color 0.14s;
    text-decoration: none;
    cursor: pointer;
    position: relative;
}
.nav-item:hover {
    background: rgba(255,255,255,0.07);
    color: #e2e8f0;
}
.nav-item--active {
    background: var(--accent-subtle) !important;
    color: var(--accent-light) !important;
    font-weight: 600;
}
.nav-item--active::before {
    content: '';
    position: absolute;
    left: 0; top: 20%; bottom: 20%;
    width: 3px;
    border-radius: 0 2px 2px 0;
    background: var(--accent-light);
}

.nav-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    opacity: 0.7;
}
.nav-item--active .nav-icon { opacity: 1; }

/* Nav group */
.nav-group { display: flex; flex-direction: column; }
.nav-group-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7.5px 10px;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgba(148,163,184,0.85);
    transition: background 0.14s, color 0.14s;
    background: transparent;
    border: none;
    cursor: pointer;
    width: 100%;
    text-align: left;
}
.nav-group-btn:hover { background: rgba(255,255,255,0.07); color: #e2e8f0; }
.nav-group-btn--open { color: #e2e8f0; }
.nav-group-btn span { flex: 1; }
.nav-chevron {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
    opacity: 0.4;
    transition: transform 0.22s ease;
}

/* Submenu */
.nav-submenu {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 2px 0 4px 25px;
    margin-top: 1px;
}
.nav-submenu .nav-item {
    padding: 6px 10px;
    font-size: 0.775rem;
    color: rgba(148,163,184,0.65);
    border-radius: 0 7px 7px 0;
    border-left: 1px solid rgba(255,255,255,0.06);
}
.nav-submenu .nav-item:hover {
    background: rgba(255,255,255,0.05);
    color: #cbd5e1;
    border-left-color: rgba(255,255,255,0.12);
}
.nav-submenu .nav-item--active {
    background: var(--accent-subtle) !important;
    color: var(--accent-light) !important;
    border-left: 2px solid var(--accent) !important;
}
.nav-submenu .nav-item--active::before { display: none; }

/* Group labels */
.nav-group-label {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(100,116,139,0.5);
    padding: 14px 10px 4px;
    margin-top: 2px;
}

/* ── Sidebar footer ── */
.sidebar-footer {
    padding: 10px 10px 14px;
    border-top: 1px solid var(--sidebar-border);
    flex-shrink: 0;
}
.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: 9px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.05);
    transition: background 0.15s, border-color 0.15s;
}
.sidebar-user:hover {
    background: rgba(255,255,255,0.07);
    border-color: rgba(255,255,255,0.09);
}
.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: linear-gradient(135deg, var(--accent), #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 2px 8px var(--accent-glow);
}
.user-info { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.user-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #e2e8f0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.user-role { font-size: 0.6875rem; color: rgba(148,163,184,0.5); line-height: 1.3; }

/* ══════════════════════════════════════════════════════════════
   MAIN AREA
   ══════════════════════════════════════════════════════════════ */
.erp-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

/* ── Topbar ── */
.erp-topbar {
    height: 58px;
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    z-index: 10;
    gap: 16px;
}
.topbar-left { display: flex; align-items: center; gap: 14px; min-width: 0; }
.topbar-title-wrap { display: flex; flex-direction: column; min-width: 0; }
.page-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.015em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.topbar-breadcrumb {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
}
.topbar-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

/* Topbar divider */
.topbar-divider {
    width: 1px;
    height: 24px;
    background: var(--border);
    flex-shrink: 0;
}

/* Topbar user button */
.topbar-user-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 5px 10px 5px 6px;
    border-radius: 9px;
    border: 1px solid var(--border);
    background: var(--surface);
    cursor: pointer;
    transition: all 0.15s;
}
.topbar-user-btn:hover {
    border-color: var(--accent);
    background: #f5f5ff;
}
.topbar-avatar {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: linear-gradient(135deg, var(--accent), #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.topbar-user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 1px;
}
.topbar-user-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.2;
    white-space: nowrap;
}
.topbar-user-role {
    font-size: 0.65rem;
    color: var(--text-muted);
    line-height: 1.1;
    white-space: nowrap;
}

/* ── Academic Year / School Switcher ── */
.ay-switcher {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 8px;
    border: 1px solid var(--border);
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text-secondary);
    background: var(--surface);
    cursor: pointer;
    transition: all 0.14s;
    white-space: nowrap;
}
.ay-switcher:hover { border-color: var(--accent); color: var(--accent); background: #f5f5ff; }
.ay-switcher--archive { border-color: #fbbf24; background: #fffbeb; color: #92400e; }
.ay-badge {
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 4px;
    background: #fde68a;
    color: #92400e;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.ay-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    width: 226px;
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px) scale(0.98);
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 60;
    overflow: hidden;
}
.group:hover .ay-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}
.ay-dropdown-label {
    padding: 9px 14px;
    font-size: 0.625rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid var(--border-light);
    background: #fafbfc;
}
.ay-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    font-size: 0.8125rem;
    color: var(--text-secondary);
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
    transition: background 0.1s;
}
.ay-option:hover { background: #f5f5ff; color: var(--accent); }
.ay-option--active { background: #f5f5ff; color: var(--accent); font-weight: 600; }
.ay-active-pill {
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 4px;
    background: #d1fae5;
    color: #065f46;
    font-weight: 700;
    text-transform: uppercase;
    margin-left: auto;
}

/* ── Punch attendance topbar ── */
.punch-topbar-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.14s;
    flex-shrink: 0;
    border: none;
    position: relative;
}
.punch-topbar-btn:hover { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); box-shadow: 0 2px 8px rgba(22,163,74,.35); }
.punch-topbar-btn--active { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.punch-topbar-btn--active:hover { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); box-shadow: 0 2px 8px rgba(217,119,6,.35); }
.punch-live-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #fff; animation: punch-blink 1.5s infinite;
    position: absolute; top: 4px; right: 4px;
}
@keyframes punch-blink { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

.punch-dropdown {
    position: absolute; top: calc(100% + 8px); right: 0;
    width: 320px; background: #fff;
    border-radius: 12px; border: 1px solid #e5e7eb;
    box-shadow: 0 12px 36px rgba(0,0,0,.12), 0 0 0 1px rgba(0,0,0,.04);
    z-index: 100; overflow: hidden;
}
.punch-dropdown-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px 10px; border-bottom: 1px solid #f3f4f6;
}
.punch-times {
    display: flex; gap: 2px; padding: 12px 16px;
    border-bottom: 1px solid #f3f4f6;
}
.punch-time-block { flex: 1; text-align: center; }
.punch-time-label { display: block; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #9ca3af; letter-spacing: 0.05em; }
.punch-time-value { display: block; font-size: 0.875rem; font-weight: 700; margin-top: 2px; }
.punch-actions { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
.punch-action-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 10px; border-radius: 8px;
    font-size: 0.8125rem; font-weight: 600; color: #fff;
    border: none; cursor: pointer; transition: all 0.14s;
}
.punch-action-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.punch-action-btn--in { background: #22c55e; }
.punch-action-btn--in:hover:not(:disabled) { background: #16a34a; }
.punch-action-btn--out { background: #ef4444; }
.punch-action-btn--out:hover:not(:disabled) { background: #dc2626; }
/* ── Geofence status bars ── */
.punch-geo-bar {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 16px; font-size: 0.75rem; line-height: 1.4;
    border-bottom: 1px solid #f3f4f6;
}
.punch-geo-bar--loading { color: #6b7280; background: #f9fafb; }
.punch-geo-bar--inside { color: #166534; background: #f0fdf4; align-items: center; }
.punch-geo-bar--outside { color: #991b1b; background: #fef2f2; }
.punch-geo-bar--error { color: #991b1b; background: #fef2f2; }
.punch-geo-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 1px; }
.punch-geo-dot--green { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
.punch-history { border-top: 1px solid #f3f4f6; }
.punch-history-row {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 16px; font-size: 0.75rem;
}
.punch-history-row:last-child { padding-bottom: 12px; }

/* ── Sign out ── */
.signout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    color: var(--danger);
    background: #fff5f5;
    border: 1px solid #fecaca;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.14s;
    flex-shrink: 0;
}
.signout-btn:hover { background: #fee2e2; border-color: #fca5a5; }

/* ── Archive banner ── */
.archive-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 24px;
    background: #fffbeb;
    border-bottom: 1px solid #fde68a;
    font-size: 0.8125rem;
    color: #78350f;
    flex-shrink: 0;
}

/* ══════════════════════════════════════════════════════════════
   IMPERSONATION BANNER
   ══════════════════════════════════════════════════════════════ */
.impersonation-banner {
    background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 60%, #4c1d95 100%);
    border-bottom: 1px solid rgba(167,139,250,0.3);
    position: relative;
    z-index: 20;
    box-shadow: 0 4px 24px rgba(109,40,217,0.3);
    flex-shrink: 0;
}
.impersonation-banner::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        -45deg, transparent, transparent 10px,
        rgba(255,255,255,0.03) 10px, rgba(255,255,255,0.03) 20px
    );
    pointer-events: none;
}
.imp-banner-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 24px;
    position: relative;
    z-index: 1;
}
.imp-icon-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 9px;
    background: rgba(255,255,255,0.15);
    flex-shrink: 0;
    animation: imp-pulse 2s ease-in-out infinite;
}
@keyframes imp-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.25); }
    50%      { box-shadow: 0 0 0 6px rgba(255,255,255,0); }
}
.imp-icon { width: 16px; height: 16px; color: #fff; }
.imp-text { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
.imp-badge {
    font-size: 0.575rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
}
.imp-message { font-size: 0.825rem; color: #fff; font-weight: 500; }
.imp-message strong { font-weight: 700; color: #e9d5ff; }
.imp-timer {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 11px;
    background: rgba(255,255,255,0.13);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 20px;
    font-size: 0.8125rem; font-weight: 700; color: #fff;
    font-variant-numeric: tabular-nums;
    flex-shrink: 0;
    transition: all 0.3s;
}
.imp-timer--warning {
    background: rgba(251,191,36,0.2) !important;
    border-color: rgba(251,191,36,0.45) !important;
    color: #fde68a !important;
    animation: imp-warning-blink 1s step-end infinite;
}
@keyframes imp-warning-blink { 50% { opacity: 0.55; } }
.imp-exit-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px;
    border-radius: 9px;
    font-size: 0.8125rem; font-weight: 600;
    color: #5b21b6; background: #fff; border: none;
    cursor: pointer; flex-shrink: 0;
    transition: all 0.15s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.18);
    white-space: nowrap;
}
.imp-exit-btn:hover:not(:disabled) {
    background: #f5f3ff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.22);
    transform: translateY(-1px);
}
.imp-exit-btn:disabled { opacity: 0.65; cursor: not-allowed; }

/* ══════════════════════════════════════════════════════════════
   CONTENT AREA
   ══════════════════════════════════════════════════════════════ */
.erp-content {
    flex: 1;
    overflow-y: auto;
    padding: 26px 28px;
}
.erp-content::-webkit-scrollbar { width: 5px; }
.erp-content::-webkit-scrollbar-track { background: transparent; }
.erp-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

/* ── Footer ── */
.erp-footer {
    padding: 12px 28px;
    background: var(--surface);
    border-top: 1px solid var(--border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.footer-credit { font-size: 0.775rem; font-weight: 500; color: var(--text-muted); }
.footer-links { display: flex; align-items: center; gap: 12px; }
.footer-version {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    background: var(--border-light);
    padding: 2px 8px;
    border-radius: 5px;
}

/* Toast notifications — moved to Components/ui/Toast.vue */

/* ══════════════════════════════════════════════════════════════
   GLOBAL FORM DESIGN SYSTEM
   ══════════════════════════════════════════════════════════════ */
input[type="text"],
input[type="email"],
input[type="password"],
input[type="number"],
input[type="date"],
input[type="tel"],
input[type="search"],
input[type="url"],
select,
textarea {
    width: 100%;
    padding: 9px 12px;
    font-size: 0.875rem;
    color: var(--text-primary);
    background: var(--surface);
    border: 1.5px solid #d1d5db;
    border-radius: var(--radius);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    line-height: 1.5;
    font-family: inherit;
    -webkit-appearance: none;
    appearance: none;
}
input:focus, select:focus, textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.14);
}
input:disabled, select:disabled, textarea:disabled {
    background: #f8fafc;
    color: var(--text-muted);
    cursor: not-allowed;
    opacity: 0.75;
}
input::placeholder, textarea::placeholder { color: #9ca3af; }

input[type="checkbox"] {
    width: 16px;
    height: 16px;
    border-radius: 5px;
    border: 1.5px solid #d1d5db;
    cursor: pointer;
    appearance: auto;
    accent-color: var(--accent);
}

/* ─── Labels ─── */
label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
}

/* ══════════════════════════════════════════════════════════════
   CARD COMPONENT
   ══════════════════════════════════════════════════════════════ */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}
.card-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.card-title { font-size: 0.9375rem; font-weight: 700; color: var(--text-primary); }
.card-body { padding: 20px; }

/* ─── Section headings ─── */
.section-heading {
    font-size: 0.675rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: var(--text-muted);
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-light);
    margin-bottom: 16px;
}

/* ══════════════════════════════════════════════════════════════
   BUTTON SYSTEM — migrated to <Button> component
   (legacy .btn / .btn-primary / .btn-secondary / .btn-danger /
    .btn-success / .btn-warning / .btn-sm / .btn-xs / .btn-lg
    removed — see resources/js/Components/ui/Button.vue)
   ══════════════════════════════════════════════════════════════ */

/* ══════════════════════════════════════════════════════════════
   TABLE SYSTEM — migrated to <Table> component
   (legacy .erp-table styles removed — see
    resources/js/Components/ui/Table.vue)
   ══════════════════════════════════════════════════════════════ */

/* ══════════════════════════════════════════════════════════════
   BADGE SYSTEM
   ══════════════════════════════════════════════════════════════ */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
}
.badge-green  { background: #d1fae5; color: #065f46; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-amber  { background: #fef3c7; color: #78350f; }
.badge-blue   { background: #dbeafe; color: #1e40af; }
.badge-purple { background: #ede9fe; color: #5b21b6; }
.badge-gray   { background: #f1f5f9; color: #475569; }
.badge-indigo { background: #e0e7ff; color: #3730a3; }
.badge-pink   { background: #fce7f3; color: #9d174d; }
.badge-cyan   { background: #cffafe; color: #155e75; }

/* ─── Page header ─── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 22px;
    gap: 16px;
}
.page-header-title { font-size: 1.25rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; }
.page-header-sub { font-size: 0.8125rem; color: var(--text-muted); margin-top: 3px; font-weight: 400; }

/* ─── Form grid helpers ─── */
.form-row   { display: grid; gap: 16px; }
.form-row-2 { grid-template-columns: repeat(2, 1fr); }
.form-row-3 { grid-template-columns: repeat(3, 1fr); }
.form-row-4 { grid-template-columns: repeat(4, 1fr); }
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-error { color: var(--danger); font-size: 0.75rem; margin-top: 3px; font-weight: 500; }

/* ══════════════════════════════════════════════════════════════
   PROFILE MODAL
   ══════════════════════════════════════════════════════════════ */
.profile-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(15,23,42,0.5);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.profile-modal {
    background: var(--surface);
    width: 100%;
    max-width: 440px;
    border-radius: var(--radius-xl);
    box-shadow: 0 30px 60px rgba(0,0,0,0.22);
    overflow: hidden;
    animation: modalSlide 0.28s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes modalSlide {
    from { transform: translateY(24px) scale(0.94); opacity: 0; }
    to   { transform: translateY(0) scale(1); opacity: 1; }
}
.profile-modal-header {
    padding: 18px 24px;
    background: #fafbfc;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.profile-modal-title { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); margin: 0; }
.close-modal-btn {
    width: 32px; height: 32px;
    border-radius: 8px; border: none;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.close-modal-btn:hover { background: #fee2e2; color: var(--danger); }

.profile-modal-body { padding: 24px; }
.profile-identity {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: linear-gradient(135deg, #f5f5ff 0%, #ede9fe 100%);
    border-radius: 14px;
    margin-bottom: 24px;
    border: 1px solid #e0e7ff;
}
.identity-avatar {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, var(--accent), #8b5cf6);
    color: #fff;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 800;
    box-shadow: 0 4px 14px var(--accent-glow);
}
.identity-details { display: flex; flex-direction: column; gap: 4px; }
.identity-name { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); }
.identity-username { display: flex; align-items: center; gap: 6px; font-size: 0.825rem; }
.identity-username .label { color: var(--text-muted); }
.identity-username .value { color: var(--accent); font-weight: 700; }

.password-form { display: flex; flex-direction: column; gap: 18px; }
.form-section-title {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
}
.form-group { display: flex; flex-direction: column; gap: 7px; }
.form-group label { font-size: 0.8375rem; font-weight: 600; color: #374151; }
.form-group input {
    padding: 11px 14px;
    border-radius: 11px;
    border: 1.5px solid var(--border);
    font-size: 0.9375rem;
    transition: all 0.18s;
}
.form-group input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }

.form-actions { display: grid; grid-template-columns: 1fr 2fr; gap: 12px; margin-top: 6px; }
/* .btn-cancel / .btn-save migrated to <Button variant="cancel|save"> */

/* ══════════════════════════════════════════════════════════════
   UTILITY CLASSES
   ══════════════════════════════════════════════════════════════ */
.text-muted   { color: var(--text-muted); }
.text-success { color: var(--success); }
.text-danger  { color: var(--danger); }
.text-warning { color: var(--warning); }
.text-accent  { color: var(--accent); }

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.2s, transform 0.2s;
}
.stat-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}
.stat-value { font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.03em; line-height: 1; }
.stat-label { font-size: 0.8125rem; color: var(--text-muted); font-weight: 500; }
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-icon-indigo { background: #e0e7ff; color: var(--accent); }
.stat-icon-green  { background: #d1fae5; color: #059669; }
.stat-icon-amber  { background: #fef3c7; color: #d97706; }
.stat-icon-red    { background: #fee2e2; color: var(--danger); }
.stat-icon-blue   { background: #dbeafe; color: #2563eb; }
.stat-icon-purple { background: #ede9fe; color: #7c3aed; }
</style>
