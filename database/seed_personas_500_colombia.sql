USE pacto_asistencia;

/*
    Siembra de 500 personas de ejemplo
    ----------------------------------
    - Genera 500 personas sinteticas pero verosimiles
    - Datos pensados para contexto colombiano
    - Edades desde los 18 anos
    - Compatible con el modulo actual de Personas
    - Referencia tipos de poblacion previamente creados

    Recomendado:
    1. Ejecutar primero database/seed_tipos_poblacion.sql
    2. Ejecutar este script sobre una base vacia o recien reiniciada

    Nota:
    - Usa INSERT IGNORE para evitar error por identificaciones duplicadas
    - Si ya existen personas con esas identificaciones, se omitiran
*/

SET @tp_jovenes = (
    SELECT id
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Jovenes vulnerables')
    LIMIT 1
);

SET @tp_mujeres = (
    SELECT id
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Mujeres cabeza de familia')
    LIMIT 1
);

SET @tp_desplazados = (
    SELECT id
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Desplazados')
    LIMIT 1
);

SET @tp_adulto_mayor = (
    SELECT id
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Adulto mayor')
    LIMIT 1
);

SET @tp_discapacidad = (
    SELECT id
    FROM tipos_poblacion
    WHERE LOWER(nombre) = LOWER('Poblacion con discapacidad')
    LIMIT 1
);

INSERT IGNORE INTO personas (
    nombres_apellidos,
    nombres,
    apellidos,
    numero_documento,
    genero,
    fecha_nacimiento,
    correo,
    celular,
    direccion,
    tipo_poblacion_id,
    es_testigo,
    es_jurado,
    es_militante,
    created_at,
    updated_at
)
SELECT
    CONCAT(base.nombres, ' ', base.apellidos) AS nombres_apellidos,
    base.nombres,
    base.apellidos,
    base.numero_documento,
    base.genero,
    base.fecha_nacimiento,
    base.correo,
    base.celular,
    base.direccion,
    base.tipo_poblacion_id,
    base.es_testigo,
    base.es_jurado,
    base.es_militante,
    NOW(),
    NOW()
FROM (
    SELECT
        genero_data.i,
        genero_data.genero,
        genero_data.nombres,
        genero_data.apellidos,
        CAST(1000000000 + genero_data.i AS CHAR(20)) AS numero_documento,
        DATE_SUB(
            DATE_SUB(CURDATE(), INTERVAL genero_data.edad YEAR),
            INTERVAL ((genero_data.i * 37) % 365) DAY
        ) AS fecha_nacimiento,
        CASE
            WHEN genero_data.i % 9 = 0 THEN NULL
            ELSE CONCAT(
                LOWER(REPLACE(SUBSTRING_INDEX(genero_data.nombres, ' ', 1), ' ', '')),
                '.',
                LOWER(REPLACE(SUBSTRING_INDEX(genero_data.apellidos, ' ', 1), ' ', '')),
                LPAD(genero_data.i, 3, '0'),
                '@',
                ELT(
                    1 + ((genero_data.i - 1) % 8),
                    'gmail.com',
                    'hotmail.com',
                    'outlook.com',
                    'yahoo.com',
                    'icloud.com',
                    'protonmail.com',
                    'mail.com',
                    'live.com'
                )
            )
        END AS correo,
        CONCAT('3', LPAD(100000000 + (genero_data.i * 17), 9, '0')) AS celular,
        CONCAT(
            ELT(
                1 + ((genero_data.i - 1) % 6),
                'Calle',
                'Carrera',
                'Transversal',
                'Diagonal',
                'Avenida',
                'Manzana'
            ),
            ' ',
            3 + ((genero_data.i * 5) % 120),
            ' # ',
            1 + ((genero_data.i * 7) % 90),
            '-',
            LPAD(1 + ((genero_data.i * 11) % 80), 2, '0'),
            ', ',
            ELT(
                1 + ((genero_data.i + 2) % 24),
                'Centro',
                'La Esperanza',
                'Villa Lina',
                'San Carlos',
                'El Progreso',
                'La Cumbre',
                'Buenos Aires',
                'La Feria',
                'La Victoria',
                'Nueva Granada',
                'El Bosque',
                'Miraflores',
                'Kennedy',
                'Bellarena',
                'San Rafael',
                'La Floresta',
                'Los Pinos',
                'Altos del Norte',
                'Primero de Mayo',
                'Villa del Sol',
                'La Paz',
                'Las Americas',
                'La Esperanza II',
                'Vereda El Carmen'
            ),
            ', ',
            ELT(
                1 + ((genero_data.i + 5) % 20),
                'San Vicente de Chucuri',
                'Bucaramanga',
                'Floridablanca',
                'Giron',
                'Piedecuesta',
                'Barrancabermeja',
                'Sabana de Torres',
                'Rionegro',
                'San Gil',
                'Socorro',
                'Malaga',
                'Barbosa',
                'Bogota',
                'Medellin',
                'Cali',
                'Barranquilla',
                'Cartagena',
                'Cucuta',
                'Ibague',
                'Villavicencio'
            )
        ) AS direccion,
        CASE
            WHEN genero_data.edad >= 60 THEN @tp_adulto_mayor
            WHEN genero_data.genero = 'Femenino' AND genero_data.i % 7 IN (0, 3) THEN @tp_mujeres
            WHEN genero_data.edad BETWEEN 18 AND 28 AND genero_data.i % 5 IN (0, 2) THEN @tp_jovenes
            WHEN genero_data.i % 11 IN (0, 4, 7) THEN @tp_desplazados
            WHEN genero_data.i % 13 = 0 THEN @tp_discapacidad
            ELSE NULL
        END AS tipo_poblacion_id,
        CASE WHEN genero_data.i % 6 = 0 THEN 1 ELSE 0 END AS es_testigo,
        CASE WHEN genero_data.i % 12 = 0 THEN 1 ELSE 0 END AS es_jurado,
        CASE WHEN genero_data.i % 5 IN (0, 1, 3) THEN 1 ELSE 0 END AS es_militante
    FROM (
        SELECT
            seq.i,
            CASE seq.i % 8
                WHEN 0 THEN 'Femenino'
                WHEN 1 THEN 'Masculino'
                WHEN 2 THEN 'Femenino'
                WHEN 3 THEN 'Masculino'
                WHEN 4 THEN 'Femenino'
                WHEN 5 THEN 'Masculino'
                WHEN 6 THEN 'Otro'
                ELSE 'Masculino'
            END AS genero,
            18 + ((seq.i * 7) % 53) AS edad,
            CASE
                WHEN seq.i % 8 IN (0, 2, 4) THEN CONCAT(
                    ELT(
                        1 + ((seq.i - 1) % 40),
                        'Maria',
                        'Ana',
                        'Luisa',
                        'Laura',
                        'Diana',
                        'Paula',
                        'Sandra',
                        'Liliana',
                        'Andrea',
                        'Carolina',
                        'Katherine',
                        'Valentina',
                        'Angie',
                        'Paola',
                        'Natalia',
                        'Tatiana',
                        'Jennifer',
                        'Lorena',
                        'Dayana',
                        'Yuliana',
                        'Marcela',
                        'Claudia',
                        'Olga',
                        'Patricia',
                        'Adriana',
                        'Alejandra',
                        'Daniela',
                        'Catalina',
                        'Juliana',
                        'Leidy',
                        'Karen',
                        'Johana',
                        'Viviana',
                        'Milena',
                        'Rocio',
                        'Luz',
                        'Gloria',
                        'Nidia',
                        'Yesenia',
                        'Camila'
                    ),
                    ' ',
                    ELT(
                        1 + ((seq.i + 9) % 30),
                        'Fernanda',
                        'Carolina',
                        'Alejandra',
                        'Marcela',
                        'Patricia',
                        'Andrea',
                        'Sofia',
                        'Isabel',
                        'Juliana',
                        'Tatiana',
                        'Lorena',
                        'Milena',
                        'Paola',
                        'Elena',
                        'Pilar',
                        'Rocio',
                        'Yurani',
                        'Estefania',
                        'Lucia',
                        'Margarita',
                        'Yohana',
                        'Vanessa',
                        'Viviana',
                        'Mabel',
                        'Nathalia',
                        'Jimena',
                        'Monica',
                        'Andrea',
                        'Liseth',
                        'Dayana'
                    )
                )
                WHEN seq.i % 8 IN (1, 3, 5, 7) THEN CONCAT(
                    ELT(
                        1 + ((seq.i - 1) % 40),
                        'Juan',
                        'Carlos',
                        'Luis',
                        'Andres',
                        'Miguel',
                        'Daniel',
                        'Felipe',
                        'Camilo',
                        'Diego',
                        'Jorge',
                        'Cristian',
                        'Kevin',
                        'Julian',
                        'Sebastian',
                        'Esteban',
                        'Nicolas',
                        'Sergio',
                        'Alejandro',
                        'Jhon',
                        'David',
                        'Wilson',
                        'Oscar',
                        'Nelson',
                        'Hernan',
                        'Edwin',
                        'Yeferson',
                        'Brayan',
                        'Mateo',
                        'Santiago',
                        'Ricardo',
                        'Guillermo',
                        'Ivan',
                        'Alvaro',
                        'Cesar',
                        'Fabio',
                        'Harold',
                        'Manuel',
                        'Ramon',
                        'Victor',
                        'Ruben'
                    ),
                    ' ',
                    ELT(
                        1 + ((seq.i + 11) % 30),
                        'David',
                        'Andres',
                        'Felipe',
                        'Alejandro',
                        'Esteban',
                        'Javier',
                        'Enrique',
                        'Eduardo',
                        'Armando',
                        'Dario',
                        'Manuel',
                        'Antonio',
                        'Jose',
                        'Alexander',
                        'Steven',
                        'Fernando',
                        'Alberto',
                        'Orlando',
                        'Mauricio',
                        'Adrian',
                        'Hernando',
                        'Yeison',
                        'Leonardo',
                        'Camilo',
                        'Nicolas',
                        'Ramon',
                        'Ricardo',
                        'Samuel',
                        'Cristian',
                        'Omar'
                    )
                )
                ELSE CONCAT(
                    ELT(
                        1 + ((seq.i - 1) % 20),
                        'Alex',
                        'Ariel',
                        'Sam',
                        'Noel',
                        'Andy',
                        'Dylan',
                        'Morgan',
                        'Robin',
                        'Angel',
                        'Terry',
                        'Alexis',
                        'Jordan',
                        'Jean',
                        'Kris',
                        'Sasha',
                        'Jhoan',
                        'Alen',
                        'Gael',
                        'Yury',
                        'Milan'
                    ),
                    ' ',
                    ELT(
                        1 + ((seq.i + 3) % 16),
                        'Andres',
                        'David',
                        'Emilio',
                        'Johan',
                        'Camilo',
                        'Noel',
                        'Ariel',
                        'Rene',
                        'Julian',
                        'Samir',
                        'Angel',
                        'Dilan',
                        'Robin',
                        'Yahir',
                        'Nicolas',
                        'Tomas'
                    )
                )
            END AS nombres,
            CONCAT(
                ELT(
                    1 + ((seq.i + 4) % 60),
                    'Rodriguez',
                    'Gomez',
                    'Martinez',
                    'Sanchez',
                    'Lopez',
                    'Hernandez',
                    'Perez',
                    'Gonzalez',
                    'Ramirez',
                    'Torres',
                    'Diaz',
                    'Vargas',
                    'Romero',
                    'Castillo',
                    'Ortiz',
                    'Moreno',
                    'Suarez',
                    'Medina',
                    'Rojas',
                    'Castro',
                    'Herrera',
                    'Navarro',
                    'Acosta',
                    'Vega',
                    'Cardenas',
                    'Duarte',
                    'Pineda',
                    'Mendoza',
                    'Quintero',
                    'Arias',
                    'Salazar',
                    'Parra',
                    'Figueroa',
                    'Cifuentes',
                    'Restrepo',
                    'Rincon',
                    'Molina',
                    'Beltran',
                    'Blanco',
                    'Nunez',
                    'Becerra',
                    'Contreras',
                    'Barrera',
                    'Gallardo',
                    'Serrano',
                    'Chaparro',
                    'Bautista',
                    'Tellez',
                    'Prada',
                    'Jaimes',
                    'Buitrago',
                    'Ochoa',
                    'Rueda',
                    'Santos',
                    'Franco',
                    'Valencia',
                    'Cardona',
                    'Bedoya',
                    'Correa',
                    'Galvis'
                ),
                ' ',
                ELT(
                    1 + ((seq.i + 19) % 60),
                    'Rodriguez',
                    'Gomez',
                    'Martinez',
                    'Sanchez',
                    'Lopez',
                    'Hernandez',
                    'Perez',
                    'Gonzalez',
                    'Ramirez',
                    'Torres',
                    'Diaz',
                    'Vargas',
                    'Romero',
                    'Castillo',
                    'Ortiz',
                    'Moreno',
                    'Suarez',
                    'Medina',
                    'Rojas',
                    'Castro',
                    'Herrera',
                    'Navarro',
                    'Acosta',
                    'Vega',
                    'Cardenas',
                    'Duarte',
                    'Pineda',
                    'Mendoza',
                    'Quintero',
                    'Arias',
                    'Salazar',
                    'Parra',
                    'Figueroa',
                    'Cifuentes',
                    'Restrepo',
                    'Rincon',
                    'Molina',
                    'Beltran',
                    'Blanco',
                    'Nunez',
                    'Becerra',
                    'Contreras',
                    'Barrera',
                    'Gallardo',
                    'Serrano',
                    'Chaparro',
                    'Bautista',
                    'Tellez',
                    'Prada',
                    'Jaimes',
                    'Buitrago',
                    'Ochoa',
                    'Rueda',
                    'Santos',
                    'Franco',
                    'Valencia',
                    'Cardona',
                    'Bedoya',
                    'Correa',
                    'Galvis'
                )
            ) AS apellidos
        FROM (
            SELECT
                nums.i
            FROM (
                SELECT
                    ones.n + (tens.n * 10) + (hundreds.n * 100) + 1 AS i
                FROM
                    (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                     UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
                CROSS JOIN
                    (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                     UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) tens
                CROSS JOIN
                    (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) hundreds
            ) nums
            WHERE nums.i BETWEEN 1 AND 500
        ) seq
    ) genero_data
) base;
