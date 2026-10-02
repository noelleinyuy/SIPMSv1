<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_admin_login(): void
{
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
        header("Location: ../login.php?role=admin");
        exit;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function admin_flash(string $type, string $message): void
{
    $_SESSION["admin_flash"] = ["type" => $type, "message" => $message];
}

function admin_get_flash(): ?array
{
    if (!empty($_SESSION["admin_flash"])) {
        $flash = $_SESSION["admin_flash"];
        unset($_SESSION["admin_flash"]);
        return $flash;
    }
    return null;
}