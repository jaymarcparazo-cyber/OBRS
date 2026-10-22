<?php
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/config.php';
require_once __DIR__ . '/certificate_templates.php';
$certificateSettings = load_certificate_settings($pdo);

$ref = trim($_GET['ref'] ?? '');
if (!$ref) {
    die('Missing reference number.');
}

$stmt = $pdo->prepare('
    SELECT r.request_id, r.reference_number, r.status, r.purpose, r.requested_at, r.updated_at, r.resident_id,
           r.on_behalf_of, r.beneficiary_name, r.beneficiary_civil_status, r.relationship,
           dt.name AS document_name, dt.certificate_title, dt.certificate_template,
           res.first_name, res.last_name, res.address, res.civil_status
    FROM requests r
    JOIN document_types dt ON dt.document_type_id = r.document_type_id
    JOIN residents res ON res.resident_id = r.resident_id
    WHERE r.reference_number = ?
');
$stmt->execute([$ref]);
$req = $stmt->fetch();

if (!$req) {
    die('Request not found.');
}

// Only approved (or further along) requests can produce a document
$allowedStatuses = ['approved', 'ready_for_pickup', 'released'];
if (!in_array($req['status'], $allowedStatuses, true)) {
    die('This document has not been approved yet. Current status: ' . htmlspecialchars($req['status']));
}

// Access control: the owning resident, or any logged-in staff member
$isResidentOwner = !empty($_SESSION['resident_id']) && $_SESSION['resident_id'] == $req['resident_id'];
$isStaff = !empty($_SESSION['staff_id']);

if (!$isResidentOwner && !$isStaff) {
    http_response_code(403);
    die('You are not authorized to view this document.');
}

$requesterName = $req['first_name'] . ' ' . $req['last_name'];
$issueDate     = date('F j, Y', strtotime($req['updated_at']));

// If this was requested on behalf of someone else, the certificate is issued
// in THEIR name/civil status — but at the requester's own registered address,
// since the beneficiary is assumed to live at the same address.
if ($req['on_behalf_of']) {
    $fullName    = $req['beneficiary_name'];
    $civilStatus = $req['beneficiary_civil_status']
        ? strtolower($req['beneficiary_civil_status'])
        : 'of legal age';
} else {
    $fullName    = $requesterName;
    $civilStatus = $req['civil_status']
        ? strtolower($req['civil_status'])
        : 'of legal age';
}

$certificateTemplate = $req['certificate_template'] ?: default_certificate_template();
$certificateWording = render_certificate_template($certificateTemplate, [
    'name' => strtoupper($fullName),
    'civil_status' => $civilStatus,
    'purpose' => $req['purpose'],
    'issue_date' => date('F j, Y', strtotime($req['updated_at'])),
    'barangay' => $certificateSettings['barangay_name'],
    'municipality' => $certificateSettings['municipality'],
    'province' => $certificateSettings['province'],
]);
$certificateTitle = $req['certificate_title'] ?: $req['document_name'];
$verifyUrl = 'verify.php?ref=' . rawurlencode($req['reference_number']);
$certificateReference = $req['reference_number'];
$requesterRelationship = $req['on_behalf_of'] ? $req['relationship'] : '';
$certificateLogoUrl = app_asset_url('logo.jpg');
$certificateHomeUrl = app_asset_url('obrs.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    <?= htmlspecialchars($req['document_name']) ?>
    —
    <?= htmlspecialchars($req['reference_number']) ?>
</title>

<style nonce="<?= $csp_nonce ?>">
  :root{
    --paper:#EAF4FB;
    --paper-2:#E4F1E5;
    --ink:#173042;
    --ink-soft:#506A78;
    --blue:#1685C7;
    --blue-deep:#075184;
    --green:#23833B;
    --green-deep:#17632B;
    --red:#9B2B22;
    --gold:#E6B92E;
    --line:#B9D2DE;
  }

  *{
    box-sizing:border-box;
    margin:0;
    padding:0;
  }

  body{
    background:var(--paper);
    color:var(--ink);
    font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    align-items:center;
    padding:32px 20px;
    overflow-x:auto;
  }

  .toolbar{
    max-width:210mm;
    width:100%;
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
  }

  .toolbar a{
    color:var(--green-deep);
    font-size:13.5px;
  }

  .toolbar a:hover{
    color:var(--blue-deep);
  }

  .print-btn{
    background:var(--green);
    color:#fff;
    border:none;
    padding:11px 22px;
    font-weight:600;
    font-size:14px;
    border-radius:2px;
    cursor:pointer;
  }

  .print-btn:hover{
    background:var(--green-deep);
  }

  .cert{
    position:relative;
    background:#fff;
    border:1px solid var(--line);
    width:210mm;
    min-height:297mm;
    padding:28mm 24mm;
    box-shadow:0 16px 40px rgba(0,0,0,0.3);
    flex-shrink:0;
    display:flex;
    flex-direction:column;
    overflow:hidden;
  }

  .watermark{
    position:absolute;
    inset:0;
    z-index:0;
    pointer-events:none;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:space-evenly;
    align-items:center;
    transform:rotate(-30deg) scale(1.5);
  }

  .watermark-line{
    font-family:'Courier New',monospace;
    font-size:13px;
    font-weight:500;
    letter-spacing:0.25em;
    color:var(--blue);
    opacity:0.06;
    white-space:nowrap;
  }

  .cert-content{
    position:relative;
    z-index:1;
    display:flex;
    flex-direction:column;
    flex:1;
  }

  .cert::before{
    content:"";
    position:absolute;
    inset:14px;
    border:1.5px solid var(--gold);
    opacity:0.55;
    pointer-events:none;
  }

  .cert-head{
    text-align:center;
    margin-bottom:6px;
  }

  .cert-head .rep{
    font-size:12px;
    letter-spacing:0.14em;
    text-transform:uppercase;
    color:var(--ink-soft);
  }

  .cert-head .office{
    font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;
    font-weight:600;
    font-size:22px;
    color:var(--blue);
    margin-top:6px;
  }

  .cert-head .addr{
    font-size:11.5px;
    color:var(--ink-soft);
    margin-top:2px;
  }

  .divider{
    width:60px;
    height:2px;
    background:var(--gold);
    margin:22px auto;
  }

  .cert-title{
    font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;
    font-size:26px;
    font-weight:600;
    text-align:center;
    letter-spacing:0.02em;
    margin-bottom:8px;
    color:var(--ink);
    text-transform:uppercase;
  }

  .cert-subtitle{
    text-align:center;
    font-size:12px;
    color:var(--ink-soft);
    margin-bottom:34px;
    letter-spacing:0.05em;
  }

  .cert-body{
    font-size:15px;
    line-height:1.9;
    color:var(--ink);
    text-align:justify;
    margin-bottom:40px;
  }

  .cert-body strong{
    font-weight:600;
  }

  .on-behalf-note{
    font-size:10.5px;
    color:var(--ink-soft);
    font-style:italic;
    margin-top:8px;
  }

  .page-footer{
    display:flex;
    justify-content:space-between;
    font-size:11px;
    color:var(--ink-soft);
    border-top:1px dashed var(--line);
    padding-top:14px;
    margin-top:36px;
  }

  .page-footer .mono{
    font-family:'Courier New',monospace;
    color:var(--blue);
  }

  .cert-foot{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    margin-top:auto;
  }

  .foot-left{
    max-width:300px;
  }

  .disclaimer{
    font-size:11px;
    color:var(--ink-soft);
    margin-bottom:18px;
  }

  .verify-block{
    display:flex;
    align-items:center;
    gap:14px;
  }

  .verify-caption{
    font-size:10.5px;
    color:var(--ink-soft);
    line-height:1.5;
  }

  .verify-caption strong{
    color:var(--ink);
  }

  .verify-caption .mono{
    font-family:'Courier New',monospace;
    color:var(--blue);
    overflow-wrap:anywhere;
    font-size:9.5px;
    text-decoration:none;
  }

  .verify-caption .mono:hover{
    text-decoration:underline;
  }

  .sig-block{
    position:relative;
    font-size:12px;
    color:var(--ink-soft);
    text-align:center;
  }

  .sig-line{
    width:170px;
    border-bottom:1px solid var(--ink);
    margin-bottom:6px;
    height:0;
  }

  .sig-name{
    font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;
    font-weight:600;
    font-size:14px;
    color:var(--ink);
  }

  .stamp{
    position:absolute;
    bottom:100%;
    left:50%;
    transform:translateX(-50%) rotate(-12deg);
    margin-bottom:-46px;
    width:112px;
    height:112px;
    border-radius:50%;
    overflow:hidden;
    mix-blend-mode:multiply;
  }

  .stamp img{
    width:100%;
    height:100%;
    object-fit:cover;
    opacity:0.55;
  }

  @page{
    size:A4;
    margin:0;
  }

  @media print{
    body{
      background:#fff;
      padding:0;
      overflow:visible;
    }

    .toolbar{
      display:none;
    }

    .cert{
      box-shadow:none;
      border:none;
      width:100%;
      min-height:100vh;
    }
  }

</style>
<link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('certificate_document.css'), ENT_QUOTES) ?>">
</head>

<body>

<div class="toolbar">
    <a href="#" id="backLink">Back</a>
<button class="print-btn" id="printBtn">
        Print / Save as PDF
    </button>
</div>

<?php
$onBehalfRelationship = $requesterRelationship;
require __DIR__ . '/certificate_document.php';
?>

<script nonce="<?= $csp_nonce ?>">
function smartBack() {

    const fallbackUrl =
        '<?= $isStaff ? "dashboard.php" : "my_requests.php" ?>';

    if (window.history.length > 1) {
        history.back();
    } else {
        window.location.href = fallbackUrl;
    }
}
document.getElementById('backLink').addEventListener('click', function (e) {
  e.preventDefault();
  smartBack();
});
document.getElementById('printBtn').addEventListener('click', function () {
  window.print();
});
</script>

</body>
</html>