<?php

namespace App\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

trait ItemImport
{
    protected array $errors = [];
    protected string $logFileName = '';
    protected array $requiredHeadings = [];

    public function validateFile($file): bool
    {
        if (!$file) {
            $this->errors[] = ['row' => '-', 'column' => '-', 'message' => 'No file uploaded.'];
            return false;
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $this->errors[] = ['row' => '-', 'column' => '-', 'message' => 'Invalid file type. Only xlsx, xls, and csv files are allowed.'];
            return false;
        }

        return true;
    }

    public function validateHeadings(Collection $rows): bool
    {
        if ($rows->isEmpty()) {
            $this->errors[] = ['row' => '-', 'column' => '-', 'message' => 'The uploaded file contains no data rows.'];
            return false;
        }

        if ($rows->count() > 1000) {
            $this->errors[] = ['row' => '-', 'column' => '-', 'message' => 'Maximum 1000 rows allowed per import. Your file has ' . $rows->count() . ' rows.'];
            return false;
        }

        return true;
    }

    public function setError(int $row, string $column, string $message): void
    {
        $this->errors[] = ['row' => $row, 'column' => $column, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function writeErrorLog(string $prefix = 'import'): ?string
    {
        if (empty($this->errors)) {
            return null;
        }

        $fileName = $prefix . '-errors-' . auth()->id() . '-' . now()->format('Y-m-d-His') . '.csv';
        $path = 'import-errors/' . $fileName;

        $csv = "Row,Column,Error Message\n";
        foreach ($this->errors as $error) {
            $csv .= '"' . $error['row'] . '","' . str_replace('"', '""', $error['column']) . '","' . str_replace('"', '""', $error['message']) . '"' . "\n";
        }

        Storage::disk('local')->put($path, $csv);
        $this->logFileName = $path;

        return $path;
    }

    public function validateRequiredHeadings(Collection $rows): bool
    {
        if ($rows->isEmpty()) return true;
        $headings = array_keys($rows->first()->toArray());
        $missing = array_diff($this->requiredHeadings, $headings);
        if (!empty($missing)) {
            $this->errors[] = ['row' => '-', 'column' => '-', 'message' => 'Missing required columns: ' . implode(', ', $missing)];
            return false;
        }
        return true;
    }

    protected function validatePhone(?string $value, int $row, string $column): void
    {
        if (empty($value)) return;
        $clean = preg_replace('/[\s\-\(\)\+]/', '', $value);
        if (!preg_match('/^\d{10,13}$/', $clean)) {
            $this->setError($row, $column, "Invalid phone number '{$value}'. Must be 10-13 digits.");
        }
    }

    protected function validateEmailFormat(?string $value, int $row, string $column): void
    {
        if (empty($value)) return;
        if (!filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            $this->setError($row, $column, "Invalid email format '{$value}'.");
        }
    }

    protected function validateAadhaar(?string $value, int $row, string $column): void
    {
        if (empty($value)) return;
        $clean = preg_replace('/[\s\-]/', '', $value);
        if (!preg_match('/^\d{12}$/', $clean)) {
            $this->setError($row, $column, "Aadhaar must be exactly 12 digits.");
        }
    }

    protected function validatePincode(?string $value, int $row, string $column): void
    {
        if (empty($value)) return;
        if (!preg_match('/^\d{6}$/', trim($value))) {
            $this->setError($row, $column, "Pincode must be exactly 6 digits.");
        }
    }

    protected function validateBloodGroup(?string $value, int $row, string $column): void
    {
        if (empty($value)) return;
        $valid = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!in_array(strtoupper(trim($value)), $valid)) {
            $this->setError($row, $column, "Invalid blood group. Valid: " . implode(', ', $valid));
        }
    }

    protected function validatePAN(?string $value, int $row, string $column): void
    {
        if (empty($value)) return;
        if (!preg_match('/^[A-Z]{5}\d{4}[A-Z]$/i', trim($value))) {
            $this->setError($row, $column, "Invalid PAN format. Expected: AAAAA0000A.");
        }
    }

    protected function validateIFSC(?string $value, int $row, string $column): void
    {
        if (empty($value)) return;
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/i', trim($value))) {
            $this->setError($row, $column, "Invalid IFSC format. Expected: AAAA0NNNNNN (11 characters).");
        }
    }

    protected function validateDateNotFuture(?string $dateStr, int $row, string $column): void
    {
        if (empty($dateStr)) return;
        $parsed = $this->parseDate($dateStr);
        if ($parsed && $parsed > now()->toDateString()) {
            $this->setError($row, $column, "Date cannot be in the future.");
        }
    }

    protected function validateDOBReasonable(?string $dateStr, int $row, string $column): void
    {
        if (empty($dateStr)) return;
        $parsed = $this->parseDate($dateStr);
        if (!$parsed) return;
        $dob = new \DateTime($parsed);
        $now = new \DateTime();
        $age = $now->diff($dob)->y;
        if ($dob > $now) {
            $this->setError($row, $column, "Date of birth cannot be in the future.");
        } elseif ($age > 100) {
            $this->setError($row, $column, "Date of birth results in age over 100 years. Please check.");
        } elseif ($age < 2) {
            $this->setError($row, $column, "Student appears to be under 2 years old. Please check DOB.");
        }
    }

    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // PhpSpreadsheet/Maatwebsite may return Carbon or DateTime objects for date-formatted cells
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && $value > 10000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime($value))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
