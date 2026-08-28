/* ============================================================
   SPARK — aplicação
   Roteamento por hash, renderização por template e uma única
   camada de acesso à API. Sem framework: o TCC precisa ser lido.
   ============================================================ */
'use strict';

const S = window.SPARK;

/* ---------- atalhos ---------- */
const $  = (sel, raiz = document) => raiz.querySelector(sel);
const $$ = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

const esc = (txt) => String(txt ?? '').replace(/[&<>"']/g,
  (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** Deixa #tags e @menções clicáveis sem abrir brecha de HTML. */
function texto_rico(txt) {
  return esc(txt)
    .replace(/#([\p{L}\p{N}_]{2,30})/gu, '<a class="tag" href="#/tag/$1">#$1</a>')
    .replace(/@([A-Za-z0-9_.]{2,50})/g, '<a class="tag" href="#/u/$1">@$1</a>');
}

/* ---------- API ---------- */
async function api(rota, opcoes = {}) {
  const cfg = { headers: {}, ...opcoes };

  if (cfg.body instanceof FormData) {
    cfg.method = cfg.method || 'POST';
    cfg.body.append('csrf', S.csrf);
  } else if (cfg.dados) {
    cfg.method = 'POST';
    const fd = new FormData();
    Object.entries(cfg.dados).forEach(([k, v]) => fd.append(k, v));
    fd.append('csrf', S.csrf);
    cfg.body = fd;
    delete cfg.dados;
  }

  const res = await fetch(`${S.base}api/${rota}`, cfg);

  let dados;
  try {
    dados = await res.json();
  } catch {
    throw new Error('O servidor respondeu algo inesperado.');
  }

  if (res.status === 401) { location.href = `${S.base}login.php`; throw new Error('Sessão encerrada.'); }
  if (!res.ok || !dados.ok) throw new Error(dados.erro || 'Algo deu errado.');

  return dados;
}

/* ---------- toast ---------- */
function toast(msg, tipo = '') {
  const el = document.createElement('div');
  el.className = 'toast' + (tipo ? ` toast--${tipo}` : '');
  el.textContent = msg;
  $('#toasts').appendChild(el);
  setTimeout(() => {
    el.style.transition = 'opacity .3s, transform .3s';
    el.style.opacity = '0';
    el.style.transform = 'translateX(30px)';
    setTimeout(() => el.remove(), 300);
  }, 3600);
}

// o player de áudio vive em outro arquivo e precisa avisar o usuário
window.SparkToast = toast;

/* ---------- modal ---------- */
function modal(html, { largura = '520px' } = {}) {
  const fundo = document.createElement('div');
  fundo.className = 'modal-fundo';
  fundo.innerHTML = `<div class="modal espectral" style="max-width:${largura}">${html}</div>`;

  const fechar = () => {
    fundo.style.opacity = '0';
    setTimeout(() => fundo.remove(), 180);
    document.removeEventListener('keydown', aoTeclar);
  };
  const aoTeclar = (ev) => { if (ev.key === 'Escape') fechar(); };

  fundo.addEventListener('click', (ev) => { if (ev.target === fundo) fechar(); });
  document.addEventListener('keydown', aoTeclar);
  $('#modais').appendChild(fundo);

  $$('[data-fechar]', fundo).forEach((b) => b.addEventListener('click', fechar));
  return { el: fundo, fechar };
}

/* ---------- pedaços reaproveitados ---------- */
function avatar(pessoa, tamanho = 'sm') {
  const inicial = esc((pessoa.nome || pessoa.usuario || '?').trim().charAt(0).toUpperCase());
  const dentro = pessoa.foto
    ? `<img src="${esc(pessoa.foto)}" alt="">`
    : inicial;
  return `<span class="avatar-anel" data-estado="${esc(pessoa.estado || 'observando')}">
            <span class="avatar avatar--${tamanho}">${dentro}</span>
          </span>`;
}

const selo = (pro) => pro ? '<span class="selo-pro">PRO</span>' : '';

function vazio(icone, titulo, texto, botao = '') {
  return `<div class="vazio">
    <i class="fa-solid ${icone}"></i>
    <h3>${esc(titulo)}</h3>
    <p>${esc(texto)}</p>
    ${botao}
  </div>`;
}

const esqueletos = (n = 3) =>
  Array.from({ length: n }, () => '<div class="esqueleto esqueleto--post"></div>').join('');

/* ============================================================
   ROTEADOR
   ============================================================ */
const rotas = {
  feed:          () => carregarFeed(true),
  explorar:      carregarExplorar,
  forum:         carregarForum,
  direct:        carregarDirect,
  notificacoes:  carregarNotificacoes,
  criar:         () => $('#post-titulo')?.focus(),
  perfil:        () => carregarPerfil(),
  config:        () => {},
};

function irPara(rota) {
  $$('.pagina').forEach((p) => p.classList.remove('ativa'));
  $(`#pg-${rota}`)?.classList.add('ativa');

  $$('.rail__item').forEach((a) => a.classList.toggle('ativo', a.dataset.rota === rota));

  window.scrollTo({ top: 0, behavior: 'instant' });
}

function rotear() {
  const hash = location.hash.slice(2) || 'feed';
  const [nome, ...resto] = hash.split('/');

  if (nome === 'tag') {
    irPara('feed');
    return carregarFeed(true, { tag: resto[0] });
  }
  if (nome === 'u') {
    irPara('perfil');
    return carregarPerfil({ usuario: resto[0] });
  }
  if (nome === 'perfil' && resto[0]) {
    irPara('perfil');
    return carregarPerfil({ id: resto[0] });
  }
  if (nome === 'forum' && resto.length) {
    irPara('forum');
    return resto[0] === 't' ? abrirTopico(resto[1]) : abrirCategoria(resto[0]);
  }
  if (nome === 'post' && resto[0]) {
    irPara('feed');
    return abrirPost(resto[0]);
  }

  const rota = rotas[nome] ? nome : 'feed';
  irPara(rota);
  rotas[rota]();
}

window.addEventListener('hashchange', rotear);

/* ============================================================
   FEED
   ============================================================ */
const feed = { modo: 'paravoce', pagina: 0, fim: false, carregando: false, tag: '' };

function cartaoPost(p) {
  let midia = '';

  if (p.tipo === 'imagem' && p.url) {
    midia = `<div class="post__midia"><img src="${esc(p.url)}" alt="${esc(p.titulo || '')}" loading="lazy"></div>`;

  } else if (p.tipo === 'video' && p.url) {
    midia = `<div class="post__midia"><video src="${esc(p.url)}" controls preload="metadata"></video></div>`;

  } else if (p.tipo === 'audio' && p.url) {
    // a onda é desenhada em canvas por assets/js/audio.js
    /* O anel espectral vai num elemento próprio: os dois pseudos da
       .faixa já são o brilho que reage ao áudio. */
    midia = `<div class="faixa" data-audio="${esc(p.url)}">
        <span class="faixa__anel iris-antes" aria-hidden="true"></span>
        <span class="faixa__bpm"></span>
        <span class="faixa__selo">no ar</span>

        <div class="faixa__comando">
          <button class="faixa__pular espectral" data-pular="-10" aria-label="Voltar 10 segundos">
            <i class="fa-solid fa-rotate-left"></i>
          </button>
          <button class="faixa__play" aria-label="Tocar"><i class="fa-solid fa-play"></i></button>
          <button class="faixa__pular espectral" data-pular="10" aria-label="Avançar 10 segundos">
            <i class="fa-solid fa-rotate-right"></i>
          </button>
        </div>

        <div class="faixa__onda"><canvas></canvas></div>

        <div class="faixa__volume espectral" data-volume="80" role="slider"
             aria-label="Volume" aria-valuemin="0" aria-valuemax="100" aria-valuenow="80" tabindex="0">
          <span class="faixa__volume-miolo" style="width:80%"></span>
          <i class="fa-solid fa-volume-high"></i>
          <span class="faixa__volume-num">80</span>
        </div>

        <span class="faixa__tempo">0:00 / --:--</span>
      </div>`;

  } else if (p.tipo === 'documento' && p.url) {
    midia = `<div class="doc">
        <div class="doc__icone"><i class="fa-solid fa-file-pdf"></i></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:14px">${esc(p.titulo || 'Documento')}</div>
          <div class="rotulo">PDF</div>
        </div>
        <a class="btn btn--linha btn--sm" href="${esc(p.url)}" target="_blank" rel="noopener">Abrir</a>
      </div>`;
  }

  const souDono = p.autor.id === S.eu.id;

  return `<article class="post" data-post="${p.id}">
    <header class="post__topo">
      <a href="#/perfil/${p.autor.id}">${avatar(p.autor, 'md')}</a>
      <div class="post__autor">
        <div class="post__nome">
          <a href="#/perfil/${p.autor.id}">${esc(p.autor.nome)}</a>${selo(p.autor.pro)}
        </div>
        <div class="post__meta">
          <span>@${esc(p.autor.usuario)}</span><span>·</span><span>${esc(p.quando)}</span>
        </div>
      </div>
      ${souDono
        ? `<button class="btn btn--icone" data-apagar="${p.id}" title="Apagar"><i class="fa-solid fa-trash"></i></button>`
        : `<button class="btn btn--linha btn--sm" data-seguir="${p.autor.id}">${p.eu_sigo ? 'Seguindo' : 'Seguir'}</button>`}
    </header>

    ${(p.titulo || p.descricao) ? `<div class="post__corpo">
      ${p.titulo ? `<h2 class="post__titulo">${esc(p.titulo)}</h2>` : ''}
      ${p.descricao ? `<p class="post__texto">${texto_rico(p.descricao)}</p>` : ''}
      ${p.tags.length ? `<div class="tags">${p.tags.map((t) => `<a class="tag" href="#/tag/${esc(t)}">#${esc(t)}</a>`).join('')}</div>` : ''}
    </div>` : ''}

    ${midia}

    <footer class="post__acoes">
      <button class="acao ${p.eu_curti ? 'curtido' : ''}" data-curtir="${p.id}">
        <i class="fa-${p.eu_curti ? 'solid' : 'regular'} fa-heart"></i>
        <span>${p.curtidas}</span>
      </button>
      <button class="acao" data-comentarios="${p.id}">
        <i class="fa-regular fa-comment"></i><span>${p.comentarios}</span>
      </button>
      <button class="acao ${p.eu_salvei ? 'salvo' : ''}" data-salvar="${p.id}">
        <i class="fa-${p.eu_salvei ? 'solid' : 'regular'} fa-bookmark"></i>
        <span>${p.eu_salvei ? 'Salvo' : 'Salvar'}</span>
      </button>
      <button class="acao" style="margin-left:auto" data-conectar="${p.autor.id}" title="Conectar com este artista">
        <i class="fa-solid fa-bolt"></i>
      </button>
    </footer>

    <div class="comentarios hidden" data-caixa="${p.id}"></div>
  </article>`;
}

async function carregarFeed(reiniciar = false, { tag = '' } = {}) {
  const grade = $('#feed');

  if (reiniciar) {
    feed.pagina = 0;
    feed.fim = false;
    feed.tag = tag;
    grade.innerHTML = esqueletos(3);

    const caixa = $('#filtro-tag');
    if (tag) {
      caixa.classList.remove('hidden');
      caixa.innerHTML = `<div style="display:flex;align-items:center;gap:10px">
          <span class="chip"><i class="fa-solid fa-hashtag"></i> ${esc(tag)}</span>
          <a class="btn btn--fantasma btn--sm" href="#/feed">Limpar filtro</a>
        </div>`;
    } else {
      caixa.classList.add('hidden');
      caixa.innerHTML = '';
    }
  }

  if (feed.carregando || feed.fim) return;
  feed.carregando = true;

  try {
    const params = new URLSearchParams({ modo: feed.modo, pagina: feed.pagina });
    if (feed.tag) params.set('tag', feed.tag);

    const dados = await api(`feed.php?${params}`);
    if (reiniciar) grade.innerHTML = '';

    if (!dados.posts.length && !grade.children.length) {
      grade.innerHTML = feed.modo === 'seguindo'
        ? vazio('fa-user-group', 'Você ainda não segue ninguém',
                'Vá ao Explorar e siga artistas, ou use o Botão Conexão.',
                '<a class="btn btn--primario btn--sm" href="#/explorar">Explorar artistas</a>')
        : vazio('fa-bolt', 'O feed está vazio',
                'Publique a primeira faísca e comece a alimentar o algoritmo.',
                '<a class="btn btn--primario btn--sm" href="#/criar">Publicar agora</a>');
    } else {
      grade.insertAdjacentHTML('beforeend', dados.posts.map(cartaoPost).join(''));
    }

    feed.pagina++;
    feed.fim = dados.fim;

  } catch (erro) {
    toast(erro.message, 'erro');
    if (reiniciar) grade.innerHTML = vazio('fa-triangle-exclamation', 'Não deu para carregar', erro.message);
  } finally {
    feed.carregando = false;
  }
}

$$('.aba').forEach((aba) => aba.addEventListener('click', () => {
  $$('.aba').forEach((a) => a.classList.remove('ativa'));
  aba.classList.add('ativa');
  feed.modo = aba.dataset.modo;
  carregarFeed(true, { tag: feed.tag });
}));

// rolagem infinita
new IntersectionObserver((entradas) => {
  if (entradas[0].isIntersecting && $('#pg-feed').classList.contains('ativa')) {
    carregarFeed(false, { tag: feed.tag });
  }
}, { rootMargin: '600px' }).observe($('#feed-fim'));

/* ============================================================
   AÇÕES DOS POSTS (delegação — funciona para o que ainda nem existe)
   ============================================================ */
document.addEventListener('click', async (ev) => {
  const alvo = (attr) => ev.target.closest(`[${attr}]`);

  /* --- curtir --- */
  const bCurtir = alvo('data-curtir');
  if (bCurtir) {
    const id = bCurtir.dataset.curtir;
    try {
      const r = await api('interagir.php', { dados: { acao: 'curtir', id } });
      bCurtir.classList.toggle('curtido', r.curtiu);
      $('i', bCurtir).className = `fa-${r.curtiu ? 'solid' : 'regular'} fa-heart`;
      $('span', bCurtir).textContent = r.total;
    } catch (e) { toast(e.message, 'erro'); }
    return;
  }

  /* --- salvar --- */
  const bSalvar = alvo('data-salvar');
  if (bSalvar) {
    try {
      const r = await api('interagir.php', { dados: { acao: 'salvar', id: bSalvar.dataset.salvar } });
      bSalvar.classList.toggle('salvo', r.salvo);
      $('i', bSalvar).className = `fa-${r.salvo ? 'solid' : 'regular'} fa-bookmark`;
      $('span', bSalvar).textContent = r.salvo ? 'Salvo' : 'Salvar';
      toast(r.salvo ? 'Guardado nas suas referências.' : 'Removido dos salvos.', 'ok');
    } catch (e) { toast(e.message, 'erro'); }
    return;
  }

  /* --- seguir --- */
  const bSeguir = alvo('data-seguir');
  if (bSeguir) {
    try {
      const r = await api('interagir.php', { dados: { acao: 'seguir', id: bSeguir.dataset.seguir } });
      $$(`[data-seguir="${bSeguir.dataset.seguir}"]`).forEach((b) => {
        b.textContent = r.seguindo ? 'Seguindo' : 'Seguir';
      });
    } catch (e) { toast(e.message, 'erro'); }
    return;
  }

  /* --- apagar post --- */
  const bApagar = alvo('data-apagar');
  if (bApagar) {
    const id = bApagar.dataset.apagar;
    const m = modal(`
      <div class="modal__topo"><h2 class="modal__titulo">Apagar publicação?</h2></div>
      <div class="modal__corpo"><p style="color:var(--fg-dim)">
        Isso remove o post, os comentários e as curtidas. Não dá para desfazer.</p></div>
      <div class="modal__rodape">
        <button class="btn btn--linha" data-fechar>Cancelar</button>
        <button class="btn btn--primario" id="confirmar-apagar" style="background:var(--danger)">Apagar</button>
      </div>`, { largura: '420px' });

    $('#confirmar-apagar', m.el).addEventListener('click', async () => {
      try {
        await api('post.php?acao=apagar', { dados: { id } });
        $(`[data-post="${id}"]`)?.remove();
        toast('Publicação apagada.', 'ok');
        m.fechar();
      } catch (e) { toast(e.message, 'erro'); }
    });
    return;
  }

  /* --- comentários --- */
  const bComent = alvo('data-comentarios');
  if (bComent) {
    const id = bComent.dataset.comentarios;
    const caixa = $(`[data-caixa="${id}"]`);
    if (!caixa) return;

    if (!caixa.classList.contains('hidden')) { caixa.classList.add('hidden'); return; }

    caixa.classList.remove('hidden');
    caixa.innerHTML = '<p class="rotulo">Carregando...</p>';

    try {
      const r = await api(`comentarios.php?id=${id}`);
      caixa.innerHTML = `
        <div data-lista-coment="${id}">
          ${r.comentarios.length
            ? r.comentarios.map(linhaComentario).join('')
            : '<p style="color:var(--fg-mute);font-size:13.5px">Ninguém comentou ainda. Comece a conversa.</p>'}
        </div>
        <form class="comentar-linha" data-form-coment="${id}">
          ${avatar(S.eu, 'xs')}
          <input type="text" placeholder="Escreva um comentário..." maxlength="1000" required>
          <button class="btn btn--primario btn--sm" type="submit">Enviar</button>
        </form>`;
    } catch (e) {
      caixa.innerHTML = `<p style="color:var(--danger);font-size:13.5px">${esc(e.message)}</p>`;
    }
    return;
  }

  /* --- apagar comentário --- */
  const bDelC = alvo('data-apagar-coment');
  if (bDelC) {
    try {
      await api('comentarios.php', { dados: { acao: 'apagar', id: bDelC.dataset.apagarComent } });
      bDelC.closest('.comentario').remove();
    } catch (e) { toast(e.message, 'erro'); }
    return;
  }

  /* --- conectar direto com alguém --- */
  const bConectar = alvo('data-conectar');
  if (bConectar) {
    conectarCom(Number(bConectar.dataset.conectar));
    return;
  }

  /* o player de áudio trata os próprios cliques em assets/js/audio.js */
});

function linhaComentario(c) {
  return `<div class="comentario">
    <a href="#/perfil/${c.autor.id}">${avatar(c.autor, 'xs')}</a>
    <div class="comentario__bolha">
      <div style="display:flex;align-items:center;gap:8px">
        <a class="comentario__nome" href="#/perfil/${c.autor.id}">${esc(c.autor.nome)}</a>
        ${selo(c.autor.pro)}
        <span class="comentario__hora">${esc(c.quando)}</span>
        ${c.posso_apagar
          ? `<button class="btn btn--fantasma btn--sm" style="margin-left:auto;padding:2px 6px"
                     data-apagar-coment="${c.id}" title="Apagar"><i class="fa-solid fa-xmark"></i></button>`
          : ''}
      </div>
      <div class="comentario__texto">${texto_rico(c.texto)}</div>
    </div>
  </div>`;
}

document.addEventListener('submit', async (ev) => {
  const form = ev.target.closest('[data-form-coment]');
  if (!form) return;

  ev.preventDefault();
  const id = form.dataset.formComent;
  const campo = $('input', form);
  const texto = campo.value.trim();
  if (!texto) return;

  campo.value = '';
  campo.disabled = true;

  try {
    const r = await api('comentarios.php', { dados: { acao: 'criar', id, texto } });
    const lista = $(`[data-lista-coment="${id}"]`);
    if (lista.querySelector('p')) lista.innerHTML = '';
    lista.insertAdjacentHTML('beforeend', linhaComentario(r.comentario));

    const contador = $(`[data-comentarios="${id}"] span`);
    if (contador) contador.textContent = r.total;
  } catch (e) {
    toast(e.message, 'erro');
    campo.value = texto;
  } finally {
    campo.disabled = false;
    campo.focus();
  }
});

/* ============================================================
   BOTÃO CONEXÃO
   ============================================================ */
async function abrirConexao() {
  const m = modal(`
    <div class="modal__topo">
      <h2 class="modal__titulo"><i class="fa-solid fa-bolt" style="color:var(--ember)"></i> Botão Conexão</h2>
      <button class="btn btn--icone" data-fechar><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__corpo" id="conexao-corpo">
      <div class="procurando">
        <div class="radar"><i class="fa-solid fa-bolt"></i></div>
        <h3 style="font-family:var(--font-display);font-size:18px;margin-bottom:8px">Procurando alguém no seu momento...</h3>
        <p style="color:var(--fg-dim);font-size:14px">
          Cruzando estado criativo, área e gosto para achar quem combina com você agora.
        </p>
      </div>
    </div>`);

  const corpo = $('#conexao-corpo', m.el);

  // pequena espera proposital: a busca precisa parecer o que é
  const [dados] = await Promise.all([
    api('conexao.php?acao=procurar').catch((e) => ({ erro: e.message })),
    new Promise((r) => setTimeout(r, 1500)),
  ]);

  if (dados.erro) {
    corpo.innerHTML = vazio('fa-triangle-exclamation', 'Deu ruim', dados.erro);
    return;
  }

  if (!dados.encontrado) {
    corpo.innerHTML = vazio('fa-user-astronaut', 'Ninguém disponível ainda', dados.mensagem);
    return;
  }

  const a = dados.artista;

  corpo.innerHTML = `<div class="match">
      <div class="match__avatar">${avatar({ ...a, estado: a.estado }, 'xl')}</div>
      <div class="match__nome">${esc(a.nome)} ${selo(a.pro)}</div>
      <div class="match__area">
        @${esc(a.usuario)} · ${esc(a.area_label)}${a.cidade ? ' · ' + esc(a.cidade) : ''}
      </div>

      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-bottom:18px">
        <span class="chip"><i class="fa-solid fa-circle-dot"></i> ${esc(a.estado_label)}</span>
        ${a.ferramenta ? `<span class="chip"><i class="fa-solid fa-sliders"></i> ${esc(a.ferramenta)}</span>` : ''}
      </div>

      ${a.bio ? `<p style="color:var(--fg-dim);font-size:14px;margin-bottom:18px">${esc(a.bio)}</p>` : ''}

      <div class="match__motivo">
        <i class="fa-solid fa-lightbulb"></i>
        <span>${esc(dados.motivo)}</span>
      </div>

      <div style="display:flex;gap:10px">
        <button class="btn btn--linha" style="flex:1" id="outro-artista">Ver outro</button>
        <button class="btn btn--primario" style="flex:2" id="conectar-agora">
          <i class="fa-solid fa-comments"></i> Puxar conversa
        </button>
      </div>
    </div>`;

  $('#outro-artista', m.el).addEventListener('click', () => { m.fechar(); abrirConexao(); });

  $('#conectar-agora', m.el).addEventListener('click', async (ev) => {
    const botao = ev.currentTarget;
    botao.disabled = true;
    botao.innerHTML = '<i class="fa-solid fa-circle-notch girando"></i> Abrindo...';

    try {
      const r = await api('conexao.php?acao=confirmar', {
        dados: { idusuario: a.id, motivo: dados.motivo.slice(0, 120) },
      });
      m.fechar();
      toast(`Conversa aberta com ${r.nome}.`, 'ok');
      location.hash = '#/direct';
      setTimeout(() => abrirConversa(r.idconversa, {
        nome: r.nome, foto: a.foto, estado: a.estado, usuario: a.usuario, outro_id: a.id,
      }), 200);
    } catch (e) {
      toast(e.message, 'erro');
      botao.disabled = false;
      botao.innerHTML = '<i class="fa-solid fa-comments"></i> Puxar conversa';
    }
  });
}

async function conectarCom(idusuario) {
  try {
    const r = await api('conexao.php?acao=confirmar', { dados: { idusuario, motivo: 'Conexão direta pelo post' } });
    toast(`Conversa aberta com ${r.nome}.`, 'ok');
    location.hash = '#/direct';
    setTimeout(() => carregarDirect().then(() => abrirConversa(r.idconversa, { nome: r.nome })), 200);
  } catch (e) { toast(e.message, 'erro'); }
}

$('#btn-conexao').addEventListener('click', abrirConexao);
$('#conexao-mobile').addEventListener('click', abrirConexao);

/* ============================================================
   ESTADO CRIATIVO
   ============================================================ */
function abrirEstado() {
  const opcoes = Object.entries(S.estados).map(([chave, info]) => `
    <button class="pilula ${chave === S.eu.estado ? 'marcada' : ''}" data-estado="${chave}">
      <i class="ponto ponto-${chave}" style="width:10px;height:10px"></i>
      <span style="flex:1">
        <span class="pilula__t">${esc(info.rotulo)}</span><br>
        <span class="pilula__d">${esc(info.desc)}</span>
      </span>
      ${chave === S.eu.estado ? '<i class="fa-solid fa-check" style="color:var(--spark)"></i>' : ''}
    </button>`).join('');

  const m = modal(`
    <div class="modal__topo">
      <h2 class="modal__titulo">Como você está agora?</h2>
      <button class="btn btn--icone" data-fechar><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__corpo">
      <p style="color:var(--fg-dim);font-size:13.5px;margin-bottom:16px">
        É isso que o Botão Conexão usa para achar quem combina com o seu momento.
      </p>
      <div class="pilulas">${opcoes}</div>
    </div>`);

  $$('[data-estado]', m.el).forEach((b) => b.addEventListener('click', async () => {
    const estado = b.dataset.estado;
    try {
      const r = await api('interagir.php', { dados: { acao: 'estado', estado } });
      S.eu.estado = estado;
      $('#estado-rotulo').textContent = r.rotulo;
      $('#abrir-estado .ponto').className = `ponto ponto-${estado}`;
      $$('.rail__rodape .avatar-anel').forEach((a) => a.dataset.estado = estado);
      atualizarResumoEstado();
      toast(`Agora você está: ${r.rotulo}.`, 'ok');
      m.fechar();
    } catch (e) { toast(e.message, 'erro'); }
  }));
}

function atualizarResumoEstado() {
  const info = S.estados[S.eu.estado];
  const el = $('#resumo-estado');
  if (info && el) el.innerHTML = `<b>${esc(info.rotulo)}</b> — ${esc(info.desc)}`;
}

$('#abrir-estado').addEventListener('click', abrirEstado);
$('#mudar-estado-lateral').addEventListener('click', abrirEstado);

/* ============================================================
   EXPLORAR
   ============================================================ */
const CORES = [
  'linear-gradient(135deg,#8b5cf6,#6d28d9)', 'linear-gradient(135deg,#ffb020,#f97316)',
  'linear-gradient(135deg,#38bdf8,#0284c7)', 'linear-gradient(135deg,#22c55e,#15803d)',
  'linear-gradient(135deg,#ec4899,#be185d)', 'linear-gradient(135deg,#6366f1,#4338ca)',
];

async function carregarExplorar() {
  const gTags = $('#explorar-tags');
  const gArt = $('#explorar-artistas');
  const gMos = $('#explorar-mosaico');

  gTags.innerHTML = '<div class="esqueleto" style="height:118px"></div>'.repeat(6);

  try {
    const d = await api('explorar.php?acao=explorar');

    gTags.innerHTML = d.tags.length
      ? d.tags.map((t, i) => `
          <a class="bloco-tag" href="#/tag/${esc(t.nome)}" style="background:${CORES[i % CORES.length]}">
            <i class="brilho" aria-hidden="true"></i>
            <span class="bloco-tag__nome">#${esc(t.nome)}</span>
            <span class="bloco-tag__n">${t.total} ${t.total === 1 ? 'post' : 'posts'}</span>
          </a>`).join('')
      : '<p style="color:var(--fg-mute);font-size:14px">Ainda não há tags. Publique usando #hashtags.</p>';

    gArt.innerHTML = d.artistas.length
      ? d.artistas.map((a) => `
          <div class="cartao" style="margin:0">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
              <a href="#/perfil/${a.id}">${avatar(a, 'md')}</a>
              <div style="min-width:0;flex:1">
                <div style="font-weight:600;font-size:14px;display:flex;gap:6px;align-items:center">
                  <a href="#/perfil/${a.id}">${esc(a.nome)}</a>${selo(a.pro)}
                </div>
                <div class="rotulo">${esc(a.area_label)}</div>
              </div>
            </div>
            ${a.bio ? `<p style="font-size:13px;color:var(--fg-dim);margin-bottom:12px">${esc(a.bio.slice(0, 90))}</p>` : ''}
            <div style="display:flex;gap:14px;margin-bottom:12px" class="rotulo">
              <span>${a.posts} posts</span><span>${a.seguidores} seguidores</span>
            </div>
            <button class="btn btn--primario btn--sm btn--bloco" data-seguir="${a.id}">Seguir</button>
          </div>`).join('')
      : '<p style="color:var(--fg-mute);font-size:14px">Você já segue todo mundo por aqui.</p>';

    gMos.innerHTML = d.mosaico.length
      ? d.mosaico.map((p) => `
          <a class="mosaico" href="#/post/${p.id}">
            ${p.tipo === 'video'
              ? `<video src="${esc(p.url)}" muted preload="metadata"></video>`
              : `<img src="${esc(p.url)}" alt="${esc(p.titulo || '')}" loading="lazy">`}
            <div class="mosaico__capa">
              <b>${esc(p.titulo || 'Sem título')}</b>
              <span style="color:var(--fg-dim)">@${esc(p.autor.usuario)}</span>
            </div>
          </a>`).join('')
      : `<div style="grid-column:1/-1">${vazio('fa-images', 'Sem publicações visuais ainda', 'Seja o primeiro a soltar uma imagem ou vídeo.')}</div>`;

  } catch (e) {
    gTags.innerHTML = `<p style="color:var(--danger)">${esc(e.message)}</p>`;
  }
}

/* ============================================================
   FÓRUNS
   ============================================================ */
async function carregarForum() {
  const box = $('#forum-conteudo');
  box.innerHTML = esqueletos(2);

  try {
    const d = await api('forum.php?acao=categorias');

    box.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
        <div>
          <h1 class="titulo-pagina">Fóruns artísticos</h1>
          <p class="sub-pagina" style="margin:0">Onde o processo é o assunto — não só o resultado.</p>
        </div>
        <button class="btn btn--primario" id="novo-topico"><i class="fa-solid fa-plus"></i> Novo tópico</button>
      </div>
      <div style="max-width:860px">
        ${d.categorias.map((c) => `
          <a class="forum-cat" href="#/forum/${c.id}">
            <div class="forum-cat__icone"><i class="fa-solid ${esc(c.icone)}"></i></div>
            <div class="forum-cat__info">
              <h3>${esc(c.nome)}</h3>
              <p>${esc(c.descricao)}</p>
            </div>
            <div class="forum-cat__n">
              ${c.topicos} tópicos<br>${c.respostas} respostas
            </div>
          </a>`).join('')}
      </div>`;

    $('#novo-topico').addEventListener('click', () => novoTopico(d.categorias));

  } catch (e) {
    box.innerHTML = vazio('fa-triangle-exclamation', 'Não deu para carregar', e.message);
  }
}

async function abrirCategoria(id) {
  const box = $('#forum-conteudo');
  box.innerHTML = esqueletos(2);

  try {
    const d = await api(`forum.php?acao=topicos&cat=${encodeURIComponent(id)}`);

    box.innerHTML = `
      <a class="btn btn--fantasma btn--sm" href="#/forum" style="margin-bottom:16px">
        <i class="fa-solid fa-arrow-left"></i> Todos os fóruns
      </a>
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
        <div class="forum-cat__icone"><i class="fa-solid ${esc(d.categoria.icone)}"></i></div>
        <div style="flex:1">
          <h1 class="titulo-pagina" style="margin:0">${esc(d.categoria.nome)}</h1>
          <p class="sub-pagina" style="margin:0">${esc(d.categoria.descricao)}</p>
        </div>
        <button class="btn btn--primario btn--sm" id="novo-topico-cat"><i class="fa-solid fa-plus"></i> Novo tópico</button>
      </div>
      <div style="max-width:860px">
        ${d.topicos.length ? d.topicos.map((t) => `
          <a class="topico" href="#/forum/t/${t.id}" style="display:block">
            <div class="topico__titulo">${esc(t.titulo)}</div>
            ${t.trecho ? `<p style="font-size:13.5px;color:var(--fg-dim);margin-bottom:8px">${esc(t.trecho)}...</p>` : ''}
            <div class="topico__meta">
              <span>@${esc(t.autor.usuario)}</span>
              <span><i class="fa-regular fa-comment"></i> ${t.respostas}</span>
              <span><i class="fa-regular fa-eye"></i> ${t.visitas}</span>
              <span>${esc(t.quando)}</span>
            </div>
          </a>`).join('')
          : vazio('fa-comments', 'Nenhum tópico aqui ainda', 'Abra o primeiro e comece a conversa.')}
      </div>`;

    $('#novo-topico-cat').addEventListener('click', () => novoTopico(null, id));

  } catch (e) {
    box.innerHTML = vazio('fa-triangle-exclamation', 'Não deu para carregar', e.message);
  }
}

async function abrirTopico(id) {
  const box = $('#forum-conteudo');
  box.innerHTML = esqueletos(1);

  try {
    const d = await api(`forum.php?acao=topico&id=${encodeURIComponent(id)}`);
    const t = d.topico;

    box.innerHTML = `
      <a class="btn btn--fantasma btn--sm" href="#/forum/${t.idcategoria}" style="margin-bottom:16px">
        <i class="fa-solid fa-arrow-left"></i> ${esc(t.categoria)}
      </a>
      <div style="max-width:820px">
        <div class="cartao">
          <h1 class="titulo-pagina" style="font-size:22px">${esc(t.titulo)}</h1>
          <div style="display:flex;align-items:center;gap:10px;margin:12px 0 16px">
            <a href="#/perfil/${t.autor.id}">${avatar(t.autor, 'sm')}</a>
            <div>
              <div style="font-weight:600;font-size:13.5px">${esc(t.autor.nome)} ${selo(t.autor.pro)}</div>
              <div class="rotulo">${esc(t.quando)} · ${t.visitas} visitas</div>
            </div>
          </div>
          ${t.texto ? `<p style="color:var(--fg-dim);white-space:pre-wrap">${texto_rico(t.texto)}</p>` : ''}
        </div>

        <h2 class="rotulo" style="margin:24px 0 8px">${d.respostas.length} respostas</h2>
        <div class="cartao" id="lista-respostas">
          ${d.respostas.length
            ? d.respostas.map(linhaResposta).join('')
            : '<p style="color:var(--fg-mute);font-size:14px">Ninguém respondeu ainda.</p>'}
        </div>

        <form class="cartao" id="form-resposta">
          <div class="campo" style="margin:0 0 12px">
            <label for="resposta-texto">Sua resposta</label>
            <textarea id="resposta-texto" maxlength="5000" placeholder="Contribua com a discussão..." required></textarea>
          </div>
          <button class="btn btn--primario" type="submit">Responder</button>
        </form>
      </div>`;

    $('#form-resposta').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const campo = $('#resposta-texto');
      const texto = campo.value.trim();
      if (!texto) return;

      const botao = $('button', ev.currentTarget);
      botao.disabled = true;

      try {
        const r = await api('forum.php', { dados: { acao: 'responder', id, texto } });
        const lista = $('#lista-respostas');
        if (lista.querySelector('p')) lista.innerHTML = '';
        lista.insertAdjacentHTML('beforeend', linhaResposta(r.resposta));
        campo.value = '';
        toast('Resposta publicada.', 'ok');
      } catch (e) {
        toast(e.message, 'erro');
      } finally {
        botao.disabled = false;
      }
    });

  } catch (e) {
    box.innerHTML = vazio('fa-triangle-exclamation', 'Não deu para abrir', e.message);
  }
}

function linhaResposta(r) {
  return `<div class="resposta">
    <a href="#/perfil/${r.autor.id}">${avatar(r.autor, 'sm')}</a>
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:8px">
        <b style="font-size:13.5px">${esc(r.autor.nome)}</b>${selo(r.autor.pro)}
        <span class="rotulo">${esc(r.quando)}</span>
      </div>
      <p style="color:var(--fg-dim);font-size:14px;white-space:pre-wrap;margin-top:4px">${texto_rico(r.texto)}</p>
    </div>
  </div>`;
}

async function novoTopico(categorias, catFixa = null) {
  if (!categorias) {
    try { categorias = (await api('forum.php?acao=categorias')).categorias; }
    catch (e) { return toast(e.message, 'erro'); }
  }

  const m = modal(`
    <div class="modal__topo">
      <h2 class="modal__titulo">Novo tópico</h2>
      <button class="btn btn--icone" data-fechar><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form class="modal__corpo" id="form-topico">
      <div class="campo">
        <label for="topico-cat">Fórum</label>
        <select id="topico-cat">
          ${categorias.map((c) => `<option value="${c.id}" ${String(c.id) === String(catFixa) ? 'selected' : ''}>${esc(c.nome)}</option>`).join('')}
        </select>
      </div>
      <div class="campo">
        <label for="topico-titulo">Título</label>
        <input type="text" id="topico-titulo" maxlength="255" required placeholder="Ex: Como vocês saem de um bloqueio de 3 semanas?">
      </div>
      <div class="campo" style="margin:0">
        <label for="topico-texto">Texto</label>
        <textarea id="topico-texto" maxlength="5000" placeholder="Explique sua situação, o que já tentou..."></textarea>
      </div>
    </form>
    <div class="modal__rodape">
      <button class="btn btn--linha" data-fechar>Cancelar</button>
      <button class="btn btn--primario" id="criar-topico">Publicar tópico</button>
    </div>`);

  $('#criar-topico', m.el).addEventListener('click', async () => {
    const dados = {
      acao: 'criar_topico',
      cat: $('#topico-cat', m.el).value,
      titulo: $('#topico-titulo', m.el).value.trim(),
      texto: $('#topico-texto', m.el).value.trim(),
    };
    if (!dados.titulo) return toast('Escreva um título.', 'erro');

    try {
      const r = await api('forum.php', { dados });
      m.fechar();
      toast('Tópico criado.', 'ok');
      location.hash = `#/forum/t/${r.id}`;
    } catch (e) { toast(e.message, 'erro'); }
  });
}

/* ============================================================
   DIRECT
   ============================================================ */
const direct = { conversa: null, polling: null, info: {} };

async function carregarDirect() {
  const lista = $('#direct-conversas');
  lista.innerHTML = '<div class="esqueleto" style="height:70px;margin:10px 18px"></div>'.repeat(4);

  try {
    const d = await api('direct.php?acao=conversas');

    if (!d.conversas.length) {
      lista.innerHTML = `<div style="padding:24px 18px;text-align:center">
        <p style="color:var(--fg-mute);font-size:13.5px;margin-bottom:14px">
          Nenhuma conversa ainda.</p>
        <button class="btn btn--primario btn--sm" onclick="document.getElementById('btn-conexao').click()">
          <i class="fa-solid fa-bolt"></i> Usar Botão Conexão
        </button></div>`;
      return;
    }

    lista.innerHTML = d.conversas.map((c) => `
      <div class="conversa ${direct.conversa === c.id ? 'ativa' : ''}" data-conversa="${c.id}"
           data-info='${esc(JSON.stringify(c))}'>
        ${avatar(c, 'md')}
        <div class="conversa__info">
          <div class="conversa__nome">${esc(c.nome)} ${selo(c.pro)}</div>
          <div class="conversa__previa">${esc(c.previa)}</div>
        </div>
        ${c.nao_lidas
          ? `<span class="conversa__nao-lidas">${c.nao_lidas}</span>`
          : `<span class="conversa__hora">${esc(c.quando)}</span>`}
      </div>`).join('');

    $$('[data-conversa]', lista).forEach((el) => el.addEventListener('click', () => {
      abrirConversa(Number(el.dataset.conversa), JSON.parse(el.dataset.info.replace(/&#39;/g, "'")));
    }));

  } catch (e) {
    lista.innerHTML = `<p style="padding:18px;color:var(--danger);font-size:13.5px">${esc(e.message)}</p>`;
  }
}

async function abrirConversa(id, info = {}) {
  direct.conversa = id;
  direct.info = info;

  $('#direct-vazio').classList.add('hidden');
  $('#direct-ativo').classList.remove('hidden');

  $('#chat-nome').textContent = info.nome || 'Conversa';
  $('#chat-estado').textContent = info.estado ? (S.estados[info.estado]?.rotulo || '') : '';
  $('#chat-anel').dataset.estado = info.estado || 'observando';
  $('#chat-avatar').innerHTML = info.foto
    ? `<img src="${esc(info.foto)}" alt="">`
    : esc((info.nome || '?').charAt(0).toUpperCase());

  const verPerfil = $('#chat-ver-perfil');
  if (info.outro_id) {
    verPerfil.href = `#/perfil/${info.outro_id}`;
    verPerfil.classList.remove('hidden');
  } else {
    verPerfil.classList.add('hidden');
  }

  $$('.conversa').forEach((c) => c.classList.toggle('ativa', Number(c.dataset.conversa) === id));

  // no celular a lista some para o chat ocupar a tela toda
  if (window.innerWidth <= 960) {
    $('#direct-lista').classList.add('escondida-mobile');
    $('#direct-voltar').classList.remove('hidden');
  }

  await carregarMensagens(true);

  clearInterval(direct.polling);
  direct.polling = setInterval(() => {
    if ($('#pg-direct').classList.contains('ativa')) carregarMensagens(false);
    else { clearInterval(direct.polling); direct.polling = null; }
  }, 5000);
}

async function carregarMensagens(rolarSempre) {
  if (!direct.conversa) return;
  const caixa = $('#direct-mensagens');

  try {
    const d = await api(`direct.php?acao=mensagens&id=${direct.conversa}`);

    const noFim = caixa.scrollTop + caixa.clientHeight >= caixa.scrollHeight - 60;
    const assinatura = d.mensagens.map((m) => m.id).join(',');
    if (!rolarSempre && caixa.dataset.assinatura === assinatura) return;
    caixa.dataset.assinatura = assinatura;

    let diaAnterior = '';
    caixa.innerHTML = d.mensagens.map((m) => {
      let sep = '';
      if (m.dia !== diaAnterior) {
        diaAnterior = m.dia;
        const hoje = new Date().toISOString().slice(0, 10);
        const rotulo = m.dia === hoje
          ? 'Hoje'
          : new Date(m.dia + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
        sep = `<span class="dia-sep">${esc(rotulo)}</span>`;
      }
      return `${sep}<div class="balao balao--${m.minha ? 'eu' : 'ele'}">
          ${esc(m.texto)}<span class="balao__hora">${esc(m.hora)}</span>
        </div>`;
    }).join('');

    if (rolarSempre || noFim) caixa.scrollTop = caixa.scrollHeight;

  } catch (e) {
    if (rolarSempre) caixa.innerHTML = `<p style="color:var(--danger)">${esc(e.message)}</p>`;
  }
}

$('#form-mensagem').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const campo = $('#msg-texto');
  const texto = campo.value.trim();
  if (!texto || !direct.conversa) return;

  campo.value = '';

  try {
    await api('direct.php?acao=enviar', { dados: { idconversa: direct.conversa, texto } });
    await carregarMensagens(true);
    carregarDirect();
  } catch (e) {
    toast(e.message, 'erro');
    campo.value = texto;
  }
});

$('#direct-voltar').addEventListener('click', () => {
  $('#direct-lista').classList.remove('escondida-mobile');
  $('#direct-voltar').classList.add('hidden');
});

/* --- buscar alguém para conversar --- */
let buscaDirectTimer;
$('#direct-busca').addEventListener('input', (ev) => {
  clearTimeout(buscaDirectTimer);
  const termo = ev.target.value.trim();
  const caixa = $('#direct-busca-resultados');

  if (termo.length < 2) { caixa.classList.add('hidden'); return; }

  buscaDirectTimer = setTimeout(async () => {
    try {
      const d = await api(`explorar.php?acao=buscar&q=${encodeURIComponent(termo)}`);
      caixa.innerHTML = d.artistas.length
        ? d.artistas.map((a) => `
            <div class="pessoa" style="padding:9px 10px;cursor:pointer" data-novo="${a.id}" data-nome="${esc(a.nome)}">
              ${avatar(a, 'sm')}
              <div class="pessoa__info">
                <div class="pessoa__nome">${esc(a.nome)}</div>
                <div class="pessoa__sub">@${esc(a.usuario)}</div>
              </div>
            </div>`).join('')
        : '<p style="padding:12px;color:var(--fg-mute);font-size:13px">Ninguém encontrado.</p>';

      caixa.classList.remove('hidden');

      $$('[data-novo]', caixa).forEach((el) => el.addEventListener('click', async () => {
        caixa.classList.add('hidden');
        $('#direct-busca').value = '';
        try {
          const r = await api('direct.php?acao=enviar', {
            dados: { idusuario: el.dataset.novo, texto: 'Oi! Vi seu perfil aqui no Spark 👋' },
          });
          await carregarDirect();
          abrirConversa(r.idconversa, { nome: el.dataset.nome, outro_id: Number(el.dataset.novo) });
        } catch (e) { toast(e.message, 'erro'); }
      }));

    } catch { /* busca falha em silêncio */ }
  }, 300);
});

/* ============================================================
   NOTIFICAÇÕES
   ============================================================ */
async function carregarNotificacoes() {
  const lista = $('#lista-notificacoes');
  lista.innerHTML = '<div class="esqueleto" style="height:66px;margin-bottom:8px"></div>'.repeat(4);

  try {
    const d = await api('notificacoes.php');

    lista.innerHTML = d.notificacoes.length
      ? d.notificacoes.map((n) => `
          <a class="notif ${n.lida ? '' : 'nao-lida'}" href="${n.post ? `#/post/${n.post.id}` : `#/perfil/${n.ator.id}`}">
            <div class="notif__icone ${esc(n.tipo)}">
              <i class="fa-solid ${{
                curtida: 'fa-heart', comentario: 'fa-comment', seguidor: 'fa-user-plus',
                mencao: 'fa-at', resposta_forum: 'fa-comments', conexao: 'fa-bolt',
              }[n.tipo] || 'fa-bell'}"></i>
            </div>
            <div class="notif__txt">
              <b>${esc(n.ator.nome)}</b> ${esc(n.frase)}
              ${n.extra ? `<span style="color:var(--fg-mute)"> — "${esc(n.extra)}"</span>` : ''}
              <div class="notif__hora">${esc(n.quando)}</div>
            </div>
            ${n.post?.url ? `<img src="${esc(n.post.url)}" style="width:44px;height:44px;border-radius:8px;object-fit:cover" alt="">` : ''}
          </a>`).join('')
      : vazio('fa-bell', 'Nada por aqui ainda',
              'Quando curtirem, comentarem ou seguirem você, aparece nesta tela.');

    atualizarBadges(d.nao_lidas, d.mensagens);

  } catch (e) {
    lista.innerHTML = vazio('fa-triangle-exclamation', 'Não deu para carregar', e.message);
  }
}

$('#marcar-lidas').addEventListener('click', async () => {
  try {
    await api('notificacoes.php', { dados: { acao: 'ler' } });
    $$('.notif').forEach((n) => n.classList.remove('nao-lida'));
    atualizarBadges(0, null);
    toast('Tudo marcado como lido.', 'ok');
  } catch (e) { toast(e.message, 'erro'); }
});

function atualizarBadges(notif, msgs) {
  const bN = $('#badge-notif');
  const bD = $('#badge-direct');

  if (notif !== null && notif !== undefined) {
    bN.textContent = notif > 99 ? '99+' : notif;
    bN.classList.toggle('hidden', notif === 0);
  }
  if (msgs !== null && msgs !== undefined) {
    bD.textContent = msgs > 99 ? '99+' : msgs;
    bD.classList.toggle('hidden', msgs === 0);
  }
}

async function checarBadges() {
  try {
    const d = await api('notificacoes.php?so_contar=1');
    atualizarBadges(d.nao_lidas, d.mensagens);
  } catch { /* silencioso: é um poll de fundo */ }
}

/* ============================================================
   PUBLICAR
   ============================================================ */
const areaSolta = $('#area-solta');
const campoArquivo = $('#post-arquivo');

areaSolta.addEventListener('click', () => campoArquivo.click());

['dragenter', 'dragover'].forEach((ev) => areaSolta.addEventListener(ev, (e) => {
  e.preventDefault(); areaSolta.classList.add('arrastando');
}));
['dragleave', 'drop'].forEach((ev) => areaSolta.addEventListener(ev, (e) => {
  e.preventDefault(); areaSolta.classList.remove('arrastando');
}));

areaSolta.addEventListener('drop', (e) => {
  if (e.dataTransfer.files.length) {
    campoArquivo.files = e.dataTransfer.files;
    mostrarPrevia(e.dataTransfer.files[0]);
  }
});

campoArquivo.addEventListener('change', () => {
  if (campoArquivo.files[0]) mostrarPrevia(campoArquivo.files[0]);
});

function mostrarPrevia(arquivo) {
  $('#area-texto').textContent = arquivo.name;
  const previa = $('#area-previa');

  if (arquivo.type.startsWith('image/')) {
    const leitor = new FileReader();
    leitor.onload = (e) => {
      previa.src = e.target.result;
      previa.classList.remove('hidden');
    };
    leitor.readAsDataURL(arquivo);
  } else {
    previa.classList.add('hidden');
  }
}

function limparFormPost() {
  $('#form-post').reset();
  $('#area-texto').textContent = 'Arraste aqui ou clique para escolher';
  $('#area-previa').classList.add('hidden');
  $('#post-alerta').innerHTML = '';
}

$('#post-limpar').addEventListener('click', limparFormPost);

$('#form-post').addEventListener('submit', async (ev) => {
  ev.preventDefault();

  const botao = $('#post-enviar');
  const alerta = $('#post-alerta');
  alerta.innerHTML = '';

  const fd = new FormData();
  fd.append('titulo', $('#post-titulo').value.trim());
  fd.append('descricao', $('#post-desc').value.trim());
  if (campoArquivo.files[0]) fd.append('midia', campoArquivo.files[0]);

  botao.disabled = true;
  botao.innerHTML = '<i class="fa-solid fa-circle-notch girando"></i> Publicando...';

  try {
    const r = await api('post.php?acao=criar', { body: fd });
    limparFormPost();
    toast('Publicado! Sua faísca está no ar.', 'ok');

    location.hash = '#/feed';
    const grade = $('#feed');
    if (grade.querySelector('.vazio')) grade.innerHTML = '';
    grade.insertAdjacentHTML('afterbegin', cartaoPost(r.post));

  } catch (e) {
    alerta.innerHTML = `<div class="alerta alerta--erro"><i class="fa-solid fa-circle-exclamation"></i> ${esc(e.message)}</div>`;
  } finally {
    botao.disabled = false;
    botao.innerHTML = '<i class="fa-solid fa-bolt"></i> Publicar';
  }
});

/* ============================================================
   PERFIL
   ============================================================ */
async function carregarPerfil({ id = null, usuario = null, aba = 'posts' } = {}) {
  const box = $('#perfil-conteudo');
  box.innerHTML = esqueletos(2);

  const params = new URLSearchParams({ acao: 'ver', aba });
  if (id) params.set('id', id);
  if (usuario) params.set('usuario', usuario);

  try {
    const d = await api(`perfil.php?${params}`);
    const p = d.perfil;

    box.innerHTML = `
      <div class="perfil-capa"></div>
      <div class="perfil-cabeca">
        ${avatar(p, 'lg')}
        <div class="perfil-dados">
          <h1 class="perfil-nome">${esc(p.nome)} ${selo(p.pro)}</h1>
          <div class="perfil-arroba">@${esc(p.usuario)}</div>
          ${p.bio ? `<p class="perfil-bio">${texto_rico(p.bio)}</p>` : ''}

          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <span class="chip"><i class="fa-solid fa-palette"></i> ${esc(p.area_label)}</span>
            ${p.ferramenta ? `<span class="chip"><i class="fa-solid fa-sliders"></i> ${esc(p.ferramenta)}</span>` : ''}
            ${p.cidade ? `<span class="chip"><i class="fa-solid fa-location-dot"></i> ${esc(p.cidade)}</span>` : ''}
            <span class="chip"><i class="fa-solid fa-circle-dot"></i> ${esc(p.estado_label)}</span>
            <span class="chip"><i class="fa-regular fa-calendar"></i> desde ${esc(p.desde)}</span>
          </div>

          <div class="numeros">
            <span class="numero"><b>${d.numeros.posts}</b><span>posts</span></span>
            <span class="numero"><b>${d.numeros.seguidores}</b><span>seguidores</span></span>
            <span class="numero"><b>${d.numeros.seguindo}</b><span>seguindo</span></span>
            <span class="numero"><b>${d.numeros.curtidas}</b><span>curtidas</span></span>
          </div>
        </div>

        <div style="display:flex;gap:8px;padding-bottom:6px;flex-wrap:wrap">
          ${d.sou_eu
            ? `<a class="btn btn--linha" href="#/config"><i class="fa-solid fa-pen"></i> Editar perfil</a>`
            : `<button class="btn ${d.eu_sigo ? 'btn--linha' : 'btn--primario'}" data-seguir="${p.id}">${d.eu_sigo ? 'Seguindo' : 'Seguir'}</button>
               <button class="btn btn--chama" data-conectar="${p.id}"><i class="fa-solid fa-bolt"></i> Conectar</button>`}
        </div>
      </div>

      ${p.tags.length ? `<div class="tags" style="margin-bottom:18px">
          ${p.tags.map((t) => `<a class="tag" href="#/tag/${esc(t)}">#${esc(t)}</a>`).join('')}</div>` : ''}

      <div class="abas">
        <button class="aba ${aba === 'posts' ? 'ativa' : ''}" data-aba-perfil="posts">Publicações</button>
        <button class="aba ${aba === 'curtidas' ? 'ativa' : ''}" data-aba-perfil="curtidas">Curtidas</button>
        ${d.sou_eu ? `<button class="aba ${aba === 'salvos' ? 'ativa' : ''}" data-aba-perfil="salvos">Salvos</button>` : ''}
      </div>

      <div class="feed" style="max-width:680px">
        ${d.posts.length
          ? d.posts.map(cartaoPost).join('')
          : vazio('fa-inbox', 'Nada aqui ainda',
                  d.sou_eu ? 'Publique sua primeira faísca.' : 'Esse artista ainda não publicou nada.')}
      </div>`;

    $$('[data-aba-perfil]', box).forEach((b) => b.addEventListener('click', () => {
      carregarPerfil({ id: p.id, aba: b.dataset.abaPerfil });
    }));

  } catch (e) {
    box.innerHTML = vazio('fa-user-slash', 'Perfil não encontrado', e.message);
  }
}

/* ============================================================
   CONFIGURAÇÕES
   ============================================================ */
$('#btn-trocar-foto').addEventListener('click', () => $('#config-foto').click());

$('#config-foto').addEventListener('change', async (ev) => {
  const arquivo = ev.target.files[0];
  if (!arquivo) return;

  const fd = new FormData();
  fd.append('foto', arquivo);

  try {
    const r = await api('perfil.php?acao=foto', { body: fd });
    S.eu.foto = r.foto;
    $('#config-avatar').innerHTML = `<img src="${esc(r.foto)}" alt="">`;
    $$('.rail__rodape .avatar').forEach((a) => a.innerHTML = `<img src="${esc(r.foto)}" alt="">`);
    toast('Foto atualizada.', 'ok');
  } catch (e) { toast(e.message, 'erro'); }
});

$('#form-perfil').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const alerta = $('#perfil-alerta');
  alerta.innerHTML = '';

  try {
    await api('perfil.php?acao=salvar', {
      dados: {
        nome: $('#cfg-nome').value.trim(),
        usuario: $('#cfg-usuario').value.trim(),
        bio: $('#cfg-bio').value.trim(),
        area: $('#cfg-area').value,
        ferramenta: $('#cfg-ferramenta').value.trim(),
        cidade: $('#cfg-cidade').value.trim(),
      },
    });
    alerta.innerHTML = '<div class="alerta alerta--ok"><i class="fa-solid fa-check"></i> Perfil salvo.</div>';
    toast('Perfil atualizado.', 'ok');
  } catch (e) {
    alerta.innerHTML = `<div class="alerta alerta--erro"><i class="fa-solid fa-circle-exclamation"></i> ${esc(e.message)}</div>`;
  }
});

$('#form-senha').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const alerta = $('#senha-alerta');
  alerta.innerHTML = '';

  try {
    await api('perfil.php?acao=senha', {
      dados: { atual: $('#cfg-senha-atual').value, nova: $('#cfg-senha-nova').value },
    });
    $('#form-senha').reset();
    alerta.innerHTML = '<div class="alerta alerta--ok"><i class="fa-solid fa-check"></i> Senha alterada.</div>';
  } catch (e) {
    alerta.innerHTML = `<div class="alerta alerta--erro"><i class="fa-solid fa-circle-exclamation"></i> ${esc(e.message)}</div>`;
  }
});

/* ============================================================
   BUSCA GLOBAL
   ============================================================ */
let buscaTimer;
const campoBusca = $('#busca');
const caixaBusca = $('#busca-resultados');

campoBusca.addEventListener('input', () => {
  clearTimeout(buscaTimer);
  const termo = campoBusca.value.trim();

  if (termo.length < 2) { caixaBusca.classList.add('hidden'); return; }

  buscaTimer = setTimeout(async () => {
    try {
      const d = await api(`explorar.php?acao=buscar&q=${encodeURIComponent(termo)}`);
      const partes = [];

      if (d.artistas.length) {
        partes.push(`<div class="rotulo" style="padding:8px 10px 4px">Artistas</div>`);
        partes.push(d.artistas.map((a) => `
          <a class="pessoa" style="padding:8px 10px;border-radius:8px" href="#/perfil/${a.id}">
            ${avatar(a, 'sm')}
            <div class="pessoa__info">
              <div class="pessoa__nome">${esc(a.nome)} ${selo(a.pro)}</div>
              <div class="pessoa__sub">@${esc(a.usuario)} · ${esc(a.area_label)}</div>
            </div>
          </a>`).join(''));
      }

      if (d.tags.length) {
        partes.push(`<div class="rotulo" style="padding:12px 10px 4px">Tags</div>`);
        partes.push(d.tags.map((t) => `
          <a class="pessoa" style="padding:8px 10px;border-radius:8px" href="#/tag/${esc(t.nome)}">
            <span class="avatar avatar--sm" style="background:var(--surface-3);color:var(--spark-hi)">#</span>
            <div class="pessoa__info">
              <div class="pessoa__nome">#${esc(t.nome)}</div>
              <div class="pessoa__sub">${t.total} posts</div>
            </div>
          </a>`).join(''));
      }

      if (d.posts.length) {
        partes.push(`<div class="rotulo" style="padding:12px 10px 4px">Publicações</div>`);
        partes.push(d.posts.map((p) => `
          <a class="pessoa" style="padding:8px 10px;border-radius:8px" href="#/post/${p.id}">
            <span class="avatar avatar--sm" style="background:var(--surface-3);color:var(--fg-dim)">
              <i class="fa-solid fa-${{ imagem: 'image', video: 'video', audio: 'music', documento: 'file' }[p.tipo] || 'note-sticky'}"></i>
            </span>
            <div class="pessoa__info">
              <div class="pessoa__nome">${esc(p.titulo || 'Sem título')}</div>
              <div class="pessoa__sub">@${esc(p.autor.usuario)}</div>
            </div>
          </a>`).join(''));
      }

      caixaBusca.innerHTML = partes.length
        ? partes.join('')
        : '<p style="padding:14px;color:var(--fg-mute);font-size:13.5px">Nada encontrado.</p>';
      caixaBusca.classList.remove('hidden');

    } catch { /* silencioso */ }
  }, 300);
});

document.addEventListener('click', (ev) => {
  if (!ev.target.closest('.busca')) {
    caixaBusca.classList.add('hidden');
    $('#direct-busca-resultados')?.classList.add('hidden');
  }
});

/* ============================================================
   POST INDIVIDUAL
   ============================================================ */
async function abrirPost(id) {
  const grade = $('#feed');
  grade.innerHTML = esqueletos(1);
  $('#filtro-tag').classList.add('hidden');

  try {
    const d = await api(`post.php?acao=ver&id=${encodeURIComponent(id)}`);
    grade.innerHTML = `
      <a class="btn btn--fantasma btn--sm" href="#/feed" style="margin-bottom:8px">
        <i class="fa-solid fa-arrow-left"></i> Voltar ao feed
      </a>` + cartaoPost(d.post);

    feed.fim = true;                    // não continua paginando numa vista de item único
    $(`[data-comentarios="${d.post.id}"]`)?.click();

  } catch (e) {
    grade.innerHTML = vazio('fa-link-slash', 'Publicação não encontrada', e.message);
  }
}

/* ============================================================
   TEMA
   ============================================================ */
const btnTema = $('#alternar-tema');

let temporizadorTema = null;

/**
 * Troca o tema de forma coordenada.
 *
 * A classe .trocando-tema impõe a mesma duração e curva a todos os
 * elementos durante a transição, e sai logo depois. Sem ela cada
 * componente muda no próprio tempo e a troca parece quebrada.
 *
 * O evento avisa quem guarda cor fora do CSS — o desenho da onda de
 * áudio, por exemplo, que lê a paleta uma vez e cacheia.
 */
function aplicarTema(tema, animar = true) {
  const raiz = document.documentElement;

  if (animar && raiz.dataset.tema && raiz.dataset.tema !== tema) {
    raiz.classList.add('trocando-tema');
    clearTimeout(temporizadorTema);
    temporizadorTema = setTimeout(() => raiz.classList.remove('trocando-tema'), 460);
  }

  raiz.dataset.tema = tema;
  localStorage.setItem('spark:tema', tema);
  $('i', btnTema).className = tema === 'claro' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';

  document.dispatchEvent(new CustomEvent('spark:tema', { detail: { tema } }));
}

btnTema.addEventListener('click', () => {
  aplicarTema(document.documentElement.dataset.tema === 'claro' ? 'escuro' : 'claro');
});

// na carga não há transição: o tema já nasce aplicado
aplicarTema(localStorage.getItem('spark:tema') || 'escuro', false);

/* ============================================================
   ATALHOS
   ============================================================ */
document.addEventListener('keydown', (ev) => {
  const digitando = /^(INPUT|TEXTAREA|SELECT)$/.test(ev.target.tagName);

  if (ev.key === '/' && !digitando) { ev.preventDefault(); campoBusca.focus(); }
  if (ev.key.toLowerCase() === 'c' && !digitando && !ev.ctrlKey && !ev.metaKey) abrirConexao();
});

/* ============================================================
   INÍCIO
   ============================================================ */
atualizarResumoEstado();
rotear();
checarBadges();
setInterval(checarBadges, 30000);

// alimenta a lateral do feed com dados reais
api('explorar.php?acao=explorar').then((d) => {
  const cx = $('#sugestoes-artistas');
  cx.innerHTML = d.artistas.length
    ? d.artistas.slice(0, 4).map((a) => `
        <div class="pessoa">
          <a href="#/perfil/${a.id}">${avatar(a, 'sm')}</a>
          <div class="pessoa__info">
            <div class="pessoa__nome"><a href="#/perfil/${a.id}">${esc(a.nome)}</a></div>
            <div class="pessoa__sub">${esc(a.area_label)}</div>
          </div>
          <button class="btn btn--linha btn--sm" data-seguir="${a.id}">Seguir</button>
        </div>`).join('')
    : '<p style="font-size:13px;color:var(--fg-mute)">Convide gente para a rede.</p>';

  const ct = $('#tags-alta');
  ct.innerHTML = d.tags.length
    ? d.tags.slice(0, 6).map((t) => `
        <a class="tag-alta" href="#/tag/${esc(t.nome)}">
          <span class="tag-alta__nome">#${esc(t.nome)}</span>
          <span class="tag-alta__n">${t.total}</span>
        </a>`).join('')
    : '<p style="font-size:13px;color:var(--fg-mute)">Sem tags ainda.</p>';
}).catch(() => {});
