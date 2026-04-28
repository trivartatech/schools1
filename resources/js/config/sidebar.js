/**
 * sidebar.js \u2014 Central sidebar configuration
 * \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
 * Every menu item MUST declare a `permission` (exact Spatie name) OR a
 * `permissions` array (any-match). Items with neither always show.
 *
 * Groups with `children` are collapsed menus.
 * Groups are hidden when NONE of their children are visible.
 *
 * Special key `alwaysShow: true` bypasses permission checks (e.g. Dashboard).
 *
 * group: string \u2014 groups items with a thin divider + label
 */

export const SIDEBAR_MENU = [
    // \u2500\u2500 Always visible \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
    {
        id: 'dashboard',
        title: 'Dashboard',
        route: '/dashboard',
        alwaysShow: true,
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>`,
    },

    // —— ADMINISTRATION ——————————————————————————————————————————————————————————————————————
    {
        id: 'analytics',
        title: 'Analytics Dashboard',
        group: 'Administration',
        route: '/school/analytics',
        permission: 'view_reports',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>`,
    },
    {
        id: 'alumni',
        title: 'Alumni',
        group: 'Administration',
        route: '/school/alumni',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>`,
    },
    {
        id: 'academic_structure',
        title: 'Academic Structure',
        group: 'Administration',
        permission: 'view_classes',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>`,
        children: [
            { title: 'Classes',         route: '/school/classes',        permission: 'view_classes' },
            { title: 'Sections',        route: '/school/sections',       permission: 'view_classes' },
            { title: 'Subject Types',   route: '/school/subject-types',  permission: 'view_classes' },
            { title: 'Subjects',        route: '/school/subjects',       permission: 'view_classes' },
            { title: 'Assign Subjects', route: '/school/class-subjects', permission: 'view_classes' },
        ],
    },
    {
        id: 'academic_resources',
        title: 'Academic Resources',
        group: 'Administration',
        permission: 'view_academic',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>`,
        children: [
            { title: 'Student Diary',     route: '/school/academic/diary',       permission: 'view_academic' },
            { title: 'Assignments',       route: '/school/academic/assignments', permission: 'view_academic' },
            { title: 'Syllabus Tracker',  route: '/school/academic/syllabus',    permission: 'view_academic' },
            { title: 'Digital Resources', route: '/school/academic/resources',   permission: 'view_academic' },
            { title: 'Book List',         route: '/school/academic/book-list',   permission: 'view_academic' },
        ],
    },
    {
        id: 'houses',
        title: 'Student Houses',
        group: 'Administration',
        permission: 'view_houses',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>`,
        children: [
            { title: 'Manage Houses',  route: '/school/houses',             permission: 'view_houses' },
            { title: 'Leaderboard',    route: '/school/houses/leaderboard', permission: 'view_houses' },
        ],
    },
    {
        id: 'students',
        title: 'Student Management',
        group: 'Administration',
        permission: 'view_students',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>`,
        children: [
            { title: 'Registrations',         route: '/school/registrations',          permission: 'create_students' },
            { title: 'Students Directory',    route: '/school/students',               permission: 'view_students' },
            { title: 'Student Leaves',        route: '/school/student-leaves',         permission: 'view_student_leaves' },
            { title: 'Student Leave Types',   route: '/school/student-leave-types',    permission: 'view_students' },
            { title: 'Roll Numbers',          route: '/school/roll-numbers',           permission: 'view_students' },
            { title: 'Transfer Certificates', route: '/school/transfer-certificates',  permission: 'view_students' },
            { title: 'Disciplinary Records',  route: '/school/disciplinary',           permission: 'view_students' },
        ],
    },
    {
        id: 'attendance',
        title: 'Attendance',
        group: 'Administration',
        permission: 'view_attendance',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>`,
        children: [
            { title: 'Mark Attendance',      route: '/school/attendance',           permission: 'create_attendance' },
            { title: 'Attendance Report',    route: '/school/attendance/report',    permission: 'view_attendance' },
            { title: 'Date-wise Report',     route: '/school/attendance/date-wise', permission: 'view_attendance' },
            { title: 'Attendance Forecast',  route: '/school/attendance/forecast',  permission: 'view_attendance' },
        ],
    },
    {
        id: 'examinations',
        title: 'Examinations',
        group: 'Administration',
        permission: 'view_exam',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>`,
        children: [
            { title: 'Exam Terms',       route: '/school/exam-terms',       permission: 'view_exam' },
            { title: 'Exam Types',       route: '/school/exam-types',       permission: 'view_exam' },
            { title: 'Exam Grades',      route: '/school/grading-systems',  permission: 'view_exam' },
            { title: 'Exam Assessment',  route: '/school/exam-assessments', permission: 'view_exam' },
            { title: 'Exam Schedule',    route: '/school/exam-schedules',   permission: 'view_exam' },
            { title: 'Admit Cards',      route: '/school/admit-cards',      permission: 'view_exam' },
            { title: 'Marks Entry',      route: '/school/exam-marks',       permission: 'edit_exam' },
            { title: 'Results',          route: '/school/exam-results',       permission: 'view_exam' },
            { title: 'Mark Summary',     route: '/school/exam-mark-summary', permission: 'view_exam' },
            { title: 'Report Cards',     route: '/school/report-cards',       permission: 'view_exam' },
            { title: 'AI Question Paper', route: '/school/question-papers', permission: 'view_exam' },
        ],
    },
    {
        id: 'schedule',
        title: 'Schedule',
        group: 'Administration',
        permission: 'view_schedule',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>`,
        children: [
            { title: 'Periods',       route: '/school/periods',    permission: 'view_schedule' },
            { title: 'Timetable',     route: '/school/timetable',  permission: 'view_schedule' },
            { title: 'PTM Sessions',  route: '/school/ptm',        permission: 'view_schedule' },
        ],
    },

    // \u2500\u2500 FINANCE \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
    {
        id: 'finance',
        title: 'Finance & Fees',
        group: 'Finance',
        permission: 'view_fee',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>`,
        children: [
            { title: 'Collect Fee',        route: '/school/fee/collect',                   permission: 'view_fee' },
            { title: 'Fee Structure',      route: '/school/fee/structure',                 permission: 'view_fee' },
            { title: 'Due Report',         route: '/school/finance/due-report',            permission: 'view_reports' },
            { title: 'Expenses',           route: '/school/expenses',                      permission: 'view_expense' },
            { title: 'Expense Categories', route: '/school/expense-categories',            permission: 'view_expense' },
            { title: 'Day Book',           route: '/school/finance/day-book',              permission: 'view_reports' },
            { title: 'Fee Summary',        route: '/school/finance/fee-summary',           permission: 'view_reports' },
            { title: 'Financial Reports',  route: '/school/finance/reports',               permission: 'view_reports' },
            { title: 'Groups & Heads',     route: '/school/fee/groups',                    permission: 'create_fee' },
            { title: 'Concessions',        route: '/school/fee/concessions',               permission: 'create_fee' },
            { title: 'Receipt Settings',   route: '/school/fee/config',                    permission: 'edit_fee' },
            // ── Accounting Ledger ──
            { title: 'Ledger Types',       route: '/school/finance/ledger-types',          permission: 'view_fee' },
            { title: 'Chart of Accounts',  route: '/school/finance/ledgers',               permission: 'view_fee' },
            { title: 'Transactions',       route: '/school/finance/transactions',           permission: 'view_fee' },
            // ── Financial Statements ──
            { title: 'Trial Balance',      route: '/school/finance/statements/trial-balance', permission: 'view_reports' },
            { title: 'Profit & Loss',      route: '/school/finance/statements/profit-loss',   permission: 'view_reports' },
            { title: 'Balance Sheet',      route: '/school/finance/statements/balance-sheet', permission: 'view_reports' },
            // ── Finance Config ──
            { title: 'Budget Management',  route: '/school/finance/budgets',                  permission: 'view_fee' },
            { title: 'GL Auto-Posting',    route: '/school/finance/gl-config',                permission: 'edit_fee' },
        ],
    },

    // \u2500\u2500 HR & STAFF \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
    {
        id: 'hr',
        title: 'Staff & HR',
        group: 'HR',
        permission: 'view_staff',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>`,
        children: [
            { title: 'Departments',         route: '/school/departments',             permission: 'view_staff' },
            { title: 'Designations',        route: '/school/designations',            permission: 'view_staff' },
            { title: 'Staff Directory',     route: '/school/staff',                   permission: 'view_staff' },
            { title: 'Staff Attendance',    route: '/school/staff-attendance',        permission: 'view_staff' },
            { title: 'Attendance Report',   route: '/school/staff-attendance/report', permission: 'view_staff' },
            { title: 'Leave Management',    route: '/school/leaves',                  permission: 'view_staff' },
            { title: 'Leave Types',         route: '/school/leave-types',             permission: 'view_staff' },
            { title: 'Payroll',             route: '/school/payroll',                 permission: 'view_payroll' },
            { title: 'Incharge Assignment', route: '/school/incharge',                permission: 'view_staff' },
            { title: 'Staff History Log',   route: '/school/staff-history',           permission: 'view_settings' },
        ],
    },

    // \u2500\u2500 OPERATIONS \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
    {
        id: 'inventory',
        title: 'Inventory & Assets',
        group: 'Operations',
        route: '/school/inventory',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 10V7m-8 3l8 4"/>`,
    },
    {
        id: 'front_office',
        title: 'Front Office',
        group: 'Operations',
        permission: 'view_front_office',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>`,
        children: [
            { title: 'Dashboard',       route: '/school/front-office',               permission: 'view_front_office' },
            { title: 'Visitor Log',     route: '/school/front-office/visitors',      permission: 'view_front_office' },
            { title: 'Gate Passes',     route: '/school/front-office/gate-passes',   permission: 'view_front_office' },
            { title: 'QR Scanner',      route: '/school/front-office/gate-passes/scanner', permission: 'view_front_office' },
            { title: 'Complaints',      route: '/school/front-office/complaints',    permission: 'view_front_office' },
            { title: 'Call Logs',       route: '/school/front-office/call-logs',     permission: 'view_front_office' },
            { title: 'Follow-Ups',      route: '/school/front-office/call-logs-follow-ups', permission: 'view_front_office' },
            { title: 'Correspondence',  route: '/school/front-office/correspondence', permission: 'view_front_office' },
            { title: 'Daily Report',    route: '/school/front-office/daily-report',  permission: 'view_front_office' },
        ],
    },
    {
        id: 'hostel',
        title: 'Hostel',
        group: 'Operations',
        permission: 'view_hostel',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>`,
        children: [
            { title: 'Dashboard',           route: '/school/hostel',              permission: 'view_hostel' },
            { title: 'Manage Hostels',      route: '/school/hostel/hostels',      permission: 'create_hostel' },
            { title: 'Rooms & Beds',        route: '/school/hostel/rooms',        permission: 'create_hostel' },
            { title: 'Student Allocations', route: '/school/hostel/allocations',  permission: 'view_hostel' },
            { title: 'Fee Collection',      route: '/school/hostel/fees',         permission: 'view_hostel' },
            { title: 'Gate Passes',         route: '/school/hostel/gate-passes',  permission: 'view_hostel' },
            { title: 'Visitor Logs',        route: '/school/hostel/visitors',     permission: 'view_hostel' },
            { title: 'Mess Menu',           route: '/school/hostel/mess',         permission: 'view_hostel' },
            { title: 'Roll Call',           route: '/school/hostel/roll-call',    permission: 'view_hostel' },
            { title: 'Roll Call Report',    route: '/school/hostel/roll-call/report', permission: 'view_hostel' },
            { title: 'Complaints',          route: '/school/hostel/complaints',   permission: 'view_hostel' },
            { title: 'Meal Report',         route: '/school/hostel/mess/meal-report', permission: 'view_hostel' },
        ],
    },
    {
        id: 'transport',
        title: 'Transport',
        group: 'Operations',
        permissions: ['view_transport_vehicles', 'view_transport_routes', 'view_transport_allocations', 'view_transport_tracking'],
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>`,
        children: [
            { title: 'Dashboard',          route: '/school/transport',              permission: 'view_transport_vehicles' },
            { title: 'Routes & Stops',     route: '/school/transport/routes',       permission: 'view_transport_routes' },
            { title: 'Vehicles',           route: '/school/transport/vehicles',     permission: 'view_transport_vehicles' },
            { title: 'Student Allocation', route: '/school/transport/allocations',  permission: 'view_transport_allocations' },
            { title: 'Fee Collection',     route: '/school/transport/fees',         permission: 'view_transport_allocations' },
            { title: 'Bus Roll Call',      route: '/school/transport/attendance',   permission: 'view_transport_tracking' },
            { title: 'Route Report',       route: '/school/transport/reports/route-report',    permission: 'view_transport_routes' },
            { title: 'Fee Defaulters',     route: '/school/transport/reports/fee-defaulters',  permission: 'view_transport_allocations' },
            { title: 'Live Tracking',      route: '/school/transport/live',         permission: 'view_transport_tracking' },
            { title: 'Driver Tracking',   route: '/school/transport/driver-tracking', permission: 'view_transport_tracking' },
        ],
    },

    // \u2500\u2500 COMMUNICATION \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
    {
        id: 'communication',
        title: 'Communication',
        group: 'Communication',
        permission: 'view_communication',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>`,
        children: [
            { title: 'Dashboard',              route: '/school/communication/dashboard',               permission: 'view_communication' },
            { title: 'Announcements',          route: '/school/communication/announcements',           permission: 'view_communication' },
            { title: 'Emergency Broadcast',    route: '/school/communication/emergency',               permission: 'create_communication' },
            { title: 'Message Logs',           route: '/school/communication/logs',                    permission: 'view_communication' },
            { title: 'Delivery Analytics',     route: '/school/communication/analytics',               permission: 'view_communication' },
            { title: 'Email Templates',        route: '/school/communication/email-templates',         permission: 'view_communication' },
            { title: 'Scheduled Queue',        route: '/school/communication/scheduled',               permission: 'view_communication' },
            { title: 'Social Buzz',            route: '/school/communication/social-buzz',             permission: 'view_communication' },
            { title: 'Notification Config',    route: '/school/communication/config/notifications',    permission: 'view_settings' },
            { title: 'SMS Config',             route: '/school/communication/config/sms',              permission: 'view_settings' },
            { title: 'WhatsApp Config',        route: '/school/communication/config/whatsapp',         permission: 'view_settings' },
            { title: 'Voice Config',           route: '/school/communication/config/voice',            permission: 'view_settings' },
            { title: 'SMS Templates',          route: '/school/communication/templates/sms',           permission: 'view_settings' },
            { title: 'WhatsApp Templates',     route: '/school/communication/templates/whatsapp',      permission: 'view_settings' },
            { title: 'Voice Templates',        route: '/school/communication/templates/voice',         permission: 'view_settings' },
            { title: 'Push Templates',         route: '/school/communication/templates/push',          permission: 'view_settings' },
        ],
    },
    {
        id: 'ai-insights',
        title: 'AI Intelligence',
        group: 'Communication',
        route: '/school/ai/insights',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>`,
    },
    {
        id: 'chat',
        title: 'Chat',
        group: 'Communication',
        route: '/school/chat',
        permission: 'view_chat',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>`,
    },
    {
        id: 'holidays',
        title: 'Holidays & Events',
        group: 'Communication',
        route: '/school/holidays',
        permission: 'view_communication',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>`,
    },

    // \u2500\u2500 SETTINGS \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
    {
        id: 'settings',
        title: 'Settings & Setup',
        group: 'Settings',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>`,
        children: [
            { title: 'General Config',   route: '/school/settings/general-config',   permission: 'view_settings' },
            { title: 'Asset Config',     route: '/school/settings/asset-config',     permission: 'view_settings' },
            { title: 'System Config',    route: '/school/settings/system-config',    permission: 'view_settings' },
            { title: 'Mobile App QR',    route: '/school/settings/mobile-qr',        permission: 'view_settings' },
            { title: 'Academic Years',   route: '/school/academic-years',            permission: 'view_settings' },
            { title: 'Setup Wizard',     route: '/school/settings/rollover',         permission: 'view_settings' },
            { title: 'Custom Fields',    route: '/school/custom-fields',             permission: 'view_settings' },
            { title: 'Number Formats',   route: '/school/settings/number-formats',   permission: 'view_settings' },
            { title: 'Edit Requests',    route: '/school/edit-requests',             permission: 'view_settings' },
        ],
    },
    {
        id: 'roles',
        title: 'Roles & Permissions',
        group: 'Settings',
        route: '/school/roles-permissions',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>`,
    },
    {
        id: 'user_management',
        title: 'User Login Management',
        group: 'Settings',
        route: '/school/users',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>`,
    },

    // —— PORTAL (Parent/Student) ——————————————————————————————————————————————————————
    {
        id: 'portal_fees',
        title: 'Fee Payment',
        group: 'Portal',
        permission: 'view_own_fee',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>`,
        children: [
            { title: 'Pay Fees',         route: '/portal/fees',         permission: 'view_own_fee' },
            { title: 'Payment History',  route: '/portal/fees/history', permission: 'view_own_fee' },
        ],
    },
    {
        id: 'portal_ptm',
        title: 'PTM Booking',
        group: 'Portal',
        route: '/school/ptm/parent/view',
        permission: 'view_own_fee',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>`,
    },
    {
        id: 'portal_hostel_gp',
        title: 'My Gate Passes',
        group: 'Portal',
        route: '/school/hostel/my-gate-passes',
        permission: 'view_hostel',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>`,
    },

    // —— BACKUP ———————————————————————————————————————————————————————————————————————
    {
        id: 'backup',
        title: 'Backup Manager',
        group: 'Settings',
        route: '/school/backup',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>`,
    },

    // —— UTILITIES ——————————————————————————————————————————————————————————————————————
    {
        id: 'utility',
        title: 'Utility',
        group: 'Utilities',
        permission: 'view_settings',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.628.282a2 2 0 01-1.154 0l-.628-.282a6 6 0 00-3.86-.517l-2.387.477.1.1a2 2 0 001.022.547l2.387.477a6 6 0 003.86-.517l.628-.282a2 2 0 011.154 0l.628.282a6 6 0 003.86.517l2.387-.477z" />`,
        children: [
            { title: 'ID Cards',     route: '/school/utility/id-cards',        permission: 'view_students' },
            { title: 'Certificates', route: '/school/utility/certificates',    permission: 'view_students' },
            { title: 'Activity Log', route: '/school/utility/activity-log',    permission: 'view_settings' },
            { title: 'Error Log',    route: '/school/utility/error-log',    permission: 'view_settings' },
        ],
    },
];
