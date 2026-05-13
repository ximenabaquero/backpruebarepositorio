<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'firstuser@olga.com')->first();

        if ($user) {
            // Actualizar en caso de que role/status/password estén mal
            $user->password = Hash::make('password');
            $user->role     = User::ROLE_ADMIN;
            $user->status   = User::STATUS_ACTIVE;
            $user->save();
            echo "Usuario admin actualizado.\n";
        } else {
            $user = new User();
            $user->name       = 'admin';
            $user->email      = 'firstuser@olga.com';
            $user->password   = Hash::make('password');
            $user->first_name = 'Admin';
            $user->last_name  = 'OLGA';
            $user->role       = User::ROLE_ADMIN;
            $user->status     = User::STATUS_ACTIVE;
            $user->brand_name = 'Olga';
            $user->brand_slug = 'olga';
            $user->save();
            echo "Usuario admin creado.\n";
        }
    }
}
