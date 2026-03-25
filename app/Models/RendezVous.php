<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RendezVous extends Model{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'client_id',
        'prestataire_id',
        'entreprise_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'client_notes',
        'prestataire_notes',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
        'reminder_sent_client',
        'reminder_sent_prestataire',
        'last_reminder_sent_at'
    ];

    protected $table = 'rendez_vous';

    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'reminder_sent_client' => 'boolean',
        'reminder_sent_prestataire' => 'boolean'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_RESCHEDULED = 'rescheduled';

    // Relations
    public function service() {
        return $this->belongsTo(Service::class);
    }

    public function client() {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function prestataire()  {
        return $this->belongsTo(User::class, 'prestataire_id');
    }

    public function entreprise(){
        return $this->belongsTo(Entreprise::class);
    }

    // Scopes
    public function scopePending($query) {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query) {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeForDate($query, $date) {
        return $query->where('date', $date);
    }

    public function scopeForService($query, $serviceId) {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForPrestataire($query, $prestataireId) {
        return $query->where('prestataire_id', $prestataireId);
    }

    public function scopeForClient($query, $clientId) {
        return $query->where('client_id', $clientId);
    }

    public function scopeUpcoming($query) {
        return $query->where('date', '>=', Carbon::today())
                     ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopeNeedsReminder($query, $hoursBefore = 24) {
        $targetTime = Carbon::now()->addHours($hoursBefore);
        
        return $query->where('status', self::STATUS_CONFIRMED)
                     ->where('date', $targetTime->toDateString())
                     ->where('start_time', '>=', $targetTime->subMinutes(30)->format('H:i:s'))
                     ->where('start_time', '<=', $targetTime->addHour()->format('H:i:s'))
                     ->where(function($q) {
                         $q->where('reminder_sent_client', false)
                           ->orWhere('reminder_sent_prestataire', false);
                     });
    }

    // Vérifications
    public function isPending() {
        return $this->status === self::STATUS_PENDING;
    }

    public function review(){
        return $this->hasOne(\App\Models\Review::class, 'rendez_vous_id');
    }

    public function isConfirmed() {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled() {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted() {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeCancelled() {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function canBeModified() {
        return $this->status === self::STATUS_PENDING;
    }

    // Vérifier si un créneau est disponible
    public static function isTimeSlotAvailable($serviceId, $date, $startTime, $endTime, $excludeId = null) {
        $query = self::where('service_id', $serviceId)
                     ->where('date', $date)
                     ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
                     ->where(function($q) use ($startTime, $endTime) {
                         // Vérifier les chevauchements de créneaux
                         $q->where('start_time', '<', $endTime)
                           ->where('end_time', '>', $startTime);
                     });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    // Obtenir les créneaux disponibles pour un service
    public static function getAvailableSlots($service, $date) {
        $interval = 60; // minutes
        
        // Vérifier si le service est ouvert 24h/24
        if ($service->is_always_open || $service->is_open_24h) {
            $start = Carbon::parse($date . ' 00:00');
            $end = Carbon::parse($date . ' 23:59');
            
            $bookedSlots = self::where('service_id', $service->id)
                               ->where('date', $date)
                               ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
                               ->get();
            
            $availableSlots = [];
            while ($start->lt($end)) {
                $slotEnd = $start->copy()->addMinutes($interval);
                if ($slotEnd->gt($end)) break;
                
                $isBooked = false;
                foreach ($bookedSlots as $booking) {
                    $bookingStart = Carbon::parse($booking->start_time);
                    $bookingEnd = Carbon::parse($booking->end_time);
                    
                    // Vérifier le chevauchement
                    if ($bookingStart->lt($slotEnd) && $bookingEnd->gt($start)) {
                        $isBooked = true;
                        break;
                    }
                }
                
                if (!$isBooked) {
                    $availableSlots[] = [
                        'start' => $start->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                        'display' => $start->format('H:i') . ' - ' . $slotEnd->format('H:i')
                    ];
                }
                $start->addMinutes($interval);
            }
            return $availableSlots;
        }
        
        // Récupérer et décoder le schedule
        $schedule = $service->schedule;
        
        // Si schedule est une chaîne JSON, la décoder
        if (is_string($schedule)) {
            $schedule = json_decode($schedule, true);
        }
        
        // Si pas de schedule ou schedule vide
        if (empty($schedule) || !is_array($schedule)) {
            return [];
        }
        
        // Obtenir le jour de la semaine en anglais
        $dayOfWeek = strtolower(Carbon::parse($date)->locale('en')->dayName);
        
        // Vérifier si le service est ouvert ce jour
        if (!isset($schedule[$dayOfWeek]) || !$schedule[$dayOfWeek]['is_open']) {
            return [];
        }
        
        // Récupérer les horaires du jour
        $daySchedule = $schedule[$dayOfWeek];
        
        // Vérifier que start et end existent
        if (!isset($daySchedule['start']) || !isset($daySchedule['end'])) {
            return [];
        }
        
        $startTime = $daySchedule['start'];
        $endTime = $daySchedule['end'];
        
        // Convertir en Carbon
        $start = Carbon::parse($date . ' ' . $startTime);
        $end = Carbon::parse($date . ' ' . $endTime);
        
        // Récupérer les créneaux déjà réservés
        $bookedSlots = self::where('service_id', $service->id)
                           ->where('date', $date)
                           ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
                           ->get();
        
        $availableSlots = [];
        
        // Générer tous les créneaux de 30 minutes
        while ($start->lt($end)) {
            $slotEnd = $start->copy()->addMinutes($interval);
            
            // Vérifier si le créneau dépasse l'heure de fermeture
            if ($slotEnd->gt($end)) {
                break;
            }
            
            // Vérifier si le créneau est déjà réservé
            $isBooked = false;
            foreach ($bookedSlots as $booking) {
                $bookingStart = Carbon::parse($booking->start_time);
                $bookingEnd = Carbon::parse($booking->end_time);
                
                // Vérifier le chevauchement
                if ($bookingStart->lt($slotEnd) && $bookingEnd->gt($start)) {
                    $isBooked = true;
                    break;
                }
            }
            
            if (!$isBooked) {
                $availableSlots[] = [
                    'start' => $start->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                    'display' => $start->format('H:i') . ' - ' . $slotEnd->format('H:i')
                ];
            }
            
            $start->addMinutes($interval);
        }
        
        return $availableSlots;
    }
}