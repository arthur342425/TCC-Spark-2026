<?php
/**
 * SPARK — PILAR 2: BOTÃO CONEXÃO
 *
 * Um clique liga o artista a outro que está no mesmo momento criativo.
 * A escolha não é aleatória: cada candidato recebe uma nota por
 * complementaridade de estado, área, gosto em comum e presença recente.
 *
 * A regra central é a complementaridade, não a igualdade:
 *   quem está BLOQUEADO precisa de quem está EM FLUXO;
 *   quem busca REFERÊNCIA precisa de quem está criando;
 *   quem quer COLAB precisa de outro querendo COLAB.
 *
 * Ao confirmar, abre a DM automaticamente com uma quebra-gelo.
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu  = exigir_login();
$pdo = db();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'procurar';

/** Pares que se completam: [meu estado => estados ideais no outro] */
function complementos(): array
{
    return [
        'bloqueado'           => ['fluxo' => 40, 'aberto_colab' => 32, 'buscando_referencia' => 14],
        'buscando_referencia' => ['fluxo' => 36, 'aberto_colab' => 28, 'buscando_referencia' => 16],
        'aberto_colab'        => ['aberto_colab' => 42, 'fluxo' => 30, 'bloqueado' => 18],
        'fluxo'               => ['aberto_colab' => 34, 'bloqueado' => 26, 'fluxo' => 20],
        'observando'          => ['aberto_colab' => 26, 'fluxo' => 22, 'buscando_referencia' => 16],
    ];
}

function motivo_do_encontro(array $eu, array $outro): string
{
    $areas = areas();
    $est   = estados();

    // contas anteriores à migração podem chegar aqui sem os campos novos
    $eu['estado_criativo']    = $eu['estado_criativo']    ?: 'observando';
    $eu['area_criativa']      = $eu['area_criativa']      ?: 'outro';
    $outro['estado_criativo'] = $outro['estado_criativo'] ?: 'observando';
    $outro['area_criativa']   = $outro['area_criativa']   ?: 'outro';

    if ($eu['estado_criativo'] === 'bloqueado' && $outro['estado_criativo'] === 'fluxo') {
        return 'Você travou e ' . ($outro['nome_exibicao'] ?: $outro['nome_usuario'])
             . ' está em pleno fluxo criativo agora. Boa hora para pegar carona na energia.';
    }
    if ($eu['estado_criativo'] === 'aberto_colab' && $outro['estado_criativo'] === 'aberto_colab') {
        return 'Vocês dois marcaram que estão abertos a colaborar. Combinação rara — aproveitem.';
    }
    if ($eu['area_criativa'] === $outro['area_criativa']) {
        return 'Mesma área (' . ($areas[$outro['area_criativa']] ?? 'criativa')
             . ') e um momento que combina com o seu: ' . mb_strtolower($est[$outro['estado_criativo']]['rotulo'], 'UTF-8') . '.';
    }
    if (!empty($outro['tags_comuns'])) {
        return 'Vocês curtem as mesmas coisas: #' . implode(', #', array_slice(explode(',', $outro['tags_comuns']), 0, 3)) . '.';
    }

    return 'Áreas diferentes que se cruzam bem — ' . ($areas[$outro['area_criativa']] ?? 'outra área')
         . ' costuma destravar quem vem de ' . ($areas[$eu['area_criativa']] ?? 'onde você vem') . '.';
}

// =========================================================
// PROCURAR — encontra o melhor par para o momento atual
// =========================================================
if ($acao === 'procurar') {

    $mim = usuario_atual();
    if (!$mim) json_err('Sessão inválida.', 401);

    $meuEstado = $mim['estado_criativo'] ?: 'observando';
    $prefs     = complementos()[$meuEstado] ?? complementos()['observando'];

    // CASE que traduz a tabela de complementaridade para SQL.
    // Sem cláusulas WHEN o CASE seria inválido, então caímos num literal.
    if ($prefs) {
        $caseEstado = 'CASE u.estado_criativo';
        foreach ($prefs as $estado => $peso) {
            $caseEstado .= ' WHEN ' . $pdo->quote($estado) . ' THEN ' . (int) $peso;
        }
        $caseEstado .= ' ELSE 0 END';
    } else {
        $caseEstado = '0';
    }

    $sql = "
        SELECT u.id_usuario, u.nome_usuario, u.nome_exibicao, u.foto_perfil_url,
               u.bio, u.area_criativa, u.estado_criativo, u.ferramenta, u.cidade, u.plano,

               (SELECT GROUP_CONCAT(t.nome)
                  FROM afinidade_usuario_tag a1
                  JOIN afinidade_usuario_tag a2 ON a2.idtag = a1.idtag AND a2.idusuario = :eu_tag
                  JOIN tags t ON t.id = a1.idtag
                 WHERE a1.idusuario = u.id_usuario
                 LIMIT 1) AS tags_comuns,

               (
                   $caseEstado

                 + CASE WHEN u.area_criativa = :minha_area THEN 22 ELSE 0 END

                 + COALESCE((
                       SELECT COUNT(*) * 6
                         FROM afinidade_usuario_tag a1
                         JOIN afinidade_usuario_tag a2
                           ON a2.idtag = a1.idtag AND a2.idusuario = :eu_afin
                        WHERE a1.idusuario = u.id_usuario
                   ), 0)

                 + CASE WHEN u.estado_em >= DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN 20
                        WHEN u.estado_em >= DATE_SUB(NOW(), INTERVAL 1 DAY)  THEN 10
                        ELSE 0 END

                 + CASE WHEN EXISTS(SELECT 1 FROM midias mm WHERE mm.idusuario = u.id_usuario)
                        THEN 8 ELSE 0 END

                 - CASE WHEN EXISTS(
                       SELECT 1 FROM conexoes cx
                        WHERE cx.idusuario_a = :eu_hist
                          AND cx.idusuario_b = u.id_usuario
                          AND cx.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                   ) THEN 45 ELSE 0 END

                 + RAND() * 12
               ) AS nota

          FROM usuario u
         WHERE u.id_usuario <> :eu
           AND u.ativo = 1
         ORDER BY nota DESC
         LIMIT 1
    ";

    try {
        $st = $pdo->prepare($sql);
        $st->execute([
            ':eu_tag'     => $eu,
            ':eu_afin'    => $eu,
            ':eu_hist'    => $eu,
            ':eu'         => $eu,
            ':minha_area' => $mim['area_criativa'],
        ]);
        $outro = $st->fetch();

        if (!$outro) {
            json_ok([
                'encontrado' => false,
                'mensagem'   => 'Ainda não há outro artista disponível na plataforma. '
                              . 'Convide alguém — a conexão fica muito melhor com gente na rede.',
            ]);
        }

        $est = estados();
        $outro['estado_criativo'] = $outro['estado_criativo'] ?: 'observando';
        $outro['area_criativa']   = $outro['area_criativa']   ?: 'outro';

        json_ok([
            'encontrado' => true,
            'artista' => [
                'id'         => (int) $outro['id_usuario'],
                'usuario'    => $outro['nome_usuario'],
                'nome'       => $outro['nome_exibicao'] ?: $outro['nome_usuario'],
                'foto'       => avatar_url($outro['foto_perfil_url']),
                'bio'        => $outro['bio'],
                'area'       => $outro['area_criativa'],
                'area_label' => areas()[$outro['area_criativa']] ?? 'Criativo',
                'estado'     => $outro['estado_criativo'],
                'estado_label' => $est[$outro['estado_criativo']]['rotulo'] ?? '',
                'ferramenta' => $outro['ferramenta'],
                'cidade'     => $outro['cidade'],
                'pro'        => $outro['plano'] === 'pro',
            ],
            'motivo' => motivo_do_encontro($mim, $outro),
        ]);

    } catch (Throwable $e) {
        error_log('[spark][conexao:procurar] ' . $e->getMessage());
        json_err('Não foi possível procurar uma conexão agora.', 500);
    }
}

// =========================================================
// CONFIRMAR — registra o encontro e abre a DM
// =========================================================
if ($acao === 'confirmar') {
    exigir_post();
    exigir_csrf();

    $destino = (int) ($_POST['idusuario'] ?? 0);
    $motivo  = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 120);

    if ($destino <= 0 || $destino === $eu) {
        json_err('Artista inválido.');
    }

    try {
        $st = $pdo->prepare('SELECT id_usuario, nome_usuario, nome_exibicao FROM usuario WHERE id_usuario = ? AND ativo = 1');
        $st->execute([$destino]);
        $outro = $st->fetch();
        if (!$outro) json_err('Esse artista não está mais disponível.', 404);

        $pdo->beginTransaction();

        // Reaproveita a conversa individual se ela já existir
        $st = $pdo->prepare(
            "SELECT cp1.idconversa
               FROM conversa_participantes cp1
               JOIN conversa_participantes cp2
                 ON cp2.idconversa = cp1.idconversa AND cp2.idusuario = ?
               JOIN conversas c ON c.id = cp1.idconversa AND c.tipo = 'individual'
              WHERE cp1.idusuario = ?
              LIMIT 1"
        );
        $st->execute([$destino, $eu]);
        $existente = $st->fetch();

        if ($existente) {
            $idconversa = (int) $existente['idconversa'];
        } else {
            $pdo->prepare("INSERT INTO conversas (tipo) VALUES ('individual')")->execute();
            $idconversa = (int) $pdo->lastInsertId();

            $ins = $pdo->prepare('INSERT INTO conversa_participantes (idconversa, idusuario) VALUES (?, ?)');
            $ins->execute([$idconversa, $eu]);
            $ins->execute([$idconversa, $destino]);
        }

        // Quebra-gelo: a conversa nunca começa numa tela em branco
        $mim   = usuario_atual();
        $texto = 'Oi! O Spark aproximou a gente aqui. '
               . 'Eu tô ' . mb_strtolower(estados()[$mim['estado_criativo']]['rotulo'], 'UTF-8')
               . ' e vi que seu momento combina. Bora trocar uma ideia?';

        $pdo->prepare(
            'INSERT INTO mensagens (idconversa, idusuario_remetente, texto) VALUES (?, ?, ?)'
        )->execute([$idconversa, $eu, $texto]);
        $idmsg = (int) $pdo->lastInsertId();

        $stStatus = $pdo->prepare(
            'INSERT INTO mensagens_status (idmensagem, idusuario, lida, lida_em) VALUES (?, ?, ?, ?)'
        );
        $stStatus->execute([$idmsg, $eu, 1, date('Y-m-d H:i:s')]);
        $stStatus->execute([$idmsg, $destino, 0, null]);

        $pdo->prepare(
            'INSERT INTO conexoes (idusuario_a, idusuario_b, motivo, idconversa) VALUES (?, ?, ?, ?)'
        )->execute([$eu, $destino, $motivo, $idconversa]);

        $pdo->commit();

        notificar($destino, $eu, 'conexao', null, 'quer trocar uma ideia com você');

        json_ok([
            'idconversa' => $idconversa,
            'nome'       => $outro['nome_exibicao'] ?: $outro['nome_usuario'],
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[spark][conexao:confirmar] ' . $e->getMessage());
        json_err('Não foi possível abrir a conversa.', 500);
    }
}

json_err('Ação desconhecida.', 404);
