<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/session_init.php';

$ref = trim($_GET['ref'] ?? '');
$req = null;
$allowedStatuses = ['approved', 'ready_for_pickup', 'released'];

if ($ref) {
    $stmt = $pdo->prepare('
        SELECT r.reference_number, r.status, r.purpose, r.updated_at,
               dt.name AS document_name,
               res.first_name, res.last_name
        FROM requests r
        JOIN document_types dt ON dt.document_type_id = r.document_type_id
        JOIN residents res ON res.resident_id = r.resident_id
        WHERE r.reference_number = ?
    ');
    $stmt->execute([$ref]);
    $req = $stmt->fetch();
}

$isValid = $req && in_array($req['status'], $allowedStatuses, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Verification — Barangay Lawacamulag, San Jose, Tarlac</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<style nonce="<?= $csp_nonce ?>">
  :root{--paper:#F3EEE1;--ink:#211D16;--ink-soft:#5B564A;--blue:#1E3A5F;--blue-deep:#122845;--red:#9B2B22;--gold:#B08D3E;--line:#C9C1AC;--green:#2F6B33;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:'Public Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
  .card{background:#FBF9F2;border:1px solid var(--line);max-width:420px;width:100%;padding:36px 32px;text-align:center;box-shadow:6px 6px 0 var(--paper-2);}
  .icon{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:26px;color:#fff;}
  .icon.valid{background:var(--green);}
  .icon.invalid{background:var(--red);}
  h1{font-family:'Fraunces',serif;font-size:20px;margin-bottom:6px;}
  .sub{font-size:13px;color:var(--ink-soft);margin-bottom:24px;}
  .details{text-align:left;border-top:1px dashed var(--line);padding-top:16px;}
  .row{display:flex;justify-content:space-between;font-size:13px;padding:6px 0;color:var(--ink-soft);}
  .row span:last-child{color:var(--ink);font-weight:500;text-align:right;}
  .mono{font-family:'IBM Plex Mono',monospace;}
</style>
</head>
<body>
  <div class="card">
    <?php if ($isValid): ?>
      <div class="icon valid">✓</div>
      <h1>Document Verified</h1>
      <p class="sub">This certificate was issued by Barangay Lawacamulag, San Jose, Tarlac.</p>
      <div class="details">
        <div class="row"><span>Document</span><span><?= htmlspecialchars($req['document_name']) ?></span></div>
        <div class="row"><span>Issued to</span><span><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></span></div>
        <div class="row"><span>Reference no.</span><span class="mono"><?= htmlspecialchars($req['reference_number']) ?></span></div>
        <div class="row"><span>Date issued</span><span><?= date('F j, Y', strtotime($req['updated_at'])) ?></span></div>
      </div>
    <?php else: ?>
      <div class="icon invalid">✕</div>
      <h1>Not Verified</h1>
      <p class="sub">
        <?= $req ? 'This reference number exists but has not been approved for release.' : 'No document was found matching this reference number.' ?>
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
