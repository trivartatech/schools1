<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({
    contacts: { type: Array, default: () => [] },
});

const page = usePage();

const settingsNav = [
    { id: 'general-config',  label: 'General Config',  route: '/school/settings/general-config',  icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'asset-config',    label: 'Asset Config',    route: '/school/settings/asset-config',    icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'system-config',   label: 'System Config',   route: '/school/settings/system-config',   icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18' },
    { id: 'geofence-config', label: 'Geofence Config', route: '/school/settings/geofence-config', icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z' },
    { id: 'admin-contacts',  label: 'Admin Numbers',   route: '/school/settings/admin-contacts',  icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
    { id: 'daily-report',    label: 'Daily Report',    route: '/school/settings/daily-report',    icon: 'M9 17v-2a4 4 0 014-4h4M3 7h18M3 12h12M3 17h6' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

// ── Add new contact form ────────────────────────────────────────────────
const showAddForm = ref(false);

const addForm = useForm({
    name: '',
    phone: '',
    whatsapp_number: '',
});

const submitAdd = () => {
    addForm.post('/school/settings/admin-contacts', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
};

const cancelAdd = () => {
    addForm.reset();
    addForm.clearErrors();
    showAddForm.value = false;
};

// ── Per-row editing ─────────────────────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name: '',
    phone: '',
    whatsapp_number: '',
});

const startEdit = (contact) => {
    editingId.value = contact.id;
    editForm.name = contact.name;
    editForm.phone = contact.phone;
    editForm.whatsapp_number = contact.whatsapp_number ?? '';
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = (id) => {
    editForm.put(`/school/settings/admin-contacts/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
};

const deleteContact = async (contact) => {
    const ok = await confirm({
        title: 'Delete admin contact?',
        message: `"${contact.name}" will no longer receive admin alerts.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/settings/admin-contacts/${contact.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <SchoolLayout title="Admin Numbers">
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

                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                        <div>
                            <h2 class="card-title">Admin Numbers</h2>
                            <p style="font-size:.775rem;color:#64748b;margin:2px 0 0;">Contacts that receive system notifications.</p>
                        </div>
                        <Button v-if="!showAddForm" type="button" @click="showAddForm = true">
                            + Add Contact
                        </Button>
                    </div>

                    <!-- Add form -->
                    <div v-if="showAddForm" class="card-body" style="border-top:1px solid #e2e8f0;background:#f8fafc;">
                        <form @submit.prevent="submitAdd" novalidate>
                            <div class="form-row form-row-3">
                                <div class="form-field">
                                    <label>Name</label>
                                    <input v-model="addForm.name" type="text" placeholder="e.g. Vandana" required />
                                    <div v-if="addForm.errors.name" class="form-error">{{ addForm.errors.name }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Phone</label>
                                    <input v-model="addForm.phone" type="tel" placeholder="e.g. 9999999999" required />
                                    <div v-if="addForm.errors.phone" class="form-error">{{ addForm.errors.phone }}</div>
                                </div>
                                <div class="form-field">
                                    <label>WhatsApp Number</label>
                                    <input v-model="addForm.whatsapp_number" type="tel" placeholder="Optional" />
                                    <div v-if="addForm.errors.whatsapp_number" class="form-error">{{ addForm.errors.whatsapp_number }}</div>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;margin-top:14px;">
                                <Button type="submit" :loading="addForm.processing">Save Contact</Button>
                                <Button variant="secondary" type="button" @click="cancelAdd">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <!-- Contacts list -->
                    <div class="card-body" style="padding:0;">
                        <div v-if="contacts.length === 0" style="padding:32px;text-align:center;color:#64748b;font-size:.875rem;">
                            No admin contacts yet. Click <strong>+ Add Contact</strong> to add one.
                        </div>
                        <table v-else class="contacts-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>WhatsApp</th>
                                    <th style="width:160px;text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="contact in contacts" :key="contact.id">
                                    <!-- View row -->
                                    <tr v-if="editingId !== contact.id">
                                        <td>{{ contact.name }}</td>
                                        <td>{{ contact.phone }}</td>
                                        <td>{{ contact.whatsapp_number || '—' }}</td>
                                        <td style="text-align:right;">
                                            <button type="button" class="row-action" @click="startEdit(contact)">Edit</button>
                                            <button type="button" class="row-action row-action--danger" @click="deleteContact(contact)">Delete</button>
                                        </td>
                                    </tr>

                                    <!-- Edit row -->
                                    <tr v-else class="edit-row">
                                        <td>
                                            <input v-model="editForm.name" type="text" />
                                            <div v-if="editForm.errors.name" class="form-error">{{ editForm.errors.name }}</div>
                                        </td>
                                        <td>
                                            <input v-model="editForm.phone" type="tel" />
                                            <div v-if="editForm.errors.phone" class="form-error">{{ editForm.errors.phone }}</div>
                                        </td>
                                        <td>
                                            <input v-model="editForm.whatsapp_number" type="tel" />
                                            <div v-if="editForm.errors.whatsapp_number" class="form-error">{{ editForm.errors.whatsapp_number }}</div>
                                        </td>
                                        <td style="text-align:right;">
                                            <button type="button" class="row-action row-action--primary" :disabled="editForm.processing" @click="submitEdit(contact.id)">Save</button>
                                            <button type="button" class="row-action" @click="cancelEdit">Cancel</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

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

/* ── Contacts table ── */
.contacts-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.contacts-table thead th {
    text-align: left;
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.contacts-table tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}
.contacts-table tbody tr:last-child td {
    border-bottom: none;
}
.contacts-table .edit-row td {
    background: #fffbea;
}
.contacts-table .edit-row input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.875rem;
}

.row-action {
    background: none;
    border: 1px solid transparent;
    padding: 4px 10px;
    margin-left: 6px;
    border-radius: 6px;
    font-size: 0.8125rem;
    color: #475569;
    cursor: pointer;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.row-action:hover {
    background: #f1f5f9;
    color: #1e293b;
}
.row-action--primary {
    color: #1169cd;
    border-color: #bfdbfe;
    background: #eff6ff;
}
.row-action--primary:hover {
    background: #dbeafe;
}
.row-action--danger {
    color: #dc2626;
}
.row-action--danger:hover {
    background: #fee2e2;
    color: #b91c1c;
}
.row-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
