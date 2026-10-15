<?php

namespace Tests\Feature;

use App\Domains\Respondents\Models\Answer;
use App\Domains\Respondents\Models\AnswerChoice;
use App\Domains\Respondents\Models\IdempotencyKey;
use App\Domains\Respondents\Models\ResponseSession;
use App\Domains\Respondents\Models\SurveyResponse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RespondentSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymous_session_defaults_have_no_identity_reference(): void
    {
        $session = ResponseSession::factory()->create();

        $this->assertSame(ResponseSession::MODE_ANONYMOUS, $session->mode);
        $this->assertSame(ResponseSession::STATUS_DRAFT, $session->status);
        $this->assertNotNull($session->anonymous_nonce_hash);
        $this->assertNull($session->eligibility_id);
        $this->assertNull($session->subject_type);
        $this->assertNull($session->subject_id);
        $this->assertNotNull($session->campaign);
        $this->assertNotNull($session->snapshot);
    }

    #[Test]
    public function identified_session_carries_eligibility_and_subject_reference(): void
    {
        $session = ResponseSession::factory()->identified()->create();

        $this->assertSame(ResponseSession::MODE_IDENTIFIED, $session->mode);
        $this->assertNull($session->anonymous_nonce_hash);
        $this->assertNotNull($session->eligibility_id);
        $this->assertNotNull($session->subject_type);
        $this->assertNotNull($session->subject_id);
        $this->assertNotNull($session->eligibility);
    }

    #[Test]
    public function anonymous_response_has_no_identity_reference(): void
    {
        $response = SurveyResponse::factory()->create();

        $this->assertSame(ResponseSession::MODE_ANONYMOUS, $response->mode);
        $this->assertNull($response->subject_type);
        $this->assertNull($response->subject_id);
        $this->assertNotNull($response->campaign);
        $this->assertNotNull($response->snapshot);
        $this->assertNotNull($response->session);
    }

    #[Test]
    public function answer_and_choice_relationships_resolve(): void
    {
        $answer = Answer::factory()->create(['value_type' => 'single_choice', 'text_value' => null]);
        $choice = AnswerChoice::factory()->create(['answer_id' => $answer->id]);

        $this->assertNotNull($answer->response);
        $this->assertTrue($answer->choices->contains($choice));
        $this->assertSame($answer->id, $choice->answer->id);
    }

    #[Test]
    public function idempotency_key_is_unique_per_scope_and_key(): void
    {
        $key = IdempotencyKey::factory()->create([
            'scope_hash' => 'scope-a',
            'key_hash' => 'key-a',
        ]);

        $this->assertSame(IdempotencyKey::STATUS_PROCESSING, $key->status);

        $this->expectException(QueryException::class);

        IdempotencyKey::factory()->create([
            'scope_hash' => 'scope-a',
            'key_hash' => 'key-a',
        ]);
    }
}
