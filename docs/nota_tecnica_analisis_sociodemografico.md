# Nota tecnica - Analisis sociodemografico

## Alcance implementado
- Nuevo modulo `Analisis` integrado al menu principal.
- Indicadores calculados a partir de `personas` y `tipos_poblacion`.
- Filtros GET para:
  - genero
  - tipo de poblacion
  - testigo
  - jurado
  - rango de edad

## Indicadores
- Total de personas registradas
- Total de testigos
- Total de jurados
- Total con fecha de nacimiento
- Total sin fecha de nacimiento
- Totales por genero
- Totales por tipo de poblacion
- Distribucion por rangos de edad:
  - 0 a 17
  - 18 a 28
  - 29 a 40
  - 41 a 59
  - 60 o mas

## Calculo de edad
- No se almacena la edad en base de datos.
- La edad se calcula en tiempo real con `TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE())`.

## Archivos agregados o ajustados
- `services/AnalisisService.php`
- `pages/analisis.php`
- `includes/router.php`
- `includes/layout/navbar.php`
- `index.php`
- `assets/js/app.js`
- `assets/css/app.css`

## Consulta y rendimiento
- El modulo usa consultas agregadas con `COUNT` y `SUM(CASE ...)`.
- Los filtros se aplican en SQL antes de agrupar para reducir datos procesados en PHP.
- La distribucion por edad se calcula con una subconsulta derivada, sin persistir edades.
