$ErrorActionPreference = "Stop"

$nginxRoot = "C:\phpstudy_pro\Extensions\Nginx1.15.11"
$nginxExe = Join-Path $nginxRoot "nginx.exe"
$nginxConfig = "C:\phpstudy_pro\WWW\CampusTrace\deploy\nginx\campustrace-8080.conf"
$phpDir = "C:\phpstudy_pro\Extensions\php\php7.3.4nts"
$phpCgi = Join-Path $phpDir "php-cgi.exe"
$phpIni = Join-Path $phpDir "php.ini"
$mysqlDir = "C:\phpstudy_pro\Extensions\MySQL5.7.26"
$mysqld = Join-Path $mysqlDir "bin\mysqld.exe"
$mysqlIni = Join-Path $mysqlDir "my.ini"
$runtimeDir = "C:\phpstudy_pro\WWW\CampusTrace\storage\run"

New-Item -ItemType Directory -Force -Path $runtimeDir | Out-Null

function Test-LocalPort([int]$Port) {
    return [bool](Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
}

if (-not (Test-LocalPort 3306)) {
    Start-Process -FilePath $mysqld `
        -ArgumentList "--defaults-file=`"$mysqlIni`"" `
        -WorkingDirectory (Join-Path $mysqlDir "bin") `
        -WindowStyle Hidden
    Start-Sleep -Seconds 2
}

if (-not (Test-LocalPort 9000)) {
    $phpProcess = Start-Process -FilePath $phpCgi `
        -ArgumentList "-b 127.0.0.1:9000 -c `"$phpIni`"" `
        -WorkingDirectory $phpDir `
        -WindowStyle Hidden `
        -PassThru
    Set-Content -LiteralPath (Join-Path $runtimeDir "php-cgi.pid") -Value $phpProcess.Id -Encoding ASCII
    Start-Sleep -Seconds 1
}

& $nginxExe -t -p $nginxRoot -c $nginxConfig
if ($LASTEXITCODE -ne 0) {
    throw "CampusTrace Nginx configuration test failed."
}

if (-not (Test-LocalPort 8080)) {
    Start-Process -FilePath $nginxExe `
        -ArgumentList @('-p', $nginxRoot, '-c', $nginxConfig) `
        -WorkingDirectory $nginxRoot `
        -WindowStyle Hidden
    Start-Sleep -Seconds 1
}

if (-not (Test-LocalPort 8080)) {
    throw "CampusTrace did not start on port 8080. See nginx logs."
}

Write-Host "CampusTrace started."
Write-Host "Local URL:    http://localhost:8080/"
Write-Host "Host URL:     http://192.168.111.148:8080/"
Write-Host "Health check: http://localhost:8080/health"

