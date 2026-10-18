<?php

namespace Tests\Unit\Rules;

use App\Rules\NoAbsenceTriggerConditions;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Structural enforcement of wiki.md §5's "no notification class may
 * trigger on recipient absence" governance note — previously documented
 * only, never enforced.
 */
class NoAbsenceTriggerConditionsTest extends TestCase
{
    private function fails(array $conditions): bool
    {
        return Validator::make(
            ['conditions' => $conditions],
            ['conditions' => [new NoAbsenceTriggerConditions]],
        )->fails();
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function absenceKeyProvider(): array
    {
        return [
            'inactive_days' => [['inactive_days' => 30]],
            'days_inactive' => [['days_inactive' => 30]],
            'dormant_days' => [['dormant_days' => 14]],
            'no_activity_days' => [['no_activity_days' => 7]],
            'days_since_last_login' => [['days_since_last_login' => 30]],
            'weeks_without_login' => [['weeks_without_login' => 4]],
            'last_login_at' => [['last_login_at' => '2026-01-01']],
            'lastSeenAt (camelCase variant)' => [['lastSeenAt' => '2026-01-01']],
            'last_purchase_at' => [['last_purchase_at' => '2026-01-01']],
            'login_streak' => [['login_streak' => 0]],
            'churn_risk' => [['churn_risk' => 'high']],
            'idle_days' => [['idle_days' => 10]],
        ];
    }

    #[DataProvider('absenceKeyProvider')]
    public function test_it_rejects_recipient_activity_field_keys(array $conditions): void
    {
        $this->assertTrue($this->fails($conditions), 'Expected condition keys '.json_encode($conditions).' to be rejected.');
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function domainEventKeyProvider(): array
    {
        return [
            'empty conditions' => [[]],
            'amount threshold' => [['amount_gt' => 100]],
            'order status' => [['status' => 'failed']],
            'region' => [['region' => 'ZA']],
            'severity' => [['severity' => 'critical']],
        ];
    }

    #[DataProvider('domainEventKeyProvider')]
    public function test_it_accepts_legitimate_domain_event_condition_keys(array $conditions): void
    {
        $this->assertFalse($this->fails($conditions), 'Expected condition keys '.json_encode($conditions).' to be accepted.');
    }
}
