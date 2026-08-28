<?php
/**
 * SPARK — notificações reais, geradas pelas ações da rede.
 *
 * GET  (padrão)    — lista as 50 mais recentes
 * GET  ?so_contar=1 — só o número de não lidas (usado no badge)
 * POST acao=ler     — marca todas como lidas
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu  = exigir_login();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    exigir_csrf();

    if (($_POST['acao'] ?? '') === 'ler') {
        try {
            $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE idusuario = ? AND lida = 0')
                ->execute([$eu]);
            json_ok();
        } catch (Throwable $e) {
            error_log('[spark][notif:ler] ' . $e->getMessage());
            json_err('Não foi possível marcar como lidas.', 500);
        }
    }

    json_err('Ação desconhecida.', 404);
}

// ---------------------------------------------------------
// Contador para o badge do menu
// ---------------------------------------------------------
try {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE idusuario = ? AND lida = 0');
    $st->execute([$eu]);
    $naoLidas = (int) $st->fetchColumn();

    // Mensagens não lidas entram no mesmo badge do Direct
    $st = $pdo->prepare(
        'SELECT COUNT(*)
           FROM mensagens_status ms
           JOIN mensagens msg ON msg.id = ms.idmensagem
          WHERE ms.idusuario = ? AND ms.lida = 0 AND msg.idusuario_remetente <> ?'
    );
    $st->execute([$eu, $eu]);
    $msgsNaoLidas = (int) $st->fetchColumn();

    if (!empty($_GET['so_contar'])) {
        json_ok(['nao_lidas' => $naoLidas, 'mensagens' => $msgsNaoLidas]);
    }

    $st = $pdo->prepare(
        'SELECT n.id, n.tipo, n.extra, n.lida, n.criado_em, n.idmidias,
                u.id_usuario, u.nome_usuario, u.nome_exibicao, u.foto_perfil_url,
                u.estado_criativo, u.plano,
                m.titulo AS post_titulo, m.midia_url AS post_url
           FROM notificacoes n
           JOIN usuario u ON u.id_usuario = n.idator
      LEFT JOIN midias  m ON m.id_midia   = n.idmidias
          WHERE n.idusuario = ?
          ORDER BY n.criado_em DESC
          LIMIT 50'
    );
    $st->execute([$eu]);

    $frases = [
        'curtida'        => 'curtiu seu post',
        'comentario'     => 'comentou no seu post',
        'seguidor'       => 'começou a seguir você',
        'mencao'         => 'mencionou você',
        'resposta_forum' => 'respondeu seu tópico no fórum',
        'conexao'        => 'se conectou com você pelo Botão Conexão',
    ];

    $lista = array_map(static function (array $n) use ($frases) {
        return [
            'id'     => (int) $n['id'],
            'tipo'   => $n['tipo'],
            'frase'  => $frases[$n['tipo']] ?? 'interagiu com você',
            'extra'  => $n['extra'],
            'lida'   => (bool) $n['lida'],
            'quando' => tempo_relativo($n['criado_em']),
            'post'   => $n['idmidias'] ? [
                'id'     => (int) $n['idmidias'],
                'titulo' => $n['post_titulo'],
                'url'    => midia_url($n['post_url']),
            ] : null,
            'ator' => [
                'id'      => (int) $n['id_usuario'],
                'usuario' => $n['nome_usuario'],
                'nome'    => $n['nome_exibicao'] ?: $n['nome_usuario'],
                'foto'    => avatar_url($n['foto_perfil_url']),
                'estado'  => $n['estado_criativo'],
                'pro'     => $n['plano'] === 'pro',
            ],
        ];
    }, $st->fetchAll());

    json_ok([
        'notificacoes' => $lista,
        'nao_lidas'    => $naoLidas,
        'mensagens'    => $msgsNaoLidas,
    ]);

} catch (Throwable $e) {
    error_log('[spark][notif] ' . $e->getMessage());
    json_err('Não foi possível carregar as notificações.', 500);
}
