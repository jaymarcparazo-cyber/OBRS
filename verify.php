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
<style nonce="<?= $csp_nonce ?>">
  :root{
    --paper:#EAF4FB;--paper-2:#E4F1E5;--ink:#173042;--ink-soft:#506A78;
    --blue:#1685C7;--blue-deep:#075184;--red:#9B2B22;--green:#23833B;
    --green-deep:#17632B;--gold:#E6B92E;--line:#B9D2DE;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:linear-gradient(135deg,var(--paper) 0%,#fff 52%,var(--paper-2) 100%);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;line-height:1.5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden;}
  .bg-logo{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:min(440px,90vw);aspect-ratio:1;border-radius:50%;background-image:url('<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>');background-size:contain;background-repeat:no-repeat;background-position:center;opacity:.06;z-index:0;pointer-events:none;}
  .card{position:relative;z-index:1;background:rgba(255,255,255,.97);border:1px solid var(--line);border-top:4px solid var(--green);border-radius:12px;max-width:460px;width:100%;padding:32px;text-align:center;box-shadow:0 18px 48px rgba(7,81,132,.12);}
  .brand{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:28px;text-align:left;}
  .brand-mark{width:46px;height:46px;object-fit:cover;border-radius:50%;border:2px solid var(--gold);flex-shrink:0;}
  .brand-name{font-size:15px;font-weight:700;letter-spacing:-.015em;color:var(--green-deep);}
  .brand-location{font-size:11px;color:var(--ink-soft);}
  .icon{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:26px;color:#fff;}
  .icon.valid{background:var(--green);}
  .icon.invalid{background:var(--red);}
  h1{font-size:24px;font-weight:700;letter-spacing:-.025em;color:var(--blue-deep);margin-bottom:6px;}
  .sub{font-size:13px;color:var(--ink-soft);margin:0 auto 24px;max-width:340px;}
  .details{text-align:left;border-top:1px solid var(--line);padding-top:14px;}
  .row{display:flex;justify-content:space-between;gap:16px;font-size:13px;padding:7px 0;color:var(--ink-soft);}
  .row span:last-child{color:var(--ink);font-weight:500;text-align:right;overflow-wrap:anywhere;}
  .mono{font-family:Consolas,'Courier New',monospace;}
  @media(max-width:480px){body{padding:18px}.card{padding:26px 22px}.brand{margin-bottom:24px}.row{font-size:12px}}
</style>
</head>
<body>
  <div class="bg-logo" aria-hidden="true"></div>
  <div class="card">
    <div class="brand">
      <img class="brand-mark" src="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>" alt="Barangay Lawacamulag seal">
      <div>
        <div class="brand-name">Barangay Lawacamulag</div>
        <div class="brand-location">San Jose, Tarlac · Document verification</div>
      </div>
    </div>
    <?php if ($isValid): ?>
      <div class="icon valid" aria-hidden="true">✓</div>
      <h1>Document Verified</h1>
      <p class="sub">This certificate was issued by Barangay Lawacamulag, San Jose, Tarlac.</p>
      <div class="details">
        <div class="row"><span>Document</span><span><?= htmlspecialchars($req['document_name']) ?></span></div>
        <div class="row"><span>Issued to</span><span><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></span></div>
        <div class="row"><span>Reference no.</span><span class="mono"><?= htmlspecialchars($req['reference_number']) ?></span></div>
        <div class="row"><span>Date issued</span><span><?= date('F j, Y', strtotime($req['updated_at'])) ?></span></div>
      </div>
    <?php else: ?>
      <div class="icon invalid" aria-hidden="true">✕</div>
      <h1>Not Verified</h1>
      <p class="sub">
        <?= $req ? 'This reference number exists but has not been approved for release.' : 'No document was found matching this reference number.' ?>
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
