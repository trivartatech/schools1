<script setup>
/**
 * PrintButton — standardized print button.
 *
 * Two modes:
 *
 * 1. Print the CURRENT page (browser print dialog):
 *    <PrintButton />
 *    or with custom label:
 *    <PrintButton label="Print Page" />
 *
 * 2. Open a print-view page or PDF endpoint in a new tab:
 *    <PrintButton href="/school/path/to/print" />
 *    <PrintButton href="/school/path/to/receipt.pdf" label="Download PDF" />
 *
 * Always renders the printer icon next to the label so users can spot
 * print actions at a glance.
 *
 * @prop {string} href     — if set, opens this URL in a new tab; otherwise calls window.print()
 * @prop {string} label    — button text (default 'Print')
 * @prop {string} variant  — Button variant (default 'secondary')
 * @prop {string} size     — Button size (default 'sm')
 * @prop {boolean} disabled
 */
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    href: { type: String, default: '' },
    label: { type: String, default: 'Print' },
    variant: { type: String, default: 'secondary' },
    size: { type: String, default: 'sm' },
    disabled: { type: Boolean, default: false },
});

function onClick() {
    if (props.disabled) return;
    if (props.href) {
        window.open(props.href, '_blank', 'noopener');
    } else {
        window.print();
    }
}
</script>

<template>
    <Button :variant="variant" :size="size" :disabled="disabled" @click="onClick">
        <template #icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <rect x="6" y="14" width="12" height="8" />
            </svg>
        </template>
        {{ label }}
    </Button>
</template>
