<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

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

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <SchoolLayout title="Email Templates">
        <div class="page-header">
            <div>
                <h1 class="page-header-title">Email Templates</h1>
                <p class="page-header-sub">Manage reusable email templates for communication</p>
            </div>
            <Button @click="openModal">+ New Template</Button>
        </div>

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
        <div v-else class="empty-state-card">
            <div class="empty-icon">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </div>
            <h3 class="empty-title">No email templates yet</h3>
            <p class="empty-text">Create your first email template to streamline communications.</p>
            <Button @click="openModal">+ New Template</Button>
        </div>

        <!-- Create Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                <div class="modal">
                    <div class="modal-header">
                        <h2 class="modal-title">Create Email Template</h2>
                        <button class="modal-close" @click="closeModal">&times;</button>
                    </div>
                    <form @submit.prevent="submit">
                        <div class="modal-body">
                            <div class="form-field">
                                <label>Template Name</label>
                                <input
                                    type="text"
                                    v-model="form.name"
                                    placeholder="e.g. Welcome Email"
                                />
                                <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
                            </div>
                            <div class="form-field">
                                <label>Subject</label>
                                <input
                                    type="text"
                                    v-model="form.subject"
                                    placeholder="e.g. Welcome to ##SCHOOL_NAME##"
                                />
                                <span v-if="form.errors.subject" class="field-error">{{ form.errors.subject }}</span>
                            </div>
                            <div class="form-field">
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
                        </div>
                        <div class="modal-footer">
                            <Button variant="secondary" type="button" @click="closeModal">Cancel</Button>
                            <Button type="submit" :loading="form.processing">
                                Create Template
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SchoolLayout>
</template>

<style scoped>
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.page-header-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}
.page-header-sub {
    font-size: .82rem;
    color: #64748b;
    margin: 2px 0 0;
}

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

.badge {
    display: inline-flex;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    white-space: nowrap;
}
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-gray { background: #f1f5f9; color: #94a3b8; }

.empty-state-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 48px 24px;
    text-align: center;
}
.empty-icon { margin-bottom: 16px; }
.empty-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 6px;
}
.empty-text {
    font-size: .85rem;
    color: #94a3b8;
    margin: 0 0 20px;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 16px;
}
.modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}
.modal-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    padding: 0 4px;
}
.modal-close:hover { color: #1e293b; }
.modal-body { padding: 24px; }
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
}

.form-field {
    margin-bottom: 16px;
}
.form-field label {
    display: block;
    font-size: .8rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
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
