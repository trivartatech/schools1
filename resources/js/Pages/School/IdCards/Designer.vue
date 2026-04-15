<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    template: { type: Object, default: null },
    school:   { type: Object, required: true },
});

const isEdit = computed(() => !!props.template);

// ── Available elements ────────────────────────────────────────────
const FIELDS = [
    { type: 'photo',  label: 'Photo',            icon: '👤', defaultW: 22, defaultH: 60 },
    { type: 'qr',     label: 'QR Code',           icon: '▦',  defaultW: 20, defaultH: 55 },
    { type: 'field',  field: 'school_name',        label: 'School Name',      icon: '🏫', defaultW: 70 },
    { type: 'field',  field: 'name',               label: 'Full Name',        icon: '👤', defaultW: 55 },
    { type: 'field',  field: 'class_section',      label: 'Class & Section',  icon: '🎓', defaultW: 45 },
    { type: 'field',  field: 'class',              label: 'Class',            icon: '🎓', defaultW: 28 },
    { type: 'field',  field: 'section',            label: 'Section',          icon: '🔤', defaultW: 22 },
    { type: 'field',  field: 'roll_no',            label: 'Roll Number',      icon: '#',  defaultW: 35 },
    { type: 'field',  field: 'admission_no',       label: 'Admission No',     icon: '#',  defaultW: 42 },
    { type: 'field',  field: 'blood_group',        label: 'Blood Group',      icon: '🩸', defaultW: 20 },
    { type: 'field',  field: 'dob',                label: 'Date of Birth',    icon: '📅', defaultW: 40 },
    { type: 'field',  field: 'parent_phone',       label: 'Parent Phone',     icon: '📞', defaultW: 40 },
    { type: 'field',  field: 'father_name',        label: 'Father Name',      icon: '👨', defaultW: 45 },
    { type: 'field',  field: 'academic_year',      label: 'Academic Year',    icon: '📆', defaultW: 28 },
    { type: 'text',   label: 'Custom Text',        icon: 'T',  defaultW: 40 },
    { type: 'line',   label: 'Divider Line',       icon: '—',  defaultW: 80 },
];

// ── Default elements ──────────────────────────────────────────────
const defaultElements = () => [
    { id: 'e1', type: 'field', field: 'school_name', label: 'School Name',     x: 2,  y: 3,  w: 96, fontSize: 12, fontWeight: 'bold',   color: '#ffffff', textAlign: 'center', prefix: '', suffix: '' },
    { id: 'e2', type: 'text',  text: 'STUDENT IDENTITY CARD',                  x: 2,  y: 12, w: 96, fontSize: 8,  fontWeight: 'normal',  color: '#bfdbfe', textAlign: 'center' },
    { id: 'e3', type: 'photo',                                                  x: 3,  y: 20, w: 22, h: 65, borderRadius: 4 },
    { id: 'e4', type: 'field', field: 'name',        label: 'Full Name',        x: 27, y: 22, w: 48, fontSize: 13, fontWeight: 'bold',   color: '#ffffff', textAlign: 'left',   prefix: '', suffix: '' },
    { id: 'e5', type: 'field', field: 'class_section', label: 'Class & Section', x: 27, y: 38, w: 48, fontSize: 10, fontWeight: 'normal', color: '#bfdbfe', textAlign: 'left',   prefix: 'Class: ', suffix: '' },
    { id: 'e6', type: 'field', field: 'roll_no',     label: 'Roll No',          x: 27, y: 53, w: 28, fontSize: 10, fontWeight: 'normal', color: '#e2e8f0', textAlign: 'left',   prefix: 'Roll: ', suffix: '' },
    { id: 'e7', type: 'field', field: 'blood_group', label: 'Blood Group',      x: 57, y: 53, w: 18, fontSize: 10, fontWeight: 'bold',   color: '#fca5a5', textAlign: 'left',   prefix: '', suffix: '' },
    { id: 'e8', type: 'qr',                                                     x: 78, y: 20, w: 20, h: 65 },
    { id: 'e9', type: 'field', field: 'academic_year', label: 'Academic Year',  x: 2,  y: 90, w: 96, fontSize: 8,  fontWeight: 'normal', color: '#94a3b8', textAlign: 'center', prefix: '', suffix: '' },
];

// ── Inertia form ──────────────────────────────────────────────────
const form = useForm({
    name:        props.template?.name        ?? '',
    orientation: props.template?.orientation ?? 'landscape',
    background:  props.template?.background  ?? { type: 'color', value: '#1e3a8a' },
    elements:    props.template?.elements    ?? defaultElements(),
    columns:     props.template?.columns     ?? 2,
});

const save = () => {
    if (isEdit.value) {
        form.put(`/school/utility/id-cards/${props.template.id}`, {
            onError: () => {},
        });
    } else {
        form.post('/school/utility/id-cards', {
            onError: () => {},
        });
    }
};

// ── Canvas sizing per orientation ─────────────────────────────────
const LANDSCAPE_W = 514;
const LANDSCAPE_H = 324;
const PORTRAIT_W  = 324;
const PORTRAIT_H  = 514;

const canvasW = computed(() => form.orientation === 'portrait' ? PORTRAIT_W  : LANDSCAPE_W);
const canvasH = computed(() => form.orientation === 'portrait' ? PORTRAIT_H  : LANDSCAPE_H);

// ── Drag logic ────────────────────────────────────────────────────
const canvasRef = ref(null);
const dragging  = ref(null);
const selected  = ref(null);

const startDrag = (e, el) => {
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    selected.value = el.id;
    const canvasRect = canvasRef.value.getBoundingClientRect();
    const elPxX = (el.x / 100) * canvasRect.width;
    const elPxY = (el.y / 100) * canvasRect.height;
    dragging.value = {
        id: el.id,
        offsetX: e.clientX - canvasRect.left - elPxX,
        offsetY: e.clientY - canvasRect.top  - elPxY,
    };
};

const onMouseMove = (e) => {
    if (!dragging.value || !canvasRef.value) return;
    const rect = canvasRef.value.getBoundingClientRect();
    const el   = form.elements.find(el => el.id === dragging.value.id);
    if (!el) return;
    let nx = ((e.clientX - rect.left - dragging.value.offsetX) / rect.width)  * 100;
    let ny = ((e.clientY - rect.top  - dragging.value.offsetY) / rect.height) * 100;
    nx = Math.round(nx * 2) / 2;
    ny = Math.round(ny * 2) / 2;
    el.x = Math.max(0, Math.min(100 - el.w, nx));
    el.y = Math.max(0, Math.min(97, ny));
};

const stopDrag = () => { dragging.value = null; };

const clickCanvas = (e) => {
    if (e.target === canvasRef.value) selected.value = null;
};

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', stopDrag);
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', stopDrag);
});

// ── Selected element ──────────────────────────────────────────────
const selectedEl = computed(() => form.elements.find(e => e.id === selected.value) ?? null);

const deleteSelected = () => {
    if (!selected.value) return;
    form.elements = form.elements.filter(e => e.id !== selected.value);
    selected.value = null;
};

const duplicateSelected = () => {
    if (!selectedEl.value) return;
    const clone = { ...selectedEl.value, id: 'e' + Date.now(), x: selectedEl.value.x + 2, y: selectedEl.value.y + 2 };
    form.elements = [...form.elements, clone];
    selected.value = clone.id;
};

const bringFront = () => {
    const idx = form.elements.findIndex(e => e.id === selected.value);
    if (idx < 0) return;
    const arr = [...form.elements];
    arr.push(arr.splice(idx, 1)[0]);
    form.elements = arr;
};

const sendBack = () => {
    const idx = form.elements.findIndex(e => e.id === selected.value);
    if (idx < 0) return;
    const arr = [...form.elements];
    arr.unshift(arr.splice(idx, 1)[0]);
    form.elements = arr;
};

// ── Add element ───────────────────────────────────────────────────
const addElement = (def) => {
    const id = 'e' + Date.now();
    const base = { id, type: def.type, x: 10, y: 40, w: def.defaultW || 40 };
    if (def.type === 'photo') {
        Object.assign(base, { h: def.defaultH || 55, borderRadius: 4 });
    } else if (def.type === 'qr') {
        Object.assign(base, { h: def.defaultH || 55 });
    } else if (def.type === 'field') {
        Object.assign(base, { field: def.field, label: def.label, fontSize: 11, fontWeight: 'normal', color: '#ffffff', textAlign: 'left', prefix: '', suffix: '' });
    } else if (def.type === 'text') {
        Object.assign(base, { text: 'Your text', fontSize: 11, fontWeight: 'normal', color: '#ffffff', textAlign: 'left' });
    } else if (def.type === 'line') {
        Object.assign(base, { color: '#ffffff' });
    }
    form.elements = [...form.elements, base];
    selected.value = id;
};

// ── Background ────────────────────────────────────────────────────
const bgInput = ref(null);

const onBgUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
        form.background = { type: 'image', value: ev.target.result };
    };
    reader.readAsDataURL(file);
    e.target.value = '';
};

const removeBgImage = () => {
    form.background = { type: 'color', value: '#1e3a8a' };
};

// ── Canvas styles ─────────────────────────────────────────────────
const canvasBg = computed(() => {
    const bg = form.background;
    return bg.type === 'image'
        ? { backgroundImage: `url(${bg.value})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { background: bg.value };
});

const elStyle = (el) => ({
    position: 'absolute',
    left:  el.x + '%',
    top:   el.y + '%',
    width: el.w + '%',
    ...(el.h ? { height: el.h + '%' } : {}),
    cursor: 'move',
    userSelect: 'none',
    zIndex: selected.value === el.id ? 20 : 5,
    outline: selected.value === el.id ? '1.5px dashed rgba(255,255,255,0.9)' : '1px dashed rgba(255,255,255,0.15)',
    outlineOffset: '1px',
    boxSizing: 'border-box',
});

const textCss = (el) => ({
    fontSize:     (el.fontSize || 11) + 'px',
    fontWeight:   el.fontWeight || 'normal',
    color:        el.color || '#ffffff',
    textAlign:    el.textAlign || 'left',
    lineHeight:   '1.2',
    overflow:     'hidden',
    whiteSpace:   'nowrap',
    textOverflow: 'ellipsis',
});

// ── Sample preview data ───────────────────────────────────────────
const SAMPLE = {
    name:          'Aarav Sharma',
    class:         'X',
    section:       'A',
    class_section: 'X - A',
    roll_no:       '12',
    admission_no:  'ADM/24/001',
    blood_group:   'B+',
    dob:           '15 Mar 2010',
    parent_phone:  '9876543210',
    father_name:   'Raj Sharma',
    school_name:   props.school?.name || 'School Name',
    academic_year: '2026-27',
};

const getPreview = (el) => {
    if (el.type === 'text') return el.text || '';
    return (el.prefix || '') + (SAMPLE[el.field] || el.label || '') + (el.suffix || '');
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Template' : 'New Template'" />
    <SchoolLayout :title="isEdit ? 'Edit Template' : 'New ID Card Template'">

        <!-- ── Top bar ── -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <a href="/school/utility/id-cards"
                   class="text-slate-400 hover:text-slate-600 transition-colors flex-shrink-0">
                    ← Back
                </a>

                <!-- Template name -->
                <input v-model="form.name"
                       type="text"
                       placeholder="Template name (e.g. Standard 2026)"
                       class="flex-1 min-w-0 max-w-xs border border-slate-300 rounded-lg px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                       :class="{ 'border-red-400': form.errors.name }" />
                <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Orientation toggle -->
                <div class="flex items-center gap-0 border border-slate-300 rounded-lg overflow-hidden">
                    <button v-for="o in ['landscape', 'portrait']" :key="o"
                            @click="form.orientation = o"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors',
                                     form.orientation === o ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
                        {{ o === 'landscape' ? '⬛ Landscape' : '▬ Portrait' }}
                    </button>
                </div>

                <!-- Columns per page -->
                <div class="flex items-center gap-0 border border-slate-300 rounded-lg overflow-hidden">
                    <span class="text-xs text-slate-500 px-2">Cols</span>
                    <button v-for="n in [1, 2, 4]" :key="n"
                            @click="form.columns = n"
                            :class="['px-3 py-1.5 text-xs font-medium border-l border-slate-300 transition-colors',
                                     form.columns === n ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
                        {{ n }}
                    </button>
                </div>

                <button @click="save"
                        :disabled="form.processing || !form.name.trim()"
                        class="px-4 py-1.5 text-sm font-semibold bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white rounded-lg transition-colors">
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Save Changes' : 'Save Template') }}
                </button>
            </div>
        </div>

        <!-- ── Three-column layout ── -->
        <div class="flex gap-4 items-start">

            <!-- ── Left: Elements palette ── -->
            <div class="w-44 flex-shrink-0 bg-white rounded-xl border border-slate-200 p-3">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Elements</div>
                <div class="space-y-1">
                    <button v-for="def in FIELDS" :key="def.type + (def.field || '')"
                            @click="addElement(def)"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-blue-50 hover:text-blue-700 transition-colors text-left border border-transparent hover:border-blue-200">
                        <span class="text-base w-5 text-center flex-shrink-0">{{ def.icon || '▪' }}</span>
                        <span class="truncate text-xs">{{ def.label }}</span>
                    </button>
                </div>

                <!-- Background section -->
                <div class="mt-4 pt-3 border-t border-slate-200">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Background</div>

                    <div v-if="form.background.type === 'color'" class="flex items-center gap-2 mb-2">
                        <input type="color" v-model="form.background.value"
                               class="w-8 h-8 rounded border border-slate-200 cursor-pointer flex-shrink-0" />
                        <input type="text" v-model="form.background.value"
                               class="flex-1 border border-slate-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-400" />
                    </div>

                    <div v-else class="flex items-center gap-2 mb-2">
                        <div class="text-xs text-green-600 flex-1 truncate">
                            {{ form.background.value?.startsWith('/storage/') ? 'Saved image' : 'Image uploaded' }}
                        </div>
                        <button @click="removeBgImage" class="text-xs text-red-500 hover:text-red-700 flex-shrink-0">✕ Remove</button>
                    </div>

                    <label class="block w-full text-center py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded-lg cursor-pointer transition-colors text-slate-600 border border-slate-300">
                        Upload Image
                        <input ref="bgInput" type="file" accept="image/*" class="hidden" @change="onBgUpload" />
                    </label>
                    <p class="text-xs text-slate-400 mt-1">Design your card in an image editor, upload as background, add fields on top</p>
                </div>
            </div>

            <!-- ── Center: Canvas ── -->
            <div class="flex-1 min-w-0">
                <div class="bg-slate-100 rounded-xl p-4 flex flex-col items-center gap-3">

                    <div
                        ref="canvasRef"
                        class="relative overflow-hidden rounded-lg shadow-xl flex-shrink-0"
                        :style="[canvasBg, { width: canvasW + 'px', height: canvasH + 'px' }]"
                        @click="clickCanvas"
                    >
                        <div
                            v-for="el in form.elements" :key="el.id"
                            :style="elStyle(el)"
                            @mousedown="(e) => startDrag(e, el)"
                            @click.stop="selected = el.id"
                        >
                            <template v-if="el.type === 'photo'">
                                <div class="w-full h-full bg-white/20 flex items-center justify-center overflow-hidden"
                                     :style="{ borderRadius: (el.borderRadius || 0) + 'px' }">
                                    <span class="text-3xl">👤</span>
                                </div>
                            </template>

                            <template v-else-if="el.type === 'qr'">
                                <div class="w-full h-full bg-white flex items-center justify-center rounded overflow-hidden p-0.5">
                                    <svg viewBox="0 0 21 21" fill="none" class="w-full h-full text-slate-800">
                                        <rect x="1" y="1" width="8" height="8" rx="1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <rect x="3" y="3" width="4" height="4" fill="currentColor"/>
                                        <rect x="12" y="1" width="8" height="8" rx="1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <rect x="14" y="3" width="4" height="4" fill="currentColor"/>
                                        <rect x="1" y="12" width="8" height="8" rx="1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <rect x="3" y="14" width="4" height="4" fill="currentColor"/>
                                        <rect x="12" y="12" width="2" height="2" fill="currentColor"/>
                                        <rect x="15" y="12" width="2" height="2" fill="currentColor"/>
                                        <rect x="18" y="12" width="2" height="2" fill="currentColor"/>
                                        <rect x="12" y="15" width="2" height="2" fill="currentColor"/>
                                        <rect x="15" y="15" width="5" height="5" fill="currentColor"/>
                                    </svg>
                                </div>
                            </template>

                            <template v-else-if="el.type === 'line'">
                                <div class="w-full" :style="{ borderTop: `1px solid ${el.color || '#ffffff'}` }"></div>
                            </template>

                            <template v-else>
                                <div :style="textCss(el)">{{ getPreview(el) }}</div>
                            </template>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400">
                        Click element to select &bull; Drag to move &bull; Edit properties in panel
                    </p>
                </div>
            </div>

            <!-- ── Right: Properties panel ── -->
            <div class="w-52 flex-shrink-0">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Properties</div>

                    <div v-if="!selectedEl" class="text-sm text-slate-400 text-center py-6">
                        Click an element to edit its properties
                    </div>

                    <template v-else>
                        <div class="text-xs font-medium text-slate-700 mb-3 px-2 py-1.5 bg-slate-50 rounded-lg truncate">
                            {{ selectedEl.label || selectedEl.type }}
                        </div>

                        <div class="space-y-3">
                            <!-- Position -->
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">X %</label>
                                    <input type="number" v-model.number="selectedEl.x" min="0" max="99" step="0.5"
                                           class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">Y %</label>
                                    <input type="number" v-model.number="selectedEl.y" min="0" max="99" step="0.5"
                                           class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                </div>
                            </div>

                            <!-- Size -->
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">Width %</label>
                                    <input type="number" v-model.number="selectedEl.w" min="1" max="100" step="1"
                                           class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                </div>
                                <div v-if="selectedEl.h !== undefined">
                                    <label class="block text-xs text-slate-500 mb-0.5">Height %</label>
                                    <input type="number" v-model.number="selectedEl.h" min="1" max="100" step="1"
                                           class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                </div>
                            </div>

                            <!-- Text-specific properties -->
                            <template v-if="selectedEl.type !== 'photo' && selectedEl.type !== 'qr' && selectedEl.type !== 'line'">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">Font Size</label>
                                    <div class="flex items-center gap-1">
                                        <input type="range" v-model.number="selectedEl.fontSize" min="6" max="36" step="1" class="flex-1" />
                                        <span class="text-xs text-slate-600 w-7">{{ selectedEl.fontSize }}</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">Color</label>
                                    <div class="flex items-center gap-1">
                                        <input type="color" v-model="selectedEl.color"
                                               class="w-7 h-7 rounded border border-slate-200 cursor-pointer flex-shrink-0" />
                                        <input type="text" v-model="selectedEl.color"
                                               class="flex-1 border border-slate-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <button @click="selectedEl.fontWeight = selectedEl.fontWeight === 'bold' ? 'normal' : 'bold'"
                                            :class="['flex-1 py-1 text-xs rounded border font-bold transition-colors',
                                                     selectedEl.fontWeight === 'bold' ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 text-slate-600']">
                                        Bold
                                    </button>
                                    <button v-for="align in ['left','center','right']" :key="align"
                                            @click="selectedEl.textAlign = align"
                                            :class="['flex-1 py-1 text-xs rounded border transition-colors',
                                                     selectedEl.textAlign === align ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 text-slate-600']">
                                        {{ align === 'left' ? '⬅' : align === 'center' ? '↔' : '➡' }}
                                    </button>
                                </div>

                                <div v-if="selectedEl.type === 'text'">
                                    <label class="block text-xs text-slate-500 mb-0.5">Text</label>
                                    <input type="text" v-model="selectedEl.text"
                                           class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                </div>

                                <template v-if="selectedEl.type === 'field'">
                                    <div>
                                        <label class="block text-xs text-slate-500 mb-0.5">Prefix</label>
                                        <input type="text" v-model="selectedEl.prefix" placeholder="e.g. Roll: "
                                               class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 mb-0.5">Suffix</label>
                                        <input type="text" v-model="selectedEl.suffix" placeholder="optional"
                                               class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                    </div>
                                </template>
                            </template>

                            <template v-if="selectedEl.type === 'line'">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">Color</label>
                                    <div class="flex items-center gap-1">
                                        <input type="color" v-model="selectedEl.color"
                                               class="w-7 h-7 rounded border border-slate-200 cursor-pointer flex-shrink-0" />
                                        <input type="text" v-model="selectedEl.color"
                                               class="flex-1 border border-slate-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                    </div>
                                </div>
                            </template>

                            <template v-if="selectedEl.type === 'photo'">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">Corner Radius</label>
                                    <div class="flex items-center gap-1">
                                        <input type="range" v-model.number="selectedEl.borderRadius" min="0" max="50" step="1" class="flex-1" />
                                        <span class="text-xs text-slate-600 w-7">{{ selectedEl.borderRadius }}</span>
                                    </div>
                                </div>
                            </template>

                            <!-- Actions -->
                            <div class="flex gap-2 pt-1 border-t border-slate-100">
                                <button @click="duplicateSelected"
                                        class="flex-1 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded text-slate-600 transition-colors">
                                    Duplicate
                                </button>
                                <button @click="deleteSelected"
                                        class="flex-1 py-1.5 text-xs bg-red-50 hover:bg-red-100 rounded text-red-600 transition-colors">
                                    Delete
                                </button>
                            </div>

                            <div class="flex gap-2">
                                <button @click="bringFront"
                                        class="flex-1 py-1 text-xs border border-slate-300 rounded text-slate-500 hover:bg-slate-50 transition-colors">
                                    Bring Front
                                </button>
                                <button @click="sendBack"
                                        class="flex-1 py-1 text-xs border border-slate-300 rounded text-slate-500 hover:bg-slate-50 transition-colors">
                                    Send Back
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Tips -->
                <div class="mt-3 bg-blue-50 border border-blue-200 rounded-xl p-3">
                    <p class="text-xs text-blue-700 font-semibold mb-1">Tips</p>
                    <ul class="text-xs text-blue-600 space-y-1 list-disc list-inside">
                        <li>Upload a designed card as background</li>
                        <li>Place data fields on top</li>
                        <li>Drag to position precisely</li>
                        <li>Switch orientation above</li>
                    </ul>
                </div>
            </div>

        </div>

    </SchoolLayout>
</template>
