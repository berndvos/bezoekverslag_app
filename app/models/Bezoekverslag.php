<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Bezoekverslag {

  public static function all() {
    $pdo = Database::getConnection();
    $q = trim($_GET['q'] ?? '');
    if ($q) {
      $stmt = $pdo->prepare("SELECT * FROM bezoekverslag WHERE naam LIKE ? OR klantnaam LIKE ? ORDER BY datum DESC");
      $stmt->execute(['%'.$q.'%', '%'.$q.'%']);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $pdo->query("SELECT * FROM bezoekverslag ORDER BY datum DESC")->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function create($data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("INSERT INTO bezoekverslag (naam, datum) VALUES (?, NOW())");
    $stmt->execute([$data['naam']]);
    return $pdo->lastInsertId();
  }

  public static function find($id) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM bezoekverslag WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function updateRelatie($id, $data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE bezoekverslag SET 
      klantnaam=?, projecttitel=?, betalingstermijn=?, straat=?, postcode=?, plaats=?, land=?, email=?, website=? 
      WHERE id=?");
    $stmt->execute([
      $data['klantnaam'] ?? '', $data['projecttitel'] ?? '', $data['betalingstermijn'] ?? '',
      $data['straat'] ?? '', $data['postcode'] ?? '', $data['plaats'] ?? '', $data['land'] ?? '',
      $data['email'] ?? '', $data['website'] ?? '', $id
    ]);
  }

  public static function updateContact($id, $data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE bezoekverslag SET
      cp_achternaam=?, cp_voorvoegsel=?, cp_voorletters=?, cp_roepnaam=?, cp_geslacht=?, cp_titel=?, cp_functie=?, cp_tekenbevoegd=?, cp_telefoon=?, cp_mobiel=?, cp_email=? 
      WHERE id=?");
    $stmt->execute([
      $data['cp_achternaam'] ?? '', $data['cp_voorvoegsel'] ?? '', $data['cp_voorletters'] ?? '',
      $data['cp_roepnaam'] ?? '', $data['cp_geslacht'] ?? '', $data['cp_titel'] ?? '',
      $data['cp_functie'] ?? '', $data['cp_tekenbevoegd'] ?? '', $data['cp_telefoon'] ?? '',
      $data['cp_mobiel'] ?? '', $data['cp_email'] ?? '', $id
    ]);
  }

  public static function updateLeveranciers($id, $data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE bezoekverslag SET
      lev_ict_type=?, lev_ict_naam=?, lev_telecom_type=?, lev_telecom_naam=?, lev_av_type=?, lev_av_naam=? 
      WHERE id=?");
    $stmt->execute([
      $data['lev_ict_type'] ?? '', $data['lev_ict_naam'] ?? '',
      $data['lev_telecom_type'] ?? '', $data['lev_telecom_naam'] ?? '',
      $data['lev_av_type'] ?? '', $data['lev_av_naam'] ?? '', $id
    ]);
  }

  public static function updateWensen($id, $data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE bezoekverslag SET
      situatie=?, wensen=?, afvoer=?, offertedatum=?, installatiedatum=?, budget=?, byod=?, device=?, installatie_adres=?, aantal_installaties=?, parkeren=?, betaald_parkeren=?, boortijden=?, parkeerbeperkingen=?, laadtijden=? 
      WHERE id=?");
    $stmt->execute([
      $data['situatie'] ?? '', $data['wensen'] ?? '', $data['afvoer'] ?? '',
      $data['offertedatum'] ?? '', $data['installatiedatum'] ?? '', $data['budget'] ?? '',
      !empty($data['byod']) ? 1 : 0, $data['device'] ?? '', $data['installatie_adres'] ?? '',
      $data['aantal_installaties'] ?? '', $data['parkeren'] ?? '', $data['betaald_parkeren'] ?? '',
      $data['boortijden'] ?? '', $data['parkeerbeperkingen'] ?? '', $data['laadtijden'] ?? '', $id
    ]);
  }

}
