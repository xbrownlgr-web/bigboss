<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\BrevoAutomationService;
use App\Services\ExpoPushService;
use App\Services\SupportInbox;
use App\Services\TransactionDecision;
use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Mode administrateur de l'application (demande du fondateur, 2026-11-15) : le compte admin,
 * connecté dans l'app, répond aux clients (texte, vocal, photo), valide ou refuse les recharges
 * et retraits, et active des abonnements. Mêmes services que le site d'admin.
 */
class AdminAppController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'unread_messages' => Message::query()->where('sender', 'user')->where('is_read', false)->distinct('user_id')->count('user_id'),
            'pending_transactions' => Transaction::query()->whereIn('status', ['pending', 'processing'])->count(),
            'active_subscriptions' => VipSubscription::query()->active()->count(),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $threads = Message::query()
            ->selectRaw("user_id, MAX(id) as last_id, SUM(CASE WHEN sender = 'user' AND is_read = 0 THEN 1 ELSE 0 END) as unread")
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%')))
            ->groupBy('user_id')
            ->orderByDesc('last_id')
            ->limit(100)
            ->get();

        $last = Message::with('user:id,name,phone')->whereIn('id', $threads->pluck('last_id'))->get()->keyBy('id');

        $items = $threads
            ->map(function ($thread) use ($last) {
                $message = $last->get($thread->last_id);

                return $message ? [
                    'user_id' => (int) $thread->user_id,
                    'name' => $message->user?->name ?? 'Utilisateur supprimé',
                    'phone' => $message->user?->phone,
                    'preview' => ($message->sender === 'admin' ? 'Vous : ' : '') . $message->content,
                    'at' => $message->created_at->toIso8601String(),
                    'unread' => (int) $thread->unread,
                ] : null;
            })
            ->filter()
            ->sortByDesc(fn ($item) => [$item['unread'] > 0, $item['at']])
            ->values();

        return response()->json(['success' => true, 'conversations' => $items]);
    }

    public function conversation(int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        $messages = Message::query()
            ->with(['audio:id,message_id', 'image:id,message_id'])
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        Message::query()->where('user_id', $user->id)->where('sender', 'user')->where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'subscriptions' => $user->vipSubscriptions()->active()->get()->map(fn ($subscription) => [
                    'level' => $subscription->level,
                    'name' => AppSetting::offerName($subscription->level),
                    'ends_at' => $subscription->ends_at->toIso8601String(),
                ])->values(),
            ],
            'messages' => $messages->map(fn (Message $message) => $message->toAppArray())->values(),
        ]);
    }

    public function reply(Request $request, int $userId, SupportInbox $inbox): JsonResponse
    {
        $validated = $request->validate(MessageController::mediaRules());
        $user = User::query()->findOrFail($userId);

        [$message, $pushError] = $inbox->reply(
            $user,
            (string) ($validated['content'] ?? ''),
            $request->file('audio'),
            isset($validated['duration']) ? (int) $validated['duration'] : null,
            $request->file('image'),
        );

        return response()->json([
            'success' => true,
            'message' => $message->load(['audio:id,message_id', 'image:id,message_id'])->toAppArray(),
            'push' => $pushError,
        ], 201);
    }

    public function transactions(Request $request): JsonResponse
    {
        $pendingOnly = $request->input('status', 'pending') === 'pending';

        $transactions = Transaction::query()
            ->with('user:id,name,phone')
            ->when($pendingOnly, fn ($query) => $query->whereIn('status', ['pending', 'processing']))
            ->latest('id')
            ->limit($pendingOnly ? 100 : 50)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'platform' => $transaction->platform,
                'player_id' => $transaction->player_id,
                'operator' => $transaction->operator,
                'mobile_money_number' => $transaction->mobile_money_number,
                'reference_code' => $transaction->reference_code,
                'withdrawal_code' => $transaction->withdrawal_code,
                'rejection_reason' => $transaction->rejection_reason,
                'created_at' => $transaction->created_at->toIso8601String(),
                'user' => ['id' => $transaction->user?->id, 'name' => $transaction->user?->name, 'phone' => $transaction->user?->phone],
            ]);

        return response()->json(['success' => true, 'transactions' => $transactions]);
    }

    public function approve(int $id, TransactionDecision $decision): JsonResponse
    {
        try {
            $transaction = $decision->approve(Transaction::with('user')->findOrFail($id));
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'status' => $transaction->status]);
    }

    public function reject(Request $request, int $id, TransactionDecision $decision): JsonResponse
    {
        $validated = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $transaction = $decision->reject(Transaction::with('user')->findOrFail($id), $validated['rejection_reason'] ?? null);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'status' => $transaction->status]);
    }

    public function users(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 80;
        $totalUsers = User::query()->whereNull('account_deleted_at')->count();
        $pushReadyUsers = User::query()
            ->whereNull('account_deleted_at')
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', 'like', 'ExponentPushToken[%')
            ->count();
        $activeVipUsers = VipSubscription::query()->active()->distinct('user_id')->count('user_id');
        $hasMore = false;

        if (mb_strlen($search) >= 2) {
            $users = User::query()
                ->whereNull('account_deleted_at')
                ->where(fn ($query) => $query
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%'))
                ->latest('id')
                ->limit(40)
                ->get(['id', 'name', 'email', 'phone', 'fcm_token', 'created_at']);

            // Numéro : comparaison chiffre à chiffre (saisis avec espaces, « + », tirets…).
            $digits = preg_replace('/\D+/', '', $search);

            if (strlen($digits) >= 4) {
                $byPhone = User::query()
                    ->whereNull('account_deleted_at')
                    ->whereNotNull('phone')
                    ->get(['id', 'name', 'email', 'phone', 'fcm_token', 'created_at'])
                    ->filter(fn (User $user) => str_contains(preg_replace('/\D+/', '', (string) $user->phone), $digits));
                $users = $users->merge($byPhone)->unique('id')->take(40)->values();
            }
        } else {
            $batch = User::query()
                ->whereNull('account_deleted_at')
                ->latest('id')
                ->skip(($page - 1) * $perPage)
                ->take($perPage + 1)
                ->get(['id', 'name', 'email', 'phone', 'fcm_token', 'created_at']);
            $hasMore = $batch->count() > $perPage;
            $users = $batch->take($perPage)->values();
        }

        $vipsByUser = VipSubscription::query()
            ->active()
            ->whereIn('user_id', $users->pluck('id'))
            ->get(['user_id', 'level'])
            ->groupBy('user_id')
            ->map(fn ($group) => $group->pluck('level')->map(fn ($lvl) => AppSetting::offerName($lvl))->values()->all());

        return response()->json([
            'success' => true,
            'total_users' => $totalUsers,
            'push_ready_users' => $pushReadyUsers,
            'active_vip_users' => $activeVipUsers,
            'page' => $page,
            'has_more' => $hasMore,
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'has_push' => str_starts_with((string) $user->fcm_token, 'ExponentPushToken['),
                'created_at' => $user->created_at?->toIso8601String(),
                'active_vips' => $vipsByUser->get($user->id, []),
            ])->values(),
        ]);
    }

    /**
     * Liste de tous les abonnements VIP actifs (qui a activé quel VIP, fin, jours restants).
     */
    public function vipSubscriptions(): JsonResponse
    {
        $items = VipSubscription::query()
            ->active()
            ->with('user:id,name,email,phone')
            ->orderBy('ends_at')
            ->get()
            ->map(fn (VipSubscription $sub) => [
                'id' => $sub->id,
                'user_id' => $sub->user_id,
                'user_name' => $sub->user?->name ?? 'Utilisateur supprimé',
                'user_email' => $sub->user?->email ?? '',
                'user_phone' => $sub->user?->phone,
                'level' => $sub->level,
                'level_name' => AppSetting::offerName($sub->level),
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at->toIso8601String(),
                'days_left' => max(0, (int) now()->diffInDays($sub->ends_at, false)),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'total' => $items->count(),
            'subscriptions' => $items,
        ]);
    }

    public function cancelSubscription(int $id, VipService $vip): JsonResponse
    {
        $subscription = VipSubscription::query()->findOrFail($id);
        $vip->cancel($subscription);

        return response()->json(['success' => true, 'message' => 'Abonnement arrêté.']);
    }

    /**
     * Active un abonnement VIP par user_id OU directement par adresse e-mail du client.
     */
    public function grant(Request $request, VipService $vip): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'nullable', 'email', 'max:255'],
            'level' => ['required', Rule::in(AppSetting::VIP_LEVELS)],
            'days' => ['required', 'integer', 'min:1', 'max:366'],
        ], [
            'email.required_without' => 'Indiquez l’e-mail du client ou sélectionnez un compte.',
            'email.email' => 'Adresse e-mail invalide.',
        ]);

        if (! empty($validated['user_id'])) {
            $user = User::query()->findOrFail((int) $validated['user_id']);
        } else {
            $needle = mb_strtolower(trim((string) $validated['email']));
            $user = User::query()
                ->whereNull('account_deleted_at')
                ->whereRaw('LOWER(email) = ?', [$needle])
                ->first();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun compte trouvé avec l’adresse e-mail : ' . $validated['email'],
                ], 422);
            }
        }

        $subscription = $vip->grantManual($user->id, $validated['level'], (int) $validated['days']);

        return response()->json([
            'success' => true,
            'user_name' => $user->name,
            'message' => AppSetting::offerName($subscription->level) . ' activé pour ' . $user->name . ' jusqu’au '
                . $subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y') . '.',
        ]);
    }

    /**
     * Envoie une notification push (titre + message) à tous les utilisateurs actifs ou aux VIP.
     */
    public function broadcastPush(Request $request, ExpoPushService $push): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'body' => ['required', 'string', 'min:2', 'max:500'],
            'target' => ['nullable', 'in:all,vip'],
        ]);

        $title = trim($validated['title']);
        $body = trim($validated['body']);
        $target = $validated['target'] ?? 'all';
        $data = ['type' => 'announcement'];

        if ($target === 'vip') {
            $vipUserIds = VipSubscription::query()->active()->select('user_id');
            $sent = $push->sendToUsers(
                User::query()->whereNull('account_deleted_at')->where('is_active', true)->whereIn('id', $vipUserIds),
                $title,
                $body,
                $data,
            );
        } else {
            $sent = $push->sendToUsers(
                User::query()->whereNull('account_deleted_at'),
                $title,
                $body,
                $data,
            );
        }

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'message' => $sent > 0
                ? "Notification envoyée à {$sent} appareil" . ($sent > 1 ? 's' : '') . '.'
                : 'Aucun appareil avec notifications activées trouvé pour cette cible.',
        ]);
    }

    /**
     * Envoie une campagne e-mail via Brevo à tous les utilisateurs, aux VIP ou aux non-VIP.
     */
    public function broadcastEmail(Request $request, BrevoAutomationService $brevo): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:2', 'max:140'],
            'body' => ['required', 'string', 'min:4', 'max:3000'],
            'target' => ['nullable', 'in:all,vip,non_vip'],
        ]);

        $result = $brevo->broadcastEmail(
            trim($validated['subject']),
            trim($validated['body']),
            $validated['target'] ?? 'all',
        );

        return response()->json([
            'success' => true,
            'sent' => $result['sent'],
            'skipped' => $result['skipped'],
            'message' => $result['sent'] > 0
                ? "E-mail Brevo envoyé à {$result['sent']} utilisateur" . ($result['sent'] > 1 ? 's' : '') . '.'
                : 'Aucun utilisateur joignable trouvé pour cette cible.',
        ]);
    }

    /**
     * Offres et durées proposées dans le formulaire d'activation de l'app.
     */
    public function vipOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'levels' => collect(AppSetting::VIP_LEVELS)->map(fn ($level) => ['level' => $level, 'name' => AppSetting::offerName($level)])->values(),
            'durations' => collect(VipService::MANUAL_DURATIONS)->map(fn ($label, $days) => ['days' => $days, 'label' => $label])->values(),
        ]);
    }

    /**
     * Tarifs VIP actuels (semaine et mois, en FCFA) pour chaque offre.
     */
    public function vipPrices(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'prices' => $this->formatVipPrices(),
        ]);
    }

    /**
     * Met à jour les prix VIP semaine (7 jours) et mois (30 jours) depuis l'app admin.
     */
    public function updateVipPrices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.level' => ['required', 'string', Rule::in(AppSetting::VIP_LEVELS)],
            'prices.*.price' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'prices.*.price_week' => ['nullable', 'integer', 'min:0', 'max:5000000'],
        ]);

        foreach ($validated['prices'] as $row) {
            $level = $row['level'];
            $offerName = AppSetting::offerName($level);
            $prefix = 'vip_' . strtolower($level);

            if (array_key_exists('price', $row)) {
                $monthVal = ($row['price'] !== null && (int) $row['price'] > 0) ? (string) (int) $row['price'] : '';
                AppSetting::query()->updateOrCreate(
                    ['key' => $prefix . '_price'],
                    [
                        'group' => 'vip',
                        'label' => "Prix de {$offerName} (FCFA)",
                        'type' => 'text',
                        'value' => $monthVal,
                    ],
                );
                cache()->forget('app_setting_' . $prefix . '_price');
            }

            if (array_key_exists('price_week', $row)) {
                $weekVal = ($row['price_week'] !== null && (int) $row['price_week'] > 0) ? (string) (int) $row['price_week'] : '';
                AppSetting::query()->updateOrCreate(
                    ['key' => $prefix . '_price_week'],
                    [
                        'group' => 'vip',
                        'label' => "Prix de {$offerName} à la semaine (FCFA)",
                        'type' => 'text',
                        'value' => $weekVal,
                    ],
                );
                cache()->forget('app_setting_' . $prefix . '_price_week');
            }
        }

        return response()->json([
            'success' => true,
            'prices' => $this->formatVipPrices(),
            'message' => 'Tarifs VIP mis à jour.',
        ]);
    }

    /**
     * @return array<int, array{level: string, name: string, price: ?int, price_week: ?int}>
     */
    private function formatVipPrices(): array
    {
        $offers = AppSetting::vipOffers();

        return collect(AppSetting::VIP_LEVELS)
            ->map(fn (string $level) => [
                'level' => $level,
                'name' => AppSetting::offerName($level),
                'price' => $offers[$level]['price'] ?? null,
                'price_week' => $offers[$level]['price_week'] ?? null,
            ])
            ->values()
            ->all();
    }
}
