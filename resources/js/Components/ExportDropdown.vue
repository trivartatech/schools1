<script setup>
import Button from '@/Components/ui/Button.vue';
import { ref } from 'vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    params: { type: Object, default: () => ({}) },
    label: { type: String, default: 'Export' },
    formats: { type: Array, default: () => ['excel', 'pdf', 'csv'] },
});

const open = ref(false);
const exporting = ref(false);

function toggle() { open.value = !open.value; }
function close() { open.value = false; }

function doExport(format) {
    exporting.value = true;
    open.value = false;

    const params = new URLSearchParams();
    params.set('output', format);
    Object.entries(props.params).forEach(([key, val]) => {
        if (val !== '' && val !== null && val !== undefined) {
            params.set(key, val);
        }
    });

    window.location.href = props.baseUrl + '?' + params.toString();
    setTimeout(() => { exporting.value = false; }, 2000);
}

const formatMeta = {
    excel: { label: 'Excel (.xlsx)', icon: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h2v4H8z M12 11h2v6h-2z' },
    pdf:   { label: 'PDF',           icon: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M9 15v-2h1.5a1.5 1.5 0 0 0 0-3H9v5' },
    csv:   { label: 'CSV',           icon: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h8 M8 9h2' },
};
</script>

<template>
    <div class="export-dropdown" @mouseleave="close">
        <Button variant="secondary" size="sm" @click="toggle" :loading="exporting">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            {{ label }}
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:4px">
                <polyline points="6 9 12 15 18 9" />
            </svg>
        </Button>

        <div v-if="open" class="export-dropdown-menu">
            <button v-for="fmt in formats" :key="fmt" class="export-dropdown-item" @click="doExport(fmt)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path :d="formatMeta[fmt]?.icon ?? ''" />
                </svg>
                {{ formatMeta[fmt]?.label ?? fmt }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.export-dropdown {
    position: relative;
    display: inline-block;
}
.export-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 50;
    min-width: 160px;
    margin-top: 4px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    padding: 4px;
    animation: dropIn 0.15s ease;
}
@keyframes dropIn {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}
.export-dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 12px;
    font-size: 13px;
    color: #374151;
    background: none;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
    text-align: left;
}
.export-dropdown-item:hover {
    background: #f3f4f6;
    color: #111827;
}
.export-dropdown-item svg {
    flex-shrink: 0;
    color: #6b7280;
}
.export-dropdown-item:hover svg {
    color: #4f46e5;
}
</style>
