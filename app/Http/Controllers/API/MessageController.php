<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

class MessageController extends Controller
{
    public function getMessages($convId)
    {
        $conv = Conversation::with(['messages.sender'])
            ->find($convId);

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        // Autorisation : faire partie de la conversation
        if (!$this->isMember($conv, Auth::id())) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json($conv);
    }

    public function sendMessage(Request $request, $convId)
    {
        $conv = Conversation::find($convId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        /* -------- validation -------- */
        $rules = [
            'type'      => 'required|in:text,image,video,vocal,document',
            'content'   => 'nullable|string',
            //  Accepter soit un fichier classique, soit base64
            'file'      => 'nullable|file',
            'file_data' => 'nullable|string',
            'file_name' => 'nullable|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        //  Vérifier qu'on a au moins du contenu ou un fichier
        if ($request->type !== 'text' && !$request->hasFile('file') && !$request->file_data) {
            return response()->json(['message' => 'Fichier requis pour ce type de message'], 422);
        }

        /* -------- upload fichier -------- */
        $filePath = null;

        //  CAS 1: Fichier classique (multipart/form-data)
        if ($request->hasFile('file')) {
            $disk = 'public';
            $dir = match ($request->type) {
                'image' => 'uploads/messages/images',
                'video' => 'uploads/messages/videos',
                'vocal' => 'uploads/messages/vocals',
                'document' => 'uploads/messages/documents', // Nouveau
                default => 'uploads/messages/others',
            };
            $file = $request->file('file');

            // Sécurité : tailles max
            $maxSize = match ($request->type) {
                'image' => 5 * 1024,   // 5 Mo
                'video' => 25 * 1024,  // 25 Mo
                'vocal' => 5 * 1024,   // 5 Mo
                'document' => 10 * 1024, // 10 Mo pour documents
                default => 5 * 1024,
            };

            if ($file->getSize() > $maxSize * 1024) {
                return response()->json(['message' => 'Fichier trop lourd'], 413);
            }

            $filePath = $file->store($dir, $disk);
        }
        //  CAS 2: Fichier en base64 (JSON)
        elseif ($request->file_data) {
            try {
                // Extraire les données base64
                $fileData = $request->file_data;
                
                Log::info('Traitement base64', [
                    'has_data' => !empty($fileData),
                    'length' => strlen($fileData),
                    'preview' => substr($fileData, 0, 50),
                ]);
                
                // Format: data:image/jpeg;base64,/9j/4AAQSkZJRg...
                if (preg_match('/^data:([^;]+);base64,(.+)$/', $fileData, $matches)) {
                    $mimeType = $matches[1];
                    $base64Data = $matches[2];
                    
                    Log::info('Base64 parsé', [
                        'mime' => $mimeType,
                        'data_length' => strlen($base64Data),
                    ]);
                } else {
                    Log::error('Format base64 invalide', ['data_preview' => substr($fileData, 0, 100)]);
                    return response()->json(['message' => 'Format base64 invalide'], 422);
                }

                // Décoder le base64
                $decodedFile = base64_decode($base64Data, true);
                if ($decodedFile === false) {
                    Log::error('Décodage base64 échoué');
                    return response()->json(['message' => 'Décodage base64 échoué'], 422);
                }

                Log::info('Fichier décodé', ['size' => strlen($decodedFile)]);

                // Vérifier la taille
                $fileSize = strlen($decodedFile);
                $maxSize = match ($request->type) {
                    'image' => 5 * 1024 * 1024,   // 5 Mo
                    'video' => 25 * 1024 * 1024,  // 25 Mo
                    'vocal' => 5 * 1024 * 1024,   // 5 Mo
                    'document' => 10 * 1024 * 1024, // 10 Mo pour documents
                    default => 5 * 1024 * 1024,
                };

                if ($fileSize > $maxSize) {
                    Log::warning('Fichier trop lourd', ['size' => $fileSize, 'max' => $maxSize]);
                    return response()->json(['message' => 'Fichier trop lourd'], 413);
                }

                // Déterminer l'extension
                $extension = match ($mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    'video/mp4' => 'mp4',
                    'video/webm' => 'webm',
                    'audio/webm' => 'webm',
                    'audio/mpeg' => 'mp3',
                    'audio/wav' => 'wav',
                    // ✅ Documents
                    'application/pdf' => 'pdf',
                    'application/zip' => 'zip',
                    'application/x-zip-compressed' => 'zip',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/vnd.ms-excel' => 'xls',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                    'text/plain' => 'txt',
                    'application/json' => 'json',
                    default => 'bin',
                };

                // Créer un nom de fichier unique
                $originalName = $request->file_name ?? 'file_' . time();
                $fileNameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
                $fileName = Str::slug($fileNameWithoutExt) . '_' . uniqid() . '.' . $extension;

                // Déterminer le répertoire
                $dir = match ($request->type) {
                    'image' => 'uploads/messages/images',
                    'video' => 'uploads/messages/videos',
                    'vocal' => 'uploads/messages/vocals',
                    'document' => 'uploads/messages/documents', // Nouveau
                    default => 'uploads/messages/others',
                };

                // Sauvegarder le fichier
                $filePath = $dir . '/' . $fileName;
                
                Log::info('Tentative sauvegarde', [
                    'path' => $filePath,
                    'dir' => $dir,
                    'file' => $fileName,
                ]);
                
                $saved = Storage::disk('public')->put($filePath, $decodedFile);
                
                if (!$saved) {
                    Log::error('Échec sauvegarde fichier');
                    throw new Exception('Impossible de sauvegarder le fichier');
                }

                Log::info('Fichier base64 sauvegardé', [
                    'path' => $filePath,
                    'size' => $fileSize,
                    'mime' => $mimeType,
                ]);

            } catch (Exception $e) {
                Log::error('Erreur traitement base64', [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);
                return response()->json([
                    'message' => 'Erreur traitement du fichier',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        /* -------- création message -------- */
        DB::beginTransaction();
        try {
            //  Définir un contenu par défaut si vide
            $content = $request->input('content');
            if (empty($content) && $filePath) {
                $content = match ($request->type) {
                    'image' => ' Image',
                    'video' => ' Vidéo',
                    'vocal' => ' Message vocal',
                    'document' => 'Document',
                    default => ' Fichier',
                };
            }
            
            $msg = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $userId,
                'content'         => $content,
                'type'            => $request->type,
                'file_path'       => $filePath,
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
            ]);
            $conv->touch(); // updated_at
            DB::commit();

            Log::info('Message créé avec succès', [
                'message_id' => $msg->id,
                'type' => $msg->type,
                'has_file' => !is_null($filePath),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            // Suppression fichier si uploadé
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            Log::error('sendMessage error', ['e' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne'], 500);
        }

        return response()->json($msg->load('sender'), 201);
    }

    public function markAsRead($convId)
    {
        $conv = Conversation::find($convId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }
        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $updated = Message::where('conversation_id', $convId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marqués lus', 'count' => $updated]);
    }

    public function myConversations()
    {
        $userId = Auth::id();
        $conv = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['messages.sender'])
            ->latest('updated_at')
            ->get()
            ->map(function ($c) use ($userId) {
                $c->other_user = $c->user_one_id === $userId ? $c->userTwo : $c->userOne;
                $c->unread_count = $c->unreadCountFor($userId);
                return $c;
            });

        return response()->json($conv);
    }

    public function startConversation(Request $request)
    {
        $request->validate(['receiver_id' => 'nullable|exists:users,id']);
        $userId = Auth::id();
        $recvId = $request->receiver_id;

        // anonyme
        if (!$userId && $recvId) {
            $conv = Conversation::create(['user_one_id' => $recvId, 'user_two_id' => null]);
            return response()->json($conv, 201);
        }

        // authentifié
        if ($userId && $recvId) {
            $conv = Conversation::where(fn($q) => $q->where('user_one_id', $userId)->where('user_two_id', $recvId))
                ->orWhere(fn($q) => $q->where('user_one_id', $recvId)->where('user_two_id', $userId))
                ->first();
            if (!$conv) {
                $conv = Conversation::create(['user_one_id' => $userId, 'user_two_id' => $recvId]);
            }
            return response()->json($conv);
        }

        return response()->json(['message' => 'Receiver required'], 422);
    }

    /**
     * Mettre à jour mon statut en ligne
     */
    public function updateOnlineStatus()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $user->last_seen_at = now();
        $user->save();

        return response()->json([
            'message' => 'Statut mis à jour',
            'last_seen_at' => $user->last_seen_at
        ]);
    }

    /**
     * Vérifier le statut en ligne d'un utilisateur
     */
    public function checkOnlineStatus($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        // Un utilisateur est considéré "en ligne" si vu dans les 5 dernières minutes
        $isOnline = $user->last_seen_at && 
                    $user->last_seen_at->diffInMinutes(now()) < 5;

        return response()->json([
            'user_id' => $user->id,
            'is_online' => $isOnline,
            'last_seen_at' => $user->last_seen_at
        ]);
    }

    private function isMember(Conversation $conv, ?int $userId): bool
    {
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }
}