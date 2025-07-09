#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Cross-Platform GhostCrew Installer and Client Manager
    
.DESCRIPTION
    Universal installer script that works on Windows and Linux using PowerShell Core.
    Automatically detects the OS, installs dependencies, and manages the GhostCrew client.
    
.PARAMETER ApiUrl
    The API URL for the GhostCrew server
    
.PARAMETER Token
    Instance token for authentication
    
.PARAMETER HostId
    Custom host identifier (auto-generated if not provided)
    
.PARAMETER Daemon
    Run the client in background/daemon mode
    
.PARAMETER InstallDeps
    Install system dependencies
    
.PARAMETER Verbose
    Enable verbose output
    
.PARAMETER Kill
    Stop running daemon
    
.PARAMETER Status
    Show daemon status
    
.EXAMPLE
    ./installer.ps1 -ApiUrl "http://server/api.php" -Token "token123"
    
.EXAMPLE
    ./installer.ps1 -ApiUrl "http://server/api.php" -Token "token123" -Daemon -InstallDeps
#>

param(
    [string]$ApiUrl = $env:GHOSTCREW_API_URL,
    [string]$Token = $env:GHOSTCREW_TOKEN,
    [string]$HostId = $env:GHOSTCREW_HOST_ID,
    [switch]$Daemon,
    [switch]$InstallDeps,
    [switch]$Verbose,
    [switch]$Kill,
    [switch]$Status,
    [switch]$Help
)

# Global variables
$script:IsWindowsOS = $IsWindows -or ($PSVersionTable.PSVersion.Major -lt 6) -and ([System.Environment]::OSVersion.Platform -eq 'Win32NT')
$script:IsLinuxOS = $IsLinux -or (!$script:IsWindowsOSOS -and (Get-Command 'uname' -ErrorAction SilentlyContinue) -and ((uname) -eq 'Linux'))
$script:IsMacOSX = $IsMacOS

# Set platform-specific paths
if ($script:IsWindowsOS) {
    $script:TempDir = Join-Path $env:TEMP "ghostcrew"
    $script:LogFile = Join-Path $script:TempDir "ghostcrew_client.log"
    $script:PidFile = Join-Path $script:TempDir "ghostcrew_client.pid"
    $script:ConfigDir = Join-Path $env:LOCALAPPDATA "GhostCrew"
    $script:PythonCmd = @("python", "python3", "py")
    $script:PipCmd = @("pip", "pip3")
} else {
    $script:TempDir = "/tmp/ghostcrew"
    $script:LogFile = "/tmp/ghostcrew_client.log"
    $script:PidFile = "/tmp/ghostcrew_client.pid"
    $script:ConfigDir = Join-Path $env:HOME ".ghostcrew"
    $script:PythonCmd = @("python3", "python")
    $script:PipCmd = @("pip3", "pip")
}

$script:ClientScript = "GhostCrew_Agent.py"

# Color support
$script:Colors = @{
    Red = if ($script:IsWindowsOS -and !$env:ANSICON) { "" } else { "`e[31m" }
    Green = if ($script:IsWindowsOS -and !$env:ANSICON) { "" } else { "`e[32m" }
    Yellow = if ($script:IsWindowsOS -and !$env:ANSICON) { "" } else { "`e[33m" }
    Blue = if ($script:IsWindowsOS -and !$env:ANSICON) { "" } else { "`e[34m" }
    Reset = if ($script:IsWindowsOS -and !$env:ANSICON) { "" } else { "`e[0m" }
}

# Check for and remove curl alias to prevent issues
if (Get-Command "curl" -ErrorAction SilentlyContinue) {
    $alias = Get-Alias "curl" -ErrorAction SilentlyContinue
    if ($alias) {
        Remove-Item -Path "Alias:curl" -Force -ErrorAction SilentlyContinue
    }
}

function Write-Log {
    param(
        [ValidateSet("INFO", "WARN", "ERROR", "DEBUG")]
        [string]$Level,
        [string]$Message
    )
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    
    # Write to log file
    try {
        if (!(Test-Path $script:TempDir)) {
            New-Item -ItemType Directory -Path $script:TempDir -Force | Out-Null
        }
        Add-Content -Path $script:LogFile -Value $logEntry -ErrorAction SilentlyContinue
    } catch {
        # Ignore log file errors
    }
    
    # Console output with colors
    if ($Verbose -or $Level -ne "DEBUG") {
        $color = switch ($Level) {
            "ERROR" { $script:Colors.Red }
            "WARN"  { $script:Colors.Yellow }
            "INFO"  { $script:Colors.Green }
            "DEBUG" { $script:Colors.Blue }
            default { "" }
        }
        
        Write-Host "$color[$Level]$($script:Colors.Reset) $Message"
    }
}

function Show-Usage {
    Write-Host @"
Cross-Platform GhostCrew Agent Installer

USAGE:
    ./GhostCrew.ps1 [OPTIONS]

OPTIONS:
    -ApiUrl <url>       API URL (required)
    -Token <token>      Instance token (required)
    -HostId <id>        Host identifier (auto-generated if not provided)
    -Daemon             Run in daemon/background mode
    -InstallDeps        Install required dependencies
    -Verbose            Enable verbose logging
    -Kill               Kill running daemon
    -Status             Show daemon status
    -Help               Show this help message

EXAMPLES:
    # Connect with auto-generated host ID
    ./GhostCrew.ps1 -ApiUrl "http://server/api.php" -Token "inst_token_123"
    
    # Connect with custom host ID in daemon mode
    ./GhostCrew.ps1 -ApiUrl "http://server/api.php" -Token "inst_token_123" -HostId "my-server" -Daemon
    
    # Install dependencies and connect
    ./GhostCrew.ps1 -InstallDeps -ApiUrl "http://server/api.php" -Token "inst_token_123"
    
    # Check daemon status
    ./GhostCrew.ps1 -Status

ENVIRONMENT VARIABLES:
    GHOSTCREW_API_URL      Default API URL
    GHOSTCREW_TOKEN        Default instance token
    GHOSTCREW_HOST_ID      Default host identifier

FILES:
    $script:LogFile              Client log file
    $script:PidFile              Daemon PID file
    $script:ClientScript                Python client script
"@
}

function Test-Administrator {
    if ($script:IsWindowsOS) {
        $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
        $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
        return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    } else {
        return (id -u) -eq 0
    }
}

function Find-Command {
    param([string[]]$Commands)
    
    foreach ($cmd in $Commands) {
        if (Get-Command $cmd -ErrorAction SilentlyContinue) {
            return $cmd
        }
    }
    return $null
}

function Test-PythonVersion {
    param([string]$PythonCmd)
    
    try {
        $version = & $PythonCmd --version 2>&1
        if ($version -match "Python (\d+)\.(\d+)") {
            $major = [int]$matches[1]
            $minor = [int]$matches[2]
            
            if ($major -eq 3 -and $minor -ge 6) {
                Write-Log "INFO" "Found compatible Python: $version"
                return $true
            } else {
                Write-Log "WARN" "Python version $version is not supported (requires 3.6+)"
                return $false
            }
        }
    } catch {
        Write-Log "DEBUG" "Failed to check Python version for $PythonCmd"
    }
    
    return $false
}

function Install-LinuxDependencies {
    Write-Log "INFO" "Installing dependencies on Linux..."
    
    $packageManagers = @(
        @{ Check = "apt-get"; Update = "sudo apt-get update"; Install = "sudo apt-get install -y"; Packages = @("python3", "python3-pip", "curl", "openssl") },
        @{ Check = "yum"; Update = "sudo yum update -y"; Install = "sudo yum install -y"; Packages = @("python3", "python3-pip", "curl", "openssl") },
        @{ Check = "dnf"; Update = "sudo dnf update -y"; Install = "sudo dnf install -y"; Packages = @("python3", "python3-pip", "curl", "openssl") },
        @{ Check = "pacman"; Update = "sudo pacman -Sy"; Install = "sudo pacman -S --noconfirm"; Packages = @("python", "python-pip", "curl", "openssl") }
    )
    
    foreach ($pm in $packageManagers) {
        if (Get-Command $pm.Check -ErrorAction SilentlyContinue) {
            Write-Log "INFO" "Found package manager: $($pm.Check)"
            
            # Update package list
            try {
                Write-Log "INFO" "Updating package list..."
                Invoke-Expression $pm.Update
            } catch {
                Write-Log "WARN" "Failed to update package list: $($_.Exception.Message)"
            }
            
            # Install packages
            foreach ($package in $pm.Packages) {
                try {
                    Write-Log "INFO" "Installing $package..."
                    Invoke-Expression "$($pm.Install) $package"
                } catch {
                    Write-Log "WARN" "Failed to install $package : $($_.Exception.Message)"
                }
            }
            
            return $true
        }
    }
    
    Write-Log "ERROR" "No supported package manager found (apt-get, yum, dnf, pacman)"
    return $false
}

function Install-WindowsDependencies {
    Write-Log "INFO" "Checking Windows dependencies..."
    
    # Check if Python is available from Microsoft Store or installer
    if (!(Find-Command $script:PythonCmd)) {
        Write-Log "WARN" "Python not found. Please install Python 3.6+ from:"
        Write-Log "WARN" "  - Microsoft Store (recommended)"
        Write-Log "WARN" "  - https://python.org/downloads"
        return $false
    }
    
    # Check if curl is available (Windows 10 1803+ has curl built-in)
    if (!(Get-Command "curl" -ErrorAction SilentlyContinue)) {
        Write-Log "WARN" "curl not found. Using PowerShell alternatives for downloads."
    }
    
    return $true
}

function Install-PythonPackages {
    param([string]$PipCmd, [string[]]$Packages)
    
    Write-Log "INFO" "Installing Python packages: $($Packages -join ', ')"
    
    foreach ($package in $Packages) {
        try {
            Write-Log "INFO" "Installing $package..."
            & $PipCmd install $package --user
            if ($LASTEXITCODE -ne 0) {
                Write-Log "ERROR" "Failed to install $package"
                return $false
            }
        } catch {
            Write-Log "ERROR" "Error installing $package : $($_.Exception.Message)"
            return $false
        }
    }
    
    return $true
}

function Test-PythonPackage {
    param([string]$PythonCmd, [string]$Package)
    
    try {
        & $PythonCmd -c "import $Package" 2>$null
        return $LASTEXITCODE -eq 0
    } catch {
        return $false
    }
}

function Install-Dependencies {
    Write-Log "INFO" "Checking and installing dependencies..."
    
    # Install system dependencies if requested
    if ($InstallDeps) {
        if ($script:IsLinuxOS) {
            if (!(Install-LinuxDependencies)) {
                return $false
            }
        } elseif ($script:IsWindowsOS) {
            if (!(Install-WindowsDependencies)) {
                return $false
            }
        }
    }
    
    # Find Python
    $pythonCmd = Find-Command $script:PythonCmd
    if (!$pythonCmd) {
        Write-Log "ERROR" "Python not found. Please install Python 3.6+"
        return $false
    }
    
    # Check Python version
    if (!(Test-PythonVersion $pythonCmd)) {
        Write-Log "ERROR" "Python version is not compatible"
        return $false
    }
    
    # Find pip
    $pipCmd = Find-Command $script:PipCmd
    if (!$pipCmd) {
        Write-Log "ERROR" "pip not found. Please install pip"
        return $false
    }
    
    # Check and install Python packages
    $requiredPackages = @("requests", "psutil")
    $missingPackages = @()
    
    foreach ($package in $requiredPackages) {
        if (!(Test-PythonPackage $pythonCmd $package)) {
            $missingPackages += $package
        }
    }
    
    if ($missingPackages.Count -gt 0) {
        if (!(Install-PythonPackages $pipCmd $missingPackages)) {
            return $false
        }
    }
    
    Write-Log "INFO" "All dependencies are satisfied"
    $script:PythonCommand = $pythonCmd
    return $true
}

function Download-ClientScript {
    param([string]$ApiUrl)
    
    if (Test-Path $script:ClientScript) {
        Write-Log "INFO" "Client script already exists: $script:ClientScript"
        return $true
    }
    
    Write-Log "INFO" "Downloading client script..."
    
    # Extract base URL
    $baseUrl = $ApiUrl -replace '/api\.php.*$', ''
    $scriptUrl = "$baseUrl/local/$script:ClientScript"
    
    try {
        if (Get-Command "curl" -ErrorAction SilentlyContinue) {
            & curl -o $script:ClientScript $scriptUrl
            $success = $LASTEXITCODE -eq 0
        } else {
            # Use PowerShell for download
            Invoke-WebRequest -Uri $scriptUrl -OutFile $script:ClientScript
            $success = $true
        }
        
        if ($success) {
            Write-Log "INFO" "Downloaded client script from: $scriptUrl"
            
            # Make executable on Unix-like systems
            if (!$script:IsWindowsOS) {
                chmod +x $script:ClientScript 2>$null
            }
            
            return $true
        } else {
            Write-Log "ERROR" "Failed to download client script"
            return $false
        }
    } catch {
        Write-Log "ERROR" "Failed to download client script: $($_.Exception.Message)"
        Write-Log "INFO" "Please ensure the client script is available in the current directory"
        return $false
    }
}

function Test-ApiConnection {
    param([string]$ApiUrl)
    
    Write-Log "INFO" "Testing connection to API..."
    
    try {
        $body = @{
            action = "ping_host"
            host_id = "test"
        }
        
        if (Get-Command "curl" -ErrorAction SilentlyContinue) {
            Write-Log "INFO" "ApiUrl: $ApiUrl"
            Write-Log "INFO" "Body: $body"
            $response = & curl -s -w "%{http_code}" -o NUL --max-time 10 --data "action=ping_host&host_id=test" $ApiUrl
            $httpCode = $response[-3..-1] -join ""
        } else {
            # Use PowerShell for connection test
            Write-Log "INFO" "ApiUrl: $ApiUrl"
            Write-Log "INFO" "Body: $body"
            $response = Invoke-WebRequest -Uri $ApiUrl -Method "POST" -Body $body -TimeoutSec 10
            $httpCode = $response.StatusCode
        }
        
        if ($httpCode -match "^[23]\d\d$") {
            Write-Log "INFO" "API connection successful"
            return $true
        } else {
            Write-Log "ERROR" "API connection failed. HTTP status: $httpCode"
            return $false
        }
    } catch {
        Write-Log "ERROR" "Connection test failed: $($_.Exception.Message)"
        return $false
    }
}

function New-HostId {
    param([string]$CustomHostId)
    
    if ($CustomHostId) {
        return $CustomHostId
    }
    
    $hostname = if ($script:IsWindowsOS) { $env:COMPUTERNAME } else { hostname }
    $randomSuffix = -join ((1..8) | ForEach-Object { '{0:x}' -f (Get-Random -Maximum 16) })
    $hostId = "$hostname-$randomSuffix"
    
    Write-Log "INFO" "Generated host ID: $hostId"
    return $hostId
}

function Test-DaemonRunning {
    if (!(Test-Path $script:PidFile)) {
        return $false
    }
    
    try {
        $p_id = Get-Content $script:PidFile -ErrorAction Stop
        
        if ($script:IsWindowsOS) {
            $process = ps -Id $p_id -ErrorAction SilentlyContinue
        } else {
            $process = ps -p $p_id -o pid= 2>/dev/null
        }
        
        if ($process) {
            return $true
        } else {
            Remove-Item $script:PidFile -Force -ErrorAction SilentlyContinue
            return $false
        }
    } catch {
        Remove-Item $script:PidFile -Force -ErrorAction SilentlyContinue
        return $false
    }
}

function Show-DaemonStatus {
    if (Test-DaemonRunning) {
        $p_id = Get-Content $script:PidFile
        Write-Log "INFO" "GhostCrew client is running (PID: $pid)"
        
        # Show recent log entries
        if (Test-Path $script:LogFile) {
            Write-Host "`nRecent log entries:"
            Get-Content $script:LogFile -Tail 10
        }
    } else {
        Write-Log "INFO" "GhostCrew client is not running"
    }
}

function Stop-Daemon {
    if (Test-DaemonRunning) {
        $p_id = Get-Content $script:PidFile
        Write-Log "INFO" "Stopping GhostCrew client (PID: $p_id)..."
        
        try {
            if ($script:IsWindowsOS) {
                Stop-Process -Id $p_id -Force -ErrorAction Stop
            } else {
                kill -TERM $p_id 2>/dev/null
                Start-Sleep -Seconds 2
                if (ps -p $p_id -o pid= 2>/dev/null) {
                    kill -KILL $p_id 2>/dev/null
                    Write-Log "WARN" "Force killed client process"
                } else {
                    Write-Log "INFO" "Client stopped gracefully"
                }
            }
            
            Remove-Item $script:PidFile -Force -ErrorAction SilentlyContinue
        } catch {
            Write-Log "ERROR" "Error stopping daemon: $($_.Exception.Message)"
        }
    } else {
        Write-Log "INFO" "No running daemon found"
    }
}

function Start-Client {
    param(
        [string]$ApiUrl,
        [string]$HostId,
        [string]$Token,
        [bool]$DaemonMode
    )
    
    if ((Test-DaemonRunning) -and $DaemonMode) {
        Write-Log "ERROR" "Client is already running in daemon mode"
        Show-DaemonStatus
        return $false
    }
    
    Write-Log "INFO" "Starting GhostCrew client..."
    Write-Log "INFO" "Host ID: $HostId"
    Write-Log "INFO" "API URL: $ApiUrl"
    Write-Log "INFO" "Mode: $(if ($DaemonMode) { 'daemon' } else { 'foreground' })"
    
    # Prepare command
    $clientArgs = @($script:ClientScript, $ApiUrl, $HostId, $Token)
    
    if ($DaemonMode) {
        # Start as daemon/background process
        try {
            if ($script:IsWindowsOS) {
                # Windows: use Start-Process with WindowStyle Hidden
                $process = Start-Process -FilePath $script:PythonCommand -ArgumentList $clientArgs -WindowStyle Hidden -PassThru -RedirectStandardOutput $script:LogFile -RedirectStandardError $script:LogFile
            } else {
                # Linux: use nohup-like behavior
                $process = Start-Process -FilePath $script:PythonCommand -ArgumentList $clientArgs -PassThru
                & nohup $script:PythonCommand @clientArgs >> $script:LogFile 2>&1 
                $process = $!
            }
            
            # Write PID file
            $process.Id | Out-File -FilePath $script:PidFile -Encoding ASCII
            
            # Wait a moment to check if it started successfully
            Start-Sleep -Seconds 2
            if (Test-DaemonRunning) {
                Write-Log "INFO" "Client started successfully in daemon mode (PID: $($process.Id))"
                Write-Log "INFO" "Log file: $script:LogFile"
                Write-Log "INFO" "Use '$($MyInvocation.MyCommand.Name) -Status' to check status"
                Write-Log "INFO" "Use '$($MyInvocation.MyCommand.Name) -Kill' to stop"
                return $true
            } else {
                Write-Log "ERROR" "Failed to start client in daemon mode"
                Remove-Item $script:PidFile -Force -ErrorAction SilentlyContinue
                return $false
            }
        } catch {
            Write-Log "ERROR" "Failed to start daemon: $($_.Exception.Message)"
            return $false
        }
    } else {
        # Run in foreground
        Write-Log "INFO" "Starting in foreground mode. Press Ctrl+C to stop."
        try {
            & $script:PythonCommand @clientArgs
            return $true
        } catch {
            Write-Log "ERROR" "Error running client: $($_.Exception.Message)"
            return $false
        }
    }
}

# Main execution
function Main {
    Write-Log "INFO" "=== GhostCrew Cross-Platform Installer Starting ==="
    Write-Log "INFO" "Operating System: $(if ($script:IsWindowsOS) { 'Windows' } elseif ($script:IsLinuxOS) { 'Linux' } else { 'Unknown' })"
    Write-Log "INFO" "PowerShell Version: $($PSVersionTable.PSVersion)"
    
    # Show help if requested
    if ($Help -or (!$Kill -and !$Status -and !$ApiUrl)) {
        Show-Usage
        return
    }
    
    # Handle status and kill commands
    if ($Status) {
        Show-DaemonStatus
        return
    }
    
    if ($Kill) {
        Stop-Daemon
        return
    }
    
    # Validate required parameters
    if (!$ApiUrl) {
        Write-Log "ERROR" "API URL is required. Use -ApiUrl or set GHOSTCREW_API_URL"
        return
    }
    
    if (!$Token) {
        Write-Log "ERROR" "Instance token is required. Use -Token or set GHOSTCREW_TOKEN"
        return
    }
    
    # Install dependencies
    if (!(Install-Dependencies)) {
        Write-Log "ERROR" "Failed to install dependencies"
        return
    }
    
    # Generate host ID if needed
    $HostId = New-HostId $HostId
    
    # Download client script
    if (!(Download-ClientScript $ApiUrl)) {
        Write-Log "ERROR" "Client script not available"
        return
    }
    
    # Test connection
    if (!(Test-ApiConnection $ApiUrl)) {
        Write-Log "ERROR" "Connection test failed"
        return
    }
    
    # Start the client
    if (!(Start-Client $ApiUrl $HostId $Token $Daemon)) {
        Write-Log "ERROR" "Failed to start client"
        return
    }
    
    Write-Log "INFO" "Installation and startup completed successfully"
}

# Execute main function
Main