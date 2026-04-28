<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object });

const form = useForm({
    max_issue_days:    props.settings?.max_issue_days ?? 14,
    fine_per_day:      props.settings?.fine_per_day ?? 1.00,
    max_books_student: props.settings?.max_books_student ?? 3,
    max_books_staff:   props.settings?.max_books_staff ?? 5,
});

const save = () => form.post('/school/library/settings', { preserveScroll: true });
</script>

<template>
    <SchoolLayout title="Library — Settings">
        <PageHeader title="Library Settings" />
        <div class="card" style="max-width:480px;">
            <div class="card-body" style="display:flex;flex-direction:column;gap:18px;">
                <div class="form-field">
                    <label>Default Issue Period (days) *</label>
                    <input v-model="form.max_issue_days" type="number" min="1" max="365" required />
                    <span class="hint">Number of days a book can be kept before it becomes overdue.</span>
                </div>
                <div class="form-field">
                    <label>Fine per Overdue Day (₹) *</label>
                    <input v-model="form.fine_per_day" type="number" step="0.50" min="0" required />
                </div>
                <div class="form-field">
                    <label>Max Books per Student *</label>
                    <input v-model="form.max_books_student" type="number" min="1" max="50" required />
                </div>
                <div class="form-field">
                    <label>Max Books per Staff *</label>
                    <input v-model="form.max_books_staff" type="number" min="1" max="50" required />
                </div>
                <div>
                    <Button @click="save" :loading="form.processing">Save Settings</Button>
                </div>
            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.hint { font-size:.75rem;color:#94a3b8;margin-top:4px; }
</style>
