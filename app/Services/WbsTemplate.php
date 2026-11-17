<?php

namespace App\Services;

use App\Models\WbsItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class WbsTemplate
{
    /**
     * The WBS items new deliverables start from, as configured by TIF admins (Admin > WBS items).
     *
     * @return Collection<int, WbsItem>
     */
    public static function items(): Collection
    {
        return WbsItem::orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * The original standard WBS. Used once to fill the wbs_items table; edit items in the app afterwards.
     * offset_start / offset_finish = days from the project start date.
     */
    public static function standard(): array
    {
        return [
            ['phase' => 'Preparing', 'name' => 'Team Building/Pembentukan Satgas', 'weight' => 5, 'circulation' => 'NDE', 'evidence_required' => 'SK/NDE Satgas', 'offset_start' => 0, 'offset_finish' => 0],
            ['phase' => 'Preparing', 'name' => 'Project Charter', 'weight' => 5, 'circulation' => 'Project & Program Manager TLKM & Mitra', 'evidence_required' => 'Project Charter', 'offset_start' => 0, 'offset_finish' => 0],
            ['phase' => 'Preparing', 'name' => 'Kickoff & Design Review Meeting', 'weight' => 10, 'circulation' => 'PM TLKM & Mitra, User', 'evidence_required' => 'Undangan, Materi & MoM', 'offset_start' => 1, 'offset_finish' => 2],
            ['phase' => 'Planning', 'name' => 'Requirement Document', 'weight' => 4, 'circulation' => 'PM TLKM & Mitra, Business User (if any)', 'evidence_required' => 'Requirement Document & Requirement Traceability Matrix', 'offset_start' => 11, 'offset_finish' => 18],
            ['phase' => 'Planning', 'name' => 'Analysis Document (*inc Security Analysis)', 'weight' => 4, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Analysis Document (*inc Security Analysis)', 'offset_start' => 16, 'offset_finish' => 37],
            ['phase' => 'Planning', 'name' => 'Design Document', 'weight' => 4, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Design Document', 'offset_start' => 28, 'offset_finish' => 39],
            ['phase' => 'Planning', 'name' => 'System & Data Security Plan Document', 'weight' => 2, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'System & Data Security Plan Document', 'offset_start' => 28, 'offset_finish' => 39],
            ['phase' => 'Planning', 'name' => 'Unit Test Plan Document', 'weight' => 2, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Unit Test Plan Document', 'offset_start' => 28, 'offset_finish' => 39],
            ['phase' => 'Planning', 'name' => 'SIT Plan Document', 'weight' => 3, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'SIT Plan Document', 'offset_start' => 28, 'offset_finish' => 39],
            ['phase' => 'Planning', 'name' => 'UAT Plan Document', 'weight' => 3, 'circulation' => 'PM TLKM & Mitra, User/Tim Accept (UAT)', 'evidence_required' => 'UAT Plan Document', 'offset_start' => 28, 'offset_finish' => 39],
            ['phase' => 'Planning', 'name' => 'Security Acceptance Test & Performance Test Plan Document', 'weight' => 3, 'circulation' => 'PM TLKM & Mitra, User/Tim Accept', 'evidence_required' => 'Security Test Plan & Performance Test Plan Document', 'offset_start' => 42, 'offset_finish' => 116],
            ['phase' => 'Planning', 'name' => 'Infrastructure Plan Document', 'weight' => 3, 'circulation' => 'Sesuai template dokumen terbaru', 'evidence_required' => 'Infrastructure Plan Document', 'offset_start' => 42, 'offset_finish' => 116],
            ['phase' => 'Planning', 'name' => 'Migration Plan Document (if any)', 'weight' => 2, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Migration Plan Document', 'offset_start' => 42, 'offset_finish' => 116],
            ['phase' => 'Implementation', 'name' => 'Development & Instalasi', 'weight' => 4, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Dokumen Development & BA Instalasi', 'offset_start' => 42, 'offset_finish' => 116],
            ['phase' => 'Implementation', 'name' => 'Rollback Scenario', 'weight' => 2, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Rollback Scenario Document', 'offset_start' => 42, 'offset_finish' => 116],
            ['phase' => 'Implementation', 'name' => 'Security & Quality Source Code Review', 'weight' => 4, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Security & Quality Source Code Review Report', 'offset_start' => 42, 'offset_finish' => 116],
            ['phase' => 'Implementation', 'name' => 'Unit Test Result', 'weight' => 4, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Unit Test Report Document', 'offset_start' => 42, 'offset_finish' => 116],
            ['phase' => 'Implementation', 'name' => 'System Integration Test (SIT) Result', 'weight' => 5, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'SIT Report Document', 'offset_start' => 119, 'offset_finish' => 130],
            ['phase' => 'Implementation', 'name' => 'Security Acceptance Test & Performance Test Result', 'weight' => 5, 'circulation' => 'PM TLKM & Mitra, User/Tim Accept, NDE CSOC', 'evidence_required' => 'Security Test Report & Performance Test Report, NDE Permintaan dan NDE Hasil dari CSOC', 'offset_start' => 119, 'offset_finish' => 130],
            ['phase' => 'Implementation', 'name' => 'User Acceptance Test (UAT) Result', 'weight' => 5, 'circulation' => 'PM TLKM & Mitra, User/Tim Accept', 'evidence_required' => 'UAT Report dan Summary UAT', 'offset_start' => 119, 'offset_finish' => 130],
            ['phase' => 'Implementation', 'name' => 'Migration Result (if any)', 'weight' => 2, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Migration Result Document', 'offset_start' => 133, 'offset_finish' => 134],
            ['phase' => 'Implementation', 'name' => 'Dokumentasi SOP & SMP, ABD (if any)', 'weight' => 2, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'SOP User & Admin, SMP Document & ABD (if any) (terpisah)', 'offset_start' => 133, 'offset_finish' => 135],
            ['phase' => 'Implementation', 'name' => 'Go Live / D2P', 'weight' => 5, 'circulation' => 'PM TLKM & Mitra, User/Tim Accept', 'evidence_required' => 'BA / NDE Go Live atau D2P & Dokumen D2P', 'offset_start' => 133, 'offset_finish' => 134],
            ['phase' => 'Implementation', 'name' => 'Training / Transfer Knowledge', 'weight' => 2, 'circulation' => 'PM TLKM & Mitra', 'evidence_required' => 'Undangan, Materi & BA Transfer Knowledge', 'offset_start' => 133, 'offset_finish' => 135],
            ['phase' => 'Closing', 'name' => 'Pelaksanaan Uji Terima', 'weight' => 5, 'circulation' => "Project & Program Manager TLKM & Mitra (BA Rekon)\nPM TLKM & Mitra, Tim Accept (BAUT)\nPM TLKM & Mitra (Lesson Learned)", 'evidence_required' => 'BAUT, BA Rekon & Lesson Learned Report Document', 'offset_start' => 136, 'offset_finish' => 137],
            ['phase' => 'Closing', 'name' => 'Penerbitan BAST-1', 'weight' => 5, 'circulation' => 'dari TIF', 'evidence_required' => 'BAST-1 Document dari TIF', 'offset_start' => 138, 'offset_finish' => 138],
        ];
    }

    /**
     * Reads a WBS from an Excel file laid out like the template:
     * a header row "No | Nama Activity | Bobot | Sirkulir | Tg. Plan Start | Tg. Plan Finish | Evidence",
     * phase rows numbered with whole numbers (1, 2, 3, 4) and activity rows with decimals (1.1, 1.2, ...).
     *
     * @return array<int, array{phase:string,name:string,weight:float,circulation:?string,plan_start:string,plan_finish:string,evidence_required:?string}>
     */
    public static function fromExcel(string $path): array
    {
        try {
            $sheet = IOFactory::load($path)->getSheet(0);
        } catch (\Throwable $e) {
            throw new RuntimeException('The Excel file could not be read. Make sure it is a .xlsx or .xls file.');
        }

        $rows = $sheet->toArray(null, true, false, false); // raw values, formulas calculated
        $header = null;
        foreach ($rows as $i => $row) {
            if (strtolower(trim((string) ($row[0] ?? ''))) === 'no'
                && str_contains(strtolower((string) ($row[1] ?? '')), 'activity')) {
                $header = $i;
                break;
            }
        }
        if ($header === null) {
            throw new RuntimeException('Header row "No | Nama Activity | Bobot | ..." was not found. Use the standard WBS template layout.');
        }

        $items = [];
        $phase = null;
        foreach (array_slice($rows, $header + 1, null, true) as $i => $row) {
            $line = $i + 1;
            $no = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            if ($no === '' || $name === '') {
                continue; // blank row or total row
            }

            $isPhase = ctype_digit($no) && empty($row[4]) && empty($row[5]);
            if ($isPhase) {
                $phase = $name;

                continue;
            }
            if ($phase === null) {
                throw new RuntimeException("Row {$line}: activity \"{$name}\" appears before any phase row.");
            }

            $start = self::toDate($row[4] ?? null);
            $finish = self::toDate($row[5] ?? null);
            if (! $start || ! $finish) {
                throw new RuntimeException("Row {$line} ({$name}): Plan Start and Plan Finish must both be dates.");
            }
            if ($finish->lt($start)) {
                throw new RuntimeException("Row {$line} ({$name}): Plan Finish is earlier than Plan Start.");
            }
            if (! is_numeric($row[2] ?? null) || $row[2] < 0 || $row[2] > 100) {
                throw new RuntimeException("Row {$line} ({$name}): weight (Bobot) must be a number from 0 to 100.");
            }

            $items[] = [
                'phase' => $phase,
                'name' => $name,
                'weight' => (float) $row[2],
                'circulation' => trim((string) ($row[3] ?? '')) ?: null,
                'plan_start' => $start->toDateString(),
                'plan_finish' => $finish->toDateString(),
                'evidence_required' => trim((string) ($row[6] ?? '')) ?: null,
            ];
        }

        if (! $items) {
            throw new RuntimeException('No activities were found in the Excel file.');
        }

        return $items;
    }

    private static function toDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
