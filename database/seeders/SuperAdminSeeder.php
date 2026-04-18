<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@skaie.com'],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt('SuperSecret123!'),
                'role'     => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Super admin created: superadmin@skaie.com');
    }
}
