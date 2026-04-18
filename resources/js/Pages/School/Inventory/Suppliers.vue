<template>
  <SchoolLayout title="Suppliers">
    <div class="page-wrap">
      <!-- Header -->
      <div class="page-header">
        <div>
          <div class="breadcrumb">
            <a :href="`/school/inventory`">Inventory</a>
            <span>/</span>
            <span>Suppliers</span>
          </div>
          <h1 class="page-title">Item Suppliers</h1>
        </div>
        <div class="header-actions">
          <button class="btn btn-primary" @click="openAdd">+ Add Supplier</button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="flash flash-success">{{ $page.props.flash.success }}</div>
      <div v-if="errors.supplier" class="flash flash-error">{{ errors.supplier }}</div>

      <!-- Stats row -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-label">Total Suppliers</div>
          <div class="stat-value">{{ suppliers.length }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Linked to Assets</div>
          <div class="stat-value">{{ suppliers.filter(s => s.assets_count > 0).length }}</div>
        </div>
      </div>

      <!-- Table -->
      <div class="card">
        <div v-if="!suppliers.length" class="empty-state">No suppliers added yet.</div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Supplier Name</th>
              <th>Contact</th>
              <th>Phone / Email</th>
              <th>City</th>
              <th>GSTIN</th>
              <th class="text-right">Assets</th>
              <th class="text-right">Items</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in suppliers" :key="s.id">
              <td class="font-medium">{{ s.name }}</td>
              <td>{{ s.contact_person || '—' }}</td>
              <td>
                <div v-if="s.phone">{{ s.phone }}</div>
                <div v-if="s.email" class="text-muted text-sm">{{ s.email }}</div>
                <span v-if="!s.phone && !s.email" class="text-muted">—</span>
              </td>
              <td>{{ s.city || '—' }}</td>
              <td class="text-mono text-sm">{{ s.gstin || '—' }}</td>
              <td class="text-right">
                <span class="badge badge-blue">{{ s.assets_count }}</span>
              </td>
              <td class="text-right">
                <span class="badge badge-purple">{{ s.store_items_count }}</span>
              </td>
              <td class="text-right action-cell">
                <button class="btn btn-xs act-blue" @click="openEdit(s)">Edit</button>
                <button class="btn btn-xs act-red" :disabled="s.assets_count > 0" @click="confirmDelete(s)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
        <div class="modal-box modal-lg">
          <div class="modal-header">
            <h3>{{ editing ? 'Edit Supplier' : 'Add Supplier' }}</h3>
            <button class="modal-close" @click="showModal = false">&times;</button>
          </div>
          <form @submit.prevent="submitForm">
            <div class="modal-body">
              <div class="form-grid-2">
                <div class="form-group full-width">
                  <label>Supplier Name *</label>
                  <input v-model="form.name" class="form-control" required />
                  <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                </div>
                <div class="form-group">
                  <label>Contact Person</label>
                  <input v-model="form.contact_person" class="form-control" />
                </div>
                <div class="form-group">
                  <label>Phone</label>
                  <input v-model="form.phone" class="form-control" maxlength="20" />
                </div>
                <div class="form-group">
                  <label>Email</label>
                  <input v-model="form.email" type="email" class="form-control" />
                  <div v-if="form.errors.email" class="form-error">{{ form.errors.email }}</div>
                </div>
                <div class="form-group">
                  <label>GSTIN</label>
                  <input v-model="form.gstin" class="form-control" maxlength="20" placeholder="e.g. 29ABCDE1234F1Z5" />
                </div>
                <div class="form-group">
                  <label>City</label>
                  <input v-model="form.city" class="form-control" />
                </div>
                <div class="form-group">
                  <label>State</label>
                  <input v-model="form.state" class="form-control" />
                </div>
                <div class="form-group">
                  <label>Website</label>
                  <input v-model="form.website" class="form-control" placeholder="https://" />
                  <div v-if="form.errors.website" class="form-error">{{ form.errors.website }}</div>
                </div>
                <div class="form-group full-width">
                  <label>Address</label>
                  <textarea v-model="form.address" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group full-width">
                  <label>Notes</label>
                  <textarea v-model="form.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showModal = false">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : (editing ? 'Update' : 'Add Supplier') }}
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
            <h3>Delete Supplier</h3>
            <button class="modal-close" @click="deleteTarget = null">&times;</button>
          </div>
          <div class="modal-body">
            <p>Delete <strong>{{ deleteTarget.name }}</strong>? This cannot be undone.</p>
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
import { useForm, usePage } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'


const props = defineProps({
  suppliers: Array,
})

const { errors } = usePage().props

const showModal   = ref(false)
const editing     = ref(null)
const deleteTarget = ref(null)

const blankForm = {
  name: '', contact_person: '', phone: '', email: '',
  gstin: '', address: '', city: '', state: '', website: '', notes: '',
}

const form       = useForm({ ...blankForm })
const deleteForm = useForm({})

function openAdd() {
  editing.value = null
  form.reset()
  Object.assign(form, { ...blankForm })
  showModal.value = true
}

function openEdit(s) {
  editing.value = s
  form.name           = s.name           || ''
  form.contact_person = s.contact_person || ''
  form.phone          = s.phone          || ''
  form.email          = s.email          || ''
  form.gstin          = s.gstin          || ''
  form.address        = s.address        || ''
  form.city           = s.city           || ''
  form.state          = s.state          || ''
  form.website        = s.website        || ''
  form.notes          = s.notes          || ''
  showModal.value = true
}

function submitForm() {
  if (editing.value) {
    form.put(`/school/inventory-suppliers/${editing.value.id}`, {
      preserveScroll: true,
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post('/school/inventory-suppliers', {
      preserveScroll: true,
      onSuccess: () => { showModal.value = false },
    })
  }
}

function confirmDelete(s) {
  deleteTarget.value = s
}

function doDelete() {
  deleteForm.delete(`/school/inventory-suppliers/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { deleteTarget.value = null },
  })
}
</script>
