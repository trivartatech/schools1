<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
    { type: 'qr',     label: 'QR Code',           icon: '▦',  defaultW: 20, defaultH: 20 },
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
    { type: 'field',  field: 'mother_name',        label: 'Mother Name',      icon: '👩', defaultW: 45 },
    { type: 'field',  field: 'address',            label: 'Address',          icon: '📍', defaultW: 70 },
    { type: 'field',  field: 'academic_year',      label: 'Academic Year',    icon: '📆', defaultW: 28 },
    { type: 'text',   label: 'Custom Text',        icon: 'T',  defaultW: 40 },
    { type: 'line',   label: 'Divider Line',       icon: '—',  defaultW: 80 },
];

// ── Default elements (front side only) ───────────────────────────
const defaultElements = () => [
    { id: 'e1', side: 'front', type: 'field', field: 'school_name', label: 'School Name',      x: 2,  y: 3,  w: 96, fontSize: 12, fontWeight: 'bold',   color: '#ffffff', textAlign: 'center', prefix: '', suffix: '' },
    { id: 'e2', side: 'front', type: 'text',  text: 'STUDENT IDENTITY CARD',                   x: 2,  y: 12, w: 96, fontSize: 8,  fontWeight: 'normal',  color: '#bfdbfe', textAlign: 'center' },
    { id: 'e3', side: 'front', type: 'photo',                                                   x: 3,  y: 20, w: 22, h: 65, borderRadius: 4 },
    { id: 'e4', side: 'front', type: 'field', field: 'name',         label: 'Full Name',        x: 27, y: 22, w: 48, fontSize: 13, fontWeight: 'bold',   color: '#ffffff', textAlign: 'left',   prefix: '', suffix: '' },
    { id: 'e5', side: 'front', type: 'field', field: 'class_section', label: 'Class & Section', x: 27, y: 38, w: 48, fontSize: 10, fontWeight: 'normal', color: '#bfdbfe', textAlign: 'left',   prefix: 'Class: ', suffix: '' },
    { id: 'e6', side: 'front', type: 'field', field: 'roll_no',      label: 'Roll No',          x: 27, y: 53, w: 28, fontSize: 10, fontWeight: 'normal', color: '#e2e8f0', textAlign: 'left',   prefix: 'Roll: ', suffix: '' },
    { id: 'e7', side: 'front', type: 'field', field: 'blood_group',  label: 'Blood Group',      x: 57, y: 53, w: 18, fontSize: 10, fontWeight: 'bold',   color: '#fca5a5', textAlign: 'left',   prefix: '', suffix: '' },
    { id: 'e8', side: 'front', type: 'qr',                                                      x: 78, y: 20, w: 20, h: 20 },
    { id: 'e9', side: 'front', type: 'field', field: 'academic_year', label: 'Academic Year',   x: 2,  y: 90, w: 96, fontSize: 8,  fontWeight: 'normal', color: '#94a3b8', textAlign: 'center', prefix: '', suffix: '' },
];

// ── Background helpers ────────────────────────────────────────────
const normalizeBackground = (bg) => {
    if (!bg) return { front: { type: 'color', value: '#1e3a8a' }, back: { type: 'color', value: '#1a1a2e' } };
    if (bg.front !== undefined || bg.back !== undefined) return {
        front: bg.front ?? { type: 'color', value: '#1e3a8a' },
        back:  bg.back  ?? { type: 'color', value: '#1a1a2e' },
    };
    // Old format: single background → assign to front, default dark for back
    return { front: bg, back: { type: 'color', value: '#1a1a2e' } };
};

// ── Inertia form ──────────────────────────────────────────────────
const form = useForm({
    name:        props.template?.name        ?? '',
    orientation: props.template?.orientation ?? 'landscape',
    background:  normalizeBackground(props.template?.background),
    elements:    props.template?.elements?.map(el => ({ side: 'front', ...el })) ?? defaultElements(),
    columns:     props.template?.columns     ?? 2,
});

const save = () => {
    if (isEdit.value) {
        form.put(`/school/utility/id-cards/${props.template.id}`, { onError: () => {} });
    } else {
        form.post('/school/utility/id-cards', { onError: () => {} });
    }
};

// ── Canvas sizing per orientation ─────────────────────────────────
const canvasW = computed(() => form.orientation === 'portrait' ? 324 : 514);
const canvasH = computed(() => form.orientation === 'portrait' ? 514 : 324);

// ── Front / Back side ─────────────────────────────────────────────
const currentSide = ref('front');

const visibleElements = computed(() =>
    form.elements.filter(el => (el.side ?? 'front') === currentSide.value)
);

const frontCount = computed(() => form.elements.filter(el => (el.side ?? 'front') === 'front').length);
const backCount  = computed(() => form.elements.filter(el => el.side === 'back').length);

// ── Drag state ────────────────────────────────────────────────────
const canvasRef = ref(null);
const dragging  = ref(null);
const resizing  = ref(null);
const selected  = ref(null);

const startDrag = (e, el) => {
    if (e.button !== 0 || resizing.value) return;
    e.preventDefault();
    e.stopPropagation();
    selected.value = el.id;
    const rect = canvasRef.value.getBoundingClientRect();
    dragging.value = {
        id:      el.id,
        offsetX: e.clientX - rect.left - (el.x / 100) * rect.width,
        offsetY: e.clientY - rect.top  - (el.y / 100) * rect.height,
    };
};

// ── Resize state ──────────────────────────────────────────────────
const startResize = (e, el, handle) => {
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    selected.value = el.id;
    resizing.value = { id: el.id, handle, lastX: e.clientX, lastY: e.clientY };
};

// ── Mouse handlers ────────────────────────────────────────────────
const onMouseMove = (e) => {
    if (!canvasRef.value) return;
    const rect = canvasRef.value.getBoundingClientRect();

    // Resize
    if (resizing.value) {
        const dx = ((e.clientX - resizing.value.lastX) / rect.width)  * 100;
        const dy = ((e.clientY - resizing.value.lastY) / rect.height) * 100;
        const el = form.elements.find(el => el.id === resizing.value.id);
        if (el) {
            const h = resizing.value.handle;

            if (el.type === 'photo' || el.type === 'qr') {
                // These use CSS aspect-ratio — only width drives the size.
                // Convert vertical drag (dy) to equivalent horizontal % using canvas ratio.
                const wRatio = canvasW.value / canvasH.value;
                if (h.includes('e')) { el.w = Math.max(5, el.w + dx); }
                if (h.includes('w')) {
                    const nw = Math.max(5, el.w - dx);
                    el.x = Math.max(0, el.x + el.w - nw);
                    el.w = nw;
                }
                if (h === 'n') { el.w = Math.max(5, el.w - dy * wRatio); }
                if (h === 's') { el.w = Math.max(5, el.w + dy * wRatio); }
            } else {
                if (h.includes('e')) { el.w = Math.max(5, el.w + dx); }
                if (h.includes('s')) { el.h = Math.max(5, (el.h ?? 20) + dy); }
                if (h.includes('w')) {
                    const nw = Math.max(5, el.w - dx);
                    el.x = Math.max(0, el.x + el.w - nw);
                    el.w = nw;
                }
                if (h.includes('n')) {
                    const nh = Math.max(5, (el.h ?? 20) - dy);
                    el.y = Math.max(0, el.y + (el.h ?? 20) - nh);
                    el.h = nh;
                }
            }
        }
        resizing.value.lastX = e.clientX;
        resizing.value.lastY = e.clientY;
        return;
    }

    // Drag
    if (dragging.value) {
        const el = form.elements.find(el => el.id === dragging.value.id);
        if (!el) return;
        let nx = ((e.clientX - rect.left - dragging.value.offsetX) / rect.width)  * 100;
        let ny = ((e.clientY - rect.top  - dragging.value.offsetY) / rect.height) * 100;
        nx = Math.round(nx * 2) / 2;
        ny = Math.round(ny * 2) / 2;
        el.x = Math.max(0, Math.min(100 - el.w, nx));
        el.y = Math.max(0, Math.min(97, ny));
    }
};

const stopInteraction = () => { dragging.value = null; resizing.value = null; };
const clickCanvas = (e) => { if (e.target === canvasRef.value) selected.value = null; };

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
    const base = { id, side: currentSide.value, type: def.type, x: 10, y: 40, w: def.defaultW || 40 };
    if (def.type === 'photo') {
        Object.assign(base, { h: def.defaultH || 55, borderRadius: 4 });
    } else if (def.type === 'qr') {
        // QR is always square
        const size = def.defaultW || 20;
        Object.assign(base, { w: size, h: size });
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
        form.background[currentSide.value] = { type: 'image', value: ev.target.result };
    };
    reader.readAsDataURL(file);
    e.target.value = '';
};

const removeBgImage = () => {
    form.background[currentSide.value] = { type: 'color', value: currentSide.value === 'front' ? '#1e3a8a' : '#1a1a2e' };
};

// ── Canvas styles ─────────────────────────────────────────────────
const canvasBg = computed(() => {
    const bg = form.background[currentSide.value];
    if (!bg) return { background: '#1e3a8a' };
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
        outline:       selected.value === el.id ? '1.5px dashed rgba(255,255,255,0.9)' : '1px dashed rgba(255,255,255,0.15)',
        outlineOffset: '1px',
        boxSizing:     'border-box',
        overflow:      'visible',
    };
    // Photo and QR use CSS aspect-ratio so they stay geometrically correct
    // regardless of the card's own aspect ratio. Height % alone is unreliable
    // because the card is not square (e.g. 514×324 landscape).
    if (el.type === 'photo') {
        base.aspectRatio = '3 / 4';   // standard passport portrait
    } else if (el.type === 'qr') {
        base.aspectRatio = '1 / 1';   // always square
    } else if (el.h) {
        base.height = el.h + '%';
    }
    return base;
};

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
    first_name:    'Aarav',
    last_name:     'Sharma',
    class:         'X',
    section:       'A',
    class_section: 'X - A',
    roll_no:       '12',
    admission_no:  'ADM/24/001',
    blood_group:   'B+',
    dob:           '15 Mar 2010',
    parent_phone:  '9876543210',
    father_name:   'Raj Sharma',
    mother_name:   'Priya Sharma',
    address:       '42, Shiv Nagar, Delhi',
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
                   class="text-slate-400 hover:text-slate-600 transition-colors flex-shrink-0 text-sm">
                    ← Back
                </a>
                <input v-model="form.name"
                       type="text"
                       placeholder="Template name (e.g. Standard 2026)"
                       class="flex-1 min-w-0 max-w-xs border border-slate-300 rounded-lg px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                       :class="{ 'border-red-400': form.errors.name }" />
                <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Orientation -->
                <div class="flex items-center gap-0 border border-slate-300 rounded-lg overflow-hidden">
                    <button v-for="o in ['landscape', 'portrait']" :key="o"
                            @click="form.orientation = o"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors',
                                     form.orientation === o ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
                        {{ o === 'landscape' ? '⬛ Landscape' : '▬ Portrait' }}
                    </button>
                </div>

                <!-- Columns -->
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
                <div class="space-y-0.5">
                    <button v-for="def in FIELDS" :key="def.type + (def.field || '')"
                            @click="addElement(def)"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-blue-50 hover:text-blue-700 transition-colors text-left border border-transparent hover:border-blue-200">
                        <span class="text-sm w-5 text-center flex-shrink-0">{{ def.icon || '▪' }}</span>
                        <span class="truncate text-xs">{{ def.label }}</span>
                    </button>
                </div>

                <!-- Background -->
                <div class="mt-4 pt-3 border-t border-slate-200">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Background</div>
                    <p class="text-xs text-slate-400 mb-2">Editing: <span class="font-medium text-slate-600 capitalize">{{ currentSide }}</span></p>

                    <div v-if="form.background[currentSide]?.type === 'color'" class="flex items-center gap-2 mb-2">
                        <input type="color" v-model="form.background[currentSide].value"
                               class="w-8 h-8 rounded border border-slate-200 cursor-pointer flex-shrink-0" />
                        <input type="text" v-model="form.background[currentSide].value"
                               class="flex-1 border border-slate-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-400" />
                    </div>
                    <div v-else class="flex items-center gap-2 mb-2">
                        <div class="text-xs text-green-600 flex-1 truncate">Image set</div>
                        <button @click="removeBgImage" class="text-xs text-red-500 hover:text-red-700 flex-shrink-0">✕ Remove</button>
                    </div>

                    <label class="block w-full text-center py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded-lg cursor-pointer transition-colors text-slate-600 border border-slate-300">
                        Upload Image
                        <input ref="bgInput" type="file" accept="image/*" class="hidden" @change="onBgUpload" />
                    </label>
                </div>
            </div>

            <!-- ── Center: Canvas ── -->
            <div class="flex-1 min-w-0">
                <!-- Front / Back toggle -->
                <div class="flex items-center justify-center gap-2 mb-3">
                    <button v-for="side in ['front', 'back']" :key="side"
                            @click="currentSide = side; selected = null"
                            :class="['px-5 py-1.5 text-xs font-semibold rounded-full border transition-colors',
                                     currentSide === side
                                         ? 'bg-slate-800 text-white border-slate-800'
                                         : 'text-slate-500 border-slate-300 hover:border-slate-400']">
                        {{ side === 'front' ? 'Front' : 'Back' }}
                        <span class="ml-1 opacity-60">{{ side === 'front' ? frontCount : backCount }}</span>
                    </button>
                </div>

                <div class="bg-slate-100 rounded-xl p-4 flex flex-col items-center gap-3">
                    <div
                        ref="canvasRef"
                        class="relative rounded-lg shadow-xl flex-shrink-0"
                        :style="{ width: canvasW + 'px', height: canvasH + 'px', overflow: 'visible' }"
                        @click="clickCanvas"
                    >
                        <!-- Background clipped to card shape; outer div stays overflow:visible so resize handles poke out -->
                        <div class="absolute inset-0 rounded-lg overflow-hidden pointer-events-none"
                             :style="canvasBg"></div>

                        <div
                            v-for="el in visibleElements" :key="el.id"
                            :style="elStyle(el)"
                            @mousedown="(e) => startDrag(e, el)"
                            @click.stop="selected = el.id"
                        >
                            <!-- Photo placeholder -->
                            <template v-if="el.type === 'photo'">
                                <div class="w-full h-full bg-white/20 flex items-center justify-center overflow-hidden"
                                     :style="{ borderRadius: (el.borderRadius || 0) + 'px' }">
                                    <span style="font-size:1.8em">👤</span>
                                </div>
                            </template>

                            <!-- QR placeholder -->
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

                            <!-- Divider line -->
                            <template v-else-if="el.type === 'line'">
                                <div class="w-full" :style="{ borderTop: `1px solid ${el.color || '#ffffff'}` }"></div>
                            </template>

                            <!-- Text / field -->
                            <template v-else>
                                <div :style="textCss(el)">{{ getPreview(el) }}</div>
                            </template>

                            <!-- Resize handles (selected only) -->
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
                                <!-- Photo/QR: height auto via aspect-ratio — only show width -->
                                <div v-if="selectedEl.type !== 'photo' && selectedEl.type !== 'qr' && selectedEl.h !== undefined">
                                    <label class="block text-xs text-slate-500 mb-0.5">Height %</label>
                                    <input type="number" v-model.number="selectedEl.h" min="1" max="100" step="1"
                                           class="w-full border border-slate-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                                </div>
                            </div>
                            <!-- Aspect-ratio hint -->
                            <p v-if="selectedEl.type === 'photo'" class="text-xs text-slate-400">
                                Height auto (3:4 passport ratio)
                            </p>
                            <p v-if="selectedEl.type === 'qr'" class="text-xs text-slate-400">
                                Height auto (1:1 square)
                            </p>

                            <!-- Text properties -->
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
                                        <input type="text" v-model="selectedEl.suffix"
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
                                        class="flex-1 py-1 text-xs border border-slate-300 rounded text-slate-500 hover:bg-slate-50">
                                    Bring Front
                                </button>
                                <button @click="sendBack"
                                        class="flex-1 py-1 text-xs border border-slate-300 rounded text-slate-500 hover:bg-slate-50">
                                    Send Back
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-3 bg-blue-50 border border-blue-200 rounded-xl p-3">
                    <p class="text-xs text-blue-700 font-semibold mb-1">Tips</p>
                    <ul class="text-xs text-blue-600 space-y-1 list-disc list-inside">
                        <li>Front / Back toggle above canvas</li>
                        <li>Drag handles to resize elements</li>
                        <li>QR code stays square automatically</li>
                        <li>Upload card image as background</li>
                    </ul>
                </div>
            </div>

        </div>

    </SchoolLayout>
</template>

<style scoped>
/* ── Resize handles ── */
.rh {
    position: absolute;
    width: 8px;
    height: 8px;
    background: #fff;
    border: 1.5px solid #2563eb;
    border-radius: 2px;
    z-index: 30;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.15);
}
.rh-nw { top: -4px;              left: -4px;              cursor: nw-resize; }
.rh-n  { top: -4px;              left: calc(50% - 4px);   cursor: n-resize; }
.rh-ne { top: -4px;              right: -4px;             cursor: ne-resize; }
.rh-e  { top: calc(50% - 4px);   right: -4px;             cursor: e-resize; }
.rh-se { bottom: -4px;           right: -4px;             cursor: se-resize; }
.rh-s  { bottom: -4px;           left: calc(50% - 4px);   cursor: s-resize; }
.rh-sw { bottom: -4px;           left: -4px;              cursor: sw-resize; }
.rh-w  { top: calc(50% - 4px);   left: -4px;              cursor: w-resize; }
</style>
