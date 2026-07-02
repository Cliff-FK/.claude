<#
.SYNOPSIS
  Stocke / lit / supprime le PAT Asana dans le Gestionnaire d'identification Windows
  (Windows Credential Manager, type GENERIC, vault de l'utilisateur courant).
  Le secret est chiffre par Windows et n'est lisible que par ce compte sur cette machine.

.USAGE
  # Stocker (le token n'est PAS passe en argument visible : lu sur stdin)
  '2/...:...'  | powershell -File asana-cred.ps1 -Action store
  # Lire (ecrit le token en clair sur stdout, pour usage par curl/node)
  powershell -File asana-cred.ps1 -Action read
  # Supprimer
  powershell -File asana-cred.ps1 -Action delete
#>
param(
  [Parameter(Mandatory)][ValidateSet('store','read','delete')][string]$Action,
  [string]$Target = 'ASANA_PAT'
)
$ErrorActionPreference = 'Stop'

Add-Type @'
using System;
using System.Runtime.InteropServices;
public class CredMan {
  [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)]
  public struct CREDENTIAL {
    public uint Flags; public uint Type; public string TargetName; public string Comment;
    public System.Runtime.InteropServices.ComTypes.FILETIME LastWritten;
    public uint CredentialBlobSize; public IntPtr CredentialBlob; public uint Persist;
    public uint AttributeCount; public IntPtr Attributes; public string TargetAlias; public string UserName;
  }
  [DllImport("advapi32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
  public static extern bool CredWriteW(ref CREDENTIAL c, uint flags);
  [DllImport("advapi32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
  public static extern bool CredReadW(string target, uint type, uint flags, out IntPtr cred);
  [DllImport("advapi32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
  public static extern bool CredDeleteW(string target, uint type, uint flags);
  [DllImport("advapi32.dll")]
  public static extern void CredFree(IntPtr cred);
}
'@

$GENERIC = [uint32]1
$PERSIST_LOCAL = [uint32]2  # survit aux redemarrages, vault de l'utilisateur

switch ($Action) {
  'store' {
    # Source du secret, par ordre de priorite (aucune ne le met en argument visible) :
    #  1) variable d'env ephemere ASANA_CRED_IN  2) pipeline $input  3) stdin redirige
    $secret = $env:ASANA_CRED_IN
    if ([string]::IsNullOrWhiteSpace($secret)) { $secret = (@($input) -join "`n") }
    if ([string]::IsNullOrWhiteSpace($secret) -and [Console]::IsInputRedirected) { $secret = [Console]::In.ReadToEnd() }
    $secret = "$secret".Trim()
    if ([string]::IsNullOrWhiteSpace($secret)) { throw 'Aucun secret recu (env ASANA_CRED_IN / pipeline / stdin vides).' }
    $blob = [System.Text.Encoding]::Unicode.GetBytes($secret)
    $ptr  = [Runtime.InteropServices.Marshal]::AllocHGlobal($blob.Length)
    [Runtime.InteropServices.Marshal]::Copy($blob, 0, $ptr, $blob.Length)
    $c = New-Object CredMan+CREDENTIAL
    $c.Type = $GENERIC; $c.TargetName = $Target; $c.UserName = 'asana'
    $c.CredentialBlobSize = $blob.Length; $c.CredentialBlob = $ptr; $c.Persist = $PERSIST_LOCAL
    $ok = [CredMan]::CredWriteW([ref]$c, 0)
    $err = [Runtime.InteropServices.Marshal]::GetLastWin32Error()
    [Runtime.InteropServices.Marshal]::FreeHGlobal($ptr)
    if (-not $ok) { throw "CredWrite a echoue (Win32 $err)" }
    Write-Output "OK: '$Target' stocke dans le Credential Manager ($($blob.Length/2) caracteres)."
  }
  'read' {
    $p = [IntPtr]::Zero
    if (-not [CredMan]::CredReadW($Target, $GENERIC, 0, [ref]$p)) {
      throw "Credential '$Target' introuvable (Win32 $([Runtime.InteropServices.Marshal]::GetLastWin32Error()))."
    }
    $c = [Runtime.InteropServices.Marshal]::PtrToStructure($p, [type]'CredMan+CREDENTIAL')
    $secret = [Runtime.InteropServices.Marshal]::PtrToStringUni($c.CredentialBlob, $c.CredentialBlobSize/2)
    [CredMan]::CredFree($p)
    [Console]::Out.Write($secret)   # pas de newline : sortie brute exploitable
  }
  'delete' {
    if (-not [CredMan]::CredDeleteW($Target, $GENERIC, 0)) {
      throw "Suppression de '$Target' impossible (Win32 $([Runtime.InteropServices.Marshal]::GetLastWin32Error()))."
    }
    Write-Output "OK: '$Target' supprime du Credential Manager."
  }
}
