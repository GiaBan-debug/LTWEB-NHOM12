param([string]$PhpPath = 'C:/xampp/php/php.exe', [switch]$Portable)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
New-Item -ItemType Directory -Force .local-test | Out-Null
$composerPath = Join-Path $projectRoot '.local-test/composer.phar'
if (!(Test-Path $composerPath)) {
    Invoke-WebRequest -Uri https://getcomposer.org/composer-stable.phar -OutFile $composerPath -UseBasicParsing
}
$phpDirectory = Split-Path (Resolve-Path $PhpPath) -Parent
$env:COMPOSER_CACHE_DIR = Join-Path $projectRoot '.local-test/composer-cache'
& $PhpPath -d "extension_dir=$phpDirectory/ext" -d extension=zip -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -d extension=curl $composerPath install --no-dev --prefer-dist --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }

if ($Portable) {
$zipPath = Join-Path $projectRoot '.local-test/mariadb.zip'
if (!(Test-Path '.local-test/mariadb-11.4.5-winx64/bin/mariadbd.exe')) {
    Write-Output 'Downloading MariaDB portable (about 90 MB); no Windows service will be installed.'
    Invoke-WebRequest -Uri 'https://archive.mariadb.org/mariadb-11.4.5/winx64-packages/mariadb-11.4.5-winx64.zip' -OutFile $zipPath -UseBasicParsing
    Expand-Archive -LiteralPath $zipPath -DestinationPath .local-test -Force
}

}
Write-Output "Setup complete. Configure .env, then run run-demo.bat."
