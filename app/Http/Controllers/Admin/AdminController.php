<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TransactionApproved;
use App\Mail\TransactionRejected;
use App\Models\AppSetting;
use App\Models\DepositNumber;
use App\Models\Message;
use App\Models\Pronostic;
use App\Models\PronosticRun;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdminNotifier;
use App\Services\AiPronosticGenerator;
use App\Services\ExpoPushService;
use App\Services\FixtureService;
use App\Services\MatchDataService;
use App\Services\OddsService;
use App\Services\SupportAssistant;
use App\Services\VipService;
use App\Services\WhatsAppNotifier;
use App\Support\BrandPalettes;
use App\Support\PronosticRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AdminController extends Controller
{
    private const PRONOSTIC_LEVEL_COLORS = [
        'Starter' => '#6B7280',
        'Bronze' => '#92400E',
        'Silver' => '#9CA3AF',
        'Gold' => '#FFD700',
        'Platinum' => '#7C3AED',
    ];

    public function __construct(private readonly ExpoPushService $pushService)
    {
    }

    public function dashboard(): View
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $todayTransactions = Transaction::query()->where('created_at', '>=', $today);
        $yesterdayTransactions = Transaction::query()
            ->whereBetween('created_at', [$yesterday, $today]);

        $stats = [
            'transactions_today' => (clone $todayTransactions)->count(),
            'transactions_yesterday' => (clone $yesterdayTransactions)->count(),
            'recharges_today' => (clone $todayTransactions)->where('type', 'recharge')->count(),
            'retraits_today' => (clone $todayTransactions)->where('type', 'retrait')->count(),
            'volume_today' => (float) (clone $todayTransactions)->where('status', 'completed')->sum('amount'),
            'volume_yesterday' => (float) (clone $yesterdayTransactions)->where('status', 'completed')->sum('amount'),
            'pending_requests' => Transaction::query()->whereIn('status', ['pending', 'processing'])->count(),
            'pending_amount' => (float) Transaction::query()->whereIn('status', ['pending', 'processing'])->sum('amount'),
            'users_total' => User::query()->count(),
            'users_week' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        $pendingQueue = Transaction::with('user')
            ->whereIn('status', ['pending', 'processing'])
            ->oldest()
            ->limit(6)
            ->get();

        $latestTransactions = Transaction::with('user')
            ->latest()
            ->limit(8)
            ->get();

        $unreadConversations = Message::with('user')
            ->where('sender', 'user')
            ->where('is_read', false)
            ->latest()
            ->limit(50)
            ->get()
            ->unique('user_id')
            ->take(5)
            ->values();

        $settledPronostics = Pronostic::query()->whereIn('result_status', ['won', 'lost']);
        $settledCount = (clone $settledPronostics)->count();
        $pronosticStats = [
            'today' => Pronostic::query()->where('published_at', '>=', $today)->count(),
            'won' => (clone $settledPronostics)->where('result_status', 'won')->count(),
            'settled' => $settledCount,
            'awaiting' => Pronostic::query()->whereNull('result_status')->count(),
            'last' => Pronostic::query()->latest('published_at')->first(),
        ];
        $pronosticStats['win_rate'] = $settledCount > 0
            ? (int) round($pronosticStats['won'] * 100 / $settledCount)
            : null;

        return view('admin.dashboard', [
            'stats' => $stats,
            'chart' => $this->weeklyChart(),
            'pendingQueue' => $pendingQueue,
            'latestTransactions' => $latestTransactions,
            'unreadConversations' => $unreadConversations,
            'pronosticStats' => $pronosticStats,
            'warnings' => $this->configurationWarnings(),
        ]);
    }

    public function transactions(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $transactions = Transaction::with('user')
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->when($request->filled('platform'), function ($query) use ($request) {
                $query->where('platform', $request->input('platform'));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $id = ltrim($search, '#');

                    if (ctype_digit($id)) {
                        $subQuery->where('id', (int) $id);
                    }

                    $subQuery
                        ->orWhere('player_id', 'like', '%' . $search . '%')
                        ->orWhere('reference_code', 'like', '%' . $search . '%')
                        ->orWhere('withdrawal_code', 'like', '%' . $search . '%')
                        ->orWhere('mobile_money_number', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%');
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statusCounts = Transaction::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.transactions', [
            'transactions' => $transactions,
            'statusCounts' => $statusCounts,
            'totalCount' => $statusCounts->sum(),
            'selectedStatus' => $request->input('status', ''),
            'selectedType' => $request->input('type', ''),
            'selectedPlatform' => $request->input('platform', ''),
            'search' => $search,
        ]);
    }

    public function settings(): View
    {
        return view('admin.settings', [
            'groups' => AppSetting::grouped(),
            'palettes' => BrandPalettes::PALETTES,
            'groupLabels' => [
                'brand' => 'Marque & couleurs',
                'app' => 'Mise à jour de l’application',
                'ia' => 'Intelligence artificielle (pronostics)',
                'videos' => 'Vidéos tutoriels',
                'retrait' => 'Retraits 1xBet / Melbet',
                'general' => 'Général',
                'whatsapp' => 'Notifications WhatsApp',
                'community' => 'Communautés',
                'vip' => 'Coupons VIP (Silver, Gold, Platinum)',
                'assistant' => 'George, l’assistant 24h/24 (onglet Support)',
            ],
            'groupDescriptions' => [
                'brand' => 'Nom, logo et couleurs de l’application, appliqués automatiquement dans l’application mobile. Choisissez une palette : elle habille tout l’écran (en-têtes, boutons, cartes). Le logo est facultatif (une adresse d’image) ; sans logo, le nom s’affiche.',
                'assistant' => 'George, l’assistant, répond aux clients à toute heure dans l’onglet Support, avec captures d’écran annotées. Il connaît le fonctionnement de l’application, vos numéros de dépôt, l’adresse de retrait, les prix VIP et les dernières demandes du client. Il utilise la même clé OpenRouter que les pronostics et propose un conseiller humain quand une action est nécessaire.',
                'retrait' => 'Ce que le client doit choisir sur 1xBet / Melbet pour un retrait en espèces : ces infos s’affichent dans les instructions de l’application.',
                'videos' => 'Cloudinary héberge les vidéos importées depuis Admin > Vidéos (compte gratuit sur cloudinary.com).',
                'ia' => 'Les cinq groupes sont générés automatiquement à leurs heures (ci-dessous, heure du Bénin) : matchs réels de tous les championnats (Highlightly), cotes de plusieurs bookmakers (The Odds API), analyse puis rédaction par OpenRouter. Le basket et le handball prennent le relais quand le football ne donne rien. Si aucune sélection n’atteint le seuil de probabilité, rien n’est publié.',
                'app' => 'Contrôle les mises à jour de l’APK : en dessous de la version minimale, l’application bloque l’accès et propose le téléchargement.',
                'general' => 'Adresses utiles pour les e-mails et le support.',
                'whatsapp' => 'Coordonnées utilisées pour prévenir l’administrateur quand une transaction arrive.',
                'community' => 'Liens des canaux visibles dans l’application mobile.',
                'vip' => 'Dans l’application, l’utilisateur voit le prix et les détails, puis « Accéder » ouvre WhatsApp vers ce numéro avec un message pré-écrit contenant sa clé (« … je veux intégrer le VIP Gold qui coûte 5 000 FCFA. Ma clé : BF-GOLD-7K3Q9 »). Après paiement, activez la clé dans Admin > VIP : 30 jours d’accès aux coupons de ce niveau.',
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $settings = AppSetting::query()->get();
        $time = '([01]?\d|2[0-3])[:hH][0-5]\d';
        $rules = $settings
            ->mapWithKeys(function (AppSetting $setting) use ($time) {
                $rule = match (true) {
                    // « 10:00, 15:00, 20:00 » ; vide = groupe désactivé.
                    str_starts_with($setting->key, 'ai_schedule_') => ['nullable', 'string', 'max:100', "regex:/^\s*{$time}(\s*[,;\s]\s*{$time})*\s*$/"],
                    str_starts_with($setting->key, 'ai_min_probability_') => ['nullable', 'integer', 'min:50', 'max:95'],
                    $setting->key === 'ai_vip_teasers_per_day' => ['nullable', 'integer', 'min:0', 'max:12'],
                    // Palette de couleurs : seulement une des clés proposées.
                    $setting->type === 'palette' => ['nullable', 'string', Rule::in(BrandPalettes::keys())],
                    $setting->type === 'email' => ['nullable', 'email', 'max:255'],
                    $setting->type === 'url' => ['nullable', 'url', 'max:500'],
                    $setting->type === 'textarea' => ['nullable', 'string', 'max:10000'],
                    default => ['nullable', 'string', 'max:500'],
                };

                return [$setting->key => $rule];
            })
            ->all();

        $validated = $request->validate($rules, [
            'regex' => 'Heures au format 10:00, séparées par des virgules (vide = groupe désactivé).',
        ]);

        foreach ($settings as $setting) {
            AppSetting::setValue($setting->key, $validated[$setting->key] ?? null);
        }

        return back()->with('success', 'Paramètres mis à jour avec succès.');
    }

    public function markProcessing(int $id): RedirectResponse
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->status !== 'pending') {
            return back()->with('error', 'Seule une demande en attente peut être prise en charge.');
        }

        $transaction->update(['status' => 'processing']);

        return back()->with('success', 'Transaction #' . $transaction->id . ' marquée en cours de traitement.');
    }

    public function updateNote(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        Transaction::findOrFail($id)->update([
            'admin_note' => $validated['admin_note'] ?? null,
        ]);

        return back()->with('success', 'Note interne enregistrée.');
    }

    public function testEmail(AdminNotifier $adminNotifier): RedirectResponse
    {
        $error = $adminNotifier->test();

        return $error === null
            ? back()->with('success', 'E-mail de test envoyé à ' . implode(', ', AdminNotifier::recipients()) . '. Vérifiez aussi les spams.')
            : back()->with('error', 'E-mail non envoyé : ' . $error);
    }

    public function testWhatsApp(WhatsAppNotifier $whatsApp): RedirectResponse
    {
        $error = $whatsApp->send('✅ BIG BOSS : les alertes WhatsApp fonctionnent. Vous serez prévenu de chaque recharge, retrait et message.', ['test' => true]);

        return $error === null
            ? back()->with('success', 'Message WhatsApp envoyé à ' . WhatsAppNotifier::normalizePhone(AppSetting::value('callmebot_phone')) . '. Il arrive en quelques secondes.')
            : back()->with('error', 'WhatsApp non envoyé : ' . $error);
    }

    /**
     * « Tester George » : une vraie question envoyée à l'IA de l'assistant, avec la réponse ou la panne exacte.
     */
    public function testAssistant(Request $request, SupportAssistant $assistant): RedirectResponse
    {
        @set_time_limit(60);
        $result = $assistant->test($request->user());

        return $result['ok']
            ? back()->with('success', 'George répond bien. Exemple : « ' . mb_strimwidth((string) $result['content'], 0, 260, '…') . ' »')
            : back()->with('error', 'George ne peut pas répondre : ' . $result['error']);
    }

    public function approve(int $id): RedirectResponse
    {
        $transaction = Transaction::with('user')->findOrFail($id);

        if (! in_array($transaction->status, ['pending', 'processing'], true)) {
            return back()->with('error', 'Cette transaction a déjà été traitée.');
        }

        $transaction->update([
            'status' => 'completed',
            'processed_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->sendTransactionEmail($transaction->fresh('user'), TransactionApproved::class);
        $typeLabel = $transaction->type === 'recharge' ? 'recharge' : 'retrait';
        $approvalAgreement = $transaction->type === 'recharge' ? 'approuvée' : 'approuvé';
        $this->pushService->sendToUser(
            $transaction->user,
            '✅ Transaction approuvée',
            'Votre ' . $typeLabel . ' de ' . number_format((float) $transaction->amount, 0, ',', ' ') . ' FCFA a été ' . $approvalAgreement . '.',
            [
                'type' => 'transaction',
                'transaction_id' => $transaction->id,
                'status' => 'completed',
            ],
        );

        return back()->with('success', 'Transaction approuvée avec succès.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $transaction = Transaction::with('user')->findOrFail($id);

        if (! in_array($transaction->status, ['pending', 'processing'], true)) {
            return back()->with('error', 'Cette transaction a déjà été traitée.');
        }

        $transaction->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'processed_at' => now(),
        ]);

        $this->sendTransactionEmail($transaction->fresh('user'), TransactionRejected::class);
        $typeLabel = $transaction->type === 'recharge' ? 'recharge' : 'retrait';
        $rejectionAgreement = $transaction->type === 'recharge' ? 'refusée' : 'refusé';
        $this->pushService->sendToUser(
            $transaction->user,
            '❌ Transaction refusée',
            'Votre ' . $typeLabel . ' de ' . number_format((float) $transaction->amount, 0, ',', ' ') . ' FCFA a été ' . $rejectionAgreement . '. Consultez les détails dans l’application.',
            [
                'type' => 'transaction',
                'transaction_id' => $transaction->id,
                'status' => 'rejected',
            ],
        );

        return back()->with('success', 'Transaction refusée avec succès.');
    }

    public function messages(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $threads = Message::query()
            ->selectRaw("user_id, MAX(id) as last_id, SUM(CASE WHEN sender = 'user' AND is_read = 0 THEN 1 ELSE 0 END) as unread")
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->groupBy('user_id')
            ->orderByDesc('last_id')
            ->limit(100)
            ->get();

        $lastMessages = Message::with('user')
            ->whereIn('id', $threads->pluck('last_id'))
            ->get()
            ->keyBy('id');

        $conversations = $threads
            ->map(function ($thread) use ($lastMessages) {
                $last = $lastMessages->get($thread->last_id);

                return $last ? (object) [
                    'user_id' => (int) $thread->user_id,
                    'user' => $last->user,
                    'last' => $last,
                    'unread' => (int) $thread->unread,
                ] : null;
            })
            ->filter()
            ->values();

        $selectedUserId = (int) ($request->integer('user_id') ?: ($conversations->first()?->user_id ?? 0));
        $selectedUser = $selectedUserId ? User::find($selectedUserId) : null;
        $messages = collect();

        if ($selectedUser) {
            $messages = Message::query()
                ->with('transaction')
                ->where('user_id', $selectedUser->id)
                ->orderBy('created_at')
                ->get();

            Message::query()
                ->where('user_id', $selectedUser->id)
                ->where('sender', 'user')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $conversations = $conversations->map(function ($conversation) use ($selectedUser) {
                if ($conversation->user_id === $selectedUser->id) {
                    $conversation->unread = 0;
                }

                return $conversation;
            });
        }

        return view('admin.messages', [
            'conversations' => $conversations,
            'selectedUser' => $selectedUser,
            'messages' => $messages,
            'search' => $search,
        ]);
    }

    public function users(Request $request): View
    {
        $filter = $request->input('filter', '');

        $users = User::query()
            ->withCount('transactions')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            })
            ->when($filter === 'vip', fn ($query) => $query->where('is_vip', true))
            ->when($filter === 'blocked', fn ($query) => $query->where('is_active', false))
            ->when($filter === 'push', fn ($query) => $query->whereNotNull('fcm_token'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'search' => $request->input('search', ''),
            'filter' => $filter,
            'counts' => [
                'all' => User::query()->count(),
                'vip' => User::query()->where('is_vip', true)->count(),
                'blocked' => User::query()->where('is_active', false)->count(),
                'push' => User::query()->whereNotNull('fcm_token')->count(),
            ],
        ]);
    }

    public function showUser(int $userId): View
    {
        $user = User::query()->withCount('transactions')->findOrFail($userId);

        $transactions = $user->transactions()->latest()->paginate(15);
        $totals = $user->transactions()
            ->where('status', 'completed')
            ->selectRaw('type, COUNT(*) as total, SUM(amount) as volume')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return view('admin.user-show', [
            'user' => $user,
            'transactions' => $transactions,
            'totals' => $totals,
            'messagesCount' => Message::query()->where('user_id', $user->id)->count(),
        ]);
    }

    public function toggleVip(int $userId): RedirectResponse
    {
        $user = User::findOrFail($userId);
        $user->update([
            'is_vip' => ! $user->is_vip,
        ]);

        return back()->with('success', 'Statut VIP mis à jour.');
    }

    public function toggleActive(int $userId): RedirectResponse
    {
        $user = User::findOrFail($userId);

        if ($user->email === config('app.admin_email')) {
            return back()->with('success', 'Le compte admin principal ne peut pas être désactivé.');
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return back()->with('success', 'Statut du compte mis à jour.');
    }

    public function testPush(int $userId): RedirectResponse
    {
        $user = User::findOrFail($userId);

        if (! $user->fcm_token) {
            return back()->with('success', 'Aucun token de notification enregistré pour cet utilisateur.');
        }

        $error = $this->pushService->sendToUser(
            $user,
            '🔔 Test BIG BOSS',
            'Les notifications push fonctionnent bien sur votre téléphone.',
            [
                'type' => 'test',
            ],
        );

        return $error === null
            ? back()->with('success', 'Notification de test acceptée par Expo : elle arrive sur le téléphone dans quelques secondes.')
            : back()->with('error', 'Notification refusée : ' . $error);
    }

    public function reply(Request $request, int $userId): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $user = User::findOrFail($userId);

        Message::create([
            'user_id' => $user->id,
            'sender' => 'admin',
            'content' => $validated['content'],
            'is_read' => false,
        ]);

        $this->pushService->sendToUser(
            $user,
            '💬 Nouveau message du support',
            \Illuminate\Support\Str::limit($validated['content'], 120),
            [
                'type' => 'support',
            ],
        );

        return redirect('/admin/messages?user_id=' . $user->id)
            ->with('success', 'Message envoyé avec succès.');
    }

    public function pronostics(Request $request): View
    {
        $pronostics = Pronostic::query()
            ->when($request->filled('niveau'), function ($query) use ($request) {
                $query->where('niveau', $request->input('niveau'));
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $query->whereDate('match_date', $request->input('date'));
            })
            ->latest('published_at')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Pronostic::query()->count(),
            'today' => Pronostic::query()->whereDate('match_date', now()->toDateString())->count(),
            'vip' => Pronostic::query()->where('is_vip', true)->count(),
            'won' => Pronostic::query()->where('result_status', 'won')->count(),
            'settled' => Pronostic::query()->whereIn('result_status', ['won', 'lost'])->count(),
            'awaiting' => Pronostic::query()->whereNull('result_status')->count(),
            'levels' => Pronostic::query()
                ->selectRaw('niveau, COUNT(*) as total')
                ->whereNotNull('niveau')
                ->whereDate('match_date', now()->toDateString())
                ->groupBy('niveau')
                ->pluck('total', 'niveau'),
        ];
        $stats['win_rate'] = $stats['settled'] > 0 ? (int) round($stats['won'] * 100 / $stats['settled']) : null;

        return view('admin.pronostics', [
            'aiDrafts' => session('ai_drafts', []),
            'aiReady' => [
                'openrouter' => (bool) AppSetting::value('openrouter_api_key'),
                'football' => (bool) AppSetting::value('highlightly_api_key'),
                'odds' => (bool) AppSetting::value('odds_api_key'),
                'model' => AppSetting::value('openrouter_model') ?: AiPronosticGenerator::DEFAULT_MODEL,
            ],
            // Créneaux du jour (publié, rien de fiable, erreur, à venir) et budget des cotes.
            'slots' => $this->todaySlots(),
            'oddsUsage' => OddsService::usage(),
            'pronostics' => $pronostics,
            'levels' => array_keys(self::PRONOSTIC_LEVEL_COLORS),
            'levelColors' => self::PRONOSTIC_LEVEL_COLORS,
            'selectedLevel' => $request->input('niveau', ''),
            'selectedDate' => $request->input('date', ''),
            'stats' => $stats,
        ]);
    }

    public function storePronostic(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'niveau' => ['required', 'string', 'in:' . implode(',', array_keys(self::PRONOSTIC_LEVEL_COLORS))],
            'match_date' => ['required', 'date'],
            'cote_totale' => ['required', 'numeric', 'min:1'],
            'probabilite' => ['required', 'integer', 'min:0', 'max:100'],
            'code_1xbet' => ['nullable', 'string', 'max:255'],
            'code_melbet' => ['nullable', 'string', 'max:255'],
            'evenements_text' => ['required', 'string'],
            'analysis' => ['nullable', 'string', 'max:4000'],
            'is_vip' => ['nullable', 'boolean'],
            'draft' => ['nullable', 'integer', 'min:0'],
        ]);

        $events = $this->parsePronosticEvents($validated['evenements_text']);

        if (isset($validated['draft'])) {
            $events = $this->withDraftMetadata($events, session('ai_drafts', [])[(int) $validated['draft']]['evenements'] ?? []);
        }

        if ($events === []) {
            return back()
                ->withErrors(['evenements_text' => 'Ajoutez au moins un événement au format : Match | Pronostic | Cote'])
                ->withInput();
        }

        [$teamHome, $teamAway] = $this->splitMatchName($events[0]['match']);
        $niveau = $validated['niveau'];
        $eventCount = count($events);

        $pronostic = Pronostic::create([
            'niveau' => $niveau,
            'couleur' => self::PRONOSTIC_LEVEL_COLORS[$niveau],
            'cote_totale' => round((float) $validated['cote_totale'], 2),
            'probabilite' => (int) $validated['probabilite'],
            'evenements' => $events,
            // Uniquement de vrais codes : un code inventé ne fonctionnerait pas sur 1xBet ou Melbet.
            'code_1xbet' => ($validated['code_1xbet'] ?? null) ?: null,
            'code_melbet' => ($validated['code_melbet'] ?? null) ?: null,
            'match_date' => $validated['match_date'],
            'match_time' => '00:00',
            'team_home' => $teamHome,
            'team_away' => $teamAway,
            // Même règle que la génération automatique : un coupon Silver / Gold / Platinum est VIP.
            'competition' => (in_array($niveau, AppSetting::VIP_LEVELS, true) ? 'VIP ' : 'Combiné ') . $niveau,
            'prediction' => 'Combiné ' . $eventCount . ' événement' . ($eventCount > 1 ? 's' : ''),
            'odds' => round((float) $validated['cote_totale'], 2),
            'confidence' => (int) $validated['probabilite'],
            'analysis' => ($validated['analysis'] ?? null) ?: 'Pronostic ajouté manuellement depuis le panel d’administration BIG BOSS.',
            'is_vip' => (bool) ($validated['is_vip'] ?? in_array($niveau, AppSetting::VIP_LEVELS, true)),
            'published_at' => now(),
        ]);

        $this->sendPronosticPublishedNotification($pronostic);

        if (isset($validated['draft'])) {
            $this->forgetDraft((int) $validated['draft']);
        }

        return back()->with('success', 'Pronostic publié avec succès.');
    }

    public function generatePronostics(
        Request $request,
        FixtureService $fixtures,
        AiPronosticGenerator $generator,
        OddsService $odds,
        MatchDataService $matchData,
    ): RedirectResponse {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'source' => ['required', 'in:auto,manual'],
            'manual_matches' => ['nullable', 'string', 'max:5000'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', 'in:' . implode(',', AiPronosticGenerator::AI_LEVELS)],
        ]);

        $date = Carbon::parse($validated['date']);

        try {
            $matches = $validated['source'] === 'auto'
                ? $fixtures->forDate($date)
                : $fixtures->parseManual($validated['manual_matches'] ?? '');

            if (count($matches) < 2) {
                throw new RuntimeException($validated['source'] === 'auto'
                    ? 'Pas assez de matchs dans les grands championnats ce jour-là.'
                    : 'Ajoutez au moins 2 matchs, un par ligne : Équipe A vs Équipe B | Compétition | Heure.');
            }

            // Cotes réelles puis collecte des données (classement, forme, H2H) avant l'analyse.
            // Les matchs collés à la main n'ont ni identifiants ni cotes : aucune valeur ne peut être vérifiée.
            if ($validated['source'] === 'auto') {
                $matches = $matchData->enrich($odds->attach($matches));
            } else {
                $matches = [];
            }

            $drafts = $generator->generate($matches, $validated['levels'] ?? AiPronosticGenerator::AI_LEVELS);
        } catch (RuntimeException $exception) {
            // Y compris NoValueBetException : « Aucun pari recommandé » avec la raison.
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $drafts = array_map(fn ($draft) => [...$draft, 'match_date' => $date->toDateString()], $drafts);
        session(['ai_drafts' => $drafts]);

        return back()->with('success', count($drafts) . ' combiné(s) proposé(s) par l’IA à partir de ' . count($matches) . ' matchs. Vérifiez les cotes, ajoutez les codes coupon puis publiez.');
    }

    /**
     * Créneaux du jour (Admin > Paramètres) et ce que chacun a donné ; les générations lancées
     * à la main depuis cette page apparaissent aussi.
     *
     * @return array<int, array{level: string, time: string, status: string, message: ?string, pronostic_id: ?int, manual: bool}>
     */
    private function todaySlots(): array
    {
        $timezone = PronosticRules::TIMEZONE;
        $now = now()->setTimezone($timezone);
        $runs = PronosticRun::query()
            ->where('run_date', $now->toDateString())
            ->get()
            ->keyBy(fn (PronosticRun $run) => $run->level . '|' . $run->slot);
        $rows = [];

        foreach (PronosticRules::schedule() as $level => $times) {
            foreach ($times as $time) {
                $run = $runs->pull($level . '|' . $time);
                $due = Carbon::parse($now->toDateString() . ' ' . $time, $timezone);

                $rows[] = [
                    'level' => $level,
                    'time' => $time,
                    'status' => $run?->status ?? match (true) {
                        $due->isFuture() => 'upcoming',
                        $due->diffInMinutes($now) <= 20 => 'due',
                        default => 'missed',
                    },
                    'message' => $run?->message,
                    'pronostic_id' => $run?->pronostic_id,
                    'manual' => false,
                ];
            }
        }

        foreach ($runs as $run) {
            $rows[] = ['level' => $run->level, 'time' => $run->slot, 'status' => $run->status, 'message' => $run->message, 'pronostic_id' => $run->pronostic_id, 'manual' => true];
        }

        return collect($rows)->sortBy('time')->values()->all();
    }

    /**
     * Lance maintenant la génération automatique (matchs réels + IA → publication directe →
     * notification) pour un groupe ou pour tous, et affiche le résultat ou l'erreur.
     * Sert aussi à vérifier que tout est bien configuré.
     */
    public function generateAndPublishNow(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'level' => ['nullable', 'string', 'in:' . implode(',', PronosticRules::LEVELS)],
        ]);

        // La chaîne complète (Highlightly + OpenRouter + Expo) peut dépasser les 60 s par défaut.
        @set_time_limit(180);

        $options = [];
        if (! empty($validated['date'])) {
            $options['--date'] = Carbon::parse($validated['date'])->toDateString();
        }

        if (! empty($validated['level'])) {
            $options['--level'] = [$validated['level']];
        }

        $exitCode = Artisan::call('binho:generate-pronostics', $options);
        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            return back()->with('success', $output ?: 'Génération et publication terminées.');
        }

        return back()->with('error', 'Génération automatique en échec : ' . ($output ?: 'raison inconnue, voir les logs du serveur.'));
    }

    /**
     * Lance maintenant la vérification des résultats (comme toutes les heures) :
     * valide les combinés terminés et notifie les coupons gagnants.
     */
    public function settleResultsNow(): RedirectResponse
    {
        @set_time_limit(120);

        $exitCode = Artisan::call('binho:settle-pronostics');
        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            return back()->with('success', $output ?: 'Vérification des résultats terminée.');
        }

        return back()->with('error', 'Vérification des résultats en échec : ' . ($output ?: 'raison inconnue, voir les logs du serveur.'));
    }

    public function dismissDraft(int $index): RedirectResponse
    {
        $this->forgetDraft($index);

        return back()->with('success', 'Proposition ignorée.');
    }

    private function forgetDraft(int $index): void
    {
        $drafts = session('ai_drafts', []);
        unset($drafts[$index]);
        session(['ai_drafts' => $drafts]);
    }

    public function markPronosticResult(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'result_status' => ['required', 'in:won,lost'],
            'result_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $pronostic = Pronostic::findOrFail($id);
        $pronostic->update([
            'result_status' => $validated['result_status'],
            'result_note' => $validated['result_note'] ?? null,
            'result_checked_at' => now(),
        ]);

        if ($pronostic->result_status === 'won' && ! $pronostic->result_notified_at) {
            $sent = $this->sendPronosticWonNotification($pronostic);
            $pronostic->update(['result_notified_at' => now()]);

            return back()->with('success', "Pronostic marqué gagnant. Notification envoyée à {$sent} utilisateur(s).");
        }

        return back()->with('success', 'Pronostic marqué perdant. Aucune notification envoyée.');
    }

    public function deletePronostic(int $id): RedirectResponse
    {
        Pronostic::findOrFail($id)->delete();

        return back()->with('success', 'Pronostic supprimé avec succès.');
    }

    /**
     * Volume par jour et par type sur 7 jours (hors transactions refusées).
     *
     * @return array<string, array<int, mixed>>
     */
    private function weeklyChart(): array
    {
        $from = now()->subDays(6)->startOfDay();

        $rows = Transaction::query()
            ->where('created_at', '>=', $from)
            ->where('status', '!=', 'rejected')
            ->selectRaw('DATE(created_at) as day, type, COUNT(*) as total, SUM(amount) as volume')
            ->groupBy('day', 'type')
            ->get();

        $chart = [
            'labels' => [],
            'recharge' => [],
            'retrait' => [],
            'rechargeCount' => [],
            'retraitCount' => [],
        ];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $from->copy()->addDays($offset);
            $key = $date->toDateString();
            $chart['labels'][] = $offset === 6 ? 'Aujourd’hui' : ucfirst($date->locale('fr')->isoFormat('ddd D'));

            foreach (['recharge', 'retrait'] as $type) {
                $row = $rows->first(fn ($item) => (string) $item->day === $key && $item->type === $type);
                $chart[$type][] = $row ? (float) $row->volume : 0.0;
                $chart[$type . 'Count'][] = $row ? (int) $row->total : 0;
            }
        }

        return $chart;
    }

    /**
     * Points de configuration à corriger avant d'accueillir de vrais utilisateurs.
     *
     * @return array<int, array{level: string, message: string, link: string}>
     */
    private function configurationWarnings(): array
    {
        $warnings = [];

        if (AdminNotifier::recipients() === []) {
            $warnings[] = [
                'level' => 'warning',
                'message' => 'Vous ne recevez aucun e-mail pour les nouvelles demandes : renseignez votre vraie adresse dans « E-mail administrateur ».',
                'link' => '/admin/settings#general',
            ];
        }

        if (DepositNumber::byCountry() === []) {
            $warnings[] = [
                'level' => 'danger',
                'message' => 'Aucun numéro de dépôt Mobile Money : les utilisateurs ne peuvent pas recharger. Renseignez au moins un numéro et le nom affiché.',
                'link' => '/admin/depots',
            ];
        }

        if (! WhatsAppNotifier::configured()) {
            $warnings[] = [
                'level' => 'warning',
                'message' => 'Alertes WhatsApp désactivées : numéro ou clé CallMeBot manquant.',
                'link' => '/admin/settings',
            ];
        }

        if (str_ends_with((string) config('mail.from.address'), '@resend.dev')) {
            $warnings[] = [
                'level' => 'warning',
                'message' => 'Expéditeur e-mail de test (onboarding@resend.dev) : Resend ne livre qu’à votre propre adresse. Vérifiez un domaine pour que les utilisateurs reçoivent leurs e-mails.',
                'link' => '',
            ];
        }

        if (config('app.debug') && app()->environment('production')) {
            $warnings[] = [
                'level' => 'danger',
                'message' => 'APP_DEBUG est activé en production : les erreurs exposent des informations sensibles.',
                'link' => '',
            ];
        }

        return $warnings;
    }

    /**
     * @param class-string<\Illuminate\Mail\Mailable> $mailableClass
     */
    private function sendTransactionEmail(Transaction $transaction, string $mailableClass): void
    {
        try {
            Mail::to($transaction->user)->send(new $mailableClass($transaction));
        } catch (Throwable $exception) {
            Log::warning('E-mail de transaction admin non envoyé.', [
                'transaction_id' => $transaction->id,
                'mailable' => $mailableClass,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendPronosticPublishedNotification(Pronostic $pronostic): int
    {
        // Coupon VIP publié depuis cette page : le code ne part qu'aux abonnés du niveau.
        if (VipService::isVipLevel($pronostic->niveau)) {
            return array_sum(app(VipService::class)->notifyNewCoupon($pronostic));
        }

        $level = $pronostic->niveau ?: 'Starter';
        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2);

        return $this->pushService->sendToActiveUsers(
            '🔥 Nouveau pronostic disponible',
            "⚽ {$level} - cote {$odd} disponible maintenant sur BIG BOSS.",
            [
                'type' => 'pronostic',
                'event' => 'published',
                'pronostic_id' => $pronostic->id,
                'niveau' => $level,
            ],
        );
    }

    private function sendPronosticWonNotification(Pronostic $pronostic): int
    {
        $level = $pronostic->niveau ?: 'Pronostic';
        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2);

        return $this->pushService->sendToActiveUsers(
            '✅ Pronostic gagnant',
            "🏆 {$level} - cote {$odd} validée. Ouvrez l’application pour voir les prochains coupons.",
            [
                'type' => 'pronostic',
                'event' => 'won',
                'pronostic_id' => $pronostic->id,
                'niveau' => $level,
            ],
        );
    }

    /**
     * @return array<int, array{match: string, pronostic: string, cote: float}>
     */
    /**
     * Proposition de l'IA publiée depuis l'admin : on garde, pour chaque match, l'identifiant du
     * match et le marché (validation automatique du résultat) et l'analyse chiffrée. Si l'admin a
     * changé le pari, le marché n'est plus sûr : il n'est pas gardé, le résultat se saisit à la main.
     *
     * @param array<int, array<string, mixed>> $events
     * @param array<int, array<string, mixed>> $draftEvents
     * @return array<int, array<string, mixed>>
     */
    private function withDraftMetadata(array $events, array $draftEvents): array
    {
        $byMatch = collect($draftEvents)->keyBy(fn ($event) => mb_strtolower(trim((string) ($event['match'] ?? ''))));

        return array_map(function (array $event) use ($byMatch) {
            $draft = $byMatch->get(mb_strtolower(trim($event['match'])));

            if (! $draft) {
                return $event;
            }

            $merged = [...$draft, ...$event];

            if (mb_strtolower(trim((string) ($draft['pronostic'] ?? ''))) !== mb_strtolower(trim($event['pronostic']))) {
                unset($merged['selection_key'], $merged['line']);
            }

            return $merged;
        }, $events);
    }

    private function parsePronosticEvents(string $value): array
    {
        $events = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));

            if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $odd = (float) str_replace(',', '.', $parts[2]);

            if ($odd <= 0) {
                continue;
            }

            $events[] = [
                'match' => $parts[0],
                'pronostic' => $parts[1],
                'cote' => $odd,
            ];
        }

        return $events;
    }

    private function splitMatchName(string $match): array
    {
        $parts = preg_split('/\s+vs\s+/i', $match);

        if (count($parts) >= 2) {
            return [trim($parts[0]), trim($parts[1])];
        }

        return [$match, 'Adversaire'];
    }
}
