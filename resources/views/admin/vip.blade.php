@extends('admin.layout')

@section('title', 'VIP')
@section('subtitle', 'Clés reçues sur WhatsApp, abonnés 30 jours et coupons 1xBet réservés aux abonnés')

@php
    $tz = config('app.timezone');
@endphp

@section('content')
    @unless ($whatsappReady)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <div>Aucun numéro WhatsApp VIP : le bouton « Accéder » de l’application ne peut pas encore vous écrire. <a href="/admin/settings#vip">Le renseigner dans Paramètres</a>.</div>
        </div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Abonnés actifs</span><span class="bf-kpi-icon"><i class="bi bi-gem"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $active->count() }}</div>
                <div class="bf-kpi-foot">
                    @foreach ($levels as $level)
                        <span class="bf-badge neutral"><span class="dot" style="color: {{ $levelColors[$level] }};"></span>{{ $level }} {{ $activeByLevel[$level] ?? 0 }}</span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi {{ $pending->isNotEmpty() ? 'is-alert' : '' }}">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Demandes en attente</span><span class="bf-kpi-icon"><i class="bi bi-key"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $pending->count() }}</div>
                <div class="bf-kpi-foot">clés valables 7 jours</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Encaissé ce mois</span><span class="bf-kpi-icon"><i class="bi bi-cash-stack"></i></span></div>
                <div class="bf-kpi-value tabular">{{ number_format($revenueThisMonth, 0, ',', ' ') }} F</div>
                <div class="bf-kpi-foot">{{ $activationsThisMonth }} activation(s) depuis le 1er</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bf-card bf-kpi {{ $expiringSoon > 0 ? 'is-alert' : '' }}">
                <div class="bf-kpi-top"><span class="bf-kpi-label">Expirent sous 3 jours</span><span class="bf-kpi-icon"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="bf-kpi-value tabular">{{ $expiringSoon }}</div>
                <div class="bf-kpi-foot">un rappel leur est envoyé automatiquement</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5 vstack gap-3">
            {{-- Activation par clé --}}
            <section class="bf-card">
                <div class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-key"></i></span>
                    <div>
                        <h2 class="bf-card-title">Activer une clé</h2>
                        <p class="bf-card-sub">Collez la clé reçue sur WhatsApp, vérifiez le compte, puis activez après paiement.</p>
                    </div>
                </div>
                <div class="bf-card-body">
                    <form method="POST" action="/admin/vip/lookup" class="d-flex gap-2">
                        @csrf
                        <input class="form-control font-monospace text-uppercase" name="key" value="{{ old('key') }}" placeholder="BF-GOLD-7K3Q9" required aria-label="Clé VIP">
                        <button class="btn bf-btn-primary" type="submit"><i class="bi bi-search"></i> Vérifier</button>
                    </form>

                    @if ($lookup)
                        <div class="mt-3 p-3 rounded-3" style="background:var(--bf-surface-2);border-left:4px solid {{ $levelColors[$lookup->level] ?? '#6B7280' }};">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $lookup->user->name }}</div>
                                    <div class="bf-small bf-muted">{{ $lookup->user->phone ?: 'téléphone non renseigné' }} · {{ $lookup->user->email }}</div>
                                </div>
                                <span class="bf-badge" style="background: {{ $levelColors[$lookup->level] ?? '#6B7280' }}26; color:#111A2E;">VIP {{ $lookup->level }}</span>
                            </div>
                            <div class="bf-small mt-2">
                                Clé <strong class="font-monospace">{{ $lookup->key }}</strong> · prix demandé <strong>{{ $lookup->price ? number_format($lookup->price, 0, ',', ' ') . ' FCFA' : 'non défini' }}</strong> · créée le {{ $lookup->created_at->timezone($tz)->format('d/m à H:i') }}
                            </div>
                            <form method="POST" action="/admin/vip/requests/{{ $lookup->id }}/activate" class="mt-3" data-confirm="Paiement reçu ? Activer le VIP {{ $lookup->level }} de {{ $lookup->user->name }} pour 30 jours ?">
                                @csrf
                                <button class="btn bf-btn-gold w-100" type="submit"><i class="bi bi-check2-circle"></i> Paiement reçu : activer 30 jours</button>
                            </form>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Demandes en attente --}}
            <section class="bf-card">
                <div class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-inbox"></i></span>
                    <div>
                        <h2 class="bf-card-title">Demandes en attente</h2>
                        <p class="bf-card-sub">Utilisateurs qui ont touché « Accéder » : activez dès que le paiement est reçu.</p>
                    </div>
                </div>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($pending as $vipRequest)
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="border:1px solid var(--bf-border);">
                            <span class="dot" style="color: {{ $levelColors[$vipRequest->level] ?? '#6B7280' }};"></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $vipRequest->user->name }} · VIP {{ $vipRequest->level }}</div>
                                <div class="bf-small bf-muted"><span class="font-monospace">{{ $vipRequest->key }}</span> · {{ $vipRequest->user->phone ?: $vipRequest->user->email }} · {{ $vipRequest->created_at->timezone($tz)->format('d/m H:i') }}</div>
                            </div>
                            <form method="POST" action="/admin/vip/requests/{{ $vipRequest->id }}/activate" data-confirm="Paiement reçu ? Activer le VIP {{ $vipRequest->level }} de {{ $vipRequest->user->name }} pour 30 jours ?">
                                @csrf
                                <button class="btn btn-sm bf-btn-gold" type="submit">Activer</button>
                            </form>
                        </div>
                    @empty
                        <div class="bf-empty py-3"><i class="bi bi-inbox"></i>Aucune demande en attente</div>
                    @endforelse
                </div>
            </section>

            {{-- Demandes de Silver OFFERT (parrainage 1xBet) --}}
            <section class="bf-card">
                <div class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-gift"></i></span>
                    <div>
                        <h2 class="bf-card-title">Silver offert — demandes à vérifier @if($freeClaims->isNotEmpty())<span class="badge bg-success ms-1">{{ $freeClaims->count() }}</span>@endif</h2>
                        <p class="bf-card-sub">Inscription 1xBet avec code promo + dépôt. Vérifiez la capture, puis offrez 30 jours de Silver.</p>
                    </div>
                </div>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($freeClaims as $claim)
                        <div class="d-flex align-items-start gap-3 p-2 rounded-3" style="border:1px solid var(--bf-border);">
                            <a href="/admin/vip/free-claims/{{ $claim->id }}/screenshot" target="_blank" rel="noopener" title="Voir la capture en grand">
                                <img src="/admin/vip/free-claims/{{ $claim->id }}/screenshot" alt="Capture dépôt" style="height:72px;width:72px;object-fit:cover;border-radius:10px;border:1px solid var(--bf-border);background:#f1f5f9;">
                            </a>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $claim->user->name }}</div>
                                <div class="bf-small bf-muted">ID 1xBet : <span class="font-monospace fw-semibold">{{ $claim->account_id }}</span></div>
                                <div class="bf-small bf-muted">Code : <span class="font-monospace">{{ $claim->promo_code }}</span> · {{ $claim->user->phone ?: $claim->user->email }} · {{ $claim->created_at->timezone($tz)->format('d/m H:i') }}</div>
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <form method="POST" action="/admin/vip/free-claims/{{ $claim->id }}/approve" data-confirm="Dépôt vérifié ? Offrir 30 jours de VIP Silver à {{ $claim->user->name }} ?">
                                    @csrf
                                    <button class="btn btn-sm bf-btn-gold w-100" type="submit"><i class="bi bi-check-lg"></i> Offrir</button>
                                </form>
                                <form method="POST" action="/admin/vip/free-claims/{{ $claim->id }}/reject" data-confirm="Refuser cette demande ?">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger w-100" type="submit">Refuser</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="bf-empty py-3"><i class="bi bi-gift"></i>Aucune demande de Silver offert</div>
                    @endforelse
                </div>
            </section>

            {{-- Abonnés actifs --}}
            <section class="bf-card">
                <div class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon"><i class="bi bi-people"></i></span>
                    <div>
                        <h2 class="bf-card-title">Abonnés actifs</h2>
                        <p class="bf-card-sub">Du plus proche de la fin au plus lointain. « +30 j » s’ajoute à la date de fin.</p>
                    </div>
                </div>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($active as $subscription)
                        @php $soon = $subscription->ends_at->lessThanOrEqualTo(now()->addDays(3)); @endphp
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="border:1px solid {{ $soon ? '#F3D27A' : 'var(--bf-border)' }};">
                            <span class="dot" style="color: {{ $levelColors[$subscription->level] ?? '#6B7280' }};"></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $subscription->user->name }} · VIP {{ $subscription->level }}</div>
                                <div class="bf-small {{ $soon ? 'text-warning-emphasis' : 'bf-muted' }}">jusqu’au {{ $subscription->ends_at->timezone($tz)->format('d/m/Y H:i') }} · {{ $subscription->user->phone ?: $subscription->user->email }}</div>
                            </div>
                            <form method="POST" action="/admin/vip/subscriptions/{{ $subscription->id }}/extend" data-confirm="Ajouter 30 jours au VIP {{ $subscription->level }} de {{ $subscription->user->name }} ?">
                                @csrf
                                <button class="btn btn-sm bf-btn-light" type="submit" title="Prolonger de 30 jours">+30 j</button>
                            </form>
                            <form method="POST" action="/admin/vip/subscriptions/{{ $subscription->id }}/cancel" data-confirm="Arrêter maintenant le VIP {{ $subscription->level }} de {{ $subscription->user->name }} ?">
                                @csrf
                                <button class="btn btn-sm btn-link text-danger p-0" type="submit" title="Arrêter l’abonnement"><i class="bi bi-x-circle"></i></button>
                            </form>
                        </div>
                    @empty
                        <div class="bf-empty py-3"><i class="bi bi-people"></i>Aucun abonné actif</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-7 vstack gap-3">
            {{-- Publication d'un coupon VIP --}}
            <section class="bf-card">
                <div class="bf-card-header justify-content-start">
                    <span class="bf-kpi-icon" style="background:#FFF7D6;color:#B45309;"><i class="bi bi-send"></i></span>
                    <div>
                        <h2 class="bf-card-title">Publier un coupon VIP</h2>
                        <p class="bf-card-sub">Vos abonnés du niveau reçoivent le code ; les autres une notification sans le code.</p>
                    </div>
                </div>
                <form method="POST" action="/admin/vip/coupons" class="bf-card-body row g-3" data-confirm="Publier ce coupon VIP et notifier les utilisateurs ?">
                    @csrf
                    <div class="col-12">
                        <span class="form-label d-block">Niveau</span>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($levels as $level)
                                <input type="radio" class="btn-check" name="niveau" value="{{ $level }}" id="vipLevel{{ $level }}" @checked(old('niveau', 'Gold') === $level) required>
                                <label class="btn btn-sm bf-btn-light" for="vipLevel{{ $level }}">
                                    <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $levelColors[$level] }};"></span>{{ $level }}
                                    <span class="bf-muted">· {{ $activeByLevel[$level] ?? 0 }} abonné(s)</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="code_1xbet">Code coupon 1xBet</label>
                        <input class="form-control font-monospace" id="code_1xbet" name="code_1xbet" value="{{ old('code_1xbet') }}" required placeholder="Ex. 7XK2P">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="code_melbet">Code Melbet <span class="bf-muted fw-normal">(facultatif)</span></label>
                        <input class="form-control font-monospace" id="code_melbet" name="code_melbet" value="{{ old('code_melbet') }}">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label" for="vipCote">Cote totale</label>
                        <input class="form-control tabular" type="number" step="0.01" min="1" id="vipCote" name="cote_totale" value="{{ old('cote_totale') }}" required>
                    </div>
                    <div class="col-6 col-md-8">
                        <label class="form-label" for="valid_until">Valable jusqu’à <span class="bf-muted fw-normal">(début du 1er match, facultatif)</span></label>
                        <input class="form-control" type="datetime-local" id="valid_until" name="valid_until" value="{{ old('valid_until') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="vipNote">Note pour les abonnés <span class="bf-muted fw-normal">(facultatif)</span></label>
                        <input class="form-control" id="vipNote" name="note" maxlength="500" value="{{ old('note') }}" placeholder="Ex. Mise conseillée : 1 à 3 % de votre bankroll">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn bf-btn-gold" type="submit"><i class="bi bi-send-fill"></i> Publier et notifier</button>
                    </div>
                </form>
            </section>

            {{-- Derniers coupons VIP --}}
            <section class="bf-card">
                <div class="bf-card-header">
                    <div>
                        <h2 class="bf-card-title">Derniers coupons VIP</h2>
                        <p class="bf-card-sub">
                            Gagnés sur 30 jours :
                            {{ collect($levels)->map(fn ($level) => $level . ' ' . $stats[$level]['won'] . '/' . $stats[$level]['settled'])->implode(' · ') }}
                            · affiché dans l’application
                        </p>
                    </div>
                </div>
                <div class="bf-card-body vstack gap-2">
                    @forelse ($coupons as $coupon)
                        <div class="d-flex flex-wrap align-items-center gap-2 p-2 rounded-3" style="border:1px solid var(--bf-border);border-left:4px solid {{ $levelColors[$coupon->niveau] ?? '#6B7280' }};">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold">VIP {{ $coupon->niveau }} · cote {{ number_format((float) $coupon->cote_totale, 2, ',', ' ') }} · <span class="font-monospace">{{ $coupon->code_1xbet ?: '—' }}</span></div>
                                <div class="bf-small bf-muted">
                                    publié {{ $coupon->published_at?->timezone($tz)->format('d/m H:i') }}
                                    @if ($coupon->valid_until) · valable jusqu’au {{ $coupon->valid_until->timezone($tz)->format('d/m H:i') }} @endif
                                </div>
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
                        <div class="bf-empty py-3"><i class="bi bi-gem"></i>Aucun coupon VIP publié</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
