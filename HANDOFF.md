# HANDOFF - PactoH

## 1. Resumen del proyecto
Aplicacion web local para gestion de personas, reuniones, asistencia, actas y analisis sociodemografico.

Stack actual:
- PHP puro
- MySQL / MariaDB
- PDO con consultas preparadas
- Bootstrap 5
- CSS y JS propios
- TCPDF local para PDF
- Compatible con XAMPP

No usa framework, Composer ni Node.

## 2. Modulos existentes
### 2.1 Dashboard
- Ruta: `index.php?page=dashboard`
- Resumen general de reuniones, personas, asistencias y testigos
- Graficos simples en canvas
- Proxima reunion

### 2.2 Personas
- Ruta: `index.php?page=personas`
- CRUD de personas
- Busqueda, ordenamiento y paginacion
- Importacion masiva desde Excel `.xlsx`
- Gestion de tipos de poblacion configurables en la misma pantalla

Campos principales:
- identificacion / `numero_documento`
- nombres
- apellidos
- genero
- fecha de nacimiento
- correo
- telefono / `celular`
- direccion
- tipo de poblacion
- testigo
- jurado

### 2.3 Reuniones
- Ruta: `index.php?page=reuniones`
- CRUD de reuniones
- Detalle operativo de reunion
- Registro de asistencia desde el detalle
- Exportacion Excel/PDF por reunion

Campos principales:
- nombre de reunion
- objetivo
- tipo de reunion
- organizacion o convocatoria
- lugar
- fecha
- hora

### 2.4 Asistencia
- Flujo principal dentro del detalle de una reunion
- Ruta legado adicional: `index.php?page=asistencias`
- Prevencion de duplicados por `(reunion_id, persona_id)`
- Totales de asistentes y testigos
- Eliminacion con confirmacion

### 2.5 Actas
- Ruta: `index.php?page=actas`
- Listado de actas
- Creacion de acta
- Edicion de datos basicos
- Detalle de acta
- Adjuntar y descargar archivo

Formatos permitidos:
- PDF
- DOC
- DOCX

El consecutivo se genera automaticamente con formato tipo `ACTA-000001`.

### 2.6 Analisis sociodemografico
- Ruta: `index.php?page=analisis`
- Indicadores poblacionales calculados desde `personas`
- Filtros por genero, tipo de poblacion, testigo, jurado y rango de edad
- Graficos simples compatibles con el frontend actual

## 3. Cambios realizados en esta etapa
- Expansion del modulo de personas sin romper compatibilidad historica
- Creacion y administracion de `tipos_poblacion`
- Ajuste del modulo de reuniones para soportar el nuevo alcance
- Refinamiento visual y funcional de asistencia dentro del detalle de reunion
- Creacion del modulo de actas con upload y descarga segura
- Creacion del modulo de analisis sociodemografico
- Pulido final de UX:
  - alertas visuales mas consistentes
  - estados vacios mas claros
  - foco automatico al primer campo invalido
- Refuerzo de seguridad:
  - verificacion CSRF en formularios POST
  - validacion de archivos en actas
  - validacion mas estricta del import `.xlsx`

## 4. Estructura actual del proyecto
```text
PactoH/
├─ index.php
├─ README.md
├─ HANDOFF.md
├─ assets/
│  ├─ css/app.css
│  └─ js/app.js
├─ config/
│  ├─ app.php
│  ├─ database.php
│  └─ session.php
├─ database/
│  ├─ schema.sql
│  ├─ fase1_nuevo_alcance_propuesta.sql
│  ├─ module_personas_update.sql
│  ├─ module_reuniones_update.sql
│  └─ module_actas_create.sql
├─ docs/
│  ├─ diagnostico_fase1.md
│  ├─ nota_tecnica_personas_fase2.md
│  ├─ nota_tecnica_reuniones_fase2.md
│  ├─ nota_tecnica_asistencia_refinamiento.md
│  ├─ nota_tecnica_actas.md
│  └─ nota_tecnica_analisis_sociodemografico.md
├─ downloads/
│  ├─ bootstrap.php
│  └─ actas/
│     └─ descargar.php
├─ exports/
│  ├─ bootstrap.php
│  ├─ excel/
│  │  └─ reunion_asistencia_excel.php
│  └─ pdf/
│     └─ reunion_asistencia_pdf.php
├─ includes/
│  ├─ helpers.php
│  ├─ router.php
│  ├─ validator.php
│  └─ layout/
│     ├─ header.php
│     ├─ navbar.php
│     └─ footer.php
├─ libs/
│  └─ tcpdf/
├─ pages/
│  ├─ dashboard.php
│  ├─ personas.php
│  ├─ reuniones.php
│  ├─ actas.php
│  ├─ analisis.php
│  ├─ asistencias.php
│  └─ 404.php
├─ services/
│  ├─ ExportService.php
│  ├─ ActaService.php
│  ├─ AnalisisService.php
│  ├─ PersonaService.php
│  ├─ PersonaImportService.php
│  ├─ ReunionService.php
│  ├─ TipoPoblacionService.php
│  └─ XlsxReader.php
├─ storage/
│  ├─ .htaccess
│  ├─ index.html
│  └─ actas/
└─ Logo/
   ├─ pacto.png
   └─ pacto_pdf.jpg
```

## 5. Arquitectura de ejecucion
1. `index.php` funciona como front controller.
2. Carga configuracion, sesion, conexion, helpers, validadores y servicios.
3. `includes/router.php` resuelve la pagina por `?page=`.
4. Cada archivo en `pages/` maneja su logica de entrada y su renderizado.
5. `includes/layout/*` envuelve la salida HTML.

## 6. Base de datos final esperada
Base por defecto:
- host: `127.0.0.1`
- puerto: `3306`
- base: `pacto_asistencia`
- usuario: `root`
- password: vacio

Tablas principales:
- `personas`
- `tipos_poblacion`
- `reuniones`
- `asistencias`
- `consecutivos`
- `actas`

Tabla legado que puede seguir existiendo:
- `acta_adjuntos`

### 6.1 Tabla `personas`
Campos relevantes:
- `id`
- `nombres_apellidos`
- `nombres`
- `apellidos`
- `numero_documento`
- `genero`
- `fecha_nacimiento`
- `correo`
- `celular`
- `direccion`
- `tipo_poblacion_id`
- `es_testigo`
- `es_jurado`
- `created_at`
- `updated_at`

Reglas:
- `numero_documento` unico

### 6.2 Tabla `tipos_poblacion`
Campos relevantes:
- `id`
- `nombre`
- `descripcion`
- `activo`
- `created_at`
- `updated_at`

### 6.3 Tabla `reuniones`
Campos relevantes:
- `id`
- `nombre_reunion`
- `objetivo`
- `tipo_reunion`
- `organizacion`
- `lugar_reunion`
- `fecha`
- `hora`
- `created_at`
- `updated_at`

### 6.4 Tabla `asistencias`
Campos relevantes:
- `id`
- `reunion_id`
- `persona_id`
- `fecha_registro`
- `hora_registro`
- `observacion`
- `created_at`

Reglas:
- unico por `reunion_id + persona_id`
- FK con cascade a `reuniones` y `personas`

### 6.5 Tabla `consecutivos`
Campos relevantes:
- `modulo` o `clave` segun historial de la base
- `ultimo_numero`
- `updated_at` o `fecha_actualizacion`

El servicio de actas ya soporta ambas variantes.

### 6.6 Tabla `actas`
Campos relevantes esperados:
- `id`
- `consecutivo`
- `nombre_o_objetivo`
- `responsable`
- `lugar`
- `nombre_archivo_original`
- `ruta_archivo`
- `tipo_mime`
- `fecha_creacion`
- `fecha_actualizacion`

Compatibilidad:
- si la tabla venia de un esquema legado, `module_actas_create.sql` la migra de forma incremental

## 7. Scripts SQL importantes
- `database/schema.sql`
  - esquema base completo para instalaciones nuevas
- `database/module_personas_update.sql`
  - ajuste incremental de personas
- `database/module_reuniones_update.sql`
  - ajuste incremental de reuniones
- `database/module_actas_create.sql`
  - creacion/migracion incremental del modulo de actas

## 8. Pasos de instalacion en XAMPP
1. Copiar el proyecto a `C:\xampp\htdocs\PactoH`
2. Iniciar Apache y MySQL desde XAMPP
3. Crear la base o ejecutar `database/schema.sql`
4. Si se parte de una instalacion anterior, ejecutar los scripts incrementales necesarios:
   - `database/module_personas_update.sql`
   - `database/module_reuniones_update.sql`
   - `database/module_actas_create.sql`
5. Verificar permisos de escritura en:
   - `storage/`
   - `storage/actas/`
6. Abrir en navegador:
   - `http://localhost/PactoH/index.php?page=dashboard`

## 9. Rutas importantes
- Dashboard:
  - `index.php?page=dashboard`
- Personas:
  - `index.php?page=personas`
- Reuniones:
  - `index.php?page=reuniones`
- Actas:
  - `index.php?page=actas`
- Analisis:
  - `index.php?page=analisis`
- Asistencias legado:
  - `index.php?page=asistencias`

Exportaciones:
- Excel asistencia:
  - `exports/excel/reunion_asistencia_excel.php?reunion_id={id}`
- PDF asistencia:
  - `exports/pdf/reunion_asistencia_pdf.php?reunion_id={id}`

Descarga de actas:
- `downloads/actas/descargar.php?id={id}`

## 10. Seguridad actual
- CSRF en todos los formularios POST de modulos principales
- Escape HTML con `e()`
- PDO con consultas preparadas en CRUD y filtros
- Confirmaciones visuales para acciones destructivas
- Upload de actas validado por:
  - extension
  - MIME con `finfo`
  - tamano maximo
  - `is_uploaded_file`
  - nombres saneados
- Import Excel validado por:
  - extension `.xlsx`
  - MIME permitido
  - tamano maximo
  - archivo temporal valido

## 11. QA manual recomendado
1. Crear una persona con tipo de poblacion, testigo y jurado.
2. Editar y buscar personas por identificacion y nombre.
3. Importar un Excel `.xlsx` valido y validar errores controlados.
4. Crear una reunion.
5. Desde el detalle de reunion, registrar asistencia y probar duplicado.
6. Quitar asistencia con confirmacion.
7. Exportar asistencia a Excel.
8. Exportar asistencia a PDF.
9. Crear un acta sin archivo.
10. Crear o actualizar un acta con PDF, DOC o DOCX.
11. Descargar el archivo del acta.
12. Abrir `Analisis` y probar filtros.

## 12. Pendientes futuros sugeridos
- Decidir si `pages/asistencias.php` se mantiene o se redirige al detalle de reuniones
- Agregar autenticacion y control de permisos si el sistema deja de ser solo local
- Agregar auditoria de acciones CRUD y descargas
- Mejorar exportaciones para incluir mas metadatos si negocio lo requiere
- Evaluar normalizar completamente actas y retirar tablas/campos legado cuando ya no se necesiten
- Agregar pruebas funcionales automatizadas si el proyecto crece

## 13. Observaciones finales
- Mantener PHP en UTF-8 sin BOM
- No usar `git reset --hard` ni cambios destructivos en instalaciones ya pobladas
- Si aparece un error por actas en una base vieja, volver a ejecutar `database/module_actas_create.sql`
- No se ha migrado a framework y no se recomienda hacerlo en caliente sin una fase aparte
