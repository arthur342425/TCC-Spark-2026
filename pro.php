<?php
/**
 * SPARK PRO — página de planos.
 * Materializa a receita 1 do pitch (assinatura, R$ 29,90/mês).
 */

declare(strict_types=1);

require __DIR__ . '/config/config.php';

exigir_login_web();
$mim = usuario_atual();
$jaEhPro = ($mim['plano'] ?? 'free') === 'pro';

// Alternar plano é uma demonstração: numa versão real isso viria
// do retorno do gateway de pagamento, nunca de um clique do usuário.
$mensagem = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valido($_POST['csrf'] ?? null)) {
    $novo = $jaEhPro ? 'free' : 'pro';
    db()->prepare('UPDATE usuario SET plano = ? WHERE id_usuario = ?')
        ->execute([$novo, $mim['id_usuario']]);

    header('Location: ' . url('pro.php?ok=' . $novo));
    exit;
}

if (isset($_GET['ok'])) {
    $mensagem = $_GET['ok'] === 'pro'
        ? 'Assinatura ativada. Bem-vindo ao Spark Pro.'
        : 'Assinatura cancelada. Você voltou para o plano gratuito.';
    $jaEhPro = $_GET['ok'] === 'pro';
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-tema="escuro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Spark Pro — mais alcance, mais conexão</title>
<link rel="icon" href="<?= e(logo_favicon()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..800&family=Inter:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= fontes_locais() ?><link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<style>
  body { padding: 0; }

  .capa {
    padding: 76px 24px 62px; text-align: center;
    position: relative;
  }
  .capa .marca__chama {
    animation: surge-modal .8s var(--mola), flutua 5s var(--suave) 1s infinite;
  }
  .capa h1 {
    font-family: var(--font-display); font-weight: 700;
    font-size: clamp(2.2rem, 5.2vw, 3.6rem); letter-spacing: -.035em; margin: 20px 0 14px;
    animation: surge-modal .8s var(--mola) .1s backwards;
  }
  .capa h1 em {
    font-style: normal;
    background: linear-gradient(110deg, var(--ember-hi), var(--ember) 45%, #ff7a00);
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .capa p {
    color: var(--fg-dim); font-size: 17px; max-width: 570px; margin: 0 auto;
    animation: surge-modal .8s var(--mola) .18s backwards;
  }

  .planos {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
    gap: 22px; max-width: 960px; margin: 0 auto; padding: 0 24px 64px;
  }

  .plano {
    border-radius: var(--r-xl); padding: 32px; position: relative;
    background: linear-gradient(150deg, var(--vidro-2), var(--vidro-1) 62%);
    backdrop-filter: blur(var(--blur)) saturate(var(--satura));
    -webkit-backdrop-filter: blur(var(--blur)) saturate(var(--satura));
    box-shadow: inset 0 1px 0 var(--borda-luz), var(--sombra-2);
    transition: transform var(--t-med) var(--suave), box-shadow var(--t-med) var(--suave);
    animation: surge-modal .8s var(--mola) backwards;
  }
  .plano:nth-child(1) { animation-delay: .26s; }
  .plano:nth-child(2) { animation-delay: .34s; }
  .plano:hover {
    transform: translateY(-6px);
    box-shadow: inset 0 1px 0 var(--borda-luz), var(--sombra-3);
  }

  .plano::before {
    content: '';
    position: absolute; inset: 0;
    border-radius: inherit; padding: 1px;
    background: linear-gradient(145deg,
        rgba(255,255,255,.5) 0%, rgba(255,255,255,.08) 28%,
        transparent 52%, rgba(255,255,255,.26) 100%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
            mask-composite: exclude;
    pointer-events: none;
  }

  .plano--destaque {
    background: linear-gradient(150deg, rgba(139,92,246,.16), var(--vidro-1) 65%);
    box-shadow: inset 0 1px 0 rgba(167,139,250,.4), 0 20px 60px var(--spark-glow);
  }
  .plano--destaque:hover {
    box-shadow: inset 0 1px 0 rgba(167,139,250,.55), 0 28px 80px rgba(139,92,246,.4);
  }

  .plano__fita {
    position: absolute; top: -13px; left: 50%; transform: translateX(-50%);
    background: linear-gradient(135deg, var(--spark-hi), var(--ember));
    color: #1a1200; font-size: 10.5px; font-weight: 800; letter-spacing: .12em;
    padding: 6px 16px; border-radius: var(--r-pill); font-family: var(--font-mono);
    white-space: nowrap;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.5), 0 6px 20px var(--spark-glow);
  }

  .plano h2 { font-family: var(--font-display); font-size: 21px; margin-bottom: 6px; position: relative; z-index: 2; }
  .plano__preco {
    font-family: var(--font-display); font-size: 42px; font-weight: 700;
    letter-spacing: -.035em; position: relative; z-index: 2;
  }
  .plano__preco small { font-size: 15px; color: var(--fg-mute); font-weight: 500; }

  .plano ul { margin: 24px 0; display: flex; flex-direction: column; gap: 12px; position: relative; z-index: 2; }
  .plano li {
    display: flex; gap: 12px; font-size: 14.5px; color: var(--fg-dim); align-items: flex-start;
    transition: transform var(--t-med) var(--suave), color var(--t-med);
  }
  .plano li:hover { transform: translateX(5px); color: var(--fg); }
  .plano li i { color: var(--ok); margin-top: 4px; font-size: 12px; flex-shrink: 0; }
  .plano li.nao { opacity: .55; }
  .plano li.nao i { color: var(--fg-mute); }
  .plano form, .plano > .btn { position: relative; z-index: 2; }

  .receitas { max-width: 960px; margin: 0 auto; padding: 0 24px 80px; }
  .receitas h2 { font-family: var(--font-display); font-size: 23px; margin-bottom: 8px; }
  .grade-receitas {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px; margin-top: 24px;
  }
  .voltar { position: fixed; top: 20px; left: 20px; z-index: 10; }

  @media (max-width: 720px) {
    .capa { padding: 84px 18px 48px; }
    .planos { padding: 0 16px 48px; }
    .plano { padding: 26px 22px; }
    .voltar { top: 14px; left: 14px; }
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


<a class="btn btn--linha btn--sm voltar" href="<?= url('index.php') ?>">
  <i class="fa-solid fa-arrow-left"></i> Voltar ao Spark
</a>

<header class="capa">
  <span class="marca__chama" style="margin:0 auto"><?= logo_marca() ?></span>
  <h1>Spark <em>Pro</em></h1>
  <p>Para quem vive de criar. Mais conexões, mais alcance e as ferramentas
     que aceleram o processo — sem nunca vender seus dados.</p>

  <?php if ($mensagem): ?>
    <div class="alerta alerta--ok" style="max-width:420px;margin:24px auto 0;justify-content:center">
      <i class="fa-solid fa-check"></i> <?= e($mensagem) ?>
    </div>
  <?php endif; ?>
</header>

<section class="planos">
  <div class="plano">
    <h2>Free</h2>
    <p style="color:var(--fg-mute);font-size:13.5px;margin-bottom:16px">Para começar e conhecer a rede</p>
    <div class="plano__preco">R$ 0<small>/mês</small></div>
    <ul>
      <li><i class="fa-solid fa-check"></i> Feed inteligente completo</li>
      <li><i class="fa-solid fa-check"></i> 3 conexões por dia</li>
      <li><i class="fa-solid fa-check"></i> Todos os fóruns artísticos</li>
      <li><i class="fa-solid fa-check"></i> Direct ilimitado</li>
      <li class="nao"><i class="fa-solid fa-minus"></i> Selo PRO no perfil</li>
      <li class="nao"><i class="fa-solid fa-minus"></i> Prioridade no Botão Conexão</li>
    </ul>
    <?php if ($jaEhPro): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn btn--linha btn--bloco" type="submit">Voltar para o Free</button>
      </form>
    <?php else: ?>
      <button class="btn btn--linha btn--bloco" disabled>Seu plano atual</button>
    <?php endif; ?>
  </div>

  <div class="plano plano--destaque">
    <span class="plano__fita">MAIS ESCOLHIDO</span>
    <h2>Pro</h2>
    <p style="color:var(--fg-mute);font-size:13.5px;margin-bottom:16px">Para quem cria todo dia</p>
    <div class="plano__preco">R$ 29,90<small>/mês</small></div>
    <ul>
      <li><i class="fa-solid fa-check"></i> Tudo do Free</li>
      <li><i class="fa-solid fa-check"></i> <b style="color:var(--fg)">Conexões ilimitadas</b></li>
      <li><i class="fa-solid fa-check"></i> Prioridade no Botão Conexão</li>
      <li><i class="fa-solid fa-check"></i> Selo PRO no perfil e nos posts</li>
      <li><i class="fa-solid fa-check"></i> Upload até 200 MB por arquivo</li>
      <li><i class="fa-solid fa-check"></i> Espaços exclusivos da comunidade</li>
      <li><i class="fa-solid fa-check"></i> Acesso antecipado a colaborações</li>
    </ul>
    <?php if ($jaEhPro): ?>
      <button class="btn btn--chama btn--bloco" disabled><i class="fa-solid fa-crown"></i> Você é Pro</button>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn btn--primario btn--bloco" type="submit">
          <i class="fa-solid fa-bolt"></i> Assinar o Pro
        </button>
      </form>
      <p style="text-align:center;color:var(--fg-mute);font-size:12px;margin-top:10px">
        Demonstração acadêmica — nenhuma cobrança é feita.
      </p>
    <?php endif; ?>
  </div>
</section>

<section class="receitas">
  <h2>Como o Spark se sustenta</h2>
  <p style="color:var(--fg-dim);font-size:15px">
    Três fontes de receita que crescem juntas — e nenhuma delas envolve vender dados pessoais.
  </p>

  <div class="grade-receitas">
    <div class="cartao" style="margin:0">
      <h3 class="cartao__titulo"><i class="fa-solid fa-crown"></i> Assinatura</h3>
      <p style="font-size:13.5px;color:var(--fg-dim)">
        O Spark Pro a R$ 29,90/mês, com recursos que importam para quem produz em ritmo profissional.
      </p>
    </div>

    <div class="cartao" style="margin:0">
      <h3 class="cartao__titulo"><i class="fa-solid fa-bullhorn"></i> Publicidade contextual</h3>
      <p style="font-size:13.5px;color:var(--fg-dim)">
        Marcas do universo criativo aparecem pelo <b style="color:var(--fg)">contexto de uso</b> —
        nunca por perfil pessoal. Em conformidade com a LGPD.
      </p>
    </div>

    <div class="cartao" style="margin:0">
      <h3 class="cartao__titulo"><i class="fa-solid fa-handshake"></i> Marketplace</h3>
      <p style="font-size:13.5px;color:var(--fg-dim)">
        Comissão sobre colaborações fechadas na plataforma: licença de samples,
        arte digital e contratação de freelancers.
      </p>
    </div>
  </div>
</section>

<script>
  document.documentElement.dataset.tema = localStorage.getItem('spark:tema') || 'escuro';
</script>
<script src="<?= asset('assets/js/vidro.js') ?>"></script>
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
