<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sign-in accounts for a partner's people (Project Manager, Project Admin, Business Analyst).
 * The admin never chooses a password: a one-time password is generated, shown once,
 * and the person must replace it at their first sign-in.
 */
class PartnerAccountController extends Controller
{
    public function store(Request $request, Partner $partner)
    {
        $data = $request->validateWithBag('account', $this->rules(), $this->messages());

        $password = User::temporaryPassword();
        $user = $partner->users()->create($data + [
            'role' => 'partner',
            'password' => $password,
            'must_change_password' => true,
        ]);

        return redirect()->route('admin.partners.show', $partner)
            ->with('credentials', ['name' => $user->name, 'username' => $user->username, 'password' => $password, 'new' => true]);
    }

    public function update(Request $request, User $account)
    {
        $partner = $this->partnerOf($account);

        $data = $request->validateWithBag('account'.$account->id, $this->rules($account), $this->messages());
        $account->update($data);

        return redirect()->route('admin.partners.show', $partner)->with('status', 'Account "'.$account->username.'" saved.');
    }

    public function resetPassword(User $account)
    {
        $partner = $this->partnerOf($account);

        $password = User::temporaryPassword();
        $account->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'remember_token' => null,   // sign out "remember me" sessions on other devices
        ])->save();

        return redirect()->route('admin.partners.show', $partner)
            ->with('credentials', ['name' => $account->name, 'username' => $account->username, 'password' => $password, 'new' => false]);
    }

    public function destroy(User $account)
    {
        $partner = $this->partnerOf($account);
        $account->delete();

        return redirect()->route('admin.partners.show', $partner)->with('status', 'Account "'.$account->username.'" deleted.');
    }

    private function partnerOf(User $account): Partner
    {
        abort_unless($account->isPartner() && $account->partner, 404);

        return $account->partner;
    }

    private function messages(): array
    {
        return ['username.regex' => 'The username can only contain letters, numbers, dots, dashes and underscores.'];
    }

    private function rules(?User $account = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]+$/', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($account?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account?->id)],
            'partner_role' => ['required', Rule::in(array_keys(User::PARTNER_ROLES))],
        ];
    }
}
