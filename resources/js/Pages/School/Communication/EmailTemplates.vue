<script setup>
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();

defineProps({
    templates: Array,
});

const showModal = ref(false);

const form = useForm({
    name: '',
    subject: '',
    content: '',
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const submit = () => {
    form.post(route('school.communication.email-templates.store'), {
        onSuccess: () => {
            closeModal();
        },
    });
};

const truncate = (text, length = 100) => {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
};

const formatDate = (dateStr) => school.fmtDate(dateStr);
</script>

<template>
    <SchoolLayout title="Email Templates">
        <PageHeader title="Email Templates" subtitle="Manage reusable email templates for communication">
            <template #actions>
                <Button @click="openModal">+ New Template</Button>
            </template>
        </PageHeader>

        <!-- Template List -->
        <div v-if="templates && templates.length > 0" class="templates-grid">
            <div v-for="tpl in templates" :key="tpl.id" class="template-card">
                <div class="template-card-header">
                    <h3 class="template-name">{{ tpl.name }}</h3>
                    <span
                        class="badge"
                        :class="tpl.is_active ? 'badge-green' : 'badge-gray'"
                    >
                        {{ tpl.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="template-subject">{{ tpl.subject }}</div>
                <p class="template-preview">{{ truncate(tpl.content) }}</p>
                <div class="template-footer">
                    <span class="template-date">Created {{ formatDate(tpl.created_at) }}</span>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="card">
            <EmptyState
                title="No email templates yet"
                description="Create your first email template to streamline communications."
                action-label="+ New Template"
                @action="openModal"
            />
        </div>

        <!-- Create Modal -->
        <Modal v-model:open="showModal" title="Create Email Template" size="lg">
            <form @submit.prevent="submit" id="email-tpl-form">
                <div class="form-field">
                    <label>Template Name</label>
                    <input
                        type="text"
                        v-model="form.name"
                        placeholder="e.g. Welcome Email"
                    />
                    <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
                </div>
                <div class="form-field" style="margin-top:14px;">
                    <label>Subject</label>
                    <input
                        type="text"
                        v-model="form.subject"
                        placeholder="e.g. Welcome to ##SCHOOL_NAME##"
                    />
                    <span v-if="form.errors.subject" class="field-error">{{ form.errors.subject }}</span>
                </div>
                <div class="form-field" style="margin-top:14px;">
                    <label>Content</label>
                    <textarea
                        v-model="form.content"
                        rows="8"
                        placeholder="Write your email content here..."
                    ></textarea>
                    <span v-if="form.errors.content" class="field-error">{{ form.errors.content }}</span>
                    <div class="variables-hint">
                        <span class="hint-label">Available variables:</span>
                        <code>##STUDENT_NAME##</code>
                        <code>##PARENT_NAME##</code>
                        <code>##SCHOOL_NAME##</code>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="closeModal">Cancel</Button>
                <Button type="submit" form="email-tpl-form" :loading="form.processing">
                    Create Template
                </Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.template-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
}
.template-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
    gap: 8px;
}
.template-name {
    font-size: .95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}
.template-subject {
    font-size: .82rem;
    color: #1169cd;
    font-weight: 600;
    margin-bottom: 8px;
}
.template-preview {
    font-size: .8rem;
    color: #64748b;
    line-height: 1.5;
    margin: 0 0 12px;
    flex: 1;
}
.template-footer {
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
}
.template-date {
    font-size: .72rem;
    color: #94a3b8;
}

/* Modal form fields */
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label {
    display: block;
    font-size: .8rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}
.form-field input,
.form-field textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: .85rem;
    color: #1e293b;
    background: #fff;
    transition: border-color .15s;
    font-family: inherit;
    box-sizing: border-box;
}
.form-field input:focus,
.form-field textarea:focus {
    outline: none;
    border-color: #1169cd;
    box-shadow: 0 0 0 3px rgba(17, 105, 205, .1);
}
.form-field textarea { resize: vertical; }
.field-error {
    display: block;
    font-size: .75rem;
    color: #ef4444;
    margin-top: 4px;
}

.variables-hint {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.hint-label {
    font-size: .72rem;
    color: #94a3b8;
    font-weight: 600;
}
.variables-hint code {
    font-size: .7rem;
    padding: 2px 8px;
    background: #f1f5f9;
    border-radius: 4px;
    color: #1169cd;
    font-weight: 600;
}
</style>
