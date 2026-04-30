<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->where('email', 'admin@admin.com')->first();

        if ($user === null) {
            User::query()->create([
                'name' => 'Admin',
                'email' => 'admin@admin.com',
                'password' => bcrypt('test@2026'),
            ]);
        }
    }
}
