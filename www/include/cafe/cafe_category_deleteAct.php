<?php
include '../../config/config.php';

$id = $_POST['id_cat'];

$stmt = $conn->prepare("DELETE FROM tb_category WHERE id_cat=?");
$stmt->bind_param("i", $id);
$stmt->execute();

echo "DELETED";
