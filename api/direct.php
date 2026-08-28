<?php
/**
 * SPARK — Direct (mensagens privadas).
 *
 * GET  acao=conversas            — lista com prévia e não lidas
 * GET  acao=mensagens&id=N       — mensagens da conversa (marca como lidas)
 * POST acao=enviar               — envia (por idconversa OU por idusuario)
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu   = exigir_login();
$pdo  = db();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'conversas';

/** Confere que eu realmente participo da conversa antes de mostrar qualquer coisa. */
function sou_participante(PDO $pdo, int $idconversa, int $eu): bool
{
    $st = $pdo->prepare('SELECT 1 FROM conversa_participantes WHERE idconversa = ? AND idusuario = ?');
    $st->execute([$idconversa, $eu]);
    return (bool) $st->fetch();
}

// =========================================================
// CONVERSAS
// =========================================================
if ($acao === 'conversas') {
    try {
        $st = $pdo->prepare("
            SELECT c.id AS idconversa, c.tipo, c.nome AS nome_grupo,

                   o.id_usuario AS outro_id, o.nome_usuario, o.nome_exibicao,
                   o.foto_perfil_url, o.estado_criativo, o.area_criativa, o.plano,

                   (SELECT msg.texto FROM mensagens msg
                     WHERE msg.idconversa = c.id AND msg.apagado = 0
                     ORDER BY msg.enviado_em DESC LIMIT 1) AS ultimo_texto,

                   (SELECT msg.enviado_em FROM mensagens msg
                     WHERE msg.idconversa = c.id
                     ORDER BY msg.enviado_em DESC LIMIT 1) AS ultima_hora,

                   (SELECT COUNT(*)
                      FROM mensagens_status ms
                      JOIN mensagens msg2 ON msg2.id = ms.idmensagem
                     WHERE ms.idusuario = :eu2 AND ms.lida = 0
                       AND msg2.idconversa = c.id
                       AND msg2.idusuario_remetente <> :eu3) AS nao_lidas

              FROM conversas c
              JOIN conversa_participantes meu ON meu.idconversa = c.id AND meu.idusuario = :eu
         LEFT JOIN conversa_participantes outro
                     ON outro.idconversa = c.id AND outro.idusuario <> :eu4
         LEFT JOIN usuario o ON o.id_usuario = outro.idusuario

             ORDER BY ultima_hora DESC
             LIMIT 60
        ");
        $st->execute([':eu' => $eu, ':eu2' => $eu, ':eu3' => $eu, ':eu4' => $eu]);

        $lista = array_map(static function (array $c) {
            $nome = $c['tipo'] === 'grupo'
                ? ($c['nome_grupo'] ?: 'Grupo')
                : ($c['nome_exibicao'] ?: $c['nome_usuario'] ?: 'Artista');

            return [
                'id'         => (int) $c['idconversa'],
                'nome'       => $nome,
                'usuario'    => $c['nome_usuario'],
                'outro_id'   => $c['outro_id'] ? (int) $c['outro_id'] : null,
                'foto'       => avatar_url($c['foto_perfil_url']),
                'estado'     => $c['estado_criativo'] ?? 'observando',
                'area'       => $c['area_criativa'],
                'pro'        => ($c['plano'] ?? '') === 'pro',
                'previa'     => $c['ultimo_texto'] ?: 'Conversa nova',
                'quando'     => tempo_relativo($c['ultima_hora']),
                'nao_lidas'  => (int) $c['nao_lidas'],
            ];
        }, $st->fetchAll());

        json_ok(['conversas' => $lista]);

    } catch (Throwable $e) {
        error_log('[spark][direct:conversas] ' . $e->getMessage());
        json_err('Não foi possível carregar suas conversas.', 500);
    }
}

// =========================================================
// MENSAGENS
// =========================================================
if ($acao === 'mensagens') {
    $idconversa = (int) ($_GET['id'] ?? 0);
    if ($idconversa <= 0) json_err('Conversa inválida.');
    if (!sou_participante($pdo, $idconversa, $eu)) json_err('Você não participa dessa conversa.', 403);

    try {
        $st = $pdo->prepare(
            'SELECT m.id, m.texto, m.idusuario_remetente, m.enviado_em, m.apagado,
                    u.nome_usuario, u.nome_exibicao, u.foto_perfil_url
               FROM mensagens m
               JOIN usuario u ON u.id_usuario = m.idusuario_remetente
              WHERE m.idconversa = ?
              ORDER BY m.enviado_em ASC
              LIMIT 300'
        );
        $st->execute([$idconversa]);

        $msgs = array_map(static function (array $m) use ($eu) {
            return [
                'id'     => (int) $m['id'],
                'texto'  => $m['apagado'] ? 'mensagem apagada' : $m['texto'],
                'minha'  => (int) $m['idusuario_remetente'] === $eu,
                'hora'   => date('H:i', strtotime($m['enviado_em'])),
                'dia'    => date('Y-m-d', strtotime($m['enviado_em'])),
                'autor'  => $m['nome_exibicao'] ?: $m['nome_usuario'],
                'foto'   => avatar_url($m['foto_perfil_url']),
            ];
        }, $st->fetchAll());

        // Abrir a conversa marca tudo que é do outro como lido
        $pdo->prepare(
            'UPDATE mensagens_status ms
               JOIN mensagens m ON m.id = ms.idmensagem
                SET ms.lida = 1, ms.lida_em = NOW()
              WHERE ms.idusuario = ? AND ms.lida = 0
                AND m.idconversa = ? AND m.idusuario_remetente <> ?'
        )->execute([$eu, $idconversa, $eu]);

        json_ok(['mensagens' => $msgs]);

    } catch (Throwable $e) {
        error_log('[spark][direct:mensagens] ' . $e->getMessage());
        json_err('Não foi possível carregar as mensagens.', 500);
    }
}

// =========================================================
// ENVIAR
// =========================================================
if ($acao === 'enviar') {
    exigir_post();
    exigir_csrf();

    $idconversa = (int) ($_POST['idconversa'] ?? 0);
    $iddestino  = (int) ($_POST['idusuario'] ?? 0);
    $texto      = mb_substr(trim((string) ($_POST['texto'] ?? '')), 0, 2000);

    if ($texto === '') json_err('A mensagem está vazia.');

    try {
        $pdo->beginTransaction();

        if ($idconversa) {
            if (!sou_participante($pdo, $idconversa, $eu)) {
                throw new RuntimeException('Você não participa dessa conversa.');
            }
        } elseif ($iddestino && $iddestino !== $eu) {
            $st = $pdo->prepare(
                "SELECT cp1.idconversa
                   FROM conversa_participantes cp1
                   JOIN conversa_participantes cp2
                     ON cp2.idconversa = cp1.idconversa AND cp2.idusuario = ?
                   JOIN conversas c ON c.id = cp1.idconversa AND c.tipo = 'individual'
                  WHERE cp1.idusuario = ?
                  LIMIT 1"
            );
            $st->execute([$iddestino, $eu]);
            $existente = $st->fetch();

            if ($existente) {
                $idconversa = (int) $existente['idconversa'];
            } else {
                $pdo->prepare("INSERT INTO conversas (tipo) VALUES ('individual')")->execute();
                $idconversa = (int) $pdo->lastInsertId();

                $ins = $pdo->prepare('INSERT INTO conversa_participantes (idconversa, idusuario) VALUES (?, ?)');
                $ins->execute([$idconversa, $eu]);
                $ins->execute([$idconversa, $iddestino]);
            }
        } else {
            throw new RuntimeException('Destino da mensagem não informado.');
        }

        $pdo->prepare('INSERT INTO mensagens (idconversa, idusuario_remetente, texto) VALUES (?, ?, ?)')
            ->execute([$idconversa, $eu, $texto]);
        $idmsg = (int) $pdo->lastInsertId();

        $st = $pdo->prepare('SELECT idusuario FROM conversa_participantes WHERE idconversa = ?');
        $st->execute([$idconversa]);

        $stStatus = $pdo->prepare(
            'INSERT INTO mensagens_status (idmensagem, idusuario, lida, lida_em) VALUES (?, ?, ?, ?)'
        );
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idp) {
            $souEu = ((int) $idp === $eu);
            $stStatus->execute([$idmsg, $idp, $souEu ? 1 : 0, $souEu ? date('Y-m-d H:i:s') : null]);
        }

        $pdo->commit();

        json_ok([
            'idconversa' => $idconversa,
            'mensagem'   => [
                'id'    => $idmsg,
                'texto' => $texto,
                'minha' => true,
                'hora'  => date('H:i'),
                'dia'   => date('Y-m-d'),
            ],
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[spark][direct:enviar] ' . $e->getMessage());
        json_err($e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível enviar.', 400);
    }
}

json_err('Ação desconhecida.', 404);
