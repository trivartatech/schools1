<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import Sortable from 'sortablejs'
import SchoolLayout from '@/Layouts/SchoolLayout.vue'
import SlidePanel from '@/Components/SlidePanel.vue'
import { useDelete } from '@/Composables/useDelete'
import Table from '@/Components/ui/Table.vue';

const props = defineProps({ subjects: Array, subjectTypes: Array })
const localSubjects = ref(props.subjects.map(s => ({ ...s })))

// Keep local list in sync after Inertia reloads props
watch(() => props.subjects, (v) => {
    localSubjects.value = v.map(s => ({ ...s }))
})

const panelOpen = ref(false)
const isEditing = ref(false)
const currentEditId = ref(null)
const saving = ref(false)
const tbodyRef = ref(null)
let sortableInstance = null

const form = useForm({
    name: '', code: '', subject_type_id: '', type: 'theory',
    is_elective: false, is_co_scholastic: false, sort_order: 0,
})

const getCsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const saveOrder = async () => {
    saving.value = true
    try {
        await fetch('/school/subjects/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ order: localSubjects.value.map((s, i) => ({ id: s.id, order: i + 1 })) }),
        })
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
                const moved = localSubjects.value.splice(evt.oldIndex, 1)[0]
                localSubjects.value.splice(evt.newIndex, 0, moved)
                saveOrder()
            },
        })
    }
})
onUnmounted(() => sortableInstance?.destroy())

const openCreate = () => { isEditing.value = false; currentEditId.value = null; form.reset(); form.clearErrors(); panelOpen.value = true }
const openEdit = (item) => {
    isEditing.value = true; currentEditId.value = item.id
    form.name             = item.name
    form.code             = item.code || ''
    form.subject_type_id  = item.subject_type_id ?? ''
    form.type             = item.type
    form.is_elective      = !!item.is_elective
    form.is_co_scholastic = !!item.is_co_scholastic
    form.sort_order       = item.sort_order ?? 0
    form.clearErrors(); panelOpen.value = true
}
const closePanel = () => { panelOpen.value = false }
const submitForm = () => {
    const opts = {
        onSuccess: () => { closePanel(); router.reload({ only: ['subjects'] }) },
        onError: (e) => form.setError(e),
    }
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/school/subjects/${currentEditId.value}`, opts)
    } else {
        form.transform((data) => data).post('/school/subjects', opts)
    }
}
const { del } = useDelete()
const deleteItem = (id) => del(`/school/subjects/${id}`, 'Delete this subject?')

// Label lookup helper for display
const typeLabel = (id) => props.subjectTypes.find(t => t.id == id)?.label ?? '—'
</script>

<template>
    <SchoolLayout title="Subjects">
        <div class="page-header">
            <div>
                <h2 class="page-header-title">Academic Subjects</h2>
                <p class="page-header-sub">
                    Drag ⠿ to reorder.
                    <Link href="/school/subject-types" style="color:var(--accent);text-decoration:underline;">Manage subject types →</Link>
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <span v-if="saving" class="text-sm animate-pulse" style="color:var(--accent)">Saving…</span>
                <Button variant="secondary" as="link" href="/school/class-subjects">Assign to Classes</Button>
                <Button @click="openCreate">+ Add Subject</Button>
            </div>
        </div>

        <div v-if="subjectTypes.length === 0" class="tip-banner">
            <span>No subject types defined. Add types first to enable the Part dropdown.</span>
            <Link href="/school/subject-types" style="font-weight:600;text-decoration:underline;">Add Subject Types →</Link>
        </div>

        <div class="card">
            <Table>
                <thead>
                    <tr>
                        <th style="width:2.5rem;"></th>
                        <th>Order</th>
                        <th>Subject</th>
                        <th>Part / Type</th>
                        <th>Theory/Practical</th>
                        <th>Flags</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody ref="tbodyRef">
                    <tr v-if="localSubjects.length === 0">
                        <td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-muted);">No subjects yet. Click "+ Add Subject" to begin.</td>
                    </tr>
                    <tr v-for="(subject, idx) in localSubjects" :key="subject.id">
                        <td>
                            <span class="drag-handle" title="Drag to reorder">⠿</span>
                        </td>
                        <td>
                            <span class="badge badge-purple">{{ idx + 1 }}</span>
                        </td>
                        <td>
                            <div style="font-weight:600;color:var(--text-primary);">{{ subject.name }}</div>
                            <div style="font-size:0.75rem;color:var(--text-muted);">{{ subject.code || 'No Code' }}</div>
                        </td>
                        <td>
                            <span v-if="subject.subject_type_id" class="badge badge-amber">{{ typeLabel(subject.subject_type_id) }}</span>
                            <span v-else-if="subject.part" class="badge badge-amber">{{ subject.part }}</span>
                            <span v-else style="color:var(--text-muted);">—</span>
                        </td>
                        <td>
                            <span :class="subject.type === 'theory' ? 'badge badge-purple' : 'badge badge-amber'" style="text-transform:capitalize;">{{ subject.type }}</span>
                        </td>
                        <td style="display:flex;gap:0.25rem;flex-wrap:wrap;align-items:center;">
                            <span v-if="subject.is_co_scholastic" class="badge badge-blue">Co-Scholastic</span>
                            <span v-if="subject.is_elective" class="badge badge-green">Elective</span>
                            <span v-if="!subject.is_co_scholastic && !subject.is_elective" style="font-size:0.75rem;color:var(--text-muted);">Core</span>
                        </td>
                        <td style="text-align:right;">
                            <Button variant="secondary" size="sm" @click="openEdit(subject)">Edit</Button>
                            <Button variant="danger" size="sm" @click="deleteItem(subject.id)" class="ml-2">Delete</Button>
                        </td>
                    </tr>
                </tbody>
            </Table>
        </div>
        <p class="hint-text">⠿ Drag the grip on the left edge of each row to reorder</p>

        <SlidePanel :open="panelOpen" :title="isEditing ? 'Edit Subject' : 'Add Subject'" width="w-[420px]" @close="closePanel">
            <form @submit.prevent="submitForm">
                <div class="form-field">
                    <label>Subject Name <span style="color:var(--danger);">*</span></label>
                    <input v-model="form.name" type="text" placeholder="e.g. Mathematics" required />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
                <div class="form-field">
                    <label>Subject Code</label>
                    <input v-model="form.code" type="text" placeholder="e.g. MAT-101" />
                    <p v-if="form.errors.code" class="form-error">{{ form.errors.code }}</p>
                </div>
                <div class="form-field">
                    <label>Part / Sub-type</label>
                    <select v-model="form.subject_type_id" :disabled="subjectTypes.length === 0">
                        <option value="">— None —</option>
                        <option v-for="st in subjectTypes" :key="st.id" :value="st.id">{{ st.label }}</option>
                    </select>
                    <p v-if="form.errors.subject_type_id" class="form-error">{{ form.errors.subject_type_id }}</p>
                </div>
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Type <span style="color:var(--danger);">*</span></label>
                        <select v-model="form.type" required>
                            <option value="theory">Theory</option>
                            <option value="practical">Practical</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Display Order</label>
                        <input v-model="form.sort_order" type="number" min="0" />
                        <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Or just drag rows</p>
                    </div>
                </div>
                <div class="flags-box">
                    <p class="section-heading" style="margin-bottom:0.75rem;">Flags</p>
                    <label class="flag-row">
                        <input v-model="form.is_co_scholastic" type="checkbox" />
                        <span>Co-Scholastic (grade-based)</span>
                    </label>
                    <label class="flag-row">
                        <input v-model="form.is_elective" type="checkbox" />
                        <span>Elective (optional for students)</span>
                    </label>
                </div>
                <div style="display:flex;gap:0.75rem;padding-top:0.5rem;">
                    <Button type="submit" :loading="form.processing" class="flex-1">
                        {{ isEditing ? 'Update' : 'Save Subject' }}
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
.tip-banner {
    margin-bottom: 1rem;
    padding: 1rem;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: #92400e;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.flags-box {
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 1rem;
    background: #f8fafc;
    margin-bottom: 1rem;
}
.flag-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-primary);
}
</style>
