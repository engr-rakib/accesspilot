# PowerShell Runtime

Purpose:

- production AD and HRMS automation scripts
- called by PHP controllers through `config/powershell.php`

Main scripts:

- `Install-AccessPilot-IIS.ps1` (Client-side IIS installer and runtime initiator)
- `KEymasterConfigPro.ps1` (Setup & Secure Config)
- `unlock-user.ps1`
- `reset-unlock-user.ps1`
- `enable-user.ps1`
- `disable-user.ps1`
- `create-user.ps1`
- `create-user-core.ps1`
- `get-user-info.ps1`
- `get-ad-user-info.ps1`
- `modify-ad-user.ps1`
- `check-ad-hrms-status.ps1`
- `export-hrms-ad-login-id.ps1`
- `export-ad-user-list.ps1`
- `get-ad-health.ps1`
- `export-group-user-list.ps1`
- `get-ad-organizational-units.ps1`
- `get-ad-groups.ps1`
- `manual-create-ad-user.ps1`
- `get-user-report.ps1`

Source of truth:

- script path mapping: `config/powershell.php`
- client installer runbook: `INSTALL_ACCESSPILOT_IIS_README.md`

Developer note:

- treat this folder as runtime infrastructure
- do not reintroduce `sample_script/`
- `Install-AccessPilot-IIS.ps1` is the preferred client-side bootstrap when you want hosting + runtime path initialization in one step
