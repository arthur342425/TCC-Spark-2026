<?php
/**
 * SPARK — ações rápidas do usuário.
 * Tudo aqui é POST + CSRF e alimenta o perfil de gosto do feed.
 *
 * acao=curtir | salvar | seguir | estado
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu = exigir_login();
exigir_post();
exigir_csrf();

$pdo  = db();
$acao = $_POST['acao'] ?? '';

// =========================================================
// CURTIR — alterna e devolve o total já atualizado
// =========================================================
if ($acao === 'curtir') {
    $idmidia = (int) ($_POST['id'] ?? 0);
    if ($idmidia <= 0) json_err('Post inválido.');

    try {
        $st = $pdo->prepare('SELECT idusuario FROM midias WHERE id_midia = ?');
        $st->execute([$idmidia]);
        $dono = $st->fetchColumn();
        if ($dono === false) json_err('Post não encontrado.', 404);

        $st = $pdo->prepare('SELECT 1 FROM curtidas WHERE idusuario = ? AND idmidias = ?');
        $st->execute([$eu, $idmidia]);

        if ($st->fetch()) {
            $pdo->prepare('DELETE FROM curtidas WHERE idusuario = ? AND idmidias = ?')
                ->execute([$eu, $idmidia]);
            $curtiu = false;
        } else {
            $pdo->prepare('INSERT INTO curtidas (idusuario, idmidias) VALUES (?, ?)')
                ->execute([$eu, $idmidia]);
            $curtiu = true;

            registrar_interacao($eu, $idmidia, 'curtida');
            notificar((int) $dono, $eu, 'curtida', $idmidia);
        }

        $st = $pdo->prepare('SELECT COUNT(*) FROM curtidas WHERE idmidias = ?');
        $st->execute([$idmidia]);

        json_ok(['curtiu' => $curtiu, 'total' => (int) $st->fetchColumn()]);

    } catch (Throwable $e) {
        error_log('[spark][curtir] ' . $e->getMessage());
        json_err('Não foi possível curtir agora.', 500);
    }
}

// =========================================================
// SALVAR — guarda o post numa pasta (cria "Salvos" se preciso)
// =========================================================
if ($acao === 'salvar') {
    $idmidia = (int) ($_POST['id'] ?? 0);
    $idpasta = (int) ($_POST['pasta'] ?? 0);
    if ($idmidia <= 0) json_err('Post inválido.');

    try {
        // Já está salvo em alguma pasta minha? Então desfaz.
        $st = $pdo->prepare(
            'SELECT mp.idPastas
               FROM midias_em_pasta mp
               JOIN Pastas p ON p.id = mp.idPastas
              WHERE mp.idmidias = ? AND p.idusuario = ?'
        );
        $st->execute([$idmidia, $eu]);
        $jaSalvo = $st->fetchAll(PDO::FETCH_COLUMN);

        if ($jaSalvo && !$idpasta) {
            $lac = implode(',', array_fill(0, count($jaSalvo), '?'));
            $pdo->prepare("DELETE FROM midias_em_pasta WHERE idmidias = ? AND idPastas IN ($lac)")
                ->execute(array_merge([$idmidia], $jaSalvo));
            json_ok(['salvo' => false]);
        }

        if (!$idpasta) {
            $st = $pdo->prepare('SELECT id FROM Pastas WHERE idusuario = ? AND nome = ? LIMIT 1');
            $st->execute([$eu, 'Salvos']);
            $idpasta = (int) ($st->fetchColumn() ?: 0);

            if (!$idpasta) {
                $pdo->prepare('INSERT INTO Pastas (idusuario, nome, descricao) VALUES (?, ?, ?)')
                    ->execute([$eu, 'Salvos', 'Referências que eu guardei pra depois']);
                $idpasta = (int) $pdo->lastInsertId();
            }
        } else {
            $st = $pdo->prepare('SELECT 1 FROM Pastas WHERE id = ? AND idusuario = ?');
            $st->execute([$idpasta, $eu]);
            if (!$st->fetch()) json_err('Essa pasta não é sua.', 403);
        }

        $pdo->prepare('INSERT IGNORE INTO midias_em_pasta (idPastas, idmidias, posicao) VALUES (?, ?, 0)')
            ->execute([$idpasta, $idmidia]);

        registrar_interacao($eu, $idmidia, 'compartilhamento');

        json_ok(['salvo' => true, 'pasta' => $idpasta]);

    } catch (Throwable $e) {
        error_log('[spark][salvar] ' . $e->getMessage());
        json_err('Não foi possível salvar agora.', 500);
    }
}

// =========================================================
// SEGUIR — alterna e devolve os contadores do perfil alvo
// =========================================================
if ($acao === 'seguir') {
    $alvo = (int) ($_POST['id'] ?? 0);
    if ($alvo <= 0)  json_err('Artista inválido.');
    if ($alvo === $eu) json_err('Você não pode seguir a si mesmo.');

    try {
        $st = $pdo->prepare('SELECT 1 FROM usuario WHERE id_usuario = ? AND ativo = 1');
        $st->execute([$alvo]);
        if (!$st->fetch()) json_err('Artista não encontrado.', 404);

        $st = $pdo->prepare('SELECT 1 FROM seguidores WHERE idseguidor = ? AND idseguido = ?');
        $st->execute([$eu, $alvo]);

        if ($st->fetch()) {
            $pdo->prepare('DELETE FROM seguidores WHERE idseguidor = ? AND idseguido = ?')
                ->execute([$eu, $alvo]);
            $seguindo = false;
        } else {
            $pdo->prepare('INSERT INTO seguidores (idseguidor, idseguido) VALUES (?, ?)')
                ->execute([$eu, $alvo]);
            $seguindo = true;
            notificar($alvo, $eu, 'seguidor');
        }

        $st = $pdo->prepare('SELECT COUNT(*) FROM seguidores WHERE idseguido = ?');
        $st->execute([$alvo]);

        json_ok(['seguindo' => $seguindo, 'seguidores' => (int) $st->fetchColumn()]);

    } catch (Throwable $e) {
        error_log('[spark][seguir] ' . $e->getMessage());
        json_err('Não foi possível concluir agora.', 500);
    }
}

// =========================================================
// ESTADO CRIATIVO — o que alimenta o Botão Conexão
// =========================================================
if ($acao === 'estado') {
    $novo = (string) ($_POST['estado'] ?? '');
    if (!array_key_exists($novo, estados())) json_err('Estado inválido.');

    try {
        $pdo->prepare('UPDATE usuario SET estado_criativo = ?, estado_em = NOW() WHERE id_usuario = ?')
            ->execute([$novo, $eu]);

        json_ok([
            'estado' => $novo,
            'rotulo' => estados()[$novo]['rotulo'],
        ]);

    } catch (Throwable $e) {
        error_log('[spark][estado] ' . $e->getMessage());
        json_err('Não foi possível atualizar seu estado.', 500);
    }
}

json_err('Ação desconhecida.', 404);
