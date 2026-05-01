import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * School store — current school and academic year context.
 *
 * Data sourced from Inertia shared props (injected by HandleInertiaRequests).
 *
 * Usage:
 *   const school = useSchoolStore();
 *   school.current       // current school object
 *   school.academicYear  // current academic year
 *   school.schoolId      // current school ID (number)
 *   school.fmtDate(d)    // format a YYYY-MM-DD string per school's date format
 *   school.fmtTime(t)    // format a HH:MM:SS string per school's time format
 *   school.fmtDateTime(dt) // format a datetime string per school's formats
 *   school.today()       // current date as YYYY-MM-DD (local clock, no UTC shift)
 *   school.currentMonth()// current month as YYYY-MM
 */
export const useSchoolStore = defineStore('school', () => {
    const page = usePage();

    const current      = computed(() => page.props.school ?? null);
    const academicYear = computed(() => page.props.academicYear ?? null);
    const schoolId     = computed(() => current.value?.id ?? null);
    const schoolName   = computed(() => current.value?.name ?? '');
    const academicYearId = computed(() => academicYear.value?.id ?? null);
    const academicYearName = computed(() => academicYear.value?.name ?? '');

    const settings     = computed(() => current.value?.settings ?? {});

    /** Currency symbol from school settings, default INR */
    const currency     = computed(() => settings.value?.currency_symbol ?? '₹');

    /** Whether a GL/ledger module is active for this school */
    const glEnabled    = computed(() => !!settings.value?.gl_enabled);

    /**
     * Edition-aware feature check. Mirrors School::isFeatureEnabled() on the
     * backend: a missing/empty `features` object means "all on" (backwards
     * compat with installs that predate the edition system); a missing key
     * defaults to on; an explicit `false` disables.
     */
    const features = computed(() => current.value?.features ?? {});
    function hasFeature(name) {
        const f = features.value;
        if (!f || typeof f !== 'object' || Object.keys(f).length === 0) return true;
        return f[name] !== false;
    }

    // ── Localization settings ─────────────────────────────────────────────
    const dateFormat = computed(() => settings.value?.date_format ?? 'DD/MM/YYYY');
    const timeFormat = computed(() => settings.value?.time_format ?? 'h:mm A');
    const timezone   = computed(() => current.value?.timezone     ?? 'Asia/Kolkata');

    const _MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    /**
     * Format a date-only value (YYYY-MM-DD or ISO datetime) using the school's
     * configured date format. Parses by splitting — avoids UTC day-shift that
     * occurs when passing a date-only string to `new Date()`.
     */
    function fmtDate(value) {
        if (!value) return '';
        const str   = String(value).substring(0, 10);
        const parts = str.split('-');
        if (parts.length !== 3) return String(value);
        const [y, m, d] = parts;
        const mon = _MONTHS[parseInt(m, 10) - 1] ?? m;
        switch (dateFormat.value) {
            case 'DD/MM/YYYY':  return `${d}/${m}/${y}`;
            case 'MM/DD/YYYY':  return `${m}/${d}/${y}`;
            case 'YYYY-MM-DD':  return `${y}-${m}-${d}`;
            case 'D MMM, YYYY': return `${parseInt(d, 10)} ${mon}, ${y}`;
            default:            return `${d}/${m}/${y}`;
        }
    }

    function _fmtTimeParts(h, m, s) {
        switch (timeFormat.value) {
            case 'h:mm A': {
                const ap = h >= 12 ? 'PM' : 'AM';
                return `${h % 12 || 12}:${m} ${ap}`;
            }
            case 'H:mm':
                return `${String(h).padStart(2, '0')}:${m}`;
            case 'h:mm:ss A': {
                const ap = h >= 12 ? 'PM' : 'AM';
                return `${h % 12 || 12}:${m}:${s} ${ap}`;
            }
            default:
                return `${String(h).padStart(2, '0')}:${m}`;
        }
    }

    /**
     * Format a time value using the school's configured time format.
     * Accepts:
     *  - Time-only strings:    "HH:MM" or "HH:MM:SS"
     *  - ISO datetime strings: "2026-04-16T09:30:00Z" — time extracted in school timezone
     *  - JS Date objects
     */
    function fmtTime(value) {
        if (!value) return '';
        const str = String(value);

        // Full datetime string → extract time in school's configured timezone
        if (str.length > 10 && (str.includes('T') || str.includes('Z') || str.includes(' '))) {
            try {
                const d = new Date(str);
                if (!isNaN(d.getTime())) {
                    const tz   = timezone.value;
                    const fmt  = new Intl.DateTimeFormat('en', {
                        timeZone: tz, hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: false,
                    });
                    const parts = fmt.formatToParts(d);
                    const get   = (type) => parts.find(p => p.type === type)?.value ?? '0';
                    const h = parseInt(get('hour'), 10);
                    const m = String(get('minute')).padStart(2, '0');
                    const s = String(get('second')).padStart(2, '0');
                    return _fmtTimeParts(h, m, s);
                }
            } catch { /* fall through */ }
        }

        // Time-only string (HH:MM or HH:MM:SS)
        const segments = str.substring(0, 8).split(':');
        const h  = parseInt(segments[0] ?? '0', 10);
        const m  = (segments[1] ?? '00').padStart(2, '0');
        const s  = (segments[2] ?? '00').substring(0, 2).padStart(2, '0');
        return _fmtTimeParts(h, m, s);
    }

    /**
     * Format a datetime string using both school date and time formats.
     */
    function fmtDateTime(value) {
        if (!value) return '';
        const str      = String(value);
        const datePart = str.substring(0, 10);
        const hasTime  = str.length > 10 && (str[10] === 'T' || str[10] === ' ');
        return hasTime ? `${fmtDate(datePart)}, ${fmtTime(str.substring(11))}` : fmtDate(datePart);
    }

    /**
     * Format a money value with the school's currency symbol.
     * Default: thousand-separated, no decimals (₹2,500). Pass { fixed: true }
     * for 2-decimal output (₹2,500.00) — used in receipts/payslips.
     */
    function fmtMoney(value, opts = {}) {
        const sym = opts.symbol ?? currency.value;
        const num = Number(value || 0);
        const out = opts.fixed
            ? num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : num.toLocaleString('en-IN', { maximumFractionDigits: 0 });
        return `${sym}${out}`;
    }

    /**
     * Today's date as YYYY-MM-DD using local clock — avoids the UTC midnight
     * shift that occurs with `new Date().toISOString().slice(0, 10)`.
     */
    function today() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }

    /**
     * Current month as YYYY-MM using local clock.
     */
    function currentMonth() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }

    return {
        current,
        academicYear,
        schoolId,
        schoolName,
        academicYearId,
        academicYearName,
        settings,
        currency,
        glEnabled,
        features,
        hasFeature,
        dateFormat,
        timeFormat,
        timezone,
        fmtDate,
        fmtTime,
        fmtDateTime,
        fmtMoney,
        today,
        currentMonth,
    };
});
