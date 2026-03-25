# Nota tecnica - Ajuste del modulo de personas

Fecha: 2026-03-24

## Cambios principales

Se extendio el modulo de personas sin rehacer el proyecto ni romper la funcionalidad existente.

Se incorporaron:

- nuevos campos de persona: identificacion, nombres, apellidos, genero, fecha de nacimiento, correo, telefono, direccion, tipo de poblacion y jurado
- gestion de `tipos_poblacion` dentro del mismo modulo de personas
- validaciones nuevas para identificacion unica, correo opcional valido y fecha valida
- busqueda y listado ampliados
- compatibilidad de importacion Excel con formato nuevo y formato historico

## Decision de compatibilidad

Para no romper asistencia, dashboard ni exportaciones, se mantuvieron estas columnas historicas:

- `numero_documento`: sigue siendo la identificacion unica
- `celular`: sigue siendo el telefono principal almacenado
- `nombres_apellidos`: se sigue llenando automaticamente a partir de `nombres` + `apellidos`
- `es_testigo`: se conserva y ahora se complementa con `es_jurado`

## Archivos tocados

- `index.php`
- `includes/validator.php`
- `pages/personas.php`
- `services/PersonaService.php`
- `services/PersonaImportService.php`
- `services/TipoPoblacionService.php`
- `database/schema.sql`
- `database/module_personas_update.sql`

## Script SQL a ejecutar en entornos existentes

Ejecutar:

- `database/module_personas_update.sql`

Ese script:

- crea `tipos_poblacion`
- agrega columnas nuevas en `personas`
- agrega indices
- crea la relacion con `tipos_poblacion`
- realiza un backfill minimo no destructivo sobre `nombres`

## Observaciones

- No se modificaron `pages/reuniones.php`, `pages/asistencias.php` ni los exportadores.
- El modulo sigue usando el estilo actual de Bootstrap 5 y tarjetas compactas.
- Si existen registros historicos, conviene revisarlos y completar apellidos donde solo quede nombre completo migrado a `nombres`.