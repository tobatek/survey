<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\Question;
use App\Domains\Questionnaires\Models\Questionnaire;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use App\Domains\Questionnaires\Models\QuestionOption;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionnaireBranchingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());
    }

    private function makeQuestionWithOptionTargeting(QuestionnaireSection $section, ?QuestionnaireSection $target): Question
    {
        $question = Question::factory()->create([
            'questionnaire_id' => $section->questionnaire_id,
            'section_id' => $section->id,
            'type' => 'single_choice',
        ]);

        QuestionOption::factory()->create([
            'question_id' => $question->id,
            'target_section_id' => $target?->id,
        ]);

        return $question;
    }

    #[Test]
    public function a_valid_branch_to_a_later_section_passes_validation(): void
    {
        $questionnaire = Questionnaire::factory()->create();
        $s1 = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id, 'sort_order' => 1]);
        $s2 = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id, 'sort_order' => 2]);

        $this->makeQuestionWithOptionTargeting($s1, $s2);

        $response = $this->actingAs($this->admin)->getJson("/api/questionnaires/{$questionnaire->id}/validate");

        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertNotContains('branch_target_not_in_questionnaire', $codes);
        $this->assertNotContains('branch_cycle_detected', $codes);
        $this->assertNotContains('section_unreachable', $codes);
    }

    #[Test]
    public function a_branch_targeting_its_own_section_is_rejected(): void
    {
        $questionnaire = Questionnaire::factory()->create();
        $section = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id]);

        $this->makeQuestionWithOptionTargeting($section, $section);

        $response = $this->actingAs($this->admin)->getJson("/api/questionnaires/{$questionnaire->id}/validate");

        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('branch_target_is_self', $codes);
    }

    #[Test]
    public function a_cycle_between_sections_is_detected(): void
    {
        $questionnaire = Questionnaire::factory()->create();
        $s1 = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id, 'sort_order' => 1]);
        $s2 = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id, 'sort_order' => 2]);

        // s1 -> s2 (lewat urutan default) dan s2 -> s1 (lewat branch opsi) = siklus.
        $this->makeQuestionWithOptionTargeting($s2, $s1);

        $response = $this->actingAs($this->admin)->getJson("/api/questionnaires/{$questionnaire->id}/validate");

        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('branch_cycle_detected', $codes);
    }

    #[Test]
    public function an_unreachable_section_is_detected(): void
    {
        $questionnaire = Questionnaire::factory()->create();
        $s1 = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id, 'sort_order' => 1]);
        QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id, 'sort_order' => 2]);
        $s3 = QuestionnaireSection::factory()->create(['questionnaire_id' => $questionnaire->id, 'sort_order' => 3]);

        // s1 lompat langsung ke s3 lewat branch, sehingga s2 (default next dari
        // s1) tidak pernah dijangkau dari manapun kecuali dari dirinya sendiri.
        $this->makeQuestionWithOptionTargeting($s1, $s3);

        $response = $this->actingAs($this->admin)->getJson("/api/questionnaires/{$questionnaire->id}/validate");

        $codes = collect($response->json('data.errors'))->pluck('code');
        $this->assertContains('section_unreachable', $codes);
    }
}
