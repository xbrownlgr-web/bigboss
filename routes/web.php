<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\DepositNumberController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\VipController;
use App\Models\AppSetting;
use App\Models\DepositNumber;
use App\Services\AccountDeletionService;
use App\Services\LatestRelease;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'downloadUrl' => LatestRelease::downloadUrl(),
        'version' => LatestRelease::version(),
        // Réglages en direct : la page suit l'admin (opérateurs, prix VIP, réseaux).
        'depositOperators' => collect(DepositNumber::byCountry())->flatten(1)->pluck('nom')->unique()->values()->all(),
        'vipOffers' => AppSetting::vipOffers(),
        'communityLinks' => array_filter(AppSetting::communityLinks()),
    ]);
});

// Pages publiques exigées par le Play Store : confidentialité et suppression de compte.
Route::get('/confidentialite', fn () => view('public.privacy', [
    'contact' => AppSetting::value('support_email') ?: 'support@binhoflash.com',
]));
Route::get('/suppression-compte', fn () => view('public.account-deletion', [
    'contact' => AppSetting::value('support_email') ?: 'support@binhoflash.com',
]));
Route::post('/suppression-compte', function (Request $request, AccountDeletionService $deletion) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::query()->where('email', $credentials['email'])->whereNull('account_deleted_at')->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        return back()->withInput($request->only('email'))->withErrors(['email' => 'E-mail ou mot de passe incorrect.']);
    }

    $deletion->delete($user);

    return back()->with('deleted', true);
})->middleware('throttle:5,1');

Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        return back()
            ->withErrors(['email' => 'Identifiants incorrects'])
            ->onlyInput('email');
    }

    if (! $user->is_active) {
        return back()
            ->withErrors(['email' => 'Compte désactivé. Contactez le support.'])
            ->onlyInput('email');
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect()->intended('/admin');
})->middleware(['guest', 'throttle:6,1']);

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard']);
    Route::get('/transactions', [AdminController::class, 'transactions']);
    Route::post('/transactions/{id}/processing', [AdminController::class, 'markProcessing']);
    Route::post('/transactions/{id}/note', [AdminController::class, 'updateNote']);
    Route::post('/transactions/{id}/approve', [AdminController::class, 'approve']);
    Route::post('/transactions/{id}/reject', [AdminController::class, 'reject']);
    Route::get('/videos', [VideoController::class, 'index']);
    Route::post('/videos/signature', [VideoController::class, 'signature']);
    Route::post('/videos/{slot}', [VideoController::class, 'update'])->whereIn('slot', ['recharge', 'retrait']);
    Route::post('/videos/{slot}/delete', [VideoController::class, 'destroy'])->whereIn('slot', ['recharge', 'retrait']);
    Route::get('/depots', [DepositNumberController::class, 'index']);
    Route::post('/depots', [DepositNumberController::class, 'store']);
    Route::post('/depots/{id}', [DepositNumberController::class, 'update'])->whereNumber('id');
    Route::post('/depots/{id}/toggle', [DepositNumberController::class, 'toggle'])->whereNumber('id');
    Route::post('/depots/{id}/delete', [DepositNumberController::class, 'destroy'])->whereNumber('id');
    Route::get('/settings', [AdminController::class, 'settings']);
    Route::post('/settings', [AdminController::class, 'updateSettings']);
    Route::post('/settings/test-email', [AdminController::class, 'testEmail'])->middleware('throttle:5,1');
    Route::post('/settings/test-whatsapp', [AdminController::class, 'testWhatsApp'])->middleware('throttle:5,1');
    Route::post('/settings/test-assistant', [AdminController::class, 'testAssistant'])->middleware('throttle:5,1');
    Route::get('/messages', [AdminController::class, 'messages']);
    Route::post('/messages/{userId}/reply', [AdminController::class, 'reply']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/users/{userId}', [AdminController::class, 'showUser'])->whereNumber('userId');
    Route::post('/users/{userId}/vip', [AdminController::class, 'toggleVip']);
    Route::post('/users/{userId}/active', [AdminController::class, 'toggleActive']);
    Route::post('/users/{userId}/test-push', [AdminController::class, 'testPush']);
    Route::get('/vip', [VipController::class, 'index']);
    Route::post('/vip/lookup', [VipController::class, 'lookup'])->middleware('throttle:30,1');
    Route::post('/vip/requests/{id}/activate', [VipController::class, 'activate'])->whereNumber('id');
    Route::post('/vip/subscriptions/{id}/extend', [VipController::class, 'extend'])->whereNumber('id');
    Route::post('/vip/subscriptions/{id}/cancel', [VipController::class, 'cancel'])->whereNumber('id');
    Route::post('/vip/coupons', [VipController::class, 'storeCoupon']);
    Route::get('/pronostics', [AdminController::class, 'pronostics']);
    Route::post('/pronostics', [AdminController::class, 'storePronostic']);
    Route::post('/pronostics/generate', [AdminController::class, 'generatePronostics'])->middleware('throttle:10,1');
    Route::post('/pronostics/generate-publish', [AdminController::class, 'generateAndPublishNow'])->middleware('throttle:6,1');
    Route::post('/pronostics/settle', [AdminController::class, 'settleResultsNow'])->middleware('throttle:6,1');
    Route::post('/pronostics/drafts/{index}/dismiss', [AdminController::class, 'dismissDraft'])->whereNumber('index');
    Route::post('/pronostics/{id}/result', [AdminController::class, 'markPronosticResult']);
    Route::post('/pronostics/{id}/delete', [AdminController::class, 'deletePronostic']);
});
