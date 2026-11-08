$ErrorActionPreference = "Stop"

$nginxRoot = "C:\phpstudy_pro\Extensions\Nginx1.15.11"
$nginxExe = Join-Path $nginxRoot "nginx.exe"
$nginxConfig = "C:\phpstudy_pro\WWW\CampusTrace\deploy\nginx\campustrace-8080.conf"
$pidFile = "C:\phpstudy_pro\WWW\CampusTrace\storage\run\php-cgi.pid"

if (Get-NetTCPConnection -State Listen -LocalPort 8080 -ErrorAction SilentlyContinue) {
    & $nginxExe -s stop -p $nginxRoot -c $nginxConfig
}

if (Test-Path -LiteralPath $pidFile) {
    $phpPid = [int](Get-Content -LiteralPath $pidFile -Raw)
    Stop-Process -Id $phpPid -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $pidFile -Force
}

Write-Host "CampusTrace Nginx and PHP-CGI stopped. MySQL was left running."

