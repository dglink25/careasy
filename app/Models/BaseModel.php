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
     * Sans cette méthode, les dates sont émises en JSON sans suffixe timezone
     * (ex: "2026-06-18 20:40:05"), ce qui est ambigu. Le client mobile
     * interprète alors la chaîne comme UTC et ajoute 1h de décalage.
     *
     * En convertissant vers UTC et en ajoutant le suffixe Z, tous les clients
     * (mobile, web) peuvent faire toLocal() de façon fiable quel que soit
     * leur fuseau horaire.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');
    }
}
