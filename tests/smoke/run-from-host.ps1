param(
    [string]$BaseUrl = "http://192.168.111.148:8080"
)

$ErrorActionPreference = "Stop"
$failures = New-Object System.Collections.Generic.List[string]

function Assert-True([bool]$Condition, [string]$Name) {
    if ($Condition) {
        Write-Host "[PASS] $Name"
    } else {
        Write-Host "[FAIL] $Name"
        $script:failures.Add($Name)
    }
}

function Get-ErrorBody($ErrorRecord) {
    $response = $ErrorRecord.Exception.Response
    if (-not $response) {
        return ""
    }

    $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
    $body = $reader.ReadToEnd()
    $reader.Dispose()
    return $body
}

Write-Host "CampusTrace smoke tests: $BaseUrl"

$health = Invoke-WebRequest -Uri "$BaseUrl/health" -UseBasicParsing -TimeoutSec 10
Assert-True ($health.StatusCode -eq 200) "GET /health returns 200"
Assert-True (-not ($health.Content -match "status-removed")) "GET /health has no failed checks"
Assert-True ($health.Content -match "campustrace_baseline") "database connection is available"
Assert-True ($health.Content -match "utf8mb4") "database uses utf8mb4"
Assert-True ($health.Content -match 'class="back-link" href="/"') "health page renders back navigation"

$homePageSmoke = Invoke-WebRequest -Uri "$BaseUrl/" -UseBasicParsing -TimeoutSec 10
Assert-True ($homePageSmoke.StatusCode -eq 200) "GET / returns 200"
Assert-True ($homePageSmoke.Content -match "/posts/1") "seed lost-item post is visible"
Assert-True ($homePageSmoke.Content -match "/posts/3") "resolved post is visible"

$filtered = Invoke-WebRequest -Uri "$BaseUrl/?q=%E4%BC%9E&type=found&status=open" -UseBasicParsing -TimeoutSec 10
Assert-True ($filtered.Content -match "/posts/2") "keyword and type filter includes matching post"
Assert-True (-not ($filtered.Content -match "/posts/1")) "filter excludes non-matching post"

$detail = Invoke-WebRequest -Uri "$BaseUrl/posts/1" -UseBasicParsing -TimeoutSec 10
Assert-True ($detail.StatusCode -eq 200) "GET /posts/1 returns 200"
Assert-True ($detail.Content -match "/login") "guest post detail renders login action"
Assert-True ($detail.Content -match 'class="back-link" href="/"') "post detail renders back navigation"

try {
    Invoke-WebRequest -Uri "$BaseUrl/posts/999" -UseBasicParsing -TimeoutSec 10 | Out-Null
    Assert-True $false "missing post returns 404"
} catch {
    Assert-True ([int]$_.Exception.Response.StatusCode -eq 404) "missing post returns 404"
}

$guestCreateStatus = curl.exe -s -o NUL -w "%{http_code}" "$BaseUrl/posts/new"
Assert-True ($guestCreateStatus -eq "302") "guest post form redirects to login"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginPage = Invoke-WebRequest -Uri "$BaseUrl/login" -WebSession $session -UseBasicParsing -TimeoutSec 10
Assert-True ($loginPage.Content -match 'class="back-link" href="/"') "login page renders back navigation"
$csrf = [regex]::Match($loginPage.Content, 'name="_csrf" value="([^"]+)"').Groups[1].Value
$homeAfterLogin = Invoke-WebRequest `
    -Uri "$BaseUrl/login" `
    -Method Post `
    -WebSession $session `
    -Body @{
        _csrf = $csrf
        username = "alice"
        password = "CampusTrace!Demo2026"
    } `
    -UseBasicParsing `
    -TimeoutSec 10
Assert-True ($homeAfterLogin.Content -match "user-chip") "active user can log in"

$createPage = Invoke-WebRequest -Uri "$BaseUrl/posts/new" -WebSession $session -UseBasicParsing -TimeoutSec 10
Assert-True ($createPage.Content -match 'class="back-link" href="/"') "create page renders back navigation"

$editPage = Invoke-WebRequest -Uri "$BaseUrl/posts/1/edit" -WebSession $session -UseBasicParsing -TimeoutSec 10
Assert-True ($editPage.Content -match 'class="back-link" href="/posts/1"') "edit page returns to post detail"

$contactPage = Invoke-WebRequest -Uri "$BaseUrl/account/contact" -WebSession $session -UseBasicParsing -TimeoutSec 10
Assert-True ($contactPage.Content -match 'class="back-link" href="/"') "contact page renders back navigation"

$claimPage = Invoke-WebRequest -Uri "$BaseUrl/claims/1" -WebSession $session -UseBasicParsing -TimeoutSec 10
Assert-True ($claimPage.Content -match 'class="back-link" href="/posts/1/claims"') "owner claim detail returns to claim inbox"

$claimContactPage = Invoke-WebRequest -Uri "$BaseUrl/claims/1/contact" -WebSession $session -UseBasicParsing -TimeoutSec 10
Assert-True ($claimContactPage.Content -match 'class="back-link" href="/claims/1"') "claim contact returns to claim detail"

$reportsPage = Invoke-WebRequest -Uri "$BaseUrl/admin/reports" -WebSession $session -UseBasicParsing -TimeoutSec 10
Assert-True ($reportsPage.Content -match 'class="back-link" href="/"') "admin reports renders back navigation"

$logoutCsrf = [regex]::Match($homeAfterLogin.Content, 'name="_csrf" value="([^"]+)"').Groups[1].Value
$logoutPage = Invoke-WebRequest `
    -Uri "$BaseUrl/logout" `
    -Method Post `
    -WebSession $session `
    -Body @{ _csrf = $logoutCsrf } `
    -UseBasicParsing `
    -TimeoutSec 10
Assert-True ($logoutPage.BaseResponse.ResponseUri.AbsolutePath -eq "/login") "logged-in user can log out"

$inactiveSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$inactiveLoginPage = Invoke-WebRequest -Uri "$BaseUrl/login" -WebSession $inactiveSession -UseBasicParsing -TimeoutSec 10
$inactiveCsrf = [regex]::Match($inactiveLoginPage.Content, 'name="_csrf" value="([^"]+)"').Groups[1].Value
try {
    Invoke-WebRequest `
        -Uri "$BaseUrl/login" `
        -Method Post `
        -WebSession $inactiveSession `
        -Body @{
            _csrf = $inactiveCsrf
            username = "carol"
            password = "CampusTrace!Demo2026"
        } `
        -UseBasicParsing `
        -TimeoutSec 10 | Out-Null
    Assert-True $false "inactive user is rejected"
} catch {
    Assert-True ([int]$_.Exception.Response.StatusCode -eq 401) "inactive user is rejected"
}

if ($failures.Count -gt 0) {
    Write-Host ""
    Write-Host "Failures:"
    $failures | ForEach-Object { Write-Host "- $_" }
    exit 1
}

Write-Host ""
Write-Host "All smoke tests passed."
