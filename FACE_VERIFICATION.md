# Local face comparison setup

The request form can compare a camera selfie with a JPG/PNG ID image using a
local Python service. Neither image is sent to a third-party face API. The
selfie is used only for the comparison and is not added to the request or saved
in the application's uploads directory. The uploaded ID image remains attached
to the request for staff review, as before.

Before face comparison, local OCR checks for government-issuer text and at
least two identity-field indicators. An image that does not meet this
document-evidence threshold cannot receive an automatic match; it can only be
submitted for staff identity review. A face non-match blocks submission.
Residents can retake the selfie or upload a clearer image of their own ID.

The OCR screen is a broad document-evidence heuristic, not an exhaustive list
of accepted ID types and not proof that a document is genuine. Some legitimate
IDs may require staff review, and text can be forged or copied onto another
image. A face match does not establish liveness or prove ID authenticity, and
must not be used as the sole reason to approve a request. The dashboard labels
requests that used staff review.

## Requirements

- Python 3.10 (the pinned TensorFlow version is needed for Windows/XAMPP compatibility)
- RapidOCR and its ONNX Runtime dependency (installed from
  `face_verification\requirements.txt`)
- PHP cURL and Fileinfo extensions enabled
- PHP `upload_max_filesize=6M` and `post_max_size=12M` (restart Apache/PHP after changing them)
- HTTPS for remote browsers (camera access on `localhost` is also allowed)
- A shared, random `OBRS_FACE_SERVICE_TOKEN` available to both Apache/PHP and
  the local Python process

Create the virtual environment, install dependencies, and run the service from
the repository root.

On Windows, from the repository root (requires `uv`):

```powershell
uv python install 3.10
uv venv --python 3.10 face_verification\.venv
uv pip install --python face_verification\.venv\Scripts\python.exe -r face_verification\requirements.txt
.\face_verification\start_service.ps1
```

On Ubuntu:

```sh
cd face_verification
python3.10 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
.venv/bin/python app.py
```

Set `OBRS_FACE_SERVICE_TOKEN` to the same randomly generated secret in the
environment used by Apache/PHP and the Python service. For example, generate a
value with PHP:

```text
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

On Windows, the token is stored in the protected
`C:\xampp\apache\conf\extra\obrs-face-verification.conf`. The service launcher
and PHP endpoint both read it from that file. Apache includes it via
`httpd.conf`; restart Apache after installing or changing the configuration.
Do not commit the token or put it in a public web directory. On Ubuntu,
configure it in the Python service's systemd environment and pass the same
value to PHP (for Apache `mod_php`, use `SetEnv`; for PHP-FPM, use the pool's
`env[]` setting).

The Windows service can be set to start automatically at user sign-in with a
Task Scheduler task named `OBRS Face Verification`. The task runs
`face_verification\start_service.ps1` as the current user and does not contain
the token. To register it in PowerShell:

```powershell
$taskName = 'OBRS Face Verification'
$userId = "$env:USERDOMAIN\$env:USERNAME"
$script = 'C:\xampp\htdocs\OBRS\face_verification\start_service.ps1'
$action = New-ScheduledTaskAction -Execute "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" `
  -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$script`"" `
  -WorkingDirectory 'C:\xampp\htdocs\OBRS\face_verification'
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $userId
$principal = New-ScheduledTaskPrincipal -UserId $userId -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -ExecutionTimeLimit ([TimeSpan]::Zero)
$task = New-ScheduledTask -Action $action -Trigger $trigger -Principal $principal -Settings $settings
Register-ScheduledTask -TaskName $taskName -InputObject $task -Force
```

The task is installed on the current Windows machine. Its first automatic
start occurs at the next sign-in; the service is already running in the current
session.

The service binds only to `127.0.0.1:8765`; it is not intended to be exposed to
the network. DeepFace downloads its model weights on the first service start
and caches those model files locally. This setup download contains model
weights only; resident images are processed locally and are not uploaded to
the model provider. RapidOCR may download its OCR model files on first use;
these are also run locally. Until OCR identifies government-issuer and
identity-field text, an image cannot receive an automatic face-match result.
Keep the service running for automatic comparisons. If it is unavailable,
residents can still submit for staff review.

## Operational review

Selfies may be held in temporary upload buffers/files while the comparison
runs; they are not written to the app's `uploads/` directory or database and
are not retained after request processing.
Uploaded ID images are retained with requests for staff review. Before
production use, have the barangay's privacy officer review the consent text,
lawful basis, access controls, retention schedule for ID attachments, and
manual review procedure. Face matching can produce false matches and
non-matches.
