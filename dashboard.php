<?php
require __DIR__ . '/admin_auth.php';
require __DIR__ . '/config.php';

$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = '
    SELECT r.request_id, r.reference_number, r.status, r.purpose, r.requested_at,
           r.on_behalf_of, r.beneficiary_name, r.relationship,
           dt.name AS document_name,
           res.first_name, res.last_name, res.email, res.contact_number,
           ra.file_path AS attachment_path
    FROM requests r
    JOIN document_types dt ON dt.document_type_id = r.document_type_id
    JOIN residents res ON res.resident_id = r.resident_id
    LEFT JOIN (
        SELECT request_id, MIN(attachment_id) AS first_attachment_id
        FROM request_attachments
        GROUP BY request_id
    ) fa ON fa.request_id = r.request_id
    LEFT JOIN request_attachments ra ON ra.attachment_id = fa.first_attachment_id
    WHERE 1=1
';
$params = [];

if ($statusFilter !== 'all') {
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

// counts per status, for the filter tabs
$countStmt = $pdo->query('SELECT status, COUNT(*) AS c FROM requests GROUP BY status');
$counts = ['all' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

$statuses = ['pending', 'processing', 'approved', 'rejected', 'ready_for_pickup', 'released'];
$filterStatuses = array_merge($statuses, ['cancelled']); // filter pills also show cancelled requests
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F3EEE1; --paper-2:#EAE3D2; --ink:#211D16; --ink-soft:#5B564A;
    --blue:#1E3A5F; --blue-deep:#122845; --red:#9B2B22; --gold:#B08D3E; --line:#C9C1AC;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:'Public Sans',sans-serif;line-height:1.5;}
  .mono{font-family:'IBM Plex Mono',monospace;}
  a{color:inherit;}
  header{
    background:var(--blue-deep);color:#fff;padding:16px 32px;
    display:flex;align-items:center;justify-content:space-between;
  }
  .brand{display:flex;align-items:center;gap:12px;}
  .seal-mark{
    width:34px;height:34px;border-radius:50%;border:1.5px solid var(--gold);
    display:flex;align-items:center;justify-content:center;
    font-family:'Fraunces',serif;font-weight:600;font-size:12px;color:var(--gold);
    overflow:hidden;flex-shrink:0;
  }
  .seal-mark img{width:100%;height:100%;object-fit:cover;display:block;}
  header .name{font-family:'Fraunces',serif;font-weight:600;font-size:15px;}
  header .eyebrow{font-size:11px;color:#9FB1C9;}
  .staff-info{display:flex;align-items:center;gap:16px;font-size:13px;color:#C9D3E0;}
  .staff-info a{color:var(--gold);font-weight:500;}
  .wrap{max-width:1200px;margin:0 auto;padding:32px;}
  h1{font-family:'Fraunces',serif;font-size:26px;color:var(--blue-deep);margin-bottom:6px;}
  .sub{color:var(--ink-soft);font-size:14px;margin-bottom:28px;}

  .filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
  .filter-pill{
    font-size:12.5px;padding:7px 14px;border:1px solid var(--line);border-radius:999px;
    background:#FBF9F2;color:var(--ink-soft);transition:border-color .15s,transform .15s;
  }
  .filter-pill.active{background:var(--blue);color:#fff;border-color:var(--blue);}
  .filter-pill:hover{border-color:var(--blue);transform:translateY(-1px);}

  .search-row{margin-bottom:22px;}
  .search-row input{
    width:100%;max-width:340px;padding:10px 13px;border:1px solid var(--line);
    background:#fff;font-family:inherit;font-size:13.5px;border-radius:2px;
    transition:border-color .15s,box-shadow .15s;
  }
  .search-row input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(30,58,95,0.12);}

  table{width:100%;border-collapse:collapse;background:#FBF9F2;border:1px solid var(--line);}
  th{
    text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;
    color:var(--ink-soft);padding:12px 14px;border-bottom:1px solid var(--line);background:var(--paper-2);
  }
  td{padding:13px 14px;border-bottom:1px solid var(--line);font-size:13.5px;vertical-align:middle;}
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

  .view-trigger{color:var(--blue);font-weight:500;cursor:pointer;border-bottom:1px dotted var(--blue);}
  .view-trigger:hover{color:var(--blue-deep);}
  .cert-link{color:var(--gold);font-weight:600;}
  .cert-link:hover{color:var(--blue-deep);}

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
  <div class="brand">
    <div class="seal-mark"><img src="logo.jpg" alt="Barangay seal"></div>
    <div>
      <div class="eyebrow">Barangay Lawacamulag, San Jose, Tarlac</div>
      <div class="name">Staff Dashboard</div>
    </div>
  </div>
  <div class="staff-info">
    <span>Logged in as <strong><?= htmlspecialchars($_SESSION['staff_name']) ?></strong> (<?= htmlspecialchars($_SESSION['staff_role']) ?>)</span>
    <a href="logout.php">Log out</a>
  </div>
</header>

<div class="wrap">
  <h1>Document requests</h1>
  <p class="sub">Review incoming requests and update their status as they move through the office.</p>

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
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Requested by</th>
          <th>Document</th>
          <th>Purpose</th>
          <th>Attachment</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Certificate</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $downloadableStatuses = ['approved', 'ready_for_pickup', 'released'];
        foreach ($requests as $r): ?>
          <tr data-request-id="<?= $r['request_id'] ?>">
            <td class="ref mono"><?= htmlspecialchars($r['reference_number']) ?></td>
            <td>
              <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?><br>
              <span style="color:var(--ink-soft);font-size:12px;"><?= htmlspecialchars($r['email']) ?></span>
              <?php if ($r['on_behalf_of']): ?>
                <br><span class="on-behalf-tag">For: <?= htmlspecialchars($r['beneficiary_name']) ?> (<?= htmlspecialchars($r['relationship']) ?>)</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['document_name']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td>
              <?php if ($r['attachment_path']): ?>
                <?php $ext = strtolower(pathinfo($r['attachment_path'], PATHINFO_EXTENSION)); ?>
                <span class="view-trigger" onclick="openAttachmentModal('<?= htmlspecialchars($r['attachment_path'], ENT_QUOTES) ?>', '<?= $ext ?>')">View</span>
              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
            <td class="mono" style="font-size:12px;"><?= date('M j, Y g:ia', strtotime($r['requested_at'])) ?></td>
            <td><span class="status-badge status-<?= $r['status'] ?>"><?= $statusLabels[$r['status']] ?></span></td>
            <td>
              <?php if (in_array($r['status'], $downloadableStatuses, true)): ?>
                <a href="generate_certificate.php?ref=<?= urlencode($r['reference_number']) ?>" class="cert-link">Preview</a>
              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status'] !== 'cancelled'): ?>
              <select class="status-select" onchange="updateStatus(<?= $r['request_id'] ?>, this.value, this)">
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
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="attachment-modal-overlay" id="attachmentModal" onclick="closeAttachmentModal()">
  <div class="attachment-modal-box" onclick="event.stopPropagation()">
    <button class="attachment-modal-close" onclick="closeAttachmentModal()" aria-label="Close">&times;</button>
    <div id="attachmentModalContent"></div>
  </div>
</div>

<script>
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
      body: `request_id=${requestId}&status=${newStatus}`
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
</script>

</body>
</html>