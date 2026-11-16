$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
New-Item -ItemType Directory -Force .local-test | Out-Null
$zipPath = Join-Path $projectRoot '.local-test/mariadb.zip'
if (!(Test-Path '.local-test/mariadb-11.4.5-winx64/bin/mariadbd.exe')) {
    Write-Output 'Downloading MariaDB portable (about 90 MB); no Windows service will be installed.'
    Invoke-WebRequest -Uri 'https://archive.mariadb.org/mariadb-11.4.5/winx64-packages/mariadb-11.4.5-winx64.zip' -OutFile $zipPath -UseBasicParsing
    Expand-Archive -LiteralPath $zipPath -DestinationPath .local-test -Force
}
Write-Output 'Ready. Run: powershell -ExecutionPolicy Bypass -File scripts/start-demo.ps1'
