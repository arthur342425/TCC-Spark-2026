<?php
session_start();
require 'conexao.php';

$nome  = trim($_POST['nova_nome'] ?? '');
$bio   = trim($_POST['nova_bio']  ?? '');
$senha = $_POST['nova_senha'] ?? '';

try {
    $stmt = $pdo->prepare("
        UPDATE usuario 
        SET nome_usuario = :nome, bio = :bio, senha_hash = :senha
        WHERE id_usuario = :id
    ");
    $stmt->execute([
        ':nome'  => $nome,
        ':bio'   => $bio,
        ':senha' => password_hash($senha, PASSWORD_BCRYPT),
        ':id'    => $_SESSION['user_id']
    ]);

    $_SESSION['nome_usuario'] = $nome;

    http_response_code(200);
    echo json_encode(['sucesso' => true]);
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit;
}