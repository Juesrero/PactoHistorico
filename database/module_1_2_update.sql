USE pacto_asistencia;

SET @db_name = DATABASE();

SET @idx_personas_nombre_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_nombre'
);
SET @sql_idx_personas_nombre = IF(
    @idx_personas_nombre_exists = 0,
    'CREATE INDEX idx_personas_nombre ON personas (nombres_apellidos)',
    'SELECT ''idx_personas_nombre already exists'''
);
PREPARE stmt_idx_personas_nombre FROM @sql_idx_personas_nombre;
EXECUTE stmt_idx_personas_nombre;
DEALLOCATE PREPARE stmt_idx_personas_nombre;

SET @idx_personas_documento_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_documento'
);
SET @sql_idx_personas_documento = IF(
    @idx_personas_documento_exists = 0,
    'CREATE INDEX idx_personas_documento ON personas (numero_documento)',
    'SELECT ''idx_personas_documento already exists'''
);
PREPARE stmt_idx_personas_documento FROM @sql_idx_personas_documento;
EXECUTE stmt_idx_personas_documento;
DEALLOCATE PREPARE stmt_idx_personas_documento;

SET @idx_personas_celular_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_celular'
);
SET @sql_idx_personas_celular = IF(
    @idx_personas_celular_exists = 0,
    'CREATE INDEX idx_personas_celular ON personas (celular)',
    'SELECT ''idx_personas_celular already exists'''
);
PREPARE stmt_idx_personas_celular FROM @sql_idx_personas_celular;
EXECUTE stmt_idx_personas_celular;
DEALLOCATE PREPARE stmt_idx_personas_celular;

SET @idx_reuniones_fecha_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'reuniones'
      AND index_name = 'idx_reuniones_fecha'
);
SET @sql_idx_reuniones_fecha = IF(
    @idx_reuniones_fecha_exists = 0,
    'CREATE INDEX idx_reuniones_fecha ON reuniones (fecha, hora)',
    'SELECT ''idx_reuniones_fecha already exists'''
);
PREPARE stmt_idx_reuniones_fecha FROM @sql_idx_reuniones_fecha;
EXECUTE stmt_idx_reuniones_fecha;
DEALLOCATE PREPARE stmt_idx_reuniones_fecha;
