<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ classes: Array });
const page = usePage();
const user = computed(() => page.props.auth.user);

const POST_TYPES = [
    { value: 'update', label: 'Update', emoji: '\uD83D\uDCE2', color: '#3B82F6' },
    { value: 'achievement', label: 'Achievement', emoji: '\uD83C\uDFC6', color: '#F59E0B' },
    { value: 'event', label: 'Event', emoji: '\uD83D\uDCC5', color: '#6366F1' },
    { value: 'sports', label: 'Sports', emoji: '\u26BD', color: '#22C55E' },
    { value: 'gallery', label: 'Gallery', emoji: '\uD83D\uDDBC\uFE0F', color: '#EC4899' },
    { value: 'birthday', label: 'Birthday', emoji: '\uD83C\uDF82', color: '#F97316' },
];

const form = useForm({ content: '', visibility: 'school', type: 'update', class_id: null, media: [] });
const mediaPreviews = ref([]);
const fileInput = ref(null);
const isOpen = ref(false);
const showTypeMenu = ref(false);

const handleFileSelect = (e) => {
    Array.from(e.target.files).forEach(file => {
        if (file.size > 20 * 1024 * 1024) return;
        form.media.push(file);
        const reader = new FileReader();
        reader.onload = (ev) => mediaPreviews.value.push({ url: ev.target.result, name: file.name, type: file.type });
        reader.readAsDataURL(file);
    });
    if (fileInput.value) fileInput.value.value = '';
};
const removeMedia = (i) => { form.media.splice(i, 1); mediaPreviews.value.splice(i, 1); };

const submit = () => {
    form.post(route('school.communication.social-buzz.store'), {
        forceFormData: true,
        onSuccess: () => { form.reset(); mediaPreviews.value = []; isOpen.value = false; },
    });
};

const cancel = () => { isOpen.value = false; form.reset(); mediaPreviews.value = []; };

const isStaff = computed(() => ['super_admin','admin','school_admin','principal','teacher'].includes(user.value.user_type));
const initials = computed(() => user.value.name?.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase());
const selectedType = computed(() => POST_TYPES.find(t => t.value === form.type) || POST_TYPES[0]);

const selectType = (t) => { form.type = t.value; showTypeMenu.value = false; };

const VISIBILITY_LABELS = { school: 'Everyone', staff: 'Staff Only', class: 'Class' };
const visibilities = ['school', 'staff', 'class'];
const cycleVisibility = () => {
    const idx = visibilities.indexOf(form.visibility);
    form.visibility = visibilities[(idx + 1) % visibilities.length];
    if (form.visibility !== 'class') form.class_id = null;
};
</script>

<template>
    <div class="cmp" :class="{ open: isOpen }">
        <!-- Collapsed -->
        <div class="cmp-collapsed" @click="isOpen = true" v-if="!isOpen">
            <div class="cmp-avatar">{{ initials }}</div>
            <div class="cmp-prompt">What's happening in school?</div>
            <div class="cmp-quick-actions">
                <span class="cmp-quick" @click.stop="fileInput?.click()">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#6366F1" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
            </div>
        </div>

        <!-- Expanded -->
        <template v-if="isOpen">
            <div class="cmp-header">
                <div class="cmp-avatar sm">{{ initials }}</div>
                <div class="cmp-user-info">
                    <span class="cmp-user-name">{{ user.name }}</span>
                    <div class="cmp-badges">
                        <!-- Type badge -->
                        <button class="cmp-badge" :style="{ background: selectedType.color + '12', color: selectedType.color, borderColor: selectedType.color + '30' }" @click="showTypeMenu = !showTypeMenu">
                            <span>{{ selectedType.emoji }}</span>
                            <span>{{ selectedType.label }}</span>
                            <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <!-- Visibility badge -->
                        <button v-if="isStaff" class="cmp-badge vis" @click="cycleVisibility">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span>{{ VISIBILITY_LABELS[form.visibility] }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Type menu dropdown -->
            <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                <div v-if="showTypeMenu" class="cmp-type-menu">
                    <button v-for="t in POST_TYPES" :key="t.value" class="cmp-type-opt" :class="{ active: form.type === t.value }" @click="selectType(t)">
                        <span class="cmp-type-emoji">{{ t.emoji }}</span>
                        <span>{{ t.label }}</span>
                    </button>
                </div>
            </Transition>

            <!-- Class selector -->
            <div v-if="form.visibility === 'class'" class="cmp-class-row">
                <select v-model="form.class_id" class="cmp-class-select">
                    <option :value="null">Select Class...</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <!-- Text area -->
            <textarea v-model="form.content" placeholder="Share an update, achievement, or event..." class="cmp-textarea" rows="3" autofocus></textarea>

            <!-- Media previews -->
            <div v-if="mediaPreviews.length" class="cmp-media">
                <div v-for="(m, i) in mediaPreviews" :key="i" class="cmp-thumb">
                    <img v-if="m.type.startsWith('image/')" :src="m.url" />
                    <div v-else class="cmp-thumb-vid">
                        <svg width="20" height="20" fill="#9CA3AF" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                    <button @click="removeMedia(i)" class="cmp-thumb-x">&times;</button>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="cmp-bottom">
                <div class="cmp-tools">
                    <button type="button" @click="fileInput?.click()" class="cmp-tool">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Photo</span>
                    </button>
                    <input type="file" ref="fileInput" @change="handleFileSelect" multiple accept="image/*,video/*" class="sr-only" />
                </div>
                <div class="cmp-right">
                    <button @click="cancel" class="cmp-cancel">Cancel</button>
                    <button @click="submit" class="cmp-post" :disabled="form.processing || (!form.content && !form.media.length)">
                        <svg v-if="form.processing" class="cmp-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93"/></svg>
                        <span>{{ form.processing ? 'Posting...' : 'Post' }}</span>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.cmp {
    background: #fff; border-radius: 18px;
    margin-bottom: 14px; overflow: hidden;
    border: 1px solid #F0F1F5;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    transition: box-shadow 0.25s;
}
.cmp.open { box-shadow: 0 8px 24px rgba(99,102,241,0.08); border-color: #E0E7FF; }

/* Collapsed */
.cmp-collapsed {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px; cursor: pointer;
}
.cmp-collapsed:hover { background: #FAFAFF; }
.cmp-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #6366F1, #8B5CF6);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 0.6875rem; font-weight: 800; flex-shrink: 0;
}
.cmp-avatar.sm { width: 36px; height: 36px; font-size: 0.625rem; }
.cmp-prompt {
    flex: 1; color: #9CA3AF; font-size: 0.875rem; font-weight: 500;
}
.cmp-quick-actions { display: flex; gap: 4px; }
.cmp-quick {
    width: 34px; height: 34px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: #EEF2FF; cursor: pointer;
}
.cmp-quick:hover { background: #E0E7FF; }

/* Expanded header */
.cmp-header {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 18px 8px;
}
.cmp-user-info { flex: 1; }
.cmp-user-name { font-size: 0.8125rem; font-weight: 700; color: #1E1E2D; display: block; margin-bottom: 6px; }
.cmp-badges { display: flex; gap: 6px; flex-wrap: wrap; }
.cmp-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 100px;
    font-size: 0.625rem; font-weight: 700;
    border: 1px solid transparent; cursor: pointer;
    transition: all 0.15s;
}
.cmp-badge:hover { filter: brightness(0.96); }
.cmp-badge.vis {
    background: #F3F4F6; color: #6B7280; border-color: #E5E7EB;
}

/* Type menu */
.cmp-type-menu {
    display: flex; flex-wrap: wrap; gap: 6px; padding: 4px 18px 8px;
}
.cmp-type-opt {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border-radius: 10px;
    font-size: 0.6875rem; font-weight: 600; color: #6B7280;
    background: #F5F5FA; border: 1.5px solid transparent;
    cursor: pointer; transition: all 0.15s;
}
.cmp-type-opt:hover { background: #EEF2FF; color: #6366F1; }
.cmp-type-opt.active { background: #EEF2FF; color: #6366F1; border-color: #C7D2FE; }
.cmp-type-emoji { font-size: 0.8125rem; }

/* Class */
.cmp-class-row { padding: 0 18px 6px; }
.cmp-class-select {
    width: 100%; padding: 8px 12px; border: 1.5px solid #E5E7EB;
    border-radius: 10px; font-size: 0.75rem; color: #6B7280;
    background: #F8F9FC; outline: none; cursor: pointer;
}
.cmp-class-select:focus { border-color: #6366F1; }

/* Textarea */
.cmp-textarea {
    width: 100%; border: none; padding: 8px 18px 12px;
    font-size: 0.875rem; color: #1E1E2D; outline: none; resize: none; line-height: 1.6;
    background: transparent;
}
.cmp-textarea::placeholder { color: #D1D5DB; }

/* Media */
.cmp-media { display: flex; gap: 8px; flex-wrap: wrap; padding: 0 18px 10px; }
.cmp-thumb {
    position: relative; width: 64px; height: 64px;
    border-radius: 12px; overflow: hidden; border: 1px solid #E5E7EB;
}
.cmp-thumb img { width: 100%; height: 100%; object-fit: cover; }
.cmp-thumb-vid {
    width: 100%; height: 100%; background: #F3F4F6;
    display: flex; align-items: center; justify-content: center;
}
.cmp-thumb-x {
    position: absolute; top: 3px; right: 3px; width: 18px; height: 18px; border-radius: 50%;
    background: rgba(0,0,0,0.55); color: #fff; font-size: 12px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: none;
}

/* Bottom */
.cmp-bottom {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 18px 14px;
    border-top: 1px solid #F3F4F6;
}
.cmp-tools { display: flex; gap: 6px; }
.cmp-tool {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border-radius: 10px;
    font-size: 0.6875rem; font-weight: 600; color: #9CA3AF;
    background: #F5F5FA; border: none; cursor: pointer;
}
.cmp-tool:hover { color: #6366F1; background: #EEF2FF; }

.cmp-right { display: flex; gap: 8px; }
.cmp-cancel {
    padding: 8px 16px; border-radius: 10px;
    font-size: 0.8125rem; font-weight: 600; color: #9CA3AF;
    background: none; border: none; cursor: pointer;
}
.cmp-cancel:hover { background: #F3F4F6; }
.cmp-post {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 8px 22px; border-radius: 10px;
    font-size: 0.8125rem; font-weight: 700; color: #fff;
    background: linear-gradient(135deg, #6366F1, #7C3AED);
    border: none; cursor: pointer; transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(99,102,241,0.25);
}
.cmp-post:hover:not(:disabled) { box-shadow: 0 4px 16px rgba(99,102,241,0.35); transform: translateY(-1px); }
.cmp-post:disabled { background: #D1D5DB; box-shadow: none; cursor: not-allowed; }

.cmp-spin { animation: cmp-rotate 1s linear infinite; }
@keyframes cmp-rotate { to { transform: rotate(360deg); } }

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
</style>
