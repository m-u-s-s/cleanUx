<?php

namespace Tests\Unit\Services;

use App\Models\OrganizationMember;
use App\Services\PermissionService;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionService();
    }

    public function test_owner_can_access_all_key_permissions(): void
    {
        $member = new OrganizationMember();
        $member->role = 'owner';
        $member->permissions = [];

        $this->assertTrue($this->service->memberCan($member, 'bookings.create'));
    }

    public function test_worker_cannot_access_admin_level_permissions(): void
    {
        $member = new OrganizationMember();
        $member->role = 'worker';
        $member->permissions = [];

        $this->assertFalse($this->service->memberCan($member, 'finance.manage'));
    }

    public function test_custom_permission_override_grants_extra_access(): void
    {
        $member = new OrganizationMember();
        $member->role = 'worker';
        $member->permissions = ['finance.manage' => true];

        $this->assertTrue($this->service->memberCan($member, 'finance.manage'));
    }

    public function test_custom_permission_override_revokes_access(): void
    {
        $member = new OrganizationMember();
        $member->role = 'owner';
        $member->permissions = ['finance.manage' => false];

        $this->assertFalse($this->service->memberCan($member, 'finance.manage'));
    }

    public function test_all_permission_keys_returns_non_empty_list(): void
    {
        $keys = $this->service->allPermissionKeys();

        $this->assertNotEmpty($keys);
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_all_permissions_for_returns_boolean_map(): void
    {
        $member = new OrganizationMember();
        $member->role = 'owner';
        $member->permissions = [];

        $result = $this->service->allPermissionsFor($member);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        foreach ($result as $perm => $value) {
            $this->assertIsString($perm);
            $this->assertIsBool($value);
        }
    }
}
