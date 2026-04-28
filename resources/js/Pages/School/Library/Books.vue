<script setup>
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Table from '@/Components/ui/Table.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';

const confirm = useConfirm();

const props = defineProps({
    books: Object,
    categories: Array,
    filters: Object,
});

// ── Filters ───────────────────────────────────────────────────────
const search   = ref(props.filters?.search ?? '');
const category = ref(props.filters?.category ?? '');

const applyFilters = () => {
    router.get('/school/library/books', { search: search.value, category: category.value }, { preserveScroll: true, replace: true });
};

const clearFilters = () => {
    search.value = '';
    category.value = '';
    applyFilters();
};

// ── Add Book Modal ────────────────────────────────────────────────
const showAdd  = ref(false);
const editBook = ref(null);

const form = useForm({
    title: '', author: '', isbn: '', publisher: '', publish_year: '',
    category: '', subject: '', language: 'English', location: '',
    total_copies: 1, price: '', description: '', barcode: '',
});

const openAdd = () => { form.reset(); editBook.value = null; showAdd.value = true; };
const openEdit = (b) => {
    editBook.value = b;
    Object.keys(form).forEach(k => { if (k in b) form[k] = b[k] ?? ''; });
    showAdd.value = true;
};
const closeModal = () => { showAdd.value = false; editBook.value = null; };

const submitBook = () => {
    if (editBook.value) {
        form.put(`/school/library/books/${editBook.value.id}`, { preserveScroll: true, onSuccess: closeModal });
    } else {
        form.post('/school/library/books', { preserveScroll: true, onSuccess: closeModal });
    }
};

const deleteBook = async (id) => {
    const ok = await confirm({
        title: 'Remove book?',
        message: 'This book will be removed from the catalog.',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/library/books/${id}`, { preserveScroll: true });
};
</script>

<template>
    <SchoolLayout title="Library — Books">
        <PageHeader title="Book Catalog" subtitle="Manage your library's book inventory.">
            <template #actions>
                <Button @click="openAdd">+ Add Book</Button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <FilterBar :active="!!(search || category)" @clear="clearFilters">
            <div class="fb-search">
                <svg class="fb-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input v-model="search" @input="applyFilters" type="text" placeholder="Search title, author, ISBN..." />
            </div>
            <select v-model="category" @change="applyFilters" style="width:180px;">
                <option value="">All Categories</option>
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
        </FilterBar>

        <!-- Books Table -->
        <div class="card">
            <Table :empty="!books.data?.length">
                <thead>
                    <tr>
                        <th>Title / Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in books.data" :key="b.id">
                        <td>
                            <div style="font-weight:500;">{{ b.title }}</div>
                            <div style="font-size:.75rem;color:#94a3b8;">{{ b.author }}</div>
                        </td>
                        <td style="font-family:monospace;font-size:.8rem;">{{ b.isbn || '—' }}</td>
                        <td>{{ b.category || '—' }}</td>
                        <td>{{ b.location || '—' }}</td>
                        <td style="text-align:center;">{{ b.total_copies }}</td>
                        <td style="text-align:center;">
                            <span :style="{ color: b.available_copies > 0 ? '#16a34a' : '#dc2626', fontWeight: 600 }">
                                {{ b.available_copies }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <Button variant="secondary" size="xs" @click="openEdit(b)">Edit</Button>
                                <Button variant="danger" size="xs" @click="deleteBook(b.id)">Delete</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #empty>
                    <EmptyState
                        title="No books found"
                        description="Add books to the catalog to get started."
                        action-label="+ Add Book"
                        @action="openAdd"
                    />
                </template>
            </Table>
            <!-- Pagination -->
            <div v-if="books.last_page > 1" class="card-footer" style="display:flex;justify-content:center;gap:8px;padding:12px;">
                <a v-for="link in books.links" :key="link.label"
                   :href="link.url" v-html="link.label"
                   :class="['page-link', { 'active': link.active, 'disabled': !link.url }]" />
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Modal v-model:open="showAdd" :title="editBook ? 'Edit Book' : 'Add Book to Catalog'" size="lg">
            <form @submit.prevent="submitBook" id="book-form">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Title *</label>
                        <input v-model="form.title" required />
                        <span v-if="form.errors.title" class="error-text">{{ form.errors.title }}</span>
                    </div>
                    <div class="form-field">
                        <label>Author</label>
                        <input v-model="form.author" />
                    </div>
                    <div class="form-field">
                        <label>ISBN</label>
                        <input v-model="form.isbn" />
                    </div>
                    <div class="form-field">
                        <label>Publisher</label>
                        <input v-model="form.publisher" />
                    </div>
                    <div class="form-field">
                        <label>Publish Year</label>
                        <input v-model="form.publish_year" type="number" min="1800" />
                    </div>
                    <div class="form-field">
                        <label>Category</label>
                        <input v-model="form.category" />
                    </div>
                    <div class="form-field">
                        <label>Subject</label>
                        <input v-model="form.subject" />
                    </div>
                    <div class="form-field">
                        <label>Location / Shelf</label>
                        <input v-model="form.location" />
                    </div>
                    <div class="form-field">
                        <label>Total Copies *</label>
                        <input v-model="form.total_copies" type="number" min="1" required />
                    </div>
                    <div class="form-field">
                        <label>Price (₹)</label>
                        <input v-model="form.price" type="number" step="0.01" min="0" />
                    </div>
                    <div class="form-field">
                        <label>Barcode</label>
                        <input v-model="form.barcode" />
                    </div>
                    <div class="form-field" style="grid-column:1/-1;">
                        <label>Description</label>
                        <textarea v-model="form.description" rows="2"></textarea>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" type="button" @click="closeModal">Cancel</Button>
                <Button type="submit" form="book-form" :loading="form.processing">{{ editBook ? 'Update' : 'Add Book' }}</Button>
            </template>
        </Modal>
    </SchoolLayout>
</template>

<style scoped>
.page-link { padding:4px 8px;border:1px solid #e2e8f0;border-radius:4px;font-size:.8rem;text-decoration:none;color:#374151; }
.page-link.active { background:#3b82f6;color:#fff;border-color:#3b82f6; }
.page-link.disabled { opacity:.4;pointer-events:none; }

/* Form fields — Tailwind preflight workaround */
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: #374151; }
.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: #fff;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field textarea { min-height: 60px; resize: vertical; }
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.error-text { font-size: 0.75rem; color: #dc2626; }
</style>
