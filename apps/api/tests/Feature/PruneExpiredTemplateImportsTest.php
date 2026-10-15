<?php

namespace Tests\Feature;

use App\Domains\Questionnaires\Models\TemplateImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// FSD §15.4 — scheduler command `template-imports:prune-expired`
// (routes/console.php, hourly).
class PruneExpiredTemplateImportsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_only_expired_and_uncommitted_staging_imports(): void
    {
        $expiredUncommitted = TemplateImport::factory()->create([
            'expires_at' => now()->subHour(),
            'committed_at' => null,
        ]);

        $expiredButCommitted = TemplateImport::factory()->create([
            'expires_at' => now()->subHour(),
            'committed_at' => now(),
        ]);

        $notYetExpired = TemplateImport::factory()->create([
            'expires_at' => now()->addHour(),
            'committed_at' => null,
        ]);

        $this->artisan('template-imports:prune-expired')->assertExitCode(0);

        $this->assertDatabaseMissing('template_imports', ['id' => $expiredUncommitted->id]);
        $this->assertDatabaseHas('template_imports', ['id' => $expiredButCommitted->id]);
        $this->assertDatabaseHas('template_imports', ['id' => $notYetExpired->id]);
    }
}
