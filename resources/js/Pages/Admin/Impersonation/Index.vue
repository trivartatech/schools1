<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    logs:  { type: Array, default: () => [] },
});

const page = usePage();

// ── Filters ────────────────────────────────────────────────────────────
const searchQuery = ref('');
const filterType  = ref('');
const activeTab   = ref('users'); // 'users' | 'logs'

const USER_TYPE_LABELS = {
    super_admin:  'Super Admin',
    admin:        'Admin',
    school_admin: 'School Admin',
    principal:    'Principal',
    accountant:   'Accountant',
    teacher:      'Teacher',
    driver:       'Driver',
    parent:       'Parent',
    student:      'Student',
};

const USER_TYPE_COLORS = {
    super_admin:  { bg: '#fef3c7', text: '#92400e', dot: '#f59e0b' },
    admin:        { bg: '#dbeafe', text: '#1e40af', dot: '#3b82f6' },
    school_admin: { bg: '#ede9fe', text: '#5b21b6', dot: '#8b5cf6' },
    principal:    { bg: '#d1fae5', text: '#065f46', dot: '#10b981' },
    accountant:   { bg: '#fce7f3', text: '#9d174d', dot: '#ec4899' },
    teacher:      { bg: '#e0f2fe', text: '#0c4a6e', dot: '#0ea5e9' },
    driver:       { bg: '#f0fdf4', text: '#166534', dot: '#22c55e' },
    parent:       { bg: '#fff7ed', text: '#7c2d12', dot: '#f97316' },
    student:      { bg: '#fdf4ff', text: '#6b21a8', dot: '#a855f7' },
};

const availableTypes = computed(() => {
    const types = [...new Set(props.users.map(u => u.user_type))].sort();
    return types;
});

const filteredUsers = computed(() => {
    let list = props.users;
    if (filterType.value) {
        list = list.filter(u => u.user_type === filterType.value);
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(u =>
            u.name.toLowerCase().includes(q) ||
            u.email?.toLowerCase().includes(q) ||
            u.user_type.toLowerCase().includes(q)
        );
    }
    return list;
});

// ── Impersonation ──────────────────────────────────────────────────────
const impersonatingId = ref(null);
const confirmTarget   = ref(null);

const startImpersonate = (user) => {
    confirmTarget.value = user;
};

const confirmImpersonate = () => {
    if (!confirmTarget.value) return;
    impersonatingId.value = confirmTarget.value.id;
    router.post(`/impersonate/${confirmTarget.value.id}`, {}, {
        onFinish: () => {
            impersonatingId.value = null;
            confirmTarget.value   = null;
        },
    });
};

const cancelConfirm = () => { confirmTarget.value = null; };

const showConfirmModal = computed({
    get: () => confirmTarget.value !== null,
    set: (v) => { if (!v) confirmTarget.value = null; },
});

const tabsConfig = computed(() => [
    { key: 'users', label: 'Users',     count: props.users.length },
    { key: 'logs',  label: 'Audit Log', count: props.logs.length },
]);

const colorFor = (type) => USER_TYPE_COLORS[type] ?? { bg: '#f1f5f9', text: '#475569', dot: '#94a3b8' };
const labelFor = (type) => USER_TYPE_LABELS[type] ?? type;

const formatDuration = (minutes) => {
    if (minutes === null || minutes === undefined) return '—';
    if (minutes < 1) return '< 1 min';
    if (minutes < 60) return `${minutes} min`;
    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
};
</script>

<template>
    <Head title="User Management" />
    <SchoolLayout title="User Management">

        <!-- Confirmation Modal -->
        <Modal v-model:open="showConfirmModal" title="Confirm Impersonation" size="sm">
            <div v-if="confirmTarget" style="text-align:center;">
                <div class="confirm-icon-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <p class="confirm-desc">
                    You are about to log in as
                    <strong>{{ confirmTarget.name }}</strong>
                    (<span :style="{ color: colorFor(confirmTarget.user_type).dot }">{{ labelFor(confirmTarget.user_type) }}</span>).
                    <br><br>
                    All actions performed during this session will be associated with that user's account.
                    This action will be <strong>logged</strong>.
                </p>
            </div>
            <template #footer>
                <Button variant="secondary" @click="cancelConfirm" id="cancel-impersonate-btn">Cancel</Button>
                <Button
                    class="btn-impersonate"
                    :loading="impersonatingId !== null"
                    :disabled="impersonatingId !== null"
                    @click="confirmImpersonate"
                    id="confirm-impersonate-btn">
                    {{ impersonatingId ? 'Logging in…' : 'Yes, Login as User' }}
                </Button>
            </template>
        </Modal>

        <!-- Page Header -->
        <PageHeader>
            <template #title>
                <div class="page-header-row">
                    <div class="page-header-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <h2 class="page-header-title">User Management</h2>
                </div>
            </template>
            <template #subtitle>
                <p class="page-header-sub" style="margin-left:62px;">Impersonate users to troubleshoot issues on their behalf. All actions are audited.</p>
            </template>
            <template #actions>
                <div class="stat-pill">
                    <span class="stat-dot dot-green"></span>
                    {{ users.length }} users available
                </div>
                <div class="stat-pill">
                    <span class="stat-dot dot-purple"></span>
                    {{ logs.length }} sessions logged
                </div>
            </template>
        </PageHeader>

        <!-- Security Warning Card -->
        <div class="security-card">
            <div class="security-card-icon">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div class="security-card-body">
                <div class="security-card-title">Security Notice</div>
                <div class="security-card-text">
                    User impersonation is a privileged action. You can only impersonate users with roles below yours in the hierarchy.
                    Every session is logged with your IP address, timestamp, and duration. Sessions auto-expire after 60 minutes.
                </div>
            </div>
        </div>

        <Tabs v-model="activeTab" :tabs="tabsConfig">
            <!-- Users Tab -->
            <template #tab-users>
                <!-- Filters -->
                <FilterBar :active="!!(searchQuery || filterType)" @clear="searchQuery = ''; filterType = ''">
                    <div class="fb-search">
                        <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search by name, email, or role…"
                            id="user-search-input" />
                    </div>
                    <select v-model="filterType" id="user-type-filter" style="width:160px;">
                        <option value="">All Roles</option>
                        <option v-for="t in availableTypes" :key="t" :value="t">{{ labelFor(t) }}</option>
                    </select>
                </FilterBar>

                <!-- User Grid -->
                <div v-if="filteredUsers.length > 0" class="user-grid">
                    <div
                        v-for="user in filteredUsers"
                        :key="user.id"
                        class="user-card"
                        :class="{ 'user-card--inactive': !user.is_active }">
                        <div class="user-card-top">
                            <div class="user-avatar-lg" :style="{ background: `linear-gradient(135deg, ${colorFor(user.user_type).dot}, ${colorFor(user.user_type).dot}88)` }">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="user-card-info">
                                <div class="user-card-name">{{ user.name }}</div>
                                <div class="user-card-email">{{ user.email || '—' }}</div>
                                <div class="user-badge-row">
                                    <span
                                        class="role-badge"
                                        :style="{ background: colorFor(user.user_type).bg, color: colorFor(user.user_type).text }">
                                        <span class="role-dot" :style="{ background: colorFor(user.user_type).dot }"></span>
                                        {{ labelFor(user.user_type) }}
                                    </span>
                                    <span v-if="!user.is_active" class="inactive-badge">Inactive</span>
                                </div>
                            </div>
                        </div>
                        <button
                            @click="startImpersonate(user)"
                            :disabled="!user.is_active || impersonatingId === user.id"
                            class="impersonate-btn"
                            :id="`impersonate-btn-${user.id}`">
                            <svg v-if="impersonatingId === user.id" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ impersonatingId === user.id ? 'Logging in…' : (user.is_active ? 'Login as User' : 'Inactive') }}
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <EmptyState v-else
                    title="No users found"
                    description="Try adjusting your search or filter." />
            </template>

            <!-- Audit Log Tab -->
            <template #tab-logs>
                <div v-if="logs.length > 0" class="logs-table-wrap">
                    <Table class="logs-table" striped>
                        <thead>
                            <tr>
                                <th>Impersonated User</th>
                                <th>Role</th>
                                <th>IP Address</th>
                                <th>Started At</th>
                                <th>Ended At</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs" :key="log.id" class="log-row">
                                <td class="log-name">{{ log.impersonated_name }}</td>
                                <td>
                                    <span class="role-badge sm"
                                        :style="{ background: colorFor(log.impersonated_type).bg, color: colorFor(log.impersonated_type).text }">
                                        {{ labelFor(log.impersonated_type) }}
                                    </span>
                                </td>
                                <td class="log-mono">{{ log.ip_address || '—' }}</td>
                                <td class="log-date">{{ log.started_at }}</td>
                                <td class="log-date">{{ log.ended_at || '—' }}</td>
                                <td class="log-mono">{{ formatDuration(log.duration_minutes) }}</td>
                                <td>
                                    <span v-if="log.ended_at" class="status-pill status-completed">Completed</span>
                                    <span v-else class="status-pill status-active">Active</span>
                                </td>
                            </tr>
                        </tbody>
                    </Table>
                </div>
                <EmptyState v-else
                    title="No impersonation sessions yet"
                    description="Sessions will appear here once you impersonate a user." />
            </template>
        </Tabs>

    </SchoolLayout>
</template>

<style scoped>
/* ── Page Header decorations ── */
.page-header-row {
    display: flex;
    align-items: center;
    gap: 14px;
}
.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
}
.page-header-icon svg { width: 24px; height: 24px; color: #fff; }
.stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
}
.stat-dot { width: 8px; height: 8px; border-radius: 50%; }
.dot-green { background: #10b981; }
.dot-purple { background: #8b5cf6; }

/* ── Security Card ── */
.security-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 18px;
    background: linear-gradient(135deg, #f5f3ff, #ede9fe);
    border: 1px solid #c4b5fd;
    border-radius: 12px;
    margin-bottom: 24px;
}
.security-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.security-card-icon svg { width: 18px; height: 18px; color: #fff; }
.security-card-title { font-size: 0.875rem; font-weight: 700; color: #4c1d95; margin-bottom: 3px; }
.security-card-text { font-size: 0.8rem; color: #5b21b6; line-height: 1.5; }

/* ── User Grid ── */
.user-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
}
.user-card {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    transition: all 0.2s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.user-card:hover {
    border-color: #c4b5fd;
    box-shadow: 0 4px 16px rgba(124, 58, 237, 0.12);
    transform: translateY(-1px);
}
.user-card--inactive { opacity: 0.6; }
.user-card-top { display: flex; align-items: center; gap: 12px; }
.user-avatar-lg {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.user-card-info { flex: 1; min-width: 0; }
.user-card-name { font-size: 0.9rem; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-card-email { font-size: 0.775rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 5px; }
.user-badge-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.role-badge.sm { font-size: 0.7rem; padding: 2px 7px; }
.role-dot { width: 6px; height: 6px; border-radius: 50%; }
.inactive-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    background: #fee2e2;
    color: #dc2626;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.impersonate-btn {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 14px;
    border-radius: 9px;
    border: none;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    color: #fff;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
}
.impersonate-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #6d28d9, #4c1d95);
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.45);
    transform: translateY(-1px);
}
.impersonate-btn:disabled {
    background: #94a3b8;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
}

/* ── Audit Logs Table ── */
.logs-table-wrap {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.log-name { font-weight: 600; color: #1e293b; }
.log-mono { font-family: 'Roboto Mono', monospace; font-size: 0.775rem; color: #64748b; }
.log-date { font-size: 0.775rem; color: #475569; }
.status-pill {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.status-completed { background: #d1fae5; color: #065f46; }
.status-active { background: #fde68a; color: #78350f; }

/* ── Confirm Modal accent ── */
.confirm-icon-wrap {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    box-shadow: 0 8px 24px rgba(124, 58, 237, 0.4);
}
.confirm-icon-wrap svg { width: 30px; height: 30px; color: #fff; }
.confirm-desc { font-size: 0.875rem; color: #475569; line-height: 1.6; margin-bottom: 8px; }
.confirm-desc strong { color: #1e293b; font-weight: 700; }
/* Purple gradient override for the confirm-impersonate button (composes with .ui-btn) */
.btn-impersonate {
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 3px 10px rgba(124, 58, 237, 0.4);
}
.btn-impersonate:hover:not(:disabled) {
    background: linear-gradient(135deg, #6d28d9, #4c1d95);
    box-shadow: 0 6px 18px rgba(124, 58, 237, 0.5);
    transform: translateY(-1px);
}
.btn-impersonate:disabled { background: #94a3b8; box-shadow: none; }
</style>
