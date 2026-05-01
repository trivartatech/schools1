<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    config: Object
});

const ATTENDANCE_CHANNEL_ROWS = [
    { key: 'sms',      label: 'SMS',       desc: 'Text message via MSG91' },
    { key: 'whatsapp', label: 'WhatsApp',  desc: 'Template message via MSG91' },
    { key: 'voice',    label: 'Voice Call',desc: 'Outbound call via Exotel' },
    { key: 'push',     label: 'Push',      desc: 'Mobile / browser push' },
];

const defaultMatrix = () => ({
    sms:      { absent: true, present: false },
    whatsapp: { absent: true, present: false },
    voice:    { absent: true, present: false },
    push:     { absent: true, present: false },
});

const form = useForm({
    in_portal: props.config.in_portal ?? true,
    push: props.config.push ?? false,
    email: props.config.email ?? false,
    attendance_channels: props.config.attendance_channels ?? defaultMatrix(),
});

const submit = () => {
    form.post(route('school.communication.config.notifications.update'));
};
</script>

<template>
    <SchoolLayout title="Notification Integration">
        <PageHeader title="Notification Channels" subtitle="Enable or disable global notification delivery channels" />

        <form @submit.prevent="submit">
            <div class="card" style="margin-bottom:16px;">
                <div class="card-body" style="padding:0;">
                    <div class="channel-row">
                        <div class="channel-info">
                            <div class="channel-icon" style="background:#eff6ff;color:#2563eb;">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div>
                                <div class="channel-name">In-Portal Notifications</div>
                                <div class="channel-desc">Show alerts in the school dashboard notification bell</div>
                            </div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" v-model="form.in_portal">
                            <span class="toggle-track"></span>
                        </label>
                    </div>

                    <div class="channel-row">
                        <div class="channel-info">
                            <div class="channel-icon" style="background:#eef2ff;color:#4f46e5;">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            </div>
                            <div>
                                <div class="channel-name">Push Notifications</div>
                                <div class="channel-desc">Send browser/mobile push alerts via Firebase</div>
                            </div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" v-model="form.push">
                            <span class="toggle-track"></span>
                        </label>
                    </div>

                    <div class="channel-row" style="border-bottom:none;">
                        <div class="channel-info">
                            <div class="channel-icon" style="background:#faf5ff;color:#7c3aed;">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="channel-name">Email Notifications</div>
                                <div class="channel-desc">Send HTML emails to parents/staff</div>
                            </div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" v-model="form.email">
                            <span class="toggle-track"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Attendance Notifications -->
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header">
                    <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                        <div class="channel-icon" style="background:#fef3c7;color:#d97706;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                        Attendance Notifications
                    </h3>
                    <p class="card-sub">
                        Pick which channels notify parents for absent vs. present students.
                        "Absent" applies to absent, late, half-day, and leave statuses.
                    </p>
                </div>
                <div class="card-body">
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th>Channel</th>
                                <th>Absent / Late / Leave</th>
                                <th>Present</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ch in ATTENDANCE_CHANNEL_ROWS" :key="ch.key">
                                <td>
                                    <div class="matrix-channel-name">{{ ch.label }}</div>
                                    <div class="matrix-channel-desc">{{ ch.desc }}</div>
                                </td>
                                <td>
                                    <label class="matrix-cell">
                                        <input type="checkbox" v-model="form.attendance_channels[ch.key].absent">
                                    </label>
                                </td>
                                <td>
                                    <label class="matrix-cell">
                                        <input type="checkbox" v-model="form.attendance_channels[ch.key].present">
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <Button type="submit" :loading="form.processing">
                    Save Changes
                </Button>
            </div>
        </form>
    </SchoolLayout>
</template>

<style scoped>
.channel-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 20px 24px; border-bottom: 1px solid var(--border); transition: background .15s;
}
.channel-row:hover { background: #f8fafc; }
.channel-info { display: flex; align-items: center; gap: 16px; }
.channel-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.channel-name { font-weight: 700; font-size: .9rem; color: var(--text-primary); }
.channel-desc { font-size: .75rem; color: var(--text-muted); margin-top: 2px; }

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

.card-sub {
    margin: 6px 0 0;
    font-size: .8125rem;
    color: var(--text-muted);
}

.matrix-table {
    width: 100%;
    border-collapse: collapse;
}
.matrix-table th {
    text-align: left;
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--text-muted);
    padding: 8px 12px;
    border-bottom: 1px solid var(--border);
    background: #f8fafc;
}
.matrix-table th:nth-child(2),
.matrix-table th:nth-child(3) { text-align: center; width: 160px; }
.matrix-table td {
    padding: 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.matrix-table tr:last-child td { border-bottom: none; }
.matrix-channel-name { font-weight: 600; font-size: .875rem; color: var(--text-primary); }
.matrix-channel-desc { font-size: .75rem; color: var(--text-muted); margin-top: 2px; }
.matrix-cell {
    display: flex;
    justify-content: center;
    cursor: pointer;
    padding: 4px;
}
.matrix-cell input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--accent);
    cursor: pointer;
}
</style>
