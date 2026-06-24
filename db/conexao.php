<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "cybersec_dojo";

$conn = new mysqli($host, $user, $pass, $db);

// Verifica se deu algum erro na conexão
if ($conn->connect_error) {
    die("" . $conn->connect_error);
}
?>