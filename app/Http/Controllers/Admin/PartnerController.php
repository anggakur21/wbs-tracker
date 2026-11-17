<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Partner companies. Their people's sign-in accounts are managed by PartnerAccountController. */
class PartnerController extends Controller
{
    public function index()
    {
        return view('admin.partners.index', [
            'partners' => Partner::withCount(['users', 'projects'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $partner = Partner::create($data);

        return redirect()->route('admin.partners.show', $partner)
            ->with('status', 'Partner "'.$partner->name.'" added. Now add the accounts of the people who will update the WBS.');
    }

    public function show(Partner $partner)
    {
        $partner->load(['users', 'projects']);

        return view('admin.partners.show', compact('partner'));
    }

    public function update(Request $request, Partner $partner)
    {
        $data = $request->validateWithBag('partner', $this->rules($partner));

        $partner->update($data);

        return redirect()->route('admin.partners.show', $partner)->with('status', 'Partner details saved.');
    }

    public function destroy(Partner $partner)
    {
        if ($partner->projects()->exists()) {
            return back()->withErrors(['delete' => 'Partner "'.$partner->name.'" still has projects. Delete or reassign those projects first.']);
        }

        // Accounts belong to the company; they go with it.
        $partner->users()->delete();
        $partner->delete();

        return redirect()->route('admin.partners.index')->with('status', 'Partner "'.$partner->name.'" and its accounts deleted.');
    }

    private function rules(?Partner $partner = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('partners', 'name')->ignore($partner?->id)],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
