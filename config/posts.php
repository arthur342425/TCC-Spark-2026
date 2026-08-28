<?php
/**
 * SPARK — consultas de post reaproveitadas pelo feed, perfil e explorar.
 */

declare(strict_types=1);

/**
 * SELECT único que já traz autor, contadores e o que EU fiz com o post.
 * Evita o problema de N+1 consultas ao montar o feed.
 */
function sql_post_base(): string
{
    return "
        SELECT
            m.id_midia, m.titulo, m.descricao, m.midia_url, m.mime_type,
            m.tamanho_bytes, m.duracao_segundos, m.criado_em,
            COALESCE(tm.tipo_midia, 'imagem') AS tipo,

            u.id_usuario   AS autor_id,
            u.nome_usuario AS autor_user,
            u.nome_exibicao AS autor_nome,
            u.foto_perfil_url AS autor_foto,
            u.area_criativa AS autor_area,
            u.estado_criativo AS autor_estado,
            u.plano AS autor_plano,

            (SELECT COUNT(*) FROM curtidas c    WHERE c.idmidias = m.id_midia) AS n_curtidas,
            (SELECT COUNT(*) FROM comentarios o WHERE o.idmidias = m.id_midia) AS n_comentarios,

            EXISTS(SELECT 1 FROM curtidas c2
                    WHERE c2.idmidias = m.id_midia AND c2.idusuario = :eu_curti) AS eu_curti,
            EXISTS(SELECT 1 FROM seguidores s
                    WHERE s.idseguido = m.idusuario AND s.idseguidor = :eu_sigo) AS eu_sigo,
            EXISTS(SELECT 1 FROM midias_em_pasta mp
                     JOIN Pastas p ON p.id = mp.idPastas AND p.idusuario = :eu_salvei
                    WHERE mp.idmidias = m.id_midia) AS eu_salvei

        FROM midias m
        JOIN usuario u      ON u.id_usuario = m.idusuario AND u.ativo = 1
        LEFT JOIN Tipos_midia tm ON tm.id = m.idTipos_midia
    ";
}

/** Anexa as tags de vários posts de uma vez só. */
function anexar_tags(array &$posts): void
{
    if (!$posts) return;

    $ids  = array_column($posts, 'id_midia');
    $lac  = implode(',', array_fill(0, count($ids), '?'));

    $st = db()->prepare(
        "SELECT mt.idmidias, t.nome
           FROM midia_tags mt
           JOIN tags t ON t.id = mt.idtag
          WHERE mt.idmidias IN ($lac)"
    );
    $st->execute($ids);

    $porPost = [];
    foreach ($st->fetchAll() as $linha) {
        $porPost[(int) $linha['idmidias']][] = $linha['nome'];
    }

    foreach ($posts as &$p) {
        $p['tags'] = $porPost[(int) $p['id_midia']] ?? [];
    }
    unset($p);
}

/** Converte a linha crua do banco no formato que o front consome. */
function formatar_post(array $r): array
{
    return [
        'id'          => (int) $r['id_midia'],
        'titulo'      => $r['titulo'],
        'descricao'   => $r['descricao'],
        'url'         => midia_url($r['midia_url']),
        'tipo'        => $r['tipo'],
        'mime'        => $r['mime_type'],
        'criado_em'   => $r['criado_em'],
        'quando'      => tempo_relativo($r['criado_em']),
        'curtidas'    => (int) $r['n_curtidas'],
        'comentarios' => (int) $r['n_comentarios'],
        'eu_curti'    => (bool) $r['eu_curti'],
        'eu_sigo'     => (bool) $r['eu_sigo'],
        'eu_salvei'   => (bool) $r['eu_salvei'],
        'tags'        => $r['tags'] ?? [],
        'autor' => [
            'id'      => (int) $r['autor_id'],
            'usuario' => $r['autor_user'],
            'nome'    => $r['autor_nome'] ?: $r['autor_user'],
            'foto'    => avatar_url($r['autor_foto']),
            'area'    => $r['autor_area'],
            'estado'  => $r['autor_estado'],
            'pro'     => $r['autor_plano'] === 'pro',
        ],
    ];
}

/** Rótulos amigáveis das áreas criativas. */
function areas(): array
{
    return [
        'musica'      => 'Produção musical',
        'visual'      => 'Artes visuais',
        'design'      => 'Design',
        'escrita'     => 'Escrita',
        'audiovisual' => 'Audiovisual',
        'outro'       => 'Multidisciplinar',
    ];
}

/** Rótulos e cores dos estados criativos — base do Botão Conexão. */
function estados(): array
{
    return [
        'fluxo' => [
            'rotulo' => 'Em fluxo',
            'desc'   => 'Estou criando agora, a ideia tá fluindo',
            'icone'  => 'fa-bolt',
        ],
        'bloqueado' => [
            'rotulo' => 'Bloqueado',
            'desc'   => 'Travei. Preciso de um empurrão',
            'icone'  => 'fa-ban',
        ],
        'buscando_referencia' => [
            'rotulo' => 'Buscando referência',
            'desc'   => 'Procurando ideia, sample, paleta, direção',
            'icone'  => 'fa-compass',
        ],
        'aberto_colab' => [
            'rotulo' => 'Aberto a colab',
            'desc'   => 'Quero fazer algo junto com alguém',
            'icone'  => 'fa-handshake',
        ],
        'observando' => [
            'rotulo' => 'Só observando',
            'desc'   => 'Passeando pelo feed sem pressa',
            'icone'  => 'fa-eye',
        ],
    ];
}
