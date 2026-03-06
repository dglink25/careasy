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
                         $q->whereBetween('start_time', [$startTime, $endTime])
                           ->orWhereBetween('end_time', [$startTime, $endTime])
                           ->orWhere(function($q2) use ($startTime, $endTime) {
                               $q2->where('start_time', '<=', $startTime)
                                  ->where('end_time', '>=', $endTime);
                           });
                     });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    // Obtenir les créneaux disponibles pour un service
    public static function getAvailableSlots($service, $date)  {
        if (!$service->schedule || $service->is_always_open) {
            return [];
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->locale('en')->dayName);
        
        if (!isset($service->schedule[$dayOfWeek]) || !$service->schedule[$dayOfWeek]['is_open']) {
            return [];
        }

        $schedule = $service->schedule[$dayOfWeek];
        $start = Carbon::parse($schedule['start']);
        $end = Carbon::parse($schedule['end']);
        
        $bookedSlots = self::where('service_id', $service->id)
                           ->where('date', $date)
                           ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
                           ->get();

        $availableSlots = [];
        $interval = 30; // minutes

        while ($start->lt($end)) {
            $slotEnd = $start->copy()->addMinutes($interval);
            
            $isBooked = $bookedSlots->contains(function($booking) use ($start, $slotEnd) {
                $bookingStart = Carbon::parse($booking->start_time);
                $bookingEnd = Carbon::parse($booking->end_time);
                
                return ($bookingStart->lt($slotEnd) && $bookingEnd->gt($start));
            });

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