<<<<<<< HEAD
﻿# Sistema de Control de Asistencia (PHP + MySQL)

Proyecto local compatible con XAMPP para administrar reuniones y registrar asistentes.

## 1) Requisitos

- XAMPP (Apache + MySQL)
- PHP 8.1+ recomendado
- Navegador web

## 2) Ubicación del proyecto

Debe estar en:

`C:\xampp\htdocs\PactoH`

## 3) Crear base de datos

1. Inicia **Apache** y **MySQL** en XAMPP.
2. Abre [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
3. Entra a la pestaña **SQL**.
4. Ejecuta los scripts:
   - `C:\xampp\htdocs\PactoH\database\schema.sql`
   - `C:\xampp\htdocs\PactoH\database\module_1_2_update.sql`
   - `C:\xampp\htdocs\PactoH\database\module_asistencia_update.sql`

## 4) Configurar conexión PDO

Archivo: `C:\xampp\htdocs\PactoH\config\database.php`

Valores por defecto para XAMPP:

- host: `127.0.0.1`
- port: `3306`
- dbname: `pacto_asistencia`
- user: `root`
- password: `` (vacío)

Si tu entorno usa otra clave, actualiza `$dbPass`.

## 5) Ejecutar sistema

Abre en navegador:

- [http://localhost/PactoH](http://localhost/PactoH)
- o [http://localhost/PactoH/index.php](http://localhost/PactoH/index.php)

## 6) Logo

La interfaz y las exportaciones usan el logo:

- `/Logo/pacto.png`
- `Logo/pacto_pdf.jpg` (usado para PDF sin error de alpha channel)

Fallback local:

- `C:\xampp\htdocs\PactoH\Logo\pacto.png`

## 7) Exportación Excel y PDF por reunión

Desde el detalle de cada reunión (`Reuniones > Ver`) hay botones:

- `Exportar a Excel`
- `Exportar a PDF`

### Excel

Se genera archivo `.xls` compatible con Excel desde:

- `exports/excel/reunion_asistencia_excel.php?reunion_id=ID`

### PDF

Se genera archivo `.pdf` desde:

- `exports/pdf/reunion_asistencia_pdf.php?reunion_id=ID`

### Librería PDF usada (sin Composer)

Se incluye **TCPDF** local en:

- `C:\xampp\htdocs\PactoH\libs\tcpdf\tcpdf.php`

Si en tu entorno no está esa carpeta, copia manualmente desde:

- `C:\xampp\phpMyAdmin\vendor\tecnickcom\tcpdf`

hacia:

- `C:\xampp\htdocs\PactoH\libs\tcpdf`

## 8) Estructura principal

- `index.php` Front controller y ruteo por `?page=`
- `config/` Configuración de app, sesión y base de datos
- `includes/` Helpers, validador, router, layout
- `pages/` Dashboard, personas, reuniones y asistencias
- `services/` Servicios de negocio y exportación
- `database/` Scripts SQL
- `exports/` Endpoints de exportación
- `libs/tcpdf/` Librería PDF local

=======
# PactoHistorico
>>>>>>> 27c0da9444594feac59f234ab3aed8577fbefddb
