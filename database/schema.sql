CREATE DATABASE IF NOT EXISTS pacto_asistencia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pacto_asistencia;

CREATE TABLE IF NOT EXISTS reuniones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_reunion VARCHAR(150) NOT NULL,
  objetivo TEXT NOT NULL,
  organizacion VARCHAR(120) NOT NULL,
  lugar_reunion VARCHAR(150) NOT NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_reuniones_fecha_hora (fecha, hora)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS personas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombres_apellidos VARCHAR(120) NOT NULL,
  numero_documento VARCHAR(20) NOT NULL,
  celular VARCHAR(20) NOT NULL,
  es_testigo TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_personas_numero_documento (numero_documento)
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
