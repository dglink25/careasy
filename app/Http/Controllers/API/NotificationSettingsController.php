<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationSettingsController extends Controller{
    // ── GET /user/notification-settings ──────────────────────────────────
    public function index(Request $request): JsonResponse {
        $user = $request->user();

        return response()->json([
            'success'  => true,
            'channels' => NotificationPreferences::channels($user),
            'types'    => NotificationPreferences::types($user),
        ]);
    }

    // ── PUT /user/notification-settings ──────────────────────────────────
    public function update(Request $request): JsonResponse {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            // Canaux
            'channels'            => 'nullable|array',
            'channels.email'      => 'nullable|boolean',
            'channels.sms'        => 'nullable|boolean',
            'channels.whatsapp'   => 'nullable|boolean',
            'channels.push'       => 'nullable|boolean',

            // Types
            'types'               => 'nullable|array',
            'types.message'       => 'nullable|boolean',
            'types.rdv'           => 'nullable|boolean',
            'types.reminder'      => 'nullable|boolean',
            'types.new_service'   => 'nullable|boolean',

            // Rétro-compat : ancienne structure plate
            'notifications'            => 'nullable|array',
            'notifications.email'      => 'nullable|boolean',
            'notifications.push'       => 'nullable|boolean',
            'notifications.sms'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Support de l'ancienne structure { notifications: { email, push, sms } }
        if ($request->has('notifications') && !$request->has('channels')) {
            $old = $request->notifications;
            $channels = [
                'email'    => $old['email']    ?? true,
                'sms'      => $old['sms']      ?? false,
                'whatsapp' => $old['whatsapp'] ?? true,
                'push'     => $old['push']     ?? true,
            ];
            $types = NotificationPreferences::types($user); // inchangé
        } else {
            $channels = array_merge(
                NotificationPreferences::channels($user),
                $request->input('channels', [])
            );
            $types = array_merge(
                NotificationPreferences::types($user),
                $request->input('types', [])
            );
        }

        NotificationPreferences::save($user, $channels, $types);

        return response()->json([
            'success'  => true,
            'message'  => 'Préférences de notifications mises à jour',
            'channels' => $channels,
            'types'    => $types,
        ]);
    }
}