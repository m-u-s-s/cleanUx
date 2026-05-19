<?php

namespace Tests\Feature\Risk;

use App\Models\RiskEvaluation;
use App\Models\User;
use App\Services\Risk\Rules\AccountAgeRule;
use App\Services\Risk\Rules\GeoMismatchRule;
use App\Services\Risk\Rules\IpReputationRule;
use App\Services\Risk\Rules\PaymentDeclineRule;
use App\Services\Risk\RiskContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RiskRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_decline_rule_hits_above_threshold(): void
    {
        $rule = new PaymentDeclineRule();
        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            extra: ['decline_count_last_24h' => 5],
        );

        $hit = $rule->evaluate($context, ['threshold' => 3]);

        $this->assertNotNull($hit);
        $this->assertSame('payment.decline_burst', $hit->code);
        $this->assertGreaterThan(0, $hit->score);
    }

    public function test_payment_decline_rule_no_hit_below_threshold(): void
    {
        $rule = new PaymentDeclineRule();
        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            extra: ['decline_count_last_24h' => 2],
        );

        $this->assertNull($rule->evaluate($context, ['threshold' => 3]));
    }

    public function test_account_age_rule_scores_younger_higher(): void
    {
        $rule = new AccountAgeRule();
        $userYoung = User::factory()->client()->create(['created_at' => now()->subHour()]);
        $userMid = User::factory()->client()->create(['created_at' => now()->subHours(12)]);
        $userOld = User::factory()->client()->create(['created_at' => now()->subDays(30)]);

        $params = ['threshold_hours' => 24, 'max_score' => 30];

        $h1 = $rule->evaluate(new RiskContext('login', $userYoung), $params);
        $h2 = $rule->evaluate(new RiskContext('login', $userMid), $params);
        $h3 = $rule->evaluate(new RiskContext('login', $userOld), $params);

        $this->assertNotNull($h1);
        $this->assertNotNull($h2);
        $this->assertNull($h3);
        $this->assertGreaterThan($h2->score, $h1->score);
    }

    public function test_account_age_rule_returns_null_for_old_account(): void
    {
        $rule = new AccountAgeRule();
        $user = User::factory()->client()->create(['created_at' => now()->subYear()]);

        $hit = $rule->evaluate(new RiskContext('signup', $user), ['threshold_hours' => 24]);

        $this->assertNull($hit);
    }

    public function test_geo_mismatch_rule_hits_when_countries_differ(): void
    {
        $rule = new GeoMismatchRule();
        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            extra: ['expected_country_code' => 'BE', 'observed_country_code' => 'RU'],
        );

        $hit = $rule->evaluate($context);

        $this->assertNotNull($hit);
        $this->assertSame('geo.country_mismatch', $hit->code);
    }

    public function test_geo_mismatch_rule_no_hit_when_countries_match(): void
    {
        $rule = new GeoMismatchRule();
        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            extra: ['expected_country_code' => 'BE', 'observed_country_code' => 'be'],
        );

        $this->assertNull($rule->evaluate($context));
    }

    public function test_ip_reputation_rule_hits_on_cidr_match(): void
    {
        $rule = new IpReputationRule();
        $request = Request::create('/test', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.42.10');

        $context = new RiskContext('login', request: $request);

        $hit = $rule->evaluate($context, ['cidrs' => ['192.168.0.0/16'], 'score' => 35]);

        $this->assertNotNull($hit);
        $this->assertSame(35, $hit->score);
    }

    public function test_ip_reputation_rule_no_hit_outside_cidr(): void
    {
        $rule = new IpReputationRule();
        $request = Request::create('/test', 'POST');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $context = new RiskContext('login', request: $request);

        $this->assertNull($rule->evaluate($context, ['cidrs' => ['192.168.0.0/16']]));
    }

    public function test_ip_reputation_rule_with_no_cidrs_does_not_hit(): void
    {
        $rule = new IpReputationRule();
        $request = Request::create('/test', 'POST');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $context = new RiskContext('login', request: $request);

        $this->assertNull($rule->evaluate($context, ['cidrs' => []]));
    }
}
