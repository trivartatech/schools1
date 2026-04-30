<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Table from '@/Components/ui/Table.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

defineProps({
    scheduled: Object,
});

const school = useSchoolStore();
const confirm = useConfirm();

const expandedError = ref(null);

const toggleError = (id) => {
    expandedError.value = expandedError.value === id ? null : id;
};

const getStatus = (item) => {
    if (item.is_broadcasted) return { label: 'Sent', class: 'badge-green' };
    if (item.failed_at) return { label: 'Failed', class: 'badge-red' };
    return { label: 'Pending', class: 'badge-amber' };
};

const isPending = (item) => !item.is_broadcasted && !item.failed_at;
const isFailed = (item) => !!item.failed_at;

const cancelMessage = async (id) => {
    const ok = await confirm({
        title: 'Cancel scheduled message?',
        message: 'Cancel this scheduled message?',
        confirmLabel: 'Cancel Message',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/communication/scheduled/${id}`, {
        preserveScroll: true,
    });
};

const retryMessage = (id) => {
    router.post(`/school/communication/scheduled/${id}/retry`, {}, {
        preserveScroll: true,
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return school.fmtDateTime(dateStr);
};

const methodColor = (method) => {
    const colors = {
        sms: '#1169cd',
        email: '#7c3aed',
        whatsapp: '#16a34a',
        voice: '#f59e0b',
    };
    return colors[method?.toLowerCase()] || '#64748b';
};
</script>

<template>
    <SchoolLayout title="Scheduled Messages">
        <PageHeader title="Scheduled Messages" subtitle="View and manage queued, sent, and failed messages" />

        <div class="card">
            <Table v-if="scheduled?.data?.length > 0" class="table" striped size="sm">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Method</th>
                            <th>Audience</th>
                            <th>Scheduled At</th>
                            <th>Status</th>
                            <th>Sender</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="item in scheduled.data" :key="item.id">
                            <tr>
                                <td>
                                    <div class="title-cell">
                                        <span class="item-title">{{ item.title }}</span>
                                        <span v-if="item.template" class="item-template">
                                            {{ item.template.name }} ({{ item.template.type }})
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="method-badge"
                                        :style="{ background: methodColor(item.delivery_method) + '15', color: methodColor(item.delivery_method) }"
                                    >
                                        {{ item.delivery_method }}
                                    </span>
                                </td>
                                <td class="audience-cell">{{ item.audience_type }}</td>
                                <td class="date-cell">{{ formatDate(item.scheduled_at) }}</td>
                                <td>
                                    <span class="badge" :class="getStatus(item).class">
                                        {{ getStatus(item).label }}
                                    </span>
                                    <button
                                        v-if="isFailed(item) && item.broadcast_error"
                                        class="error-toggle"
                                        @click="toggleError(item.id)"
                                    >
                                        {{ expandedError === item.id ? 'Hide' : 'Details' }}
                                    </button>
                                </td>
                                <td class="sender-cell">{{ item.sender?.name ?? '-' }}</td>
                                <td>
                                    <div class="actions-cell">
                                        <button
                                            v-if="isPending(item)"
                                            class="action-btn action-cancel"
                                            @click="cancelMessage(item.id)"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            v-if="isFailed(item)"
                                            class="action-btn action-retry"
                                            @click="retryMessage(item.id)"
                                        >
                                            Retry
                                        </button>
                                        <span v-if="item.is_broadcasted" class="action-none">-</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expandedError === item.id && item.broadcast_error">
                                <td colspan="7" class="error-detail-cell">
                                    <div class="error-detail">{{ item.broadcast_error }}</div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
            </Table>

            <!-- Empty State -->
            <EmptyState
                v-else
                tone="muted"
                title="No scheduled messages"
                description="Scheduled messages will appear here once created."
            />
        </div>

        <!-- Pagination -->
        <div v-if="scheduled?.links?.length > 3" class="pagination">
            <template v-for="link in scheduled.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="page-link"
                    :class="{ active: link.active }"
                    v-html="link.label"
                    :preserve-scroll="true"
                />
                <span
                    v-else
                    class="page-link disabled"
                    v-html="link.label"
                />
            </template>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.title-cell { display: flex; flex-direction: column; gap: 2px; }
.item-title { font-weight: 600; font-size: .85rem; }
.item-template { font-size: .72rem; color: #94a3b8; }

.method-badge {
    display: inline-flex;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.audience-cell { text-transform: capitalize; font-size: .82rem; }
.date-cell { white-space: nowrap; font-size: .82rem; color: #475569; }
.sender-cell { font-size: .82rem; color: #475569; }

.badge {
    display: inline-flex;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-red { background: #fee2e2; color: #ef4444; }
.badge-amber { background: #fef3c7; color: #f59e0b; }

.error-toggle {
    background: none;
    border: none;
    font-size: .72rem;
    color: #ef4444;
    cursor: pointer;
    margin-left: 6px;
    text-decoration: underline;
    padding: 0;
}
.error-detail-cell {
    padding: 0 16px 12px !important;
    border-bottom: 1px solid #f1f5f9 !important;
}
.error-detail {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: .78rem;
    color: #b91c1c;
    line-height: 1.5;
    font-family: monospace;
}

.actions-cell { display: flex; gap: 6px; }
.action-btn {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: opacity .15s;
}
.action-btn:hover { opacity: .85; }
.action-cancel { background: #fee2e2; color: #ef4444; }
.action-retry { background: #fef3c7; color: #92400e; }
.action-none { color: #cbd5e1; font-size: .82rem; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.page-link {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: .82rem;
    color: #475569;
    text-decoration: none;
    border: 1px solid #e2e8f0;
    background: #fff;
    transition: all .15s;
}
.page-link:hover:not(.disabled):not(.active) { border-color: #1169cd; color: #1169cd; }
.page-link.active { background: #1169cd; color: #fff; border-color: #1169cd; }
.page-link.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }
</style>
