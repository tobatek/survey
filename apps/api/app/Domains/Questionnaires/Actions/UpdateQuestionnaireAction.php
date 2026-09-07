<?php

namespace App\Domains\Questionnaires\Actions;

use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Support\QuestionnaireLockGuard;
use Illuminate\Support\Facades\DB;

class UpdateQuestionnaireAction
{
    public function __construct(private readonly QuestionnaireLockGuard $lockGuard) {}

    /**
     * @param  array{title?: string, description?: string|null, category?: string|null, audience_type?: string, identity_mode?: string, allow_draft_save?: bool, privacy_notice?: string|null, confirmation_text?: string|null}  $data
     */
    public function execute(Questionnaire $questionnaire, array $data, ?int $ifMatch): Questionnaire
    {
        return DB::transaction(function () use ($questionnaire, $data, $ifMatch) {
            $this->lockGuard->assertMatches($questionnaire, $ifMatch);

            $questionnaire->fill($data);
            $questionnaire->save();
            $this->lockGuard->bump($questionnaire);

            return $questionnaire->fresh();
        });
    }
}
