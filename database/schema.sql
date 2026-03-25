CREATE DATABASE IF NOT EXISTS pacto_asistencia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pacto_asistencia;

CREATE TABLE IF NOT EXISTS tipos_poblacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tipos_poblacion_nombre (nombre)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS reuniones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_reunion VARCHAR(150) NOT NULL,
  objetivo TEXT NOT NULL,
  tipo_reunion VARCHAR(80) NOT NULL,
  organizacion VARCHAR(120) NOT NULL,
  lugar_reunion VARCHAR(150) NOT NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_reuniones_fecha_hora (fecha, hora),
  INDEX idx_reuniones_tipo (tipo_reunion)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS personas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombres_apellidos VARCHAR(120) NOT NULL,
  nombres VARCHAR(60) NULL,
  apellidos VARCHAR(60) NULL,
  numero_documento VARCHAR(20) NOT NULL,
  genero VARCHAR(20) NULL,
  fecha_nacimiento DATE NULL,
  correo VARCHAR(120) NULL,
  celular VARCHAR(20) NOT NULL,
  direccion VARCHAR(255) NULL,
  tipo_poblacion_id INT UNSIGNED NULL,
  es_testigo TINYINT(1) NOT NULL DEFAULT 0,
  es_jurado TINYINT(1) NOT NULL DEFAULT 0,
  es_militante TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_personas_numero_documento (numero_documento),
  INDEX idx_personas_nombre_completo (nombres_apellidos),
  INDEX idx_personas_nombres (nombres),
  INDEX idx_personas_apellidos (apellidos),
  INDEX idx_personas_celular (celular),
  INDEX idx_personas_correo (correo),
  INDEX idx_personas_tipo_poblacion (tipo_poblacion_id),
  CONSTRAINT fk_personas_tipo_poblacion
    FOREIGN KEY (tipo_poblacion_id) REFERENCES tipos_poblacion(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asistencias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reunion_id INT UNSIGNED NOT NULL,
  persona_id INT UNSIGNED NOT NULL,
  fecha_registro DATE NOT NULL,
  hora_registro TIME NOT NULL,
  observacion VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_asistencia_reunion_persona (reunion_id, persona_id),
  INDEX idx_asistencias_reunion (reunion_id),
  INDEX idx_asistencias_persona (persona_id),
  CONSTRAINT fk_asistencias_reunion
    FOREIGN KEY (reunion_id) REFERENCES reuniones(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_asistencias_persona
    FOREIGN KEY (persona_id) REFERENCES personas(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO tipos_poblacion (nombre, descripcion, activo)
SELECT 'General', 'Tipo de poblacion por defecto', 1
WHERE NOT EXISTS (
  SELECT 1
  FROM tipos_poblacion
  WHERE nombre = 'General'
);

INSERT INTO consecutivos (modulo, ultimo_numero)
SELECT 'actas', 0
WHERE NOT EXISTS (
  SELECT 1
  FROM consecutivos
  WHERE modulo = 'actas'
);
