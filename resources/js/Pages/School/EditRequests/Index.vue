<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';

const props = defineProps({
    editRequests: Object,
    filters: Object,
});

const currentStatus = ref(props.filters.status || 'pending');

const setStatus = (status) => {
    currentStatus.value = status;
    router.get('/school/edit-requests', { status }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Edit Requests" />
    <SchoolLayout title="Profile Edit Requests">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Profile Edit Requests</h1>
                <p class="page-header-sub">Review and approve profile change requests</p>
            </div>
        </div>

        <!-- Status Tabs -->
        <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;">
            <Button @click="setStatus('pending')"
                :variant="currentStatus === 'pending' ? 'primary' : 'secondary'">
                Pending
            </Button>
            <Button @click="setStatus('approved')"
                :variant="currentStatus === 'approved' ? 'primary' : 'secondary'">
                Approved
            </Button>
            <Button @click="setStatus('rejected')"
                :variant="currentStatus === 'rejected' ? 'primary' : 'secondary'">
                Rejected
            </Button>
        </div>

        <!-- Empty State -->
        <div v-if="editRequests.data.length === 0" class="card">
            <div class="card-body" style="text-align:center;padding:3rem;">
                <div style="font-size:2.5rem;margin-bottom:1rem;">📋</div>
                <h3 style="font-size:1.125rem;font-weight:500;color:#111827;margin-bottom:0.25rem;">No requests found</h3>
                <p style="color:#6b7280;font-size:0.875rem;">There are currently no {{ currentStatus }} edit requests.</p>
            </div>
        </div>

        <!-- Table -->
        <div v-else class="card">
            <div class="card-body" style="padding:0;">
                <Table>
                    <thead>
                        <tr>
                            <th>Requested By</th>
                            <th>Role Type</th>
                            <th>Submitted On</th>
                            <th>Fields Affected</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="req in editRequests.data" :key="req.id">
                            <td>
                                <div style="font-weight:500;color:#111827;">{{ req.user?.name || 'Unknown User' }}</div>
                                <div style="font-size:0.75rem;color:#6b7280;">{{ req.user?.email || req.user?.phone }}</div>
                            </td>
                            <td>
                                <span :class="req.requestable_type.includes('Student') ? 'badge badge-indigo' : 'badge badge-green'">
                                    {{ req.requestable_type.includes('Student') ? 'Student' : 'Staff' }}
                                </span>
                            </td>
                            <td>{{ new Date(req.created_at).toLocaleDateString('en-GB') }}</td>
                            <td>
                                <div style="display:flex;flex-wrap:wrap;gap:0.25rem;max-width:12.5rem;">
                                    <span v-for="(val, key) in req.requested_changes" :key="key"
                                        class="badge badge-gray" style="font-family:monospace;font-size:0.65rem;">
                                        {{ key }}
                                    </span>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <Button variant="secondary" size="sm" as="link" :href="`/school/edit-requests/${req.id}`">
                                    Review
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="editRequests.links && editRequests.links.length > 3" style="display:flex;justify-content:center;margin-top:1.5rem;">
            <div style="display:flex;gap:0.25rem;background:#fff;padding:0.25rem;border-radius:0.5rem;border:1px solid #e5e7eb;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                <template v-for="(link, key) in editRequests.links" :key="key">
                    <div v-if="link.url === null" style="padding:0.375rem 0.75rem;font-size:0.875rem;color:#9ca3af;background:#f9fafb;border-radius:0.375rem;" v-html="link.label" />
                    <Link v-else :href="link.url"
                        style="padding:0.375rem 0.75rem;font-size:0.875rem;border-radius:0.375rem;font-weight:500;transition:background 0.15s;"
                        :style="link.active ? 'background:var(--accent);color:#fff;' : 'color:#374151;'"
                        v-html="link.label" />
                </template>
            </div>
        </div>

    </SchoolLayout>
</template>
