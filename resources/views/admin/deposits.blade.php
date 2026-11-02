@extends('admin.layout')

@section('title', 'Dépôts Mobile Money')
@section('subtitle', 'Numéros où les clients envoient l’argent pour recharger · chaque client ne voit que ceux de son pays')

@section('content')
    @php
        $covered = collect($countries)->keys()->filter(fn ($country) => ($numbers[$country] ?? collect())->where('is_active', true)->isNotEmpty());
        $missingWithClients = collect($countries)->keys()->reject(fn ($country) => $covered->contains($country))->filter(fn ($country) => ($clients[$country] ?? 0) > 0);
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Pays où l’on peut recharger</span><span class="bf-kpi-icon"><i class="bi bi-globe-europe-africa"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $covered->count() }} / {{ count($countries) }}</div>
                <div class="bf-kpi-foot">au moins un numéro actif</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi {{ $missingWithClients->isNotEmpty() ? 'is-alert' : '' }}">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Clients sans numéro local</span><span class="bf-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $missingWithClients->sum(fn ($country) => $clients[$country]) }}</div>
                <div class="bf-kpi-foot">{{ $missingWithClients->isEmpty() ? 'tous vos clients peuvent recharger' : $missingWithClients->map(fn ($country) => $countries[$country])->implode(', ') }}</div>
            </div>
        </div>
    </div>

    <section class="bf-card mb-3">
        <div class="bf-card-header">
            <div>
                <h2 class="bf-card-title"><i class="bi bi-plus-circle"></i> Ajouter un numéro de dépôt</h2>
                <p class="bf-card-sub">Visible tout de suite dans l’écran Recharger des clients de ce pays. Le nom est celui que l’opérateur affiche pendant le transfert : le client le vérifie avant de payer.</p>
            </div>
        </div>
        <form method="POST" action="/admin/depots" class="bf-card-body" id="addDeposit">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="country">Pays</label>
                    <select class="form-select" id="country" name="country" required data-country>
                        @foreach ($countries as $key => $label)
                            <option value="{{ $key }}" @selected(old('country', 'benin') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="operator">Opérateur</label>
                    <select class="form-select" id="operator" name="operator" required data-operator>
                        @foreach ($operators as $operator)
                            <option value="{{ $operator }}" @selected(old('operator') === $operator)>{{ $operator }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="number">Numéro</label>
                    <input class="form-control tabular" id="number" name="number" value="{{ old('number') }}" placeholder="Ex. 01 97 00 00 00" required>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="account_name">Nom affiché pendant le transfert</label>
                    <input class="form-control" id="account_name" name="account_name" value="{{ old('account_name') }}" placeholder="Ex. JEAN DUPONT" required>
                </div>
                <div class="col-12 col-xl-2 d-grid">
                    <button class="btn bf-btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Ajouter</button>
                </div>
            </div>
        </form>
    </section>

    <div class="row g-3">
        @foreach ($countries as $country => $label)
            @php
                $list = $numbers[$country] ?? collect();
                $active = $list->where('is_active', true)->count();
            @endphp
            <div class="col-12 col-xl-6">
                <section class="bf-card h-100" id="{{ $country }}">
                    <div class="bf-card-header">
                        <div>
                            <h2 class="bf-card-title">{{ $label }}</h2>
                            <p class="bf-card-sub">{{ $clients[$country] ?? 0 }} client(s) · opérateurs du pays : {{ implode(', ', $countryOperators[$country] ?? []) }}</p>
                        </div>
                        @if ($active > 0)
                            <span class="bf-badge success"><span class="dot"></span>{{ $active }} numéro(s) actif(s)</span>
                        @else
                            <span class="bf-badge danger"><span class="dot"></span>Recharge indisponible</span>
                        @endif
                    </div>
                    <div class="bf-card-body vstack gap-2">
                        @if ($country === 'rdc')
                            <div class="bf-small" style="color:#B45309;"><i class="bi bi-info-circle"></i> En RD Congo, on paie en francs congolais ou en dollars, alors que l’application affiche les montants en FCFA. Précisez au client le montant exact à envoyer avant d’activer ce pays.</div>
                        @endif

                        @forelse ($list as $number)
                            <div class="p-3 rounded-3" style="border:1px solid var(--bf-border);{{ $number->is_active ? '' : 'opacity:.6;' }}">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="d-inline-block rounded-circle" style="width:12px;height:12px;background:{{ $operatorColors[$number->operator] ?? '#FFD700' }};"></span>
                                    <strong>{{ $number->operator }}</strong>
                                    <span class="tabular">{{ $number->number }}</span>
                                    <span class="bf-muted">· {{ $number->account_name }}</span>
                                    @unless ($number->is_active)
                                        <span class="bf-badge neutral">Masqué</span>
                                    @endunless
                                    <div class="ms-auto d-flex gap-2">
                                        <form method="POST" action="/admin/depots/{{ $number->id }}/toggle">
                                            @csrf
                                            <button class="btn btn-sm bf-btn-light" type="submit">{{ $number->is_active ? 'Masquer' : 'Afficher' }}</button>
                                        </form>
                                        <form method="POST" action="/admin/depots/{{ $number->id }}/delete" data-confirm="Supprimer le numéro {{ $number->operator }} {{ $number->number }} ?">
                                            @csrf
                                            <button class="btn btn-sm bf-btn-danger-soft" type="submit" aria-label="Supprimer"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <details class="mt-2">
                                    <summary class="bf-small" style="cursor:pointer;">Modifier</summary>
                                    <form method="POST" action="/admin/depots/{{ $number->id }}" class="row g-2 mt-1">
                                        @csrf
                                        <input type="hidden" name="country" value="{{ $country }}">
                                        <div class="col-12 col-sm-4">
                                            <select class="form-select form-select-sm" name="operator" aria-label="Opérateur">
                                                @foreach ($operators as $operator)
                                                    <option value="{{ $operator }}" @selected($number->operator === $operator)>{{ $operator }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-4"><input class="form-control form-control-sm tabular" name="number" value="{{ $number->number }}" aria-label="Numéro" required></div>
                                        <div class="col-12 col-sm-4"><input class="form-control form-control-sm" name="account_name" value="{{ $number->account_name }}" aria-label="Nom affiché" required></div>
                                        <div class="col-12"><button class="btn btn-sm bf-btn-primary" type="submit">Enregistrer</button></div>
                                    </form>
                                </details>
                            </div>
                        @empty
                            <p class="bf-small bf-muted mb-0">Aucun numéro : les clients de ce pays voient « La recharge n’est pas encore disponible dans votre pays » et sont invités à écrire au conseiller.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        // Opérateurs du pays choisi en premier dans la liste.
        (function () {
            var countryOperators = @json($countryOperators);
            var country = document.querySelector('[data-country]');
            var operator = document.querySelector('[data-operator]');
            var all = Array.from(operator.options).map(function (option) { return option.value; });
            var refresh = function () {
                var local = countryOperators[country.value] || [];
                var current = operator.value;
                operator.innerHTML = '';
                local.concat(all.filter(function (name) { return local.indexOf(name) === -1; })).forEach(function (name) {
                    operator.add(new Option(name, name));
                });
                operator.value = local.indexOf(current) !== -1 ? current : (local[0] || all[0]);
            };
            country.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endpush
