<script setup>
/**
 * FilterBar — standardized one-row filter container for every index page.
 *
 * Usage:
 *   <FilterBar :active="!!(search || cls)" @clear="search = ''; cls = ''">
 *     <div class="fb-search">
 *       <svg class="fb-search-icon" .../>
 *       <input v-model="search" type="text" placeholder="Search...">
 *     </div>
 *     <select v-model="cls" style="width:160px;">...</select>
 *   </FilterBar>
 *
 * Props:
 *   active  — shows the Clear button when true
 *
 * Emits:
 *   clear   — fired when the Clear button is clicked
 *
 * Helper classes (use inside the slot):
 *   .fb-search        — wraps a search input + icon (relative container)
 *   .fb-search-icon   — the SVG icon inside .fb-search
 *   .fb-grow          — opt-in modifier on any direct child to flex-grow and
 *                       fill remaining row space (e.g. on a .fb-search wrapper
 *                       when the page has only a few filters and you want the
 *                       search field to span the row).
 */
defineProps({
    active: { type: Boolean, default: false },
})
defineEmits(['clear'])
</script>

<template>
    <div class="fb">
        <div class="fb-row">
            <slot />
            <button v-if="active" class="fb-clear" type="button" @click="$emit('clear')">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Clear
            </button>
        </div>
    </div>
</template>

<style scoped>
/* ── Container ──────────────────────────────────────────────── */
.fb {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 16px;
    margin-bottom: 20px;
    /* Single-row design: when filters overflow on narrow screens,
       scroll horizontally instead of wrapping to multiple rows. */
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.fb-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;          /* enforce single row */
    min-height: 38px;
    /* Default flex container width (100% of .fb) is enough — children
       have flex-shrink:0, so when they sum past the visible width they
       overflow naturally and .fb's overflow-x:auto shows the scrollbar.
       Setting width:max-content here caused short rows to render with
       unexpected stretching in some browsers. */
}

/* Inputs and selects must not shrink below their natural width */
.fb-row :deep(> *) {
    flex-shrink: 0;
}

/* ── Normalize every input & select inside the bar ─────────── */
.fb-row :deep(input:not([type="checkbox"]):not([type="radio"])),
.fb-row :deep(select) {
    height: 38px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    font-size: 0.875rem;
    color: #1e293b;
    padding: 0 12px;
    outline: none;
    box-shadow: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.fb-row :deep(input:not([type="checkbox"]):not([type="radio"]):focus),
.fb-row :deep(select:focus) {
    border-color: #1169cd;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(17, 105, 205, 0.08);
}
.fb-row :deep(input:disabled),
.fb-row :deep(select:disabled) {
    opacity: 0.45;
    cursor: not-allowed;
    background: #f1f5f9;
}

/* form-field labels (used in Attendance pages) */
.fb-row :deep(.form-field) {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.fb-row :deep(.form-field label) {
    font-size: 0.7rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    white-space: nowrap;
}

/* ── Search wrapper (.fb-search + .fb-search-icon) ─────────── */
.fb-row :deep(.fb-search) {
    position: relative;
    display: flex;
    align-items: center;
}
.fb-row :deep(.fb-search input) {
    padding-left: 34px;
    width: 260px;
}
.fb-row :deep(.fb-search-icon) {
    position: absolute;
    left: 10px;
    width: 15px;
    height: 15px;
    color: #94a3b8;
    pointer-events: none;
    flex-shrink: 0;
}

/* ── Clear button ───────────────────────────────────────────── */
.fb-clear {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 5px;
    height: 38px;
    padding: 0 14px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s;
}
.fb-clear:hover {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #ef4444;
}

/* ── Grow modifier — opt-in: makes a child fill remaining row space ── */
.fb-row :deep(.fb-grow) {
    flex: 1 1 auto;
    min-width: 220px;
}
.fb-row :deep(.fb-search.fb-grow input) {
    width: 100%;
}
</style>
