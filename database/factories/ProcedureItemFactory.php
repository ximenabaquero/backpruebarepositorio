<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Models\ProcedureItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureItemFactory extends Factory
{
    protected $model = ProcedureItem::class;

    public function definition(): array
    {
        return [
            // Factory lazy — crea un Procedure si no se pasa uno
            // Evita Procedure::inRandomOrder()->first() que rompe si no hay datos en DB
            'procedure_id' => Procedure::factory()->sinItems(),
            'item_name'    => $this->faker->randomElement([
                'Abdomen Completo',
                'Laterales',
                'Cintura',
                'Espalda Completa',
                'Coxis',
                'Brazos',
                'Láser Básico',
                'Láser Premium',
                'Post-operatorio',
                'Zonas Múltiples',
            ]),
            // Precios en COP realistas
            'price' => $this->faker->randomElement([
                50000, 80000, 100000, 120000,
                150000, 180000, 200000, 250000,
                300000, 350000, 400000, 500000,
            ]),
        ];
    }
}