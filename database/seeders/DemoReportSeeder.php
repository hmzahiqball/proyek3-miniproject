<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DemoReportSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $newPaths = [];
        try {
            DB::transaction(function () use (&$newPaths): void {
                $coordinator = User::query()->where('email', 'koordinator@example.test')->lockForUpdate()->firstOrFail();
                if (Report::query()->where('title', 'like', '[DEMO17-%')->exists()) {
                    return;
                }

                $volunteer = User::query()->where('email', 'relawan@example.test')->firstOrFail();
                $first = User::query()->where('email', 'pelapor@example.test')->firstOrFail();
                $second = User::query()->where('email', 'pelapor2@example.test')->firstOrFail();
                if ($coordinator->role !== UserRole::Koordinator || $volunteer->role !== UserRole::Relawan || $first->role !== UserRole::Pelapor || $second->role !== UserRole::Pelapor) {
                    throw new RuntimeException('Role akun demo telah berubah. Data laporan demo tidak ditambahkan.');
                }

                $areas = Area::query()->whereIn('code', ['DEMO-A', 'DEMO-B', 'DEMO-C'])->get()->keyBy('code');
                if ($areas->count() !== 3) {
                    throw new RuntimeException('Jalankan AreaSeeder sebelum menambahkan laporan demo.');
                }
                $historical = Area::firstOrCreate(['code' => 'DEMO-LAMA'], ['name' => 'Wilayah Demo Historis', 'is_active' => false]);

                for ($index = 1; $index <= 26; $index++) {
                    $owner = $index <= 11 || $index % 2 === 1 ? $first : $second;
                    $area = match (true) {
                        $index <= 12 || $index === 21 => $areas['DEMO-A'],
                        $index >= 25 => $historical,
                        in_array($index, [13, 14, 15, 19, 22], true) => $areas['DEMO-B'],
                        default => $areas['DEMO-C'],
                    };
                    $states = $this->states($index);
                    $this->createReport($index, $owner, $area, $volunteer, $coordinator, $states, $newPaths);
                }
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $path) {
                try {
                    if (! Storage::disk('local')->delete($path)) {
                        Log::warning('Bukti demo gagal dibersihkan.', ['path' => $path]);
                    }
                } catch (Throwable $cleanupException) {
                    Log::warning('Bukti demo gagal dibersihkan.', ['path' => $path, 'exception' => $cleanupException]);
                }
            }
            throw $exception;
        }
    }

    /** @return array<int, ReportStatus> */
    private function states(int $index): array
    {
        return match (true) {
            $index <= 12 || $index === 25 => [],
            $index === 13 => [ReportStatus::Ditinjau, ReportStatus::PerluPerbaikan, ReportStatus::Ditinjau],
            in_array($index, [14, 26], true) => [ReportStatus::Ditinjau],
            in_array($index, [15, 16], true) => [ReportStatus::Ditinjau, ReportStatus::PerluPerbaikan],
            in_array($index, [17, 18], true) => [ReportStatus::Ditinjau, ReportStatus::PerluDiteruskan],
            in_array($index, [19, 20], true) => [ReportStatus::Ditinjau, ReportStatus::PerluDiteruskan, ReportStatus::Diteruskan],
            in_array($index, [21, 22], true) => [ReportStatus::Ditinjau, ReportStatus::Selesai],
            default => [ReportStatus::Ditinjau, ReportStatus::PerluDiteruskan, ReportStatus::Diteruskan, ReportStatus::Selesai],
        };
    }

    /**
     * @param  array<int, ReportStatus>  $states
     * @param  array<int, string>  $newPaths
     */
    private function createReport(int $index, User $owner, Area $area, User $volunteer, User $coordinator, array $states, array &$newPaths): void
    {
        $createdAt = now()->subDays(3)->startOfSecond()->addMinutes($index);
        $report = new Report([
            'area_id' => $area->id,
            'title' => sprintf('[DEMO17-%02d] SIMULASI asap di wilayah demo', $index),
            'description' => 'Data SIMULASI untuk peer testing aplikasi Karhutla. Asap dicontohkan dari lahan demo; bukan laporan kejadian nyata.',
            'occurred_at' => $createdAt->copy()->subMinutes(30),
            'location_description' => 'Lokasi SIMULASI di sisi utara pos warga pada wilayah demo. Koordinat hanya contoh pengujian.',
            'latitude' => '-6.9000000',
            'longitude' => '107.6000000',
        ]);
        $report->reporter()->associate($owner);
        $report->forceFill(['status' => ReportStatus::Baru, 'created_at' => $createdAt, 'updated_at' => $createdAt]);
        if ($index % 4 !== 0) {
            $this->attachEvidence($report, $index, $newPaths);
        }
        $report->save();

        foreach ($states as $step => $target) {
            $time = $createdAt->copy()->addMinutes(($step + 1) * 5);
            $from = $report->status;
            $actor = $from === ReportStatus::PerluPerbaikan ? $owner : ($target === ReportStatus::Diteruskan || $from === ReportStatus::Diteruskan ? $coordinator : $volunteer);
            $note = 'SIMULASI: '.$target->label().' untuk pengujian alur aplikasi, bukan pernyataan kondisi kebakaran nyata.';

            if (in_array($target, [ReportStatus::PerluPerbaikan, ReportStatus::PerluDiteruskan], true) || ($target === ReportStatus::Selesai && $from === ReportStatus::Ditinjau)) {
                $review = $report->reviews()->make(['decision' => ReviewDecision::from($target->value), 'note' => $note]);
                $review->reviewer()->associate($volunteer);
                $review->created_at = $time;
                $review->save();
            }
            if ($target === ReportStatus::Diteruskan) {
                $forwarding = $report->forwarding()->make(['recipient' => 'Posko Wilayah Demo (SIMULASI)', 'forwarded_at' => $time, 'note' => $note]);
                $forwarding->coordinator()->associate($coordinator);
                $forwarding->created_at = $time;
                $forwarding->save();
            }
            if ($target === ReportStatus::Selesai) {
                $report->closure_note = $note;
                $report->closed_at = $time;
            }
            $log = $report->statusLogs()->make(['from_status' => $from, 'to_status' => $target]);
            $log->changedBy()->associate($actor);
            $log->created_at = $time;
            $log->save();
            $report->forceFill(['status' => $target, 'updated_at' => $time])->save();
        }
    }

    /** @param array<int, string> $newPaths */
    private function attachEvidence(Report $report, int $index, array &$newPaths): void
    {
        $extension = ['jpg', 'png', 'pdf'][$index % 3];
        $source = database_path('seeders/fixtures/bukti-simulasi.'.$extension);
        $content = file_get_contents($source);
        if ($content === false) {
            throw new RuntimeException('File sumber bukti demo tidak dapat dibaca.');
        }
        $path = 'report-evidence/'.Str::uuid().'.'.$extension;
        $newPaths[] = $path;
        if (! Storage::disk('local')->put($path, $content)) {
            throw new RuntimeException('Bukti demo gagal disimpan.');
        }
        $report->forceFill(['evidence_path' => $path, 'evidence_mime' => mime_content_type($source), 'evidence_size' => strlen($content)]);
    }
}
