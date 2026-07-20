# Migración desde CodeRED-95/dni-api

## Diagnóstico del sistema legado

El repositorio `dni-api` es una aplicación FastAPI. Su endpoint principal es `GET /dni/{dni}`; autentica clientes con API keys propias y almacena personas en `dni_consultas`. PeruDevs se consume con `GET https://api.perudevs.com/api/v1/dni/complete`, usando `document` y `key` como query.

Se reutilizaron conceptualmente:

- prioridad de base local y fallback a PeruDevs;
- campos `perudevs_id`, nombres, género, fecha y código de verificación;
- validación del payload `estado/resultado`;
- persistencia posterior a una respuesta válida.

Se adaptaron:

- FastAPI/SQLAlchemy a Laravel/Eloquent;
- API keys heredadas a Sanctum con abilities;
- `dni_consultas` a `dni_records`;
- fecha string a columna DATE;
- logs con DNI plano a hash SHA-256;
- caché con DNI plano a claves hash;
- URL concatenada a parámetros seguros del Laravel HTTP Client.

No se migran las API keys del servicio anterior. Deben crearse clientes y tokens Sanctum nuevos. Tampoco se migran logs que contienen DNI plano.

## Importación de datos

Configurar una conexión de solo lectura mediante `DNI_LEGACY_DB_*` y ejecutar primero:

```bash
php artisan dni:import-legacy --dry-run
```

Después de revisar los conteos:

```bash
php artisan dni:import-legacy --chunk=500
```

El comando lee `dni_consultas`, conserva ceros iniciales, normaliza fechas `DD/MM/YYYY`, omite DNI existentes, usa transacciones por lote y nunca elimina datos.

No retirar `dni-api` hasta comparar conteos, probar muestras y confirmar que los consumidores utilizan tokens Sanctum nuevos.
