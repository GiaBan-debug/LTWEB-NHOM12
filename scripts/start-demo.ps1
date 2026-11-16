param([int]$Port = 8000, [int]$DatabasePort = 3307, [string]$PhpPath = 'C:/xampp/php/php.exe')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
$mariaDirectory = Join-Path $projectRoot '.local-test/mariadb-11.4.5-winx64'
if (!(Test-Path "$mariaDirectory/bin/mariadbd.exe")) { throw 'Chay scripts/setup-local.ps1 truoc de tai MariaDB portable.' }
if (!(Test-Path "$projectRoot/.local-test/mysql-data/my.ini")) {
    & "$mariaDirectory/bin/mariadb-install-db.exe" "--datadir=$projectRoot/.local-test/mysql-data" "--port=$DatabasePort" --silent
    if ($LASTEXITCODE -ne 0) { throw 'Khong khoi tao duoc MariaDB.' }
}
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = "$DatabasePort"
$env:DB_NAME = 'ltweb_nhom12_demo'
$env:DB_USER = 'root'
$env:DB_PASSWORD = ''
$dbProcess = $null
try {
    $dbProcess = Start-Process -FilePath "$mariaDirectory/bin/mariadbd.exe" -ArgumentList @('--no-defaults',"--basedir=`"$mariaDirectory`"","--datadir=`"$projectRoot/.local-test/mysql-data`"","--port=$DatabasePort",'--bind-address=127.0.0.1','--console') -WindowStyle Hidden -PassThru -RedirectStandardOutput "$projectRoot/.local-test/mysql-out.log" -RedirectStandardError "$projectRoot/.local-test/mysql-error.log"
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Milliseconds 500
        if ($dbProcess.HasExited) { throw 'MariaDB khong khoi dong duoc; xem .local-test/mysql-error.log. Kiem tra cong dang bi chiem.' }
        $connectionProbe = New-Object Net.Sockets.TcpClient
        try { $connectionProbe.Connect('127.0.0.1', $DatabasePort); $ready = $true }
        catch { $ready = $false }
        finally { $connectionProbe.Dispose() }
        if ($ready) { break }
    }
    if (!$ready) { throw 'MariaDB chua san sang.' }
    $phpDirectory = Split-Path (Resolve-Path $PhpPath) -Parent
    & $PhpPath -d "extension_dir=$phpDirectory/ext" -d extension=pdo_mysql -d extension=mbstring scripts/setup-demo.php
    if ($LASTEXITCODE -ne 0) { throw 'Khong chuan bi duoc database demo.' }
    & "$PSScriptRoot/start.ps1" -PhpPath $PhpPath -Port $Port
} finally {
    if ($dbProcess -and !$dbProcess.HasExited) {
        & "$mariaDirectory/bin/mariadb-admin.exe" --skip-ssl --host=127.0.0.1 "--port=$DatabasePort" --user=root shutdown 2>$null | Out-Null
    }
}
