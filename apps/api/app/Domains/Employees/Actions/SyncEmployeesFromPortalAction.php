<?php

namespace App\Domains\Employees\Actions;

use App\Domains\Employees\Models\Employee;
use App\Domains\Integrations\Models\SyncError;
use App\Domains\Integrations\Models\SyncRun;
use App\Domains\Integrations\Support\PortalMasterDataClient;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

// Setara SyncStudentsFromPortalAction — lihat catatan asumsi payload di sana.
class SyncEmployeesFromPortalAction
{
    public function __construct(
        private readonly PortalMasterDataClient $client,
    ) {}

    public function execute(string $mode, ?User $triggeredBy = null): SyncRun
    {
        $correlationId = (string) Str::uuid();

        $run = SyncRun::query()->create([
            'tenant_id' => 'default',
            'entity_type' => 'employee',
            'mode' => $mode,
            'status' => SyncRun::STATUS_RUNNING,
            'triggered_by' => $triggeredBy?->id,
            'correlation_id' => $correlationId,
            'started_at' => now(),
        ]);

        $processed = 0;
        $upserted = 0;
        $errors = 0;

        try {
            foreach ($this->client->paginate('employees') as $page) {
                foreach ($page as $row) {
                    $processed++;

                    try {
                        $this->upsert($row);
                        $upserted++;
                    } catch (Throwable $e) {
                        $errors++;

                        SyncError::query()->create([
                            'sync_run_id' => $run->id,
                            'source_id' => $row['source_id'] ?? null,
                            'error_code' => 'UPSERT_FAILED',
                            'message' => $e->getMessage(),
                            'rejected_payload' => $row,
                        ]);
                    }
                }
            }

            $run->update([
                'status' => $errors === 0 ? SyncRun::STATUS_SUCCESS : SyncRun::STATUS_PARTIAL,
                'processed_count' => $processed,
                'upserted_count' => $upserted,
                'error_count' => $errors,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Sync pegawai gagal total', ['correlation_id' => $correlationId, 'error' => $e->getMessage()]);

            $run->update([
                'status' => SyncRun::STATUS_FAILED,
                'processed_count' => $processed,
                'upserted_count' => $upserted,
                'error_count' => $errors,
                'finished_at' => now(),
            ]);
        }

        return $run->fresh();
    }

    private function upsert(array $row): void
    {
        if (empty($row['source_id']) || empty($row['employee_number']) || empty($row['name'])) {
            throw new \RuntimeException('Baris tidak lengkap: source_id, employee_number, dan name wajib ada.');
        }

        Employee::query()->updateOrCreate(
            [
                'tenant_id' => 'default',
                'source_system' => 'portal',
                'source_id' => (string) $row['source_id'],
            ],
            [
                'employee_number' => $row['employee_number'],
                'name' => $row['name'],
                'normalized_name' => Str::lower((string) $row['name']),
                'institutional_email' => $row['email'] ?? null,
                'employee_type' => $row['employee_type'] ?? null,
                'work_unit_name' => $row['work_unit_name'] ?? null,
                'position_name' => $row['position_name'] ?? null,
                'status' => $row['status'] ?? 'active',
                'source_updated_at' => $row['updated_at'] ?? null,
                'last_synced_at' => now(),
            ],
        );
    }
}
