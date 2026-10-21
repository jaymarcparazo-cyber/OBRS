<?php
session_start();
require __DIR__ . '/config.php';

$ref = trim($_GET['ref'] ?? '');
if (!$ref) {
    die('Missing reference number.');
}

$stmt = $pdo->prepare('
    SELECT r.request_id, r.reference_number, r.status, r.purpose, r.requested_at, r.updated_at, r.resident_id,
           r.on_behalf_of, r.beneficiary_name, r.beneficiary_civil_status, r.relationship,
           dt.name AS document_name,
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

$address = $req['address'];

// Fixed location details
$barangayName = 'Lawacamulag';
$municipality = 'San Jose';
$province     = 'Tarlac';

// Build an absolute verification URL for the QR code
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';

$host = $_SERVER['HTTP_HOST'];

$dir = rtrim(
    str_replace(
        basename($_SERVER['SCRIPT_NAME']),
        '',
        $_SERVER['SCRIPT_NAME']
    ),
    '/'
);

$verifyUrl = "{$scheme}://{$host}{$dir}/verify.php?ref="
    . urlencode($req['reference_number']);

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data='
    . urlencode($verifyUrl);
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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap"
      rel="stylesheet">

<style>
  :root{
    --paper:#F3EEE1;
    --ink:#211D16;
    --ink-soft:#5B564A;
    --blue:#1E3A5F;
    --blue-deep:#122845;
    --red:#9B2B22;
    --gold:#B08D3E;
    --line:#C9C1AC;
  }

  *{
    box-sizing:border-box;
    margin:0;
    padding:0;
  }

  body{
    background:var(--blue-deep);
    color:var(--ink);
    font-family:'Public Sans',sans-serif;
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
    color:#C9D3E0;
    font-size:13.5px;
  }

  .print-btn{
    background:var(--gold);
    color:var(--blue-deep);
    border:none;
    padding:11px 22px;
    font-weight:600;
    font-size:14px;
    border-radius:2px;
    cursor:pointer;
  }

  .print-btn:hover{
    filter:brightness(0.95);
  }

  .cert{
    position:relative;
    background:#FBF9F2;
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
    font-family:'IBM Plex Mono',monospace;
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
    font-family:'Fraunces',serif;
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
    font-family:'Fraunces',serif;
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
    font-family:'IBM Plex Mono',monospace;
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

  .verify-block img{
    border:1px solid var(--line);
    padding:3px;
    background:#fff;
    flex-shrink:0;
    width:90px;
    height:90px;
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
    font-family:'IBM Plex Mono',monospace;
    color:var(--blue);
    word-break:break-all;
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
    font-family:'Fraunces',serif;
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
</head>

<body>

<div class="toolbar">
    <a href="javascript:void(0)" onclick="smartBack()">&larr; Back</a>

    <button class="print-btn" onclick="window.print()">
        Print / Save as PDF
    </button>
</div>

<div class="cert">

    <div class="watermark" aria-hidden="true">
        <?php for ($i = 0; $i < 16; $i++): ?>
            <div class="watermark-line">
                OFFICIAL COPY
                &nbsp;&nbsp;
                BARANGAY <?= strtoupper(htmlspecialchars("$barangayName, $municipality, $province")) ?>
                &nbsp;&nbsp;
                OFFICIAL COPY
            </div>
        <?php endfor; ?>
    </div>

    <div class="cert-content">

        <div class="cert-head">
            <div class="rep">
                Republic of the Philippines
            </div>

            <div class="office">
                Barangay <?= htmlspecialchars("$barangayName, $municipality, $province") ?>
            </div>

            <div class="addr">
                Barangay Hall, <?= htmlspecialchars("$barangayName, $municipality, $province") ?>, Philippines
            </div>
        </div>

        <div class="divider"></div>

        <div class="cert-title">
            <?= htmlspecialchars($req['document_name']) ?>
        </div>

        <div class="cert-subtitle">
            Office of the Punong Barangay
        </div>

        <div class="cert-body">

            This is to certify that
            <strong><?= htmlspecialchars(strtoupper($fullName)) ?></strong>,
            of legal age,
            <?= htmlspecialchars($civilStatus) ?>,
            Filipino, and a resident of
            Barangay <?= htmlspecialchars($barangayName) ?>,
            <?= htmlspecialchars($municipality) ?>,
            <?= htmlspecialchars($province) ?>,
            is a bona fide resident of this barangay.

            <br><br>

            This certification is issued upon the request of the above-named person for
            <strong><?= htmlspecialchars($req['purpose']) ?></strong>.

            <br><br>

            Issued this
            <strong><?= date('jS', strtotime($req['updated_at'])) ?></strong>
            day of
            <strong><?= date('F', strtotime($req['updated_at'])) ?></strong>,
            <strong><?= date('Y', strtotime($req['updated_at'])) ?></strong>
            at Barangay <?= htmlspecialchars($barangayName) ?>,
            <?= htmlspecialchars($municipality) ?>,
            <?= htmlspecialchars($province) ?>,
            Philippines.

        </div>

        <div class="cert-foot">

            <div class="foot-left">

                <div class="disclaimer">
                    Not valid without the official dry seal of this office.
                </div>

                <div class="verify-block">

                    <img
                        src="<?= htmlspecialchars($qrUrl) ?>"
                        alt="Verification QR code"
                        width="90"
                        height="90"
                    >

                    <div class="verify-caption">

                        <strong>Scan to verify authenticity.</strong>
                        <br>

                        Confirms this document was genuinely issued and unaltered.
                        <br>

                        <a
                            class="mono"
                            href="<?= htmlspecialchars($verifyUrl) ?>"
                            target="_blank"
                        >
                            <?= htmlspecialchars($verifyUrl) ?>
                        </a>

                    </div>

                </div>

            </div>

            <div class="sig-block">

                <div class="stamp">
                    <img src="logo.jpg" alt="Official seal">
                </div>

                <div class="sig-line"></div>

                <div class="sig-name">
                    Hon. Punong Barangay
                </div>

                <div>
                    Barangay Chairperson
                </div>

            </div>

        </div>

        <?php if ($req['on_behalf_of']): ?>

            <div class="on-behalf-note">
                Requested by
                <?= htmlspecialchars($requesterName) ?>
                (<?= htmlspecialchars($req['relationship']) ?>)
                on behalf of the named recipient.
            </div>

        <?php endif; ?>

        <div class="page-footer">

            <span>
                Reference No.
                <span class="mono">
                    <?= htmlspecialchars($req['reference_number']) ?>
                </span>
            </span>

            <span>
                Date issued: <?= $issueDate ?>
            </span>

        </div>

    </div>

</div>

<script>
function smartBack() {

    const fallbackUrl =
        '<?= $isStaff ? "dashboard.php" : "my_requests.php" ?>';

    if (window.history.length > 1) {
        history.back();
    } else {
        window.location.href = fallbackUrl;
    }
}
</script>

</body>
</html>