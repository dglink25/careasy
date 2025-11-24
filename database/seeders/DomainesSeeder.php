<?php

// database/seeders/DomainesSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomainesSeeder extends Seeder
{
    public function run(): void
    {
        $domaines = [
            "Garage mécanique",
            "Vente de voitures",
            "Vente de motos",
            "Location de voitures",
            "Station d’essence",
            "Lavage automobile",
            "Électricien auto",
            "Climatisation auto",
            "Peinture auto",
            "Tôlerie",
            "Pneumatique / vulcanisation",
            "Dépannage / remorquage",
            "Diagnostic automobile",
            "Changement d'huile",
            "Assurance automobile",
            "École de conduite",
            "Vente de pièces détachées",
            "Maintenance poids lourds",
            "Réparation moto",
            "Vente de vélos / entretien"
        ];

        foreach ($domaines as $domaine) {
            DB::table('domaines')->insert([
                'name' => $domaine,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
