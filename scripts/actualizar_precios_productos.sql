-- ============================================================================
-- Script: Actualizar precios de productos desde última compra
-- Propósito: Llenar el campo unit_price de productos que están en 0
-- Fecha: 2026-04-22
-- Uso: Ejecutar UNA SOLA VEZ después de implementar el cambio en el código
-- ============================================================================

-- Este script actualiza el precio unitario (unit_price) de cada producto
-- tomando el precio de su compra más reciente.
-- Solo afecta productos que actualmente tienen unit_price = 0

UPDATE inventory_products AS p
INNER JOIN (
    SELECT 
        product_id,
        unit_price
    FROM inventory_purchases
    WHERE (product_id, purchase_date) IN (
        SELECT product_id, MAX(purchase_date)
        FROM inventory_purchases
        GROUP BY product_id
    )
) AS latest_purchase ON p.id = latest_purchase.product_id
SET p.unit_price = latest_purchase.unit_price
WHERE p.unit_price = 0;

-- Verificar el resultado
SELECT 
    id,
    name,
    unit_price,
    stock,
    type
FROM inventory_products
WHERE unit_price > 0
ORDER BY name;

-- ============================================================================
-- NOTAS IMPORTANTES:
-- ============================================================================
-- 1. Este script es OPCIONAL - solo necesario para productos existentes
-- 2. Después de ejecutarlo, las nuevas compras actualizarán automáticamente
-- 3. Si un producto nunca ha tenido compras, seguirá en 0 (es normal)
-- 4. Puedes ejecutar este script múltiples veces sin problemas (es idempotente)
-- ============================================================================
