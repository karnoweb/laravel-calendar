param([string[]]$Filter)
Set-Location (Split-Path $PSScriptRoot -Parent)
foreach ($f in $Filter) {
    $o = Join-Path $env:TEMP ("run-" + ($f -replace '[^\w]', '_') + ".txt")
    $p = Start-Process php -ArgumentList 'vendor\bin\phpunit', '--filter', $f -RedirectStandardOutput $o -PassThru -NoNewWindow
    if (-not $p.WaitForExit(120000)) { $p.Kill(); "$f TIMEOUT"; continue }
    $l = Get-Content $o
    $r = ($l | Where-Object { $_ -match '^(OK|Tests:)' } | Select-Object -First 1)
    "$f => $r"
    for ($i = 0; $i -lt $l.Count; $i++) {
        if ($l[$i] -match '^\d+\) ') { '   ' + $l[$i]; '   ' + $l[$i + 1].Substring(0, [Math]::Min(220, $l[$i + 1].Length)) }
    }
}
