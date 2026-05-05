<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SmsAdminController extends Controller{
    public function status(){
        if (Auth::user()->role !== 'admin') abort(403);

        $sms       = app(SmsService::class);
        $available = $sms->isAvailable();

        return response()->json([
            'gateway_url' => config('sms.gateway_url') ?: '(non configurée)',
            'enabled'     => config('sms.enabled'),
            'available'   => $available,
            'sender'      => config('sms.sender_phone'),
            'status'      => $available ? 'online' : 'offline',
        ]);
    }

    public function test(Request $request) {
        if (Auth::user()->role !== 'admin') abort(403);

        $request->validate([
            'phone'   => 'required|string',
            'message' => 'nullable|string|max:160',
        ]);

        $sms  = app(SmsService::class);
        $sent = $sms->sendMessage(
            $request->phone,
            $request->message ?? 'CarEasy Test SMS gateway — OK !'
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'SMS envoyé avec succès' : 'Échec de l\'envoi — vérifiez la gateway',
        ], $sent ? 200 : 503);
    }
}