# Diagnostico Fase 1 - PactoH

Fecha: 2026-03-24

## 1. Objetivo de este diagnostico

Documentar el estado real del proyecto actual antes de ampliar alcance, manteniendo intacto lo ya funcional.

Este diagnostico fue elaborado leyendo:

- `HANDOFF.md`
- `index.php`
- `config/*`
- `includes/*`
- `pages/*`
- `services/*`
- `database/*.sql`
- `exports/*`

## 2. Resumen ejecutivo

El proyecto actual es una aplicacion web local para XAMPP orientada al control de asistencia de reuniones.

Stack real detectado:

- PHP puro
- MySQL
- PDO con consultas preparadas
- Bootstrap 5
- CSS y JS propio
- TCPDF local para exportacion PDF

Estado funcional actual confirmado:

- Dashboard operativo
- CRUD de personas
- CRUD de reuniones
- Registro y eliminacion de asistencias
- Exportacion por reunion a Excel y PDF
- Importacion masiva de personas desde Excel `.xlsx`

No se detectaron aun en el sistema:

- modulo de actas
- adjuntos PDF/DOC/DOCX
- consecutivo automatico para actas
- modulo de analisis sociodemografico
- campos extendidos de persona del nuevo alcance
- tipo de reunion en base de datos

## 3. Archivos principales del sistema

### Entrada y arquitectura base

- `index.php`: front controller. Carga configuracion, sesion, conexion, helpers, validadores, router y servicios.
- `includes/router.php`: resuelve `?page=` hacia archivos dentro de `pages/`.
- `includes/layout/header.php`, `includes/layout/navbar.php`, `includes/layout/footer.php`: layout global.
- `config/app.php`: constantes globales, version, `BASE_PATH`, `BASE_URL`, timezone.
- `config/database.php`: conexion PDO a la base `pacto_asistencia`.
- `config/session.php`: inicializacion de sesion.

### Modulos funcionales

- `pages/dashboard.php`: resumen general y graficos.
- `pages/personas.php`: CRUD de personas e importacion Excel.
- `pages/reuniones.php`: CRUD de reuniones y flujo principal de asistencias.
- `pages/asistencias.php`: flujo legado para registrar asistencias por pantalla separada.

### Servicios

- `services/PersonaService.php`: consultas CRUD, busqueda, paginacion e insercion masiva.
- `services/PersonaImportService.php`: importacion de personas desde `.xlsx`.
- `services/XlsxReader.php`: lector manual de Excel `.xlsx` sin Composer.
- `services/ReunionService.php`: CRUD de reuniones y manejo de asistentes por reunion.
- `services/ExportService.php`: armado de datos y resolucion de logo para exportaciones.

### Base de datos y exportacion

- `database/schema.sql`: esquema base de tablas actuales.
- `database/module_1_2_update.sql`: indices complementarios.
- `database/module_asistencia_update.sql`: indice y constraint unico para asistencia.
- `exports/bootstrap.php`: bootstrap comun para exportadores.
- `exports/excel/reunion_asistencia_excel.php`: exportador Excel.
- `exports/pdf/reunion_asistencia_pdf.php`: exportador PDF.

## 4. Flujo actual por modulo

### 4.1 Flujo de personas

Archivo principal:

- `pages/personas.php`

Entradas y acciones detectadas:

- `GET page=personas`
- `GET q`: busqueda por nombre, documento o celular
- `GET sort`, `GET dir`, `GET p`: ordenamiento y paginacion
- `GET action=edit&id={persona}`
- `POST form_action=create_persona`
- `POST form_action=update_persona`
- `POST form_action=delete_persona`
- `POST form_action=import_personas_excel`

Comportamiento actual:

- crea, edita y elimina personas
- valida documento unico
- busca por nombre, documento y celular
- clasifica persona como testigo con `es_testigo`
- soporta importacion masiva por Excel

Campos actuales de formulario:

- `nombres_apellidos`
- `numero_documento`
- `celular`
- `es_testigo`

Dependencias directas:

- `services/PersonaService.php`
- `services/PersonaImportService.php`
- `includes/validator.php`

### 4.2 Flujo de reuniones

Archivo principal:

- `pages/reuniones.php`

Entradas y acciones detectadas:

- `GET page=reuniones`
- `GET action=edit&id={reunion}`
- `GET action=detail&id={reunion}`
- `GET person_q`: filtro de personas disponibles al agregar asistencia
- `POST form_action=create_reunion`
- `POST form_action=update_reunion`
- `POST form_action=delete_reunion`

Comportamiento actual:

- crea, edita y elimina reuniones
- lista reuniones con total de asistentes
- permite ver detalle de una reunion
- expone exportacion Excel y PDF desde el detalle

Campos actuales de reunion:

- `nombre_reunion`
- `objetivo`
- `organizacion`
- `lugar_reunion`
- `fecha`
- `hora`

Dependencias directas:

- `services/ReunionService.php`
- `includes/validator.php`
- `exports/*`

### 4.3 Flujo de asistencia

Flujo principal actual:

- dentro de `pages/reuniones.php`, en `action=detail`

Acciones detectadas:

- `POST form_action=add_asistencia`
- `POST form_action=remove_asistencia`

Comportamiento actual:

- muestra asistentes por reunion
- busca personas disponibles que aun no estan asignadas a la reunion
- registra asistencia con fecha y hora actuales
- evita duplicados por indice unico en BD
- permite eliminar asistencia existente

Flujo legado:

- `pages/asistencias.php`

Observacion:

- el sistema tiene dos entradas para asistencia, pero el flujo principal y mas coherente con el estado actual es el detalle de reunion.

## 5. Estructura actual de base de datos

Base configurada:

- `pacto_asistencia`

Tablas actuales:

### `personas`

Columnas actuales:

- `id`
- `nombres_apellidos`
- `numero_documento`
- `celular`
- `es_testigo`
- `created_at`
- `updated_at`

Reglas actuales:

- `numero_documento` unico

### `reuniones`

Columnas actuales:

- `id`
- `nombre_reunion`
- `objetivo`
- `organizacion`
- `lugar_reunion`
- `fecha`
- `hora`
- `created_at`
- `updated_at`

### `asistencias`

Columnas actuales:

- `id`
- `reunion_id`
- `persona_id`
- `fecha_registro`
- `hora_registro`
- `observacion`
- `created_at`

Reglas actuales:

- unico por `(reunion_id, persona_id)`
- FK a `reuniones`
- FK a `personas`
- `ON DELETE CASCADE`

## 6. Que existe frente al nuevo alcance

### Ya existe

- personas
- reuniones
- asistencia
- dashboard general
- exportacion Excel/PDF
- validaciones backend y frontend
- proteccion CSRF

### Existe parcialmente

- personas: existe solo version basica
- reuniones: existe, pero sin `tipo`
- dashboard: existe, pero aun no es analisis sociodemografico

### No existe todavia

- campos extendidos de persona:
  - tipo de identificacion
  - nombres
  - apellidos
  - genero
  - fecha de nacimiento
  - correo
  - telefono
  - direccion
  - tipo de poblacion configurable
  - jurado
- modulo de actas
- consecutivo automatico de actas
- adjuntos PDF/DOC/DOCX
- modulo de analisis sociodemografico

## 7. Brecha tecnica respecto al nuevo alcance

### Personas

El modelo actual de `personas` es insuficiente para el nuevo alcance porque condensa el nombre completo en un solo campo y solo guarda documento, celular y bandera de testigo.

Para no romper el sistema actual, conviene extender la tabla existente de forma aditiva, manteniendo:

- `nombres_apellidos`
- `numero_documento`
- `celular`
- `es_testigo`

y agregando nuevos campos sin eliminar los actuales.

### Reuniones

El modulo actual de reuniones ya sirve como base. El ajuste minimo de base de datos es agregar `tipo_reunion` o una referencia equivalente, manteniendo `organizacion` mientras se valida si sigue siendo necesaria para negocio.

### Asistencia

La tabla actual de `asistencias` es suficiente para esta fase. No requiere rediseno para soportar el nuevo alcance base.

### Actas

No existe infraestructura para actas. Haran falta, como minimo:

- tabla de actas
- tabla de adjuntos de acta
- mecanismo de consecutivo automatico
- carpeta de almacenamiento de adjuntos en disco
- nuevo servicio, pagina y validaciones

### Analisis sociodemografico

No existe modulo dedicado. La base actual tampoco soporta ese analisis porque faltan dimensiones de persona como genero, fecha de nacimiento, jurado y tipo de poblacion.

## 8. Archivos que conviene modificar en la siguiente fase

### Extender personas

- `pages/personas.php`
- `services/PersonaService.php`
- `services/PersonaImportService.php`
- `includes/validator.php`
- `database/schema.sql`

### Extender reuniones

- `pages/reuniones.php`
- `services/ReunionService.php`
- `includes/validator.php`
- `database/schema.sql`

### Incorporar actas

- `includes/router.php`
- `includes/layout/navbar.php`
- `pages/actas.php` o `pages/actas/` si se decide separar vistas
- `services/ActaService.php`
- `includes/validator.php`
- `database/schema.sql`
- nuevo directorio de almacenamiento de adjuntos

### Incorporar analisis sociodemografico

- `includes/router.php`
- `includes/layout/navbar.php`
- `pages/analisis.php`
- servicio nuevo para consultas agregadas
- posibles ajustes en `assets/js/app.js` y `assets/css/app.css`

## 9. Tablas que conviene ajustar o crear

### Ajustar

- `personas`
  - agregar campos del nuevo alcance
- `reuniones`
  - agregar `tipo_reunion`

### Crear

- `tipos_poblacion`
  - catalogo configurable
- `consecutivos`
  - control de consecutivo automatico para modulos como actas
- `actas`
  - cabecera de acta relacionada con reunion
- `acta_adjuntos`
  - archivos PDF/DOC/DOCX asociados al acta

## 10. Estrategia recomendada para mantener intacto lo funcional

1. No eliminar columnas actuales ni renombrarlas en la primera migracion.
2. Agregar columnas nuevas en `personas` y `reuniones`.
3. Mantener `pages/asistencias.php` como legado hasta completar el flujo consolidado.
4. Crear los nuevos modulos como adicionales, no como reemplazo inmediato.
5. Introducir migraciones SQL aditivas y reversibles en lo posible.
6. Posponer cualquier normalizacion fuerte de nombres hasta despues de estabilizar formularios y consultas.

## 11. Respaldo logico y criterio aplicado

En esta fase no se tocaron modulos funcionales ni se ejecutaron cambios sobre la base de datos. Por eso no se genero un backup logico nuevo dentro del proyecto.

El baseline funcional actual queda representado por:

- `HANDOFF.md`
- `database/schema.sql`
- `database/module_1_2_update.sql`
- `database/module_asistencia_update.sql`

Antes de aplicar SQL en una siguiente fase, si se quiere mayor seguridad operativa, se recomienda generar un respaldo de estructura, por ejemplo:

```powershell
mysqldump --no-data -u root pacto_asistencia > C:\xampp\htdocs\PactoH\database\backup_schema_pre_fase2.sql
```

## 12. Entregables de esta fase

- este archivo: `docs/diagnostico_fase1.md`
- script de propuesta no ejecutado: `database/fase1_nuevo_alcance_propuesta.sql`

## 13. Conclusion

La base actual del proyecto es util y aprovechable. No hace falta rehacer el sistema desde cero.

La ruta menos riesgosa es:

- extender `personas`
- extender `reuniones`
- mantener `asistencias`
- agregar `actas` como modulo nuevo
- construir `analisis sociodemografico` sobre los nuevos campos de persona

Todo lo anterior puede hacerse de forma incremental y compatible con el estado actual del proyecto.
