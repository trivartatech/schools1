<script setup>
import Button from '@/Components/ui/Button.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import { ref, reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import { useDelete } from '@/Composables/useDelete';
import { usePermissions } from '@/Composables/usePermissions';
import { useTableFilters } from '@/Composables/useTableFilters';
import Table from '@/Components/ui/Table.vue';

const { canDo } = usePermissions();

const props = defineProps({
    staff: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || 'current',
});

const { navigate } = useTableFilters('/school/staff', filters);
watch(filters, navigate);

const setStatus = (s) => { filters.status = s; };

const { del } = useDelete();
const deleteStaff = (id, name) => del(`/school/staff/${id}`, `Delete staff member "${name}"? This action cannot be undone.`);
</script>

<template>
    <SchoolLayout title="Staff Directory">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Staff Directory</h1>
                <p class="page-header-sub">Manage all school employees and their information</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <ExportDropdown
                    base-url="/school/export/staff"
                    :params="{ search: filters.search, status: filters.status }"
                />
                <Button variant="secondary" size="sm" as="link" v-if="canDo('create', 'staff')" href="/school/bulk-import?type=staff">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Bulk Import
                </Button>
                <Button v-if="canDo('create', 'staff')" as="link" href="/school/staff/create">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add New Staff
                </Button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#ede9fe;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ staff.total ?? staff.data.length }}</div>
                    <div class="stat-card-label">Total Staff</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#d1fae5;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#10b981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ staff.data.filter(m => m.status === 'active').length }}</div>
                    <div class="stat-card-label">Active</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#fef3c7;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#f59e0b"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ staff.data.filter(m => m.status === 'on_leave').length }}</div>
                    <div class="stat-card-label">On Leave</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#fee2e2;">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#ef4444"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
                <div>
                    <div class="stat-card-value">{{ staff.data.filter(m => m.status !== 'active' && m.status !== 'on_leave').length }}</div>
                    <div class="stat-card-label">Inactive / Other</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <FilterBar :active="!!filters.search" @clear="filters.search = ''">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="filters.search" type="text" placeholder="Search by name, employee ID, phone...">
            </div>
            <div class="status-toggle">
                <button @click="setStatus('current')" class="toggle-btn" :class="{ active: filters.status === 'current' }">Current Staff</button>
                <button @click="setStatus('past')" class="toggle-btn" :class="{ active: filters.status === 'past' }">Past Staff</button>
            </div>
        </FilterBar>

        <!-- Empty State -->
        <div v-if="staff.data.length === 0" class="card empty-state">
            <div class="empty-icon">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h3 class="empty-title">No Staff Members Found</h3>
            <p class="empty-sub">Add your first employee to the directory.</p>
            <Button v-if="canDo('create', 'staff')" as="link" href="/school/staff/create">Add New Staff</Button>
        </div>

        <!-- Staff Table -->
        <div v-else class="card" style="overflow:hidden;">
            <Table>
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Employee ID</th>
                        <th>Department / Designation</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="member in staff.data" :key="member.id">
                        <!-- Avatar + Name -->
                        <td>
                            <div class="staff-cell">
                                <div v-if="member.photo" class="avatar-photo">
                                    <img :src="`/storage/${member.photo}`" alt="Photo">
                                </div>
                                <div v-else class="avatar-initials">
                                    {{ member.user?.name?.substring(0, 2).toUpperCase() || 'NA' }}
                                </div>
                                <div class="staff-name-group">
                                    <span class="staff-name">{{ member.user?.name }}</span>
                                    <span class="staff-email">{{ member.user?.email }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="emp-id">{{ member.employee_id || '—' }}</span>
                        </td>
                        <td>
                            <div class="dept-desig">
                                <span class="dept-name">{{ member.department?.name || '—' }}</span>
                                <span class="desig-name">{{ member.designation?.name || '' }}</span>
                            </div>
                        </td>
                        <td style="color:#475569;font-size:0.825rem;">
                            {{ member.joining_date ? new Date(member.joining_date).toLocaleDateString('en-GB') : '—' }}
                        </td>
                        <td>
                            <span v-if="member.status === 'active'" class="badge badge-green">Active</span>
                            <span v-else-if="member.status === 'on_leave'" class="badge badge-amber">On Leave</span>
                            <span v-else-if="member.status === 'resigned'" class="badge badge-red">Resigned</span>
                            <span v-else-if="member.status === 'terminated'" class="badge badge-red">Terminated</span>
                            <span v-else class="badge badge-gray">{{ member.status }}</span>
                        </td>
                        <td>
                            <div class="action-group">
                                <Button variant="secondary" size="xs" as="link" :href="`/school/staff/${member.id}`">View</Button>
                                <Button variant="secondary" size="xs" as="link" :href="`/school/staff/${member.id}/edit`">Edit</Button>
                                <Button variant="danger" size="xs" @click="deleteStaff(member.id, member.user?.name)">Delete</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>

        <!-- Pagination -->
        <div v-if="staff.links && staff.links.length > 3" class="pagination-wrap">
            <div class="pagination">
                <template v-for="(link, key) in staff.links" :key="key">
                    <span v-if="link.url === null" v-html="link.label" class="page-item disabled"></span>
                    <Link v-else :href="link.url" v-html="link.label"
                        class="page-item"
                        :class="{ 'page-item-active': link.active }" />
                </template>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
/* Stats row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 900px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .stats-row { grid-template-columns: 1fr; } }

/* Filter bar */
.filter-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.search-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
    max-width: 360px;
}
.search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}
.search-input {
    width: 100%;
    padding-left: 34px !important;
}
.status-toggle {
    display: flex;
    background: #f1f5f9;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 3px;
    gap: 2px;
}
.toggle-btn {
    padding: 5px 14px;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s;
}
.toggle-btn.active {
    background: #fff;
    color: var(--accent);
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

/* Staff table cells */
.staff-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.avatar-photo {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid #e0e7ff;
}
.avatar-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-initials {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--accent), #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: 0.03em;
}
.staff-name-group {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.staff-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
}
.staff-email {
    font-size: 0.75rem;
    color: #94a3b8;
}
.emp-id {
    font-family: monospace;
    font-size: 0.8rem;
    font-weight: 600;
    color: #4f46e5;
    background: #ede9fe;
    padding: 2px 8px;
    border-radius: 5px;
}
.dept-desig {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.dept-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
}
.desig-name {
    font-size: 0.75rem;
    color: #94a3b8;
}
.action-group {
    display: flex;
    gap: 6px;
    align-items: center;
}

/* Empty state */
.empty-state {
    padding: 64px 24px;
    text-align: center;
}
.empty-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: #f5f3ff;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}
.empty-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 6px;
}
.empty-sub {
    font-size: 0.8125rem;
    color: #64748b;
    margin-bottom: 20px;
}

/* Pagination */
.pagination-wrap {
    display: flex;
    justify-content: center;
    margin-top: 24px;
}
.pagination {
    display: flex;
    gap: 4px;
    background: #fff;
    padding: 4px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.page-item {
    padding: 6px 12px;
    font-size: 0.8125rem;
    font-weight: 500;
    border-radius: 7px;
    transition: all 0.15s;
    text-decoration: none;
    color: #374151;
    cursor: pointer;
    border: none;
    background: transparent;
}
.page-item.disabled {
    color: #94a3b8;
    cursor: default;
}
.page-item-active {
    background: var(--accent) !important;
    color: #fff !important;
}
</style>
