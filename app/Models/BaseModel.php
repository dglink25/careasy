<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

abstract class BaseModel extends Model
{
    /**
     * Sérialiser toutes les dates en ISO 8601 UTC avec suffixe Z.
     *
     * Pourquoi : Laravel est configuré sur Africa/Porto-Novo (UTC+1).
     * Sans cette méthode, les dates sont émises en JSON sans suffixe timezone,
     * ce qui cause des décalages horaires côté mobile.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');
    }
}
