/* ============================================================
   SPARK — camada "Liquid Glass"

   Comportamentos que o CSS sozinho não alcança:
     · aurora e granulado de fundo (o que o vidro refrata)
     · luz especular que segue o cursor
     · ondinha no ponto exato do clique
     · mostrar/ocultar senha, força da senha, contador
     · lupa de imagem e voltar ao topo

   Cuidado com custo: o app troca telas inteiras o tempo todo, então
   nada aqui pode varrer o documento a cada mutação. O observador
   processa APENAS as subárvores recém-inseridas.
   ============================================================ */
'use strict';

(function () {

  const menosMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ==========================================================
     FUNDO
     ========================================================== */
  if (!document.querySelector('.aurora')) {
    const aurora = document.createElement('div');
    aurora.className = 'aurora';
    aurora.setAttribute('aria-hidden', 'true');
    aurora.innerHTML = '<span></span><span></span><span></span>';
    document.body.prepend(aurora);

    const grao = document.createElement('div');
    grao.className = 'grao';
    grao.setAttribute('aria-hidden', 'true');
    document.body.prepend(grao);
  }

  /* ==========================================================
     LUZ DO CURSOR
     Lista curta de propósito: cada elemento marcado ganha um
     ::after que pinta um gradiente. Espalhar isso por tudo
     multiplica camadas sem ninguém perceber a diferença.
     ========================================================== */
  const SELETOR_LUZ = [
    '.cartao', '.post', '.modal', '.forum-cat', '.topico',
    '.notif', '.pilula', '.area-solta', '.painel__caixa', '.plano',
  ].join(',');

  function marcarLuz(raiz) {
    if (raiz.nodeType !== 1) return;
    if (raiz.matches?.(SELETOR_LUZ)) raiz.classList.add('luz-cursor');
    raiz.querySelectorAll?.(SELETOR_LUZ).forEach((el) => el.classList.add('luz-cursor'));
  }
  marcarLuz(document.body);

  /* Elementos magnéticos: seguem o cursor de leve.
     Raio de atração e deslocamento máximo em pixels. */
  const SELETOR_IMA = '.btn--primario, .btn--chama, .btn-conexao, .conexao-flutuante, .faixa__play';
  const RAIO_IMA = 90;
  const FORCA_IMA = 7;

  /* Lista dos ímãs E suas caixas, ambas em cache.
     Medir a caixa com getBoundingClientRect força o navegador a
     recalcular layout. Fazer isso para cada ímã a cada movimento do
     mouse são dezenas de recálculos por segundo — era o gargalo.
     As caixas só mudam quando o DOM muda, a janela redimensiona ou a
     página rola; então é só nesses três momentos que remedimos. */
  let imas = [];
  let caixasSujas = true;

  function recolherImas() {
    imas = [...document.querySelectorAll(SELETOR_IMA)].map((el) => ({ el, r: null }));
    caixasSujas = true;
  }

  function remedirImas() {
    for (const m of imas) {
      const r = m.el.getBoundingClientRect();
      m.r = r.width ? { cx: r.left + r.width / 2, cy: r.top + r.height / 2, lado: Math.max(r.width, r.height) } : null;
    }
    caixasSujas = false;
  }

  recolherImas();
  addEventListener('scroll', () => { caixasSujas = true; }, { passive: true });
  addEventListener('resize', () => { caixasSujas = true; }, { passive: true });

  if (!menosMovimento) {
    let pendente = false;
    let ex = 0, ey = 0;
    let alvoAtual = null;
    let ultimoImas = [];

    document.addEventListener('pointermove', (ev) => {
      // movimento minúsculo não muda nada visível: sai antes de agendar
      if (Math.abs(ev.clientX - ex) < 2 && Math.abs(ev.clientY - ey) < 2) return;

      ex = ev.clientX;
      ey = ev.clientY;
      alvoAtual = ev.target;

      if (pendente) return;
      pendente = true;

      requestAnimationFrame(() => {
        pendente = false;

        /* --- luz e anel especular do painel sob o cursor ---
           O alvo vem do próprio evento. elementFromPoint faria o
           navegador refazer o teste de acerto (outro recálculo de
           layout) para chegar ao mesmo elemento. */
        const painel = alvoAtual?.closest?.('.luz-cursor');
        if (painel) {
          const r = painel.getBoundingClientRect();
          const px = ex - r.left;
          const py = ey - r.top;

          painel.style.setProperty('--mx', px + 'px');
          painel.style.setProperty('--my', py + 'px');

          /* ângulo do centro até o cursor: o arco de luz na borda
             se posiciona do lado de onde a "luz" está vindo */
          const ang = Math.atan2(py - r.height / 2, px - r.width / 2) * 180 / Math.PI + 90;
          painel.style.setProperty('--ang', ang.toFixed(1) + 'deg');
        }

        /* --- atração magnética --- */
        // devolve ao lugar quem saiu do raio
        for (const el of ultimoImas) {
          el.style.setProperty('--ix', '0px');
          el.style.setProperty('--iy', '0px');
        }
        ultimoImas = [];

        // caixas remedidas só quando algo pode tê-las mudado
        if (caixasSujas) remedirImas();

        for (const m of imas) {
          if (!m.r) continue;

          const dx = ex - m.r.cx;
          const dy = ey - m.r.cy;
          const dist = Math.hypot(dx, dy);

          if (dist > RAIO_IMA + m.r.lado / 2) continue;

          const puxao = Math.max(0, 1 - dist / (RAIO_IMA * 2));
          m.el.classList.add('magnetico');
          m.el.style.setProperty('--ix', (dx * puxao * FORCA_IMA / 40).toFixed(2) + 'px');
          m.el.style.setProperty('--iy', (dy * puxao * FORCA_IMA / 40).toFixed(2) + 'px');
          ultimoImas.push(m.el);
        }
      });
    }, { passive: true });

    // ao sair da janela, tudo volta ao lugar
    document.addEventListener('pointerleave', () => {
      document.querySelectorAll('.magnetico').forEach((el) => {
        el.style.setProperty('--ix', '0px');
        el.style.setProperty('--iy', '0px');
      });
    });
  }

  /* ==========================================================
     ONDINHA DO CLIQUE
     ========================================================== */
  const SELETOR_ONDA = '.btn, .rail__item, .aba, .pilula, .acao, .btn-conexao, .conexao-flutuante, .semente, .cartao-area';

  if (!menosMovimento) {
    document.addEventListener('pointerdown', (ev) => {
      const alvo = ev.target.closest(SELETOR_ONDA);
      if (!alvo || alvo.disabled) return;

      const r = alvo.getBoundingClientRect();
      const tamanho = Math.max(r.width, r.height);

      const onda = document.createElement('span');
      onda.className = 'onda';
      onda.style.cssText =
        `width:${tamanho}px;height:${tamanho}px;` +
        `left:${ev.clientX - r.left - tamanho / 2}px;` +
        `top:${ev.clientY - r.top - tamanho / 2}px`;

      const estilo = getComputedStyle(alvo);
      if (estilo.position === 'static') alvo.style.position = 'relative';
      if (estilo.overflow === 'visible') alvo.style.overflow = 'hidden';

      alvo.appendChild(onda);
      onda.addEventListener('animationend', () => onda.remove(), { once: true });
    }, { passive: true });
  }

  /* ==========================================================
     SENHA — botão de mostrar/ocultar
     ========================================================== */
  function equiparSenhas(raiz) {
    const campos = raiz.matches?.('input[type="password"]')
      ? [raiz]
      : [...(raiz.querySelectorAll?.('input[type="password"]') || [])];

    campos.forEach((campo) => {
      if (campo.dataset.olhoPronto) return;
      campo.dataset.olhoPronto = '1';

      const caixa = campo.closest('.campo') || campo.parentElement;
      if (!caixa) return;

      caixa.classList.add('campo--senha');
      if (getComputedStyle(caixa).position === 'static') caixa.style.position = 'relative';

      const botao = document.createElement('button');
      botao.type = 'button';
      botao.className = 'olho';
      botao.setAttribute('aria-label', 'Mostrar senha');
      botao.title = 'Mostrar senha';
      botao.innerHTML = '<i class="fa-regular fa-eye"></i>';

      botao.addEventListener('click', () => {
        const revelado = campo.type === 'text';
        campo.type = revelado ? 'password' : 'text';
        botao.innerHTML = `<i class="fa-regular fa-eye${revelado ? '' : '-slash'}"></i>`;
        botao.setAttribute('aria-label', revelado ? 'Mostrar senha' : 'Ocultar senha');
        botao.title = botao.getAttribute('aria-label');

        const fim = campo.value.length;
        campo.focus();
        try { campo.setSelectionRange(fim, fim); } catch { /* type=text nem sempre aceita */ }
      });

      caixa.appendChild(botao);
    });
  }

  /* ---------- força da senha ---------- */
  const ROTULOS = ['muito fraca', 'fraca', 'razoável', 'boa', 'forte'];

  function medirForca(senha) {
    let p = 0;
    if (senha.length >= 6)  p++;
    if (senha.length >= 10) p++;
    if (/[A-Z]/.test(senha) && /[a-z]/.test(senha)) p++;
    if (/\d/.test(senha) && /[^A-Za-z0-9]/.test(senha)) p++;
    return Math.min(p, 4);
  }

  function equiparForca(raiz) {
    const sel = '#c-senha, #cfg-senha-nova, input[data-forca]';
    const campos = raiz.matches?.(sel) ? [raiz] : [...(raiz.querySelectorAll?.(sel) || [])];

    campos.forEach((campo) => {
      if (campo.dataset.forcaPronta) return;
      campo.dataset.forcaPronta = '1';

      const medidor = document.createElement('div');
      medidor.className = 'forca';
      medidor.dataset.nivel = '0';
      medidor.innerHTML = '<div class="forca__barra"><i></i></div><div class="forca__txt"></div>';
      (campo.closest('.campo') || campo.parentElement).appendChild(medidor);

      const txt = medidor.querySelector('.forca__txt');
      campo.addEventListener('input', () => {
        const nivel = campo.value ? medirForca(campo.value) : 0;
        medidor.dataset.nivel = String(nivel);
        txt.textContent = campo.value ? `Senha ${ROTULOS[nivel]}` : '';
      });
    });
  }

  /* ---------- contador de caracteres ---------- */
  function equiparContadores(raiz) {
    const sel = 'textarea[maxlength]';
    const campos = raiz.matches?.(sel) ? [raiz] : [...(raiz.querySelectorAll?.(sel) || [])];

    campos.forEach((campo) => {
      if (campo.dataset.contadorPronto) return;
      campo.dataset.contadorPronto = '1';

      const caixa = campo.closest('.campo') || campo.parentElement;
      if (!caixa) return;
      if (getComputedStyle(caixa).position === 'static') caixa.style.position = 'relative';

      const limite = Number(campo.maxLength);
      const marca = document.createElement('span');
      marca.className = 'contador';
      caixa.appendChild(marca);

      const atualizar = () => {
        const n = campo.value.length;
        marca.textContent = `${n}/${limite}`;
        marca.classList.toggle('perto', n > limite * .85 && n < limite);
        marca.classList.toggle('estourou', n >= limite);
      };
      campo.addEventListener('input', atualizar);
      atualizar();
    });
  }

  /* ==========================================================
     REVELAÇÃO NA ROLAGEM
     Entra escondido, aparece uma vez e o observador solta o
     elemento — sem custo depois disso.
     ========================================================== */
  const olheiro = window.IntersectionObserver
    ? new IntersectionObserver((entradas, obs) => {
        for (const e of entradas) {
          if (!e.isIntersecting) continue;
          e.target.classList.add('visto');
          obs.unobserve(e.target);
        }
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 })
    : null;

  /* Só elementos SEM animação de entrada própria.
     .pilar (login) e .plano (pro) já entram com keyframes; somar a
     revelação faria dois mecanismos disputarem a mesma opacity —
     e o elemento pisca ou trava no meio do caminho. */
  const SELETOR_REVELA = '.cartao, .forum-cat, .topico, .bloco-tag, .mosaico';

  /* REDE DE SEGURANÇA
     .revelar começa com opacity:0. Se o observador não disparar,
     o conteúdo sumiria de vez. Este temporizador garante que nada
     fique invisível, aconteça o que acontecer. */
  let resgate = null;
  function agendarResgate() {
    clearTimeout(resgate);
    resgate = setTimeout(() => {
      document.querySelectorAll('.revelar:not(.visto)').forEach((el) => {
        el.classList.add('visto');
        olheiro?.unobserve(el);
      });
    }, 900);
  }

  function prepararRevelacao(raiz) {
    // sem observador não escondemos nada: melhor sem animação que invisível
    if (menosMovimento || !olheiro) return;

    const alvos = raiz.matches?.(SELETOR_REVELA)
      ? [raiz]
      : [...(raiz.querySelectorAll?.(SELETOR_REVELA) || [])];

    if (!alvos.length) return;

    const alturaJanela = window.innerHeight;
    let algumEscondido = false;

    alvos.forEach((el) => {
      if (el.dataset.revelaPronto) return;
      el.dataset.revelaPronto = '1';

      /* Quem já está na tela não tem o que "revelar" ao rolar — e é
         justamente esse caso que ficaria invisível se o observador
         falhasse. Só entra escondido quem está abaixo da dobra. */
      const r = el.getBoundingClientRect();
      const jaVisivel = r.top < alturaJanela * 0.95;
      if (jaVisivel) return;

      el.classList.add('revelar');
      olheiro.observe(el);
      algumEscondido = true;
    });

    if (algumEscondido) agendarResgate();
  }

  /* ==========================================================
     CONTADORES
     Os números do perfil sobem do zero quando entram na tela.
     ========================================================== */
  function animarNumero(el) {
    const destino = parseInt(el.textContent.replace(/\D/g, ''), 10);
    if (!Number.isFinite(destino) || destino === 0) return;

    const duracao = Math.min(1100, 380 + destino * 9);
    const inicio = performance.now();

    function passo(agora) {
      const t = Math.min(1, (agora - inicio) / duracao);
      // desacelera no fim: chega no número, não passa dele
      const suave = 1 - Math.pow(1 - t, 3);
      el.textContent = Math.round(destino * suave).toLocaleString('pt-BR');
      if (t < 1) requestAnimationFrame(passo);
    }

    el.textContent = '0';
    requestAnimationFrame(passo);
  }

  const contadorOlheiro = window.IntersectionObserver
    ? new IntersectionObserver((entradas, obs) => {
        for (const e of entradas) {
          if (!e.isIntersecting) continue;
          animarNumero(e.target);
          obs.unobserve(e.target);
        }
      }, { threshold: 0.5 })
    : null;

  function prepararContadores(raiz) {
    if (!contadorOlheiro || menosMovimento) return;

    const alvos = [...(raiz.querySelectorAll?.('.numero b') || [])];
    if (raiz.matches?.('.numero b')) alvos.push(raiz);

    alvos.forEach((el) => {
      if (el.dataset.contouPronto) return;
      el.dataset.contouPronto = '1';
      contadorOlheiro.observe(el);
    });
  }

  function equiparTudo(raiz) {
    marcarLuz(raiz);
    equiparSenhas(raiz);
    equiparForca(raiz);
    equiparContadores(raiz);
    prepararRevelacao(raiz);
    prepararContadores(raiz);
  }

  equiparTudo(document.body);

  /* Processa só o que entrou — não o documento todo. */
  new MutationObserver((mutacoes) => {
    let mexeuEmImas = false;

    for (const m of mutacoes) {
      for (const no of m.addedNodes) {
        if (no.nodeType !== 1) continue;
        equiparTudo(no);
        mexeuEmImas = true;
      }
      if (m.removedNodes.length) mexeuEmImas = true;
    }

    if (mexeuEmImas) recolherImas();
  }).observe(document.body, { childList: true, subtree: true });

  /* ==========================================================
     LUPA
     ========================================================== */
  document.addEventListener('click', (ev) => {
    const img = ev.target.closest('.post__midia img');
    if (!img) return;

    const lupa = document.createElement('div');
    lupa.className = 'lupa';
    lupa.innerHTML =
      `<button class="btn btn--icone lupa__fechar" aria-label="Fechar">
         <i class="fa-solid fa-xmark"></i>
       </button>
       <img src="${img.src}" alt="${img.alt || ''}">`;

    const fechar = () => {
      lupa.style.opacity = '0';
      setTimeout(() => lupa.remove(), 180);
      document.removeEventListener('keydown', aoTeclar);
    };
    const aoTeclar = (e) => { if (e.key === 'Escape') fechar(); };

    lupa.addEventListener('click', fechar);
    document.addEventListener('keydown', aoTeclar);
    document.body.appendChild(lupa);
  });

  /* ==========================================================
     VOLTAR AO TOPO
     ========================================================== */
  if (document.querySelector('.app')) {
    const botao = document.createElement('button');
    botao.className = 'ao-topo';
    botao.setAttribute('aria-label', 'Voltar ao topo');
    botao.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
    botao.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    document.body.appendChild(botao);

    let travado = false;
    window.addEventListener('scroll', () => {
      if (travado) return;
      travado = true;
      requestAnimationFrame(() => {
        travado = false;
        botao.classList.toggle('visivel', window.scrollY > 700);
      });
    }, { passive: true });
  }

})();
