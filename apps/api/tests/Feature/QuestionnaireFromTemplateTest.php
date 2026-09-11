<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Role;
use App\Domains\Questionnaires\Models\QuestionnaireSection;
use App\Domains\Questionnaires\Models\SurveyTemplate;
use App\Domains\Questionnaires\Models\SurveyTemplateOption;
use App\Domains\Questionnaires\Models\SurveyTemplateQuestion;
use App\Domains\Questionnaires\Models\SurveyTemplateSection;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionnaireFromTemplateTest extends TestCase
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

    #[Test]
    public function creating_questionnaire_from_template_copies_structure_and_remaps_branch_target(): void
    {
        $template = SurveyTemplate::factory()->create([
            'tenant_id' => $this->admin->tenant_id,
            'status' => 'active',
        ]);

        $sectionA = SurveyTemplateSection::factory()->create(['template_id' => $template->id, 'code' => 'S1']);
        $sectionB = SurveyTemplateSection::factory()->create(['template_id' => $template->id, 'code' => 'S2']);

        $question = SurveyTemplateQuestion::factory()->create([
            'template_id' => $template->id,
            'section_id' => $sectionA->id,
            'code' => 'Q1',
            'type' => 'single_choice',
        ]);

        SurveyTemplateOption::factory()->create([
            'question_id' => $question->id,
            'code' => 'O1',
            'target_section_id' => $sectionB->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/questionnaires', [
            'code' => 'Q-FROM-TPL',
            'title' => 'Dari Template',
            'template_id' => $template->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.audience_type', $template->audience_type);
        $response->assertJsonPath('data.identity_mode', $template->identity_mode);

        $questionnaireId = $response->json('data.id');

        $this->assertDatabaseHas('questionnaire_sections', ['questionnaire_id' => $questionnaireId, 'code' => 'S1']);
        $this->assertDatabaseHas('questionnaire_sections', ['questionnaire_id' => $questionnaireId, 'code' => 'S2']);
        $this->assertDatabaseHas('questions', ['questionnaire_id' => $questionnaireId, 'code' => 'Q1', 'type' => 'single_choice']);

        $newSectionB = QuestionnaireSection::query()
            ->where('questionnaire_id', $questionnaireId)->where('code', 'S2')->first();

        $this->assertDatabaseHas('question_options', [
            'code' => 'O1',
            'target_section_id' => $newSectionB->id,
        ]);
    }

    #[Test]
    public function creating_questionnaire_from_unknown_template_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/questionnaires', [
            'code' => 'Q-BAD-TPL',
            'title' => 'Gagal',
            'template_id' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'template_not_found');
    }

    #[Test]
    public function listing_templates_only_returns_active_templates_for_own_tenant(): void
    {
        SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'active', 'title' => 'A']);
        SurveyTemplate::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'inactive', 'title' => 'B']);
        SurveyTemplate::factory()->create(['tenant_id' => 'other-tenant', 'status' => 'active', 'title' => 'C']);

        $response = $this->actingAs($this->admin)->getJson('/api/templates');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertSame(['A'], $titles->all());
    }
}
