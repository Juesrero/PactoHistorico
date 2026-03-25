USE pacto_asistencia;

/*
    Propuesta Fase 1
    ----------------
    Este script NO reemplaza el esquema actual.
    Su objetivo es preparar la base de datos para el nuevo alcance
    de forma aditiva y compatible con el proyecto existente.

    Alcance cubierto por esta propuesta:
    - extension de personas
    - extension de reuniones
    - catalogo configurable de tipos de poblacion
    - infraestructura para actas
    - control de consecutivos

    Nota:
    - no elimina columnas existentes
    - no renombra tablas ni campos actuales
    - no ejecuta backfill agresivo de nombres/apellidos
*/

SET @db_name = DATABASE();

/*
    1) Catalogo configurable: tipos_poblacion
*/
CREATE TABLE IF NOT EXISTS tipos_poblacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tipos_poblacion_nombre (nombre)
) ENGINE=InnoDB;

/*
    2) Tabla de consecutivos para modulos nuevos
*/
CREATE TABLE IF NOT EXISTS consecutivos (
    modulo VARCHAR(50) NOT NULL PRIMARY KEY,
    ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO consecutivos (modulo, ultimo_numero)
SELECT 'actas', 0
WHERE NOT EXISTS (
    SELECT 1
    FROM consecutivos
    WHERE modulo = 'actas'
);

/*
    3) Extension de personas
*/

SET @col_personas_tipo_identificacion = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'tipo_identificacion'
);
SET @sql_personas_tipo_identificacion = IF(
    @col_personas_tipo_identificacion = 0,
    'ALTER TABLE personas ADD COLUMN tipo_identificacion VARCHAR(30) NULL AFTER numero_documento',
    'SELECT ''personas.tipo_identificacion already exists'''
);
PREPARE stmt_personas_tipo_identificacion FROM @sql_personas_tipo_identificacion;
EXECUTE stmt_personas_tipo_identificacion;
DEALLOCATE PREPARE stmt_personas_tipo_identificacion;

SET @col_personas_nombres = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'nombres'
);
SET @sql_personas_nombres = IF(
    @col_personas_nombres = 0,
    'ALTER TABLE personas ADD COLUMN nombres VARCHAR(80) NULL AFTER nombres_apellidos',
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
    'ALTER TABLE personas ADD COLUMN apellidos VARCHAR(80) NULL AFTER nombres',
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
    'ALTER TABLE personas ADD COLUMN genero VARCHAR(20) NULL AFTER apellidos',
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

SET @col_personas_telefono = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'telefono'
);
SET @sql_personas_telefono = IF(
    @col_personas_telefono = 0,
    'ALTER TABLE personas ADD COLUMN telefono VARCHAR(20) NULL AFTER correo',
    'SELECT ''personas.telefono already exists'''
);
PREPARE stmt_personas_telefono FROM @sql_personas_telefono;
EXECUTE stmt_personas_telefono;
DEALLOCATE PREPARE stmt_personas_telefono;

SET @col_personas_direccion = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND column_name = 'direccion'
);
SET @sql_personas_direccion = IF(
    @col_personas_direccion = 0,
    'ALTER TABLE personas ADD COLUMN direccion VARCHAR(255) NULL AFTER telefono',
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

SET @idx_personas_fecha_nacimiento_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'personas'
      AND index_name = 'idx_personas_fecha_nacimiento'
);
SET @sql_idx_personas_fecha_nacimiento = IF(
    @idx_personas_fecha_nacimiento_exists = 0,
    'CREATE INDEX idx_personas_fecha_nacimiento ON personas (fecha_nacimiento)',
    'SELECT ''idx_personas_fecha_nacimiento already exists'''
);
PREPARE stmt_idx_personas_fecha_nacimiento FROM @sql_idx_personas_fecha_nacimiento;
EXECUTE stmt_idx_personas_fecha_nacimiento;
DEALLOCATE PREPARE stmt_idx_personas_fecha_nacimiento;

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
    4) Extension de reuniones
*/
SET @col_reuniones_tipo_reunion = (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'reuniones'
      AND column_name = 'tipo_reunion'
);
SET @sql_reuniones_tipo_reunion = IF(
    @col_reuniones_tipo_reunion = 0,
    'ALTER TABLE reuniones ADD COLUMN tipo_reunion VARCHAR(80) NULL AFTER objetivo',
    'SELECT ''reuniones.tipo_reunion already exists'''
);
PREPARE stmt_reuniones_tipo_reunion FROM @sql_reuniones_tipo_reunion;
EXECUTE stmt_reuniones_tipo_reunion;
DEALLOCATE PREPARE stmt_reuniones_tipo_reunion;

SET @idx_reuniones_tipo_reunion_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'reuniones'
      AND index_name = 'idx_reuniones_tipo_reunion'
);
SET @sql_idx_reuniones_tipo_reunion = IF(
    @idx_reuniones_tipo_reunion_exists = 0,
    'CREATE INDEX idx_reuniones_tipo_reunion ON reuniones (tipo_reunion)',
    'SELECT ''idx_reuniones_tipo_reunion already exists'''
);
PREPARE stmt_idx_reuniones_tipo_reunion FROM @sql_idx_reuniones_tipo_reunion;
EXECUTE stmt_idx_reuniones_tipo_reunion;
DEALLOCATE PREPARE stmt_idx_reuniones_tipo_reunion;

/*
    5) Infraestructura de actas
*/
CREATE TABLE IF NOT EXISTS actas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reunion_id INT UNSIGNED NOT NULL,
    consecutivo INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    fecha_acta DATE NOT NULL,
    resumen TEXT NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_actas_consecutivo (consecutivo),
    INDEX idx_actas_reunion (reunion_id),
    CONSTRAINT fk_actas_reunion
        FOREIGN KEY (reunion_id) REFERENCES reuniones(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS acta_adjuntos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    acta_id INT UNSIGNED NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    mime_type VARCHAR(120) NULL,
    tamano_bytes BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acta_adjuntos_acta (acta_id),
    CONSTRAINT fk_acta_adjuntos_acta
        FOREIGN KEY (acta_id) REFERENCES actas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

/*
    6) Semillas opcionales para tipos de poblacion
    Ajustar segun negocio real.
*/
INSERT INTO tipos_poblacion (nombre, descripcion)
SELECT 'General', 'Registro general por defecto'
WHERE NOT EXISTS (
    SELECT 1
    FROM tipos_poblacion
    WHERE nombre = 'General'
);

/*
    7) Notas de uso posteriores a esta propuesta

    - El sistema actual puede seguir operando con las columnas antiguas.
    - Los nuevos formularios deben escribir tanto el modelo viejo como el nuevo
      durante la transicion, especialmente en personas.
    - Si se requiere backfill de datos existentes, hacerlo manualmente y con
      revision previa.

    Backfill opcional sugerido, no automatico:

    UPDATE personas
    SET telefono = celular
    WHERE telefono IS NULL
      AND celular IS NOT NULL
      AND celular <> '';
*/