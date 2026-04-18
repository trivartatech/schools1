<template>
  <SchoolLayout title="Store Detail">
    <div class="page-wrap">
      <!-- Header -->
      <div class="page-header">
        <div>
          <div class="breadcrumb">
            <a href="/school/inventory">Inventory</a>
            <span>/</span>
            <a href="/school/inventory-stores">Stores</a>
            <span>/</span>
            <span>{{ store.name }}</span>
          </div>
          <h1 class="page-title">{{ store.name }}</h1>
          <div v-if="store.location" class="page-subtitle">📍 {{ store.location }}</div>
        </div>
        <div class="header-actions">
          <button class="btn btn-primary" @click="openAddItem">+ Add Item</button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="flash flash-success">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.errors?.quantity" class="flash flash-error">{{ $page.props.errors.quantity }}</div>

      <!-- Low stock banner -->
      <div v-if="lowStockItems.length" class="alert-banner alert-warning">
        ⚠ <strong>{{ lowStockItems.length }}</strong> item{{ lowStockItems.length > 1 ? 's' : '' }} below minimum stock:
        {{ lowStockItems.map(i => i.name).join(', ') }}
      </div>

      <!-- Items Table -->
      <div class="card">
        <div v-if="!store.items?.length" class="empty-state">No items in this store yet.</div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Item Name</th>
              <th>Supplier</th>
              <th class="text-right">Qty</th>
              <th>Unit</th>
              <th class="text-right">Min Stock</th>
              <th class="text-right">Unit Price</th>
              <th class="text-right">Stock Value</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in store.items" :key="item.id" :class="{ 'row-warning': isLow(item) }">
              <td>
                <span class="font-medium">{{ item.name }}</span>
                <span v-if="isLow(item)" class="badge badge-red ml-1">Low</span>
              </td>
              <td>{{ item.supplier?.name || '—' }}</td>
              <td class="text-right font-medium">{{ fmtQty(item.quantity) }}</td>
              <td>{{ item.unit }}</td>
              <td class="text-right text-muted">{{ item.min_quantity > 0 ? fmtQty(item.min_quantity) : '—' }}</td>
              <td class="text-right">{{ item.unit_price > 0 ? '₹' + fmt(item.unit_price) : '—' }}</td>
              <td class="text-right">{{ item.unit_price > 0 ? '₹' + fmt(item.quantity * item.unit_price) : '—' }}</td>
              <td class="text-right action-cell">
                <button class="btn btn-xs act-green" @click="openTxn(item, 'in')" title="Stock In">In</button>
                <button class="btn btn-xs act-orange" @click="openTxn(item, 'out')" title="Stock Out">Out</button>
                <button class="btn btn-xs act-blue" @click="openEditItem(item)">Edit</button>
                <button class="btn btn-xs act-red" @click="confirmDeleteItem(item)">Del</button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="6" class="text-right font-medium">Total Stock Value</td>
              <td class="text-right font-bold">₹{{ fmt(totalValue) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Add / Edit Item Modal -->
    <Teleport to="body">
      <div v-if="showItemModal" class="modal-backdrop" @click.self="showItemModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <h3>{{ editingItem ? 'Edit Item' : 'Add Item' }}</h3>
            <button class="modal-close" @click="showItemModal = false">&times;</button>
          </div>
          <form @submit.prevent="submitItemForm">
            <div class="modal-body">
              <div class="form-group">
                <label>Item Name *</label>
                <input v-model="itemForm.name" class="form-control" required />
              </div>
              <div class="form-grid-2">
                <div class="form-group">
                  <label>Unit *</label>
                  <input v-model="itemForm.unit" class="form-control" placeholder="pcs / kg / L / box" required />
                </div>
                <div class="form-group">
                  <label>Supplier</label>
                  <select v-model="itemForm.supplier_id" class="form-control">
                    <option value="">— None —</option>
                    <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                  </select>
                </div>
                <div class="form-group" v-if="!editingItem">
                  <label>Opening Qty</label>
                  <input v-model="itemForm.quantity" type="number" min="0" step="0.01" class="form-control" />
                </div>
                <div class="form-group">
                  <label>Min Stock (alert threshold)</label>
                  <input v-model="itemForm.min_quantity" type="number" min="0" step="0.01" class="form-control" />
                </div>
                <div class="form-group">
                  <label>Unit Price (₹)</label>
                  <input v-model="itemForm.unit_price" type="number" min="0" step="0.01" class="form-control" />
                </div>
              </div>
              <div class="form-group">
                <label>Notes</label>
                <textarea v-model="itemForm.notes" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showItemModal = false">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="itemForm.processing">
                {{ itemForm.processing ? 'Saving…' : (editingItem ? 'Update' : 'Add Item') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Stock In / Out Modal -->
    <Teleport to="body">
      <div v-if="txnTarget" class="modal-backdrop" @click.self="txnTarget = null">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <h3>Stock {{ txnForm.type === 'in' ? 'In' : 'Out' }} — {{ txnTarget.name }}</h3>
            <button class="modal-close" @click="txnTarget = null">&times;</button>
          </div>
          <form @submit.prevent="submitTxn">
            <div class="modal-body">
              <div class="txn-current">
                Current stock: <strong>{{ fmtQty(txnTarget.quantity) }} {{ txnTarget.unit }}</strong>
              </div>
              <div class="form-group">
                <label>Quantity *</label>
                <input v-model="txnForm.quantity" type="number" min="0.01" step="0.01" class="form-control" required />
              </div>
              <div class="form-group">
                <label>Date *</label>
                <input v-model="txnForm.transaction_date" type="date" class="form-control" required />
              </div>
              <div class="form-group">
                <label>Reference</label>
                <input v-model="txnForm.reference" class="form-control" placeholder="PO no., invoice, etc." />
              </div>
              <div class="form-group">
                <label>Notes</label>
                <textarea v-model="txnForm.notes" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="txnTarget = null">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="txnForm.processing">
                {{ txnForm.processing ? 'Saving…' : 'Confirm' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete item confirm -->
    <Teleport to="body">
      <div v-if="deleteItemTarget" class="modal-backdrop" @click.self="deleteItemTarget = null">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <h3>Delete Item</h3>
            <button class="modal-close" @click="deleteItemTarget = null">&times;</button>
          </div>
          <div class="modal-body">
            <p>Delete <strong>{{ deleteItemTarget.name }}</strong> and all its transaction history?</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="deleteItemTarget = null">Cancel</button>
            <button class="btn btn-danger" :disabled="deleteItemForm.processing" @click="doDeleteItem">
              {{ deleteItemForm.processing ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </SchoolLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'

const props = defineProps({
  store:     Object,
  suppliers: Array,
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmt    = (n) => Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtQty = (n) => Number(n).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
const isLow  = (item) => Number(item.min_quantity) > 0 && Number(item.quantity) <= Number(item.min_quantity)

const lowStockItems = computed(() => (props.store.items || []).filter(isLow))
const totalValue    = computed(() =>
  (props.store.items || []).reduce((sum, i) => sum + Number(i.quantity) * Number(i.unit_price), 0)
)

// ── Item CRUD ────────────────────────────────────────────────────────────────
const showItemModal = ref(false)
const editingItem   = ref(null)

const itemForm = useForm({
  name: '', unit: 'pcs', supplier_id: '', quantity: '', min_quantity: '', unit_price: '', notes: '',
})

function openAddItem() {
  editingItem.value = null
  itemForm.reset()
  itemForm.unit = 'pcs'
  showItemModal.value = true
}

function openEditItem(item) {
  editingItem.value       = item
  itemForm.name           = item.name           || ''
  itemForm.unit           = item.unit           || 'pcs'
  itemForm.supplier_id    = item.supplier_id    || ''
  itemForm.min_quantity   = item.min_quantity   || ''
  itemForm.unit_price     = item.unit_price     || ''
  itemForm.notes          = item.notes          || ''
  showItemModal.value     = true
}

function submitItemForm() {
  if (editingItem.value) {
    itemForm.put(`/school/inventory-stores/items/${editingItem.value.id}`, {
      preserveScroll: true,
      onSuccess: () => { showItemModal.value = false },
    })
  } else {
    itemForm.post(`/school/inventory-stores/${props.store.id}/items`, {
      preserveScroll: true,
      onSuccess: () => { showItemModal.value = false },
    })
  }
}

// ── Delete item ──────────────────────────────────────────────────────────────
const deleteItemTarget = ref(null)
const deleteItemForm   = useForm({})

function confirmDeleteItem(item) {
  deleteItemTarget.value = item
}

function doDeleteItem() {
  deleteItemForm.delete(`/school/inventory-stores/items/${deleteItemTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { deleteItemTarget.value = null },
  })
}

// ── Transactions ─────────────────────────────────────────────────────────────
const txnTarget = ref(null)
const txnForm   = useForm({
  type: 'in', quantity: '', transaction_date: '', reference: '', notes: '',
})

function openTxn(item, type) {
  txnTarget.value            = item
  txnForm.type               = type
  txnForm.quantity           = ''
  txnForm.transaction_date   = new Date().toISOString().slice(0, 10)
  txnForm.reference          = ''
  txnForm.notes              = ''
}

function submitTxn() {
  txnForm.post(`/school/inventory-stores/items/${txnTarget.value.id}/transaction`, {
    preserveScroll: true,
    onSuccess: () => { txnTarget.value = null },
  })
}
</script>

<style scoped>
.txn-current {
  background: var(--bg-subtle, #f4f4f5);
  border-radius: 6px;
  padding: .5rem .75rem;
  margin-bottom: .75rem;
  font-size: .875rem;
}
.row-warning td {
  background: rgba(245, 158, 11, .06);
}
.alert-banner {
  padding: .65rem 1rem;
  border-radius: 8px;
  font-size: .875rem;
  margin-bottom: 1rem;
}
.alert-warning {
  background: #fffbeb;
  border: 1px solid #f59e0b;
  color: #92400e;
}
</style>
