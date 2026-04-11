<script setup>
import { computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    school:   { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
});

const page = usePage();

// ── Settings sidebar nav items ────────────────────────────────────────────
const settingsNav = [
    { id: 'general-config',     label: 'General Config',              route: '/school/settings/general-config',    icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'asset-config',       label: 'Asset Config',                route: '/school/settings/asset-config',      icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'system-config',      label: 'System Config',               route: '/school/settings/system-config',     icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18' },
    { id: 'geofence-config',    label: 'Geofence Config',             route: '/school/settings/geofence-config', icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

// —— Form —————————————————————————————————————————————————————————————————
const form = useForm({
    date_format:   props.settings.date_format   ?? 'DD/MM/YYYY',
    time_format:   props.settings.time_format   ?? 'h:mm A',
    currency:      props.school.currency         ?? 'INR',
    timezone:      props.school.timezone         ?? 'Asia/Kolkata',
    language:      props.school.language         ?? 'en',
    page_length:   props.settings.page_length    ?? 20,
    footer_credit: props.settings.footer_credit  ?? '',
});

const submit = () => {
    form.post('/school/settings/system-config', { preserveScroll: true });
};

const reset = () => form.reset();
</script>

<template>
    <SchoolLayout title="System Config">
        <div class="gc-shell">
            <!-- —— Settings Sidebar ————————————————————————————————— -->
            <aside class="gc-sidebar">
                <nav class="gc-sidebar-nav">
                    <Link
                        v-for="item in settingsNav"
                        :key="item.id"
                        :href="item.route"
                        class="gc-nav-item"
                        :class="{ 'gc-nav-item--active': isActive(item.route) }"
                    >
                        <svg class="gc-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>
            </aside>

            <!-- —— Main Content ————————————————————————————————————— -->
            <section class="gc-content">
                <div class="gc-page-header">
                    <h1 class="gc-page-title">System Configuration</h1>
                    <p class="gc-page-subtitle">Configure core platform preferences, formats, and regional settings.</p>
                </div>

                <form @submit.prevent="submit" novalidate>
                    <div class="gc-card">
                        <div class="gc-card-header">
                            <h2 class="gc-card-title">Localization & Formats</h2>
                            <p class="gc-card-subtitle">Define how dates, times, and currency appear across the system.</p>
                        </div>
                        <div class="gc-card-body">
                            <div class="gc-row">
                                <div class="gc-field">
                                    <label class="gc-label">Date Format</label>
                                    <select v-model="form.date_format" class="gc-input">
                                        <option value="DD/MM/YYYY">DD/MM/YYYY (e.g. 27/03/2026)</option>
                                        <option value="MM/DD/YYYY">MM/DD/YYYY (e.g. 03/27/2026)</option>
                                        <option value="YYYY-MM-DD">YYYY-MM-DD (e.g. 2026-03-27)</option>
                                        <option value="D MMM, YYYY">D MMM, YYYY (e.g. 27 Mar, 2026)</option>
                                    </select>
                                    <span v-if="form.errors.date_format" class="gc-error">{{ form.errors.date_format }}</span>
                                </div>
                                <div class="gc-field">
                                    <label class="gc-label">Time Format</label>
                                    <select v-model="form.time_format" class="gc-input">
                                        <option value="h:mm A">12-hour (e.g. 9:30 AM)</option>
                                        <option value="H:mm">24-hour (e.g. 09:30)</option>
                                        <option value="h:mm:ss A">12-hour with Seconds</option>
                                    </select>
                                    <span v-if="form.errors.time_format" class="gc-error">{{ form.errors.time_format }}</span>
                                </div>
                            </div>

                            <div class="gc-row">
                                <div class="gc-field">
                                    <label class="gc-label">Currency Symbol / Code</label>
                                    <input v-model="form.currency" type="text" class="gc-input" placeholder="e.g. INR or ₹" />
                                    <p class="field-hint">Used for all financial displays.</p>
                                    <span v-if="form.errors.currency" class="gc-error">{{ form.errors.currency }}</span>
                                </div>
                                <div class="gc-field">
                                    <label class="gc-label">Page Length (Default)</label>
                                    <select v-model="form.page_length" class="gc-input">
                                        <option :value="10">10 Rows per page</option>
                                        <option :value="20">20 Rows per page</option>
                                        <option :value="50">50 Rows per page</option>
                                        <option :value="100">100 Rows per page</option>
                                    </select>
                                    <p class="field-hint">Initial number of rows for tables.</p>
                                    <span v-if="form.errors.page_length" class="gc-error">{{ form.errors.page_length }}</span>
                                </div>
                            </div>

                            <div class="gc-row">
                                <div class="gc-field">
                                    <label class="gc-label">Timezone</label>
                                    <select v-model="form.timezone" class="gc-input">
                                        <option value="Asia/Kolkata">Asia/Kolkata (IST, UTC+5:30)</option>
                                        <option value="Asia/Dubai">Asia/Dubai (GST, UTC+4)</option>
                                        <option value="Asia/Singapore">Asia/Singapore (SGT, UTC+8)</option>
                                        <option value="Asia/Tokyo">Asia/Tokyo (JST, UTC+9)</option>
                                        <option value="Europe/London">Europe/London (GMT/BST)</option>
                                        <option value="America/New_York">America/New York (EST/EDT)</option>
                                        <option value="America/Los_Angeles">America/Los Angeles (PST/PDT)</option>
                                        <option value="Australia/Sydney">Australia/Sydney (AEST/AEDT)</option>
                                        <option value="Africa/Nairobi">Africa/Nairobi (EAT, UTC+3)</option>
                                        <option value="Pacific/Auckland">Pacific/Auckland (NZST/NZDT)</option>
                                    </select>
                                    <p class="field-hint">Controls system-wide time display.</p>
                                    <span v-if="form.errors.timezone" class="gc-error">{{ form.errors.timezone }}</span>
                                </div>
                                <div class="gc-field">
                                    <label class="gc-label">Language</label>
                                    <select v-model="form.language" class="gc-input">
                                        <option value="en">English</option>
                                        <option value="hi">Hindi</option>
                                        <option value="kn">Kannada</option>
                                        <option value="ta">Tamil</option>
                                        <option value="te">Telugu</option>
                                        <option value="mr">Marathi</option>
                                        <option value="bn">Bengali</option>
                                        <option value="gu">Gujarati</option>
                                        <option value="ml">Malayalam</option>
                                        <option value="pa">Punjabi</option>
                                        <option value="ar">Arabic</option>
                                    </select>
                                    <p class="field-hint">Default language for the application.</p>
                                    <span v-if="form.errors.language" class="gc-error">{{ form.errors.language }}</span>
                                </div>
                            </div>

                            <div class="gc-row">
                                <div class="gc-field" style="grid-column: span 2;">
                                    <label class="gc-label">Footer Credit / Copyright Text</label>
                                    <input v-model="form.footer_credit" type="text" class="gc-input" placeholder="e.g. © 2026 Your School Name. All rights reserved." />
                                    <p class="field-hint">This text will appear in the application footer.</p>
                                    <span v-if="form.errors.footer_credit" class="gc-error">{{ form.errors.footer_credit }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="gc-actions">
                        <button type="button" @click="reset" class="gc-btn-reset" :disabled="!form.isDirty">Reset</button>
                        <button type="submit" :disabled="form.processing || !form.isDirty" class="gc-btn-save">
                            <svg v-if="form.processing" class="gc-spinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </SchoolLayout>
</template>

<style scoped>
/* —— Shell —— */
.gc-shell {
    display: flex;
    min-height: calc(100vh - 56px);
    margin: -24px -28px;
    background: #f8fafc;
}

/* —— Sidebar —— */
.gc-sidebar { width: 220px; background: #fff; border-right: 1px solid #e2e8f0; padding: 16px 0; flex-shrink: 0; }
.gc-sidebar-nav { display: flex; flex-direction: column; gap: 1px; padding: 0 8px; }
.gc-nav-item { display: flex; align-items: center; gap: 9px; padding: 8px 10px; border-radius: 7px; font-size: 0.8125rem; font-weight: 500; color: #64748b; text-decoration: none; transition: 0.1s; }
.gc-nav-item:hover { background: #f1f5f9; color: #1e293b; }
.gc-nav-item--active { background: #eff6ff !important; color: #1169cd !important; font-weight: 600; }
.gc-nav-icon { width: 15px; height: 15px; }

/* —— Content —— */
.gc-content { flex: 1; padding: 28px 32px; overflow-y: auto; }
.gc-page-header { margin-bottom: 24px; }
.gc-page-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; letter-spacing: -0.02em; margin-bottom: 4px; }
.gc-page-subtitle { font-size: 0.875rem; color: #64748b; }

/* —— Cards —— */
.gc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.gc-card-header { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; }
.gc-card-title { font-size: 0.9375rem; font-weight: 700; color: #1e293b; margin: 0; }
.gc-card-subtitle { font-size: 0.775rem; color: #64748b; margin-top: 2px; }
.gc-card-body { padding: 24px; display: flex; flex-direction: column; gap: 24px; }

/* —— Grid —— */
.gc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

/* —— Form Fields —— */
.gc-field { display: flex; flex-direction: column; gap: 6px; }
.gc-label { font-size: 0.775rem; font-weight: 600; color: #374151; }
.gc-input { height: 38px; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.8125rem; color: #1e293b; background: #fff; transition: 0.15s; outline: none; }
.gc-input:focus { border-color: #1169cd; box-shadow: 0 0 0 3px rgba(17, 105, 205, 0.1); }
.field-hint { font-size: 0.7rem; color: #94a3b8; margin-top: 2px; }
.gc-error { font-size: 0.7rem; color: #ef4444; }

/* —— Actions —— */
.gc-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 24px; margin-top: 8px; }
.gc-btn-reset { height: 40px; padding: 0 20px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; color: #374151; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: 0.15s; }
.gc-btn-reset:hover:not(:disabled) { background: #f9fafb; border-color: #9ca3af; }
.gc-btn-reset:disabled { opacity: 0.4; cursor: not-allowed; }
.gc-btn-save { height: 40px; padding: 0 24px; border-radius: 8px; border: none; background: #1169cd; color: #fff; font-size: 0.875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.15s; }
.gc-btn-save:hover:not(:disabled) { background: #0d50a3; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
.gc-btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.gc-spinner { width: 16px; height: 16px; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
