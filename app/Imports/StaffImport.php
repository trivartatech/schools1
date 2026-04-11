<?php

namespace App\Imports;

use App\Concerns\ItemImport;
use App\Models\Staff;
use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StaffImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected int $schoolId;
    protected bool $validateOnly;
    protected int $importedCount = 0;

    protected array $validRoles = [
        'admin', 'teacher', 'accountant', 'librarian', 'receptionist',
        'hr', 'transport_manager', 'driver', 'conductor',
        'hostel_warden', 'nurse', 'it_support', 'auditor',
    ];

    public function __construct(int $schoolId, bool $validateOnly = false)
    {
        $this->schoolId = $schoolId;
        $this->validateOnly = $validateOnly;
    }

    protected array $requiredHeadings = ['name', 'email', 'employee_id'];

    public function collection(Collection $rows)
    {
        if (!$this->validateHeadings($rows)) {
            return;
        }
        if (!$this->validateRequiredHeadings($rows)) {
            return;
        }

        $departments = Department::where('school_id', $this->schoolId)->pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
        $designations = Designation::where('school_id', $this->schoolId)->where('is_active', true)->pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);
        $existingEmpIds = Staff::where('school_id', $this->schoolId)->pluck('employee_id')->filter()->map(fn($v) => strtolower($v))->toArray();
        $existingEmails = User::where('school_id', $this->schoolId)->pluck('email')->filter()->map(fn($v) => strtolower($v))->toArray();

        $newEmpIds = [];
        $newEmails = [];

        // Phase 1: Validate
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // Required: Name
            if (empty(trim($row['name'] ?? ''))) {
                $this->setError($rowNum, 'name', 'Name is required.');
            }

            // Required: Email (with uniqueness)
            if (empty(trim($row['email'] ?? ''))) {
                $this->setError($rowNum, 'email', 'Email is required.');
            } else {
                $email = strtolower(trim($row['email']));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->setError($rowNum, 'email', "Invalid email format.");
                } elseif (in_array($email, $existingEmails) || in_array($email, $newEmails)) {
                    $this->setError($rowNum, 'email', "Email '{$row['email']}' already exists or duplicated in file.");
                } else {
                    $newEmails[] = $email;
                }
            }

            // Required: Employee ID (with uniqueness)
            if (empty(trim($row['employee_id'] ?? ''))) {
                $this->setError($rowNum, 'employee_id', 'Employee ID is required.');
            } else {
                $empId = strtolower(trim($row['employee_id']));
                if (in_array($empId, $existingEmpIds) || in_array($empId, $newEmpIds)) {
                    $this->setError($rowNum, 'employee_id', "Employee ID '{$row['employee_id']}' already exists or duplicated in file.");
                } else {
                    $newEmpIds[] = $empId;
                }
            }

            // Role
            if (!empty(trim($row['role'] ?? '')) && !in_array(strtolower(trim($row['role'])), $this->validRoles)) {
                $this->setError($rowNum, 'role', "Invalid role. Valid: " . implode(', ', $this->validRoles));
            }

            // Department & Designation
            if (!empty(trim($row['department'] ?? '')) && !$departments->has(strtolower(trim($row['department'])))) {
                $this->setError($rowNum, 'department', "Department '{$row['department']}' not found.");
            }

            if (!empty(trim($row['designation'] ?? '')) && !$designations->has(strtolower(trim($row['designation'])))) {
                $this->setError($rowNum, 'designation', "Designation '{$row['designation']}' not found.");
            }

            // Joining date
            if (!empty(trim($row['joining_date'] ?? ''))) {
                if (!$this->parseDate($row['joining_date'])) {
                    $this->setError($rowNum, 'joining_date', "Invalid date format.");
                }
            }

            // Phone
            $this->validatePhone(trim($row['phone'] ?? ''), $rowNum, 'phone');

            // Salary
            if (!empty(trim($row['basic_salary'] ?? ''))) {
                if (!is_numeric($row['basic_salary'])) {
                    $this->setError($rowNum, 'basic_salary', "Must be a number.");
                } elseif ((float) $row['basic_salary'] < 0) {
                    $this->setError($rowNum, 'basic_salary', "Salary cannot be negative.");
                }
            }

            // Experience years
            if (!empty(trim($row['experience_years'] ?? ''))) {
                if (!is_numeric($row['experience_years'])) {
                    $this->setError($rowNum, 'experience_years', "Must be a number.");
                } elseif ((float) $row['experience_years'] < 0 || (float) $row['experience_years'] > 60) {
                    $this->setError($rowNum, 'experience_years', "Experience years must be between 0 and 60.");
                }
            }

            // PAN
            $this->validatePAN(trim($row['pan_no'] ?? ''), $rowNum, 'pan_no');

            // IFSC
            $this->validateIFSC(trim($row['ifsc_code'] ?? ''), $rowNum, 'ifsc_code');

            // Bank account number
            if (!empty(trim($row['bank_account_no'] ?? ''))) {
                $accNo = preg_replace('/\s/', '', trim($row['bank_account_no']));
                if (!preg_match('/^\d{9,18}$/', $accNo)) {
                    $this->setError($rowNum, 'bank_account_no', "Bank account number must be 9-18 digits.");
                }
            }
        }

        if ($this->validateOnly || $this->hasErrors()) {
            return;
        }

        // Phase 2: Import
        DB::transaction(function () use ($rows, $departments, $designations) {
            foreach ($rows as $row) {
                $role = strtolower(trim($row['role'] ?? 'teacher'));
                if (!in_array($role, $this->validRoles)) $role = 'teacher';

                $user = User::create([
                    'name' => trim($row['name']),
                    'email' => trim($row['email']),
                    'phone' => trim($row['phone'] ?? ''),
                    'user_type' => $role,
                    'school_id' => $this->schoolId,
                    'password' => Hash::make(Str::random(10)),
                    'is_active' => true,
                ]);

                $user->syncRoles([$role]);

                Staff::create([
                    'school_id' => $this->schoolId,
                    'user_id' => $user->id,
                    'employee_id' => trim($row['employee_id']),
                    'department_id' => !empty(trim($row['department'] ?? '')) ? $departments->get(strtolower(trim($row['department']))) : null,
                    'designation_id' => !empty(trim($row['designation'] ?? '')) ? $designations->get(strtolower(trim($row['designation']))) : null,
                    'qualification' => trim($row['qualification'] ?? ''),
                    'experience_years' => is_numeric($row['experience_years'] ?? '') ? (int) $row['experience_years'] : 0,
                    'joining_date' => $this->parseDate($row['joining_date'] ?? null),
                    'basic_salary' => is_numeric($row['basic_salary'] ?? '') ? $row['basic_salary'] : 0,
                    'bank_name' => trim($row['bank_name'] ?? ''),
                    'bank_account_no' => trim($row['bank_account_no'] ?? ''),
                    'ifsc_code' => trim($row['ifsc_code'] ?? ''),
                    'pan_no' => trim($row['pan_no'] ?? ''),
                    'epf_no' => trim($row['epf_no'] ?? ''),
                    'status' => 'active',
                ]);

                $this->importedCount++;
            }
        });
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
