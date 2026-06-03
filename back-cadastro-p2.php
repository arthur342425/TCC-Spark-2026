<?php
session_start();
require 'conexao.php';

ini_set('display_errors', 1); // tirar em produção
error_reporting(E_ALL);       // tirar em produção

if (empty($_SESSION['cadastro'])) {
    http_response_code(403);
    exit;
}

// ETAPA 2 — bio e imagem
if (!isset($_POST['finalizar'])) { // ← SEM ALTERAÇÃO, lógica correta aqui

    $_SESSION['cadastro']['bio'] = trim($_POST['bio'] ?? '');

    $foto_url = null;

    if (!empty($_FILES['img_user']['tmp_name'])) {
        $arquivo          = $_FILES['img_user'];
        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif'];
        $tamanho_max      = 5 * 1024 * 1024;

        if (in_array($arquivo['type'], $tipos_permitidos) && $arquivo['size'] <= $tamanho_max) {
            $pasta = 'profile_pics/';
            if (!is_dir($pasta)) mkdir($pasta, 0755, true);
            $extensao    = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
            $nome_seguro = uniqid('pfp_', true) . '.' . $extensao;
            $destino     = $pasta . $nome_seguro;
            if (move_uploaded_file($arquivo['tmp_name'], $destino)) {
                $foto_url = $destino;
            }
        }
    }

    $_SESSION['cadastro']['foto_url'] = $foto_url;
    http_response_code(200);
    exit;
}


$nome     = $_SESSION['cadastro']['cadnome']  ?? '';
$email    = $_SESSION['cadastro']['cademail'] ?? '';
$senha    = $_SESSION['cadastro']['cadsenha'] ?? '';
$bio      = $_SESSION['cadastro']['bio']      ?? '';
$foto_url = $_SESSION['cadastro']['foto_url'] ?? null;

if (empty($nome) || empty($email) || empty($senha)) {
    http_response_code(400);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO usuario (nome_usuario, email, senha_hash, bio, foto_perfil_url, idtipos_user, ativo, criado_em, pais)
        VALUES (:nome, :email, :senha, :bio, :foto, 2, 1, NOW(), :pais)
    ");
    $stmt->execute([
        ':nome'  => $nome,
        ':email' => $email,
        ':senha' => $senha,
        ':bio'   => $bio,
        ':foto'  => $foto_url,
        ':pais'  => 'brasil'
    ]);

    $_SESSION['user_id']      = $pdo->lastInsertId();

   


    unset($_SESSION['cadastro']);

    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit;
}