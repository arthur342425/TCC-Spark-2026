<?php
/**
 * SPARK — perfil de artista.
 *
 * GET  acao=ver&id=  ou &usuario=   — dados, números e conteúdo
 * POST acao=salvar                  — edita o próprio perfil
 * POST acao=foto                    — troca a foto de perfil
 * POST acao=senha                   — troca a senha (exige a atual)
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu   = exigir_login();
$pdo  = db();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'ver';

// =========================================================
// VER
// =========================================================
if ($acao === 'ver') {
    $id      = (int) ($_GET['id'] ?? 0);
    $usuario = trim((string) ($_GET['usuario'] ?? ''));
    $aba     = $_GET['aba'] ?? 'posts';

    if (!$id && $usuario === '') $id = $eu;

    try {
        if ($id) {
            $st = $pdo->prepare('SELECT * FROM usuario WHERE id_usuario = ? AND ativo = 1');
            $st->execute([$id]);
        } else {
            $st = $pdo->prepare('SELECT * FROM usuario WHERE nome_usuario = ? AND ativo = 1');
            $st->execute([$usuario]);
        }

        $u = $st->fetch();
        if (!$u) json_err('Artista não encontrado.', 404);

        $idPerfil = (int) $u['id_usuario'];
        $souEu    = ($idPerfil === $eu);

        $numeros = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM midias     WHERE idusuario = :id)  AS posts,
                (SELECT COUNT(*) FROM seguidores WHERE idseguido = :id2) AS seguidores,
                (SELECT COUNT(*) FROM seguidores WHERE idseguidor = :id3) AS seguindo,
                (SELECT COUNT(*) FROM curtidas c JOIN midias m ON m.id_midia = c.idmidias
                  WHERE m.idusuario = :id4) AS curtidas_recebidas'
        );
        $numeros->execute([':id' => $idPerfil, ':id2' => $idPerfil, ':id3' => $idPerfil, ':id4' => $idPerfil]);
        $n = $numeros->fetch();

        $st = $pdo->prepare('SELECT 1 FROM seguidores WHERE idseguidor = ? AND idseguido = ?');
        $st->execute([$eu, $idPerfil]);
        $euSigo = (bool) $st->fetch();

        // --- conteúdo da aba ---
        $posts  = [];
        $pastas = [];

        if ($aba === 'salvos' && $souEu) {
            $st = $pdo->prepare(
                sql_post_base() . ' WHERE EXISTS(
                        SELECT 1 FROM midias_em_pasta mp2
                          JOIN Pastas p2 ON p2.id = mp2.idPastas AND p2.idusuario = :dono
                         WHERE mp2.idmidias = m.id_midia)
                    ORDER BY m.criado_em DESC LIMIT 60'
            );
            $st->execute([':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu, ':dono' => $idPerfil]);

        } elseif ($aba === 'curtidas') {
            $st = $pdo->prepare(
                sql_post_base() . ' WHERE EXISTS(
                        SELECT 1 FROM curtidas c2
                         WHERE c2.idmidias = m.id_midia AND c2.idusuario = :dono)
                    ORDER BY m.criado_em DESC LIMIT 60'
            );
            $st->execute([':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu, ':dono' => $idPerfil]);

        } else {
            $st = $pdo->prepare(
                sql_post_base() . ' WHERE m.idusuario = :dono ORDER BY m.criado_em DESC LIMIT 60'
            );
            $st->execute([':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu, ':dono' => $idPerfil]);
        }

        $linhas = $st->fetchAll();
        anexar_tags($linhas);
        $posts = array_map('formatar_post', $linhas);

        if ($souEu) {
            $st = $pdo->prepare(
                'SELECT p.id, p.nome, p.descricao, p.privada,
                        (SELECT COUNT(*) FROM midias_em_pasta mp WHERE mp.idPastas = p.id) AS itens
                   FROM Pastas p WHERE p.idusuario = ? ORDER BY p.criado_em DESC'
            );
            $st->execute([$idPerfil]);
            $pastas = array_map(static fn($p) => [
                'id'    => (int) $p['id'],
                'nome'  => $p['nome'],
                'itens' => (int) $p['itens'],
            ], $st->fetchAll());
        }

        // tags que mais definem esse artista
        $st = $pdo->prepare(
            'SELECT t.nome, COUNT(*) AS n
               FROM midias m
               JOIN midia_tags mt ON mt.idmidias = m.id_midia
               JOIN tags t ON t.id = mt.idtag
              WHERE m.idusuario = ?
              GROUP BY t.id, t.nome ORDER BY n DESC LIMIT 6'
        );
        $st->execute([$idPerfil]);
        $tagsDele = array_column($st->fetchAll(), 'nome');

        $est = estados();

        json_ok([
            'perfil' => [
                'id'           => $idPerfil,
                'usuario'      => $u['nome_usuario'],
                'nome'         => $u['nome_exibicao'] ?: $u['nome_usuario'],
                'bio'          => $u['bio'],
                'foto'         => avatar_url($u['foto_perfil_url']),
                'area'         => $u['area_criativa'],
                'area_label'   => areas()[$u['area_criativa']] ?? 'Criativo',
                'ferramenta'   => $u['ferramenta'],
                'estado'       => $u['estado_criativo'],
                'estado_label' => $est[$u['estado_criativo']]['rotulo'] ?? '',
                'cidade'       => $u['cidade'],
                'pais'         => $u['pais'],
                'pro'          => $u['plano'] === 'pro',
                'desde'        => $u['criado_em'] ? date('m/Y', strtotime($u['criado_em'])) : '',
                'tags'         => $tagsDele,
            ],
            'sou_eu'  => $souEu,
            'eu_sigo' => $euSigo,
            'numeros' => [
                'posts'      => (int) $n['posts'],
                'seguidores' => (int) $n['seguidores'],
                'seguindo'   => (int) $n['seguindo'],
                'curtidas'   => (int) $n['curtidas_recebidas'],
            ],
            'posts'  => $posts,
            'pastas' => $pastas,
            'aba'    => $aba,
        ]);

    } catch (Throwable $e) {
        error_log('[spark][perfil:ver] ' . $e->getMessage());
        json_err('Não foi possível carregar o perfil.', 500);
    }
}

exigir_post();
exigir_csrf();

// =========================================================
// SALVAR PERFIL
// =========================================================
if ($acao === 'salvar') {
    $nome       = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 100);
    $usuario    = mb_substr(trim((string) ($_POST['usuario'] ?? '')), 0, 100);
    $bio        = mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 500);
    $area       = (string) ($_POST['area'] ?? 'outro');
    $ferramenta = mb_substr(trim((string) ($_POST['ferramenta'] ?? '')), 0, 80);
    $cidade     = mb_substr(trim((string) ($_POST['cidade'] ?? '')), 0, 80);

    if (!array_key_exists($area, areas())) $area = 'outro';

    if ($usuario !== '' && !preg_match('/^[A-Za-z0-9_\.]{3,50}$/', $usuario)) {
        json_err('O @ deve ter de 3 a 50 caracteres: letras, números, ponto ou _.');
    }

    try {
        if ($usuario !== '') {
            $st = $pdo->prepare('SELECT 1 FROM usuario WHERE nome_usuario = ? AND id_usuario <> ?');
            $st->execute([$usuario, $eu]);
            if ($st->fetch()) json_err('Esse @ já está em uso.');
        }

        $campos = [
            'nome_exibicao = ?', 'bio = ?', 'area_criativa = ?',
            'ferramenta = ?', 'cidade = ?',
        ];
        $vals = [$nome ?: null, $bio, $area, $ferramenta ?: null, $cidade ?: null];

        if ($usuario !== '') {
            $campos[] = 'nome_usuario = ?';
            $vals[]   = $usuario;
        }

        $vals[] = $eu;
        $pdo->prepare('UPDATE usuario SET ' . implode(', ', $campos) . ' WHERE id_usuario = ?')
            ->execute($vals);

        json_ok(['mensagem' => 'Perfil atualizado.']);

    } catch (Throwable $e) {
        error_log('[spark][perfil:salvar] ' . $e->getMessage());
        json_err('Não foi possível salvar o perfil.', 500);
    }
}

// =========================================================
// FOTO
// =========================================================
if ($acao === 'foto') {
    if (empty($_FILES['foto']['tmp_name']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        json_err('Selecione uma imagem.');
    }

    $arq   = $_FILES['foto'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($arq['tmp_name']) ?: '';

    $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($ok[$mime]))            json_err('Envie uma imagem JPG, PNG, GIF ou WEBP.');
    if ($arq['size'] > 5 * 1024 * 1024) json_err('A imagem passa de 5 MB.');

    try {
        $dir = SPARK_ROOT . '/profile_pics';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            json_err('Não foi possível preparar a pasta.', 500);
        }

        $nome = bin2hex(random_bytes(16)) . '.' . $ok[$mime];
        if (!move_uploaded_file($arq['tmp_name'], $dir . '/' . $nome)) {
            json_err('Falha ao salvar a imagem.', 500);
        }

        // apaga a foto antiga para não acumular lixo no disco
        $st = $pdo->prepare('SELECT foto_perfil_url FROM usuario WHERE id_usuario = ?');
        $st->execute([$eu]);
        $antiga = $st->fetchColumn();

        $pdo->prepare('UPDATE usuario SET foto_perfil_url = ? WHERE id_usuario = ?')
            ->execute(['profile_pics/' . $nome, $eu]);

        if ($antiga) {
            $velho = SPARK_ROOT . '/' . ltrim(str_replace('../', '', (string) $antiga), '/');
            if (is_file($velho)) @unlink($velho);
        }

        json_ok(['foto' => url('profile_pics/' . $nome)]);

    } catch (Throwable $e) {
        error_log('[spark][perfil:foto] ' . $e->getMessage());
        json_err('Não foi possível trocar a foto.', 500);
    }
}

// =========================================================
// SENHA — exige a atual (o update.php antigo apagava a senha)
// =========================================================
if ($acao === 'senha') {
    $atual = (string) ($_POST['atual'] ?? '');
    $nova  = (string) ($_POST['nova'] ?? '');

    if ($atual === '' || $nova === '') json_err('Preencha a senha atual e a nova.');
    if (mb_strlen($nova) < 6)          json_err('A nova senha precisa de pelo menos 6 caracteres.');

    try {
        $st = $pdo->prepare('SELECT senha_hash FROM usuario WHERE id_usuario = ?');
        $st->execute([$eu]);
        $hash = (string) $st->fetchColumn();

        if (!password_verify($atual, $hash)) json_err('A senha atual não confere.');

        $pdo->prepare('UPDATE usuario SET senha_hash = ? WHERE id_usuario = ?')
            ->execute([password_hash($nova, PASSWORD_DEFAULT), $eu]);

        json_ok(['mensagem' => 'Senha alterada.']);

    } catch (Throwable $e) {
        error_log('[spark][perfil:senha] ' . $e->getMessage());
        json_err('Não foi possível alterar a senha.', 500);
    }
}

json_err('Ação desconhecida.', 404);
