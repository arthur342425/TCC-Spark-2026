<?php
session_start();
require "conexao.php";

$nome  = trim($_POST["login-nome"]);
$email = trim($_POST["login-email"]);
$senha = trim($_POST["login-senha"]);

$stmt = $pdo->prepare("SELECT * FROM usuario WHERE nome_usuario = :nome AND email = :email AND ativo = 1");
$stmt->execute([':nome' => $nome, ':email' => $email]);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($senha, $user['senha_hash'])) {
    header("Location: conexa2o.php");
    exit;
}

$_SESSION['user_id'] = $user['id_usuario'];


if ($user['idtipos_user'] === 2) {
    header('Location: index.php');
} else {
    header('Location: admin/dashboard.php');
}
exit;