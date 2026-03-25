USE pacto_asistencia;

/*
    Siembra inicial de reuniones
    ----------------------------
    Crea 3 reuniones orientadas a comites y temas de campana politica
    del Pacto Historico en San Vicente de Chucuri.

    Evita duplicados por nombre, fecha y hora.
*/

INSERT INTO reuniones (
    nombre_reunion,
    objetivo,
    tipo_reunion,
    organizacion,
    lugar_reunion,
    fecha,
    hora,
    created_at,
    updated_at
)
SELECT
    'Comite de organizacion territorial',
    'Coordinar la agenda territorial, definir responsables de barrios y veredas, y organizar la movilizacion politica del Pacto Historico en San Vicente de Chucuri.',
    'Comite',
    'Pacto Historico - San Vicente de Chucuri',
    'Casa de encuentro comunitario, San Vicente de Chucuri',
    '2026-04-05',
    '09:00:00',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM reuniones
    WHERE nombre_reunion = 'Comite de organizacion territorial'
      AND fecha = '2026-04-05'
      AND hora = '09:00:00'
);

INSERT INTO reuniones (
    nombre_reunion,
    objetivo,
    tipo_reunion,
    organizacion,
    lugar_reunion,
    fecha,
    hora,
    created_at,
    updated_at
)
SELECT
    'Comite de comunicaciones y pedagogia politica',
    'Definir mensajes clave, estrategia de redes, vocerias y acciones de pedagogia ciudadana para fortalecer la campana politica del Pacto Historico en el municipio.',
    'Comite',
    'Pacto Historico - San Vicente de Chucuri',
    'Salon comunal del barrio Centro, San Vicente de Chucuri',
    '2026-04-12',
    '15:00:00',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM reuniones
    WHERE nombre_reunion = 'Comite de comunicaciones y pedagogia politica'
      AND fecha = '2026-04-12'
      AND hora = '15:00:00'
);

INSERT INTO reuniones (
    nombre_reunion,
    objetivo,
    tipo_reunion,
    organizacion,
    lugar_reunion,
    fecha,
    hora,
    created_at,
    updated_at
)
SELECT
    'Comite electoral y defensa del voto',
    'Planear la preparacion de jurados, testigos electorales y equipos de defensa del voto para la estrategia politica del Pacto Historico en San Vicente de Chucuri.',
    'Comite',
    'Pacto Historico - San Vicente de Chucuri',
    'Auditorio de la Casa de la Cultura, San Vicente de Chucuri',
    '2026-04-19',
    '10:30:00',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM reuniones
    WHERE nombre_reunion = 'Comite electoral y defensa del voto'
      AND fecha = '2026-04-19'
      AND hora = '10:30:00'
);
