<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import IdCardQR from '@/Components/IdCardQR.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';

const schoolStore = useSchoolStore();

const props = defineProps({
    students: { type: Array,  required: true },
    school:   { type: Object, required: true },
    template: { type: Object, required: true },
});

const tpl = computed(() => props.template);

// ── Orientation ───────────────────────────────────────────────────
const isPortrait = computed(() => tpl.value.orientation === 'portrait');

// ── Background per side (supports new {front,back} and old flat format) ──
const getBgStyle = (side) => {
    const bg = tpl.value.background;
    if (!bg) return { background: '#1e3a8a' };
    const sideBg = (bg.front !== undefined) ? (bg[side] ?? bg.front) : bg;
    return sideBg?.type === 'image'
        ? { backgroundImage: `url(${sideBg.value})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { background: sideBg?.value || '#1e3a8a' };
};

const cardBgFront = computed(() => getBgStyle('front'));
const cardBgBack  = computed(() => getBgStyle('back'));

// ── Elements split by side ────────────────────────────────────────
const frontElements = computed(() => tpl.value.elements.filter(el => !el.side || el.side === 'front'));
const backElements  = computed(() => tpl.value.elements.filter(el => el.side === 'back'));
const hasBack       = computed(() => backElements.value.length > 0);

// ── Element positioning ───────────────────────────────────────────
const elStyle = (el) => {
    const base = {
        position:  'absolute',
        left:      el.x + '%',
        top:       el.y + '%',
        width:     el.w + '%',
        overflow:  'hidden',
        boxSizing: 'border-box',
    };
    if (el.type === 'photo') {
        base.aspectRatio = '3 / 4';
    } else if (el.type === 'qr') {
        base.aspectRatio = '1 / 1';
    } else if (el.h) {
        base.height = el.h + '%';
    }
    return base;
};

const textStyle = (el) => ({
    fontSize:     (el.fontSize || 11) + 'px',
    fontWeight:   el.fontWeight  || 'normal',
    color:        el.color       || '#ffffff',
    textAlign:    el.textAlign   || 'left',
    lineHeight:   '1.2',
    whiteSpace:   'nowrap',
    overflow:     'hidden',
    textOverflow: 'ellipsis',
    display:      'block',
});

// ── Field value resolver ──────────────────────────────────────────
const currentYear  = new Date().getFullYear();
const academicYear = `${currentYear}-${String(currentYear + 1).slice(2)}`;

const fieldValue = (student, field) => {
    const map = {
        name:          student.name,
        first_name:    student.first_name,
        last_name:     student.last_name,
        class:         student.class    ? `Class ${student.class}` : '',
        section:       student.section  || '',
        class_section: student.class && student.section
                           ? `${student.class} - ${student.section}`
                           : (student.class || ''),
        roll_no:       student.roll_no      || '',
        admission_no:  student.admission_no || '',
        blood_group:   student.blood_group  || '',
        dob:           student.dob ? schoolStore.fmtDate(student.dob) : '',
        parent_phone:  student.parent_phone || '',
        father_name:   student.father_name  || '',
        mother_name:   student.mother_name  || '',
        address:       student.address      || '',
        school_name:   props.school?.name   || '',
        academic_year: academicYear,
    };
    return map[field] ?? '';
};

const getFieldText = (el, student) =>
    (el.prefix || '') + fieldValue(student, el.field) + (el.suffix || '');

// ── QR URL ────────────────────────────────────────────────────────
const qrUrl = (uuid) => `${window.location.origin}/q/${uuid}`;

// ── Grid class ────────────────────────────────────────────────────
const gridClass = computed(() => {
    const cols   = tpl.value?.columns || 2;
    const orient = isPortrait.value ? 'portrait' : 'landscape';
    return `grid-cols-${cols} orient-${orient}`;
});
</script>

<template>
    <!-- Toolbar -->
    <div class="no-print toolbar">
        <div class="toolbar-left">
            <span class="toolbar-title">ID Cards</span>
            <span class="toolbar-name">{{ tpl.name }}</span>
            <span class="toolbar-count">{{ students.length }} student{{ students.length !== 1 ? 's' : '' }}</span>
            <span class="toolbar-cols">{{ tpl.columns }} col/page</span>
            <span v-if="hasBack" class="toolbar-cols">Front + Back</span>
        </div>
        <div class="toolbar-right">
            <button @click="window.print()" class="btn-print">🖨 Print</button>
            <Link href="/school/utility/id-cards" class="btn-back">← Templates</Link>
        </div>
    </div>

    <!-- Empty -->
    <div v-if="!students.length" class="no-print empty-state">
        <div class="empty-icon">🪪</div>
        <h2>No students found</h2>
        <p>Try adjusting the class or section filter.</p>
        <Link href="/school/utility/id-cards" class="btn-back-link">← Back to Templates</Link>
    </div>

    <template v-else>
        <!-- ── Front side ── -->
        <div class="cards-page" :class="gridClass">
            <div v-for="student in students" :key="`f-${student.id}`" class="id-card" :style="cardBgFront">
                <template v-for="el in frontElements" :key="el.id">
                    <div v-if="el.type === 'photo'" :style="elStyle(el)">
                        <img v-if="student.photo_url" :src="student.photo_url"
                             :style="{ width:'100%', height:'100%', objectFit:'cover', borderRadius:(el.borderRadius||0)+'px', display:'block' }" />
                        <div v-else :style="{ width:'100%', height:'100%', background:'rgba(255,255,255,0.2)', display:'flex', alignItems:'center', justifyContent:'center', borderRadius:(el.borderRadius||0)+'px', fontSize:'1.4em', color:'#fff', fontWeight:'bold' }">
                            {{ (student.first_name||'S')[0].toUpperCase() }}
                        </div>
                    </div>
                    <div v-else-if="el.type === 'qr' && student.uuid"
                         :style="{ ...elStyle(el), padding:'2px', background:'#fff', boxSizing:'border-box', borderRadius:'2px' }">
                        <IdCardQR :value="qrUrl(student.uuid)" :size="80" style="width:100%;height:100%" />
                    </div>
                    <div v-else-if="el.type === 'line'" :style="{ ...elStyle(el), borderTop:`1px solid ${el.color||'#fff'}` }"></div>
                    <div v-else-if="el.type === 'text' || el.type === 'field'" :style="{ ...elStyle(el), ...textStyle(el) }">
                        <template v-if="el.type === 'text'">{{ el.text }}</template>
                        <template v-else>{{ getFieldText(el, student) }}</template>
                    </div>
                </template>
            </div>
        </div>

        <!-- ── Back side (only if back elements exist) ── -->
        <div v-if="hasBack" class="cards-page page-break-before" :class="gridClass">
            <div v-for="student in students" :key="`b-${student.id}`" class="id-card" :style="cardBgBack">
                <template v-for="el in backElements" :key="el.id">
                    <div v-if="el.type === 'photo'" :style="elStyle(el)">
                        <img v-if="student.photo_url" :src="student.photo_url"
                             :style="{ width:'100%', height:'100%', objectFit:'cover', borderRadius:(el.borderRadius||0)+'px', display:'block' }" />
                        <div v-else :style="{ width:'100%', height:'100%', background:'rgba(255,255,255,0.2)', display:'flex', alignItems:'center', justifyContent:'center', borderRadius:(el.borderRadius||0)+'px', fontSize:'1.4em', color:'#fff', fontWeight:'bold' }">
                            {{ (student.first_name||'S')[0].toUpperCase() }}
                        </div>
                    </div>
                    <div v-else-if="el.type === 'qr' && student.uuid"
                         :style="{ ...elStyle(el), padding:'2px', background:'#fff', boxSizing:'border-box', borderRadius:'2px' }">
                        <IdCardQR :value="qrUrl(student.uuid)" :size="80" style="width:100%;height:100%" />
                    </div>
                    <div v-else-if="el.type === 'line'" :style="{ ...elStyle(el), borderTop:`1px solid ${el.color||'#fff'}` }"></div>
                    <div v-else-if="el.type === 'text' || el.type === 'field'" :style="{ ...elStyle(el), ...textStyle(el) }">
                        <template v-if="el.type === 'text'">{{ el.text }}</template>
                        <template v-else>{{ getFieldText(el, student) }}</template>
                    </div>
                </template>
            </div>
        </div>
    </template>
</template>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #e2e8f0; font-family: 'Inter', system-ui, -apple-system, sans-serif; }

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
.toolbar-count,
.toolbar-cols  { font-size: 12px; color: #94a3b8; background: #334155; padding: 2px 8px; border-radius: 12px; }
.btn-print { background: #2563eb; color: #fff; border: none; border-radius: 8px; padding: 6px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-print:hover { background: #1d4ed8; }
.btn-back { color: #94a3b8; font-size: 13px; text-decoration: none; padding: 6px 12px; border-radius: 8px; }
.btn-back:hover { color: #fff; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; gap: 8px; color: #475569; margin-top: 48px; }
.empty-icon  { font-size: 48px; }
.empty-state h2 { font-size: 20px; font-weight: 700; }
.btn-back-link { margin-top: 12px; color: #2563eb; text-decoration: underline; font-size: 14px; }

/* Cards page */
.cards-page { margin-top: 56px; padding: 20px; display: grid; gap: 16px; justify-items: center; }
.cards-page + .cards-page { margin-top: 24px; }

.cards-page.grid-cols-1.orient-landscape { grid-template-columns: repeat(1, minmax(0, 420px)); justify-content: center; }
.cards-page.grid-cols-2.orient-landscape { grid-template-columns: repeat(2, 340px); }
.cards-page.grid-cols-4.orient-landscape { grid-template-columns: repeat(4, 240px); }
.cards-page.grid-cols-1.orient-landscape .id-card { width: 420px; }
.cards-page.grid-cols-2.orient-landscape .id-card { width: 340px; }
.cards-page.grid-cols-4.orient-landscape .id-card { width: 240px; }

.cards-page.grid-cols-1.orient-portrait { grid-template-columns: repeat(1, minmax(0, 264px)); justify-content: center; }
.cards-page.grid-cols-2.orient-portrait { grid-template-columns: repeat(2, 214px); }
.cards-page.grid-cols-4.orient-portrait { grid-template-columns: repeat(4, 152px); }
.cards-page.grid-cols-1.orient-portrait .id-card { width: 264px; }
.cards-page.grid-cols-2.orient-portrait .id-card { width: 214px; }
.cards-page.grid-cols-4.orient-portrait .id-card { width: 152px; }

.id-card { position: relative; overflow: hidden; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.15); page-break-inside: avoid; break-inside: avoid; }
.orient-landscape .id-card { aspect-ratio: 85.6 / 54; }
.orient-portrait  .id-card { aspect-ratio: 54 / 85.6; }

@media print {
    .no-print { display: none !important; }
    body { background: #fff; }

    .cards-page { margin-top: 0; padding: 5mm; gap: 5mm; }
    .cards-page + .cards-page { margin-top: 0; }
    .page-break-before { page-break-before: always; break-before: page; }

    .cards-page.grid-cols-1.orient-landscape { grid-template-columns: 1fr; justify-items: center; }
    .cards-page.grid-cols-1.orient-landscape .id-card { width: 180mm; border-radius: 4mm; box-shadow: none; border: 0.5pt solid #d1d5db; page-break-after: always; }
    .cards-page.grid-cols-2.orient-landscape { grid-template-columns: repeat(2, 85.6mm); }
    .cards-page.grid-cols-2.orient-landscape .id-card { width: 85.6mm; border-radius: 2mm; box-shadow: none; border: 0.5pt solid #d1d5db; }
    .cards-page.grid-cols-4.orient-landscape { grid-template-columns: repeat(4, 42mm); }
    .cards-page.grid-cols-4.orient-landscape .id-card { width: 42mm; border-radius: 1mm; box-shadow: none; border: 0.5pt solid #d1d5db; }

    .cards-page.grid-cols-1.orient-portrait { grid-template-columns: 1fr; justify-items: center; }
    .cards-page.grid-cols-1.orient-portrait .id-card { width: 54mm; border-radius: 4mm; box-shadow: none; border: 0.5pt solid #d1d5db; page-break-after: always; }
    .cards-page.grid-cols-2.orient-portrait { grid-template-columns: repeat(2, 54mm); }
    .cards-page.grid-cols-2.orient-portrait .id-card { width: 54mm; border-radius: 2mm; box-shadow: none; border: 0.5pt solid #d1d5db; }
    .cards-page.grid-cols-4.orient-portrait { grid-template-columns: repeat(4, 54mm); }
    .cards-page.grid-cols-4.orient-portrait .id-card { width: 54mm; border-radius: 1mm; box-shadow: none; border: 0.5pt solid #d1d5db; }

    @page { size: A4; margin: 10mm; }
}
</style>
