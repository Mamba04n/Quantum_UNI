param([switch]$SoloConfig)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (-not (Test-Path -LiteralPath '.quantum-uni')) { throw 'Ubicacion academica invalida.' }
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
$academicConfig = [IO.File]::ReadAllText((Join-Path $PSScriptRoot '.env'))
if ($academicConfig -notmatch '(?m)^DB_DATABASE=quantum_uni\s*$') { throw 'Este instalador requiere DB_DATABASE=quantum_uni.' }
if ($academicConfig -notmatch '(?m)^QUANTUM_UNI_ADMIN_PASSWORD=.+$') {
    $randomBytes = New-Object byte[] 24
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($randomBytes) } finally { $generator.Dispose() }
    $academicPassword = 'Uni!' + [Convert]::ToBase64String($randomBytes)
    $academicConfig = [regex]::Replace($academicConfig, '(?m)^QUANTUM_UNI_ADMIN_PASSWORD=.*$', ('QUANTUM_UNI_ADMIN_PASSWORD=' + $academicPassword))
    [IO.File]::WriteAllText((Join-Path $PSScriptRoot '.env'), $academicConfig, (New-Object Text.UTF8Encoding($false)))
}
if ($SoloConfig) { Write-Host 'Configuracion lista. Revisa DB_USERNAME y DB_PASSWORD en .env.'; exit 0 }
& composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { throw 'Fallo Composer.' }
if ($academicConfig -notmatch '(?m)^APP_KEY=.+$') {
    & php artisan key:generate
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo generar APP_KEY.' }
}
& php artisan quantum-uni:install
if ($LASTEXITCODE -ne 0) { throw 'Revisa MySQL y la configuracion academica.' }
Write-Host 'Instalacion lista. Usuario: admin@quantum-uni.test'
Write-Host 'La clave generada esta en QUANTUM_UNI_ADMIN_PASSWORD de tu .env privado.'
Write-Host 'Inicia con: php artisan serve --host=127.0.0.1 --port=8000'
