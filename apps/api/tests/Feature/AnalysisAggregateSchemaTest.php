<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalysisAggregate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §16.6 -- murni skema (belum ada action yang menulis ke tabel ini di
// Fase 5 slice 1, dipakai mulai slice 4). Pola sama RespondentSchemaTest
// (Fase 4 slice 1).
class AnalysisAggregateSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_be_created_via_factory_and_resolves_its_campaign(): void
    {
        $aggregate = AnalysisAggregate::factory()->create();

        $this->assertNotNull($aggregate->campaign);
        $this->assertNotNull($aggregate->refreshed_at);
    }

    #[Test]
    public function it_is_unique_per_campaign_snapshot_filter_question_option_and_row(): void
    {
        // Semua kolom kunci komposit diisi non-null -- NULL tidak pernah
        // dianggap sama dengan NULL lain di constraint unique SQL manapun,
        // jadi harus non-null semua supaya benar-benar menguji constraint-nya.
        $aggregate = AnalysisAggregate::factory()->create([
            'question_code' => 'q1',
            'option_code' => 'opt-a',
            'matrix_row_code' => 'row-1',
        ]);

        $this->expectException(QueryException::class);

        AnalysisAggregate::factory()->create([
            'campaign_id' => $aggregate->campaign_id,
            'snapshot_checksum' => $aggregate->snapshot_checksum,
            'normalized_filter_hash' => $aggregate->normalized_filter_hash,
            'question_code' => 'q1',
            'option_code' => 'opt-a',
            'matrix_row_code' => 'row-1',
        ]);
    }
}
