<script setup>
/**
 * UI Sandbox · Forms & Filters
 * FilterBar, DateRangeFilter, LedgerCombobox, SlidePanel, form tokens.
 * URL: /school/_ui-sandbox/forms
 */
import { ref, computed } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import DateRangeFilter from '@/Components/ui/DateRangeFilter.vue';
import SlidePanel from '@/Components/SlidePanel.vue';
import LedgerCombobox from '@/Components/LedgerCombobox.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

// ── FilterBar demo state ────────────────────────────────────────
const fbSearch = ref('');
const fbClass = ref('');
const fbStatus = ref('');
const fbHouse = ref('');
const fbDefaulter = ref('');
const fbActive = computed(() =>
    !!(fbSearch.value || fbClass.value || fbStatus.value || fbHouse.value || fbDefaulter.value)
);
function fbClear() {
    fbSearch.value = ''; fbClass.value = ''; fbStatus.value = '';
    fbHouse.value = ''; fbDefaulter.value = '';
}

// ── DateRangeFilter demo state ─────────────────────────────────
const drFrom = ref('');
const drTo = ref('');
const drLast = ref(null);
function onDateRange(v) { drLast.value = v; drFrom.value = v.from; drTo.value = v.to; }

// ── LedgerCombobox demo state ──────────────────────────────────
const sampleLedgers = [
    { id: 1, code: '1001', name: 'Cash in Hand',     ledger_type: { name: 'Asset' } },
    { id: 2, code: '1002', name: 'Bank — Savings',   ledger_type: { name: 'Asset' } },
    { id: 3, code: '2001', name: 'Tuition Income',   ledger_type: { name: 'Income' } },
    { id: 4, code: '2002', name: 'Transport Income', ledger_type: { name: 'Income' } },
    { id: 5, code: '3001', name: 'Salaries',         ledger_type: { name: 'Expense' } },
    { id: 6, code: '3002', name: 'Stationery Cost',  ledger_type: { name: 'Expense' } },
];
const selectedLedgerId = ref('');

// ── SlidePanel demo state ──────────────────────────────────────
const showSlideSm = ref(false);
const showSlideLg = ref(false);
</script>

<template>
    <SchoolLayout title="UI Sandbox · Forms & Filters">

        <PageHeader
            title="Forms &amp; Filters"
            subtitle="FilterBar, DateRangeFilter, LedgerCombobox, SlidePanel, plus form-grid tokens."
            back-href="/school/_ui-sandbox"
            back-label="← Back to sandbox"
        />

        <!-- ── Tokens ────────────────────────────────────────────────── -->
        <h2 class="section-heading">Tokens — form-row + form-field + form-error</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <div class="form-row form-row-2">
                <div class="form-field">
                    <label>2-col field A</label>
                    <input class="form-input" placeholder="Type something">
                </div>
                <div class="form-field">
                    <label>2-col field B</label>
                    <input class="form-input" placeholder="Another value">
                    <span class="form-error">Example error message — uses .form-error</span>
                </div>
            </div>
            <div class="form-row form-row-3" style="margin-top:14px;">
                <div class="form-field"><label>3-col</label><input class="form-input"></div>
                <div class="form-field"><label>3-col</label><input class="form-input"></div>
                <div class="form-field"><label>3-col</label><input class="form-input"></div>
            </div>
            <div class="form-row form-row-4" style="margin-top:14px;">
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
                <div class="form-field"><label>4-col</label><input class="form-input"></div>
            </div>
        </div>

        <!-- ── FilterBar ─────────────────────────────────────────────── -->
        <h2 class="section-heading">FilterBar — full row</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Always one row. Overflows horizontally on narrow screens. Clear button appears when any filter is active.
        </p>
        <FilterBar :active="fbActive" @clear="fbClear">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="fbSearch" type="search" placeholder="Search by name or admission no...">
            </div>
            <select v-model="fbClass" style="width:160px;">
                <option value="">All Classes</option>
                <option value="6">Grade 6</option>
                <option value="7">Grade 7</option>
                <option value="8">Grade 8</option>
                <option value="9">Grade 9</option>
                <option value="10">Grade 10</option>
            </select>
            <select v-model="fbStatus" style="width:140px;">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select v-model="fbHouse" style="width:140px;">
                <option value="">All Houses</option>
                <option value="red">Red</option>
                <option value="blue">Blue</option>
                <option value="green">Green</option>
                <option value="yellow">Yellow</option>
            </select>
            <select v-model="fbDefaulter" style="width:160px;">
                <option value="">All Students</option>
                <option value="1">Defaulters Only</option>
                <option value="0">Non-Defaulters</option>
            </select>
        </FilterBar>

        <h2 class="section-heading">FilterBar — minimal (one search input)</h2>
        <FilterBar :active="!!fbSearch" @clear="fbSearch = ''">
            <div class="fb-search">
                <svg class="fb-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                <input v-model="fbSearch" type="search" placeholder="Search...">
            </div>
        </FilterBar>

        <!-- ── DateRangeFilter ───────────────────────────────────────── -->
        <h2 class="section-heading">DateRangeFilter — presets + custom range</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Emits <code>@change="{ from, to }"</code>. Used by Communication/Logs, Finance reports, Attendance.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;">
            <DateRangeFilter :from="drFrom" :to="drTo" @change="onDateRange" />
            <p style="font-size:0.78rem;color:var(--text-muted);margin:14px 0 0;">
                Last @change payload: <code>{{ drLast ? JSON.stringify(drLast) : '— click a preset or Apply —' }}</code>
            </p>
        </div>

        <!-- ── LedgerCombobox ────────────────────────────────────────── -->
        <h2 class="section-heading">LedgerCombobox — searchable account picker</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Used in <code>Finance/Transactions/Create</code>. Searches code/name/type, groups by ledger type.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;max-width:520px;">
            <label style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;">Account</label>
            <LedgerCombobox v-model="selectedLedgerId" :ledgers="sampleLedgers" />
            <p style="font-size:0.78rem;color:var(--text-muted);margin:10px 0 0;">
                Selected id: <code>{{ selectedLedgerId || '—' }}</code>
            </p>
        </div>

        <!-- ── SlidePanel ────────────────────────────────────────────── -->
        <h2 class="section-heading">SlidePanel — right-side form panel</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin:-6px 0 8px;">
            Used in 12+ pages (Academics/Subjects, Sections, AcademicYears, Schedule/Periods, etc.) for create/edit forms.
        </p>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;">
            <Button size="sm" @click="showSlideSm = true">Open default (w-96)</Button>
            <Button size="sm" variant="secondary" @click="showSlideLg = true">Open wide (w-[420px])</Button>
        </div>

        <SlidePanel :open="showSlideSm" title="Add Subject" @close="showSlideSm = false">
            <div class="form-field" style="margin-bottom:14px;">
                <label>Subject Name</label>
                <input class="form-input" placeholder="Mathematics">
            </div>
            <div class="form-field" style="margin-bottom:14px;">
                <label>Code</label>
                <input class="form-input" placeholder="MATH">
            </div>
            <Button block @click="showSlideSm = false; toast.success('Subject saved')">Save</Button>
        </SlidePanel>

        <SlidePanel :open="showSlideLg" title="Edit Period (wider panel)" width="w-[420px]" @close="showSlideLg = false">
            <div class="form-row form-row-2" style="margin-bottom:14px;">
                <div class="form-field"><label>Start</label><input class="form-input" type="time" value="09:00"></div>
                <div class="form-field"><label>End</label><input class="form-input" type="time" value="09:45"></div>
            </div>
            <div class="form-field" style="margin-bottom:14px;">
                <label>Notes</label>
                <textarea class="form-input" rows="3" placeholder="Optional"></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <Button variant="secondary" @click="showSlideLg = false">Cancel</Button>
                <Button @click="showSlideLg = false; toast.success('Period updated')">Save changes</Button>
            </div>
        </SlidePanel>

    </SchoolLayout>
</template>

<style scoped>
.section-heading {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin: 24px 0 10px;
}
.form-input {
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.85rem;
    width: 100%;
    color: var(--text-primary);
    transition: border-color 0.15s;
    font-family: inherit;
}
.form-input:focus { border-color: var(--accent); outline: none; }
</style>
