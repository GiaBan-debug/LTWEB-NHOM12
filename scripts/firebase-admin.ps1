param([Parameter(Mandatory=$true)][string]$Email, [ValidateSet('user','admin')][string]$Role = 'admin', [string]$DatabaseName = '', [int]$DatabasePort = 0, [string]$PhpPath = 'C:/xampp/php/php.exe')
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
# PHP reads credentials from .env; override only explicitly supplied connection options.
if ($DatabasePort -gt 0) { $env:DB_PORT = "$DatabasePort" }
if ($DatabaseName) { $env:DB_NAME = $DatabaseName }
$phpDirectory = Split-Path (Resolve-Path $PhpPath) -Parent
& $PhpPath -d "extension_dir=$phpDirectory/ext" -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -d extension=curl scripts/firebase-user.php "--email=$Email" "--role=$Role"
if ($LASTEXITCODE -ne 0) { throw 'Role update failed. User must sign in with Firebase first.' }
