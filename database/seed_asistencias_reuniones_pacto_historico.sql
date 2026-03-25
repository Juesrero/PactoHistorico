USE pacto_asistencia;

/*
    Siembra de asistencias para reuniones del Pacto Historico
    ---------------------------------------------------------
    Toma personas ya registradas y crea asistencia para:
    - Reunion 1: 50 personas
    - Reunion 2: 20 personas
    - Reunion 3: 11 personas

    Requisitos previos:
    - Haber ejecutado database/seed_personas_500_colombia.sql
    - Haber ejecutado database/seed_reuniones_pacto_historico.sql

    Notas:
    - Usa INSERT IGNORE para no duplicar asistencia por reunion
    - Si hay menos personas registradas que el cupo indicado,
      insertara solo las disponibles
*/

SET @reunion_1_id = (
    SELECT id
    FROM reuniones
    WHERE nombre_reunion = 'Comite de organizacion territorial'
      AND fecha = '2026-04-05'
      AND hora = '09:00:00'
    LIMIT 1
);

SET @reunion_2_id = (
    SELECT id
    FROM reuniones
    WHERE nombre_reunion = 'Comite de comunicaciones y pedagogia politica'
      AND fecha = '2026-04-12'
      AND hora = '15:00:00'
    LIMIT 1
);

SET @reunion_3_id = (
    SELECT id
    FROM reuniones
    WHERE nombre_reunion = 'Comite electoral y defensa del voto'
      AND fecha = '2026-04-19'
      AND hora = '10:30:00'
    LIMIT 1
);

INSERT IGNORE INTO asistencias (
    reunion_id,
    persona_id,
    fecha_registro,
    hora_registro,
    observacion,
    created_at
)
SELECT
    @reunion_1_id,
    base.persona_id,
    '2026-04-05',
    ADDTIME('09:00:00', SEC_TO_TIME((base.orden - 1) * 60)),
    NULL,
    NOW()
FROM (
    SELECT
        p.id AS persona_id,
        @rownum_1 := @rownum_1 + 1 AS orden
    FROM personas p
    CROSS JOIN (SELECT @rownum_1 := 0) vars
    ORDER BY p.id ASC
    LIMIT 50
) base
WHERE @reunion_1_id IS NOT NULL;

INSERT IGNORE INTO asistencias (
    reunion_id,
    persona_id,
    fecha_registro,
    hora_registro,
    observacion,
    created_at
)
SELECT
    @reunion_2_id,
    base.persona_id,
    '2026-04-12',
    ADDTIME('15:00:00', SEC_TO_TIME((base.orden - 1) * 90)),
    NULL,
    NOW()
FROM (
    SELECT
        p.id AS persona_id,
        @rownum_2 := @rownum_2 + 1 AS orden
    FROM personas p
    CROSS JOIN (SELECT @rownum_2 := 0) vars
    ORDER BY p.id ASC
    LIMIT 20 OFFSET 50
) base
WHERE @reunion_2_id IS NOT NULL;

INSERT IGNORE INTO asistencias (
    reunion_id,
    persona_id,
    fecha_registro,
    hora_registro,
    observacion,
    created_at
)
SELECT
    @reunion_3_id,
    base.persona_id,
    '2026-04-19',
    ADDTIME('10:30:00', SEC_TO_TIME((base.orden - 1) * 120)),
    NULL,
    NOW()
FROM (
    SELECT
        p.id AS persona_id,
        @rownum_3 := @rownum_3 + 1 AS orden
    FROM personas p
    CROSS JOIN (SELECT @rownum_3 := 0) vars
    ORDER BY p.id ASC
    LIMIT 11 OFFSET 70
) base
WHERE @reunion_3_id IS NOT NULL;
