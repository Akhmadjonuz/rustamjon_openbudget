<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Seeder;
use SebastianBergmann\CodeCoverage\Report\Xml\Project;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        ProjectFactory::new()->create([
            'endpoint' => json_encode(['https://openbudget.uz/boards/initiatives/initiative/32/42ae755b-2eca-42b8-924d-bd756fc88f31']),
            'per_referral_amount' => 5000,
            'per_vote_amount' => 5000,
            'card_number' => '8600120486785366',
            'phone_number' => '998902224311',
            'password' => '123456',
        ]);
    }
}
