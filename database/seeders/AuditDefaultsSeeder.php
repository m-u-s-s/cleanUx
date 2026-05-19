<?php

namespace Database\Seeders;

use App\Models\AuditRedactionRule;
use App\Models\AuditRetentionPolicy;
use Illuminate\Database\Seeder;

class AuditDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $redactionRules = [
            [
                'code' => 'iban_regex',
                'name' => 'IBAN mask',
                'pattern' => '/[A-Z]{2}\d{2}[A-Z0-9]{10,30}/i',
                'match_type' => AuditRedactionRule::MATCH_REGEX,
                'replacement' => '[IBAN]',
                'is_active' => true,
                'priority' => 50,
            ],
            [
                'code' => 'card_pan_regex',
                'name' => 'Card PAN mask',
                'pattern' => '/\b\d{13,19}\b/',
                'match_type' => AuditRedactionRule::MATCH_REGEX,
                'replacement' => '[CARD_PAN]',
                'is_active' => true,
                'priority' => 60,
            ],
            [
                'code' => 'jwt_regex',
                'name' => 'JWT mask',
                'pattern' => '/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/',
                'match_type' => AuditRedactionRule::MATCH_REGEX,
                'replacement' => '[JWT]',
                'is_active' => true,
                'priority' => 70,
            ],
        ];

        foreach ($redactionRules as $rule) {
            AuditRedactionRule::query()->updateOrCreate(['code' => $rule['code']], $rule);
        }

        $retentionPolicies = [
            ['code' => 'auth_default',     'name' => 'Auth events',     'domain' => 'auth',     'retention_days' => 365],
            ['code' => 'security_default', 'name' => 'Security events', 'domain' => 'security', 'retention_days' => 730],
            ['code' => 'finance_legal',    'name' => 'Finance legal',   'domain' => 'finance',  'retention_days' => 2555],
            ['code' => 'payment_legal',    'name' => 'Payment legal',   'domain' => 'payment',  'retention_days' => 2555],
            ['code' => 'gdpr_consent',     'name' => 'GDPR consent',    'domain' => 'gdpr',     'retention_days' => 2190],
            ['code' => 'kyc_aml',          'name' => 'KYC AML',         'domain' => 'kyc',      'retention_days' => 1825],
            ['code' => 'general_short',    'name' => 'General short',   'domain' => 'general',  'retention_days' => 180],
        ];

        foreach ($retentionPolicies as $p) {
            AuditRetentionPolicy::query()->updateOrCreate(
                ['code' => $p['code']],
                array_merge($p, ['is_active' => true]),
            );
        }
    }
}
