<?php

namespace Database\Factories;

use App\Models\ProcedureItem;
use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureItemFactory extends Factory
{
    protected $model = ProcedureItem::class;

    public function definition(): array
    {
        return [
            // ✅ procedure_id se sobreescribe siempre desde el seeder
            'procedure_id' => Procedure::inRandomOrder()->first()?->id,
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
            // Precios en COP realistas (sin decimales)
            'price' => $this->faker->randomElement([
                50000, 80000, 100000, 120000,
                150000, 180000, 200000, 250000,
                300000, 350000, 400000, 500000,
            ]),
        ];
    }
}