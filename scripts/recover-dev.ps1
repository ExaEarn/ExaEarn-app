$ErrorActionPreference = "Stop"

Set-Location (Resolve-Path "$PSScriptRoot\..")

Write-Host "Repairing workspace dependencies..."
pnpm install --prefer-offline
if ($LASTEXITCODE -ne 0) { throw "pnpm install failed." }

Write-Host "Checking source files..."
pnpm run check:source-corruption
if ($LASTEXITCODE -ne 0) { throw "Source corruption markers were found." }

$ports = @(5173, 5174, 5176, 4173, 4176)
foreach ($port in $ports) {
    $listeners = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue
    foreach ($listener in $listeners) {
        $processId = $listener.OwningProcess
        if ($processId -gt 0) {
            Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
        }
    }
}

Write-Host "Development dependency and source recovery completed."
