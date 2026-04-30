#!/usr/bin/env node
/**
 * audit_ui_sandbox.mjs — UI Sandbox compliance auditor.
 *
 * Scans the codebase to verify two invariants:
 *
 *   1. SANDBOX COVERAGE — every primitive shipped under
 *      `resources/js/Components/ui/` (and the cross-cutting shared components
 *      in `resources/js/Components/`) is imported and demoed in
 *      `resources/js/Pages/School/UISandbox.vue`. For primitives whose
 *      script-block exposes a `validator: (v) => [...].includes(v)` array
 *      on a `variant` / `size` / `tone` / `align` prop, the script also
 *      verifies every enum value appears verbatim in the sandbox template.
 *
 *   2. PAGE COMPLIANCE — every Vue page under `resources/js/Pages/**` avoids
 *      the legacy patterns the sandbox-driven primitives are meant to
 *      replace:
 *        - native `confirm(...)`  / `window.confirm(...)`  → use useConfirm()
 *        - native `alert(...)`    / `window.alert(...)`    → use useToast()
 *        - native `prompt(...)`                            → use a Modal form
 *        - `class="btn-*"`                                 → use <Button>
 *        - `<div class="modal-backdrop">`                  → use <Modal>
 *        - `<div class="page-header">`                     → use <PageHeader>
 *        - `<table class="erp-table">`                     → use <Table>
 *        - `<th @click="sort(...)">`-style hand-rolled sort  → use <SortableTh>
 *        - inline `style="overflow-x:auto"` filter rows    → use <FilterBar>
 *
 *   3. PER-PAGE HEURISTICS (advisory) — softer, structural checks for pages
 *      that LIKELY would benefit from a sandbox primitive but currently
 *      hand-roll the markup. These don't fail the build by default — pass
 *      `--strict` to elevate them. False positives are possible; review the
 *      per-page output to decide.
 *
 * Exit code:
 *   0  → no violations
 *   1  → violations found (or sandbox missing required demos, or --strict
 *         heuristic findings)
 *   2  → sandbox file or primitive source not found (script setup error)
 *
 * Usage:
 *   node scripts/audit_ui_sandbox.mjs              # full report
 *   node scripts/audit_ui_sandbox.mjs --json       # machine-readable
 *   node scripts/audit_ui_sandbox.mjs --quiet      # summary only
 *   node scripts/audit_ui_sandbox.mjs --no-pages   # skip page scan
 *   node scripts/audit_ui_sandbox.mjs --no-sandbox # skip sandbox-coverage scan
 *   node scripts/audit_ui_sandbox.mjs --no-heuristics  # skip per-page heuristics
 *   node scripts/audit_ui_sandbox.mjs --strict     # heuristic findings → exit 1
 *   node scripts/audit_ui_sandbox.mjs --by-module  # group heuristics by module dir
 *
 * The script reads only — never writes. Run it from CI or pre-commit.
 */
import { readdirSync, readFileSync, statSync, existsSync } from 'node:fs';
import { join, relative, basename, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');

const args = new Set(process.argv.slice(2));
const OUTPUT_JSON      = args.has('--json');
const QUIET            = args.has('--quiet');
const SKIP_PAGES       = args.has('--no-pages');
const SKIP_SANDBOX     = args.has('--no-sandbox');
const SKIP_HEURISTICS  = args.has('--no-heuristics');
const STRICT           = args.has('--strict');
const GROUP_BY_MODULE  = args.has('--by-module');

// The sandbox is now multi-page: a landing file plus subpages under
// `Pages/School/UISandbox/`. The audit treats them as one collective sandbox.
const SANDBOX_PATH    = join(ROOT, 'resources/js/Pages/School/UISandbox.vue');
const SANDBOX_DIR     = join(ROOT, 'resources/js/Pages/School/UISandbox');
const UI_DIR          = join(ROOT, 'resources/js/Components/ui');
const SHARED_DIR      = join(ROOT, 'resources/js/Components');
const PAGES_DIR       = join(ROOT, 'resources/js/Pages');

// Cross-cutting (top-level Components/*.vue) that count as primitives
const SHARED_PRIMITIVES = [
    'ExportDropdown.vue',
    'SlidePanel.vue',
    'LedgerCombobox.vue',
    'IdCardQR.vue',
    'GatePassCard.vue',
    'VisitorPassCard.vue',
    'WebcamCapture.vue',
    'PermissionGate.vue',
    'ErrorBoundary.vue',
    'AiChatbot.vue',
    'ChatWidget.vue',
];

// Legacy-pattern detectors. Each entry: { id, label, pattern, allowFiles?, kind }
const PAGE_RULES = [
    {
        id: 'native-confirm',
        label: 'Native confirm() — use useConfirm() instead',
        // confirm( as bare global call. Excludes our `await confirm({...})` pattern
        // by also checking that the file imports useConfirm. (See `pageHasUseConfirm`.)
        pattern: /\b(?:window\.|globalThis\.)?confirm\s*\(/g,
        kind: 'context-aware',
    },
    {
        id: 'native-alert',
        label: 'Native alert() — use useToast() instead',
        pattern: /\b(?:window\.|globalThis\.)?alert\s*\(/g,
        kind: 'simple',
    },
    {
        id: 'native-prompt',
        label: 'Native prompt() — use a Modal form instead',
        pattern: /\b(?:window\.|globalThis\.)?prompt\s*\(/g,
        kind: 'simple',
    },
    {
        id: 'legacy-btn-class',
        label: 'Legacy btn / btn-primary / btn-sm / etc. — use <Button variant="…">',
        // Strict: match the canonical legacy bootstrap-style class TOKEN ONLY.
        // Lookbehind/-ahead require the class to be a standalone token in the
        // class attribute (delimited by whitespace or quote), so that
        // intentional one-off classes like `btn-impersonate`, `pag-btn`,
        // `btn-export` are NOT flagged.
        pattern: /class\s*=\s*"[^"]*(?<=[\s"])(?:btn|btn-primary|btn-secondary|btn-danger|btn-success|btn-warning|btn-sm|btn-xs|btn-lg|btn-block)(?=[\s"])[^"]*"/g,
        kind: 'simple',
    },
    {
        id: 'legacy-modal-backdrop',
        label: 'Hand-rolled .modal-backdrop — use <Modal>',
        pattern: /<div\s+[^>]*class\s*=\s*"[^"]*(?<=[\s"])modal-backdrop(?=[\s"])[^"]*"/g,
        kind: 'simple',
    },
    {
        id: 'legacy-modal-overlay',
        label: 'Hand-rolled .modal-overlay — use <Modal>',
        pattern: /<div\s+[^>]*class\s*=\s*"[^"]*(?<=[\s"])modal-overlay(?=[\s"])[^"]*"/g,
        kind: 'simple',
    },
    {
        id: 'legacy-page-header',
        label: 'Legacy <div class="page-header"> — use <PageHeader>',
        // Strict: match the bare `page-header` class only, NOT compound names
        // like `gc-page-header`, `form-page-header`, `page-header-left`.
        pattern: /<div\s+[^>]*class\s*=\s*"[^"]*(?<=[\s"])page-header(?=[\s"])[^"]*"/g,
        kind: 'simple',
    },
    {
        id: 'legacy-erp-table',
        label: 'Inline <table class="erp-table"> — use <Table>',
        pattern: /<table\s+[^>]*class\s*=\s*"[^"]*(?<=[\s"])erp-table(?=[\s"])[^"]*"/g,
        kind: 'simple',
    },
];

// Primitives intentionally NOT live-mounted in any sandbox file.
// All other primitives are demoed across the sandbox subpages
// (Buttons / Forms / Tables / Modals / Components / Composables).
const SANDBOX_EXEMPT = new Set([
    // Layout-mounted via SchoolLayout (don't re-mount in pages — would stack
    // duplicate global instances or i18n providers).
    'AiChatbot.vue',
    'ChatWidget.vue',
    'LanguageSwitcher.vue',
    // Singletons mounted at app root, exercised via composables.
    'Toast.vue',
    'ConfirmDialog.vue',
    // Module-specific live primitives — depend on Pinia store / API endpoints
    // and are best previewed inside their owning module pages.
    'GatePassCard.vue',
    'VisitorPassCard.vue',
    'WebcamCapture.vue',
    // ErrorBoundary is documented in the layout-mounted note (Components page)
    // but not live-mounted because its earlier live demo (a defineComponent
    // throwing from a render fn) triggered a Vue 3.5.29 + Vite 7 prod-build
    // render-effect recursion. The primitive itself is fine in real pages.
    'ErrorBoundary.vue',
]);

const PAGE_EXEMPT = new Set([
    // Sandbox files MUST contain demos of legacy patterns when illustrating tokens.
    'School/UISandbox.vue',
    'School/UISandbox/Buttons.vue',
    'School/UISandbox/Forms.vue',
    'School/UISandbox/Tables.vue',
    'School/UISandbox/Modals.vue',
    'School/UISandbox/Components.vue',
    'School/UISandbox/Composables.vue',
    // Specialized real-time UI explicitly excluded from the migration (see commit c3ef067).
    'School/Chat/Index.vue',
    // Tailwind-utility modal pattern, documented exclusion.
    'Admin/Roles/Matrix.vue',
    // Standalone pass cards — print-only HTML pages, not Vue-managed UI.
    'School/Certificates/Print.vue',
    'School/IdCards/Print.vue',
]);

// Pages that legitimately have no sandbox primitives (auth screens, public
// landing/verify, print-only views, dashboards that hand-roll layouts).
// These are skipped from the heuristic pass to avoid noise.
const HEURISTIC_EXEMPT_RE = [
    /^Auth\//,
    /^Public\//,
    /\/Print\.vue$/,
    /^School\/UISandbox\.vue$/,
    /^School\/UISandbox\//,
    /^School\/Chat\/Index\.vue$/,
    /^Admin\/Roles\/Matrix\.vue$/,
    // Pure print/PDF views
    /\/Print\//,
    /\/Receipt\b/,
];

// Per-rule exemption: skip the multi-select-no-filterbar check on pages whose
// purpose is a CRUD form, designer, generator or wizard. Their multiple
// `<select>` elements are PICKERS, not list-page filters — so wrapping them in
// a horizontal FilterBar would harm UX. The audit still scans these pages for
// every other rule.
const RULE_EXEMPTIONS = {
    'multi-select-no-filterbar': [
        /\/Create\.vue$/,     // CRUD create forms
        /\/Edit\.vue$/,       // CRUD edit forms
        /\/Generate\.vue$/,   // Generator wizards (Certificates, IdCards)
        /\/Designer\.vue$/,   // Visual designers (Certificates, IdCards)
        /\/Wizard\.vue$/,     // Multi-step wizards
        /\/Show\.vue$/,       // Detail/show pages
        /\/GlConfig\.vue$/,   // Finance GL config form
        /\/Calendar\.vue$/,   // Sidebar-filter calendar UI
        /\/RolloverManualPromote\.vue$/,  // Rollover wizard step
    ],
    'bare-table': [
        // Public verify pages — minimal-HTML public-facing receipts.
        /\/VerifyPublic\.vue$/,
        // True grid layouts (rows × cols of distinct meaning) — Table primitive
        // doesn't fit. Calendar / Timetable use <table> for layout, not data.
        /\/Schedule\/Timetable\.vue$/,
        /\/Calendar\.vue$/,
    ],
};

// ─── Per-page heuristic rules ───────────────────────────────────
//
// Each rule receives the raw source, the extracted template block, and the
// extracted script block (so it can check imports). Returns true if the page
// LIKELY hand-rolls something a sandbox primitive provides.
//
// Conservative: each check has a guard that limits false positives.
const HEURISTIC_RULES = [
    {
        id: 'missing-page-header',
        label: 'Has visible <h1> at the top of the template but does not import <PageHeader>',
        suggest: 'Wrap the title in `<PageHeader title="…" subtitle="…" />` (Sandbox §2)',
        check: (src, tpl, script) => {
            if (!/from\s+['"]@\/Layouts\//.test(script)) return false;
            if (/from\s+['"]@\/Components\/ui\/PageHeader\.vue['"]/.test(script)) return false;
            // Only flag h1 (not h2/h3 — those are typically section/card headings).
            // The h1 must appear in the first 1200 chars of the template (top of page).
            const headPart = tpl.slice(0, 1200);
            return /<h1\b/.test(headPart);
        },
    },
    {
        id: 'bare-table',
        label: 'Has <table> markup but does not import <Table> primitive',
        suggest: 'Replace `<table>` with `<Table>` (Sandbox §7)',
        check: (src, tpl, script) => {
            if (!/<table\b/.test(tpl)) return false;
            if (/from\s+['"]@\/Components\/ui\/Table\.vue['"]/.test(script)) return false;
            return true;
        },
    },
    {
        id: 'multi-select-no-filterbar',
        label: 'Has 2+ filter <select> elements (NOT form fields) without importing <FilterBar>',
        suggest: 'Wrap the filters in `<FilterBar>` so they share the canonical row layout (Sandbox §6)',
        check: (src, tpl, script) => {
            const selects = (tpl.match(/<select\b/g) || []).length;
            if (selects < 2) return false;
            if (/from\s+['"]@\/Components\/ui\/FilterBar\.vue['"]/.test(script)) return false;
            // Skip if there's a <form> in the template — those are CRUD form fields.
            if (/<form\b/.test(tpl)) return false;
            // Skip if EVERY select v-model is bound to a CRUD form object
            // (form.*, newItem.*, editItem.*) — those are form fields, not filters.
            const vModels = [...tpl.matchAll(/<select\b[^>]*v-model\s*=\s*"([^"]+)"/g)].map(m => m[1]);
            if (vModels.length && vModels.every(v =>
                /^(form|newItem|editItem|editing|creating|create|edit|item|record|payload|data)\./.test(v)
            )) return false;
            return true;
        },
    },
    {
        id: 'sortable-th-handrolled',
        label: 'Has <th @click="sort"> hand-rolled sort header without importing <SortableTh>',
        suggest: 'Use `<SortableTh sort-key="…">…</SortableTh>` for consistent arrows (Sandbox §8)',
        check: (src, tpl, script) => {
            if (/from\s+['"]@\/Components\/ui\/SortableTh\.vue['"]/.test(script)) return false;
            return /<th\b[^>]*@click\s*=\s*"[^"]*sort/i.test(tpl);
        },
    },
    {
        id: 'print-window-print',
        label: 'Has @click="window.print()" handler without importing <PrintButton>',
        suggest: 'Use `<PrintButton />` so the icon + label match the rest of the app (Sandbox §11)',
        check: (src, tpl, script) => {
            if (/from\s+['"]@\/Components\/ui\/PrintButton\.vue['"]/.test(script)) return false;
            // Only flag click handlers — not window.print() inside HTML/template strings
            // generated for new-tab print views (legitimate pattern).
            return /@click\s*=\s*"[^"]*window\.print\(\)/.test(tpl);
        },
    },
    {
        id: 'export-handrolled',
        label: 'Has a multi-format "Export" button without importing <ExportDropdown>',
        suggest: 'Use `<ExportDropdown base-url="…" :params="…" />` for the multi-format menu (Sandbox §11)',
        check: (src, tpl, script) => {
            if (/from\s+['"]@\/Components\/ExportDropdown\.vue['"]/.test(script)) return false;
            // Strict: only flag "Export" (or "Export as …", "Export to …") buttons.
            // Skip generic "Download X" labels — those are single-asset downloads
            // (e.g., "Download QR", "Download payslip", "Download PDF receipt").
            return /<(?:button|a|Button|Link)\b[^>]*>\s*(?:[\u{1F4E5}↓⬇📥]+\s*)?Export(?:\s+(?:as|to|all)\s|\s*<|\s*$|\s*\{)/iu.test(tpl);
        },
    },
    {
        id: 'legacy-page-header-subclass',
        label: 'Uses legacy `.page-header-left|right|row` sub-classes',
        suggest: 'These belong to the old hand-rolled header. Migrate to `<PageHeader>` slots (Sandbox §2)',
        check: (src, tpl) =>
            /class\s*=\s*"[^"]*\bpage-header-(?:left|right|row)\b/.test(tpl),
    },
    {
        id: 'native-date-input-cluster',
        label: 'Has 2+ <input type="date"> filters (NOT form fields) without importing <DateRangeFilter>',
        suggest: 'Use `<DateRangeFilter @change="…" />` for presets + range emit (Sandbox §15)',
        check: (src, tpl, script) => {
            const dates = (tpl.match(/<input\b[^>]*type\s*=\s*["']date["']/gi) || []).length;
            if (dates < 2) return false;
            if (/from\s+['"]@\/Components\/ui\/DateRangeFilter\.vue['"]/.test(script)) return false;
            // Skip if there's a <form> — date inputs in a form are field inputs, not filters.
            if (/<form\b/.test(tpl)) return false;
            // Skip if every date input is bound to a form-like ref (form.*, exportForm.*, etc.)
            // — those are export/modal form fields, not page-level filters.
            const vModels = [...tpl.matchAll(/<input\b[^>]*type\s*=\s*["']date["'][^>]*v-model\s*=\s*"([^"]+)"/g)].map(m => m[1]);
            if (vModels.length && vModels.every(v =>
                /^(form|newItem|editItem|item|record|payload|data|exportForm|importForm)\./.test(v)
            )) return false;
            // Skip if every date input has the `required` attribute (form-field hint).
            const allRequired = [...tpl.matchAll(/<input\b[^>]*type\s*=\s*["']date["'][^>]*>/g)]
                .every(m => /\brequired\b/.test(m[0]));
            if (allRequired && vModels.length) return false;
            return true;
        },
    },
    {
        id: 'inline-confirm-message-box',
        label: 'Has hand-rolled "Are you sure?" confirmation div without using useConfirm()',
        suggest: 'Replace inline confirm UI with `useConfirm({ … danger: true })` (Sandbox §13)',
        check: (src, tpl, script) => {
            if (/useConfirm/.test(script)) return false;
            return /Are you sure\?/i.test(tpl);
        },
    },
];

// ─── Utilities ──────────────────────────────────────────────────

function walk(dir, out = []) {
    if (!existsSync(dir)) return out;
    for (const name of readdirSync(dir)) {
        const p = join(dir, name);
        const st = statSync(p);
        if (st.isDirectory()) walk(p, out);
        else if (st.isFile() && p.endsWith('.vue')) out.push(p);
    }
    return out;
}

function readFileSafe(path) {
    try {
        return readFileSync(path, 'utf8');
    } catch {
        return null;
    }
}

function relPath(p) {
    return relative(ROOT, p).replace(/\\/g, '/');
}

// Strip <style> blocks before scanning for legacy patterns —
// CSS rules naturally name `.modal-backdrop` etc. without violating anything.
function stripStyle(src) {
    return src.replace(/<style[^>]*>[\s\S]*?<\/style>/g, '');
}

// Strip JS/HTML comments so detectors don't false-positive on docs.
function stripComments(src) {
    return src
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/^\s*\/\/.*$/gm, '');
}

// Extract the <script setup> block (or first <script>) for AST-lite analysis.
function extractScript(src) {
    const m = src.match(/<script\b[^>]*>([\s\S]*?)<\/script>/);
    return m ? m[1] : '';
}

// Vue files contain nested <template #slot> tags. We want the OUTER template,
// so use first opening and LAST closing tag rather than non-greedy match.
function extractTemplate(src) {
    const open = src.match(/<template\b[^>]*>/);
    if (!open) return '';
    const startIdx = open.index + open[0].length;
    const lastClose = src.lastIndexOf('</template>');
    if (lastClose === -1 || lastClose < startIdx) return '';
    return src.slice(startIdx, lastClose);
}

// Detect whether a file imports `useConfirm` — its `confirm(...)` calls are
// then NOT native, just a callback returned by the composable.
function pageHasUseConfirm(src) {
    return /from\s+['"]@\/Composables\/useConfirm['"]/.test(src)
        || /useConfirm\s*\(/.test(src);
}

// ─── Primitive inventory ────────────────────────────────────────

/**
 * For a primitive .vue file, parse out:
 *   - name           (e.g. "Button")
 *   - propEnums      Map<propName, [validValues...]> from validator: (v) => [..].includes(v)
 *   - slots          string[] of slot names declared in the template (or in JSDoc)
 *   - hasVModel      boolean — whether modelValue/update:modelValue or v-model:open exists
 */
function inspectPrimitive(filePath) {
    const src = readFileSafe(filePath);
    if (src === null) return null;
    const script = extractScript(src);
    const template = extractTemplate(src);

    const name = basename(filePath, '.vue');

    // Find all `validator: (v) => [...].includes(v)` arrays.
    // Captures: [propName context loose] → array contents
    const propEnums = new Map();
    const propBlockRe = /([a-zA-Z_]\w*)\s*:\s*\{[^}]*?validator\s*:\s*\(\s*\w+\s*\)\s*=>\s*\[([^\]]+)\]\.includes\(\s*\w+\s*\)/g;
    let m;
    while ((m = propBlockRe.exec(script))) {
        const propName = m[1];
        const values = m[2]
            .split(',')
            .map(s => s.trim().replace(/^['"`]+|['"`]+$/g, '').trim())
            .filter(Boolean);
        propEnums.set(propName, values);
    }

    // Slot names from <slot name="…"> in template.
    const slots = new Set();
    const slotRe = /<slot\s+[^>]*name\s*=\s*["']([^"']+)["']/g;
    while ((m = slotRe.exec(template))) slots.add(m[1]);
    // Implicit default slot
    if (/<slot\s*\/>|<slot\s*>/.test(template)) slots.add('default');

    const hasVModel =
        /defineEmits\([^)]*['"]update:open['"]/.test(script)
        || /defineEmits\([^)]*['"]update:modelValue['"]/.test(script)
        || /defineEmits\([^)]*['"]update:sortKey['"]/.test(script);

    return { name, file: filePath, propEnums, slots, hasVModel };
}

function loadPrimitives() {
    const ui = walk(UI_DIR);
    const shared = SHARED_PRIMITIVES
        .map(n => join(SHARED_DIR, n))
        .filter(p => existsSync(p));
    return [...ui, ...shared].map(inspectPrimitive).filter(Boolean);
}

// ─── Sandbox coverage scan ──────────────────────────────────────

function scanSandbox(primitives) {
    // Collect every sandbox file: the landing UISandbox.vue plus every
    // subpage in Pages/School/UISandbox/. Merge their script and template
    // sections so coverage is evaluated across the whole multi-page sandbox.
    const sandboxFiles = [];
    if (existsSync(SANDBOX_PATH)) sandboxFiles.push(SANDBOX_PATH);
    if (existsSync(SANDBOX_DIR)) {
        for (const name of readdirSync(SANDBOX_DIR)) {
            const p = join(SANDBOX_DIR, name);
            if (statSync(p).isFile() && p.endsWith('.vue')) sandboxFiles.push(p);
        }
    }
    if (sandboxFiles.length === 0) {
        return { ok: false, error: `No sandbox files found at ${SANDBOX_PATH} or ${SANDBOX_DIR}` };
    }

    let script = '';
    let template = '';
    for (const f of sandboxFiles) {
        const s = readFileSafe(f);
        if (s === null) continue;
        script   += '\n' + extractScript(s);
        template += '\n' + extractTemplate(s);
    }

    const importedPrimitives = new Set();
    const importRe = /import\s+(\w+)\s+from\s+['"]@\/Components\/(?:ui\/)?(\w+)\.vue['"]/g;
    let m;
    while ((m = importRe.exec(script))) {
        importedPrimitives.add(m[2]); // file basename without .vue
    }

    const rows = primitives.map(p => {
        const exempt = SANDBOX_EXEMPT.has(basename(p.file));
        const imported = importedPrimitives.has(p.name);
        const usedInTemplate = new RegExp(`<${p.name}\\b`).test(template);

        // For each enum prop, check every value appears in the template.
        // Vue accepts both camelCase and kebab-case for prop names in
        // templates, and values can be passed as `prop="value"`,
        // `:prop="'value'"`, or even buried in :stats arrays. We accept the
        // first two forms verbatim and rely on a per-component value scan
        // for the third (stats arrays, etc.).
        const missingEnumValues = {};
        for (const [propName, values] of p.propEnums.entries()) {
            const kebab = propName.replace(/([A-Z])/g, '-$1').toLowerCase();
            const propAlts = propName === kebab ? propName : `(?:${propName}|${kebab})`;
            const missing = values.filter(v => {
                if (v === undefined || v === null || v === '') return false;
                const propValueRe = new RegExp(
                    `<${p.name}\\b[^>]*${propAlts}\\s*=\\s*["']?:?["']?${escapeReg(v)}\\b`
                );
                if (propValueRe.test(template)) return false;
                // Fallback: array literals (e.g. StatsRow's :stats=[{ color: '…' }])
                // — accept if `color: 'value'` (or `cols: 4`) appears under a
                // `<Name … :…="[…]">` block. Coarse but adequate.
                const arrayValueRe = new RegExp(
                    `<${p.name}\\b[^>]*:?[a-zA-Z\\-_]+="[^"]*\\b${escapeReg(v)}\\b`
                );
                if (arrayValueRe.test(template)) return false;
                return true;
            });
            if (missing.length) missingEnumValues[propName] = missing;
        }

        // For every named slot, check the sandbox uses it as `#slot-name` or `v-slot:slot-name`.
        const missingSlots = [...p.slots]
            .filter(s => s !== 'default')
            .filter(s => {
                // Skip dynamic patterns like `icon-{label-slug}` or `tab-{key}`
                if (s.includes('{')) return false;
                const slotRe = new RegExp(`(?:#${escapeReg(s)}|v-slot:${escapeReg(s)})`);
                return !slotRe.test(template);
            });

        const status = exempt
            ? 'EXEMPT'
            : !imported
                ? 'MISSING'
                : !usedInTemplate
                    ? 'NOT-RENDERED'
                    : Object.keys(missingEnumValues).length || missingSlots.length
                        ? 'PARTIAL'
                        : 'OK';

        return {
            primitive: p.name,
            file: relPath(p.file),
            status,
            imported,
            usedInTemplate,
            missingEnumValues,
            missingSlots,
        };
    });

    return { ok: true, rows };
}

function escapeReg(s) {
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// ─── Page compliance scan ───────────────────────────────────────

function scanPages() {
    const files = walk(PAGES_DIR);
    const violations = []; // { file, ruleId, count, samples: [{line, snippet}] }

    for (const file of files) {
        const rel = relPath(file).replace(/^resources\/js\/Pages\//, '');
        if (PAGE_EXEMPT.has(rel)) continue;

        const raw = readFileSafe(file);
        if (raw === null) continue;
        const cleaned = stripComments(stripStyle(raw));
        const lines = raw.split('\n');

        for (const rule of PAGE_RULES) {
            if (rule.kind === 'context-aware' && rule.id === 'native-confirm') {
                if (pageHasUseConfirm(raw)) continue; // confirm() in this file is the composable
            }

            const samples = [];
            const re = new RegExp(rule.pattern.source, rule.pattern.flags);
            let m;
            while ((m = re.exec(cleaned))) {
                // Map the cleaned-text offset back to a rough line number using
                // the first match in the raw file (good enough for reporting).
                const idx = raw.indexOf(m[0]);
                const lineNo = idx === -1 ? '?' : raw.slice(0, idx).split('\n').length;
                const snippet = (lines[lineNo - 1] ?? '').trim().slice(0, 160);
                samples.push({ line: lineNo, snippet });
                if (samples.length >= 3) break;
            }
            if (samples.length) {
                violations.push({
                    file: relPath(file),
                    ruleId: rule.id,
                    label: rule.label,
                    count: samples.length,
                    samples,
                });
            }
        }
    }

    return { totalFiles: files.length, violations };
}

// ─── Per-page heuristic scan ────────────────────────────────────

function scanHeuristics() {
    const files = walk(PAGES_DIR);
    const findings = []; // { file, ruleId, label, suggest }

    for (const file of files) {
        const rel = relPath(file).replace(/^resources\/js\/Pages\//, '');
        if (PAGE_EXEMPT.has(rel)) continue;
        if (HEURISTIC_EXEMPT_RE.some(re => re.test(rel))) continue;

        const raw = readFileSafe(file);
        if (raw === null) continue;
        const tpl = extractTemplate(raw);
        const script = extractScript(raw);

        for (const rule of HEURISTIC_RULES) {
            // Per-rule path exemptions (e.g. skip the FilterBar check on Create/Edit/Generate pages).
            const ruleExempts = RULE_EXEMPTIONS[rule.id];
            if (ruleExempts && ruleExempts.some(re => re.test(rel))) continue;
            try {
                if (rule.check(raw, tpl, script)) {
                    findings.push({
                        file: relPath(file),
                        ruleId: rule.id,
                        label: rule.label,
                        suggest: rule.suggest,
                    });
                }
            } catch (e) {
                // Skip silently — heuristics shouldn't crash the audit.
            }
        }
    }

    return { totalFiles: files.length, findings };
}

// ─── Reporting ──────────────────────────────────────────────────

const ANSI = {
    reset: '\x1b[0m', dim: '\x1b[2m', bold: '\x1b[1m',
    red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', cyan: '\x1b[36m',
};
const useColor = process.stdout.isTTY && !OUTPUT_JSON;
const c = (clr, txt) => useColor ? `${ANSI[clr]}${txt}${ANSI.reset}` : txt;

function statusIcon(status) {
    return {
        OK:           c('green',  '✔'),
        PARTIAL:      c('yellow', '◑'),
        MISSING:      c('red',    '✘'),
        'NOT-RENDERED': c('red',  '✘'),
        EXEMPT:       c('dim',    '·'),
    }[status] || '?';
}

function printSandboxReport(report) {
    if (!report.ok) {
        console.error(c('red', `Sandbox scan failed: ${report.error}`));
        return;
    }
    const { rows } = report;

    if (!QUIET) {
        console.log(c('bold', '\n━━━ 1. Sandbox primitive coverage ━━━\n'));
        const widthName = Math.max(...rows.map(r => r.primitive.length), 18);
        for (const r of rows) {
            const head = `${statusIcon(r.status)} ${c('cyan', r.primitive.padEnd(widthName))}  ${r.status.padEnd(13)} ${c('dim', r.file)}`;
            console.log(head);
            for (const [prop, missing] of Object.entries(r.missingEnumValues)) {
                console.log(`    ${c('yellow', '↳')} missing ${prop} values: ${c('yellow', missing.join(', '))}`);
            }
            if (r.missingSlots.length) {
                console.log(`    ${c('yellow', '↳')} missing slot demos: ${c('yellow', r.missingSlots.map(s => `#${s}`).join(', '))}`);
            }
            if (r.status === 'MISSING') {
                console.log(`    ${c('red', '↳')} not imported in UISandbox.vue`);
            }
            if (r.status === 'NOT-RENDERED') {
                console.log(`    ${c('red', '↳')} imported but never rendered as <${r.primitive}>`);
            }
        }
    }

    const counts = rows.reduce((acc, r) => { acc[r.status] = (acc[r.status] || 0) + 1; return acc; }, {});
    console.log(`\nSandbox: ${c('green', counts.OK || 0)} OK · ${c('yellow', counts.PARTIAL || 0)} partial · ${c('red', (counts.MISSING || 0) + (counts['NOT-RENDERED'] || 0))} missing · ${c('dim', (counts.EXEMPT || 0) + ' exempt')} (of ${rows.length} primitives)`);
}

function printPageReport(report) {
    if (!QUIET) {
        console.log(c('bold', '\n━━━ 2. Page compliance (strict) ━━━\n'));
        if (!report.violations.length) {
            console.log(c('green', '✔ Zero legacy patterns across all pages.'));
        } else {
            // Group violations by ruleId
            const byRule = {};
            for (const v of report.violations) {
                (byRule[v.ruleId] ||= []).push(v);
            }
            for (const [ruleId, list] of Object.entries(byRule)) {
                console.log(`${c('red', '✘')} ${c('bold', list[0].label)} — ${c('red', list.length)} file(s)`);
                for (const v of list.slice(0, 12)) {
                    const sample = v.samples[0];
                    console.log(`    ${c('dim', v.file)}${sample ? c('dim', `:${sample.line}`) : ''}  ${c('dim', sample?.snippet ?? '')}`);
                }
                if (list.length > 12) console.log(`    ${c('dim', `… +${list.length - 12} more`)}`);
            }
        }
    }
    console.log(`\nPages scanned: ${report.totalFiles}  ·  Total strict violations: ${c(report.violations.length ? 'red' : 'green', report.violations.length)}`);
}

function printHeuristicReport(report) {
    const sym = STRICT ? c('red', '✘') : c('yellow', '◑');
    const strictTag = STRICT ? ' (strict mode — counts toward exit)' : ' (advisory)';

    if (!QUIET) {
        console.log(c('bold', `\n━━━ 3. Per-page heuristics${strictTag} ━━━\n`));
        if (!report.findings.length) {
            console.log(c('green', '✔ No heuristic findings — every scanned page already uses sandbox primitives where expected.'));
        } else if (GROUP_BY_MODULE) {
            // Group by top-level module dir under Pages/
            const byModule = {};
            for (const f of report.findings) {
                const m = f.file.match(/^resources\/js\/Pages\/([^/]+(?:\/[^/]+)?)/);
                const key = m ? m[1] : '(other)';
                (byModule[key] ||= []).push(f);
            }
            const sortedModules = Object.entries(byModule).sort((a, b) => b[1].length - a[1].length);
            for (const [mod, list] of sortedModules) {
                console.log(`${c('cyan', mod.padEnd(40))} ${sym} ${c(STRICT ? 'red' : 'yellow', list.length)} finding(s)`);
                const byFile = {};
                for (const f of list) (byFile[f.file] ||= []).push(f.ruleId);
                for (const [file, rules] of Object.entries(byFile).slice(0, 8)) {
                    console.log(`    ${c('dim', file.replace(/^resources\/js\/Pages\//, ''))}  ${c('dim', rules.join(', '))}`);
                }
                if (Object.keys(byFile).length > 8) console.log(`    ${c('dim', `… +${Object.keys(byFile).length - 8} more files`)}`);
            }
        } else {
            // Default: group by ruleId
            const byRule = {};
            for (const f of report.findings) (byRule[f.ruleId] ||= []).push(f);
            const sortedRules = Object.entries(byRule).sort((a, b) => b[1].length - a[1].length);
            for (const [ruleId, list] of sortedRules) {
                const head = list[0];
                console.log(`${sym} ${c('bold', head.label)} — ${c(STRICT ? 'red' : 'yellow', list.length)} file(s)`);
                console.log(`    ${c('dim', '↳ ' + head.suggest)}`);
                for (const f of list.slice(0, 10)) {
                    console.log(`    ${c('dim', f.file.replace(/^resources\/js\/Pages\//, ''))}`);
                }
                if (list.length > 10) console.log(`    ${c('dim', `… +${list.length - 10} more`)}`);
                console.log('');
            }
        }
    }
    const filesWithFindings = new Set(report.findings.map(f => f.file)).size;
    console.log(`Pages scanned: ${report.totalFiles}  ·  Files with heuristic findings: ${c(filesWithFindings ? (STRICT ? 'red' : 'yellow') : 'green', filesWithFindings)} (${report.findings.length} total)`);
}

// ─── Main ───────────────────────────────────────────────────────

const primitives      = loadPrimitives();
const sandboxReport   = SKIP_SANDBOX    ? null : scanSandbox(primitives);
const pageReport      = SKIP_PAGES      ? null : scanPages();
const heuristicReport = SKIP_HEURISTICS ? null : scanHeuristics();

if (OUTPUT_JSON) {
    process.stdout.write(JSON.stringify({
        primitives: primitives.length,
        sandbox:    sandboxReport,
        pages:      pageReport,
        heuristics: heuristicReport,
    }, null, 2) + '\n');
} else {
    if (sandboxReport)   printSandboxReport(sandboxReport);
    if (pageReport)      printPageReport(pageReport);
    if (heuristicReport) printHeuristicReport(heuristicReport);
    console.log('');
}

let exitCode = 0;
if (sandboxReport?.ok) {
    // Hard-fail only on MISSING / NOT-RENDERED — PARTIAL means the primitive
    // IS demoed but not every prop enum value is covered, which is advisory.
    const sandboxViolations = sandboxReport.rows.filter(r =>
        r.status === 'MISSING' || r.status === 'NOT-RENDERED'
    );
    if (sandboxViolations.length) exitCode = 1;
} else if (sandboxReport && !sandboxReport.ok) {
    exitCode = 2;
}
if (pageReport && pageReport.violations.length) exitCode = 1;
if (STRICT && heuristicReport && heuristicReport.findings.length) exitCode = 1;

process.exit(exitCode);
