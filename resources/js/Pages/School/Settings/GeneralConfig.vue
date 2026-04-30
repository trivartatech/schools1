<script setup>
import Button from '@/Components/ui/Button.vue';
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
    { id: 'attendance-timings', label: 'Attendance Timings',          route: '/school/settings/attendance-timings', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'admin-contacts',     label: 'Admin Numbers',               route: '/school/settings/admin-contacts',  icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
    { id: 'receipt-print',      label: 'Receipt Print',               route: '/school/settings/receipt-print',   icon: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

// ── Form ─────────────────────────────────────────────────────────────────
const s = props.settings;
const sch = props.school;

const form = useForm({
    // School identity (was Profile.vue)
    name:                sch.name              ?? '',
    code:                sch.code              ?? '',
    board:               sch.board             ?? 'CBSE',
    affiliation_no:      sch.affiliation_no    ?? '',
    udise_code:          sch.udise_code        ?? '',
    principal_name:      sch.principal_name    ?? '',
    // App / meta
    app_name:            s.app_name            ?? sch.name   ?? '',
    app_description:     s.app_description     ?? '',
    meta_author:         s.meta_author         ?? '',
    meta_description:    s.meta_description    ?? '',
    meta_keywords:       s.meta_keywords       ?? '',
    // Address
    address_line1:       s.address_line1       ?? '',
    address_line2:       s.address_line2       ?? '',
    city:                sch.city              ?? '',
    state:               sch.state             ?? '',
    zipcode:             s.zipcode             ?? sch.pincode ?? '',
    country:             s.country             ?? '',
    latitude:            s.latitude            ?? '',
    longitude:           s.longitude           ?? '',
    // Contact
    email:               sch.email             ?? '',
    phone:               sch.phone             ?? '',
    fax:                 s.fax                 ?? '',
    website:             sch.website           ?? '',
    // Financial
    financial_year_code: s.financial_year_code ?? '',
});

const submit = () => {
    form.post('/school/settings/general-config', { preserveScroll: true });
};

const reset = () => form.reset();
</script>

<template>
    <SchoolLayout title="General Config">
        <div class="settings-shell">

            <!-- ── Settings Sidebar ─────────────────────────────────── -->
            <aside class="settings-sidebar">
                <nav class="settings-sidebar-nav">
                    <Link
                        v-for="item in settingsNav"
                        :key="item.id"
                        :href="item.route"
                        class="settings-nav-item"
                        :class="{ 'settings-nav-item--active': isActive(item.route) }"
                    >
                        <svg class="settings-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>
            </aside>

            <!-- ── Main Content ─────────────────────────────────────── -->
            <section class="settings-content">
                <form @submit.prevent="submit" novalidate>

                    <!-- ── School Identity ──────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">General Configuration</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">This information will be displayed publicly so be careful what you share.</p>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-row form-row-2">
                                <div class="form-field">
                                    <label>App Name</label>
                                    <input v-model="form.app_name" type="text" id="app_name" />
                                    <div v-if="form.errors.app_name" class="form-error">{{ form.errors.app_name }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Description</label>
                                    <input v-model="form.app_description" type="text" id="app_description" />
                                    <div v-if="form.errors.app_description" class="form-error">{{ form.errors.app_description }}</div>
                                </div>
                            </div>
                            <div class="form-row form-row-2">
                                <div class="form-field">
                                    <label>School Name</label>
                                    <input v-model="form.name" type="text" id="school_name" required />
                                    <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                                </div>
                                <div class="form-field">
                                    <label>School Code / Slug</label>
                                    <input v-model="form.code" type="text" id="school_code" />
                                    <div v-if="form.errors.code" class="form-error">{{ form.errors.code }}</div>
                                </div>
                            </div>
                            <div class="form-row form-row-3">
                                <div class="form-field">
                                    <label>Board Type</label>
                                    <select v-model="form.board" id="board">
                                        <option value="CBSE">CBSE</option>
                                        <option value="ICSE">ICSE</option>
                                        <option value="State">State Board</option>
                                    </select>
                                    <div v-if="form.errors.board" class="form-error">{{ form.errors.board }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Affiliation No.</label>
                                    <input v-model="form.affiliation_no" type="text" id="affiliation_no" placeholder="Optional" />
                                    <div v-if="form.errors.affiliation_no" class="form-error">{{ form.errors.affiliation_no }}</div>
                                </div>
                                <div class="form-field">
                                    <label>UDISE Code</label>
                                    <input v-model="form.udise_code" type="text" id="udise_code" placeholder="Optional" />
                                    <div v-if="form.errors.udise_code" class="form-error">{{ form.errors.udise_code }}</div>
                                </div>
                            </div>
                            <div class="form-row" style="max-width:400px;">
                                <div class="form-field">
                                    <label>Principal Name</label>
                                    <input v-model="form.principal_name" type="text" id="principal_name" placeholder="Principal" />
                                    <div v-if="form.errors.principal_name" class="form-error">{{ form.errors.principal_name }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── App / Meta ───────────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">App &amp; Meta</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">Used for SEO and browser tab display.</p>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-field">
                                <label>Meta Author</label>
                                <input v-model="form.meta_author" type="text" id="meta_author" />
                            </div>
                            <div class="form-field">
                                <label>Meta Description</label>
                                <input v-model="form.meta_description" type="text" id="meta_description" />
                            </div>
                            <div class="form-field">
                                <label>Meta Keywords</label>
                                <input v-model="form.meta_keywords" type="text" id="meta_keywords" />
                            </div>
                        </div>
                    </div>

                    <!-- ── Address ──────────────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">Address</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">This address will be displayed publicly.</p>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-row form-row-3">
                                <div class="form-field">
                                    <label>Address Line 1</label>
                                    <input v-model="form.address_line1" type="text" id="address_line1" />
                                    <div v-if="form.errors.address_line1" class="form-error">{{ form.errors.address_line1 }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Address Line 2</label>
                                    <input v-model="form.address_line2" type="text" id="address_line2" />
                                    <div v-if="form.errors.address_line2" class="form-error">{{ form.errors.address_line2 }}</div>
                                </div>
                                <div class="form-field">
                                    <label>City</label>
                                    <input v-model="form.city" type="text" id="city" />
                                    <div v-if="form.errors.city" class="form-error">{{ form.errors.city }}</div>
                                </div>
                            </div>
                            <div class="form-row form-row-3">
                                <div class="form-field">
                                    <label>State</label>
                                    <input v-model="form.state" type="text" id="state" />
                                    <div v-if="form.errors.state" class="form-error">{{ form.errors.state }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Zipcode</label>
                                    <input v-model="form.zipcode" type="text" id="zipcode" />
                                    <div v-if="form.errors.zipcode" class="form-error">{{ form.errors.zipcode }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Country</label>
                                    <input v-model="form.country" type="text" id="country" />
                                    <div v-if="form.errors.country" class="form-error">{{ form.errors.country }}</div>
                                </div>
                            </div>
                            <!-- GPS Coordinates -->
                            <div class="form-row form-row-2">
                                <div class="form-field">
                                    <label>Latitude <span style="font-size:0.75rem;color:#94a3b8;font-weight:400;">(e.g. 28.6139)</span></label>
                                    <input v-model="form.latitude" type="number" step="any" id="latitude" placeholder="e.g. 28.6139" />
                                    <div v-if="form.errors.latitude" class="form-error">{{ form.errors.latitude }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Longitude <span style="font-size:0.75rem;color:#94a3b8;font-weight:400;">(e.g. 77.2090)</span></label>
                                    <input v-model="form.longitude" type="number" step="any" id="longitude" placeholder="e.g. 77.2090" />
                                    <div v-if="form.errors.longitude" class="form-error">{{ form.errors.longitude }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Contact Details ──────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">Contact Details</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">This contact details will be displayed publicly.</p>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="form-row form-row-3">
                                <div class="form-field">
                                    <label>Email</label>
                                    <input v-model="form.email" type="email" id="email" />
                                    <div v-if="form.errors.email" class="form-error">{{ form.errors.email }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Phone</label>
                                    <input v-model="form.phone" type="tel" id="phone" />
                                    <div v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Fax</label>
                                    <input v-model="form.fax" type="text" id="fax" />
                                    <div v-if="form.errors.fax" class="form-error">{{ form.errors.fax }}</div>
                                </div>
                            </div>
                            <div class="form-field" style="max-width:400px;">
                                <label>Website</label>
                                <input v-model="form.website" type="url" id="website" />
                                <div v-if="form.errors.website" class="form-error">{{ form.errors.website }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Financial Year ───────────────────────────── -->
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <h2 class="card-title">Financial Year</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">Used to identify the financial year of the organisation.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-field" style="max-width:360px;">
                                <label>Financial Year Code</label>
                                <input v-model="form.financial_year_code" type="text" id="financial_year_code" />
                                <div v-if="form.errors.financial_year_code" class="form-error">{{ form.errors.financial_year_code }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Actions ──────────────────────────────────── -->
                    <div style="display:flex;align-items:center;gap:10px;padding:16px 0 8px;">
                        <Button variant="secondary" type="button" @click="reset">Reset</Button>
                        <Button type="submit" :loading="form.processing">
                            <svg v-if="form.processing" style="width:14px;height:14px;animation:spin .8s linear infinite;" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Save Changes
                        </Button>
                    </div>

                </form>
            </section>
        </div>
    </SchoolLayout>
</template>

<style scoped>
/* ── Shell ── */
.settings-shell {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 56px);
    margin: -24px -28px;
    background: #f8fafc;
}

/* ── Settings Sidebar ── */
.settings-sidebar {
    width: 220px;
    min-width: 220px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    padding: 16px 0;
    flex-shrink: 0;
    overflow-y: auto;
}

.settings-sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 0 8px;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 10px;
    border-radius: 7px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    transition: background 0.13s, color 0.13s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}
.settings-nav-item:hover {
    background: #f1f5f9;
    color: #1e293b;
}
.settings-nav-item--active {
    background: #eff6ff !important;
    color: #1169cd !important;
    font-weight: 600;
}
.settings-nav-icon {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    opacity: 0.75;
}
.settings-nav-item--active .settings-nav-icon {
    opacity: 1;
}

/* ── Content ── */
.settings-content {
    flex: 1;
    padding: 28px 32px;
    overflow-y: auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
