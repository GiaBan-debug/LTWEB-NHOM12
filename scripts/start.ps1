param([string]$PhpPath = '', [int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
if (!$PhpPath) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) { $PhpPath = $phpCommand.Source }
    elseif (Test-Path 'C:/xampp/php/php.exe') { $PhpPath = 'C:/xampp/php/php.exe' }
    else { throw 'Khong tim thay PHP. Truyen -PhpPath duong-dan/php.exe' }
}
$phpDirectory = Split-Path (Resolve-Path $PhpPath) -Parent
$sessionDirectory = Join-Path $projectRoot '.local-test/sessions'
New-Item -ItemType Directory -Force $sessionDirectory | Out-Null
Write-Output "Website: http://localhost:$Port/"
Write-Output 'Giu terminal nay mo. Nhan Ctrl+C de dung.'
& $PhpPath -d "extension_dir=$phpDirectory/ext" -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -d extension=curl -d "curl.cainfo=$projectRoot/backend/config/cacert.pem" -d "openssl.cafile=$projectRoot/backend/config/cacert.pem" -d "session.save_path=$sessionDirectory" -d display_errors=1 -d log_errors=1 -d error_log="$projectRoot/php-server-error.log" -S "localhost:$Port" -t $projectRoot router.php
