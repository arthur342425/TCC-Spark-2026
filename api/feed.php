<?php
/**
 * SPARK — PILAR 1: FEED INTELIGENTE
 *
 * Não é ordem cronológica. A nota de cada post combina:
 *   1. afinidade   — quanto as tags do post batem com o gosto aprendido do usuário
 *   2. área        — mesma área criativa (produtor vê sample, pintor vê paleta)
 *   3. vínculo     — autor que eu sigo pesa muito
 *   4. engajamento — curtidas e comentários, com peso menor que os anteriores
 *   5. frescor     — decai com as horas para o feed não estagnar
 *   6. repetição   — quem eu acabei de ver perde pontos
 *
 * modo=paravoce  → algoritmo completo
 * modo=seguindo  → só quem eu sigo, cronológico
 * modo=recentes  → tudo, cronológico
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu = exigir_login();

$modo    = $_GET['modo'] ?? 'paravoce';
$pagina  = max(0, (int) ($_GET['pagina'] ?? 0));
$tag     = trim((string) ($_GET['tag'] ?? ''));
$porPag  = 12;
$offset  = $pagina * $porPag;

$sql    = sql_post_base();
$params = [':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu];
$onde   = [];

// filtro por tag (usado ao clicar numa hashtag ou no Explorar)
if ($tag !== '') {
    $onde[] = 'EXISTS(SELECT 1 FROM midia_tags mtf
                        JOIN tags tf ON tf.id = mtf.idtag
                       WHERE mtf.idmidias = m.id_midia AND tf.nome = :tag)';
    $params[':tag'] = mb_strtolower($tag, 'UTF-8');
}

if ($modo === 'seguindo') {
    $onde[] = 'EXISTS(SELECT 1 FROM seguidores sf
                       WHERE sf.idseguido = m.idusuario AND sf.idseguidor = :eu_seg)';
    $params[':eu_seg'] = $eu;
}

if ($onde) {
    $sql .= ' WHERE ' . implode(' AND ', $onde);
}

if ($modo === 'paravoce') {
    // --- a nota ---
    $sql .= "
        ORDER BY (
              COALESCE((
                  SELECT SUM(a.pontuacao)
                    FROM midia_tags mt2
                    JOIN afinidade_usuario_tag a
                      ON a.idtag = mt2.idtag AND a.idusuario = :eu_afin
                   WHERE mt2.idmidias = m.id_midia
              ), 0) * 1.6

            + CASE WHEN u.area_criativa = (SELECT area_criativa FROM usuario WHERE id_usuario = :eu_area)
                   THEN 18 ELSE 0 END

            + CASE WHEN EXISTS(SELECT 1 FROM seguidores s3
                                WHERE s3.idseguido = m.idusuario AND s3.idseguidor = :eu_vinc)
                   THEN 30 ELSE 0 END

            + (SELECT COUNT(*) FROM curtidas c3    WHERE c3.idmidias = m.id_midia) * 2.5
            + (SELECT COUNT(*) FROM comentarios o3 WHERE o3.idmidias = m.id_midia) * 4

            - TIMESTAMPDIFF(HOUR, m.criado_em, NOW()) * 0.9

            - CASE WHEN EXISTS(SELECT 1 FROM interacoes i3
                                WHERE i3.idmidias = m.id_midia
                                  AND i3.idusuario = :eu_visto
                                  AND i3.tipo_interacao IN ('visualizacao','pulou'))
                   THEN 22 ELSE 0 END

            + CASE WHEN m.idusuario = :eu_meu THEN -12 ELSE 0 END
        ) DESC, m.criado_em DESC
    ";
    $params[':eu_afin']  = $eu;
    $params[':eu_area']  = $eu;
    $params[':eu_vinc']  = $eu;
    $params[':eu_visto'] = $eu;
    $params[':eu_meu']   = $eu;
} else {
    $sql .= ' ORDER BY m.criado_em DESC';
}

$sql .= ' LIMIT :limite OFFSET :salto';

try {
    $st = db()->prepare($sql);
    foreach ($params as $chave => $valor) {
        $st->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->bindValue(':limite', $porPag, PDO::PARAM_INT);
    $st->bindValue(':salto',  $offset, PDO::PARAM_INT);
    $st->execute();

    $linhas = $st->fetchAll();
    anexar_tags($linhas);

    $posts = array_map('formatar_post', $linhas);

    // Ver conta como sinal fraco: ensina o algoritmo sem poluir o histórico.
    foreach ($linhas as $l) {
        if ((int) $l['autor_id'] !== $eu) {
            registrar_interacao($eu, (int) $l['id_midia'], 'visualizacao');
        }
    }

    json_ok([
        'posts' => $posts,
        'fim'   => count($posts) < $porPag,
        'modo'  => $modo,
    ]);

} catch (Throwable $e) {
    error_log('[spark][feed] ' . $e->getMessage());
    json_err('Não foi possível carregar o feed.', 500);
}
