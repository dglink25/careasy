<?php
// app/Services/NotificationPreferences.php
// Service centralisé pour lire & vérifier les préférences de notification
// d'un utilisateur (canaux + types).
//
// Structure stockée dans users.settings (JSON) :
//
//   "notifications": {
//       "channels": {
//           "email":    true,
//           "sms":      false,
//           "whatsapp": true,
//           "push":     true
//       },
//       "types": {
//           "message":      true,
//           "rdv":          true,
//           "reminder":     true,
//           "new_service":  false
//       }
//   }
//
// La structure est rétro-compatible avec l'ancienne :
//   "notifications": { "email": true, "push": true, "sms": false }

namespace App\Services;

use App\Models\User;

class NotificationPreferences{
    // ── Canaux disponibles ────────────────────────────────────────────────
    public const CHANNEL_EMAIL    = 'email';
    public const CHANNEL_SMS      = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_PUSH     = 'push';

    // ── Types de notification ─────────────────────────────────────────────
    public const TYPE_MESSAGE     = 'message';
    public const TYPE_RDV         = 'rdv';
    public const TYPE_REMINDER    = 'reminder';
    public const TYPE_NEW_SERVICE = 'new_service';

    // ── Valeurs par défaut ────────────────────────────────────────────────
    private static array $defaultChannels = [
        self::CHANNEL_EMAIL    => true,
        self::CHANNEL_SMS      => false,
        self::CHANNEL_WHATSAPP => true,
        self::CHANNEL_PUSH     => true,
    ];

    private static array $defaultTypes = [
        self::TYPE_MESSAGE     => true,
        self::TYPE_RDV         => true,
        self::TYPE_REMINDER    => true,
        self::TYPE_NEW_SERVICE => true,
    ];

    public static function channels(User $user): array   {
        $notifSettings = self::getNotifSettings($user);
        $raw           = $notifSettings['channels'] ?? [];

        // Rétro-compatibilité : ancienne structure plate { email, push, sms }
        if (empty($raw) && isset($notifSettings['email'])) {
            $raw = [
                self::CHANNEL_EMAIL    => (bool) ($notifSettings['email']    ?? true),
                self::CHANNEL_SMS      => (bool) ($notifSettings['sms']      ?? false),
                self::CHANNEL_WHATSAPP => (bool) ($notifSettings['whatsapp'] ?? true),
                self::CHANNEL_PUSH     => (bool) ($notifSettings['push']     ?? true),
            ];
        }

        return array_merge(self::$defaultChannels, array_map('boolval', $raw));
    }


    public static function types(User $user): array {
        $notifSettings = self::getNotifSettings($user);
        $raw           = $notifSettings['types'] ?? [];

        return array_merge(self::$defaultTypes, array_map('boolval', $raw));
    }

    public static function canReceiveViaChannel(User $user, string $channel): bool   {
        return self::channels($user)[$channel] ?? (self::$defaultChannels[$channel] ?? false);
    }

    public static function canReceiveType(User $user, string $type): bool
    {
        return self::types($user)[$type] ?? (self::$defaultTypes[$type] ?? true);
    }

    public static function canReceive(User $user, string $channel, string $type): bool {
        return self::canReceiveViaChannel($user, $channel)
            && self::canReceiveType($user, $type);
    }

    public static function save(User $user, array $channels, array $types): void {
        $settings = $user->settings; // accesseur qui retourne un array

        $settings['notifications'] = [
            'channels' => array_merge(self::$defaultChannels, array_map('boolval', $channels)),
            'types'    => array_merge(self::$defaultTypes,    array_map('boolval', $types)),
        ];

        $user->settings = $settings; // mutateur qui encode en JSON
        $user->save();
    }


    private static function getNotifSettings(User $user): array
    {
        $settings = $user->settings; // array (via accesseur)
        $notif    = $settings['notifications'] ?? [];
        return is_array($notif) ? $notif : [];
    }
}