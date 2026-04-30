<script setup>
/**
 * UI Sandbox · Buttons
 * Every <Button> variant × size × state × slot.
 * URL: /school/_ui-sandbox/buttons
 */
import { ref } from 'vue';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

const btnLoading = ref(false);
function toggleBtnLoading() {
    btnLoading.value = true;
    setTimeout(() => (btnLoading.value = false), 1500);
}
</script>

<template>
    <SchoolLayout title="UI Sandbox · Buttons">

        <PageHeader
            title="Buttons"
            subtitle="Every <Button> variant, size, state, slot and render-target."
            back-href="/school/_ui-sandbox"
            back-label="← Back to sandbox"
        />

        <h2 class="section-heading">Variants — 10 total</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button variant="primary">primary</Button>
            <Button variant="secondary">secondary</Button>
            <Button variant="danger">danger</Button>
            <Button variant="success">success</Button>
            <Button variant="warning">warning</Button>
            <Button variant="save">save</Button>
            <Button variant="cancel">cancel</Button>
            <Button variant="ghost">ghost</Button>
            <Button variant="icon" aria-label="Settings">
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </template>
            </Button>
            <Button variant="tab" :active="false">tab (inactive)</Button>
            <Button variant="tab" :active="true">tab (active)</Button>
        </div>

        <h2 class="section-heading">Sizes — xs / sm / md / lg</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button size="xs">xs</Button>
            <Button size="sm">sm</Button>
            <Button size="md">md (default)</Button>
            <Button size="lg">lg</Button>
            <Button size="xs" variant="secondary">xs secondary</Button>
            <Button size="lg" variant="danger">lg danger</Button>
        </div>

        <h2 class="section-heading">States — loading / disabled / block</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <Button :loading="btnLoading" @click="toggleBtnLoading">
                    {{ btnLoading ? 'Saving…' : 'Click to load 1.5s' }}
                </Button>
                <Button disabled>disabled</Button>
                <Button variant="success" disabled>disabled success</Button>
                <Button variant="danger" :loading="true">always loading</Button>
            </div>
            <Button block>block (full-width)</Button>
            <Button block variant="secondary" size="sm">block · secondary · sm</Button>
        </div>

        <h2 class="section-heading">Slots — #icon (leading) + #iconRight (trailing)</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button>
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                </template>
                Add new
            </Button>
            <Button variant="secondary">
                Continue
                <template #iconRight>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </template>
            </Button>
            <Button variant="success">
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                </template>
                Approve
                <template #iconRight>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </template>
            </Button>
        </div>

        <h2 class="section-heading">Render targets — as=&quot;button&quot; / as=&quot;link&quot; / as=&quot;a&quot;</h2>
        <div class="card" style="padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <Button as="button" type="submit">as=&quot;button&quot; (default)</Button>
            <Button as="link" href="/school">as=&quot;link&quot; — Inertia &lt;Link&gt;</Button>
            <Button as="a" href="https://example.com" variant="secondary">as=&quot;a&quot; — plain &lt;a&gt;</Button>
        </div>

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
</style>
