<?php

/*
|--------------------------------------------------------------------------
| ERP Navigation Map (for AI assistant)
|--------------------------------------------------------------------------
| Lightweight server-side mirror of resources/js/config/sidebar.js used by
| the AI chat bot's `search_routes` tool. Only contains title + route + group
| — no SVG icons or permissions (those live in the JS file for the UI).
|
| Keep this in sync with sidebar.js when major nav changes happen.
*/

return [
    'groups' => [
        'Dashboard' => [
            ['title' => 'Dashboard',                     'route' => '/dashboard'],
            ['title' => 'Analytics Dashboard',           'route' => '/school/analytics'],
            ['title' => 'AI Intelligence Hub',           'route' => '/school/ai/insights'],
        ],

        'Academics' => [
            ['title' => 'Classes',                        'route' => '/school/classes'],
            ['title' => 'Sections',                       'route' => '/school/sections'],
            ['title' => 'Subject Types',                  'route' => '/school/subject-types'],
            ['title' => 'Subjects',                       'route' => '/school/subjects'],
            ['title' => 'Class Subjects (assignment)',    'route' => '/school/class-subjects'],
            ['title' => 'Diary',                          'route' => '/school/academic/diary'],
            ['title' => 'Assignments',                    'route' => '/school/academic/assignments'],
            ['title' => 'Syllabus',                       'route' => '/school/academic/syllabus'],
            ['title' => 'Resources',                      'route' => '/school/academic/resources'],
            ['title' => 'Book List',                      'route' => '/school/academic/book-list'],
            ['title' => 'Periods',                        'route' => '/school/periods'],
            ['title' => 'Timetable',                      'route' => '/school/timetable'],
            ['title' => 'Incharge Assignment',            'route' => '/school/incharge'],
        ],

        'Attendance' => [
            ['title' => 'Mark Attendance',                'route' => '/school/attendance'],
            ['title' => 'Attendance Report',              'route' => '/school/attendance/report'],
            ['title' => 'Staff Attendance',               'route' => '/school/staff-attendance'],
            ['title' => 'Staff Attendance Report',        'route' => '/school/staff-attendance/report'],
        ],

        'Students' => [
            ['title' => 'Registrations',                  'route' => '/school/registrations'],
            ['title' => 'Student Directory',              'route' => '/school/students'],
            ['title' => 'Roll Numbers',                   'route' => '/school/roll-numbers'],
            ['title' => 'Student Leaves',                 'route' => '/school/student-leaves'],
            ['title' => 'Student Leave Types',            'route' => '/school/student-leave-types'],
            ['title' => 'Transfer Certificates',          'route' => '/school/transfer-certificates'],
            ['title' => 'Edit Requests',                  'route' => '/school/edit-requests'],
        ],

        'Examinations' => [
            ['title' => 'Exam Terms',                     'route' => '/school/exam-terms'],
            ['title' => 'Exam Types',                     'route' => '/school/exam-types'],
            ['title' => 'Grading Systems',                'route' => '/school/grading-systems'],
            ['title' => 'Exam Assessments',               'route' => '/school/exam-assessments'],
            ['title' => 'Exam Schedules',                 'route' => '/school/exam-schedules'],
            ['title' => 'Admit Cards',                    'route' => '/school/admit-cards'],
            ['title' => 'Exam Marks (entry)',             'route' => '/school/exam-marks'],
            ['title' => 'Exam Results',                   'route' => '/school/exam-results'],
            ['title' => 'Report Cards',                   'route' => '/school/report-cards'],
            ['title' => 'AI Question Papers',             'route' => '/school/question-papers'],
        ],

        'Finance' => [
            ['title' => 'Fee Collect',                    'route' => '/school/fee/collect'],
            ['title' => 'Fee Structure',                  'route' => '/school/fee/structure'],
            ['title' => 'Fee Groups',                     'route' => '/school/fee/groups'],
            ['title' => 'Fee Concessions',                'route' => '/school/fee/concessions'],
            ['title' => 'Fee Due Report',                 'route' => '/school/finance/due-report'],
            ['title' => 'Day Book',                       'route' => '/school/finance/day-book'],
            ['title' => 'Finance Reports',                'route' => '/school/finance/reports'],
            ['title' => 'Expenses',                       'route' => '/school/expenses'],
            ['title' => 'Ledgers',                        'route' => '/school/finance/ledgers'],
            ['title' => 'Finance Transactions',           'route' => '/school/finance/transactions'],
            ['title' => 'Trial Balance',                  'route' => '/school/finance/statements/trial-balance'],
            ['title' => 'Profit & Loss',                  'route' => '/school/finance/statements/profit-loss'],
            ['title' => 'Balance Sheet',                  'route' => '/school/finance/statements/balance-sheet'],
            ['title' => 'Budgets',                        'route' => '/school/finance/budgets'],
            ['title' => 'GL Auto-posting Config',         'route' => '/school/finance/gl-config'],
        ],

        'HR' => [
            ['title' => 'Departments',                    'route' => '/school/departments'],
            ['title' => 'Designations',                   'route' => '/school/designations'],
            ['title' => 'Staff Directory',                'route' => '/school/staff'],
            ['title' => 'Leaves',                         'route' => '/school/leaves'],
            ['title' => 'Leave Types',                    'route' => '/school/leave-types'],
            ['title' => 'Payroll',                        'route' => '/school/payroll'],
        ],

        'Front Office' => [
            ['title' => 'Front Office Dashboard',         'route' => '/school/front-office'],
            ['title' => 'Visitors',                       'route' => '/school/front-office/visitors'],
            ['title' => 'Gate Passes',                    'route' => '/school/front-office/gate-passes'],
            ['title' => 'Gate Pass QR Scanner',           'route' => '/school/front-office/gate-passes/scanner'],
            ['title' => 'Complaints',                     'route' => '/school/front-office/complaints'],
            ['title' => 'Call Logs',                      'route' => '/school/front-office/call-logs'],
            ['title' => 'Call Log Follow-ups',            'route' => '/school/front-office/call-logs-follow-ups'],
            ['title' => 'Correspondence',                 'route' => '/school/front-office/correspondence'],
            ['title' => 'Daily Report',                   'route' => '/school/front-office/daily-report'],
        ],

        'Hostel' => [
            ['title' => 'Hostel Dashboard',               'route' => '/school/hostel'],
            ['title' => 'Hostels',                        'route' => '/school/hostel/hostels'],
            ['title' => 'Rooms',                          'route' => '/school/hostel/rooms'],
            ['title' => 'Allocations',                    'route' => '/school/hostel/allocations'],
            ['title' => 'Hostel Gate Passes',             'route' => '/school/hostel/gate-passes'],
            ['title' => 'Hostel Visitors',                'route' => '/school/hostel/visitors'],
            ['title' => 'Mess',                           'route' => '/school/hostel/mess'],
            ['title' => 'Roll Call',                      'route' => '/school/hostel/roll-call'],
            ['title' => 'Roll Call Report',               'route' => '/school/hostel/roll-call/report'],
            ['title' => 'Hostel Complaints',              'route' => '/school/hostel/complaints'],
            ['title' => 'Meal Report',                    'route' => '/school/hostel/mess/meal-report'],
        ],

        'Transport' => [
            ['title' => 'Transport Dashboard',            'route' => '/school/transport'],
            ['title' => 'Routes',                         'route' => '/school/transport/routes'],
            ['title' => 'Vehicles',                       'route' => '/school/transport/vehicles'],
            ['title' => 'Allocations',                    'route' => '/school/transport/allocations'],
            ['title' => 'Bus Roll Call',                  'route' => '/school/transport/attendance'],
            ['title' => 'Route Report',                   'route' => '/school/transport/reports/route-report'],
            ['title' => 'Transport Fee Defaulters',       'route' => '/school/transport/reports/fee-defaulters'],
            ['title' => 'Live Tracking',                  'route' => '/school/transport/live'],
            ['title' => 'Driver Tracking',                'route' => '/school/transport/driver-tracking'],
        ],

        'Communication' => [
            ['title' => 'Communication Dashboard',        'route' => '/school/communication/dashboard'],
            ['title' => 'Announcements',                  'route' => '/school/communication/announcements'],
            ['title' => 'Emergency Broadcast',            'route' => '/school/communication/emergency'],
            ['title' => 'Communication Logs',             'route' => '/school/communication/logs'],
            ['title' => 'Communication Analytics',        'route' => '/school/communication/analytics'],
            ['title' => 'Email Templates',                'route' => '/school/communication/email-templates'],
            ['title' => 'Scheduled Messages',             'route' => '/school/communication/scheduled'],
            ['title' => 'Social Buzz',                    'route' => '/school/communication/social-buzz'],
            ['title' => 'SMS Config',                     'route' => '/school/communication/config/sms'],
            ['title' => 'WhatsApp Config',                'route' => '/school/communication/config/whatsapp'],
            ['title' => 'Voice Config',                   'route' => '/school/communication/config/voice'],
            ['title' => 'SMS Templates',                  'route' => '/school/communication/templates/sms'],
            ['title' => 'WhatsApp Templates',             'route' => '/school/communication/templates/whatsapp'],
            ['title' => 'Voice Templates',                'route' => '/school/communication/templates/voice'],
            ['title' => 'Push Templates',                 'route' => '/school/communication/templates/push'],
            ['title' => 'Internal Chat',                  'route' => '/school/chat'],
            ['title' => 'Holidays',                       'route' => '/school/holidays'],
        ],

        'Settings' => [
            ['title' => 'General Configuration',          'route' => '/school/settings/general-config'],
            ['title' => 'Asset Configuration',            'route' => '/school/settings/asset-config'],
            ['title' => 'System Configuration',           'route' => '/school/settings/system-config'],
            ['title' => 'Mobile QR',                      'route' => '/school/settings/mobile-qr'],
            ['title' => 'Academic Years',                 'route' => '/school/academic-years'],
            ['title' => 'Rollover Wizard',                'route' => '/school/settings/rollover'],
            ['title' => 'Custom Fields',                  'route' => '/school/custom-fields'],
            ['title' => 'Number Formats',                 'route' => '/school/settings/number-formats'],
            ['title' => 'Roles & Permissions',            'route' => '/school/roles-permissions'],
            ['title' => 'User Login Management',          'route' => '/school/users'],
            ['title' => 'Activity Log',                   'route' => '/school/utility/activity-log'],
            ['title' => 'Error Log',                      'route' => '/school/utility/error-log'],
        ],
    ],
];
