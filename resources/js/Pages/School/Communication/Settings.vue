<script setup>
import Button from '@/Components/ui/Button.vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    settings: Object
});

const form = useForm({
    enabled: props.settings.enabled ?? false,
    attendance: props.settings.attendance ?? {
        enabled: false, sms_enabled: true, whatsapp_enabled: false,
        target: 'absent_only', template_id: '', whatsapp_template: ''
    },
    fees: props.settings.fees ?? {
        enabled: false, sms_enabled: true, whatsapp_enabled: false,
        template_id: '', whatsapp_template: ''
    },
    exams: props.settings.exams ?? {
        enabled: false, sms_enabled: true, whatsapp_enabled: false,
        template_id: '', whatsapp_template: ''
    },
    voice: props.settings.voice ?? { exotel_sid: '', exotel_token: '', exotel_number: '' },
    whatsapp: props.settings.whatsapp ?? { auth_key: '', sender_number: '' }
});

const submit = () => {
    form.post(route('school.communication.settings.update'));
};
</script>

<template>
    <SchoolLayout title="Communication Settings">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Communication Settings</h1>
                <p class="page-header-sub">Master control for all automated alerts and provider configuration</p>
            </div>
            <Button @click="submit" :loading="form.processing">
                Save Settings
            </Button>
        </div>

        <!-- Global Switch -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <h3 class="card-title">Global Notification Switch</h3>
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="badge" :class="form.enabled ? 'badge-green' : 'badge-gray'">
                        {{ form.enabled ? 'ACTIVE' : 'PAUSED' }}
                    </span>
                    <label class="toggle">
                        <input type="checkbox" v-model="form.enabled">
                        <span class="toggle-track"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="settings-grid">
            <!-- Attendance Alerts -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Attendance Alerts</h3>
                    <label class="toggle">
                        <input type="checkbox" v-model="form.attendance.enabled">
                        <span class="toggle-track"></span>
                    </label>
                </div>
                <div class="card-body" :style="!form.attendance.enabled ? 'opacity:0.5;pointer-events:none' : ''">
                    <div class="form-field" style="margin-bottom:16px;">
                        <label>Target Audience</label>
                        <select v-model="form.attendance.target">
                            <option value="absent_only">Absent Students Only</option>
                            <option value="all">All (Present, Absent, Late)</option>
                        </select>
                    </div>

                    <div class="channel-section">
                        <div class="channel-toggle">
                            <span class="channel-label" style="color:#2563eb;">SMS Channel</span>
                            <label class="toggle">
                                <input type="checkbox" v-model="form.attendance.sms_enabled">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                        <div v-if="form.attendance.sms_enabled" class="form-field" style="margin-top:8px;">
                            <input type="text" v-model="form.attendance.template_id" placeholder="MSG91 SMS DLT Template ID">
                        </div>

                        <div class="channel-toggle" style="margin-top:12px;">
                            <span class="channel-label" style="color:#16a34a;">WhatsApp Channel</span>
                            <label class="toggle">
                                <input type="checkbox" v-model="form.attendance.whatsapp_enabled">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                        <div v-if="form.attendance.whatsapp_enabled" class="form-field" style="margin-top:8px;">
                            <input type="text" v-model="form.attendance.whatsapp_template" placeholder="WhatsApp Template Name">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Collection Alerts -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Fee Alerts</h3>
                    <label class="toggle">
                        <input type="checkbox" v-model="form.fees.enabled">
                        <span class="toggle-track"></span>
                    </label>
                </div>
                <div class="card-body" :style="!form.fees.enabled ? 'opacity:0.5;pointer-events:none' : ''">
                    <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px;">Sent instantly after successful payment.</p>

                    <div class="channel-section">
                        <div class="channel-toggle">
                            <span class="channel-label" style="color:#2563eb;">SMS Channel</span>
                            <label class="toggle">
                                <input type="checkbox" v-model="form.fees.sms_enabled">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                        <div v-if="form.fees.sms_enabled" class="form-field" style="margin-top:8px;">
                            <input type="text" v-model="form.fees.template_id" placeholder="MSG91 SMS DLT Template ID">
                        </div>

                        <div class="channel-toggle" style="margin-top:12px;">
                            <span class="channel-label" style="color:#16a34a;">WhatsApp Channel</span>
                            <label class="toggle">
                                <input type="checkbox" v-model="form.fees.whatsapp_enabled">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                        <div v-if="form.fees.whatsapp_enabled" class="form-field" style="margin-top:8px;">
                            <input type="text" v-model="form.fees.whatsapp_template" placeholder="WhatsApp Template Name">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exam Alerts -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Exam Alerts</h3>
                    <label class="toggle">
                        <input type="checkbox" v-model="form.exams.enabled">
                        <span class="toggle-track"></span>
                    </label>
                </div>
                <div class="card-body" :style="!form.exams.enabled ? 'opacity:0.5;pointer-events:none' : ''">
                    <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px;">Schedule changes & result publications.</p>

                    <div class="channel-section">
                        <div class="channel-toggle">
                            <span class="channel-label" style="color:#2563eb;">SMS Channel</span>
                            <label class="toggle">
                                <input type="checkbox" v-model="form.exams.sms_enabled">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                        <div v-if="form.exams.sms_enabled" class="form-field" style="margin-top:8px;">
                            <input type="text" v-model="form.exams.template_id" placeholder="MSG91 SMS DLT Template ID">
                        </div>

                        <div class="channel-toggle" style="margin-top:12px;">
                            <span class="channel-label" style="color:#16a34a;">WhatsApp Channel</span>
                            <label class="toggle">
                                <input type="checkbox" v-model="form.exams.whatsapp_enabled">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                        <div v-if="form.exams.whatsapp_enabled" class="form-field" style="margin-top:8px;">
                            <input type="text" v-model="form.exams.whatsapp_template" placeholder="WhatsApp Template Name">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Provider Configuration -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Provider Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="provider-section">
                        <h4 class="section-heading" style="color:#2563eb;">Voice Integration (Exotel)</h4>
                        <div class="form-field" style="margin-bottom:10px;">
                            <label>Exotel SID</label>
                            <input type="text" v-model="form.voice.exotel_sid" placeholder="Exotel SID">
                        </div>
                        <div class="form-field" style="margin-bottom:10px;">
                            <label>Exotel Token</label>
                            <input type="password" v-model="form.voice.exotel_token" placeholder="Exotel Token">
                        </div>
                        <div class="form-field">
                            <label>Virtual Number</label>
                            <input type="text" v-model="form.voice.exotel_number" placeholder="Virtual Number">
                        </div>
                    </div>

                    <div class="provider-section" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
                        <h4 class="section-heading" style="color:#16a34a;">WhatsApp Integration (MSG91)</h4>
                        <div class="form-field" style="margin-bottom:10px;">
                            <label>WhatsApp Auth Key</label>
                            <input type="password" v-model="form.whatsapp.auth_key" placeholder="WhatsApp Auth Key / API Key">
                        </div>
                        <div class="form-field">
                            <label>WhatsApp Integrated Number</label>
                            <input type="text" v-model="form.whatsapp.sender_number" placeholder="WhatsApp Integrated Number ID">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
@media (max-width: 768px) { .settings-grid { grid-template-columns: 1fr; } }

.channel-section { padding-top: 14px; border-top: 1px solid var(--border); }
.channel-toggle {
    display: flex; align-items: center; justify-content: space-between;
}
.channel-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }

.toggle { position: relative; display: inline-flex; cursor: pointer; }
.toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle-track {
    width: 44px; height: 24px; background: #e2e8f0; border-radius: 12px;
    transition: background .2s; position: relative;
}
.toggle-track::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 20px; height: 20px; background: #fff; border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,.15); transition: transform .2s;
}
.toggle input:checked + .toggle-track { background: var(--accent); }
.toggle input:checked + .toggle-track::after { transform: translateX(20px); }
</style>
