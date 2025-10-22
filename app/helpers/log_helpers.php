<?php

function log_action($action, $details = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO audit_log (user_id, user_fullname, action, details, created_at) 
         VALUES (:user_id, :user_fullname, :action, :details, NOW())"
    );
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'] ?? null,
        ':user_fullname' => $_SESSION['fullname'] ?? 'Systeem/Anoniem',
        ':action' => $action,
        ':details' => $details
    ]);
}