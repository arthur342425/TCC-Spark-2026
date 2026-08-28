<?php
/**
 * SPARK — PILAR 3: FÓRUNS ARTÍSTICOS
 *
 * Espaços temáticos onde o processo criativo é o assunto,
 * não o resultado final.
 *
 * GET  acao=categorias
 * GET  acao=topicos&cat=N
 * GET  acao=topico&id=N
 * POST acao=criar_topico | responder
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu   = exigir_login();
$pdo  = db();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'categorias';

// =========================================================
// CATEGORIAS
// =========================================================
if ($acao === 'categorias') {
    try {
        $st = $pdo->query(
            'SELECT c.id, c.nome, c.descricao, c.icone,
                    (SELECT COUNT(*) FROM forum_topicos t WHERE t.idcategoria = c.id) AS topicos,
                    (SELECT COUNT(*) FROM forum_respostas r
                       JOIN forum_topicos t2 ON t2.id = r.idtopico
                      WHERE t2.idcategoria = c.id) AS respostas
               FROM forum_categorias c
              ORDER BY c.id'
        );

        json_ok(['categorias' => array_map(static fn($c) => [
            'id'        => (int) $c['id'],
            'nome'      => $c['nome'],
            'descricao' => $c['descricao'] ?: '',
            'icone'     => $c['icone'] ?: 'fa-comments',
            'topicos'   => (int) $c['topicos'],
            'respostas' => (int) $c['respostas'],
        ], $st->fetchAll())]);

    } catch (Throwable $e) {
        error_log('[spark][forum:categorias] ' . $e->getMessage());
        json_err('Não foi possível carregar os fóruns.', 500);
    }
}

// =========================================================
// TÓPICOS DE UMA CATEGORIA
// =========================================================
if ($acao === 'topicos') {
    $cat = (int) ($_GET['cat'] ?? 0);
    if ($cat <= 0) json_err('Categoria inválida.');

    try {
        $st = $pdo->prepare('SELECT id, nome, descricao, icone FROM forum_categorias WHERE id = ?');
        $st->execute([$cat]);
        $categoria = $st->fetch();
        if (!$categoria) json_err('Categoria não encontrada.', 404);

        $st = $pdo->prepare(
            'SELECT t.id, t.titulo, t.texto, t.visitas, t.criado_em,
                    u.id_usuario, u.nome_usuario, u.nome_exibicao, u.foto_perfil_url, u.plano,
                    (SELECT COUNT(*) FROM forum_respostas r WHERE r.idtopico = t.id) AS respostas,
                    (SELECT MAX(r2.criado_em) FROM forum_respostas r2 WHERE r2.idtopico = t.id) AS ultima
               FROM forum_topicos t
               JOIN usuario u ON u.id_usuario = t.idusuario
              WHERE t.idcategoria = ?
              ORDER BY COALESCE((SELECT MAX(r3.criado_em) FROM forum_respostas r3 WHERE r3.idtopico = t.id),
                                t.criado_em) DESC
              LIMIT 50'
        );
        $st->execute([$cat]);

        json_ok([
            'categoria' => [
                'id'        => (int) $categoria['id'],
                'nome'      => $categoria['nome'],
                'descricao' => $categoria['descricao'] ?: '',
                'icone'     => $categoria['icone'] ?: 'fa-comments',
            ],
            'topicos' => array_map(static fn($t) => [
                'id'        => (int) $t['id'],
                'titulo'    => $t['titulo'],
                'trecho'    => mb_substr((string) $t['texto'], 0, 140),
                'respostas' => (int) $t['respostas'],
                'visitas'   => (int) $t['visitas'],
                'quando'    => tempo_relativo($t['ultima'] ?: $t['criado_em']),
                'autor'     => [
                    'id'      => (int) $t['id_usuario'],
                    'usuario' => $t['nome_usuario'],
                    'nome'    => $t['nome_exibicao'] ?: $t['nome_usuario'],
                    'foto'    => avatar_url($t['foto_perfil_url']),
                    'pro'     => $t['plano'] === 'pro',
                ],
            ], $st->fetchAll()),
        ]);

    } catch (Throwable $e) {
        error_log('[spark][forum:topicos] ' . $e->getMessage());
        json_err('Não foi possível carregar os tópicos.', 500);
    }
}

// =========================================================
// UM TÓPICO COM AS RESPOSTAS
// =========================================================
if ($acao === 'topico') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) json_err('Tópico inválido.');

    try {
        $st = $pdo->prepare(
            'SELECT t.id, t.titulo, t.texto, t.visitas, t.criado_em, t.idcategoria,
                    c.nome AS categoria,
                    u.id_usuario, u.nome_usuario, u.nome_exibicao, u.foto_perfil_url, u.plano
               FROM forum_topicos t
               JOIN usuario u ON u.id_usuario = t.idusuario
               JOIN forum_categorias c ON c.id = t.idcategoria
              WHERE t.id = ?'
        );
        $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) json_err('Tópico não encontrado.', 404);

        $pdo->prepare('UPDATE forum_topicos SET visitas = visitas + 1 WHERE id = ?')->execute([$id]);

        $st = $pdo->prepare(
            'SELECT r.id, r.texto, r.criado_em,
                    u.id_usuario, u.nome_usuario, u.nome_exibicao, u.foto_perfil_url,
                    u.estado_criativo, u.plano
               FROM forum_respostas r
               JOIN usuario u ON u.id_usuario = r.idusuario
              WHERE r.idtopico = ?
              ORDER BY r.criado_em ASC
              LIMIT 200'
        );
        $st->execute([$id]);

        json_ok([
            'topico' => [
                'id'        => (int) $t['id'],
                'titulo'    => $t['titulo'],
                'texto'     => $t['texto'],
                'categoria' => $t['categoria'],
                'idcategoria' => (int) $t['idcategoria'],
                'visitas'   => (int) $t['visitas'] + 1,
                'quando'    => tempo_relativo($t['criado_em']),
                'autor'     => [
                    'id'      => (int) $t['id_usuario'],
                    'usuario' => $t['nome_usuario'],
                    'nome'    => $t['nome_exibicao'] ?: $t['nome_usuario'],
                    'foto'    => avatar_url($t['foto_perfil_url']),
                    'pro'     => $t['plano'] === 'pro',
                ],
            ],
            'respostas' => array_map(static fn($r) => [
                'id'     => (int) $r['id'],
                'texto'  => $r['texto'],
                'quando' => tempo_relativo($r['criado_em']),
                'autor'  => [
                    'id'      => (int) $r['id_usuario'],
                    'usuario' => $r['nome_usuario'],
                    'nome'    => $r['nome_exibicao'] ?: $r['nome_usuario'],
                    'foto'    => avatar_url($r['foto_perfil_url']),
                    'estado'  => $r['estado_criativo'],
                    'pro'     => $r['plano'] === 'pro',
                ],
            ], $st->fetchAll()),
        ]);

    } catch (Throwable $e) {
        error_log('[spark][forum:topico] ' . $e->getMessage());
        json_err('Não foi possível abrir o tópico.', 500);
    }
}

exigir_post();
exigir_csrf();

// =========================================================
// CRIAR TÓPICO
// =========================================================
if ($acao === 'criar_topico') {
    $cat    = (int) ($_POST['cat'] ?? 0);
    $titulo = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 255);
    $texto  = mb_substr(trim((string) ($_POST['texto'] ?? '')), 0, 5000);

    if ($cat <= 0)          json_err('Escolha uma categoria.');
    if ($titulo === '')     json_err('O tópico precisa de um título.');
    if (mb_strlen($titulo) < 5) json_err('Dê um título um pouco mais descritivo.');

    try {
        $st = $pdo->prepare('SELECT 1 FROM forum_categorias WHERE id = ?');
        $st->execute([$cat]);
        if (!$st->fetch()) json_err('Categoria inválida.');

        $pdo->prepare('INSERT INTO forum_topicos (idcategoria, idusuario, titulo, texto) VALUES (?, ?, ?, ?)')
            ->execute([$cat, $eu, $titulo, $texto]);

        json_out(['ok' => true, 'id' => (int) $pdo->lastInsertId()], 201);

    } catch (Throwable $e) {
        error_log('[spark][forum:criar] ' . $e->getMessage());
        json_err('Não foi possível criar o tópico.', 500);
    }
}

// =========================================================
// RESPONDER
// =========================================================
if ($acao === 'responder') {
    $idtopico = (int) ($_POST['id'] ?? 0);
    $texto    = mb_substr(trim((string) ($_POST['texto'] ?? '')), 0, 5000);

    if ($idtopico <= 0) json_err('Tópico inválido.');
    if ($texto === '')  json_err('Escreva sua resposta.');

    try {
        $st = $pdo->prepare('SELECT idusuario, titulo FROM forum_topicos WHERE id = ?');
        $st->execute([$idtopico]);
        $topico = $st->fetch();
        if (!$topico) json_err('Tópico não encontrado.', 404);

        $pdo->prepare('INSERT INTO forum_respostas (idtopico, idusuario, texto) VALUES (?, ?, ?)')
            ->execute([$idtopico, $eu, $texto]);

        notificar((int) $topico['idusuario'], $eu, 'resposta_forum', null,
                  mb_substr((string) $topico['titulo'], 0, 80));

        $mim = usuario_atual();

        json_out([
            'ok' => true,
            'resposta' => [
                'id'     => (int) $pdo->lastInsertId(),
                'texto'  => $texto,
                'quando' => 'agora',
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
        error_log('[spark][forum:responder] ' . $e->getMessage());
        json_err('Não foi possível responder.', 500);
    }
}

json_err('Ação desconhecida.', 404);
