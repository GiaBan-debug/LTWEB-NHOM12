param([Parameter(Mandatory=$true)][string]$Email, [ValidateSet('user','admin')][string]$Role = 'admin', [string]$DatabaseName = 'ltweb_nhom12_demo', [int]$DatabasePort = 3307, [string]$PhpPath = 'C:/xampp/php/php.exe')
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = "$DatabasePort"
$env:DB_NAME = $DatabaseName
$env:DB_USER = 'root'
$env:DB_PASSWORD = ''
$phpDirectory = Split-Path (Resolve-Path $PhpPath) -Parent
& $PhpPath -d "extension_dir=$phpDirectory/ext" -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -d extension=curl scripts/firebase-user.php "--email=$Email" "--role=$Role"
if ($LASTEXITCODE -ne 0) { throw 'Role update failed. User must sign in with Firebase first.' }
