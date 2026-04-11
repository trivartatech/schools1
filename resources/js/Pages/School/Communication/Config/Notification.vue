<script setup>
import Button from '@/Components/ui/Button.vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    config: Object
});

const form = useForm({
    in_portal: props.config.in_portal ?? true,
    push: props.config.push ?? false,
    email: props.config.email ?? false,
    attendance_notify_all: props.config.attendance_notify_all ?? false,
});

const submit = () => {
    form.post(route('school.communication.config.notifications.update'));
};
</script>

<template>
    <SchoolLayout title="Notification Integration">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Notification Channels</h1>
                <p class="page-header-sub">Enable or disable global notification delivery channels</p>
            </div>
        </div>

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

            <!-- Attendance Notification Settings -->
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header">
                    <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                        <div class="channel-icon" style="background:#fef3c7;color:#d97706;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                        Attendance Notification Trigger
                    </h3>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="channel-row" style="border-bottom:none;">
                        <div class="channel-info">
                            <div>
                                <div class="channel-name">Notify All Students (Including Present)</div>
                                <div class="channel-desc">
                                    <span v-if="form.attendance_notify_all" style="color:#059669;font-weight:600;">ON</span>
                                    <span v-else style="color:#6366f1;font-weight:600;">OFF (Default)</span>
                                    &mdash;
                                    <span v-if="form.attendance_notify_all">Attendance notifications will be sent for ALL students, including those marked present.</span>
                                    <span v-else>Attendance notifications will only be sent for absent, late, half-day, and leave statuses. Present students are skipped.</span>
                                </div>
                            </div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" v-model="form.attendance_notify_all">
                            <span class="toggle-track"></span>
                        </label>
                    </div>
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
</style>
