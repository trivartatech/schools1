<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ post: Object });
const page = usePage();
const user = page.props.auth.user;

const ROLE_COLORS = {
    super_admin: '#6366F1', admin: '#6366F1', school_admin: '#6366F1',
    principal: '#6366F1', teacher: '#0D9488', student: '#F59E0B', parent: '#EC4899',
};

const form = useForm({ comment: '', parent_id: null });
const replyTo = ref(null);
const commentInput = ref(null);

const submitComment = () => {
    if (!form.comment.trim()) return;
    form.post(route('school.communication.social-buzz.comment.add', props.post.id), {
        onSuccess: () => { form.reset(); replyTo.value = null; },
        preserveScroll: true,
    });
};
const setReply = (c) => { replyTo.value = c; form.parent_id = c.id; setTimeout(() => commentInput.value?.focus(), 50); };
const cancelReply = () => { replyTo.value = null; form.parent_id = null; };

const timeAgo = (d) => {
    if (!d) return '';
    const diff = Math.floor((Date.now() - new Date(d)) / 1000);
    if (diff < 60) return 'now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d`;
    return new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
};

const roleColor = (userType) => ROLE_COLORS[userType] || '#6B7280';
const initials = (name) => name?.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() || '?';

const comments = computed(() => props.post.comments || []);
</script>

<template>
    <div class="cs">
        <!-- Input box -->
        <div class="cs-input-box">
            <div v-if="replyTo" class="cs-reply-bar">
                <span>Replying to <b>{{ replyTo.user.name }}</b></span>
                <button @click="cancelReply" class="cs-reply-x">&times;</button>
            </div>
            <div class="cs-input-row">
                <div class="cs-my-av" :style="{ background: roleColor(user.user_type) + '18', color: roleColor(user.user_type) }">
                    {{ initials(user.name) }}
                </div>
                <input
                    ref="commentInput" v-model="form.comment" type="text"
                    :placeholder="replyTo ? `Reply to ${replyTo.user.name}...` : 'Write a comment...'"
                    class="cs-input" @keyup.enter="submitComment" maxlength="1000"
                />
                <button
                    @click="submitComment"
                    :disabled="!form.comment.trim() || form.processing"
                    class="cs-send" :class="{ on: form.comment.trim() }"
                >
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-7-7l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Comments -->
        <div class="cs-list" v-if="comments.length">
            <TransitionGroup enter-active-class="cs-anim" enter-from-class="cs-from" enter-to-class="cs-to">
                <div v-for="c in comments" :key="c.id" class="cs-thread">
                    <div class="cs-item">
                        <div class="cs-av" :style="{ background: roleColor(c.user?.user_type) + '18', color: roleColor(c.user?.user_type) }">
                            {{ initials(c.user?.name) }}
                        </div>
                        <div class="cs-body">
                            <div class="cs-bubble">
                                <span class="cs-author">{{ c.user?.name }}</span>
                                <p class="cs-text">{{ c.comment }}</p>
                            </div>
                            <div class="cs-foot">
                                <span class="cs-time">{{ timeAgo(c.created_at) }}</span>
                                <button @click="setReply(c)" class="cs-reply-btn">Reply</button>
                            </div>
                        </div>
                    </div>
                    <!-- Replies -->
                    <div v-if="c.replies?.length" class="cs-replies">
                        <div v-for="r in c.replies" :key="r.id" class="cs-item reply">
                            <div class="cs-av sm" :style="{ background: roleColor(r.user?.user_type) + '18', color: roleColor(r.user?.user_type) }">
                                {{ initials(r.user?.name) }}
                            </div>
                            <div class="cs-body">
                                <div class="cs-bubble reply-bubble">
                                    <span class="cs-author">{{ r.user?.name }}</span>
                                    <p class="cs-text">{{ r.comment }}</p>
                                </div>
                                <div class="cs-foot">
                                    <span class="cs-time">{{ timeAgo(r.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>

        <!-- Empty -->
        <div v-else class="cs-empty">
            <span>No comments yet. Be the first!</span>
        </div>
    </div>
</template>

<style scoped>
.cs { padding: 14px 18px 16px; }

/* Input box */
.cs-input-box {
    background: #F8F9FC; border: 1px solid #E5E7EB; border-radius: 14px;
    overflow: hidden; margin-bottom: 14px;
}
.cs-reply-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 14px; background: #EEF2FF;
    font-size: 0.6875rem; color: #6366F1; font-weight: 500;
}
.cs-reply-bar b { font-weight: 700; }
.cs-reply-x { font-size: 1rem; color: #818CF8; line-height: 1; cursor: pointer; background: none; border: none; }

.cs-input-row { display: flex; align-items: center; gap: 10px; padding: 10px 14px; }
.cs-my-av {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.5625rem; font-weight: 800; flex-shrink: 0;
}
.cs-input {
    flex: 1; background: transparent; border: none;
    font-size: 0.8125rem; color: #1F2937; outline: none; padding: 0;
}
.cs-input::placeholder { color: #9CA3AF; }

.cs-send {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #E5E7EB; color: #9CA3AF; border: none;
    cursor: pointer; transition: all 0.2s; flex-shrink: 0;
}
.cs-send.on { background: #6366F1; color: #fff; }
.cs-send.on:hover { background: #4F46E5; }
.cs-send:disabled { cursor: not-allowed; }

/* List */
.cs-list { display: flex; flex-direction: column; gap: 12px; }
.cs-anim { transition: all 0.25s ease-out; }
.cs-from { opacity: 0; transform: translateY(-4px); }
.cs-to { opacity: 1; transform: translateY(0); }

.cs-thread { display: flex; flex-direction: column; }
.cs-item { display: flex; gap: 10px; }
.cs-av {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.5rem; font-weight: 800; flex-shrink: 0; margin-top: 2px;
}
.cs-av.sm { width: 22px; height: 22px; font-size: 0.4375rem; }

.cs-body { flex: 1; min-width: 0; }
.cs-bubble {
    display: inline-block; max-width: 100%;
    background: #F3F4F6; padding: 8px 12px; border-radius: 4px 14px 14px 14px;
}
.reply-bubble { background: #F8F9FC; }
.cs-author { display: block; font-size: 0.6875rem; font-weight: 700; color: #1F2937; margin-bottom: 1px; }
.cs-text { font-size: 0.8125rem; color: #4B5563; line-height: 1.45; word-break: break-word; margin: 0; }

.cs-foot { display: flex; align-items: center; gap: 10px; margin-top: 4px; padding-left: 4px; }
.cs-time { font-size: 0.625rem; color: #9CA3AF; font-weight: 500; }
.cs-reply-btn {
    font-size: 0.625rem; font-weight: 700; color: #9CA3AF;
    cursor: pointer; background: none; border: none; padding: 0;
    transition: color 0.15s;
}
.cs-reply-btn:hover { color: #6366F1; }

.cs-replies {
    margin-left: 38px; padding-left: 14px;
    border-left: 2px solid #F0F1F5; margin-top: 10px;
    display: flex; flex-direction: column; gap: 10px;
}

.cs-empty {
    text-align: center; padding: 12px 0;
    font-size: 0.75rem; color: #C5C7D0;
}
</style>
