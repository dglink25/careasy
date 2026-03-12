<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationController extends Controller
{

    public function vapidPublicKey()
    {
        return response()->json([
            'key' => config('webpush.vapid.public_key'),
        ]);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'subscription'          => 'required|array',
            'subscription.endpoint' => 'required|string',
        ]);

        $user = $request->user();
        $subscriptions = $user->settings['push_subscriptions'] ?? [];

        $endpoint = $request->subscription['endpoint'];
        $exists   = collect($subscriptions)->contains(fn($s) => $s['endpoint'] === $endpoint);

        if (!$exists) {
            $subscriptions[] = $request->subscription;
            $settings = $user->settings;
            $settings['push_subscriptions'] = array_slice($subscriptions, -5); // max 5 appareils
            $user->settings = $settings;
            $user->save();
        }

        return response()->json(['message' => 'Abonnement enregistré.']);
    }

    public function unsubscribe(Request $request)
    {
        $user = $request->user();
        $settings = $user->settings;
        $settings['push_subscriptions'] = [];
        $user->settings = $settings;
        $user->save();

        return response()->json(['message' => 'Désabonné.']);
    }

    /**
     * Envoyer une notification push à un utilisateur
     * (méthode utilitaire — appelée depuis les Notifications Laravel)
     */
    public static function sendToUser($user, array $payload): void
    {
        $subscriptions = $user->settings['push_subscriptions'] ?? [];
        if (empty($subscriptions)) return;

        $auth = [
            'VAPID' => [
                'subject'    => 'mailto:' . config('mail.from.address'),
                'publicKey'  => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ];

        $webPush = new WebPush($auth);

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create($sub);
                $webPush->queueNotification(
                    $subscription,
                    json_encode($payload)
                );
            } catch (\Exception $e) {
                \Log::warning('[Push] Erreur abonnement:', ['err' => $e->getMessage()]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                \Log::warning('[Push] Envoi échoué:', ['reason' => $report->getReason()]);
            }
        }
    }
}