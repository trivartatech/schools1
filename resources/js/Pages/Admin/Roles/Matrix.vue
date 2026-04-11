<template>
    <Head title="Role & Permission Matrix" />

    <SchoolLayout>

        <!-- Header -->
        <div class="mb-6 flex flex-wrap gap-3 justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Access Control Matrix</h2>
                <p class="mt-1 text-sm text-gray-500">Manage what each role can see and do across the ERP.</p>
            </div>
            <Button @click="showAddModal = true">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add New Role
            </Button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-5 border-b border-gray-200">
            <button
                v-for="tab in tabs" :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                    'px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors',
                    activeTab === tab.id
                        ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50',
                ]"
            >{{ tab.label }}</button>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- TAB 1: Role Permission Matrix                                     -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <template v-if="activeTab === 'matrix'">

            <!-- Search + Filter bar -->
            <div class="mb-4 flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-48">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search permissions..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                    >
                </div>
                <select
                    v-model="moduleFilter"
                    class="py-2 pl-3 pr-8 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                >
                    <option value="">All Modules</option>
                    <option v-for="mod in moduleList" :key="mod" :value="mod">{{ mod }}</option>
                </select>
                <span class="text-xs text-gray-400">
                    Showing {{ filteredCount }} of {{ totalPermissions }} permissions
                </span>
            </div>

            <!-- Matrix Table -->
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border-collapse">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="sticky left-0 z-10 bg-gray-50 px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)] w-72">
                                    Module / Permission
                                </th>
                                <th v-for="role in roles" :key="role.id" scope="col" class="px-4 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider min-w-[140px] border-r border-gray-100 last:border-r-0">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="truncate block w-full text-center font-semibold" :title="role.label || formatName(role.name)">
                                            {{ role.label || formatName(role.name) }}
                                        </span>
                                        <span v-if="role.description" class="text-[10px] text-gray-400 font-normal normal-case leading-tight text-center line-clamp-2 max-w-[120px]" :title="role.description">
                                            {{ role.description }}
                                        </span>
                                        <Button variant="secondary" @click="toggleRole(role)" class="text-[10px] mt-1">
                                            {{ hasAllPermissions(role) ? 'Deselect All' : 'Select All' }}
                                        </Button>
                                        <button v-if="!isSystemRole(role.name)" @click="confirmDeleteRole(role)" class="text-[10px] text-red-500 hover:text-red-700 transition-colors mt-0.5">
                                            ✕ Delete
                                        </button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="(perms, moduleName) in visiblePermissions" :key="moduleName">
                                <tr class="bg-gray-50/50">
                                    <td :colspan="roles.length + 1" class="px-6 py-3 text-sm font-bold text-gray-900 border-b border-gray-200 uppercase tracking-wide bg-gray-100/50 sticky left-0">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                            {{ moduleName }}
                                            <span class="text-xs font-normal text-gray-400">({{ perms.length }})</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="permission in perms" :key="permission.id" class="hover:bg-indigo-50/30 transition-colors">
                                    <td class="sticky left-0 z-10 bg-white px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-700 border-r border-gray-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)] w-72">
                                        <div class="flex flex-col">
                                            <span>{{ formatAction(permission.name) }}</span>
                                            <span class="text-xs text-gray-400 font-normal font-mono">{{ permission.name }}</span>
                                        </div>
                                    </td>
                                    <td v-for="role in roles" :key="role.id" class="px-4 py-3 whitespace-nowrap text-center border-r border-gray-50 last:border-r-0">
                                        <label class="inline-flex items-center cursor-pointer justify-center w-full h-full">
                                            <input
                                                type="checkbox"
                                                class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50"
                                                :checked="hasPermission(role.id, permission.id)"
                                                @change="updatePermission(role, permission, $event.target.checked)"
                                                :disabled="updatingRole === role.id"
                                            >
                                        </label>
                                    </td>
                                </tr>
                            </template>
                            <!-- Empty state -->
                            <tr v-if="filteredCount === 0">
                                <td :colspan="roles.length + 1" class="px-6 py-10 text-center text-sm text-gray-400">
                                    No permissions match your search.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer stats -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        <span class="font-medium text-gray-900">{{ roles.length }}</span> roles ·
                        <span class="font-medium text-gray-900">{{ totalPermissions }}</span> permissions across
                        <span class="font-medium text-gray-900">{{ Object.keys(groupedPermissions).length }}</span> modules
                    </div>
                    <span v-if="successMessage" class="text-emerald-600 text-sm font-medium flex items-center bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Saved!
                    </span>
                </div>
            </div>
        </template>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- TAB 2: User Permission Overrides                                  -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <template v-else-if="activeTab === 'overrides'">
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-6">
                <p class="text-sm text-gray-500 mb-5">
                    Grant or revoke specific permissions for a single user, overriding their role's defaults.
                    These are in addition to (or removed from) their role permissions.
                </p>

                <!-- User selector -->
                <div class="flex flex-wrap gap-4 mb-6">
                    <div class="flex-1 min-w-64">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Select User</label>
                        <select
                            v-model="overrideUserId"
                            class="w-full py-2 pl-3 pr-8 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                        >
                            <option :value="null">— choose a user —</option>
                            <option v-for="u in schoolUsers" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.email }}) · {{ formatName(u.user_type) }}
                            </option>
                        </select>
                    </div>
                    <div v-if="overrideUserId" class="flex items-end gap-2">
                        <Button @click="saveOverrides('assign')" :loading="overrideLoading">
                            Assign Selected
                        </Button>
                        <Button variant="danger" @click="saveOverrides('revoke')" :loading="overrideLoading">
                            Revoke Selected
                        </Button>
                    </div>
                </div>

                <!-- Permission list (grouped) with override checkboxes -->
                <template v-if="overrideUserId">
                    <div class="mb-3 flex gap-3 items-center">
                        <div class="relative flex-1 max-w-sm">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input v-model="overrideSearch" type="text" placeholder="Filter permissions..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white">
                        </div>
                        <span class="text-xs text-gray-400">
                            <span class="font-semibold text-indigo-600">{{ selectedUserDirectPerms.length }}</span> direct overrides active
                        </span>
                    </div>

                    <div class="space-y-5 max-h-[60vh] overflow-y-auto pr-1">
                        <template v-for="(perms, moduleName) in filteredOverridePermissions" :key="moduleName">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                    <span class="text-xs font-bold text-gray-700 uppercase tracking-wide">{{ moduleName }}</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 pl-4">
                                    <label
                                        v-for="perm in perms" :key="perm.id"
                                        class="flex items-center gap-2 cursor-pointer py-1.5 px-2 rounded-lg hover:bg-gray-50 transition-colors"
                                        :class="{ 'bg-indigo-50 ring-1 ring-indigo-200': isUserDirectPerm(perm.name) }"
                                    >
                                        <input
                                            type="checkbox"
                                            class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                            :value="perm.name"
                                            v-model="overrideSelected"
                                        >
                                        <span class="text-xs text-gray-700 leading-tight">
                                            {{ formatAction(perm.name) }}
                                            <span v-if="isUserDirectPerm(perm.name)" class="ml-1 text-[9px] text-indigo-500 font-semibold uppercase">override</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div v-if="overrideSuccessMessage" class="mt-4 flex items-center gap-2 text-emerald-600 text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        User permissions updated successfully.
                    </div>
                </template>
                <div v-else class="text-sm text-gray-400 py-8 text-center">
                    Select a user above to view and manage their individual permission overrides.
                </div>
            </div>
        </template>

        <!-- ── Add Role Modal ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="closeAddModal">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 animate-modal">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Add New Role</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Create a custom role, then assign permissions from the matrix.</p>
                        </div>
                        <button @click="closeAddModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitAddRole" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role Name <span class="text-red-500">*</span></label>
                            <input v-model="addForm.label" @input="syncSlug" type="text" placeholder="e.g. Class Teacher"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                required id="role-name-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">System Slug <span class="text-gray-400 font-normal">(auto-generated)</span></label>
                            <input v-model="addForm.name" type="text" placeholder="class_teacher"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                required pattern="^[a-z0-9_]+$">
                            <p class="mt-1 text-xs text-gray-400">Lowercase letters, numbers and underscores only.</p>
                            <p v-if="addForm.errors?.name" class="mt-1 text-xs text-red-500">{{ addForm.errors.name }}</p>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <Button variant="secondary" type="button" @click="closeAddModal">Cancel</Button>
                            <Button type="submit" :loading="addForm.processing">
                                {{ addForm.processing ? 'Creating...' : 'Create Role' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Delete Confirm Modal ───────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="deleteTarget = null">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 animate-modal">
                    <div class="flex flex-col items-center text-center gap-3 mb-5">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Delete "{{ deleteTarget?.label || formatName(deleteTarget?.name) }}" role?</h3>
                            <p class="text-sm text-gray-500 mt-1">This will remove all permissions assigned to this role. Users with this role will lose access immediately.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <Button variant="secondary" @click="deleteTarget = null">Cancel</Button>
                        <Button variant="danger" @click="executeDelete">Yes, Delete</Button>
                    </div>
                </div>
            </div>
        </Teleport>

    </SchoolLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import Button from '@/Components/ui/Button.vue'
import { useToast } from '@/Composables/useToast'

const toast = useToast()

const props = defineProps({
    roles:                 Array,
    groupedPermissions:    Object,
    rolePermissions:       Array,
    matrix:                Object,  // { modules, roles } from PermissionService::getMatrix()
    schoolUsers:           Array,
    userDirectPermissions: Object,  // { userId: ['perm1', 'perm2', ...] }
})

// ── Tabs ─────────────────────────────────────────────────────────────────────
const tabs      = [
    { id: 'matrix',    label: 'Role Matrix' },
    { id: 'overrides', label: 'User Overrides' },
]
const activeTab = ref('matrix')

// ── System roles that cannot be deleted ──────────────────────────────────────
const SYSTEM_ROLES = [
    'teacher', 'student', 'parent',
    'super_admin', 'admin', 'school_admin', 'principal', 'manager', 'hr',
    'accountant', 'receptionist', 'front_office', 'it_support',
    'driver', 'conductor', 'transport_manager',
    'hostel_warden', 'mess_manager', 'maintenance',
    'librarian', 'nurse', 'auditor',
]
const isSystemRole = (name) => SYSTEM_ROLES.includes(name)

// ── Matrix state ──────────────────────────────────────────────────────────────
const updatingRole   = ref(null)
const successMessage = ref(false)
const search         = ref('')
const moduleFilter   = ref('')

const totalPermissions = computed(() => {
    let count = 0
    for (const key in props.groupedPermissions) count += props.groupedPermissions[key].length
    return count
})

const moduleList = computed(() => Object.keys(props.groupedPermissions).sort())

/** Filter permissions by search + module */
const visiblePermissions = computed(() => {
    const q   = search.value.trim().toLowerCase()
    const mod = moduleFilter.value

    const result = {}
    for (const [moduleName, perms] of Object.entries(props.groupedPermissions)) {
        if (mod && moduleName !== mod) continue
        const filtered = perms.filter(p =>
            !q || p.name.includes(q) || (p.label ?? '').toLowerCase().includes(q)
        )
        if (filtered.length) result[moduleName] = filtered
    }
    return result
})

const filteredCount = computed(() => {
    let n = 0
    for (const perms of Object.values(visiblePermissions.value)) n += perms.length
    return n
})

const hasPermission = (roleId, permissionId) =>
    props.rolePermissions.some(rp => rp.role_id === roleId && rp.permission_id === permissionId)

const formatName   = (str) => (str ?? '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
const formatAction = (str) => {
    if (str.startsWith('request_edit_')) return 'Request Profile Edit'
    const parts = str.split('_')
    const verb  = parts[0]
    return verb.charAt(0).toUpperCase() + verb.slice(1)
}

const updatePermission = (role, permission, isChecked) => {
    updatingRole.value = role.id
    let currentPerms = props.rolePermissions
        .filter(rp => rp.role_id === role.id)
        .map(rp => {
            for (const module in props.groupedPermissions) {
                const p = props.groupedPermissions[module].find(x => x.id === rp.permission_id)
                if (p) return p.name
            }
        }).filter(Boolean)

    if (isChecked) { if (!currentPerms.includes(permission.name)) currentPerms.push(permission.name) }
    else           { currentPerms = currentPerms.filter(n => n !== permission.name) }

    router.post(route('school.roles.update'), { role_id: role.id, permissions: currentPerms }, {
        preserveScroll: true,
        onSuccess: () => { updatingRole.value = null; successMessage.value = true; setTimeout(() => successMessage.value = false, 3000) },
        onError:   () => { updatingRole.value = null; toast.error('Failed to update permissions.') },
    })
}

const hasAllPermissions = (role) => {
    const rolePerms = props.rolePermissions.filter(rp => rp.role_id === role.id).length
    return rolePerms === totalPermissions.value && totalPermissions.value > 0
}

const toggleRole = (role) => {
    updatingRole.value = role.id
    let targetPerms = []
    if (!hasAllPermissions(role)) {
        for (const module in props.groupedPermissions) props.groupedPermissions[module].forEach(p => targetPerms.push(p.name))
    }
    router.post(route('school.roles.update'), { role_id: role.id, permissions: targetPerms }, {
        preserveScroll: true,
        onSuccess: () => { updatingRole.value = null; successMessage.value = true; setTimeout(() => successMessage.value = false, 3000) },
        onError:   () => { updatingRole.value = null },
    })
}

// ── Add Role ──────────────────────────────────────────────────────────────────
const showAddModal = ref(false)
const addForm = useForm({ name: '', label: '' })

const syncSlug = () => {
    addForm.name = addForm.label
        .toLowerCase()
        .replace(/\s+/g, '_')
        .replace(/[^a-z0-9_]/g, '')
}

const closeAddModal = () => {
    showAddModal.value = false
    addForm.reset()
    addForm.clearErrors()
}

const submitAddRole = () => {
    addForm.post(route('school.roles.store'), {
        preserveScroll: true,
        onSuccess: () => closeAddModal(),
    })
}

// ── Delete Role ───────────────────────────────────────────────────────────────
const deleteTarget = ref(null)
const confirmDeleteRole = (role) => { deleteTarget.value = role }
const executeDelete = () => {
    router.delete(route('school.roles.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null },
    })
}

// ── User Overrides panel ──────────────────────────────────────────────────────
const overrideUserId       = ref(null)
const overrideSelected     = ref([])
const overrideSearch       = ref('')
const overrideLoading      = ref(false)
const overrideSuccessMessage = ref(false)

// When the selected user changes, pre-tick their existing direct permissions
watch(overrideUserId, (uid) => {
    overrideSelected.value = uid
        ? [...(props.userDirectPermissions?.[uid] ?? [])]
        : []
})

const selectedUserDirectPerms = computed(() =>
    overrideUserId.value ? (props.userDirectPermissions?.[overrideUserId.value] ?? []) : []
)

const isUserDirectPerm = (name) => selectedUserDirectPerms.value.includes(name)

const filteredOverridePermissions = computed(() => {
    const q = overrideSearch.value.trim().toLowerCase()
    if (!q) return props.groupedPermissions
    const result = {}
    for (const [mod, perms] of Object.entries(props.groupedPermissions)) {
        const filtered = perms.filter(p => p.name.includes(q))
        if (filtered.length) result[mod] = filtered
    }
    return result
})

const saveOverrides = (action) => {
    if (!overrideUserId.value || overrideSelected.value.length === 0) {
        toast.error('Select a user and at least one permission.')
        return
    }
    overrideLoading.value = true
    router.post(route('school.users.permissions.update'), {
        users:       [overrideUserId.value],
        permissions: overrideSelected.value,
        action,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            overrideLoading.value    = false
            overrideSuccessMessage.value = true
            setTimeout(() => overrideSuccessMessage.value = false, 3000)
        },
        onError: () => {
            overrideLoading.value = false
            toast.error('Failed to update user permissions.')
        },
    })
}
</script>

<style scoped>
.animate-modal {
    animation: modalIn 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.92) translateY(8px); }
    to   { opacity: 1; transform: scale(1)    translateY(0); }
}
</style>
