param([string]$PhpPath = 'C:/xampp/php/php.exe')
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
