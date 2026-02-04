<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateCarAIData extends Command
{
    // C'est ce que vous taperez dans le terminal
    protected $signature = 'carai:generate-data';
    protected $description = 'Génère le fichier JSONL pour l\'entraînement de CarAI';

    public function handle()
    {
        $this->info("Démarrage de la génération...");

        // 1. Récupération des données
        $domaines = DB::table('domaines')->pluck('name')->toArray();
        $locations = DB::table('locations_benin')->limit(545)->get();

        if (empty($domaines) || $locations->isEmpty()) {
            $this->error("Erreur : La table domaines ou locations_benin est vide !");
            return;
        }

        $templates = [
            ["in" => "Je cherche un service de {domaine} à {lieu}.", "out" => "Sur CarEasy, nous avons plusieurs experts en {domaine} à {lieu}. Je vous suggère de regarder les prestataires vérifiés dans cette zone."],
            ["in" => "Besoin urgent de {domaine} vers {lieu}, vous avez quelqu'un ?", "out" => "Bien sûr ! CarAI a identifié des spécialistes en {domaine} à {lieu}. Je peux vous envoyer l'itinéraire du plus proche."],
            ["in" => "Mɛ̌ ka ɖò {lieu} e nɔ bló {domaine} é? (Qui fait {domaine} à {lieu} ?)", "out" => "CarAI mɔ mɛ e nɔ bló {domaine} é ɖò {lieu}. (CarAI a trouvé des experts en {domaine} à {lieu}). Consultez la liste sur la plateforme."],
            ["in" => "Prix pour {domaine} à {lieu} ?", "out" => "Les prix pour {domaine} varient selon le prestataire. À {lieu}, vous pouvez demander un devis gratuit directement via le bouton WhatsApp sur CarEasy."]
        ];

        $filePath = storage_path('carai_data.jsonl');
        
        // Ouvrir le fichier en mode écriture (écrase le précédent)
        $handle = fopen($filePath, 'w');

        // Réduire à un nombre raisonnable (ex: 50 000 pour commencer) car 5 millions prendront des heures
        $totalRows = 50000; 
        $progressBar = $this->output->createProgressBar($totalRows);

        for ($i = 0; $i < $totalRows; $i++) {
            $domaine = $domaines[array_rand($domaines)];
            $location = $locations->random();
            $template = $templates[array_rand($templates)];

            $input = str_replace(['{domaine}', '{lieu}'], [strtolower($domaine), $location->arrondissement], $template['in']);
            $output = str_replace(['{domaine}', '{lieu}'], [$domaine, $location->arrondissement], $template['out']);

            $line = [
                "instruction" => "Tu es CarAI, l'assistant expert de la plateforme CarEasy au Bénin.",
                "input" => $input,
                "output" => $output
            ];

            fwrite($handle, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n");
            $progressBar->advance();
        }

        fclose($handle);
        $progressBar->finish();
        
        $this->newLine();
        $this->info("Terminé ! Fichier généré ici : " . $filePath);
    }
}
