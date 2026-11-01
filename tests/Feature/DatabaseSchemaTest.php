<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new RuntimeException('Pengujian database harus memakai database terpisah dengan akhiran _testing.');
        }
    }

    public function test_database_defaults_new_users_to_active_reporters(): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Pelapor Uji',
            'email' => 'pelapor@example.test',
            'password' => 'hash-for-schema-test',
        ]);

        $user = User::findOrFail($id);

        $this->assertSame(UserRole::Pelapor, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_user_factory_supports_volunteer_coordinator_and_inactive_accounts(): void
    {
        $volunteer = User::factory()->relawan()->create();
        $coordinator = User::factory()->koordinator()->inactive()->create();

        $this->assertSame(UserRole::Relawan, $volunteer->fresh()->role);
        $this->assertSame(UserRole::Koordinator, $coordinator->fresh()->role);
        $this->assertFalse($coordinator->fresh()->is_active);
    }

    public function test_public_user_fields_cannot_mass_assign_role_or_active_status(): void
    {
        $user = User::factory()->make();
        $user->fill(['role' => UserRole::Koordinator, 'is_active' => false]);

        $this->assertSame(UserRole::Pelapor, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_report_database_defaults_and_optional_fields_match_the_design(): void
    {
        $reporter = User::factory()->create();
        $area = Area::factory()->create();
        $id = DB::table('reports')->insertGetId([
            'reporter_id' => $reporter->id,
            'area_id' => $area->id,
            'title' => 'Pengamatan pada wilayah demo',
            'description' => 'Asap terlihat di lahan demo untuk pengujian database.',
            'occurred_at' => now()->subHour(),
            'location_description' => 'Lahan demo di sebelah timur jalan.',
            'latitude' => '-6.1234567',
            'longitude' => '107.7654321',
        ]);

        $report = Report::findOrFail($id);

        $this->assertSame(ReportStatus::Baru, $report->status);
        $this->assertSame('-6.1234567', $report->latitude);
        $this->assertSame('107.7654321', $report->longitude);
        $this->assertNull($report->evidence_path);
        $this->assertNull($report->evidence_mime);
        $this->assertNull($report->evidence_size);
        $this->assertNull($report->closure_note);
        $this->assertNull($report->closed_at);
        $this->assertNotNull($report->occurred_at);
    }

    public function test_reports_resolve_their_reporter_area_and_inverse_relationships(): void
    {
        $reporter = User::factory()->create();
        $area = Area::factory()->create();
        $reports = Report::factory()->count(2)->for($reporter, 'reporter')->for($area)->create();
        Report::factory()->create();

        $this->assertTrue($reports->first()->reporter->is($reporter));
        $this->assertTrue($reports->first()->area->is($area));
        $this->assertCount(2, $reporter->reports);
        $this->assertCount(2, $area->reports);
    }

    public function test_coordinate_boundary_values_retain_seven_decimal_places(): void
    {
        $northEast = Report::factory()->create(['latitude' => '90.0000000', 'longitude' => '180.0000000']);
        $southWest = Report::factory()->create(['latitude' => '-90.0000000', 'longitude' => '-180.0000000']);

        $this->assertSame('90.0000000', $northEast->fresh()->latitude);
        $this->assertSame('180.0000000', $northEast->fresh()->longitude);
        $this->assertSame('-90.0000000', $southWest->fresh()->latitude);
        $this->assertSame('-180.0000000', $southWest->fresh()->longitude);
    }

    public function test_public_report_fields_cannot_mass_assign_process_or_evidence_metadata(): void
    {
        $report = Report::factory()->create();
        $reporterId = $report->reporter_id;
        $report->fill([
            'reporter_id' => 999999,
            'status' => ReportStatus::Selesai,
            'evidence_path' => 'arbitrary-file.pdf',
            'evidence_mime' => 'application/pdf',
            'evidence_size' => 10,
            'closure_note' => 'Catatan penutupan tanpa proses yang sah.',
            'closed_at' => now(),
        ]);

        $this->assertSame($reporterId, $report->reporter_id);
        $this->assertSame(ReportStatus::Baru, $report->status);
        $this->assertNull($report->evidence_path);
        $this->assertNull($report->evidence_mime);
        $this->assertNull($report->evidence_size);
        $this->assertNull($report->closure_note);
        $this->assertNull($report->closed_at);
    }

    public function test_unknown_reporter_is_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        Report::factory()->create(['reporter_id' => 999999]);
    }

    public function test_referenced_area_cannot_be_deleted(): void
    {
        $report = Report::factory()->create();

        $this->expectException(QueryException::class);

        $report->area->delete();
    }

    public function test_referenced_reporter_cannot_be_deleted(): void
    {
        $report = Report::factory()->create();

        $this->expectException(QueryException::class);

        $report->reporter->delete();
    }

    public function test_area_codes_are_normalized_and_unique(): void
    {
        $area = Area::factory()->inactive()->create(['code' => ' wil-a ']);

        $this->assertSame('WIL-A', $area->fresh()->code);
        $this->assertFalse($area->fresh()->is_active);
        $this->expectException(QueryException::class);

        Area::factory()->create(['code' => 'WIL-A']);
    }

    public function test_report_indexes_support_the_planned_lists_and_summary(): void
    {
        $columns = array_column(Schema::getIndexes('reports'), 'columns');

        $this->assertContains(['reporter_id', 'created_at'], $columns);
        $this->assertContains(['area_id', 'status'], $columns);
        $this->assertContains(['status', 'created_at'], $columns);
    }
}
