USE pacto_asistencia;

/*
    Creacion incremental del modulo de actas
    ----------------------------------------
    Este script agrega las tablas necesarias para el nuevo modulo
    sin afectar los modulos existentes de personas, reuniones y asistencia.
*/

SET @db_name = DATABASE();

CREATE TABLE IF NOT EXISTS consecutivos (
  modulo VARCHAR(50) PRIMARY KEY,
  ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS actas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  consecutivo VARCHAR(30) NOT NULL,
  nombre_o_objetivo VARCHAR(200) NOT NULL,
  responsable VARCHAR(150) NOT NULL,
  lugar VARCHAR(150) NOT NULL,
  nombre_archivo_original VARCHAR(255) NULL,
  ruta_archivo VARCHAR(255) NULL,
  tipo_mime VARCHAR(120) NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_actas_consecutivo (consecutivo),
  INDEX idx_actas_nombre_objetivo (nombre_o_objetivo),
  INDEX idx_actas_responsable (responsable),
  INDEX idx_actas_fecha_actualizacion (fecha_actualizacion)
) ENGINE=InnoDB;

SET @col_actas_nombre_objetivo = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'nombre_o_objetivo'
);
SET @sql_actas_nombre_objetivo = IF(
    @col_actas_nombre_objetivo = 0,
    'ALTER TABLE actas ADD COLUMN nombre_o_objetivo VARCHAR(200) NULL AFTER consecutivo',
    'SELECT ''actas.nombre_o_objetivo already exists'''
);
PREPARE stmt_actas_nombre_objetivo FROM @sql_actas_nombre_objetivo;
EXECUTE stmt_actas_nombre_objetivo;
DEALLOCATE PREPARE stmt_actas_nombre_objetivo;

SET @col_actas_responsable = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'responsable'
);
SET @sql_actas_responsable = IF(
    @col_actas_responsable = 0,
    'ALTER TABLE actas ADD COLUMN responsable VARCHAR(150) NULL AFTER nombre_o_objetivo',
    'SELECT ''actas.responsable already exists'''
);
PREPARE stmt_actas_responsable FROM @sql_actas_responsable;
EXECUTE stmt_actas_responsable;
DEALLOCATE PREPARE stmt_actas_responsable;

SET @col_actas_lugar = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'lugar'
);
SET @sql_actas_lugar = IF(
    @col_actas_lugar = 0,
    'ALTER TABLE actas ADD COLUMN lugar VARCHAR(150) NULL AFTER responsable',
    'SELECT ''actas.lugar already exists'''
);
PREPARE stmt_actas_lugar FROM @sql_actas_lugar;
EXECUTE stmt_actas_lugar;
DEALLOCATE PREPARE stmt_actas_lugar;

SET @col_actas_nombre_archivo_original = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'nombre_archivo_original'
);
SET @sql_actas_nombre_archivo_original = IF(
    @col_actas_nombre_archivo_original = 0,
    'ALTER TABLE actas ADD COLUMN nombre_archivo_original VARCHAR(255) NULL AFTER lugar',
    'SELECT ''actas.nombre_archivo_original already exists'''
);
PREPARE stmt_actas_nombre_archivo_original FROM @sql_actas_nombre_archivo_original;
EXECUTE stmt_actas_nombre_archivo_original;
DEALLOCATE PREPARE stmt_actas_nombre_archivo_original;

SET @col_actas_ruta_archivo = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'ruta_archivo'
);
SET @sql_actas_ruta_archivo = IF(
    @col_actas_ruta_archivo = 0,
    'ALTER TABLE actas ADD COLUMN ruta_archivo VARCHAR(255) NULL AFTER nombre_archivo_original',
    'SELECT ''actas.ruta_archivo already exists'''
);
PREPARE stmt_actas_ruta_archivo FROM @sql_actas_ruta_archivo;
EXECUTE stmt_actas_ruta_archivo;
DEALLOCATE PREPARE stmt_actas_ruta_archivo;

SET @col_actas_tipo_mime = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'tipo_mime'
);
SET @sql_actas_tipo_mime = IF(
    @col_actas_tipo_mime = 0,
    'ALTER TABLE actas ADD COLUMN tipo_mime VARCHAR(120) NULL AFTER ruta_archivo',
    'SELECT ''actas.tipo_mime already exists'''
);
PREPARE stmt_actas_tipo_mime FROM @sql_actas_tipo_mime;
EXECUTE stmt_actas_tipo_mime;
DEALLOCATE PREPARE stmt_actas_tipo_mime;

SET @col_actas_fecha_creacion = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'fecha_creacion'
);
SET @sql_actas_fecha_creacion = IF(
    @col_actas_fecha_creacion = 0,
    'ALTER TABLE actas ADD COLUMN fecha_creacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER tipo_mime',
    'SELECT ''actas.fecha_creacion already exists'''
);
PREPARE stmt_actas_fecha_creacion FROM @sql_actas_fecha_creacion;
EXECUTE stmt_actas_fecha_creacion;
DEALLOCATE PREPARE stmt_actas_fecha_creacion;

SET @col_actas_fecha_actualizacion = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'fecha_actualizacion'
);
SET @sql_actas_fecha_actualizacion = IF(
    @col_actas_fecha_actualizacion = 0,
    'ALTER TABLE actas ADD COLUMN fecha_actualizacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_creacion',
    'SELECT ''actas.fecha_actualizacion already exists'''
);
PREPARE stmt_actas_fecha_actualizacion FROM @sql_actas_fecha_actualizacion;
EXECUTE stmt_actas_fecha_actualizacion;
DEALLOCATE PREPARE stmt_actas_fecha_actualizacion;

SET @actas_consecutivo_type = (
    SELECT LOWER(data_type)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'consecutivo'
    LIMIT 1
);
SET @sql_actas_consecutivo = IF(
    @actas_consecutivo_type IS NULL,
    'SELECT ''actas.consecutivo not found''',
    IF(
        @actas_consecutivo_type IN ('varchar', 'char'),
        'SELECT ''actas.consecutivo already text''',
        'ALTER TABLE actas MODIFY consecutivo VARCHAR(30) NOT NULL'
    )
);
PREPARE stmt_actas_consecutivo FROM @sql_actas_consecutivo;
EXECUTE stmt_actas_consecutivo;
DEALLOCATE PREPARE stmt_actas_consecutivo;

SET @actas_reunion_nullable = (
    SELECT is_nullable
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'actas'
      AND column_name = 'reunion_id'
    LIMIT 1
);
SET @sql_actas_reunion_nullable = IF(
    @actas_reunion_nullable = 'NO',
    'ALTER TABLE actas MODIFY reunion_id INT UNSIGNED NULL',
    'SELECT ''actas.reunion_id already nullable or absent'''
);
PREPARE stmt_actas_reunion_nullable FROM @sql_actas_reunion_nullable;
EXECUTE stmt_actas_reunion_nullable;
DEALLOCATE PREPARE stmt_actas_reunion_nullable;

UPDATE actas
SET consecutivo = CONCAT('ACTA-', LPAD(CAST(consecutivo AS UNSIGNED), 6, '0'))
WHERE consecutivo REGEXP '^[0-9]+$';

UPDATE actas a
LEFT JOIN reuniones r ON r.id = a.reunion_id
SET a.nombre_o_objetivo = COALESCE(NULLIF(TRIM(a.nombre_o_objetivo), ''), NULLIF(TRIM(a.titulo), ''), CONCAT('Acta ', a.consecutivo)),
    a.responsable = COALESCE(NULLIF(TRIM(a.responsable), ''), NULLIF(TRIM(r.organizacion), ''), 'Pendiente'),
    a.lugar = COALESCE(NULLIF(TRIM(a.lugar), ''), NULLIF(TRIM(r.lugar_reunion), ''), 'Pendiente'),
    a.fecha_creacion = COALESCE(a.fecha_creacion, a.created_at, NOW()),
    a.fecha_actualizacion = COALESCE(a.fecha_actualizacion, a.updated_at, NOW());

SET @acta_adjuntos_exists = (
    SELECT COUNT(1)
    FROM information_schema.tables
    WHERE table_schema = @db_name
      AND table_name = 'acta_adjuntos'
);
SET @sql_copy_legacy_adjuntos = IF(
    @acta_adjuntos_exists = 0,
    'SELECT ''acta_adjuntos not found''',
    'UPDATE actas a
     INNER JOIN (
         SELECT aa.acta_id, aa.nombre_original, aa.ruta_archivo, aa.mime_type
         FROM acta_adjuntos aa
         INNER JOIN (
             SELECT acta_id, MAX(id) AS max_id
             FROM acta_adjuntos
             GROUP BY acta_id
         ) latest ON latest.max_id = aa.id
     ) adj ON adj.acta_id = a.id
     SET a.nombre_archivo_original = COALESCE(NULLIF(TRIM(a.nombre_archivo_original), ''''), adj.nombre_original),
         a.ruta_archivo = COALESCE(NULLIF(TRIM(a.ruta_archivo), ''''), adj.ruta_archivo),
         a.tipo_mime = COALESCE(NULLIF(TRIM(a.tipo_mime), ''''), adj.mime_type)'
);
PREPARE stmt_copy_legacy_adjuntos FROM @sql_copy_legacy_adjuntos;
EXECUTE stmt_copy_legacy_adjuntos;
DEALLOCATE PREPARE stmt_copy_legacy_adjuntos;

UPDATE actas
SET nombre_o_objetivo = CONCAT('Acta ', consecutivo)
WHERE nombre_o_objetivo IS NULL OR TRIM(nombre_o_objetivo) = '';

UPDATE actas
SET responsable = 'Pendiente'
WHERE responsable IS NULL OR TRIM(responsable) = '';

UPDATE actas
SET lugar = 'Pendiente'
WHERE lugar IS NULL OR TRIM(lugar) = '';

SET @sql_actas_nombre_objetivo_not_null = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db_name
              AND table_name = 'actas'
              AND column_name = 'nombre_o_objetivo'
              AND is_nullable = 'YES'
        ),
        'ALTER TABLE actas MODIFY nombre_o_objetivo VARCHAR(200) NOT NULL',
        'SELECT ''actas.nombre_o_objetivo already not null'''
    )
);
PREPARE stmt_actas_nombre_objetivo_not_null FROM @sql_actas_nombre_objetivo_not_null;
EXECUTE stmt_actas_nombre_objetivo_not_null;
DEALLOCATE PREPARE stmt_actas_nombre_objetivo_not_null;

SET @sql_actas_responsable_not_null = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db_name
              AND table_name = 'actas'
              AND column_name = 'responsable'
              AND is_nullable = 'YES'
        ),
        'ALTER TABLE actas MODIFY responsable VARCHAR(150) NOT NULL',
        'SELECT ''actas.responsable already not null'''
    )
);
PREPARE stmt_actas_responsable_not_null FROM @sql_actas_responsable_not_null;
EXECUTE stmt_actas_responsable_not_null;
DEALLOCATE PREPARE stmt_actas_responsable_not_null;

SET @sql_actas_lugar_not_null = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db_name
              AND table_name = 'actas'
              AND column_name = 'lugar'
              AND is_nullable = 'YES'
        ),
        'ALTER TABLE actas MODIFY lugar VARCHAR(150) NOT NULL',
        'SELECT ''actas.lugar already not null'''
    )
);
PREPARE stmt_actas_lugar_not_null FROM @sql_actas_lugar_not_null;
EXECUTE stmt_actas_lugar_not_null;
DEALLOCATE PREPARE stmt_actas_lugar_not_null;

SET @max_acta_numero = (
    SELECT COALESCE(MAX(
        CASE
            WHEN consecutivo REGEXP '^ACTA-[0-9]+$' THEN CAST(SUBSTRING(consecutivo, 6) AS UNSIGNED)
            WHEN consecutivo REGEXP '^[0-9]+$' THEN CAST(consecutivo AS UNSIGNED)
            ELSE 0
        END
    ), 0)
    FROM actas
);

SET @counter_key_column = (
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db_name
              AND table_name = 'consecutivos'
              AND column_name = 'modulo'
        ) THEN 'modulo'
        WHEN EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db_name
              AND table_name = 'consecutivos'
              AND column_name = 'clave'
        ) THEN 'clave'
        ELSE ''
    END
);

SET @sql_seed_actas_counter = IF(
    @counter_key_column = '',
    'SELECT ''No compatible key column found in consecutivos''',
    CONCAT(
        'INSERT INTO consecutivos (', @counter_key_column, ', ultimo_numero) ',
        'SELECT ''actas'', 0 ',
        'WHERE NOT EXISTS (SELECT 1 FROM consecutivos WHERE ', @counter_key_column, ' = ''actas'')'
    )
);

PREPARE stmt_seed_actas_counter FROM @sql_seed_actas_counter;
EXECUTE stmt_seed_actas_counter;
DEALLOCATE PREPARE stmt_seed_actas_counter;

SET @sql_update_actas_counter = IF(
    @counter_key_column = '',
    'SELECT ''No compatible key column found in consecutivos''',
    CONCAT(
        'UPDATE consecutivos SET ultimo_numero = GREATEST(ultimo_numero, ', @max_acta_numero, ') ',
        'WHERE ', @counter_key_column, ' = ''actas'''
    )
);
PREPARE stmt_update_actas_counter FROM @sql_update_actas_counter;
EXECUTE stmt_update_actas_counter;
DEALLOCATE PREPARE stmt_update_actas_counter;
