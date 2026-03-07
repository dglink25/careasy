<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder{
    public function run(){
        $plans = [
            [
                'name' => 'Plan Essentiel',
                'code' => 'VP1',
                'description' => 'Idéal pour démarrer votre activité sur notre plateforme',
                'price' => 25000,
                'duration_days' => 30,
                'features' => [
                    'Création d\'un profil entreprise active',
                    'Jusqu\'à 5 services',
                    'Support standard'
                ],
                'limitations' => [
                    'Pas de statistiques avancées',
                    'Support standard uniquement',
                    'Moins recommandé aux clients'
                ],
                'max_services' => 5,
                'max_employees' => 2,
                'has_priority_support' => false,
                'has_analytics' => false,
                'has_api_access' => false,
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Plan Professionnel',
                'code' => 'VP2',
                'description' => 'Pour les entreprises qui veulent se développer',
                'price' => 50000,
                'duration_days' => 30,
                'features' => [
                    'Tout du plan Essentiel',
                    'Jusqu\'à 15 services',
                    'Statistiques de base',
                    'Support prioritaire'
                ],
                'limitations' => [
                    'Statistiques limitées'
                ],
                'max_services' => 15,
                'max_employees' => 5,
                'has_priority_support' => true,
                'has_analytics' => true,
                'has_api_access' => false,
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Plan Premium',
                'code' => 'VP3',
                'description'  => 'Solution complète pour les grandes entreprises',
                'price' => 100000,
                'duration_days' => 30,
                'features' => [
                    'Tout du plan Professionnel',
                    'Services illimités',
                    'Vidéos promotionnelles',
                    'Statistiques avancées',
                    'Accès Notification SMS Client',
                    'Support VIP 24/7'
                ],
                'limitations' => [],
                'max_services' => null, // illimité
                'max_employees' => 15,
                'has_priority_support' => true,
                'has_analytics' => true,
                'has_api_access' => true,
                'is_active' => true,
                'sort_order' => 3
            ],
            [
                'name' => 'Plan Annuel',
                'code' => 'VP3-ANN',
                'description' => 'Économisez 2 mois avec un abonnement annuel',
                'price' => 1000000,
                'duration_days' => 365,
                'features' => [
                    'Toutes les fonctionnalités du plan Premium',
                    '2 mois offerts',
                    'Consultance dédiée',
                    'Formation incluse'
                ],
                'limitations' => [],
                'max_services' => null,
                'max_employees' => 20,
                'has_priority_support' => true,
                'has_analytics' => true,
                'has_api_access' => true,
                'is_active' => true,
                'sort_order' => 4
            ]
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}