<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

// 1. Récupérer vos données réelles
$domaines = DB::table('domaines')->pluck('name')->toArray();
$locations = DB::table('locations_benin')->limit(500)->get(); // On prend 50 arrondissements variés

$dataset = [];

// 2. Modèles de phrases pour varier les intentions
$templates = [
    ["in" => "Je cherche un service de {domaine} à {lieu}.", "out" => "Sur CarEasy, nous avons plusieurs experts en {domaine} à {lieu}. Je vous suggère de regarder les prestataires vérifiés dans cette zone."],
    ["in" => "Besoin urgent de {domaine} vers {lieu}, vous avez quelqu'un ?", "out" => "Bien sûr ! CarAI a identifié des spécialistes en {domaine} à {lieu}. Je peux vous envoyer l'itinéraire du plus proche."],
    ["in" => "Mɛ̌ ka ɖò {lieu} e nɔ bló {domaine} é? (Qui fait {domaine} à {lieu} ?)", "out" => "CarAI mɔ mɛ e nɔ bló {domaine} é ɖò {lieu}. (CarAI a trouvé des experts en {domaine} à {lieu}). Consultez la liste sur la plateforme."],
    ["in" => "Prix pour {domaine} à {lieu} ?", "out" => "Les prix pour {domaine} varient selon le prestataire. À {lieu}, vous pouvez demander un devis gratuit directement via le bouton WhatsApp sur CarEasy."]
];

// 3. Boucle pour générer 5000000 lignes
for ($i = 0; $i < 5000000; $i++) {
    $domaine = $domaines[array_rand($domaines)];
    $location = $locations->random();
    $template = $templates[array_rand($templates)];

    $input = str_replace(['{domaine}', '{lieu}'], [strtolower($domaine), $location->arrondissement], $template['in']);
    $output = str_replace(['{domaine}', '{lieu}'], [$domaine, $location->arrondissement], $template['out']);

    $dataset[] = [
        "instruction" => "Tu es CarAI, l'assistant expert de la plateforme CarEasy au Bénin. Réponds avec courtoisie et précision.",
        "input" => $input,
        "output" => $output
    ];
}

// 4. Sauvegarde en format JSONL
$fileContent = "";
foreach ($dataset as $line) {
    $fileContent .= json_encode($line, JSON_UNESCAPED_UNICODE) . "\n";
}

File::put(storage_path('carai_data.jsonl'), $fileContent);

return "Fichier carai_data.jsonl généré avec succès !";
