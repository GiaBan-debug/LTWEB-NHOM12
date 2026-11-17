$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$projectRoot = Split-Path $PSScriptRoot -Parent
$packageDirectory = Join-Path $projectRoot ('.local-test/hosting-' + [Guid]::NewGuid().ToString('N'))
$outputDirectory = Join-Path $projectRoot 'dist'
New-Item -ItemType Directory -Force $packageDirectory, $outputDirectory | Out-Null
foreach ($relativePath in @('index.php', '.htaccess', '.env.example', 'README.md', 'backend', 'frontend', 'uploads', 'database')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $relativePath) -Destination $packageDirectory -Recurse -Force
}
# Only explicitly selected application paths are packaged; .env and test/runtime data are excluded.
$archivePath = Join-Path $outputDirectory 'nhom12-store-demo.zip'
if (Test-Path -LiteralPath $archivePath) { [IO.File]::Delete($archivePath) }
[IO.Compression.ZipFile]::CreateFromDirectory($packageDirectory, $archivePath)
Remove-Item -LiteralPath $packageDirectory -Recurse -Force
Write-Output "Hosting archive: $archivePath"
