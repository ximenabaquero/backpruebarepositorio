<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\InventoryUsage;
use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ClinicSeeder::class);

        $this->command->newLine();
        $this->command->info('✅ Seed completado:');
        $this->command->table(
            ['Entidad', 'Total'],
            [
                ['Usuarios (admin)',        User::where('role', 'ADMIN')->count()],
                ['Usuarios (remitente)',    User::where('role', 'REMITENTE')->count()],
                ['  → activos',             User::where('role', 'REMITENTE')->where('status', 'active')->count()],
                ['  → inactivos',           User::where('role', 'REMITENTE')->where('status', 'inactive')->count()],
                ['  → despedidos',          User::where('role', 'REMITENTE')->where('status', 'fired')->count()],
                ['Pacientes',               Patient::count()],
                ['Evaluaciones',            MedicalEvaluation::count()],
                ['  → confirmadas',         MedicalEvaluation::where('status', 'CONFIRMADO')->count()],
                ['  → canceladas',          MedicalEvaluation::where('status', 'CANCELADO')->count()],
                ['  → en espera',           MedicalEvaluation::where('status', 'EN_ESPERA')->count()],
                ['Procedimientos',          Procedure::count()],
                ['Items de procedimiento',  ProcedureItem::count()],
                ['─────────────────────', '─────'],
                ['Categorías inventario',   InventoryCategory::count()],
                ['Productos inventario',    InventoryProduct::count()],
                ['Compras registradas',     InventoryPurchase::count()],
                ['Consumos registrados',    InventoryUsage::count()],
            ]
        );
        $this->command->newLine();
        $this->command->line('🔑 Credenciales admin:');
        $this->command->line('   Email:    admin@coldesthetic.com');
        $this->command->line('   Password: password');
        $this->command->newLine();
    }
}