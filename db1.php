<?php
// Production Database Configuration for AL FATIHA INSTITUTE (InfinityFree)
// Copy this content into your live server's "htdocs/config/db.php"

define('DB_HOST', 'sql104.infinityfree.com');
define('DB_USER', 'if0_43344815');
define('DB_PASS', 'SnshakibnuR980');
define('DB_NAME', 'if0_43344815_afidb');

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
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
            error_log("Database Connection Error: " . $e->getMessage());
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Database Connection Error</h2><p>Could not connect to the database. Please check your database credentials or wait a few moments for server propagation.</p></div>");
        }
    }
    return $pdo;
}

