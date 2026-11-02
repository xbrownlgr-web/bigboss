@extends('admin.layout')

@section('title', 'VIP')
@section('subtitle', 'Demandes à traiter, abonnés 30 jours et coupons 1xBet réservés aux abonnés')

@php
    $tz = config('app.timezone');
    $toMark = $coupons->filter(fn ($coupon) => ! in_array($coupon->result_status, ['won', 'lost'], true))->count();
    $publishOpen = $errors->any() || old('code_1xbet');
@endphp

@push('head')
    <style>
        .bf-claim { display: grid; grid-template-columns: 64px 1fr; gap: 10px; padding: 10px; border: 1px solid var(--bf-border); border-radius: 12px; background: #fff; }
        .bf-claim-img img { display: block; width: 64px; height: 64px; border: 1px solid var(--bf-border); border-radius: 10px; background: #F1F5F3; object-fit: cover; }
        .bf-claim-actions { display: grid; grid-column: 1 / -1; grid-template-columns: 1fr 1fr; gap: 8px; }
        .bf-claim-actions .btn { width: 100%; }
        .bf-lvl { display: inline-block; width: 8px; height: 8px; flex: 0 0 auto; border-radius: 50%; }
        .bf-item .i-main { min-width: 0; flex: 1; }
        .bf-item .i-title { font-weight: 600; }
        .bf-item .i-sub { color: var(--bf-muted); font-size: .76rem; }
        .bf-item.is-soon { border-color: #F3D27A; background: #FFFBEB; }
    </style>
@endpush

@section('content')
    @unless ($whatsappReady)
        <div class="bf-flash warning">
            <i class="bi bi-exclamation-triangle"></i>
            <div>Aucun numéro WhatsApp VIP : le bouton « Accéder » de l’app ne peut pas vous écrire. <a href="/admin/settings#vip" class="fw-semibold">Le renseigner</a></div>
        </div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Abonnés actifs</span><span class="bf-kpi-icon"><i class="bi bi-gem"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $active->count() }}</div>
                <div class="bf-kpi-foot">
                    @foreach ($levels as $level)
                        <span><span class="bf-lvl" style="background: {{ $levelColors[$level] }};"></span> {{ $activeByLevel[$level] ?? 0 }}</span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi {{ ($pending->isNotEmpty() || $freeClaims->isNotEmpty()) ? 'is-alert' : '' }}">
                <div class="bf-kpi-top"><span class="bf-kpi-label">À traiter</span><span class="bf-kpi-icon"><i class="bi bi-bell"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $pending->count() + $freeClaims->count() }}</div>
                <div class="bf-kpi-foot">{{ $freeClaims->count() }} offert · {{ $pending->count() }} clé(s)</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Encaissé ce mois</span><span class="bf-kpi-icon"><i class="bi bi-cash-stack"></i></span></div>
                <div class="bf-kpi-value tabular">{{ number_format($revenueThisMonth, 0, ',', ' ') }} F</div>
                <div class="bf-kpi-foot">{{ $activationsThisMonth }} activation(s)</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi {{ $expiringSoon > 0 ? 'is-alert' : '' }}">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Expirent ≤ 3 j</span><span class="bf-kpi-icon"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $expiringSoon }}</div>
                <div class="bf-kpi-foot">rappel automatique</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5 vstack gap-3">
            {{-- Silver offert (parrainage 1xBet) --}}
            <details class="bf-card bf-fold" @if ($freeClaims->isNotEmpty()) open @endif data-desktop-open>
                <summary class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-gift"></i></span>
                    <div class="min-w-0">
                        <h2 class="bf-card-title">Silver offert</h2>
                        <p class="bf-card-sub">Code promo + dépôt 1xBet : vérifiez la capture, puis offrez 30 jours.</p>
                    </div>
                    <span class="bf-pill {{ $freeClaims->isNotEmpty() ? 'warn' : 'muted' }} ms-auto">{{ $freeClaims->count() }}</span>
                    <i class="bi bi-chevron-down bf-chev"></i>
                </summary>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($freeClaims as $claim)
                        <div class="bf-claim">
                            <a href="/admin/vip/free-claims/{{ $claim->id }}/screenshot" target="_blank" rel="noopener" class="bf-claim-img" title="Voir la capture en grand">
                                <img src="/admin/vip/free-claims/{{ $claim->id }}/screenshot" alt="Capture du dépôt" loading="lazy">
                            </a>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $claim->user->name }}</div>
                                <div class="bf-small bf-muted">ID 1xBet <span class="font-monospace fw-semibold text-body">{{ $claim->account_id }}</span><button type="button" class="bf-copy" data-copy="{{ $claim->account_id }}" aria-label="Copier l’ID"><i class="bi bi-copy"></i></button></div>
                                <div class="bf-small bf-muted text-truncate">{{ $claim->user->phone ?: $claim->user->email }} · {{ $claim->created_at->timezone($tz)->format('d/m H:i') }}</div>
                            </div>
                            <div class="bf-claim-actions">
                                <form method="POST" action="/admin/vip/free-claims/{{ $claim->id }}/approve" data-confirm="Dépôt vérifié ? Offrir 30 jours de VIP Silver à {{ $claim->user->name }} ?">
                                    @csrf
                                    <button class="btn btn-sm bf-btn-gold" type="submit"><i class="bi bi-check-lg"></i> Offrir 30 j</button>
                                </form>
                                <form method="POST" action="/admin/vip/free-claims/{{ $claim->id }}/reject" data-confirm="Refuser cette demande ?">
                                    @csrf
                                    <button class="btn btn-sm bf-btn-danger-soft" type="submit"><i class="bi bi-x-lg"></i> Refuser</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="bf-empty-line"><i class="bi bi-check2-circle"></i> Aucune demande à vérifier.</div>
                    @endforelse
                </div>
            </details>

            {{-- Clés WhatsApp en attente --}}
            <details class="bf-card bf-fold" @if ($pending->isNotEmpty()) open @endif data-desktop-open>
                <summary class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-inbox"></i></span>
                    <div class="min-w-0">
                        <h2 class="bf-card-title">Demandes en attente</h2>
                        <p class="bf-card-sub">Clients qui ont touché « Accéder » : activez dès le paiement reçu.</p>
                    </div>
                    <span class="bf-pill {{ $pending->isNotEmpty() ? 'warn' : 'muted' }} ms-auto">{{ $pending->count() }}</span>
                    <i class="bi bi-chevron-down bf-chev"></i>
                </summary>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($pending as $vipRequest)
                        <div class="bf-item">
                            <span class="bf-lvl" style="background: {{ $levelColors[$vipRequest->level] ?? '#6B7280' }};"></span>
                            <div class="i-main">
                                <div class="i-title text-truncate">{{ $vipRequest->user->name }} · {{ $vipRequest->level }}</div>
                                <div class="i-sub text-truncate"><span class="font-monospace">{{ $vipRequest->key }}</span> · {{ $vipRequest->created_at->timezone($tz)->format('d/m H:i') }}</div>
                            </div>
                            <form method="POST" action="/admin/vip/requests/{{ $vipRequest->id }}/activate" data-confirm="Paiement reçu ? Activer le VIP {{ $vipRequest->level }} de {{ $vipRequest->user->name }} pour 30 jours ?">
                                @csrf
                                <button class="btn btn-sm bf-btn-gold" type="submit">Activer</button>
                            </form>
                        </div>
                    @empty
                        <div class="bf-empty-line"><i class="bi bi-check2-circle"></i> Aucune demande en attente.</div>
                    @endforelse
                </div>
            </details>

            {{-- Activation par clé --}}
            <section class="bf-card">
                <div class="bf-card-body">
                    <label class="form-label" for="vipKey"><i class="bi bi-key"></i> Activer une clé reçue sur WhatsApp</label>
                    <form method="POST" action="/admin/vip/lookup" class="d-flex gap-2">
                        @csrf
                        <input class="form-control font-monospace text-uppercase" id="vipKey" name="key" value="{{ old('key') }}" placeholder="BF-GOLD-7K3Q9" required>
                        <button class="btn bf-btn-primary flex-shrink-0" type="submit"><i class="bi bi-search"></i></button>
                    </form>

                    @if ($lookup)
                        <div class="mt-3 p-3 rounded-3" style="background:var(--bf-surface-2);border-left:4px solid {{ $levelColors[$lookup->level] ?? '#6B7280' }};">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $lookup->user->name }}</div>
                                    <div class="bf-small bf-muted text-truncate">{{ $lookup->user->phone ?: $lookup->user->email }}</div>
                                </div>
                                <span class="bf-badge neutral"><span class="bf-lvl" style="background: {{ $levelColors[$lookup->level] ?? '#6B7280' }};"></span>{{ $lookup->level }}</span>
                            </div>
                            <div class="bf-small mt-2">
                                <span class="font-monospace fw-semibold">{{ $lookup->key }}</span> · {{ $lookup->price ? number_format($lookup->price, 0, ',', ' ') . ' FCFA' : 'prix non défini' }} · {{ $lookup->created_at->timezone($tz)->format('d/m H:i') }}
                            </div>
                            <form method="POST" action="/admin/vip/requests/{{ $lookup->id }}/activate" class="mt-2" data-confirm="Paiement reçu ? Activer le VIP {{ $lookup->level }} de {{ $lookup->user->name }} pour 30 jours ?">
                                @csrf
                                <button class="btn bf-btn-gold w-100" type="submit"><i class="bi bi-check2-circle"></i> Paiement reçu : activer 30 jours</button>
                            </form>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Abonnés actifs --}}
            <details class="bf-card bf-fold" data-desktop-open>
                <summary class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-people"></i></span>
                    <div class="min-w-0">
                        <h2 class="bf-card-title">Abonnés actifs</h2>
                        <p class="bf-card-sub">Du plus proche de la fin au plus lointain.</p>
                    </div>
                    <span class="bf-pill {{ $expiringSoon > 0 ? 'warn' : 'muted' }} ms-auto">{{ $active->count() }}</span>
                    <i class="bi bi-chevron-down bf-chev"></i>
                </summary>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($active as $subscription)
                        @php $soon = $subscription->ends_at->lessThanOrEqualTo(now()->addDays(3)); @endphp
                        <div class="bf-item {{ $soon ? 'is-soon' : '' }}">
                            <span class="bf-lvl" style="background: {{ $levelColors[$subscription->level] ?? '#6B7280' }};"></span>
                            <div class="i-main">
                                <div class="i-title text-truncate">{{ $subscription->user->name }} · {{ $subscription->level }}</div>
                                <div class="i-sub">jusqu’au {{ $subscription->ends_at->timezone($tz)->format('d/m/Y') }}</div>
                            </div>
                            <form method="POST" action="/admin/vip/subscriptions/{{ $subscription->id }}/extend" data-confirm="Ajouter 30 jours au VIP {{ $subscription->level }} de {{ $subscription->user->name }} ?">
                                @csrf
                                <button class="btn btn-sm bf-btn-light" type="submit">+30 j</button>
                            </form>
                            <form method="POST" action="/admin/vip/subscriptions/{{ $subscription->id }}/cancel" data-confirm="Arrêter maintenant le VIP {{ $subscription->level }} de {{ $subscription->user->name }} ?">
                                @csrf
                                <button class="btn btn-sm btn-link text-danger px-1" type="submit" aria-label="Arrêter l’abonnement"><i class="bi bi-x-circle"></i></button>
                            </form>
                        </div>
                    @empty
                        <div class="bf-empty-line">Aucun abonné actif.</div>
                    @endforelse
                </div>
            </details>
        </div>

        <div class="col-12 col-xl-7 vstack gap-3">
            {{-- Publication d'un coupon VIP --}}
            <details class="bf-card bf-fold" @if ($publishOpen) open @endif data-desktop-open>
                <summary class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon" style="background:#DCFCE7;color:#15803D;"><i class="bi bi-send"></i></span>
                    <div class="min-w-0">
                        <h2 class="bf-card-title">Publier un coupon VIP</h2>
                        <p class="bf-card-sub">Les abonnés du niveau reçoivent le code ; les autres une annonce sans le code.</p>
                    </div>
                    <i class="bi bi-chevron-down bf-chev"></i>
                </summary>
                <form method="POST" action="/admin/vip/coupons" class="bf-card-body row g-2" data-confirm="Publier ce coupon VIP et notifier les utilisateurs ?">
                    @csrf
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($levels as $level)
                                <input type="radio" class="btn-check" name="niveau" value="{{ $level }}" id="vipLevel{{ $level }}" @checked(old('niveau', 'Gold') === $level) required>
                                <label class="btn btn-sm bf-btn-light" for="vipLevel{{ $level }}">
                                    <span class="bf-lvl me-1" style="background:{{ $levelColors[$level] }};"></span>{{ $level }} <span class="bf-muted">· {{ $activeByLevel[$level] ?? 0 }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="code_1xbet">Code 1xBet</label>
                        <input class="form-control font-monospace" id="code_1xbet" name="code_1xbet" value="{{ old('code_1xbet') }}" required placeholder="7XK2P">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="code_melbet">Code Melbet <span class="bf-muted fw-normal">(option)</span></label>
                        <input class="form-control font-monospace" id="code_melbet" name="code_melbet" value="{{ old('code_melbet') }}">
                    </div>
                    <div class="col-5">
                        <label class="form-label" for="vipCote">Cote totale</label>
                        <input class="form-control tabular" type="number" step="0.01" min="1" id="vipCote" name="cote_totale" value="{{ old('cote_totale') }}" required inputmode="decimal">
                    </div>
                    <div class="col-7">
                        <label class="form-label" for="valid_until">Valable jusqu’à <span class="bf-muted fw-normal">(option)</span></label>
                        <input class="form-control" type="datetime-local" id="valid_until" name="valid_until" value="{{ old('valid_until') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="vipNote">Note <span class="bf-muted fw-normal">(option)</span></label>
                        <input class="form-control" id="vipNote" name="note" maxlength="500" value="{{ old('note') }}" placeholder="Ex. Mise conseillée : 1 à 3 % de la bankroll">
                    </div>
                    <div class="col-12 d-grid mt-1">
                        <button class="btn bf-btn-gold" type="submit"><i class="bi bi-send-fill"></i> Publier et notifier</button>
                    </div>
                </form>
            </details>

            {{-- Derniers coupons VIP --}}
            <details class="bf-card bf-fold" @if ($toMark > 0) open @endif data-desktop-open>
                <summary class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-trophy"></i></span>
                    <div class="min-w-0">
                        <h2 class="bf-card-title">Derniers coupons VIP</h2>
                        <p class="bf-card-sub keep">
                            30 j : {{ collect($levels)->map(fn ($level) => $level . ' ' . $stats[$level]['won'] . '/' . $stats[$level]['settled'])->implode(' · ') }}
                        </p>
                    </div>
                    <span class="bf-pill {{ $toMark > 0 ? 'warn' : 'muted' }} ms-auto" title="Résultats à noter">{{ $toMark }}</span>
                    <i class="bi bi-chevron-down bf-chev"></i>
                </summary>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($coupons as $coupon)
                        <div class="bf-item flex-wrap" style="border-left:4px solid {{ $levelColors[$coupon->niveau] ?? '#6B7280' }};">
                            <div class="i-main">
                                <div class="i-title">{{ $coupon->niveau }} · cote {{ number_format((float) $coupon->cote_totale, 2, ',', ' ') }} · <span class="font-monospace">{{ $coupon->code_1xbet ?: '—' }}</span></div>
                                <div class="i-sub">publié {{ $coupon->published_at?->timezone($tz)->format('d/m H:i') }}@if ($coupon->valid_until) · jusqu’au {{ $coupon->valid_until->timezone($tz)->format('d/m H:i') }}@endif</div>
                            </div>
                            @if ($coupon->result_status === 'won')
                                <span class="bf-badge success"><i class="bi bi-trophy-fill"></i>Gagné</span>
                            @elseif ($coupon->result_status === 'lost')
                                <span class="bf-badge danger"><i class="bi bi-x-circle-fill"></i>Perdu</span>
                            @else
                                <form method="POST" action="/admin/pronostics/{{ $coupon->id }}/result" class="d-flex gap-1">
                                    @csrf
                                    <button class="btn btn-sm bf-btn-success" type="submit" name="result_status" value="won" title="Notifie tous les utilisateurs"><i class="bi bi-trophy"></i> Gagné</button>
                                    <button class="btn btn-sm bf-btn-danger-soft" type="submit" name="result_status" value="lost"><i class="bi bi-x-lg"></i> Perdu</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="bf-empty-line">Aucun coupon VIP publié.</div>
                    @endforelse
                </div>
            </details>
        </div>
    </div>
@endsection
