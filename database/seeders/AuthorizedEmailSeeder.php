<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AuthorizedEmailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\AuthorizedEmail::updateOrCreate(
            ['email' => 'devanlustig@gmail.com'],
            [
                'name' => 'Ade Irfan Hilmi',
                'is_active' => true,
            ]
        );
    }
}
