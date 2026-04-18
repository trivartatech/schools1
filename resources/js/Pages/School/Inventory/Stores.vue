<template>
  <SchoolLayout title="Item Stores">
    <div class="page-wrap">
      <!-- Header -->
      <div class="page-header">
        <div>
          <div class="breadcrumb">
            <a :href="`/school/inventory`">Inventory</a>
            <span>/</span>
            <span>Stores</span>
          </div>
          <h1 class="page-title">Item Stores</h1>
        </div>
        <div class="header-actions">
          <button class="btn btn-primary" @click="openAdd">+ Add Store</button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="flash flash-success">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.errors?.store" class="flash flash-error">{{ $page.props.errors.store }}</div>

      <!-- Grid of stores -->
      <div v-if="!stores.length" class="card empty-state">No stores created yet.</div>
      <div class="stores-grid">
        <div v-for="s in stores" :key="s.id" class="store-card">
          <div class="store-card-header">
            <div class="store-icon">🏪</div>
            <div class="store-meta">
              <h3 class="store-name">{{ s.name }}</h3>
              <div v-if="s.location" class="store-location">📍 {{ s.location }}</div>
            </div>
          </div>
          <div class="store-stats">
            <span class="badge badge-blue">{{ s.items_count }} item type{{ s.items_count !== 1 ? 's' : '' }}</span>
          </div>
          <div v-if="s.description" class="store-desc">{{ s.description }}</div>
          <div class="store-actions">
            <a :href="`/school/inventory-stores/${s.id}`" class="btn btn-sm btn-primary">Manage Items</a>
            <button class="btn btn-sm act-blue" @click="openEdit(s)">Edit</button>
            <button class="btn btn-sm act-red" :disabled="s.items_count > 0" @click="confirmDelete(s)">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <h3>{{ editing ? 'Edit Store' : 'Add Store' }}</h3>
            <button class="modal-close" @click="showModal = false">&times;</button>
          </div>
          <form @submit.prevent="submitForm">
            <div class="modal-body">
              <div class="form-group">
                <label>Store Name *</label>
                <input v-model="form.name" class="form-control" required />
                <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
              </div>
              <div class="form-group">
                <label>Location / Room</label>
                <input v-model="form.location" class="form-control" placeholder="e.g. Block A, Room 12" />
              </div>
              <div class="form-group">
                <label>Description</label>
                <textarea v-model="form.description" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showModal = false">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : (editing ? 'Update' : 'Create Store') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirm -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="modal-backdrop" @click.self="deleteTarget = null">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <h3>Delete Store</h3>
            <button class="modal-close" @click="deleteTarget = null">&times;</button>
          </div>
          <div class="modal-body">
            <p>Delete <strong>{{ deleteTarget.name }}</strong>? All items in this store will be removed.</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="deleteTarget = null">Cancel</button>
            <button class="btn btn-danger" :disabled="deleteForm.processing" @click="doDelete">
              {{ deleteForm.processing ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </SchoolLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'

defineProps({
  stores:    Array,
  suppliers: Array,
})

const showModal    = ref(false)
const editing      = ref(null)
const deleteTarget = ref(null)

const form       = useForm({ name: '', location: '', description: '' })
const deleteForm = useForm({})

function openAdd() {
  editing.value = null
  form.reset()
  showModal.value = true
}

function openEdit(s) {
  editing.value    = s
  form.name        = s.name        || ''
  form.location    = s.location    || ''
  form.description = s.description || ''
  showModal.value  = true
}

function submitForm() {
  if (editing.value) {
    form.put(`/school/inventory-stores/${editing.value.id}`, {
      preserveScroll: true,
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post('/school/inventory-stores', {
      preserveScroll: true,
      onSuccess: () => { showModal.value = false },
    })
  }
}

function confirmDelete(s) {
  deleteTarget.value = s
}

function doDelete() {
  deleteForm.delete(`/school/inventory-stores/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { deleteTarget.value = null },
  })
}
</script>

<style scoped>
.stores-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}
.store-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: .6rem;
}
.store-card-header {
  display: flex;
  gap: .75rem;
  align-items: flex-start;
}
.store-icon {
  font-size: 1.8rem;
  line-height: 1;
}
.store-name {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}
.store-location {
  font-size: .8rem;
  color: var(--text-muted);
}
.store-desc {
  font-size: .82rem;
  color: var(--text-secondary);
}
.store-stats {
  display: flex;
  gap: .4rem;
}
.store-actions {
  display: flex;
  gap: .4rem;
  margin-top: .25rem;
  flex-wrap: wrap;
}
</style>
