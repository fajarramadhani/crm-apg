$ErrorActionPreference = 'Stop'

$backend = $PSScriptRoot
$runtimeRoot = 'C:\Users\USER\AppData\Local\Temp\opencode'
$database = Join-Path $runtimeRoot 'apg-crm-phase15-runtime.sqlite'
$stdout = Join-Path $runtimeRoot 'apg-crm-phase15-server.out.log'
$stderr = Join-Path $runtimeRoot 'apg-crm-phase15-server.err.log'
$baseUrl = 'http://127.0.0.1:8015'
$origin = 'http://localhost:5173'
$server = $null

function Invoke-Api {
    param(
        [string] $Method,
        [string] $Path,
        [Microsoft.PowerShell.Commands.WebRequestSession] $Session,
        [hashtable] $Body
    )

    $headers = @{ Accept = 'application/json'; Origin = $origin }
    if ($Session) {
        $xsrf = $Session.Cookies.GetCookies($baseUrl)['XSRF-TOKEN']
        if ($xsrf) {
            $headers['X-XSRF-TOKEN'] = [uri]::UnescapeDataString($xsrf.Value)
        }
    }

    $arguments = @{
        Uri = "$baseUrl$Path"
        Method = $Method
        Headers = $headers
        SkipHttpErrorCheck = $true
    }
    if ($Session) {
        $arguments.WebSession = $Session
    }
    if ($Body) {
        $arguments.ContentType = 'application/json'
        $arguments.Body = $Body | ConvertTo-Json -Depth 10
    }

    $response = Invoke-WebRequest @arguments
    $json = if ($response.Content) { $response.Content | ConvertFrom-Json } else { $null }

    return [pscustomobject]@{
        Status = [int] $response.StatusCode
        Body = $json
        RequestId = [string] $response.Headers['X-Request-ID']
    }
}

function Assert-Status {
    param([string] $Name, $Response, [int] $Expected)

    if ($Response.Status -ne $Expected) {
        throw "$Name expected HTTP $Expected, received $($Response.Status): $($Response.Body | ConvertTo-Json -Depth 10 -Compress)"
    }
    if ([string]::IsNullOrWhiteSpace($Response.RequestId)) {
        throw "$Name did not return X-Request-ID"
    }

    [pscustomobject]@{ Check = $Name; Status = $Response.Status; RequestId = $Response.RequestId }
}

function New-AuthenticatedSession {
    param([string] $Email)

    $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    Invoke-WebRequest -Uri "$baseUrl/sanctum/csrf-cookie" -Headers @{ Origin = $origin; Accept = 'application/json' } -WebSession $session | Out-Null
    $login = Invoke-Api -Method POST -Path '/api/v1/auth/login' -Session $session -Body @{ email = $Email; password = 'password' }
    Assert-Status -Name "Login $Email" -Response $login -Expected 200 | Out-Null

    return $session
}

try {
    if (-not (Test-Path -LiteralPath $runtimeRoot -PathType Container)) {
        throw "Runtime parent does not exist: $runtimeRoot"
    }

    Remove-Item -LiteralPath $database, $stdout, $stderr -Force -ErrorAction SilentlyContinue
    New-Item -ItemType File -Path $database | Out-Null

    $env:APP_ENV = 'local'
    $env:APP_URL = $baseUrl
    $env:FRONTEND_URL = $origin
    $env:SANCTUM_STATEFUL_DOMAINS = 'localhost:5173,127.0.0.1:8015'
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = $database
    $env:SESSION_DRIVER = 'file'
    $env:CACHE_STORE = 'array'
    $env:QUEUE_CONNECTION = 'sync'

    & php (Join-Path $backend 'artisan') migrate:fresh --seed --force --no-interaction | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'Runtime database migration or seeding failed.'
    }

    $server = Start-Process -FilePath php -ArgumentList @((Join-Path $backend 'artisan'), 'serve', '--host=127.0.0.1', '--port=8015', '--no-reload') -WorkingDirectory $backend -RedirectStandardOutput $stdout -RedirectStandardError $stderr -PassThru

    $ready = $false
    foreach ($attempt in 1..40) {
        Start-Sleep -Milliseconds 250
        try {
            $health = Invoke-WebRequest -Uri "$baseUrl/api/v1/health" -Headers @{ Accept = 'application/json' } -SkipHttpErrorCheck
            if ($health.StatusCode -eq 200) {
                $ready = $true
                break
            }
        } catch {}
    }
    if (-not $ready) {
        throw 'Laravel runtime server did not become ready.'
    }

    $results = @()
    $results += Assert-Status -Name 'Unauthenticated article list' -Response (Invoke-Api -Method GET -Path '/api/v1/knowledge-base') -Expected 401

    $requester = New-AuthenticatedSession 'requester@tichub.local'
    $pic = New-AuthenticatedSession 'pic@tichub.local'
    $itLead = New-AuthenticatedSession 'itlead@tichub.local'
    $executive = New-AuthenticatedSession 'executive@tichub.local'
    $admin = New-AuthenticatedSession 'admin@tichub.local'

    $payload = @{
        title = 'Phase 15 Runtime VPN Guide'
        summary = 'Runtime-verified instructions for secure remote access.'
        content = '<p>Install the approved VPN client.</p><script>alert(1)</script>'
        visibility = 'all_authenticated'
    }

    $results += Assert-Status -Name 'Requester create denied' -Response (Invoke-Api -Method POST -Path '/api/v1/knowledge-base' -Session $requester -Body $payload) -Expected 403
    $created = Invoke-Api -Method POST -Path '/api/v1/knowledge-base' -Session $pic -Body $payload
    $results += Assert-Status -Name 'PIC creates draft' -Response $created -Expected 201
    $articleId = [int] $created.Body.data.id
    $articleSlug = [string] $created.Body.data.slug
    if ($created.Body.data.content -match '<script') {
        throw 'Stored article content retained a script tag.'
    }

    $results += Assert-Status -Name 'Requester cannot read draft' -Response (Invoke-Api -Method GET -Path "/api/v1/knowledge-base/$articleId" -Session $requester) -Expected 404
    $results += Assert-Status -Name 'PIC submits review' -Response (Invoke-Api -Method POST -Path "/api/v1/knowledge-base/$articleId/submit-review" -Session $pic) -Expected 200
    $results += Assert-Status -Name 'Duplicate submit conflicts' -Response (Invoke-Api -Method POST -Path "/api/v1/knowledge-base/$articleId/submit-review" -Session $pic) -Expected 409
    $results += Assert-Status -Name 'IT Lead publishes' -Response (Invoke-Api -Method POST -Path "/api/v1/knowledge-base/$articleId/publish" -Session $itLead -Body @{ change_summary = 'Runtime approval.' }) -Expected 200
    $results += Assert-Status -Name 'Requester lists published article' -Response (Invoke-Api -Method GET -Path '/api/v1/knowledge-base?search=Runtime%20VPN' -Session $requester) -Expected 200
    $results += Assert-Status -Name 'Requester reads published slug' -Response (Invoke-Api -Method GET -Path "/api/v1/knowledge-base/$articleSlug" -Session $requester) -Expected 200
    $results += Assert-Status -Name 'Requester submits feedback' -Response (Invoke-Api -Method POST -Path "/api/v1/knowledge-base/$articleId/feedback" -Session $requester -Body @{ helpful = $true; comment = 'Useful runtime guide.' }) -Expected 200
    $results += Assert-Status -Name 'Executive feedback denied' -Response (Invoke-Api -Method POST -Path "/api/v1/knowledge-base/$articleId/feedback" -Session $executive -Body @{ helpful = $true }) -Expected 403
    $results += Assert-Status -Name 'Admin reads activity' -Response (Invoke-Api -Method GET -Path "/api/v1/knowledge-base/$articleId/activity" -Session $admin) -Expected 200

    $results | ConvertTo-Json -Depth 5
} finally {
    if ($server -and -not $server.HasExited) {
        Stop-Process -Id $server.Id -Force
        $server.WaitForExit()
    }
    Remove-Item -LiteralPath $database -Force -ErrorAction SilentlyContinue
}
