<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useSchoolStore } from '@/stores/useSchoolStore';

const schoolStore = useSchoolStore();

const props = defineProps({
    students:    { type: Array,  required: true },
    school:      { type: Object, required: true },
    template:    { type: Object, required: true },
    custom_vals: { type: Object, default: () => ({}) },
    cert_date:   { type: String, default: '' },
});

const tpl = computed(() => props.template);

// ── Card background ───────────────────────────────────────────────
const cardBg = computed(() => {
    const bg = tpl.value.background;
    if (!bg) return { background: '#ffffff' };
    const side = bg.front !== undefined ? bg.front : bg;
    return side?.type === 'image'
        ? { backgroundImage: `url(${side.value})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { background: side?.value || '#ffffff' };
});

// ── Orientation ───────────────────────────────────────────────────
const isPortrait = computed(() => tpl.value.orientation === 'portrait');

// ── Variable resolution ───────────────────────────────────────────
const currentYear  = new Date().getFullYear();
const academicYear = `${currentYear}-${String(currentYear + 1).slice(2)}`;

const studentVars = (student) => ({
    name:          student.name          || '',
    first_name:    student.first_name    || '',
    last_name:     student.last_name     || '',
    class:         student.class         || '',
    section:       student.section       || '',
    class_section: student.class && student.section
                       ? `${student.class} - ${student.section}`
                       : (student.class || ''),
    roll_no:       student.roll_no       || '',
    admission_no:  student.admission_no  || '',
    blood_group:   student.blood_group   || '',
    dob:           student.dob ? schoolStore.fmtDate(student.dob) : '',
    father_name:   student.father_name   || '',
    mother_name:   student.mother_name   || '',
    address:       student.address       || '',
    gender:        student.gender        || '',
    school_name:   props.school?.name    || '',
    academic_year: academicYear,
    cert_date:     props.cert_date       || '',
    ...props.custom_vals,
});

const resolveTemplate = (template, vars) => {
    if (!template) return '';
    return template.replace(/\{(\w+)\}/g, (_, key) => vars[key] ?? `{${key}}`);
};

const fieldValue = (el, vars) =>
    (el.prefix || '') + (vars[el.field] ?? '') + (el.suffix || '');

// ── Element styles ────────────────────────────────────────────────
const elStyle = (el) => {
    const base = {
        position:  'absolute',
        left:      el.x + '%',
        top:       el.y + '%',
        width:     el.w + '%',
        boxSizing: 'border-box',
        overflow:  'hidden',
    };
    if (el.type === 'logo') {
        base.aspectRatio = '1 / 1';
    } else if (el.h) {
        base.height = el.h + '%';
    }
    return base;
};

const textStyle = (el) => ({
    fontSize:    (el.fontSize || 12) + 'px',
    fontWeight:  el.fontWeight  || 'normal',
    color:       el.color       || '#333333',
    textAlign:   el.textAlign   || 'left',
    lineHeight:  el.lineHeight  || 1.4,
    whiteSpace:  'nowrap',
    overflow:    'hidden',
    textOverflow:'ellipsis',
    display:     'block',
});

const multilineStyle = (el) => ({
    fontSize:    (el.fontSize || 12) + 'px',
    fontWeight:  el.fontWeight  || 'normal',
    color:       el.color       || '#333333',
    textAlign:   el.textAlign   || 'left',
    lineHeight:  el.lineHeight  || 1.7,
    whiteSpace:  'pre-wrap',
    wordWrap:    'break-word',
    overflow:    'hidden',
    display:     'block',
    height:      '100%',
});
</script>

<template>
    <!-- Toolbar -->
    <div class="no-print toolbar">
        <div class="toolbar-left">
            <span class="toolbar-title">Certificates</span>
            <span class="toolbar-name">{{ tpl.name }}</span>
            <span class="toolbar-count">{{ students.length }} student{{ students.length !== 1 ? 's' : '' }}</span>
        </div>
        <div class="toolbar-right">
            <button @click="window.print()" class="btn-print">🖨 Print</button>
            <Link href="/school/utility/certificates" class="btn-back">← Templates</Link>
        </div>
    </div>

    <!-- Empty state -->
    <div v-if="!students.length" class="no-print empty-state">
        <div class="empty-icon">🎓</div>
        <h2>No students found</h2>
        <p>Try adjusting the class or section filter.</p>
        <Link href="/school/utility/certificates" class="btn-back-link">← Back to Templates</Link>
    </div>

    <!-- One certificate per student -->
    <div v-else>
        <div v-for="student in students" :key="student.id"
             :class="['certificate', isPortrait ? 'orient-portrait' : 'orient-landscape']"
             :style="cardBg">

            <template v-for="el in tpl.elements" :key="el.id">

                <!-- School logo -->
                <div v-if="el.type === 'logo'" :style="elStyle(el)">
                    <img v-if="school.logo" :src="school.logo"
                         :style="{ width:'100%', height:'100%', objectFit: el.objectFit || 'contain', display:'block' }" />
                </div>

                <!-- Uploaded image (signature, seal, etc.) -->
                <div v-else-if="el.type === 'image' && el.src" :style="elStyle(el)">
                    <img :src="el.src"
                         :style="{ width:'100%', height:'100%', objectFit: el.objectFit || 'contain', display:'block' }" />
                </div>

                <!-- Paragraph with variable substitution -->
                <div v-else-if="el.type === 'multiline'"
                     :style="{ ...elStyle(el), ...multilineStyle(el) }">
                    {{ resolveTemplate(el.template, studentVars(student)) }}
                </div>

                <!-- Divider -->
                <div v-else-if="el.type === 'line'"
                     :style="{ ...elStyle(el), borderTop: `${el.thickness || 1}px solid ${el.color || '#cbd5e1'}` }">
                </div>

                <!-- Static text -->
                <div v-else-if="el.type === 'text'" :style="{ ...elStyle(el), ...textStyle(el) }">
                    {{ el.text }}
                </div>

                <!-- Data field -->
                <div v-else-if="el.type === 'field'" :style="{ ...elStyle(el), ...textStyle(el) }">
                    {{ fieldValue(el, studentVars(student)) }}
                </div>

            </template>
        </div>
    </div>
</template>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #e2e8f0; font-family: 'Inter', system-ui, -apple-system, sans-serif; }

/* ── Toolbar ── */
.toolbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    background: #1e293b; color: #fff;
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 24px; height: 48px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.toolbar-left  { display: flex; align-items: center; gap: 10px; }
.toolbar-right { display: flex; align-items: center; gap: 10px; }
.toolbar-title { font-weight: 700; font-size: 15px; }
.toolbar-name  { font-size: 13px; color: #e2e8f0; }
.toolbar-count { font-size: 12px; color: #94a3b8; background: #334155; padding: 2px 8px; border-radius: 12px; }
.btn-print { background: #2563eb; color: #fff; border: none; border-radius: 8px; padding: 6px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-print:hover { background: #1d4ed8; }
.btn-back { color: #94a3b8; font-size: 13px; text-decoration: none; padding: 6px 12px; border-radius: 8px; }
.btn-back:hover { color: #fff; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 8px; color: #475569; }
.empty-icon  { font-size: 48px; }
.empty-state h2 { font-size: 20px; font-weight: 700; }
.btn-back-link { margin-top: 12px; color: #2563eb; text-decoration: underline; font-size: 14px; }

/* ── Certificate (screen) ── */
.certificate {
    position: relative;
    overflow: hidden;
    margin: 56px auto 24px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.15);
}
.certificate.orient-landscape { width: 297mm; height: 210mm; }
.certificate.orient-portrait  { width: 210mm; height: 297mm; }

/* ── Print ── */
@media print {
    .no-print { display: none !important; }
    body { background: #fff; }

    .certificate {
        margin: 0;
        box-shadow: none;
        page-break-after: always;
        break-after: page;
    }
    .certificate.orient-landscape { width: 297mm; height: 210mm; }
    .certificate.orient-portrait  { width: 210mm; height: 297mm; }

    @page { size: A4; margin: 0; }
}
</style>
