<?php

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "senai_conecta";

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Erro na conexão com o banco de dados: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");