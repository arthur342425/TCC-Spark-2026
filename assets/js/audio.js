/* ============================================================
   SPARK — visualizador de áudio
   Ondas empilhadas que respondem ao som de verdade.

   Por que canvas e não elementos no DOM:
   uma faixa com 48 barrinhas seria 48 nós recebendo transform a
   cada quadro — 2.880 mutações de estilo por segundo, por faixa.
   Um canvas é uma superfície só; o traçado inteiro sai numa
   chamada e o navegador nunca precisa recalcular layout.

   O sinal vem da Web Audio API (AnalyserNode, domínio do tempo).
   Cada linha é a mesma onda com amplitude e opacidade menores,
   o que dá o efeito de fita/rastro da referência.

   Só a faixa que está tocando roda o laço de animação. As outras
   desenham uma vez e ficam paradas, sem custo nenhum.
   ============================================================ */
'use strict';

window.SparkAudio = (function () {

  const LINHAS  = 9;    // camadas empilhadas da fita
  const PONTOS  = 72;   // amostras ao longo da largura
  const SUAVIZA = 0.30; // quanto cada quadro puxa em direção ao alvo (fluidez)

  /* Cores lidas do CSS uma vez, na primeira necessidade.
     Ler variável CSS custa uma consulta de estilo; num laço de 60 fps
     isso apareceria no perfil, então fica em cache. */
  const PALETA = {
    tocadoClaro:   '#f0eee4',
    tocado:        '#dbd9cf',
    restante:      '#7f00ff',
    restanteClaro: '#a64dff',
    lido: false,
  };

  /* A paleta é cacheada, mas o tema pode mudar embaixo dela.
     Quando isso acontece o app avisa, e a próxima pintura relê. */
  document.addEventListener('spark:tema', () => {
    PALETA.lido = false;
    document.querySelectorAll('.faixa').forEach((f) => {
      if (f._spark) repintar(f);
    });
  });

  function lerPaleta() {
    if (PALETA.lido) return;
    PALETA.lido = true;

    const raiz = getComputedStyle(document.documentElement);
    const pega = (nome, padrao) => (raiz.getPropertyValue(nome) || '').trim() || padrao;

    PALETA.tocadoClaro   = pega('--ember-hi', PALETA.tocadoClaro);
    PALETA.tocado        = pega('--osso',     PALETA.tocado);
    PALETA.restante      = pega('--violeta',  PALETA.restante);
    PALETA.restanteClaro = pega('--spark-hi', PALETA.restanteClaro);
  }

  const menosMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let contexto = null;
  const fontes = new WeakMap();   // <audio> → MediaElementSourceNode (só pode existir um)
  let atual = null;               // faixa tocando agora
  let laco = null;

  /* ---------------------------------------------------------
     Contexto de áudio: um só para a página inteira.
     Criado no primeiro clique — navegador não deixa antes disso.
     --------------------------------------------------------- */
  function garantirContexto() {
    if (!contexto) {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return null;
      contexto = new AC();
    }
    if (contexto.state === 'suspended') contexto.resume();
    return contexto;
  }

  /* ---------------------------------------------------------
     Estado por faixa
     --------------------------------------------------------- */
  function estado(faixa) {
    if (faixa._spark) return faixa._spark;

    const canvas = faixa.querySelector('.faixa__onda canvas');
    const s = {
      faixa,
      canvas,
      ctx2d: canvas.getContext('2d', { alpha: true }),
      audio: null,
      analisador: null,
      dados: null,
      // valores desenhados x valores alvo: a diferença é o que suaviza
      atual: new Float32Array(PONTOS).fill(0),
      alvo:  new Float32Array(PONTOS).fill(0),
      fase: Math.random() * Math.PI * 2,
      progresso: 0,
      largura: 0,
      altura: 0,
      dpr: 1,
    };

    // detecção de batida
    s.espectro = null;
    s.historico = new Float32Array(60).fill(0);  // ~1 s de energia grave a 60 fps
    s.hIndice = 0;
    s.hCheio = false;
    s.beat = 0;              // 1 no golpe, decai a cada quadro
    s.ultimaBatida = 0;
    s.intervalos = [];       // para estimar o andamento
    s.bpm = 0;

    faixa._spark = s;
    medir(s);

    // o canvas acompanha o tamanho do contêiner sem precisar de listener de resize
    if (window.ResizeObserver) {
      new ResizeObserver(() => { medir(s); if (atual !== faixa) desenhar(s, true); })
        .observe(canvas.parentElement);
    }

    return s;
  }

  function medir(s) {
    const caixa = s.canvas.parentElement.getBoundingClientRect();
    if (!caixa.width) return;

    // 2x já é suficiente; acima disso é desperdício puro
    s.dpr = Math.min(window.devicePixelRatio || 1, 2);
    s.largura = caixa.width;
    s.altura  = caixa.height;

    s.canvas.width  = Math.round(caixa.width  * s.dpr);
    s.canvas.height = Math.round(caixa.height * s.dpr);
    s.canvas.style.width  = caixa.width + 'px';
    s.canvas.style.height = caixa.height + 'px';

    s.ctx2d.setTransform(s.dpr, 0, 0, s.dpr, 0, 0);
  }

  /* ---------------------------------------------------------
     Liga o elemento <audio> ao analisador
     --------------------------------------------------------- */
  function conectar(s) {
    if (s.analisador) return true;

    const ac = garantirContexto();
    if (!ac) return false;

    try {
      let fonte = fontes.get(s.audio);
      if (!fonte) {
        // createMediaElementSource só pode ser chamado UMA vez por elemento
        fonte = ac.createMediaElementSource(s.audio);
        fontes.set(s.audio, fonte);
      }

      const analisador = ac.createAnalyser();
      analisador.fftSize = 1024;
      /* Suavização baixa de propósito: ela só afeta getByteFrequencyData,
         e é justamente ali que procuramos o transiente do bumbo. Com 0.75
         o pico do golpe é achatado e a batida passa despercebida.
         A forma de onda usa o domínio do tempo, que é sempre cru. */
      analisador.smoothingTimeConstant = 0.25;

      fonte.connect(analisador);
      analisador.connect(ac.destination);

      s.analisador = analisador;
      s.dados = new Uint8Array(analisador.fftSize);
      s.espectro = new Uint8Array(analisador.frequencyBinCount);
      return true;

    } catch (e) {
      // sem analisador o áudio ainda toca; a onda só fica decorativa
      console.warn('[spark][audio] analisador indisponível:', e.message);
      return false;
    }
  }

  /* ---------------------------------------------------------
     Amostragem — traduz o som em PONTOS alturas
     --------------------------------------------------------- */
  function amostrar(s) {
    if (s.analisador) {
      s.analisador.getByteTimeDomainData(s.dados);

      const passo = Math.floor(s.dados.length / PONTOS);
      for (let i = 0; i < PONTOS; i++) {
        // pico da janela: mais expressivo que a média para forma de onda
        let pico = 0;
        const ini = i * passo;
        for (let j = 0; j < passo; j++) {
          const v = Math.abs(s.dados[ini + j] - 128) / 128;
          if (v > pico) pico = v;
        }
        // leve realce nas bordas para a fita não morrer nas pontas
        const borda = Math.sin((i / (PONTOS - 1)) * Math.PI);
        s.alvo[i] = Math.min(1, pico * 1.9) * (0.35 + borda * 0.65);
      }
    } else {
      // sem Web Audio: onda sintética, ainda ritmada pelo tempo do áudio
      const t = (s.audio ? s.audio.currentTime : performance.now() / 1000) * 2.2;
      for (let i = 0; i < PONTOS; i++) {
        const x = i / (PONTOS - 1);
        const v = Math.sin(x * 7 + t) * 0.5 + Math.sin(x * 13 - t * 1.6) * 0.3;
        s.alvo[i] = (0.32 + Math.abs(v) * 0.55) * Math.sin(x * Math.PI);
      }
    }
  }

  /* ---------------------------------------------------------
     DETECÇÃO DE BATIDA
     Compara a energia das frequências graves (onde mora o bumbo)
     com a média do último segundo. Quando o instante supera bem a
     média, é um golpe. Um intervalo mínimo evita contar o mesmo
     bumbo duas vezes; a mediana dos intervalos vira o andamento.
     --------------------------------------------------------- */
  function detectarBatida(s, agora) {
    if (!s.analisador || !s.espectro) return;

    s.analisador.getByteFrequencyData(s.espectro);

    // bins 1..10 do FFT de 1024 ≈ 20–220 Hz numa taxa de 44,1 kHz
    let energia = 0;
    for (let i = 1; i <= 10; i++) energia += s.espectro[i] * s.espectro[i];
    energia /= 10 * 255 * 255;

    const n = s.hCheio ? s.historico.length : Math.max(1, s.hIndice);

    let media = 0;
    for (let i = 0; i < n; i++) media += s.historico[i];
    media /= n;

    let variancia = 0;
    for (let i = 0; i < n; i++) variancia += (s.historico[i] - media) ** 2;
    variancia /= n;
    const desvio = Math.sqrt(variancia);

    /* Limiar em desvios padrão, não em porcentagem da média.
       Assim funciona igual numa faixa comprimida (pouca variação) e
       numa gravação dinâmica: o que conta é o quanto este instante
       destoa do próprio material, não um número fixo. */
    const limiar = media + desvio * 1.45;

    if (s.hCheio && energia > limiar && energia > media * 1.12
        && energia > 0.004 && agora - s.ultimaBatida > 210) {
      s.beat = 1;

      if (s.ultimaBatida) {
        const intervalo = agora - s.ultimaBatida;
        if (intervalo > 250 && intervalo < 1400) {
          s.intervalos.push(intervalo);
          if (s.intervalos.length > 12) s.intervalos.shift();

          // mediana resiste melhor a batidas perdidas que a média
          const ord = [...s.intervalos].sort((a, b) => a - b);
          const mediana = ord[Math.floor(ord.length / 2)];

          /* Dobra o valor até cair na faixa musical usual.
             O detector marca bumbo E caixa/hat, então costuma medir o
             dobro do andamento real — 236 em vez de 118. Todo detector
             sério faz esse dobramento antes de mostrar o número. */
          let bpm = 60000 / mediana;
          // limite alto em 190 e não 180: senão um trap a 180 exatos
          // seria dobrado para 90, que soaria errado para o produtor
          while (bpm > 190) bpm /= 2;
          while (bpm < 70)  bpm *= 2;
          s.bpm = Math.round(bpm);
        }
      }
      s.ultimaBatida = agora;
    }

    s.historico[s.hIndice] = energia;
    s.hIndice = (s.hIndice + 1) % s.historico.length;
    if (s.hIndice === 0) s.hCheio = true;

    // decaimento: o golpe some em ~200 ms
    s.beat *= 0.86;
    if (s.beat < 0.01) s.beat = 0;
  }

  /** Onda parada, para quando a faixa não está tocando. */
  function amostrarRepouso(s) {
    for (let i = 0; i < PONTOS; i++) {
      const x = i / (PONTOS - 1);
      const v = Math.sin(x * 6 + s.fase) * 0.5 + Math.sin(x * 11 + s.fase * 1.7) * 0.28;
      s.alvo[i] = (0.12 + Math.abs(v) * 0.16) * Math.sin(x * Math.PI);
    }
  }

  /* ---------------------------------------------------------
     Desenho
     --------------------------------------------------------- */
  function desenhar(s, instantaneo = false) {
    const { ctx2d: c, largura: L, altura: A } = s;
    if (!L || !A) return;
    lerPaleta();

    // aproxima o desenhado do alvo — é daqui que vem a fluidez
    const k = instantaneo ? 1 : SUAVIZA;
    for (let i = 0; i < PONTOS; i++) {
      s.atual[i] += (s.alvo[i] - s.atual[i]) * k;
    }

    c.clearRect(0, 0, L, A);

    const meio = A / 2;
    const px = L / (PONTOS - 1);
    const corte = s.progresso * L;   // até onde já tocou

    /* Gradiente na paleta do site: osso no que já tocou, violeta no
       que falta. As cores saem das variáveis CSS em vez de estarem
       cravadas aqui — assim trocar o tema troca a onda junto, e não
       existe uma segunda paleta escondida no JavaScript. */
    const grad = c.createLinearGradient(0, 0, L, 0);
    const p = Math.max(0.001, Math.min(0.999, s.progresso));
    grad.addColorStop(0, PALETA.tocadoClaro);
    grad.addColorStop(Math.max(0, p - 0.02), PALETA.tocado);
    grad.addColorStop(Math.min(1, p + 0.02), PALETA.restante);
    grad.addColorStop(1, PALETA.restanteClaro);

    c.lineCap = 'round';
    c.lineJoin = 'round';

    // no golpe a fita inteira abre; entre golpes ela recolhe
    const pulso = 1 + (s.beat || 0) * 0.28;

    for (let linha = 0; linha < LINHAS; linha++) {
      const t = linha / (LINHAS - 1);

      // cada camada é menor e mais apagada: dá profundidade à fita
      const amplitude = (A * 0.42) * (1 - t * 0.62) * pulso;
      const alfa = ((1 - t) * 0.72 + 0.06) * (1 + (s.beat || 0) * 0.35);
      const desloc = (t - 0.5) * A * 0.10;

      c.beginPath();
      for (let i = 0; i < PONTOS; i++) {
        const x = i * px;
        // deslocamento de fase por camada = o rastro que abre em leque
        const onda = s.atual[i] * Math.cos(t * 1.5 + i * 0.09 + s.fase * 0.35);
        const y = meio + desloc + onda * amplitude;

        if (i === 0) { c.moveTo(x, y); continue; }

        // curva suave entre pontos médios: sem bicos
        const xa = (i - 1) * px;
        const ondaA = s.atual[i - 1] * Math.cos(t * 1.5 + (i - 1) * 0.09 + s.fase * 0.35);
        const ya = meio + desloc + ondaA * amplitude;
        c.quadraticCurveTo(xa, ya, (xa + x) / 2, (ya + y) / 2);
      }

      // brilho barato: um traço largo e translúcido embaixo do fino
      c.globalAlpha = alfa * 0.22;
      c.lineWidth = 5 - t * 3;
      c.strokeStyle = grad;
      c.stroke();

      c.globalAlpha = alfa;
      c.lineWidth = 1.6 - t * 0.9;
      c.stroke();
    }

    // cabeça de leitura
    if (s.progresso > 0 && s.progresso < 1) {
      c.globalAlpha = 0.5;
      c.lineWidth = 1;
      c.strokeStyle = PALETA.tocadoClaro;
      c.beginPath();
      c.moveTo(corte, A * 0.12);
      c.lineTo(corte, A * 0.88);
      c.stroke();
    }

    c.globalAlpha = 1;
  }

  /* ---------------------------------------------------------
     Laço de animação — existe só enquanto algo toca
     --------------------------------------------------------- */
  function quadro(agora) {
    if (!atual) { laco = null; return; }

    const s = atual._spark;

    detectarBatida(s, agora || performance.now());

    // a fita corre mais rápido quando a música está acelerada
    s.fase += 0.022 + (s.beat || 0) * 0.05;

    if (s.audio && s.audio.duration) {
      s.progresso = s.audio.currentTime / s.audio.duration;
    }

    amostrar(s);
    desenhar(s);

    // nível médio e batida alimentam o CSS: halo, brilho e pulso
    let soma = 0;
    for (let i = 0; i < PONTOS; i++) soma += s.atual[i];

    const estilo = s.faixa.style;
    estilo.setProperty('--nivel', (soma / PONTOS).toFixed(3));
    estilo.setProperty('--beat', s.beat.toFixed(3));

    if (s.bpm && s.quadros % 12 === 0) {
      const alvo = s.faixa.querySelector('.faixa__bpm');
      if (alvo) alvo.textContent = `${s.bpm} BPM`;
    }
    s.quadros = (s.quadros || 0) + 1;

    if (s.quadros % 6 === 0) atualizarTempo(s);   // 10x/s basta para o relógio

    laco = requestAnimationFrame(quadro);
  }

  function iniciarLaco() {
    if (!laco) laco = requestAnimationFrame(quadro);
  }

  function pararLaco() {
    if (laco) { cancelAnimationFrame(laco); laco = null; }
  }

  /* ---------------------------------------------------------
     Tempo
     --------------------------------------------------------- */
  function mmss(seg) {
    if (!isFinite(seg)) return '--:--';
    const m = Math.floor(seg / 60);
    const s = Math.floor(seg % 60);
    return `${m}:${String(s).padStart(2, '0')}`;
  }

  function atualizarTempo(s) {
    const el = s.faixa.querySelector('.faixa__tempo');
    if (el && s.audio) {
      el.textContent = `${mmss(s.audio.currentTime)} / ${mmss(s.audio.duration)}`;
    }
  }

  /* ---------------------------------------------------------
     Controle
     --------------------------------------------------------- */
  function pausar(faixa) {
    const s = faixa._spark;
    if (!s || !s.audio) return;

    s.audio.pause();
    faixa.classList.remove('tocando');
    trocarIcone(faixa, false);

    if (atual === faixa) {
      atual = null;
      pararLaco();
      faixa.style.setProperty('--nivel', '0');
      faixa.style.setProperty('--beat', '0');
      s.beat = 0;
      // o andamento é reestimado na próxima reprodução
      s.intervalos = [];
      s.ultimaBatida = 0;
    }
    atualizarTempo(s);
  }

  function trocarIcone(faixa, tocando) {
    const i = faixa.querySelector('.faixa__play i');
    if (i) i.className = `fa-solid fa-${tocando ? 'pause' : 'play'}`;
  }

  function tocar(faixa) {
    const s = estado(faixa);

    if (!s.audio) {
      s.audio = new Audio();
      s.audio.preload = 'metadata';
      s.audio.crossOrigin = 'anonymous';
      s.audio.src = faixa.dataset.audio;

      s.audio.addEventListener('loadedmetadata', () => atualizarTempo(s));
      s.audio.addEventListener('ended', () => {
        s.progresso = 0;
        pausar(faixa);
        desenhar(s, true);
      });
      s.audio.addEventListener('error', () => {
        faixa.classList.remove('tocando');
        trocarIcone(faixa, false);
        window.SparkToast?.('Não consegui tocar esse áudio.', 'erro');
      });

      // o <audio> nasce depois do controle: herda o volume já escolhido
      s.audio.volume = volumeValido(
        faixa.querySelector('.faixa__volume')?.dataset.volume ?? volumeGuardado, volumeGuardado
      ) / 100;
    }

    // uma faixa por vez
    if (atual && atual !== faixa) pausar(atual);

    conectar(s);

    s.audio.play().then(() => {
      atual = faixa;
      faixa.classList.add('tocando');
      trocarIcone(faixa, true);
      if (!menosMovimento) iniciarLaco();
    }).catch(() => {
      window.SparkToast?.('O navegador bloqueou a reprodução.', 'erro');
    });
  }

  function alternar(faixa) {
    const s = faixa._spark;
    if (s && s.audio && !s.audio.paused) pausar(faixa);
    else tocar(faixa);
  }

  /** Clique na onda pula para aquele ponto. */
  function buscar(faixa, ev) {
    const s = estado(faixa);
    if (!s.audio || !s.audio.duration) return;

    const caixa = s.canvas.getBoundingClientRect();
    const razao = Math.max(0, Math.min(1, (ev.clientX - caixa.left) / caixa.width));

    s.audio.currentTime = razao * s.audio.duration;
    s.progresso = razao;

    if (s.audio.paused) desenhar(s, true);
    atualizarTempo(s);
  }

  /* ---------------------------------------------------------
     Preparo inicial: desenha a onda de repouso uma única vez
     --------------------------------------------------------- */
  function prepararVisiveis() {
    document.querySelectorAll('.faixa:not([data-pronta])').forEach((faixa) => {
      const canvas = faixa.querySelector('.faixa__onda canvas');
      if (!canvas) return;

      faixa.dataset.pronta = '1';
      const s = estado(faixa);
      amostrarRepouso(s);
      desenhar(s, true);

      // o volume lembrado vale para toda faixa nova
      if (faixa.querySelector('.faixa__volume')) aplicarVolume(faixa, volumeGuardado, false);
    });
  }

  /* ---------------------------------------------------------
     Eventos (delegados: valem para o que ainda nem foi criado)
     --------------------------------------------------------- */
  document.addEventListener('click', (ev) => {
    const faixa = ev.target.closest('.faixa');
    if (!faixa) return;

    if (ev.target.closest('.faixa__play')) { alternar(faixa); return; }

    /* pular ±10s. Se nada foi carregado ainda, começa a tocar —
       pular numa faixa parada não teria sentido. */
    const pular = ev.target.closest('[data-pular]');
    if (pular) {
      const s = faixa._spark;
      if (!s || !s.audio) { alternar(faixa); return; }

      const salto = Number(pular.dataset.pular);
      s.audio.currentTime = Math.max(0, Math.min(s.audio.duration || 0, s.audio.currentTime + salto));
      s.progresso = s.audio.duration ? s.audio.currentTime / s.audio.duration : 0;
      atualizarTempo(s);
      if (s.audio.paused) desenhar(s, true);
      return;
    }

    if (ev.target.closest('.faixa__volume')) return;   // o volume tem tratamento próprio

    if (ev.target.closest('.faixa__onda')) {
      const s = faixa._spark;
      if (s && s.audio) buscar(faixa, ev);
      else alternar(faixa);
    }
  });

  /* ---------------------------------------------------------
     VOLUME
     Um controle por faixa, com arrasto e teclado. O valor é
     lembrado entre faixas: quem baixou o volume não quer que a
     próxima venha no talo.
     --------------------------------------------------------- */
  /* Sanidade: um valor inválido aqui vira `audio.volume = NaN`, que o
     navegador recusa com exceção — e como o valor é persistido, o defeito
     sobreviveria ao recarregamento e mudo toda faixa seguinte.
     Atenção: `null` e `''` viram 0 num Number() distraído — e o site
     abriria mudo para quem nunca tocou no controle. Ausência ≠ zero. */
  const volumeValido = (n, padrao = 80) => {
    if (n === null || n === undefined || n === '') return padrao;
    const x = Number(n);
    return Number.isFinite(x) ? Math.max(0, Math.min(100, Math.round(x))) : padrao;
  };

  let volumeGuardado = volumeValido(localStorage.getItem('spark:volume'));

  function aplicarVolume(faixa, pct, guardar = true) {
    // largura zero (controle oculto no celular) daria divisão 0/0
    if (!Number.isFinite(pct)) return;
    const v = volumeValido(pct);
    const miolo = faixa.querySelector('.faixa__volume-miolo');
    const num   = faixa.querySelector('.faixa__volume-num');
    const icone = faixa.querySelector('.faixa__volume i.fa-solid');
    const caixa = faixa.querySelector('.faixa__volume');

    if (miolo) miolo.style.width = v + '%';
    if (num) num.textContent = v;
    if (caixa) {
      caixa.dataset.volume = v;
      caixa.setAttribute('aria-valuenow', v);
    }
    if (icone) {
      icone.className = 'fa-solid fa-volume-' + (v === 0 ? 'xmark' : v < 45 ? 'low' : 'high');
    }

    const s = faixa._spark;
    if (s && s.audio) s.audio.volume = v / 100;

    if (guardar) {
      volumeGuardado = v;
      localStorage.setItem('spark:volume', String(v));
    }
  }

  document.addEventListener('pointerdown', (ev) => {
    const caixa = ev.target.closest('.faixa__volume');
    if (!caixa) return;

    const faixa = caixa.closest('.faixa');
    caixa.classList.add('arrastando');
    caixa.setPointerCapture(ev.pointerId);

    const ajustar = (x) => {
      const r = caixa.getBoundingClientRect();
      if (!r.width) return;
      aplicarVolume(faixa, ((x - r.left) / r.width) * 100);
    };
    ajustar(ev.clientX);

    const mover = (e) => ajustar(e.clientX);
    const soltar = () => {
      caixa.classList.remove('arrastando');
      caixa.removeEventListener('pointermove', mover);
      caixa.removeEventListener('pointerup', soltar);
      caixa.removeEventListener('pointercancel', soltar);
    };
    caixa.addEventListener('pointermove', mover);
    caixa.addEventListener('pointerup', soltar);
    caixa.addEventListener('pointercancel', soltar);
  });

  // setas do teclado, para quem não usa mouse
  document.addEventListener('keydown', (ev) => {
    const caixa = ev.target.closest?.('.faixa__volume');
    if (!caixa) return;

    const passo = ev.key === 'ArrowRight' || ev.key === 'ArrowUp' ? 5
                : ev.key === 'ArrowLeft'  || ev.key === 'ArrowDown' ? -5 : 0;
    if (!passo) return;

    ev.preventDefault();
    aplicarVolume(caixa.closest('.faixa'), volumeValido(caixa.dataset.volume) + passo);
  });

  /* O feed monta faixas depois. Agrupamos num timeout em vez de
     requestAnimationFrame: rAF não dispara com a aba em segundo plano,
     e aí a faixa ficaria em branco até o usuário voltar. */
  let pendente = false;
  new MutationObserver(() => {
    if (pendente) return;
    pendente = true;
    setTimeout(() => { pendente = false; prepararVisiveis(); }, 0);
  }).observe(document.body, { childList: true, subtree: true });

  /** Um quadro avulso: usado ao voltar do segundo plano e nos testes. */
  function repintar(faixa) {
    const s = estado(faixa);
    if (faixa.classList.contains('tocando')) amostrar(s);
    else amostrarRepouso(s);
    desenhar(s, true);
  }

  // nada de laço rodando com a aba escondida
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      pararLaco();
    } else if (atual) {
      repintar(atual);   // volta já com a onda no lugar certo
      iniciarLaco();
    }
  });

  prepararVisiveis();

  return { tocar, pausar, alternar, prepararVisiveis, repintar };
})();
