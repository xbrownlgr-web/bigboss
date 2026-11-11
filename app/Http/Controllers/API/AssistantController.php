<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AssistantMessage;
use App\Services\SupportAssistant;
use App\Services\SupportInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(private readonly SupportAssistant $assistant)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $messages = AssistantMessage::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, SupportInbox $inbox): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // Assistant coupé (décision du fondateur) : les anciennes versions de l'app affichent encore
        // l'onglet assistant, mais le message part directement à un conseiller humain.
        if (! AppSetting::assistantEnabled()) {
            $inbox->receive($user, trim($data['content']));

            $message = AssistantMessage::create([
                'user_id' => $user->id,
                'role' => AssistantMessage::ROLE_USER,
                'content' => trim($data['content']),
            ]);
            $reply = AssistantMessage::create([
                'user_id' => $user->id,
                'role' => AssistantMessage::ROLE_ASSISTANT,
                'content' => 'Votre message a été transmis à un conseiller BIG BOSS. Il vous répond dans l’onglet « Conseiller ».',
                'handoff' => true,
            ]);

            return response()->json(['success' => true, 'message' => $message, 'reply' => $reply], 201);
        }
        // Plafond quotidien : protège le crédit OpenRouter contre un abus ou une boucle.
        $limit = max(1, (int) (AppSetting::value('assistant_daily_limit') ?: 60));
        $sentToday = AssistantMessage::query()
            ->where('user_id', $user->id)
            ->where('role', AssistantMessage::ROLE_USER)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($sentToday >= $limit) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez atteint la limite de messages à l’assistant pour aujourd’hui. '
                    . 'Un conseiller peut vous répondre dans l’onglet Conseiller.',
            ], 429);
        }

        $message = AssistantMessage::create([
            'user_id' => $user->id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => trim($data['content']),
        ]);

        $answer = $this->assistant->reply($user);

        $reply = AssistantMessage::create([
            'user_id' => $user->id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => $answer['content'],
            'guides' => $answer['guides'] ?: null,
            'handoff' => $answer['handoff'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'reply' => $reply,
        ], 201);
    }

    /**
     * « Nouvelle conversation » : l'assistant repart de zéro.
     */
    public function destroy(Request $request): JsonResponse
    {
        AssistantMessage::query()->where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }
}
