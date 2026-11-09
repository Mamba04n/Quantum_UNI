# Eloquent Models

Esta carpeta se mantiene como la capa estable de persistencia Eloquent del proyecto.

Aunque la logica de negocio ya se esta moviendo a `app/Domain`, los modelos siguen en
`App\Models` porque ese namespace esta usado por relaciones, policies, factories,
seeders, migrations, Livewire, controladores y tests.

## Regla Actual

- Los modelos representan persistencia y relaciones Eloquent.
- La logica de proceso debe vivir en `app/Domain/{Modulo}`.
- Los servicios legacy compatibles viven en `app/Services` como fachadas delgadas.
- No mover modelos a namespaces de dominio sin una fase dedicada de compatibilidad.

## Ownership Modular

El ownership de cada modelo se documenta en:

- `docs/model-ownership-map.md`

Ese mapa define que modulo es responsable de cada modelo aunque fisicamente siga en
`app/Models`.

## Como Agregar un Modelo Nuevo

1. Crear el modelo en `app/Models`.
2. Mantenerlo delgado: casts, fillable, relaciones y accessors simples.
3. Crear la logica del proceso en `app/Domain/{Modulo}/{Proceso}`.
4. Registrar el ownership en `docs/model-ownership-map.md`.
5. Agregar factory/test si el modelo participa en un flujo critico.

## Por Que No Se Divide Por Carpetas Todavia

Mover fisicamente modelos como `App\Models\QuotationRequest` a
`App\Domain\Sales\Requests\Models\QuotationRequest` cambia el FQCN. Eso puede romper:

- relaciones Eloquent declaradas con `::class`;
- factories que infieren el modelo por namespace;
- policies registradas contra `App\Models`;
- logs/auditorias que guardan `get_class($model)`;
- tests y seeders con imports directos;
- posibles relaciones polimorficas.

La separacion segura por ahora es de ownership y logica de negocio, no de namespace
Eloquent.
