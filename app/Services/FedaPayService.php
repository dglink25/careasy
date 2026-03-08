<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Paiement;
use App\Models\Plan;

class FedaPayService
{
    protected $apiUrl;
    protected $secretKey;
    protected $publicKey;

    public function __construct() {
        $this->secretKey = config('fedapay.secret_key');
        $this->publicKey = config('fedapay.public_key');
        
        $environment = config('fedapay.environment', 'sandbox');
        $this->apiUrl = config("fedapay.urls.{$environment}");
        
        Log::info('FedaPay Service Initialisé', [
            'environment' => $environment,
            'url' => $this->apiUrl,
            'secret_key_presente' => !empty($this->secretKey),
            'public_key_presente' => !empty($this->publicKey)
        ]);
    }

    public function creerPaiement(Paiement $paiement, Plan $plan, $user) {
        try {
            if (empty($this->secretKey)) {
                Log::error('Clé secrète FedaPay manquante');
                return [
                    'success' => false,
                    'message' => 'Configuration FedaPay incomplète'
                ];
            }

            // Montant en centimes
            $montantEnCentimes = (int) ($paiement->montant*1);
            
            $payload = [
                'amount' => $montantEnCentimes,
                'currency' => ['iso' => 'XOF'],
                'description' => "Abonnement {$plan->name} - {$plan->duration_text}",
                'customer' => [
                    'firstname' => $user->prenom ?? $user->name ?? 'Client',
                    'lastname' => $user->nom ?? '',
                    'email' => $user->email,
                    'phone_number' => [
                        'number' => $user->phone ?? '60000000',
                        'country' => 'BJ'
                    ]
                ],
                'metadata' => [
                    'paiement_id' => $paiement->id,
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'plan_code' => $plan->code
                ],
                'callback_url' => config('fedapay.callback_url'),
                'return_url' => config('fedapay.return_url') . '?reference=' . $paiement->reference,
                'cancel_url' => config('fedapay.cancel_url') . '?reference=' . $paiement->reference,
                'options' => [
                    'mobile_money' => [
                        'enabled' => true
                    ],
                    'card' => [
                        'enabled' => true
                    ]
                ]
            ];

            Log::info('Tentative création transaction FedaPay', [
                'url' => $this->apiUrl . '/transactions',
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-FedaPay-Environment' => config('fedapay.environment')
            ])->post($this->apiUrl . '/transactions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Log la réponse complète pour debug
                Log::info('Réponse FedaPay complète', ['data' => $data]);
                
                // Extraction CORRECTE des données FedaPay
                $transactionData = null;
                
                // La structure exacte de FedaPay
                if (isset($data['data']['v1/transaction'])) {
                    $transactionData = $data['data']['v1/transaction'];
                } elseif (isset($data['data']['transaction'])) {
                    $transactionData = $data['data']['transaction'];
                } elseif (isset($data['v1/transaction'])) {
                    $transactionData = $data['v1/transaction'];
                } elseif (isset($data['transaction'])) {
                    $transactionData = $data['transaction'];
                } else {
                    $transactionData = $data;
                }
                
                // Extraire les informations avec des clés correctes
                $transactionId = $transactionData['id'] ?? null;
                $status = $transactionData['status'] ?? null;
                $paymentUrl = $transactionData['payment_url'] ?? null;
                $token = $transactionData['payment_token'] ?? null;
                
                Log::info('Transaction FedaPay extraite', [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'payment_url' => $paymentUrl ? 'Présent' : 'Manquant'
                ]);
                
                // Mettre à jour le paiement
                $paiement->update([
                    'fedapay_response' => json_encode($data),
                    'fedapay_transaction_id' => $transactionId,
                    'fedapay_status' => $status
                ]);

                return [
                    'success' => true,
                    'transaction' => $transactionData,
                    'payment_url' => $paymentUrl,
                    'token' => $token
                ];
            }

            // Log l'erreur détaillée
            $errorResponse = $response->json();
            Log::error('Erreur FedaPay création transaction', [
                'status' => $response->status(),
                'response' => $errorResponse,
                'paiement_id' => $paiement->id
            ]);

            return [
                'success' => false,
                'message' => $errorResponse['message'] ?? 'Erreur lors de la création de la transaction'
            ];

        } catch (\Exception $e) {
            Log::error('Exception FedaPay', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'paiement_id' => $paiement->id
            ]);

            return [
                'success' => false,
                'message' => 'Erreur de communication avec FedaPay: ' . $e->getMessage()
            ];
        }
    }

    public function verifierTransaction($transactionId) {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-FedaPay-Environment' => config('fedapay.environment')
            ])->get($this->apiUrl . '/transactions/' . $transactionId);

            if ($response->successful()) {
                $data = $response->json();
                
                // Extraction similaire
                $transactionData = null;
                if (isset($data['data']['v1/transaction'])) {
                    $transactionData = $data['data']['v1/transaction'];
                } elseif (isset($data['data']['transaction'])) {
                    $transactionData = $data['data']['transaction'];
                } elseif (isset($data['v1/transaction'])) {
                    $transactionData = $data['v1/transaction'];
                } elseif (isset($data['transaction'])) {
                    $transactionData = $data['transaction'];
                } else {
                    $transactionData = $data;
                }
                
                return [
                    'success' => true,
                    'transaction' => $transactionData
                ];
            }

            Log::error('Erreur vérification transaction', [
                'transaction_id' => $transactionId,
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Transaction non trouvée'
            ];

        } catch (\Exception $e) {
            Log::error('Exception vérification transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);

            return [
                'success' => false,
                'message' => 'Erreur de vérification'
            ];
        }
    }
}