<?php

namespace Tests\Feature;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RolePermissionTransactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invalid_permission_payload_keeps_existing_role_permissions()
    {
        $user = $this->makeAdmin();
        $role = Role::where('name', 'ADMIN')->firstOrFail();
        $permission = Permission::where('module', 'categories')
            ->where('action', 'view')
            ->firstOrFail();
        $role->permissions()->sync([$permission->id]);

        $this->actingAs($user)
            ->from(route('roles.index'))
            ->put(route('roles.update', $role->id), ['permissions' => 'invalid'])
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_role_permission_sync_rolls_back_if_a_pivot_write_fails()
    {
        $user = $this->makeAdmin();
        $role = Role::where('name', 'ADMIN')->firstOrFail();
        $permission = Permission::where('module', 'categories')
            ->where('action', 'view')
            ->firstOrFail();
        $role->permissions()->sync([$permission->id]);

        $shouldFail = true;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$shouldFail) {
            if (!$shouldFail) {
                return;
            }

            if (stripos($query->sql, 'delete') !== false
                && stripos($query->sql, 'permission_role') !== false) {
                throw new \RuntimeException('Simulated permission pivot write failure.');
            }
        });

        try {
            $response = $this->actingAs($user)
                ->put(route('roles.update', $role->id), ['permissions' => []]);
        } finally {
            $shouldFail = false;
        }

        $response->assertStatus(500);
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    private function makeAdmin()
    {
        return User::create([
            'name' => 'Role Transaction Test Admin',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]);
    }
}
