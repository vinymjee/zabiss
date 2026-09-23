# Zabiss - Lancement local 1-click (2 terminaux auto)
# Usage: powershell -ExecutionPolicy Bypass -File ./start-local.ps1

$ErrorActionPreference = "Stop"
$root = $PSScriptRoot

Write-Host "=== Zabiss Local ===" -ForegroundColor Cyan

# 1. Backend PHP SQLite sur 8081 (8080 réservé par pro-academy)
Write-Host "[1/2] Backend http://127.0.0.1:8081/api/health (SQLite)" -ForegroundColor Green
$backendJob = Start-Job -Name zabiss-backend -ScriptBlock {
  Set-Location $using:root\backend
  $env:DB_DRIVER="sqlite"
  php -S 127.0.0.1:8081 router.php
}
Start-Sleep -Seconds 2
try { Invoke-RestMethod http://127.0.0.1:8081/api/health -TimeoutSec 5 | Out-Null; Write-Host "  -> Backend OK" -ForegroundColor Green } catch { Write-Host "  -> Backend erreur: $_" -ForegroundColor Red; Receive-Job $backendJob -Keep | Select-Object -Last 20 }

# 2. Frontend Angular sur 4200
Write-Host "[2/2] Frontend http://localhost:4200 (proxy -> 8081)" -ForegroundColor Green
Write-Host "  Lancement ng serve..." -ForegroundColor Yellow
# Lance en job séparé pour ne pas bloquer
$frontendJob = Start-Job -Name zabiss-frontend -ScriptBlock {
  Set-Location $using:root
  npx ng serve --host 127.0.0.1 --port 4200 --proxy-config proxy.conf.json
}
Write-Host ""
Write-Host "=== Pret ===" -ForegroundColor Cyan
Write-Host "Frontend: http://localhost:4200"
Write-Host "Backend : http://127.0.0.1:8081/api/health"
Write-Host "Logs backend : Receive-Job zabiss-backend -Keep"
Write-Host "Logs frontend: Receive-Job zabiss-frontend -Keep"
Write-Host "Arret: Stop-Job zabiss-backend,zabiss-frontend; Remove-Job zabiss-backend,zabiss-frontend"
Write-Host ""
Write-Host "Comptes demo: MAT-2025-001/koffi.aya/eleve123 | MAT-2025-002/koffi.moussa/eleve123 | MAT-2025-003/traore.fatou/eleve123"
