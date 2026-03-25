USE pacto_asistencia;

/*
    Ajuste incremental del modulo de personas
    ----------------------------------------
    Este script es seguro para una base existente y mantiene compatibilidad
    con el sistema actual:

    - conserva numero_documento como identificacion unica
    - conserva celular como telefono principal
    - conserva nombres_apellidos para no romper asistencia ni exportaciones
    - agrega datos nuevos para el modulo de personas
    - crea el catalogo configurable de tipos_poblacion
*/

SET @db_name = DATABASE();

CREATE TABLE IF NOT EXISTS tipos_poblacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tipos_poblacion_nombre (nombre)
) ENGINE=InnoDB;

INSERT INTO tipos_poblacion (nombre, descripcion, activo)
SELECT 'General', 'Tipo de poblacion por defecto', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tipos_poblacion
    WHERE nombre = 'General'
);

SET @col_personas_nombres = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'nombres'
);
SET @sql_personas_nombres = IF(
    @col_personas_nombres = 0,
    'ALTER TABLE personas ADD COLUMN nombres VARCHAR(60) NULL AFTER nombres_apellidos',
    'SELECT ''personas.nombres already exists'''
);
PREPARE stmt_personas_nombres FROM @sql_personas_nombres;
EXECUTE stmt_personas_nombres;
DEALLOCATE PREPARE stmt_personas_nombres;

SET @col_personas_apellidos = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'apellidos'
);
SET @sql_personas_apellidos = IF(
    @col_personas_apellidos = 0,
    'ALTER TABLE personas ADD COLUMN apellidos VARCHAR(60) NULL AFTER nombres',
    'SELECT ''personas.apellidos already exists'''
);
PREPARE stmt_personas_apellidos FROM @sql_personas_apellidos;
EXECUTE stmt_personas_apellidos;
DEALLOCATE PREPARE stmt_personas_apellidos;

SET @col_personas_genero = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'genero'
);
SET @sql_personas_genero = IF(
    @col_personas_genero = 0,
    'ALTER TABLE personas ADD COLUMN genero VARCHAR(20) NULL AFTER numero_documento',
    'SELECT ''personas.genero already exists'''
);
PREPARE stmt_personas_genero FROM @sql_personas_genero;
EXECUTE stmt_personas_genero;
DEALLOCATE PREPARE stmt_personas_genero;

SET @col_personas_fecha_nacimiento = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'fecha_nacimiento'
);
SET @sql_personas_fecha_nacimiento = IF(
    @col_personas_fecha_nacimiento = 0,
    'ALTER TABLE personas ADD COLUMN fecha_nacimiento DATE NULL AFTER genero',
    'SELECT ''personas.fecha_nacimiento already exists'''
);
PREPARE stmt_personas_fecha_nacimiento FROM @sql_personas_fecha_nacimiento;
EXECUTE stmt_personas_fecha_nacimiento;
DEALLOCATE PREPARE stmt_personas_fecha_nacimiento;

SET @col_personas_correo = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'correo'
);
SET @sql_personas_correo = IF(
    @col_personas_correo = 0,
    'ALTER TABLE personas ADD COLUMN correo VARCHAR(120) NULL AFTER fecha_nacimiento',
    'SELECT ''personas.correo already exists'''
);
PREPARE stmt_personas_correo FROM @sql_personas_correo;
EXECUTE stmt_personas_correo;
DEALLOCATE PREPARE stmt_personas_correo;

SET @col_personas_direccion = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'direccion'
);
SET @sql_personas_direccion = IF(
    @col_personas_direccion = 0,
    'ALTER TABLE personas ADD COLUMN direccion VARCHAR(255) NULL AFTER celular',
    'SELECT ''personas.direccion already exists'''
);
PREPARE stmt_personas_direccion FROM @sql_personas_direccion;
EXECUTE stmt_personas_direccion;
DEALLOCATE PREPARE stmt_personas_direccion;

SET @col_personas_tipo_poblacion_id = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'tipo_poblacion_id'
);
SET @sql_personas_tipo_poblacion_id = IF(
    @col_personas_tipo_poblacion_id = 0,
    'ALTER TABLE personas ADD COLUMN tipo_poblacion_id INT UNSIGNED NULL AFTER direccion',
    'SELECT ''personas.tipo_poblacion_id already exists'''
);
PREPARE stmt_personas_tipo_poblacion_id FROM @sql_personas_tipo_poblacion_id;
EXECUTE stmt_personas_tipo_poblacion_id;
DEALLOCATE PREPARE stmt_personas_tipo_poblacion_id;

SET @col_personas_es_jurado = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'es_jurado'
);
SET @sql_personas_es_jurado = IF(
    @col_personas_es_jurado = 0,
    'ALTER TABLE personas ADD COLUMN es_jurado TINYINT(1) NOT NULL DEFAULT 0 AFTER es_testigo',
    'SELECT ''personas.es_jurado already exists'''
);
PREPARE stmt_personas_es_jurado FROM @sql_personas_es_jurado;
EXECUTE stmt_personas_es_jurado;
DEALLOCATE PREPARE stmt_personas_es_jurado;

SET @col_personas_es_militante = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'es_militante'
);
SET @sql_personas_es_militante = IF(
    @col_personas_es_militante = 0,
    'ALTER TABLE personas ADD COLUMN es_militante TINYINT(1) NOT NULL DEFAULT 0 AFTER es_jurado',
    'SELECT ''personas.es_militante already exists'''
);
PREPARE stmt_personas_es_militante FROM @sql_personas_es_militante;
EXECUTE stmt_personas_es_militante;
DEALLOCATE PREPARE stmt_personas_es_militante;

SET @idx_personas_nombres_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_nombres'
);
SET @sql_idx_personas_nombres = IF(
    @idx_personas_nombres_exists = 0,
    'CREATE INDEX idx_personas_nombres ON personas (nombres)',
    'SELECT ''idx_personas_nombres already exists'''
);
PREPARE stmt_idx_personas_nombres FROM @sql_idx_personas_nombres;
EXECUTE stmt_idx_personas_nombres;
DEALLOCATE PREPARE stmt_idx_personas_nombres;

SET @idx_personas_apellidos_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_apellidos'
);
SET @sql_idx_personas_apellidos = IF(
    @idx_personas_apellidos_exists = 0,
    'CREATE INDEX idx_personas_apellidos ON personas (apellidos)',
    'SELECT ''idx_personas_apellidos already exists'''
);
PREPARE stmt_idx_personas_apellidos FROM @sql_idx_personas_apellidos;
EXECUTE stmt_idx_personas_apellidos;
DEALLOCATE PREPARE stmt_idx_personas_apellidos;

SET @idx_personas_correo_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_correo'
);
SET @sql_idx_personas_correo = IF(
    @idx_personas_correo_exists = 0,
    'CREATE INDEX idx_personas_correo ON personas (correo)',
    'SELECT ''idx_personas_correo already exists'''
);
PREPARE stmt_idx_personas_correo FROM @sql_idx_personas_correo;
EXECUTE stmt_idx_personas_correo;
DEALLOCATE PREPARE stmt_idx_personas_correo;

SET @idx_personas_tipo_poblacion_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_tipo_poblacion'
);
SET @sql_idx_personas_tipo_poblacion = IF(
    @idx_personas_tipo_poblacion_exists = 0,
    'CREATE INDEX idx_personas_tipo_poblacion ON personas (tipo_poblacion_id)',
    'SELECT ''idx_personas_tipo_poblacion already exists'''
);
PREPARE stmt_idx_personas_tipo_poblacion FROM @sql_idx_personas_tipo_poblacion;
EXECUTE stmt_idx_personas_tipo_poblacion;
DEALLOCATE PREPARE stmt_idx_personas_tipo_poblacion;

SET @fk_personas_tipo_poblacion_exists = (
    SELECT COUNT(1)
    FROM information_schema.table_constraints
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND constraint_name = 'fk_personas_tipo_poblacion'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql_fk_personas_tipo_poblacion = IF(
    @fk_personas_tipo_poblacion_exists = 0,
    'ALTER TABLE personas ADD CONSTRAINT fk_personas_tipo_poblacion FOREIGN KEY (tipo_poblacion_id) REFERENCES tipos_poblacion(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT ''fk_personas_tipo_poblacion already exists'''
);
PREPARE stmt_fk_personas_tipo_poblacion FROM @sql_fk_personas_tipo_poblacion;
EXECUTE stmt_fk_personas_tipo_poblacion;
DEALLOCATE PREPARE stmt_fk_personas_tipo_poblacion;

/*
    Backfill minimo y no destructivo para registros historicos.
    Se conserva nombres_apellidos como fuente actual de compatibilidad.
*/
UPDATE personas
SET nombres = TRIM(nombres_apellidos)
WHERE (nombres IS NULL OR nombres = '')
  AND TRIM(COALESCE(nombres_apellidos, '')) <> '';
