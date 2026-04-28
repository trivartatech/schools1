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

class StudentBulkUpdateImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected int $schoolId;
    protected ?int $academicYearId;
    protected bool $validateOnly;
    protected int $updatedCount = 0;

    public function __construct(int $schoolId, ?int $academicYearId, bool $validateOnly = false)
    {
        $this->schoolId = $schoolId;
        $this->academicYearId = $academicYearId;
        $this->validateOnly = $validateOnly;
    }

    public function collection(Collection $rows)
    {
        if (!$this->validateHeadings($rows)) {
            return;
        }

        $classes = CourseClass::where('school_id', $this->schoolId)->pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
        $sectionsByClass = [];
        $studentsByErp = Student::where('school_id', $this->schoolId)->whereNotNull('erp_no')->pluck('id', 'erp_no')->mapWithKeys(fn($id, $erp) => [strtolower(trim($erp)) => $id]);

        // Phase 1: Validate
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            if (empty(trim($row['erp_no'] ?? ''))) {
                $this->setError($rowNum, 'erp_no', 'ERP number is required to identify the student.');
                continue;
            }

            $erpNo = strtolower(trim($row['erp_no']));
            if (!$studentsByErp->has($erpNo)) {
                $this->setError($rowNum, 'erp_no', "Student with ERP number '{$row['erp_no']}' not found.");
                continue;
            }

            // Class & Section
            if (!empty(trim($row['class'] ?? ''))) {
                $className = strtolower(trim($row['class']));
                if (!$classes->has($className)) {
                    $this->setError($rowNum, 'class', "Class '{$row['class']}' not found.");
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
            if (!empty(trim($row['dob'] ?? ''))) {
                if (!$this->parseDate($row['dob'])) {
                    $this->setError($rowNum, 'dob', "Invalid date format. Use YYYY-MM-DD or DD/MM/YYYY.");
                } else {
                    $this->validateDOBReasonable($row['dob'], $rowNum, 'dob');
                }
            }

            // Status
            if (!empty(trim($row['status'] ?? ''))) {
                $status = ucfirst(strtolower(trim($row['status'])));
                if (!in_array($status, ['Active', 'Inactive', 'Graduated', 'Transferred', 'Dropped'])) {
                    $this->setError($rowNum, 'status', "Invalid status. Use Active, Inactive, Graduated, Transferred, or Dropped.");
                }
            }

            // Blood group
            $this->validateBloodGroup(trim($row['blood_group'] ?? ''), $rowNum, 'blood_group');

            // Aadhaar
            $this->validateAadhaar(trim($row['aadhaar_no'] ?? ''), $rowNum, 'aadhaar_no');

            // Pincode
            $this->validatePincode(trim($row['pincode'] ?? ''), $rowNum, 'pincode');

            // Phone numbers
            $this->validatePhone(trim($row['phone'] ?? ''), $rowNum, 'phone');
            $this->validatePhone(trim($row['father_phone'] ?? ''), $rowNum, 'father_phone');
            $this->validatePhone(trim($row['mother_phone'] ?? ''), $rowNum, 'mother_phone');
            $this->validatePhone(trim($row['guardian_phone'] ?? ''), $rowNum, 'guardian_phone');
            $this->validatePhone(trim($row['emergency_contact_phone'] ?? ''), $rowNum, 'emergency_contact_phone');

            // Emails
            $this->validateEmailFormat(trim($row['guardian_email'] ?? ''), $rowNum, 'guardian_email');

            // Student type — empty means "leave existing value alone"; the
            // runtime resolver still falls back to the count heuristic when
            // the column is null.
            $studentType = trim($row['student_type'] ?? '');
            if ($studentType !== '' && !in_array($studentType, ['New Student', 'Old Student'], true)) {
                $this->setError($rowNum, 'student_type', "Student type must be 'New Student' or 'Old Student'.");
            }

            // Enrollment type
            $enrollmentType = trim($row['enrollment_type'] ?? '');
            if ($enrollmentType !== '' && !in_array($enrollmentType, ['Regular', 'Transfer', 'Lateral', 'Re-admission'], true)) {
                $this->setError($rowNum, 'enrollment_type', "Enrollment type must be Regular, Transfer, Lateral, or Re-admission.");
            }
        }

        if ($this->validateOnly || $this->hasErrors()) {
            return;
        }

        // Phase 2: Update
        DB::transaction(function () use ($rows, $studentsByErp, $classes, &$sectionsByClass) {
            foreach ($rows as $row) {
                $studentId = $studentsByErp->get(strtolower(trim($row['erp_no'])));
                if (!$studentId) continue;

                $student = Student::find($studentId);
                if (!$student) continue;

                // Only update non-empty fields
                $update = [];
                foreach (['admission_no','first_name','last_name','birth_place','roll_no','blood_group','religion','caste','category','mother_tongue','nationality','aadhaar_no','address','city','state','pincode','emergency_contact_name','emergency_contact_phone'] as $field) {
                    $val = trim($row[$field] ?? '');
                    if ($val !== '') $update[$field] = $val;
                }

                if (!empty(trim($row['gender'] ?? ''))) $update['gender'] = ucfirst(strtolower(trim($row['gender'])));
                if (!empty(trim($row['dob'] ?? ''))) $update['dob'] = $this->parseDate($row['dob']);
                if (!empty(trim($row['status'] ?? ''))) $update['status'] = ucfirst(strtolower(trim($row['status'])));

                if (!empty($update)) {
                    $student->update($update);
                }

                // Update parent
                $this->updateParent($student, $row);

                // Update class/section + student_type/enrollment_type on the
                // current academic-history row. student_type / enrollment_type
                // can be set EVEN WHEN class isn't being changed — fetch the
                // existing history row and patch only the fields that were
                // actually present in the spreadsheet.
                if ($this->academicYearId) {
                    $history = StudentAcademicHistory::where('student_id', $student->id)
                        ->where('academic_year_id', $this->academicYearId)
                        ->first();

                    $historyData = [];

                    if (!empty(trim($row['class'] ?? ''))) {
                        $classId = $classes->get(strtolower(trim($row['class'])));
                        if ($classId) {
                            $historyData['class_id'] = $classId;
                            if (!empty(trim($row['section'] ?? ''))) {
                                if (!isset($sectionsByClass[$classId])) {
                                    $sectionsByClass[$classId] = Section::where('school_id', $this->schoolId)->where('class_id', $classId)->pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
                                }
                                $historyData['section_id'] = $sectionsByClass[$classId]->get(strtolower(trim($row['section'])));
                            }
                        }
                    }

                    if (!empty(trim($row['roll_no'] ?? ''))) {
                        $historyData['roll_no'] = trim($row['roll_no']);
                    }

                    if (!empty(trim($row['student_type'] ?? ''))) {
                        $historyData['student_type'] = trim($row['student_type']);
                    }

                    if (!empty(trim($row['enrollment_type'] ?? ''))) {
                        $historyData['enrollment_type'] = trim($row['enrollment_type']);
                    }

                    if (!empty($historyData)) {
                        if ($history) {
                            $history->update($historyData);
                        } elseif (isset($historyData['class_id'])) {
                            // Only create a new history row if we have a class_id
                            // — student_type alone can't seed a row.
                            StudentAcademicHistory::create(array_merge($historyData, [
                                'student_id'       => $student->id,
                                'school_id'        => $this->schoolId,
                                'academic_year_id' => $this->academicYearId,
                                'status'           => 'current',
                            ]));
                        }
                    }
                }

                $this->updatedCount++;
            }
        });
    }

    protected function updateParent(Student $student, Collection $row): void
    {
        // Field set matches StudentParent::$fillable. father_email /
        // mother_email are intentionally not here — those columns don't
        // exist on the parents table (used to be silently dropped).
        $parentFields = [
            'father_name', 'mother_name', 'guardian_name',
            'phone', 'father_phone', 'mother_phone',
            'guardian_email', 'guardian_phone',
            'father_occupation', 'father_qualification',
            'mother_occupation', 'mother_qualification',
            'parent_address',
        ];

        $hasData = false;
        foreach ($parentFields as $f) {
            if (!empty(trim($row[$f] ?? ''))) { $hasData = true; break; }
        }
        if (!$hasData) return;

        $data = [];
        foreach ([
            'father_name', 'mother_name', 'guardian_name',
            'father_phone', 'mother_phone',
            'guardian_email', 'guardian_phone',
            'father_occupation', 'father_qualification',
            'mother_occupation', 'mother_qualification',
        ] as $f) {
            $val = trim($row[$f] ?? '');
            if ($val !== '') $data[$f] = $val;
        }

        $phone = trim($row['phone'] ?? '');
        if ($phone !== '') $data['primary_phone'] = $phone;

        // Map the form-only key 'parent_address' to the actual column 'address'
        // on the parents table.
        $parentAddress = trim($row['parent_address'] ?? '');
        if ($parentAddress !== '') $data['address'] = $parentAddress;

        if (empty($data)) return;

        if ($student->parent_id) {
            StudentParent::where('id', $student->parent_id)->update($data);
        } else {
            $parent = StudentParent::create(array_merge($data, ['school_id' => $this->schoolId]));
            $student->update(['parent_id' => $parent->id]);
        }
    }

    public function getImportedCount(): int
    {
        return $this->updatedCount;
    }
}
