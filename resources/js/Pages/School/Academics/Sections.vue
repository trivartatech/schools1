<script setup>
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import Sortable from 'sortablejs'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import SlidePanel from '@/Components/SlidePanel.vue'
import { useDelete } from '@/Composables/useDelete'
import Table from '@/Components/ui/Table.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';

const props = defineProps({ sections: Array, classes: Array })

// Local copy — refreshed when Inertia reloads props
const localSections = ref(props.sections.map(s => ({ ...s })))
watch(() => props.sections, (v) => {
    localSections.value = v.map(s => ({ ...s }))
    rebuildSortables()
})

// Group sections by class (ordered by class numeric_value)
const sortedClasses = computed(() =>
    [...props.classes].sort((a, b) => (a.numeric_value ?? 0) - (b.numeric_value ?? 0) || a.name.localeCompare(b.name))
)

const localSectionsByClass = computed(() => {
    const map = {}
    for (const cls of sortedClasses.value) {
        map[cls.id] = localSections.value
            .filter(s => s.course_class_id === cls.id)
            .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
    }
    return map
})

// Class filter dropdown
const filterClassId = ref('')
const visibleClasses = computed(() =>
    filterClassId.value
        ? sortedClasses.value.filter(c => c.id == filterClassId.value)
        : sortedClasses.value.filter(c => (localSectionsByClass.value[c.id]?.length ?? 0) > 0 || true)
)

// Per-class Sortable instances
const tbodyEls = {}
const sortableInstances = {}
const saving = ref(false)

function initSortable(el, classId) {
    if (!el || sortableInstances[classId]) return
    sortableInstances[classId] = Sortable.create(el, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd(evt) {
            const group = localSections.value.filter(s => s.course_class_id === classId)
                .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
            const moved = group.splice(evt.oldIndex, 1)[0]
            group.splice(evt.newIndex, 0, moved)
            // Write new sort orders back into localSections
            group.forEach((s, i) => {
                const idx = localSections.value.findIndex(x => x.id === s.id)
                if (idx !== -1) localSections.value[idx].sort_order = i + 1
            })
            saveOrder()
        },
    })
}

function destroySortables() {
    for (const id in sortableInstances) {
        sortableInstances[id]?.destroy()
        delete sortableInstances[id]
    }
}

function rebuildSortables() {
    destroySortables()
    for (const classId in tbodyEls) {
        if (tbodyEls[classId]) initSortable(tbodyEls[classId], Number(classId))
    }
}

function setTbodyRef(el, classId) {
    if (el) {
        tbodyEls[classId] = el
        initSortable(el, classId)
    } else {
        delete tbodyEls[classId]
        if (sortableInstances[classId]) {
            sortableInstances[classId].destroy()
            delete sortableInstances[classId]
        }
    }
}

onBeforeUnmount(destroySortables)

const getCsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const saveOrder = async () => {
    saving.value = true
    try {
        await fetch('/school/sections/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ order: localSections.value.map(s => ({ id: s.id, order: s.sort_order ?? 0 })) }),
        })
    } finally {
        saving.value = false
    }
}

// Panel / form
const panelOpen = ref(false)
const isEditing = ref(false)
const editingId = ref(null)

const form = useForm({ name: '', course_class_id: '', capacity: '', sort_order: 0 })

const openCreate = (classId = '') => {
    isEditing.value = false; editingId.value = null
    form.reset(); form.clearErrors()
    form.course_class_id = classId || ''
    panelOpen.value = true
}
const openEdit = (s) => {
    isEditing.value = true; editingId.value = s.id
    form.name = s.name
    form.course_class_id = s.course_class_id
    form.capacity = s.capacity || ''
    form.sort_order = s.sort_order ?? 0
    form.clearErrors(); panelOpen.value = true
}
const closePanel = () => { panelOpen.value = false }
const submit = () => {
    const opts = {
        onSuccess: () => { closePanel(); router.reload({ only: ['sections'] }) },
        onError: (e) => form.setError(e),
    }
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/sections/${editingId.value}`, opts)
    } else {
        form.transform((data) => data).post('/school/sections', opts)
    }
}
const { del } = useDelete()
const destroy = (id) => del(`/school/sections/${id}`, 'Delete this section?')
</script>

<template>
    <SchoolLayout title="Sections">
        <PageHeader title="Class Sections" subtitle="Manage sections per class. Drag ⠿ within a class to reorder.">
            <template #actions>
                <span v-if="saving" class="text-sm animate-pulse" style="color:var(--accent)">Saving…</span>
                <Button @click="openCreate()">+ Add Section</Button>

            </template>
        </PageHeader>

        <!-- Class filter -->
        <FilterBar :active="!!filterClassId" @clear="filterClassId = ''">
            <select v-model="filterClassId" style="width:200px;">
                <option value="">All Classes</option>
                <option v-for="cls in sortedClasses" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
            </select>
        </FilterBar>

        <!-- Per-class tables -->
        <div v-for="cls in visibleClasses" :key="cls.id" class="class-group">
            <div class="class-group-header">
                <span class="class-group-title">{{ cls.name }}</span>
                <span v-if="cls.department" class="class-group-dept">{{ cls.department?.name }}</span>
                <Button variant="secondary" size="sm" @click="openCreate(cls.id)" class="ml-auto">+ Add Section</Button>
            </div>

            <div class="card overflow-hidden" style="margin-bottom:0;">
                <Table>
                    <thead>
                        <tr>
                            <th style="width:2.5rem;"></th>
                            <th>Order</th>
                            <th>Section Name</th>
                            <th>Capacity</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody :ref="(el) => setTbodyRef(el, cls.id)">
                        <tr v-if="(localSectionsByClass[cls.id] ?? []).length === 0">
                            <td colspan="5" style="text-align:center;padding:1.5rem;color:var(--text-muted);font-style:italic;">No sections yet for {{ cls.name }}.</td>
                        </tr>
                        <tr v-for="(s, idx) in (localSectionsByClass[cls.id] ?? [])" :key="s.id">
                            <td>
                                <span class="drag-handle" title="Drag to reorder">⠿</span>
                            </td>
                            <td>
                                <span class="badge badge-green">{{ idx + 1 }}</span>
                            </td>
                            <td style="font-weight:600;color:var(--text-primary);">Section {{ s.name }}</td>
                            <td style="color:var(--text-secondary);">{{ s.capacity ? s.capacity + ' students' : 'Unlimited' }}</td>
                            <td style="text-align:right;">
                                <Button variant="secondary" size="sm" @click="openEdit(s)">Edit</Button>
                                <Button variant="danger" size="sm" @click="destroy(s.id)" class="ml-2">Delete</Button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </div>
        </div>

        <div v-if="visibleClasses.length === 0" class="card" style="text-align:center;padding:3rem;color:var(--text-muted);">
            No classes found. <a href="/school/classes" style="color:var(--accent);">Add classes first →</a>
        </div>

        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Section' : 'Add Section'" @close="closePanel">
            <form @submit.prevent="submit">
                <div class="form-field">
                    <label>Parent Class <span style="color:var(--danger);">*</span></label>
                    <select v-model="form.course_class_id" required>
                        <option value="" disabled>Select Class</option>
                        <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
                    </select>
                    <p v-if="form.errors.course_class_id" class="form-error">{{ form.errors.course_class_id }}</p>
                </div>
                <div class="form-field">
                    <label>Section Name <span style="color:var(--danger);">*</span></label>
                    <input v-model="form.name" type="text" placeholder="e.g. A, B, Rose" required />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
                <div class="form-field">
                    <label>Capacity (optional)</label>
                    <input v-model="form.capacity" type="number" placeholder="e.g. 40" />
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
.class-group {
    margin-bottom: 1.5rem;
}
.class-group-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    background: #f8fafc;
    border: 1px solid var(--border);
    border-bottom: none;
    border-radius: 10px 10px 0 0;
}
.class-group-title { font-weight: 700; font-size: 0.9rem; color: var(--text-primary); }
.class-group-dept  { font-size: 0.75rem; color: var(--text-muted); }
</style>
