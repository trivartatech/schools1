<?php

namespace App\Exports;

use App\Models\Student;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class StudentQRExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $schoolId;
    protected $academicYearId;
    protected $classId;
    protected $sectionId;
    protected $students;
    protected $tempFiles = [];

    public function __construct($schoolId, $academicYearId, $classId = null, $sectionId = null)
    {
        $this->schoolId       = $schoolId;
        $this->academicYearId = $academicYearId;
        $this->classId        = $classId;
        $this->sectionId      = $sectionId;
    }

    public function collection()
    {
        $query = Student::with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('school_id', $this->schoolId);

        if ($this->classId) {
            $query->whereHas('currentAcademicHistory', function ($q) {
                $q->where('academic_year_id', $this->academicYearId)
                  ->where('class_id', $this->classId);
            });
        }

        if ($this->sectionId) {
            $query->whereHas('currentAcademicHistory', function ($q) {
                $q->where('academic_year_id', $this->academicYearId)
                  ->where('section_id', $this->sectionId);
            });
        }

        $this->students = $query->orderBy('first_name')->get();
        return $this->students;
    }

    public function headings(): array
    {
        return [
            'Admission No',
            'Roll No',
            'First Name',
            'Last Name',
            'Class',
            'Section',
            'QR Code',          // Column G — embedded image
            'Attendance Link',
        ];
    }

    public function map($student): array
    {
        $history = $student->currentAcademicHistory;
        return [
            $student->admission_no,
            $student->roll_no,
            $student->first_name,
            $student->last_name,
            $history->courseClass->name ?? 'N/A',
            $history->section->name ?? 'N/A',
            '',                 // Placeholder for embedded QR
            url('/q/' . $student->uuid),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet  = $event->sheet->getDelegate();
                $sheet->getColumnDimension('G')->setWidth(18);

                $writer = new PngWriter();
                $rowIndex = 2;

                foreach ($this->students as $student) {
                    $sheet->getRowDimension($rowIndex)->setRowHeight(100);

                    $profileUrl = url('/q/' . $student->uuid);

                    // Generate QR using GD — no imagick required
                    $qrCode = new QrCode($profileUrl);
                    $qrCode->setSize(280);
                    $qrCode->setMargin(6);

                    $result  = $writer->write($qrCode);
                    $tmpPath = sys_get_temp_dir() . '/qr_' . $student->uuid . '.png';
                    file_put_contents($tmpPath, $result->getString());
                    $this->tempFiles[] = $tmpPath;

                    $drawing = new Drawing();
                    $drawing->setName('QR_' . $student->admission_no);
                    $drawing->setDescription('Student QR Code');
                    $drawing->setPath($tmpPath);
                    $drawing->setHeight(95);
                    $drawing->setOffsetX(4);
                    $drawing->setOffsetY(3);
                    $drawing->setCoordinates('G' . $rowIndex);
                    $drawing->setWorksheet($sheet);

                    $rowIndex++;
                }

                register_shutdown_function(function () {
                    foreach ($this->tempFiles as $file) {
                        if (file_exists($file)) {
                            @unlink($file);
                        }
                    }
                });
            },
        ];
    }
}
