<?php
require_once __DIR__ . '/../../config/database.php';

class Foto {
    public static function allByRuimte($ruimte_id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM ruimte_foto WHERE ruimte_id = ? ORDER BY id DESC");
        $stmt->execute([$ruimte_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function add($ruimte_id, $pad) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO ruimte_foto (ruimte_id, pad) VALUES (?, ?)");
        $stmt->execute([$ruimte_id, $pad]);
    }
}
