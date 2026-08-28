<?php
/**
 * SPARK — comentários de um post.
 *
 * GET  ?id=123          — lista
 * POST acao=criar       — comenta (notifica o autor e as @menções)
 * POST acao=apagar      — autor do comentário ou dono do post
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu   = exigir_login();
$pdo  = db();
$acao = $_POST['acao'] ?? 'listar';

// =========================================================
// LISTAR
// =========================================================
if ($acao === 'listar') {
    $idmidia = (int) ($_GET['id'] ?? 0);
    if ($idmidia <= 0) json_err('Post inválido.');

    try {
        $st = $pdo->prepare(
            'SELECT c.id, c.texto, c.criado_em,
                    u.id_usuario, u.nome_usuario, u.nome_exibicao,
                    u.foto_perfil_url, u.estado_criativo, u.plano
               FROM comentarios c
               JOIN usuario u ON u.id_usuario = c.idusuario
              WHERE c.idmidias = ?
              ORDER BY c.criado_em ASC
              LIMIT 200'
        );
        $st->execute([$idmidia]);

        $st2 = $pdo->prepare('SELECT idusuario FROM midias WHERE id_midia = ?');
        $st2->execute([$idmidia]);
        $dono = (int) $st2->fetchColumn();

        $lista = array_map(static function (array $c) use ($eu, $dono) {
            return [
                'id'        => (int) $c['id'],
                'texto'     => $c['texto'],
                'quando'    => tempo_relativo($c['criado_em']),
                'posso_apagar' => ((int) $c['id_usuario'] === $eu || $dono === $eu),
                'autor' => [
                    'id'      => (int) $c['id_usuario'],
                    'usuario' => $c['nome_usuario'],
                    'nome'    => $c['nome_exibicao'] ?: $c['nome_usuario'],
                    'foto'    => avatar_url($c['foto_perfil_url']),
                    'estado'  => $c['estado_criativo'],
                    'pro'     => $c['plano'] === 'pro',
                ],
            ];
        }, $st->fetchAll());

        json_ok(['comentarios' => $lista]);

    } catch (Throwable $e) {
        error_log('[spark][coment:listar] ' . $e->getMessage());
        json_err('Não foi possível carregar os comentários.', 500);
    }
}

exigir_post();
exigir_csrf();

// =========================================================
// CRIAR
// =========================================================
if ($acao === 'criar') {
    $idmidia = (int) ($_POST['id'] ?? 0);
    $texto   = mb_substr(trim((string) ($_POST['texto'] ?? '')), 0, 1000);

    if ($idmidia <= 0)  json_err('Post inválido.');
    if ($texto === '')  json_err('Escreva alguma coisa antes de enviar.');

    try {
        $st = $pdo->prepare('SELECT idusuario FROM midias WHERE id_midia = ?');
        $st->execute([$idmidia]);
        $dono = $st->fetchColumn();
        if ($dono === false) json_err('Post não encontrado.', 404);

        $pdo->prepare('INSERT INTO comentarios (idusuario, idmidias, texto) VALUES (?, ?, ?)')
            ->execute([$eu, $idmidia, $texto]);
        $idcoment = (int) $pdo->lastInsertId();

        registrar_interacao($eu, $idmidia, 'comentario');

        $trecho = mb_substr($texto, 0, 80);
        notificar((int) $dono, $eu, 'comentario', $idmidia, $trecho);

        // @menções viram notificação para quem foi citado
        if (preg_match_all('/@([A-Za-z0-9_\.]{2,50})/', $texto, $m)) {
            $stU = $pdo->prepare('SELECT id_usuario FROM usuario WHERE nome_usuario = ? AND ativo = 1');
            foreach (array_unique($m[1]) as $apelido) {
                $stU->execute([$apelido]);
                $alvo = $stU->fetchColumn();
                if ($alvo && (int) $alvo !== (int) $dono) {
                    notificar((int) $alvo, $eu, 'mencao', $idmidia, $trecho);
                }
            }
        }

        $mim = usuario_atual();
        $st  = $pdo->prepare('SELECT COUNT(*) FROM comentarios WHERE idmidias = ?');
        $st->execute([$idmidia]);

        json_out([
            'ok'    => true,
            'total' => (int) $st->fetchColumn(),
            'comentario' => [
                'id'     => $idcoment,
                'texto'  => $texto,
                'quando' => 'agora',
                'posso_apagar' => true,
                'autor'  => [
                    'id'      => $eu,
                    'usuario' => $mim['nome_usuario'],
                    'nome'    => $mim['nome_exibicao'] ?: $mim['nome_usuario'],
                    'foto'    => avatar_url($mim['foto_perfil_url']),
                    'estado'  => $mim['estado_criativo'] ?? 'observando',
                    'pro'     => ($mim['plano'] ?? 'free') === 'pro',
                ],
            ],
        ], 201);

    } catch (Throwable $e) {
        error_log('[spark][coment:criar] ' . $e->getMessage());
        json_err('Não foi possível comentar agora.', 500);
    }
}

// =========================================================
// APAGAR
// =========================================================
if ($acao === 'apagar') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_err('Comentário inválido.');

    try {
        $st = $pdo->prepare(
            'SELECT c.idusuario AS autor, m.idusuario AS dono, c.idmidias
               FROM comentarios c
               JOIN midias m ON m.id_midia = c.idmidias
              WHERE c.id = ?'
        );
        $st->execute([$id]);
        $c = $st->fetch();

        if (!$c) json_err('Comentário não encontrado.', 404);
        if ((int) $c['autor'] !== $eu && (int) $c['dono'] !== $eu) {
            json_err('Você não pode apagar esse comentário.', 403);
        }

        $pdo->prepare('DELETE FROM comentarios WHERE id = ?')->execute([$id]);

        $st = $pdo->prepare('SELECT COUNT(*) FROM comentarios WHERE idmidias = ?');
        $st->execute([(int) $c['idmidias']]);

        json_ok(['total' => (int) $st->fetchColumn()]);

    } catch (Throwable $e) {
        error_log('[spark][coment:apagar] ' . $e->getMessage());
        json_err('Não foi possível apagar o comentário.', 500);
    }
}

json_err('Ação desconhecida.', 404);
