<?php
function liteup_env(): array
{
    static $values;
    if ($values !== null) {
        return $values;
    }

    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $parsed = is_readable($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : [];
    $fileValues = is_array($parsed) ? $parsed : [];
    $processValues = getenv();
    if (!is_array($processValues)) {
        $processValues = [];
    }

    // Deployment-provided environment variables override local .env values.
    $values = array_merge($fileValues, $_SERVER, $_ENV, $processValues);

    return $values;
}
