<?php

namespace Tests\Unit\Analytics;

use App\Domains\Analytics\DTO\AnalyticsFilterDTO;
use App\Domains\Analytics\Support\FunnelBarChartRenderer;
use App\Domains\Campaigns\Models\Campaign;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §14.1 (Fase 5 slice 6 bagian 3-4).
class FunnelBarChartRendererTest extends TestCase
{
    private function campaign(): Campaign
    {
        return Campaign::factory()->make([
            'id' => 'campaign-1',
            'questionnaire_id' => 'questionnaire-1',
            'minimum_segment_size' => 5,
        ]);
    }

    #[Test]
    public function renders_a_valid_png_with_expected_canvas_size(): void
    {
        $renderer = new FunnelBarChartRenderer;

        $png = $renderer->render(
            $this->campaign(),
            'Survei Kepuasan',
            AnalyticsFilterDTO::fromArray([]),
            [
                'funnel' => ['eligible' => 10, 'invited' => 8, 'opened' => 6, 'started' => 5, 'completed' => 4, 'expired' => 1, 'excluded' => 0],
                'response_rate' => ['numerator' => 4, 'denominator' => 10, 'value' => 40.0],
                'completion_rate' => ['numerator' => 4, 'denominator' => 5, 'value' => 80.0],
                'total_responses' => 4,
                'last_response_at' => null,
            ],
        );

        $info = getimagesizefromstring($png);
        $this->assertNotFalse($info, 'Hasil render() harus berupa PNG valid.');
        $this->assertSame(900, $info[0]);
        $this->assertSame(500, $info[1]);
        $this->assertSame('image/png', $info['mime']);
    }

    #[Test]
    public function renders_a_valid_png_when_summary_is_suppressed(): void
    {
        $renderer = new FunnelBarChartRenderer;

        $png = $renderer->render(
            $this->campaign(),
            'Survei Kepuasan',
            AnalyticsFilterDTO::fromArray([]),
            ['suppressed' => true, 'minimum_segment_size' => 5],
        );

        $info = getimagesizefromstring($png);
        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);
    }
}
