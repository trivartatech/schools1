<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';

const props = defineProps({
    templates: { type: Array, required: true },
});

const deleteTemplate = (id, name) => {
    if (!confirm(`Delete template "${name}"? This cannot be undone.`)) return;
    router.delete(`/school/utility/id-cards/${id}`, { preserveScroll: true });
};

const orientationLabel = (o) => o === 'portrait' ? 'Portrait' : 'Landscape';
const orientationClass = (o) => o === 'portrait' ? 'badge-portrait' : 'badge-landscape';

const bgStyle = (tpl) => {
    const bg = tpl.background;
    if (!bg) return { background: '#1e3a8a' };
    return bg.type === 'image'
        ? { backgroundImage: `url(${bg.value})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { background: bg.value || '#1e3a8a' };
};

const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="ID Card Templates" />
    <SchoolLayout title="ID Card Templates">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-slate-800">ID Card Templates</h1>
                <p class="text-sm text-slate-500 mt-0.5">Design, manage, and print student ID cards</p>
            </div>
            <Link href="/school/utility/id-cards/create"
                  class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                + New Template
            </Link>
        </div>

        <!-- Empty state -->
        <div v-if="!templates.length" class="flex flex-col items-center justify-center py-24 text-center">
            <div class="text-5xl mb-4">🪪</div>
            <h2 class="text-lg font-semibold text-slate-700 mb-1">No templates yet</h2>
            <p class="text-sm text-slate-500 mb-5">Create your first ID card template to get started.</p>
            <Link href="/school/utility/id-cards/create"
                  class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                + Create Template
            </Link>
        </div>

        <!-- Template grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <div v-for="tpl in templates" :key="tpl.id"
                 class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow">

                <!-- Card preview -->
                <div class="relative h-32 flex items-center justify-center"
                     :style="bgStyle(tpl)">
                    <!-- Orientation frame hint -->
                    <div :class="['preview-frame', tpl.orientation === 'portrait' ? 'frame-portrait' : 'frame-landscape']">
                        <div class="frame-inner">
                            <div class="frame-line w-3/4"></div>
                            <div class="frame-line w-1/2"></div>
                            <div class="frame-line w-2/3"></div>
                        </div>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="font-semibold text-slate-800 text-sm leading-snug">{{ tpl.name }}</h3>
                        <span :class="['badge', orientationClass(tpl.orientation)]">
                            {{ orientationLabel(tpl.orientation) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mb-4">
                        {{ tpl.columns }} col{{ tpl.columns !== 1 ? 's' : '' }}/page &middot; {{ formatDate(tpl.created_at) }}
                    </p>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <Link :href="`/school/utility/id-cards/${tpl.id}/generate`"
                              class="flex-1 text-center py-1.5 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            Generate
                        </Link>
                        <Link :href="`/school/utility/id-cards/${tpl.id}/edit`"
                              class="flex-1 text-center py-1.5 text-xs font-medium text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                            Edit
                        </Link>
                        <button @click="deleteTemplate(tpl.id, tpl.name)"
                                class="py-1.5 px-2.5 text-xs text-red-500 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                            ✕
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
.badge {
    flex-shrink: 0;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 99px;
}
.badge-landscape { background: #dbeafe; color: #1d4ed8; }
.badge-portrait  { background: #ede9fe; color: #6d28d9; }

.preview-frame {
    background: rgba(255,255,255,0.15);
    border: 1.5px solid rgba(255,255,255,0.5);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}
.frame-landscape { width: 80px; height: 50px; }
.frame-portrait  { width: 50px; height: 80px; }

.frame-inner {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 6px;
    width: 100%;
}
.frame-line {
    height: 3px;
    background: rgba(255,255,255,0.6);
    border-radius: 2px;
    align-self: flex-start;
}
</style>
