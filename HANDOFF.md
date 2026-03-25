# HANDOFF - PactoH (Control de Asistencia)

## 1. Objetivo del proyecto
Aplicacion web local para control de asistencia de reuniones, desarrollada en:
- PHP puro (sin framework)
- MySQL
- Bootstrap 5 + CSS/JS propio
- PDO con sentencias preparadas
- Compatible con XAMPP

## 2. Estado actual (resumen)
El sistema ya tiene implementado:
- CRUD de reuniones
- CRUD de personas con busqueda
- Registro y eliminacion de asistencias desde el detalle de reunion
- Validaciones backend y frontend
- Proteccion CSRF
- Exportacion por reunion:
  - Excel (.xls HTML compatible con Excel)
  - PDF (TCPDF local)

Tambien incluye logo en interfaz y exportaciones.

## 3. Entorno esperado
- Ruta del proyecto: `C:\xampp\htdocs\PactoH`
- PHP: `C:\xampp\php\php.exe`
- DB por defecto:
  - host: `127.0.0.1`
  - puerto: `3306`
  - base: `pacto_asistencia`
  - usuario: `root`
  - password: vacio

## 4. Estructura del proyecto
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
├─ includes/
│  ├─ helpers.php
│  ├─ router.php
│  ├─ validator.php
│  └─ layout/
│     ├─ header.php
│     ├─ navbar.php
│     └─ footer.php
├─ pages/
│  ├─ dashboard.php
│  ├─ personas.php
│  ├─ reuniones.php
│  ├─ asistencias.php
│  └─ 404.php
├─ services/
│  ├─ PersonaService.php
│  ├─ ReunionService.php
│  └─ ExportService.php
├─ database/
│  ├─ schema.sql
│  ├─ module_1_2_update.sql
│  └─ module_asistencia_update.sql
├─ exports/
│  ├─ bootstrap.php
│  ├─ excel/
│  │  ├─ reunion_asistencia_excel.php
│  │  └─ README.txt
│  └─ pdf/
│     ├─ reunion_asistencia_pdf.php
│     └─ README.txt
├─ libs/
│  └─ tcpdf/ ... (libreria PDF local)
└─ Logo/
   ├─ pacto.png
   └─ pacto_pdf.jpg
```

## 5. Flujo de ejecucion (arquitectura)
1. `index.php` es el front controller.
2. Carga config, sesion, conexion, helpers, validator, router y servicios.
3. `includes/router.php` resuelve `?page=` hacia un archivo en `pages/`.
4. `includes/layout/header.php` y `footer.php` envuelven cada pagina.
5. Cada pagina maneja su logica `POST` + renderizado.

## 6. Rutas funcionales
Definidas en `includes/router.php`:
- `?page=dashboard`
- `?page=personas`
- `?page=reuniones`
- `?page=asistencias` (pantalla legado; la operacion principal de asistencia esta en detalle de reuniones)

## 7. Modulo Personas (`pages/personas.php`)
Acciones por `form_action`:
- `create_persona`
- `update_persona`
- `delete_persona`

Funcionalidades:
- Listado
- Busqueda por nombre/documento/celular (`GET q`)
- Alta
- Edicion
- Eliminacion con confirmacion JS (`data-confirm`)
- Regla de documento unico (consulta + indice unico DB)

Servicio usado:
- `services/PersonaService.php`

## 8. Modulo Reuniones + Asistencia (`pages/reuniones.php`)
Acciones reuniones:
- `create_reunion`
- `update_reunion`
- `delete_reunion`

Acciones asistencia desde detalle de reunion:
- `add_asistencia`
- `remove_asistencia`

Parametros clave:
- `action=detail&id={reunion}` para detalle completo
- `person_q` para buscar personas al agregar asistencia

Detalle de reunion muestra:
- Datos de reunion
- Totales: asistentes y testigos
- Form para agregar asistencia (persona existente)
- Tabla de asistentes de esa reunion
- Boton para quitar asistencia
- Botones exportar Excel/PDF

Servicio usado:
- `services/ReunionService.php`

## 9. Exportaciones
### 9.1 Excel
Archivo:
- `exports/excel/reunion_asistencia_excel.php?reunion_id={id}`

Caracteristicas:
- Descarga `.xls` con HTML tabular compatible con Excel
- BOM UTF-8 para caracteres especiales
- Incluye logo
- Incluye datos de reunion
- Incluye listado de asistentes
- Incluye total asistentes y total testigos

### 9.2 PDF
Archivo:
- `exports/pdf/reunion_asistencia_pdf.php?reunion_id={id}`

Caracteristicas:
- Usa TCPDF local (`libs/tcpdf`)
- Incluye logo
- Incluye datos de reunion
- Tabla de asistentes
- Totales al final
- Nombre de archivo: `asistencia_reunion_{id}_{YYYY-MM-DD}.pdf`

### 9.3 Bootstrap de export
Archivo:
- `exports/bootstrap.php`

Responsabilidad:
- Carga config comun para exportadores
- Valida `reunion_id`
- Redirecciona a `index.php?page=reuniones` con flash en caso de error

## 10. Nota importante sobre logo PDF
Problema historico:
- TCPDF arroja error con PNG de transparencia cuando no hay GD/Imagick.

Solucion aplicada:
- Se genero `Logo/pacto_pdf.jpg` (sin alpha) y el PDF lo usa primero.
- Fallback: JPG/JPEG y luego PNG.

Metodo relevante:
- `ExportService::resolveLogoPathForPdf()`

## 11. Base de datos
Script base:
- `database/schema.sql`

Tablas:
- `reuniones`
- `personas`
- `asistencias`

Reglas clave:
- `personas.numero_documento` unico
- `asistencias (reunion_id, persona_id)` unico
- FK con cascade en asistencias

Scripts de ajuste:
- `database/module_1_2_update.sql` (indices en personas/reuniones)
- `database/module_asistencia_update.sql` (indices/constraint asistencia)

## 12. Helpers y validaciones
Helpers principales en `includes/helpers.php`:
- URL/ruteo: `url`, `page_url`, `page_url_with_query`
- Redireccion: `redirect_to`, `redirect_to_url`
- Flash messages: `flash`, `get_flash`
- CSRF: `csrf_token`, `csrf_field`, `verify_csrf`
- Request safe parsing: `request_string`, `request_int`

Validaciones en `includes/validator.php`:
- `validatePersona`
- `validateReunion`
- `validateAsistencia`

## 13. Seguridad actual
- PDO + consultas preparadas
- Escape HTML con `e()`
- Token CSRF en formularios POST
- Confirmaciones UI para borrados
- Validaciones server-side y client-side

## 14. Convenciones de codigo
- `declare(strict_types=1)` en archivos PHP principales
- Texto UI mayormente en ASCII (para evitar issues de encoding en entorno local)
- Se recomienda guardar PHP en UTF-8 **sin BOM**
  - Si aparece error `strict_types declaration must be the very first statement`, revisar BOM

## 15. Dependencias externas
- TCPDF local: `libs/tcpdf`
- No Composer
- No Node

## 16. QA rapido (manual)
1. Crear reunion.
2. Crear personas (incluyendo testigo).
3. En `Reuniones > Ver`, agregar asistentes por buscador.
4. Intentar registrar duplicado (debe mostrar alerta).
5. Quitar asistencia (debe funcionar y actualizar totales).
6. Exportar Excel (descarga correcta, tabla y totales).
7. Exportar PDF (descarga correcta y logo visible).

## 17. Comandos utiles
Lint de todo PHP:
```powershell
$phpFiles = Get-ChildItem -Recurse -File -Filter *.php | Select-Object -ExpandProperty FullName
foreach($file in $phpFiles){ & 'C:\xampp\php\php.exe' -l $file }
```

## 18. Riesgos / pendientes sugeridos
- `pages/asistencias.php` existe como pantalla separada legado; decidir si:
  - se mantiene, o
  - se simplifica/redirige a `reuniones?action=detail`
- Agregar paginacion para listados grandes
- Agregar auditoria/log de acciones CRUD/export
- Agregar control de usuarios/permisos (si entra autenticacion)
- Mejorar manejo de timezone/locale en exportes

## 19. Punto de partida recomendado para el siguiente agente
Si hay que continuar desarrollo, empezar por:
1. Leer `pages/reuniones.php` y `services/ReunionService.php` (nucleo funcional actual).
2. Revisar `exports/*` para cualquier cambio de formato.
3. Ejecutar QA rapido del punto 16.
4. Mantener consistencia con `helpers.php` y `validator.php`.

## 20. Observaciones de handoff
- Proyecto no esta en repo git dentro de esta carpeta (no hay `.git` local).
- Ultimo ajuste tecnico: export PDF robusto para entornos sin GD/Imagick.
