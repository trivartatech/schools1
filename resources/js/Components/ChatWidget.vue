<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useSchoolStore } from '@/stores/useSchoolStore';

const school = useSchoolStore();
import axios from 'axios';

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

// ── UI state ───────────────────────────────────────────────────────────────
const open       = ref(false);
const view       = ref('list'); // 'list' | 'chat'

// ── Chat state ─────────────────────────────────────────────────────────────
const convList        = ref([]);
const activeConvId    = ref(null);
const messages        = ref([]);
const lastMessageId   = ref(0);
const typers          = ref([]);
const msgInput        = ref('');
const replyTo         = ref(null);
const loadingMsgs     = ref(false);
const sending         = ref(false);
const messagesEl      = ref(null);

let pollTimer     = null;
let convPollTimer = null;

const activeConv = computed(() => convList.value.find(c => c.id === activeConvId.value));
const totalUnread = computed(() => convList.value.reduce((s, c) => s + (c.unread_count || 0), 0));

// ── Load conversations (once on open) ─────────────────────────────────────
async function loadConversations() {
    try {
        const res = await axios.get('/school/chat/conversations/poll');
        convList.value = res.data.conversations || [];
    } catch {}
}

// ── Poll conversation list ─────────────────────────────────────────────────
async function pollConvList() {
    try {
        const res = await axios.get('/school/chat/conversations/poll');
        convList.value = res.data.conversations || [];
    } catch {}
}

// ── Select conversation ────────────────────────────────────────────────────
async function selectConv(id) {
    activeConvId.value = id;
    messages.value = [];
    lastMessageId.value = 0;
    replyTo.value = null;
    view.value = 'chat';

    clearInterval(pollTimer);
    await fetchMessages(id);
    pollTimer = setInterval(() => pollMessages(id), 2000);
}

async function fetchMessages(convId) {
    loadingMsgs.value = true;
    try {
        const res = await axios.get(`/school/chat/conversations/${convId}/messages`);
        messages.value = res.data.messages || [];
        lastMessageId.value = messages.value.length ? messages.value[messages.value.length - 1].id : 0;
        await nextTick();
        scrollBottom();
    } finally {
        loadingMsgs.value = false;
    }
}

async function pollMessages(convId) {
    if (!convId) return;
    try {
        const res = await axios.get(`/school/chat/conversations/${convId}/poll`, {
            params: { after_id: lastMessageId.value }
        });
        if (res.data.messages?.length) {
            messages.value.push(...res.data.messages);
            lastMessageId.value = messages.value[messages.value.length - 1].id;
            await nextTick();
            scrollBottom();
        }
        typers.value = res.data.typers || [];
    } catch {}
}

// ── Send message ───────────────────────────────────────────────────────────
async function sendMessage() {
    const body = msgInput.value.trim();
    if (!body || !activeConvId.value || sending.value) return;

    sending.value = true;
    const formData = new FormData();
    formData.append('type', 'text');
    formData.append('body', body);
    if (replyTo.value) formData.append('reply_to_id', replyTo.value.id);

    try {
        const res = await axios.post(
            `/school/chat/conversations/${activeConvId.value}/messages`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );
        messages.value.push(res.data.message);
        lastMessageId.value = res.data.message.id;
        msgInput.value = '';
        replyTo.value = null;
        await nextTick();
        scrollBottom();
    } catch {} finally {
        sending.value = false;
    }
}

// ── Typing indicator ───────────────────────────────────────────────────────
let typingTimer = null;
function onTyping() {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        if (activeConvId.value) {
            axios.post(`/school/chat/conversations/${activeConvId.value}/typing`).catch(() => {});
        }
    }, 500);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function scrollBottom() {
    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
}

function formatTime(dt) { return school.fmtTime(dt); }

function isMe(msg) {
    return msg.sender_id === authUser.value?.id;
}

const avatarColors = ['#6366f1','#8b5cf6','#0ea5e9','#10b981','#f59e0b','#ef4444','#ec4899'];
function avatarColor(id) {
    return `background:${avatarColors[(id || 0) % avatarColors.length]};color:#fff`;
}

function convTypeIcon(type) {
    if (type === 'broadcast') return '📢';
    if (type === 'group') return '👥';
    return '💬';
}

function convName(conv) {
    if (conv.type === 'direct') {
        const other = conv.members?.find(m => m.user_id !== authUser.value?.id);
        return other?.user?.name || conv.name || 'Chat';
    }
    return conv.name || 'Chat';
}

// ── Lifecycle ──────────────────────────────────────────────────────────────
watch(open, (val) => {
    if (val) {
        loadConversations();
        convPollTimer = setInterval(pollConvList, 5000);
    } else {
        clearInterval(pollTimer);
        clearInterval(convPollTimer);
        pollTimer = null;
        convPollTimer = null;
        view.value = 'list';
        activeConvId.value = null;
        messages.value = [];
    }
});

onMounted(() => {
    // Poll conversations in background for unread count even when closed
    pollConvList();
    convPollTimer = setInterval(pollConvList, 10000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(convPollTimer);
});
</script>

<template>
    <!-- Floating button -->
    <div class="cw-wrap">
        <button class="cw-fab" @click="open = !open" :title="open ? 'Close Chat' : 'Open Chat'">
            <!-- Chat icon when closed -->
            <svg v-if="!open" class="cw-fab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <!-- Close icon when open -->
            <svg v-else class="cw-fab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <!-- Unread badge -->
            <span v-if="totalUnread > 0 && !open" class="cw-unread-badge">
                {{ totalUnread > 99 ? '99+' : totalUnread }}
            </span>
        </button>

        <!-- Popup panel -->
        <transition name="cw-slide">
            <div v-if="open" class="cw-panel">

                <!-- ── CONVERSATION LIST ──────────────────────────────────── -->
                <div v-if="view === 'list'" class="cw-list-view">
                    <div class="cw-header">
                        <span class="cw-header-title">Messages</span>
                        <a href="/school/chat" class="cw-header-link" title="Open full chat">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>

                    <div class="cw-conv-list">
                        <div v-if="!convList.length" class="cw-empty">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:36px;height:36px;color:#cbd5e1;margin-bottom:8px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <p>No conversations yet</p>
                            <a href="/school/chat" style="font-size:12px;color:#6366f1;margin-top:4px;">Start a chat →</a>
                        </div>
                        <button
                            v-for="conv in convList"
                            :key="conv.id"
                            class="cw-conv-row"
                            @click="selectConv(conv.id)"
                        >
                            <div class="cw-conv-avatar" :style="avatarColor(conv.id)">
                                {{ convTypeIcon(conv.type) }}
                            </div>
                            <div class="cw-conv-info">
                                <div class="cw-conv-name">{{ convName(conv) }}</div>
                                <div class="cw-conv-preview">{{ conv.last_message?.body || 'No messages yet' }}</div>
                            </div>
                            <span v-if="conv.unread_count > 0" class="cw-conv-badge">{{ conv.unread_count }}</span>
                        </button>
                    </div>
                </div>

                <!-- ── CHAT VIEW ───────────────────────────────────────────── -->
                <div v-else class="cw-chat-view">
                    <div class="cw-header">
                        <button class="cw-back-btn" @click="view = 'list'; clearInterval(pollTimer);">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:18px;height:18px">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <div class="cw-header-conv-info">
                            <div class="cw-header-conv-name">{{ activeConv ? convName(activeConv) : 'Chat' }}</div>
                            <div v-if="typers.length" class="cw-typers">{{ typers.map(t => t.name).join(', ') }} typing...</div>
                        </div>
                        <a :href="`/school/chat?conv=${activeConvId}`" class="cw-header-link" title="Open full chat">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>

                    <!-- Messages -->
                    <div class="cw-messages" ref="messagesEl">
                        <div v-if="loadingMsgs" class="cw-loading">
                            <div class="cw-spinner"></div>
                        </div>
                        <template v-else>
                            <div v-if="!messages.length" class="cw-empty">
                                <p>No messages yet. Say hello!</p>
                            </div>
                            <div
                                v-for="msg in messages"
                                :key="msg.id"
                                class="cw-msg-row"
                                :class="isMe(msg) ? 'cw-msg-row--me' : 'cw-msg-row--them'"
                            >
                                <div v-if="!isMe(msg)" class="cw-msg-sender-avatar" :style="avatarColor(msg.sender_id)">
                                    {{ msg.sender?.name?.charAt(0) }}
                                </div>
                                <div class="cw-msg-bubble" :class="isMe(msg) ? 'cw-bubble--me' : 'cw-bubble--them'">
                                    <div v-if="!isMe(msg)" class="cw-msg-sender-name">{{ msg.sender?.name }}</div>
                                    <div v-if="msg.deleted_at_for_all" class="cw-msg-deleted">Message deleted</div>
                                    <template v-else>
                                        <div v-if="msg.reply_to" class="cw-msg-reply">
                                            <span>↩ {{ msg.reply_to.sender?.name }}:</span> {{ msg.reply_to.body }}
                                        </div>
                                        <p v-if="msg.type === 'text'" class="cw-msg-body">{{ msg.body }}</p>
                                        <a v-else-if="msg.type === 'image'" :href="`/storage/${msg.attachment_path}`" target="_blank">
                                            <img :src="`/storage/${msg.attachment_path}`" class="cw-msg-img" />
                                        </a>
                                        <a v-else-if="msg.type === 'pdf'" :href="`/storage/${msg.attachment_path}`" target="_blank" class="cw-msg-file">
                                            📄 {{ msg.attachment_name || 'PDF' }}
                                        </a>
                                        <audio v-else-if="msg.type === 'voice'" :src="`/storage/${msg.attachment_path}`" controls class="cw-msg-audio"></audio>
                                    </template>
                                    <div class="cw-msg-meta">
                                        {{ formatTime(msg.created_at) }}
                                        <span v-if="msg.edited_at" class="cw-edited">· edited</span>
                                    </div>
                                </div>
                                <button v-if="!msg.deleted_at_for_all" class="cw-reply-btn" @click="replyTo = msg" title="Reply">↩</button>
                            </div>
                        </template>
                    </div>

                    <!-- Reply banner -->
                    <div v-if="replyTo" class="cw-reply-banner">
                        <div class="cw-reply-banner-body">
                            <span class="cw-reply-banner-name">↩ {{ replyTo.sender?.name }}</span>
                            <span class="cw-reply-banner-text">{{ replyTo.body }}</span>
                        </div>
                        <button @click="replyTo = null" class="cw-reply-banner-close">×</button>
                    </div>

                    <!-- Input -->
                    <div class="cw-input-row">
                        <textarea
                            v-model="msgInput"
                            @input="onTyping"
                            @keydown.enter.exact.prevent="sendMessage"
                            rows="1"
                            class="cw-input"
                            placeholder="Type a message..."
                        ></textarea>
                        <button @click="sendMessage" :disabled="!msgInput.trim() || sending" class="cw-send-btn">
                            <svg v-if="!sending" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:18px;height:18px">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <div v-else class="cw-spinner cw-spinner--sm"></div>
                        </button>
                    </div>
                </div>

            </div>
        </transition>
    </div>
</template>

<style scoped>
/* ── Wrapper ──────────────────────────────────────────────────────────────── */
.cw-wrap {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}

/* ── FAB button ───────────────────────────────────────────────────────────── */
.cw-fab {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(99,102,241,0.4);
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
    flex-shrink: 0;
}
.cw-fab:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(99,102,241,0.5); }
.cw-fab-icon { width: 24px; height: 24px; color: #fff; }
.cw-unread-badge {
    position: absolute;
    top: -4px; right: -4px;
    min-width: 20px; height: 20px;
    background: #ef4444;
    color: #fff;
    font-size: 11px; font-weight: 700;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 4px;
    border: 2px solid #fff;
    line-height: 1;
}

/* ── Panel ────────────────────────────────────────────────────────────────── */
.cw-panel {
    width: 340px;
    height: 480px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.18);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.cw-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #fff;
    flex-shrink: 0;
}
.cw-header-title { font-weight: 700; font-size: 15px; flex: 1; }
.cw-header-link {
    color: rgba(255,255,255,0.8);
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    border-radius: 6px;
    transition: background 0.15s;
    text-decoration: none;
}
.cw-header-link:hover { background: rgba(255,255,255,0.15); color: #fff; }
.cw-back-btn {
    color: rgba(255,255,255,0.85);
    background: none; border: none; cursor: pointer;
    display: flex; align-items: center;
    padding: 2px; border-radius: 4px;
    transition: background 0.15s;
}
.cw-back-btn:hover { background: rgba(255,255,255,0.15); }
.cw-header-conv-info { flex: 1; min-width: 0; }
.cw-header-conv-name { font-weight: 700; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cw-typers { font-size: 11px; color: rgba(255,255,255,0.75); margin-top: 1px; }

/* ── Conversation list ────────────────────────────────────────────────────── */
.cw-list-view { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
.cw-conv-list { flex: 1; overflow-y: auto; }
.cw-conv-row {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.12s;
    border-bottom: 1px solid #f1f5f9;
}
.cw-conv-row:hover { background: #f8fafc; }
.cw-conv-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.cw-conv-info { flex: 1; min-width: 0; }
.cw-conv-name { font-size: 13px; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cw-conv-preview { font-size: 11px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 2px; }
.cw-conv-badge {
    min-width: 18px; height: 18px;
    background: #6366f1; color: #fff;
    font-size: 10px; font-weight: 700;
    border-radius: 9px; padding: 0 4px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ── Chat view ────────────────────────────────────────────────────────────── */
.cw-chat-view { display: flex; flex-direction: column; flex: 1; overflow: hidden; }

/* ── Messages ─────────────────────────────────────────────────────────────── */
.cw-messages {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #f8fafc;
}
.cw-msg-row {
    display: flex;
    align-items: flex-end;
    gap: 6px;
}
.cw-msg-row--me { flex-direction: row-reverse; }
.cw-msg-sender-avatar {
    width: 28px; height: 28px;
    border-radius: 50%;
    font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.cw-msg-bubble {
    max-width: 72%;
    padding: 8px 10px;
    border-radius: 12px;
    position: relative;
}
.cw-bubble--me {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.cw-bubble--them {
    background: #fff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 4px;
}
.cw-msg-sender-name { font-size: 10px; font-weight: 700; color: #6366f1; margin-bottom: 2px; }
.cw-bubble--me .cw-msg-sender-name { color: rgba(255,255,255,0.8); }
.cw-msg-body { font-size: 13px; line-height: 1.4; margin: 0; white-space: pre-wrap; word-break: break-word; }
.cw-msg-deleted { font-size: 12px; color: #94a3b8; font-style: italic; }
.cw-msg-reply {
    font-size: 11px; color: #94a3b8;
    border-left: 2px solid #6366f1;
    padding-left: 6px; margin-bottom: 4px;
}
.cw-bubble--me .cw-msg-reply { color: rgba(255,255,255,0.7); border-color: rgba(255,255,255,0.5); }
.cw-msg-img { max-width: 100%; border-radius: 8px; }
.cw-msg-file { font-size: 12px; color: #6366f1; text-decoration: none; }
.cw-msg-audio { width: 100%; height: 32px; }
.cw-msg-meta {
    font-size: 10px;
    color: rgba(255,255,255,0.65);
    margin-top: 3px;
    text-align: right;
}
.cw-bubble--them .cw-msg-meta { color: #94a3b8; }
.cw-edited { font-style: italic; }
.cw-reply-btn {
    background: none; border: none;
    font-size: 14px; cursor: pointer;
    color: #94a3b8; padding: 2px 4px;
    opacity: 0;
    transition: opacity 0.15s;
}
.cw-msg-row:hover .cw-reply-btn { opacity: 1; }

/* ── Reply banner ─────────────────────────────────────────────────────────── */
.cw-reply-banner {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 12px;
    background: #ede9fe;
    border-top: 1px solid #ddd6fe;
    flex-shrink: 0;
}
.cw-reply-banner-body { flex: 1; min-width: 0; }
.cw-reply-banner-name { font-size: 11px; font-weight: 700; color: #6366f1; margin-right: 4px; }
.cw-reply-banner-text { font-size: 11px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cw-reply-banner-close {
    background: none; border: none; cursor: pointer;
    font-size: 16px; color: #94a3b8; line-height: 1;
}

/* ── Input ────────────────────────────────────────────────────────────────── */
.cw-input-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 10px 12px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
    flex-shrink: 0;
}
.cw-input {
    flex: 1;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 8px 14px;
    font-size: 13px;
    resize: none;
    outline: none;
    max-height: 80px;
    overflow-y: auto;
    line-height: 1.4;
    font-family: inherit;
    transition: border-color 0.15s;
}
.cw-input:focus { border-color: #6366f1; }
.cw-send-btn {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #6366f1;
    border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0;
    transition: background 0.15s, transform 0.1s;
}
.cw-send-btn:hover:not(:disabled) { background: #4f46e5; transform: scale(1.05); }
.cw-send-btn:disabled { background: #c7d2fe; cursor: not-allowed; }

/* ── Empty / Loading ──────────────────────────────────────────────────────── */
.cw-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    height: 100%; color: #94a3b8;
    font-size: 13px; text-align: center;
    padding: 20px;
}
.cw-loading {
    display: flex; align-items: center; justify-content: center;
    height: 100%;
}
.cw-spinner {
    width: 28px; height: 28px;
    border: 3px solid #e2e8f0;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: cw-spin 0.7s linear infinite;
}
.cw-spinner--sm { width: 16px; height: 16px; border-width: 2px; }
@keyframes cw-spin { to { transform: rotate(360deg); } }

/* ── Slide transition ─────────────────────────────────────────────────────── */
.cw-slide-enter-active { animation: cw-slide-in 0.22s cubic-bezier(.4,0,.2,1); }
.cw-slide-leave-active { animation: cw-slide-out 0.18s cubic-bezier(.4,0,.2,1); }
@keyframes cw-slide-in {
    from { opacity: 0; transform: translateY(16px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes cw-slide-out {
    from { opacity: 1; transform: translateY(0) scale(1); }
    to   { opacity: 0; transform: translateY(16px) scale(0.97); }
}
</style>
