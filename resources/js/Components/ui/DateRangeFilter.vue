<template>
    <div class="drf">
        <div class="drf-presets">
            <button v-for="p in presets" :key="p.id"
                class="drf-preset"
                :class="{ active: activePreset === p.id }"
                @click="applyPreset(p.id)">
                {{ p.label }}
            </button>
        </div>
        <div class="drf-custom">
            <input type="date" v-model="localFrom" :max="localTo || undefined" class="drf-date" />
            <span class="drf-arrow">→</span>
            <input type="date" v-model="localTo" :min="localFrom || undefined" class="drf-date" />
            <button class="drf-apply" @click="applyCustom" :disabled="!isDirty">Apply</button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    from:    { type: String, default: '' },
    to:      { type: String, default: '' },
    minDate: { type: String, default: '' },
    maxDate: { type: String, default: '' },
});

const emit = defineEmits(['change']);

const localFrom = ref(props.from);
const localTo   = ref(props.to);

watch(() => [props.from, props.to], ([f, t]) => {
    localFrom.value = f;
    localTo.value   = t;
});

function fmt(d) {
    return d.toISOString().slice(0, 10);
}

const presets = [
    { id: 'today',      label: 'Today' },
    { id: 'this_week',  label: 'This Week' },
    { id: 'this_month', label: 'This Month' },
    { id: 'last_30',    label: 'Last 30 Days' },
    { id: 'this_term',  label: 'This Term' },
    { id: 'this_year',  label: 'This Year' },
];

function presetRange(id) {
    const now = new Date();
    const start = new Date(now);
    const end   = new Date(now);

    switch (id) {
        case 'today':
            break;
        case 'this_week':
            start.setDate(now.getDate() - now.getDay());
            break;
        case 'this_month':
            start.setDate(1);
            break;
        case 'last_30':
            start.setDate(now.getDate() - 29);
            break;
        case 'this_term':
            start.setMonth(now.getMonth() - 3);
            break;
        case 'this_year':
            start.setMonth(0);
            start.setDate(1);
            break;
    }
    return [fmt(start), fmt(end)];
}

const activePreset = computed(() => {
    for (const p of presets) {
        const [f, t] = presetRange(p.id);
        if (localFrom.value === f && localTo.value === t) return p.id;
    }
    return null;
});

const isDirty = computed(() =>
    (localFrom.value !== props.from || localTo.value !== props.to) &&
    localFrom.value && localTo.value
);

function applyPreset(id) {
    const [f, t] = presetRange(id);
    localFrom.value = f;
    localTo.value   = t;
    emit('change', { from: f, to: t });
}

function applyCustom() {
    if (!localFrom.value || !localTo.value) return;
    emit('change', { from: localFrom.value, to: localTo.value });
}
</script>

<style scoped>
.drf {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.drf-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.drf-preset {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 5px 12px;
    font-size: 0.78rem;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.drf-preset:hover { background: #e0e7ff; border-color: #c7d2fe; color: #4338ca; }
.drf-preset.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border-color: transparent;
}
.drf-custom {
    display: flex;
    align-items: center;
    gap: 6px;
}
.drf-date {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 5px 8px;
    font-size: 0.78rem;
    color: #1e293b;
    background: #fff;
    outline: none;
    transition: border-color 0.15s;
}
.drf-date:focus { border-color: #6366f1; }
.drf-arrow { color: #94a3b8; font-size: 0.85rem; }
.drf-apply {
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s;
}
.drf-apply:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
