<?php
/**
 * SPARK — bootstrap da aplicação
 * Carregado por toda página e por toda rota da API.
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// Ambiente
// ---------------------------------------------------------------
define('SPARK_ROOT', dirname(__DIR__));
define('SPARK_DEBUG', true); // em produção: false

if (SPARK_DEBUG) {
    ini_set('display_errors', '0');   // nunca vazar stack trace no JSON
    error_reporting(E_ALL);
}

/**
 * Fuso horário — precisa ser o MESMO do banco.
 *
 * O php.ini do XAMPP vem com Europe/Berlin. O MySQL grava com NOW(),
 * que usa o fuso do sistema (America/Sao_Paulo). Sem alinhar os dois,
 * o PHP lê "2026-08-12 15:00" do banco como se fosse hora de Berlim e
 * compara com um time() de Berlim: um post de 10 minutos atrás aparece
 * como "5 h", e o Direct mostra as mensagens na hora errada.
 */
date_default_timezone_set('America/Sao_Paulo');

// URL base ("/spark-app") descoberta sozinha, então o projeto funciona
// em qualquer pasta do htdocs sem precisar editar nada — inclusive se
// você renomear a pasta.
function base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;

    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appDir  = str_replace('\\', '/', SPARK_ROOT);
    $base    = $docRoot && str_starts_with($appDir, $docRoot)
        ? substr($appDir, strlen($docRoot))
        : '';

    return $base = rtrim($base, '/');
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/**
 * URL de um arquivo estático com a data de modificação embutida:
 *   assets/css/app.css?v=1754996293
 *
 * Sem isso o navegador continua servindo o CSS antigo do cache depois
 * de cada alteração — e a tela parece que "não mudou nada".
 */
function asset(string $path): string
{
    $path    = ltrim($path, '/');
    $arquivo = SPARK_ROOT . '/' . $path;
    $versao  = is_file($arquivo) ? filemtime($arquivo) : time();

    return url($path) . '?v=' . $versao;
}

/**
 * Data da última alteração do código, formatada.
 *
 * Existe por um motivo prático: é fácil acabar com várias cópias do
 * projeto dentro do htdocs (um ZIP extraído, uma pasta de backup) e
 * abrir a errada sem perceber — a tela fica idêntica à de ontem e
 * parece que nada mudou. Este carimbo, exibido nas Configurações,
 * diz na hora qual cópia está no ar.
 */
function versao_build(): string
{
    $maisRecente = 0;

    foreach ([
        'assets/css/app.css',
        'assets/js/app.js',
        'assets/js/vidro.js',
        'assets/js/audio.js',
        'index.php',
    ] as $arquivo) {
        $caminho = SPARK_ROOT . '/' . $arquivo;
        if (is_file($caminho)) {
            $maisRecente = max($maisRecente, filemtime($caminho));
        }
    }

    return $maisRecente ? date('d/m/Y H:i', $maisRecente) : 'desconhecida';
}

/** Caminho da pasta servida — mostra qual cópia respondeu. */
function pasta_do_app(): string
{
    return basename(SPARK_ROOT);
}

// ---------------------------------------------------------------
// Identidade visual
// ---------------------------------------------------------------

/**
 * O raio — símbolo do Spark.
 * Um "S" construído só com ângulos retos e diagonais de 45°, para
 * ler como raio e como letra ao mesmo tempo. Usa currentColor, então
 * herda a cor de quem o contém.
 */
function logo_marca(string $classe = 'marca__raio'): string
{
    return '<svg class="' . e($classe) . '" viewBox="0 0 100 100" aria-hidden="true" focusable="false">'
         . '<path d="M88 6 L36 6 L12 30 L48 30 L12 94 L88 42 L52 42 Z" fill="currentColor"/>'
         . '</svg>';
}

/**
 * Assinatura completa: raio + palavra. A inclinação vem do CSS
 * (skew), não de uma fonte itálica — assim o corte fica reto,
 * como no logotipo.
 */
function logo_assinatura(string $classe = ''): string
{
    return '<span class="assinatura ' . e($classe) . '">'
         . logo_marca('assinatura__raio')
         . '<span class="assinatura__palavra">PARK</span>'
         . '</span>';
}

/**
 * @font-face da Triumph — emitido só se os arquivos existirem.
 *
 * Triumph é fonte comercial: não dá para servir de CDN. A pilha em
 * --font-ui já a pede em primeiro lugar e cai na Archivo. Se
 * declarássemos o @font-face de qualquer jeito, o navegador tentaria
 * baixar nove arquivos inexistentes a cada página — nove 404 por
 * visita. Então só declaramos quando há o que declarar.
 *
 * Para ativar: largue em assets/fonts/ os arquivos
 *   triumph-regular / triumph-medium / triumph-bold
 * em .woff2, .woff ou .otf. A fonte assume o site inteiro sozinha.
 */
function fontes_locais(): string
{
    $pesos = [
        'regular' => '400',
        'medium'  => '500 600',
        'bold'    => '700 800',
    ];
    $formatos = ['woff2' => 'woff2', 'woff' => 'woff', 'otf' => 'opentype'];

    $blocos = [];

    foreach ($pesos as $nome => $peso) {
        $fontes = [];

        foreach ($formatos as $ext => $formato) {
            $rel = "assets/fonts/triumph-$nome.$ext";
            if (is_file(SPARK_ROOT . '/' . $rel)) {
                $fontes[] = "url('" . url($rel) . "') format('$formato')";
            }
        }

        if (!$fontes) continue;

        $blocos[] = "@font-face{font-family:'Triumph';src:" . implode(',', $fontes)
                  . ";font-weight:$peso;font-style:normal;font-display:swap}";
    }

    return $blocos ? "<style>\n" . implode("\n", $blocos) . "\n</style>\n" : '';
}

/** Favicon em SVG, mesma marca. */
function logo_favicon(): string
{
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>"
         . "<rect width='100' height='100' rx='22' fill='%23121214'/>"
         . "<path d='M88 6 L36 6 L12 30 L48 30 L12 94 L88 42 L52 42 Z' fill='%237f00ff'/>"
         . "</svg>";

    return 'data:image/svg+xml,' . str_replace(['<', '>', '"', ' '], ['%3C', '%3E', "'", '%20'], $svg);
}

// ---------------------------------------------------------------
// Sessão endurecida
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_url() ?: '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('SPARKSESS');
    session_start();
}

// ---------------------------------------------------------------
// Banco
// ---------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_NAME = 'spark';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('[spark][db] ' . $e->getMessage());
        http_response_code(500);
        exit('Não foi possível conectar ao banco de dados. Verifique se o MySQL está ligado no XAMPP.');
    }

    return $pdo;
}

// ---------------------------------------------------------------
// Helpers de escape / resposta
// ---------------------------------------------------------------
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(array $data = []): never
{
    json_out(['ok' => true] + $data);
}

function json_err(string $msg, int $status = 400): never
{
    json_out(['ok' => false, 'erro' => $msg], $status);
}

// ---------------------------------------------------------------
// Autenticação
// ---------------------------------------------------------------
function user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function logado(): bool
{
    return user_id() !== null;
}

/** Usuário logado completo (com cache por requisição). */
function usuario_atual(): ?array
{
    static $cache = null;
    if ($cache !== null) return $cache ?: null;

    $id = user_id();
    if (!$id) return null;

    $st = db()->prepare(
        'SELECT id_usuario, nome_usuario, nome_exibicao, email, bio, foto_perfil_url,
                area_criativa, ferramenta, estado_criativo, estado_em,
                cidade, pais, plano, idtipos_user, criado_em
           FROM usuario WHERE id_usuario = ? AND ativo = 1'
    );
    $st->execute([$id]);
    $u = $st->fetch();

    // Contas criadas antes da migração podem ter os campos novos nulos.
    if ($u) {
        $u['estado_criativo'] = $u['estado_criativo'] ?: 'observando';
        $u['area_criativa']   = $u['area_criativa']   ?: 'outro';
        $u['plano']           = $u['plano']           ?: 'free';
    }

    if (!$u) {                 // conta removida/desativada no meio da sessão
        session_destroy();
        return $cache = null;
    }

    return $cache = $u;
}

function exigir_login(): int
{
    $id = user_id();
    if (!$id) json_err('Você precisa estar logado.', 401);
    return $id;
}

function exigir_login_web(): void
{
    if (!logado()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    return !empty($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

/** Toda rota de escrita da API passa por aqui. */
function exigir_csrf(): void
{
    $token = $_POST['csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? null;

    if (!csrf_valido($token)) {
        json_err('Sessão expirada. Recarregue a página.', 419);
    }
}

function exigir_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_err('Método não permitido.', 405);
    }
}

// ---------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------

/** Avatar servível pelo navegador, tolerando os caminhos legados ("../profile_pics/x"). */
function avatar_url(?string $raw): ?string
{
    if (!$raw) return null;
    if (preg_match('#^https?://#i', $raw)) return $raw;
    return url(ltrim(str_replace('../', '', $raw), '/'));
}

function midia_url(?string $raw): ?string
{
    return avatar_url($raw);
}

function iniciais(string $nome): string
{
    return mb_strtoupper(mb_substr(trim($nome), 0, 1, 'UTF-8'), 'UTF-8') ?: '?';
}

/** "agora", "5 min", "3 h", "2 d", "14/03" */
function tempo_relativo(?string $data): string
{
    if (!$data) return '';
    $ts = strtotime($data);
    if (!$ts) return '';

    $d = time() - $ts;
    if ($d < 60)     return 'agora';
    if ($d < 3600)   return floor($d / 60) . ' min';
    if ($d < 86400)  return floor($d / 3600) . ' h';
    if ($d < 604800) return floor($d / 86400) . ' d';

    return date('d/m', $ts);
}

/**
 * Cria uma notificação. Nunca notifica a si mesmo e nunca derruba a
 * ação principal se falhar — notificação é efeito colateral, não o objetivo.
 */
function notificar(int $destino, int $ator, string $tipo, ?int $idmidia = null, ?string $extra = null): void
{
    if ($destino === $ator) return;

    try {
        db()->prepare(
            'INSERT INTO notificacoes (idusuario, idator, tipo, idmidias, extra)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$destino, $ator, $tipo, $idmidia, $extra]);
    } catch (Throwable $e) {
        error_log('[spark][notificar] ' . $e->getMessage());
    }
}

/**
 * Alimenta o perfil de gosto do usuário: registra a interação e soma
 * pontos nas tags do conteúdo. É isso que o feed lê para ordenar.
 */
function registrar_interacao(int $idusuario, int $idmidia, string $tipo, ?int $segundos = null): void
{
    static $pesos = [
        'visualizacao'      => 1,
        'curtida'           => 5,
        'comentario'        => 7,
        'compartilhamento'  => 9,
        'pulou'             => -3,
    ];

    try {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO interacoes (idusuario, idmidias, tipo_interacao, tempo_assistido_seg)
             VALUES (?, ?, ?, ?)'
        )->execute([$idusuario, $idmidia, $tipo, $segundos]);

        $peso = $pesos[$tipo] ?? 1;

        $pdo->prepare(
            'INSERT INTO afinidade_usuario_tag (idusuario, idtag, pontuacao)
             SELECT ?, mt.idtag, ?
               FROM midia_tags mt
              WHERE mt.idmidias = ?
             ON DUPLICATE KEY UPDATE pontuacao = GREATEST(0, pontuacao + VALUES(pontuacao))'
        )->execute([$idusuario, $peso, $idmidia]);
    } catch (Throwable $e) {
        error_log('[spark][interacao] ' . $e->getMessage());
    }
}

/** Extrai #hashtags do texto, cria as que faltam e vincula à mídia. */
function sincronizar_tags(int $idmidia, string $texto): array
{
    preg_match_all('/#([\p{L}\p{N}_]{2,30})/u', $texto, $m);

    $nomes = array_values(array_unique(array_map(
        static fn($t) => mb_strtolower($t, 'UTF-8'),
        $m[1] ?? []
    )));

    if (!$nomes) return [];

    $pdo      = db();
    $buscar   = $pdo->prepare('SELECT id FROM tags WHERE nome = ?');
    $criar    = $pdo->prepare('INSERT INTO tags (nome) VALUES (?)');
    $vincular = $pdo->prepare('INSERT IGNORE INTO midia_tags (idmidias, idtag) VALUES (?, ?)');

    foreach (array_slice($nomes, 0, 10) as $nome) {
        $buscar->execute([$nome]);
        $tag = $buscar->fetch();

        if ($tag) {
            $idtag = (int) $tag['id'];
        } else {
            $criar->execute([$nome]);
            $idtag = (int) $pdo->lastInsertId();
        }

        $vincular->execute([$idmidia, $idtag]);
    }

    return $nomes;
}
