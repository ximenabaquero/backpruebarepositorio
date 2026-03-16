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

        // ── 2. Remitentes con distintos estados ───────────────────────────
        $remitentesActivos    = User::factory()->remitente()->count(3)->create();
        $remitentesInactivos  = User::factory()->remitente()->inactivo()->count(2)->create();
        $remitentesDespedidos = User::factory()->remitente()->despedido()->count(2)->create();

        // ── 3. Pacientes del admin ────────────────────────────────────────
        // El admin también es médico y registra sus propios pacientes
        Patient::factory()
            ->count(rand(2, 4))
            ->forRemitente($admin)
            ->create()
            ->each(fn(Patient $p) => $this->seedEvaluaciones($p, $admin));

        // ── 4. Pacientes de remitentes activos ────────────────────────────
        // Solo los activos generan registros — inactivos/despedidos
        // existen para probar que no pueden operar en el sistema
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

    /**
     * Crea evaluaciones y procedimientos para un paciente.
     * Distribuye fechas a lo largo del año actual.
     * referrer_name coincide con $user->name — igual que en producción.
     */
    private function seedEvaluaciones(Patient $patient, User $user): void
    {
        $now              = Carbon::now();
        $mesesDisponibles = range(1, $now->month);

        for ($i = 0; $i < rand(2, 4); $i++) {
            $statusRoll = rand(1, 100);

            // Distribución realista: 60% confirmado, 25% cancelado, 15% en espera
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

            // Canceladas no tienen procedimiento — igual que en el flujo real
            if ($evaluation->status === MedicalEvaluation::STATUS_CANCELADO) {
                continue;
            }

            // ProcedureFactory::afterCreating() crea items y calcula total_amount
            Procedure::factory()->create([
                'medical_evaluation_id' => $evaluation->id,
                'procedure_date'        => $this->randomDateInYear($now, $mesesDisponibles),
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // Privado — inventario
    // ─────────────────────────────────────────────

    /**
     * Crea categorías, productos, compras y consumos.
     * Admin y remitentes activos registran compras y consumos propios.
     */
    private function seedInventario(User $admin, Collection $remitentesActivos): void
    {
        $now = Carbon::now();
        $mesesDisponibles = range(1, $now->month);

        // Categorías fijas y realistas
        $categorias = collect([
            ['name' => 'Insumos médicos',       'color' => '#3B82F6'],
            ['name' => 'Productos cosméticos',  'color' => '#EC4899'],
            ['name' => 'Equipos y herramientas','color' => '#8B5CF6'],
            ['name' => 'Medicamentos',          'color' => '#10B981'],
            ['name' => 'Aseo y limpieza',       'color' => '#F59E0B'],
        ])->map(fn($data) => InventoryCategory::factory()->create([
            'user_id' => $admin->id,
            'name'    => $data['name'],
            'color'   => $data['color'],
        ]));

        // Productos vinculados a categorías
        $productos = $categorias->flatMap(fn(InventoryCategory $cat) =>
            InventoryProduct::factory()
                ->count(rand(2, 4))
                ->create(['category_id' => $cat->id])
        );

        // Todos los usuarios (admin + remitentes activos) registran compras
        $todosLosUsuarios = $remitentesActivos->prepend($admin);

        $todosLosUsuarios->each(function (User $user) use ($categorias, $productos, $mesesDisponibles, $now) {
            // Compras del año distribuidas en distintos meses
            collect(range(1, rand(3, 6)))->each(function () use ($user, $categorias, $productos, $mesesDisponibles, $now) {
                $mes         = fake()->randomElement($mesesDisponibles);
                $producto    = fake()->optional(0.6)->randomElement($productos->all());
                $categoria   = $producto
                    ? $productos->firstWhere('id', $producto->id)?->category_id
                    : $categorias->random()->id;

                $quantity  = rand(1, 10);
                $unitPrice = fake()->randomElement([15000, 25000, 45000, 60000, 80000, 100000]);

                InventoryPurchase::factory()->create([
                    'user_id'       => $user->id,
                    'category_id'   => $categoria,
                    'product_id'    => $producto?->id,
                    'item_name'     => $producto?->name ?? fake()->words(3, true),
                    'quantity'      => $quantity,
                    'unit_price'    => $unitPrice,
                    'total_price'   => $quantity * $unitPrice,
                    'purchase_date' => $this->randomDateInYear($now, [$mes]),
                ]);
            });

            // Consumos de productos con stock disponible
            $productosConStock = $productos->where('stock', '>', 0);
            if ($productosConStock->isEmpty()) return;

            collect(range(1, rand(2, 4)))->each(function () use ($user, $productosConStock, $mesesDisponibles, $now) {
                $producto = $productosConStock->random();
                $cantidad = rand(1, min(3, $producto->stock));

                InventoryUsage::factory()->create([
                    'user_id'    => $user->id,
                    'product_id' => $producto->id,
                    'quantity'   => $cantidad,
                    'usage_date' => $this->randomDateInYear($now, $mesesDisponibles),
                ]);

                // Actualizar stock en memoria para evitar sobreconsumo
                $producto->stock -= $cantidad;
            });
        });
    }

    /**
     * Genera una fecha aleatoria dentro del año actual.
     * Si es el mes en curso, no supera el día de hoy.
     */
    private function randomDateInYear(Carbon $now, array $meses): string
    {
        $mes         = fake()->randomElement($meses);
        $daysInMonth = Carbon::create($now->year, $mes, 1)->daysInMonth;
        $maxDay      = ($mes === $now->month) ? $now->day : $daysInMonth;

        return Carbon::create($now->year, $mes, rand(1, max(1, $maxDay)))->toDateString();
    }
}