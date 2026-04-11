<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import CommentSection from './CommentSection.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({ post: Object });
const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const isAdmin = computed(() => ['super_admin','admin','school_admin','principal'].includes(currentUser.value.user_type));
const isOwner = computed(() => props.post.user_id === currentUser.value.id);

const TYPE_CONFIG = {
    achievement: { color: '#F59E0B', label: 'Achievement', bg: '#FEF3C7', emoji: '\uD83C\uDFC6' },
    event:       { color: '#6366F1', label: 'Event', bg: '#EEF2FF', emoji: '\uD83D\uDCC5' },
    sports:      { color: '#22C55E', label: 'Sports', bg: '#DCFCE7', emoji: '\u26BD' },
    gallery:     { color: '#EC4899', label: 'Gallery', bg: '#FCE7F3', emoji: '\uD83D\uDDBC\uFE0F' },
    update:      { color: '#3B82F6', label: 'Update', bg: '#DBEAFE', emoji: '\uD83D\uDCE2' },
    birthday:    { color: '#F97316', label: 'Birthday', bg: '#FFF7ED', emoji: '\uD83C\uDF82' },
};
const ROLE_COLORS = { admin: '#6366F1', school_admin: '#6366F1', super_admin: '#6366F1', principal: '#6366F1', teacher: '#0D9488', student: '#F59E0B', parent: '#EC4899' };

const showComments = ref(false);
const showMenu = ref(false);
const isEditing = ref(false);
const editContent = ref('');
const menuRef = ref(null);

const typeConf = computed(() => TYPE_CONFIG[props.post.type] || TYPE_CONFIG.update);
const roleColor = computed(() => ROLE_COLORS[props.post.user?.user_type] || '#9CA3AF');
const initials = computed(() => (props.post.user?.name || '').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase());
const roleName = computed(() => {
    const map = { admin: 'Admin', school_admin: 'Admin', super_admin: 'Admin', principal: 'Principal', teacher: 'Teacher', student: 'Student', parent: 'Parent' };
    return map[props.post.user?.user_type] || '';
});
const isVerified = computed(() => ['admin','school_admin','super_admin','principal'].includes(props.post.user?.user_type));

const isLiked = computed(() => props.post.likes?.some(l => l.user_id === currentUser.value.id));
const totalLikes = computed(() => props.post.likes_count || 0);
const totalComments = computed(() => props.post.comments_count || 0);
const tags = computed(() => props.post.tags || []);

const toggleLike = () => useForm({ type: 'like' }).post(route('school.communication.social-buzz.react.toggle', props.post.id), { preserveScroll: true });
const toggleBookmark = () => useForm({}).post(route('school.communication.social-buzz.bookmark.toggle', props.post.id), { preserveScroll: true });
const togglePin = () => { showMenu.value = false; useForm({}).post(route('school.communication.social-buzz.pin.toggle', props.post.id), { preserveScroll: true }); };
const startEdit = () => { editContent.value = props.post.content; isEditing.value = true; showMenu.value = false; };
const cancelEdit = () => { isEditing.value = false; };
const saveEdit = () => useForm({ content: editContent.value }).put(route('school.communication.social-buzz.update', props.post.id), { preserveScroll: true, onSuccess: () => { isEditing.value = false; } });
const deletePost = () => { if (confirm('Delete this post?')) useForm({}).delete(route('school.communication.social-buzz.destroy', props.post.id)); };

const timeAgo = (d) => {
    const diff = Math.floor((Date.now() - new Date(d)) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff/86400)}d ago`;
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
};
const formatCount = (n) => n >= 1000 ? `${(n/1000).toFixed(1)}K` : String(n);

const handleOutsideClick = (e) => { if (showMenu.value && menuRef.value && !menuRef.value.contains(e.target)) showMenu.value = false; };
onMounted(() => document.addEventListener('click', handleOutsideClick));
onUnmounted(() => document.removeEventListener('click', handleOutsideClick));
</script>

<template>
    <article class="card" :class="{ pinned: post.is_pinned }">
        <!-- Pinned label -->
        <div v-if="post.is_pinned" class="card-pin">
            <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            <span>Pinned Post</span>
        </div>

        <!-- Author row -->
        <div class="card-top">
            <div class="card-avatar" :style="{ background: roleColor + '18', color: roleColor }">
                {{ initials }}
            </div>
            <div class="card-info">
                <div class="card-name-row">
                    <span class="card-name">{{ post.user?.name }}</span>
                    <!-- Verified -->
                    <svg v-if="isVerified" class="card-verified" width="16" height="16" viewBox="0 0 20 20" fill="#6366F1">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="card-meta">
                    <span class="card-role" :style="{ background: roleColor + '12', color: roleColor }">{{ roleName }}</span>
                    <span class="card-dot">&middot;</span>
                    <span class="card-time">{{ timeAgo(post.created_at) }}</span>
                </div>
            </div>

            <!-- Type badge -->
            <div class="card-type" :style="{ background: typeConf.bg, color: typeConf.color }">
                <span class="card-type-emoji">{{ typeConf.emoji }}</span>
                <span>{{ typeConf.label }}</span>
            </div>

            <!-- Menu -->
            <div v-if="isOwner || isAdmin" class="card-menu-wrap" ref="menuRef">
                <button @click.stop="showMenu = !showMenu" class="card-more">
                    <svg width="18" height="18" fill="#C5C7D0" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                </button>
                <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                    <div v-if="showMenu" class="card-dropdown">
                        <button v-if="isOwner" @click="startEdit" class="dd-item">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit
                        </button>
                        <button v-if="isAdmin" @click="togglePin" class="dd-item">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                            {{ post.is_pinned ? 'Unpin' : 'Pin' }}
                        </button>
                        <button @click="deletePost" class="dd-item danger">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Content -->
        <div class="card-body">
            <div v-if="isEditing" class="card-edit">
                <textarea v-model="editContent" class="card-edit-ta" rows="3"></textarea>
                <div class="card-edit-btns">
                    <Button variant="cancel" @click="cancelEdit">Cancel</Button>
                    <Button variant="save" @click="saveEdit" :disabled="!editContent.trim()">Save</Button>
                </div>
            </div>
            <p v-else-if="post.content" class="card-text">{{ post.content }}</p>
        </div>

        <!-- Tags -->
        <div v-if="tags.length" class="card-tags">
            <span v-for="(tag, i) in tags" :key="i" class="card-tag">{{ tag }}</span>
        </div>

        <!-- Gallery -->
        <div v-if="post.media?.length" class="card-gallery">
            <template v-if="post.media.length === 1">
                <div class="gal-single">
                    <img v-if="!post.media[0].mime_type?.includes('video')" :src="'/storage/' + post.media[0].file_path" loading="lazy" />
                    <video v-else :src="'/storage/' + post.media[0].file_path" controls preload="metadata"></video>
                </div>
            </template>
            <template v-else-if="post.media.length === 2">
                <div class="gal-duo">
                    <div v-for="m in post.media.slice(0,2)" :key="m.id" class="gal-duo-cell">
                        <img v-if="!m.mime_type?.includes('video')" :src="'/storage/' + m.file_path" loading="lazy" />
                        <video v-else :src="'/storage/' + m.file_path" controls preload="metadata"></video>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="gal-multi">
                    <div class="gal-main">
                        <img v-if="!post.media[0].mime_type?.includes('video')" :src="'/storage/' + post.media[0].file_path" loading="lazy" />
                        <video v-else :src="'/storage/' + post.media[0].file_path" controls preload="metadata"></video>
                    </div>
                    <div class="gal-side">
                        <div class="gal-side-cell">
                            <img :src="'/storage/' + post.media[1].file_path" loading="lazy" />
                        </div>
                        <div class="gal-side-cell">
                            <div v-if="post.media.length > 3" class="gal-overlay">+{{ post.media.length - 2 }}</div>
                            <img v-if="post.media[2]" :src="'/storage/' + post.media[2].file_path" loading="lazy" />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Stats -->
        <div v-if="totalLikes > 0 || totalComments > 0" class="card-stats">
            <span v-if="totalLikes > 0" class="stat">
                <span class="stat-heart">&hearts;</span> {{ formatCount(totalLikes) }} likes
            </span>
            <span v-if="totalLikes > 0 && totalComments > 0" class="stat-sep">&middot;</span>
            <span v-if="totalComments > 0" class="stat">{{ formatCount(totalComments) }} comments</span>
        </div>

        <!-- Actions -->
        <div class="card-actions">
            <Button variant="icon" size="sm" @click="toggleLike" :class="{ liked: isLiked }">
                <svg width="19" height="19" :fill="isLiked ? '#EF4444' : 'none'" viewBox="0 0 24 24" :stroke="isLiked ? '#EF4444' : '#9CA3AF'" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span>{{ isLiked ? 'Liked' : 'Like' }}</span>
            </Button>
            <Button variant="icon" size="sm" @click="showComments = !showComments" :class="{ active: showComments }">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span>Comment</span>
            </Button>
            <Button variant="icon" size="sm">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                <span>Share</span>
            </Button>
        </div>

        <!-- Comment Section -->
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100">
            <div v-if="showComments" class="card-comments">
                <CommentSection :post="post" />
            </div>
        </Transition>
    </article>
</template>

<style scoped>
.card {
    background: #fff; border-radius: 18px; overflow: hidden;
    border: 1px solid #F0F1F5;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    transition: box-shadow 0.25s;
}
.card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.card.pinned { border-color: #C7D2FE; box-shadow: 0 2px 12px rgba(99,102,241,0.08); }

/* Pin */
.card-pin {
    display: flex; align-items: center; gap: 5px;
    padding: 7px 18px; background: linear-gradient(90deg, #EEF2FF, #F5F3FF);
    color: #6366F1; font-size: 0.625rem; font-weight: 700; letter-spacing: 0.02em;
}

/* Top row */
.card-top { display: flex; align-items: center; padding: 16px 18px 0; gap: 10px; }
.card-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 800; flex-shrink: 0;
}
.card-info { flex: 1; min-width: 0; }
.card-name-row { display: flex; align-items: center; gap: 4px; }
.card-name { font-size: 0.8125rem; font-weight: 700; color: #1E1E2D; }
.card-verified { flex-shrink: 0; }
.card-meta { display: flex; align-items: center; gap: 5px; margin-top: 3px; }
.card-role {
    padding: 2px 8px; border-radius: 100px;
    font-size: 0.5625rem; font-weight: 700; letter-spacing: 0.01em;
}
.card-dot { color: #D1D5DB; font-size: 0.5rem; }
.card-time { font-size: 0.6875rem; color: #9CA3AF; font-weight: 500; }

/* Type */
.card-type {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 11px; border-radius: 100px;
    font-size: 0.625rem; font-weight: 700; white-space: nowrap; flex-shrink: 0;
}
.card-type-emoji { font-size: 0.6875rem; }

/* Menu */
.card-menu-wrap { position: relative; margin-left: 2px; }
.card-more { padding: 4px; border-radius: 8px; cursor: pointer; background: none; border: none; }
.card-more:hover { background: #F5F5FA; }
.card-dropdown {
    position: absolute; right: 0; top: calc(100% + 6px);
    background: #fff; border: 1px solid #E5E7EB; border-radius: 14px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.12); z-index: 20;
    min-width: 140px; padding: 6px; overflow: hidden;
}
.dd-item {
    display: flex; align-items: center; gap: 8px; width: 100%;
    padding: 9px 12px; border-radius: 10px;
    font-size: 0.8125rem; font-weight: 600; color: #374151;
    background: none; border: none; cursor: pointer; text-align: left;
}
.dd-item:hover { background: #F5F5FA; }
.dd-item.danger { color: #EF4444; }
.dd-item.danger:hover { background: #FEF2F2; }

/* Body */
.card-body { padding: 12px 18px 4px; }
.card-text {
    font-size: 0.875rem; color: #4B5563; line-height: 1.65;
    white-space: pre-wrap; word-break: break-word; margin: 0;
}
.card-edit { padding-bottom: 4px; }
.card-edit-ta {
    width: 100%; border: 1.5px solid #6366F1; border-radius: 12px;
    padding: 10px 14px; font-size: 0.875rem; color: #374151; outline: none; resize: none;
    background: #FAFAFF;
}
.card-edit-ta:focus { box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.card-edit-btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; }
/* Tags */
.card-tags { display: flex; flex-wrap: wrap; gap: 6px; padding: 6px 18px 0; }
.card-tag {
    font-size: 0.75rem; color: #6366F1; font-weight: 600;
    cursor: pointer; transition: opacity 0.15s;
}
.card-tag:hover { opacity: 0.7; }

/* Gallery */
.card-gallery { margin: 12px 18px 0; border-radius: 14px; overflow: hidden; }
.gal-single { max-height: 340px; overflow: hidden; background: #F3F4F6; }
.gal-single img, .gal-single video { width: 100%; height: 100%; object-fit: cover; display: block; }

.gal-duo { display: grid; grid-template-columns: 1fr 1fr; gap: 3px; height: 200px; }
.gal-duo-cell { overflow: hidden; }
.gal-duo-cell img, .gal-duo-cell video { width: 100%; height: 100%; object-fit: cover; display: block; }

.gal-multi { display: flex; gap: 3px; height: 200px; }
.gal-main { flex: 2; overflow: hidden; }
.gal-main img, .gal-main video { width: 100%; height: 100%; object-fit: cover; display: block; }
.gal-side { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.gal-side-cell { flex: 1; overflow: hidden; position: relative; background: #F3F4F6; }
.gal-side-cell img { width: 100%; height: 100%; object-fit: cover; display: block; }
.gal-overlay {
    position: absolute; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.25rem; font-weight: 800; z-index: 2;
}

/* Stats */
.card-stats { padding: 12px 18px 6px; font-size: 0.6875rem; color: #9CA3AF; display: flex; align-items: center; }
.stat { display: inline-flex; align-items: center; gap: 3px; }
.stat-heart { color: #EF4444; font-size: 0.75rem; }
.stat-sep { margin: 0 8px; }

/* Actions */
.card-actions {
    display: flex; border-top: 1px solid #F3F4F6;
    padding: 6px 12px; margin-top: 6px;
}
.liked { color: #EF4444; font-weight: 700; }
.liked:hover { background: #FEF2F2; }
.active { color: #6366F1; }
.active:hover { background: #EEF2FF; }

/* Comments zone */
.card-comments { border-top: 1px solid #F3F4F6; }
</style>
