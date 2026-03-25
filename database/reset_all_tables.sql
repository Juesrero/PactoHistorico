USE pacto_asistencia;

/*
    Reinicio total de datos del sistema
    -----------------------------------
    - Elimina todos los registros de las tablas principales
    - Reinicia los AUTO_INCREMENT
    - No elimina tablas ni estructura
    - No borra archivos fisicos en storage/

    Tablas contempladas:
    - asistencias
    - personas
    - reuniones
    - actas
    - tipos_poblacion
    - consecutivos
    - acta_adjuntos (solo si existe en una instalacion legado)

    Recomendacion:
    - Hacer backup antes de ejecutar este script
*/

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM asistencias;
DELETE FROM personas;
DELETE FROM reuniones;
DELETE FROM actas;
DELETE FROM tipos_poblacion;
DELETE FROM consecutivos;

SET @db_name = DATABASE();
SET @legacy_acta_adjuntos_exists = (
    SELECT COUNT(1)
    FROM information_schema.tables
    WHERE table_schema = @db_name
      AND table_name = 'acta_adjuntos'
);

SET @sql_truncate_acta_adjuntos = IF(
    @legacy_acta_adjuntos_exists > 0,
    'DELETE FROM acta_adjuntos',
    'SELECT ''acta_adjuntos does not exist'''
);

PREPARE stmt_truncate_acta_adjuntos FROM @sql_truncate_acta_adjuntos;
EXECUTE stmt_truncate_acta_adjuntos;
DEALLOCATE PREPARE stmt_truncate_acta_adjuntos;

ALTER TABLE asistencias AUTO_INCREMENT = 1;
ALTER TABLE personas AUTO_INCREMENT = 1;
ALTER TABLE reuniones AUTO_INCREMENT = 1;
ALTER TABLE actas AUTO_INCREMENT = 1;
ALTER TABLE tipos_poblacion AUTO_INCREMENT = 1;

SET @sql_reset_acta_adjuntos_ai = IF(
    @legacy_acta_adjuntos_exists > 0,
    'ALTER TABLE acta_adjuntos AUTO_INCREMENT = 1',
    'SELECT ''acta_adjuntos auto_increment skipped'''
);

PREPARE stmt_reset_acta_adjuntos_ai FROM @sql_reset_acta_adjuntos_ai;
EXECUTE stmt_reset_acta_adjuntos_ai;
DEALLOCATE PREPARE stmt_reset_acta_adjuntos_ai;

SET FOREIGN_KEY_CHECKS = 1;
