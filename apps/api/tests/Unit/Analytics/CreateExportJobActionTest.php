<?php

namespace Tests\Unit\Analytics;

use App\Domains\Analytics\Actions\CreateExportJobAction;
use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Models\ExportJob;
use App\Domains\Campaigns\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §14.2 langkah 1-3 (Fase 5 slice 5).
class CreateExportJobActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function filter_hash_is_identical_regardless_of_array_value_order(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);
        $user = User::factory()->create(['tenant_id' => 'default']);
        $action = new CreateExportJobAction;

        $filterA = AnalyticsFilterDTO::fromArray(['faculty_names' => ['Teknik', 'Ekonomi']]);
        $filterB = AnalyticsFilterDTO::fromArray(['faculty_names' => ['Ekonomi', 'Teknik']]);

        $jobA = $action->execute($campaign, $user, $filterA, ExportJob::FORMAT_XLSX);
        $jobA->update(['status' => ExportJob::STATUS_SUCCEEDED]); // supaya request kedua tidak dianggap dedup queued/processing

        $jobB = $action->execute($campaign, $user, $filterB, ExportJob::FORMAT_XLSX);

        $this->assertSame($jobA->filter_hash, $jobB->filter_hash);
        $this->assertNotSame($jobA->id, $jobB->id); // beda job karena jobA sudah bukan queued/processing lagi
    }

    #[Test]
    public function reuses_existing_queued_job_instead_of_creating_a_duplicate(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->create(['tenant_id' => 'default']);
        $user = User::factory()->create(['tenant_id' => 'default']);
        $action = new CreateExportJobAction;
        $filter = AnalyticsFilterDTO::fromArray([]);

        $first = $action->execute($campaign, $user, $filter, ExportJob::FORMAT_XLSX);
        $second = $action->execute($campaign, $user, $filter, ExportJob::FORMAT_XLSX);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ExportJob::query()->count());
    }
}
