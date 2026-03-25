# Nota tecnica - Modulo de actas

## Alcance implementado
- Nuevo modulo `Actas` integrado al menu principal y al router actual.
- CRUD basico orientado a:
  - listar actas
  - crear acta
  - ver detalle
  - editar datos basicos
  - adjuntar o reemplazar archivo
  - descargar archivo
- Generacion automatica de consecutivo con formato `ACTA-000001` usando la tabla `consecutivos`.

## Archivos agregados o ajustados
- `services/ActaService.php`
- `pages/actas.php`
- `downloads/bootstrap.php`
- `downloads/actas/descargar.php`
- `includes/router.php`
- `includes/layout/navbar.php`
- `includes/validator.php`
- `index.php`
- `assets/css/app.css`
- `database/schema.sql`
- `database/module_actas_create.sql`

## Decisiones tecnicas
- El download del adjunto no pasa por `index.php` porque el layout ya envia salida HTML. Por eso se creo un endpoint separado en `downloads/actas/descargar.php`, siguiendo el patron de exportaciones.
- Los archivos se almacenan en `storage/actas/{YYYY}/{MM}` para mantener orden cronologico.
- El nombre almacenado fisicamente se genera de forma segura con consecutivo + timestamp + sufijo aleatorio.
- El nombre original mostrado al usuario se sanea para evitar rutas o caracteres inseguros.
- Validaciones de upload en servidor:
  - `is_uploaded_file`
  - tamano maximo 10 MB
  - extension permitida: PDF, DOC, DOCX
  - verificacion MIME con `finfo`

## SQL
- Script incremental sugerido: `database/module_actas_create.sql`
- Tablas introducidas:
  - `consecutivos`
  - `actas`
- Compatibilidad adicional:
  - si `consecutivos` ya existia por la fase de diagnostico con la columna `modulo`, el modulo de actas ya la reutiliza
  - si existe una version con la columna `clave`, el servicio tambien la soporta para no romper instalaciones previas
  - si `actas` ya existia con el esquema legado (`reunion_id`, `titulo`, `fecha_acta`, `created_at`, `updated_at`), el script actual agrega los campos nuevos, vuelve `reunion_id` opcional, convierte `consecutivo` a texto y migra datos base al formato actual
  - si existe `acta_adjuntos`, el script copia el ultimo adjunto conocido hacia las columnas nuevas de `actas`

## Compatibilidad
- No se tocaron los flujos actuales de personas, reuniones, asistencia ni exportaciones.
- El modulo es aditivo y puede desplegarse ejecutando primero el SQL incremental.
