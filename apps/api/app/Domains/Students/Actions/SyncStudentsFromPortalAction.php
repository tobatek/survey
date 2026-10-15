<?php

namespace App\Domains\Students\Actions;

use App\Domains\Integrations\Models\SyncError;
use App\Domains\Integrations\Models\SyncRun;
use App\Domains\Integrations\Support\PortalMasterDataClient;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

// FSD §8.3 alur sync + RULE-D02 (gagal sebagian tidak menghapus data lama).
// Kunci upsert (tenant_id, source_system, source_id) — BUKAN nim, karena
// NIM historisnya bisa berubah/direvisi sementara source_id (identifier
// Portal) stabil (FSD §8.1 catatan, RULE-D01).
//
// Field Portal (id, full_name, study_program_name, academic_status, ...)
// dipetakan ke kolom lokal di normalize(). Nama kolom lokal juga tetap
// diterima apa adanya. Alias selain id/full_name/study_program_name/
// academic_status masih tebakan sampai skema respons Portal dikonfirmasi —
// baris yang tidak terpetakan masuk SyncError (dengan payload mentah).
class SyncStudentsFromPortalAction
{
    public function __construct(
        private readonly PortalMasterDataClient $client,
    ) {}

    public function execute(string $mode, ?User $triggeredBy = null): SyncRun
    {
        $correlationId = (string) Str::uuid();

        $run = SyncRun::query()->create([
            'tenant_id' => 'default',
            'entity_type' => 'student',
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
            foreach ($this->client->paginate('students') as $page) {
                foreach ($page as $row) {
                    $processed++;
                    $rawRow = $row;
                    $row = $this->normalize($row);

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
                            'rejected_payload' => $rawRow,
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
            Log::error('Sync mahasiswa gagal total', ['correlation_id' => $correlationId, 'error' => $e->getMessage()]);

            $run->update([
                'status' => SyncRun::STATUS_FAILED,
                'processed_count' => $processed,
                'upserted_count' => $upserted,
                'error_count' => $errors,
                'finished_at' => now(),
            ]);
        }

        // Data lama TIDAK dihapus di sini meski run gagal/partial (RULE-D02).
        // Deaktivasi mahasiswa yang tak lagi muncul di Portal memerlukan
        // langkah reconciliation terpisah dengan kebijakan eksplisit —
        // sengaja belum diimplementasikan di Fase 1.
        return $run->fresh();
    }

    /**
     * Petakan field Portal ke nama kolom lokal; nama lokal yang sudah ada menang.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $aliases = [
            'source_id' => 'id',
            'name' => 'full_name',
            'email' => 'institutional_email',
            'program_study_code' => 'study_program_code',
            'program_study_name' => 'study_program_name',
            'status' => 'academic_status',
            'cohort_year' => 'entry_year',
        ];

        foreach ($aliases as $local => $portal) {
            if (! isset($row[$local]) && isset($row[$portal])) {
                $row[$local] = $row[$portal];
            }
        }

        return $row;
    }

    private function upsert(array $row): void
    {
        if (empty($row['source_id']) || empty($row['nim']) || empty($row['name'])) {
            throw new \RuntimeException('Baris tidak lengkap: source_id, nim, dan name wajib ada.');
        }

        Student::query()->updateOrCreate(
            [
                'tenant_id' => 'default',
                'source_system' => 'portal',
                'source_id' => (string) $row['source_id'],
            ],
            [
                'nim' => $row['nim'],
                'normalized_nim' => Str::lower((string) $row['nim']),
                'name' => $row['name'],
                'normalized_name' => Str::lower((string) $row['name']),
                'institutional_email' => $row['email'] ?? null,
                'faculty_name' => $row['faculty_name'] ?? null,
                'program_study_code' => $row['program_study_code'] ?? null,
                'program_study_name' => $row['program_study_name'] ?? null,
                'degree_level' => $row['degree_level'] ?? null,
                'cohort_year' => $row['cohort_year'] ?? null,
                'status' => $row['status'] ?? 'active',
                'source_updated_at' => $row['updated_at'] ?? null,
                'last_synced_at' => now(),
            ],
        );
    }
}
