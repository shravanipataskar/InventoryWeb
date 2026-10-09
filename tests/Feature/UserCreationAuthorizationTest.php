<?php

namespace Tests\Feature;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserCreationAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_see_add_user_and_create_a_user_with_phone_and_status()
    {
        $admin = $this->makeUser('ADMIN');
        $role = $this->makeRole('PURCHASE_OFFICER', ['users.view']);
        $this->actingAs($admin);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Add User');
        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('Full Name')
            ->assertSee('Email Address')
            ->assertSee('Phone Number')
            ->assertSee('Account Status')
            ->assertSee('type="tel"', false)
            ->assertDontSee('Optional. Enter 7 to 15 digits; spaces, +, parentheses, periods, and hyphens are allowed.')
            ->assertSee('value="active" selected', false);
        $this->post(route('users.store'), [
            'name' => 'Created by Admin',
            'email' => 'created-' . Str::uuid() . '@example.test',
            'phone' => '+1 (415) 555-0134',
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
            'role_id' => $role->id,
            'status' => 'inactive',
        ])->assertRedirect(route('users.index'));

        $createdUser = User::where('name', 'Created by Admin')->firstOrFail();
        $this->assertSame('PURCHASE_OFFICER', $createdUser->role);
        $this->assertSame('+1 (415) 555-0134', $createdUser->phone);
        $this->assertFalse($createdUser->is_active);
        $this->assertTrue(Hash::check('test-password', $createdUser->password));
        $this->assertTrue($createdUser->roles->contains('id', $role->id));
    }

    public function test_new_users_default_to_active_when_status_is_omitted()
    {
        $admin = $this->makeUser('ADMIN');
        $role = $this->makeRole('STORE_MANAGER', ['users.view']);
        $this->actingAs($admin);
        $email = 'active-' . Str::uuid() . '@example.test';

        $this->post(route('users.store'), [
            'name' => 'Active by Default',
            'email' => $email,
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
            'role_id' => $role->id,
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'phone' => null,
            'is_active' => true,
        ]);
    }

    public function test_user_creation_validates_required_fields_and_phone_and_status_values()
    {
        $admin = $this->makeUser('ADMIN');
        $this->actingAs($admin);

        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
                'role_id' => '',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'name',
                'email',
                'password',
                'password_confirmation',
                'role_id',
            ]);

        $role = $this->makeRole('PURCHASE_OFFICER', ['users.view']);
        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Invalid Contact',
                'email' => 'invalid-contact-' . Str::uuid() . '@example.test',
                'phone' => '123',
                'password' => 'test-password',
                'password_confirmation' => 'different-password',
                'role_id' => $role->id,
                'status' => 'pending',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['phone', 'password_confirmation', 'status']);

        $this->get(route('users.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'name="phone"',
                'The phone number must contain between 7 and 15 digits.',
                'name="password_confirmation"',
                'The password confirmation and password must match.',
                'name="status"',
                'The selected status is invalid.',
            ], false);
    }

    public function test_non_admins_cannot_create_users_even_when_permissions_are_granted()
    {
        foreach (['OWNER', 'PURCHASE_OFFICER', 'STORE_MANAGER'] as $roleName) {
            $user = $this->makeUser($roleName);
            $role = $this->makeRole($roleName, [
                'users.view',
                'users.create',
                'users.edit',
            ]);
            $user->roles()->sync([$role->id]);
            $this->actingAs($user);

            $this->get(route('users.index'))
                ->assertOk()
                ->assertDontSee('Add User');
            $this->get(route('users.create'))->assertForbidden();

            $email = strtolower($roleName) . '-' . Str::uuid() . '@example.test';
            $this->post(route('users.store'), [
                'name' => 'Unauthorized User',
                'email' => $email,
                'password' => 'test-password',
                'password_confirmation' => 'test-password',
                'role_id' => $role->id,
            ])->assertForbidden();

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_existing_user_view_and_edit_access_still_uses_role_permissions()
    {
        $owner = $this->makeUser('OWNER');
        $ownerRole = $this->makeRole('OWNER', [
            'users.view',
            'users.edit',
        ]);
        $owner->roles()->sync([$ownerRole->id]);

        $target = $this->makeUser('PURCHASE_OFFICER');
        $this->actingAs($owner);

        $this->get(route('users.show', $target->id))->assertOk();
        $this->get(route('users.edit', $target->id))->assertOk();
    }

    private function makeUser($roleName)
    {
        return User::create([
            'name' => 'Test ' . $roleName,
            'email' => strtolower($roleName) . '-' . Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => $roleName,
            'is_active' => true,
        ]);
    }

    private function makeRole($roleName, array $permissions)
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['label' => ucwords(strtolower(str_replace('_', ' ', $roleName)))]
        );

        foreach ($permissions as $permissionName) {
            [$module, $action] = explode('.', $permissionName, 2);
            $permission = Permission::firstOrCreate(
                ['module' => $module, 'action' => $action],
                ['label' => ucfirst($module) . ' ' . ucfirst($action)]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }
}
