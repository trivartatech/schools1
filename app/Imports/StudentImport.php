<?php

namespace App\Imports;

use App\Concerns\ItemImport;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\StudentAcademicHistory;
use App\Models\CourseClass;
use App\Models\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected int $schoolId;
    protected ?int $academicYearId;
    protected bool $validateOnly;
    protected int $importedCount = 0;

    public function __construct(int $schoolId, ?int $academicYearId, bool $validateOnly = false)
    {
        $this->schoolId = $schoolId;
        $this->academicYearId = $academicYearId;
        $this->validateOnly = $validateOnly;
    }

    protected array $requiredHeadings = ['admission_no', 'first_name'];

    public function collection(Collection $rows)
    {
        if (!$this->validateHeadings($rows)) {
            return;
        }
        if (!$this->validateRequiredHeadings($rows)) {
            return;
        }

        // Pre-load lookup data
        $classes = CourseClass::where('school_id', $this->schoolId)->pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
        $sectionsByClass = [];
        $existingAdmNos = Student::where('school_id', $this->schoolId)->pluck('admission_no')->filter()->map(fn($v) => strtolower($v))->toArray();

        $newAdmNos = [];

        // Phase 1: Validate
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // Required fields
            if (empty(trim($row['first_name'] ?? ''))) {
                $this->setError($rowNum, 'first_name', 'First name is required.');
            }

            if (empty(trim($row['admission_no'] ?? ''))) {
                $this->setError($rowNum, 'admission_no', 'Admission number is required.');
            } else {
                $admNo = strtolower(trim($row['admission_no']));
                if (in_array($admNo, $existingAdmNos)) {
                    $this->setError($rowNum, 'admission_no', "Admission number '{$row['admission_no']}' already exists.");
                } elseif (in_array($admNo, $newAdmNos)) {
                    $this->setError($rowNum, 'admission_no', "Duplicate admission number '{$row['admission_no']}' in file.");
                } else {
                    $newAdmNos[] = $admNo;
                }
            }

            // Class & Section
            if (!empty(trim($row['class'] ?? ''))) {
                $className = strtolower(trim($row['class']));
                if (!$classes->has($className)) {
                    $this->setError($rowNum, 'class', "Class '{$row['class']}' not found. Please create it first.");
                } elseif (!empty(trim($row['section'] ?? ''))) {
                    $classId = $classes->get($className);
                    if (!isset($sectionsByClass[$classId])) {
                        $sectionsByClass[$classId] = Section::where('school_id', $this->schoolId)->where('class_id', $classId)->pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
                    }
                    if (!$sectionsByClass[$classId]->has(strtolower(trim($row['section'])))) {
                        $this->setError($rowNum, 'section', "Section '{$row['section']}' not found in class '{$row['class']}'.");
                    }
                }
            }

            // Gender
            if (!empty(trim($row['gender'] ?? '')) && !in_array(strtolower(trim($row['gender'])), ['male', 'female', 'other'])) {
                $this->setError($rowNum, 'gender', "Invalid gender. Use Male, Female, or Other.");
            }

            // DOB
            if (!empty(trim((string)($row['dob'] ?? '')))) {
                $parsedDob = $this->parseDate($row['dob']);
                if (!$parsedDob) {
                    $this->setError($rowNum, 'dob', "Invalid date format for DOB. Use YYYY-MM-DD or DD/MM/YYYY.");
                } else {
                    $this->validateDOBReasonable($parsedDob, $rowNum, 'dob');
                }
            }

            // Admission date
            if (!empty(trim((string)($row['admission_date'] ?? '')))) {
                $parsedAdmDate = $this->parseDate($row['admission_date']);
                if (!$parsedAdmDate) {
                    $this->setError($rowNum, 'admission_date', "Invalid date format. Use YYYY-MM-DD or DD/MM/YYYY.");
                } else {
                    $this->validateDateNotFuture($parsedAdmDate, $rowNum, 'admission_date');
                }
            }

            // Blood group
            $this->validateBloodGroup(trim($row['blood_group'] ?? ''), $rowNum, 'blood_group');

            // Aadhaar
            $this->validateAadhaar(trim($row['aadhaar_no'] ?? ''), $rowNum, 'aadhaar_no');

            // Pincode
            $this->validatePincode(trim($row['pincode'] ?? ''), $rowNum, 'pincode');

            // Phone numbers
            $this->validatePhone(trim($row['phone'] ?? $row['parent_phone'] ?? ''), $rowNum, 'phone');
            $this->validatePhone(trim($row['father_phone'] ?? ''), $rowNum, 'father_phone');
            $this->validatePhone(trim($row['mother_phone'] ?? ''), $rowNum, 'mother_phone');
            $this->validatePhone(trim($row['guardian_phone'] ?? ''), $rowNum, 'guardian_phone');
            $this->validatePhone(trim($row['emergency_contact_phone'] ?? ''), $rowNum, 'emergency_contact_phone');

            // Emails
            $this->validateEmailFormat(trim($row['guardian_email'] ?? ''), $rowNum, 'guardian_email');

            // Student type — case-insensitive match so 'new student' / 'NEW STUDENT' are accepted.
            $studentType = trim($row['student_type'] ?? '');
            if ($studentType !== '' && !in_array(strtolower($studentType), ['new student', 'old student'])) {
                $this->setError($rowNum, 'student_type', "Student type must be 'New Student' or 'Old Student'.");
            }

            // Enrollment type — free-form on the column (validated max:50 in
            // controller) but constrain the bulk path so accidental typos
            // don't pollute the audit field.
            $enrollmentType = trim($row['enrollment_type'] ?? '');
            if ($enrollmentType !== '' && !in_array($enrollmentType, ['Regular', 'Transfer', 'Lateral', 'Re-admission'], true)) {
                $this->setError($rowNum, 'enrollment_type', "Enrollment type must be Regular, Transfer, Lateral, or Re-admission.");
            }
        }

        if ($this->validateOnly || $this->hasErrors()) {
            return;
        }

        // Phase 2: Import
        DB::transaction(function () use ($rows, $classes, &$sectionsByClass) {
            foreach ($rows as $row) {
                $parent = $this->createParent($row);

                $student = Student::create([
                    'school_id' => $this->schoolId,
                    'parent_id' => $parent?->id,
                    'admission_no' => trim($row['admission_no']),
                    'roll_no' => trim($row['roll_no'] ?? ''),
                    'first_name' => trim($row['first_name']),
                    'last_name' => trim($row['last_name'] ?? ''),
                    'dob' => $this->parseDate($row['dob'] ?? null),
                    'birth_place' => trim($row['birth_place'] ?? ''),
                    'gender' => ucfirst(strtolower(trim($row['gender'] ?? ''))),
                    'blood_group' => trim($row['blood_group'] ?? ''),
                    'religion' => trim($row['religion'] ?? ''),
                    'caste' => trim($row['caste'] ?? ''),
                    'category' => trim($row['category'] ?? ''),
                    'mother_tongue' => trim($row['mother_tongue'] ?? ''),
                    'nationality' => trim($row['nationality'] ?? '') ?: 'Indian',
                    'aadhaar_no' => trim($row['aadhaar_no'] ?? ''),
                    'address' => trim($row['address'] ?? ''),
                    'city' => trim($row['city'] ?? ''),
                    'state' => trim($row['state'] ?? ''),
                    'pincode' => trim($row['pincode'] ?? ''),
                    'emergency_contact_name' => trim($row['emergency_contact_name'] ?? ''),
                    'emergency_contact_phone' => trim($row['emergency_contact_phone'] ?? ''),
                    'admission_date' => $this->parseDate($row['admission_date'] ?? null) ?: now()->toDateString(),
                    'status' => 'Active',
                ]);

                if (!empty(trim($row['class'] ?? '')) && $this->academicYearId) {
                    $classId = $classes->get(strtolower(trim($row['class'])));
                    $sectionId = null;

                    if ($classId && !empty(trim($row['section'] ?? ''))) {
                        if (!isset($sectionsByClass[$classId])) {
                            $sectionsByClass[$classId] = Section::where('school_id', $this->schoolId)->where('class_id', $classId)->pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
                        }
                        $sectionId = $sectionsByClass[$classId]->get(strtolower(trim($row['section'])));
                    }

                    if ($classId) {
                        StudentAcademicHistory::create([
                            'student_id'       => $student->id,
                            'school_id'        => $this->schoolId,
                            'academic_year_id' => $this->academicYearId,
                            'class_id'         => $classId,
                            'section_id'       => $sectionId,
                            'roll_no'          => trim($row['roll_no'] ?? ''),
                            'status'           => 'current',
                            'student_type'    => ucwords(strtolower(trim($row['student_type'] ?? '')))    ?: 'New Student',
                            'enrollment_type' => ucwords(strtolower(trim($row['enrollment_type'] ?? ''))) ?: 'Regular',
                        ]);
                    }
                }

                $this->importedCount++;
            }
        });
    }

    protected function createParent(Collection $row): ?StudentParent
    {
        $fatherName   = trim($row['father_name']   ?? '');
        $motherName   = trim($row['mother_name']   ?? '');
        $guardianName = trim($row['guardian_name'] ?? '');

        if (!$fatherName && !$motherName && !$guardianName) {
            return null;
        }

        // Field set matches StudentParent::$fillable. father_email and
        // mother_email are intentionally not here — those columns don't
        // exist on the parents table.
        return StudentParent::create([
            'school_id'            => $this->schoolId,
            'father_name'          => $fatherName,
            'mother_name'          => $motherName,
            'guardian_name'        => $guardianName,
            'primary_phone'        => trim($row['phone'] ?? $row['parent_phone'] ?? ''),
            'father_phone'         => trim($row['father_phone'] ?? ''),
            'mother_phone'         => trim($row['mother_phone'] ?? ''),
            'guardian_email'       => trim($row['guardian_email'] ?? ''),
            'guardian_phone'       => trim($row['guardian_phone'] ?? ''),
            'father_occupation'    => trim($row['father_occupation']    ?? ''),
            'father_qualification' => trim($row['father_qualification'] ?? ''),
            'mother_occupation'    => trim($row['mother_occupation']    ?? ''),
            'mother_qualification' => trim($row['mother_qualification'] ?? ''),
            'address'              => trim($row['parent_address'] ?? ''),
        ]);
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
