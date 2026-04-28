<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

const school = useSchoolStore();
const confirm = useConfirm();

const props = defineProps({
    templates: { type: Array, required: true },
});

const deleteTemplate = async (id, name) => {
    const ok = await confirm({
        title: 'Delete template?',
        message: `Delete template "${name}"? This cannot be undone.`,
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    router.delete(`/school/utility/certificates/${id}`, { preserveScroll: true });
};

const orientationLabel = (o) => o === 'portrait' ? 'Portrait' : 'Landscape';
const orientationClass = (o) => o === 'portrait' ? 'badge-portrait' : 'badge-landscape';

const bgStyle = (tpl) => {
    const bg = tpl.background;
    if (!bg) return { background: '#1a1a2e' };
    const side = bg.front !== undefined ? bg.front : bg;
    return side?.type === 'image'
        ? { backgroundImage: `url(${side.value})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { background: side?.value || '#1a1a2e' };
};

const formatDate = (d) => school.fmtDate(d);
const varCount = (tpl) => tpl.custom_vars?.length ?? 0;
</script>

<template>
    <Head title="Certificate Templates" />
    <SchoolLayout title="Certificate Templates">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Certificate Templates</h1>
                <p class="text-sm text-slate-500 mt-0.5">Design, manage, and print student certificates</p>
            </div>
            <Link href="/school/utility/certificates/create"
                  class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                + New Template
            </Link>
        </div>

        <!-- Empty state -->
        <div v-if="!templates.length" class="flex flex-col items-center justify-center py-24 text-center">
            <div class="text-5xl mb-4">🎓</div>
            <h2 class="text-lg font-semibold text-slate-700 mb-1">No certificate templates yet</h2>
            <p class="text-sm text-slate-500 mb-5">Create your first certificate template to get started.</p>
            <Link href="/school/utility/certificates/create"
                  class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                + Create Template
            </Link>
        </div>

        <!-- Template grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <div v-for="tpl in templates" :key="tpl.id"
                 class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow">

                <!-- Preview -->
                <div class="relative h-36 flex items-center justify-center"
                     :style="bgStyle(tpl)">
                    <!-- A4 frame hint -->
                    <div :class="['preview-frame', tpl.orientation === 'portrait' ? 'frame-portrait' : 'frame-landscape']">
                        <div class="frame-inner">
                            <div class="frame-line-title"></div>
                            <div class="frame-line-subtitle"></div>
                            <div class="frame-lines">
                                <div class="frame-line w-full"></div>
                                <div class="frame-line w-5/6"></div>
                                <div class="frame-line w-full"></div>
                            </div>
                            <div class="frame-sigs">
                                <div class="frame-sig"></div>
                                <div class="frame-sig"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="font-semibold text-slate-800 text-sm leading-snug">{{ tpl.name }}</h3>
                        <span :class="['badge', orientationClass(tpl.orientation)]">
                            {{ orientationLabel(tpl.orientation) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mb-4">
                        {{ varCount(tpl) }} custom var{{ varCount(tpl) !== 1 ? 's' : '' }}
                        &middot; {{ formatDate(tpl.created_at) }}
                    </p>

                    <div class="flex gap-2">
                        <Link :href="`/school/utility/certificates/${tpl.id}/generate`"
                              class="flex-1 text-center py-1.5 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            Generate
                        </Link>
                        <Link :href="`/school/utility/certificates/${tpl.id}/edit`"
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
.badge { flex-shrink: 0; font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 99px; }
.badge-landscape { background: #dbeafe; color: #1d4ed8; }
.badge-portrait  { background: #ede9fe; color: #6d28d9; }

.preview-frame {
    background: rgba(255,255,255,0.12);
    border: 1.5px solid rgba(255,255,255,0.5);
    border-radius: 3px;
    backdrop-filter: blur(2px);
}
.frame-landscape { width: 100px; height: 71px; }
.frame-portrait  { width: 71px;  height: 100px; }

.frame-inner { padding: 6px; display: flex; flex-direction: column; gap: 4px; height: 100%; }

.frame-line-title    { height: 5px; background: rgba(255,255,255,0.7); border-radius: 2px; width: 60%; align-self: center; }
.frame-line-subtitle { height: 3px; background: rgba(255,255,255,0.4); border-radius: 2px; width: 40%; align-self: center; }

.frame-lines { display: flex; flex-direction: column; gap: 2px; flex: 1; justify-content: center; }
.frame-line  { height: 2px; background: rgba(255,255,255,0.3); border-radius: 1px; }

.frame-sigs { display: flex; justify-content: space-between; gap: 6px; margin-top: 2px; }
.frame-sig  { flex: 1; height: 10px; border-top: 1.5px solid rgba(255,255,255,0.4); }
</style>
