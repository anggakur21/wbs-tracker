<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsItem;
use App\Services\WbsTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Dummy data for trying the app: partner companies with PM / Project Admin / BA accounts,
 * projects in different states (ahead, on schedule, lagged, not started), progress, notes and
 * small evidence files. Every demo account signs in with DEMO_PASSWORD from .env.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Safe to run again: it removes the previous demo data (everything tagged "[Demo]") first.
 */
class DemoSeeder extends Seeder
{
    private const TAG = '[Demo]';

    public function run(): void
    {
        $password = env('DEMO_PASSWORD');
        if (! $password || strlen($password) < 8) {
            $this->command->error('Set DEMO_PASSWORD (at least 8 characters) in .env first. All demo accounts use it.');

            return;
        }

        $admin = User::where('role', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->command->error('Create the admin first: php artisan db:seed --class=AdminSeeder');

            return;
        }

        if (WbsItem::count() === 0) {
            $this->command->error('There are no WBS items. Run php artisan migrate first.');

            return;
        }

        DB::transaction(function () use ($password, $admin) {
            $this->removePreviousDemo();

            $today = CarbonImmutable::today();
            $items = WbsTemplate::items();

            // ---- Partners and their people ------------------------------------------------
            $mitra = $this->partner('PT Mitra Solusi Teknologi', 'pmo@mitrasolusi.example', '021 5550 1010', [
                ['Rina Hartono', 'rina.hartono', 'project_manager'],
                ['Dewi Lestari', 'dewi.lestari', 'project_admin'],
                ['Agus Pratama', 'agus.pratama', 'business_analyst'],
            ], $password);

            $nusantara = $this->partner('PT Data Nusantara', 'project@datanusantara.example', null, [
                ['Budi Santoso', 'budi.santoso', 'project_manager'],
                ['Sari Wulandari', 'sari.wulandari', 'business_analyst'],
            ], $password);

            $integrasi = $this->partner('PT Integrasi Digital Indonesia', null, null, [
                ['Yoga Firmansyah', 'yoga.firmansyah', 'project_manager'],
                ['Nadia Putri', 'nadia.putri', 'project_admin', true],   // still on a one-time password
            ], $password);

            // ---- Projects --------------------------------------------------------------
            // [name, contract no, value, partner, start (days ago), deliverables [name, pace, skip optional?]]
            // pace = actual progress as a share of the plan (1.0 = on plan, 0.7 = behind, 1.1 = ahead).
            $this->project($admin, $items, $today, 'Fiber Asset Management System', 'K.TIF.0142/HK.810/2026', 4250000000, $mitra, 95, [
                ['Asset inventory module', 0.72, false],
                ['Field survey mobile app', 1.00, true],
                ['Reporting dashboard', 0.55, true],
            ]);
            $this->project($admin, $items, $today, 'Network Capacity Planner', 'K.TIF.0207/HK.810/2026', 1850000000, $nusantara, 60, [
                ['Capacity forecasting engine', 1.05, true],
                ['Planner web console', 1.00, true],
            ]);
            $this->project($admin, $items, $today, 'Tower Lease Billing', 'K.TIF.0311/HK.810/2026', 2675000000, $integrasi, 150, [
                ['Billing core', 1.00, false],
                ['Customer self-service portal', 0.85, true],
            ]);
            $this->project($admin, $items, $today, 'Infranexia Partner Portal', null, 980000000, $mitra, -10, [
                ['Partner onboarding flow', 1.00, true],
            ]);
        });

        $this->command->info('Demo data loaded: 3 partners, 7 accounts, 4 projects. Every demo account uses DEMO_PASSWORD from .env.');
        $this->command->line('  Partner sign-ins: rina.hartono (PM), dewi.lestari (Project Admin), agus.pratama (BA), budi.santoso, sari.wulandari, yoga.firmansyah, nadia.putri (must change password)');
    }

    /** @param  list<array{0:string,1:string,2:string,3?:bool}>  $people */
    private function partner(string $name, ?string $email, ?string $phone, array $people, string $password): Partner
    {
        $partner = Partner::create(['name' => self::TAG.' '.$name, 'contact_email' => $email, 'phone' => $phone]);

        foreach ($people as $person) {
            $partner->users()->create([
                'name' => $person[0],
                'username' => $person[1],
                'email' => $person[1].'@'.str($name)->after('PT ')->slug()->value().'.example',
                'role' => 'partner',
                'partner_role' => $person[2],
                'password' => $password,
                'must_change_password' => $person[3] ?? false,
            ]);
        }

        return $partner;
    }

    private function project(User $admin, $items, CarbonImmutable $today, string $name, ?string $contract, int $value,
        Partner $partner, int $startedDaysAgo, array $deliverables): void
    {
        $start = $today->subDays($startedDaysAgo);
        $project = Project::create([
            'name' => $name, 'contract_number' => $contract, 'contract_value' => $value,
            'partner_id' => $partner->id, 'created_by' => $admin->id,
        ]);
        $staff = $partner->users()->get();

        foreach ($deliverables as $d => [$deliverableName, $pace, $skipOptional]) {
            $deliverable = $project->deliverables()->create(['name' => $deliverableName, 'sort_order' => $d + 1]);
            $order = 0;

            foreach ($items as $item) {
                if (! $item->is_mandatory && $skipOptional) {
                    continue;   // this deliverable doesn't need the optional step
                }

                // Later deliverables start a little later, so tabs don't all look the same.
                $planStart = $start->addDays($item->offset_start + $d * 7);
                $planFinish = $start->addDays($item->offset_finish + $d * 7);

                $progress = $this->progressFor($planStart, $planFinish, $today, $pace);
                $updater = $progress > 0 ? $staff[($order + $d) % $staff->count()] : null;
                $pm = $staff->firstWhere('partner_role', 'project_manager') ?? $staff->first();

                // Workflow status: finished recently → waiting for review; finished earlier → accepted.
                $status = match (true) {
                    $progress === 0 => 'planned',
                    $progress < 100 => 'in_progress',
                    $planFinish->gte($today->subDays(30)) => 'in_review',
                    default => 'done',
                };
                // A few steps in progress were returned by the admin and are being revised.
                $returned = $status === 'in_progress' && $progress >= 40 && ($order + $d) % 4 === 0;

                $activity = $deliverable->activities()->create([
                    'phase' => $item->phase,
                    'name' => $item->name,
                    'weight' => $item->weight,
                    'is_mandatory' => $item->is_mandatory,
                    'circulation' => $item->circulation,
                    'evidence_required' => $item->evidence_required,
                    'plan_start' => $planStart->toDateString(),
                    'plan_finish' => $planFinish->toDateString(),
                    'sort_order' => ++$order,
                    'progress' => $progress,
                    'status' => $status,
                    'actual_start' => $progress > 0 ? $planStart->addDays($pace < 1 ? 2 : 0)->toDateString() : null,
                    'actual_finish' => $progress >= 100 ? min($planFinish->addDays($pace < 1 ? 3 : 0), $today)->toDateString() : null,
                    'note' => $this->noteFor($progress, $pace),
                    'updated_by' => $updater?->id,
                ]);

                // Submitted and accepted steps always carry evidence (it is required to submit).
                if (in_array($status, ['in_review', 'done'], true) || $returned) {
                    $this->evidence($activity, $deliverable, $updater ?? $pm);
                }

                $submittedAt = $activity->actual_finish ?? $today;
                if ($status === 'in_review') {
                    $activity->reviews()->create([
                        'submitted_by' => $pm->id, 'submitted_at' => $submittedAt->setTime(16, 30),
                        'submission_note' => 'Final version attached, signed by both PMs.', 'progress_before' => 90,
                    ]);
                } elseif ($status === 'done') {
                    $activity->reviews()->create([
                        'submitted_by' => $pm->id, 'submitted_at' => $submittedAt->setTime(16, 30), 'progress_before' => 90,
                        'decision' => 'accepted', 'reviewed_by' => $admin->id, 'reviewed_at' => $submittedAt->addDay()->setTime(10, 0),
                    ]);
                } elseif ($returned) {
                    $activity->reviews()->create([
                        'submitted_by' => $pm->id, 'submitted_at' => $today->subDays(4)->setTime(15, 0), 'progress_before' => $progress,
                        'decision' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => $today->subDays(3)->setTime(9, 15),
                        'reason' => 'The document is not signed by the user representative, and the scope section still refers to the previous release. Please revise both and resubmit.',
                    ]);
                }
            }
        }
    }

    private function progressFor(CarbonImmutable $start, CarbonImmutable $finish, CarbonImmutable $today, float $pace): int
    {
        if ($start->gt($today)) {
            return 0;
        }
        $planned = $finish->lte($today) ? 100 : (int) round(($start->diffInDays($today) + 1) / ($start->diffInDays($finish) + 1) * 100);
        $actual = (int) (round($planned * $pace / 5) * 5);

        // Slow deliverables still finish the early steps; they fall behind on the long ones.
        if ($pace < 1 && $finish->lt($today->subDays(45))) {
            $actual = 100;
        }

        return max(0, min(100, $actual));
    }

    private function noteFor(int $progress, float $pace): ?string
    {
        if ($progress === 0 || $progress >= 100) {
            return null;
        }

        return $pace < 0.9
            ? 'Waiting for user availability to review. Revised target agreed in the weekly meeting.'
            : 'On track. Draft shared with the TIF PMO for review.';
    }

    private function evidence(Activity $activity, Deliverable $deliverable, User $uploader): void
    {
        $filename = str($activity->name)->before(' (')->slug()->limit(60, '')->value().'.pdf';
        $path = 'evidence/'.$deliverable->project_id.'/'.$deliverable->id.'/'.$activity->id.'/demo-'.$filename;
        $pdf = $this->tinyPdf($activity->name.' - '.$deliverable->name);

        Storage::disk('local')->put($path, $pdf);
        $activity->evidences()->create([
            'uploaded_by' => $uploader->id,
            'original_name' => $filename,
            'path' => $path,
            'storage' => 'local',
            'size' => strlen($pdf),
            'mime' => 'application/pdf',
        ]);
    }

    /** A one-page PDF with a title line, so demo downloads open in a viewer. */
    private function tinyPdf(string $title): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], 'Demo evidence: '.$title);
        $stream = "BT /F1 14 Tf 60 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /** Deletes demo partners (and with them their accounts, projects, deliverables, steps and files). */
    private function removePreviousDemo(): void
    {
        foreach (Partner::where('name', 'like', self::TAG.'%')->get() as $partner) {
            foreach ($partner->projects as $project) {
                Storage::disk('local')->deleteDirectory('evidence/'.$project->id);
                $project->delete();
            }
            $partner->users()->delete();
            $partner->delete();
        }
    }
}
