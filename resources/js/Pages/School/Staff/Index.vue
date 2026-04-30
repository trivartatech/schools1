<script setup>
import Button from '@/Components/ui/Button.vue';
import StatsRow from '@/Components/ui/StatsRow.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref, reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import ExportDropdown from '@/Components/ExportDropdown.vue';
import { useDelete } from '@/Composables/useDelete';
import { usePermissions } from '@/Composables/usePermissions';
import { useTableFilters } from '@/Composables/useTableFilters';
import Table from '@/Components/ui/Table.vue';
import SortableTh from '@/Components/ui/SortableTh.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const { canDo } = usePermissions();

const props = defineProps({
    staff: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || 'current',
    sort:   props.filters?.sort   || '',
    dir:    props.filters?.dir    || 'asc',
});

const { navigate } = useTableFilters('/school/staff', filters);
watch(filters, navigate);

const setStatus = (s) => { filters.status = s; };

function toggleSort(key) {
    if (filters.sort === key) {
        filters.dir = filters.dir === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = key;
        filters.dir  = 'asc';
    }
}

const { del } = useDelete();
const deleteStaff = (id, name) => del(`/school/staff/${id}`, `Delete staff member "${name}"? This action cannot be undone.`);
</script>

<template>
    <SchoolLayout title="Staff Directory">

        <!-- Page Header -->
        <PageHeader title="Staff Directory" subtitle="Manage all school employees and their information">
            <template #actions>
                <ExportDropdown
                    base-url="/school/export/staff"
                    :params="{ search: filters.search, status: filters.status }"
                />
                <!-- Staff QR badges — print/sheet for the whole roster -->
                <Button variant="secondary" size="sm" as="a" href="/school/staff/qr-codes/pdf" target="_blank">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-9h-1M5 12H4m11.314-6.314l-.707.707M6.393 17.607l-.707.707M17.607 17.607l-.707-.707M6.393 6.393l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    QR Badges PDF
                </Button>
                <Button variant="secondary" size="sm" as="a" href="/school/staff/qr-codes/excel">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    QR Excel
                </Button>
                <Button variant="secondary" size="sm" as="link" v-if="canDo('create', 'staff')" href="/school/bulk-import?type=staff">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Bulk Import
                </Button>
                <Button v-if="canDo('create', 'staff')" as="link" href="/school/staff/create">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add New Staff
                </Button>

            </template>
        </PageHeader>

        <!-- Stats Row -->
        <StatsRow :cols="4" :stats="[
            { label: 'Total Staff', value: staff.total ?? staff.data.length },
            { label: 'Active', value: staff.data.filter(m => m.status === 'active').length, color: 'success' },
            { label: 'On Leave', value: staff.data.filter(m => m.status === 'on_leave').length, color: 'warning' },
            { label: 'Inactive / Other', value: staff.data.filter(m => m.status !== 'active' && m.status !== 'on_leave').length, color: 'danger' },
        ]" />

        <!-- Filters -->
        <FilterBar :active="!!filters.search" @clear="filters.search = ''">
            <div class="fb-search fb-grow">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="filters.search" type="text" placeholder="Search by name, employee ID, phone...">
            </div>
            <div class="status-toggle">
                <button @click="setStatus('current')" class="toggle-btn" :class="{ active: filters.status === 'current' }">Current Staff</button>
                <button @click="setStatus('past')" class="toggle-btn" :class="{ active: filters.status === 'past' }">Past Staff</button>
            </div>
        </FilterBar>

        <!-- Empty State -->
        <EmptyState
            v-if="staff.data.length === 0"
            title="No Staff Members Found"
            description="Add your first employee to the directory."
        >
            <template #action>
                <Button v-if="canDo('create', 'staff')" as="link" href="/school/staff/create">Add New Staff</Button>
            </template>
        </EmptyState>

        <!-- Staff Table -->
        <div v-else class="card" style="overflow:hidden;">
            <Table :sort-key="filters.sort" :sort-dir="filters.dir" @sort="toggleSort">
                <thead>
                    <tr>
                        <SortableTh sort-key="name">Staff Member</SortableTh>
                        <SortableTh sort-key="employee_id">Employee ID</SortableTh>
                        <SortableTh sort-key="department">Department / Designation</SortableTh>
                        <SortableTh sort-key="joining_date">Joined</SortableTh>
                        <SortableTh sort-key="status">Status</SortableTh>
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
                            {{ member.joining_date ? school.fmtDate(member.joining_date) : '—' }}
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
