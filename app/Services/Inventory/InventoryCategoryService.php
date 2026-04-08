<?php

namespace App\Services\Inventory;

use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Collection;

class InventoryCategoryService
{
    public function all(): Collection
    {
        return InventoryCategory::withCount('products')->get();
    }

    public function create(int $adminId, string $name): InventoryCategory
    {
        return InventoryCategory::create([
            'user_id' => $adminId,
            'name'    => $name,
        ]);
    }

    public function update(InventoryCategory $category, string $name): InventoryCategory
    {
        $category->update(['name' => $name]);

        return $category->fresh();
    }
}