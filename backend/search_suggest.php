<?php
require_once '../database/db.php';
header('Content-Type: application/json');
$query = isset($_GET['q']) ? $_GET['q'] : '';
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}
$searchTerm = "%$query%";
$stmt = $conn->prepare("SELECT id, tenvatpham, giatien, url FROM vatpham 
                       WHERE tenvatpham LIKE ? OR thuonghieu LIKE ? OR mota LIKE ?
                       ORDER BY tenvatpham ASC LIMIT 6");
$stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);
?>