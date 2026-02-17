<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LocationSeeder extends Seeder{
    public function run(): void {
        $csvFile = public_path('geo.csv');
        
        // On vérifie si le fichier existe
        if (!File::exists($csvFile)) {
            $this->command->error("Fichier geo.csv introuvable dans le dossier public.");
            return;
        }

        $data = fopen($csvFile, "r");
        
        $firstline = true;
        while (($row = fgetcsv($data, 1000, ",")) !== FALSE) {
            if (!$firstline) {
                DB::table('locations_benin')->insert([
                    "code_admin"   => $row[0],
                    "arrondissement" => $row[1],
                    "commune"      => $row[2],
                    "departement"  => $row[3],
                    "latitude"     => $row[4],
                    "longitude"    => $row[5],
                    "created_at"   => now(),
                    "updated_at"   => now(),
                ]);
            }
            $firstline = false;
        }
        fclose($data);
    }
}
