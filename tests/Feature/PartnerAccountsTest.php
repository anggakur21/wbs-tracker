<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsWbs;
use Tests\TestCase;

class PartnerAccountsTest extends TestCase
{
    use BuildsWbs, RefreshDatabase;

    public function test_admin_adds_a_partner_then_accounts_for_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.partners.store'), ['name' => 'PT Mitra Solusi'])
            ->assertRedirect(route('admin.partners.show', Partner::first()));

        $partner = Partner::first();
        $this->assertSame('PT Mitra Solusi', $partner->name);

        foreach (['project_manager' => 'rina', 'project_admin' => 'dewi', 'business_analyst' => 'budi'] as $role => $username) {
            $response = $this->actingAs($admin)->post(route('admin.accounts.store', $partner), [
                'name' => ucfirst($username), 'username' => $username, 'partner_role' => $role,
            ]);

            $credentials = $response->getSession()->get('credentials');
            $this->assertSame($username, $credentials['username']);

            $user = User::where('username', $username)->first();
            $this->assertSame($partner->id, $user->partner_id);
            $this->assertSame($role, $user->partner_role);
            $this->assertTrue($user->must_change_password);
            $this->assertTrue(Hash::check($credentials['password'], $user->password));
        }

        $this->actingAs($admin)->get(route('admin.partners.show', $partner))
            ->assertOk()->assertSee('Project Manager')->assertSee('Project Admin')->assertSee('Business Analyst');
    }

    public function test_admin_resets_a_partner_password(): void
    {
        $admin = $this->admin();
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $user = $this->partnerUser($partner);

        $response = $this->actingAs($admin)->post(route('admin.accounts.reset', $user));

        $password = $response->getSession()->get('credentials')['password'];
        $user->refresh();
        $this->assertFalse(Hash::check('secret-password', $user->password));
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_one_time_password_must_be_replaced_before_using_the_app(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $user = $this->partnerUser($partner);
        $user->forceFill(['must_change_password' => true])->save();

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('account.password'));
        $this->actingAs($user)->get(route('account.password'))->assertOk()->assertSee('Choose your password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'secret-password',
            'password' => 'my-own-password',
            'password_confirmation' => 'my-own-password',
        ])->assertRedirect(route('home'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('my-own-password', $user->password));
        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_partner_with_projects_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $this->projectFor($partner);

        $this->actingAs($admin)->delete(route('admin.partners.destroy', $partner))->assertSessionHasErrors('delete');
        $this->assertModelExists($partner);
    }

    public function test_partner_staff_cannot_manage_partners(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $user = $this->partnerUser($partner);

        $this->actingAs($user)->get(route('admin.partners.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.accounts.reset', $user))->assertForbidden();
        $this->actingAs($user)->get(route('admin.drive.show'))->assertForbidden();
    }
}
