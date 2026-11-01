<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReportEvidenceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    #[DataProvider('validTypes')]
    public function test_valid_evidence_is_stored_privately_with_server_metadata_and_random_name(string $extension, string $mime): void
    {
        $file = $this->validFile($extension);
        $this->actingAs(User::factory()->create())->post(route('pelapor.reports.store'), [
            ...$this->input(Area::factory()->create()->id), 'evidence' => $file,
            'evidence_path' => 'public/palsu.pdf', 'evidence_size' => 99999, 'evidence_mime' => 'palsu',
        ])->assertRedirect(route('pelapor.dashboard'));
        $report = Report::sole();
        $this->assertSame($mime, $report->evidence_mime);
        $this->assertSame($file->getSize(), $report->evidence_size);
        $this->assertStringStartsWith('report-evidence/', $report->evidence_path);
        $this->assertStringNotContainsString('nama-asli', $report->evidence_path);
        Storage::disk('local')->assertExists($report->evidence_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->get(route('pelapor.reports.show', $report))->assertOk()->assertSee('Unduh bukti')->assertDontSee($report->evidence_path);
    }

    public function test_file_is_optional_and_absence_of_new_upload_preserves_existing_file(): void
    {
        $this->actingAs(User::factory()->create())->post(route('pelapor.reports.store'), $this->input(Area::factory()->create()->id))
            ->assertRedirect(route('pelapor.dashboard'));
        $report = Report::sole();
        $this->assertNull($report->evidence_path);
        $this->assertNull($report->evidence_mime);
        $this->assertNull($report->evidence_size);
        $this->patch(route('pelapor.reports.update', $report), [...$this->input($report->area_id), 'evidence' => $this->validFile('png')])
            ->assertRedirect(route('pelapor.reports.show', $report));
        $path = $report->fresh()->evidence_path;
        $this->patch(route('pelapor.reports.update', $report), $this->input($report->area_id))
            ->assertRedirect(route('pelapor.reports.show', $report));
        $this->assertSame($path, $report->fresh()->evidence_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    #[DataProvider('editableStatuses')]
    public function test_owner_can_replace_one_file_on_allowed_status_and_old_file_is_deleted(ReportStatus $status): void
    {
        $report = $this->reportWithEvidence($status);
        $oldPath = $report->evidence_path;
        $this->actingAs($report->reporter)->patch(route('pelapor.reports.update', $report), [
            ...$this->input($report->area_id), 'evidence' => $this->validFile('png'),
        ])->assertRedirect(route('pelapor.reports.show', $report));
        $fresh = $report->fresh();
        $this->assertNotSame($oldPath, $fresh->evidence_path);
        $this->assertSame($status, $fresh->status);
        $this->assertSame('image/png', $fresh->evidence_mime);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($fresh->evidence_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_evidence_rejects_creation_and_replacement_without_touching_existing_data(string $kind): void
    {
        $report = $this->reportWithEvidence();
        $before = $report->fresh()->toArray();
        $file = match ($kind) {
            'too-large' => $this->upload('bukti.pdf', str_pad("%PDF-1.4\n", 2049 * 1024, ' ')),
            'one-byte-over' => $this->upload('bukti.pdf', str_pad("%PDF-1.4\n", 2048 * 1024 + 1, ' ')),
            'fake-image' => $this->upload('bukti.jpg', '<?php echo "bukan gambar"; ?>'),
            'svg' => $this->upload('bukti.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'bad-extension' => $this->upload('bukti.txt', file_get_contents($this->validFile('png')->getPathname())),
            'array' => [$this->validFile('png'), $this->validFile('pdf')],
            'text' => 'private/path.pdf',
            'failed-upload' => $this->upload('bukti.pdf', '%PDF-1.4', UPLOAD_ERR_INI_SIZE),
        };
        $this->actingAs($report->reporter)->postJson(route('pelapor.reports.store'), [...$this->input($report->area_id), 'evidence' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->patchJson(route('pelapor.reports.update', $report), [...$this->input($report->area_id), 'evidence' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->assertDatabaseCount('reports', 1);
        $this->assertEquals($before, $report->fresh()->toArray());
        Storage::disk('local')->assertExists($report->evidence_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_exactly_two_megabytes_is_accepted(): void
    {
        $file = $this->upload('bukti.pdf', str_pad("%PDF-1.4\n", 2048 * 1024, ' '));
        $this->actingAs(User::factory()->create())->post(route('pelapor.reports.store'), [
            ...$this->input(Area::factory()->create()->id), 'evidence' => $file,
        ])->assertRedirect(route('pelapor.dashboard'));
        $this->assertSame(2048 * 1024, Report::sole()->evidence_size);
    }

    #[DataProvider('downloadRoles')]
    public function test_download_checks_owner_or_explicit_staff_roles(?UserRole $role, bool $owner, bool $active, int $status): void
    {
        $report = $this->reportWithEvidence();
        if ($role !== null) {
            $user = $owner ? $report->reporter : User::factory()->create(['role' => $role]);
            $user->is_active = $active;
            $user->save();
            $this->actingAs($user);
        }
        $response = $this->getJson(route('reports.evidence', ['report' => $report, 'path' => '../rahasia', 'role' => 'koordinator']));
        $response->assertStatus($status);
        if ($status === 200) {
            $response->assertDownload('bukti-laporan-'.$report->id.'.pdf')
                ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame(Storage::disk('local')->get($report->evidence_path), $response->streamedContent());
        } else {
            $response->assertDontSee($report->evidence_path)->assertDontSee('%PDF');
        }
    }

    #[DataProvider('allStatuses')]
    public function test_evidence_remains_readable_at_every_status_but_locked_status_cannot_replace_it(ReportStatus $status): void
    {
        $report = $this->reportWithEvidence($status);
        $this->actingAs($report->reporter)->get(route('reports.evidence', $report))->assertOk();
        if (! in_array($status, [ReportStatus::Baru, ReportStatus::PerluPerbaikan], true)) {
            $before = $report->fresh()->toArray();
            $this->patchJson(route('pelapor.reports.update', $report), [
                ...$this->input($report->area_id), 'evidence' => $this->validFile('png'),
            ])->assertStatus(409);
            $this->assertEquals($before, $report->fresh()->toArray());
            $this->assertCount(1, Storage::disk('local')->allFiles());
        }
    }

    public function test_foreign_report_cannot_be_replaced_and_file_is_not_written(): void
    {
        $report = $this->reportWithEvidence();
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->create())->patchJson(route('pelapor.reports.update', $report), [
            ...$this->input($report->area_id), 'evidence' => $this->validFile('png'), 'reporter_id' => $report->reporter_id,
        ])->assertNotFound();
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    #[DataProvider('unauthorizedUploads')]
    public function test_unauthorized_uploads_never_write_a_file(?UserRole $role, bool $active): void
    {
        $report = $this->reportWithEvidence();
        $before = $report->fresh()->toArray();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $status = $role === null || ! $active ? 401 : 403;
        $input = [...$this->input($report->area_id), 'evidence' => $this->validFile('png')];
        $this->postJson(route('pelapor.reports.store'), $input)->assertStatus($status);
        $this->patchJson(route('pelapor.reports.update', $report), $input)->assertStatus($status);
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertDatabaseCount('reports', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_status_change_after_validation_rejects_replacement_without_writing_file(): void
    {
        $report = $this->reportWithEvidence();
        $this->app->afterResolving(UpdateReportRequest::class, function () use ($report): void {
            $report->status = ReportStatus::Ditinjau;
            $report->save();
        });
        $this->actingAs($report->reporter)->patchJson(route('pelapor.reports.update', $report), [
            ...$this->input($report->area_id), 'evidence' => $this->validFile('png'),
        ])->assertStatus(409);
        $this->assertSame($report->evidence_path, $report->fresh()->evidence_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_invalid_report_fields_prevent_file_storage(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('pelapor.reports.store'), [
            ...$this->input(Area::factory()->create()->id), 'title' => 'X', 'evidence' => $this->validFile('png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->assertDatabaseCount('reports', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_missing_report_missing_metadata_and_missing_file_return_404(): void
    {
        $report = Report::factory()->create();
        $this->actingAs($report->reporter)->get(route('reports.evidence', $report))->assertNotFound();
        $this->get(route('reports.evidence', 999999))->assertNotFound();
        $report->forceFill(['evidence_path' => 'report-evidence/tidak-ada.pdf', 'evidence_mime' => 'application/pdf', 'evidence_size' => 100])->save();
        $this->get(route('reports.evidence', $report))->assertNotFound();
    }

    public function test_private_storage_cannot_be_downloaded_through_public_urls(): void
    {
        $report = $this->reportWithEvidence();
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/storage/'.$report->evidence_path)->assertNotFound();
        $this->get('/storage/private/'.$report->evidence_path)->assertNotFound();
        $this->get(route('reports.evidence', $report))->assertRedirect(route('access'));
    }

    public function test_database_failure_on_create_cleans_up_new_file_and_rolls_back_report(): void
    {
        Event::listen('eloquent.creating: '.Report::class, function (Report $report): void {
            Storage::disk('local')->assertExists($report->evidence_path);
            throw new RuntimeException('Kegagalan database simulasi');
        });
        $this->actingAs(User::factory()->create())->post(route('pelapor.reports.store'), [
            ...$this->input(Area::factory()->create()->id), 'evidence' => $this->validFile('png'),
        ])->assertServerError();
        $this->assertDatabaseCount('reports', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_database_failure_on_replace_preserves_old_file_and_removes_new_file(): void
    {
        $report = $this->reportWithEvidence();
        $before = $report->fresh()->toArray();
        Event::listen('eloquent.updating: '.Report::class, function (Report $saving) use ($report): void {
            Storage::disk('local')->assertExists($report->evidence_path);
            Storage::disk('local')->assertExists($saving->evidence_path);
            throw new RuntimeException('Kegagalan database simulasi');
        });
        $this->actingAs($report->reporter)->patch(route('pelapor.reports.update', $report), [
            ...$this->input($report->area_id), 'evidence' => $this->validFile('png'),
        ])->assertServerError();
        $this->assertEquals($before, $report->fresh()->toArray());
        Storage::disk('local')->assertExists($report->evidence_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_failed_storage_write_does_not_change_report_or_destroy_old_evidence(): void
    {
        $report = $this->reportWithEvidence();
        $before = $report->fresh()->toArray();
        $disk = Storage::disk('local');
        $mock = Mockery::mock($disk);
        $mock->shouldReceive('putFileAs')->andReturn(false);
        Storage::set('local', $mock);
        $this->actingAs($report->reporter)->patchJson(route('pelapor.reports.update', $report), [
            ...$this->input($report->area_id), 'evidence' => $this->validFile('png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->assertEquals($before, $report->fresh()->toArray());
        $disk->assertExists($report->evidence_path);
    }

    public function test_old_file_cleanup_failure_is_logged_after_success_without_losing_new_evidence(): void
    {
        $report = $this->reportWithEvidence();
        $disk = Storage::disk('local');
        $mock = Mockery::mock($disk);
        $mock->shouldReceive('delete')->with($report->evidence_path)->andReturn(false);
        Storage::set('local', $mock);
        Log::spy();
        $this->actingAs($report->reporter)->patch(route('pelapor.reports.update', $report), [
            ...$this->input($report->area_id), 'evidence' => $this->validFile('png'),
        ])->assertRedirect(route('pelapor.reports.show', $report));
        $disk->assertExists($report->fresh()->evidence_path);
        $disk->assertExists($report->evidence_path);
        Log::shouldHaveReceived('warning')->once();
    }

    private function reportWithEvidence(ReportStatus $status = ReportStatus::Baru): Report
    {
        $path = 'report-evidence/bukti-awal.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nData simulasi bukti\n%%EOF");

        return Report::factory()->create(['status' => $status, 'evidence_path' => $path, 'evidence_mime' => 'application/pdf', 'evidence_size' => Storage::disk('local')->size($path)]);
    }

    /** @return array<string, mixed> */
    private function input(int $areaId): array
    {
        return [
            'title' => 'Asap di wilayah simulasi', 'description' => 'Data simulasi: asap tipis terlihat dari lahan di sebelah jalan.',
            'occurred_at' => '2026-10-31T10:00', 'area_id' => $areaId,
            'location_description' => 'Lahan simulasi di sebelah timur jalan utama.', 'latitude' => '-6.9', 'longitude' => '107.6',
        ];
    }

    private function validFile(string $extension): UploadedFile
    {
        $content = "%PDF-1.4\nData simulasi\n%%EOF";
        if ($extension !== 'pdf') {
            $image = UploadedFile::fake()->image('gambar.'.$extension);
            $content = file_get_contents($image->getPathname());
        }

        return $this->upload('nama-asli.'.$extension, $content);
    }

    private function upload(string $name, string $content, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'karhutla-test-');
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, 'application/octet-stream', $error, true);
    }

    public static function validTypes(): array
    {
        return [['jpg', 'image/jpeg'], ['jpeg', 'image/jpeg'], ['png', 'image/png'], ['pdf', 'application/pdf']];
    }

    public static function editableStatuses(): array
    {
        return [[ReportStatus::Baru], [ReportStatus::PerluPerbaikan]];
    }

    public static function invalidFiles(): array
    {
        return array_map(fn (string $kind): array => [$kind], ['too-large', 'one-byte-over', 'fake-image', 'svg', 'bad-extension', 'array', 'text', 'failed-upload']);
    }

    public static function allStatuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }

    public static function downloadRoles(): array
    {
        return [
            [null, false, true, 401], [UserRole::Pelapor, true, true, 200], [UserRole::Pelapor, false, true, 404],
            [UserRole::Relawan, false, true, 200], [UserRole::Koordinator, false, true, 200],
            [UserRole::Pelapor, true, false, 401], [UserRole::Relawan, false, false, 401], [UserRole::Koordinator, false, false, 401],
        ];
    }

    public static function unauthorizedUploads(): array
    {
        return [[null, true], [UserRole::Relawan, true], [UserRole::Koordinator, true], [UserRole::Pelapor, false]];
    }
}
