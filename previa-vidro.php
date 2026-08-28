<?php
/**
 * SPARK — galeria de vidro
 *
 * Página de consulta, fora do app. Reúne várias interpretações de
 * Liquid Glass nos mesmos componentes, sobre fundos trocáveis —
 * que é o único jeito honesto de julgar material translúcido.
 * Nada aqui está aplicado no site; serve para escolher primeiro.
 */

declare(strict_types=1);
require __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-tema="escuro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Spark — galeria de vidro</title>
<link rel="icon" href="<?= e(logo_favicon()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..800&family=Inter:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= fontes_locais() ?><link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">

<style>
/* ============================================================
   FUNDOS TROCÁVEIS
   Vidro só existe em relação ao que está atrás. Três cenários,
   um botão para alternar.
   ============================================================ */
body { overflow-x: hidden; }

.cena { position: fixed; inset: -20%; z-index: -2; overflow: hidden; transition: background .5s; }

/* cinza neutro — o fundo da referência do interruptor */
.cena[data-fundo="cinza"] { background: linear-gradient(150deg, #9a9ba1, #7e8087 55%, #6f7178); }
.cena[data-fundo="cinza"] .faixa, .cena[data-fundo="cinza"] .trama { display: none; }

/* trama escura — o fundo da referência da barra "Ask AI" */
.cena[data-fundo="trama"] { background: #0b0b0d; }
.cena[data-fundo="trama"] .faixa { display: none; }
.trama {
  position: absolute; inset: 0; display: none;
  background-image:
    repeating-linear-gradient(90deg, rgba(255,255,255,.10) 0 1px, transparent 1px 5px),
    repeating-linear-gradient(0deg,  rgba(255,255,255,.07) 0 1px, transparent 1px 5px);
  mask-image: radial-gradient(120% 90% at 50% 40%, #000 10%, transparent 78%);
}
.cena[data-fundo="trama"] .trama { display: block; }

/* faixas violeta — o fundo do próprio Spark */
.cena[data-fundo="spark"] { background: #0a0a0c; }
.faixa {
  position: absolute; top: -30%; height: 160%;
  border-radius: 999px; transform: rotate(24deg); filter: blur(2px);
  animation: correr 26s linear infinite;
}
@keyframes correr {
  from { transform: rotate(24deg) translateX(-14vw); }
  to   { transform: rotate(24deg) translateX(14vw); }
}

/* ============================================================
   VARIANTE 1 — LENTE
   A reprodução fiel do interruptor da referência.

   O que faz a peça funcionar não é o desfoque: é a GEOMETRIA da
   luz. Uma esfera de vidro capta o ambiente em dois arcos opostos
   — um no alto, onde a luz entra, outro embaixo, onde ela sai
   depois de atravessar. O miolo fica mais limpo que as bordas,
   porque é ali que o vidro é mais fino em relação ao olhar.

   E o botão é MAIOR que a trilha, transbordando por cima dela.
   Esse detalhe é metade do efeito: mostra que a lente tem volume
   próprio, em vez de estar encaixada num sulco.
   ============================================================ */
.interruptor {
  position: relative;
  display: inline-flex;
  align-items: center;
  padding: 0;
  border: 0;
  background: none;
  cursor: pointer;
  filter: drop-shadow(0 18px 34px rgba(0,0,0,.34));
}

.interruptor__trilha {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  width: 200px;
  height: 68px;
  padding: 0 30px 0 74px;
  border-radius: 999px;
  background: linear-gradient(160deg, rgba(255,255,255,.26), rgba(255,255,255,.11));
  backdrop-filter: blur(10px) brightness(1.04);
  -webkit-backdrop-filter: blur(10px) brightness(1.04);
  box-shadow:
    inset 0 1px 1px rgba(255,255,255,.5),
    inset 0 -1px 2px rgba(0,0,0,.10),
    0 0 26px rgba(255,255,255,.14);
}

.interruptor__rotulo {
  font-family: var(--font-entrada);
  font-size: 27px;
  font-weight: 400;
  color: #fff;
  letter-spacing: .005em;
  text-shadow: 0 0 14px rgba(255,255,255,.45);
}

/* a lente */
.interruptor__lente {
  position: absolute;
  left: -6px;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  display: grid;
  place-items: center;

  /* o vidro em si: refrata e clareia um pouco o que passa */
  backdrop-filter: blur(3px) brightness(1.1) saturate(1.05);
  -webkit-backdrop-filter: blur(3px) brightness(1.1) saturate(1.05);
  background: radial-gradient(circle at 50% 46%, rgba(255,255,255,.14), rgba(255,255,255,.05) 58%, rgba(255,255,255,.12));

  box-shadow:
    /* espessura da parede de vidro */
    inset 0 0 0 1px rgba(255,255,255,.30),
    inset 0 0 22px rgba(255,255,255,.16),
    inset 0 0 40px rgba(0,0,0,.06),
    /* a lente projeta sombra na trilha, atrás dela */
    6px 10px 22px rgba(0,0,0,.20);

  transition: transform .55s cubic-bezier(.34, 1.4, .5, 1);
}

/* arco de luz de cima: a entrada do feixe */
.interruptor__lente::before {
  content: '';
  position: absolute;
  inset: 3px;
  border-radius: 50%;
  background: conic-gradient(from 186deg,
      transparent 0deg,
      rgba(255,255,255,.85) 42deg,
      rgba(255,255,255,.25) 76deg,
      transparent 104deg);
  -webkit-mask: radial-gradient(circle, transparent 60%, #000 68%);
          mask: radial-gradient(circle, transparent 60%, #000 68%);
  filter: blur(1.5px);
  pointer-events: none;
}

/* arco de baixo: a saída, depois de atravessar o vidro */
.interruptor__lente::after {
  content: '';
  position: absolute;
  inset: 3px;
  border-radius: 50%;
  background: conic-gradient(from 8deg,
      transparent 0deg,
      rgba(255,255,255,.70) 40deg,
      rgba(255,255,255,.18) 72deg,
      transparent 96deg);
  -webkit-mask: radial-gradient(circle, transparent 62%, #000 70%);
          mask: radial-gradient(circle, transparent 62%, #000 70%);
  filter: blur(1.5px);
  pointer-events: none;
}

.interruptor__sol {
  position: relative;
  z-index: 2;
  width: 34px; height: 34px;
  color: #fff;
  filter: drop-shadow(0 0 8px rgba(255,255,255,.6));
  transition: transform .55s cubic-bezier(.34, 1.4, .5, 1);
}

.interruptor[aria-checked="true"] .interruptor__lente { transform: translateX(118px); }
.interruptor[aria-checked="true"] .interruptor__sol { transform: rotate(140deg); }
.interruptor[aria-checked="true"] .interruptor__trilha { justify-content: flex-start; padding: 0 74px 0 30px; }

/* ============================================================
   VARIANTE 2 — BARRA (a referência "Ask AI")
   Vidro quase preto, quase sem cor. O que o define é o fio de
   contorno claríssimo e um clarão interno bem discreto no centro.
   Nada de saturação: aqui o vidro é fumê.
   ============================================================ */
.barra {
  display: flex;
  align-items: center;
  gap: 18px;
  width: min(560px, 100%);
  padding: 20px 26px;
  border-radius: 999px;
  background:
    radial-gradient(120% 140% at 50% 130%, rgba(255,255,255,.10), transparent 60%),
    linear-gradient(180deg, rgba(28,28,30,.86), rgba(12,12,14,.92));
  backdrop-filter: blur(26px) saturate(120%);
  -webkit-backdrop-filter: blur(26px) saturate(120%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.16),
    inset 0 1px 0 rgba(255,255,255,.26),
    0 20px 44px rgba(0,0,0,.6);
  color: #e8e8ea;
}
.barra input {
  flex: 1; min-width: 0;
  background: none; border: 0; outline: none;
  font-family: var(--font-entrada); font-size: 18px; color: #e8e8ea;
}
.barra input::placeholder { color: rgba(232,232,234,.5); }
.barra i { font-size: 19px; opacity: .82; }
.barra .mais { font-size: 26px; opacity: .9; }

.fechar-barra {
  width: 68px; height: 68px;
  border-radius: 50%;
  display: grid; place-items: center;
  color: #e8e8ea; font-size: 19px;
  background: linear-gradient(180deg, rgba(28,28,30,.86), rgba(12,12,14,.92));
  backdrop-filter: blur(26px);
  -webkit-backdrop-filter: blur(26px);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.16), 0 20px 44px rgba(0,0,0,.6);
}

/* ============================================================
   VARIANTE 3 — PROFUNDO (o widget de voo)
   Desfoque forte e saturação alta: o fundo perde a forma e vira
   mancha de cor dentro do painel.
   ============================================================ */
.v-profundo {
  position: relative;
  border-radius: 28px;
  isolation: isolate;
  overflow: hidden;
  background:
    radial-gradient(130% 90% at 10% -8%, rgba(255,255,255,.17), transparent 56%),
    linear-gradient(160deg, rgba(255,255,255,.075), rgba(255,255,255,.022) 48%, rgba(255,255,255,.05));
  backdrop-filter: blur(40px) saturate(200%) brightness(1.06);
  -webkit-backdrop-filter: blur(40px) saturate(200%) brightness(1.06);
  box-shadow:
    inset 0  1px 0 0 rgba(255,255,255,.30),
    inset 0  0 0 1px rgba(255,255,255,.055),
    inset 0 -26px 44px -34px rgba(0,0,0,.85),
    0 26px 60px -22px rgba(0,0,0,.72);
}
.v-profundo::after {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  opacity: .05; mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}

/* ============================================================
   VARIANTE 4 — LENTE APLICADA
   O princípio do interruptor levado ao painel: parede de vidro
   com brilho nas duas bordas opostas, miolo limpo, desfoque médio.
   ============================================================ */
.v-lente {
  position: relative;
  border-radius: 26px;
  background: linear-gradient(158deg, rgba(255,255,255,.10), rgba(255,255,255,.035) 55%, rgba(255,255,255,.075));
  backdrop-filter: blur(22px) saturate(150%) brightness(1.05);
  -webkit-backdrop-filter: blur(22px) saturate(150%) brightness(1.05);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.16),
    inset 0 2px 2px rgba(255,255,255,.34),
    inset 0 -3px 4px rgba(255,255,255,.16),
    inset 0 0 34px rgba(0,0,0,.10),
    0 20px 44px -16px rgba(0,0,0,.55);
}

/* ============================================================
   VARIANTE 5 — FINO (o que o site usa hoje)
   ============================================================ */
.v-fino {
  position: relative;
  border-radius: var(--r);
  background:
    linear-gradient(158deg, rgba(255,255,255,.10) 0%, rgba(255,255,255,.035) 20%, transparent 44%),
    linear-gradient(155deg, rgba(219,217,207,.055), rgba(219,217,207,.022) 62%);
  backdrop-filter: blur(14px) saturate(155%);
  -webkit-backdrop-filter: blur(14px) saturate(155%);
  box-shadow:
    inset 0  1px 0 0 var(--borda-luz),
    inset 0 -1px 0 0 rgba(0,0,0,.35),
    inset 0 -14px 22px -18px rgba(0,0,0,.5),
    var(--sombra-2);
}

/* ------------------------------------------------------------
   Ângulos animáveis. Sem registrar, o navegador não sabe
   interpolar um <angle> guardado em variável — o giro pularia
   de 0 a 360 em vez de correr.
   ------------------------------------------------------------ */
@property --giro-iris { syntax: '<angle>'; inherits: false; initial-value: 0deg; }
@property --giro-caust { syntax: '<angle>'; inherits: false; initial-value: 0deg; }

/* ============================================================
   ANEL ESPECTRAL — uma implementação, três usos

   Vidro com película fina devolve cores diferentes conforme o
   ângulo de visão. Aqui o ângulo gira sozinho, e o resultado é um
   arco de cor percorrendo a borda.

   Duas camadas em vez de uma, de propósito:
     · a de baixo gira devagar e vive acesa, discreta
     · a de cima gira rápido e só aparece no hover

   Poderia ser uma camada só com a duração mudando no hover — mas
   trocar animation-duration no meio faz o ângulo saltar. Com duas
   camadas, o que muda é opacidade, e a aceleração parece contínua.

   Herda o border-radius de quem a hospeda, então serve em pílula,
   círculo ou cartão sem ajuste.
   ============================================================ */
.espectral { position: relative; }

.espectral::before,
.espectral::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  padding: var(--iris-fio, 1.4px);
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
          mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
          mask-composite: exclude;
  pointer-events: none;
  z-index: 3;
}

/* camada lenta, sempre acesa */
.espectral::before {
  background: conic-gradient(from var(--giro-iris),
      rgba(127,0,255,.10)  0deg,
      rgba(166,77,255,.85) 54deg,
      rgba(219,217,207,1)  104deg,
      rgba(167,90,255,.9)  148deg,
      rgba(127,0,255,.10)  228deg,
      rgba(127,0,255,.10)  360deg);
  opacity: var(--iris-base, .62);
  animation: girar-iris 7s linear infinite;
  transition: opacity .45s var(--suave);
}

/* camada rápida, só no hover */
.espectral::after {
  background: conic-gradient(from calc(var(--giro-iris) * -1.9),
      transparent 0deg,
      rgba(219,217,207,.95) 30deg,
      rgba(166,77,255,.9)   70deg,
      transparent 120deg,
      transparent 360deg);
  opacity: 0;
  animation: girar-iris 2.4s linear infinite;
  transition: opacity .4s var(--suave);
}

.espectral:hover::before { opacity: 1; }
.espectral:hover::after  { opacity: .9; }

@media (prefers-reduced-motion: reduce) {
  .espectral::before, .espectral::after { animation: none; }
}

/* ============================================================
   VARIANTE 5 — DESLIZANTE
   A linha "Volume" da referência. O mesmo princípio da lente:
   o miolo é uma peça de vidro MAIOR que a trilha, transbordando
   por cima. Ele clareia o que passa por baixo e arrasta consigo
   um rastro de luz — é o que dá a sensação de estar deslocando
   matéria, não movendo um retângulo.
   ============================================================ */
.deslizante {
  position: relative;
  display: flex;
  align-items: center;
  gap: 16px;
  width: min(420px, 100%);
  height: 62px;
  padding: 0 26px;
  border-radius: 999px;
  background: linear-gradient(180deg, rgba(255,255,255,.075), rgba(255,255,255,.03));
  backdrop-filter: blur(20px) saturate(140%);
  -webkit-backdrop-filter: blur(20px) saturate(140%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.10),
    inset 0 1px 0 rgba(255,255,255,.22),
    0 14px 32px rgba(0,0,0,.42);
  cursor: ew-resize;
  user-select: none;
  touch-action: none;   /* o arrasto é nosso, não do navegador */
  --iris-base: .42;     /* discreto em repouso */
  transition:
    transform .38s cubic-bezier(.34,1.4,.5,1),
    box-shadow .38s var(--suave);
}

/* a trilha inteira sobe de leve e ganha halo ao ser tocada */
.deslizante:hover {
  transform: translateY(-2px);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.16),
    inset 0 1px 0 rgba(255,255,255,.3),
    0 20px 42px rgba(0,0,0,.5),
    0 0 34px rgba(166,77,255,.22);
}
.deslizante:active { transform: translateY(0); }

/* o miolo de vidro que desliza */
.deslizante__miolo {
  position: absolute;
  left: 0; top: -6px;
  width: 58%;
  height: calc(100% + 12px);
  border-radius: 999px;
  background: linear-gradient(180deg, rgba(255,255,255,.16), rgba(255,255,255,.06));
  backdrop-filter: blur(2px) brightness(1.14);
  -webkit-backdrop-filter: blur(2px) brightness(1.14);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.26),
    inset 0 2px 3px rgba(255,255,255,.34),
    inset 0 -2px 4px rgba(255,255,255,.14),
    8px 0 26px rgba(0,0,0,.22);
  pointer-events: none;
  z-index: 2;

  /* a escala vem do arrasto: ver .arrastando, abaixo */
  transform: scaleY(var(--esticar, 1));
  transform-origin: center;
  transition:
    width .18s cubic-bezier(.22,1,.36,1),
    transform .3s cubic-bezier(.34,1.4,.5,1),
    filter .3s var(--suave);
}

.deslizante:hover .deslizante__miolo { filter: brightness(1.1); }

/* Enquanto arrasta, o miolo ACHATA. É o mesmo squash & stretch da
   navegação líquida do site: matéria em movimento se deforma no
   eixo do deslocamento, e é isso que dá peso ao gesto. */
.deslizante.arrastando { transition-duration: 0s; }
.deslizante.arrastando .deslizante__miolo {
  --esticar: .92;
  transition: transform .12s var(--saida), filter .12s;
  filter: brightness(1.2);
}

/* o fio de luz na ponta, onde a lente corta a trilha */
.deslizante__miolo::after {
  content: '';
  position: absolute;
  right: 0; top: 12%;
  width: 2px; height: 76%;
  border-radius: 2px;
  background: rgba(255,255,255,.9);
  box-shadow: 0 0 12px rgba(255,255,255,.7);
  transition: box-shadow .3s var(--suave), width .3s var(--suave);
}
.deslizante:hover .deslizante__miolo::after,
.deslizante.arrastando .deslizante__miolo::after {
  width: 3px;
  box-shadow: 0 0 22px rgba(219,217,207,.95), 0 0 40px rgba(166,77,255,.6);
}

.deslizante__rotulo,
.deslizante__valor {
  position: relative; z-index: 4;
  font-family: var(--font-entrada);
  font-size: 16px;
  color: #fff;
  white-space: nowrap;
  transition: text-shadow .3s var(--suave);
}
.deslizante:hover .deslizante__rotulo { text-shadow: 0 0 16px rgba(255,255,255,.6); }

.deslizante__valor {
  margin-left: auto;
  font-variant-numeric: tabular-nums;
  transition: transform .3s cubic-bezier(.34,1.4,.5,1), text-shadow .3s;
}
.deslizante.arrastando .deslizante__valor {
  transform: scale(1.16);
  text-shadow: 0 0 18px rgba(219,217,207,.8);
}

/* os pontinhos da trilha */
.deslizante__pontos {
  position: relative; z-index: 4;
  flex: 1;
  display: flex; justify-content: space-around; align-items: center;
}
.deslizante__pontos i {
  width: 3px; height: 3px; border-radius: 50%;
  background: rgba(255,255,255,.45);
  transition: transform .3s cubic-bezier(.34,1.4,.5,1), background .3s, box-shadow .3s;
}
/* os pontos acendem em cascata quando a mão chega */
.deslizante:hover .deslizante__pontos i {
  background: rgba(255,255,255,.8);
  transform: scale(1.5);
  box-shadow: 0 0 8px rgba(219,217,207,.7);
}
.deslizante:hover .deslizante__pontos i:nth-child(1) { transition-delay: 0ms; }
.deslizante:hover .deslizante__pontos i:nth-child(2) { transition-delay: 40ms; }
.deslizante:hover .deslizante__pontos i:nth-child(3) { transition-delay: 80ms; }
.deslizante:hover .deslizante__pontos i:nth-child(4) { transition-delay: 120ms; }
.deslizante:hover .deslizante__pontos i:nth-child(5) { transition-delay: 160ms; }
.deslizante:hover .deslizante__pontos i:nth-child(6) { transition-delay: 200ms; }

/* o ponto já ultrapassado pelo miolo fica aceso */
.deslizante__pontos i.passou {
  background: rgba(219,217,207,.95);
  box-shadow: 0 0 10px rgba(219,217,207,.8);
}

/* ============================================================
   VARIANTE 6 — COMANDO
   O trio de botões da referência. As peças de vidro invadem o
   botão sólido do meio: a sobreposição é o que cria hierarquia
   sem precisar de tamanho diferente ou de moldura.
   ============================================================ */
.comando { display: flex; align-items: center; }

.comando__lado {
  width: 84px; height: 84px;
  border-radius: 50%;
  display: grid; place-items: center;
  color: #fff; font-size: 13px;
  font-family: var(--font-entrada);
  background: linear-gradient(180deg, rgba(255,255,255,.11), rgba(255,255,255,.04));
  backdrop-filter: blur(16px) saturate(140%);
  -webkit-backdrop-filter: blur(16px) saturate(140%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.16),
    inset 0 2px 3px rgba(255,255,255,.24),
    0 14px 30px rgba(0,0,0,.45);
  --iris-base: .34;
  transition:
    transform .45s cubic-bezier(.34,1.4,.5,1),
    box-shadow .45s var(--suave);
}

/* Ao aproximar, o botão avança na direção de quem olha e sai um
   pouco de trás do play — a sobreposição diminui, e é isso que dá
   a impressão de que a peça se soltou da pilha. */
.comando__lado:first-child  { margin-right: -22px; }
.comando__lado:last-child   { margin-left:  -22px; }
.comando__lado:first-child:hover { transform: scale(1.09) translateX(-8px); }
.comando__lado:last-child:hover  { transform: scale(1.09) translateX(8px); }
.comando__lado:hover {
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.24),
    inset 0 2px 3px rgba(255,255,255,.34),
    0 22px 44px rgba(0,0,0,.55),
    0 0 34px rgba(166,77,255,.3);
}
.comando__lado:active { transform: scale(.94); }

/* o ícone gira no sentido do que o botão faz */
.comando__lado i { transition: transform .5s cubic-bezier(.34,1.4,.5,1); }
.comando__lado:first-child:hover i { transform: rotate(-58deg); }
.comando__lado:last-child:hover  i { transform: rotate(58deg); }

.comando__play {
  position: relative;
  z-index: 2;
  width: 92px; height: 92px;
  border-radius: 50%;
  display: grid; place-items: center;
  background: linear-gradient(180deg, #fff, #e6e4dc);
  color: #121214; font-size: 24px;
  box-shadow:
    inset 0 2px 3px rgba(255,255,255,.9),
    inset 0 -3px 6px rgba(0,0,0,.10),
    0 18px 40px rgba(0,0,0,.5);
  transition:
    transform .45s cubic-bezier(.34,1.4,.5,1),
    box-shadow .45s var(--suave);
}
.comando__play:hover {
  transform: scale(1.06);
  box-shadow:
    inset 0 2px 3px rgba(255,255,255,.95),
    inset 0 -3px 6px rgba(0,0,0,.12),
    0 24px 52px rgba(0,0,0,.6),
    0 0 44px rgba(219,217,207,.45);
}
.comando__play:active { transform: scale(.94); }
.comando__play i { transition: transform .45s cubic-bezier(.34,1.4,.5,1); }
.comando__play:hover i { transform: scale(1.14); }

/* anel que se abre a partir do play, uma vez por aproximação */
.comando__play::after {
  content: '';
  position: absolute; inset: -2px;
  border-radius: 50%;
  border: 1.5px solid rgba(219,217,207,.8);
  opacity: 0;
  transform: scale(1);
  pointer-events: none;
}
.comando__play:hover::after { animation: anel-play 1.1s var(--suave); }
@keyframes anel-play {
  0%   { opacity: .9; transform: scale(1); }
  100% { opacity: 0;  transform: scale(1.42); }
}

/* etiqueta suave, como o "Stress Relief" da referência */
.etiqueta {
  display: inline-flex; align-items: center;
  padding: 11px 22px;
  border-radius: 999px;
  font-family: var(--font-entrada); font-size: 14px; color: #fff;
  background: rgba(255,255,255,.09);
  backdrop-filter: blur(18px) saturate(140%);
  -webkit-backdrop-filter: blur(18px) saturate(140%);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.14), inset 0 1px 0 rgba(255,255,255,.26);
}

/* ============================================================
   VARIANTE 7 — IRIDESCENTE
   Um arco de luz colorida girando pela borda, sem parar. É o
   comportamento de um vidro com película fina: a cor que ele
   devolve muda conforme o ângulo. Aqui o ângulo é que gira.

   Só a borda anima, e por interpolação de um <angle> registrado —
   o miolo do painel não é repintado.
   ============================================================ */
/* o corpo; o anel vem da classe .espectral */
.v-iris {
  border-radius: 26px;
  background: linear-gradient(158deg, rgba(255,255,255,.07), rgba(255,255,255,.025) 55%, rgba(255,255,255,.05));
  backdrop-filter: blur(24px) saturate(160%);
  -webkit-backdrop-filter: blur(24px) saturate(160%);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.20), 0 22px 46px -18px rgba(0,0,0,.6);
  --iris-fio: 1.5px;
  transition: transform .45s cubic-bezier(.34,1.4,.5,1), box-shadow .45s var(--suave);
}
.v-iris:hover {
  transform: translateY(-4px);
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.3),
    0 30px 60px -18px rgba(0,0,0,.7),
    0 0 46px rgba(166,77,255,.24);
}

@keyframes girar-iris { to { --giro-iris: 360deg; } }

/* ============================================================
   VARIANTE 8 — CÁUSTICA
   Luz atravessando água: manchas claras que se deformam devagar
   dentro do vidro. Duas manchas em movimento lento sob a camada
   fosca, só transform — o compositor resolve sozinho.
   ============================================================ */
.v-caustica {
  position: relative;
  border-radius: 26px;
  overflow: hidden;
  isolation: isolate;
  background: linear-gradient(158deg, rgba(255,255,255,.08), rgba(255,255,255,.028) 55%, rgba(255,255,255,.05));
  backdrop-filter: blur(26px) saturate(170%);
  -webkit-backdrop-filter: blur(26px) saturate(170%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.10),
    inset 0 1px 0 rgba(255,255,255,.26),
    0 22px 46px -18px rgba(0,0,0,.6);
}
.v-caustica > .luz {
  position: absolute;
  width: 62%; aspect-ratio: 1;
  border-radius: 50%;
  pointer-events: none;
  z-index: -1;
  filter: blur(26px);
  will-change: transform;
}
.v-caustica > .luz:nth-of-type(1) {
  left: -12%; top: -30%;
  background: radial-gradient(circle, rgba(166,77,255,.5), transparent 68%);
  animation: vaguear-a 11s ease-in-out infinite alternate;
}
.v-caustica > .luz:nth-of-type(2) {
  right: -14%; bottom: -34%;
  background: radial-gradient(circle, rgba(219,217,207,.32), transparent 68%);
  animation: vaguear-b 14s ease-in-out infinite alternate;
}
@keyframes vaguear-a {
  from { transform: translate3d(0,0,0) scale(1); }
  to   { transform: translate3d(46%, 40%, 0) scale(1.25); }
}
@keyframes vaguear-b {
  from { transform: translate3d(0,0,0) scale(1.15); }
  to   { transform: translate3d(-40%, -34%, 0) scale(.9); }
}

/* ============================================================
   VARIANTE 9 — VARREDURA
   Um feixe atravessa o painel de tempos em tempos, como um
   scanner passando sobre a superfície. Translação pura, atrás
   do conteúdo.
   ============================================================ */
.v-varredura {
  position: relative;
  border-radius: 26px;
  overflow: hidden;
  isolation: isolate;
  background: linear-gradient(158deg, rgba(255,255,255,.075), rgba(255,255,255,.025) 55%, rgba(255,255,255,.05));
  backdrop-filter: blur(22px) saturate(150%);
  -webkit-backdrop-filter: blur(22px) saturate(150%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.10),
    inset 0 1px 0 rgba(255,255,255,.24),
    0 22px 46px -18px rgba(0,0,0,.6);
}
.v-varredura::before {
  content: '';
  position: absolute;
  top: -30%; left: 0;
  width: 44%; height: 160%;
  z-index: -1;
  background: linear-gradient(100deg,
      transparent,
      rgba(166,77,255,.20) 38%,
      rgba(219,217,207,.30) 50%,
      rgba(166,77,255,.20) 62%,
      transparent);
  transform: translateX(-140%) skewX(-16deg);
  animation: varrer 4.6s cubic-bezier(.5,0,.5,1) infinite;
  will-change: transform;
}
@keyframes varrer {
  0%   { transform: translateX(-140%) skewX(-16deg); }
  55%  { transform: translateX(330%) skewX(-16deg); }
  100% { transform: translateX(330%) skewX(-16deg); }
}

/* trilho fino de progresso, como o da referência */
.trilho-fino {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; height: 220px;
  font-family: var(--font-entrada); font-size: 12px; color: rgba(255,255,255,.7);
}
.trilho-fino span { flex-shrink: 0; }
.trilho-fino i {
  flex: 1; width: 1px;
  background: linear-gradient(180deg, rgba(255,255,255,.9) 0%, rgba(255,255,255,.9) 32%, rgba(255,255,255,.18) 32%);
  border-radius: 1px;
}

/* ============================================================
   VARIANTE 10 — REPRODUTOR

   Junta as três que você aprovou: o cluster sobreposto do 06, o
   anel espectral do 07, e a fita de ondas da tela de login.

   A fita é desenhada em canvas, não em elementos: nove linhas
   empilhadas com amplitude e fase decrescentes. Cada uma repete a
   mesma onda um pouco depois da anterior, e é esse atraso que abre
   o leque e faz parecer rastro em vez de traço.
   ============================================================ */
.reprodutor {
  display: flex;
  align-items: center;
  gap: 26px;
  width: min(760px, 100%);
  padding: 20px 30px;
  border-radius: 34px;
  background:
    radial-gradient(120% 160% at 18% -20%, rgba(255,255,255,.10), transparent 58%),
    linear-gradient(180deg, rgba(255,255,255,.07), rgba(255,255,255,.028));
  backdrop-filter: blur(28px) saturate(160%);
  -webkit-backdrop-filter: blur(28px) saturate(160%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.10),
    inset 0 1px 0 rgba(255,255,255,.24),
    inset 0 -22px 36px -30px rgba(0,0,0,.8),
    0 26px 56px -18px rgba(0,0,0,.62);
  --iris-base: .38;
  --iris-fio: 1.5px;
  transition: transform .45s cubic-bezier(.34,1.4,.5,1), box-shadow .45s var(--suave);
}
.reprodutor:hover {
  transform: translateY(-3px);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.16),
    inset 0 1px 0 rgba(255,255,255,.3),
    0 34px 68px -18px rgba(0,0,0,.7),
    0 0 46px rgba(166,77,255,.2);
}

/* ---------- o cluster, em escala de barra ---------- */
.reprodutor__comando { display: flex; align-items: center; flex-shrink: 0; }

.rep-lado {
  width: 46px; height: 46px;
  border-radius: 50%;
  display: grid; place-items: center;
  color: #fff; font-size: 12px;
  background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.035));
  backdrop-filter: blur(12px) saturate(140%);
  -webkit-backdrop-filter: blur(12px) saturate(140%);
  box-shadow:
    inset 0 0 0 1px rgba(255,255,255,.14),
    inset 0 1px 2px rgba(255,255,255,.22),
    0 8px 18px rgba(0,0,0,.4);
  --iris-base: .3;
  transition: transform .45s cubic-bezier(.34,1.4,.5,1), box-shadow .45s var(--suave);
}
.rep-lado:first-child { margin-right: -14px; }
.rep-lado:last-child  { margin-left:  -14px; }
.rep-lado:first-child:hover { transform: scale(1.1) translateX(-5px); }
.rep-lado:last-child:hover  { transform: scale(1.1) translateX(5px); }
.rep-lado:hover { box-shadow: inset 0 0 0 1px rgba(255,255,255,.22), 0 12px 26px rgba(0,0,0,.5), 0 0 24px rgba(166,77,255,.32); }
.rep-lado:active { transform: scale(.93); }
.rep-lado i { transition: transform .5s cubic-bezier(.34,1.4,.5,1); }
.rep-lado:first-child:hover i { transform: rotate(-58deg); }
.rep-lado:last-child:hover  i { transform: rotate(58deg); }

.rep-play {
  position: relative;
  z-index: 2;
  width: 58px; height: 58px;
  border-radius: 50%;
  display: grid; place-items: center;
  background: linear-gradient(180deg, #fff, #e6e4dc);
  color: #121214; font-size: 17px;
  box-shadow:
    inset 0 2px 3px rgba(255,255,255,.9),
    inset 0 -3px 6px rgba(0,0,0,.10),
    0 12px 26px rgba(0,0,0,.5);
  transition: transform .45s cubic-bezier(.34,1.4,.5,1), box-shadow .45s var(--suave);
}
.rep-play:hover {
  transform: scale(1.07);
  box-shadow: inset 0 2px 3px rgba(255,255,255,.95), 0 18px 36px rgba(0,0,0,.6), 0 0 34px rgba(219,217,207,.42);
}
.rep-play:active { transform: scale(.93); }
.rep-play i { transition: transform .4s cubic-bezier(.34,1.4,.5,1); }
.rep-play:hover i { transform: scale(1.15); }

/* o anel que pulsa no compasso, quando está tocando */
.rep-play::after {
  content: '';
  position: absolute; inset: -3px;
  border-radius: 50%;
  border: 1.5px solid rgba(219,217,207,.75);
  opacity: 0;
  pointer-events: none;
}
.reprodutor.tocando .rep-play::after { animation: anel-play 1.6s var(--suave) infinite; }

/* ---------- a fita ---------- */
.reprodutor__onda {
  position: relative;
  flex: 1;
  min-width: 0;
  height: 62px;
  cursor: pointer;
  contain: strict;
}
.reprodutor__onda canvas {
  display: block; width: 100%; height: 100%;
  opacity: .8;
  filter: drop-shadow(0 0 6px rgba(127,0,255,.35));
  transition: opacity .35s var(--suave), filter .35s var(--suave);
}
.reprodutor:hover .reprodutor__onda canvas { opacity: .95; }
.reprodutor.tocando .reprodutor__onda canvas {
  opacity: 1;
  filter: drop-shadow(0 0 10px rgba(219,217,207,.35));
}

/* ---------- leituras ---------- */
.reprodutor__lado-direito {
  display: flex; flex-direction: column; align-items: flex-end; gap: 6px;
  flex-shrink: 0;
}
.rep-bpm {
  font-family: var(--font-mono);
  font-size: 9.5px; letter-spacing: .18em; text-transform: uppercase;
  color: rgba(219,217,207,.55);
  opacity: 0; transition: opacity .4s var(--suave);
}
.reprodutor.tocando .rep-bpm { opacity: 1; }
.rep-tempo {
  font-family: var(--font-mono);
  font-size: 12px; color: rgba(255,255,255,.82);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

@media (max-width: 760px) {
  .reprodutor { flex-direction: column; gap: 16px; padding: 18px; border-radius: 28px; }
  .reprodutor__onda { width: 100%; height: 54px; }
  .reprodutor__lado-direito { flex-direction: row; align-items: center; gap: 14px; }
}

/* ============================================================
   PÁGINA
   ============================================================ */
.pag { position: relative; max-width: 1240px; margin: 0 auto; padding: 44px 26px 100px; }
.cabeca h1 {
  font-family: var(--font-display); font-weight: 700;
  font-size: clamp(28px, 4vw, 44px); text-transform: uppercase;
  letter-spacing: -.035em; line-height: .95; margin-top: 16px;
}
.cabeca p { color: var(--fg-dim); max-width: 60ch; margin-top: 12px; font-size: 15px; }

.controles { display: flex; gap: 12px; flex-wrap: wrap; margin: 26px 0 0; }
.grupo {
  display: inline-flex; gap: 5px; padding: 5px; border-radius: 14px;
  background: rgba(255,255,255,.06); box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
}
.grupo button {
  padding: 9px 17px; border-radius: 10px; font-size: 13.5px; font-weight: 600;
  color: var(--fg-mute); transition: color .25s, background .25s;
}
.grupo button.on {
  color: #fff;
  background: linear-gradient(135deg, var(--spark-hi), var(--spark) 60%, #5c00b8);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.3);
}

.secao { margin-top: 58px; }
.secao__topo { display: flex; align-items: baseline; gap: 14px; flex-wrap: wrap; margin-bottom: 6px; }
.secao__n {
  font-family: var(--font-mono); font-size: 12px; color: var(--spark-hi);
  border: 1px solid rgba(166,77,255,.35); border-radius: 8px; padding: 3px 8px;
}
.secao__t { font-family: var(--font-display); font-weight: 700; font-size: 22px; letter-spacing: -.02em; }
.secao__d { color: var(--fg-dim); font-size: 14.5px; max-width: 62ch; margin-bottom: 24px; }

.palco {
  display: grid; place-items: center;
  min-height: 260px; padding: 40px 24px;
  border-radius: 22px;
  border: 1px dashed rgba(255,255,255,.10);
}

.aplicado { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 22px; }
.amostra { padding: 22px; }
.linha { display: flex; align-items: center; gap: 13px; margin-bottom: 14px; }
.tit { font-family: var(--font-display); font-weight: 600; font-size: 17px; letter-spacing: -.02em; }
.sub { font-family: var(--font-mono); font-size: 11.5px; color: var(--fg-mute); }
.corpo { color: var(--fg-dim); font-size: 14.5px; }
.barra-demo { height: 4px; border-radius: 999px; margin: 16px 0 8px; background: rgba(255,255,255,.12); overflow: hidden; }
.barra-demo i { display: block; height: 100%; width: 62%; border-radius: 999px; background: linear-gradient(90deg, var(--spark), var(--spark-hi)); }
.pes { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: 11.5px; color: var(--fg-mute); }

.voto {
  margin-top: 18px; padding: 14px 16px; border-radius: 14px;
  border: 1px solid rgba(255,255,255,.10); background: rgba(255,255,255,.04);
  font-size: 13.5px; color: var(--fg-dim);
}
.voto b { color: var(--fg); }

.aviso {
  margin-top: 40px; padding: 18px 20px; border-radius: 16px;
  border: 1px solid rgba(127,0,255,.28); background: rgba(127,0,255,.10);
  font-size: 14.5px; color: var(--fg-dim);
}

@media (max-width: 760px) {
  .pag { padding: 26px 16px 70px; }
  .interruptor__trilha { width: 172px; height: 60px; padding: 0 24px 0 66px; }
  .interruptor__lente { width: 78px; height: 78px; }
  .interruptor__rotulo { font-size: 23px; }
  .interruptor[aria-checked="true"] .interruptor__lente { transform: translateX(100px); }
}
</style>
</head>
<body>

<div class="cena" id="cena" data-fundo="cinza" aria-hidden="true">
  <div class="trama"></div>
  <i class="faixa" style="left:6%;  width:70px;  background:linear-gradient(180deg,#7f00ff,#3a0072); opacity:.85"></i>
  <i class="faixa" style="left:16%; width:16px;  background:#dbd9cf; opacity:.25; animation-delay:-6s"></i>
  <i class="faixa" style="left:26%; width:130px; background:linear-gradient(180deg,#a64dff,#4b00a0); opacity:.55; animation-delay:-12s"></i>
  <i class="faixa" style="left:44%; width:8px;   background:#dbd9cf; opacity:.35; animation-delay:-3s"></i>
  <i class="faixa" style="left:54%; width:180px; background:linear-gradient(180deg,#5c00b8,#12001f); opacity:.7;  animation-delay:-18s"></i>
  <i class="faixa" style="left:72%; width:90px;  background:linear-gradient(180deg,#7f00ff,#a64dff); opacity:.75; animation-delay:-9s"></i>
  <i class="faixa" style="left:93%; width:110px; background:linear-gradient(180deg,#4b00a0,#0a0a0c); opacity:.8;  animation-delay:-22s"></i>
</div>

<main class="pag">

  <header class="cabeca">
    <?= logo_assinatura('assinatura--marca') ?>
    <h1>Galeria de vidro</h1>
    <p>
      Cinco interpretações de Liquid Glass, nos mesmos componentes do Spark.
      Troque o fundo — vidro só pode ser julgado com algo atrás, e cada
      variante se comporta de um jeito diferente conforme o que passa por trás.
      <b style="color:var(--fg)">Nada disso está aplicado no site ainda.</b>
    </p>

    <div class="controles">
      <div class="grupo" role="group" aria-label="Fundo">
        <button class="on" data-fundo="cinza">Cinza neutro</button>
        <button data-fundo="trama">Trama escura</button>
        <button data-fundo="spark">Faixas Spark</button>
      </div>
    </div>
  </header>

  <!-- ============ 1. LENTE ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">01</span>
      <span class="secao__t">Lente</span>
    </div>
    <p class="secao__d">
      A reprodução da sua referência. O que faz a peça funcionar não é o
      desfoque — é a geometria da luz: dois arcos opostos, um no alto onde o
      feixe entra e outro embaixo onde ele sai, com o miolo mais limpo.
      E o botão é maior que a trilha, transbordando por cima: esse detalhe é
      metade do efeito, porque mostra que a lente tem volume próprio.
      <b style="color:var(--fg)">Clique para acionar.</b>
    </p>

    <div class="palco">
      <button class="interruptor" id="interruptor" role="switch" aria-checked="false" aria-label="Alternar modo claro">
        <span class="interruptor__trilha"><span class="interruptor__rotulo">Light</span></span>
        <span class="interruptor__lente">
          <svg class="interruptor__sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
            <circle cx="12" cy="12" r="4.2"/>
            <path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2M5.4 5.4l1.6 1.6M17 17l1.6 1.6M18.6 5.4L17 7M7 17l-1.6 1.6"/>
          </svg>
        </span>
      </button>
    </div>

    <div class="aplicado">
      <article class="amostra v-lente">
        <div class="linha">
          <span class="avatar avatar--md">N</span>
          <div style="flex:1;min-width:0"><div class="tit">Novak Prod</div><div class="sub">@novakprod · agora</div></div>
          <button class="btn btn--linha btn--sm">Seguir</button>
        </div>
        <p class="corpo">Beat de 120 BPM que nasceu de um bloqueio.</p>
        <div class="barra-demo"><i></i></div>
        <div class="pes"><span>0:42</span><span>1:35</span></div>
      </article>
      <article class="amostra v-lente">
        <div class="tit" style="margin-bottom:6px">Seu momento</div>
        <p class="corpo">Buscando referência — procurando ideia, sample, paleta.</p>
        <div style="display:flex;gap:10px;margin-top:16px">
          <button class="btn btn--primario btn--sm">Publicar</button>
          <button class="btn btn--linha btn--sm">Cancelar</button>
        </div>
      </article>
    </div>
    <div class="voto"><b>Onde brilha:</b> em fundos claros ou de contraste médio. O brilho de borda depende de haver luz atrás para captar.</div>
  </section>

  <!-- ============ 2. BARRA ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">02</span>
      <span class="secao__t">Fumê</span>
    </div>
    <p class="secao__d">
      A referência da barra escura. Vidro quase preto, quase sem cor: o que o
      define é o fio de contorno claríssimo e um clarão interno discreto vindo
      de baixo. Sem saturação — aqui o vidro é fumê, não cristal.
    </p>

    <div class="palco">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:center">
        <div class="barra">
          <span class="mais">+</span>
          <input placeholder="Pergunte à IA" aria-label="Exemplo">
          <i class="fa-solid fa-microphone-lines"></i>
          <i class="fa-solid fa-wave-square"></i>
        </div>
        <button class="fechar-barra" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>
    <div class="voto"><b>Onde brilha:</b> sobre fundos escuros e texturizados. Some em fundo claro — vira apenas uma caixa cinza.</div>
  </section>

  <!-- ============ 3. PROFUNDO ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">03</span>
      <span class="secao__t">Profundo</span>
    </div>
    <p class="secao__d">
      O do widget de voo: desfoque de 40px e saturação de 200%. O fundo perde a
      forma e vira mancha de cor dentro do painel. É como vidro espesso se
      comporta — não deixa ver o que está atrás, deixa passar a cor.
    </p>

    <div class="aplicado">
      <article class="amostra v-profundo">
        <div class="linha">
          <span class="avatar avatar--md">N</span>
          <div style="flex:1;min-width:0"><div class="tit">Novak Prod</div><div class="sub">@novakprod · agora</div></div>
          <button class="btn btn--linha btn--sm">Seguir</button>
        </div>
        <p class="corpo">Beat de 120 BPM que nasceu de um bloqueio.</p>
        <div class="barra-demo"><i></i></div>
        <div class="pes"><span>0:42</span><span>1:35</span></div>
      </article>
      <article class="amostra v-profundo">
        <div class="tit" style="margin-bottom:6px">Seu momento</div>
        <p class="corpo">Buscando referência — procurando ideia, sample, paleta.</p>
        <div style="display:flex;gap:10px;margin-top:16px">
          <button class="btn btn--primario btn--sm">Publicar</button>
          <button class="btn btn--linha btn--sm">Cancelar</button>
        </div>
      </article>
    </div>
    <div class="voto"><b>Custo:</b> o mais caro dos cinco — cerca de três vezes o desfoque atual.</div>
  </section>

  <!-- ============ 4. FINO (atual) ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">04</span>
      <span class="secao__t">Fino</span>
      <span class="rotulo" style="color:var(--fg-mute)">— o que o site usa hoje</span>
    </div>
    <p class="secao__d">
      Desfoque de 14px, saturação 155%. O fundo atravessa ainda reconhecível:
      lê como película sobre a imagem, não como vidro com espessura.
    </p>

    <div class="aplicado">
      <article class="amostra v-fino">
        <div class="linha">
          <span class="avatar avatar--md">N</span>
          <div style="flex:1;min-width:0"><div class="tit">Novak Prod</div><div class="sub">@novakprod · agora</div></div>
          <button class="btn btn--linha btn--sm">Seguir</button>
        </div>
        <p class="corpo">Beat de 120 BPM que nasceu de um bloqueio.</p>
        <div class="barra-demo"><i></i></div>
        <div class="pes"><span>0:42</span><span>1:35</span></div>
      </article>
      <article class="amostra v-fino">
        <div class="tit" style="margin-bottom:6px">Seu momento</div>
        <p class="corpo">Buscando referência — procurando ideia, sample, paleta.</p>
        <div style="display:flex;gap:10px;margin-top:16px">
          <button class="btn btn--primario btn--sm">Publicar</button>
          <button class="btn btn--linha btn--sm">Cancelar</button>
        </div>
      </article>
    </div>
    <div class="voto"><b>Custo:</b> o mais barato. É o que roda hoje sem pesar.</div>
  </section>

  <!-- ============ 5. DESLIZANTE ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">05</span>
      <span class="secao__t">Deslizante</span>
    </div>
    <p class="secao__d">
      A linha de volume da sua referência, com o mesmo princípio da Lente: o
      miolo é uma peça de vidro <b style="color:var(--fg)">maior que a trilha</b>,
      transbordando acima e abaixo. Ele clareia o que passa por baixo e leva um
      fio de luz na ponta — é o que dá a sensação de arrastar matéria em vez de
      esticar um retângulo. <b style="color:var(--fg)">Arraste os dois.</b>
    </p>

    <div class="palco" style="flex-direction:column;gap:20px">
      <div style="display:flex;flex-direction:column;gap:20px;align-items:center">
        <div class="deslizante espectral" data-deslizante data-valor="54">
          <span class="deslizante__miolo" style="width:54%"></span>
          <span class="deslizante__rotulo">Volume</span>
          <span class="deslizante__pontos"><i></i><i></i><i></i><i></i><i></i><i></i></span>
          <span class="deslizante__valor">54</span>
        </div>

        <div class="deslizante espectral" data-deslizante data-valor="28">
          <span class="deslizante__miolo" style="width:28%"></span>
          <span class="deslizante__rotulo">Intensidade</span>
          <span class="deslizante__pontos"><i></i><i></i><i></i><i></i><i></i><i></i></span>
          <span class="deslizante__valor">28</span>
        </div>
      </div>
    </div>
    <div class="voto"><b>No Spark:</b> serviria para o volume do player, o tamanho da fonte e os controles de preferência.</div>
  </section>

  <!-- ============ 6. COMANDO ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">06</span>
      <span class="secao__t">Comando</span>
    </div>
    <p class="secao__d">
      O trio de botões da referência. As peças de vidro invadem o botão sólido
      do meio — a sobreposição cria hierarquia sem precisar de tamanho diferente
      nem de moldura. Ao lado, a etiqueta suave e o trilho fino de progresso.
    </p>

    <div class="palco">
      <div style="display:flex;align-items:center;gap:46px;flex-wrap:wrap;justify-content:center">
        <div style="display:flex;flex-direction:column;align-items:center;gap:26px">
          <span class="etiqueta">Beat de 120 BPM</span>
          <div class="comando">
            <button class="comando__lado espectral" aria-label="Voltar 10 segundos">
              <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
            </button>
            <button class="comando__play" aria-label="Tocar">
              <i class="fa-solid fa-play" aria-hidden="true"></i>
            </button>
            <button class="comando__lado espectral" aria-label="Avançar 10 segundos">
              <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="trilho-fino"><span>0:42</span><i></i><span>1:35</span></div>
      </div>
    </div>
    <div class="voto"><b>No Spark:</b> substituiria o player de áudio do feed — o botão de play sólido no centro, com retroceder e avançar em vidro.</div>
  </section>

  <!-- ============ 7. IRIDESCENTE ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">07</span>
      <span class="secao__t">Iridescente</span>
      <span class="rotulo" style="color:var(--spark-hi)">animada</span>
    </div>
    <p class="secao__d">
      Um arco de luz colorida girando pela borda, sem parar. É o comportamento
      de um vidro com película fina: a cor que ele devolve muda conforme o
      ângulo — aqui o ângulo é que gira. Só a borda anima, por interpolação de
      um ângulo registrado; o miolo do painel não é repintado.
    </p>

    <div class="aplicado">
      <article class="amostra v-iris espectral">
        <div class="linha">
          <span class="avatar avatar--md">N</span>
          <div style="flex:1;min-width:0"><div class="tit">Novak Prod</div><div class="sub">@novakprod · agora</div></div>
          <button class="btn btn--linha btn--sm">Seguir</button>
        </div>
        <p class="corpo">Beat de 120 BPM que nasceu de um bloqueio.</p>
        <div class="barra-demo"><i></i></div>
        <div class="pes"><span>0:42</span><span>1:35</span></div>
      </article>
      <article class="amostra v-iris espectral">
        <div class="tit" style="margin-bottom:6px">Botão Conexão</div>
        <p class="corpo">Um clique liga você a outro artista no mesmo momento criativo.</p>
        <div style="display:flex;gap:10px;margin-top:16px">
          <button class="btn btn--primario btn--sm">Procurar</button>
        </div>
      </article>
    </div>
    <div class="voto"><b>Onde brilha:</b> em poucos elementos de destaque. Em todos os painéis, cansa.</div>
  </section>

  <!-- ============ 8. CÁUSTICA ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">08</span>
      <span class="secao__t">Cáustica</span>
      <span class="rotulo" style="color:var(--spark-hi)">animada</span>
    </div>
    <p class="secao__d">
      Luz atravessando água: manchas claras que se deformam devagar dentro do
      vidro, nunca repetindo a mesma posição. Duas manchas sob a camada fosca,
      movidas só por transformação — o compositor resolve sozinho, sem repintar.
    </p>

    <div class="aplicado">
      <article class="amostra v-caustica">
        <span class="luz"></span><span class="luz"></span>
        <div class="linha">
          <span class="avatar avatar--md">N</span>
          <div style="flex:1;min-width:0"><div class="tit">Novak Prod</div><div class="sub">@novakprod · agora</div></div>
          <button class="btn btn--linha btn--sm">Seguir</button>
        </div>
        <p class="corpo">Beat de 120 BPM que nasceu de um bloqueio.</p>
        <div class="barra-demo"><i></i></div>
        <div class="pes"><span>0:42</span><span>1:35</span></div>
      </article>
      <article class="amostra v-caustica">
        <span class="luz"></span><span class="luz"></span>
        <div class="tit" style="margin-bottom:6px">Seu momento</div>
        <p class="corpo">Buscando referência — procurando ideia, sample, paleta.</p>
        <div style="display:flex;gap:10px;margin-top:16px">
          <button class="btn btn--primario btn--sm">Publicar</button>
          <button class="btn btn--linha btn--sm">Cancelar</button>
        </div>
      </article>
    </div>
    <div class="voto"><b>Onde brilha:</b> painéis grandes e parados, como a lateral do feed e os modais.</div>
  </section>

  <!-- ============ 9. VARREDURA ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">09</span>
      <span class="secao__t">Varredura</span>
      <span class="rotulo" style="color:var(--spark-hi)">animada</span>
    </div>
    <p class="secao__d">
      Um feixe atravessa o painel de tempos em tempos, como um scanner passando
      pela superfície. Translação pura, atrás do conteúdo — e com pausa entre
      as passagens, para não virar um pisca-pisca.
    </p>

    <div class="aplicado">
      <article class="amostra v-varredura">
        <div class="linha">
          <span class="avatar avatar--md">N</span>
          <div style="flex:1;min-width:0"><div class="tit">Novak Prod</div><div class="sub">@novakprod · agora</div></div>
          <button class="btn btn--linha btn--sm">Seguir</button>
        </div>
        <p class="corpo">Beat de 120 BPM que nasceu de um bloqueio.</p>
        <div class="barra-demo"><i></i></div>
        <div class="pes"><span>0:42</span><span>1:35</span></div>
      </article>
      <article class="amostra v-varredura">
        <div class="tit" style="margin-bottom:6px">Notificação</div>
        <p class="corpo">teste7 curtiu seu post e começou a seguir você.</p>
        <div style="display:flex;gap:10px;margin-top:16px">
          <button class="btn btn--linha btn--sm">Ver</button>
        </div>
      </article>
    </div>
    <div class="voto"><b>Onde brilha:</b> em avisos e novidades — a passagem do feixe chama o olho uma vez e para.</div>
  </section>

  <!-- ============ 10. REPRODUTOR ============ -->
  <section class="secao">
    <div class="secao__topo">
      <span class="secao__n">10</span>
      <span class="secao__t">Reprodutor</span>
      <span class="rotulo" style="color:var(--spark-hi)">as três juntas</span>
    </div>
    <p class="secao__d">
      O cluster sobreposto do <b style="color:var(--fg)">06</b>, o anel espectral
      do <b style="color:var(--fg)">07</b> e a fita de ondas da tela de login,
      numa peça só. A fita é canvas: nove linhas empilhadas com amplitude e fase
      decrescentes — cada uma repete a onda um pouco depois da anterior, e é esse
      atraso que abre o leque e faz parecer rastro em vez de traço.
      <b style="color:var(--fg)">Clique no play e arraste a onda.</b>
    </p>

    <div class="palco" style="padding:56px 24px">
      <div class="reprodutor espectral tocando" id="reprodutor">
        <div class="reprodutor__comando">
          <button class="rep-lado espectral" aria-label="Voltar 10 segundos">
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
          </button>
          <button class="rep-play" id="rep-play" aria-label="Pausar">
            <i class="fa-solid fa-pause" aria-hidden="true"></i>
          </button>
          <button class="rep-lado espectral" aria-label="Avançar 10 segundos">
            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
          </button>
        </div>

        <div class="reprodutor__onda" id="rep-onda"><canvas></canvas></div>

        <div class="reprodutor__lado-direito">
          <span class="rep-bpm">120 BPM</span>
          <span class="rep-tempo" id="rep-tempo">0:42 / 1:35</span>
        </div>
      </div>
    </div>

    <div class="voto">
      <b>No Spark:</b> substitui o player do feed. A fita reage ao áudio de
      verdade pela Web Audio API — aqui, sem arquivo carregado, ela corre com um
      sinal sintético só para mostrar o movimento.
    </div>
  </section>

  <div class="aviso">
    <b style="color:var(--fg)">Me diga o número.</b> Aplico a variante escolhida em todos os painéis do
    app — cartões, posts, notificações, tópicos, trilho e modais — mantendo as
    regras que já protegem o desempenho: só onde existe fundo para refratar,
    nunca no celular, e com o guarda de qualidade podendo reduzir o raio
    sozinho se a máquina não acompanhar. Dá também para misturar: a
    <b style="color:var(--fg)">Lente</b> nos controles e a
    <b style="color:var(--fg)">Fino</b> nos painéis grandes, por exemplo.
  </div>

</main>

<script>
  // troca de fundo
  const cena = document.getElementById('cena');
  document.querySelectorAll('.grupo button').forEach((b) => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.grupo button').forEach((o) => o.classList.remove('on'));
      b.classList.add('on');
      cena.dataset.fundo = b.dataset.fundo;
    });
  });

  // o interruptor
  const sw = document.getElementById('interruptor');
  sw.addEventListener('click', () => {
    sw.setAttribute('aria-checked', sw.getAttribute('aria-checked') === 'true' ? 'false' : 'true');
  });

  /* os deslizantes: arrastar com mouse ou dedo.
     Um único pointermove por controle, e a largura do miolo é a
     única coisa que muda — nada de recalcular layout do resto. */
  document.querySelectorAll('[data-deslizante]').forEach((el) => {
    const miolo  = el.querySelector('.deslizante__miolo');
    const valor  = el.querySelector('.deslizante__valor');
    const pontos = [...el.querySelectorAll('.deslizante__pontos i')];
    let arrastando = false;

    const ajustar = (clientX) => {
      const r = el.getBoundingClientRect();
      const pct = Math.max(2, Math.min(100, Math.round(((clientX - r.left) / r.width) * 100)));

      miolo.style.width = pct + '%';
      valor.textContent = pct;

      /* os pontos que o miolo já passou ficam acesos.
         A conta é a fração de cada ponto ao longo da faixa central,
         onde eles vivem — comparar com a largura crua marcaria a
         cascata cedo demais. */
      pontos.forEach((p, i) => {
        const posicao = ((i + 0.5) / pontos.length) * 100;
        p.classList.toggle('passou', pct >= posicao);
      });
    };

    el.addEventListener('pointerdown', (ev) => {
      arrastando = true;
      el.classList.add('arrastando');
      el.setPointerCapture(ev.pointerId);
      ajustar(ev.clientX);
    });
    el.addEventListener('pointermove', (ev) => { if (arrastando) ajustar(ev.clientX); });

    const soltar = () => { arrastando = false; el.classList.remove('arrastando'); };
    el.addEventListener('pointerup', soltar);
    el.addEventListener('pointercancel', soltar);

    // estado inicial dos pontos
    ajustar(el.getBoundingClientRect().left + el.getBoundingClientRect().width * (parseInt(el.dataset.valor, 10) / 100));
  });

  /* ==========================================================
     A FITA DE ONDAS

     Mesma técnica do player do app: nove linhas empilhadas, cada
     uma com amplitude e opacidade menores e um deslocamento de
     fase. Curvas suavizadas entre pontos médios, para não haver
     bicos. O brilho é um traço largo e translúcido por baixo do
     fino — sai muito mais barato que shadowBlur.

     Aqui não há arquivo de áudio, então o sinal é sintético: duas
     senoides em velocidades diferentes, que nunca coincidem e por
     isso não repetem visivelmente.
     ========================================================== */
  (() => {
    const caixa = document.getElementById('rep-onda');
    const rep   = document.getElementById('reprodutor');
    const botao = document.getElementById('rep-play');
    const tempo = document.getElementById('rep-tempo');
    if (!caixa) return;

    const canvas = caixa.querySelector('canvas');
    const ctx = canvas.getContext('2d');

    const LINHAS = 9, PONTOS = 72, SUAVIZA = 0.3;
    const atual = new Float32Array(PONTOS);
    const alvo  = new Float32Array(PONTOS);

    let L = 0, A = 0, dpr = 1;
    let fase = 0, progresso = 0.44, tocando = true, laco = null;
    const DURACAO = 95;   // segundos fingidos, só para o relógio

    function medir() {
      const r = caixa.getBoundingClientRect();
      if (!r.width) return;
      dpr = Math.min(devicePixelRatio || 1, 2);
      L = r.width; A = r.height;
      canvas.width  = Math.round(L * dpr);
      canvas.height = Math.round(A * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function amostrar() {
      for (let i = 0; i < PONTOS; i++) {
        const x = i / (PONTOS - 1);
        const v = Math.sin(x * 7.5 + fase) * 0.55
                + Math.sin(x * 13.2 - fase * 1.63) * 0.3
                + Math.sin(x * 3.1 + fase * 0.42) * 0.2;
        // realce nas bordas para a fita não morrer nas pontas
        alvo[i] = (0.3 + Math.abs(v) * 0.6) * Math.sin(x * Math.PI);
      }
    }

    function desenhar() {
      if (!L || !A) return;

      for (let i = 0; i < PONTOS; i++) atual[i] += (alvo[i] - atual[i]) * SUAVIZA;

      ctx.clearRect(0, 0, L, A);

      const meio = A / 2;
      const px = L / (PONTOS - 1);
      const p = Math.max(0.001, Math.min(0.999, progresso));

      // osso no que já tocou, violeta no que falta — a paleta do site
      const grad = ctx.createLinearGradient(0, 0, L, 0);
      grad.addColorStop(0, '#f0eee4');
      grad.addColorStop(Math.max(0, p - 0.02), '#dbd9cf');
      grad.addColorStop(Math.min(1, p + 0.02), '#7f00ff');
      grad.addColorStop(1, '#a64dff');

      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';

      for (let linha = 0; linha < LINHAS; linha++) {
        const t = linha / (LINHAS - 1);
        const amplitude = (A * 0.4) * (1 - t * 0.62);
        const alfa = (1 - t) * 0.72 + 0.06;
        const desloc = (t - 0.5) * A * 0.1;

        ctx.beginPath();
        for (let i = 0; i < PONTOS; i++) {
          const x = i * px;
          const onda = atual[i] * Math.cos(t * 1.5 + i * 0.09 + fase * 0.35);
          const y = meio + desloc + onda * amplitude;
          if (i === 0) { ctx.moveTo(x, y); continue; }

          const xa = (i - 1) * px;
          const ondaA = atual[i - 1] * Math.cos(t * 1.5 + (i - 1) * 0.09 + fase * 0.35);
          const ya = meio + desloc + ondaA * amplitude;
          ctx.quadraticCurveTo(xa, ya, (xa + x) / 2, (ya + y) / 2);
        }

        ctx.strokeStyle = grad;
        ctx.globalAlpha = alfa * 0.22;
        ctx.lineWidth = 5 - t * 3;
        ctx.stroke();

        ctx.globalAlpha = alfa;
        ctx.lineWidth = 1.6 - t * 0.9;
        ctx.stroke();
      }

      // cabeça de leitura
      ctx.globalAlpha = 0.5;
      ctx.lineWidth = 1;
      ctx.strokeStyle = '#f0eee4';
      ctx.beginPath();
      ctx.moveTo(p * L, A * 0.12);
      ctx.lineTo(p * L, A * 0.88);
      ctx.stroke();
      ctx.globalAlpha = 1;
    }

    const mmss = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

    function quadro() {
      if (!tocando) { laco = null; return; }
      fase += 0.045;
      progresso += 1 / (DURACAO * 60);
      if (progresso > 1) progresso = 0;

      amostrar();
      desenhar();
      tempo.textContent = `${mmss(progresso * DURACAO)} / ${mmss(DURACAO)}`;
      laco = requestAnimationFrame(quadro);
    }

    function ligar()  { if (!laco) laco = requestAnimationFrame(quadro); }
    function parar()  { if (laco) { cancelAnimationFrame(laco); laco = null; } }

    botao.addEventListener('click', () => {
      tocando = !tocando;
      rep.classList.toggle('tocando', tocando);
      botao.querySelector('i').className = `fa-solid fa-${tocando ? 'pause' : 'play'}`;
      botao.setAttribute('aria-label', tocando ? 'Pausar' : 'Tocar');
      if (tocando) ligar(); else parar();
    });

    // clicar ou arrastar na onda pula para aquele ponto
    let buscando = false;
    const buscar = (clientX) => {
      const r = caixa.getBoundingClientRect();
      progresso = Math.max(0, Math.min(1, (clientX - r.left) / r.width));
      tempo.textContent = `${mmss(progresso * DURACAO)} / ${mmss(DURACAO)}`;
      if (!tocando) desenhar();
    };
    caixa.addEventListener('pointerdown', (ev) => { buscando = true; caixa.setPointerCapture(ev.pointerId); buscar(ev.clientX); });
    caixa.addEventListener('pointermove', (ev) => { if (buscando) buscar(ev.clientX); });
    caixa.addEventListener('pointerup',     () => { buscando = false; });
    caixa.addEventListener('pointercancel', () => { buscando = false; });

    // nada de laço com a aba escondida
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) parar(); else if (tocando) ligar();
    });

    if (window.ResizeObserver) new ResizeObserver(() => { medir(); desenhar(); }).observe(caixa);
    medir();
    amostrar();
    for (let i = 0; i < PONTOS; i++) atual[i] = alvo[i];
    desenhar();

    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) ligar();
    else { tocando = false; rep.classList.remove('tocando'); }
  })();
</script>
</body>
</html>
