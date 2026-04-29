<script setup>
import { computed } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    settings:    { type: Object, required: true },
    paper_sizes: { type: Array,  required: true },
    max_copies:  { type: Number, required: true },
    copy_labels: { type: Array,  required: true },
});

const page = usePage();

const settingsNav = [
    { id: 'general-config',   label: 'General Config',   route: '/school/settings/general-config' },
    { id: 'asset-config',     label: 'Asset Config',     route: '/school/settings/asset-config' },
    { id: 'system-config',    label: 'System Config',    route: '/school/settings/system-config' },
    { id: 'geofence-config',  label: 'Geofence Config',  route: '/school/settings/geofence-config' },
    { id: 'admin-contacts',   label: 'Admin Numbers',    route: '/school/settings/admin-contacts' },
    { id: 'daily-report',     label: 'Daily Report',     route: '/school/settings/daily-report' },
    { id: 'receipt-print',    label: 'Receipt Print',    route: '/school/settings/receipt-print' },
];

const currentPath = computed(() => page.url);
const isActive = (route) => currentPath.value === route || currentPath.value.startsWith(route);

const PAPER_HINTS = {
    A4: '210 × 297 mm — standard office paper',
    A5: '148 × 210 mm — half of A4, fits most printers',
    A6: '105 × 148 mm — quarter of A4, compact receipt',
};

const form = useForm({
    paper_size: props.settings.paper_size,
    copies:     props.settings.copies,
});

const previewLabels = computed(() => {
    const n = Math.max(1, Math.min(form.copies || 1, props.max_copies));
    return props.copy_labels.slice(0, n);
});

const submit = () => {
    form.post('/school/settings/receipt-print', { preserveScroll: true });
};
</script>

<template>
    <SchoolLayout title="Receipt Print Settings">
        <div class="settings-shell">

            <aside class="settings-sidebar">
                <nav class="settings-sidebar-nav">
                    <Link
                        v-for="item in settingsNav"
                        :key="item.id"
                        :href="item.route"
                        class="settings-nav-item"
                        :class="{ 'settings-nav-item--active': isActive(item.route) }"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <section class="settings-content">

                <div class="info-banner">
                    These settings apply to <strong>all fee receipts</strong> — Tuition Fee, Hostel, Transport, and Stationary.
                </div>

                <form @submit.prevent="submit" novalidate>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h2 class="card-title">Paper size</h2>
                            <p class="card-sub">The page size every fee receipt PDF will use.</p>
                        </div>
                        <div class="card-body">
                            <div class="paper-grid">
                                <label
                                    v-for="size in paper_sizes"
                                    :key="size"
                                    class="paper-option"
                                    :class="{ 'paper-option--active': form.paper_size === size }"
                                >
                                    <input
                                        type="radio"
                                        :value="size"
                                        v-model="form.paper_size"
                                    />
                                    <div class="paper-option-name">{{ size }}</div>
                                    <div class="paper-option-hint">{{ PAPER_HINTS[size] }}</div>
                                </label>
                            </div>
                            <div v-if="form.errors.paper_size" class="form-error">{{ form.errors.paper_size }}</div>
                            <p class="footnote">
                                Receipts are designed for A4. A5 / A6 may cause some content to wrap — preview a sample receipt before deploying.
                            </p>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h2 class="card-title">Number of copies</h2>
                            <p class="card-sub">How many copies of each receipt to generate. Each copy is labelled and printed on its own page.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>Copies per receipt</label>
                                    <input
                                        v-model.number="form.copies"
                                        type="number"
                                        :min="1"
                                        :max="max_copies"
                                    />
                                    <small>Between 1 and {{ max_copies }}.</small>
                                    <div v-if="form.errors.copies" class="form-error">{{ form.errors.copies }}</div>
                                </div>
                                <div class="form-field">
                                    <label>Preview</label>
                                    <div class="preview-strip">
                                        <span
                                            v-for="(label, i) in previewLabels"
                                            :key="i"
                                            class="preview-chip"
                                        >{{ label }}</span>
                                    </div>
                                    <small>Each copy prints on its own page with the label above.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;">
                        <Button type="submit" :loading="form.processing">Save Settings</Button>
                    </div>
                </form>

            </section>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.settings-shell {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 56px);
    margin: -24px -28px;
    background: #f8fafc;
}
.settings-sidebar {
    width: 220px;
    min-width: 220px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    padding: 16px 0;
    flex-shrink: 0;
}
.settings-sidebar-nav { display: flex; flex-direction: column; gap: 1px; padding: 0 8px; }
.settings-nav-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    border-radius: 7px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
}
.settings-nav-item:hover { background: #f1f5f9; color: #1e293b; }
.settings-nav-item--active { background: #eff6ff; color: #1169cd; font-weight: 600; }

.settings-content { flex: 1; padding: 28px 32px; overflow-y: auto; }

.info-banner {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 10px 14px;
    border-radius: 6px;
    margin-bottom: 14px;
    font-size: .875rem;
    color: #1e3a8a;
}

.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.card-header { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
.card-title { margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a; }
.card-sub { margin: 4px 0 0; font-size: .8125rem; color: #64748b; }
.card-body { padding: 16px; }
.mb-3 { margin-bottom: 14px; }

.paper-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
}
.paper-option {
    position: relative;
    display: block;
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
    transition: border-color .15s, background .15s;
}
.paper-option:hover { background: #f8fafc; }
.paper-option--active {
    border-color: #1169cd;
    background: #eff6ff;
    box-shadow: 0 0 0 1px #1169cd inset;
}
.paper-option input[type="radio"] {
    position: absolute;
    top: 12px;
    right: 12px;
}
.paper-option-name { font-size: 1rem; font-weight: 700; color: #0f172a; }
.paper-option-hint { font-size: .75rem; color: #64748b; margin-top: 4px; }

.footnote { margin-top: 10px; font-size: .75rem; color: #64748b; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 14px;
}
.form-field label {
    display: block;
    font-size: .8125rem;
    font-weight: 500;
    color: #334155;
    margin-bottom: 4px;
}
.form-field input[type="number"] {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: .875rem;
}
.form-field small { color: #64748b; font-size: .75rem; display: block; margin-top: 3px; }
.form-error { color: #dc2626; font-size: .75rem; margin-top: 3px; }

.preview-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 7px 10px;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    min-height: 36px;
}
.preview-chip {
    background: #1e293b;
    color: #fff;
    padding: 3px 9px;
    border-radius: 4px;
    font-size: .7rem;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
}
</style>
