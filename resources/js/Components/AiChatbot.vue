<template>
    <!-- Floating bubble -->
    <button class="ai-bubble" @click="toggle" :class="{ open: isOpen }" title="AI Assistant">
        <span v-if="!isOpen" class="ai-bubble-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </span>
        <span v-else class="ai-bubble-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </span>
        <span v-if="unread > 0 && !isOpen" class="ai-unread">{{ unread }}</span>
    </button>

    <!-- Chat panel -->
    <Transition name="ai-slide">
        <div v-if="isOpen" class="ai-panel">

            <!-- ── Header ── -->
            <div class="ai-header">
                <div class="ai-header-top">
                    <div class="ai-header-avatar">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <div class="ai-header-info">
                        <div class="ai-header-name">ERP Assistant</div>
                        <div class="ai-header-status"><span class="ai-status-dot"></span> Powered by Gemini AI</div>
                    </div>
                    <div class="ai-header-actions">
                        <button v-if="activeTab === 'chat'" class="ai-hdr-btn" @click="newChat" title="New chat">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button v-if="activeTab === 'history'" class="ai-hdr-btn ai-hdr-btn-warn" @click="clearAllSessions" title="Clear all history">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                <path d="M10 11v6M14 11v6M9 6V4h6v2"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="ai-tabs">
                    <button class="ai-tab" :class="{ active: activeTab === 'chat' }" @click="activeTab = 'chat'">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Chat
                    </button>
                    <button class="ai-tab" :class="{ active: activeTab === 'browse' }" @click="activeTab = 'browse'">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        Browse ERP
                    </button>
                    <button class="ai-tab" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        History
                    </button>
                </div>
            </div>

            <!-- ═══════════ CHAT VIEW ═══════════ -->
            <template v-if="activeTab === 'chat'">
                <div class="ai-messages" ref="messagesEl">
                    <!-- Welcome -->
                    <div v-if="messages.length === 0" class="ai-welcome">
                        <div class="ai-welcome-icon">🎓</div>
                        <div class="ai-welcome-title">Hi! I'm your ERP Assistant</div>
                        <div class="ai-welcome-sub">Ask me anything — data, how-to, navigation</div>
                        <div class="ai-suggestions">
                            <button v-for="s in pageSuggestions" :key="s" class="ai-suggestion" @click="sendSuggestion(s)">{{ s }}</button>
                        </div>
                        <div class="ai-page-hint">💡 Suggestions match your current page</div>
                    </div>

                    <!-- Messages -->
                    <template v-for="(msg, i) in messages" :key="i">
                        <div v-if="showDateSep(i)" class="ai-date-sep"><span>{{ formatDateSep(msg.time) }}</span></div>
                        <div class="ai-msg" :class="msg.role">
                            <div class="ai-msg-avatar" v-if="msg.role === 'assistant'">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </div>
                            <div class="ai-msg-wrap">
                                <div class="ai-msg-bubble-row">
                                    <div class="ai-msg-bubble" v-html="formatMessage(msg.content)"></div>
                                    <button v-if="msg.role === 'assistant'" class="ai-copy-btn" @click="copyMessage(msg.content, i)" :title="copiedIdx === i ? 'Copied!' : 'Copy'">
                                        <svg v-if="copiedIdx !== i" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                        </svg>
                                        <svg v-else width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="ai-msg-time" v-if="msg.time">{{ formatTime(msg.time) }}</div>
                                <!-- Follow-up chips (only on last assistant msg) -->
                                <div v-if="msg.role === 'assistant' && msg.follow_ups?.length && i === messages.length - 1" class="ai-follow-ups">
                                    <button v-for="f in msg.follow_ups" :key="f" class="ai-followup-chip" @click="sendSuggestion(f)">{{ f }}</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Typing indicator -->
                    <div v-if="loading" class="ai-msg assistant">
                        <div class="ai-msg-avatar">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </div>
                        <div class="ai-msg-bubble ai-typing"><span></span><span></span><span></span></div>
                    </div>
                </div>

                <div v-if="error" class="ai-error-bar">⚠️ {{ error }}</div>

                <div class="ai-input-wrap">
                    <textarea ref="inputEl" v-model="input" class="ai-input"
                        placeholder="Ask anything about the ERP…" rows="1"
                        @keydown.enter.exact.prevent="send"
                        @input="autoResize" :disabled="loading"></textarea>
                    <button class="ai-send" @click="send" :disabled="!input.trim() || loading">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
                <div class="ai-hint">Enter to send · Shift+Enter for new line</div>
            </template>

            <!-- ═══════════ BROWSE ERP ═══════════ -->
            <div v-else-if="activeTab === 'browse'" class="ai-browse">
                <div class="ai-browse-search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ai-browse-search-icon">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input v-model="browseSearch" class="ai-browse-search" placeholder="Search pages…" />
                </div>

                <div class="ai-browse-list">
                    <template v-for="group in filteredBrowseGroups" :key="group.label">
                        <div class="ai-browse-group-label">{{ group.label }}</div>
                        <template v-for="module in group.modules" :key="module.id">
                            <!-- Module with children -->
                            <div v-if="module.children" class="ai-browse-module">
                                <button class="ai-browse-module-header" @click="toggleModule(module.id)"
                                    :class="{ expanded: expandedModules.has(module.id) || browseSearch }">
                                    <span class="ai-browse-module-title">{{ module.title }}</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="ai-browse-chevron">
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </button>
                                <div v-show="expandedModules.has(module.id) || browseSearch" class="ai-browse-children">
                                    <a v-for="child in module.children" :key="child.route"
                                        :href="child.route" class="ai-browse-link">
                                        <span class="ai-browse-link-dot"></span>
                                        {{ child.title }}
                                    </a>
                                </div>
                            </div>
                            <!-- Module without children (direct link) -->
                            <a v-else :href="module.route" class="ai-browse-direct-link">
                                <span class="ai-browse-module-title">{{ module.title }}</span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="ai-browse-arrow">
                                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                        </template>
                    </template>
                    <div v-if="filteredBrowseGroups.length === 0" class="ai-browse-empty">No pages match "{{ browseSearch }}"</div>
                </div>
            </div>

            <!-- ═══════════ HISTORY VIEW ═══════════ -->
            <div v-else-if="activeTab === 'history'" class="ai-history">
                <div class="ai-history-new" @click="newChat">
                    <div class="ai-history-new-icon">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </div>
                    <span>New Chat</span>
                </div>
                <div v-if="sessionsSorted.length === 0" class="ai-history-empty">
                    <div style="font-size:2rem;margin-bottom:8px;">💬</div>
                    <div>No previous chats yet</div>
                </div>
                <div v-else class="ai-history-list">
                    <div v-for="s in sessionsSorted" :key="s.id" class="ai-history-item"
                        :class="{ active: s.id === currentSessionId }" @click="loadSession(s.id)">
                        <div class="ai-history-icon">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </div>
                        <div class="ai-history-body">
                            <div class="ai-history-title">{{ s.title }}</div>
                            <div class="ai-history-meta">{{ s.messages.length }} msg{{ s.messages.length !== 1 ? 's' : '' }} · {{ formatSessionDate(s.updatedAt) }}</div>
                        </div>
                        <button class="ai-history-del" @click.stop="deleteSession(s.id)" title="Delete">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import axios from 'axios';

const SESSIONS_KEY = 'erp_ai_sessions';
const CURRENT_KEY  = 'erp_ai_current_session';
const MAX_SESSIONS = 30;

// ── State ────────────────────────────────────────────────────
const isOpen           = ref(false);
const activeTab        = ref('chat');
const input            = ref('');
const loading          = ref(false);
const error            = ref('');
const unread           = ref(0);
const messagesEl       = ref(null);
const inputEl          = ref(null);
const sessions         = ref([]);
const currentSessionId = ref(null);
const messages         = ref([]);
const copiedIdx        = ref(null);

// ── Browse state ─────────────────────────────────────────────
const browseSearch     = ref('');
const expandedModules  = ref(new Set());

// ── Full ERP menu (from sidebar) ─────────────────────────────
const ERP_MENU = [
    {
        label: 'Administration', modules: [
            { id: 'dashboard',   title: 'Dashboard',             route: '/school/dashboard' },
            { id: 'academic_structure', title: 'Academic Structure', children: [
                { title: 'Classes',         route: '/school/classes' },
                { title: 'Sections',        route: '/school/sections' },
                { title: 'Subject Types',   route: '/school/subject-types' },
                { title: 'Subjects',        route: '/school/subjects' },
                { title: 'Assign Subjects', route: '/school/class-subjects' },
            ]},
            { id: 'academic_resources', title: 'Academic Resources', children: [
                { title: 'Student Diary',     route: '/school/academic/diary' },
                { title: 'Assignments',       route: '/school/academic/assignments' },
                { title: 'Syllabus Tracker',  route: '/school/academic/syllabus' },
                { title: 'Digital Resources', route: '/school/academic/resources' },
                { title: 'Book List',         route: '/school/academic/book-list' },
            ]},
            { id: 'students', title: 'Student Management', children: [
                { title: 'Registrations',         route: '/school/registrations' },
                { title: 'Students Directory',    route: '/school/students' },
                { title: 'Student Leaves',        route: '/school/student-leaves' },
                { title: 'Student Leave Types',   route: '/school/student-leave-types' },
                { title: 'Roll Numbers',          route: '/school/roll-numbers' },
                { title: 'Transfer Certificates', route: '/school/transfer-certificates' },
            ]},
            { id: 'attendance', title: 'Attendance', children: [
                { title: 'Mark Attendance',   route: '/school/attendance' },
                { title: 'Attendance Report', route: '/school/attendance/report' },
            ]},
            { id: 'examinations', title: 'Examinations', children: [
                { title: 'Exam Terms',        route: '/school/exam-terms' },
                { title: 'Exam Types',        route: '/school/exam-types' },
                { title: 'Exam Grades',       route: '/school/grading-systems' },
                { title: 'Exam Assessment',   route: '/school/exam-assessments' },
                { title: 'Exam Schedule',     route: '/school/exam-schedules' },
                { title: 'Admit Cards',       route: '/school/admit-cards' },
                { title: 'Marks Entry',       route: '/school/exam-marks' },
                { title: 'Results',           route: '/school/exam-results' },
                { title: 'Report Cards',      route: '/school/report-cards' },
                { title: 'AI Question Paper', route: '/school/question-papers' },
            ]},
            { id: 'schedule', title: 'Schedule', children: [
                { title: 'Periods',   route: '/school/periods' },
                { title: 'Timetable', route: '/school/timetable' },
            ]},
        ]
    },
    {
        label: 'Finance', modules: [
            { id: 'finance', title: 'Finance & Fees', children: [
                { title: 'Collect Fee',        route: '/school/fee/collect' },
                { title: 'Fee Structure',      route: '/school/fee/structure' },
                { title: 'Groups & Heads',     route: '/school/fee/groups' },
                { title: 'Concessions',        route: '/school/fee/concessions' },
                { title: 'Receipt Settings',   route: '/school/fee/config' },
                { title: 'Due Report',         route: '/school/finance/due-report' },
                { title: 'Day Book',           route: '/school/finance/day-book' },
                { title: 'Fee Summary',        route: '/school/finance/fee-summary' },
                { title: 'Financial Reports',  route: '/school/finance/reports' },
                { title: 'Expenses',           route: '/school/expenses' },
                { title: 'Expense Categories', route: '/school/expense-categories' },
                { title: 'Ledger Types',       route: '/school/finance/ledger-types' },
                { title: 'Chart of Accounts',  route: '/school/finance/ledgers' },
                { title: 'Transactions',       route: '/school/finance/transactions' },
                { title: 'Trial Balance',      route: '/school/finance/statements/trial-balance' },
                { title: 'Profit & Loss',      route: '/school/finance/statements/profit-loss' },
                { title: 'Balance Sheet',      route: '/school/finance/statements/balance-sheet' },
                { title: 'Budget Management',  route: '/school/finance/budgets' },
                { title: 'GL Auto-Posting',    route: '/school/finance/gl-config' },
            ]},
        ]
    },
    {
        label: 'HR & Staff', modules: [
            { id: 'hr', title: 'Staff & HR', children: [
                { title: 'Departments',         route: '/school/departments' },
                { title: 'Designations',        route: '/school/designations' },
                { title: 'Staff Directory',     route: '/school/staff' },
                { title: 'Staff Attendance',    route: '/school/staff-attendance' },
                { title: 'Attendance Report',   route: '/school/staff-attendance/report' },
                { title: 'Leave Management',    route: '/school/leaves' },
                { title: 'Leave Types',         route: '/school/leave-types' },
                { title: 'Payroll',             route: '/school/payroll' },
                { title: 'Incharge Assignment', route: '/school/incharge' },
            ]},
        ]
    },
    {
        label: 'Operations', modules: [
            { id: 'front_office', title: 'Front Office', children: [
                { title: 'Dashboard',      route: '/school/front-office' },
                { title: 'Visitor Log',    route: '/school/front-office/visitors' },
                { title: 'Gate Passes',    route: '/school/front-office/gate-passes' },
                { title: 'QR Scanner',     route: '/school/front-office/gate-passes/scanner' },
                { title: 'Complaints',     route: '/school/front-office/complaints' },
                { title: 'Call Logs',      route: '/school/front-office/call-logs' },
                { title: 'Follow-Ups',     route: '/school/front-office/call-logs-follow-ups' },
                { title: 'Correspondence', route: '/school/front-office/correspondence' },
                { title: 'Daily Report',   route: '/school/front-office/daily-report' },
            ]},
            { id: 'hostel', title: 'Hostel', children: [
                { title: 'Dashboard',           route: '/school/hostel' },
                { title: 'Manage Hostels',      route: '/school/hostel/hostels' },
                { title: 'Rooms & Beds',        route: '/school/hostel/rooms' },
                { title: 'Student Allocations', route: '/school/hostel/allocations' },
                { title: 'Gate Passes',         route: '/school/hostel/gate-passes' },
                { title: 'Visitor Logs',        route: '/school/hostel/visitors' },
                { title: 'Mess Menu',           route: '/school/hostel/mess' },
                { title: 'Roll Call',           route: '/school/hostel/roll-call' },
                { title: 'Roll Call Report',    route: '/school/hostel/roll-call/report' },
                { title: 'Complaints',          route: '/school/hostel/complaints' },
                { title: 'Meal Report',         route: '/school/hostel/mess/meal-report' },
            ]},
            { id: 'transport', title: 'Transport', children: [
                { title: 'Dashboard',          route: '/school/transport' },
                { title: 'Routes & Stops',     route: '/school/transport/routes' },
                { title: 'Vehicles',           route: '/school/transport/vehicles' },
                { title: 'Student Allocation', route: '/school/transport/allocations' },
                { title: 'Bus Roll Call',      route: '/school/transport/attendance' },
                { title: 'Route Report',       route: '/school/transport/reports/route-report' },
                { title: 'Fee Defaulters',     route: '/school/transport/reports/fee-defaulters' },
                { title: 'Live Tracking',      route: '/school/transport/live' },
                { title: 'Driver Tracking',    route: '/school/transport/driver-tracking' },
            ]},
        ]
    },
    {
        label: 'Communication', modules: [
            { id: 'communication', title: 'Communication', children: [
                { title: 'Dashboard',           route: '/school/communication/dashboard' },
                { title: 'Announcements',       route: '/school/communication/announcements' },
                { title: 'Emergency Broadcast', route: '/school/communication/emergency' },
                { title: 'Message Logs',        route: '/school/communication/logs' },
                { title: 'Delivery Analytics',  route: '/school/communication/analytics' },
                { title: 'Email Templates',     route: '/school/communication/email-templates' },
                { title: 'Scheduled Queue',     route: '/school/communication/scheduled' },
                { title: 'Social Buzz',         route: '/school/communication/social-buzz' },
                { title: 'SMS Config',          route: '/school/communication/config/sms' },
                { title: 'WhatsApp Config',     route: '/school/communication/config/whatsapp' },
                { title: 'Voice Config',        route: '/school/communication/config/voice' },
                { title: 'SMS Templates',       route: '/school/communication/templates/sms' },
                { title: 'WhatsApp Templates',  route: '/school/communication/templates/whatsapp' },
                { title: 'Voice Templates',     route: '/school/communication/templates/voice' },
                { title: 'Push Templates',      route: '/school/communication/templates/push' },
            ]},
            { id: 'ai_insights', title: 'AI Intelligence Hub', route: '/school/ai/insights' },
            { id: 'chat',        title: 'Chat',                route: '/school/chat' },
            { id: 'holidays',    title: 'Holidays & Events',   route: '/school/holidays' },
        ]
    },
    {
        label: 'Settings', modules: [
            { id: 'settings', title: 'Settings & Setup', children: [
                { title: 'General Config',  route: '/school/settings/general-config' },
                { title: 'Asset Config',    route: '/school/settings/asset-config' },
                { title: 'System Config',   route: '/school/settings/system-config' },
                { title: 'Mobile App QR',   route: '/school/settings/mobile-qr' },
                { title: 'Academic Years',  route: '/school/academic-years' },
                { title: 'Setup Wizard',    route: '/school/settings/rollover' },
                { title: 'Custom Fields',   route: '/school/custom-fields' },
                { title: 'Number Formats',  route: '/school/settings/number-formats' },
                { title: 'Edit Requests',   route: '/school/edit-requests' },
            ]},
            { id: 'roles',    title: 'Roles & Permissions',  route: '/school/roles-permissions' },
            { id: 'users',    title: 'User Login Management', route: '/school/users' },
            { id: 'activity', title: 'Activity Log',          route: '/school/utility/activity-log' },
            { id: 'errors',   title: 'Error Log',             route: '/school/utility/error-log' },
        ]
    },
];

function toggleModule(id) {
    if (expandedModules.value.has(id)) {
        expandedModules.value.delete(id);
    } else {
        expandedModules.value.add(id);
    }
    expandedModules.value = new Set(expandedModules.value);
}

const filteredBrowseGroups = computed(() => {
    const q = browseSearch.value.toLowerCase().trim();
    if (!q) return ERP_MENU;
    return ERP_MENU.map(group => {
        const modules = group.modules.map(mod => {
            if (mod.children) {
                const children = mod.children.filter(c => c.title.toLowerCase().includes(q));
                if (children.length || mod.title.toLowerCase().includes(q)) {
                    return { ...mod, children: children.length ? children : mod.children };
                }
                return null;
            }
            return mod.title.toLowerCase().includes(q) ? mod : null;
        }).filter(Boolean);
        return modules.length ? { ...group, modules } : null;
    }).filter(Boolean);
});

// ── Page-aware suggestions ────────────────────────────────────
const PAGE_SUGGESTIONS = {
    '/school/students':           ['How many students enrolled this year?', 'How do I add a new student?', 'Show students with pending fees'],
    '/school/students/directory': ['How many students are in each class?', 'How do I filter students by class?', 'How do I export student data?'],
    '/school/attendance':         ['How many students are absent today?', 'How do I mark attendance via QR?', 'Show low attendance students'],
    '/school/exam-marks':         ['How do I enter marks in bulk?', 'What does ABS mean in marks?', 'How do I finalize marks?'],
    '/school/report-cards':       ['How do I print report cards?', 'How do I add teacher remarks?', 'How do I generate all report cards?'],
    '/school/fee/collect':        ['How do I collect fee for a student?', 'What payment modes are supported?', 'How do I generate a receipt?'],
    '/school/fee/structure':      ['How do I create a fee structure?', 'Can I set different fees per class?', 'How do I add a fee head?'],
    '/school/finance/due-report': ['Which students have pending fees?', 'How do I send fee reminders?', 'Who are the top defaulters?'],
    '/school/staff':              ['How many staff are on leave today?', 'How do I add a new staff member?', 'How do I assign a department?'],
    '/school/payroll':            ['How do I generate payroll for this month?', 'How do I mark salary as paid?', 'Which staff haven\'t been paid?'],
    '/school/transport':          ['How many students use transport?', 'How do I add a new route?', 'How do I allocate a student to a route?'],
    '/school/hostel':             ['How many students are in the hostel?', 'How do I allocate a room?', 'Show hostel occupancy'],
    '/school/communication/dashboard': ['How do I send an SMS to all parents?', 'How do I schedule an announcement?', 'How do I send a WhatsApp message?'],
    '/school/ai/insights':        ['What do my attendance numbers mean?', 'Which students need urgent attention?', 'How do I improve fee collection?'],
};
const DEFAULT_SUGGESTIONS = [
    'How many students are absent today?',
    'What is today\'s fee collection?',
    'How do I add a new student?',
    'How do I mark attendance?',
    'Which students have pending fees?',
    'How do I generate report cards?',
];
const pageSuggestions = computed(() => PAGE_SUGGESTIONS[window.location.pathname] ?? DEFAULT_SUGGESTIONS);

// ── Sessions ─────────────────────────────────────────────────
const sessionsSorted = computed(() => [...sessions.value].sort((a, b) => b.updatedAt - a.updatedAt));

function persistSessions() {
    try {
        const sorted = [...sessions.value].sort((a, b) => b.updatedAt - a.updatedAt);
        localStorage.setItem(SESSIONS_KEY, JSON.stringify(sorted.slice(0, MAX_SESSIONS)));
        if (currentSessionId.value) localStorage.setItem(CURRENT_KEY, String(currentSessionId.value));
    } catch {}
}

function saveCurrentSession() {
    const idx = sessions.value.findIndex(s => s.id === currentSessionId.value);
    if (idx === -1) return;
    sessions.value[idx].messages  = [...messages.value];
    sessions.value[idx].updatedAt = Date.now();
    persistSessions();
}

function startNewSession() {
    const id = Date.now();
    sessions.value.push({ id, title: 'New Chat', updatedAt: id, messages: [] });
    currentSessionId.value = id;
    messages.value = [];
    persistSessions();
}

function loadSessions() {
    try {
        const raw = localStorage.getItem(SESSIONS_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed) && parsed.length > 0) {
                sessions.value = parsed;
                const savedId = localStorage.getItem(CURRENT_KEY);
                if (savedId) {
                    const id    = parseInt(savedId);
                    const found = sessions.value.find(s => s.id === id);
                    if (found) { currentSessionId.value = id; messages.value = [...found.messages]; return; }
                }
                const latest = [...sessions.value].sort((a, b) => b.updatedAt - a.updatedAt)[0];
                currentSessionId.value = latest.id;
                messages.value = [...latest.messages];
                return;
            }
        }
        const legacyRaw = localStorage.getItem('erp_ai_chat_history');
        if (legacyRaw) {
            const legacyMsgs = JSON.parse(legacyRaw);
            if (Array.isArray(legacyMsgs) && legacyMsgs.length > 0) {
                const id    = Date.now();
                const first = legacyMsgs.find(m => m.role === 'user');
                sessions.value = [{ id, title: first ? first.content.slice(0, 45) + '…' : 'Previous Chat', updatedAt: id, messages: legacyMsgs }];
                currentSessionId.value = id;
                messages.value = [...legacyMsgs];
                persistSessions();
                localStorage.removeItem('erp_ai_chat_history');
                return;
            }
        }
    } catch {}
    startNewSession();
}

function newChat() {
    if (messages.value.length === 0) { activeTab.value = 'chat'; nextTick(() => inputEl.value?.focus()); return; }
    saveCurrentSession();
    startNewSession();
    activeTab.value = 'chat';
    error.value = '';
    nextTick(() => { inputEl.value?.focus(); scrollToBottom(); });
}

function loadSession(id) {
    saveCurrentSession();
    const s = sessions.value.find(s => s.id === id);
    if (!s) return;
    currentSessionId.value = id;
    messages.value = [...s.messages];
    localStorage.setItem(CURRENT_KEY, String(id));
    activeTab.value = 'chat';
    error.value = '';
    nextTick(() => scrollToBottom());
}

function deleteSession(id) {
    const idx = sessions.value.findIndex(s => s.id === id);
    if (idx !== -1) sessions.value.splice(idx, 1);
    if (id === currentSessionId.value) {
        if (sessions.value.length > 0) {
            const latest = [...sessions.value].sort((a, b) => b.updatedAt - a.updatedAt)[0];
            currentSessionId.value = latest.id; messages.value = [...latest.messages];
        } else { startNewSession(); activeTab.value = 'chat'; }
    }
    persistSessions();
}

function clearAllSessions() {
    if (!confirm('Clear all chat history? This cannot be undone.')) return;
    sessions.value = [];
    startNewSession();
    activeTab.value = 'chat';
}

// ── Toggle panel ─────────────────────────────────────────────
function toggle() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        unread.value = 0;
        localStorage.setItem('erp_ai_last_opened', Date.now().toString());
        nextTick(() => { inputEl.value?.focus(); scrollToBottom(); });
    }
}

function sendSuggestion(text) { input.value = text; send(); }

// ── Send message ──────────────────────────────────────────────
async function send() {
    const text = input.value.trim();
    if (!text || loading.value) return;

    messages.value.push({ role: 'user', content: text, time: Date.now() });
    input.value  = '';
    error.value  = '';
    loading.value = true;

    const session = sessions.value.find(s => s.id === currentSessionId.value);
    if (session && session.title === 'New Chat') {
        session.title = text.slice(0, 45) + (text.length > 45 ? '…' : '');
    }
    saveCurrentSession();
    await nextTick(); scrollToBottom(); resetInputHeight();

    try {
        const history = messages.value.slice(0, -1).slice(-14).map(m => ({ role: m.role, content: m.content }));
        const { data } = await axios.post('/school/ai/chat', { message: text, history, page: window.location.pathname });

        messages.value.push({ role: 'assistant', content: data.reply, follow_ups: data.follow_ups ?? [], time: Date.now() });
        saveCurrentSession();
        if (!isOpen.value) unread.value++;
    } catch (e) {
        error.value = e.response?.data?.error ?? 'Something went wrong. Please try again.';
    } finally {
        loading.value = false;
        await nextTick(); scrollToBottom();
    }
}

// ── Copy ─────────────────────────────────────────────────────
async function copyMessage(content, idx) {
    try { await navigator.clipboard.writeText(content); copiedIdx.value = idx; setTimeout(() => copiedIdx.value = null, 1800); } catch {}
}

// ── Helpers ───────────────────────────────────────────────────
function scrollToBottom() { if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight; }
function autoResize(e) { e.target.style.height = 'auto'; e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px'; }
function resetInputHeight() { if (inputEl.value) inputEl.value.style.height = 'auto'; }

function escapeHtml(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function formatMessage(text) {
    if (!text) return '';
    return escapeHtml(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        // Nav paths: **Label** (`/path`) → clickable nav pill (must run before backtick→code)
        .replace(/<strong>(.+?)<\/strong>\s*\(`(\/[^`]+)`\)/g, (_, label, path) => {
            // Only allow safe relative paths (no javascript:, no protocol, no quotes)
            if (!/^\/[\w\-/]*$/.test(path)) return `<strong>${label}</strong>`;
            return `<a href="${path}" class="ai-nav-link"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg><strong>${label}</strong><span class="ai-nav-path">${path} →</span></a>`;
        })
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^•\s(.+)/gm, '<li>$1</li>')
        .replace(/^-\s(.+)/gm, '<li>$1</li>')
        .replace(/^\d+\.\s(.+)/gm, '<li>$1</li>')
        .replace(/(<li>[\s\S]*?<\/li>)+/g, '<ul>$&</ul>')
        .replace(/\n\n/g, '<br><br>')
        .replace(/\n/g, '<br>')
        // Highlight numbers, %, currency
        .replace(/(₹[\d,]+(?:\.\d+)?[KkLl]?|\d+(?:\.\d+)?%|\b\d{3,}(?:,\d{3})*(?:\.\d+)?\b)/g,
            '<span class="ai-num">$1</span>');
}

function formatTime(ts) { if (!ts) return ''; return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }

function formatDateSep(ts) {
    if (!ts) return '';
    const d = new Date(ts), today = new Date(), yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatSessionDate(ts) {
    if (!ts) return '';
    const d = new Date(ts), today = new Date(), yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });
}

function showDateSep(index) {
    if (index === 0) return !!messages.value[0]?.time;
    const prev = messages.value[index - 1], curr = messages.value[index];
    if (!prev?.time || !curr?.time) return false;
    return new Date(prev.time).toDateString() !== new Date(curr.time).toDateString();
}

onMounted(() => {
    loadSessions();
    const lastOpenedAt = localStorage.getItem('erp_ai_last_opened');
    if (lastOpenedAt) {
        unread.value = messages.value.filter(m => m.role === 'assistant' && m.time && m.time > parseInt(lastOpenedAt)).length;
    }
});
</script>

<style scoped>
/* ── Bubble ─────────────────────────────────────────────────── */
.ai-bubble {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    width: 54px; height: 54px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #fff; box-shadow: 0 4px 20px rgba(99,102,241,0.45);
    transition: transform 0.2s, box-shadow 0.2s; position: fixed;
}
.ai-bubble:hover { transform: scale(1.08); box-shadow: 0 6px 28px rgba(99,102,241,0.55); }
.ai-bubble.open  { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
.ai-bubble-icon  { display: flex; align-items: center; justify-content: center; }
.ai-unread {
    position: absolute; top: -4px; right: -4px;
    background: #ef4444; color: #fff; font-size: 11px; font-weight: 700;
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff;
}

/* ── Panel ──────────────────────────────────────────────────── */
.ai-panel {
    position: fixed; bottom: 90px; right: 24px; z-index: 9998;
    width: 400px;
    height: min(600px, calc(100dvh - 90px - 16px));
    max-height: min(600px, calc(100dvh - 90px - 16px));
    background: #fff; border-radius: 20px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.18);
    display: flex; flex-direction: column; overflow: hidden;
    border: 1px solid rgba(99,102,241,0.12);
}
@media (max-width: 440px) {
    .ai-panel { right: 10px; left: 10px; width: auto; bottom: 82px; border-radius: 16px; }
    .ai-bubble { bottom: 16px; right: 16px; }
}

/* ── Header ─────────────────────────────────────────────────── */
.ai-header {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    flex-shrink: 0;
}
.ai-header-top {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 16px 10px;
}
.ai-header-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0;
}
.ai-header-info { flex: 1; min-width: 0; }
.ai-header-name   { color: #fff; font-weight: 700; font-size: 0.92rem; }
.ai-header-status { display: flex; align-items: center; gap: 5px; color: rgba(255,255,255,0.72); font-size: 0.7rem; margin-top: 2px; }
.ai-status-dot    { width: 6px; height: 6px; background: #4ade80; border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.4; } }
.ai-header-actions { display: flex; gap: 4px; }
.ai-hdr-btn {
    background: rgba(255,255,255,0.15); border: none; cursor: pointer; color: #fff;
    width: 30px; height: 30px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s; flex-shrink: 0;
}
.ai-hdr-btn:hover      { background: rgba(255,255,255,0.28); }
.ai-hdr-btn-warn:hover { background: rgba(239,68,68,0.4); }

/* ── Tabs ───────────────────────────────────────────────────── */
.ai-tabs {
    display: flex; gap: 0;
    padding: 0 14px;
    border-top: 1px solid rgba(255,255,255,0.12);
}
.ai-tab {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
    padding: 8px 4px;
    background: none; border: none; cursor: pointer;
    color: rgba(255,255,255,0.6); font-size: 0.73rem; font-weight: 600;
    border-bottom: 2px solid transparent;
    transition: color 0.15s, border-color 0.15s;
    letter-spacing: 0.01em;
}
.ai-tab:hover  { color: rgba(255,255,255,0.85); }
.ai-tab.active { color: #fff; border-bottom-color: #fff; }

/* ── Messages ───────────────────────────────────────────────── */
.ai-messages {
    flex: 1; overflow-y: auto; padding: 14px;
    display: flex; flex-direction: column; gap: 10px;
    scroll-behavior: smooth;
}
.ai-messages::-webkit-scrollbar { width: 4px; }
.ai-messages::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }

/* ── Welcome ─────────────────────────────────────────────────── */
.ai-welcome { text-align: center; padding: 16px 10px; }
.ai-welcome-icon  { font-size: 2.2rem; margin-bottom: 8px; }
.ai-welcome-title { font-weight: 700; font-size: 0.95rem; color: #1e293b; }
.ai-welcome-sub   { font-size: 0.78rem; color: #64748b; margin-top: 3px; }
.ai-suggestions   { display: flex; flex-direction: column; gap: 5px; margin-top: 14px; }
.ai-suggestion {
    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 8px 12px; font-size: 0.79rem; color: #475569; cursor: pointer;
    text-align: left; transition: background 0.15s, border-color 0.15s;
}
.ai-suggestion:hover { background: #ede9fe; border-color: #c4b5fd; color: #6d28d9; }
.ai-page-hint { font-size: 0.63rem; color: #cbd5e1; margin-top: 8px; }

/* ── Date separator ─────────────────────────────────────────── */
.ai-date-sep { text-align: center; margin: 2px 0; }
.ai-date-sep span { font-size: 0.65rem; color: #94a3b8; background: #f1f5f9; padding: 2px 10px; border-radius: 20px; }

/* ── Message bubbles ─────────────────────────────────────────── */
.ai-msg          { display: flex; align-items: flex-end; gap: 7px; }
.ai-msg.user     { flex-direction: row-reverse; }
.ai-msg-avatar   {
    width: 26px; height: 26px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0;
}
.ai-msg-wrap            { display: flex; flex-direction: column; max-width: 80%; }
.ai-msg.user .ai-msg-wrap { align-items: flex-end; }
.ai-msg.assistant .ai-msg-wrap { align-items: flex-start; }

.ai-msg-bubble-row { display: flex; align-items: flex-start; gap: 4px; position: relative; }
.ai-msg-bubble {
    padding: 9px 12px; border-radius: 16px;
    font-size: 0.83rem; line-height: 1.55; word-break: break-word;
}
.ai-msg.user .ai-msg-bubble {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff; border-bottom-right-radius: 4px;
}
.ai-msg.assistant .ai-msg-bubble {
    background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 4px;
}
.ai-msg-bubble :deep(ul)     { padding-left: 16px; margin: 4px 0; }
.ai-msg-bubble :deep(li)     { margin: 2px 0; }
.ai-msg-bubble :deep(p)      { margin: 3px 0; }
.ai-msg-bubble :deep(strong) { font-weight: 700; }
.ai-msg-bubble :deep(code)   { background: rgba(0,0,0,0.08); padding: 1px 4px; border-radius: 3px; font-size: 0.82em; font-family: monospace; }
.ai-msg-bubble :deep(.ai-num) {
    display: inline-block; background: rgba(99,102,241,0.14); color: #4f46e5;
    font-weight: 700; padding: 0 4px; border-radius: 4px; font-size: 0.87em;
}
.ai-msg.user .ai-msg-bubble :deep(.ai-num) { background: rgba(255,255,255,0.25); color: #fff; }
.ai-msg-bubble :deep(.ai-nav-link) {
    display: inline-flex; align-items: center; gap: 5px;
    color: #4f46e5; text-decoration: none; font-weight: 600;
    background: #ede9fe; border: 1px solid #c4b5fd;
    padding: 4px 10px 4px 7px; border-radius: 8px;
    font-size: 0.8em; transition: background 0.15s, border-color 0.15s;
    white-space: nowrap; margin: 2px 0; vertical-align: middle;
}
.ai-msg-bubble :deep(.ai-nav-link:hover) { background: #ddd6fe; border-color: #a78bfa; }
.ai-msg-bubble :deep(.ai-nav-link svg) { color: #7c3aed; flex-shrink: 0; }
.ai-msg-bubble :deep(.ai-nav-path) { font-size: 0.77em; color: #7c3aed; font-weight: 500; }

.ai-msg-time { font-size: 0.6rem; color: #cbd5e1; margin-top: 3px; padding: 0 2px; }

/* ── Copy button ─────────────────────────────────────────────── */
.ai-copy-btn {
    width: 22px; height: 22px; background: #f1f5f9; border: 1px solid #e2e8f0;
    border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: #94a3b8; opacity: 0; transition: opacity 0.15s, color 0.15s;
    flex-shrink: 0; margin-top: 4px;
}
.ai-msg-wrap:hover .ai-copy-btn { opacity: 1; }
.ai-copy-btn:hover { color: #6366f1; }

/* ── Follow-up chips ─────────────────────────────────────────── */
.ai-follow-ups  { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.ai-followup-chip {
    background: #ede9fe; border: 1px solid #c4b5fd; border-radius: 20px;
    padding: 4px 10px; font-size: 0.7rem; color: #5b21b6; cursor: pointer;
    white-space: nowrap; transition: background 0.15s;
}
.ai-followup-chip:hover { background: #ddd6fe; }

/* ── Typing indicator ─────────────────────────────────────────── */
.ai-typing { display: flex; align-items: center; gap: 4px; padding: 11px 14px; }
.ai-typing span { width: 6px; height: 6px; background: #94a3b8; border-radius: 50%; animation: bounce 1.2s infinite ease-in-out; }
.ai-typing span:nth-child(2) { animation-delay: 0.2s; }
.ai-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce { 0%,80%,100% { transform:translateY(0); } 40% { transform:translateY(-6px); } }

/* ── Error bar ───────────────────────────────────────────────── */
.ai-error-bar { background: #fee2e2; color: #dc2626; font-size: 0.76rem; padding: 7px 14px; border-top: 1px solid #fecaca; flex-shrink: 0; }

/* ── Input ───────────────────────────────────────────────────── */
.ai-input-wrap {
    display: flex; align-items: flex-end; gap: 8px;
    padding: 10px 14px 8px; border-top: 1px solid #f1f5f9;
    background: #fff; flex-shrink: 0;
}
.ai-input {
    flex: 1; border: 1.5px solid #e2e8f0; border-radius: 12px;
    padding: 9px 12px; font-size: 0.83rem; font-family: inherit;
    resize: none; outline: none; transition: border-color 0.15s;
    line-height: 1.4; max-height: 120px; overflow-y: auto; color: #1e293b;
}
.ai-input:focus        { border-color: #6366f1; }
.ai-input::placeholder { color: #94a3b8; }
.ai-send {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none; border-radius: 10px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0; transition: opacity 0.15s, transform 0.15s;
}
.ai-send:disabled             { opacity: 0.45; cursor: not-allowed; }
.ai-send:not(:disabled):hover { transform: scale(1.05); }
.ai-hint { text-align: center; font-size: 0.65rem; color: #cbd5e1; padding-bottom: 7px; flex-shrink: 0; }

/* ── Browse ERP ──────────────────────────────────────────────── */
.ai-browse { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.ai-browse-search-wrap {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px; border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.ai-browse-search-icon { color: #94a3b8; flex-shrink: 0; }
.ai-browse-search {
    flex: 1; border: none; outline: none; font-size: 0.82rem;
    font-family: inherit; color: #1e293b; background: none;
}
.ai-browse-search::placeholder { color: #94a3b8; }

.ai-browse-list { flex: 1; overflow-y: auto; padding: 8px 0; }
.ai-browse-list::-webkit-scrollbar { width: 4px; }
.ai-browse-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }

.ai-browse-group-label {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
    color: #94a3b8; padding: 10px 16px 4px;
}
.ai-browse-module { margin: 0; }
.ai-browse-module-header {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; background: none; border: none; cursor: pointer;
    font-size: 0.83rem; font-weight: 600; color: #374151;
    transition: background 0.12s; text-align: left;
}
.ai-browse-module-header:hover { background: #f8fafc; }
.ai-browse-chevron { color: #94a3b8; transition: transform 0.2s; flex-shrink: 0; }
.ai-browse-module-header.expanded .ai-browse-chevron { transform: rotate(180deg); }

.ai-browse-children { padding: 0 8px 4px 28px; display: flex; flex-direction: column; gap: 1px; }
.ai-browse-link {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 10px; border-radius: 8px; text-decoration: none;
    font-size: 0.8rem; color: #475569;
    transition: background 0.12s, color 0.12s;
}
.ai-browse-link:hover { background: #ede9fe; color: #5b21b6; }
.ai-browse-link-dot { width: 5px; height: 5px; border-radius: 50%; background: #c4b5fd; flex-shrink: 0; }

.ai-browse-direct-link {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; text-decoration: none;
    font-size: 0.83rem; font-weight: 600; color: #374151;
    transition: background 0.12s, color 0.12s;
}
.ai-browse-direct-link:hover { background: #f8fafc; color: #6366f1; }
.ai-browse-arrow { color: #94a3b8; flex-shrink: 0; }
.ai-browse-direct-link:hover .ai-browse-arrow { color: #6366f1; }
.ai-browse-empty { text-align: center; color: #94a3b8; font-size: 0.82rem; padding: 40px 20px; }

/* ── History ─────────────────────────────────────────────────── */
.ai-history { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
.ai-history::-webkit-scrollbar { width: 4px; }
.ai-history::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }
.ai-history-new {
    display: flex; align-items: center; gap: 10px; padding: 12px 16px;
    font-size: 0.83rem; font-weight: 600; color: #6366f1; cursor: pointer;
    border-bottom: 1px solid #f1f5f9; transition: background 0.15s; flex-shrink: 0;
}
.ai-history-new:hover { background: #f5f3ff; }
.ai-history-new-icon {
    width: 26px; height: 26px; border-radius: 7px; background: #ede9fe;
    display: flex; align-items: center; justify-content: center; color: #6366f1;
}
.ai-history-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; color: #94a3b8; padding: 40px 20px; text-align: center; font-size: 0.82rem; }
.ai-history-list { display: flex; flex-direction: column; padding: 6px 8px; gap: 2px; }
.ai-history-item {
    display: flex; align-items: center; gap: 10px; padding: 9px 10px;
    border-radius: 10px; cursor: pointer; transition: background 0.15s;
}
.ai-history-item:hover  { background: #f8fafc; }
.ai-history-item.active { background: #ede9fe; }
.ai-history-icon {
    width: 30px; height: 30px; border-radius: 8px; background: #e2e8f0;
    display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0;
}
.ai-history-item.active .ai-history-icon { background: #c4b5fd; color: #5b21b6; }
.ai-history-body  { flex: 1; min-width: 0; }
.ai-history-title { font-size: 0.81rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ai-history-meta  { font-size: 0.68rem; color: #94a3b8; margin-top: 2px; }
.ai-history-del {
    background: none; border: none; cursor: pointer; color: #cbd5e1;
    padding: 3px; border-radius: 4px; display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.15s, color 0.15s; flex-shrink: 0;
}
.ai-history-item:hover .ai-history-del { opacity: 1; }
.ai-history-del:hover { color: #ef4444; }

/* ── Slide transition ────────────────────────────────────────── */
.ai-slide-enter-active, .ai-slide-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.ai-slide-enter-from, .ai-slide-leave-to       { opacity: 0; transform: translateY(16px) scale(0.97); }
</style>
