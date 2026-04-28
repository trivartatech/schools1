import { usePage } from '@inertiajs/vue3';

/**
 * useFormat — date / time / datetime / money formatters that respect the
 * admin's System Config settings (date_format, time_format, currency).
 *
 * Settings come from the school object that HandleInertiaRequests shares
 * on every page (see app/Http/Middleware/HandleInertiaRequests.php).
 *
 * Usage:
 *   import { useFormat } from '@/Composables/useFormat';
 *   const { formatDate, formatTime, formatDateTime, formatMoney } = useFormat();
 *   ...
 *   {{ formatDate(student.dob) }}             → 27/03/2026
 *   {{ formatTime(payment.created_at) }}      → 9:30 AM
 *   {{ formatDateTime(notification.sent_at) }} → 27/03/2026, 9:30 AM
 *
 * Falls back gracefully:
 *   - empty / null / undefined input → '—'
 *   - already-formatted string (un-parseable Date) → returned as-is
 *   - missing / unknown setting → DD/MM/YYYY (parents-friendly default)
 */
const MONTHS_SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

const pad2 = (n) => String(n).padStart(2, '0');

function applyDateFormat(d, format) {
  const dd   = pad2(d.getDate());
  const mm   = pad2(d.getMonth() + 1);
  const yyyy = d.getFullYear();
  const mmm  = MONTHS_SHORT[d.getMonth()];
  const dRaw = String(d.getDate());

  // Order matters — replace longest tokens first so partial matches don't clash.
  return format
    .replace('YYYY', yyyy)
    .replace('MMM',  mmm)
    .replace('DD',   dd)
    .replace('MM',   mm)
    .replace(/(?<![A-Z])D(?![A-Z])/, dRaw); // bare D = day-of-month without leading zero
}

function applyTimeFormat(d, format) {
  const hours = d.getHours();
  const mins  = pad2(d.getMinutes());
  const secs  = pad2(d.getSeconds());
  const ampm  = hours >= 12 ? 'PM' : 'AM';
  const h12   = ((hours + 11) % 12) + 1;
  const h24   = pad2(hours);

  switch (format) {
    case 'h:mm A':    return `${h12}:${mins} ${ampm}`;
    case 'H:mm':      return `${h24}:${mins}`;
    case 'h:mm:ss A': return `${h12}:${mins}:${secs} ${ampm}`;
    default:          return `${h12}:${mins} ${ampm}`;
  }
}

export function useFormat() {
  const page = usePage();

  const dateFmt = () => page.props.school?.settings?.date_format || 'DD/MM/YYYY';
  const timeFmt = () => page.props.school?.settings?.time_format || 'h:mm A';
  const currency = () => page.props.school?.currency || '₹';

  const formatDate = (input) => {
    if (input === null || input === undefined || input === '') return '—';
    const d = new Date(input);
    if (Number.isNaN(d.getTime())) return String(input); // already formatted, pass through
    return applyDateFormat(d, dateFmt());
  };

  const formatTime = (input) => {
    if (input === null || input === undefined || input === '') return '—';
    // Time-only strings ("HH:MM" or "HH:MM:SS") need a date prefix to parse
    if (typeof input === 'string' && /^\d{1,2}:\d{2}(:\d{2})?$/.test(input)) {
      const d = new Date(`1970-01-01T${input}${input.length === 5 ? ':00' : ''}`);
      if (!Number.isNaN(d.getTime())) return applyTimeFormat(d, timeFmt());
      return String(input);
    }
    const d = new Date(input);
    if (Number.isNaN(d.getTime())) return String(input);
    return applyTimeFormat(d, timeFmt());
  };

  const formatDateTime = (input) => {
    if (input === null || input === undefined || input === '') return '—';
    const d = new Date(input);
    if (Number.isNaN(d.getTime())) return String(input);
    return `${applyDateFormat(d, dateFmt())}, ${applyTimeFormat(d, timeFmt())}`;
  };

  const formatMoney = (n, opts = {}) => {
    const value = Number(n || 0);
    const sym   = opts.symbol ?? currency();
    const fixed = opts.fixed ?? false; // 2-decimal version for receipts
    const num   = fixed
      ? value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      : value.toLocaleString('en-IN');
    return `${sym}${num}`;
  };

  return { formatDate, formatTime, formatDateTime, formatMoney, dateFmt, timeFmt };
}
