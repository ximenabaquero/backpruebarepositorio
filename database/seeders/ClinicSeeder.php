<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'firstuser@olga.com'],
            [
                'name'       => 'admin',
                'first_name' => 'Admin',
                'last_name'  => 'OLGA',
                'password'   => 'password',
                'role'       => User::ROLE_ADMIN,
                'status'     => User::STATUS_ACTIVE,
            ]
        );
    }
}
