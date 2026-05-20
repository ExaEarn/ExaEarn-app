$iniPath = "C:\Program Files\php-8.2\php.ini"

try {
    $content = Get-Content $iniPath -Raw
    $content = $content -replace '(?m)^;extension=pdo_pgsql', 'extension=pdo_pgsql'
    $content = $content -replace '(?m)^;extension=pgsql\r?\n', "extension=pgsql`r`n"
    $content = $content -replace '(?m)^;extension=pdo_sqlite', 'extension=pdo_sqlite'
    $content = $content -replace '(?m)^;extension=sqlite3\r?\n', "extension=sqlite3`r`n"

    Set-Content -Path $iniPath -Value $content -NoNewline -ErrorAction Stop
    Write-Host "Done - pdo_pgsql, pgsql, pdo_sqlite and sqlite3 extensions enabled"
}
catch {
    Write-Error "Failed to update $iniPath. Re-run PowerShell as Administrator."
    exit 1
}
