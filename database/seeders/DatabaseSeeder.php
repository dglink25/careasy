<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder {
    use WithoutModelEvents;

    public function run(): void {
        User::factory()->create([
            'name'     => 'CarAI',
            'email'    => 'carai@careasy.ai',
            'password' => Hash::make('ai_careasy_2025'),
            'role'     => 'admin',
        ]);

        User::factory()->create([
            'name'     => 'CarEasy Support',
            'email'    => 'careasy26@gmail.com',
            'password' => Hash::make('123456789'),
            'role'     => 'admin',
        ]);

        $this->call([
            DomainesSeeder::class,
            LocationSeeder::class,   
            PlanSeeder::class,
        ]);
    }
}
