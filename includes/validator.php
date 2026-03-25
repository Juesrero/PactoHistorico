<?php
declare(strict_types=1);

class Validator
{
    public static function validatePersona(array $input): array
    {
        $errors = [];

        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidos = trim((string) ($input['apellidos'] ?? ''));
        $documento = strtoupper(trim((string) ($input['numero_documento'] ?? ($input['identificacion'] ?? ''))));
        $genero = trim((string) ($input['genero'] ?? ''));
        $fechaNacimiento = trim((string) ($input['fecha_nacimiento'] ?? ''));
        $correo = trim((string) ($input['correo'] ?? ''));
        $celular = trim((string) ($input['celular'] ?? ($input['telefono'] ?? '')));
        $direccion = trim((string) ($input['direccion'] ?? ''));
        $tipoPoblacionId = isset($input['tipo_poblacion_id']) && (string) $input['tipo_poblacion_id'] !== ''
            ? (int) $input['tipo_poblacion_id']
            : null;
        $esTestigo = self::inputToBool($input['es_testigo'] ?? 0) ? 1 : 0;
        $esJurado = self::inputToBool($input['es_jurado'] ?? 0) ? 1 : 0;
        $esMilitante = self::inputToBool($input['es_militante'] ?? 0) ? 1 : 0;

        if ($documento === '') {
            $errors['numero_documento'] = 'La identificacion es obligatoria.';
        } elseif (!preg_match('/^[A-Z0-9\-]{5,20}$/', $documento)) {
            $errors['numero_documento'] = 'Use entre 5 y 20 caracteres (letras, numeros o guion).';
        }

        if ($nombres === '') {
            $errors['nombres'] = 'Los nombres son obligatorios.';
        } elseif (mb_strlen($nombres) < 2 || mb_strlen($nombres) > 60) {
            $errors['nombres'] = 'Los nombres deben tener entre 2 y 60 caracteres.';
        }

        if ($apellidos === '') {
            $errors['apellidos'] = 'Los apellidos son obligatorios.';
        } elseif (mb_strlen($apellidos) < 2 || mb_strlen($apellidos) > 60) {
            $errors['apellidos'] = 'Los apellidos deben tener entre 2 y 60 caracteres.';
        }

        $nombresApellidos = trim($nombres . ' ' . $apellidos);
        if ($nombresApellidos !== '' && mb_strlen($nombresApellidos) > 120) {
            $errors['nombres'] = 'El nombre completo no puede superar 120 caracteres.';
        }

        $allowedGeneros = ['', 'Femenino', 'Masculino', 'Otro'];
        if (!in_array($genero, $allowedGeneros, true)) {
            $errors['genero'] = 'Seleccione un genero valido.';
        }

        if ($fechaNacimiento !== '') {
            if (!self::isValidDate($fechaNacimiento)) {
                $errors['fecha_nacimiento'] = 'La fecha de nacimiento es invalida.';
            } elseif ($fechaNacimiento > date('Y-m-d')) {
                $errors['fecha_nacimiento'] = 'La fecha de nacimiento no puede estar en el futuro.';
            }
        }

        if ($correo !== '') {
            if (mb_strlen($correo) > 120) {
                $errors['correo'] = 'El correo no puede superar 120 caracteres.';
            } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $errors['correo'] = 'Ingrese un correo electronico valido.';
            }
        }

        if ($celular === '') {
            $errors['celular'] = 'El telefono es obligatorio.';
        } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $celular)) {
            $errors['celular'] = 'Use entre 7 y 20 caracteres (numeros, espacios, + o guion).';
        }

        if ($direccion !== '' && mb_strlen($direccion) > 255) {
            $errors['direccion'] = 'La direccion no puede superar 255 caracteres.';
        }

        if ($tipoPoblacionId !== null && $tipoPoblacionId <= 0) {
            $errors['tipo_poblacion_id'] = 'Seleccione un tipo de poblacion valido.';
        }

        return [[
            'nombres_apellidos' => $nombresApellidos,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'numero_documento' => $documento,
            'genero' => $genero !== '' ? $genero : null,
            'fecha_nacimiento' => $fechaNacimiento !== '' ? $fechaNacimiento : null,
            'correo' => $correo !== '' ? $correo : null,
            'celular' => $celular,
            'direccion' => $direccion !== '' ? $direccion : null,
            'tipo_poblacion_id' => $tipoPoblacionId,
            'es_testigo' => $esTestigo,
            'es_jurado' => $esJurado,
            'es_militante' => $esMilitante,
        ], $errors];
    }

    public static function validateReunion(array $input): array
    {
        $errors = [];

        $nombre = trim((string) ($input['nombre_reunion'] ?? ''));
        $objetivo = trim((string) ($input['objetivo'] ?? ''));
        $tipo = trim((string) ($input['tipo_reunion'] ?? ''));
        $organizacion = trim((string) ($input['organizacion'] ?? ''));
        $lugar = trim((string) ($input['lugar_reunion'] ?? ''));
        $fecha = trim((string) ($input['fecha'] ?? ''));
        $hora = trim((string) ($input['hora'] ?? ''));

        if ($nombre === '') {
            $errors['nombre_reunion'] = 'El nombre de la reunion es obligatorio.';
        } elseif (mb_strlen($nombre) > 150) {
            $errors['nombre_reunion'] = 'Maximo 150 caracteres.';
        }

        if ($objetivo === '') {
            $errors['objetivo'] = 'El objetivo es obligatorio.';
        } elseif (mb_strlen($objetivo) > 2000) {
            $errors['objetivo'] = 'El objetivo no puede superar 2000 caracteres.';
        }

        if ($tipo === '') {
            $errors['tipo_reunion'] = 'El tipo de reunion es obligatorio.';
        } elseif (mb_strlen($tipo) < 3 || mb_strlen($tipo) > 80) {
            $errors['tipo_reunion'] = 'El tipo de reunion debe tener entre 3 y 80 caracteres.';
        }

        if ($organizacion !== '' && mb_strlen($organizacion) > 120) {
            $errors['organizacion'] = 'Maximo 120 caracteres.';
        }

        if ($lugar === '') {
            $errors['lugar_reunion'] = 'El lugar de la reunion es obligatorio.';
        } elseif (mb_strlen($lugar) > 150) {
            $errors['lugar_reunion'] = 'Maximo 150 caracteres.';
        }

        if (!self::isValidDate($fecha)) {
            $errors['fecha'] = 'La fecha es invalida.';
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            $errors['hora'] = 'La hora es invalida.';
        }

        return [[
            'nombre_reunion' => $nombre,
            'objetivo' => $objetivo,
            'tipo_reunion' => $tipo,
            'organizacion' => $organizacion !== '' ? $organizacion : $tipo,
            'lugar_reunion' => $lugar,
            'fecha' => $fecha,
            'hora' => $hora,
        ], $errors];
    }

    public static function validateActa(array $input): array
    {
        $errors = [];

        $nombreObjetivo = trim((string) ($input['nombre_o_objetivo'] ?? ''));
        $responsable = trim((string) ($input['responsable'] ?? ''));
        $lugar = trim((string) ($input['lugar'] ?? ''));

        if ($nombreObjetivo === '') {
            $errors['nombre_o_objetivo'] = 'El nombre u objetivo del acta es obligatorio.';
        } elseif (mb_strlen($nombreObjetivo) < 3 || mb_strlen($nombreObjetivo) > 200) {
            $errors['nombre_o_objetivo'] = 'Use entre 3 y 200 caracteres.';
        }

        if ($responsable === '') {
            $errors['responsable'] = 'El responsable es obligatorio.';
        } elseif (mb_strlen($responsable) < 3 || mb_strlen($responsable) > 150) {
            $errors['responsable'] = 'Use entre 3 y 150 caracteres.';
        }

        if ($lugar === '') {
            $errors['lugar'] = 'El lugar es obligatorio.';
        } elseif (mb_strlen($lugar) < 3 || mb_strlen($lugar) > 150) {
            $errors['lugar'] = 'Use entre 3 y 150 caracteres.';
        }

        return [[
            'nombre_o_objetivo' => $nombreObjetivo,
            'responsable' => $responsable,
            'lugar' => $lugar,
        ], $errors];
    }

    public static function validateAsistencia(array $input): array
    {
        $errors = [];

        $reunionId = (int) ($input['reunion_id'] ?? 0);
        $personaId = (int) ($input['persona_id'] ?? 0);
        $observacion = trim((string) ($input['observacion'] ?? ''));

        if ($reunionId <= 0) {
            $errors['reunion_id'] = 'Debe seleccionar una reunion.';
        }

        if ($personaId <= 0) {
            $errors['persona_id'] = 'Debe seleccionar una persona.';
        }

        if (mb_strlen($observacion) > 255) {
            $errors['observacion'] = 'La observacion no puede superar 255 caracteres.';
        }

        return [[
            'reunion_id' => $reunionId,
            'persona_id' => $personaId,
            'observacion' => $observacion !== '' ? $observacion : null,
            'fecha_registro' => date('Y-m-d'),
            'hora_registro' => date('H:i:s'),
        ], $errors];
    }

    private static function isValidDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
    }

    private static function inputToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'on', 'yes', 'si'], true);
    }
}
