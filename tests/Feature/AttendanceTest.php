<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\UserType;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\CourseClass;
use App\Models\Department;

use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private AcademicYear $academicYear;
    private User $teacher;
    private Student $student;
    private Section $section;
    private CourseClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name'     => 'Test School',
            'slug'     => 'test-school',
            'is_active'=> true,
        ]);

        $this->academicYear = AcademicYear::create([
            'school_id'  => $this->school->id,
            'name'       => '2025-26',
            'start_date' => '2025-04-01',
            'end_date'   => '2026-03-31',
            'is_current' => true,
        ]);

        $department = Department::create(['school_id' => $this->school->id, 'name' => 'Primary']);
        $this->class = CourseClass::create(['school_id' => $this->school->id, 'department_id' => $department->id, 'name' => 'Class 5']);
        $class = $this->class;
        $this->section = Section::create([
            'school_id'       => $this->school->id,
            'name'            => 'A',
            'course_class_id' => $class->id,
        ]);

        $teacherUser = User::create([
            'name'      => 'Test Teacher',
            'email'     => 'teacher@test.com',
            'password'  => Hash::make('password'),
            'user_type' => UserType::Teacher->value,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
        $this->teacher = $teacherUser;

        $studentUser = User::create([
            'name'      => 'Test Student',
            'email'     => 'student@test.com',
            'password'  => Hash::make('password'),
            'user_type' => UserType::Student->value,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'school_id'      => $this->school->id,
            'user_id'        => $studentUser->id,
            'first_name'     => 'John',
            'last_name'      => 'Doe',
            'gender'         => 'Male',
            'admission_date' => now(),
            'status'         => 'active',
        ]);

        StudentAcademicHistory::create([
            'student_id'       => $this->student->id,
            'school_id'        => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id'         => $class->id,
            'section_id'       => $this->section->id,
            'status'           => 'current',
        ]);

        app()->bind('current_school_id', fn() => $this->school->id);
        app()->bind('current_academic_year_id', fn() => $this->academicYear->id);
    }

    // ── Attendance Model ──────────────────────────────────────────────────────

    public function test_attendance_record_can_be_created(): void
    {
        $attendance = Attendance::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'class_id'         => $this->class->id,
            'section_id'       => $this->section->id,
            'academic_year_id' => $this->academicYear->id,
            'date'             => '2025-06-01',
            'status'           => AttendanceStatus::Present->value,
            'marked_by'        => $this->teacher->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student->id,
            'date'       => '2025-06-01',
            'status'     => AttendanceStatus::Present->value,
        ]);
    }

    public function test_attendance_present_status_counts_as_present(): void
    {
        $this->assertTrue(AttendanceStatus::Present->countsAsPresent());
    }

    public function test_attendance_late_status_counts_as_present(): void
    {
        $this->assertTrue(AttendanceStatus::Late->countsAsPresent());
    }

    public function test_attendance_absent_does_not_count_as_present(): void
    {
        $this->assertFalse(AttendanceStatus::Absent->countsAsPresent());
    }

    public function test_attendance_half_day_counts_as_present(): void
    {
        $this->assertTrue(AttendanceStatus::HalfDay->countsAsPresent());
    }

    public function test_duplicate_attendance_for_same_student_same_date_can_be_updated(): void
    {
        $attendance = Attendance::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'class_id'         => $this->class->id,
            'section_id'       => $this->section->id,
            'academic_year_id' => $this->academicYear->id,
            'date'             => '2025-06-02',
            'status'           => AttendanceStatus::Absent->value,
            'marked_by'        => $this->teacher->id,
        ]);

        // Update to present (late correction)
        $attendance->update(['status' => AttendanceStatus::Present->value]);

        $this->assertDatabaseHas('attendances', [
            'id'     => $attendance->id,
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    public function test_attendance_percentage_calculation(): void
    {
        $date = '2025-06-';
        $days = [
            ['date' => $date . '01', 'status' => AttendanceStatus::Present->value],
            ['date' => $date . '02', 'status' => AttendanceStatus::Present->value],
            ['date' => $date . '03', 'status' => AttendanceStatus::Absent->value],
            ['date' => $date . '04', 'status' => AttendanceStatus::Present->value],
        ];

        foreach ($days as $day) {
            Attendance::create(array_merge($day, [
                'school_id'        => $this->school->id,
                'student_id'       => $this->student->id,
                'class_id'         => $this->class->id,
                'section_id'       => $this->section->id,
                'academic_year_id' => $this->academicYear->id,
                'marked_by'        => $this->teacher->id,
            ]));
        }

        $records  = Attendance::where('student_id', $this->student->id)->get();
        $total    = $records->count();
        $present  = $records->filter(fn($a) => AttendanceStatus::from($a->status)->countsAsPresent())->count();
        $percentage = ($present / $total) * 100;

        $this->assertEquals(4, $total);
        $this->assertEquals(3, $present);
        $this->assertEquals(75.0, $percentage);
    }

    // ── AttendanceStatus Enum ─────────────────────────────────────────────────

    public function test_attendance_status_labels(): void
    {
        $this->assertEquals('Present', AttendanceStatus::Present->label());
        $this->assertEquals('Absent', AttendanceStatus::Absent->label());
        $this->assertEquals('Late', AttendanceStatus::Late->label());
        $this->assertEquals('Half Day', AttendanceStatus::HalfDay->label());
    }

    public function test_attendance_status_colors(): void
    {
        $this->assertEquals('green', AttendanceStatus::Present->color());
        $this->assertEquals('red', AttendanceStatus::Absent->color());
        $this->assertEquals('yellow', AttendanceStatus::Late->color());
    }
}
