<?php

namespace App\Traits;

use App\Exports\ListExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

trait Exportable
{
    /**
     * Export data as Excel, PDF, or CSV based on ?output= query param.
     *
     * @param  array   $headers   ['Column Name', 'Column Name', ...]
     * @param  array   $rows      [['val1', 'val2', ...], ...]
     * @param  string  $filename  Base filename (without extension)
     * @param  array   $options   Optional: 'footer' => [...], 'orientation' => 'landscape'
     */
    protected function exportResponse(Request $request, array $headers, array $rows, string $filename, array $options = [])
    {
        $output = $request->query('output', 'excel');
        $footer = $options['footer'] ?? [];
        $orientation = $options['orientation'] ?? 'portrait';

        // Build the full data array: headers + rows + optional footer
        $data = [$headers];
        foreach ($rows as $row) {
            $data[] = $row;
        }
        $hasFooter = !empty($footer);
        if ($hasFooter) {
            $data[] = $footer;
        }

        if ($output === 'excel') {
            return Excel::download(new ListExport($data, $hasFooter), $filename . '.xlsx');
        }

        if ($output === 'csv') {
            $handle = fopen('php://memory', 'r+');
            foreach ($data as $line) {
                fputcsv($handle, $line);
            }
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            ]);
        }

        if ($output === 'pdf') {
            $pdf = Pdf::loadView('print.table', [
                'title'       => str_replace('-', ' ', $filename),
                'headers'     => $headers,
                'rows'        => $rows,
                'footerRow'   => $footer,
                'orientation' => $orientation,
                'generatedAt' => now()->format($this->schoolDateFmt() . ', ' . $this->schoolTimeFmt()),
            ])->setPaper('a4', $orientation);

            return $pdf->download($filename . '.pdf');
        }

        // Fallback: HTML print view
        return response()->view('print.table', [
            'title'       => str_replace('-', ' ', $filename),
            'headers'     => $headers,
            'rows'        => $rows,
            'footerRow'   => $footer,
            'orientation' => $orientation,
            'generatedAt' => now()->format($this->schoolDateFmt() . ', ' . $this->schoolTimeFmt()),
        ]);
    }

    /** Convert system-config date_format key to a PHP date() format string. */
    private function schoolDateFmt(): string
    {
        $settings = app('current_school')?->settings ?? [];
        return match($settings['date_format'] ?? '') {
            'DD/MM/YYYY'  => 'd/m/Y',
            'MM/DD/YYYY'  => 'm/d/Y',
            'YYYY-MM-DD'  => 'Y-m-d',
            'D MMM, YYYY' => 'j M, Y',
            default        => 'd M Y',
        };
    }

    /** Convert system-config time_format key to a PHP date() format string. */
    private function schoolTimeFmt(): string
    {
        $settings = app('current_school')?->settings ?? [];
        return match($settings['time_format'] ?? '') {
            'h:mm A'    => 'g:i A',
            'H:mm'      => 'H:i',
            'h:mm:ss A' => 'g:i:s A',
            default      => 'g:i A',
        };
    }
}
