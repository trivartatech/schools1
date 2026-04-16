<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const schoolStore = useSchoolStore();

const props = defineProps({
    template: { type: Object, default: null },
    school:   { type: Object, required: true },
});

const isEdit = computed(() => !!props.template);

// ── Canvas sizes (A4 ratio: 297/210 = 1.4142) ─────────────────────
const CANVAS = {
    landscape: { w: 800, h: 566 },
    portrait:  { w: 566, h: 800 },
};
const canvasW = computed(() => CANVAS[form.orientation]?.w ?? 800);
const canvasH = computed(() => CANVAS[form.orientation]?.h ?? 566);

// ── Element palette ───────────────────────────────────────────────
const PALETTE = [
    // Media
    { type: 'logo',      label: 'School Logo',     icon: '🏫', defaultW: 15, hint: 'auto' },
    { type: 'image',     label: 'Upload Image',     icon: '🖼', defaultW: 20, defaultH: 20 },
    // Text
    { type: 'multiline', label: 'Paragraph Text',   icon: '¶',  defaultW: 70, defaultH: 15 },
    { type: 'text',      label: 'Static Text',      icon: 'T',  defaultW: 50 },
    // Student data fields
    { type: 'field', field: 'school_name',    label: 'School Name',      icon: '🏫', defaultW: 70 },
    { type: 'field', field: 'name',           label: 'Student Name',     icon: '👤', defaultW: 55 },
    { type: 'field', field: 'class_section',  label: 'Class & Section',  icon: '🎓', defaultW: 40 },
    { type: 'field', field: 'roll_no',        label: 'Roll Number',      icon: '#',  defaultW: 30 },
    { type: 'field', field: 'admission_no',   label: 'Admission No',     icon: '#',  defaultW: 35 },
    { type: 'field', field: 'father_name',    label: 'Father Name',      icon: '👨', defaultW: 45 },
    { type: 'field', field: 'academic_year',  label: 'Academic Year',    icon: '📆', defaultW: 28 },
    { type: 'field', field: 'cert_date',      label: 'Certificate Date', icon: '📅', defaultW: 40 },
    // Decorative
    { type: 'line',      label: 'Divider Line',     icon: '—',  defaultW: 80 },
];

// ── Default elements ──────────────────────────────────────────────
const defaultElements = () => [
    { id: 'c1', type: 'logo',      x: 44,  y: 5,  w: 12, h: 12, objectFit: 'contain' },
    { id: 'c2', type: 'field', field: 'school_name', label: 'School Name', x: 5, y: 20, w: 90, fontSize: 22, fontWeight: 'bold',   color: '#1e3a8a', textAlign: 'center', prefix: '', suffix: '' },
    { id: 'c3', type: 'text',  text: 'CERTIFICATE OF ACHIEVEMENT',         x: 5, y: 31, w: 90, fontSize: 14, fontWeight: 'normal', color: '#64748b', textAlign: 'center' },
    { id: 'c4', type: 'line',                                               x: 10, y: 40, w: 80, color: '#cbd5e1', thickness: 1 },
    { id: 'c5', type: 'text',  text: 'This is to certify that',            x: 5, y: 44, w: 90, fontSize: 12, fontWeight: 'normal', color: '#475569', textAlign: 'center' },
    { id: 'c6', type: 'field', field: 'name', label: 'Student Name',       x: 5, y: 51, w: 90, fontSize: 20, fontWeight: 'bold',   color: '#1e3a8a', textAlign: 'center', prefix: '', suffix: '' },
    { id: 'c7', type: 'multiline', template: 'of Class {class_section} has successfully achieved excellence in {achievement} during the Academic Year {academic_year}.', x: 10, y: 60, w: 80, h: 12, fontSize: 12, fontWeight: 'normal', color: '#475569', textAlign: 'center', lineHeight: 1.7 },
    { id: 'c8', type: 'field', field: 'cert_date', label: 'Date',          x: 5, y: 76, w: 28, fontSize: 11, fontWeight: 'normal', color: '#475569', textAlign: 'center', prefix: '', suffix: '' },
    { id: 'c9', type: 'line',                                               x: 64, y: 88, w: 28, color: '#94a3b8', thickness: 1 },
    { id: 'c10', type: 'text', text: 'Principal',                          x: 64, y: 90, w: 28, fontSize: 10, fontWeight: 'normal', color: '#94a3b8', textAlign: 'center' },
];

// ── Background normalization ──────────────────────────────────────
const normalizeBackground = (bg) => {
    if (!bg) return { front: { type: 'color', value: '#ffffff' } };
    if (bg.front !== undefined) return bg;
    return { front: bg };
};

// ── Inertia form ──────────────────────────────────────────────────
const form = useForm({
    name:        props.template?.name        ?? '',
    orientation: props.template?.orientation ?? 'landscape',
    background:  normalizeBackground(props.template?.background),
    elements:    props.template?.elements    ?? defaultElements(),
    custom_vars: props.template?.custom_vars ?? [],
});

const save = () => {
    if (isEdit.value) {
        form.put(`/school/utility/certificates/${props.template.id}`, { onError: () => {} });
    } else {
        form.post('/school/utility/certificates', { onError: () => {} });
    }
};

// ── Canvas drag/resize state ──────────────────────────────────────
const canvasRef = ref(null);
const dragging  = ref(null);
const resizing  = ref(null);
const selected  = ref(null);

const startDrag = (e, el) => {
    if (e.button !== 0 || resizing.value) return;
    e.preventDefault(); e.stopPropagation();
    selected.value = el.id;
    const rect = canvasRef.value.getBoundingClientRect();
    dragging.value = {
        id:      el.id,
        offsetX: e.clientX - rect.left - (el.x / 100) * rect.width,
        offsetY: e.clientY - rect.top  - (el.y / 100) * rect.height,
    };
};

const startResize = (e, el, handle) => {
    if (e.button !== 0) return;
    e.preventDefault(); e.stopPropagation();
    selected.value = el.id;
    resizing.value = { id: el.id, handle, lastX: e.clientX, lastY: e.clientY };
};

const onMouseMove = (e) => {
    if (!canvasRef.value) return;
    const rect = canvasRef.value.getBoundingClientRect();

    if (resizing.value) {
        const dx = ((e.clientX - resizing.value.lastX) / rect.width)  * 100;
        const dy = ((e.clientY - resizing.value.lastY) / rect.height) * 100;
        const el = form.elements.find(el => el.id === resizing.value.id);
        if (el) {
            const h = resizing.value.handle;
            if (el.type === 'logo') {
                // Logo: square lock
                const wR = canvasW.value / canvasH.value;
                if (h.includes('e')) { el.w = Math.max(3, el.w + dx); el.h = el.w; }
                if (h.includes('w')) { const nw = Math.max(3, el.w - dx); el.x = Math.max(0, el.x + el.w - nw); el.w = nw; el.h = nw; }
                if (h === 'n') { el.w = Math.max(3, el.w - dy * wR); el.h = el.w; }
                if (h === 's') { el.w = Math.max(3, el.w + dy * wR); el.h = el.w; }
            } else {
                if (h.includes('e')) { el.w = Math.max(5, el.w + dx); }
                if (h.includes('s')) { el.h = Math.max(3, (el.h ?? 8) + dy); }
                if (h.includes('w')) { const nw = Math.max(5, el.w - dx); el.x = Math.max(0, el.x + el.w - nw); el.w = nw; }
                if (h.includes('n')) { const nh = Math.max(3, (el.h ?? 8) - dy); el.y = Math.max(0, el.y + (el.h ?? 8) - nh); el.h = nh; }
            }
        }
        resizing.value.lastX = e.clientX;
        resizing.value.lastY = e.clientY;
        return;
    }

    if (dragging.value) {
        const el = form.elements.find(el => el.id === dragging.value.id);
        if (!el) return;
        let nx = ((e.clientX - rect.left - dragging.value.offsetX) / rect.width)  * 100;
        let ny = ((e.clientY - rect.top  - dragging.value.offsetY) / rect.height) * 100;
        nx = Math.round(nx * 2) / 2;
        ny = Math.round(ny * 2) / 2;
        el.x = Math.max(0, Math.min(100 - el.w, nx));
        el.y = Math.max(0, Math.min(98, ny));
    }
};

const stopInteraction = () => { dragging.value = null; resizing.value = null; };
const clickCanvas     = (e) => { if (e.target === canvasRef.value) selected.value = null; };

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', stopInteraction);
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', stopInteraction);
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
    const clone = { ...selectedEl.value, id: 'c' + Date.now(), x: selectedEl.value.x + 2, y: selectedEl.value.y + 2 };
    form.elements = [...form.elements, clone];
    selected.value = clone.id;
};
const bringFront = () => {
    const idx = form.elements.findIndex(e => e.id === selected.value);
    if (idx < 0) return;
    const arr = [...form.elements]; arr.push(arr.splice(idx, 1)[0]);
    form.elements = arr;
};
const sendBack = () => {
    const idx = form.elements.findIndex(e => e.id === selected.value);
    if (idx < 0) return;
    const arr = [...form.elements]; arr.unshift(arr.splice(idx, 1)[0]);
    form.elements = arr;
};

// ── Add element ───────────────────────────────────────────────────
const addElement = (def) => {
    const id = 'c' + Date.now();
    const base = { id, type: def.type, x: 10, y: 40, w: def.defaultW || 40 };
    if (def.type === 'logo') {
        Object.assign(base, { h: def.defaultW || 15, objectFit: 'contain' });
    } else if (def.type === 'image') {
        Object.assign(base, { h: def.defaultH || 20, src: null, objectFit: 'contain' });
    } else if (def.type === 'multiline') {
        Object.assign(base, { h: def.defaultH || 15, template: 'Type your paragraph here. Use {name}, {class_section} for student data.', fontSize: 12, fontWeight: 'normal', color: '#333333', textAlign: 'center', lineHeight: 1.7 });
    } else if (def.type === 'text') {
        Object.assign(base, { text: 'Your text', fontSize: 14, fontWeight: 'normal', color: '#333333', textAlign: 'center' });
    } else if (def.type === 'field') {
        Object.assign(base, { field: def.field, label: def.label, fontSize: 14, fontWeight: 'normal', color: '#333333', textAlign: 'center', prefix: '', suffix: '' });
    } else if (def.type === 'line') {
        Object.assign(base, { color: '#cbd5e1', thickness: 1 });
    }
    form.elements = [...form.elements, base];
    selected.value = id;
};

// ── Image element upload ──────────────────────────────────────────
const uploadElementImage = (e) => {
    const file = e.target.files[0];
    if (!file || !selectedEl.value) return;
    const reader = new FileReader();
    reader.onload = (ev) => { selectedEl.value.src = ev.target.result; };
    reader.readAsDataURL(file);
    e.target.value = '';
};

// ── Background ────────────────────────────────────────────────────
const bgInput = ref(null);
const onBgUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => { form.background.front = { type: 'image', value: ev.target.result }; };
    reader.readAsDataURL(file);
    e.target.value = '';
};
const removeBgImage = () => { form.background.front = { type: 'color', value: '#ffffff' }; };

// ── Canvas style ──────────────────────────────────────────────────
const canvasBg = computed(() => {
    const bg = form.background?.front;
    if (!bg) return { background: '#ffffff' };
    return bg.type === 'image'
        ? { backgroundImage: `url(${bg.value})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { background: bg.value };
});

const RESIZE_HANDLES = ['nw','n','ne','e','se','s','sw','w'];

const elStyle = (el) => {
    const base = {
        position:      'absolute',
        left:          el.x + '%',
        top:           el.y + '%',
        width:         el.w + '%',
        cursor:        'move',
        userSelect:    'none',
        zIndex:        selected.value === el.id ? 20 : 5,
        outline:       selected.value === el.id ? '1.5px dashed #2563eb' : '1px dashed rgba(0,0,0,0.1)',
        outlineOffset: '1px',
        boxSizing:     'border-box',
        overflow:      'visible',
    };
    if (el.type === 'logo') {
        base.aspectRatio = '1 / 1';
    } else if (el.h) {
        base.height = el.h + '%';
    }
    return base;
};

const textCss = (el) => ({
    fontSize:    (el.fontSize || 12) + 'px',
    fontWeight:  el.fontWeight || 'normal',
    color:       el.color || '#333333',
    textAlign:   el.textAlign || 'left',
    lineHeight:  el.lineHeight || 1.4,
    overflow:    'hidden',
    whiteSpace:  'nowrap',
    textOverflow:'ellipsis',
    display:     'block',
});

const multilineCss = (el) => ({
    fontSize:    (el.fontSize || 12) + 'px',
    fontWeight:  el.fontWeight || 'normal',
    color:       el.color || '#333333',
    textAlign:   el.textAlign || 'left',
    lineHeight:  el.lineHeight || 1.7,
    whiteSpace:  'pre-wrap',
    wordWrap:    'break-word',
    overflow:    'hidden',
    height:      '100%',
    display:     'block',
});

// ── Sample data for designer preview ─────────────────────────────
const SAMPLE = {
    name:          'Aarav Sharma',
    first_name:    'Aarav',
    class:         'X',
    section:       'A',
    class_section: 'X - A',
    roll_no:       '12',
    admission_no:  'ADM/24/001',
    father_name:   'Raj Sharma',
    school_name:   props.school?.name || 'School Name',
    academic_year: '2026-27',
    cert_date:     schoolStore.fmtDate(schoolStore.today()),
};

// Inject custom var samples too
const customVarSample = (key) => `{${key}}`;

const resolvePreview = (template) => {
    if (!template) return '';
    return template.replace(/\{(\w+)\}/g, (_, key) => SAMPLE[key] ?? customVarSample(key));
};

const getPreview = (el) => {
    if (el.type === 'text') return el.text || '';
    return (el.prefix || '') + (SAMPLE[el.field] ?? el.label ?? '') + (el.suffix || '');
};

// ── Custom variables manager ──────────────────────────────────────
const newVar = ref({ key: '', label: '', placeholder: '' });

const addCustomVar = () => {
    const key = newVar.value.key.trim().replace(/\s+/g, '_').toLowerCase();
    if (!key) return;
    if (form.custom_vars.find(v => v.key === key)) return;
    form.custom_vars = [...form.custom_vars, { key, label: newVar.value.label || key, placeholder: newVar.value.placeholder || '' }];
    newVar.value = { key: '', label: '', placeholder: '' };
};

const removeCustomVar = (key) => {
    form.custom_vars = form.custom_vars.filter(v => v.key !== key);
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Certificate Template' : 'New Certificate Template'" />
    <SchoolLayout :title="isEdit ? 'Edit Template' : 'New Certificate Template'">

        <!-- ── Top bar ── -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <a href="/school/utility/certificates" class="text-slate-400 hover:text-slate-600 text-sm flex-shrink-0">← Back</a>
                <input v-model="form.name" type="text" placeholder="Template name (e.g. Merit Certificate 2026)"
                       class="flex-1 min-w-0 max-w-sm border border-slate-300 rounded-lg px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                       :class="{ 'border-red-400': form.errors.name }" />
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Orientation -->
                <div class="flex border border-slate-300 rounded-lg overflow-hidden">
                    <button v-for="o in ['landscape','portrait']" :key="o" @click="form.orientation = o"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors', form.orientation === o ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
                        {{ o === 'landscape' ? '⬛ Landscape' : '▬ Portrait' }}
                    </button>
                </div>
                <button @click="save" :disabled="form.processing || !form.name.trim()"
                        class="px-4 py-1.5 text-sm font-semibold bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white rounded-lg transition-colors">
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Save Changes' : 'Save Template') }}
                </button>
            </div>
        </div>

        <!-- ── Three-column layout ── -->
        <div class="flex gap-4 items-start">

            <!-- ── Left: Palette + Background + Custom vars ── -->
            <div class="w-48 flex-shrink-0 space-y-3">

                <!-- Elements -->
                <div class="bg-white rounded-xl border border-slate-200 p-3">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Elements</div>
                    <div class="space-y-0.5">
                        <button v-for="def in PALETTE" :key="def.type + (def.field || '')"
                                @click="addElement(def)"
                                class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs text-slate-600 hover:bg-blue-50 hover:text-blue-700 transition-colors text-left border border-transparent hover:border-blue-200">
                            <span class="w-5 text-center flex-shrink-0 text-sm">{{ def.icon }}</span>
                            <span class="truncate">{{ def.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Background -->
                <div class="bg-white rounded-xl border border-slate-200 p-3">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Background</div>
                    <div v-if="form.background.front?.type === 'color'" class="flex items-center gap-2 mb-2">
                        <input type="color" v-model="form.background.front.value"
                               class="w-8 h-8 rounded border border-slate-200 cursor-pointer flex-shrink-0" />
                        <input type="text" v-model="form.background.front.value"
                               class="flex-1 border border-slate-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-400" />
                    </div>
                    <div v-else class="flex items-center gap-2 mb-2">
                        <span class="text-xs text-green-600 flex-1">Image set</span>
                        <button @click="removeBgImage" class="text-xs text-red-500">✕ Remove</button>
                    </div>
                    <label class="block w-full text-center py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded-lg cursor-pointer transition-colors text-slate-600 border border-slate-300">
                        Upload Image
                        <input ref="bgInput" type="file" accept="image/*" class="hidden" @change="onBgUpload" />
                    </label>
                    <p class="text-xs text-slate-400 mt-1">Upload a certificate border/frame design as background</p>
                </div>

                <!-- Custom variables -->
                <div class="bg-white rounded-xl border border-slate-200 p-3">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Custom Variables</div>
                    <p class="text-xs text-slate-400 mb-2">Define <code class="bg-slate-100 px-1 rounded">{variable}</code> tokens filled at generate time</p>

                    <!-- Existing vars -->
                    <div v-if="form.custom_vars.length" class="space-y-1 mb-2">
                        <div v-for="v in form.custom_vars" :key="v.key"
                             class="flex items-center justify-between gap-1 bg-blue-50 rounded px-2 py-1">
                            <div class="min-w-0">
                                <code class="text-xs text-blue-700 font-medium">{{'{'}}{{ v.key }}{{'}'}}</code>
                                <p class="text-xs text-slate-500 truncate">{{ v.label }}</p>
                            </div>
                            <button @click="removeCustomVar(v.key)" class="text-red-400 hover:text-red-600 flex-shrink-0 text-xs">✕</button>
                        </div>
                    </div>

                    <!-- Add new var -->
                    <div class="space-y-1">
                        <input v-model="newVar.key" type="text" placeholder="key (e.g. achievement)"
                               class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                        <input v-model="newVar.label" type="text" placeholder="Label for generate form"
                               class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                        <button @click="addCustomVar"
                                class="w-full py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors">
                            + Add Variable
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Center: Canvas ── -->
            <div class="flex-1 min-w-0 overflow-x-auto">
                <div class="bg-slate-200 rounded-xl p-4 flex flex-col items-center gap-3 min-w-0">
                    <div
                        ref="canvasRef"
                        class="relative rounded shadow-xl flex-shrink-0"
                        :style="{ width: canvasW + 'px', height: canvasH + 'px', overflow: 'visible' }"
                        @click="clickCanvas"
                    >
                        <!-- Background clipped to card shape -->
                        <div class="absolute inset-0 rounded overflow-hidden pointer-events-none" :style="canvasBg"></div>

                        <div v-for="el in form.elements" :key="el.id"
                             :style="elStyle(el)"
                             @mousedown="(e) => startDrag(e, el)"
                             @click.stop="selected = el.id">

                            <!-- Logo -->
                            <template v-if="el.type === 'logo'">
                                <img v-if="school.logo" :src="school.logo"
                                     class="w-full h-full" :style="{ objectFit: el.objectFit || 'contain' }" />
                                <div v-else class="w-full h-full bg-slate-200 flex items-center justify-center rounded text-xs text-slate-400">Logo</div>
                            </template>

                            <!-- Uploaded image -->
                            <template v-else-if="el.type === 'image'">
                                <img v-if="el.src" :src="el.src" class="w-full h-full" :style="{ objectFit: el.objectFit || 'contain' }" />
                                <div v-else class="w-full h-full bg-slate-100 border border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-400 rounded">
                                    🖼 Upload in panel
                                </div>
                            </template>

                            <!-- Paragraph / multiline -->
                            <template v-else-if="el.type === 'multiline'">
                                <div :style="multilineCss(el)">{{ resolvePreview(el.template) }}</div>
                            </template>

                            <!-- Divider -->
                            <template v-else-if="el.type === 'line'">
                                <div :style="{ borderTop: `${el.thickness || 1}px solid ${el.color || '#cbd5e1'}` }"></div>
                            </template>

                            <!-- Text / field -->
                            <template v-else>
                                <div :style="textCss(el)">{{ getPreview(el) }}</div>
                            </template>

                            <!-- Resize handles -->
                            <template v-if="selected === el.id">
                                <div v-for="handle in RESIZE_HANDLES" :key="handle"
                                     :class="`rh rh-${handle}`"
                                     @mousedown.stop.prevent="startResize($event, el, handle)" />
                            </template>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">Click to select &bull; Drag to move &bull; Drag handles to resize</p>
                </div>
            </div>

            <!-- ── Right: Properties ── -->
            <div class="w-52 flex-shrink-0">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Properties</div>

                    <div v-if="!selectedEl" class="text-sm text-slate-400 text-center py-6">
                        Click an element to edit
                    </div>

                    <template v-else>
                        <div class="text-xs font-medium text-slate-700 mb-3 px-2 py-1.5 bg-slate-50 rounded-lg truncate capitalize">
                            {{ selectedEl.type }} {{ selectedEl.field ? `· ${selectedEl.field}` : '' }}
                        </div>

                        <div class="space-y-3">
                            <!-- Position -->
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="block text-xs text-slate-500 mb-0.5">X %</label>
                                    <input type="number" v-model.number="selectedEl.x" min="0" max="99" step="0.5" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                                <div><label class="block text-xs text-slate-500 mb-0.5">Y %</label>
                                    <input type="number" v-model.number="selectedEl.y" min="0" max="99" step="0.5" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                            </div>

                            <!-- Size -->
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="block text-xs text-slate-500 mb-0.5">Width %</label>
                                    <input type="number" v-model.number="selectedEl.w" min="1" max="100" step="1" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                                <div v-if="selectedEl.h !== undefined && selectedEl.type !== 'logo'">
                                    <label class="block text-xs text-slate-500 mb-0.5">Height %</label>
                                    <input type="number" v-model.number="selectedEl.h" min="1" max="100" step="1" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                            </div>
                            <p v-if="selectedEl.type === 'logo'" class="text-xs text-slate-400">Height auto (1:1 square)</p>

                            <!-- Multiline template -->
                            <template v-if="selectedEl.type === 'multiline'">
                                <div><label class="block text-xs text-slate-500 mb-0.5">Template</label>
                                    <textarea v-model="selectedEl.template" rows="5" placeholder="Use {name}, {class_section}, {achievement}…"
                                              class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400 resize-none"></textarea></div>
                                <div><label class="block text-xs text-slate-500 mb-0.5">Line Height</label>
                                    <input type="number" v-model.number="selectedEl.lineHeight" min="1" max="3" step="0.1" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                            </template>

                            <!-- Static text -->
                            <div v-if="selectedEl.type === 'text'">
                                <label class="block text-xs text-slate-500 mb-0.5">Text</label>
                                <input type="text" v-model="selectedEl.text" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" />
                            </div>

                            <!-- Field prefix/suffix -->
                            <template v-if="selectedEl.type === 'field'">
                                <div><label class="block text-xs text-slate-500 mb-0.5">Prefix</label>
                                    <input type="text" v-model="selectedEl.prefix" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                                <div><label class="block text-xs text-slate-500 mb-0.5">Suffix</label>
                                    <input type="text" v-model="selectedEl.suffix" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                            </template>

                            <!-- Image upload -->
                            <template v-if="selectedEl.type === 'image'">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-0.5">Image</label>
                                    <label class="block w-full text-center py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded-lg cursor-pointer transition-colors text-slate-600 border border-slate-300">
                                        {{ selectedEl.src ? 'Replace' : 'Upload Image' }}
                                        <input type="file" accept="image/*" class="hidden" @change="uploadElementImage" />
                                    </label>
                                    <button v-if="selectedEl.src" @click="selectedEl.src = null" class="mt-1 text-xs text-red-500 hover:text-red-700">✕ Remove</button>
                                </div>
                                <div><label class="block text-xs text-slate-500 mb-0.5">Fit</label>
                                    <select v-model="selectedEl.objectFit" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none">
                                        <option value="contain">Contain</option>
                                        <option value="cover">Cover</option>
                                        <option value="fill">Fill</option>
                                    </select></div>
                            </template>

                            <!-- Logo fit -->
                            <template v-if="selectedEl.type === 'logo'">
                                <div><label class="block text-xs text-slate-500 mb-0.5">Fit</label>
                                    <select v-model="selectedEl.objectFit" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none">
                                        <option value="contain">Contain</option>
                                        <option value="cover">Cover</option>
                                    </select></div>
                            </template>

                            <!-- Text styling (shared by text, field, multiline) -->
                            <template v-if="['text','field','multiline'].includes(selectedEl.type)">
                                <div><label class="block text-xs text-slate-500 mb-0.5">Font Size</label>
                                    <div class="flex items-center gap-1">
                                        <input type="range" v-model.number="selectedEl.fontSize" min="6" max="60" step="1" class="flex-1" />
                                        <span class="text-xs text-slate-600 w-7">{{ selectedEl.fontSize }}</span>
                                    </div>
                                </div>
                                <div><label class="block text-xs text-slate-500 mb-0.5">Color</label>
                                    <div class="flex items-center gap-1">
                                        <input type="color" v-model="selectedEl.color" class="w-7 h-7 rounded border border-slate-200 cursor-pointer flex-shrink-0" />
                                        <input type="text" v-model="selectedEl.color" class="flex-1 border border-slate-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="selectedEl.fontWeight = selectedEl.fontWeight === 'bold' ? 'normal' : 'bold'"
                                            :class="['flex-1 py-1 text-xs rounded border font-bold transition-colors', selectedEl.fontWeight === 'bold' ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 text-slate-600']">
                                        Bold
                                    </button>
                                    <button v-for="align in ['left','center','right']" :key="align"
                                            @click="selectedEl.textAlign = align"
                                            :class="['flex-1 py-1 text-xs rounded border transition-colors', selectedEl.textAlign === align ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 text-slate-600']">
                                        {{ align === 'left' ? '⬅' : align === 'center' ? '↔' : '➡' }}
                                    </button>
                                </div>
                            </template>

                            <!-- Line styling -->
                            <template v-if="selectedEl.type === 'line'">
                                <div><label class="block text-xs text-slate-500 mb-0.5">Color</label>
                                    <div class="flex items-center gap-1">
                                        <input type="color" v-model="selectedEl.color" class="w-7 h-7 rounded border border-slate-200 cursor-pointer flex-shrink-0" />
                                        <input type="text" v-model="selectedEl.color" class="flex-1 border border-slate-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                    </div>
                                </div>
                                <div><label class="block text-xs text-slate-500 mb-0.5">Thickness (px)</label>
                                    <input type="number" v-model.number="selectedEl.thickness" min="1" max="10" step="1" class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:outline-none" /></div>
                            </template>

                            <!-- Actions -->
                            <div class="flex gap-2 pt-1 border-t border-slate-100">
                                <button @click="duplicateSelected" class="flex-1 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded text-slate-600">Duplicate</button>
                                <button @click="deleteSelected"    class="flex-1 py-1.5 text-xs bg-red-50 hover:bg-red-100 rounded text-red-600">Delete</button>
                            </div>
                            <div class="flex gap-2">
                                <button @click="bringFront" class="flex-1 py-1 text-xs border border-slate-300 rounded text-slate-500 hover:bg-slate-50">Bring Front</button>
                                <button @click="sendBack"   class="flex-1 py-1 text-xs border border-slate-300 rounded text-slate-500 hover:bg-slate-50">Send Back</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-3 bg-amber-50 border border-amber-200 rounded-xl p-3">
                    <p class="text-xs text-amber-700 font-semibold mb-1">Variable Reference</p>
                    <div class="text-xs text-amber-600 space-y-0.5">
                        <div><code class="bg-amber-100 px-1 rounded">{name}</code> Full name</div>
                        <div><code class="bg-amber-100 px-1 rounded">{class_section}</code> X - A</div>
                        <div><code class="bg-amber-100 px-1 rounded">{academic_year}</code> 2026-27</div>
                        <div><code class="bg-amber-100 px-1 rounded">{cert_date}</code> Issue date</div>
                        <div v-for="v in form.custom_vars" :key="v.key">
                            <code class="bg-amber-100 px-1 rounded">{{'{'}}{{ v.key }}{{'}'}}</code> {{ v.label }}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </SchoolLayout>
</template>

<style scoped>
.rh {
    position: absolute; width: 8px; height: 8px;
    background: #fff; border: 1.5px solid #2563eb;
    border-radius: 2px; z-index: 30;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.15);
}
.rh-nw { top: -4px;            left: -4px;            cursor: nw-resize; }
.rh-n  { top: -4px;            left: calc(50% - 4px); cursor: n-resize; }
.rh-ne { top: -4px;            right: -4px;           cursor: ne-resize; }
.rh-e  { top: calc(50% - 4px); right: -4px;           cursor: e-resize; }
.rh-se { bottom: -4px;         right: -4px;           cursor: se-resize; }
.rh-s  { bottom: -4px;         left: calc(50% - 4px); cursor: s-resize; }
.rh-sw { bottom: -4px;         left: -4px;            cursor: sw-resize; }
.rh-w  { top: calc(50% - 4px); left: -4px;            cursor: w-resize; }
</style>
