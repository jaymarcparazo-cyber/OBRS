$ErrorActionPreference = 'Stop'

$tokenConfig = 'C:\xampp\apache\conf\extra\obrs-face-verification.conf'
if (-not (Test-Path -LiteralPath $tokenConfig)) {
    throw "Apache token configuration not found: $tokenConfig"
}

$tokenLine = Get-Content -LiteralPath $tokenConfig |
    Where-Object { $_ -match '^\s*SetEnv\s+OBRS_FACE_SERVICE_TOKEN\s+"([^"]+)"\s*$' } |
    Select-Object -First 1
if (-not $tokenLine -or $tokenLine -notmatch '^\s*SetEnv\s+OBRS_FACE_SERVICE_TOKEN\s+"([^"]+)"\s*$') {
    throw 'Could not read OBRS_FACE_SERVICE_TOKEN from Apache configuration.'
}

$env:OBRS_FACE_SERVICE_TOKEN = $Matches[1]
$env:PYTHONUTF8 = '1'
$env:PYTHONIOENCODING = 'utf-8'
Push-Location $PSScriptRoot
try {
    & '.\.venv\Scripts\python.exe' '.\app.py'
    if ($LASTEXITCODE -ne 0) {
        throw "Face verification service exited with code $LASTEXITCODE."
    }
}
finally {
    Pop-Location
}
