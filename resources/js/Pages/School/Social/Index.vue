<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PostCard from './Partials/PostCard.vue';
import SocialComposer from './Partials/SocialComposer.vue';

const props = defineProps({
    posts: Object,
    classes: Array,
    trending: Array,
    stories: Array,
    filters: Object,
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const searchOpen = ref(false);
const searchQuery = ref(props.filters?.search || '');
const activeType = ref(props.filters?.type || 'all');
const isLoadingMore = ref(false);

const STORY_ICONS = {
    sparkles: '\u2728', football: '\u26BD', 'color-palette': '\uD83C\uDFA8',
    book: '\uD83D\uDCDA', flask: '\uD83E\uDDEA', people: '\uD83D\uDC65',
};

const TYPE_FILTERS = [
    { key: 'all', label: 'All', color: '#6366F1', bg: '#EEF2FF', emoji: '\uD83D\uDCCB' },
    { key: 'achievement', label: 'Wins', color: '#F59E0B', bg: '#FEF3C7', emoji: '\uD83C\uDFC6' },
    { key: 'event', label: 'Events', color: '#6366F1', bg: '#EEF2FF', emoji: '\uD83D\uDCC5' },
    { key: 'sports', label: 'Sports', color: '#22C55E', bg: '#DCFCE7', emoji: '\u26BD' },
    { key: 'gallery', label: 'Gallery', color: '#EC4899', bg: '#FCE7F3', emoji: '\uD83D\uDDBC\uFE0F' },
    { key: 'birthday', label: 'Birthday', color: '#F97316', bg: '#FFF7ED', emoji: '\uD83C\uDF82' },
];

let searchTimeout = null;
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('school.communication.social-buzz.index'), {
            search: searchQuery.value || undefined,
            type: activeType.value !== 'all' ? activeType.value : undefined,
        }, { preserveState: true, preserveScroll: true });
    }, 400);
};

const setTypeFilter = (type) => {
    activeType.value = type;
    router.get(route('school.communication.social-buzz.index'), {
        search: searchQuery.value || undefined,
        type: type !== 'all' ? type : undefined,
    }, { preserveState: true, preserveScroll: false });
};

const loadMore = () => {
    if (isLoadingMore.value || !props.posts.next_page_url) return;
    isLoadingMore.value = true;
    router.get(props.posts.next_page_url, {}, {
        preserveState: true, preserveScroll: true, only: ['posts'],
        onSuccess: () => { isLoadingMore.value = false; },
    });
};

const handleScroll = () => {
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 600) loadMore();
};
onMounted(() => window.addEventListener('scroll', handleScroll));
onUnmounted(() => { window.removeEventListener('scroll', handleScroll); clearTimeout(searchTimeout); });

const isStaffOrAdmin = computed(() => ['super_admin','admin','school_admin','principal','teacher'].includes(currentUser.value.user_type));
</script>

<template>
    <Head title="Social Buzz" />
    <SchoolLayout title="Social Buzz">
        <div class="sb-page">

            <!-- Hero header -->
            <div class="sb-hero">
                <div class="sb-hero-bg"></div>
                <div class="sb-hero-content">
                    <div class="sb-hero-left">
                        <div class="sb-hero-icon">
                            <span>&#x2728;</span>
                        </div>
                        <div>
                            <h1 class="sb-hero-title">Social Buzz</h1>
                            <p class="sb-hero-sub">What's happening in your school</p>
                        </div>
                    </div>
                    <button @click="searchOpen = !searchOpen" class="sb-search-toggle" :class="{ active: searchOpen }">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                </div>

                <!-- Search -->
                <Transition enter-active-class="sb-search-enter" enter-from-class="sb-search-from" enter-to-class="sb-search-to" leave-active-class="sb-search-leave" leave-from-class="sb-search-to" leave-to-class="sb-search-from">
                    <div v-if="searchOpen" class="sb-search-wrap">
                        <svg class="sb-search-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="searchQuery" @input="handleSearch" type="text" placeholder="Search posts, hashtags..." class="sb-search-field" autofocus />
                        <button v-if="searchQuery" @click="searchQuery=''; handleSearch()" class="sb-search-x">&times;</button>
                    </div>
                </Transition>
            </div>

            <div class="sb-container">

                <!-- Story circles -->
                <div class="sb-stories">
                    <div class="sb-stories-track">
                        <div v-for="(story, idx) in stories" :key="idx" class="sb-story" :class="{ fresh: story.hasNew }">
                            <div class="sb-story-ring" :style="story.hasNew ? { background: `linear-gradient(135deg, ${story.color}, ${story.color}88)` } : {}">
                                <div class="sb-story-inner" :style="{ background: story.color + '12' }">
                                    <span class="sb-story-emoji">{{ STORY_ICONS[story.icon] || '\u2728' }}</span>
                                </div>
                            </div>
                            <span class="sb-story-label">{{ story.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Filter chips -->
                <div class="sb-chips">
                    <button
                        v-for="f in TYPE_FILTERS" :key="f.key"
                        @click="setTypeFilter(f.key)"
                        class="sb-chip"
                        :class="{ on: activeType === f.key }"
                        :style="activeType === f.key ? { background: f.bg, borderColor: f.color, color: f.color } : {}"
                    >
                        <span class="sb-chip-emoji">{{ f.emoji }}</span>
                        <span>{{ f.label }}</span>
                    </button>
                </div>

                <!-- Trending -->
                <div v-if="activeType === 'all' && trending?.length" class="sb-trending">
                    <div class="sb-trending-top">
                        <span class="sb-trending-icon">&#x1F525;</span>
                        <span class="sb-trending-heading">Trending in School</span>
                    </div>
                    <div class="sb-trending-list">
                        <div v-for="(t, i) in trending" :key="i" class="sb-trend-item">
                            <span class="sb-trend-rank">{{ i + 1 }}</span>
                            <div class="sb-trend-body">
                                <span class="sb-trend-tag">{{ t.tag }}</span>
                                <span class="sb-trend-count">{{ t.posts }} posts</span>
                            </div>
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </div>

                <!-- Composer -->
                <SocialComposer v-if="isStaffOrAdmin" :classes="classes" />

                <!-- Feed -->
                <div class="sb-feed">
                    <template v-if="posts.data.length">
                        <TransitionGroup enter-active-class="sb-post-enter" enter-from-class="sb-post-from" enter-to-class="sb-post-to">
                            <PostCard v-for="post in posts.data" :key="post.id" :post="post" />
                        </TransitionGroup>

                        <div v-if="isLoadingMore" class="sb-loader">
                            <span class="sb-bounce b1"></span>
                            <span class="sb-bounce b2"></span>
                            <span class="sb-bounce b3"></span>
                        </div>
                        <div v-else-if="posts.next_page_url" class="sb-load-more">
                            <button @click="loadMore" class="sb-load-btn">Load more posts</button>
                        </div>
                        <div v-else class="sb-end">
                            <div class="sb-end-line"></div>
                            <span class="sb-end-text">You're all caught up &#x1F389;</span>
                            <div class="sb-end-line"></div>
                        </div>
                    </template>
                    <div v-else class="sb-empty">
                        <div class="sb-empty-icon">&#x1F4E2;</div>
                        <h3 class="sb-empty-title">No posts yet</h3>
                        <p class="sb-empty-desc">Be the first to share something amazing!</p>
                    </div>
                </div>

            </div>
        </div>
    </SchoolLayout>
</template>

<style scoped>
.sb-page { background: #F8F9FC; min-height: calc(100vh - 64px); padding-bottom: 60px; }
.sb-container { max-width: 640px; margin: 0 auto; padding: 0 16px; }

/* ── Hero Header ── */
.sb-hero {
    position: sticky; top: 0; z-index: 40;
    background: #fff; overflow: hidden;
    border-bottom: 1px solid #F0F1F5;
}
.sb-hero-bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #EEF2FF 0%, #F5F3FF 50%, #FDF2F8 100%);
    opacity: 0.5;
}
.sb-hero-content {
    position: relative; display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px;
}
.sb-hero-left { display: flex; align-items: center; gap: 12px; }
.sb-hero-icon {
    width: 40px; height: 40px; border-radius: 12px;
    background: linear-gradient(135deg, #6366F1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.125rem; box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}
.sb-hero-title { font-size: 1.125rem; font-weight: 800; color: #1E1E2D; line-height: 1.2; }
.sb-hero-sub { font-size: 0.6875rem; color: #9CA3AF; font-weight: 500; margin-top: 1px; }
.sb-search-toggle {
    width: 38px; height: 38px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #9CA3AF; background: #F3F4F6; border: none; cursor: pointer;
    transition: all 0.2s;
}
.sb-search-toggle:hover, .sb-search-toggle.active { background: #EEF2FF; color: #6366F1; }

/* Search */
.sb-search-wrap {
    position: relative; padding: 0 20px 14px;
}
.sb-search-icon { position: absolute; left: 34px; top: 12px; color: #9CA3AF; pointer-events: none; }
.sb-search-field {
    width: 100%; padding: 10px 36px 10px 42px; background: #F5F5FA;
    border: 1.5px solid transparent; border-radius: 12px;
    font-size: 0.8125rem; color: #1E1E2D; outline: none; transition: all 0.2s;
}
.sb-search-field:focus { background: #fff; border-color: #6366F1; box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
.sb-search-field::placeholder { color: #C5C7D0; }
.sb-search-x { position: absolute; right: 32px; top: 10px; color: #9CA3AF; font-size: 1.25rem; cursor: pointer; }
.sb-search-enter { transition: all 0.2s ease-out; }
.sb-search-leave { transition: all 0.15s ease-in; }
.sb-search-from { opacity: 0; transform: translateY(-8px); }
.sb-search-to { opacity: 1; transform: translateY(0); }

/* ── Stories ── */
.sb-stories { padding: 18px 0 6px; }
.sb-stories-track {
    display: flex; gap: 16px; overflow-x: auto; padding: 0 4px;
    scrollbar-width: none;
}
.sb-stories-track::-webkit-scrollbar { display: none; }
.sb-story {
    display: flex; flex-direction: column; align-items: center;
    width: 68px; flex-shrink: 0; cursor: pointer;
}
.sb-story-ring {
    width: 60px; height: 60px; border-radius: 50%; padding: 3px;
    background: #E5E7EB; transition: transform 0.2s;
}
.sb-story:hover .sb-story-ring { transform: scale(1.06); }
.sb-story.fresh .sb-story-ring { box-shadow: 0 3px 12px rgba(99,102,241,0.2); }
.sb-story-inner {
    width: 100%; height: 100%; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #F5F5FA; border: 2.5px solid #fff;
}
.sb-story-emoji { font-size: 1.25rem; line-height: 1; }
.sb-story-label {
    font-size: 0.625rem; color: #6B7280; margin-top: 6px;
    text-align: center; font-weight: 600; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; max-width: 68px;
}
.sb-story.fresh .sb-story-label { color: #4F46E5; font-weight: 700; }

/* ── Filter Chips ── */
.sb-chips {
    display: flex; gap: 8px; overflow-x: auto; padding: 10px 0 16px;
    scrollbar-width: none;
}
.sb-chips::-webkit-scrollbar { display: none; }
.sb-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; border-radius: 100px;
    font-size: 0.75rem; font-weight: 600; color: #6B7280;
    background: #fff; border: 1.5px solid #E5E7EB;
    white-space: nowrap; cursor: pointer; transition: all 0.2s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.sb-chip:hover { border-color: #C7D2FE; }
.sb-chip.on { font-weight: 700; box-shadow: 0 2px 8px rgba(99,102,241,0.12); }
.sb-chip-emoji { font-size: 0.8125rem; line-height: 1; }

/* ── Trending ── */
.sb-trending {
    background: #fff; border-radius: 16px; margin-bottom: 14px; padding: 16px 18px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04); border: 1px solid #F0F1F5;
}
.sb-trending-top { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.sb-trending-icon { font-size: 1rem; }
.sb-trending-heading { font-size: 0.875rem; font-weight: 700; color: #1E1E2D; }
.sb-trending-list { display: flex; flex-direction: column; }
.sb-trend-item {
    display: flex; align-items: center; gap: 12px; padding: 9px 4px;
    border-radius: 8px; cursor: pointer; transition: background 0.15s;
}
.sb-trend-item:hover { background: #F8F9FC; }
.sb-trend-rank {
    width: 22px; height: 22px; border-radius: 6px;
    background: #F3F4F6; color: #9CA3AF;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.6875rem; font-weight: 800;
}
.sb-trend-body { flex: 1; }
.sb-trend-tag { font-size: 0.8125rem; font-weight: 700; color: #6366F1; display: block; }
.sb-trend-count { font-size: 0.625rem; color: #9CA3AF; }

/* ── Feed ── */
.sb-feed { display: flex; flex-direction: column; gap: 14px; }

.sb-post-enter { transition: all 0.3s ease-out; }
.sb-post-from { opacity: 0; transform: translateY(12px); }
.sb-post-to { opacity: 1; transform: translateY(0); }

/* Loader */
.sb-loader { display: flex; justify-content: center; gap: 6px; padding: 28px 0; }
.sb-bounce {
    width: 10px; height: 10px; border-radius: 50%;
    background: #A5B4FC; animation: sb-pulse 1.4s ease-in-out infinite;
}
.sb-bounce.b2 { animation-delay: 0.16s; }
.sb-bounce.b3 { animation-delay: 0.32s; }
@keyframes sb-pulse {
    0%, 80%, 100% { transform: scale(0.5); opacity: 0.3; }
    40% { transform: scale(1); opacity: 1; }
}

.sb-load-more { text-align: center; padding: 24px 0; }
.sb-load-btn {
    padding: 10px 28px; border-radius: 100px; border: 1.5px solid #E5E7EB;
    font-size: 0.8125rem; font-weight: 600; color: #6366F1; background: #fff;
    cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sb-load-btn:hover { border-color: #6366F1; box-shadow: 0 4px 12px rgba(99,102,241,0.12); }

.sb-end { display: flex; align-items: center; gap: 16px; padding: 32px 0; }
.sb-end-line { flex: 1; height: 1px; background: #E5E7EB; }
.sb-end-text { font-size: 0.75rem; color: #9CA3AF; font-weight: 500; white-space: nowrap; }

.sb-empty {
    text-align: center; padding: 60px 20px; background: #fff;
    border-radius: 20px; border: 1px solid #F0F1F5;
}
.sb-empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
.sb-empty-title { font-size: 1rem; font-weight: 700; color: #374151; margin-bottom: 4px; }
.sb-empty-desc { font-size: 0.8125rem; color: #9CA3AF; }
</style>
