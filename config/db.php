<?php
// Database configuration for AL FATIHA INSTITUTE (afidb)

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'afidb');

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Attempt to connect to the specific database
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Self-Healing Schema: Automatically ensure role column supports 'super_admin' and 'principal'
            static $schemaChecked = false;
            if (!$schemaChecked) {
                $schemaChecked = true;
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
                    if ($col && isset($col['Type']) && strpos(strtolower($col['Type']), 'varchar') === false) {
                        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'parent'");
                    }
                    // Fix any corrupted or unassigned root roles
                    $pdo->exec("UPDATE `users` SET `role` = 'super_admin' WHERE `id` = 1 OR `username` = 'admin'");
                    $pdo->exec("UPDATE `users` SET `role` = 'principal' WHERE (`role` = '' OR `role` IS NULL) AND `username` LIKE '%principal%'");
                } catch (Exception $ign) {
                    // Ignore silently if table doesn't exist yet or during setup
                }
            }
        } catch (PDOException $e) {
            // Fallback for local development: Try to auto-create database if on localhost/dev
            try {
                $dsn_server = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
                $pdo_server = new PDO($dsn_server, DB_USER, DB_PASS);
                $pdo_server->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo_server->exec("USE `" . DB_NAME . "`");
                $pdo = $pdo_server;
            } catch (PDOException $e2) {
                // In production, display friendly error without sensitive leaks
                error_log("Database Connection Error: " . $e->getMessage());
                die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Database Connection Error</h2><p>Could not connect to the database. Please check your database settings in <code>config/db.php</code>.</p></div>");
            }
        }
    }
    return $pdo;
}
