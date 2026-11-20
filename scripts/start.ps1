param([string]$PhpPath = '', [int]$Port = 8000, [int]$DatabasePort = 0)
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
if (!(Test-Path (Join-Path $projectRoot 'vendor/autoload.php'))) { throw 'Run scripts/setup.ps1 to install PHP dependencies.' }

# Read the same connection configuration as PHP; process environment takes precedence.
$settings = @{}
$envFile = Join-Path $projectRoot '.env'
if (Test-Path -LiteralPath $envFile) {
    foreach ($line in [IO.File]::ReadAllLines($envFile)) {
        $line = $line.Trim()
        if (!$line -or $line.StartsWith('#') -or !$line.Contains('=')) { continue }
        $parts = $line -split '=', 2
        $settings[$parts[0].Trim()] = $parts[1].Trim().Trim('"', "'")
    }
}
function Get-AppSetting([string]$Name, [string]$Default) {
    $value = [Environment]::GetEnvironmentVariable($Name)
    if ($null -ne $value) { return $value }
    if ($settings.ContainsKey($Name)) { return $settings[$Name] }
    return $Default
}
if ($DatabasePort -gt 0) { $env:DB_PORT = "$DatabasePort" }
$dbHost = Get-AppSetting 'DB_HOST' '127.0.0.1'
$dbPort = [int](Get-AppSetting 'DB_PORT' '3306')
function Test-AppPort([string]$HostName, [int]$TargetPort) {
    $addresses = if ($HostName -eq 'localhost') { @('127.0.0.1', '::1') } else { @($HostName) }
    foreach ($address in $addresses) {
        $resolvedIp = $null
        if ([Net.IPAddress]::TryParse($address, [ref]$resolvedIp)) {
            $probe = New-Object Net.Sockets.TcpClient($resolvedIp.AddressFamily)
        } else {
            $probe = New-Object Net.Sockets.TcpClient
        }
        try {
            $pending = $probe.BeginConnect($address, $TargetPort, $null, $null)
            if ($pending.AsyncWaitHandle.WaitOne(1500)) { $probe.EndConnect($pending); return $true }
        } catch {}
        finally { $probe.Dispose() }
    }
    return $false
}
if (Test-AppPort 'localhost' $Port) {
    try {
        $health = Invoke-RestMethod -Uri "http://localhost:$Port/backend/api/health.php" -TimeoutSec 3
        if ($health.success -and $health.data.database -eq 'connected') {
            Write-Output "Website da chay: http://localhost:$Port/"
            exit 0
        }
    } catch {}
    throw "Cong $Port dang duoc su dung. Dung server cu hoac chon -Port khac."
}
$dbProcess = $null
$mariaDirectory = Join-Path $projectRoot '.local-test/mariadb-11.4.5-winx64'
try {
    if (!(Test-AppPort $dbHost $dbPort)) {
        if ($dbHost -notin @('127.0.0.1', 'localhost') -or $dbPort -ne 3307 -or !(Test-Path "$mariaDirectory/bin/mariadbd.exe")) {
            throw 'Database chua chay. Bat MySQL/XAMPP hoac kiem tra DB_HOST/DB_PORT trong .env.'
        }
        $dataDirectory = Join-Path $projectRoot '.local-test/mysql-data'
        if (!(Test-Path "$dataDirectory/my.ini")) {
            & "$mariaDirectory/bin/mariadb-install-db.exe" "--datadir=$dataDirectory" "--port=$dbPort" --silent
            if ($LASTEXITCODE -ne 0) { throw 'Khong khoi tao duoc MariaDB.' }
        }
        $dbProcess = Start-Process -FilePath "$mariaDirectory/bin/mariadbd.exe" -ArgumentList @('--no-defaults', "--basedir=`"$mariaDirectory`"", "--datadir=`"$dataDirectory`"", "--port=$dbPort", '--bind-address=127.0.0.1', '--console') -WindowStyle Hidden -PassThru -RedirectStandardOutput "$projectRoot/.local-test/mysql-out.log" -RedirectStandardError "$projectRoot/.local-test/mysql-error.log"
        $ready = $false
        for ($attempt = 0; $attempt -lt 30; $attempt++) {
            if ($dbProcess.HasExited) { throw 'MariaDB khong khoi dong duoc; xem .local-test/mysql-error.log.' }
            if (Test-AppPort $dbHost $dbPort) { $ready = $true; break }
            Start-Sleep -Milliseconds 500
        }
        if (!$ready) { throw 'MariaDB chua san sang.' }
    }
    & $PhpPath -d "extension_dir=$phpDirectory/ext" -d extension=pdo_mysql scripts/database.php --initialize
    if ($LASTEXITCODE -ne 0) { throw 'Khong cap nhat duoc database. Neu DB moi, chay scripts/database.php --initialize truoc.' }
$sessionDirectory = Join-Path $projectRoot '.local-test/sessions'
New-Item -ItemType Directory -Force $sessionDirectory | Out-Null
Write-Output "Website: http://localhost:$Port/"
Write-Output 'Giu terminal nay mo. Nhan Ctrl+C de dung.'
& $PhpPath -d "extension_dir=$phpDirectory/ext" -d extension=pdo_mysql -d extension=mbstring -d extension=openssl -d extension=curl -d extension=fileinfo -d "curl.cainfo=$projectRoot/backend/config/cacert.pem" -d "openssl.cafile=$projectRoot/backend/config/cacert.pem" -d "session.save_path=$sessionDirectory" -d display_errors=0 -d log_errors=1 -d error_log="$projectRoot/php-server-error.log" -S "localhost:$Port" -t $projectRoot router.php
if ($LASTEXITCODE -ne 0) { throw 'PHP server stopped with an error; inspect php-server-error.log.' }
} finally {
    # Shut down only a database process started by this invocation.
    if ($dbProcess -and !$dbProcess.HasExited) {
        & "$mariaDirectory/bin/mariadb-admin.exe" --no-defaults --skip-ssl --host=127.0.0.1 "--port=$dbPort" --user=root shutdown 2>$null | Out-Null
    }
}
