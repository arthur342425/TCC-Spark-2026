<?php
session_start();

$cadnome  = trim($_POST['cad-nome'] ?? '');
$cademail  = trim($_POST['cad-email'] ?? '');
$cadsenha  = ($_POST['cad-senha'] ?? '');


if (empty($cadnome) || empty($cademail) || empty($cadsenha)) {
    header('Location: login.php');
    exit;
}

$_SESSION['cadastro']['cadnome']  = $cadnome;
$_SESSION['cadastro']['cademail']  = $cademail;
$_SESSION['cadastro']['cadsenha'] = password_hash($cadsenha, PASSWORD_BCRYPT);

header('Location: login2.html');
exit;