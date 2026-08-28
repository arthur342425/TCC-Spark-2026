/* ============================================================
   SPARK — túnel de estrelas

   Um volume denso de pontos envolvendo a câmera, correndo em
   direção a ela. Cada estrela cintila na própria fase enquanto o
   campo inteiro gira devagar. O cursor conduz o rumo e afasta as
   estrelas próximas.

   Fica atrás do app inteiro: feed, explorar, fóruns, direct, tudo.

   Adaptações e por quê:

   · COR — a referência é menta/jade. Aqui é osso, violeta claro e
     violeta puro, na paleta do site.

   · UM COMPOSITOR — o original usa três, e dois deles rasterizam a
     cena de novo para somar o mesmo pixel. Como o app roda por
     cima, cada quadro desperdiçado sai do orçamento da interface.
     Sobrou um RenderPass; o passe final só compõe.

   · DISCRIÇÃO — brilho e opacidade bem abaixo da referência. Isto é
     fundo de aplicação, não protagonista: precisa dar profundidade
     sem competir com o texto que fica em cima.

   · QUALIDADE ADAPTATIVA — mede o quadro real e reage. Se não
     alcançar, desliga e devolve a aurora.
   ============================================================ */

const CFG = {
  fundo:   '#121214',   // grafite
  chamaA:  '#7f00ff',   // violeta
  chamaB:  '#dbd9cf',   // osso
  chamaForca: 0.14,

  corA: '#dbd9cf',      // osso
  corB: '#a64dff',      // violeta claro
  corC: '#7f00ff',      // violeta

  quantidade: 4200,
  profundidade: 30,

  opacidade: 0.85,      // referência usa 2; aqui é fundo
  tamanho:   46,
  brilho:    1.15,      // referência usa 1.85

  deriva:    2.0,
  cintilo:   1,
  giro:      0.028,

  raioCursor:  5,
  forcaCursor: 0.35,

  mergulhoRolagem: 3,   // referência usa 8; aqui seria enjoativo
  derivaRolagem:   4,
  giroRolagem:     0.07,
  parallax:        0.5,
};

const lerp  = (a, b, t) => a + (b - a) * t;
const trava = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

function permitido() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;
  if (window.innerWidth < 820) return false;
  if ((navigator.hardwareConcurrency || 4) < 4) return false;
  try {
    const c = document.createElement('canvas');
    if (!(c.getContext('webgl') || c.getContext('experimental-webgl'))) return false;
  } catch { return false; }
  return true;
}

export async function iniciarEstrelas() {
  if (!permitido()) return false;

  let THREE, EffectComposer, RenderPass, UnrealBloomPass, ShaderPass, GammaCorrectionShader;
  try {
    THREE = await import('three');
    ({ EffectComposer }        = await import('three/addons/postprocessing/EffectComposer.js'));
    ({ RenderPass }            = await import('three/addons/postprocessing/RenderPass.js'));
    ({ UnrealBloomPass }       = await import('three/addons/postprocessing/UnrealBloomPass.js'));
    ({ ShaderPass }            = await import('three/addons/postprocessing/ShaderPass.js'));
    ({ GammaCorrectionShader } = await import('three/addons/shaders/GammaCorrectionShader.js'));
  } catch (e) {
    console.info('[spark] estrelas indisponíveis:', e.message);
    return false;
  }

  const hex = (h) => {
    const n = parseInt(h.slice(1), 16);
    return new THREE.Vector3(((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255);
  };

  const canvas = document.createElement('canvas');
  canvas.className = 'estrelas';
  canvas.setAttribute('aria-hidden', 'true');
  document.body.prepend(canvas);

  const renderer = new THREE.WebGL1Renderer({ canvas, antialias: true, alpha: true });
  const cena = new THREE.Scene();
  cena.fog = new THREE.Fog(0x000000, 0, 15);

  const camera = new THREE.PerspectiveCamera(45, innerWidth / innerHeight, 0.1, 80);
  camera.position.set(0, 0, 5);
  cena.add(camera);

  /* ---------- as estrelas ---------- */
  const N = CFG.quantidade, D = CFG.profundidade;
  const pos = new Float32Array(N * 3);
  const escalas = new Float32Array(N), fases = new Float32Array(N);
  const paleta = new Float32Array(N), brilhos = new Float32Array(N);

  for (let i = 0; i < N; i++) {
    const i3 = i * 3;
    pos[i3]     = (Math.random() - 0.5) * 24;
    pos[i3 + 1] = (Math.random() - 0.5) * 16;
    pos[i3 + 2] = (Math.random() - 0.5) * D;   // igual à profundidade: a volta é sem costura
    paleta[i]  = Math.floor(Math.random() * 3);
    brilhos[i] = 0.7 + Math.random() * 0.6;
    escalas[i] = 0.5 + Math.pow(Math.random(), 1.4) * 2.5;
    fases[i]   = Math.random();
  }

  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  geo.setAttribute('aScale',   new THREE.Float32BufferAttribute(escalas, 1));
  geo.setAttribute('aPhase',   new THREE.Float32BufferAttribute(fases, 1));
  geo.setAttribute('aPalette', new THREE.Float32BufferAttribute(paleta, 1));
  geo.setAttribute('aBright',  new THREE.Float32BufferAttribute(brilhos, 1));

  const uni = {
    uTime: { value: 0 }, uSize: { value: CFG.tamanho }, uOpacity: { value: 0 },
    uDrift: { value: 0 }, uDepth: { value: D }, uTwinkle: { value: CFG.cintilo },
    uCursor: { value: new THREE.Vector3() },
    uRepelRadius: { value: CFG.raioCursor },
    uRepelStrength: { value: CFG.forcaCursor },
    uActivity: { value: 0 },
    uColorA: { value: hex(CFG.corA) },
    uColorB: { value: hex(CFG.corB) },
    uColorC: { value: hex(CFG.corC) },
    uBrightness: { value: CFG.brilho },
  };

  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: uni,
    vertexShader: `
uniform float uTime; uniform float uSize; uniform float uDrift; uniform float uDepth; uniform float uTwinkle;
uniform vec3 uCursor; uniform float uRepelRadius; uniform float uRepelStrength; uniform float uActivity;
uniform vec3 uColorA; uniform vec3 uColorB; uniform vec3 uColorC;
attribute float aScale; attribute float aPhase; attribute float aPalette; attribute float aBright;
varying vec3 vColor; varying float vTwinkle;
void main() {
  vec3 pos = position;
  pos.z = mod(pos.z + uDrift + (uDepth * 0.5), uDepth) - (uDepth * 0.5);

  float tw = sin(uTime * 1.6 + aPhase * 6.2831);
  vTwinkle = (1.0 - uTwinkle) + uTwinkle * (0.55 + 0.45 * tw);

  vec4 modelPosition = modelMatrix * vec4(pos, 1.0);

  vec3 toParticle = modelPosition.xyz - uCursor;
  float dist = length(toParticle);
  float falloff = smoothstep(uRepelRadius, 0.0, dist);
  modelPosition.xyz += normalize(toParticle + vec3(0.0001)) * falloff * uRepelStrength * uActivity;

  vec4 viewPosition = viewMatrix * modelPosition;
  gl_Position = projectionMatrix * viewPosition;
  gl_PointSize = uSize * aScale;
  gl_PointSize *= (1.0 / -viewPosition.z);

  vec3 base = aPalette < 0.5 ? uColorA : (aPalette < 1.5 ? uColorB : uColorC);
  vColor = base * aBright;
}`,
    fragmentShader: `
uniform float uOpacity; uniform float uBrightness;
varying vec3 vColor; varying float vTwinkle;
void main() {
  vec2 uv = gl_PointCoord - 0.5;
  float d = length(uv);
  if (d > 0.5) discard;
  float strength = pow(1.0 - d * 2.0, 4.0);
  vec3 color = mix(vec3(0.0), vColor, strength);
  gl_FragColor = vec4(color * uBrightness, strength * uOpacity * vTwinkle);
}`,
  });

  const pontos = new THREE.Points(geo, mat);
  pontos.frustumCulled = false;
  const grupo = new THREE.Group();
  grupo.add(pontos);
  cena.add(grupo);

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
  gl_FragColor = vec4(bg + flame*uFlameAmt + texture2D(bloomTexture, vUv).xyz, 1.);
}`,
  });

  const final = new EffectComposer(renderer);
  final.addPass(passeFinal);
  passeFinal.uniforms.bloomTexture.value = bloom.renderTarget1.texture;

  /* ---------- entrada ---------- */
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
      if (Math.abs(_dir.z) > 1e-4) {
        const tt = -camera.position.z / _dir.z;
        if (tt > 0 && Number.isFinite(tt)) _tgt.copy(camera.position).addScaledVector(_dir, tt);
      }
    }
    PT.mundo.lerp(_tgt, 0.12);
    const parado = (performance.now() - PT.ultimo) / 1000;
    PT.atividade += (((PT.ativo && parado < 3) ? 1 : 0) - PT.atividade) * 0.06;
  }

  /* ---------- guarda de qualidade ---------- */
  const Q = { nivel: 1, degrau: 0, amostras: [], avaliado: false };

  function encerrar() {
    vivo = false;
    removeEventListener('resize', medir);
    try { renderer.dispose(); geo.dispose(); mat.dispose(); } catch {}
    canvas.remove();
    document.body.classList.remove('com-estrelas');
    console.info('[spark] estrelas desligadas: desempenho abaixo do aceitável');
  }

  /* Três degraus, do mais barato de perder para o mais caro:

       1. desliga o BLOOM — cinco níveis de desfoque separável somem de
          uma vez. É a maior economia isolada e o campo continua legível,
          só sem o halo.
       2. baixa a RESOLUÇÃO do render.
       3. desiste e devolve a aurora.

     Perder o halo é bem menos grave que perder o site. */
  function avaliar(dt) {
    if (Q.avaliado || performance.now() - nasceu < 1000) return;
    Q.amostras.push(dt);
    if (Q.amostras.length < 60) return;

    const fps = 1 / (Q.amostras.reduce((a, b) => a + b, 0) / Q.amostras.length);
    Q.amostras.length = 0;

    if (fps >= 42) { Q.avaliado = true; return; }

    if (Q.degrau === 0) { Q.degrau = 1; passeBloom.enabled = false; return; }
    if (Q.degrau === 1) { Q.degrau = 2; Q.nivel = 0.65; medir(); return; }

    Q.avaliado = true;
    encerrar();
  }

  /* ---------- laço ---------- */
  let t0 = performance.now() / 1000;
  const nasceu = performance.now();
  let vivo = true;

  function quadro() {
    if (!vivo) return;
    if (document.hidden) { requestAnimationFrame(quadro); return; }

    const t = performance.now() / 1000;
    const dt = Math.min(0.05, t - t0); t0 = t;
    avaliar(dt);

    rolagemSuave = lerp(rolagemSuave, alvoRolagem, 0.10);
    rolagem      = lerp(rolagem, rolagemSuave, 0.06);
    mouse.x = lerp(mouse.x, alvoMouse.x, 0.06);
    mouse.y = lerp(mouse.y, alvoMouse.y, 0.06);

    uni.uTime.value = t;
    uni.uDrift.value += dt * (CFG.deriva + rolagem * CFG.derivaRolagem);

    const px = mouse.x * CFG.parallax, py = mouse.y * CFG.parallax;
    camera.position.set(px, py, 5 - rolagem * CFG.mergulhoRolagem);
    camera.lookAt(px, py, -10);

    grupo.rotation.z += dt * (CFG.giro + rolagem * CFG.giroRolagem);

    cursorNoMundo();
    uni.uCursor.value.copy(PT.mundo);
    uni.uActivity.value = PT.atividade;
    uni.uOpacity.value = trava((performance.now() - nasceu - 300) / 1400, 0, 1) * CFG.opacidade;

    passeFinal.uniforms.iTime.value = t;

    bloom.render();
    final.render();
    requestAnimationFrame(quadro);
  }

  function medir() {
    const w = innerWidth, h = innerHeight;
    const dpr = Math.min(devicePixelRatio, 1.5) * Q.nivel;

    renderer.setPixelRatio(dpr);
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();

    /* O bloom roda a METADE da resolução.
       O UnrealBloomPass faz cinco níveis de desfoque separável — são
       ~10 passagens de tela cheia, e é de longe a parte mais cara do
       quadro. Como o resultado é borrão por definição, metade da
       resolução é indistinguível a olho e custa um quarto do
       preenchimento. O passe final continua em resolução cheia, então
       as estrelas não perdem nitidez: só o halo delas é que é
       calculado menor. */
    bloom.setPixelRatio(dpr);
    bloom.setSize(Math.round(w / 2), Math.round(h / 2));

    final.setPixelRatio(dpr);
    final.setSize(w, h);
  }

  addEventListener('resize', medir, { passive: true });
  medir();
  requestAnimationFrame(quadro);

  document.body.classList.add('com-estrelas');
  return true;
}
