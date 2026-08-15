# Builds a clean release archive (printease-release.zip) for deployment.
#
# Uses 7-Zip for speed. Excludes local/dev-only content: .git, .vscode,
# .agents, node_modules, the deploy folder itself, uploads/*, any .env file,
# and the local DB dumps. Keeps .htaccess, .user.ini, service-worker.js,
# manifest.json, .env.example, and the schema.
#
# Prerequisite: 7-Zip installed (default path C:\Program Files\7-Zip\7z.exe).
#
# Usage (PowerShell 5.1+):
#   powershell -ExecutionPolicy Bypass -File deploy\zip-release.ps1
#   powershell -ExecutionPolicy Bypass -File deploy\zip-release.ps1 -OutputPath C:\out\printease-release.zip

param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')),
    [string]$OutputPath  = (Join-Path $PSScriptRoot 'printease-release.zip')
)

$ErrorActionPreference = 'Stop'

$root = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\')
$targetZip = [System.IO.Path]::GetFullPath($OutputPath)

$sevenZipCandidates = @(
    'C:\Program Files\7-Zip\7z.exe',
    'C:\Program Files (x86)\7-Zip\7z.exe'
)

$sevenZip = $sevenZipCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1

if (-not $sevenZip) {
    throw "7-Zip not found. Install it from https://www.7-zip.org/ or upload the project via FTP/File Manager instead."
}

if (-not (Test-Path -LiteralPath $root)) {
    throw "Project root not found: $root"
}

if (Test-Path -LiteralPath $targetZip) {
    Remove-Item -LiteralPath $targetZip -Force
}

$excludes = @(
    '-xr!node_modules',
    '-xr!.git',
    '-xr!.vscode',
    '-xr!.agents',
    '-xr!deploy',
    '-xr!uploads',
    '-xr!.env',
    '-xr!pe_db.sql',
    '-xr!pe_database.sql'
)

& $sevenZip a -tzip -mx=1 $targetZip (Join-Path $root '*') @excludes -r | Out-Null

if ($LASTEXITCODE -ne 0) {
    throw "7-Zip failed with exit code $LASTEXITCODE"
}

$sizeMb = '{0:N2}' -f ((Get-Item -LiteralPath $targetZip).Length / 1MB)
Write-Output "Created: $targetZip"
Write-Output "Size: $sizeMb MB"

Write-Output ''
Write-Output 'Remember: the local `vendor/` folder is gitignored and not in this zip.'
Write-Output 'Either run `composer install --no-dev --optimize-autoloader` on the server (SSH),'
Write-Output 'or upload `vendor/` together with this archive.'
