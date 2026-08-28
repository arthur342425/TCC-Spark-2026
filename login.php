<?php
/**
 * SPARK — entrada.
 * Login e cadastro na mesma página. O cadastro coleta área criativa e
 * interesses, que já entram como pontuação inicial do feed inteligente.
 */

declare(strict_types=1);

require __DIR__ . '/config/config.php';
require __DIR__ . '/config/posts.php';

if (logado()) {
    header('Location: ' . url('index.php'));
    exit;
}

$erro    = '';
$sucesso = '';
$aba     = ($_GET['aba'] ?? 'entrar') === 'criar' ? 'criar' : 'entrar';

$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        $tipo = $_POST['tipo'] ?? '';

        // ---------------------------------------------------
        // ENTRAR — aceita @usuario ou e-mail no mesmo campo
        // ---------------------------------------------------
        if ($tipo === 'entrar') {
            $aba   = 'entrar';
            $login = trim((string) ($_POST['login'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');

            if ($login === '' || $senha === '') {
                $erro = 'Preencha os dois campos.';
            } else {
                $st = $pdo->prepare(
                    'SELECT id_usuario, senha_hash FROM usuario
                      WHERE (nome_usuario = ? OR email = ?) AND ativo = 1 LIMIT 1'
                );
                $st->execute([$login, $login]);
                $u = $st->fetch();

                // Mensagem única: não revela se a conta existe.
                if (!$u || !password_verify($senha, $u['senha_hash'])) {
                    $erro = 'Usuário ou senha incorretos.';
                    usleep(300000);
                } else {
                    if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
                        $pdo->prepare('UPDATE usuario SET senha_hash = ? WHERE id_usuario = ?')
                            ->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id_usuario']]);
                    }

                    session_regenerate_id(true);   // evita fixação de sessão
                    $_SESSION['user_id'] = (int) $u['id_usuario'];

                    $pdo->prepare('UPDATE usuario SET ultimo_acesso = NOW() WHERE id_usuario = ?')
                        ->execute([$u['id_usuario']]);

                    header('Location: ' . url('index.php'));
                    exit;
                }
            }
        }

        // ---------------------------------------------------
        // CRIAR CONTA
        // ---------------------------------------------------
        if ($tipo === 'criar') {
            $aba        = 'criar';
            $usuario    = trim((string) ($_POST['usuario'] ?? ''));
            $email      = trim((string) ($_POST['email'] ?? ''));
            $senha      = (string) ($_POST['nova_senha'] ?? '');
            $nome       = trim((string) ($_POST['nome'] ?? ''));
            $area       = (string) ($_POST['area'] ?? 'outro');
            $ferramenta = trim((string) ($_POST['ferramenta'] ?? ''));
            $interesses = $_POST['interesses'] ?? [];

            if (!array_key_exists($area, areas())) $area = 'outro';

            if ($usuario === '' || $email === '' || $senha === '') {
                $erro = 'Preencha usuário, e-mail e senha.';
            } elseif (!preg_match('/^[A-Za-z0-9_\.]{3,50}$/', $usuario)) {
                $erro = 'O @ aceita de 3 a 50 caracteres: letras, números, ponto ou _.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erro = 'Esse e-mail não parece válido.';
            } elseif (mb_strlen($senha) < 6) {
                $erro = 'A senha precisa de pelo menos 6 caracteres.';
            } else {
                $st = $pdo->prepare('SELECT nome_usuario, email FROM usuario WHERE nome_usuario = ? OR email = ? LIMIT 1');
                $st->execute([$usuario, $email]);
                $existe = $st->fetch();

                if ($existe) {
                    $erro = $existe['nome_usuario'] === $usuario
                        ? 'Esse @ já está em uso.'
                        : 'Já existe uma conta com esse e-mail.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $pdo->prepare(
                            'INSERT INTO usuario
                                (nome_usuario, nome_exibicao, email, senha_hash, area_criativa,
                                 ferramenta, estado_criativo, estado_em, idtipos_user, ativo, pais)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 2, 1, ?)'
                        )->execute([
                            $usuario, ($nome ?: null), $email,
                            password_hash($senha, PASSWORD_DEFAULT),
                            $area, ($ferramenta ?: null), 'observando', 'Brasil',
                        ]);

                        $novoId = (int) $pdo->lastInsertId();

                        // Interesses viram afinidade inicial: o feed já nasce
                        // com uma noção do gosto do usuário.
                        if (is_array($interesses) && $interesses) {
                            $buscar = $pdo->prepare('SELECT id FROM tags WHERE nome = ?');
                            $criar  = $pdo->prepare('INSERT INTO tags (nome) VALUES (?)');
                            $afin   = $pdo->prepare(
                                'INSERT INTO afinidade_usuario_tag (idusuario, idtag, pontuacao)
                                 VALUES (?, ?, 15)
                                 ON DUPLICATE KEY UPDATE pontuacao = pontuacao + 15'
                            );

                            foreach (array_slice($interesses, 0, 12) as $nomeTag) {
                                $nomeTag = mb_strtolower(trim((string) $nomeTag), 'UTF-8');
                                if ($nomeTag === '') continue;

                                $buscar->execute([$nomeTag]);
                                $tag = $buscar->fetch();

                                if ($tag) {
                                    $idtag = (int) $tag['id'];
                                } else {
                                    $criar->execute([$nomeTag]);
                                    $idtag = (int) $pdo->lastInsertId();
                                }

                                $afin->execute([$novoId, $idtag]);
                            }
                        }

                        $pdo->commit();

                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $novoId;

                        header('Location: ' . url('index.php'));
                        exit;

                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log('[spark][cadastro] ' . $e->getMessage());
                        $erro = 'Não foi possível criar sua conta agora.';
                    }
                }
            }
        }
    }
}

$sugestoesTags = [
    'musica'      => ['sample', 'beat', 'mixagem', 'composicao', 'letra', 'loop'],
    'visual'      => ['pintura', 'ilustracao', 'sketch', 'paleta', 'fotografia'],
    'design'      => ['ui', 'tipografia', 'branding', 'processo'],
    'escrita'     => ['roteiro', 'poesia', 'letra', 'processo'],
    'audiovisual' => ['fotografia', 'roteiro', 'processo', 'referencia'],
];
$todasTags = array_values(array_unique(array_merge(...array_values($sugestoesTags))));
?>
<!DOCTYPE html>
<html lang="pt-BR" data-tema="escuro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Spark — quebre o bloqueio criativo</title>
<meta name="description" content="A rede social feita por artistas, para artistas. Entre e destrave seu processo criativo.">
<link rel="icon" href="<?= e(logo_favicon()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..800&family=Inter:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= fontes_locais() ?><link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<style>
  body { overflow-x: hidden; }

  .entrada { display: grid; grid-template-columns: 1.05fr .95fr; min-height: 100vh; }

  /* ---------- lado esquerdo: a promessa ---------- */
  .vitrine {
    padding: 54px 56px;
    display: flex; flex-direction: column; justify-content: space-between;
    position: relative; overflow: hidden;
    border-right: 1px solid var(--line);
    backdrop-filter: blur(var(--blur)) saturate(var(--satura));
    -webkit-backdrop-filter: blur(var(--blur)) saturate(var(--satura));
    background: linear-gradient(160deg, var(--vidro-2), transparent 70%);
    box-shadow: inset -1px 0 0 var(--borda-baixa);
  }

  .vitrine__marca {
    display: flex; align-items: center; gap: 13px;
    animation: entra-esq .7s var(--suave) backwards;
  }
  @keyframes entra-esq {
    from { opacity: 0; transform: translateX(-26px); }
    to   { opacity: 1; transform: none; }
  }

  .vitrine__frase {
    font-family: var(--font-display);
    font-size: clamp(2rem, 3.7vw, 3.2rem);
    font-weight: 700; line-height: 1.06; letter-spacing: -.035em;
    margin-bottom: 20px;
    animation: entra-esq .7s var(--suave) .1s backwards;
  }
  .vitrine__frase em {
    font-style: normal;
    background: linear-gradient(110deg, var(--ember-hi), var(--ember) 45%, #ff7a00);
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
    position: relative;
  }
  .vitrine__texto {
    color: var(--fg-dim); font-size: 16px; max-width: 470px; margin-bottom: 36px;
    animation: entra-esq .7s var(--suave) .18s backwards;
  }

  .pilar {
    display: flex; gap: 15px; margin-bottom: 14px; max-width: 450px;
    padding: 13px 15px;
    border-radius: var(--r);
    position: relative;
    background: transparent;
    transition: transform var(--t-med) var(--mola), background var(--t-med) var(--suave),
                box-shadow var(--t-med) var(--suave);
    animation: entra-esq .7s var(--suave) backwards;
  }
  .pilar:nth-of-type(1) { animation-delay: .26s; }
  .pilar:nth-of-type(2) { animation-delay: .34s; }
  .pilar:nth-of-type(3) { animation-delay: .42s; }

  .pilar:hover {
    transform: translateX(7px);
    background: var(--vidro-1);
    box-shadow: inset 0 1px 0 var(--borda-luz), var(--sombra-1);
  }

  .pilar__i {
    width: 44px; height: 44px; border-radius: var(--r-sm); flex-shrink: 0;
    display: grid; place-items: center; font-size: 16px;
    background: linear-gradient(140deg, var(--spark-glow), rgba(139,92,246,.05));
    color: var(--spark-hi);
    box-shadow: inset 0 1px 0 rgba(167,139,250,.3);
    transition: transform var(--t-med) var(--mola), box-shadow var(--t-med);
  }
  .pilar:hover .pilar__i {
    transform: rotate(-9deg) scale(1.1);
    box-shadow: inset 0 1px 0 rgba(167,139,250,.5), 0 6px 20px var(--spark-glow);
  }
  .pilar h3 { font-family: var(--font-display); font-size: 15px; font-weight: 600; margin-bottom: 3px; }
  .pilar p { font-size: 13.5px; color: var(--fg-mute); }

  /* ---------- lado direito: o formulário ---------- */
  .painel { display: grid; place-items: center; padding: 40px 44px; }

  .painel__caixa {
    width: 100%; max-width: 430px;
    padding: 34px;
    border-radius: var(--r-xl);
    position: relative;
    /* A caixa precisa de base própria. Só com osso a 7% ela não é um
       cartão: é uma lente, e o que aparece é o violeta das ondas
       ampliado pelo saturate. Pior que feio, isso torna o contraste
       do texto refém da animação — num quadro claro, o rótulo some.
       Com o grafite embaixo o vidro continua vidro (28% passa, e o
       blur segue vivo), mas a leitura vira garantida. */
    background:
      radial-gradient(130% 100% at 12% -14%, rgba(219,217,207,.11), transparent 56%),
      linear-gradient(158deg, rgba(127,0,255,.14), transparent 46%),
      rgba(17,17,20,.72);
    backdrop-filter: blur(30px) saturate(125%);
    -webkit-backdrop-filter: blur(30px) saturate(125%);
    box-shadow:
      inset 0 1px 0 var(--borda-luz),
      inset 0 0 0 1px rgba(219,217,207,.06),
      0 30px 70px rgba(0,0,0,.5),
      0 0 64px rgba(127,0,255,.13);
    animation: surge-painel .8s var(--mola);
  }
  @keyframes surge-painel {
    from { opacity: 0; transform: translateY(30px) scale(.94); filter: blur(8px); }
    to   { opacity: 1; transform: none; filter: none; }
  }

  /* anel especular: arco vivo que segue o cursor + bisel fixo
     (mesma técnica de app.css — ver o bloco ANEL ESPECULAR lá) */
  .painel__caixa::before {
    content: '';
    position: absolute; inset: 0;
    border-radius: inherit;
    padding: 1px;
    background:
      conic-gradient(from calc(var(--ang) - 62deg) at 50% 50%,
          transparent 0deg,
          rgba(255,255,255, calc(var(--rim) * .55)) 30deg,
          rgba(255,255,255, calc(var(--rim) * .95)) 62deg,
          rgba(255,255,255, calc(var(--rim) * .55)) 94deg,
          transparent 124deg),
      linear-gradient(145deg,
          rgba(255,255,255,.55) 0%, rgba(255,255,255,.08) 28%,
          transparent 52%, rgba(255,255,255,.28) 100%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
            mask-composite: exclude;
    pointer-events: none;
    transition: --rim .45s var(--suave);
  }

  /* ---------- leitura dentro da caixa ----------
     Os tons --fg-mute foram calibrados contra o grafite #121214.
     Aqui embaixo há violeta, então o que era 5,3:1 no app cai para
     ~4,4:1. Subir um degrau devolve o AA sem inventar cor nova. */
  .painel__caixa .campo label {
    color: var(--fg-dim);
    font-size: 11px;
  }
  .painel__caixa .campo:focus-within label { color: var(--spark-hi); }

  /* O campo era osso a 4,5%: sobre violeta isso não é uma caixa,
     é nada. Escurecer faz dele um poço, e aí a borda tem o que
     delimitar e o texto digitado tem sobre o que pousar. */
  .painel__caixa .campo input {
    background: rgba(9, 9, 11, .5);
    border-color: rgba(219, 217, 207, .16);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, .3);
    color: var(--fg);
  }
  .painel__caixa .campo input:hover {
    background: rgba(9, 9, 11, .58);
    border-color: rgba(219, 217, 207, .26);
  }
  .painel__caixa .campo input:focus {
    background: rgba(9, 9, 11, .62);
    border-color: rgba(166, 77, 255, .78);
    box-shadow: inset 0 1px 2px rgba(0,0,0,.3), 0 0 0 4px rgba(127, 0, 255, .22);
  }
  .painel__caixa .campo input::placeholder { color: #8f8d85; }
  .painel__caixa .olho { color: var(--fg-dim); }
  .painel__caixa .olho:hover { color: var(--fg); }

  /* Com a caixa mais escura, a placa de osso a 4% sumiu contra ela
     (1,10:1). Um fio de borda devolve o limite sem pesar. */
  .painel__caixa .cartao-area:not(.marcada) {
    background: rgba(219, 217, 207, .055);
    box-shadow: inset 0 0 0 1px rgba(219, 217, 207, .1),
                inset 0 1px 0 rgba(219, 217, 207, .16);
  }
  .painel__caixa .cartao-area:not(.marcada):hover {
    background: rgba(219, 217, 207, .1);
    box-shadow: inset 0 0 0 1px rgba(219, 217, 207, .2),
                inset 0 1px 0 rgba(219, 217, 207, .26);
  }

  /* subtítulo e rodapé legal: eram o texto mais apagado da tela */
  .painel__caixa > p,
  .painel__caixa .passo > p { color: var(--fg-dim); }
  .painel__caixa .termos {
    text-align: center;
    color: var(--fg-dim);
    font-size: 12.5px;
    line-height: 1.65;
    margin-top: 26px;
  }

  /* ---------- alternador entrar / criar ---------- */
  .troca {
    display: flex;
    border-radius: var(--r-pill);
    padding: 5px;
    margin-bottom: 28px;
    /* trilho escuro: dá ao botão ativo contra o que se destacar */
    background: rgba(9, 9, 11, .42);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, .26);
    /* sem isto, o realce do botão ativo (::before z-index:-1) some atrás deste fundo */
    isolation: isolate;
  }
  .troca button {
    flex: 1; padding: 10px; border-radius: var(--r-pill);
    font-size: 14px; font-weight: 600; color: var(--fg-dim);
    position: relative; overflow: hidden;
    transition: color var(--t-med) var(--suave), transform var(--t-fast) var(--mola);
  }
  .troca button::before {
    content: '';
    position: absolute; inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, var(--spark-hi), var(--spark) 60%, #6d28d9);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 4px 16px var(--spark-glow);
    opacity: 0; transform: scale(.86);
    transition: opacity var(--t-med) var(--suave), transform var(--t-med) var(--mola);
    z-index: -1;
  }
  .troca button:hover { color: var(--fg); }
  .troca button.ativa { color: #fff; }
  .troca button.ativa::before { opacity: 1; transform: scale(1); }

  /* ---------- passos ---------- */
  .passo { display: none; }
  .passo.ativo { display: block; animation: entra-passo .45s var(--suave); }
  @keyframes entra-passo {
    from { opacity: 0; transform: translateX(24px); filter: blur(4px); }
    to   { opacity: 1; transform: none; filter: none; }
  }

  .trilha { display: flex; gap: 7px; margin-bottom: 26px; }
  .trilha i {
    height: 4px; flex: 1; border-radius: var(--r-pill);
    background: var(--vidro-3);
    transition: background var(--t-med) var(--suave), box-shadow var(--t-med);
  }
  .trilha i.feito {
    background: linear-gradient(90deg, var(--spark), var(--spark-hi));
    box-shadow: 0 0 12px var(--spark-glow);
  }

  /* ---------- áreas criativas ---------- */
  .grade-areas { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .cartao-area {
    padding: 17px 13px;
    border-radius: var(--r-sm);
    text-align: center;
    position: relative; overflow: hidden;
    background: var(--vidro-1);
    box-shadow: inset 0 1px 0 var(--borda-baixa);
    transition: transform var(--t-med) var(--mola), background var(--t-med) var(--suave),
                box-shadow var(--t-med) var(--suave);
  }
  .cartao-area:hover {
    transform: translateY(-4px) scale(1.03);
    background: var(--vidro-3);
    box-shadow: inset 0 1px 0 var(--borda-luz), var(--sombra-2);
  }
  .cartao-area:active { transform: translateY(0) scale(.97); }
  .cartao-area.marcada {
    background: linear-gradient(140deg, var(--spark-glow), rgba(139,92,246,.05));
    box-shadow: inset 0 1px 0 rgba(167,139,250,.45), 0 6px 22px var(--spark-glow);
  }
  .cartao-area i {
    font-size: 21px; color: var(--spark-hi); display: block; margin-bottom: 8px;
    transition: transform var(--t-med) var(--mola);
  }
  .cartao-area:hover i { transform: translateY(-3px) scale(1.15); }
  .cartao-area.marcada i { color: var(--ember); }
  .cartao-area span { font-size: 13px; font-weight: 500; }

  /* ---------- sementes de interesse ---------- */
  .nuvem { display: flex; flex-wrap: wrap; gap: 9px; }
  .semente {
    padding: 9px 15px;
    border-radius: var(--r-pill);
    font-family: var(--font-mono);
    font-size: 12.5px;
    position: relative; overflow: hidden;
    background: var(--vidro-1);
    box-shadow: inset 0 1px 0 var(--borda-baixa);
    transition: transform var(--t-med) var(--mola), background var(--t-med),
                color var(--t-med), box-shadow var(--t-med);
  }
  .semente:hover {
    transform: translateY(-3px) scale(1.05);
    background: var(--vidro-3);
    box-shadow: inset 0 1px 0 var(--borda-luz), var(--sombra-1);
  }
  .semente:active { transform: scale(.94); }
  .semente.marcada {
    color: var(--spark-hi);
    background: linear-gradient(135deg, var(--spark-glow), rgba(139,92,246,.06));
    box-shadow: inset 0 1px 0 rgba(167,139,250,.4), 0 4px 16px var(--spark-glow);
  }

  @media (max-width: 980px) {
    .entrada { grid-template-columns: 1fr; }
    .vitrine { display: none; }
    .painel { padding: 24px 16px; min-height: 100vh; }
    .painel__caixa { max-width: 450px; padding: 26px 22px; }
  }
</style>
</head>
<body>

<!-- cortina de abertura -->
<div class="cortina" id="cortina" aria-hidden="true">
  <div class="cortina__marca"><?= logo_assinatura() ?></div>
  <div class="cortina__trilho"><i></i></div>
  <div class="cortina__nota">quebre o bloqueio</div>
</div>


<div class="entrada">

  <!-- ============ VITRINE ============ -->
  <section class="vitrine">
    <div class="vitrine__marca">
      <span class="marca__chama"><?= logo_marca() ?></span>
      <span class="marca__nome"><?= logo_assinatura() ?></span>
    </div>

    <div>
      <h1 class="vitrine__frase">Quebre o bloqueio.<br>Acenda a <em>chama criativa</em>.</h1>
      <p class="vitrine__texto">
        A rede social feita por artistas, para artistas. Aqui o processo é o
        protagonista — não só o resultado publicado.
      </p>

      <div class="pilar">
        <span class="pilar__i"><i class="fa-solid fa-fire"></i></span>
        <div>
          <h3>Feed inteligente</h3>
          <p>Produtor vê sample e referência sonora. Pintor vê técnica e paleta. O feed entende seu ofício.</p>
        </div>
      </div>

      <div class="pilar">
        <span class="pilar__i"><i class="fa-solid fa-bolt"></i></span>
        <div>
          <h3>Botão Conexão</h3>
          <p>Um clique liga você a outro artista que está no mesmo momento criativo. Você não trava sozinho.</p>
        </div>
      </div>

      <div class="pilar">
        <span class="pilar__i"><i class="fa-solid fa-comments"></i></span>
        <div>
          <h3>Fóruns artísticos</h3>
          <p>Espaços por gênero, técnica e ferramenta. Comunidade de verdade, sem algoritmo de vaidade.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ PAINEL ============ -->
  <section class="painel">
    <div class="painel__caixa">

      <div class="troca">
        <button type="button" class="<?= $aba === 'entrar' ? 'ativa' : '' ?>" id="tab-entrar">Entrar</button>
        <button type="button" class="<?= $aba === 'criar' ? 'ativa' : '' ?>" id="tab-criar">Criar conta</button>
      </div>

      <?php if ($erro): ?>
        <div class="alerta alerta--erro"><i class="fa-solid fa-circle-exclamation"></i> <?= e($erro) ?></div>
      <?php endif; ?>
      <?php if ($sucesso): ?>
        <div class="alerta alerta--ok"><i class="fa-solid fa-check"></i> <?= e($sucesso) ?></div>
      <?php endif; ?>

      <!-- ---------- ENTRAR ---------- -->
      <form method="post" id="form-entrar" class="<?= $aba === 'entrar' ? '' : 'hidden' ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="tipo" value="entrar">

        <h2 style="font-family:var(--font-display);font-size:23px;margin-bottom:6px">Bom te ver de novo</h2>
        <p style="color:var(--fg-dim);font-size:14px;margin-bottom:24px">Continue de onde parou.</p>

        <div class="campo">
          <label for="login">@ ou e-mail</label>
          <input type="text" id="login" name="login" required autocomplete="username"
                 value="<?= e($_POST['login'] ?? '') ?>" placeholder="seuarroba ou voce@email.com">
        </div>

        <div class="campo">
          <label for="senha">Senha</label>
          <input type="password" id="senha" name="senha" required autocomplete="current-password" placeholder="••••••••">
        </div>

        <button class="btn btn--primario btn--bloco" type="submit" style="margin-top:8px">
          <i class="fa-solid fa-bolt"></i> Entrar no Spark
        </button>
      </form>

      <!-- ---------- CRIAR CONTA ---------- -->
      <form method="post" id="form-criar" class="<?= $aba === 'criar' ? '' : 'hidden' ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="tipo" value="criar">

        <div class="trilha">
          <i class="feito" data-trilha="1"></i>
          <i data-trilha="2"></i>
          <i data-trilha="3"></i>
        </div>

        <!-- passo 1 -->
        <div class="passo ativo" data-passo="1">
          <h2 style="font-family:var(--font-display);font-size:23px;margin-bottom:6px">Crie sua conta</h2>
          <p style="color:var(--fg-dim);font-size:14px;margin-bottom:22px">Leva menos de um minuto.</p>

          <div class="campo">
            <label for="c-usuario">Seu @</label>
            <input type="text" id="c-usuario" name="usuario" maxlength="50" required
                   value="<?= e($_POST['usuario'] ?? '') ?>" placeholder="ex: novakprod">
          </div>

          <div class="campo">
            <label for="c-email">E-mail</label>
            <input type="email" id="c-email" name="email" required
                   value="<?= e($_POST['email'] ?? '') ?>" placeholder="voce@email.com">
          </div>

          <div class="campo">
            <label for="c-senha">Senha</label>
            <input type="password" id="c-senha" name="nova_senha" required minlength="6" placeholder="mínimo 6 caracteres">
          </div>

          <button type="button" class="btn btn--primario btn--bloco" data-avancar="2">Continuar</button>
        </div>

        <!-- passo 2 -->
        <div class="passo" data-passo="2">
          <h2 style="font-family:var(--font-display);font-size:23px;margin-bottom:6px">O que você cria?</h2>
          <p style="color:var(--fg-dim);font-size:14px;margin-bottom:22px">
            É assim que o feed sabe o que te mostrar.
          </p>

          <input type="hidden" name="area" id="c-area" value="musica">

          <div class="grade-areas" style="margin-bottom:18px">
            <button type="button" class="cartao-area marcada" data-area="musica">
              <i class="fa-solid fa-music"></i><span>Produção musical</span></button>
            <button type="button" class="cartao-area" data-area="visual">
              <i class="fa-solid fa-palette"></i><span>Artes visuais</span></button>
            <button type="button" class="cartao-area" data-area="design">
              <i class="fa-solid fa-pen-nib"></i><span>Design</span></button>
            <button type="button" class="cartao-area" data-area="escrita">
              <i class="fa-solid fa-feather"></i><span>Escrita</span></button>
            <button type="button" class="cartao-area" data-area="audiovisual">
              <i class="fa-solid fa-video"></i><span>Audiovisual</span></button>
            <button type="button" class="cartao-area" data-area="outro">
              <i class="fa-solid fa-shapes"></i><span>Multidisciplinar</span></button>
          </div>

          <div class="campo">
            <label for="c-nome">Nome de exibição</label>
            <input type="text" id="c-nome" name="nome" maxlength="100" placeholder="Como quer ser chamado">
          </div>

          <div class="campo">
            <label for="c-ferramenta">Instrumento / ferramenta principal</label>
            <input type="text" id="c-ferramenta" name="ferramenta" maxlength="80" placeholder="Ex: FL Studio, guitarra, Procreate">
          </div>

          <div style="display:flex;gap:10px">
            <button type="button" class="btn btn--linha" data-voltar="1">Voltar</button>
            <button type="button" class="btn btn--primario" style="flex:1" data-avancar="3">Continuar</button>
          </div>
        </div>

        <!-- passo 3 -->
        <div class="passo" data-passo="3">
          <h2 style="font-family:var(--font-display);font-size:23px;margin-bottom:6px">O que te interessa?</h2>
          <p style="color:var(--fg-dim);font-size:14px;margin-bottom:22px">
            Escolha alguns temas. Dá para mudar depois — o feed aprende sozinho.
          </p>

          <div class="nuvem" style="margin-bottom:24px">
            <?php foreach ($todasTags as $t): ?>
              <button type="button" class="semente" data-tag="<?= e($t) ?>">#<?= e($t) ?></button>
            <?php endforeach; ?>
          </div>

          <div id="caixa-interesses"></div>

          <div style="display:flex;gap:10px">
            <button type="button" class="btn btn--linha" data-voltar="2">Voltar</button>
            <button type="submit" class="btn btn--primario" style="flex:1">
              <i class="fa-solid fa-bolt"></i> Criar minha conta
            </button>
          </div>
        </div>
      </form>

      <p class="termos">
        Ao continuar você concorda com os termos de uso.<br>
        Seus dados nunca são vendidos — publicidade aqui é contextual.
      </p>
    </div>
  </section>
</div>

<script src="<?= asset('assets/js/vidro.js') ?>"></script>
<script>
const $ = (s) => document.querySelector(s);
const $$ = (s) => [...document.querySelectorAll(s)];

/* troca entre entrar e criar */
function mostrarAba(qual) {
  $('#form-entrar').classList.toggle('hidden', qual !== 'entrar');
  $('#form-criar').classList.toggle('hidden', qual !== 'criar');
  $('#tab-entrar').classList.toggle('ativa', qual === 'entrar');
  $('#tab-criar').classList.toggle('ativa', qual === 'criar');
}
$('#tab-entrar').addEventListener('click', () => mostrarAba('entrar'));
$('#tab-criar').addEventListener('click', () => mostrarAba('criar'));

/* passos do cadastro */
function irAoPasso(n) {
  $$('.passo').forEach((p) => p.classList.toggle('ativo', p.dataset.passo === String(n)));
  $$('[data-trilha]').forEach((t) => t.classList.toggle('feito', Number(t.dataset.trilha) <= n));
}

$$('[data-avancar]').forEach((b) => b.addEventListener('click', () => {
  const destino = Number(b.dataset.avancar);

  // valida só o passo atual antes de deixar avançar
  if (destino === 2) {
    const campos = ['#c-usuario', '#c-email', '#c-senha'];
    for (const sel of campos) {
      const el = $(sel);
      if (!el.checkValidity()) { el.reportValidity(); return; }
    }
  }
  irAoPasso(destino);
}));

$$('[data-voltar]').forEach((b) => b.addEventListener('click', () => irAoPasso(Number(b.dataset.voltar))));

/* área criativa */
$$('.cartao-area').forEach((c) => c.addEventListener('click', () => {
  $$('.cartao-area').forEach((o) => o.classList.remove('marcada'));
  c.classList.add('marcada');
  $('#c-area').value = c.dataset.area;
}));

/* interesses viram inputs escondidos no envio */
const escolhidas = new Set();
$$('.semente').forEach((s) => s.addEventListener('click', () => {
  const tag = s.dataset.tag;
  if (escolhidas.has(tag)) { escolhidas.delete(tag); s.classList.remove('marcada'); }
  else { escolhidas.add(tag); s.classList.add('marcada'); }

  $('#caixa-interesses').innerHTML = [...escolhidas]
    .map((t) => `<input type="hidden" name="interesses[]" value="${t}">`).join('');
}));
</script>
<script type="importmap">
{
  "imports": {
    "lenis": "https://cdn.jsdelivr.net/npm/lenis@1.1.18/+esm",
    "three": "https://unpkg.com/three@0.143.0/build/three.module.js",
    "three/addons/": "https://unpkg.com/three@0.143.0/examples/jsm/"
  }
}
</script>
<script type="module" src="<?= asset('assets/js/movimento.js') ?>"></script>
</body>
</html>
