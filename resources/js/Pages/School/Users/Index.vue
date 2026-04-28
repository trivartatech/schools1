<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import debounce from 'lodash/debounce';
import Button from '@/Components/ui/Button.vue';
import { useToast } from '@/Composables/useToast';
import { usePermissions } from '@/Composables/usePermissions';

const toast = useToast();
const { can } = usePermissions();

// CSRF — Laravel sets the `XSRF-TOKEN` cookie and rotates it on session
// changes. The meta tag is set once on first render and goes stale on
// long-lived pages. Reading the cookie + sending as X-XSRF-TOKEN is what
// axios does automatically; we replicate it here for our fetch() calls.
const csrfHeader = () => {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m
        ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) }
        : { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' };
};

const props = defineProps({
    users:           Object,
    filters:         Object,
    classes:         Array,
    sections:        Array,
    missing_counts:  Object,
});

const search    = ref(props.filters.search    || '');
const userType  = ref(props.filters.user_type || '');
const status    = ref(props.filters.status    || '');
const classId   = ref(props.filters.class_id  || '');
const sectionId = ref(props.filters.section_id|| '');

// Reset section when class changes (sections are class-scoped)
watch(classId, () => { sectionId.value = ''; });

// Sections filtered to the chosen class (or all if no class)
const sectionsForClass = computed(() => {
    if (!classId.value) return props.sections;
    return props.sections.filter(s => String(s.course_class_id) === String(classId.value));
});

// Show class+section filter for both students AND parents now
const showClassFilter = computed(() => userType.value === 'student' || userType.value === 'parent');

watch([search, userType, status, classId, sectionId], debounce(([v1, v2, v3, v4, v5]) => {
    router.get('/school/users', {
        search: v1, user_type: v2, status: v3, class_id: v4, section_id: v5,
    }, { preserveState: true, replace: true });
}, 300));

// ── Selection (bulk actions) ────────────────────────────────────────────────
const selectedIds = ref(new Set());
const isSelected  = (id) => selectedIds.value.has(id);
const toggleOne   = (id) => {
    if (selectedIds.value.has(id)) selectedIds.value.delete(id);
    else selectedIds.value.add(id);
    selectedIds.value = new Set(selectedIds.value); // trigger reactivity
};
const allSelected = computed(() =>
    props.users.data.length > 0 &&
    props.users.data.every(u => selectedIds.value.has(u.id))
);
const toggleAll = () => {
    if (allSelected.value) {
        props.users.data.forEach(u => selectedIds.value.delete(u.id));
    } else {
        props.users.data.forEach(u => selectedIds.value.add(u.id));
    }
    selectedIds.value = new Set(selectedIds.value);
};
const clearSelection = () => { selectedIds.value = new Set(); };

// ── Per-row password reset (existing modal flow) ────────────────────────────
const resetModalUser     = ref(null);
const generatedPassword  = ref('');

const handleResetPassword = async (user) => {
    if (!confirm(`Are you sure you want to reset the password for ${user.name}?`)) return;
    try {
        const response = await fetch(`/school/users/${user.id}/reset-password`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
        });
        const result = await response.json();
        if (result.success) {
            resetModalUser.value = user;
            generatedPassword.value = result.password;
        }
    } catch (error) {
        console.error('Error resetting password:', error);
    }
};

const toggleAccess = (user) => {
    if (user.id === usePage().props.auth.user.id) {
        toast.warning("You cannot disable your own account.");
        return;
    }
    router.post(`/school/users/${user.id}/toggle-status`, {}, { preserveScroll: true });
};

const impersonateUser = (user) => {
    if (!confirm(`Are you sure you want to login as ${user.name}?`)) return;
    router.post(`/impersonate/${user.id}`);
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    toast.info('Password copied to clipboard!');
};

// ── Bulk credentials modal ──────────────────────────────────────────────────
const bulkModal       = ref(false);     // visible after createMissing or bulkReset
const bulkRows        = ref([]);        // [{ name, role, username, password }]
const bulkBusy        = ref(false);
const bulkTitle       = ref('');

const closeBulkModal = () => { bulkModal.value = false; bulkRows.value = []; clearSelection(); router.reload({ only: ['users', 'missing_counts'] }); };

// Create missing logins (parents / students / all)
const createMissing = async (type) => {
    const label = { parent: 'parents', student: 'students', all: 'parents and students' }[type] || type;
    if (!confirm(`Create login accounts for all ${label} who don't have one yet?`)) return;
    bulkBusy.value = true;
    try {
        const res = await fetch('/school/users/create-missing', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
            body: JSON.stringify({ type }),
        });
        const result = await res.json();
        if (result.success && result.count > 0) {
            bulkRows.value  = result.credentials;
            bulkTitle.value = `${result.count} new login${result.count === 1 ? '' : 's'} created`;
            bulkModal.value = true;
        } else if (result.success) {
            toast.info(result.message || 'No missing accounts to create.');
        } else {
            toast.error(result.message || 'Could not create logins.');
        }
    } catch (e) {
        toast.error('Could not create logins.');
    } finally { bulkBusy.value = false; }
};

// Reset passwords for all currently selected users
const bulkReset = async () => {
    const ids = [...selectedIds.value];
    if (ids.length === 0) return toast.warning('Select at least one user.');
    if (!confirm(`Reset passwords for ${ids.length} selected user(s)? Each will get a fresh random password.`)) return;
    bulkBusy.value = true;
    try {
        const res = await fetch('/school/users/bulk-reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
            body: JSON.stringify({ user_ids: ids }),
        });
        const result = await res.json();
        if (result.success) {
            bulkRows.value  = result.credentials;
            bulkTitle.value = `${result.count} password${result.count === 1 ? '' : 's'} reset`;
            bulkModal.value = true;
        } else {
            toast.error('Bulk reset failed.');
        }
    } catch (e) {
        toast.error('Bulk reset failed.');
    } finally { bulkBusy.value = false; }
};

// Export the rows currently shown in the modal as Excel or PDF
const exportCredentials = async (format) => {
    if (bulkRows.value.length === 0) return;
    const res = await fetch('/school/users/export-credentials', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...csrfHeader() },
        body: JSON.stringify({ rows: bulkRows.value, format }),
    });
    if (!res.ok) { toast.error('Export failed.'); return; }
    const blob = await res.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `user-credentials-${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.${format}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    toast.success(`Downloaded ${format.toUpperCase()}`);
};

const getRoleLabel = (type) => ({
    principal: 'Principal', teacher: 'Teacher', student: 'Student',
    parent: 'Parent', accountant: 'Accountant', driver: 'Driver',
    school_admin: 'Admin',
})[type] || type;

const getStatusBadgeClass = (active) => active
    ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
    : 'bg-rose-100 text-rose-700 border-rose-200';
</script>

<template>
    <SchoolLayout title="User Management">
        <Head title="User Management" />

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 py-2 border-b bg-white -mx-6 px-6 -mt-6 rounded-b-2xl shadow-sm">
                <div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight">User Login Management</h2>
                    <p class="text-sm text-gray-500 font-medium">Manage access credentials for staff, students, and parents.</p>
                </div>
                <!-- Quick action: Create missing logins -->
                <div class="flex flex-wrap gap-2 items-center">
                    <span v-if="missing_counts.parents > 0 || missing_counts.students > 0"
                          class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-full">
                        Missing: {{ missing_counts.parents }} parents · {{ missing_counts.students }} students
                    </span>
                    <Button size="sm" :disabled="bulkBusy" @click="createMissing('all')">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Create Missing Logins
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Search Users</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input v-model="search" type="text" placeholder="Name, Username, or Phone..."
                               class="pl-10.5 w-full rounded-xl border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm transition-all h-11 py-2">
                    </div>
                </div>

                <div class="w-48">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">User Type</label>
                    <select v-model="userType" class="w-full rounded-xl border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm h-11 py-2">
                        <option value="">All Staff</option>
                        <option value="teacher">Teachers Only</option>
                        <option value="student">Students</option>
                        <option value="parent">Parents</option>
                        <option value="accountant">Accountant</option>
                        <option value="driver">Drivers</option>
                    </select>
                </div>

                <div v-if="showClassFilter" class="w-48">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Class</label>
                    <select v-model="classId" class="w-full rounded-xl border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm h-11 py-2">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div v-if="showClassFilter" class="w-48">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Section</label>
                    <select v-model="sectionId" :disabled="!classId" class="w-full rounded-xl border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm h-11 py-2 disabled:bg-gray-100 disabled:text-gray-400">
                        <option value="">{{ classId ? 'All Sections' : 'Pick a class first' }}</option>
                        <option v-for="s in sectionsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <div class="w-40">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Status</label>
                    <select v-model="status" class="w-full rounded-xl border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm h-11 py-2">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Locked</option>
                    </select>
                </div>

                <Button variant="secondary" @click="search=''; userType=''; status=''; classId=''; sectionId=''" class="h-11">
                    Reset Filters
                </Button>
            </div>

            <!-- Bulk action bar (sticky-ish, shown when something is selected) -->
            <div v-if="selectedIds.size > 0"
                 class="bg-indigo-50 border border-indigo-200 rounded-2xl px-5 py-3 flex flex-wrap items-center gap-3">
                <span class="text-sm font-bold text-indigo-900">
                    {{ selectedIds.size }} selected
                </span>
                <button @click="clearSelection" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                    Clear
                </button>
                <div class="ml-auto flex flex-wrap gap-2">
                    <Button size="sm" :disabled="bulkBusy" @click="bulkReset">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        Bulk Reset Passwords
                    </Button>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-4 py-4 w-10">
                                    <input type="checkbox" :checked="allSelected" @change="toggleAll"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                </th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Name &amp; Profile</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">System Role</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Username</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Access Status</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Manage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="user in users.data" :key="user.id"
                                class="hover:bg-indigo-50/20 transition-colors group"
                                :class="{ 'bg-indigo-50/40': isSelected(user.id) }">
                                <td class="px-4 py-4 w-10">
                                    <input type="checkbox" :checked="isSelected(user.id)" @change="toggleOne(user.id)"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-black text-sm border-2 border-white shadow-sm flex-shrink-0">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-black text-gray-900">{{ user.name }}</div>
                                            <div class="text-xs text-gray-500 font-medium">{{ user.phone || user.email || '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-gray-100 text-gray-600 border border-gray-200">
                                        {{ getRoleLabel(user.user_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <code class="text-[11px] font-black text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md">{{ user.username }}</code>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <button @click="toggleAccess(user)"
                                            class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider border transition-all hover:shadow-sm"
                                            :class="getStatusBadgeClass(user.is_active)">
                                        {{ user.is_active ? 'Active' : 'Locked' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <Button v-if="$page.props.auth.user.id !== user.id && can('impersonate_users')"
                                            @click="impersonateUser(user)" title="Login as this user" size="xs">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                        Login As
                                    </Button>
                                    <Button @click="handleResetPassword(user)" size="xs">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                                        Reset
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-4 text-gray-300">
                                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                        </div>
                                        <p class="text-sm font-bold text-gray-900">No users found</p>
                                        <p class="text-xs text-gray-500 mt-1">Try adjusting your filters or search query.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="users.links.length > 3" class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">
                        Showing {{ users.from }} - {{ users.to }} of {{ users.total }}
                    </p>
                    <div class="flex gap-1">
                        <template v-for="(link, k) in users.links" :key="k">
                            <Link v-if="link.url" :href="link.url" v-html="link.label"
                                  class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border"
                                  :class="link.active
                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-100'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-400'" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single-user reset modal (existing) -->
        <div v-if="resetModalUser" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-white/20 animate-in fade-in zoom-in duration-300">
                <div class="p-8 text-center">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 scale-110">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 mb-2">Password Reset Successful</h3>
                    <p class="text-sm text-gray-500 font-medium mb-8">Generated new password for <strong>{{ resetModalUser.name }}</strong></p>

                    <div class="bg-gray-50 rounded-2xl p-6 border-2 border-dashed border-gray-200 relative group mb-8">
                        <label class="absolute -top-3 left-4 px-2 bg-white text-[10px] font-black text-indigo-500 uppercase tracking-widest">New Credentials</label>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500 font-medium">Username:</span>
                                <span class="font-black text-gray-900 font-mono">{{ resetModalUser.username }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500 font-medium">Password:</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-emerald-600 font-mono text-lg tracking-wider">{{ generatedPassword }}</span>
                                    <button @click="copyToClipboard(generatedPassword)" class="p-1.5 text-gray-400 hover:text-indigo-600 transition-colors" title="Copy password">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button @click="resetModalUser = null" class="w-full h-12 bg-indigo-600 text-white rounded-2xl font-black text-sm hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all active:scale-95">
                        Done
                    </button>
                </div>
                <div class="p-4 bg-amber-50 border-t border-amber-100">
                    <p class="text-[10px] text-amber-800 font-bold leading-relaxed flex items-start gap-2">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        IMPORTANT: This password is shown only once. Please share it with the user immediately.
                    </p>
                </div>
            </div>
        </div>

        <!-- Bulk credentials modal (after createMissing or bulkReset) -->
        <div v-if="bulkModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden border border-white/20">
                <div class="p-6 border-b border-gray-100 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-black text-gray-900">{{ bulkTitle }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Export now — passwords are shown only once.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" variant="secondary" @click="exportCredentials('xlsx')">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                            Excel
                        </Button>
                        <Button size="sm" variant="secondary" @click="exportCredentials('pdf')">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            PDF
                        </Button>
                    </div>
                </div>

                <div class="max-h-[60vh] overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-5 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Name</th>
                                <th class="px-5 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Role</th>
                                <th class="px-5 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Username</th>
                                <th class="px-5 py-3 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in bulkRows" :key="i" class="border-t border-gray-100">
                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ r.name }}</td>
                                <td class="px-5 py-2.5 text-gray-500">{{ r.role }}</td>
                                <td class="px-5 py-2.5 font-mono font-bold text-indigo-600">{{ r.username }}</td>
                                <td class="px-5 py-2.5 font-mono font-bold text-emerald-600">{{ r.password }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-amber-50 border-t border-amber-100 flex justify-between items-center">
                    <p class="text-[10px] text-amber-800 font-bold">
                        IMPORTANT: Distribute via secure channel. This list is shown only once.
                    </p>
                    <Button size="sm" @click="closeBulkModal">Done</Button>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>
