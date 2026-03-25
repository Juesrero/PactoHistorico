USE pacto_asistencia;

/*
    Siembra inicial de tipos de poblacion
    -------------------------------------
    Inserta 5 tipos de poblacion base sin duplicar nombres existentes.
*/

INSERT INTO tipos_poblacion (nombre, descripcion, activo)
SELECT 'Jovenes vulnerables', 'Personas jovenes en condicion de vulnerabilidad social o economica.', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Jovenes vulnerables')
);

INSERT INTO tipos_poblacion (nombre, descripcion, activo)
SELECT 'Mujeres cabeza de familia', 'Mujeres responsables del sostenimiento principal del hogar.', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Mujeres cabeza de familia')
);

INSERT INTO tipos_poblacion (nombre, descripcion, activo)
SELECT 'Desplazados', 'Personas victimas de desplazamiento forzado.', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Desplazados')
);

INSERT INTO tipos_poblacion (nombre, descripcion, activo)
SELECT 'Adulto mayor', 'Personas de 60 anos o mas.', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Adulto mayor')
);

INSERT INTO tipos_poblacion (nombre, descripcion, activo)
SELECT 'Poblacion con discapacidad', 'Personas con discapacidad fisica, sensorial, cognitiva o multiple.', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Poblacion con discapacidad')
);
