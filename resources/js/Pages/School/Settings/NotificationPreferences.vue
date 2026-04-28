<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    preferences: Object,
    eventTypes:  Object,
    channels:    Array,
});

// Deep clone into reactive form state
const prefs = reactive(JSON.parse(JSON.stringify(props.preferences)));

const form = useForm({});

const save = () => {
    form.transform(() => ({ preferences: prefs }))
        .post('/school/settings/notification-preferences', { preserveScroll: true });
};

const channelLabel = {
    push:      'Push',
    email:     'Email',
    sms:       'SMS',
    whatsapp:  'WhatsApp',
};

const channelIcon = {
    push:     '🔔',
    email:    '✉️',
    sms:      '💬',
    whatsapp: '📲',
};

const groupLabels = {
    fee_payment:        'Finance',
    fee_due_reminder:   'Finance',
    attendance_marked:  'Attendance',
    leave_approved:     'Leaves',
    leave_rejected:     'Leaves',
    exam_result:        'Academics',
    announcement:       'Communication',
    gate_pass_approved: 'Hostel',
    gate_pass_rejected: 'Hostel',
    timetable_update:   'Academics',
    holiday_notice:     'Communication',
    ptm_booking:        'Communication',
    library_due:        'Library',
};

// Group events by category
const grouped = {};
for (const [key, label] of Object.entries(props.eventTypes)) {
    const group = groupLabels[key] ?? 'Other';
    if (!grouped[group]) grouped[group] = [];
    grouped[group].push({ key, label });
}

const toggleAll = (eventKey, enable) => {
    props.channels.forEach(ch => { prefs[eventKey][ch] = enable; });
};

const groupColor = {
    Finance: '#10b981', Attendance: '#3b82f6', Leaves: '#f59e0b',
    Academics: '#8b5cf6', Communication: '#06b6d4', Hostel: '#f97316',
    Library: '#84cc16', Other: '#6b7280',
};
</script>

<template>
    <SchoolLayout title="Notification Preferences">
        <PageHeader title="Notification Preferences">
            <template #subtitle>
                <p style="color:#64748b;font-size:.9rem;">Choose which notifications you receive and via which channels.</p>
            </template>
            <template #actions>
                <Button @click="save" :loading="form.processing">Save Preferences</Button>
            </template>
        </PageHeader>

        <!-- Channel legend -->
        <div class="card" style="margin-bottom:20px;padding:14px 16px;">
            <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
                <span style="font-size:.8rem;color:#64748b;font-weight:600;">Channels:</span>
                <span v-for="ch in channels" :key="ch" style="display:flex;align-items:center;gap:6px;font-size:.85rem;">
                    {{ channelIcon[ch] }} {{ channelLabel[ch] }}
                </span>
            </div>
        </div>

        <!-- Groups -->
        <div v-for="(events, groupName) in grouped" :key="groupName" class="card" style="margin-bottom:16px;">
            <div class="card-header" :style="{ borderLeft: `3px solid ${groupColor[groupName] ?? '#3b82f6'}` }">
                <span class="card-title">{{ groupName }}</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="text-align:left;padding:10px 16px;font-size:.8rem;color:#64748b;font-weight:600;min-width:220px;">Notification</th>
                            <th v-for="ch in channels" :key="ch" style="text-align:center;padding:10px 16px;font-size:.8rem;color:#64748b;font-weight:600;min-width:80px;">
                                {{ channelIcon[ch] }} {{ channelLabel[ch] }}
                            </th>
                            <th style="text-align:center;padding:10px 16px;font-size:.8rem;color:#94a3b8;min-width:80px;">All</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.key" style="border-top:1px solid #f1f5f9;">
                            <td style="padding:12px 16px;font-size:.875rem;color:#1e293b;">{{ event.label }}</td>
                            <td v-for="ch in channels" :key="ch" style="text-align:center;padding:12px 16px;">
                                <label style="display:inline-flex;align-items:center;cursor:pointer;">
                                    <input type="checkbox" v-model="prefs[event.key][ch]"
                                           style="width:18px;height:18px;cursor:pointer;accent-color:#3b82f6;" />
                                </label>
                            </td>
                            <td style="text-align:center;padding:12px 16px;">
                                <div style="display:flex;gap:4px;justify-content:center;">
                                    <button type="button" @click="toggleAll(event.key, true)"
                                            style="font-size:.7rem;padding:3px 8px;border-radius:4px;border:1px solid #e2e8f0;background:#f0fdf4;color:#10b981;cursor:pointer;">All</button>
                                    <button type="button" @click="toggleAll(event.key, false)"
                                            style="font-size:.7rem;padding:3px 8px;border-radius:4px;border:1px solid #e2e8f0;background:#fef2f2;color:#ef4444;cursor:pointer;">None</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;padding-bottom:32px;">
            <Button @click="save" :loading="form.processing" style="min-width:150px;">Save All Preferences</Button>
        </div>
    </SchoolLayout>
</template>
