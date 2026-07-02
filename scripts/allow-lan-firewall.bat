@echo off
:: Right-click this file -> Run as administrator
echo Adding firewall rules for LAN testing (Private + Public networks)...
echo.

netsh advfirewall firewall delete rule name="DDEV Moodle HTTP 8080" >nul 2>&1
netsh advfirewall firewall add rule name="DDEV Moodle HTTP 8080" dir=in action=allow protocol=TCP localport=8080 profile=private,public,domain

netsh advfirewall firewall delete rule name="DDEV Moodle HTTPS 8443" >nul 2>&1
netsh advfirewall firewall add rule name="DDEV Moodle HTTPS 8443" dir=in action=allow protocol=TCP localport=8443 profile=private,public,domain

if exist "%ProgramFiles%\Docker\Docker\resources\com.docker.backend.exe" (
    netsh advfirewall firewall delete rule name="DDEV Docker LAN inbound" >nul 2>&1
    netsh advfirewall firewall add rule name="DDEV Docker LAN inbound" dir=in action=allow program="%ProgramFiles%\Docker\Docker\resources\com.docker.backend.exe" enable=yes profile=private,public,domain
)

netsh advfirewall firewall delete rule name="DDEV LAN subnet 8080" >nul 2>&1
netsh advfirewall firewall add rule name="DDEV LAN subnet 8080" dir=in action=allow protocol=TCP localport=8080 remoteip=localsubnet profile=private,public,domain

echo.
echo Done. Test from another PC:
echo   http://192.168.10.230:8080
echo.
echo Keep "ddev start" running on this PC.
pause
