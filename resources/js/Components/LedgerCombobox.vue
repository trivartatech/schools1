<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    modelValue  : { type: [Number, String], default: '' },
    ledgers     : { type: Array, default: () => [] },
    placeholder : { type: String, default: '— Search or select account —' },
    inputClass  : { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const search   = ref('');
const isOpen   = ref(false);
const inputRef = ref(null);

// ── Sync display text when value changes from parent ─────────
function displayName(id) {
    const l = props.ledgers.find(x => x.id == id);
    if (!l) return '';
    return l.code ? `[${l.code}] ${l.name}` : l.name;
}

watch(() => props.modelValue, (val) => {
    if (val && !isOpen.value) search.value = displayName(val);
    if (!val) search.value = '';
}, { immediate: true });

// ── Filtered ledgers ─────────────────────────────────────────
const filteredLedgers = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.ledgers;
    return props.ledgers.filter(l =>
        l.name.toLowerCase().includes(q) ||
        (l.code && l.code.toLowerCase().includes(q)) ||
        (l.ledger_type?.name?.toLowerCase().includes(q))
    );
});

// ── Group by ledger type name ────────────────────────────────
const groupedLedgers = computed(() => {
    const groups = {};
    for (const l of filteredLedgers.value) {
        const key = l.ledger_type?.name ?? 'Other';
        if (!groups[key]) groups[key] = [];
        groups[key].push(l);
    }
    return groups;
});

// ── Interaction handlers ─────────────────────────────────────
function onFocus() {
    search.value = ''; // clear on focus so user can type fresh
    isOpen.value = true;
}

function onBlur() {
    setTimeout(() => {
        isOpen.value = false;
        // Restore display name after blur
        search.value = props.modelValue ? displayName(props.modelValue) : '';
    }, 160);
}

function select(ledger) {
    emit('update:modelValue', ledger.id);
    search.value = displayName(ledger.id);
    isOpen.value = false;
}

function clearValue() {
    emit('update:modelValue', '');
    search.value = '';
    isOpen.value = false;
    inputRef.value?.focus();
}
</script>

<template>
    <div class="ledger-combo" :class="{ 'combo-open': isOpen }">
        <div class="combo-wrap">
            <input
                ref="inputRef"
                v-model="search"
                @focus="onFocus"
                @blur="onBlur"
                :placeholder="placeholder"
                :class="['combo-input', inputClass]"
                autocomplete="off"
                spellcheck="false"
            />
            <button v-if="modelValue" type="button" class="combo-clear" @mousedown.prevent="clearValue" tabindex="-1">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <span class="combo-chevron">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
        </div>

        <!-- Dropdown -->
        <div v-if="isOpen" class="combo-dropdown">
            <div v-if="filteredLedgers.length === 0" class="combo-empty">No accounts found</div>
            <template v-for="(group, typeName) in groupedLedgers" :key="typeName">
                <div class="combo-group">{{ typeName }}</div>
                <div
                    v-for="l in group"
                    :key="l.id"
                    class="combo-option"
                    :class="{ 'combo-active': l.id == modelValue }"
                    @mousedown.prevent="select(l)"
                >
                    <span v-if="l.code" class="combo-code">{{ l.code }}</span>
                    <span class="combo-name">{{ l.name }}</span>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.ledger-combo { position: relative; }

.combo-wrap { display: flex; align-items: center; position: relative; }

.combo-input {
    border: 1.5px solid #e2e8f0; border-radius: 7px;
    padding: 7px 42px 7px 10px;
    font-size: 0.82rem; outline: none; font-family: inherit; color: #1e293b;
    transition: border-color 0.15s; width: 100%; background: #fff;
}
.combo-input:focus { border-color: #6366f1; }

.combo-clear {
    position: absolute; right: 24px;
    background: none; border: none; color: #94a3b8;
    cursor: pointer; padding: 4px; line-height: 0;
    transition: color 0.12s;
}
.combo-clear:hover { color: #ef4444; }

.combo-chevron {
    position: absolute; right: 7px;
    color: #94a3b8; pointer-events: none; line-height: 0;
    transition: transform 0.15s;
}
.combo-open .combo-chevron { transform: rotate(180deg); }

/* Dropdown */
.combo-dropdown {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 200;
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 10px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.13);
    max-height: 260px; overflow-y: auto;
}

.combo-group {
    font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
    color: #94a3b8; padding: 8px 12px 3px; letter-spacing: 0.05em;
    border-top: 1px solid #f1f5f9; position: sticky; top: 0;
    background: #fff;
}
.combo-group:first-child { border-top: none; }

.combo-option {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; cursor: pointer;
    font-size: 0.82rem; color: #374151;
    transition: background 0.1s;
}
.combo-option:hover  { background: #f1f5f9; }
.combo-active { background: #ede9fe; color: #6366f1; font-weight: 600; }

.combo-code {
    font-family: monospace; font-size: 0.72rem;
    background: #f1f5f9; color: #64748b;
    padding: 1px 5px; border-radius: 4px; flex-shrink: 0;
}
.combo-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.combo-empty { padding: 14px; text-align: center; color: #94a3b8; font-size: 0.82rem; }
</style>
