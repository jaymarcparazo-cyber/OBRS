<?php
require __DIR__ . '/admin_auth.php';
require __DIR__ . '/config.php';

$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$isAdmin = ($_SESSION['staff_role'] ?? '') === 'admin';
$statuses = ['pending', 'processing', 'approved', 'rejected', 'ready_for_pickup', 'released'];
$filterStatuses = array_merge($statuses, ['cancelled']);
if ($statusFilter !== 'all' && !in_array($statusFilter, $filterStatuses, true)) {
    $statusFilter = 'all';
}

$sql = '
    SELECT r.request_id, r.resident_id, r.document_type_id, r.reference_number, r.status, r.purpose, r.requested_at,
           r.on_behalf_of, r.beneficiary_name, r.relationship,
           q.queue_number, q.status AS queue_status,
           dt.name AS document_name,
           res.first_name, res.last_name, res.email, res.contact_number,
           ra.file_path AS attachment_path, ra.file_label AS attachment_label
    FROM requests r
    JOIN document_types dt ON dt.document_type_id = r.document_type_id
    JOIN residents res ON res.resident_id = r.resident_id
    LEFT JOIN service_queue_entries q ON q.request_id = r.request_id
    LEFT JOIN (
        SELECT request_id, MIN(attachment_id) AS first_attachment_id
        FROM request_attachments
        GROUP BY request_id
    ) fa ON fa.request_id = r.request_id
    LEFT JOIN request_attachments ra ON ra.attachment_id = fa.first_attachment_id
    WHERE 1=1
';
$params = [];

if ($statusFilter === 'all') {
    $sql .= " AND r.status <> 'cancelled'";
} else {
    $sql .= ' AND r.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (r.reference_number LIKE ? OR res.first_name LIKE ? OR res.last_name LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY r.requested_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();
$documentTypes = $pdo->query('SELECT document_type_id, name FROM document_types WHERE is_active = 1 ORDER BY name')->fetchAll();
$residents = $pdo->query('SELECT resident_id, first_name, last_name, email FROM residents ORDER BY last_name, first_name')->fetchAll();
$csrfToken = csrf_token();
$todayQueueStmt = $pdo->prepare('SELECT last_number, current_number FROM service_queue_daily WHERE queue_date = CURRENT_DATE');
$todayQueueStmt->execute();
$todayQueue = $todayQueueStmt->fetch() ?: ['last_number' => 0, 'current_number' => null];
$queueWaitingCountStmt = $pdo->prepare('
    SELECT COUNT(*) FROM service_queue_entries q
    JOIN requests r ON r.request_id = q.request_id
    WHERE q.queue_date = CURRENT_DATE AND q.status = "waiting" AND r.status <> "cancelled"
');
$queueWaitingCountStmt->execute();
$queueWaitingCount = (int)$queueWaitingCountStmt->fetchColumn();

// counts per status, for the filter tabs
$countStmt = $pdo->query('SELECT status, COUNT(*) AS c FROM requests GROUP BY status');
$counts = ['all' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['c'];
    if ($row['status'] !== 'cancelled') {
        $counts['all'] += (int)$row['c'];
    }
}

$statusLabels = [
    'pending' => 'Pending', 'processing' => 'Processing', 'approved' => 'Approved',
    'rejected' => 'Rejected', 'ready_for_pickup' => 'Ready for pickup', 'released' => 'Released',
    'cancelled' => 'Cancelled'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Barangay Lawacamulag, San Jose, Tarlac</title>
<style nonce="<?= $csp_nonce ?>">
  :root{
    --paper:#EAF4FB; --paper-2:#E4F1E5; --ink:#173042; --ink-soft:#506A78;
    --blue:#1685C7; --blue-deep:#075184; --red:#9B2B22; --green:#23833B;
    --green-deep:#17632B; --gold:#E6B92E; --line:#B9D2DE;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;line-height:1.5;}
  .mono{font-family:'IBM Plex Mono',monospace;}
  a{color:inherit;}
  header{
    background:var(--green-deep);color:#fff;padding:16px 32px;
    display:flex;align-items:center;justify-content:space-between;
  }
  .brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
  .seal-mark{
    width:34px;height:34px;border-radius:50%;border:1.5px solid var(--gold);
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:12px;color:var(--gold);
    overflow:hidden;flex-shrink:0;
  }
  .seal-mark img{width:100%;height:100%;object-fit:cover;display:block;}
  header .name{font-weight:700;font-size:15px;letter-spacing:-.015em;}
  header .eyebrow{font-size:11px;color:#9FB1C9;}
  .staff-info{display:flex;align-items:center;gap:16px;font-size:13px;color:#C9D3E0;}
  .staff-info a{color:var(--gold);font-weight:500;}
  .wrap{max-width:1440px;margin:0 auto;padding:32px;}
  h1{font-size:26px;font-weight:700;letter-spacing:-.025em;color:var(--blue-deep);margin-bottom:6px;}
  .sub{color:var(--ink-soft);font-size:14px;margin-bottom:28px;}

  .filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
  .filter-pill{
    font-size:12.5px;padding:7px 14px;border:1px solid var(--line);border-radius:999px;
    background:#fff;color:var(--ink-soft);transition:border-color .15s,transform .15s;
  }
  .filter-pill.active{background:var(--green);color:#fff;border-color:var(--green);}
  .filter-pill:hover{border-color:var(--blue);transform:translateY(-1px);}

  .search-row{margin-bottom:22px;}
  .search-row input{
    width:100%;max-width:340px;padding:10px 13px;border:1px solid var(--line);
    background:#fff;font-family:inherit;font-size:13.5px;border-radius:2px;
    transition:border-color .15s,box-shadow .15s;
  }
  .search-row input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(30,58,95,0.12);}

  .requests-table-wrap{width:100%;overflow-x:auto;border:1px solid var(--line);background:#fff;overscroll-behavior-x:contain;}
  table{width:100%;min-width:1160px;border-collapse:collapse;background:#fff;}
  th{
    text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;
    color:var(--ink-soft);padding:12px 14px;border-bottom:1px solid var(--line);background:var(--paper-2);
  }
  td{padding:11px 10px;border-bottom:1px solid var(--line);font-size:13px;vertical-align:middle;}
  tbody tr{transition:background .12s;}
  tbody tr:hover{background:var(--paper-2);}
  tr:last-child td{border-bottom:none;}
  .ref{color:var(--blue);font-weight:500;}
  .on-behalf-tag{display:inline-block;font-size:11px;color:var(--gold);font-weight:600;margin-top:3px;}
  .status-badge{
    display:inline-block;font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;
    text-transform:uppercase;letter-spacing:0.03em;
  }
  .status-pending{background:#EAE3D2;color:var(--ink-soft);}
  .status-processing{background:#DCE7F0;color:var(--blue);}
  .status-approved{background:#DCE9DC;color:#2F6B33;}
  .status-rejected{background:#F5E3E0;color:var(--red);}
  .status-ready_for_pickup{background:#F1E4C4;color:#8A6A1E;}
  .status-released{background:var(--blue-deep);color:#fff;}
  .status-cancelled{background:#E5E0D3;color:var(--ink-soft);text-decoration:line-through;}
  select.status-select{
    font-family:inherit;font-size:12.5px;padding:6px 8px;border:1px solid var(--line);border-radius:2px;background:#fff;
    transition:border-color .15s;cursor:pointer;
  }
  select.status-select:hover,select.status-select:focus{border-color:var(--blue);outline:none;}
  .empty{padding:48px;text-align:center;color:var(--ink-soft);font-size:14px;}

  .view-trigger{color:var(--blue);font-family:inherit;font-size:inherit;font-weight:500;cursor:pointer;border:0;border-bottom:1px dotted var(--blue);padding:0;background:transparent;}
  .view-trigger:hover{color:var(--blue-deep);}
  .cert-link{color:var(--green-deep);font-weight:600;}
  .cert-link:hover{color:var(--blue-deep);}
  .identity-note{display:block;max-width:210px;margin-top:6px;font-size:11px;line-height:1.35;font-weight:600;}
  .identity-review{color:#70550A;}
  .identity-matched{color:var(--green-deep);}
  .crud-actions{display:flex;flex-wrap:wrap;gap:6px;min-width:145px;}
  .crud-button{font:inherit;font-size:12px;padding:6px 9px;border:1px solid var(--line);background:#fff;color:var(--blue-deep);cursor:pointer;border-radius:3px;}
  .crud-button:hover{border-color:var(--blue);background:var(--paper);}
  .crud-button.danger{color:var(--red);}
  .create-row{display:flex;justify-content:flex-end;margin:-4px 0 16px;}
  .queue-panel{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0 0 20px;padding:15px;background:#fff;border:1px solid var(--line);}
  .queue-current{margin-right:auto;color:var(--ink-soft);font-size:13px;}
  .queue-current strong{display:block;color:var(--green-deep);font:600 24px 'IBM Plex Mono',monospace;}
  .queue-controls{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
  .queue-controls input{width:110px;padding:7px;border:1px solid var(--line);font:inherit;}
  .crud-modal-overlay{
    display:none;position:fixed;inset:0;background:rgba(18,40,69,0.55);
    align-items:center;justify-content:center;z-index:220;padding:18px;
  }
  .crud-modal-overlay.open{display:flex;}
  .crud-modal-box{width:min(520px,100%);max-height:90vh;overflow:auto;background:#fff;border:1px solid var(--line);padding:24px;box-shadow:0 12px 32px rgba(0,0,0,0.25);}
  .crud-modal-box h2{font-weight:700;letter-spacing:-.02em;color:var(--blue-deep);margin-bottom:16px;}
  .crud-modal-box label{display:block;font-size:13px;font-weight:600;margin:0 0 13px;}
  .crud-modal-box input,.crud-modal-box select,.crud-modal-box textarea{display:block;width:100%;margin-top:5px;padding:10px;border:1px solid var(--line);font:inherit;font-size:14px;}
  .crud-modal-box textarea{min-height:96px;resize:vertical;}
  .crud-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;}
  .crud-notice{min-height:20px;color:var(--red);font-size:13px;margin-top:8px;}
  @media(max-width:900px){
    .wrap{padding:22px 16px;}
    header{padding:14px 16px;gap:12px;}
    .staff-info{gap:8px;flex-wrap:wrap;justify-content:flex-end;text-align:right;}
    .queue-panel{align-items:flex-start;}
    .queue-current{width:100%;margin:0;}
    .queue-controls{width:100%;}
    .requests-table-wrap{overflow:visible;border:0;background:transparent;}
    .requests-table-wrap table{min-width:0;display:block;background:transparent;border:0;}
    .requests-table-wrap thead{display:none;}
    .requests-table-wrap tbody{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
    .requests-table-wrap tbody tr{display:flex;flex-direction:column;min-width:0;padding:12px;background:#fff;border:1px solid var(--line);border-radius:6px;box-shadow:0 2px 8px rgba(23,48,66,.06);}
    .requests-table-wrap tbody tr:hover{background:#fff;}
    .requests-table-wrap td{display:grid;grid-template-columns:minmax(88px,35%) minmax(0,1fr);gap:10px;padding:9px 4px;border-bottom:1px solid #e5eef2;overflow-wrap:anywhere;}
    .requests-table-wrap td:last-child{border-bottom:0;}
    .requests-table-wrap td::before{content:attr(data-label);font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--ink-soft);}
    .requests-table-wrap td:first-child{font-size:14px;font-weight:600;}
    .requests-table-wrap .crud-actions{min-width:0;}
  }
  @media(max-width:600px){
    .wrap{padding:18px 12px;}
    header{align-items:flex-start;}
    header .eyebrow{max-width:190px;}
    .staff-info{font-size:11px;}
    h1{font-size:23px;}
    .sub{font-size:13px;margin-bottom:18px;}
    .queue-panel{padding:12px;}
    .queue-controls{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));}
    .queue-controls input{width:100%;min-height:38px;}
    .queue-controls .crud-button{min-height:38px;}
    .requests-table-wrap tbody{grid-template-columns:minmax(0,1fr);}
    .requests-table-wrap td{grid-template-columns:92px minmax(0,1fr);font-size:13px;}
    .filters{gap:6px;}
    .filter-pill{font-size:11.5px;padding:6px 10px;}
  }

  .attachment-modal-overlay{
    display:none;position:fixed;inset:0;background:rgba(18,40,69,0.55);
    align-items:center;justify-content:center;z-index:200;padding:24px;
  }
  .attachment-modal-overlay.open{display:flex;}
  .attachment-modal-box{
    background:#fff;border:1px solid var(--line);padding:22px;position:relative;
    max-width:90vw;max-height:85vh;overflow:auto;box-shadow:0 12px 32px rgba(0,0,0,0.25);
  }
  .attachment-modal-close{
    position:absolute;top:6px;right:10px;background:none;border:none;font-size:24px;
    cursor:pointer;color:var(--ink-soft);line-height:1;padding:4px;
  }
  .attachment-modal-close:hover{color:var(--red);}
  .attachment-modal-box img{display:block;max-width:100%;max-height:75vh;margin-top:6px;}
  .pdf-preview-large{padding:50px 46px;text-align:center;color:var(--ink-soft);font-size:14px;}
  .pdf-preview-large a{color:var(--blue);font-weight:500;}
</style>
</head>
<body>

<header>
  <a class="brand" href="<?= htmlspecialchars(app_asset_url('obrs.php'), ENT_QUOTES) ?>" aria-label="Return to homepage">
    <div class="seal-mark"><img src="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>" alt="Barangay seal"></div>
    <div>
      <div class="eyebrow">Barangay Lawacamulag, San Jose, Tarlac</div>
      <div class="name">Staff Dashboard</div>
    </div>
  </a>
  <div class="staff-info">
    <span>Logged in as <strong><?= htmlspecialchars($_SESSION['staff_name']) ?></strong> (<?= htmlspecialchars($_SESSION['staff_role']) ?>)</span>
    <a href="logout.php">Log out</a>
  </div>
</header>

<div class="wrap">
  <h1>Document requests</h1>
  <p class="sub">Manage document requests and today’s service queue. Cancelled requests remain in the Cancelled filter; only administrators can permanently delete them.</p>

  <div class="create-row">
    <button type="button" class="crud-button" id="createRequestButton">Create request</button>
  </div>

  <section class="queue-panel" aria-label="Service queue controls">
    <div class="queue-current">
      Now serving
      <strong id="dashboardQueueCurrent"><?= $todayQueue['current_number'] !== null ? htmlspecialchars(str_pad((string)$todayQueue['current_number'], 4, '0', STR_PAD_LEFT)) : '—' ?></strong>
      <span><span id="dashboardQueueWaiting"><?= $queueWaitingCount ?></span> waiting · <span id="dashboardQueueLast"><?= (int)$todayQueue['last_number'] ?></span> tickets assigned today</span>
    </div>
    <div class="queue-controls">
      <button type="button" class="crud-button" data-queue-action="next">Call next</button>
      <button type="button" class="crud-button" data-queue-action="repeat">Repeat</button>
      <input type="number" id="manualQueueNumber" min="1" placeholder="Queue no." aria-label="Queue number to call">
      <button type="button" class="crud-button" data-queue-action="set">Call number</button>
      <button type="button" class="crud-button" data-queue-action="served">Mark served</button>
    </div>
    <p class="crud-notice" id="queueNotice" aria-live="polite"></p>
  </section>

  <div class="filters">
    <a class="filter-pill <?= $statusFilter==='all'?'active':'' ?>" href="?status=all">All (<?= $counts['all'] ?? 0 ?>)</a>
    <?php foreach ($filterStatuses as $s): ?>
      <a class="filter-pill <?= $statusFilter===$s?'active':'' ?>" href="?status=<?= $s ?>">
        <?= $statusLabels[$s] ?> (<?= $counts[$s] ?? 0 ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <form class="search-row" method="get">
    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
    <input type="text" name="q" placeholder="Search by name or reference number…" value="<?= htmlspecialchars($search) ?>">
  </form>

  <?php if (empty($requests)): ?>
    <div class="empty">No requests match this view.</div>
  <?php else: ?>
    <div class="requests-table-wrap" role="region" aria-label="Document request records" tabindex="0">
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Queue ticket</th>
          <th>Requested by</th>
          <th>Document</th>
          <th>Purpose</th>
          <th>Attachment</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Certificate</th>
          <th>Update</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $downloadableStatuses = ['approved', 'ready_for_pickup', 'released'];
        foreach ($requests as $r): ?>
          <tr data-request-id="<?= $r['request_id'] ?>">
            <td class="ref mono" data-label="Reference"><?= htmlspecialchars($r['reference_number']) ?></td>
            <td class="mono" data-label="Queue ticket">
              <?php if ($r['queue_number'] !== null): ?>
                <?= htmlspecialchars(str_pad((string)$r['queue_number'], 4, '0', STR_PAD_LEFT)) ?>
                <small class="identity-note"><?= htmlspecialchars(ucfirst($r['queue_status'] ?? 'waiting')) ?></small>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td data-label="Requested by">
              <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?><br>
              <span style="color:var(--ink-soft);font-size:12px;"><?= htmlspecialchars($r['email']) ?></span>
              <?php if ($r['on_behalf_of']): ?>
                <br><span class="on-behalf-tag">For: <?= htmlspecialchars($r['beneficiary_name']) ?> (<?= htmlspecialchars($r['relationship']) ?>)</span>
              <?php endif; ?>
            </td>
            <td data-label="Document"><?= htmlspecialchars($r['document_name']) ?></td>
            <td data-label="Purpose"><?= htmlspecialchars($r['purpose']) ?></td>
            <td data-label="Attachment">
              <?php if ($r['attachment_path']): ?>
                <?php $ext = strtolower(pathinfo($r['attachment_path'], PATHINFO_EXTENSION)); ?>
                  <button type="button" class="view-trigger" data-path="<?= htmlspecialchars($r['attachment_path'], ENT_QUOTES) ?>" data-ext="<?= $ext ?>">Review ID</button>
                  <?php if (in_array($r['attachment_label'], [
                    'ID document pending staff identity review',
                    'Valid ID (staff identity review required)',
                  ], true)): ?>
                    <small class="identity-note identity-review">Staff identity review required</small>
                  <?php elseif ($r['attachment_label'] === 'Staff-created request (ID authenticity review required)'): ?>
                    <small class="identity-note identity-review">Staff-entered request; verify ID</small>
                  <?php elseif ($r['attachment_label']): ?>
                    <small class="identity-note identity-matched">Face comparison matched; verify ID authenticity</small>
                  <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
            <td class="mono" data-label="Submitted" style="font-size:12px;"><?= date('M j, Y g:ia', strtotime($r['requested_at'])) ?></td>
            <td data-label="Status"><span class="status-badge status-<?= $r['status'] ?>"><?= $statusLabels[$r['status']] ?></span></td>
            <td data-label="Certificate">
              <?php if (in_array($r['status'], $downloadableStatuses, true)): ?>
                <a href="generate_certificate.php?ref=<?= urlencode($r['reference_number']) ?>" class="cert-link">Preview</a>
              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
            <td data-label="Update">
              <?php if (!in_array($r['status'], ['cancelled', 'released'], true)): ?>
              <select class="status-select">
                <option value="">Change to…</option>
                <?php foreach ($statuses as $s): ?>
                  <?php if ($s !== $r['status']): ?>
                    <option value="<?= $s ?>"><?= $statusLabels[$s] ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
            <td data-label="Actions">
              <div class="crud-actions">
                <?php if (!in_array($r['status'], ['cancelled', 'released'], true)): ?>
                  <button type="button" class="crud-button edit-request"
                    data-request-id="<?= (int)$r['request_id'] ?>"
                    data-document-type-id="<?= (int)$r['document_type_id'] ?>"
                    data-purpose="<?= htmlspecialchars($r['purpose'], ENT_QUOTES) ?>"
                    data-reference="<?= htmlspecialchars($r['reference_number'], ENT_QUOTES) ?>">Edit</button>
                  <button type="button" class="crud-button danger cancel-request"
                    data-request-id="<?= (int)$r['request_id'] ?>"
                    data-reference="<?= htmlspecialchars($r['reference_number'], ENT_QUOTES) ?>"
                    title="Cancel this request and preserve its history">Cancel</button>
                <?php elseif ($r['status'] === 'cancelled' && $isAdmin): ?>
                  <button type="button" class="crud-button danger permanently-delete-request"
                    data-request-id="<?= (int)$r['request_id'] ?>"
                    data-reference="<?= htmlspecialchars($r['reference_number'], ENT_QUOTES) ?>"
                    title="Permanently delete this cancelled request and its ID attachments">Delete permanently</button>
                <?php else: ?>
                  <span style="color:var(--ink-soft);">—</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="crud-modal-overlay" id="createRequestModal" role="dialog" aria-modal="true" aria-labelledby="createRequestTitle">
  <div class="crud-modal-box">
    <h2 id="createRequestTitle">Create request</h2>
    <form id="createRequestForm" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <label>Resident
        <select name="resident_id" required>
          <option value="">Select a resident</option>
          <?php foreach ($residents as $resident): ?>
            <option value="<?= (int)$resident['resident_id'] ?>"><?= htmlspecialchars($resident['last_name'] . ', ' . $resident['first_name'] . ' — ' . $resident['email']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Document type
        <select name="document_type_id" required>
          <option value="">Select a document</option>
          <?php foreach ($documentTypes as $documentType): ?>
            <option value="<?= (int)$documentType['document_type_id'] ?>"><?= htmlspecialchars($documentType['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Purpose
        <textarea name="purpose" maxlength="255" required></textarea>
      </label>
      <label>Resident's government-issued ID (JPG/PNG, max 5MB)
        <input type="file" name="attachment" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required>
      </label>
      <p class="sub">Staff-created requests are marked for ID authenticity review. No selfie is collected or stored.</p>
      <p class="crud-notice" aria-live="polite"></p>
      <div class="crud-modal-actions">
        <button type="button" class="crud-button close-crud-modal">Close</button>
        <button type="submit" class="crud-button">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="crud-modal-overlay" id="editRequestModal" role="dialog" aria-modal="true" aria-labelledby="editRequestTitle">
  <div class="crud-modal-box">
    <h2 id="editRequestTitle">Edit request</h2>
    <form id="editRequestForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
      <input type="hidden" name="request_id">
      <p class="sub" id="editRequestReference"></p>
      <label>Document type
        <select name="document_type_id" required>
          <?php foreach ($documentTypes as $documentType): ?>
            <option value="<?= (int)$documentType['document_type_id'] ?>"><?= htmlspecialchars($documentType['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Purpose
        <textarea name="purpose" maxlength="255" required></textarea>
      </label>
      <p class="crud-notice" aria-live="polite"></p>
      <div class="crud-modal-actions">
        <button type="button" class="crud-button close-crud-modal">Close</button>
        <button type="submit" class="crud-button">Save changes</button>
      </div>
    </form>
  </div>
</div>

<div class="attachment-modal-overlay" id="attachmentModal">
  <div class="attachment-modal-box">
    <button class="attachment-modal-close" aria-label="Close">&times;</button>
    <div id="attachmentModalContent"></div>
  </div>
</div>

<script nonce="<?= $csp_nonce ?>">
const csrfToken = <?= json_encode($csrfToken) ?>;
async function refreshQueueStatus() {
  try {
    const response = await fetch('get_queue_status.php');
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Queue status is unavailable.');
    document.getElementById('dashboardQueueCurrent').textContent = result.current_number === null
      ? '—'
      : String(result.current_number).padStart(4, '0');
    document.getElementById('dashboardQueueWaiting').textContent = result.waiting_count;
    document.getElementById('dashboardQueueLast').textContent = result.last_number;
  } catch (error) {
    document.getElementById('queueNotice').textContent = error.message || 'Queue status is unavailable.';
  }
}
document.querySelectorAll('[data-queue-action]').forEach(button => {
  button.addEventListener('click', async () => {
    const notice = document.getElementById('queueNotice');
    const queueNumber = document.getElementById('manualQueueNumber').value;
    if (button.dataset.queueAction === 'set' && !queueNumber) {
      notice.textContent = 'Enter the queue number you want to call.';
      return;
    }
    button.disabled = true;
    notice.textContent = '';
    try {
      const response = await fetch('manage_queue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          csrf_token: csrfToken,
          action: button.dataset.queueAction,
          queue_number: queueNumber
        })
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || 'Could not update the queue.');
      notice.textContent = result.message;
      document.getElementById('manualQueueNumber').value = '';
      await refreshQueueStatus();
    } catch (error) {
      notice.textContent = error.message || 'Could not reach the server.';
    } finally {
      button.disabled = false;
    }
  });
});
refreshQueueStatus();
window.setInterval(refreshQueueStatus, 10000);

function closeCrudModals() {
  document.querySelectorAll('.crud-modal-overlay').forEach(modal => modal.classList.remove('open'));
}
function showCrudNotice(form, message) {
  form.querySelector('.crud-notice').textContent = message;
}
async function submitCrudForm(form, endpoint) {
  const submitButton = form.querySelector('button[type="submit"]');
  submitButton.disabled = true;
  showCrudNotice(form, '');
  try {
    const response = await fetch(endpoint, {method: 'POST', body: new FormData(form)});
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'The request could not be saved.');
    location.reload();
  } catch (error) {
    showCrudNotice(form, error.message || 'Could not reach the server.');
    submitButton.disabled = false;
  }
}
document.getElementById('createRequestButton').addEventListener('click', () => {
  const form = document.getElementById('createRequestForm');
  form.reset();
  showCrudNotice(form, '');
  document.getElementById('createRequestModal').classList.add('open');
});
document.getElementById('createRequestForm').addEventListener('submit', event => {
  event.preventDefault();
  submitCrudForm(event.currentTarget, 'staff_create_request.php');
});
document.getElementById('editRequestForm').addEventListener('submit', event => {
  event.preventDefault();
  submitCrudForm(event.currentTarget, 'staff_update_request.php');
});
document.addEventListener('click', async event => {
  const editButton = event.target.closest('.edit-request');
  if (editButton) {
    const form = document.getElementById('editRequestForm');
    form.elements.request_id.value = editButton.dataset.requestId;
    form.elements.document_type_id.value = editButton.dataset.documentTypeId;
    form.elements.purpose.value = editButton.dataset.purpose;
    document.getElementById('editRequestReference').textContent = editButton.dataset.reference;
    showCrudNotice(form, '');
    document.getElementById('editRequestModal').classList.add('open');
    return;
  }
  if (event.target.closest('.close-crud-modal')) {
    closeCrudModals();
    return;
  }
  if (event.target.classList.contains('crud-modal-overlay')) {
    closeCrudModals();
    return;
  }
  const deleteButton = event.target.closest('.cancel-request');
  if (deleteButton) {
    if (!confirm(`Cancel request ${deleteButton.dataset.reference}? The record and ID attachment will be retained for the audit trail.`)) return;
    deleteButton.disabled = true;
    try {
      const response = await fetch('staff_delete_request.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({csrf_token: csrfToken, request_id: deleteButton.dataset.requestId})
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || 'The request could not be cancelled.');
      location.reload();
    } catch (error) {
      alert(error.message || 'Could not reach the server.');
      deleteButton.disabled = false;
    }
    return;
  }

  const permanentDeleteButton = event.target.closest('.permanently-delete-request');
  if (!permanentDeleteButton) return;
  const deletionConfirmed = confirm(
    `Permanently delete cancelled request ${permanentDeleteButton.dataset.reference} and all its ID attachments? This cannot be undone and removes its audit history.`
  );
  if (!deletionConfirmed) return;
  permanentDeleteButton.disabled = true;
  try {
    const response = await fetch('staff_permanently_delete_request.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({csrf_token: csrfToken, request_id: permanentDeleteButton.dataset.requestId})
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'The request could not be permanently deleted.');
    location.reload();
  } catch (error) {
    alert(error.message || 'Could not reach the server.');
    permanentDeleteButton.disabled = false;
  }
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeCrudModals();
});

function openAttachmentModal(path, ext) {
  const content = document.getElementById('attachmentModalContent');
  if (['jpg','jpeg','png'].includes(ext)) {
    content.innerHTML = '<img src="' + path + '" alt="Attachment">';
  } else {
    content.innerHTML = '<div class="pdf-preview-large">📄 PDF document<br><br><a href="' + path + '" target="_blank">Open in new tab</a></div>';
  }
  document.getElementById('attachmentModal').classList.add('open');
}
function closeAttachmentModal() {
  document.getElementById('attachmentModal').classList.remove('open');
}
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAttachmentModal(); });

async function updateStatus(requestId, newStatus, selectEl) {
  if (!newStatus) return;
  selectEl.disabled = true;
  try {
    const res = await fetch('update_status.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({request_id: requestId, status: newStatus})
    });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Could not update status.');
      selectEl.disabled = false;
    }
  } catch (err) {
    alert('Could not reach the server.');
    selectEl.disabled = false;
  }
}
document.addEventListener('click', function (e) {
  const v = e.target.closest('.view-trigger');
  if (v) { openAttachmentModal(v.dataset.path, v.dataset.ext); return; }
  if (e.target.closest('.attachment-modal-close')) { closeAttachmentModal(); return; }
  if (e.target.id === 'attachmentModal') closeAttachmentModal();
});

document.addEventListener('change', function (e) {
  if (!(e.target instanceof HTMLSelectElement)) return;
  const selectEl = e.target.closest('.status-select');
  if (!selectEl) return;

  const requestId = selectEl.closest('tr')?.dataset.requestId;
  if (requestId) updateStatus(requestId, selectEl.value, selectEl);
});

</script>

</body>
</html>