USE pacto_asistencia;

SET @db_name = DATABASE();

SET @uq_asistencia_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'asistencias'
      AND index_name = 'uq_asistencia_reunion_persona'
      AND non_unique = 0
);
SET @sql_uq_asistencia = IF(
    @uq_asistencia_exists = 0,
    'ALTER TABLE asistencias ADD UNIQUE KEY uq_asistencia_reunion_persona (reunion_id, persona_id)',
    'SELECT ''uq_asistencia_reunion_persona already exists'''
);
PREPARE stmt_uq_asistencia FROM @sql_uq_asistencia;
EXECUTE stmt_uq_asistencia;
DEALLOCATE PREPARE stmt_uq_asistencia;

SET @idx_asistencia_reunion_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'asistencias'
      AND index_name = 'idx_asistencias_reunion'
);
SET @sql_idx_asistencia_reunion = IF(
    @idx_asistencia_reunion_exists = 0,
    'CREATE INDEX idx_asistencias_reunion ON asistencias (reunion_id)',
    'SELECT ''idx_asistencias_reunion already exists'''
);
PREPARE stmt_idx_asistencia_reunion FROM @sql_idx_asistencia_reunion;
EXECUTE stmt_idx_asistencia_reunion;
DEALLOCATE PREPARE stmt_idx_asistencia_reunion;

SET @idx_asistencia_persona_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'asistencias'
      AND index_name = 'idx_asistencias_persona'
);
SET @sql_idx_asistencia_persona = IF(
    @idx_asistencia_persona_exists = 0,
    'CREATE INDEX idx_asistencias_persona ON asistencias (persona_id)',
    'SELECT ''idx_asistencias_persona already exists'''
);
PREPARE stmt_idx_asistencia_persona FROM @sql_idx_asistencia_persona;
EXECUTE stmt_idx_asistencia_persona;
DEALLOCATE PREPARE stmt_idx_asistencia_persona;
