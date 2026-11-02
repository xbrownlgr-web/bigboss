<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositNumber;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin > Dépôts Mobile Money : les numéros où les clients envoient l'argent, pays par pays.
 * Un client ne voit que les numéros actifs de son pays.
 */
class DepositNumberController extends Controller
{
    public function index(): View
    {
        $numbers = DepositNumber::query()->orderBy('sort_order')->orderBy('id')->get()->groupBy('country');

        return view('admin.deposits', [
            'countries' => User::COUNTRIES,
            'numbers' => $numbers,
            'operators' => array_keys(DepositNumber::OPERATORS),
            'countryOperators' => DepositNumber::COUNTRY_OPERATORS,
            'operatorColors' => DepositNumber::OPERATORS,
            // Clients inscrits par pays : montre où un numéro manque vraiment.
            'clients' => User::query()->whereNotNull('country')->selectRaw('country, COUNT(*) as total')->groupBy('country')->pluck('total', 'country'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) DepositNumber::query()->where('country', $data['country'])->max('sort_order') + 1;
        $number = DepositNumber::create($data + ['is_active' => true]);

        return redirect('/admin/depots#' . $number->country)
            ->with('success', "{$number->operator} ajouté pour " . User::COUNTRIES[$number->country] . ' : les clients de ce pays le voient tout de suite.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $number = DepositNumber::findOrFail($id);
        $number->update($this->validated($request));

        return redirect('/admin/depots#' . $number->country)->with('success', "Numéro {$number->operator} mis à jour.");
    }

    public function toggle(int $id): RedirectResponse
    {
        $number = DepositNumber::findOrFail($id);
        $number->update(['is_active' => ! $number->is_active]);

        return redirect('/admin/depots#' . $number->country)->with('success', $number->is_active
            ? "{$number->operator} est de nouveau visible dans l’application."
            : "{$number->operator} est masqué : les clients ne le voient plus.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $number = DepositNumber::findOrFail($id);
        $number->delete();

        return redirect('/admin/depots#' . $number->country)->with('success', "Numéro {$number->operator} supprimé.");
    }

    /**
     * @return array{country: string, operator: string, number: string, account_name: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'country' => ['required', Rule::in(array_keys(User::COUNTRIES))],
            'operator' => ['required', Rule::in(array_keys(DepositNumber::OPERATORS))],
            'number' => ['required', 'string', 'max:40', 'regex:/^[0-9+ ().-]{6,40}$/'],
            // Le client vérifie ce nom pendant le transfert : sans lui, il ne peut pas repérer une erreur.
            'account_name' => ['required', 'string', 'max:120'],
        ], [
            'number.regex' => 'Le numéro ne doit contenir que des chiffres, des espaces et éventuellement « + ».',
            'account_name.required' => 'Indiquez le nom affiché pendant le transfert : le client le vérifie avant de payer.',
        ]);
    }
}
