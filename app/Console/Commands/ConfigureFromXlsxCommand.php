<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;

/**
 * ConfigureFromXlsxCommand
 * -----------------------------------------------------------------------------
 * Reads a school-setup.xlsx file (3 sheets: School / Database / Mail &
 * Integrations) and generates a .env file from it. Each sheet is a 2-column
 * table of [Label, Value] rows; this command maps every known label to an
 * .env key and writes the result.
 *
 * Used by bootstrap.sh as the friendly alternative to manual .env editing.
 *
 * Usage:
 *   php artisan school:configure-from-xlsx                    # uses school-setup.xlsx
 *   php artisan school:configure-from-xlsx custom-config.xlsx
 *   php artisan school:configure-from-xlsx --init             # (re)generate the example template
 */
class ConfigureFromXlsxCommand extends Command
{
    protected $signature = 'school:configure-from-xlsx
        {file=school-setup.xlsx : Path to the filled-in xlsx config}
        {--init : Regenerate school-setup.example.xlsx instead of reading}';

    protected $description = 'Generate .env from a school-setup.xlsx config file';

    /**
     * Mapping: xlsx label → .env key. Keep these labels in lockstep with the
     * cells written by school-setup.example.xlsx.
     */
    private const LABEL_TO_ENV = [
        // ── School sheet ──────────────────────────────────────────────────
        'App Name'                => 'APP_NAME',
        'App URL'                 => 'APP_URL',
        'ERP Edition'             => 'ERP_EDITION',
        'Timezone'                => 'APP_TIMEZONE',
        'School Name'             => 'SCHOOL_NAME',
        'School Slug'             => 'SCHOOL_SLUG',
        'School Code'             => 'SCHOOL_CODE',
        'School Board'            => 'SCHOOL_BOARD',
        'School Email'            => 'SCHOOL_EMAIL',
        'School Phone'            => 'SCHOOL_PHONE',
        'School Address'          => 'SCHOOL_ADDRESS',
        'School City'             => 'SCHOOL_CITY',
        'School State'            => 'SCHOOL_STATE',
        'School Pincode'          => 'SCHOOL_PINCODE',
        'School Website'          => 'SCHOOL_WEBSITE',
        'Principal Name'          => 'SCHOOL_PRINCIPAL_NAME',
        'Currency'                => 'SCHOOL_CURRENCY',
        'Language'                => 'SCHOOL_LANGUAGE',
        'Org Name'                => 'ORG_NAME',
        'Org Slug'                => 'ORG_SLUG',
        'Org Email'               => 'ORG_EMAIL',
        'Org Website'             => 'ORG_WEBSITE',
        'Super Admin Email'       => 'SUPER_ADMIN_EMAIL',
        'School Admin Email'      => 'ADMIN_EMAIL',
        'Principal Email'         => 'PRINCIPAL_EMAIL',
        'Default Password'        => 'DEFAULT_PASSWORD',

        // ── Database sheet ────────────────────────────────────────────────
        'DB Host'                                    => 'DB_HOST',
        'DB Port'                                    => 'DB_PORT',
        'DB Name'                                    => 'DB_DATABASE',
        'DB User'                                    => 'DB_USERNAME',
        'DB Password'                                => 'DB_PASSWORD',
        'Root User (optional, for auto-create)'      => 'DB_ROOT_USERNAME',
        'Root Password (optional, for auto-create)'  => 'DB_ROOT_PASSWORD',

        // ── Mail & Integrations sheet ─────────────────────────────────────
        'Mail Host'                 => 'MAIL_HOST',
        'Mail Port'                 => 'MAIL_PORT',
        'Mail Username'             => 'MAIL_USERNAME',
        'Mail Password'             => 'MAIL_PASSWORD',
        'Mail From Address'         => 'MAIL_FROM_ADDRESS',
        'Razorpay Key ID'           => 'RAZORPAY_KEY_ID',
        'Razorpay Key Secret'       => 'RAZORPAY_KEY_SECRET',
        'Razorpay Webhook Secret'   => 'RAZORPAY_WEBHOOK_SECRET',
        'Firebase Credentials Path' => 'FIREBASE_CREDENTIALS',
        'Gemini API Key'            => 'GEMINI_API_KEY',
        'Groq API Key'              => 'GROQ_API_KEY',
        'Log Viewer Secret'         => 'LOG_VIEWER_SECRET',
    ];

    public function handle(): int
    {
        if ($this->option('init')) {
            return $this->generateTemplate();
        }

        $file = $this->argument('file');
        if (!is_file($file)) {
            $this->error("File not found: $file");
            $this->line("Tip: cp school-setup.example.xlsx school-setup.xlsx, fill it, retry.");
            return self::FAILURE;
        }

        // Read all sheets as flat arrays of rows
        $importer = new class implements ToArray {
            public array $rows = [];
            public function array(array $rows): array
            {
                $this->rows = array_merge($this->rows, $rows);
                return $rows;
            }
        };

        $sheets = Excel::toArray($importer, $file);

        // Flatten: every sheet returns its rows as a sub-array
        $kv = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet as $row) {
                $label = trim((string) ($row[0] ?? ''));
                $value = trim((string) ($row[1] ?? ''));

                // Skip header / empty / non-mapped rows
                if ($label === '' || strtolower($label) === 'label') {
                    continue;
                }

                $kv[$label] = $value;
            }
        }

        // Start from .env.example as scaffold; override with xlsx values
        $scaffold = '.env.example';
        if (!is_file($scaffold)) {
            $this->error("Scaffold missing: $scaffold");
            return self::FAILURE;
        }
        $envContent = file_get_contents($scaffold);

        $appliedCount = 0;
        foreach (self::LABEL_TO_ENV as $label => $envKey) {
            if (!array_key_exists($label, $kv)) {
                continue;
            }

            $value = $kv[$label];
            $line  = $this->buildEnvLine($envKey, $value);

            $pattern = '/^' . preg_quote($envKey, '/') . '=.*$/m';
            $newContent = preg_replace($pattern, $line, $envContent, 1, $count);

            if ($count > 0) {
                $envContent = $newContent;
                $appliedCount++;
            } else {
                // Key not in scaffold — append at end
                $envContent .= "\n" . $line;
                $appliedCount++;
            }
        }

        file_put_contents('.env', $envContent);

        $this->info('✅ .env generated from ' . $file . " (applied $appliedCount values)");
        return self::SUCCESS;
    }

    /**
     * Build a single .env line, quoting only when needed.
     */
    private function buildEnvLine(string $key, string $value): string
    {
        if ($value === '') {
            return $key . '=';
        }
        // Quote if value contains whitespace or special chars
        if (preg_match('/[\s"#$\\\\]/', $value)) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            return $key . '="' . $escaped . '"';
        }
        return $key . '=' . $value;
    }

    /**
     * Regenerate school-setup.example.xlsx from the LABEL_TO_ENV map +
     * .env.production.example default values.
     */
    private function generateTemplate(): int
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->error('PhpSpreadsheet not available — composer install first.');
            return self::FAILURE;
        }

        $defaults = $this->parseEnvDefaults('.env.production.example');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $sheets = [
            'School' => [
                ['App Name',           $defaults['APP_NAME']           ?? 'Your School Name'],
                ['App URL',            $defaults['APP_URL']            ?? 'https://yourschool.com'],
                ['ERP Edition',        $defaults['ERP_EDITION']        ?? 'full'],
                ['Timezone',           $defaults['APP_TIMEZONE']       ?? 'Asia/Kolkata'],
                ['School Name',        $defaults['SCHOOL_NAME']        ?? 'Your School Name'],
                ['School Slug',        $defaults['SCHOOL_SLUG']        ?? 'your-school'],
                ['School Code',        $defaults['SCHOOL_CODE']        ?? 'YS001'],
                ['School Board',       $defaults['SCHOOL_BOARD']       ?? 'CBSE'],
                ['School Email',       $defaults['SCHOOL_EMAIL']       ?? 'admin@yourschool.com'],
                ['School Phone',       ''],
                ['School Address',     ''],
                ['School City',        ''],
                ['School State',       ''],
                ['School Pincode',     ''],
                ['School Website',     $defaults['SCHOOL_WEBSITE']     ?? 'https://yourschool.com'],
                ['Principal Name',     $defaults['SCHOOL_PRINCIPAL_NAME'] ?? 'Principal'],
                ['Currency',           $defaults['SCHOOL_CURRENCY']    ?? 'INR'],
                ['Language',           $defaults['SCHOOL_LANGUAGE']    ?? 'en'],
                ['Org Name',           $defaults['ORG_NAME']           ?? 'Your School Trust'],
                ['Org Slug',           $defaults['ORG_SLUG']           ?? 'your-school-trust'],
                ['Org Email',          $defaults['ORG_EMAIL']          ?? 'info@yourschool.com'],
                ['Org Website',        $defaults['ORG_WEBSITE']        ?? 'https://yourschool.com'],
                ['Super Admin Email',  $defaults['SUPER_ADMIN_EMAIL']  ?? 'superadmin@yourschool.com'],
                ['School Admin Email', $defaults['ADMIN_EMAIL']        ?? 'admin@yourschool.com'],
                ['Principal Email',    $defaults['PRINCIPAL_EMAIL']    ?? 'principal@yourschool.com'],
                ['Default Password',   $defaults['DEFAULT_PASSWORD']   ?? 'ChangeMe@2026'],
            ],
            'Database' => [
                ['DB Host',                                    $defaults['DB_HOST']     ?? '127.0.0.1'],
                ['DB Port',                                    $defaults['DB_PORT']     ?? '3306'],
                ['DB Name',                                    'yourschool_erp'],
                ['DB User',                                    'yourschool_db'],
                ['DB Password',                                ''],
                ['Root User (optional, for auto-create)',     ''],
                ['Root Password (optional, for auto-create)', ''],
            ],
            'Mail & Integrations' => [
                ['Mail Host',                 ''],
                ['Mail Port',                 ''],
                ['Mail Username',             ''],
                ['Mail Password',             ''],
                ['Mail From Address',         $defaults['MAIL_FROM_ADDRESS']?? 'no-reply@yourschool.com'],
                ['Razorpay Key ID',           ''],
                ['Razorpay Key Secret',       ''],
                ['Razorpay Webhook Secret',   ''],
                ['Firebase Credentials Path', ''],
                ['Gemini API Key',            ''],
                ['Groq API Key',              ''],
                ['Log Viewer Secret',         ''],
            ],
        ];

        foreach ($sheets as $name => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray(['Label', 'Value'], null, 'A1');

            // Bold + light fill for header row
            $sheet->getStyle('A1:B1')->getFont()->setBold(true);
            $sheet->getStyle('A1:B1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E5E7EB');

            $rowIdx = 2;
            foreach ($rows as $row) {
                $sheet->setCellValue('A' . $rowIdx, $row[0]);
                $sheet->setCellValue('B' . $rowIdx, $row[1]);
                $rowIdx++;
            }

            $sheet->getColumnDimension('A')->setWidth(40);
            $sheet->getColumnDimension('B')->setWidth(40);
            $sheet->freezePane('A2');
        }

        // Make first sheet the active one
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('school-setup.example.xlsx');

        $this->info('✅ Regenerated school-setup.example.xlsx');
        return self::SUCCESS;
    }

    /**
     * Parse .env.production.example into a key→value map.
     */
    private function parseEnvDefaults(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                $val = trim($m[2]);
                // Strip surrounding quotes
                if (preg_match('/^"(.*)"$/', $val, $q)) {
                    $val = $q[1];
                }
                $out[$m[1]] = $val;
            }
        }
        return $out;
    }
}
