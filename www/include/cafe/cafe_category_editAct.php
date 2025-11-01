<?php
include '../../config/config.php';

$id = $_POST['id_cat'];
$name = $_POST['name_cat'];
$icon = $_POST['icon'];

$stmt = $conn->prepare("UPDATE tb_category SET name_cat=?, icon=? WHERE id_cat=?");
$stmt->bind_param("ssi", $name, $icon, $id);
$stmt->execute();

echo "OK";
