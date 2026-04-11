<script setup>
import Button from '@/Components/ui/Button.vue';
import { useForm, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const props = defineProps({
    config: Object
});

const form = useForm({
    gateway: props.config?.gateway || 'msg91',
    sender_id: props.config?.sender_id || '',
    api_key: props.config?.api_key || '',
    test_number: props.config?.test_number || '',
    test_template_id: props.config?.test_template_id || '',
    number_prefix: props.config?.number_prefix || '91'
});

const submit = () => {
    form.post(route('school.communication.config.sms.update'), {
        preserveScroll: true,
    });
};

const sendTest = () => {
    if (!form.test_number) {
        toast.warning('Please enter a test number first.');
        return;
    }
    router.post(route('school.communication.config.sms.send-test'), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Test SMS triggered! Check communication logs.')
    });
};
</script>

<template>
    <SchoolLayout title="SMS Config">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">SMS Configuration</h1>
                <p class="page-header-sub">Configure SMS Gateway to send SMS from the system</p>
            </div>
            <Button variant="secondary" @click="sendTest">Send Test SMS</Button>
        </div>

        <form @submit.prevent="submit" class="config-layout">
            <!-- Main Config -->
            <div class="config-main">
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3 class="card-title">Gateway Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>SMS Gateway</label>
                                <div class="provider-badge" style="color:var(--accent);border-color:#c7d2fe;background:#eef2ff;">
                                    <span class="provider-dot" style="background:var(--accent);"></span>
                                    MSG91 (Enterprise)
                                </div>
                            </div>
                            <div class="form-field">
                                <label>Sender ID</label>
                                <input type="text" v-model="form.sender_id" placeholder="e.g. TRIIVA">
                            </div>
                        </div>
                        <div class="form-row-2" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Test Number</label>
                                <input type="text" v-model="form.test_number" placeholder="Recipient for test SMS">
                            </div>
                            <div class="form-field">
                                <label>Number Prefix</label>
                                <input type="text" v-model="form.number_prefix" placeholder="e.g. 91">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Test Template ID</label>
                                <input type="text" v-model="form.test_template_id" placeholder="Enter a registered template ID to test" style="font-family:monospace;font-size:.82rem;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Provider Specifics -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3 class="card-title">Msg91 SMS Gateway</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-field">
                            <label>Auth API Key</label>
                            <input type="password" v-model="form.api_key" placeholder="xxxxxxxxxxxxxxxx">
                        </div>
                    </div>
                </div>

                <!-- Template Management Link -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Template Management</h3>
                        <Button variant="secondary" size="xs" as="a" :href="route('school.communication.templates.index', 'sms')">Open Template Manager</Button>
                    </div>
                    <div class="card-body">
                        <div class="template-grid">
                            <div v-for="t in ['Attendance', 'Fee Due', 'Fee Paid', 'OTP', 'Test']" :key="t" class="template-item">
                                <span style="font-size:.78rem;font-weight:600;color:var(--text-secondary);">{{ t }} SMS</span>
                                <span class="status-dot"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column / Summary -->
            <div class="config-sidebar">
                <div class="card config-status">
                    <div class="card-body">
                        <h4 class="status-heading">Configuration Status</h4>
                        <div class="status-rows">
                            <div class="status-row">
                                <span>Gateway Active</span>
                                <span class="status-dot"></span>
                            </div>
                            <div class="status-row">
                                <span>Auth Verified</span>
                                <span class="badge badge-blue">Enabled</span>
                            </div>
                        </div>
                        <div style="margin-top:24px;">
                            <Button type="submit" :loading="form.processing" block>
                                Save Configuration
                            </Button>
                            <Button variant="secondary" type="button" @click="form.reset()" block class="mt-2">
                                Reset Changes
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="info-tip">
                    <h5 class="info-tip-title">Setup Help</h5>
                    <p class="info-tip-text">Ensure your Sender ID is approved by DLT before use. Template IDs must match exactly as provided in the portal.</p>
                </div>
            </div>
        </form>
    </SchoolLayout>
</template>

<style scoped>
.config-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
@media (max-width: 1024px) { .config-layout { grid-template-columns: 1fr; } }

.provider-badge {
    font-size: .82rem; font-weight: 700; padding: 10px 14px; border-radius: var(--radius);
    border: 1px solid; display: flex; align-items: center; gap: 8px; text-transform: uppercase;
    letter-spacing: .03em;
}
.provider-dot { width: 6px; height: 6px; border-radius: 50%; }

.template-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
@media (max-width: 640px) { .template-grid { grid-template-columns: 1fr; } }
.template-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; background: #f8fafc; border-radius: var(--radius); border: 1px solid var(--border);
}

.config-status { background: var(--accent); color: #fff; }
.config-status .card-body { padding: 24px; }
.status-heading { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .7; margin-bottom: 16px; }
.status-rows { display: flex; flex-direction: column; gap: 0; }
.status-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.15);
    font-size: .78rem; font-weight: 500;
}
.status-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
    box-shadow: 0 0 6px rgba(34,197,94,.5);
}
.config-status :deep(.ui-btn--primary) { background: #fff; color: var(--accent); border-color: #fff; }
.config-status :deep(.ui-btn--primary:hover) { background: #f0f0ff; border-color: #f0f0ff; }
.config-status :deep(.ui-btn--secondary) { background: transparent; color: rgba(255,255,255,.7); border-color: rgba(255,255,255,.2); }
.config-status :deep(.ui-btn--secondary:hover) { color: #fff; border-color: rgba(255,255,255,.4); background: transparent; }

.info-tip {
    padding: 16px 20px; background: #eef2ff; border-radius: var(--radius);
    border: 1px solid #c7d2fe; margin-top: 16px;
}
.info-tip-title { font-size: .78rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
.info-tip-text { font-size: .78rem; color: var(--text-secondary); line-height: 1.5; }
</style>
