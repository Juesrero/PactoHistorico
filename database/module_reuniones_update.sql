USE pacto_asistencia;

/*
    Ajuste incremental del modulo de reuniones
    -----------------------------------------
    Este script agrega soporte para tipo de reunion sin romper
    el flujo actual de asistencia ni los datos historicos.
*/

SET @db_name = DATABASE();

SET @col_reuniones_tipo = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'reuniones'
      AND column_name = 'tipo_reunion'
);
SET @sql_reuniones_tipo = IF(
    @col_reuniones_tipo = 0,
    'ALTER TABLE reuniones ADD COLUMN tipo_reunion VARCHAR(80) NULL AFTER objetivo',
    'SELECT ''reuniones.tipo_reunion already exists'''
);
PREPARE stmt_reuniones_tipo FROM @sql_reuniones_tipo;
EXECUTE stmt_reuniones_tipo;
DEALLOCATE PREPARE stmt_reuniones_tipo;

UPDATE reuniones
SET tipo_reunion = COALESCE(NULLIF(TRIM(tipo_reunion), ''''), NULLIF(TRIM(organizacion), ''''), 'General')
WHERE tipo_reunion IS NULL
   OR TRIM(tipo_reunion) = '';

SET @sql_reuniones_tipo_not_null = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db_name
              AND table_name = 'reuniones'
              AND column_name = 'tipo_reunion'
              AND is_nullable = 'YES'
        ),
        'ALTER TABLE reuniones MODIFY tipo_reunion VARCHAR(80) NOT NULL',
        'SELECT ''reuniones.tipo_reunion already not null'''
    )
);
PREPARE stmt_reuniones_tipo_not_null FROM @sql_reuniones_tipo_not_null;
EXECUTE stmt_reuniones_tipo_not_null;
DEALLOCATE PREPARE stmt_reuniones_tipo_not_null;

SET @idx_reuniones_tipo_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'reuniones'
      AND index_name = 'idx_reuniones_tipo'
);
SET @sql_idx_reuniones_tipo = IF(
    @idx_reuniones_tipo_exists = 0,
    'CREATE INDEX idx_reuniones_tipo ON reuniones (tipo_reunion)',
    'SELECT ''idx_reuniones_tipo already exists'''
);
PREPARE stmt_idx_reuniones_tipo FROM @sql_idx_reuniones_tipo;
EXECUTE stmt_idx_reuniones_tipo;
DEALLOCATE PREPARE stmt_idx_reuniones_tipo;