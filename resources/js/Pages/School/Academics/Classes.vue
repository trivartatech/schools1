<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import Sortable from 'sortablejs'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import SlidePanel from '@/Components/SlidePanel.vue'
import { useDelete } from '@/Composables/useDelete'
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ classes: Array, departments: Array, staff: Array })
const localClasses = ref(props.classes.map((c, i) => ({ ...c, _order: i + 1 })))

// Keep local list in sync after Inertia reloads props
watch(() => props.classes, (v) => {
    localClasses.value = v.map((c, i) => ({ ...c, _order: i + 1 }))
})

const panelOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const saving = ref(false)
const tbodyRef = ref(null)
let sortableInstance = null

const form = useForm({ name: '', department_id: '', numeric_value: '', incharge_staff_id: '' })

const getCsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const saveOrder = async () => {
    saving.value = true
    try {
        await fetch('/school/classes/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ order: localClasses.value.map((c, i) => ({ id: c.id, order: i + 1 })) }),
        })
        localClasses.value.forEach((c, i) => { c._order = i + 1; c.numeric_value = i + 1 })
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    if (tbodyRef.value) {
        sortableInstance = Sortable.create(tbodyRef.value, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd(evt) {
                const moved = localClasses.value.splice(evt.oldIndex, 1)[0]
                localClasses.value.splice(evt.newIndex, 0, moved)
                saveOrder()
            },
        })
    }
})
onUnmounted(() => sortableInstance?.destroy())

const openCreate = () => { isEditing.value = false; editingId.value = null; form.reset(); form.clearErrors(); panelOpen.value = true }
const openEdit = (c) => {
    isEditing.value = true; editingId.value = c.id
    form.name = c.name
    form.department_id = c.department_id
    form.numeric_value = c.numeric_value ?? ''
    form.incharge_staff_id = c.incharge_staff_id ?? ''
    form.clearErrors(); panelOpen.value = true
}
const closePanel = () => { panelOpen.value = false }
const submit = () => {
    const opts = { onSuccess: () => { closePanel(); router.reload({ only: ['classes'] }) }, onError: (e) => form.setError(e) }
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/classes/${editingId.value}`, opts)
    } else {
        form.transform((data) => data).post('/school/classes', opts)
    }
}
const { del } = useDelete()
const destroy = (id) => del(`/school/classes/${id}`, 'Delete this class? All sections in it will also be removed.')
</script>

<template>
    <SchoolLayout title="Classes">
        <PageHeader title="Academic Classes" subtitle="Drag ⠿ to reorder classes. Order saves automatically.">
            <template #actions>
                <span v-if="saving" class="text-sm animate-pulse" style="color:var(--accent)">Saving…</span>
                <Button @click="openCreate">+ Add Class</Button>

            </template>
        </PageHeader>

        <div class="card">
            <Table>
                <thead>
                    <tr>
                        <th style="width:2.5rem;"></th>
                        <th>Order</th>
                        <th>Class Name</th>
                        <th>Department</th>
                        <th>Sections</th>
                        <th>Incharge</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody ref="tbodyRef">
                    <tr v-if="localClasses.length === 0">
                        <td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-muted);">No classes yet.</td>
                    </tr>
                    <tr v-for="(cls, idx) in localClasses" :key="cls.id">
                        <td>
                            <span class="drag-handle" title="Drag to reorder">⠿</span>
                        </td>
                        <td>
                            <span class="badge badge-indigo">{{ idx + 1 }}</span>
                        </td>
                        <td style="font-weight:600;color:var(--text-primary);">{{ cls.name }}</td>
                        <td>
                            <span class="badge badge-blue">{{ cls.department?.name || 'N/A' }}</span>
                        </td>
                        <td>
                            <span class="badge badge-green">{{ cls.sections_count ?? 0 }}</span>
                        </td>
                        <td style="color:var(--text-secondary);font-size:0.82rem;">
                            {{ cls.incharge_staff?.name || '—' }}
                        </td>
                        <td style="text-align:right;">
                            <Button variant="secondary" size="sm" @click="openEdit(cls)">Edit</Button>
                            <Button variant="danger" size="sm" @click="destroy(cls.id)" class="ml-2">Delete</Button>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>
        <p class="hint-text">⠿ Drag the grip on the left edge of each row to reorder</p>

        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Class' : 'Add Class'" @close="closePanel">
            <form @submit.prevent="submit">
                <div class="form-field">
                    <label>Department <span style="color:var(--danger);">*</span></label>
                    <select v-model="form.department_id" required>
                        <option value="" disabled>Select Department</option>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <p v-if="form.errors.department_id" class="form-error">{{ form.errors.department_id }}</p>
                </div>
                <div class="form-field">
                    <label>Class Name <span style="color:var(--danger);">*</span></label>
                    <input v-model="form.name" type="text" placeholder="e.g. Class 1, Grade 9" required />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
                <div class="form-field">
                    <label>Incharge Staff (optional)</label>
                    <select v-model="form.incharge_staff_id">
                        <option value="">— None —</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <p v-if="form.errors.incharge_staff_id" class="form-error">{{ form.errors.incharge_staff_id }}</p>
                </div>
                <div class="form-field">
                    <label>Display Order</label>
                    <input v-model="form.numeric_value" type="number" min="0" placeholder="e.g. 1 = first" />
                    <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">You can also drag rows to reorder.</p>
                </div>
                <div style="display:flex;gap:0.75rem;padding-top:0.5rem;">
                    <Button type="submit" :loading="form.processing" class="flex-1">
                        {{ isEditing ? 'Update' : 'Save' }}
                    </Button>
                    <Button variant="secondary" type="button" @click="closePanel">Cancel</Button>
                </div>
            </form>
        </SlidePanel>
    </SchoolLayout>
</template>

<style>
.sortable-ghost { opacity: 0.4; background: #e8f0fc !important; }
.sortable-chosen { background: #f0f7ff !important; box-shadow: 0 4px 16px rgba(17,104,205,0.15); }
</style>
<style scoped>
.drag-handle {
    cursor: grab;
    color: var(--text-muted);
    font-size: 1.25rem;
    user-select: none;
    line-height: 1;
}
.hint-text {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
    text-align: center;
}
</style>
