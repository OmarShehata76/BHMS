<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

class Auth {

    // Login: verify credentials, start session
    public static function login(string $email, string $password): bool {
        $staff = db()->fetchOne(
            "SELECT staff_id, name, email, password_hash, role, active
             FROM staff WHERE email = ? LIMIT 1",
            's', $email
        );

        
        if (!$staff || !$staff['active']) return false;
        if (!password_verify($password, $staff['password_hash'])) return false;

        // Update last login
        db()->execute(
            "UPDATE staff SET last_login = NOW() WHERE staff_id = ?",
            'i', $staff['staff_id']
        );

        // Set session
        $_SESSION['staff_id']   = $staff['staff_id'];
        $_SESSION['staff_name'] = $staff['name'];
        $_SESSION['staff_role'] = $staff['role'];
        $_SESSION['logged_in']  = true;

        // Audit log
        AuditLogger::log('LOGIN', 'staff', $staff['staff_id'], null, 'Logged in');

        return true;
    }

    // Logout
    public static function logout(): void {
        AuditLogger::log('LOGOUT', 'staff', $_SESSION['staff_id'] ?? null, null, 'Logged out');
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Check if logged in
    public static function check(): void {
        if (empty($_SESSION['logged_in'])) {
            header('Location: login.php');
            exit;
        }
    }

    // Check role permission
    public static function requireRole(array $roles): void {
        self::check();
        if (!in_array($_SESSION['staff_role'], $roles)) {
            http_response_code(403);
            die('<h2 style="color:#E05C5C;font-family:sans-serif;padding:40px">
                 403 — Access Denied. Your role does not permit this action.</h2>');
        }
    }

    public static function role(): string {
        return $_SESSION['staff_role'] ?? '';
    }

    public static function id(): int {
        return (int)($_SESSION['staff_id'] ?? 0);
    }

    public static function can(array $roles): bool {
        return in_array(self::role(), $roles);
    }
}

// ── Audit Logger ────────────────────────────────────
class AuditLogger {
    public static function log(
        string $action,
        ?string $table    = null,
        ?int    $recordId = null,
        mixed   $oldVal   = null,
        mixed   $newVal   = null
    ): void {
        try {
            db()->execute(
                "INSERT INTO audit_logs
                 (staff_id, action, table_name, record_id, old_value, new_value, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                'issiiss',
                $_SESSION['staff_id'] ?? null,
                $action,
                $table,
                $recordId,
                $oldVal ? json_encode($oldVal) : null,
                $newVal ? json_encode($newVal) : null,
                $_SERVER['REMOTE_ADDR'] ?? null
            );
        } catch (Exception $e) {
            error_log('AuditLog error: ' . $e->getMessage());
        }
    }
}
