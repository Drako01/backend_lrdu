<?php
#region Imports
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    error_log('[bootstrap] vendor/autoload.php no encontrado (modo sin dependencias)');
}
require_once __DIR__ . '/../routes/UserRouter.php';
require_once __DIR__ . '/../routes/MainRouter.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';

// ⛳ Asegurate de que el path apunte a donde esté tu clase Conexion
require_once __DIR__ . '/../config/Conexion.php';
#endregion

class Server
{
    private array $routers = [];

    public function __construct()
    {
        $this->initializeAutoloader();
        $this->handleCORS();
        $this->initializeRouters();
        date_default_timezone_set('America/Argentina/Buenos_Aires');
    }

    private function initializeAutoloader()
    {
        require_once __DIR__ . '/../helpers/autoloader.helper.php';
        handleAutoloader();
    }

    private function handleCORS()
    {
        require_once __DIR__ . '/../helpers/cors.helper.php';
        handleCORS();
    }

    private function initializeRouters()
    {
        $this->routers = [
            '/api'  => new UserRouter(),
            '/auth' => new MainRouter(),
        ];
    }

    public function handleRequest()
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
            $path   = parse_url($rawUri, PHP_URL_PATH) ?: '/';
            $params = json_decode(file_get_contents('php://input'), true) ?? [];

            // ✅ Root: '/', sin colgarse de $this->routers
            if ($path === '/' || $path === '') {
                $this->handleRoot($method);
                return;
            }

            // ✅ Enrutado existente
            foreach ($this->routers as $basePath => $router) {
                // Coincidencia exacta del basePath o con “/” siguiente (evita que '/' capture todo)
                if ($path === $basePath || strpos($path, $basePath . '/') === 0) {
                    $router->handleRequest($method, $path, $params);
                    return;
                }
            }

            ResponseHelper::serverError(['status' => 'Error', 'message' => 'Ruta no encontrada.'], 404);
        } catch (\Exception $e) {
            ResponseHelper::serverError(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET / -> Health básico con estado de DB.
     * Responde HTML si el cliente acepta text/html, si no JSON.
     */
    private function handleRoot(string $method): void
    {
        if ($method === 'OPTIONS') {
            http_response_code(204);
            return;
        }
        if ($method === 'HEAD') {
            // Solo headers; mismo status que GET
            $conn = Conexion::getInstance();
            $dbOnline = $conn->ping();
            http_response_code($dbOnline ? 200 : 503);
            header('Content-Type: application/json; charset=utf-8');
            return;
        }
        if ($method !== 'GET') {
            ResponseHelper::serverError(['status' => 'Error', 'message' => 'Método no permitido.'], 405);
            return;
        }

        // ⚠️ Nota: si la conexión falla, tu Conexion::__construct() ya envía 500 y exit.
        $dbOnline = false;
        $dbType = 'unknown';
        $dbDesc = 'unknown';

        try {
            $conn = Conexion::getInstance();
            $dbOnline = $conn->ping();
            $dbType = Conexion::getDbTypeStatic();
            $dbDesc = Conexion::getDescriptionDbType();
        } catch (\Throwable $t) {
            // Si llegamos acá, damos 503 pero seguimos respondiendo (no exit)
            $dbOnline = false;
        }

        $payload = [
            'service'   => 'Los Reyes del Usado - Backend',
            'status'    => $dbOnline ? 'ok' : 'degraded',
            'time'      => date('c'),
            'client_ip' => $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? null,
            'db' => [
                'online'      => $dbOnline,
                'type'        => $dbType,
                'description' => $dbDesc,
            ],
        ];

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $wantsHtml = stripos($accept, 'text/html') !== false;

        if ($dbOnline) {
            http_response_code(200);
        } else {
            // Si ping falló pero no explotó el constructor
            http_response_code(503);
        }

        if ($wantsHtml) {
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderRootHtml($payload);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    private function renderRootHtml(array $p): string
    {
        // Calculados fuera del heredoc
        $badgeColor   = $p['db']['online'] ? '#16a34a' : '#dc2626';
        $badgeText    = $p['db']['online'] ? 'ONLINE' : 'OFFLINE';
        $subtitle     = $p['db']['online'] ? 'Base de datos operativa' : 'Base de datos no disponible';
        $dbOnlineText = $p['db']['online'] ? 'Sí' : 'No';

        // (Opcional pero sano) escapamos por si algo viene raro
        $service = htmlspecialchars($p['service'] ?? 'Backend', ENT_QUOTES, 'UTF-8');
        $status  = htmlspecialchars($p['status'] ?? '-', ENT_QUOTES, 'UTF-8');
        $time    = htmlspecialchars($p['time'] ?? '', ENT_QUOTES, 'UTF-8');
        $client  = htmlspecialchars((string)($p['client_ip'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dbType  = htmlspecialchars($p['db']['type'] ?? '-', ENT_QUOTES, 'UTF-8');
        $dbDesc  = htmlspecialchars($p['db']['description'] ?? '-', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>{$service} • Health</title>
<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;margin:0;padding:40px;background:#0b1220;color:#e5e7eb}
    .card{max-width:720px;margin:10rem auto;background:#0f172a;border:1px solid #1f2937;border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
    h1{margin:0 0 8px 0;font-size:22px}
    .muted{color:#9ca3af;font-size:14px;margin:0 0 16px 0}
    .row{display:flex;gap:16px;flex-wrap:wrap}
    .kv{flex:1 1 220px;background:#111827;border:1px solid #1f2937;border-radius:12px;padding:12px}
    .k{color:#9ca3af;font-size:12px}
    .v{font-size:14px}
    .badge{display:inline-block;padding:6px 10px;border-radius:999px;background:{$badgeColor}22;border:1px solid {$badgeColor};color:#fff;font-weight:600;font-size:12px}
    .footer{margin-top:18px;color:#6b7280;font-size:12px}
    code{background:#111827;border:1px solid #1f2937;border-radius:6px;padding:2px 6px}
</style>
</head>
    <body>
        <div class="card">
            <h1>{$service}</h1>
            <p class="muted">Health check del backend • <span class="badge">{$badgeText}</span> — {$subtitle}</p>

            <div class="row">
            <div class="kv"><div class="k">Estado</div><div class="v">{$status}</div></div>
            <div class="kv"><div class="k">Hora</div><div class="v"><code>{$time}</code></div></div>
            <div class="kv"><div class="k">Cliente IP</div><div class="v"><code>{$client}</code></div></div>
            </div>

            <div class="row" style="margin-top:12px">
            <div class="kv"><div class="k">DB Online</div><div class="v">{$dbOnlineText}</div></div>
            <div class="kv"><div class="k">DB Tipo</div><div class="v">{$dbType}</div></div>
            <div class="kv"><div class="k">DB Descripción</div><div class="v">{$dbDesc}</div></div>
            </div>

            <div class="footer">
            Si preferís JSON, enviá <code>Accept: application/json</code>.
            <p>Backend</p>
            </div>
        </div>
    </body>
</html>
HTML;
    }
}
