<?php
declare(strict_types=1);

class Validator
{
    public static function validatePersona(array $input): array
    {
        $errors = [];

        $nombres = trim((string) ($input['nombres_apellidos'] ?? ''));
        $documento = trim((string) ($input['numero_documento'] ?? ''));
        $celular = trim((string) ($input['celular'] ?? ''));
        $esTestigo = isset($input['es_testigo']) ? 1 : 0;

        if ($nombres === '') {
            $errors['nombres_apellidos'] = 'Nombres y apellidos son obligatorios.';
        } elseif (mb_strlen($nombres) < 3 || mb_strlen($nombres) > 120) {
            $errors['nombres_apellidos'] = 'Debe tener entre 3 y 120 caracteres.';
        }

        if ($documento === '') {
            $errors['numero_documento'] = 'El numero de documento es obligatorio.';
        } elseif (!preg_match('/^[0-9]{5,20}$/', $documento)) {
            $errors['numero_documento'] = 'Use solo numeros (5 a 20 digitos).';
        }

        if ($celular === '') {
            $errors['celular'] = 'El celular es obligatorio.';
        } elseif (!preg_match('/^[0-9]{7,20}$/', $celular)) {
            $errors['celular'] = 'Use solo numeros (7 a 20 digitos).';
        }

        return [[
            'nombres_apellidos' => $nombres,
            'numero_documento' => $documento,
            'celular' => $celular,
            'es_testigo' => $esTestigo,
        ], $errors];
    }

    public static function validateReunion(array $input): array
    {
        $errors = [];

        $nombre = trim((string) ($input['nombre_reunion'] ?? ''));
        $objetivo = trim((string) ($input['objetivo'] ?? ''));
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
        }

        if ($organizacion === '') {
            $errors['organizacion'] = 'La organizacion es obligatoria.';
        } elseif (mb_strlen($organizacion) > 120) {
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
            'organizacion' => $organizacion,
            'lugar_reunion' => $lugar,
            'fecha' => $fecha,
            'hora' => $hora,
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
}
