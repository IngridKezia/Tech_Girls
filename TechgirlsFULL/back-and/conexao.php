<?php
$conexao = mysqli_connect("localhost", "root", "", "cliente");

if (!$conexao) {
    die("Erro na conexão!");
}

echo "Conectado!";
?>
