<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\ReportForwarding;
use App\Models\ReportReview;
use App\Models\ReportStatusLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_review_relationships_allow_multiple_reviews_for_one_report(): void
    {
        $review = ReportReview::factory()->create();
        ReportReview::factory()->for($review->report)->for($review->reviewer, 'reviewer')->create();

        $this->assertSame(ReviewDecision::PerluPerbaikan, $review->fresh()->decision);
        $this->assertSame(UserRole::Relawan, $review->reviewer->role);
        $this->assertCount(2, $review->report->reviews);
        $this->assertCount(2, $review->reviewer->reviews);
        $this->assertNotNull($review->created_at);
    }

    public function test_forwarding_relationships_link_the_report_and_coordinator(): void
    {
        $forwarding = ReportForwarding::factory()->create();

        $this->assertTrue($forwarding->report->forwarding->is($forwarding));
        $this->assertTrue($forwarding->coordinator->forwardings->first()->is($forwarding));
        $this->assertSame(UserRole::Koordinator, $forwarding->coordinator->role);
        $this->assertNotNull($forwarding->forwarded_at);
        $this->assertNotNull($forwarding->created_at);
    }

    public function test_database_rejects_a_second_forwarding_for_the_same_report(): void
    {
        $forwarding = ReportForwarding::factory()->create();

        $this->expectException(QueryException::class);

        ReportForwarding::factory()->for($forwarding->report)->create();
    }

    public function test_status_log_records_enum_values_and_actor_relationships(): void
    {
        $log = ReportStatusLog::factory()->create();

        $this->assertSame(ReportStatus::Baru, $log->fresh()->from_status);
        $this->assertSame(ReportStatus::Ditinjau, $log->fresh()->to_status);
        $this->assertTrue($log->report->statusLogs->first()->is($log));
        $this->assertTrue($log->changedBy->statusChanges->first()->is($log));
        $this->assertNotNull($log->created_at);
    }

    public function test_system_status_log_accepts_a_null_actor(): void
    {
        $log = ReportStatusLog::factory()->system()->create();

        $this->assertNull($log->fresh()->changed_by);
        $this->assertNull($log->fresh()->changedBy);
        $this->assertTrue($log->report->statusLogs->first()->is($log));
    }

    public function test_process_records_have_only_creation_timestamps_and_planned_indexes(): void
    {
        foreach (['report_reviews', 'report_forwardings', 'report_status_logs'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'created_at'));
            $this->assertFalse(Schema::hasColumn($table, 'updated_at'));
        }

        foreach (['report_reviews', 'report_status_logs'] as $table) {
            $this->assertContains(['report_id', 'created_at'], array_column(Schema::getIndexes($table), 'columns'));
        }
    }

    #[DataProvider('invalidForeignKeys')]
    public function test_unknown_related_records_are_rejected(string $model, string $foreignKey): void
    {
        $factory = match ($model) {
            'report' => Report::factory(),
            'review' => ReportReview::factory(),
            'forwarding' => ReportForwarding::factory(),
            'log' => ReportStatusLog::factory(),
        };

        $this->expectException(QueryException::class);

        $factory->create([$foreignKey => 999999]);
    }

    public static function invalidForeignKeys(): array
    {
        return [
            'report area' => ['report', 'area_id'],
            'review report' => ['review', 'report_id'],
            'review actor' => ['review', 'reviewer_id'],
            'forwarding report' => ['forwarding', 'report_id'],
            'forwarding actor' => ['forwarding', 'coordinator_id'],
            'log report' => ['log', 'report_id'],
            'log actor' => ['log', 'changed_by'],
        ];
    }

    #[DataProvider('referencedRecords')]
    public function test_process_records_prevent_deleting_referenced_reports_and_users(string $model, string $relationship): void
    {
        $record = match ($model) {
            'review' => ReportReview::factory()->create(),
            'forwarding' => ReportForwarding::factory()->create(),
            'log' => ReportStatusLog::factory()->create(),
        };

        $this->expectException(QueryException::class);

        $record->{$relationship}->delete();
    }

    public static function referencedRecords(): array
    {
        return [
            'review keeps report' => ['review', 'report'],
            'review keeps reviewer' => ['review', 'reviewer'],
            'forwarding keeps report' => ['forwarding', 'report'],
            'forwarding keeps coordinator' => ['forwarding', 'coordinator'],
            'log keeps report' => ['log', 'report'],
            'log keeps actor' => ['log', 'changedBy'],
        ];
    }

    public function test_seeders_can_be_repeated_without_duplicates_or_overwriting_existing_areas(): void
    {
        $this->seed(DatabaseSeeder::class);
        $area = Area::where('code', 'DEMO-A')->firstOrFail();
        $area->update(['name' => 'Nama wilayah yang diperbarui', 'is_active' => false]);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('areas', 3);
        $this->assertDatabaseCount('users', 3);
        $this->assertSame('Nama wilayah yang diperbarui', $area->fresh()->name);
        $this->assertFalse($area->fresh()->is_active);
        $this->assertDatabaseHas('areas', ['code' => 'DEMO-B', 'name' => 'Wilayah Demo B']);
        $this->assertDatabaseHas('areas', ['code' => 'DEMO-C', 'name' => 'Wilayah Demo C']);
    }
}
