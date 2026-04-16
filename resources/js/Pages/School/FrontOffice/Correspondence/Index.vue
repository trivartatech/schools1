<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

const props = defineProps({
    correspondences: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const form = useForm({
    type: 'Incoming',
    reference_number: '',
    sender_receiver_name: '',
    subject: '',
    department_id: '',
    date: new Date().toISOString().split('T')[0],
    dispatch_tracking: '',
    courier_name: '',
    notes: '',
    file: null,
});

const showForm = ref(false);

const submit = () => {
    form.post('/school/front-office/correspondence', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        }
    });
};

const deleteEntry = (id) => {
    if (confirm('Are you sure you want to delete this record? Data and attachments will be lost.')) {
        router.delete(`/school/front-office/correspondence/${id}`, { preserveScroll: true });
    }
};

const updateDelivery = (id, status) => {
    if (!status) return;
    router.patch(`/school/front-office/correspondence/${id}/status`, { delivery_status: status }, { preserveScroll: true });
};

const acknowledge = (id) => {
    router.post(`/school/front-office/correspondence/${id}/acknowledge`, {}, { preserveScroll: true });
};

const activeTab = ref('All');
const tabs = ['All', 'Incoming', 'Outgoing'];

const filteredData = computed(() => {
    if (activeTab.value === 'All') return props.correspondences;
    return props.correspondences.filter(c => c.type === activeTab.value);
});
</script>

<template>
    <SchoolLayout title="Correspondence">

        <div class="page-header">
            <div>
                <h1 class="page-header-title">Mail & Dispatch Log</h1>
                <p class="page-header-sub">Record incoming deliveries, couriers, and official dispatch letters.</p>
            </div>
            <Button @click="showForm = !showForm">
                {{ showForm ? 'Close Entry Form' : '+ New Correspondence' }}
            </Button>
        </div>

        <!-- NEW CORRESPONDENCE FORM -->
        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-[-20px] opacity-0"
                    enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-[-20px]">
        <div v-show="showForm" class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Register Mail / Courier</h2>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="form-row-3">
                        <div class="form-field">
                            <label>Direction Type</label>
                            <select v-model="form.type" required>
                                <option value="Incoming">Received / Incoming</option>
                                <option value="Outgoing">Sent / Outgoing</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Sender / Receiver Name</label>
                            <input v-model="form.sender_receiver_name" type="text" placeholder="Organization or Person Name" required>
                        </div>
                        <div class="form-field">
                            <label>Date</label>
                            <input v-model="form.date" type="date" required>
                        </div>
                    </div>

                    <div class="form-row" style="margin-top:1rem;">
                        <div class="form-field">
                            <label>Subject / Description</label>
                            <input v-model="form.subject" type="text" placeholder="Brief subject of the correspondence..." required>
                        </div>
                    </div>

                    <div class="form-row-3" style="margin-top:1rem;">
                        <div class="form-field">
                            <label>Internal Reference No. <span class="field-optional">(Optional)</span></label>
                            <input v-model="form.reference_number" type="text" placeholder="e.g. IN/2026/001">
                        </div>
                        <div class="form-field">
                            <label>Assign to Department</label>
                            <select v-model="form.department_id">
                                <option value="">-- General / Admin --</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Attachment Scan <span class="field-optional">(Optional)</span></label>
                            <input type="file" @change="e => form.file = e.target.files[0]">
                        </div>
                    </div>

                    <!-- Logistics Details -->
                    <div class="logistics-section">
                        <p class="section-heading">Logistics Details (Optional)</p>
                        <div class="form-row-3">
                            <div class="form-field">
                                <label>Courier Service Name</label>
                                <input v-model="form.courier_name" type="text" placeholder="BlueDart, DTDC, Regular Post">
                            </div>
                            <div class="form-field">
                                <label>Tracking / AWB Number</label>
                                <input v-model="form.dispatch_tracking" type="text" placeholder="AWB...">
                            </div>
                            <div class="form-field">
                                <label>Dispatch Notes</label>
                                <input v-model="form.notes" type="text" placeholder="Additional notes...">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <Button variant="secondary" type="button" @click="showForm = false">Cancel</Button>
                        <Button type="submit" :loading="form.processing">Record Correspondence</Button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- TABS -->
        <div class="tab-bar">
            <button v-for="tab in tabs" :key="tab" @click="activeTab = tab"
                    class="tab-item" :class="{ 'tab-active': activeTab === tab }">
                {{ tab }}
            </button>
        </div>

        <!-- CORRESPONDENCE CARDS -->
        <div class="corr-grid">
            <div v-if="filteredData.length === 0" class="card" style="grid-column:1/-1;padding:3rem;text-align:center;color:var(--text-muted);">
                No mail records found.
            </div>

            <div v-for="item in filteredData" :key="item.id" class="card corr-card">
                <!-- Direction Badge -->
                <span class="corr-type-badge" :class="item.type === 'Incoming' ? 'corr-type--in' : 'corr-type--out'">
                    {{ item.type }}
                </span>

                <div class="card-body" style="padding-top:2rem;">
                    <h3 class="corr-subject">{{ item.subject }}</h3>
                    <div class="corr-party">
                        <span>{{ item.type === 'Incoming' ? 'From:' : 'To:' }}</span>
                        <strong>{{ item.sender_receiver_name }}</strong>
                    </div>

                    <div class="corr-details">
                        <div class="corr-detail-item">
                            <span class="corr-detail-label">Date</span>
                            <span class="corr-detail-value">{{ school.fmtDate(item.date) }}</span>
                        </div>
                        <div class="corr-detail-item">
                            <span class="corr-detail-label">Department</span>
                            <span class="corr-detail-value">{{ item.department?.name || 'General' }}</span>
                        </div>
                        <div v-if="item.reference_number" class="corr-detail-item">
                            <span class="corr-detail-label">Ref No.</span>
                            <span class="corr-detail-value" style="font-family:monospace;">{{ item.reference_number }}</span>
                        </div>
                        <div v-if="item.dispatch_tracking" class="corr-detail-item">
                            <span class="corr-detail-label">Tracking</span>
                            <span class="corr-detail-value" style="font-family:monospace;font-size:.75rem;">{{ item.dispatch_tracking }} <span v-if="item.courier_name">({{ item.courier_name }})</span></span>
                        </div>
                    </div>

                    <!-- Delivery & Acknowledgment Status -->
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:.75rem;flex-wrap:wrap;">
                        <span class="badge" :class="{
                            'badge-gray': item.delivery_status === 'pending',
                            'badge-amber': item.delivery_status === 'in_transit',
                            'badge-green': item.delivery_status === 'delivered',
                            'badge-red': item.delivery_status === 'returned',
                        }">{{ (item.delivery_status || 'pending').replace('_', ' ') }}</span>
                        <span v-if="item.acknowledged" class="badge badge-green" style="font-size:.65rem;">
                            Acknowledged {{ item.acknowledged_by ? 'by ' + item.acknowledged_by : '' }}
                        </span>
                        <select v-if="!item.acknowledged" style="font-size:.72rem;padding:2px 6px;border:1px solid var(--border);border-radius:4px;"
                                @change="updateDelivery(item.id, $event.target.value); $event.target.selectedIndex = 0;">
                            <option value="" selected disabled>Status...</option>
                            <option value="pending">Pending</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="returned">Returned</option>
                        </select>
                        <Button size="xs" v-if="!item.acknowledged && item.delivery_status === 'delivered'" @click="acknowledge(item.id)" style="font-size:.68rem;">
                            Acknowledge
                        </Button>
                    </div>

                    <div class="corr-footer">
                        <Button variant="secondary" size="xs" as="a" v-if="item.attachment_path" :href="`/storage/${item.attachment_path}`" target="_blank">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            View Document
                        </Button>
                        <span v-else class="corr-no-attach">No Attachment</span>

                        <Button variant="danger" size="xs" @click="deleteEntry(item.id)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </Button>
                    </div>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.field-optional { color: var(--text-muted); font-weight: 400; font-size: .8em; }

.logistics-section {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

/* Tab bar */
.tab-bar {
    display: flex;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    font-size: .875rem;
    font-weight: 500;
    gap: .25rem;
}
.tab-item {
    padding: .75rem 1.25rem;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
    cursor: pointer;
    color: var(--text-muted);
    transition: color .15s;
}
.tab-active {
    border-bottom-color: var(--accent);
    color: var(--accent);
}

/* Card grid */
.corr-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
@media (max-width: 800px) { .corr-grid { grid-template-columns: 1fr; } }

.corr-card {
    position: relative;
    overflow: hidden;
}
.corr-type-badge {
    position: absolute;
    top: 0;
    right: 0;
    padding: .25rem .75rem;
    border-radius: 0 0 0 .5rem;
    font-size: .65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #fff;
}
.corr-type--in { background: var(--accent); }
.corr-type--out { background: #f59e0b; }

.corr-subject {
    font-weight: 700;
    font-size: 1rem;
    color: var(--text-primary);
    line-height: 1.3;
    margin-bottom: .375rem;
}
.corr-party {
    font-size: .875rem;
    color: var(--text-secondary);
    margin-bottom: .75rem;
}
.corr-party strong { color: var(--text-primary); }

.corr-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
    background: var(--bg);
    padding: .75rem;
    border-radius: var(--radius);
    border: 1px solid var(--border-light);
    margin-bottom: .75rem;
}
.corr-detail-label {
    display: block;
    font-size: .625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text-muted);
}
.corr-detail-value {
    font-size: .8125rem;
    font-weight: 500;
    color: var(--text-primary);
}

.corr-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.corr-no-attach {
    font-size: .75rem;
    color: var(--text-muted);
}
</style>
