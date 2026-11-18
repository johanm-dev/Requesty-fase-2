$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $root '.env.docker'
if (-not (Test-Path $envFile)) {
    throw "Falta $envFile. Copia .env.docker.example a .env.docker con secretos reales."
}

# Cargar variables de .env.docker
Get-Content $envFile | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]+)=(.*)$') {
        [Environment]::SetEnvironmentVariable($Matches[1].Trim(), $Matches[2].Trim(), 'Process')
    }
}

$port = if ($env:WEB_PORT) { $env:WEB_PORT } else { '8080' }
$baseUrl = "http://127.0.0.1:$port"

docker compose --env-file $envFile up -d --build

# 1. Regresion PostgreSQL 17 dentro del contenedor db
docker compose --env-file $envFile exec -T db sh -lc 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -v enc_key="$APP_ENCRYPTION_KEY" -c "SELECT set_config('\''restaurante.encryption_key'\'', :'\''enc_key'\'', false);" -f /opt/requesty-sql/qa_suite.sql'

# 2. Red-Team HTTP + Vistas Web contra Nginx + PHP-FPM
$env:TEST_BASE_URL = $baseUrl
php "$root\REQUESTY\PHP\tests\redteam_http_qa.php"

# 3. Estres y Concurrencia Real (16 paralelas sobre Nginx + PHP-FPM)
$env:CONCURRENCY_PORTS = (($port) * 1 -as [string])
$portsArr = @($port, $port, $port, $port, $port, $port, $port, $port) -join ','
$env:CONCURRENCY_PORTS = $portsArr
php "$root\REQUESTY\PHP\tests\concurrency_stress_qa.php"

Write-Output "DOCKER_STACK_CERTIFIED=PASS"
