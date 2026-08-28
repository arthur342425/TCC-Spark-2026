<?php
/**
 * SPARK — sair.
 * Limpa a sessão inteira e o cookie, não só a variável do usuário.
 */

declare(strict_types=1);

require __DIR__ . '/config/config.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: ' . url('login.php'));
exit;
