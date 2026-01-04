<?php

$db_name = "mysql:host=localhost;port=3307;dbname=nutribox_db";
$username = "root";
$password = "";

$conn = new PDO($db_name, $username, $password);

function load_local_env($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }
        $parts = explode("=", $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === "" || getenv($key) !== false) {
            continue;
        }
        if (
            (str_starts_with($value, "\"") && str_ends_with($value, "\"")) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        putenv($key . "=" . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

load_local_env(__DIR__ . "/.env");

$env_smtp_host = getenv("SMTP_HOST");
$env_smtp_port = getenv("SMTP_PORT");
$env_smtp_user = getenv("SMTP_USERNAME");
$env_smtp_pass = getenv("SMTP_PASSWORD");
$env_smtp_encryption = getenv("SMTP_ENCRYPTION");
$env_from_email = getenv("SMTP_FROM_EMAIL");
$env_from_name = getenv("SMTP_FROM_NAME");

if (!defined("SMTP_HOST")) {
    define("SMTP_HOST", $env_smtp_host !== false ? $env_smtp_host : "");
}
if (!defined("SMTP_PORT")) {
    define("SMTP_PORT", $env_smtp_port !== false ? (int) $env_smtp_port : 0);
}
if (!defined("SMTP_USERNAME")) {
    define("SMTP_USERNAME", $env_smtp_user !== false ? $env_smtp_user : "");
}
if (!defined("SMTP_PASSWORD")) {
    define("SMTP_PASSWORD", $env_smtp_pass !== false ? $env_smtp_pass : "");
}
if (!defined("SMTP_ENCRYPTION")) {
    define(
        "SMTP_ENCRYPTION",
        $env_smtp_encryption !== false ? strtolower($env_smtp_encryption) : "",
    );
}
if (!defined("SMTP_FROM_EMAIL")) {
    define("SMTP_FROM_EMAIL", $env_from_email !== false ? $env_from_email : "");
}
if (!defined("SMTP_FROM_NAME")) {
    define(
        "SMTP_FROM_NAME",
        $env_from_name !== false ? $env_from_name : "NutriBox",
    );
}

function smtp_config_valid()
{
    if (SMTP_HOST === "" || SMTP_PORT <= 0) {
        return false;
    }
    if (SMTP_USERNAME === "" || SMTP_PASSWORD === "") {
        return false;
    }
    if (SMTP_FROM_EMAIL === "") {
        return false;
    }
    if (
        SMTP_ENCRYPTION !== "" &&
        SMTP_ENCRYPTION !== "tls" &&
        SMTP_ENCRYPTION !== "ssl"
    ) {
        return false;
    }
    return true;
}

?>
