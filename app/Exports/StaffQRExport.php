<?php

namespace App\Exports;

use App\Models\Staff;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export of every active staff member with their QR code embedded as
 * a PNG image in column F. Mirrors StudentQRExport. Each QR encodes the
 * "/q/staff/<employee_id>" URL — same shape the mobile rapid-scan handler
 * unwraps via its /staff/<id> regex.
 */
class StaffQRExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected int $schoolId;
    protected ?int $departmentId;
    protected $staffList;
    protected array $tempFiles = [];

    public function __construct(int $schoolId, ?int $departmentId = null)
    {
        $this->schoolId     = $schoolId;
        $this->departmentId = $departmentId;
    }

    public function collection()
    {
        $query = Staff::with(['user:id,name,phone,email', 'designation:id,name', 'department:id,name'])
            ->where('school_id', $this->schoolId)
            ->where('status', 'active')
            ->whereNotNull('employee_id');

        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        }

        $this->staffList = $query
            ->orderBy('employee_id')
            ->get();

        return $this->staffList;
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Name',
            'Designation',
            'Department',
            'Phone',
            'QR Code',          // F — embedded image
            'Scan URL',
        ];
    }

    public function map($staff): array
    {
        return [
            $staff->employee_id,
            $staff->user?->name ?? '—',
            $staff->designation?->name ?? '—',
            $staff->department?->name ?? '—',
            $staff->user?->phone ?? '—',
            '',                                            // QR placeholder
            url('/q/staff/' . $staff->employee_id),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1565C0'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getColumnDimension('F')->setWidth(18);

                $writer  = new PngWriter();
                $rowIndex = 2;

                foreach ($this->staffList as $staff) {
                    $sheet->getRowDimension($rowIndex)->setRowHeight(100);

                    $url = url('/q/staff/' . $staff->employee_id);

                    $qrCode = new QrCode($url);
                    $qrCode->setSize(280);
                    $qrCode->setMargin(6);

                    $result  = $writer->write($qrCode);
                    $tmpPath = sys_get_temp_dir() . '/staff_qr_' . $staff->employee_id . '_' . uniqid() . '.png';
                    file_put_contents($tmpPath, $result->getString());
                    $this->tempFiles[] = $tmpPath;

                    $drawing = new Drawing();
                    $drawing->setName('QR_' . $staff->employee_id);
                    $drawing->setDescription('Staff QR Code');
                    $drawing->setPath($tmpPath);
                    $drawing->setHeight(95);
                    $drawing->setOffsetX(4);
                    $drawing->setOffsetY(3);
                    $drawing->setCoordinates('F' . $rowIndex);
                    $drawing->setWorksheet($sheet);

                    $rowIndex++;
                }

                register_shutdown_function(function () {
                    foreach ($this->tempFiles as $file) {
                        if (file_exists($file)) @unlink($file);
                    }
                });
            },
        ];
    }
}
