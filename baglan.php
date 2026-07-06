<?php
// Triskel Veri Tabanı Bağlantı Köprüsü
$host = "localhost";
$user = "root";
$password = "";
$database = "triskel";

try {
    $db = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Kadim veri tabanına bağlanılamadı: " . $e->getMessage());
}
?>