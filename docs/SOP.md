# School's 1 — School Admin Operating Manual

*A Trivartha Tech Pvt Ltd product*

**Version:** 1.0
**Date:** 2026-04-29
**Audience:** School Admin (the operator of the ERP)

---

## Who This Manual Is For

You are the **school admin** — the person who runs School's 1 day-to-day at
your school. You hold the `admin`, `school_admin`, `principal`, or `hr` role
(any of which gives you unrestricted access). You set up the academic year,
admit students, oversee fee collection, schedule exams, run payroll, send
announcements, and publish report cards.

You are *not* expected to be an IT person or a developer. This manual contains
only what you can do from the screen — every action is described as
**Sidebar → Menu → Button → Outcome**.

If something needs server access, code changes, or database surgery, you'll
see a note that says *"call IT"* — that's the boundary of this manual.

## How to Use This Manual

Three reading paths, depending on why you opened this:

1. **Setting the system up for the first time?** → Read **Part 1: First-Time
   Setup** in order. Skip nothing — later steps assume earlier ones are done.
2. **Need to do a specific task today?** → Jump to **Part 2: Module-by-Module
   Operations** via the table of contents.
3. **Want to know what to do this week / month / quarter / year?** → Use
   **Part 3: Daily / Weekly / Monthly / Annual Rhythm** as a checklist.

If something breaks, **Part 4: Troubleshooting** lists the everyday problems
you can fix yourself before calling IT.

---

## Table of Contents

- [Glossary](#glossary)
- [Roles You Will Create and Manage](#roles-you-will-create-and-manage)
- **Part 1 — [First-Time Setup](#part-1--first-time-setup)**
  1. [School Profile](#11-school-profile)
  2. [System Configuration](#12-system-configuration)
  3. [Academic Year](#13-academic-year)
  4. [Holiday Calendar](#14-holiday-calendar)
  5. [Classes, Sections, Subjects](#15-classes-sections-subjects)
  6. [Periods & Timetable Shell](#16-periods--timetable-shell)
  7. [Grading System & Exam Terms](#17-grading-system--exam-terms)
  8. [Fee Structure](#18-fee-structure)
  9. [Receipt Print Settings](#19-receipt-print-settings)
  10. [Daily Report Settings](#110-daily-report-settings)
  11. [Communication Channels](#111-communication-channels)
  12. [Roles & Permissions Review](#112-roles--permissions-review)
  13. [Staff Onboarding](#113-staff-onboarding)
  14. [Optional: Transport / Hostel / Stationary](#114-optional-modules)
- **Part 2 — [Module-by-Module Operations](#part-2--module-by-module-operations)**
  - [Dashboard](#21-dashboard)
  - [Academics](#22-academics)
  - [Attendance](#23-attendance)
  - [Students](#24-students)
  - [Examinations](#25-examinations)
  - [Finance](#26-finance)
  - [HR](#27-hr)
  - [Front Office](#28-front-office)
  - [Hostel](#29-hostel)
  - [Transport](#210-transport)
  - [Communication](#211-communication)
  - [Settings](#212-settings)
  - [Parent / Student Portal](#213-parent--student-portal)
  - [Stationary](#214-stationary)
  - [Inventory & Assets](#215-inventory--assets)
  - [Daily Master Report](#216-daily-master-report)
  - [AI Intelligence](#217-ai-intelligence)
  - [Other Modules](#218-other-modules)
- **Part 3 — [Operating Rhythm](#part-3--operating-rhythm)**
  - [Daily Checklist](#daily-checklist)
  - [Weekly Checklist](#weekly-checklist)
  - [Monthly Checklist](#monthly-checklist)
  - [Term / Quarterly](#term--quarterly)
  - [Annual Cycle](#annual-cycle)
  - [Background Jobs](#background-jobs-informational)
- **Part 4 — [Troubleshooting](#part-4--troubleshooting)**
- **Appendices**
  - [A. Roles Cheat-Sheet](#appendix-a--roles-cheat-sheet)
  - [B. Sidebar Map](#appendix-b--sidebar-map)
  - [C. Reports Index](#appendix-c--reports-index)
  - [D. Exports Index](#appendix-d--exports-index)
  - [E. Communication Templates](#appendix-e--communication-templates)
  - [F. Glossary (Expanded)](#appendix-f--glossary-expanded)
  - [G. Change Log](#appendix-g--change-log)

---

## Glossary

| Term | Meaning |
|---|---|
| **Admission No** | Auto-generated unique number assigned to a student when admitted. Format is configured in Settings → Number Formats. |
| **Roll No** | Position number within a class/section. Reset when a student is promoted to the next class. |
| **Academic Year** | A school year (e.g. 2026-27). All students, fees, exams, and attendance are scoped to one academic year. |
| **Fee Head** | A single line item on a fee bill (Tuition, Lab, Library, Dev Fund). |
| **Fee Group** | A bundle of fee heads applied to a class or set of students. |
| **GL Posting** | The accounting entry that records a transaction in the ledger (e.g. fee payment debits Cash, credits Tuition Income). Done automatically. |
| **Fee Defaulter** | A student whose fee has crossed the due date without payment. |
| **Concession** | A waiver or discount applied to a student's fee. |
| **Receipt Copy Types** | Original, Duplicate, Office Copy, Triplicate — labelled copies of a fee receipt printed together. Configured once in Settings → Receipt Print. |
| **Term** | A division of the academic year (Term I / II / III, or Mid / Final). |
| **Grading System** | The mapping of marks to grades (A+, A, B, etc.). |
| **Rollover** | The end-of-year process that promotes students to the next class and copies forward the fee structure. |
| **Edit Request** | A request from a teacher or parent to correct student data. The admin approves or rejects it. |
| **Daily Master Report** | An automated daily summary of attendance, fees, and announcements, sent at a configured time to admin contacts. |
| **AI Insights** | LLM-generated analytics on attendance, fees, and exam data. Rate-limited. |

A fuller glossary is in [Appendix F](#appendix-f--glossary-expanded).

## Roles You Will Create and Manage

School's 1 ships with **21 pre-configured roles**. As school admin you'll
mostly create users and assign one of these roles — you don't normally edit
the permission matrix.

The roles are:

| Role name (system) | Display label | Plain-language description |
|---|---|---|
| `admin` | School Admin | You. Unrestricted school-level access. |
| `school_admin` | School Admin (Alt) | Backup admin — same access as you. |
| `principal` | Principal | Full access; usually the academic head. |
| `hr` | HR Officer | Staff records, leave approval, payroll. |
| `accountant` | Accountant | All fee collection, expenses, payroll view, financial reports. |
| `teacher` | Teacher | Marks attendance, enters exam marks, manages diary/assignments for assigned classes. |
| `student` | Student | Portal-only: own profile, marks, attendance, fees, timetable. |
| `parent` | Parent / Guardian | Portal-only: views their child's data; can pay fees online. |
| `driver` | Driver | Views assigned route/vehicle. |
| `conductor` | Conductor | Views assigned route/vehicle. |
| `transport_manager` | Transport Manager | Manages routes, vehicles, allocations, and transport fee collection. |
| `stationary_manager` | Stationary Manager | Issues stationery, accepts returns, collects stationery fees. |
| `receptionist` | Receptionist | Front-office operations. |
| `front_office` | Front Office | Same as Receptionist. |
| `front_gate_keeper` | Front Gate Keeper | Visitor entry/exit + QR attendance scanner at the gate. |
| `hostel_warden` | Hostel Warden | Manages hostel rooms, students, and approvals; collects hostel fees. |
| `mess_manager` | Mess Manager | Manages hostel mess menu and meal records. |
| `librarian` | Librarian | Library and academic resources access. |
| `nurse` | Nurse / Medical | Views student health records. |
| `auditor` | Auditor | Read-only access to everything plus audit log — for compliance. |
| `it_support` | IT Support | Technical support role for troubleshooting. |
| `super_admin` | Super Admin | Platform-level — usually held by the vendor (Trivartha Tech). Do not assign. |

**Rule of thumb when adding a new staff member:** pick the *narrowest* role
that lets them do their job. Over-permissioning is the most common security
mistake.

---

# Part 1 — First-Time Setup

Do these in order. Steps later in the list assume earlier steps are done.
Each step says *"You'll know it worked when…"* so you can verify before
moving on.

## 1.1 School Profile

**Path:** Sidebar → **Settings & Setup** → **General Configuration**

Set the school's identity. This information appears on receipts, report
cards, ID cards, and certificates — get it right before printing anything.

Fields to fill:

- School name (full legal name)
- Board (CBSE / ICSE / State / IB / etc.) and affiliation number
- UDISE code (Indian schools — leave blank if not applicable)
- Principal's name (appears on certificates)
- Address — building, street, city, state, PIN
- Phone, email, website
- Logo (upload square PNG or JPG; this prints on every receipt)
- Financial year code (e.g. "FY 2026-27")

**You'll know it worked when:** the dashboard header shows the school name
and logo correctly.

**Common mistakes:**
- Logo too large → receipts overflow. Use ≤ 200 KB, square aspect ratio.
- Wrong principal name → reprinting all certificates later. Double-check.

---

## 1.2 System Configuration

**Path:** Sidebar → **Settings & Setup** → **System Configuration**

Set timezone, currency, date/time format. These affect every screen.

Then set number formats — *separately* — at **Settings → Number Formats**.
This controls how Admission No and Roll No are generated (e.g.
`SCH-2026-0001` vs `0001`).

**Do this once.** Changing it after admissions begin creates a mix of old
and new format numbers, which is messy.

**You'll know it worked when:** today's date appears in your chosen format on
the dashboard.

---

## 1.3 Academic Year

**Path:** Sidebar → **Settings & Setup** → **Academic Years**

Create the current academic year (e.g. "2026-27") with start and end dates.
Mark it as the active year.

If you're setting up mid-year, you may also want to create the next academic
year so the Rollover Wizard (Section 1.14, used at year-end) has somewhere to
roll students into.

**You'll know it worked when:** the active year shows in the top bar /
dashboard header. Until you do this, students cannot be admitted, attendance
cannot be marked, and exams cannot be created.

---

## 1.4 Holiday Calendar

**Path:** Sidebar → **Holidays & Events**

Add every public holiday for the year. The system uses this list for two
things:

1. **Daily 00:30 holiday-fill job** — automatically marks students and staff
   as `holiday` in the attendance record (so teachers don't see those
   students on the mark-attendance page on a holiday).
2. **Timetable & exam scheduling** — flagged as conflicts.

Add holidays in bulk; you can mark each as `school holiday`, `optional`, or
`exam holiday`.

**You'll know it worked when:** a known holiday (e.g. Independence Day)
appears in the holiday list and shows up shaded on the timetable view.

**Common mistake:** forgetting state-specific holidays. Re-check at the start
of every term.

---

## 1.5 Classes, Sections, Subjects

You build the academic structure in this order:

### Classes
**Path:** Sidebar → **Academic Structure** → **Classes**

Create every class your school runs (LKG, UKG, 1, 2, …, 12). Drag and drop
to reorder so they display in academic order, not creation order.

### Sections
**Path:** Sidebar → **Academic Structure** → **Sections**

Create sections (A, B, C, …) and assign each to a class.

### Subject Types
**Path:** Sidebar → **Academic Structure** → **Subject Types**

Create the categories: Core, Elective, Co-curricular, Lab, etc.

### Subjects
**Path:** Sidebar → **Academic Structure** → **Subjects**

Create every subject (English, Math, Science, …). Assign a subject type to
each.

### Assign Subjects
**Path:** Sidebar → **Academic Structure** → **Assign Subjects**

Map subjects to class-sections. Set the weight (used by weighted grading) if
applicable.

**You'll know it worked when:** picking a class on the timetable page shows
the assigned subjects.

---

## 1.6 Periods & Timetable Shell

### Periods
**Path:** Sidebar → **Schedule** → **Periods**

Define the period schedule: period 1 starts 08:00 ends 08:45, period 2…
include break periods explicitly. Order them.

### Timetable
**Path:** Sidebar → **Schedule** → **Timetable**

Once periods exist, build the per-section timetable: pick a class-section,
fill each period × day cell with a teacher + subject. The system warns you
if a teacher is double-booked.

You can leave the timetable for later, but **periods must exist before any
exam can be scheduled** (exam slots reference periods).

---

## 1.7 Grading System & Exam Terms

### Grading System
**Path:** Sidebar → **Examinations** → **Exam Grades**

Define your grade scale (A+, A, B, …) with the mark range for each grade. You
can have multiple grading systems (e.g. one for primary, one for secondary).

### Exam Terms
**Path:** Sidebar → **Examinations** → **Exam Terms**

Create the term structure: Term I, Term II, Term III, or Mid-Term, Final.
Each term has a date range.

### Exam Types
**Path:** Sidebar → **Examinations** → **Exam Types**

Create exam types: Unit Test, Mid-Term, Final, etc.

### Exam Assessments
**Path:** Sidebar → **Examinations** → **Exam Assessment**

(Optional, for question-wise marking.) Define assessment templates (Q1/20,
Q2/30) so teachers can enter sub-marks instead of just totals.

**You'll know it worked when:** opening **Exam Schedule** lets you pick
from your terms and types.

---

## 1.8 Fee Structure

Set up fees in this order:

### Fee Heads
**Path:** Sidebar → **Finance & Fees** → (open Fee Structure → Heads section)

Create every line item that appears on a bill: Tuition, Dev Fund, Lab,
Computer Fee, Activity Fee, etc.

### Fee Groups
**Path:** Sidebar → **Finance & Fees** → **Groups & Heads**

Group fee heads into bundles. Typical groups: "Class 1–5 Annual", "Class
6–10 Annual", "Hostel — All Classes".

### Fee Structure
**Path:** Sidebar → **Finance & Fees** → **Fee Structure**

Assign a fee group to each class (or section, or specific student set). Set
amount, frequency (monthly/quarterly/annual), and due date.

### Fee Concessions
**Path:** Sidebar → **Finance & Fees** → **Concessions**

Define concession types (Sibling, Staff Ward, Merit, Need-based) with
percentage or fixed-amount logic.

**You'll know it worked when:** opening **Collect Fee** for a test student
shows the correct amount due and bill breakdown.

---

## 1.9 Receipt Print Settings

**Path:** Sidebar → **Settings & Setup** → **General Config** → click the
**Receipt Print** tab in the page's tab bar.

(Receipt Print is *not* a sidebar item; it lives as a tab on every Settings
page. The tab bar appears at the top of General Config, System Config,
Daily Report, Admin Numbers, and other settings pages — open any of them
and click "Receipt Print".)

This **one setting drives every fee receipt** the system prints — tuition,
hostel fee, transport fee, stationary fee. Configure it once and forget it.

Fields:

- **Paper size** — `A4`, `A5`, or `A6`. Pick what your school's printer
  trays are loaded with. (Default: A4.)
- **Number of copies** — 1 to 4. The system places that many receipts on a
  single page with a cut-line between them, each labelled:

  | Copies | Labels printed |
  |---|---|
  | 1 | Original |
  | 2 | Original, Duplicate |
  | 3 | Original, Duplicate, Office Copy |
  | 4 | Original, Duplicate, Office Copy, Triplicate |

**You'll know it worked when:** collecting any test fee → printing →
the page shows your chosen number of labelled copies on the chosen paper
size.

**Important:** if you change this setting later, *all subsequent receipts*
use the new setting — but already-printed receipts are unchanged.

---

## 1.10 Daily Report Settings

**Path:** Sidebar → **Settings & Setup** → **Daily Report**

Configure the **Daily Master Report** — an automated email/SMS digest sent
to school admin contacts.

Fields:

- **Sections enabled** — toggle which sections appear (attendance summary,
  fee collection, expenses, announcements, etc.).
- **Auto-send time** — time of day in HH:MM (24-hour). The report fires once
  per day at this time.
- **Auto-send enabled** — master switch. Turn on once you've tested.
- **Weekly digest enabled** — adds a Sunday weekly summary on top.
- **Oversized expense threshold** — currency value; expenses above this are
  flagged in the report.
- **Low attendance threshold %** — students below this attendance % are
  flagged.
- **Repeat absent days (2–14)** — students absent this many consecutive
  school days are flagged.

**Pre-requisite:** at least one Admin Contact must exist (Settings →
General Configuration), or the report has nowhere to go.

**You'll know it worked when:** at the configured time tomorrow, the admin
contacts receive the report.

---

## 1.11 Communication Channels

**Path:** Sidebar → **Communication** → **SMS Config / WhatsApp Config /
Voice Config**

For each channel you intend to use:

1. Enter the API credentials (provided by your channel vendor — Msg91 for
   SMS by default, Twilio for voice).
2. Click **Send Test** to a known phone number. Verify it lands.
3. Mark the channel as active.

Then create a few starter templates:

- Sidebar → **Communication** → **SMS Templates**
- Sidebar → **Communication** → **WhatsApp Templates**
- Sidebar → **Communication** → **Voice Templates**
- Sidebar → **Communication** → **Push Templates**
- Sidebar → **Communication** → **Email Templates**

Suggested first templates: *Fee Reminder*, *Absence Alert*, *General
Announcement*, *Holiday Notice*.

**You'll know it worked when:** **Communication → Communication Logs** shows
your test sends as `Delivered`.

---

## 1.12 Roles & Permissions Review

**Path:** Sidebar → **Settings & Setup** → **Roles & Permissions**

The 21 default roles described in [Roles You Will Create and
Manage](#roles-you-will-create-and-manage) are pre-configured with sensible
permissions. You usually don't need to change them.

Review them once to be sure the permission matrix matches your school's
policy (e.g. "should the receptionist see student attendance?"). Make
adjustments only if needed.

**Do not delete the protected system roles** (`super_admin`, `admin`,
`teacher`, `student`, `parent`, `accountant`, `driver`) — the system depends
on them.

---

## 1.13 Staff Onboarding

Create your staff in this order:

### Departments
**Path:** Sidebar → **Staff & HR** → **Departments**

Create departments: Primary, Secondary, Administration, Transport, etc.

### Designations
**Path:** Sidebar → **Staff & HR** → **Designations**

Create designations: PRT, TGT, PGT, Principal, Vice Principal, Accountant,
Driver, etc.

### Leave Types
**Path:** Sidebar → **Staff & HR** → **Leave Types**

Define leave categories with annual quotas: Casual (12), Sick (10), Earned
(15), etc.

### Staff Records
**Path:** Sidebar → **Staff & HR** → **Staff Directory** → **Add Staff**

For each staff member, fill: name, photo, contact, joining date,
qualifications, designation, department, salary structure (basic, HRA, DA,
deductions). The salary fields drive payroll later.

### User Accounts
**Path:** Sidebar → **Settings & Setup** → **User Login Management** → **Add User**

For each staff member who needs to log in, create a user with:

- Email (or username, or phone — login accepts any of these)
- Initial password (they'll change it on first login)
- **Role** (one from the list in [Roles You Will Create and
  Manage](#roles-you-will-create-and-manage))
- Link to their Staff record

**Tip:** for parents/students, IT can run a one-shot bulk-create command
(`portal:create-users`) that auto-creates accounts for every parent/student
record. Ask them after admissions are loaded.

**You'll know it worked when:** the staff member logs in with their
credentials and sees the dashboard appropriate to their role.

---

## 1.14 Optional Modules

If you run hostel, transport, or stationary services, set them up after
core setup is done:

### Transport
- Sidebar → **Transport** → **Routes** — define each route (start stop, end
  stop, intermediate pickup points with times).
- Sidebar → **Transport** → **Vehicles** — register each bus (reg no,
  capacity, driver, conductor).
- Sidebar → **Transport** → **Allocations** — assign students to routes.

### Hostel
- Sidebar → **Hostel** → **Hostels** — create the building(s).
- Sidebar → **Hostel** → **Rooms** — create rooms with capacity.
- Sidebar → **Hostel** → **Allocations** — assign students to beds.
- Sidebar → **Hostel** → **Mess** — set up mess menu and meal plans.

### Stationary
- (Sidebar entry under Finance in Fee Collect → Stationary; or via a
  dedicated stationary route.) Set up items, then allocate to students.

---

# Part 2 — Module-by-Module Operations

This part documents every menu item you'll see in the sidebar. Use the table
of contents above to jump to a module.

> **Note on labels.** Section headings in this part are descriptive
> (e.g. "Hostel Dashboard"), but the **Path:** line under each heading
> always shows the **exact label that appears in the sidebar** (e.g.
> "Sidebar → Hostel → Dashboard"). When in doubt, follow the **Path:**
> line — it's the source of truth. The complete sidebar map with every
> label and route is in [Appendix B](#appendix-b--sidebar-map).

## 2.1 Dashboard

Three landing pages live under Dashboard:

### Dashboard
**Path:** Sidebar → **Dashboard** → **Dashboard**

Your daily landing. Shows:

- **KPIs** — total students, new admissions today, pending fees, today's fee
  collection, attendance %, staff present.
- **Charts** — fee trend, admission trend, attendance donut, class-wise
  attendance, monthly income vs expense.
- **Operational widgets** — pending fee students, low-attendance alerts,
  absent staff, pending leaves, today's birthdays, today's visitors,
  upcoming exams, recent announcements.

Use this every morning as your first stop.

### Analytics Dashboard
**Path:** Sidebar → **Dashboard** → **Analytics Dashboard**

Deeper analytical view: trend lines, drill-downs, period comparisons. Click
any KPI to drill into the underlying data.

### AI Intelligence Hub
**Path:** Sidebar → **Dashboard** → **AI Intelligence Hub**

LLM-generated insights, saved views, ad-hoc queries about your school's
data ("show me classes with declining attendance over the last 30 days",
"top 5 fee defaulters with overdue > 60 days"). Rate-limited; if you hit a
limit, wait one minute and retry.

---

## 2.2 Academics

Thirteen menu items. Most are setup screens you touched in Part 1; here's
how to use them day-to-day.

### Classes
**Path:** Sidebar → **Academic Structure** → **Classes**

List of classes. Add / Edit / Reorder via drag-and-drop. Avoid deleting a
class once students are enrolled — deactivate instead.

### Sections
**Path:** Sidebar → **Academic Structure** → **Sections**

List of sections. Same edit/reorder pattern.

### Subject Types
**Path:** Sidebar → **Academic Structure** → **Subject Types**

Categories used by Subjects.

### Subjects
**Path:** Sidebar → **Academic Structure** → **Subjects**

Master subject list.

### Assign Subjects
**Path:** Sidebar → **Academic Structure** → **Assign Subjects**

Maps subjects → class-sections. Edit weights for weighted grading.

### Student Diary
**Path:** Sidebar → **Academic Resources** → **Student Diary**

Teacher tool — daily diary entries. As admin you'll mostly view diary
completion (which teachers wrote, which didn't).

### Assignments
**Path:** Sidebar → **Academic Resources** → **Assignments**

Teachers create assignments here; students submit via portal. Admin can
filter by class/teacher to monitor activity.

### Syllabus Tracker
**Path:** Sidebar → **Academic Resources** → **Syllabus Tracker**

Chapter-level syllabus tracker. Useful at term-end to confirm what was
covered.

### Digital Resources
**Path:** Sidebar → **Academic Resources** → **Digital Resources**

Learning materials shared with students (PDFs, links, files).

### Book List
**Path:** Sidebar → **Academic Resources** → **Book List**

Per-class book list — printed and given to parents at year start.

### Periods
**Path:** Sidebar → **Schedule** → **Periods**

Period definitions (start time, end time).

### Timetable
**Path:** Sidebar → **Schedule** → **Timetable**

Section-wise timetable editor.

### Incharge Assignment
**Path:** Sidebar → **Staff & HR** → **Incharge Assignment**

Assign class teachers / section in-charges. Affects who sees what students
on attendance / mark entry.

---

## 2.3 Attendance

### Mark Attendance
**Path:** Sidebar → **Attendance** → **Mark Attendance**

Class teachers do this every morning. As admin you'll mostly **monitor**:
go in, pick any class/section/date to verify attendance was actually
marked.

Workflow:
1. Pick **class** → **section** → **date**.
2. Page loads students in roll-no order with photos.
3. For each student, tap **Present**, **Absent**, **Leave**, or
   **Holiday**. (Holiday rows are pre-filled for declared holidays.)
4. Click **Save**.

If your school uses QR-code attendance at the gate, the `mark_attendance_scanner`
permission lets the gate-keeper scan student QR cards instead.

### Attendance Report
**Path:** Sidebar → **Attendance** → **Attendance Report**

Filter by class, section, date range. Export to Excel/CSV/PDF.

### Staff Attendance
**Path:** Sidebar → **Attendance** → **Staff Attendance**

HR or admin marks staff attendance daily. Same Present/Absent/Leave model.

### Staff Attendance Report
**Path:** Sidebar → **Attendance** → **Staff Attendance Report**

Filter and export.

**Common issues:**
- *Student missing from the roster* → check their class/section assignment
  for the *current* academic year (Sidebar → Students → Student Directory).
- *Holiday rows show as Absent* → the daily 00:30 holiday-fill job didn't
  run. Add the holiday and ask IT to re-run the job manually.

---

## 2.4 Students

### Registrations
**Path:** Sidebar → **Student Management** → **Registrations**

Pre-admission applications submitted from the website or by front office.
Review → approve (converts to a Student record) or reject.

### Student Directory
**Path:** Sidebar → **Student Management** → **Students Directory**

The master list. Click **Add Student** to admit:

1. Fill: name, DOB, photo, gender, blood group, mother/father name and
   contacts, address, custom fields.
2. Choose class + section + academic year.
3. Click **Save**.
4. System auto-generates Admission No (per Number Format) and creates
   Parent / Guardian records.
5. If you provided parent email, a Parent portal user is created (default
   password — communicate it to them).

For bulk admission, use the **CSV Import** option (admin-only). Download
the template, fill it, upload.

Other actions on the directory:
- **View** student profile (academic history, fees, attendance, documents).
- **Edit** student details.
- **Deactivate** instead of delete (preserves history).
- **Generate Documents** — admit card, ID card, bonafide, TC.

### Roll Numbers
**Path:** Sidebar → **Student Management** → **Roll Numbers**

Bulk roll-number assignment per section. Sort by name or admission no, then
auto-number.

### Student Leaves
**Path:** Sidebar → **Student Management** → **Student Leaves**

Leave applications submitted by students/parents. Review → approve /
reject. Approved leaves are reflected on attendance.

### Student Leave Types
**Path:** Sidebar → **Student Management** → **Student Leave Types**

Master list (Medical, Family, Religious, etc.).

### Transfer Certificates
**Path:** Sidebar → **Student Management** → **Transfer Certificates**

Issue a TC when a student leaves the school. Fill the form (reason, last
attendance date, conduct remark) → generate PDF → print on letterhead.

### Edit Requests
**Path:** Sidebar → **Student Management** → **Edit Requests**

Teachers / parents can request corrections to student data. As admin you
review the proposed change → approve (it applies) or reject (with reason).

---

## 2.5 Examinations

### Exam Terms
**Path:** Sidebar → **Examinations** → **Exam Terms**

Term I / II / III, etc. (set up in Part 1).

### Exam Types
**Path:** Sidebar → **Examinations** → **Exam Types**

Unit Test, Mid-Term, Final.

### Grading Systems
**Path:** Sidebar → **Examinations** → **Exam Grades**

Mark-to-grade mapping.

### Exam Assessments
**Path:** Sidebar → **Examinations** → **Exam Assessment**

Optional question-wise mark templates.

### Exam Schedules
**Path:** Sidebar → **Examinations** → **Exam Schedule**

For each term/type, schedule each subject's exam: date, time, room.

Workflow:
1. Pick **Term** + **Type** + **Class**.
2. Add a row per subject: date, time, period, room.
3. Save.
4. Schedule appears to teachers and (after publishing) to parents.

### Admit Cards
**Path:** Sidebar → **Examinations** → **Admit Cards**

Generate admit cards in bulk. Pick class + exam → preview → print.
Honours the Number Format settings.

### Exam Marks (entry)
**Path:** Sidebar → **Examinations** → **Marks Entry**

Teachers do this; admin can also enter or correct.

1. Pick **Exam** (e.g. "Mid-Term 2026 — Class 5") + **Section** + **Subject**.
2. Page loads students with assessment-item columns (or a single Total column).
3. Enter marks → click **Save**.
4. System auto-calculates grades using the grading system + class weight.

### Exam Results
**Path:** Sidebar → **Examinations** → **Exam Results**

Per-student / per-class result view. Verify before publishing.

### Report Cards
**Path:** Sidebar → **Examinations** → **Report Cards**

1. Pick **Term** + **Class**.
2. Review each report card.
3. Add comments — manually or click **AI Generate Comments** (rate-limited
   to ~5/minute; use sparingly).
4. Click **Publish** — parents now see the report card on the portal.
5. Print bulk PDFs for paper distribution.

### AI Question Papers
**Path:** Sidebar → **Examinations** → **AI Question Paper**

AI assistant for drafting question papers. Pick subject + class + topics →
get a draft paper. Edit before using.

---

## 2.6 Finance

The biggest module. Every monetary action in the school flows through here.

### Collect Fee
**Path:** Sidebar → **Finance & Fees** → **Collect Fee**

The most-used screen in this module — used multiple times a day at the
front desk.

Workflow:

1. **Search student** by admission no, roll no, or name.
2. The bill loads — pending fee heads with amount due, fine (if any),
   concession (if any), net amount.
3. Tick the heads being paid (or pay the full amount).
4. Pick **payment method** (Cash / Cheque / Card / UPI / Online).
5. Click **Collect & Print Receipt**.
6. System creates the Fee Payment record + GL posting (debits Cash, credits
   Tuition Income or the appropriate revenue head).
7. PDF receipt opens with the configured paper size and copies (per
   Section 1.9). Print and hand the **Original** to the parent; file the
   other copies.

**If a parent pays a partial amount:** enter the part-amount; the system
records the payment and shows the remainder as still due.

**If a parent has a concession:** the concession is auto-applied. Override
manually if needed (you'll need the `override_fee_discount` permission, which
admin and accountant have).

### Fee Structure
**Path:** Sidebar → **Finance & Fees** → **Fee Structure**

Class-wise fee plan. (Set up in Part 1; revisit at year-start to adjust
amounts for the new year.)

### Fee Groups
**Path:** Sidebar → **Finance & Fees** → **Groups & Heads**

Fee bundles.

### Fee Concessions
**Path:** Sidebar → **Finance & Fees** → **Concessions**

Define concession types and apply them to specific students.

### Fee Due Report
**Path:** Sidebar → **Finance & Fees** → **Due Report**

Defaulter list — students whose fee is overdue. Filter by class, ageing
bucket (0-30, 31-60, 60+ days). Export. Trigger reminder messages
straight from this screen via Communication module integration.

### Day Book
**Path:** Sidebar → **Finance & Fees** → **Day Book**

A single day's cash position — opening balance, all receipts, all
disbursements, closing balance. Match to your cash drawer at end of day.

### Finance Reports
**Path:** Sidebar → **Finance & Fees** → **Financial Reports**

Pre-built reports: collection by class, collection by head, expense by
category, daily/monthly summary.

### Expenses
**Path:** Sidebar → **Finance & Fees** → **Expenses**

Record school expenses (utilities, maintenance, supplies). Fields:
category, amount, paid to, payment method, date, attachment (bill scan).
Auto-posts to GL.

### Ledgers
**Path:** Sidebar → **Finance & Fees** → **Chart of Accounts**

Master chart of accounts. View any ledger account's running balance.

### Finance Transactions
**Path:** Sidebar → **Finance & Fees** → **Transactions**

Raw transaction list — every GL posting in the system. Use for audit /
reconciliation.

### Trial Balance
**Path:** Sidebar → **Finance & Fees** → **Trial Balance**

Standard accounting trial balance — debits should equal credits.

### Profit & Loss
**Path:** Sidebar → **Finance & Fees** → **Profit & Loss**

Income vs Expenses for a period.

### Balance Sheet
**Path:** Sidebar → **Finance & Fees** → **Balance Sheet**

Assets vs Liabilities + Equity at a point in time.

### Budgets
**Path:** Sidebar → **Finance & Fees** → **Budget Management**

Set period budgets per category; report shows variance (budget vs actual).

### GL Auto-posting Config
**Path:** Sidebar → **Finance & Fees** → **GL Auto-Posting**

Maps each fee head and expense category to a ledger account. **Set this up
once, with your accountant.** Wrong mapping here means wrong financial
reports forever.

---

## 2.7 HR

### Departments
**Path:** Sidebar → **Staff & HR** → **Departments**

(Set up in Part 1.)

### Designations
**Path:** Sidebar → **Staff & HR** → **Designations**

(Set up in Part 1.)

### Staff Directory
**Path:** Sidebar → **Staff & HR** → **Staff Directory**

Master staff list. Add / edit / deactivate. Click any staff member for
profile (history, leaves, payslips, attendance).

### Leaves
**Path:** Sidebar → **Staff & HR** → **Leave Management**

Pending leave requests. Review → approve / reject with comment.

### Leave Types
**Path:** Sidebar → **Staff & HR** → **Leave Types**

(Set up in Part 1.)

### Payroll
**Path:** Sidebar → **Staff & HR** → **Payroll**

Run payroll once a month, late on the last working day or first of the
following month.

Workflow:

1. Click **Generate Payroll**.
2. Pick the **month**.
3. System pulls every active staff member, calculates: basic + HRA + DA −
   leave deduction − fine − other deductions.
4. Review each row; adjust manually if needed.
5. Click **Mark as Paid** — payroll posts to GL (debits Salary Expense,
   credits Bank).
6. Click **Download Payslips** for bulk PDF.

**Common issues:**
- *Staff missing from payroll* → they were marked inactive or have no salary
  structure. Fix the staff record and regenerate.
- *Leave deductions look wrong* → leave records for that month aren't
  approved. Approve them first, then regenerate.

---

## 2.8 Front Office

### Front Office Dashboard
**Path:** Sidebar → **Front Office** → **Dashboard**

Live counts: visitors today, calls today, gate passes today, pending
complaints, recent correspondence.

### Visitors
**Path:** Sidebar → **Front Office** → **Visitor Log**

Log every visitor: name, phone, purpose, person to meet, in-time, out-time.
Print a visitor pass.

### Gate Passes
**Path:** Sidebar → **Front Office** → **Gate Passes**

Issue gate passes for students leaving early or staff leaving on duty.
Generates a pass with QR code.

### Gate Pass QR Scanner
**Path:** Sidebar → **Front Office** → **QR Scanner**

The gatekeeper scans the QR on a gate pass to validate it (prevents reuse /
forgery).

### Complaints
**Path:** Sidebar → **Front Office** → **Complaints**

Log and track complaints from parents, students, staff, visitors. Assign,
update status, close.

### Call Logs
**Path:** Sidebar → **Front Office** → **Call Logs**

Log every important call (incoming and outgoing). Useful for follow-up.

### Call Log Follow-ups
**Path:** Sidebar → **Front Office** → **Follow-Ups**

Tasks created from call logs that need follow-up.

### Correspondence
**Path:** Sidebar → **Front Office** → **Correspondence**

Inward / outward letters. Attach scans, mark as actioned.

### Daily Report
**Path:** Sidebar → **Front Office** → **Daily Report**

The front office's own daily summary — distinct from the school-wide Daily
Master Report.

---

## 2.9 Hostel

### Hostel Dashboard
**Path:** Sidebar → **Hostel** → **Dashboard**

Bed occupancy, today's roll-call status, pending leave requests, mess
attendance.

### Hostels
**Path:** Sidebar → **Hostel** → **Manage Hostels**

Buildings.

### Rooms
**Path:** Sidebar → **Hostel** → **Rooms & Beds**

Rooms per building, with capacity and bed numbers.

### Allocations
**Path:** Sidebar → **Hostel** → **Student Allocations**

Assign students to beds. One bed → one student. The system warns if a bed
is double-allocated.

### Hostel Gate Passes
**Path:** Sidebar → **Hostel** → **Gate Passes**

Hostel-specific gate passes for students leaving the hostel for outings,
home visits, etc.

### Hostel Visitors
**Path:** Sidebar → **Hostel** → **Visitor Logs**

Visitors to the hostel (often parents).

### Mess
**Path:** Sidebar → **Hostel** → **Mess Menu**

Mess menu by day-of-week and meal (breakfast / lunch / snacks / dinner).
Set the menu for the week.

### Roll Call
**Path:** Sidebar → **Hostel** → **Roll Call**

Wardens mark hostel roll call (typically twice daily). Same Present /
Absent / On Leave statuses.

### Roll Call Report
**Path:** Sidebar → **Hostel** → **Roll Call Report**

Filter and export.

### Hostel Complaints
**Path:** Sidebar → **Hostel** → **Complaints**

Student complaints (food, water, electricity, room issues).

### Meal Report
**Path:** Sidebar → **Hostel** → **Meal Report**

Per-meal headcount used for catering.

**Hostel Fee Collection** — done from **Sidebar → Finance & Fees → Collect Fee**
when the student has a hostel fee head, or via the Hostel allocation page.
Receipt obeys the same Receipt Print Settings.

---

## 2.10 Transport

### Transport Dashboard
**Path:** Sidebar → **Transport** → **Dashboard**

Routes active today, vehicles on road, students allocated, fee defaulters.

### Routes
**Path:** Sidebar → **Transport** → **Routes & Stops**

Define routes with stops (and pickup / drop-off times per stop).

### Vehicles
**Path:** Sidebar → **Transport** → **Vehicles**

Buses with reg no, capacity, driver, conductor, fitness expiry.

### Allocations
**Path:** Sidebar → **Transport** → **Student Allocation**

Assign students to routes (and the stop they board at). Charges transport
fee accordingly.

### Bus Roll Call
**Path:** Sidebar → **Transport** → **Bus Roll Call**

Conductor / driver marks attendance at boarding and alighting. Helps with
"my child didn't come home" calls.

### Route Report
**Path:** Sidebar → **Transport** → **Route Report**

Per-route summary: total students, fee collected, defaulters.

### Transport Fee Defaulters
**Path:** Sidebar → **Transport** → **Fee Defaulters**

Students with overdue transport fee.

### Live Tracking
**Path:** Sidebar → **Transport** → **Live Tracking**

Map view of buses (requires the GPS device on each bus to be reporting).

### Driver Tracking
**Path:** Sidebar → **Transport** → **Driver Tracking**

Per-driver shift / location history.

**Transport Fee Collection** — done from **Sidebar → Finance & Fees → Collect Fee**
or from the allocations page. Same receipt format.

---

## 2.11 Communication

The most flexible module — also the easiest to misuse. **Test on yourself
first.**

### Communication Dashboard
**Path:** Sidebar → **Communication** → **Dashboard**

Sends today, by channel; delivery success rate; pending queue.

### Announcements
**Path:** Sidebar → **Communication** → **Announcements**

Broadcast to selected audiences:

1. Click **New Announcement**.
2. Title + body. Pick channels (SMS, WhatsApp, Email, Push).
3. Pick audience: All, Class(es), Section(s), Roles, or specific users.
4. Schedule (now / future date+time).
5. Click **Send**.

Scheduled announcements are processed by the per-minute background job
(`announcements:process`) — they fire automatically at the scheduled time.

### Emergency Broadcast
**Path:** Sidebar → **Communication** → **Emergency Broadcast**

A one-click "send everyone, every channel" path. **Only use for genuine
emergencies** — fire, weather closure, security incident.

### Communication Logs
**Path:** Sidebar → **Communication** → **Message Logs**

Every send, every recipient, with delivery status. First place to look when
a parent says "I didn't get the message."

### Communication Analytics
**Path:** Sidebar → **Communication** → **Delivery Analytics**

Send volume trends, delivery success by channel, click-through (where
trackable).

### Email Templates / SMS Templates / WhatsApp Templates / Voice Templates / Push Templates
**Paths:** Sidebar → **Communication** → (each respective Template item)

Master templates for each channel. Use placeholders like `{student_name}`,
`{amount_due}` that get filled in at send time.

### Scheduled Messages
**Path:** Sidebar → **Communication** → **Scheduled Queue**

Future-dated messages. Cancel before send time if needed.

### Social Buzz
**Path:** Sidebar → **Communication** → **Social Buzz**

Captures social media activity (school's posts, mentions). Optional
feature; ignore if not configured.

### SMS Config / WhatsApp Config / Voice Config
**Paths:** Sidebar → **Communication** → (each respective Config item)

API credentials and per-channel settings (sender ID, opt-in defaults). Set
once.

### Internal Chat
**Path:** Sidebar → **Chat** *(top-level under the Communication group)*

Real-time chat between staff. Useful for "principal's office to all class
teachers" announcements.

### Holidays & Events
**Path:** Sidebar → **Holidays & Events** *(top-level under the Communication
group)*

(Already covered in Section 1.4.)

---

## 2.12 Settings

### General Configuration
**Path:** Sidebar → **Settings & Setup** → **General Configuration**

School profile (name, board, address, contact, logo, financial year code,
admin contacts).

### Asset Configuration
**Path:** Sidebar → **Settings & Setup** → **Asset Configuration**

Asset categories, depreciation rules, maintenance defaults — for the
school's inventory module.

### System Configuration
**Path:** Sidebar → **Settings & Setup** → **System Configuration**

Timezone, currency, date/time format, system-wide flags.

### Mobile QR
**Path:** Sidebar → **Settings & Setup** → **Mobile QR**

Generate the deep-link QR code that parents scan to install the school's
mobile app and auto-link to your school.

### Academic Years
**Path:** Sidebar → **Settings & Setup** → **Academic Years**

List of years; create the next one before each rollover.

### Rollover Wizard
**Path:** Sidebar → **Settings & Setup** → **Setup Wizard**

The end-of-year ritual. Promotes students from the closing year to the new
year.

Phases (run in order):

1. **Structure** — copies classes, sections, subjects, fee heads, fee
   groups to the new year.
2. **Students** — promotes students per the explicit class mapping you
   provide (e.g. "everyone in 5A goes to 6A unless individually marked
   otherwise"). Detained students stay back; leavers don't carry over.
3. **Fees** — copies the fee structure forward (you adjust amounts after).
4. **Finalize** — locks the closing year (no more edits).

**Permission needed:** `manage_rollover` + `execute_rollover` (admin has
these by default). **Do not run this until** final exam results are
published, all fees are reconciled, and TCs are issued. Practice the
mapping in a test environment if possible.

### Custom Fields
**Path:** Sidebar → **Settings & Setup** → **Custom Fields**

Add school-specific fields to the Student / Staff forms (e.g. Aadhaar
number, blood group, mother tongue).

### Number Formats
**Path:** Sidebar → **Settings & Setup** → **Number Formats**

Templates for Admission No, Roll No, Receipt No, etc.

### Roles & Permissions
**Path:** Sidebar → **Roles & Permissions** *(top-level under the Settings
group)*

The permission matrix.

### User Login Management
**Path:** Sidebar → **User Login Management** *(top-level under the Settings
group)*

All user accounts. Add / deactivate / reset password / change role / unlock
locked accounts.

### Backup Manager
**Path:** Sidebar → **Backup Manager** *(top-level under the Settings
group)*

Schedule and download database backups. Useful for monthly archival.

### Activity Log
**Path:** Sidebar → **Utility** → **Activity Log**

Audit trail — every meaningful action with user, time, IP, before/after
values. Use for "who deleted that?" questions.

### Error Log
**Path:** Sidebar → **Utility** → **Error Log**

System errors. Mostly for IT; occasionally useful when a screen behaves
oddly — share the error ID with IT.

### ID Cards & Certificates
**Path:** Sidebar → **Utility** → **ID Cards** / **Certificates**

Bulk generation of student ID cards and certificates (bonafide, character,
pass, custom). Pick the template, filter the students, generate, print.

---

## 2.13 Parent / Student Portal

This isn't a sidebar item *for you* — it's what parents and students see
when they log in. Knowing what they see helps when they call you.

**What a parent sees:**

- **Dashboard** — each child's name, class, photo, fee balance, attendance %.
- **Fees** — pending and paid history. **Pay Online** button → Razorpay
  checkout. After payment, receipt is auto-generated.
- **Attendance** — calendar view of present/absent days.
- **Marks / Report Cards** — published exam results.
- **Assignments** — their child's assignments and submission status.
- **Diary** — daily diary entries from the class teacher.
- **Announcements** — messages from the school.
- **Apply for Leave** — submit a student leave request (you approve it via
  Sidebar → **Student Management** → **Student Leaves**).

**What a student sees:**

A subset of the above — own profile, own attendance, own marks, own
assignments, own timetable, own fee summary.

**If a parent calls you about something on their portal**, log into the
admin portal, search for the student, and you'll see the same data they
see — usually the issue is published-but-not-seen or a permission glitch.

---

## 2.14 Stationary

A full module for schools that supply stationery items (notebooks,
textbooks, lab kits) to students with separate billing.

### Stationary Dashboard
**Path:** Sidebar → **Stationary** → **Dashboard**

Items issued today, fees collected, defaulters, return queue.

### Items
**Path:** Sidebar → **Stationary** → **Items**

Master stationery catalogue: name, SKU, unit price, current stock.

### Allocations
**Path:** Sidebar → **Stationary** → **Allocations**

Allocate items to a student or class. Triggers a stationary fee bill.

### Fee Collection
**Path:** Sidebar → **Stationary** → **Fee Collection**

Collect stationary fees. Receipt prints with the same Receipt Print
Settings as tuition fees.

### Fee Defaulters / Collection Pending / Returns
**Paths:** Sidebar → **Stationary** → **Fee Defaulters** / **Collection
Pending** / **Returns**

Operational reports — chase unpaid stationary fees, track items not yet
issued, and process returned items (with refund GL postings).

---

## 2.15 Inventory & Assets

**Path:** Sidebar → **Inventory & Assets**

The school's asset register — purchased items, depreciation, maintenance
logs, disposal. Use for furniture, lab equipment, IT hardware, vehicles
(non-bus), facility assets.

Workflow:
1. Add an asset with purchase price, date, vendor, expected lifespan.
2. Record maintenance events (cost, vendor, downtime).
3. At year-end, run depreciation (configured under Settings → Asset Config).
4. When disposed, mark the asset and record sale value (if any).

---

## 2.16 Daily Master Report

**Path:** Sidebar → **Daily Master Report**

The on-demand version of the auto-emailed daily report (covered in Section
1.10). Use this when:

- Yesterday's report didn't arrive and you need a quick view.
- You want to verify what was sent.
- You need an ad-hoc report for a date earlier in the week.

The report shows: attendance summary, today's fee collection, expense
flags (above threshold), pending leaves, low-attendance students, repeat
absentees, recent announcements. The sections shown match what's enabled
in Settings → Daily Report.

---

## 2.17 AI Intelligence

**Path:** Sidebar → **AI Intelligence**

LLM-powered analytics for ad-hoc questions and saved insights. Use cases:

- **Trend analysis:** "Show me classes with declining attendance over the
  last 30 days." → System runs the underlying query, generates a chart and
  a narrative summary.
- **Drill-down comparisons:** "Compare fee collection in April vs March
  by class."
- **Saved views:** Save useful queries; re-run any time. Appears on the AI
  Intelligence Hub on the Dashboard.
- **Chart explanation:** On any dashboard chart, click "Explain this" for
  an AI-generated narrative summary.
- **Export:** Any insight can be exported to PDF or Excel.

Rate-limited to prevent abuse. If you hit a limit, wait one minute and
retry.

---

## 2.18 Other Modules

Brief notes on smaller modules surfaced in the sidebar:

- **Alumni** *(Sidebar → Alumni)* — basic alumni directory: name, batch,
  current contact, optional notes. Useful for newsletters and reunions.
- **Student Houses → Manage Houses / Leaderboard** — competitive house
  system. Create houses, assign students, award/deduct points; the
  leaderboard ranks them.
- **Disciplinary Records** *(Sidebar → Student Management → Disciplinary
  Records)* — log incidents per student with category, severity, action
  taken, follow-up date.
- **PTM Sessions** *(Sidebar → Schedule → PTM Sessions)* — schedule
  parent-teacher meeting slots; parents book a slot via the portal.
- **Staff History Log** *(Sidebar → Staff & HR → Staff History Log)* —
  audit trail of staff record changes (promotions, transfers, salary
  revisions).
- **Date-wise Report, Attendance Forecast** *(Sidebar → Attendance →
  …)* — alternative attendance views: a single date across all classes,
  and a forecast model based on trends.
- **Mark Summary** *(Sidebar → Examinations → Mark Summary)* — class /
  subject-wise summary of marks entered, useful before publishing results.
- **Notification Config** *(Sidebar → Communication → Notification
  Config)* — turns specific system notifications on / off (e.g. "send SMS
  on absence", "send WhatsApp on fee due").
- **Admin Numbers** *(Sidebar → Settings & Setup → Admin Numbers)* — list
  of phone numbers / emails who receive admin-level notifications,
  including the Daily Master Report.

---

# Part 3 — Operating Rhythm

If you do nothing else from this manual, do **the daily checklist** every
day.

## Daily Checklist

Each morning (≈ 15 minutes):

1. **Confirm yesterday's Daily Master Report arrived** in your inbox at the
   configured time.
2. **Review the Dashboard** — KPIs at the top. Note anything red.
3. **Review attendance** — Sidebar → Attendance → Mark Attendance, scan a
   sample of class-sections to confirm teachers are marking on time.
4. **Review today's fee collection** — Dashboard widget; or Sidebar →
   Finance → Day Book.
5. **Review pending fees** — Dashboard widget "Pending Fee Students."
6. **Approve / reject pending requests:**
   - Leave requests (Sidebar → HR → Leaves; Sidebar → Students → Student
     Leaves).
   - Edit requests (Sidebar → Students → Edit Requests).
   - New admission applications (Sidebar → Students → Registrations).
7. **Scan announcements queue** — anything pending or scheduled?
8. **Clear front-office complaints** — Sidebar → Front Office → Complaints,
   any new entries since yesterday.

## Weekly Checklist

Once a week (Friday afternoon works well):

1. **Defaulter report** — Sidebar → **Finance & Fees** → **Due Report**. Sort by
   ageing. From the page, trigger reminder SMS / WhatsApp via Communication.
2. **Staff attendance & pending leaves** — Sidebar → HR → Leaves.
3. **House points** (if you run the house system) — Sidebar (Houses page,
   if enabled).
4. **Disciplinary log** — review new incidents.
5. **Backups** — run the exports you care about (students, fee payments,
   attendance for the week) via the per-page Excel/CSV/PDF download.

## Monthly Checklist

End-of-month / first of next month (≈ 60-90 minutes):

1. **Run payroll** — Sidebar → HR → Payroll → Generate → Review → Mark as
   Paid.
2. **Finance reports** — Sidebar → **Finance & Fees** → **Financial Statements** (Profit & Loss tab) for the month;
   Trial Balance to verify books are balanced.
3. **Budget variance** — Sidebar → **Finance & Fees** → **Budget Management**.
4. **Transport fee defaulters** — Sidebar → Transport → Transport Fee
   Defaulters.
5. **Inventory stock check** — Settings → Asset Configuration if managing
   assets; physical stock count of stationery.
6. **AI Insights review** — Sidebar → Dashboard → AI Intelligence Hub. Save
   any useful views.
7. **Communication analytics** — Sidebar → Communication → Communication
   Analytics. Channels with low delivery rates → escalate to channel vendor.

## Term / Quarterly

At the start of each term:

1. **Schedule exams** — Sidebar → Examinations → Exam Schedules.
2. **Generate admit cards** — Sidebar → Examinations → Admit Cards.
3. **Update syllabus progress** — Sidebar → **Academic Resources** → **Syllabus Tracker**.

At the end of each term:

1. **Collect marks** — confirm all teachers have entered marks (Sidebar →
   Examinations → Exam Marks).
2. **Verify results** — Sidebar → Examinations → Exam Results.
3. **Generate report cards** with comments — Sidebar → Examinations →
   Report Cards. Use AI Generate Comments sparingly.
4. **Publish report cards** — parents now see them on the portal.
5. **Print bulk** — for paper distribution at PTM.

## Annual Cycle

The yearly journey, March to March (or whatever your school's calendar):

### Year-end (last 4-6 weeks)

1. **Conduct final exams** + enter marks.
2. **Generate and publish final report cards.**
3. **Reconcile fees** — every student's fee account should be settled or
   formally written off. Check Fee Due Report = clean.
4. **Issue TCs** for leaving students (Sidebar → Students → Transfer
   Certificates).
5. **Close month-end** finance books for the closing year.
6. **Run final payroll** for the closing year.

### Year-start (just before the new year begins)

1. **Create the new academic year** (Sidebar → Settings → Academic Years)
   if not done already.
2. **Run the Rollover Wizard** (Sidebar → Settings → Rollover Wizard) —
   structure → students → fees → finalize. **Practice on test data first
   if you can.**
3. **Adjust the new year's fee structure** — typical inflation increases.
4. **Re-assign roll numbers** (Sidebar → Students → Roll Numbers).
5. **Update the holiday calendar** for the new year (Sidebar →
   **Holidays & Events**).
6. **Review staff salary structures** for any increments.
7. **Print and distribute new ID cards** (Sidebar → Examinations / Students
   sub-features) for re-photo or class-change updates.
8. **Onboard new admissions** as they come in.
9. **Print book lists** (Sidebar → **Academic Resources** → **Book List**) and circulate to
   parents.

### Anytime in the year

- **Backup exports** — at minimum, monthly: Students, Staff, Fee Payments,
  Attendance, Ledger transactions. Use the per-page Excel export.

## Background Jobs (Informational)

These run automatically. You don't trigger them — they're listed so you
aren't surprised:

| When | What runs | What it does |
|---|---|---|
| Every minute | `announcements:process` | Sends queued / scheduled announcements. |
| Every minute | `report:daily-master` | Checks if the configured send time matches; if so, sends Daily Master Report. |
| Every 15 min | `auth:clear-resets` | Removes expired password-reset tokens. |
| Hourly | `app:cleanup-voice-cache` | Cleans stale voice-call cache files. |
| Daily 00:30 | `attendance:fill-holidays` | Auto-fills `holiday` attendance for declared holidays. |
| Daily 03:00 | `model:prune` | Permanently deletes records soft-deleted > 90 days. |
| Daily | `sanctum:prune-expired` | Removes API tokens older than 30 days. |
| Weekly Sun 02:00 | `activitylog:clean` | Removes activity-log entries older than 1 year. |

If any of these stops running, you'll notice symptoms (Daily Master Report
doesn't arrive; password resets stay valid forever; etc.). **Call IT** —
they need to verify the cron job on the server.

---

# Part 4 — Troubleshooting

The everyday problems you can fix yourself, without calling IT.

### A user can't log in

1. Sidebar → **User Login Management**.
2. Find the user. Confirm **Active** is on.
3. If active and they say "wrong password," click **Reset Password** and
   give them the new one.
4. The system accepts login by **email**, **username**, or **phone** — make
   sure the user is entering the same one that's on file.
5. If still failing → **Activity Log** for their email; if many recent
   failed attempts, account may be lock-protected → ask IT to unlock.

### Fee receipt prints wrong size or wrong number of copies

1. Sidebar → **Settings & Setup** → **Receipt Print**.
2. Verify Paper Size and Copies are what you expect.
3. Save → next receipt uses the new setting.

### Fee receipt is missing details (class, parent name, address)

The receipt pulls fields from the Student record. Open the student
(Sidebar → Students → Student Directory → student) and confirm the missing
fields are filled. Save the student record, retry.

### Parent says "I didn't get the SMS / WhatsApp / email"

1. Sidebar → **Communication** → **Communication Logs**.
2. Filter by recipient phone or email.
3. Look at delivery status:
   - `Delivered` → it was delivered; problem is the parent's phone /
     spam folder.
   - `Failed` → click for reason. Common: invalid number, opted out,
     channel down.
   - `Queued` → may be backlogged. Wait 5 min; if still queued, call IT.
4. To re-send, open the original message and click **Resend**.

### Marks won't save

- Confirm the exam isn't published (published exams lock marks).
- Confirm the teacher is assigned to that section + subject (Sidebar →
  Academics → Class Subjects).

### Report card not visible to parent

The most common cause: it isn't published yet.

1. Sidebar → **Examinations** → **Report Cards**.
2. Find the term + class.
3. Click **Publish**.

### Online payment received but receipt not generated

1. Sidebar → **Communication** → **Communication Logs** — confirm payment
   notification was received.
2. Sidebar → **Finance & Fees** → **Transactions** — search by amount or
   parent phone, see if a Fee Payment was created.
3. If yes → go to Sidebar → **Finance & Fees** → **Collect Fee** → student → it should
   show as paid; print receipt.
4. If no → call IT (Razorpay webhook may have failed); meanwhile, manually
   record the payment in **Collect Fee** with **Online** as the method.

### Daily Master Report didn't arrive

1. Sidebar → **Settings & Setup** → **Daily Report**. Confirm:
   - **Auto-send enabled** is on.
   - **Auto-send time** is what you expect (24-hour, HH:MM).
   - At least one Admin Contact exists (Sidebar → Settings → General
     Configuration).
2. If all looks right → call IT to verify the per-minute scheduler is
   running.

### Student doesn't appear in the attendance roster

1. Sidebar → **Student Management** → **Students Directory** → search the student.
2. Open profile, check **Class**, **Section**, and **Academic Year**.
3. They must be in the *current* academic year and assigned to the class
   you're trying to mark.

### AI chat / insights say "rate limit"

You hit the throttle. Wait one minute and retry. (5/min for report-card
comments; 30/min for chat.)

### Receipt or PDF prints with garbled font

The browser's PDF renderer may be missing fonts. Use Chrome / Edge — they
ship with the fonts the system uses. If still broken, call IT.

---

# Appendix A — Roles Cheat-Sheet

Full list of pre-configured roles and their default permission scope. Source:
the system seeder — do not edit role definitions in the database directly.

| Role | Default scope |
|---|---|
| **super_admin** | Everything. Reserved for vendor (Trivartha Tech). |
| **admin** | Everything within the school. |
| **school_admin** | Everything within the school (alternate admin). |
| **principal** | Everything within the school (academic head). |
| **hr** | Staff CRUD, Payroll CRUD, payslip download, leave approval, attendance view, audit log view. |
| **teacher** | Student view, attendance CRUD + scanner + export, academic CRUD (diary, assignments, syllabus), exam view + create + edit + mark entry + schedule/term/type/grade/assessment management, schedule view, chat, reports view, payslip download (own), transport view, student leaves view + approve + download. |
| **accountant** | Student view; fee CRUD + waive + receipt + structure + override discount; transport / hostel / stationary fee collection; expense CRUD; payroll view + payslip; reports view + financial reports + export; chat. |
| **student** | Own portal: own student / attendance / fee / exam view, academic view, exam view, schedule view, own documents, own fee receipt generation, chat, transport view (own bus), student leaves apply + view + download (own). |
| **parent** | Own children's portal: same as student plus view + apply leave for child; chat; view child's transport / hostel / stationary. |
| **driver** | Transport vehicle / route / tracking view; chat. |
| **conductor** | Transport vehicle / route / tracking view. |
| **transport_manager** | Transport vehicles / routes / allocations CRUD; collect transport fee; generate fee receipt; student view; tracking view. |
| **stationary_manager** | Stationary items / allocations CRUD; issue items; accept returns; collect stationary fee; receipt generation; student view. |
| **receptionist / front_office** | Front-office CRUD; student view; attendance view. |
| **front_gate_keeper** | Front-office view + create + edit; student view; attendance view + QR scanner; chat. |
| **hostel_warden** | Hostel CRUD; collect hostel fee; student view. |
| **mess_manager** | Hostel view + create. |
| **librarian** | Academic view; student view. |
| **nurse** | Student view (for health records). |
| **auditor** | Read-only across everything; export data; audit log + financial reports view. |
| **it_support** | (Custom — set by your IT team.) |

---

# Appendix B — Sidebar Map

Every group, item, and route exactly as it appears in the live UI sidebar.
Source of truth: `resources/js/config/sidebar.js`. Items in **bold** are
collapsible parent menus.

### Always visible
- Dashboard → `/dashboard`

### Administration
- Analytics Dashboard → `/school/analytics`
- Alumni → `/school/alumni`
- **Academic Structure**
  - Classes → `/school/classes`
  - Sections → `/school/sections`
  - Subject Types → `/school/subject-types`
  - Subjects → `/school/subjects`
  - Assign Subjects → `/school/class-subjects`
- **Academic Resources**
  - Student Diary → `/school/academic/diary`
  - Assignments → `/school/academic/assignments`
  - Syllabus Tracker → `/school/academic/syllabus`
  - Digital Resources → `/school/academic/resources`
  - Book List → `/school/academic/book-list`
- **Student Houses**
  - Manage Houses → `/school/houses`
  - Leaderboard → `/school/houses/leaderboard`
- **Student Management**
  - Registrations → `/school/registrations`
  - Students Directory → `/school/students`
  - Student Leaves → `/school/student-leaves`
  - Student Leave Types → `/school/student-leave-types`
  - Roll Numbers → `/school/roll-numbers`
  - Transfer Certificates → `/school/transfer-certificates`
  - Disciplinary Records → `/school/disciplinary`
- **Attendance**
  - Mark Attendance → `/school/attendance`
  - Attendance Report → `/school/attendance/report`
  - Date-wise Report → `/school/attendance/date-wise`
  - Attendance Forecast → `/school/attendance/forecast`
- **Examinations**
  - Exam Terms → `/school/exam-terms`
  - Exam Types → `/school/exam-types`
  - Exam Grades → `/school/grading-systems`
  - Exam Assessment → `/school/exam-assessments`
  - Exam Schedule → `/school/exam-schedules`
  - Admit Cards → `/school/admit-cards`
  - Marks Entry → `/school/exam-marks`
  - Results → `/school/exam-results`
  - Mark Summary → `/school/exam-mark-summary`
  - Report Cards → `/school/report-cards`
  - AI Question Paper → `/school/question-papers`
- **Schedule**
  - Periods → `/school/periods`
  - Timetable → `/school/timetable`
  - PTM Sessions → `/school/ptm`

### Finance
- **Finance & Fees**
  - Collect Fee → `/school/fee/collect`
  - Fee Structure → `/school/fee/structure`
  - Due Report → `/school/finance/due-report`
  - Groups & Heads → `/school/fee/groups`
  - Concessions → `/school/fee/concessions`
  - Expenses → `/school/expenses`
  - Day Book → `/school/finance/day-book`
  - Financial Reports → `/school/finance/reports`
  - Chart of Accounts → `/school/finance/ledgers`
  - Transactions → `/school/finance/transactions`
  - Financial Statements → `/school/finance/statements/trial-balance` *(opens with Trial Balance tab; switch tabs for P&L and Balance Sheet)*
  - Payment Methods → `/school/finance/payment-methods`
  - Budget Management → `/school/finance/budgets`
  - GL Auto-Posting → `/school/finance/gl-config`

### HR
- **Staff & HR**
  - Departments → `/school/departments`
  - Designations → `/school/designations`
  - Staff Directory → `/school/staff`
  - Staff Attendance → `/school/staff-attendance`
  - Attendance Report → `/school/staff-attendance/report`
  - Leave Management → `/school/leaves`
  - Leave Types → `/school/leave-types`
  - Payroll → `/school/payroll`
  - Incharge Assignment → `/school/incharge`
  - Staff History Log → `/school/staff-history`

### Operations
- Inventory & Assets → `/school/inventory`
- Daily Master Report → `/school/reports/daily-master`
- **Front Office**
  - Dashboard → `/school/front-office`
  - Visitor Log → `/school/front-office/visitors`
  - Gate Passes → `/school/front-office/gate-passes`
  - QR Scanner → `/school/front-office/gate-passes/scanner`
  - Complaints → `/school/front-office/complaints`
  - Call Logs → `/school/front-office/call-logs`
  - Follow-Ups → `/school/front-office/call-logs-follow-ups`
  - Correspondence → `/school/front-office/correspondence`
  - Daily Report → `/school/front-office/daily-report`
- **Hostel**
  - Dashboard → `/school/hostel`
  - Manage Hostels → `/school/hostel/hostels`
  - Rooms & Beds → `/school/hostel/rooms`
  - Student Allocations → `/school/hostel/allocations`
  - Fee Collection → `/school/hostel/fees`
  - Fee Defaulters → `/school/hostel/reports/fee-defaulters`
  - Gate Passes → `/school/hostel/gate-passes`
  - Visitor Logs → `/school/hostel/visitors`
  - Mess Menu → `/school/hostel/mess`
  - Roll Call → `/school/hostel/roll-call`
  - Roll Call Report → `/school/hostel/roll-call/report`
  - Complaints → `/school/hostel/complaints`
  - Meal Report → `/school/hostel/mess/meal-report`
- **Transport**
  - Dashboard → `/school/transport`
  - Routes & Stops → `/school/transport/routes`
  - Vehicles → `/school/transport/vehicles`
  - Student Allocation → `/school/transport/allocations`
  - Fee Collection → `/school/transport/fees`
  - Bus Roll Call → `/school/transport/attendance`
  - Route Report → `/school/transport/reports/route-report`
  - Fee Defaulters → `/school/transport/reports/fee-defaulters`
  - Live Tracking → `/school/transport/live`
  - Driver Tracking → `/school/transport/driver-tracking`
- **Stationary**
  - Dashboard → `/school/stationary`
  - Items → `/school/stationary/items`
  - Allocations → `/school/stationary/allocations`
  - Fee Collection → `/school/stationary/fees`
  - Fee Defaulters → `/school/stationary/reports/fee-defaulters`
  - Collection Pending → `/school/stationary/reports/collection-pending`
  - Returns → `/school/stationary/reports/returns`

### Communication
- **Communication**
  - Dashboard → `/school/communication/dashboard`
  - Announcements → `/school/communication/announcements`
  - Emergency Broadcast → `/school/communication/emergency`
  - Message Logs → `/school/communication/logs`
  - Delivery Analytics → `/school/communication/analytics`
  - Email Templates → `/school/communication/email-templates`
  - Scheduled Queue → `/school/communication/scheduled`
  - Social Buzz → `/school/communication/social-buzz`
  - Notification Config → `/school/communication/config/notifications`
  - SMS Config → `/school/communication/config/sms`
  - WhatsApp Config → `/school/communication/config/whatsapp`
  - Voice Config → `/school/communication/config/voice`
  - SMS Templates → `/school/communication/templates/sms`
  - WhatsApp Templates → `/school/communication/templates/whatsapp`
  - Voice Templates → `/school/communication/templates/voice`
  - Push Templates → `/school/communication/templates/push`
- AI Intelligence → `/school/ai/insights`
- Chat → `/school/chat`
- Holidays & Events → `/school/holidays`

### Settings
- **Settings & Setup**
  - General Config → `/school/settings/general-config`
  - Asset Config → `/school/settings/asset-config`
  - System Config → `/school/settings/system-config`
  - Admin Numbers → `/school/settings/admin-contacts`
  - Daily Report → `/school/settings/daily-report`
  - Mobile App QR → `/school/settings/mobile-qr`
  - Academic Years → `/school/academic-years`
  - Setup Wizard → `/school/settings/rollover`
  - Custom Fields → `/school/custom-fields`
  - Number Formats → `/school/settings/number-formats`
  - Edit Requests → `/school/edit-requests`
- Roles & Permissions → `/school/roles-permissions`
- User Login Management → `/school/users`
- Backup Manager → `/school/backup`

### Utilities
- **Utility**
  - ID Cards → `/school/utility/id-cards`
  - Certificates → `/school/utility/certificates`
  - Activity Log → `/school/utility/activity-log`
  - Error Log → `/school/utility/error-log`

### Portal *(visible only to Parent / Student users)*
- **Fee Payment**
  - Pay Fees → `/portal/fees`
  - Payment History → `/portal/fees/history`
- PTM Booking → `/school/ptm/parent/view`
- My Gate Passes → `/school/hostel/my-gate-passes`

> **Settings sub-tabs (not in the sidebar but reachable from any Settings
> page):** every page under **Settings & Setup** displays a tab bar that
> includes **General Config, Asset Config, System Config, Admin Numbers,
> Daily Report, Receipt Print, Mobile App QR, GeoFence Config, Activity
> Log, Receipt Print Settings**. **Receipt Print is reached only via this
> tab bar** — it is not a sidebar item. Open any Settings page from the
> sidebar, then click the **Receipt Print** tab.

---

# Appendix C — Reports Index

| Report | Where to find it | What it answers |
|---|---|---|
| Daily Master Report | Auto-emailed daily; configure at Settings → Daily Report | One-glance summary of yesterday's school operations. |
| Attendance Report | Attendance → Attendance Report | Class / date-range attendance with %. |
| Staff Attendance Report | Attendance → Staff Attendance Report | Staff present / absent / leave by date. |
| Fee Due Report | Finance → Fee Due Report | Who owes what. Ageing buckets. |
| Day Book | Finance → Day Book | Today's cash position. |
| Finance Reports | Finance → Finance Reports | Pre-built collection / expense / period reports. |
| Trial Balance | Finance → Trial Balance | Accounting trial balance. |
| Profit & Loss | Finance → Profit & Loss | Income vs expenses for a period. |
| Balance Sheet | Finance → Balance Sheet | Assets vs liabilities at a point in time. |
| Route Report | Transport → Route Report | Per-route headcount and fee. |
| Transport Fee Defaulters | Transport → Transport Fee Defaulters | Overdue transport fee list. |
| Roll Call Report | Hostel → Roll Call Report | Hostel roll call summary. |
| Meal Report | Hostel → Meal Report | Hostel meal headcount. |
| Communication Analytics | Communication → Communication Analytics | Send / delivery / engagement by channel. |
| Front Office Daily Report | Front Office → Daily Report | Visitors, calls, gate passes, complaints today. |
| Activity Log | Settings → Activity Log | Audit trail. |
| Error Log | Settings → Error Log | System errors (mostly for IT). |
| AI Insights | Dashboard → AI Intelligence Hub | LLM-generated narrative analytics. |

---

# Appendix D — Exports Index

Almost every list page in the system has an export button. The system
supports four output formats, selected by the URL query parameter
`?output=…`:

| Format | Query value | Output |
|---|---|---|
| Excel (default) | `?output=excel` | `.xlsx` download |
| CSV | `?output=csv` | `.csv` download |
| PDF | `?output=pdf` | `.pdf` download (A4) |
| HTML print view | `?output=html` | inline printable view |

You don't usually need to type the URL — every list page has dropdown
buttons for **Export → Excel / CSV / PDF / Print**.

Common exports:
- **Students** — full directory or filtered subset.
- **Staff** — directory with salary breakdown.
- **Attendance** — filtered student or staff attendance.
- **Fee Payments** — receipt history.
- **Due Report** — defaulter list.
- **Transactions** — GL transactions.
- **Expenses** — expense register.
- **Budget Management** — budget vs actual.
- **Diary entries**.
- **Book List**.
- **Chart of Accounts**.
- **Assignments**.

For backups, the recommended monthly cadence: export Students, Staff, Fee
Payments, Attendance, and the Finance Transactions register at month-end —
keep the Excel files in your school's cloud drive.

---

# Appendix E — Communication Templates

The system comes with starter templates per channel. You can edit them at:

- Sidebar → Communication → **SMS Templates**
- Sidebar → Communication → **WhatsApp Templates**
- Sidebar → Communication → **Voice Templates**
- Sidebar → Communication → **Push Templates**
- Sidebar → Communication → **Email Templates**

Templates support **placeholders** that get filled in at send time:

| Placeholder | Filled with |
|---|---|
| `{student_name}` | The student's full name. |
| `{class_section}` | e.g. "5-A". |
| `{parent_name}` | Mother / Father name from the student record. |
| `{amount_due}` | Pending fee amount. |
| `{due_date}` | Fee due date in school date format. |
| `{school_name}` | From General Configuration. |
| `{date}` | Today's date. |
| `{message}` | Your custom body. |

(Available placeholders depend on the template's context; the editor shows
which ones work where.)

Recommended starter templates:

- **Fee Reminder** — *"Dear {parent_name}, fee of {amount_due} for
  {student_name} (class {class_section}) is due on {due_date}. Please pay
  via the parent portal. — {school_name}"*
- **Absence Alert** — *"Dear {parent_name}, {student_name} is absent today.
  Please confirm. — {school_name}"*
- **General Announcement** — *"{school_name}: {message}"*
- **Holiday Notice** — *"{school_name}: school will be closed on {date}.
  Classes resume the next working day."*

Test every template on yourself before broadcasting.

---

# Appendix F — Glossary (Expanded)

| Term | Definition |
|---|---|
| **Academic Year** | A school year, used to scope every record (students, fees, exams, attendance). Switch the active year via Settings → Academic Years. |
| **Admission No** | Auto-generated unique identifier for every student. Format set in Settings → Number Formats. Doesn't change after admission. |
| **Roll No** | Position within a class-section. Reset every academic year via Sidebar → Students → Roll Numbers. |
| **Active / Inactive** | A staff member or user who can no longer log in / be selected in workflows but is preserved for historical records. Always **deactivate**, don't delete. |
| **Allocation** | A student's assignment to a transport route, hostel bed, or stationary item set. |
| **Audit Log** | Settings → Activity Log. Every meaningful change with user, time, IP, before/after. Available to admin and auditor roles. |
| **Concession** | A discount or waiver applied to a student's fee. Defined as a type in Sidebar → Finance & Fees → Concessions, then assigned per student. |
| **Custom Field** | A school-specific field added to Student / Staff forms via Settings → Custom Fields. |
| **Day Book** | A single day's complete cash movement summary. |
| **Defaulter** | A student with overdue fee. |
| **Edit Request** | A teacher's or parent's proposal to change a student's data, awaiting admin approval. |
| **Emergency Broadcast** | A one-click send-to-all-channels-and-everyone path. Use only for genuine emergencies. |
| **Fee Group** | A bundle of fee heads applied together (e.g. "Class 1–5 Annual"). |
| **Fee Head** | A single line item on a fee bill (Tuition, Lab, Library, Dev Fund). |
| **Fee Structure** | The mapping of fee groups to classes (or sections, or specific students). |
| **Fine** | Late-payment penalty added automatically when a fee head crosses its due date. |
| **GL Auto-posting** | The mapping of each fee head and expense category to a chart-of-accounts ledger. Configured in Sidebar → Finance & Fees → GL Auto-Posting. |
| **Grading System** | The mapping of marks to grades, used by exam results. |
| **Holiday** | A non-working day declared in Sidebar → Holidays & Events. The 00:30 holiday-fill job auto-marks attendance for these. |
| **Incharge** | The class teacher / section in-charge assigned in Sidebar → Staff & HR → Incharge Assignment. |
| **Ledger** | An accounting account (Cash, Bank, Tuition Income, Salary Expense, etc.). |
| **Permission** | A specific action a role can perform (e.g. `view_fee`, `generate_fee_receipt`). |
| **Promote** | Move a student to the next class at year-end via the Rollover Wizard. |
| **Receipt Copy Types** | Original, Duplicate, Office Copy, Triplicate — labelled receipt copies set in Receipt Print Settings. |
| **Role** | A named bundle of permissions (e.g. `accountant`, `teacher`). Assigned per user. |
| **Rollover** | The end-of-year migration from the closing year to the new year. |
| **Section** | A subdivision of a class (A, B, C, etc.). |
| **Term** | A division of the academic year (Term I / II / III, or Mid / Final). |
| **Timetable** | The per-section weekly schedule of period × day → teacher + subject. |
| **User** | A login account. Linked to a Staff record (for teachers, accountant, principal, etc.) or a Student / Parent record (for portal users). |

---

# Appendix G — Change Log

| Version | Date | Notes |
|---|---|---|
| 1.0 | 2026-04-29 | Initial release. Module-by-module coverage of all 25 functional areas, sidebar map sourced from `resources/js/config/sidebar.js`, role definitions sourced from `RolePermissionSeeder`. |

---

*End of School Admin Operating Manual. For technical queries, contact your
IT support contact at Trivartha Tech Pvt Ltd.*
