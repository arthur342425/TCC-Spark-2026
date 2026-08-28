<?php
/**
 * SPARK — Explorar e Buscar.
 *
 * GET acao=explorar  — tags em alta, artistas sugeridos, mosaico recente
 * GET acao=buscar&q= — artistas, posts e tags de uma vez (usado no topo)
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu   = exigir_login();
$pdo  = db();
$acao = $_GET['acao'] ?? 'explorar';

// =========================================================
// EXPLORAR
// =========================================================
if ($acao === 'explorar') {
    try {
        // Tags em alta: peso maior para uso recente
        $st = $pdo->query("
            SELECT t.nome,
                   COUNT(mt.idmidias) AS total,
                   SUM(CASE WHEN m.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS recentes
              FROM tags t
              JOIN midia_tags mt ON mt.idtag = t.id
              JOIN midias m ON m.id_midia = mt.idmidias
             GROUP BY t.id, t.nome
             ORDER BY recentes DESC, total DESC
             LIMIT 12
        ");
        $tags = array_map(static fn($t) => [
            'nome'  => $t['nome'],
            'total' => (int) $t['total'],
        ], $st->fetchAll());

        // Artistas sugeridos: prioriza quem eu ainda não sigo e está ativo
        $st = $pdo->prepare("
            SELECT u.id_usuario, u.nome_usuario, u.nome_exibicao, u.foto_perfil_url,
                   u.area_criativa, u.estado_criativo, u.bio, u.plano,
                   (SELECT COUNT(*) FROM seguidores s WHERE s.idseguido = u.id_usuario) AS seguidores,
                   (SELECT COUNT(*) FROM midias mm WHERE mm.idusuario = u.id_usuario) AS posts
              FROM usuario u
             WHERE u.id_usuario <> :eu
               AND u.ativo = 1
               AND NOT EXISTS(SELECT 1 FROM seguidores s2
                               WHERE s2.idseguidor = :eu2 AND s2.idseguido = u.id_usuario)
             ORDER BY (
                   CASE WHEN u.area_criativa = (SELECT area_criativa FROM usuario WHERE id_usuario = :eu3)
                        THEN 20 ELSE 0 END
                 + (SELECT COUNT(*) FROM midias mm2 WHERE mm2.idusuario = u.id_usuario) * 3
                 + (SELECT COUNT(*) FROM seguidores s3 WHERE s3.idseguido = u.id_usuario) * 2
                 + CASE WHEN u.estado_em >= DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 12 ELSE 0 END
             ) DESC
             LIMIT 8
        ");
        $st->execute([':eu' => $eu, ':eu2' => $eu, ':eu3' => $eu]);

        $artistas = array_map(static fn($u) => [
            'id'         => (int) $u['id_usuario'],
            'usuario'    => $u['nome_usuario'],
            'nome'       => $u['nome_exibicao'] ?: $u['nome_usuario'],
            'foto'       => avatar_url($u['foto_perfil_url']),
            'bio'        => $u['bio'],
            'area'       => $u['area_criativa'],
            'area_label' => areas()[$u['area_criativa']] ?? 'Criativo',
            'estado'     => $u['estado_criativo'],
            'seguidores' => (int) $u['seguidores'],
            'posts'      => (int) $u['posts'],
            'pro'        => $u['plano'] === 'pro',
        ], $st->fetchAll());

        // Mosaico: só posts com mídia visual
        $st = $pdo->prepare(
            sql_post_base() . " WHERE m.midia_url IS NOT NULL
                                  AND COALESCE(tm.tipo_midia,'imagem') IN ('imagem','video')
                                ORDER BY m.criado_em DESC LIMIT 24"
        );
        $st->execute([':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu]);
        $linhas = $st->fetchAll();
        anexar_tags($linhas);

        json_ok([
            'tags'     => $tags,
            'artistas' => $artistas,
            'mosaico'  => array_map('formatar_post', $linhas),
        ]);

    } catch (Throwable $e) {
        error_log('[spark][explorar] ' . $e->getMessage());
        json_err('Não foi possível carregar o explorar.', 500);
    }
}

// =========================================================
// BUSCAR
// =========================================================
if ($acao === 'buscar') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) json_ok(['artistas' => [], 'posts' => [], 'tags' => []]);

    $like = '%' . $q . '%';

    try {
        $st = $pdo->prepare(
            'SELECT id_usuario, nome_usuario, nome_exibicao, foto_perfil_url,
                    area_criativa, estado_criativo, plano
               FROM usuario
              WHERE ativo = 1 AND id_usuario <> ?
                AND (nome_usuario LIKE ? OR nome_exibicao LIKE ? OR bio LIKE ?)
              LIMIT 8'
        );
        $st->execute([$eu, $like, $like, $like]);

        $artistas = array_map(static fn($u) => [
            'id'         => (int) $u['id_usuario'],
            'usuario'    => $u['nome_usuario'],
            'nome'       => $u['nome_exibicao'] ?: $u['nome_usuario'],
            'foto'       => avatar_url($u['foto_perfil_url']),
            'area_label' => areas()[$u['area_criativa']] ?? 'Criativo',
            'estado'     => $u['estado_criativo'],
            'pro'        => $u['plano'] === 'pro',
        ], $st->fetchAll());

        $st = $pdo->prepare(
            'SELECT t.nome, COUNT(mt.idmidias) AS total
               FROM tags t
          LEFT JOIN midia_tags mt ON mt.idtag = t.id
              WHERE t.nome LIKE ?
              GROUP BY t.id, t.nome
              ORDER BY total DESC
              LIMIT 6'
        );
        $st->execute(['%' . mb_strtolower($q, 'UTF-8') . '%']);
        $tags = array_map(static fn($t) => [
            'nome' => $t['nome'], 'total' => (int) $t['total'],
        ], $st->fetchAll());

        $st = $pdo->prepare(
            sql_post_base() . ' WHERE (m.titulo LIKE :q1 OR m.descricao LIKE :q2)
                                ORDER BY m.criado_em DESC LIMIT 8'
        );
        $st->execute([
            ':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu,
            ':q1' => $like, ':q2' => $like,
        ]);
        $linhas = $st->fetchAll();
        anexar_tags($linhas);

        json_ok([
            'artistas' => $artistas,
            'tags'     => $tags,
            'posts'    => array_map('formatar_post', $linhas),
        ]);

    } catch (Throwable $e) {
        error_log('[spark][buscar] ' . $e->getMessage());
        json_err('A busca falhou.', 500);
    }
}

json_err('Ação desconhecida.', 404);
