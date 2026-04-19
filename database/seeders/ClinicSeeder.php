<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\InventoryUsage;
use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Admin fijo ─────────────────────────────────────────────────
        $admin = User::factory()->admin()->create([
            'name'       => 'admin',
            'email'      => 'admin@coldesthetic.com',
            'first_name' => 'Admin',
            'last_name'  => 'Sistema',
            'password'   => 'password',
        ]);

        // ── 2. Remitentes ─────────────────────────────────────────────────
        $remitentesActivos   = User::factory()->remitente()->count(3)->create();
        User::factory()->remitente()->inactivo()->count(2)->create();
        User::factory()->remitente()->despedido()->count(2)->create();

        // ── 3. Pacientes del admin ────────────────────────────────────────
        Patient::factory()
            ->count(rand(2, 4))
            ->forRemitente($admin)
            ->create()
            ->each(fn(Patient $p) => $this->seedEvaluaciones($p, $admin));

        // ── 4. Pacientes de remitentes activos ────────────────────────────
        $remitentesActivos->each(function (User $remitente) {
            Patient::factory()
                ->count(rand(3, 5))
                ->forRemitente($remitente)
                ->create()
                ->each(fn(Patient $p) => $this->seedEvaluaciones($p, $remitente));
        });

        // ── 5. Inventario ─────────────────────────────────────────────────
        $this->seedInventario($admin, $remitentesActivos);
    }

    // ─────────────────────────────────────────────
    // Privado — clínica
    // ─────────────────────────────────────────────

    private function seedEvaluaciones(Patient $patient, User $user): void
    {
        $now              = Carbon::now();
        $mesesDisponibles = range(1, $now->month);

        for ($i = 0; $i < rand(2, 4); $i++) {
            $statusRoll = rand(1, 100);

            $factory = match (true) {
                $statusRoll <= 60 => MedicalEvaluation::factory()->confirmado(),
                $statusRoll <= 85 => MedicalEvaluation::factory()->cancelado(),
                default           => MedicalEvaluation::factory()->enEspera(),
            };

            $evaluation = $factory->create([
                'user_id'       => $user->id,
                'patient_id'    => $patient->id,
                'referrer_name' => $user->name,
            ]);

            if ($evaluation->status === MedicalEvaluation::STATUS_CANCELADO) {
                continue;
            }

            Procedure::factory()->create([
                'medical_evaluation_id' => $evaluation->id,
                'procedure_date'        => $this->randomDateInYear($now, $mesesDisponibles),
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // Privado — inventario
    // ─────────────────────────────────────────────

    private function seedInventario(User $admin, Collection $remitentesActivos): void
    {
        $now              = Carbon::now();
        $mesesDisponibles = range(1, $now->month);

        // Categorías fijas
        $categorias = collect([
            'Insumos médicos',
            'Productos cosméticos',
            'Equipos y herramientas',
            'Medicamentos',
            'Aseo y limpieza',
        ])->map(fn($nombre) => InventoryCategory::factory()->create([
            'user_id' => $admin->id,
            'name'    => $nombre,
        ]));

        // Insumos consumibles por categoría
        $insumos = $categorias->flatMap(fn(InventoryCategory $cat) =>
            InventoryProduct::factory()
                ->insumo()
                ->count(rand(2, 3))
                ->create(['category_id' => $cat->id])
        );

        // Equipos (no consumibles, gasto único)
        $equipos = InventoryProduct::factory()
            ->equipo()
            ->count(2)
            ->create(['category_id' => $categorias->first()->id]);

        $todosLosProductos = $insumos->merge($equipos);
        $todosLosUsuarios  = $remitentesActivos->prepend($admin);

        // Compras distribuidas entre todos los usuarios
        $todosLosUsuarios->each(function (User $user) use ($todosLosProductos, $mesesDisponibles, $now) {
            collect(range(1, rand(3, 6)))->each(function () use ($user, $todosLosProductos, $mesesDisponibles, $now) {
                $producto  = $todosLosProductos->random();
                $quantity  = rand(1, 10);
                $unitPrice = fake()->randomElement([15000, 25000, 45000, 60000, 80000, 100000]);

                InventoryPurchase::factory()->create([
                    'user_id'       => $user->id,
                    'product_id'    => $producto->id,
                    'quantity'      => $quantity,
                    'unit_price'    => $unitPrice,
                    'total_price'   => $quantity * $unitPrice,
                    'purchase_date' => $this->randomDateInYear($now, $mesesDisponibles),
                ]);

                // Incrementar stock en memoria si es insumo
                if ($producto->type === 'insumo') {
                    $producto->stock += $quantity;
                }
            });
        });

        // Consumos solo de insumos con stock
        $todosLosUsuarios->each(function (User $user) use ($insumos, $mesesDisponibles, $now) {
            $insumosConStock = $insumos->where('stock', '>', 0);
            if ($insumosConStock->isEmpty()) return;

            collect(range(1, rand(2, 4)))->each(function () use ($user, $insumosConStock, $mesesDisponibles, $now) {
                $producto = $insumosConStock->random();
                $cantidad = rand(1, min(3, $producto->stock));

                InventoryUsage::factory()->sinPaciente()->create([
                    'user_id'    => $user->id,
                    'product_id' => $producto->id,
                    'quantity'   => $cantidad,
                    'usage_date' => $this->randomDateInYear($now, $mesesDisponibles),
                ]);

                $producto->stock -= $cantidad;
            });
        });
    }

    private function randomDateInYear(Carbon $now, array $meses): string
    {
        $mes         = fake()->randomElement($meses);
        $daysInMonth = Carbon::create($now->year, $mes)->daysInMonth;
        $maxDay      = ($mes === $now->month) ? $now->day : $daysInMonth;

        return Carbon::create($now->year, $mes, rand(1, max(1, $maxDay)))->toDateString();
    }
}