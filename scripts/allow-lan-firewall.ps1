# Run as Administrator: right-click -> Run as administrator
# Allows other PCs on Wi-Fi to reach DDEV Moodle on port 8080.
# Important: rules must include PRIVATE profile (your Wi-Fi is usually Private).

$profile = 'private,public,domain'

$rules = @(
    @{ Name = 'DDEV Moodle HTTP 8080'; Port = 8080 },
    @{ Name = 'DDEV Moodle HTTPS 8443'; Port = 8443 }
)

foreach ($rule in $rules) {
    netsh advfirewall firewall delete rule name="$($rule.Name)" 2>$null | Out-Null
    netsh advfirewall firewall add rule name="$($rule.Name)" dir=in action=allow protocol=TCP localport=$($rule.Port) profile=$profile
    Write-Host "Added: $($rule.Name) (profiles: $profile)"
}

$dockerExe = "${env:ProgramFiles}\Docker\Docker\resources\com.docker.backend.exe"
if (Test-Path $dockerExe) {
    netsh advfirewall firewall delete rule name="DDEV Docker LAN inbound" 2>$null | Out-Null
    netsh advfirewall firewall add rule name="DDEV Docker LAN inbound" dir=in action=allow program="$dockerExe" enable=yes profile=$profile
    Write-Host "Added: DDEV Docker LAN inbound (Private + Public)"
}

Write-Host ""
Write-Host "Done. Share this URL with testers on the same Wi-Fi:"
$ip = (Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias '*Wi-Fi*' -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notlike '169.*' } | Select-Object -First 1).IPAddress
if (-not $ip) {
    $ip = 'YOUR_WIFI_IP'
}
Write-Host "  http://${ip}:8080"
Write-Host ""
Write-Host "On your PC, keep running: ddev start"
