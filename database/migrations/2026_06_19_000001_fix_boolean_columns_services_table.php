<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Répare les colonnes boolean de la table services.
 *
 * Contexte : un hook `saving` défectueux dans BaseModel a stocké
 * les booléens sous forme de strings ('true'/'false') au lieu de
 * vrais booléens PostgreSQL, ce qui a cassé les requêtes WHERE.
 *
 * Cette migration ne change pas le schéma (les colonnes sont déjà boolean),
 * elle re-cast toutes les valeurs pour s'assurer qu'elles sont correctes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Forcer le re-cast de toutes les colonnes boolean via USING
        // Cela corrige aussi les éventuelles strings 'true'/'false' stockées
        DB::statement("
            UPDATE services SET
                is_price_on_request = CASE
                    WHEN is_price_on_request::text IN ('1','true','t') THEN TRUE ELSE FALSE
                END,
                has_promo = CASE
                    WHEN has_promo::text IN ('1','true','t') THEN TRUE ELSE FALSE
                END,
                is_always_open = CASE
                    WHEN is_always_open::text IN ('1','true','t') THEN TRUE ELSE FALSE
                END,
                is_open_24h = CASE
                    WHEN is_open_24h::text IN ('1','true','t') THEN TRUE ELSE FALSE
                END,
                is_visibility = CASE
                    WHEN is_visibility::text IN ('1','true','t') THEN TRUE ELSE FALSE
                END
        ");
    }

    public function down(): void
    {
        // Pas de rollback nécessaire — on ne change pas le schéma
    }
};
