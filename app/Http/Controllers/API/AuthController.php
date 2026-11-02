<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode;
use App\Mail\WelcomeUser;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'country' => ['required', Rule::in(array_keys(User::COUNTRIES))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Case « J'ai 18 ans ou plus » de l'application. Facultative côté serveur tant que
            // d'anciennes versions de l'application (sans la case) sont encore utilisées.
            'adult_confirmed' => ['sometimes', 'accepted'],
        ], [
            'adult_confirmed.accepted' => 'Vous devez avoir 18 ans ou plus pour utiliser BIG BOSS.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'country' => $validated['country'],
            'password' => Hash::make($validated['password']),
        ]);

        if (! empty($validated['adult_confirmed'])) {
            $user->forceFill(['adult_confirmed_at' => now()])->save();
        }

        try {
            Mail::to($user)->send(new WelcomeUser($user));
        } catch (Throwable $exception) {
            Log::warning('E-mail WelcomeUser non envoyé.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Suppression du compte depuis l'application (exigée par le Play Store) : le mot de passe
     * est redemandé pour éviter une suppression par erreur ou depuis un téléphone prêté.
     */
    public function deleteAccount(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe incorrect.',
            ], 422);
        }

        $deletion->delete($user);

        return response()->json(['success' => true, 'message' => 'Votre compte a été supprimé.']);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects',
            ], 401);
        }

        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé. Contactez le support.',
            ], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($code),
                    'created_at' => now(),
                ],
            );

            try {
                Mail::to($user)->send(new PasswordResetCode($user, $code));
            } catch (Throwable $exception) {
                Log::warning('E-mail PasswordResetCode non envoyé.', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d’envoyer le code pour le moment.',
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Si ce compte existe, un code a été envoyé par e-mail.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! $record || ! Hash::check($validated['code'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré.',
            ], 422);
        }

        if (! $record->created_at || Carbon::parse($record->created_at)->lt(now()->subMinutes(60))) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnecté avec succès',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => ['required', 'string'],
            'app_variant' => ['sometimes', 'in:apk,play'],
        ]);

        $request->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        // Envoyée par les nouvelles versions de l'app ; les anciennes sont toutes des APK du site.
        $request->user()->forceFill(['app_variant' => $request->input('app_variant', 'apk')])->save();

        return response()->json([
            'success' => true,
            'message' => 'Token de notifications enregistré',
        ]);
    }
}
