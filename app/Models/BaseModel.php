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
     * ce qui cause des décalages horaires côté mobile.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Corriger le binding PDO des booléens pour PostgreSQL.
     *
     * Problème : PDO avec le driver pgsql lie les bool PHP en tant qu'integers
     * (PDO::PARAM_INT), ce qui cause :
     *   "column X is of type boolean but expression is of type integer"
     *
     * Solution : intercepter les attributs avant l'envoi en base et convertir
     * les colonnes déclarées comme 'boolean' en string 'true'/'false' que
     * PostgreSQL accepte nativement pour ses colonnes boolean.
     *
     * Cette méthode est appelée via le hook `saving` enregistré dans boot().
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model) {
            $casts = $model->getCasts();

            foreach ($model->getAttributes() as $key => $value) {
                if (
                    isset($casts[$key]) &&
                    in_array($casts[$key], ['boolean', 'bool'], true) &&
                    $value !== null
                ) {
                    // Forcer la valeur en string 'true'/'false'
                    // PDO ne cast pas ces strings en int → PostgreSQL les accepte
                    $model->attributes[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? 'true'
                        : 'false';
                }
            }
        });
    }
}
