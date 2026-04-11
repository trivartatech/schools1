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
    provider: props.config.provider || 'msg91',
    sender_id: props.config.sender_id || '',
    api_key: props.config.api_key || '',
    identifier: props.config.identifier || '',
    test_number: props.config.test_number || '',
    test_template_id: props.config.test_template_id || '',
    number_prefix: props.config.number_prefix || '91'
});

const submit = () => {
    form.post(route('school.communication.config.whatsapp.update'));
};

const sendTest = () => {
    if (!form.test_number || !form.test_template_id) {
        toast.warning('Please enter both Test Template ID and Test Number.');
        return;
    }
    router.post(route('school.communication.config.whatsapp.send-test'), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Test WhatsApp triggered! Check communication logs.')
    });
};
</script>

<template>
    <SchoolLayout title="WhatsApp Config">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">WhatsApp Configuration</h1>
                <p class="page-header-sub">Configure WhatsApp Gateway to send multi-media notifications</p>
            </div>
            <Button variant="secondary" @click="sendTest">Send Test WhatsApp</Button>
        </div>

        <form @submit.prevent="submit" class="config-layout">
            <!-- Main Config -->
            <div class="config-main">
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3 class="card-title">WhatsApp Provider Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>WhatsApp Provider</label>
                                <div class="provider-badge" style="color:#059669;border-color:#a7f3d0;background:#ecfdf5;">
                                    <span class="provider-dot" style="background:#059669;"></span>
                                    MSG91 Platform
                                </div>
                            </div>
                            <div class="form-field">
                                <label>Sender Number (ID)</label>
                                <input type="text" v-model="form.sender_id" placeholder="e.g. 919901737937">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Number Prefix</label>
                                <input type="text" v-model="form.number_prefix" placeholder="e.g. 91">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Test Template ID</label>
                                <input type="text" v-model="form.test_template_id" placeholder="Enter a registered WhatsApp template ID to test" style="font-family:monospace;font-size:.82rem;">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top:14px;">
                            <div class="form-field">
                                <label>Test Recipient Number</label>
                                <input type="text" v-model="form.test_number" placeholder="Enter recipient with country code">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Provider Specifics -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Msg91 WhatsApp Platform</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Auth API Key</label>
                                <input type="password" v-model="form.api_key" placeholder="xxxxxxxxxxxxxxxx">
                            </div>
                            <div class="form-field">
                                <label>Identifier (Integrated Number)</label>
                                <input type="text" v-model="form.identifier" placeholder="e.g. 91xxxxxxxxxx">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column / Summary -->
            <div class="config-sidebar">
                <div class="card config-status">
                    <div class="card-body">
                        <h4 class="status-heading">WhatsApp Hub Status</h4>
                        <div class="status-rows">
                            <div class="status-row">
                                <span>Channel Active</span>
                                <span class="status-dot"></span>
                            </div>
                            <div class="status-row">
                                <span>Business ID</span>
                                <span class="badge badge-green" style="color:#fff;background:rgba(255,255,255,.2);">Verified</span>
                            </div>
                        </div>
                        <div style="margin-top:24px;">
                            <Button type="submit" :loading="form.processing" block>
                                Sync Configuration
                            </Button>
                            <Button variant="secondary" type="button" @click="form.reset()" block class="mt-2">
                                Reset Changes
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="info-tip">
                    <h5 class="info-tip-title">WhatsApp Guidelines</h5>
                    <p class="info-tip-text">Templates must be pre-approved by Meta/MSG91. Use parameter notation like {{1}}, {{2}} in portal but ##PLACEHOLDER## in our system for easy mapping.</p>
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

.config-status { background: #059669; color: #fff; }
.config-status .card-body { padding: 24px; }
.status-heading { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .7; margin-bottom: 16px; }
.status-rows { display: flex; flex-direction: column; gap: 0; }
.status-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.15);
    font-size: .78rem; font-weight: 500;
}
.status-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #86efac;
    box-shadow: 0 0 6px rgba(134,239,172,.5);
}
.config-status :deep(.ui-btn--primary) { background: #fff; color: #059669; border-color: #fff; }
.config-status :deep(.ui-btn--primary:hover) { background: #f0fdf4; border-color: #f0fdf4; }
.config-status :deep(.ui-btn--secondary) { background: transparent; color: rgba(255,255,255,.7); border-color: rgba(255,255,255,.2); }
.config-status :deep(.ui-btn--secondary:hover) { color: #fff; border-color: rgba(255,255,255,.4); background: transparent; }

.info-tip {
    padding: 16px 20px; background: #ecfdf5; border-radius: var(--radius);
    border: 1px solid #a7f3d0; margin-top: 16px;
}
.info-tip-title { font-size: .78rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
.info-tip-text { font-size: .78rem; color: var(--text-secondary); line-height: 1.5; }
</style>
