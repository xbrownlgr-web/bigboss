<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipFreeClaim;
use App\Models\VipRequest;
use App\Models\VipSubscription;
use App\Services\VipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin > VIP : activation des clés reçues sur WhatsApp, abonnés, et publication des coupons
 * VIP (code 1xBet saisi par l'admin, sans IA).
 */
class VipController extends Controller
{
    private const LEVEL_COLORS = [
        'Bronze' => '#92400E',
        'Silver' => '#9CA3AF',
        'Gold' => '#FFD700',
        'Platinum' => '#7C3AED',
        'Score' => '#BE123C',
    ];

    public function __construct(private readonly VipService $vip)
    {
    }

    public function index(Request $request): View
    {
        $lookup = session('vip_lookup')
            ? VipRequest::query()->with('user')->find(session('vip_lookup'))
            : null;

        $active = VipSubscription::query()->active()->with('user')->orderBy('ends_at')->get();

        return view('admin.vip', [
            'levels' => AppSetting::VIP_LEVELS,
            'levelColors' => self::LEVEL_COLORS,
            'offers' => AppSetting::vipOffers(),
            'lookup' => $lookup,
            'pending' => VipRequest::query()->usable()->with('user')->latest()->limit(50)->get(),
            'freeClaims' => VipFreeClaim::query()->pending()->with('user')->latest()->limit(50)->get(),
            'active' => $active,
            'activeByLevel' => $active->countBy('level'),
            'expiringSoon' => $active->filter(fn ($subscription) => $subscription->ends_at->lessThanOrEqualTo(now()->addDays(3)))->count(),
            'revenueThisMonth' => (int) VipRequest::query()
                ->where('status', VipRequest::STATUS_ACTIVATED)
                ->where('activated_at', '>=', now()->startOfMonth())
                ->sum('price'),
            'activationsThisMonth' => VipRequest::query()
                ->where('status', VipRequest::STATUS_ACTIVATED)
                ->where('activated_at', '>=', now()->startOfMonth())
                ->count(),
            'stats' => $this->vip->stats(),
            'coupons' => Pronostic::query()
                ->whereIn('niveau', AppSetting::VIP_LEVELS)
                ->orderByDesc('published_at')
                ->limit(15)
                ->get(),
            'whatsappReady' => (bool) AppSetting::value('vip_whatsapp_number'),
        ]);
    }

    /**
     * Étape 1 : l'admin colle la clé reçue sur WhatsApp ; on affiche à qui elle appartient.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate(['key' => ['required', 'string', 'max:30']]);
        $vipRequest = $this->vip->findUsableKey($validated['key']);

        if (! $vipRequest) {
            return back()->withInput()->with('error', 'Clé introuvable, déjà utilisée ou expirée (une clé est valable 7 jours). Demandez à l’utilisateur de toucher à nouveau « Accéder » dans l’application.');
        }

        return back()->with('vip_lookup', $vipRequest->id);
    }

    /**
     * Étape 2 : après vérification du paiement, activation pour 30 jours.
     */
    public function activate(Request $request, int $id): RedirectResponse
    {
        $vipRequest = VipRequest::query()->with('user')->findOrFail($id);

        try {
            $subscription = $this->vip->activate($vipRequest, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', sprintf(
            'VIP %s activé pour %s jusqu’au %s. Une notification lui a été envoyée.',
            $subscription->level,
            $subscription->user->name,
            $subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y à H:i'),
        ));
    }

    /**
     * Activation à la main : l'admin choisit le client (fiche client, ou e-mail / téléphone), l'offre
     * (Bronze → Platinum, Score exact) et la durée. Demande du fondateur du 2026-11-11.
     */
    public function grant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client' => ['required_without:user_id', 'nullable', 'string', 'max:120'],
            'level' => ['required', Rule::in(AppSetting::VIP_LEVELS)],
            'days' => ['required', 'integer', 'min:1', 'max:366'],
            'custom_days' => ['nullable', 'integer', 'min:1', 'max:366'],
        ], [
            'client.required_without' => 'Indiquez l’e-mail ou le téléphone du client.',
        ]);

        $user = ! empty($validated['user_id'])
            ? User::query()->find($validated['user_id'])
            : $this->findClient((string) $validated['client']);

        if (! $user instanceof User) {
            return back()->withInput()->with('error', is_string($user) ? $user : 'Client introuvable : vérifiez l’e-mail ou le numéro de téléphone.');
        }

        $days = (int) ($validated['custom_days'] ?? $validated['days']);
        $subscription = $this->vip->grantManual($user->id, $validated['level'], $days);

        return back()->with('success', sprintf(
            '%s activé pour %s (%s), jusqu’au %s. Une notification lui a été envoyée.',
            AppSetting::offerName($subscription->level),
            $user->name,
            VipService::durationLabel($days),
            $subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y à H:i'),
        ));
    }

    /**
     * Client par e-mail (exact) ou par téléphone (les 8 derniers chiffres, quel que soit le format
     * saisi : « +229 01 97… », « 0197… »). Renvoie un message si plusieurs comptes correspondent.
     */
    private function findClient(string $client): User|string|null
    {
        $client = trim($client);

        if (str_contains($client, '@')) {
            return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($client)])->first();
        }

        $digits = preg_replace('/\D+/', '', $client);

        if (strlen($digits) < 8) {
            return 'Numéro trop court : indiquez au moins 8 chiffres, ou l’e-mail du client.';
        }

        // Comparaison chiffre à chiffre en PHP : les numéros sont saisis avec espaces, « + », tirets…
        $tail = substr($digits, -8);
        $matches = User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone'])
            ->filter(fn (User $user) => str_ends_with(preg_replace('/\D+/', '', (string) $user->phone), $tail))
            ->values();
        $matches = $matches->count() === 1 ? User::query()->whereKey($matches->first()->id)->get() : $matches;

        return match ($matches->count()) {
            0 => null,
            1 => $matches->first(),
            default => $matches->count() . ' comptes ont ce numéro : utilisez l’e-mail du client (ou activez depuis sa fiche).',
        };
    }

    public function extend(int $id): RedirectResponse
    {
        $subscription = $this->vip->extend(VipSubscription::query()->findOrFail($id));

        return back()->with('success', "Abonnement prolongé de 30 jours, jusqu’au {$subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y')}.");
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->vip->cancel(VipSubscription::query()->findOrFail($id));

        return back()->with('success', 'Abonnement arrêté.');
    }

    /**
     * Coupon VIP : l'admin fait son analyse, sort son code 1xBet et le publie ici.
     * Seuls les abonnés actifs du niveau voient le code.
     */
    public function storeCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'niveau' => ['required', Rule::in(AppSetting::VIP_LEVELS)],
            'code_1xbet' => ['required', 'string', 'max:255'],
            'code_melbet' => ['nullable', 'string', 'max:255'],
            'cote_totale' => ['required', 'numeric', 'min:1', 'max:999'],
            'valid_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $niveau = $validated['niveau'];
        $cote = round((float) $validated['cote_totale'], 2);
        $validUntil = ! empty($validated['valid_until'])
            ? Carbon::parse($validated['valid_until'], config('app.timezone'))
            : null;

        $pronostic = Pronostic::create([
            'niveau' => $niveau,
            'couleur' => self::LEVEL_COLORS[$niveau],
            'cote_totale' => $cote,
            'probabilite' => null,
            'evenements' => [],
            'code_1xbet' => trim($validated['code_1xbet']),
            'code_melbet' => trim((string) ($validated['code_melbet'] ?? '')) ?: null,
            'match_date' => ($validUntil ?? now())->toDateString(),
            'match_time' => $validUntil ? $validUntil->format('H:i') : '00:00',
            'team_home' => 'Coupon',
            'team_away' => '1xBet',
            'competition' => 'VIP ' . $niveau,
            'prediction' => 'Coupon 1xBet VIP ' . $niveau,
            'odds' => $cote,
            'confidence' => 0,
            'analysis' => trim((string) ($validated['note'] ?? '')),
            'is_vip' => true,
            'published_at' => now(),
            'valid_until' => $validUntil,
        ]);

        $sent = $this->vip->notifyNewCoupon($pronostic);

        return back()->with('success', "Coupon VIP {$niveau} publié. Notification : {$sent['subscribers']} abonné(s) avec le code, {$sent['others']} autre(s) utilisateur(s) sans le code.");
    }

    /**
     * Capture d'écran d'une demande de VIP offert (stockée en base64), servie à l'admin seul.
     */
    public function freeClaimScreenshot(int $id): Response
    {
        $claim = VipFreeClaim::query()->findOrFail($id);
        $binary = base64_decode((string) $claim->screenshot, true);

        abort_if($binary === false, 404);

        return response($binary, 200, [
            'Content-Type' => $claim->screenshot_mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Dépôt vérifié : on offre 30 jours de VIP Silver et on notifie l'utilisateur.
     */
    public function approveFreeClaim(Request $request, int $id): RedirectResponse
    {
        $claim = VipFreeClaim::query()->with('user')->findOrFail($id);

        if (! $claim->isPending()) {
            return back()->with('error', 'Cette demande a déjà été traitée.');
        }

        $subscription = $this->vip->grantFree($claim->user_id, $claim->level);

        $claim->update([
            'status' => VipFreeClaim::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', sprintf(
            'VIP %s offert à %s jusqu’au %s. Une notification lui a été envoyée.',
            $subscription->level,
            $claim->user->name,
            $subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y'),
        ));
    }

    /**
     * Dépôt non valable : on refuse la demande (motif facultatif renvoyé plus tard si besoin).
     */
    public function rejectFreeClaim(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $claim = VipFreeClaim::query()->findOrFail($id);

        if (! $claim->isPending()) {
            return back()->with('error', 'Cette demande a déjà été traitée.');
        }

        $claim->update([
            'status' => VipFreeClaim::STATUS_REJECTED,
            'admin_note' => $validated['note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Demande refusée. L’utilisateur pourra en renvoyer une nouvelle.');
    }
}
