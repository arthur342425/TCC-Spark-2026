<?php
/**
 * SPARK — aplicação principal.
 * Tudo o que é dinâmico vem da API; aqui fica só o esqueleto e o
 * estado inicial do usuário, para a primeira pintura já sair certa.
 */

declare(strict_types=1);

require __DIR__ . '/config/config.php';
require __DIR__ . '/config/posts.php';

exigir_login_web();

$mim = usuario_atual();
if (!$mim) {
    header('Location: ' . url('login.php'));
    exit;
}

// marca presença — o Botão Conexão prioriza quem esteve por aqui
db()->prepare('UPDATE usuario SET ultimo_acesso = NOW() WHERE id_usuario = ?')
    ->execute([$mim['id_usuario']]);

$estados = estados();
$areas   = areas();
$meuEstado = $mim['estado_criativo'] ?: 'observando';
$foto = avatar_url($mim['foto_perfil_url']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-tema="escuro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#121214">
<title>Spark — quebre o bloqueio</title>
<meta name="description" content="A rede social feita por artistas, para artistas. Feed inteligente, Botão Conexão e fóruns para destravar o processo criativo.">

<link rel="icon" href="<?= e(logo_favicon()) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..800&family=Inter:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= fontes_locais() ?><link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body>

<!-- cortina de abertura: sai sozinha e destrava a rolagem -->
<div class="cortina" id="cortina" aria-hidden="true">
  <div class="cortina__marca"><?= logo_assinatura() ?></div>
  <div class="cortina__trilho"><i></i></div>
  <div class="cortina__nota">quebre o bloqueio</div>
</div>

<div class="app">

  <!-- ============ TRILHO LATERAL ============ -->
  <nav class="rail" aria-label="Navegação principal">
    <a class="marca" href="#/feed" aria-label="Spark, início">
      <?= logo_assinatura('assinatura--marca') ?>
    </a>

    <!-- filtro que funde as gotas: é ele que dá o comportamento líquido.
         O desfoque borra as formas e a matriz corta o meio-tom, então
         duas manchas próximas viram uma só, com pescoço. -->
    <svg class="goo-defs" aria-hidden="true" focusable="false">
      <defs>
        <!-- região explícita e sRGB: sem isso o Chrome calcula o filtro
             numa área maior e ainda converte para linearRGB no caminho,
             o que custa caro e desbota o violeta -->
        <filter id="goo-liquido"
                x="-30%" y="-15%" width="160%" height="130%"
                color-interpolation-filters="sRGB">
          <feGaussianBlur in="SourceGraphic" stdDeviation="7" result="borrado"/>
          <feColorMatrix in="borrado" mode="matrix"
            values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -9" result="goo"/>
        </filter>
      </defs>
    </svg>

    <div class="rail__nav">
      <!-- as gotas vivem atrás dos itens e seguem o ativo -->
      <span class="rail__liquido" aria-hidden="true">
        <i class="gota gota--rastro"></i>
        <i class="gota gota--frente"></i>
      </span>

      <a class="rail__item ativo" href="#/feed"     data-rota="feed"><i class="fa-solid fa-house"></i><span>Início</span></a>
      <a class="rail__item" href="#/explorar"       data-rota="explorar"><i class="fa-solid fa-compass"></i><span>Explorar</span></a>
      <a class="rail__item" href="#/forum"          data-rota="forum"><i class="fa-solid fa-comments"></i><span>Fóruns</span></a>
      <a class="rail__item" href="#/direct"         data-rota="direct">
        <i class="fa-solid fa-paper-plane"></i><span>Direct</span>
        <em class="rail__badge hidden" id="badge-direct">0</em>
      </a>
      <a class="rail__item" href="#/notificacoes"   data-rota="notificacoes">
        <i class="fa-solid fa-bell"></i><span>Notificações</span>
        <em class="rail__badge hidden" id="badge-notif">0</em>
      </a>
      <a class="rail__item" href="#/criar"          data-rota="criar"><i class="fa-solid fa-square-plus"></i><span>Publicar</span></a>
    </div>

    <button class="btn-conexao iris-depois" id="btn-conexao" title="Conecte-se com um artista no seu momento criativo">
      <i class="fa-solid fa-bolt"></i><span>Botão Conexão</span>
    </button>

    <div class="rail__rodape">
      <div class="rail__sep"></div>
      <a class="rail__item" href="#/perfil" data-rota="perfil">
        <span class="avatar-anel" data-estado="<?= e($meuEstado) ?>">
          <span class="avatar avatar--xs">
            <?php if ($foto): ?><img src="<?= e($foto) ?>" alt=""><?php else: ?><?= e(iniciais($mim['nome_usuario'])) ?><?php endif; ?>
          </span>
        </span>
        <span>Perfil</span>
      </a>
      <a class="rail__item" href="#/config" data-rota="config"><i class="fa-solid fa-gear"></i><span>Configurações</span></a>
      <a class="rail__item" href="<?= url('logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sair</span></a>
    </div>
  </nav>

  <!-- ============ PRINCIPAL ============ -->
  <div class="principal">

    <header class="topo">
      <div class="busca">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" id="busca" placeholder="Buscar artistas, posts, #tags..." autocomplete="off" aria-label="Buscar">
        <div class="busca__resultados hidden" id="busca-resultados"></div>
      </div>

      <div class="topo__acoes">
        <button class="estado-select" id="abrir-estado" title="Seu momento criativo agora">
          <i class="ponto ponto-<?= e($meuEstado) ?>"></i>
          <span id="estado-rotulo"><?= e($estados[$meuEstado]['rotulo']) ?></span>
          <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--fg-mute)"></i>
        </button>

        <button class="btn btn--icone" id="alternar-tema" title="Alternar tema" aria-label="Alternar tema">
          <i class="fa-solid fa-moon"></i>
        </button>

        <a class="btn btn--primario btn--sm" href="#/criar"><i class="fa-solid fa-plus"></i> Publicar</a>
      </div>
    </header>

    <!-- ---------- FEED ---------- -->
    <section class="pagina ativa" id="pg-feed">
      <div class="colunas">
        <div>
          <h1 class="titulo-pagina">Seu feed</h1>
          <p class="sub-pagina">Conteúdo escolhido pelo que você cria, curte e segue.</p>

          <div class="abas" role="tablist">
            <button class="aba ativa" data-modo="paravoce">Para você</button>
            <button class="aba" data-modo="seguindo">Seguindo</button>
            <button class="aba" data-modo="recentes">Recentes</button>
          </div>

          <div id="filtro-tag" class="hidden" style="margin-bottom:16px"></div>
          <div class="feed" id="feed"></div>
          <div id="feed-fim" style="height:1px"></div>
        </div>

        <aside class="lateral-direita">
          <div class="cartao">
            <h3 class="cartao__titulo"><i class="fa-solid fa-bolt"></i> Seu momento</h3>
            <p style="font-size:13.5px;color:var(--fg-dim);margin-bottom:14px" id="resumo-estado"></p>
            <button class="btn btn--linha btn--bloco btn--sm" id="mudar-estado-lateral">Mudar meu estado</button>
          </div>

          <div class="cartao" id="cartao-artistas">
            <h3 class="cartao__titulo"><i class="fa-solid fa-user-plus"></i> Artistas para seguir</h3>
            <div id="sugestoes-artistas"><div class="esqueleto" style="height:120px"></div></div>
          </div>

          <div class="cartao" id="cartao-tags">
            <h3 class="cartao__titulo"><i class="fa-solid fa-fire"></i> Em alta agora</h3>
            <div id="tags-alta"></div>
          </div>

          <div class="cartao patrocinado">
            <span class="rotulo">Patrocinado · contextual</span>
            <h3 class="cartao__titulo" style="margin-top:10px"><i class="fa-solid fa-sliders"></i> Studio One 6</h3>
            <p style="font-size:13px;color:var(--fg-dim);margin-bottom:12px">
              30% off para produtores independentes. Anúncio escolhido pelo contexto da sua área — sem usar dados pessoais.
            </p>
            <button class="btn btn--linha btn--sm btn--bloco">Ver oferta</button>
          </div>
        </aside>
      </div>
    </section>

    <!-- ---------- EXPLORAR ---------- -->
    <section class="pagina" id="pg-explorar">
      <h1 class="titulo-pagina">Explorar</h1>
      <p class="sub-pagina">Descubra referências, temas e gente nova para destravar.</p>

      <h2 class="rotulo" style="margin:22px 0 12px">Temas em alta</h2>
      <div class="grade-explorar" id="explorar-tags"></div>

      <h2 class="rotulo" style="margin:32px 0 12px">Artistas para conhecer</h2>
      <div class="grade-explorar" id="explorar-artistas"></div>

      <h2 class="rotulo" style="margin:32px 0 12px">Publicações recentes</h2>
      <div class="grade-mosaico" id="explorar-mosaico"></div>
    </section>

    <!-- ---------- FÓRUNS ---------- -->
    <section class="pagina" id="pg-forum">
      <div id="forum-conteudo"></div>
    </section>

    <!-- ---------- DIRECT ---------- -->
    <section class="pagina" id="pg-direct">
      <div class="direct">
        <div class="direct__lista" id="direct-lista">
          <div class="direct__cabeca">
            <h2>Direct</h2>
            <div class="busca" style="max-width:none">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="search" id="direct-busca" placeholder="Procurar artista..." autocomplete="off">
              <div class="busca__resultados hidden" id="direct-busca-resultados"></div>
            </div>
          </div>
          <div class="direct__rolagem" id="direct-conversas"></div>
        </div>

        <div class="direct__chat" id="direct-chat">
          <div class="direct__vazio" id="direct-vazio">
            <div>
              <i class="fa-regular fa-paper-plane"></i>
              <h3 style="font-family:var(--font-display);color:var(--fg-dim);margin-bottom:6px">Suas conversas</h3>
              <p style="font-size:14px">Escolha uma conversa ou use o Botão Conexão para conhecer alguém novo.</p>
            </div>
          </div>

          <div class="hidden" id="direct-ativo" style="display:flex;flex-direction:column;min-height:0;flex:1">
            <div class="direct__chat-topo">
              <button class="btn btn--icone hidden" id="direct-voltar" style="margin-left:-8px"><i class="fa-solid fa-arrow-left"></i></button>
              <span class="avatar-anel" id="chat-anel" data-estado="observando">
                <span class="avatar avatar--sm" id="chat-avatar">?</span>
              </span>
              <div style="min-width:0;flex:1">
                <div style="font-weight:600;font-size:14.5px" id="chat-nome">—</div>
                <div class="rotulo" id="chat-estado"></div>
              </div>
              <a class="btn btn--linha btn--sm" id="chat-ver-perfil">Ver perfil</a>
            </div>

            <div class="direct__msgs" id="direct-mensagens"></div>

            <form class="direct__enviar" id="form-mensagem">
              <input type="text" id="msg-texto" placeholder="Escreva uma mensagem..." autocomplete="off" maxlength="2000">
              <button class="btn btn--primario btn--icone" type="submit" aria-label="Enviar">
                <i class="fa-solid fa-paper-plane"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>

    <!-- ---------- NOTIFICAÇÕES ---------- -->
    <section class="pagina" id="pg-notificacoes">
      <div style="max-width:720px;margin:0 auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
          <div>
            <h1 class="titulo-pagina">Notificações</h1>
            <p class="sub-pagina" style="margin:0">O que rolou enquanto você criava.</p>
          </div>
          <button class="btn btn--linha btn--sm" id="marcar-lidas">Marcar como lidas</button>
        </div>
        <div id="lista-notificacoes"></div>
      </div>
    </section>

    <!-- ---------- CRIAR ---------- -->
    <section class="pagina" id="pg-criar">
      <div style="max-width:660px;margin:0 auto">
        <h1 class="titulo-pagina">Publicar</h1>
        <p class="sub-pagina">Solte o processo, não só o resultado. Use #tags para o feed entender seu estilo.</p>

        <form id="form-post">
          <div class="campo">
            <label for="post-titulo">Título</label>
            <input type="text" id="post-titulo" maxlength="255" placeholder="Ex: Loop de rhodes que fiz às 3 da manhã">
          </div>

          <div class="campo">
            <label for="post-desc">Descrição</label>
            <textarea id="post-desc" maxlength="1000" placeholder="Conte o processo, o que travou, o que funcionou... use #sample #beat #processo"></textarea>
            <p class="campo__dica">Cada #palavra vira uma tag e ensina o algoritmo sobre o seu gosto.</p>
          </div>

          <div class="campo">
            <label>Arquivo (opcional)</label>
            <input type="file" id="post-arquivo" class="hidden"
                   accept="image/*,video/mp4,video/webm,audio/mpeg,audio/wav,audio/ogg,application/pdf">
            <div class="area-solta" id="area-solta">
              <i class="fa-solid fa-cloud-arrow-up"></i>
              <p id="area-texto">Arraste aqui ou clique para escolher</p>
              <small style="color:var(--fg-mute);font-size:12px">Imagem, vídeo, áudio ou PDF</small>
              <img class="area-solta__previa hidden" id="area-previa" alt="">
            </div>
          </div>

          <div id="post-alerta"></div>

          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
            <button type="button" class="btn btn--linha" id="post-limpar">Limpar</button>
            <button type="submit" class="btn btn--primario" id="post-enviar">
              <i class="fa-solid fa-bolt"></i> Publicar
            </button>
          </div>
        </form>
      </div>
    </section>

    <!-- ---------- PERFIL ---------- -->
    <section class="pagina" id="pg-perfil">
      <div id="perfil-conteudo"></div>
    </section>

    <!-- ---------- CONFIGURAÇÕES ---------- -->
    <section class="pagina" id="pg-config">
      <div style="max-width:660px;margin:0 auto">
        <h1 class="titulo-pagina">Configurações</h1>
        <p class="sub-pagina">Quanto melhor seu perfil, melhor o feed e as conexões.</p>

        <div class="cartao">
          <h3 class="cartao__titulo"><i class="fa-solid fa-image"></i> Foto de perfil</h3>
          <div style="display:flex;align-items:center;gap:18px">
            <span class="avatar avatar--lg" id="config-avatar">
              <?php if ($foto): ?><img src="<?= e($foto) ?>" alt=""><?php else: ?><?= e(iniciais($mim['nome_usuario'])) ?><?php endif; ?>
            </span>
            <div>
              <input type="file" id="config-foto" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp">
              <button class="btn btn--linha btn--sm" id="btn-trocar-foto">Trocar foto</button>
              <p class="campo__dica">JPG, PNG, GIF ou WEBP até 5 MB.</p>
            </div>
          </div>
        </div>

        <form class="cartao" id="form-perfil">
          <h3 class="cartao__titulo"><i class="fa-solid fa-user-pen"></i> Perfil de artista</h3>

          <div class="campo">
            <label for="cfg-nome">Nome de exibição</label>
            <input type="text" id="cfg-nome" maxlength="100" value="<?= e($mim['nome_exibicao'] ?? '') ?>" placeholder="Como quer ser chamado">
          </div>

          <div class="campo">
            <label for="cfg-usuario">Seu @</label>
            <input type="text" id="cfg-usuario" maxlength="50" value="<?= e($mim['nome_usuario']) ?>">
          </div>

          <div class="campo">
            <label for="cfg-bio">Bio</label>
            <textarea id="cfg-bio" maxlength="500" placeholder="Em uma frase: o que você cria?"><?= e($mim['bio'] ?? '') ?></textarea>
          </div>

          <div class="campo">
            <label for="cfg-area">Área criativa</label>
            <select id="cfg-area">
              <?php foreach ($areas as $chave => $rotulo): ?>
                <option value="<?= e($chave) ?>" <?= ($mim['area_criativa'] ?? '') === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="campo__dica">O feed usa isso para te mostrar o que faz sentido no seu ofício.</p>
          </div>

          <div class="campo">
            <label for="cfg-ferramenta">Instrumento / ferramenta principal</label>
            <input type="text" id="cfg-ferramenta" maxlength="80" value="<?= e($mim['ferramenta'] ?? '') ?>" placeholder="Ex: FL Studio, guitarra, Procreate, Figma">
          </div>

          <div class="campo">
            <label for="cfg-cidade">Cidade</label>
            <input type="text" id="cfg-cidade" maxlength="80" value="<?= e($mim['cidade'] ?? '') ?>" placeholder="Ex: São Paulo">
          </div>

          <div id="perfil-alerta"></div>
          <button class="btn btn--primario" type="submit">Salvar alterações</button>
        </form>

        <form class="cartao" id="form-senha">
          <h3 class="cartao__titulo"><i class="fa-solid fa-lock"></i> Senha</h3>
          <div class="campo">
            <label for="cfg-senha-atual">Senha atual</label>
            <input type="password" id="cfg-senha-atual" autocomplete="current-password">
          </div>
          <div class="campo">
            <label for="cfg-senha-nova">Nova senha</label>
            <input type="password" id="cfg-senha-nova" autocomplete="new-password">
          </div>
          <div id="senha-alerta"></div>
          <button class="btn btn--linha" type="submit">Alterar senha</button>
        </form>

        <div class="cartao">
          <span class="rotulo">Plano atual</span>
          <h3 class="cartao__titulo" style="margin-top:10px">
            <i class="fa-solid fa-crown"></i>
            <?= ($mim['plano'] ?? 'free') === 'pro' ? 'Spark Pro' : 'Spark Free' ?>
          </h3>
          <p style="font-size:13.5px;color:var(--fg-dim);margin-bottom:14px">
            O Pro dá conexões ilimitadas, feed com mais alcance e espaços exclusivos da comunidade.
          </p>
          <a class="btn btn--chama btn--sm" href="<?= url('pro.php') ?>">Conhecer o Spark Pro</a>
        </div>

        <div class="cartao">
          <span class="rotulo">Sobre esta instalação</span>
          <p style="font-size:13.5px;color:var(--fg-dim);margin:12px 0 14px">
            Se a tela parecer desatualizada, confira estes dois valores: eles dizem
            exatamente qual cópia do projeto respondeu.
          </p>
          <div style="display:flex;flex-direction:column;gap:8px;font-family:var(--font-mono);font-size:12.5px">
            <div style="display:flex;justify-content:space-between;gap:12px">
              <span style="color:var(--fg-mute)">código de</span>
              <b><?= e(versao_build()) ?></b>
            </div>
            <div style="display:flex;justify-content:space-between;gap:12px">
              <span style="color:var(--fg-mute)">pasta</span>
              <b>/<?= e(pasta_do_app()) ?>/</b>
            </div>
          </div>
        </div>
      </div>
    </section>

  </div>
</div>

<button class="conexao-flutuante iris-antes" id="conexao-mobile" aria-label="Botão Conexão">
  <i class="fa-solid fa-bolt"></i>
</button>

<div class="toasts" id="toasts" role="status" aria-live="polite"></div>
<div id="modais"></div>

<script>
window.SPARK = {
  base: <?= json_encode(url('')) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  eu: {
    id:     <?= (int) $mim['id_usuario'] ?>,
    usuario: <?= json_encode($mim['nome_usuario']) ?>,
    nome:   <?= json_encode($mim['nome_exibicao'] ?: $mim['nome_usuario']) ?>,
    foto:   <?= json_encode($foto) ?>,
    area:   <?= json_encode($mim['area_criativa'] ?? 'outro') ?>,
    estado: <?= json_encode($meuEstado) ?>,
    pro:    <?= ($mim['plano'] ?? 'free') === 'pro' ? 'true' : 'false' ?>
  },
  estados: <?= json_encode($estados, JSON_UNESCAPED_UNICODE) ?>,
  areas:   <?= json_encode($areas, JSON_UNESCAPED_UNICODE) ?>
};
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

<script src="<?= asset('assets/js/vidro.js') ?>"></script>
<script src="<?= asset('assets/js/audio.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script type="module" src="<?= asset('assets/js/movimento.js') ?>"></script>
</body>
</html>
