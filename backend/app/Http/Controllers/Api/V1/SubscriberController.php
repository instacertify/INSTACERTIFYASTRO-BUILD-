<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Notifications\ConfirmSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class SubscriberController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'website' => ['prohibited'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'source_page' => ['nullable', 'string', 'max:255'],
        ]);

        $subscriber = Subscriber::firstOrNew(['email' => strtolower(trim($validated['email']))]);

        if ($subscriber->status !== 'active') {
            $subscriber->fill([
                'status' => 'pending',
                'source_page' => $validated['source_page'] ?? null,
                'ip' => $request->ip(),
                'unsubscribed_at' => null,
            ])->save();

            Notification::route('mail', $subscriber->email)->notify(new ConfirmSubscription($subscriber));
        }

        return response()->json([
            'data' => ['message' => 'Almost done — please confirm from the email we just sent you.'],
        ], 201);
    }

    public function confirm(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->update(['status' => 'active', 'confirmed_at' => $subscriber->confirmed_at ?? now()]);

        return redirect()->away(rtrim(config('instacertify.frontend_url'), '/').'/?subscribed=1');
    }

    public function unsubscribe(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        return redirect()->away(rtrim(config('instacertify.frontend_url'), '/').'/?unsubscribed=1');
    }
}
