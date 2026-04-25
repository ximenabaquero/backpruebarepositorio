# Changelog · XIMCA Backend

Formato: `[versión] YYYY-MM-DD — descripción`

---

## [1.0.0] 2026-03-14 — Versión inicial de producción

### Agregado
- API REST con Laravel 11 + Sanctum SPA stateful
- Autenticación por cookies httpOnly con regeneración de sesión (previene session fixation)
- Roles ADMIN y REMITENTE con `AdminMiddleware`
- CRUD de pacientes con verificación de cédula duplicada (devuelve 200 si ya existe)
- Valoraciones médicas: cálculo automático de IMC (escala OMS, 8 categorías)
- Flujo de estados EN_ESPERA → CONFIRMADO / CANCELADO con auditoría completa
- Procedimientos con ítems y cálculo de total desde items
- Galería de imágenes clínicas antes/después en `storage/public`
- Dashboard estadístico: KPIs, top procedimientos por demanda e ingresos, series históricas
- Gestión de remitentes: crear, activar, inactivar, despedir (sin eliminar — preserva historial)
- `ClinicSeeder`: datos de prueba realistas (1 admin, 5 remitentes, 15 pacientes)
- Solo registros CONFIRMADOS cuentan en finanzas y estadísticas
- Módulo Google Calendar implementado (en pausa — descomentar en `routes/api.php`)

---

<!-- Agregar nuevas entradas arriba de esta línea -->
<!-- Formato: ## [x.x.x] YYYY-MM-DD — título corto -->
<!-- Secciones: Agregado | Cambiado | Corregido | Eliminado -->

para cargar docker ->