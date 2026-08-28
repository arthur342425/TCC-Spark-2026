/* ============================================================
   SPARK — campo de ondas

   Um mar de partículas erguido por duas oitavas de ruído Simplex,
   com brilho aditivo. O cursor abre a superfície onde aponta.

   Adaptações em relação à referência, e por quê:

   · COR — a referência é verde-esmeralda. Aqui tudo vem da paleta
     do site: violeta na crista, grafite no vale, osso no clarão.

   · A CENA É DESENHADA UMA VEZ — o original tem três compositores, e
     dois deles rasterizam a cena inteira de novo. Aqui sobrou um
     RenderPass só: o bloom já devolve a cena COM o brilho somado, e
     o passe final apenas compõe fundo, chama de canto e esse
     resultado. Metade do trabalho de vértice por quadro.

   · QUALIDADE ADAPTATIVA — mede o quadro real e cede em três degraus:
     primeiro desliga o bloom (a passagem mais cara), depois baixa a
     resolução, e só então desiste e devolve a aurora. Perder o halo é
     bem menos grave que perder o site.

   · ONDE RODA — só nas telas de entrada (login, Pro). Dentro do app
     o feed, o analisador de áudio e a navegação líquida já disputam
     a GPU; somar um campo de dezenas de milhares de partículas ali
     derrubaria justamente o que o usuário veio usar.

   · QUANDO NÃO RODA — celular, tela pequena, pouca CPU, ou
     prefers-reduced-motion. Nesses casos a aurora original fica,
     e ninguém perde nada.
   ============================================================ */

const CFG = {
  // --- paleta do Spark ---
  fundo:      '#121214',   // grafite
  chamaA:     '#7f00ff',   // violeta
  chamaB:     '#dbd9cf',   // osso
  chamaForca: 0.16,
  poeiraCor:  '#c9a6ff',
  poeiraQtd:  260,
  poeiraTam:  22,

  corVale:    '#150a24',   // violeta quase preto
  corCrista:  '#a64dff',   // violeta claro

  opacidade:  0.24,
  tamanho:    5.0,
  brilho:     0.5,

  altura:     3,
  fluxo:      1,
  escala:     0.275,

  parallax:      1.2,
  raioCursor:    7.0,
  forcaCursor:   0.9,
};

const lerp  = (a, b, t) => a + (b - a) * t;
const trava = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

/* ---------- pode rodar aqui? ---------- */
function permitido() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;
  if (window.innerWidth < 900) return false;                 // no celular não compensa
  if ((navigator.hardwareConcurrency || 4) < 4) return false; // máquina fraca
  try {
    const c = document.createElement('canvas');
    if (!(c.getContext('webgl') || c.getContext('experimental-webgl'))) return false;
  } catch { return false; }
  return true;
}

const SNOISE = `
vec4 permute(vec4 x){return mod(((x*34.0)+1.0)*x, 289.0);}
vec4 taylorInvSqrt(vec4 r){return 1.79284291400159 - 0.85373472095314 * r;}
float snoise(vec3 v){
  const vec2 C = vec2(1.0/6.0, 1.0/3.0); const vec4 D = vec4(0.0, 0.5, 1.0, 2.0);
  vec3 i = floor(v + dot(v, C.yyy)); vec3 x0 = v - i + dot(i, C.xxx);
  vec3 g = step(x0.yzx, x0.xyz); vec3 l = 1.0 - g;
  vec3 i1 = min(g.xyz, l.zxy); vec3 i2 = max(g.xyz, l.zxy);
  vec3 x1 = x0 - i1 + 1.0 * C.xxx; vec3 x2 = x0 - i2 + 2.0 * C.xxx; vec3 x3 = x0 - 1.0 + 3.0 * C.xxx;
  i = mod(i, 289.0);
  vec4 p = permute(permute(permute(i.z + vec4(0.0, i1.z, i2.z, 1.0)) + i.y + vec4(0.0, i1.y, i2.y, 1.0)) + i.x + vec4(0.0, i1.x, i2.x, 1.0));
  float n_ = 1.0/7.0; vec3 ns = n_ * D.wyz - D.xzx;
  vec4 j = p - 49.0 * floor(p * ns.z *ns.z);
  vec4 x_ = floor(j * ns.z); vec4 y_ = floor(j - 7.0 * x_);
  vec4 x = x_ *ns.x + ns.yyyy; vec4 y = y_ *ns.x + ns.yyyy; vec4 h = 1.0 - abs(x) - abs(y);
  vec4 b0 = vec4(x.xy, y.xy); vec4 b1 = vec4(x.zw, y.zw);
  vec4 s0 = floor(b0)*2.0 + 1.0; vec4 s1 = floor(b1)*2.0 + 1.0; vec4 sh = -step(h, vec4(0.0));
  vec4 a0 = b0.xzyw + s0.xzyw*sh.xxyy; vec4 a1 = b1.xzyw + s1.xzyw*sh.zzww;
  vec3 p0 = vec3(a0.xy,h.x); vec3 p1 = vec3(a0.zw,h.y); vec3 p2 = vec3(a1.xy,h.z); vec3 p3 = vec3(a1.zw,h.w);
  vec4 norm = taylorInvSqrt(vec4(dot(p0,p0), dot(p1,p1), dot(p2, p2), dot(p3,p3)));
  p0 *= norm.x; p1 *= norm.y; p2 *= norm.z; p3 *= norm.w;
  vec4 m = max(0.5 - vec4(dot(x0,x0), dot(x1,x1), dot(x2,x2), dot(x3,x3)), 0.0); m = m * m;
  return 42.0 * dot(m*m, vec4(dot(p0,x0), dot(p1,x1), dot(p2,x2), dot(p3,x3)));
}`;

export async function iniciarOndas() {
  if (!permitido()) return false;

  let THREE, EffectComposer, RenderPass, UnrealBloomPass, ShaderPass, GammaCorrectionShader;
  try {
    THREE = await import('three');
    ({ EffectComposer }     = await import('three/addons/postprocessing/EffectComposer.js'));
    ({ RenderPass }         = await import('three/addons/postprocessing/RenderPass.js'));
    ({ UnrealBloomPass }    = await import('three/addons/postprocessing/UnrealBloomPass.js'));
    ({ ShaderPass }         = await import('three/addons/postprocessing/ShaderPass.js'));
    ({ GammaCorrectionShader } = await import('three/addons/shaders/GammaCorrectionShader.js'));
  } catch (e) {
    console.info('[spark] ondas indisponíveis:', e.message);
    return false;   // sem CDN, a aurora continua no lugar
  }

  const hex = (h) => {
    const n = parseInt(h.slice(1), 16);
    return new THREE.Vector3(((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255);
  };

  /* ---------- tela ---------- */
  const canvas = document.createElement('canvas');
  canvas.className = 'ondas';
  canvas.setAttribute('aria-hidden', 'true');
  document.body.prepend(canvas);

  const renderer = new THREE.WebGL1Renderer({ canvas, antialias: true, alpha: true });
  // o valor definitivo vem de medir(), que respeita o guarda de qualidade

  const cena = new THREE.Scene();
  cena.fog = new THREE.Fog(0x000000, 0, 15);

  const camera = new THREE.PerspectiveCamera(45, innerWidth / innerHeight, 0.1, 400);
  camera.position.set(0, 6.2, 15);
  cena.add(camera);

  /* ---------- o mar ---------- */
  // Densidade conforme a máquina: numa GPU integrada 120 mil pontos
  // custam caro e a diferença visual é pequena.
  const forte = (navigator.hardwareConcurrency || 4) >= 8;
  const geo = new THREE.SphereGeometry(4.2, forte ? 150 : 96, forte ? 380 : 240);

  const uni = {
    uTime: { value: 0 }, uStream: { value: 0 }, uAppear: { value: 0 },
    uColLow:  { value: hex(CFG.corVale) },
    uColHigh: { value: hex(CFG.corCrista) },
    uOpacity: { value: CFG.opacidade }, uSize: { value: CFG.tamanho },
    uBrightness: { value: CFG.brilho }, uWaveHeight: { value: CFG.altura },
    uFlow: { value: CFG.fluxo }, uScale: { value: CFG.escala },
    uCursor: { value: new THREE.Vector3() },
    uRepelRadius: { value: CFG.raioCursor },
    uRepelStrength: { value: CFG.forcaCursor },
    uActivity: { value: 0 },
  };

  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: uni,
    vertexShader: `
uniform float uTime; uniform float uStream; uniform float uSize; uniform float uWaveHeight; uniform float uFlow; uniform float uScale;
uniform vec3 uColLow; uniform vec3 uColHigh;
uniform vec3 uCursor; uniform float uRepelRadius; uniform float uRepelStrength; uniform float uActivity;
varying float vFade; varying vec3 vColor;
${SNOISE}
void main() {
  vec3 wp = vec3(position.x * 13.0, 0.0, position.z * 25.0);
  wp.x += position.y * 6.0;
  float zc = wp.z + uStream;
  float wn = snoise(vec3(wp.x * 0.08, zc * 0.08, uTime * 0.15 * uFlow)) * 2.0;
  wn += snoise(vec3(wp.x * 0.16, zc * 0.16, uTime * 0.3 * uFlow)) * 0.8;
  wp.y += wn * uWaveHeight;

  vec3 finalPos = wp * uScale;
  vec4 modelPosition = modelMatrix * vec4(finalPos, 1.0);
  vec3 toP = modelPosition.xyz - uCursor;
  float cd = length(toP);
  float fall = smoothstep(uRepelRadius, 0.0, cd);
  modelPosition.xyz += normalize(toP + vec3(0.0001)) * fall * uRepelStrength * uActivity;
  vec4 mvPosition = viewMatrix * modelPosition;

  float colMix = smoothstep(-3.0, 3.0, position.y + position.x * 0.5);
  vColor = mix(uColLow, uColHigh, clamp(colMix, 0.0, 1.0));
  vFade = 1.0;

  gl_PointSize = uSize * (10.0 / -mvPosition.z);
  gl_PointSize = max(gl_PointSize, 1.5);
  gl_Position = projectionMatrix * mvPosition;
}`,
    fragmentShader: `
uniform float uOpacity; uniform float uBrightness; uniform float uAppear;
varying float vFade; varying vec3 vColor;
void main() {
  vec2 xy = gl_PointCoord - 0.5;
  float ll = length(xy);
  if (ll > 0.5) discard;
  float a = smoothstep(0.5, 0.1, ll);
  gl_FragColor = vec4(vColor * uBrightness, vFade * a * uOpacity * uAppear);
}`,
  });

  const pontos = new THREE.Points(geo, mat);
  pontos.frustumCulled = false;
  const grupo = new THREE.Group();
  grupo.add(pontos);
  cena.add(grupo);

  /* ---------- poeira que segue a câmera ---------- */
  const N = CFG.poeiraQtd;
  const pos = new Float32Array(N * 3), tam = new Float32Array(N), sem = new Float32Array(N);
  for (let i = 0; i < N; i++) {
    pos[i * 3]     = 2 * Math.random() - 1;
    pos[i * 3 + 1] = 2 * Math.random() - 1;
    pos[i * 3 + 2] = 2 * Math.random() - 1;
    tam[i] = CFG.poeiraTam * (0.4 + Math.random());
    sem[i] = Math.random();
  }
  const gPoeira = new THREE.BufferGeometry();
  gPoeira.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  gPoeira.setAttribute('size', new THREE.BufferAttribute(tam, 1));
  gPoeira.setAttribute('seed', new THREE.BufferAttribute(sem, 1));

  const mPoeira = new THREE.ShaderMaterial({
    transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, depthTest: false,
    uniforms: {
      uTime: { value: 0 },
      uColor: { value: hex(CFG.poeiraCor) },
      uRes: { value: new THREE.Vector2(innerWidth * devicePixelRatio, innerHeight * devicePixelRatio) },
    },
    vertexShader: `
attribute float size; attribute float seed; uniform float uTime; uniform vec2 uRes;
varying float vA;
vec3 warp(vec3 p, float t){ float c=0.9,a=1.9,b=0.02,s=0.05; p*=2.;
  p.x+=c*sin(s*t+a*p.y)+t*b; p.y+=c*cos(s*t+a*p.x); p.y+=c*sin(s*t+a*p.z)+t*b;
  p.z+=c*cos(s*t+a*p.y); p.z+=c*sin(s*t+a*p.x)+t*b; p.x+=c*cos(s*t+a*p.z);
  return cos(p+vec3(1,2,4)); }
void main(){
  vec3 v = position*4.0 + warp(position, uTime)*1.2;
  vec4 mv = modelViewMatrix * vec4(v, 1.0);
  float r = length(v); float farF = 1.0 - smoothstep(5.0, 6.5, r); float nearF = smoothstep(0.0, 0.5, -mv.z);
  vA = farF * nearF;
  gl_PointSize = size * uRes.y / 900.0 / -mv.z; gl_PointSize = max(gl_PointSize, 1.0);
  gl_Position = projectionMatrix * mv;
}`,
    fragmentShader: `
uniform vec3 uColor; varying float vA;
void main(){ vec2 p = gl_PointCoord - 0.5; float l = length(p); if (l > 0.5) discard;
  float tex = smoothstep(0.5, 0.0, l); gl_FragColor = vec4(uColor * tex, tex * vA * 0.6); }`,
  });

  const poeira = new THREE.Points(gPoeira, mPoeira);
  poeira.frustumCulled = false;
  cena.add(poeira);

  /* ---------- composição ---------- */
  const passeRender = new RenderPass(cena, camera);

  const passeBloom = new UnrealBloomPass(new THREE.Vector2(innerWidth, innerHeight), 0.4, 0.55, 0);

  const bloom = new EffectComposer(renderer);
  bloom.renderToScreen = false;
  bloom.addPass(passeRender);
  bloom.addPass(passeBloom);
  bloom.addPass(new ShaderPass(GammaCorrectionShader));

  const passeFinal = new ShaderPass({
    uniforms: {
      // tDiffuse fica declarado porque o EffectComposer o preenche
      // sozinho em todo ShaderPass; o shader simplesmente não o lê.
      iTime: { value: 0 }, tDiffuse: { value: null }, bloomTexture: { value: null },
      uBg: { value: hex(CFG.fundo) },
      uFlameA: { value: hex(CFG.chamaA) },
      uFlameB: { value: hex(CFG.chamaB) },
      uFlameAmt: { value: CFG.chamaForca },
    },
    vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = vec4(position, 1.0); }`,
    fragmentShader: `
uniform float iTime; uniform sampler2D bloomTexture;
uniform vec3 uBg; uniform vec3 uFlameA; uniform vec3 uFlameB; uniform float uFlameAmt;
varying vec2 vUv;
vec3 warp3d(vec3 pos, float t){ float curv=.8,a=1.9,b=0.7; pos*=2.;
  pos.x+=curv*sin(t+a*pos.y)+t*b; pos.y+=curv*cos(t+a*pos.x);
  pos.y+=curv*sin(t+a*pos.z)+t*b; pos.z+=curv*cos(t+a*pos.y);
  pos.z+=curv*sin(t+a*pos.x)+t*b; pos.x+=curv*cos(t+a*pos.z);
  return 0.5+0.5*cos(pos.xyz+vec3(1,2,4)); }
void main(){
  vec2 uv = 2.*vUv - 1.;
  vec3 w = pow(warp3d(vec3(uv.x, sin(uv.y), uv.y), iTime*1.5), vec3(1.5));
  vec3 flame = 1.5*uFlameA*w.x; flame*=w.y; flame += uFlameB*w.z;
  flame *= smoothstep(0.25, 1., abs(uv.y));
  float md = smoothstep(-0.7, 1., -uv.y*uv.x); flame *= md*md;
  vec3 bg = uBg * (1.0 - 0.4 * length(uv));
  // bloomTexture já traz a cena COM o brilho somado — o UnrealBloomPass
  // compõe sobre a entrada, não devolve só o halo. Por isso não há
  // segunda amostra da cena aqui.
  gl_FragColor = vec4(bg + flame*uFlameAmt + texture2D(bloomTexture, vUv).xyz, 1.);
}`,
  });

  /* O composer final NÃO tem RenderPass.
     Tinha, e isso desenhava as ~60 mil partículas uma segunda vez por
     quadro só para somar o mesmo pixel que o bloom já entregava.
     Agora a cena é rasterizada uma vez; o final só compõe fundo,
     chama de canto e o resultado do bloom. */
  const final = new EffectComposer(renderer);
  final.addPass(passeFinal);
  passeFinal.uniforms.bloomTexture.value = bloom.renderTarget1.texture;

  /* ---------- entrada do usuário ---------- */
  const alvoMouse = { x: 0, y: 0 }, mouse = { x: 0, y: 0 };
  const PT = { mundo: new THREE.Vector3(), atividade: 0, ativo: false, ultimo: performance.now() };

  addEventListener('mousemove', (ev) => {
    alvoMouse.x = (ev.clientX / innerWidth) * 2 - 1;
    alvoMouse.y = -((ev.clientY / innerHeight) * 2 - 1);
    PT.ativo = true; PT.ultimo = performance.now();
  }, { passive: true });
  addEventListener('mouseout', () => { PT.ativo = false; }, { passive: true });

  let alvoRolagem = 0, rolagemSuave = 0, rolagem = 0;
  addEventListener('scroll', () => {
    const max = document.documentElement.scrollHeight - innerHeight;
    alvoRolagem = max > 0 ? trava(scrollY / max, 0, 1) : 0;
  }, { passive: true });

  const _ndc = new THREE.Vector3(), _dir = new THREE.Vector3(), _tgt = new THREE.Vector3();
  function cursorNoMundo() {
    _tgt.set(0, 0, 0);
    if (PT.ativo) {
      _ndc.set(mouse.x, mouse.y, 0.5).unproject(camera);
      _dir.copy(_ndc).sub(camera.position).normalize();
      const dn = _dir.z;
      if (Math.abs(dn) > 1e-4) {
        const tt = -camera.position.z / dn;
        if (tt > 0 && Number.isFinite(tt)) _tgt.copy(camera.position).addScaledVector(_dir, tt);
      }
    }
    PT.mundo.lerp(_tgt, 0.12);
    const parado = (performance.now() - PT.ultimo) / 1000;
    PT.atividade += (((PT.ativo && parado < 3) ? 1 : 0) - PT.atividade) * 0.06;
  }

  /* ---------------------------------------------------------
     GUARDA DE QUALIDADE

     Não dá para saber de antemão se a máquina aguenta: o número de
     núcleos diz pouco sobre a GPU. Então medimos o quadro de
     verdade e reagimos.

     Dois degraus:
       · abaixo de ~40 fps → corta a resolução do render pela metade
         (o bloom é limitado por preenchimento; é aí que dói)
       · abaixo de ~24 fps mesmo depois disso → desliga e devolve a
         aurora. Um fundo bonito não vale um site travado.
     --------------------------------------------------------- */
  const QUALIDADE = { nivel: 1, degrau: 0, amostras: [], avaliado: false };

  function encerrar() {
    vivo = false;
    removeEventListener('resize', medir);
    try { renderer.dispose(); geo.dispose(); mat.dispose(); gPoeira.dispose(); mPoeira.dispose(); } catch {}
    canvas.remove();
    document.body.classList.remove('com-ondas');
    console.info('[spark] ondas desligadas: desempenho abaixo do aceitável');
  }

  function avaliar(dt) {
    if (QUALIDADE.avaliado) return;

    // ignora o primeiro segundo: compilar shader e subir textura pesa
    if (performance.now() - nasceu < 1000) return;

    QUALIDADE.amostras.push(dt);
    if (QUALIDADE.amostras.length < 60) return;

    const media = QUALIDADE.amostras.reduce((a, b) => a + b, 0) / QUALIDADE.amostras.length;
    const fps = 1 / media;
    QUALIDADE.amostras.length = 0;

    if (fps >= 40) { QUALIDADE.avaliado = true; return; }

    // primeiro o bloom, depois a resolução, e só então desiste
    if (QUALIDADE.degrau === 0) { QUALIDADE.degrau = 1; passeBloom.enabled = false; return; }
    if (QUALIDADE.degrau === 1) { QUALIDADE.degrau = 2; QUALIDADE.nivel = 0.6; medir(); return; }

    QUALIDADE.avaliado = true;
    encerrar();
  }

  /* ---------- laço ---------- */
  let stream = 0, t0 = performance.now() / 1000;
  const nasceu = performance.now();
  let vivo = true;

  function quadro() {
    if (!vivo) return;

    // aba escondida: nada a desenhar, e a GPU agradece
    if (document.hidden) { requestAnimationFrame(quadro); return; }

    const t = performance.now() / 1000;
    const dt = Math.min(0.05, t - t0); t0 = t;
    avaliar(dt);

    rolagemSuave = lerp(rolagemSuave, alvoRolagem, 0.10);
    rolagem      = lerp(rolagem, rolagemSuave, 0.06);
    mouse.x = lerp(mouse.x, alvoMouse.x, 0.06);
    mouse.y = lerp(mouse.y, alvoMouse.y, 0.06);

    uni.uTime.value = t;
    stream += dt * (CFG.fluxo * 2.0) * 4.0;
    uni.uStream.value = stream;
    uni.uWaveHeight.value = CFG.altura * (1 + rolagem);

    const ea = Math.min(rolagem / 0.35, 1);
    const e = ea * ea * (3 - 2 * ea);
    camera.position.set(mouse.x * CFG.parallax, lerp(6.2, 1.0, e) + mouse.y * CFG.parallax * 0.3, lerp(15, -1, e));
    camera.lookAt(mouse.x * CFG.parallax * 0.5, lerp(0, 0.6, e), lerp(2, -14, e));

    cursorNoMundo();
    uni.uCursor.value.copy(PT.mundo);
    uni.uActivity.value = PT.atividade;
    uni.uAppear.value = trava(((performance.now() - nasceu) / 1000 - 0.2) / 1.4, 0, 1);

    mPoeira.uniforms.uTime.value = t * 8.0;
    poeira.position.copy(camera.position);
    passeFinal.uniforms.iTime.value = t;

    bloom.render();
    final.render();

    requestAnimationFrame(quadro);
  }

  function medir() {
    const w = innerWidth, h = innerHeight;

    /* Teto de 1.5 em vez do devicePixelRatio cheio: numa tela 2x isso
       é 44% menos pixel para o bloom processar, e num fundo desfocado
       ninguém percebe a diferença. O nível cai mais se o guarda de
       qualidade pedir. */
    const dpr = Math.min(devicePixelRatio, 1.5) * QUALIDADE.nivel;

    renderer.setPixelRatio(dpr);
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();

    // bloom a meia resolução — ver a explicação em estrelas.js
    bloom.setPixelRatio(dpr);
    bloom.setSize(Math.round(w / 2), Math.round(h / 2));

    final.setPixelRatio(dpr);
    final.setSize(w, h);

    mPoeira.uniforms.uRes.value.set(w * dpr, h * dpr);
  }

  addEventListener('resize', medir, { passive: true });
  medir();
  requestAnimationFrame(quadro);

  document.body.classList.add('com-ondas');
  return true;
}
