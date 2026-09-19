<?php

namespace App\Domains\Campaigns\Actions;

use App\Domains\Audit\Actions\RecordAuditEventAction;
use App\Domains\Campaigns\DTO\AudienceFilterDTO;
use App\Domains\Campaigns\Events\CampaignActivated;
use App\Domains\Campaigns\Exceptions\CampaignActivationInProgressException;
use App\Domains\Campaigns\Exceptions\CampaignNotActivatableException;
use App\Domains\Campaigns\Models\AudienceMember;
use App\Domains\Campaigns\Models\Campaign;
use App\Domains\Campaigns\Models\Eligibility;
use App\Domains\Employees\Models\Employee;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// FSD §10.2 — audience snapshot & aktivasi. Idempotent terhadap retry lewat
// dua lapis: (1) seluruh materialize+set-active ada dalam satu transaction,
// jadi kegagalan di tengah jalan rollback bersih; (2) kalau campaign SUDAH
// active saat execute() dipanggil lagi (retry setelah response pertama
// hilang), langsung dikembalikan apa adanya tanpa mengulang materialize —
// realisasi literal "aktivasi retry mengembalikan hasil yang sama".
class ActivateCampaignAction
{
    private const CHUNK_SIZE = 500;

    /**
     * @var array<string, list<string>>
     */
    private const SNAPSHOT_ATTRIBUTES = [
        AudienceMember::SUBJECT_STUDENT => ['faculty_name', 'program_study_code', 'program_study_name', 'degree_level', 'cohort_year', 'status'],
        AudienceMember::SUBJECT_EMPLOYEE => ['employee_type', 'work_unit_name', 'status'],
    ];

    public function __construct(
        private readonly BuildAudienceQueryAction $buildQuery,
        private readonly PreviewAudienceAction $previewAudience,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(Campaign $campaign, ?User $actor = null): Campaign
    {
        $lock = Cache::lock("campaign-activate:{$campaign->id}", 30);

        if (! $lock->block(5)) {
            throw new CampaignActivationInProgressException;
        }

        try {
            [$campaign, $wasAlreadyActive] = DB::transaction(fn () => $this->activateWithinTransaction($campaign, $actor));
        } finally {
            $lock->release();
        }

        if (! $wasAlreadyActive) {
            event(new CampaignActivated($campaign));
        }

        return $campaign;
    }

    /**
     * @return array{0: Campaign, 1: bool}
     */
    private function activateWithinTransaction(Campaign $campaign, ?User $actor): array
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

        if ($campaign->status === Campaign::STATUS_ACTIVE) {
            return [$campaign, true];
        }

        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            throw new CampaignNotActivatableException(
                'invalid_status',
                "Campaign berstatus {$campaign->status} tidak bisa diaktifkan.",
            );
        }

        if (empty($campaign->target_rule_json)) {
            throw new CampaignNotActivatableException(
                'target_not_set',
                'Target campaign belum ditentukan, jalankan preview terlebih dahulu.',
            );
        }

        $filter = AudienceFilterDTO::fromArray($campaign->target_rule_json);
        $preview = $this->previewAudience->execute($campaign, $filter);

        if ($preview['total'] === 0) {
            throw new CampaignNotActivatableException('empty_target', 'Target campaign kosong, aktivasi ditolak.');
        }

        $this->materializeSubjects(
            $campaign,
            AudienceMember::SUBJECT_STUDENT,
            $this->buildQuery->studentQuery($filter, $campaign->tenant_id),
        );
        $this->materializeSubjects(
            $campaign,
            AudienceMember::SUBJECT_EMPLOYEE,
            $this->buildQuery->employeeQuery($filter, $campaign->tenant_id),
        );

        $denominatorCount = AudienceMember::query()->where('campaign_id', $campaign->id)->count();

        $campaign->status = Campaign::STATUS_ACTIVE;
        $campaign->denominator_count = $denominatorCount;
        $campaign->activated_by = $actor?->id;
        $campaign->activated_at = now();
        $campaign->save();

        $this->recordAuditEvent->execute(
            action: 'campaign.activate',
            result: 'success',
            actor: $actor,
            resourceType: 'campaign',
            resourceId: $campaign->id,
            context: ['denominator_count' => $denominatorCount],
        );

        return [$campaign->fresh(), false];
    }

    private function materializeSubjects(Campaign $campaign, string $subjectType, Builder $query): void
    {
        $query->chunkById(self::CHUNK_SIZE, function ($subjects) use ($campaign, $subjectType) {
            $now = now();

            $memberRows = $subjects->map(function (Student|Employee $subject) use ($campaign, $subjectType, $now) {
                $attributesJson = json_encode($this->snapshotAttributes($subject, $subjectType), JSON_THROW_ON_ERROR);

                return [
                    'id' => (string) Str::uuid(),
                    'campaign_id' => $campaign->id,
                    'subject_type' => $subjectType,
                    'subject_id' => $subject->id,
                    'attributes_snapshot_json' => $attributesJson,
                    'snapshot_hash' => hash('sha256', $attributesJson),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            AudienceMember::query()->upsert(
                $memberRows,
                ['campaign_id', 'subject_type', 'subject_id'],
                ['attributes_snapshot_json', 'snapshot_hash', 'updated_at'],
            );

            $memberIds = AudienceMember::query()
                ->where('campaign_id', $campaign->id)
                ->where('subject_type', $subjectType)
                ->whereIn('subject_id', $subjects->pluck('id'))
                ->pluck('id');

            $eligibilityRows = $memberIds->map(fn (string $memberId) => [
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'audience_member_id' => $memberId,
                'status' => Eligibility::STATUS_ELIGIBLE,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            Eligibility::query()->upsert($eligibilityRows, ['campaign_id', 'audience_member_id'], []);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotAttributes(Student|Employee $subject, string $subjectType): array
    {
        $attributes = [];

        foreach (self::SNAPSHOT_ATTRIBUTES[$subjectType] as $field) {
            $attributes[$field] = $subject->{$field};
        }

        return $attributes;
    }
}
