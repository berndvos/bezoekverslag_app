<?php
require_once __DIR__ . '/../../config/database.php';

class Ruimte {
    public static function allByVerslag($verslag_id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM ruimte WHERE verslag_id = ? ORDER BY id DESC");
        $stmt->execute([$verslag_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM ruimte WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($verslag_id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO ruimte (verslag_id, naam) VALUES (?, ?)");
        $stmt->execute([$verslag_id, 'Nieuwe ruimte']);
        return $pdo->lastInsertId();
    }

    public static function update($id, $data) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE ruimte SET naam=?, etage=?, bereikbaarheid=?, lift=?, afm_lift=?, voorzieningen=?, bereikb_voorzieningen=?, kabellengte=?, netwerkintegratie=?, afmetingen=?, plafond=?, wand=?, vloer=?, beperkingen=?, opmerkingen=? WHERE id=?");
        $stmt->execute([
            $data['naam'] ?? null, $data['etage'] ?? null, $data['bereikbaarheid'] ?? null,
            $data['lift'] ?? null, $data['afm_lift'] ?? null, $data['voorzieningen'] ?? null,
            $data['bereikb_voorzieningen'] ?? null, $data['kabellengte'] ?? null, $data['netwerkintegratie'] ?? null,
            $data['afmetingen'] ?? null, $data['plafond'] ?? null, $data['wand'] ?? null,
            $data['vloer'] ?? null, $data['beperkingen'] ?? null, $data['opmerkingen'] ?? null, $id
        ]);
    }
}
