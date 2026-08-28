<?php
/**
 * SPARK — criação, leitura e remoção de posts.
 *
 * acao=criar   (POST, multipart)  — publica com mídia opcional
 * acao=apagar  (POST)             — só o autor
 * acao=ver     (GET)              — post único com comentários
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/posts.php';

$eu   = exigir_login();
$pdo  = db();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'ver';

/**
 * Tipos aceitos. O MIME é lido do CONTEÚDO do arquivo (finfo), não do
 * cabeçalho enviado pelo navegador — esse é falsificável.
 */
function tipos_aceitos(): array
{
    return [
        'image/jpeg' => ['id' => 1, 'pasta' => 'imagem',    'ext' => 'jpg',  'max' => 8],
        'image/png'  => ['id' => 1, 'pasta' => 'imagem',    'ext' => 'png',  'max' => 8],
        'image/gif'  => ['id' => 1, 'pasta' => 'imagem',    'ext' => 'gif',  'max' => 8],
        'image/webp' => ['id' => 1, 'pasta' => 'imagem',    'ext' => 'webp', 'max' => 8],
        'video/mp4'  => ['id' => 2, 'pasta' => 'video',     'ext' => 'mp4',  'max' => 60],
        'video/webm' => ['id' => 2, 'pasta' => 'video',     'ext' => 'webm', 'max' => 60],
        'application/pdf' => ['id' => 3, 'pasta' => 'documento', 'ext' => 'pdf', 'max' => 20],
        'audio/mpeg' => ['id' => 4, 'pasta' => 'audio',     'ext' => 'mp3',  'max' => 30],
        'audio/wav'  => ['id' => 4, 'pasta' => 'audio',     'ext' => 'wav',  'max' => 30],
        'audio/x-wav'=> ['id' => 4, 'pasta' => 'audio',     'ext' => 'wav',  'max' => 30],
        'audio/ogg'  => ['id' => 4, 'pasta' => 'audio',     'ext' => 'ogg',  'max' => 30],
        'audio/mp4'  => ['id' => 4, 'pasta' => 'audio',     'ext' => 'm4a',  'max' => 30],
    ];
}

// =========================================================
// CRIAR
// =========================================================
if ($acao === 'criar') {
    exigir_post();
    exigir_csrf();

    $titulo    = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 255);
    $descricao = mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 1000);

    $temArquivo = !empty($_FILES['midia']['tmp_name'])
               && ($_FILES['midia']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

    if (!$temArquivo && $descricao === '' && $titulo === '') {
        json_err('Escreva alguma coisa ou anexe um arquivo.');
    }

    $urlPublica = null;
    $idTipo     = 1;
    $mime       = null;
    $tamanho    = null;

    if ($temArquivo) {
        $arq = $_FILES['midia'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($arq['tmp_name']) ?: '';

        $aceitos = tipos_aceitos();
        if (!isset($aceitos[$mime])) {
            json_err('Formato não suportado. Envie imagem, vídeo, áudio ou PDF.');
        }

        $cfg = $aceitos[$mime];
        if ($arq['size'] > $cfg['max'] * 1024 * 1024) {
            json_err('Arquivo muito grande. O limite para esse tipo é ' . $cfg['max'] . ' MB.');
        }

        $dir = SPARK_ROOT . '/midias_upload/' . $cfg['pasta'];
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            json_err('Não foi possível preparar a pasta de upload.', 500);
        }

        // Nome gerado por nós: o nome original nunca chega ao disco.
        $nome    = bin2hex(random_bytes(16)) . '.' . $cfg['ext'];
        $destino = $dir . '/' . $nome;

        if (!move_uploaded_file($arq['tmp_name'], $destino)) {
            json_err('Falha ao salvar o arquivo.', 500);
        }

        $urlPublica = 'midias_upload/' . $cfg['pasta'] . '/' . $nome;
        $idTipo     = $cfg['id'];
        $tamanho    = (int) $arq['size'];
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            'INSERT INTO midias (idusuario, idTipos_midia, titulo, descricao, midia_url, mime_type, tamanho_bytes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eu, $idTipo, $titulo, $descricao, $urlPublica, $mime, $tamanho]);

        $idmidia = (int) $pdo->lastInsertId();

        // #hashtags do título + descrição viram tags de verdade
        sincronizar_tags($idmidia, $titulo . ' ' . $descricao);

        $pdo->commit();

        // Publicar é o sinal mais forte de gosto que existe
        registrar_interacao($eu, $idmidia, 'compartilhamento');

        $st = $pdo->prepare(sql_post_base() . ' WHERE m.id_midia = :id');
        $st->execute([':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu, ':id' => $idmidia]);
        $linhas = $st->fetchAll();
        anexar_tags($linhas);

        json_out(['ok' => true, 'post' => formatar_post($linhas[0])], 201);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($urlPublica && is_file(SPARK_ROOT . '/' . $urlPublica)) {
            @unlink(SPARK_ROOT . '/' . $urlPublica);   // não deixa arquivo órfão
        }
        error_log('[spark][post:criar] ' . $e->getMessage());
        json_err('Não foi possível publicar agora.', 500);
    }
}

// =========================================================
// APAGAR
// =========================================================
if ($acao === 'apagar') {
    exigir_post();
    exigir_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_err('Post inválido.');

    try {
        $st = $pdo->prepare('SELECT idusuario, midia_url FROM midias WHERE id_midia = ?');
        $st->execute([$id]);
        $post = $st->fetch();

        if (!$post) json_err('Post não encontrado.', 404);
        if ((int) $post['idusuario'] !== $eu) json_err('Esse post não é seu.', 403);

        $pdo->beginTransaction();

        // As FKs não têm ON DELETE CASCADE no schema original, então limpamos na mão.
        foreach ([
            'DELETE FROM notificacoes    WHERE idmidias = ?',
            'DELETE FROM midias_em_pasta WHERE idmidias = ?',
            'DELETE FROM midia_tags      WHERE idmidias = ?',
            'DELETE FROM interacoes      WHERE idmidias = ?',
            'DELETE FROM comentarios     WHERE idmidias = ?',
            'DELETE FROM curtidas        WHERE idmidias = ?',
            'UPDATE mensagens SET idmidia_anexo = NULL WHERE idmidia_anexo = ?',
            'DELETE FROM midias          WHERE id_midia = ?',
        ] as $sql) {
            $pdo->prepare($sql)->execute([$id]);
        }

        $pdo->commit();

        if (!empty($post['midia_url'])) {
            $caminho = SPARK_ROOT . '/' . ltrim(str_replace('../', '', $post['midia_url']), '/');
            if (is_file($caminho)) @unlink($caminho);
        }

        json_ok();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[spark][post:apagar] ' . $e->getMessage());
        json_err('Não foi possível apagar o post.', 500);
    }
}

// =========================================================
// VER — post único
// =========================================================
if ($acao === 'ver') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) json_err('Post inválido.');

    try {
        $st = $pdo->prepare(sql_post_base() . ' WHERE m.id_midia = :id');
        $st->execute([':eu_curti' => $eu, ':eu_sigo' => $eu, ':eu_salvei' => $eu, ':id' => $id]);
        $linhas = $st->fetchAll();

        if (!$linhas) json_err('Post não encontrado.', 404);

        anexar_tags($linhas);
        registrar_interacao($eu, $id, 'visualizacao');

        json_ok(['post' => formatar_post($linhas[0])]);

    } catch (Throwable $e) {
        error_log('[spark][post:ver] ' . $e->getMessage());
        json_err('Não foi possível carregar o post.', 500);
    }
}

json_err('Ação desconhecida.', 404);
