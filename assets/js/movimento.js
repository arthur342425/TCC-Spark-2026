/* ============================================================
   SPARK — camada de movimento

   Três coisas:
     1. rolagem suave (Lenis, via importmap)
     2. cortina de abertura, que destrava a rolagem ao sair
     3. revelação de títulos por máscara, palavra a palavra

   Carregado como módulo. Se o CDN do Lenis não responder, tudo
   segue funcionando — a rolagem só volta a ser a nativa.
   ============================================================ */

const menosMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------------------------------------------------------
   Rolagem suave
   --------------------------------------------------------- */
let lenis = null;

function travarRolagem(travar) {
  const html = document.documentElement;
  if (travar) {
    lenis?.stop();
    html.style.overflow = 'hidden';
  } else {
    lenis?.start();
    html.style.removeProperty('overflow');
  }
}

// exposto para o modal e o menu bloquearem a rolagem
window.SparkRolagem = { travar: travarRolagem };

async function iniciarLenis() {
  if (menosMovimento) return;

  try {
    const { default: Lenis } = await import('lenis');

    lenis = new Lenis({
      smoothWheel: true,
      duration: 1.05,
      // a rolagem do Direct e dos modais é própria; o Lenis não deve tocar nela
      prevent: (no) => !!no.closest?.('.direct__msgs, .direct__rolagem, .modal, .busca__resultados'),
    });

    const passo = (t) => { lenis.raf(t); requestAnimationFrame(passo); };
    requestAnimationFrame(passo);

  } catch (e) {
    // sem CDN, sem drama: a rolagem nativa continua
    console.info('[spark] rolagem suave indisponível:', e.message);
  }
}

/* ---------------------------------------------------------
   Mola — um integrador simples, para o que o CSS não alcança
   --------------------------------------------------------- */
export function mola({ de = 0, para = 1, tensao = 200, atrito = 26, aoMudar }) {
  let x = de, v = 0, alvo = para, rodando = false, ultimo = 0;

  function passo(agora) {
    const dt = Math.min((agora - ultimo) / 1000, 0.064);
    ultimo = agora;

    const a = -tensao * (x - alvo) - atrito * v;
    v += a * dt;
    x += v * dt;

    aoMudar(x);

    if (Math.abs(x - alvo) < 0.001 && Math.abs(v) < 0.001) {
      x = alvo; aoMudar(x); rodando = false; return;
    }
    requestAnimationFrame(passo);
  }

  return {
    para(novo) {
      alvo = novo;
      if (!rodando) { rodando = true; ultimo = performance.now(); requestAnimationFrame(passo); }
    },
  };
}

/* ---------------------------------------------------------
   Revelação por máscara
   Envolve cada palavra numa caixa que corta o transbordo e
   solta uma de cada vez.
   --------------------------------------------------------- */
const ESCALONAMENTO = 90;   // ms entre palavras

function fatiarEmPalavras(el) {
  if (el.dataset.fatiado) return [...el.querySelectorAll('.corta-palavra')];
  el.dataset.fatiado = '1';

  const palavras = el.textContent.trim().split(/\s+/);
  el.textContent = '';

  return palavras.map((p, i) => {
    const caixa = document.createElement('span');
    caixa.className = 'corta-palavra';

    const dentro = document.createElement('span');
    dentro.textContent = p;
    caixa.appendChild(dentro);

    el.appendChild(caixa);
    if (i < palavras.length - 1) el.appendChild(document.createTextNode(' '));
    return caixa;
  });
}

function revelar(el, atrasoBase = 0) {
  const caixas = fatiarEmPalavras(el);
  caixas.forEach((c, i) => {
    const espera = atrasoBase + i * ESCALONAMENTO;
    c.querySelector('span').style.transitionDelay = espera + 'ms';
    // um quadro de folga para o navegador registrar o estado inicial
    requestAnimationFrame(() => requestAnimationFrame(() => c.classList.add('revelado')));
  });
}

const ALVOS_TITULO = '.titulo-pagina, .vitrine__frase, .capa h1';

const observador = window.IntersectionObserver
  ? new IntersectionObserver((entradas, obs) => {
      for (const e of entradas) {
        if (!e.isIntersecting) continue;
        revelar(e.target);
        obs.unobserve(e.target);
      }
    }, { threshold: 0.25 })
  : null;

function prepararTitulos(raiz = document) {
  if (menosMovimento) return;

  const alvos = raiz.querySelectorAll?.(ALVOS_TITULO) || [];
  alvos.forEach((el) => {
    if (el.dataset.revelaPronto) return;
    el.dataset.revelaPronto = '1';

    // título já visível revela na hora; o resto espera a rolagem
    const r = el.getBoundingClientRect();
    if (r.top < window.innerHeight && r.bottom > 0) {
      fatiarEmPalavras(el);
      revelar(el, 120);
    } else if (observador) {
      fatiarEmPalavras(el);
      observador.observe(el);
    }
  });
}

/* REDE DE SEGURANÇA: nada pode ficar invisível.
   Se a revelação não disparar, isto solta tudo. */
function resgatar() {
  document.querySelectorAll('.corta-palavra:not(.revelado), .corta:not(.revelado)')
    .forEach((c) => c.classList.add('revelado'));
}

/* ---------------------------------------------------------
   Cortina de abertura
   --------------------------------------------------------- */
const MIN_VISIVEL = menosMovimento ? 200 : 1400;
const MAX_VISIVEL = 2600;
const SAIDA       = menosMovimento ? 0 : 850;

function abrirCortina() {
  const cortina = document.querySelector('.cortina');
  if (!cortina) { travarRolagem(false); prepararTitulos(); return; }

  travarRolagem(true);

  let jaFoi = false;
  const sair = () => {
    if (jaFoi) return;
    jaFoi = true;

    cortina.classList.add('subindo');
    travarRolagem(false);
    document.body.dataset.pronto = '1';
    prepararTitulos();

    setTimeout(() => cortina.remove(), SAIDA + 60);
  };

  const contar = () => setTimeout(sair, MIN_VISIVEL);

  if (document.readyState === 'complete') contar();
  else window.addEventListener('load', contar, { once: true });

  // se o load nunca vier, sai assim mesmo
  setTimeout(sair, MAX_VISIVEL);
}

/* ============================================================
   NAVEGAÇÃO LÍQUIDA

   Duas gotas perseguem o item ativo com molas de rigidez
   diferente. A da frente chega antes; o rastro se atrasa. O filtro
   goo (no CSS) costura as duas numa forma só enquanto estiverem
   próximas — daí o pescoço de líquido.

   O esticão sai da velocidade da mola: alonga no eixo do movimento
   e afina no outro, com o volume aproximadamente conservado. É o
   que faz a forma parecer ter massa em vez de deslizar.
   ============================================================ */
function iniciarNavLiquida() {
  const nav = document.querySelector('.rail__nav');
  const liquido = nav?.querySelector('.rail__liquido');
  if (!nav || !liquido || menosMovimento) return;

  const frente = liquido.querySelector('.gota--frente');
  const rastro = liquido.querySelector('.gota--rastro');

  // halo fora do filtro: dentro dele o goo apagaria o brilho
  const halo = document.createElement('i');
  halo.className = 'rail__halo';
  nav.appendChild(halo);

  // estado de cada mola: posição, velocidade
  const m1 = { y: 0, v: 0 };   // frente
  const m2 = { y: 0, v: 0 };   // rastro
  let alvo = 0;
  let laco = null;
  let iniciado = false;
  let horizontal = false;   // barra de abas do celular

  function medirAlvo() {
    const ativo = nav.querySelector('.rail__item.ativo');
    if (!ativo) return null;

    // o menu vira barra horizontal no celular; a gota tem que seguir o eixo certo
    horizontal = getComputedStyle(nav).flexDirection.startsWith('row');

    const rNav  = nav.getBoundingClientRect();
    const rItem = ativo.getBoundingClientRect();

    for (const el of [liquido, halo]) {
      el.style.setProperty('--gota-w', rItem.width + 'px');
      el.style.setProperty('--gota-h', rItem.height + 'px');
    }

    return horizontal ? rItem.left - rNav.left : rItem.top - rNav.top;
  }

  function passo() {
    let parado = true;

    for (const [m, tensao, atrito] of [[m1, 420, 30], [m2, 190, 24]]) {
      const a = -tensao * (m.y - alvo) - atrito * m.v;
      m.v += a * (1 / 60);
      m.y += m.v * (1 / 60);
      if (Math.abs(m.y - alvo) > 0.15 || Math.abs(m.v) > 0.15) parado = false;
    }

    aplicar(frente, m1);
    aplicar(rastro, m2);
    posicionarHalo(m1.y);

    if (parado) {
      m1.y = m2.y = alvo; m1.v = m2.v = 0;
      aplicar(frente, m1); aplicar(rastro, m2);
      posicionarHalo(alvo);
      liquido.classList.remove('fundindo');   // devolve a área ao compositor
      laco = null;
      return;
    }
    laco = requestAnimationFrame(passo);
  }

  function aplicar(el, m) {
    // velocidade → esticão, sempre no eixo do movimento
    const estica = Math.min(Math.abs(m.v) / 900, 0.34);
    const pos = m.y.toFixed(2) + 'px';

    if (horizontal) {
      el.style.setProperty('--gxp', pos);
      el.style.setProperty('--gy', '0px');
      el.style.setProperty('--gx', (1 + estica).toFixed(3));       // alonga em X
      el.style.setProperty('--ge', (1 - estica * 0.55).toFixed(3)); // afina em Y
    } else {
      el.style.setProperty('--gy', pos);
      el.style.setProperty('--gxp', '0px');
      el.style.setProperty('--ge', (1 + estica).toFixed(3));
      el.style.setProperty('--gx', (1 - estica * 0.55).toFixed(3));
    }
  }

  /* o halo vive fora do filtro goo, mas segue o mesmo eixo */
  function posicionarHalo(v) {
    const pos = v.toFixed(2) + 'px';
    halo.style.setProperty(horizontal ? '--gxp' : '--gy', pos);
    halo.style.setProperty(horizontal ? '--gy' : '--gxp', '0px');
  }

  function acompanhar(instantaneo = false) {
    const novo = medirAlvo();
    if (novo === null) return;

    /* Nada mudou e já estamos parados: não há o que animar.
       Sem esta saída, qualquer disparo repetido do observador
       reacenderia o laço à toa. */
    if (!instantaneo && iniciado && Math.abs(novo - alvo) < 0.5 && !laco) return;

    alvo = novo;

    /* Com a aba escondida o navegador congela o requestAnimationFrame:
       a mola não avançaria e a gota ficaria parada no item anterior.
       Além disso, animar o que ninguém está vendo é desperdício. Nos
       dois casos a resposta é a mesma — vai direto para o destino. */
    if (!iniciado || instantaneo || document.hidden) {
      iniciado = true;
      m1.y = m2.y = alvo; m1.v = m2.v = 0;
      aplicar(frente, m1); aplicar(rastro, m2);
      posicionarHalo(alvo);
      liquido.classList.add('viva');
      nav.classList.add('viva');
      return;
    }
    liquido.classList.add('fundindo');   // fusão só durante o trajeto
    if (!laco) laco = requestAnimationFrame(passo);
  }

  /* O app troca a classe .ativo ao navegar.
     ATENÇÃO: observar o nav inteiro com subtree seria um laço fechado —
     o .rail__liquido mora dentro dele e o próprio acompanhar() mexe na
     classe dele, o que dispararia o observador de novo, sem fim.
     Por isso vigiamos apenas os itens de menu, um a um. */
  const vigia = new MutationObserver(() => acompanhar());
  nav.querySelectorAll('.rail__item').forEach((item) => {
    vigia.observe(item, { attributes: true, attributeFilter: ['class'] });
  });

  window.addEventListener('resize', () => acompanhar(true), { passive: true });

  // primeira medição depois das fontes, senão a altura do item muda
  (document.fonts?.ready || Promise.resolve()).then(() => {
    requestAnimationFrame(() => acompanhar(true));
  });
  setTimeout(() => acompanhar(true), 700);   // rede de segurança
}

/* ---------------------------------------------------------
   Início
   --------------------------------------------------------- */
/* ---------------------------------------------------------
   Campo de ondas — só nas telas de entrada.
   Dentro do app o feed, o analisador de áudio e a navegação
   líquida já disputam a GPU; somar 100 mil partículas ali
   atrapalharia justamente o que o usuário veio usar.
   --------------------------------------------------------- */
/* Registra o que cada camada decidiu, em data-fundo no <body>.
   Serve de diagnóstico: basta inspecionar o body para saber se o
   fundo não apareceu por escolha (tela pequena, máquina fraca) ou
   por falha (módulo, CDN, WebGL). */
function anotar(valor) {
  const atual = document.body.dataset.fundo;
  document.body.dataset.fundo = atual ? atual + ' · ' + valor : valor;
}

/**
 * Tenta subir um fundo. Se ele recusar por a janela estar pequena,
 * fica de olho no resize e tenta de novo quando houver espaço.
 *
 * Sem isso, quem abre o navegador em janela pequena e depois maximiza
 * nunca veria o fundo: a decisão teria sido tomada uma única vez, no
 * pior momento possível.
 */
function subirFundo(nome, arquivo, iniciar, larguraMinima) {
  let tentando = false;
  let desistiu = false;

  let avisouEspera = false;

  function tentar() {
    if (tentando || desistiu) return;

    if (innerWidth < larguraMinima) {
      if (!avisouEspera) { avisouEspera = true; anotar(nome + ': aguardando espaço'); }
      return;
    }
    tentando = true;

    import(arquivo)
      .then((m) => iniciar(m))
      .then((ok) => {
        tentando = false;
        if (ok) { desistiu = true; anotar(nome + ': no ar'); removeEventListener('resize', aoRedimensionar); }
        else anotar(nome + ': recusado');
      })
      .catch((e) => {
        tentando = false;
        desistiu = true;
        anotar(nome + ': falhou');
        console.info('[spark] ' + nome + ' não carregou:', e.message);
      });
  }

  let espera = null;
  const aoRedimensionar = () => { clearTimeout(espera); espera = setTimeout(tentar, 350); };

  tentar();
  if (!desistiu) addEventListener('resize', aoRedimensionar, { passive: true });
}

function talvezOndas() {
  if (document.querySelector('.app')) return;          // é o app: não entra
  if (!document.querySelector('.entrada, .capa')) { anotar('ondas: sem alvo'); return; }

  subirFundo('ondas', './ondas.js', (m) => m.iniciarOndas(), 900);
}

/* Dentro do app é o túnel de estrelas: 4.200 pontos, um décimo do
   custo das ondas. Cabe atrás do feed sem tirar quadro da interface. */
function talvezEstrelas() {
  if (!document.querySelector('.app')) return;

  subirFundo('estrelas', './estrelas.js', (m) => m.iniciarEstrelas(), 820);
}

window.scrollTo(0, 0);
iniciarLenis();
abrirCortina();
iniciarNavLiquida();
talvezOndas();
talvezEstrelas();

// títulos criados depois (o app troca telas inteiras)
let pendente = false;
new MutationObserver(() => {
  if (pendente) return;
  pendente = true;
  setTimeout(() => { pendente = false; prepararTitulos(); }, 0);
}).observe(document.body, { childList: true, subtree: true });

// duas redes: uma no load, outra tardia
window.addEventListener('load', () => setTimeout(resgatar, 2500));
setTimeout(resgatar, 4000);
