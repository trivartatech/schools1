<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import axios from 'axios';
import { useSchoolStore } from '@/stores/useSchoolStore';
import { useConfirm } from '@/Composables/useConfirm';

const school = useSchoolStore();
const confirm = useConfirm();

const props = defineProps({
    conversations:   { type: Array, default: () => [] },
    available_users: { type: Array, default: () => [] },
    sections:        { type: Array, default: () => [] },
    active_id:       { type: Number, default: 0 },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

// ── State ──────────────────────────────────────────────────────────────────
const convList       = ref([...props.conversations]);
const activeConvId   = ref(props.active_id || null);
const messages       = ref([]);
const hasMore        = ref(false);
const loadingMessages = ref(false);
const lastMessageId  = ref(0);
const typers         = ref([]);
const pinnedMessages = ref([]);
const showPinned     = ref(false);
const showSearch     = ref(false);
const searchQuery    = ref('');
const searchResults  = ref([]);
const showNewChat    = ref(false);
const showGroupForm  = ref(false);
const showBroadcast  = ref(false);
const showMembers    = ref(false);
const msgInput       = ref('');
const attachFile     = ref(null);
const replyTo        = ref(null);
const editingMsg     = ref(null);
const editBody       = ref('');
const messagesEl     = ref(null);
const inputEl        = ref(null);
const fileEl         = ref(null);

// Is the current user privileged (can interact in any conversation)
const isPrivileged = computed(() => {
    const t = authUser.value?.user_type;
    return ['super_admin', 'admin', 'school_admin', 'principal'].includes(t);
});

// New group form
const groupName  = ref('');
const groupUsers = ref([]);
const bcastName  = ref('');
const bcastUsers = ref([]);

let pollTimer    = null;
let convPollTimer = null;

// ── Active conversation ────────────────────────────────────────────────────
const activeConv = computed(() => convList.value.find(c => c.id === activeConvId.value));

// ── Select conversation ────────────────────────────────────────────────────
async function selectConv(id) {
    activeConvId.value = id;
    messages.value = [];
    lastMessageId.value = 0;
    replyTo.value = null;
    editingMsg.value = null;
    showPinned.value = false;
    showSearch.value = false;

    if (id) {
        await loadMessages(id);
        startPolling(id);
    }
}

async function loadMessages(convId) {
    loadingMessages.value = true;
    try {
        const res = await axios.get(`/school/chat/conversations/${convId}/messages`);
        messages.value = res.data.messages;
        hasMore.value   = res.data.has_more;
        lastMessageId.value = messages.value.length ? messages.value[messages.value.length - 1].id : 0;
        await nextTick();
        scrollBottom();
    } finally {
        loadingMessages.value = false;
    }
}

async function loadOlder() {
    if (!hasMore.value || !activeConvId.value || !messages.value.length) return;
    const firstId = messages.value[0].id;
    const res = await axios.get(`/school/chat/conversations/${activeConvId.value}/messages`, {
        params: { before_id: firstId }
    });
    messages.value = [...res.data.messages, ...messages.value];
    hasMore.value = res.data.has_more;
}

// ── Polling ────────────────────────────────────────────────────────────────
function startPolling(convId) {
    clearInterval(pollTimer);
    pollTimer = setInterval(() => pollMessages(convId), 2000);
}

async function pollMessages(convId) {
    if (!convId) return;
    try {
        const res = await axios.get(`/school/chat/conversations/${convId}/poll`, {
            params: { after_id: lastMessageId.value }
        });
        if (res.data.messages.length) {
            const newMsgs = res.data.messages;
            messages.value.push(...newMsgs);
            lastMessageId.value = messages.value[messages.value.length - 1].id;
            await nextTick();
            scrollBottom();
            // Notify for incoming messages
            newMsgs.forEach(m => {
                if (m.sender_id !== authUser.value?.id) {
                    playBeep();
                    pushNotification(m.sender?.name ?? 'New message', m.body ?? '📎 Attachment');
                }
            });
        }
        typers.value = res.data.typers || [];
    } catch {}
}

// Poll conversation list for badge updates
async function pollConvList() {
    try {
        const res = await axios.get('/school/chat/conversations/poll');
        convList.value = res.data.conversations;
    } catch {}
}

function scrollBottom() {
    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
}

// ── Send message ───────────────────────────────────────────────────────────
async function sendMessage() {
    if (!activeConvId.value) return;
    const body = msgInput.value.trim();
    if (!body && !attachFile.value) return;

    const formData = new FormData();
    formData.append('type', attachFile.value ? detectType(attachFile.value) : 'text');
    if (body) formData.append('body', body);
    if (attachFile.value) formData.append('attachment', attachFile.value);
    if (replyTo.value) formData.append('reply_to_id', replyTo.value.id);

    try {
        const res = await axios.post(`/school/chat/conversations/${activeConvId.value}/messages`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        messages.value.push(res.data.message);
        lastMessageId.value = res.data.message.id;
        msgInput.value = '';
        attachFile.value = null;
        replyTo.value = null;
        if (fileEl.value) fileEl.value.value = '';
        await nextTick();
        scrollBottom();
    } catch {}
}

function detectType(file) {
    if (file.type.startsWith('image/')) return 'image';
    if (file.type === 'application/pdf') return 'pdf';
    if (file.type.startsWith('audio/')) return 'voice';
    return 'text';
}

// ── Typing indicator ───────────────────────────────────────────────────────
let typingTimer;
async function onTyping() {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(async () => {
        if (activeConvId.value) {
            await axios.post(`/school/chat/conversations/${activeConvId.value}/typing`).catch(() => {});
        }
    }, 500);
}

// ── Edit message ───────────────────────────────────────────────────────────
function startEdit(msg) {
    editingMsg.value = msg;
    editBody.value = msg.body;
}

async function submitEdit() {
    if (!editingMsg.value) return;
    await axios.patch(`/school/chat/messages/${editingMsg.value.id}`, { body: editBody.value });
    const idx = messages.value.findIndex(m => m.id === editingMsg.value.id);
    if (idx !== -1) {
        messages.value[idx].body = editBody.value;
        messages.value[idx].edited_at = new Date().toISOString();
    }
    editingMsg.value = null;
    editBody.value = '';
}

// ── Delete message ─────────────────────────────────────────────────────────
async function deleteMsg(msg) {
    const ok = await confirm({
        title: 'Delete message?',
        message: 'Delete this message for everyone?',
        confirmLabel: 'Delete',
        danger: true,
    });
    if (!ok) return;
    await axios.delete(`/school/chat/messages/${msg.id}`);
    const idx = messages.value.findIndex(m => m.id === msg.id);
    if (idx !== -1) messages.value[idx].deleted_at_for_all = new Date().toISOString();
}

// ── Pin message ────────────────────────────────────────────────────────────
async function pinMsg(msg) {
    const res = await axios.patch(`/school/chat/messages/${msg.id}/pin`);
    const idx = messages.value.findIndex(m => m.id === msg.id);
    if (idx !== -1) messages.value[idx].is_pinned = res.data.is_pinned;
}

// ── Pinned messages ────────────────────────────────────────────────────────
async function togglePinned() {
    showPinned.value = !showPinned.value;
    if (showPinned.value && activeConvId.value) {
        const res = await axios.get(`/school/chat/conversations/${activeConvId.value}/pinned`);
        pinnedMessages.value = res.data.pinned;
    }
}

// ── Search ─────────────────────────────────────────────────────────────────
async function doSearch() {
    if (!searchQuery.value || !activeConvId.value) return;
    const res = await axios.get(`/school/chat/conversations/${activeConvId.value}/search`, {
        params: { q: searchQuery.value }
    });
    searchResults.value = res.data.results;
}

// ── Start DM ───────────────────────────────────────────────────────────────
async function startDirect(userId) {
    const res = await axios.post('/school/chat/direct', { user_id: userId });
    showNewChat.value = false;
    await pollConvList();
    selectConv(res.data.conversation_id);
}

// ── Create group ───────────────────────────────────────────────────────────
async function createGroup() {
    if (!groupName.value || !groupUsers.value.length) return;
    const res = await axios.post('/school/chat/groups', {
        name: groupName.value,
        user_ids: groupUsers.value,
    });
    showGroupForm.value = false;
    groupName.value = '';
    groupUsers.value = [];
    await pollConvList();
    selectConv(res.data.conversation_id);
}

// ── Create broadcast ───────────────────────────────────────────────────────
async function createBroadcast() {
    if (!bcastName.value || !bcastUsers.value.length) return;
    const res = await axios.post('/school/chat/broadcasts', {
        name: bcastName.value,
        user_ids: bcastUsers.value,
    });
    showBroadcast.value = false;
    bcastName.value = '';
    bcastUsers.value = [];
    await pollConvList();
    selectConv(res.data.conversation_id);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function formatTime(dt) { return school.fmtTime(dt); }
function formatDate(dt) { return school.fmtDate(dt); }

function isMe(msg) {
    return msg.sender_id === authUser.value?.id;
}

function canDelete(msg) {
    const t = authUser.value?.user_type;
    const isAdminUser = ['super_admin', 'admin', 'school_admin', 'principal'].includes(t);
    return msg.sender_id === authUser.value?.id || isAdminUser;
}

function canPin() {
    const t = authUser.value?.user_type;
    return ['super_admin', 'admin', 'school_admin', 'principal', 'teacher'].includes(t);
}

function convTypeIcon(type) {
    if (type === 'broadcast') return '📢';
    if (type === 'group') return '👥';
    return '💬';
}

// Members grouped by role for the members panel
const membersByRole = computed(() => {
    const members = activeConv.value?.members || [];
    const admins  = members.filter(m => m.role === 'admin');
    const regular = members.filter(m => m.role !== 'admin');
    const groups  = [];
    if (admins.length)  groups.push({ role: 'admin',  label: '👑 Admins / Teachers', members: admins });
    if (regular.length) groups.push({ role: 'member', label: '👤 Members', members: regular });
    return groups;
});

const avatarColors = ['#6366f1','#8b5cf6','#0ea5e9','#10b981','#f59e0b','#ef4444','#ec4899','#14b8a6'];
function avatarColor(id) {
    const bg = avatarColors[id % avatarColors.length];
    return `background:${bg};color:#fff;`;
}

const totalUnread = computed(() => convList.value.reduce((s, c) => s + (c.unread_count || 0), 0));

// ── Push Notifications + Sound ──────────────────────────────────────────────
let audioCtx = null;

function playBeep() {
    try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
        osc.start(audioCtx.currentTime);
        osc.stop(audioCtx.currentTime + 0.3);
    } catch {}
}

async function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        await Notification.requestPermission();
    }
}

function pushNotification(title, body) {
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;
    if (document.hasFocus()) return; // only notify when tab not focused
    try {
        const n = new Notification(`💬 ${title}`, {
            body: body.length > 80 ? body.substring(0, 80) + '...' : body,
            icon: '/favicon.ico',
            tag: 'school-chat',
            silent: true, // we handle sound ourselves
        });
        setTimeout(() => n.close(), 4000);
    } catch {}
}

// ── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(async () => {
    await requestNotificationPermission();
    if (props.active_id) {
        await selectConv(props.active_id);
    }
    convPollTimer = setInterval(pollConvList, 5000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(convPollTimer);
});
</script>

<template>
    <SchoolLayout :title="'Chat' + (totalUnread > 0 ? ` (${totalUnread})` : '')">

        <div class="chat-shell">

            <!-- ══ SIDEBAR ══ -->
            <aside class="chat-sidebar">
                <!-- Sidebar Header -->
                <div class="cs-header">
                    <div class="cs-title">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <span>Messages</span>
                        <span v-if="totalUnread > 0" class="unread-badge">{{ totalUnread }}</span>
                    </div>
                    <div class="cs-actions">
                        <button @click="showNewChat = !showNewChat" class="cs-btn" title="New Direct Message">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <div class="relative">
                            <button @click="showGroupForm = !showGroupForm" class="cs-btn" title="Create Group">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </button>
                        </div>
                        <button v-if="authUser?.user_type === 'admin' || authUser?.user_type === 'school_admin' || authUser?.user_type === 'principal'" @click="showBroadcast = !showBroadcast" class="cs-btn" title="Broadcast">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- New DM panel -->
                <div v-if="showNewChat" class="cs-new-panel">
                    <p class="cs-panel-label">Start a conversation</p>
                    <div class="cs-user-list">
                        <button v-for="u in available_users" :key="u.id" @click="startDirect(u.id)" class="cs-user-item">
                            <div class="cs-avatar" :class="'bg-' + (u.id % 5 === 0 ? 'indigo' : u.id % 4 === 0 ? 'rose' : u.id % 3 === 0 ? 'emerald' : u.id % 2 === 0 ? 'amber' : 'violet') + '-500'">
                                {{ u.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <p class="cs-user-name">{{ u.name }}</p>
                                <p class="cs-user-type">{{ u.user_type }}</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Create Group panel -->
                <div v-if="showGroupForm" class="cs-new-panel">
                    <p class="cs-panel-label">Create Group</p>
                    <input v-model="groupName" placeholder="Group name..." class="cs-input" />
                    <div class="cs-user-check-list">
                        <label v-for="u in available_users" :key="u.id" class="cs-check-item">
                            <input type="checkbox" :value="u.id" v-model="groupUsers" />
                            <span>{{ u.name }}</span>
                        </label>
                    </div>
                    <button @click="createGroup" class="cs-create-btn">Create Group</button>
                </div>

                <!-- Create Broadcast panel -->
                <div v-if="showBroadcast" class="cs-new-panel">
                    <p class="cs-panel-label">Create Broadcast Channel</p>
                    <input v-model="bcastName" placeholder="Channel name..." class="cs-input" />
                    <div class="cs-user-check-list">
                        <label v-for="u in available_users" :key="u.id" class="cs-check-item">
                            <input type="checkbox" :value="u.id" v-model="bcastUsers" />
                            <span>{{ u.name }}</span>
                        </label>
                    </div>
                    <button @click="createBroadcast" class="cs-create-btn">Create Broadcast</button>
                </div>

                <!-- Conversation List -->
                <div class="cs-conv-list">
                    <div v-if="convList.length === 0" class="cs-empty">
                        <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <p>No conversations yet</p>
                    </div>
                    <button v-for="conv in convList" :key="conv.id" @click="selectConv(conv.id)"
                        class="cs-conv-item" :class="{ 'cs-conv-item--active': activeConvId === conv.id }">
                        <!-- Avatar with type gradient -->
                        <div class="cs-conv-avatar"
                            :style="conv.type === 'broadcast' ? 'background: linear-gradient(135deg,#f59e0b,#ef4444)' : conv.type === 'group' ? 'background: linear-gradient(135deg,#6366f1,#8b5cf6)' : 'background: linear-gradient(135deg,#0ea5e9,#6366f1)'">
                            <span class="text-base">{{ convTypeIcon(conv.type) }}</span>
                        </div>
                        <div class="cs-conv-info">
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="cs-conv-name">{{ conv.display_name }}</span>
                                <!-- Section badge -->
                                <span v-if="conv.group_type === 'section_group'" class="role-badge section">Section</span>
                                <span v-else-if="conv.type === 'broadcast'" class="role-badge broadcast">📢 Broadcast</span>
                                <span v-else-if="conv.type === 'group'" class="role-badge group">Group</span>
                            </div>
                            <!-- Role tag for my role in this conv -->
                            <div class="flex items-center gap-1 mt-0.5">
                                <span v-if="conv.my_role === 'admin'" class="role-tag admin">Admin</span>
                                <span v-else class="role-tag member">Member</span>
                                <span class="cs-conv-preview flex-1">{{ conv.latest_message?.body || 'No messages yet' }}</span>
                            </div>
                        </div>
                        <div class="cs-conv-meta">
                            <span class="cs-conv-time">{{ formatTime(conv.latest_message?.created_at) }}</span>
                            <span v-if="conv.unread_count > 0" class="cs-unread">{{ conv.unread_count }}</span>
                        </div>
                    </button>
                </div>
            </aside>

            <!-- ══ MAIN CHAT AREA ══ -->
            <div class="chat-main" v-if="activeConv">

                <!-- Chat Header -->
                <div class="cm-header">
                    <div class="flex items-center gap-3">
                        <div class="cm-avatar"
                            :style="activeConv.type === 'broadcast' ? 'background:linear-gradient(135deg,#f59e0b,#ef4444)' : activeConv.type === 'group' ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6)' : 'background:linear-gradient(135deg,#0ea5e9,#6366f1)'">
                            {{ convTypeIcon(activeConv.type) }}
                        </div>
                        <div>
                            <p class="cm-name">{{ activeConv.display_name }}
                                <span v-if="activeConv.group_type === 'section_group'" class="role-badge section ml-1">Section</span>
                                <span v-else-if="activeConv.type === 'broadcast'" class="role-badge broadcast ml-1">📢 Broadcast</span>
                            </p>
                            <p class="cm-sub" @click="showMembers = !showMembers; showSearch = false; showPinned = false" style="cursor:pointer">
                                <span v-if="activeConv.type === 'direct'">Direct Message</span>
                                <span v-else>
                                    <span class="font-semibold text-indigo-500">{{ activeConv.members?.length }}</span> members
                                    <span class="text-indigo-400 ml-1">• click to view</span>
                                </span>
                                <span v-if="typers.length > 0" class="typing-indicator">
                                    · {{ typers.map(t => t.name).join(', ') }} typing...
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="cm-header-actions">
                        <!-- Members list -->
                        <button v-if="activeConv.type !== 'direct'" @click="showMembers = !showMembers; showSearch = false; showPinned = false"
                            class="cm-action-btn" :class="{ 'cm-action-btn--active': showMembers }" title="Members">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span v-if="activeConv.members?.length" class="members-count">{{ activeConv.members.length }}</span>
                        </button>
                        <!-- Search -->
                        <button @click="showSearch = !showSearch; showPinned = false; showMembers = false" class="cm-action-btn" :class="{ 'cm-action-btn--active': showSearch }" title="Search">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                        <!-- Pinned -->
                        <button @click="togglePinned(); showSearch = false; showMembers = false" class="cm-action-btn" :class="{ 'cm-action-btn--active': showPinned }" title="Pinned">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Search Panel -->
                <div v-if="showSearch" class="cm-search-panel">
                    <div class="flex gap-2">
                        <input v-model="searchQuery" @input="doSearch" placeholder="Search messages..." class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-indigo-400 outline-none" />
                    </div>
                    <div class="mt-2 space-y-1 max-h-40 overflow-y-auto">
                        <div v-for="r in searchResults" :key="r.id" class="text-sm text-slate-600 p-2 bg-slate-50 rounded-lg">
                            <span class="font-semibold text-indigo-600">{{ r.sender?.name }}</span>: {{ r.body }}
                            <span class="text-xs text-slate-400 ml-2">{{ formatDate(r.created_at) }}</span>
                        </div>
                        <div v-if="searchResults.length === 0 && searchQuery" class="text-sm text-slate-400 text-center py-2">No results found</div>
                    </div>
                </div>

                <!-- Pinned Panel -->
                <div v-if="showPinned" class="cm-pinned-panel">
                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-2">📌 Pinned Messages</p>
                    <div v-if="pinnedMessages.length === 0" class="text-sm text-slate-400">No pinned messages</div>
                    <div v-for="p in pinnedMessages" :key="p.id" class="pinned-item">
                        <span class="font-semibold text-slate-700">{{ p.sender?.name }}:</span>
                        <span class="text-slate-600">{{ p.body }}</span>
                    </div>
                </div>

                <!-- ══ MEMBERS PANEL (right drawer) ══ -->
                <transition name="slide-members">
                <div v-if="showMembers && activeConv.type !== 'direct'" class="members-drawer">
                    <div class="members-drawer-header">
                        <span>👥 Members <span class="members-count-badge">{{ activeConv.members?.length }}</span></span>
                        <button @click="showMembers = false" class="members-close">×</button>
                    </div>
                    <div class="members-drawer-body">
                        <!-- Group by role -->
                        <div v-for="roleGroup in membersByRole" :key="roleGroup.role" class="members-role-group">
                            <p class="members-role-label">{{ roleGroup.label }} ({{ roleGroup.members.length }})</p>
                            <div v-for="m in roleGroup.members" :key="m.id" class="member-row">
                                <div class="member-avatar" :style="avatarColor(m.id)">
                                    {{ m.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div class="member-info">
                                    <p class="member-name">{{ m.name }}</p>
                                    <p class="member-type">{{ m.user_type?.replace('_', ' ') }}</p>
                                </div>
                                <div class="member-badges">
                                    <span class="member-role-tag" :class="m.role">
                                        {{ m.role === 'admin' ? '👑 Admin' : 'Member' }}
                                    </span>
                                </div>
                                <!-- Start DM button -->
                                <button v-if="m.id !== authUser?.id" @click="startDirect(m.id); showMembers = false"
                                    class="member-dm-btn" title="Send Direct Message">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="!activeConv.members?.length" class="members-empty">No members found</div>
                    </div>
                </div>
                </transition>

                <!-- Load More -->
                <div v-if="hasMore" class="text-center py-2">
                    <button @click="loadOlder" class="text-xs text-indigo-500 hover:text-indigo-700 font-semibold">Load older messages</button>
                </div>

                <!-- Messages Area -->
                <div class="cm-messages" ref="messagesEl">
                    <div v-if="loadingMessages" class="flex items-center justify-center h-full">
                        <div class="loading-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>

                    <div v-else class="cm-messages-inner">
                        <div v-for="msg in messages" :key="msg.id" class="msg-row" :class="{ 'msg-row--me': isMe(msg) }">
                            <!-- Deleted -->
                            <div v-if="msg.deleted_at_for_all" class="msg-deleted">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/></svg>
                                This message was deleted
                            </div>

                            <template v-else>
                                <!-- Avatar (for others) -->
                                <div v-if="!isMe(msg)" class="msg-avatar">{{ msg.sender?.name?.charAt(0) }}</div>

                                <div class="msg-content" :class="{ 'msg-content--me': isMe(msg) }">
                                    <!-- Sender name (group/broadcast) -->
                                    <p v-if="!isMe(msg) && activeConv.type !== 'direct'" class="msg-sender">{{ msg.sender?.name }}</p>

                                    <!-- Reply context -->
                                    <div v-if="msg.reply_to" class="msg-reply-ctx">
                                        <span class="font-semibold">{{ msg.reply_to.sender?.name }}:</span>
                                        {{ msg.reply_to.body?.substring(0, 60) }}{{ msg.reply_to.body?.length > 60 ? '...' : '' }}
                                    </div>

                                    <!-- Message bubble -->
                                    <div class="msg-bubble" :class="isMe(msg) ? 'msg-bubble--me' : 'msg-bubble--other'">
                                        <!-- Image attachment -->
                                        <img v-if="msg.type === 'image' && msg.attachment_url" :src="msg.attachment_url" class="msg-image" />

                                        <!-- PDF attachment -->
                                        <a v-else-if="msg.type === 'pdf' && msg.attachment_url" :href="msg.attachment_url" target="_blank" class="msg-file">
                                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            <span>{{ msg.attachment_name }}</span>
                                        </a>

                                        <!-- Voice -->
                                        <audio v-else-if="msg.type === 'voice' && msg.attachment_url" :src="msg.attachment_url" controls class="msg-audio" />

                                        <!-- Text -->
                                        <p v-if="msg.body" class="msg-text">{{ msg.body }}</p>

                                        <!-- Edited indicator -->
                                        <span v-if="msg.edited_at" class="msg-edited">edited</span>
                                    </div>

                                    <!-- Message meta row -->
                                    <div class="msg-meta" :class="isMe(msg) ? 'justify-end' : 'justify-start'">
                                        <span class="msg-time">{{ formatTime(msg.created_at) }}</span>

                                        <!-- Read receipts for sent messages -->
                                        <span v-if="isMe(msg) && msg.read_by?.length > 0" class="msg-read-tick" title="Read">✓✓</span>
                                        <span v-else-if="isMe(msg)" class="msg-sent-tick" title="Sent">✓</span>

                                        <!-- Pinned indicator -->
                                        <span v-if="msg.is_pinned" class="text-amber-500 text-xs">📌</span>
                                    </div>

                                    <!-- Action buttons (hover) -->
                                    <div class="msg-actions" :class="isMe(msg) ? 'msg-actions--me' : ''">
                                        <button @click="replyTo = msg" class="msg-act" title="Reply">↩</button>
                                        <button v-if="isMe(msg) && msg.type === 'text'" @click="startEdit(msg)" class="msg-act" title="Edit">✎</button>
                                        <button v-if="canPin()" @click="pinMsg(msg)" class="msg-act" title="Pin">📌</button>
                                        <button v-if="canDelete(msg)" @click="deleteMsg(msg)" class="msg-act msg-act--del" title="Delete">🗑</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Typing indicator -->
                        <div v-if="typers.length > 0" class="msg-row">
                            <div class="msg-avatar">?</div>
                            <div class="msg-bubble msg-bubble--other typing-bubble">
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Bar -->
                <div class="cm-input-bar">
                    <!-- Reply Context -->
                    <div v-if="replyTo" class="input-reply-ctx">
                        <svg class="w-3 h-3 text-indigo-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        <span class="text-xs text-slate-600 flex-1 truncate">Replying to <strong>{{ replyTo.sender?.name }}</strong>: {{ replyTo.body }}</span>
                        <button @click="replyTo = null" class="text-slate-400 hover:text-slate-600 text-lg leading-none">×</button>
                    </div>

                    <!-- Edit Context -->
                    <div v-if="editingMsg" class="input-reply-ctx bg-amber-50 border-amber-200">
                        <span class="text-xs text-amber-700 flex-1">Editing message</span>
                        <button @click="editingMsg = null; editBody = ''" class="text-amber-400 hover:text-amber-700 text-lg leading-none">×</button>
                    </div>

                    <!-- Attachment Preview -->
                    <div v-if="attachFile" class="input-attachment">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        <span class="text-xs text-slate-600 flex-1 truncate">{{ attachFile.name }}</span>
                        <button @click="attachFile = null; fileEl.value = ''" class="text-slate-400 hover:text-rose-500 text-sm">×</button>
                    </div>

                    <div class="input-row">
                        <!-- Attach button -->
                        <button @click="$refs.fileEl.click()" class="input-btn" title="Attach file">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </button>
                        <input ref="fileEl" type="file" class="hidden" accept="image/*,application/pdf,audio/*" @change="attachFile = $event.target.files[0]" />

                        <!-- Text input (edit mode or normal) -->
                        <textarea v-if="editingMsg" v-model="editBody" rows="1"
                            placeholder="Edit message..." class="input-textarea"
                            @keydown.enter.exact.prevent="submitEdit" />
                        <textarea v-else v-model="msgInput" rows="1" ref="inputEl"
                            placeholder="Type a message... (Enter to send)" class="input-textarea"
                            @keydown.enter.exact.prevent="sendMessage"
                            @input="onTyping" />

                        <!-- Send/Save button -->
                        <button v-if="editingMsg" @click="submitEdit" class="input-send-btn">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </button>
                        <button v-else @click="sendMessage" class="input-send-btn" :disabled="!msgInput.trim() && !attachFile">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="chat-empty">
                <div class="chat-empty-inner">
                    <div class="chat-empty-icon">💬</div>
                    <h2 class="text-xl font-bold text-slate-700 mb-2">School Chat</h2>
                    <p class="text-slate-400">Select a conversation or start a new one</p>
                </div>
            </div>
        </div>

    </SchoolLayout>
</template>

<style scoped>
@reference "tailwindcss";

/* ── Shell ── */
.chat-shell {
    display: flex;
    height: calc(100vh - 56px);
    overflow: hidden;
    background: #f0f4f8;
    margin: -24px -28px;
    border-radius: 0;
}

/* ── Sidebar ── */
.chat-sidebar {
    width: 300px;
    min-width: 300px;
    background: #fff;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #e2e8f0;
    overflow: hidden;
}

.cs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.cs-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
}

.cs-actions { display: flex; gap: 4px; }

.cs-btn {
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    color: #64748b;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
}
.cs-btn:hover { background: #f1f5f9; color: #1169cd; }

.unread-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #ef4444;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
}

.cs-new-panel {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    background: #f8faff;
    max-height: 280px;
    overflow-y: auto;
}

.cs-panel-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin-bottom: 8px;
}

.cs-input {
    width: 100%;
    padding: 7px 10px;
    font-size: 0.8rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 8px;
    outline: none;
}
.cs-input:focus { border-color: #6366f1; }

.cs-user-list { display: flex; flex-direction: column; gap: 2px; }

.cs-user-item {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 8px;
    border-radius: 8px;
    width: 100%;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.12s;
}
.cs-user-item:hover { background: #eff6ff; }

.cs-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; color: #fff;
    flex-shrink: 0;
}
.bg-indigo-500 { background: #6366f1; }
.bg-rose-500 { background: #f43f5e; }
.bg-emerald-500 { background: #10b981; }
.bg-amber-500 { background: #f59e0b; }
.bg-violet-500 { background: #8b5cf6; }

.cs-user-name { font-size: 0.8rem; font-weight: 600; color: #1e293b; line-height: 1.2; }
.cs-user-type { font-size: 0.7rem; color: #94a3b8; text-transform: capitalize; }

.cs-user-check-list { display: flex; flex-direction: column; gap: 4px; margin-bottom: 8px; max-height: 140px; overflow-y: auto; }
.cs-check-item { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: #374151; cursor: pointer; padding: 3px 0; }

.cs-create-btn {
    width: 100%;
    padding: 7px;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.cs-create-btn:hover { background: #4f46e5; }

.cs-conv-list { flex: 1; overflow-y: auto; padding: 8px 0; }

.cs-empty { text-align: center; padding: 32px 16px; font-size: 0.8rem; color: #94a3b8; }

.cs-conv-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    width: 100%;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    transition: background 0.12s;
}
.cs-conv-item:hover { background: #f8faff; }
.cs-conv-item--active { background: #eff6ff !important; border-right: 2px solid #6366f1; }

.cs-conv-avatar {
    width: 40px; height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.cs-conv-info { flex: 1; min-width: 0; }
.cs-conv-name { font-size: 0.825rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cs-conv-preview { font-size: 0.75rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }

.cs-conv-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.cs-conv-time { font-size: 0.68rem; color: #94a3b8; }
.cs-unread {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 4px;
    border-radius: 9px;
    background: #6366f1;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
}

.conv-type-badge {
    font-size: 0.6rem;
    padding: 1px 5px;
    border-radius: 4px;
    font-weight: 600;
}
.conv-type-badge.broadcast { background: #fef3c7; color: #92400e; }
.conv-type-badge.group { background: #ede9fe; color: #4c1d95; }

/* Role badges — Section / Broadcast / Group labels in sidebar */
.role-badge {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    letter-spacing: 0.03em;
    white-space: nowrap;
}
.role-badge.section  { background: #dcfce7; color: #166534; }
.role-badge.broadcast { background: #fef3c7; color: #92400e; }
.role-badge.group    { background: #ede9fe; color: #4c1d95; }

/* Role tags — Admin / Member shown below conversation name */
.role-tag {
    font-size: 0.58rem;
    font-weight: 700;
    padding: 0px 4px;
    border-radius: 3px;
    white-space: nowrap;
    flex-shrink: 0;
}
.role-tag.admin  { background: #fef08a; color: #713f12; }
.role-tag.member { background: #e0f2fe; color: #075985; }


/* ── Main Chat ── */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f8faff;
}

.cm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.cm-avatar {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
}

.cm-name { font-size: 0.875rem; font-weight: 700; color: #1e293b; }
.cm-sub { font-size: 0.72rem; color: #94a3b8; }

.typing-indicator { color: #6366f1; font-style: italic; }

.cm-header-actions { display: flex; gap: 6px; }

.cm-action-btn {
    width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s;
}
.cm-action-btn:hover { border-color: #6366f1; color: #6366f1; }
.cm-action-btn--active { background: #ede9fe; border-color: #6366f1; color: #6366f1; }

.cm-search-panel {
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.cm-pinned-panel {
    padding: 10px 16px;
    background: #fffbeb;
    border-bottom: 1px solid #fcd34d;
    flex-shrink: 0;
    max-height: 120px;
    overflow-y: auto;
}

.pinned-item {
    font-size: 0.8rem;
    color: #78350f;
    padding: 3px 0;
    border-bottom: 1px solid #fde68a;
}
.pinned-item:last-child { border-bottom: none; }

/* ── Messages ── */
.cm-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
}

.cm-messages-inner {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: auto;
}

.msg-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    position: relative;
}
.msg-row--me { flex-direction: row-reverse; }

.msg-avatar {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 700; color: #fff;
    flex-shrink: 0;
}

.msg-content {
    display: flex;
    flex-direction: column;
    max-width: 65%;
    position: relative;
}
.msg-content--me { align-items: flex-end; }

.msg-sender {
    font-size: 0.7rem;
    font-weight: 700;
    color: #6366f1;
    margin-bottom: 2px;
    padding-left: 4px;
}

.msg-reply-ctx {
    font-size: 0.72rem;
    color: #64748b;
    padding: 4px 8px;
    border-left: 2px solid #6366f1;
    background: rgba(99,102,241,0.06);
    border-radius: 4px;
    margin-bottom: 3px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.msg-bubble {
    padding: 8px 12px;
    border-radius: 16px;
    font-size: 0.845rem;
    line-height: 1.5;
    word-break: break-word;
    position: relative;
}
.msg-bubble--other {
    background: #fff;
    border-bottom-left-radius: 4px;
    color: #1e293b;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
}
.msg-bubble--me {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border-bottom-right-radius: 4px;
    color: #fff;
}

.msg-image {
    max-width: 240px;
    max-height: 200px;
    border-radius: 8px;
    object-fit: cover;
}

.msg-file {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: inherit;
    text-decoration: none;
}
.msg-file:hover { text-decoration: underline; }

.msg-audio { max-width: 220px; height: 32px; }

.msg-text { margin: 0; white-space: pre-wrap; }
.msg-edited { font-size: 0.65rem; opacity: 0.6; display: block; text-align: right; }

.msg-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
    padding: 0 4px;
}
.msg-time { font-size: 0.65rem; color: #94a3b8; }
.msg-read-tick { font-size: 0.7rem; color: #6366f1; font-weight: 700; }
.msg-sent-tick { font-size: 0.7rem; color: #94a3b8; }

.msg-actions {
    display: none;
    position: absolute;
    top: -28px;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 3px 6px;
    gap: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 10;
    flex-direction: row;
}
.msg-actions--me { right: auto; left: 0; }
.msg-content:hover .msg-actions { display: flex; }

.msg-act {
    font-size: 0.8rem;
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px 3px;
    border-radius: 4px;
    transition: background 0.12s;
}
.msg-act:hover { background: #f1f5f9; }
.msg-act--del:hover { background: #fee2e2; }

.msg-deleted {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.78rem;
    color: #94a3b8;
    font-style: italic;
    padding: 4px 8px;
}

/* ── Typing bubble ── */
.typing-bubble {
    padding: 10px 14px !important;
    display: flex;
    gap: 4px;
    align-items: center;
}
.typing-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #94a3b8;
    animation: bounce 1.2s infinite;
}
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-6px); }
}

/* ── Loading dots ── */
.loading-dots { display: flex; gap: 5px; }
.loading-dots span {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #6366f1;
    animation: bounce 1.2s infinite;
}
.loading-dots span:nth-child(2) { animation-delay: 0.2s; }
.loading-dots span:nth-child(3) { animation-delay: 0.4s; }

/* ── Input Bar ── */
.cm-input-bar {
    background: #fff;
    border-top: 1px solid #e2e8f0;
    padding: 12px 16px;
    flex-shrink: 0;
}

.input-reply-ctx {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    margin-bottom: 8px;
    background: #f5f3ff;
    border: 1px solid #e0e7ff;
    border-radius: 8px;
}

.input-attachment {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    margin-bottom: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    font-size: 0.78rem;
}

.input-row {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.input-btn {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s;
}
.input-btn:hover { border-color: #6366f1; color: #6366f1; }

.input-textarea {
    flex: 1;
    padding: 9px 12px;
    font-size: 0.875rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    outline: none;
    resize: none;
    font-family: inherit;
    line-height: 1.5;
    max-height: 100px;
    transition: border-color 0.15s;
}
.input-textarea:focus { border-color: #6366f1; }

.input-send-btn {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border: none;
    cursor: pointer;
    flex-shrink: 0;
    transition: opacity 0.15s;
}
.input-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.input-send-btn:not(:disabled):hover { opacity: 0.9; }

/* ── Empty state ── */
.chat-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8faff;
}

.chat-empty-inner { text-align: center; }
.chat-empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
}

/* ── Members Drawer ── */
.members-drawer {
    position: absolute;
    top: 57px; /* below header */
    right: 0;
    width: 280px;
    height: calc(100% - 57px);
    background: #fff;
    border-left: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    z-index: 20;
    box-shadow: -4px 0 20px rgba(0,0,0,0.07);
}

.members-drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.875rem;
    font-weight: 700;
    color: #1e293b;
}

.members-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 10px;
    background: #6366f1;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    margin-left: 4px;
}

.members-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    height: 16px;
    padding: 0 3px;
    border-radius: 8px;
    background: #6366f1;
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    margin-left: 2px;
}

.members-close {
    font-size: 1.2rem;
    color: #94a3b8;
    background: none;
    border: none;
    cursor: pointer;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 4px;
    transition: background 0.12s;
}
.members-close:hover { background: #f1f5f9; color: #1e293b; }

.members-drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}

.members-role-group { margin-bottom: 4px; }

.members-role-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #94a3b8;
    padding: 8px 16px 4px;
}

.member-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 16px;
    transition: background 0.12s;
}
.member-row:hover { background: #f8faff; }

.member-avatar {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
}

.member-info { flex: 1; min-width: 0; }
.member-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.member-type {
    font-size: 0.68rem;
    color: #94a3b8;
    text-transform: capitalize;
}

.member-badges { flex-shrink: 0; }
.member-role-tag {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
}
.member-role-tag.admin  { background: #fef3c7; color: #92400e; }
.member-role-tag.member { background: #e0f2fe; color: #075985; }

.member-dm-btn {
    width: 26px; height: 26px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 7px;
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #6366f1;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.12s;
}
.member-dm-btn:hover { background: #eff6ff; border-color: #6366f1; }

.members-empty { text-align: center; color: #94a3b8; font-size: 0.8rem; padding: 32px 16px; }

/* Slide transition */
.slide-members-enter-active, .slide-members-leave-active { transition: transform 0.22s ease, opacity 0.22s ease; }
.slide-members-enter-from, .slide-members-leave-to { transform: translateX(100%); opacity: 0; }

/* cm-header needs position:relative for drawer positioning */
.chat-main { position: relative; }
</style>
