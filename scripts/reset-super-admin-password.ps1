$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$securePassword = $null
$secureConfirmation = $null
$passwordPointer = [IntPtr]::Zero
$confirmationPointer = [IntPtr]::Zero
$password = $null
$confirmation = $null
$process = $null
$exitCode = 1

try {
    $phpCommand = Get-Command php -ErrorAction Stop
    $securePassword = Read-Host 'Password baru (minimal 12 karakter)' -AsSecureString
    $secureConfirmation = Read-Host 'Ulangi password baru' -AsSecureString

    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $confirmationPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureConfirmation)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $confirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmationPointer)

    if ($password.Length -lt 12) {
        throw 'Password harus memiliki minimal 12 karakter. Password tidak diubah.'
    }

    if (-not [string]::Equals($password, $confirmation, [StringComparison]::Ordinal)) {
        throw 'Konfirmasi password tidak cocok. Password tidak diubah.'
    }

    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $phpCommand.Source
    $startInfo.Arguments = 'artisan sidatex:reset-super-admin-password --stdin --no-interaction'
    $startInfo.WorkingDirectory = $projectRoot
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardInput = $true

    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    $null = $process.Start()
    $process.StandardInput.WriteLine($password)
    $process.StandardInput.WriteLine($confirmation)
    $process.StandardInput.Close()
    $process.WaitForExit()
    $exitCode = $process.ExitCode
}
catch {
    Write-Error $_.Exception.Message
    $exitCode = 1
}
finally {
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }

    if ($confirmationPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmationPointer)
    }

    if ($null -ne $securePassword) {
        $securePassword.Dispose()
    }

    if ($null -ne $secureConfirmation) {
        $secureConfirmation.Dispose()
    }

    if ($null -ne $process) {
        $process.Dispose()
    }

    $password = $null
    $confirmation = $null
}

exit $exitCode
