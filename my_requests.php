<?php
require __DIR__ . '/resident_auth.php';

$stmt = $pdo->prepare('
    SELECT r.reference_number, r.status, r.purpose, r.requested_at, r.updated_at,
           r.on_behalf_of, r.beneficiary_name, r.relationship,
           dt.name AS document_name,
           q.queue_number, q.status AS queue_status,
           ra.attachment_id, ra.file_path AS attachment_path, ra.file_label AS attachment_label
    FROM requests r
    JOIN document_types dt ON dt.document_type_id = r.document_type_id
    LEFT JOIN service_queue_entries q ON q.request_id = r.request_id
    LEFT JOIN (
        SELECT request_id, MIN(attachment_id) AS first_attachment_id
        FROM request_attachments
        GROUP BY request_id
    ) fa ON fa.request_id = r.request_id
    LEFT JOIN request_attachments ra ON ra.attachment_id = fa.first_attachment_id
    WHERE r.resident_id = ?
    ORDER BY r.requested_at DESC
');
$stmt->execute([$_SESSION['resident_id']]);
$requests = $stmt->fetchAll();

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
<link rel="icon" type="image/jpeg" href="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>">
<title>My requests — Barangay Lawacamulag, San Jose, Tarlac</title>
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
  header{background:var(--green-deep);color:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(230,185,46,.45);}
  .brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
   .seal-mark{
    width:34px;height:34px;border-radius:50%;border:1.5px solid var(--gold);
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:12px;color:var(--gold);
    overflow:hidden;flex-shrink:0;
  }
  .seal-mark img{width:100%;height:100%;object-fit:cover;display:block;}
  header .name{font-weight:700;font-size:15px;letter-spacing:-.015em;}
  header .eyebrow{font-size:11px;color:#D7E8DA;}
  .staff-info{display:flex;align-items:center;gap:16px;font-size:13px;color:#C9D3E0;}
  .staff-info a{color:var(--gold);font-weight:500;}
  .wrap{max-width:900px;margin:0 auto;padding:32px;}
  h1{font-size:26px;font-weight:700;letter-spacing:-.025em;color:var(--blue-deep);margin-bottom:6px;}
  .sub{color:var(--ink-soft);font-size:14px;margin-bottom:26px;}
  .top-links{display:flex;align-items:center;gap:18px;font-size:13px;color:#C9D3E0;}
  .top-links a{color:var(--gold);font-weight:500;}
  .notif-wrap{position:relative;}
  .notif-bell{position:relative;background:none;border:0;color:#fff;font-size:20px;cursor:pointer;padding:6px;}
  .notif-badge{position:absolute;top:0;right:0;min-width:16px;height:16px;padding:0 3px;border-radius:999px;background:var(--green);color:#fff;font-size:10px;font-weight:600;display:flex;align-items:center;justify-content:center;}
  .notif-badge[hidden]{display:none;}
  .notif-dropdown{display:none;position:absolute;top:calc(100% + 10px);right:0;width:min(320px,calc(100vw - 40px));background:#fff;border:1px solid var(--line);box-shadow:0 10px 28px rgba(0,0,0,.15);z-index:80;color:var(--ink);}
  .notif-dropdown.open{display:block;}
  .notif-header{padding:12px 16px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-soft);border-bottom:1px solid var(--line);background:var(--paper-2);}
  .notif-list{max-height:320px;overflow-y:auto;}
  .notif-item{padding:12px 16px;font-size:13px;border-bottom:1px solid var(--line);}
  .notif-item:last-child{border-bottom:0;}
  .notif-item.unread{background:#EAF4FB;}
  .notif-time{font-size:11px;color:var(--ink-soft);margin-top:3px;}
  .notif-empty{padding:24px 16px;text-align:center;font-size:13px;color:var(--ink-soft);}
  .btn-primary{display:inline-block;background:var(--green);color:#fff;padding:11px 20px;border-radius:2px;font-size:14px;font-weight:500;margin-bottom:26px;transition:background .15s,transform .15s,box-shadow .15s;}
  .btn-primary:hover{background:var(--green-deep);transform:translateY(-2px);box-shadow:0 6px 14px rgba(35,131,59,0.25);}
  .btn-primary:active{transform:translateY(0);box-shadow:none;}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);}
  th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:var(--ink-soft);padding:12px 14px;border-bottom:1px solid var(--line);background:var(--paper-2);}
  td{padding:13px 14px;border-bottom:1px solid var(--line);font-size:13.5px;}
  tbody tr{transition:background .12s;}
  tbody tr:hover{background:var(--paper-2);}
  tr:last-child td{border-bottom:none;}
  .ref{color:var(--blue);font-weight:500;}
  .on-behalf-tag{display:inline-block;font-size:11px;color:var(--gold);font-weight:600;margin-top:3px;}
  .status-badge{display:inline-block;font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:0.03em;}
  .status-pending{background:#E4F1E5;color:var(--ink-soft);}
  .status-processing{background:#DCEEF8;color:var(--blue-deep);}
  .status-approved{background:#DCEBDD;color:var(--green-deep);}
  .status-rejected{background:#F5E3E0;color:var(--red);}
  .status-ready_for_pickup{background:#F8EAB7;color:#70550A;}
  .status-released{background:var(--green-deep);color:#fff;}
  .status-cancelled{background:#E4F1E5;color:var(--ink-soft);text-decoration:line-through;}
  .queue-ticket-status{display:block;margin-top:3px;font-size:11px;color:var(--ink-soft);}
  .empty{padding:48px;text-align:center;color:var(--ink-soft);font-size:14px;background:#fff;border:1px solid var(--line);}

  .view-trigger{color:var(--blue);font-weight:500;cursor:pointer;border-bottom:1px dotted var(--blue);}
  .view-trigger:hover{color:var(--blue-deep);}
  .cert-link{color:var(--green-deep);font-weight:600;}
  .identity-note{display:block;max-width:210px;margin-top:6px;font-size:11px;line-height:1.35;font-weight:600;}
  .identity-review{color:#70550A;}
  .identity-matched{color:var(--green-deep);}
  .cert-link:hover{color:var(--blue-deep);}
  .cancel-trigger{color:var(--red);font-weight:500;cursor:pointer;border-bottom:1px dotted var(--red);}
  .cancel-trigger:hover{color:#7f2019;}

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
  @media (max-width:760px){
    header{padding:12px 16px;align-items:flex-start;gap:12px;}
    .top-links{flex-wrap:wrap;justify-content:flex-end;gap:10px;}
    .wrap{padding:24px 16px;}
    table{display:block;overflow-x:auto;white-space:nowrap;}
  }
</style>
</head>
<body>

<header>
  <a class="brand" href="<?= htmlspecialchars(app_asset_url('obrs.php'), ENT_QUOTES) ?>" aria-label="Return to homepage">
 <div class="seal-mark"><img src="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>" alt="Barangay seal"></div>
    <div>
      <div class="eyebrow">Barangay Lawacamulag, San Jose, Tarlac</div>
      <div class="name">My Account</div>
    </div>
  </a>
  <div class="top-links">
    <span>Hi, <?= htmlspecialchars($_SESSION['resident_name']) ?></span>
    <div class="notif-wrap">
      <button class="notif-bell" id="notifBell" type="button" aria-label="Notifications" aria-expanded="false">
        &#128276;<span class="notif-badge" id="notifBadge" hidden>0</span>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">Notifications</div>
        <div id="notifList" class="notif-list" aria-live="polite"></div>
      </div>
    </div>
    <a href="obrs.php">Home</a>
    <a href="update_information.php">Update Information</a>
    <a href="resident_logout.php">Log out</a>
  </div>
</header>

<div class="wrap">
  <h1>My requests</h1>
  <p class="sub">Request status tracks document review. Queue status separately shows whether your service ticket is waiting or being called.</p>
  <a href="obrs.php#services" class="btn-primary">New request</a>

  <?php if (empty($requests)): ?>
    <div class="empty">You haven't submitted any requests yet.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr><th>Reference</th><th>Queue ticket</th><th>Document</th><th>Purpose</th><th>Attachment</th><th>Submitted</th><th>Request status</th><th>Certificate</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php
        $downloadableStatuses = ['approved', 'ready_for_pickup', 'released'];
        foreach ($requests as $r): ?>
          <tr data-reference="<?= htmlspecialchars($r['reference_number'], ENT_QUOTES) ?>">
            <td class="ref mono"><?= htmlspecialchars($r['reference_number']) ?></td>
            <td class="mono">
              <?php if ($r['queue_number'] !== null): ?>
                <?= htmlspecialchars(str_pad((string)$r['queue_number'], 4, '0', STR_PAD_LEFT)) ?>
                <span class="queue-ticket-status"><?= htmlspecialchars(ucfirst($r['queue_status'])) ?></span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <?= htmlspecialchars($r['document_name']) ?>
              <?php if ($r['on_behalf_of']): ?>
                <br><span class="on-behalf-tag">For: <?= htmlspecialchars($r['beneficiary_name']) ?> (<?= htmlspecialchars($r['relationship']) ?>)</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td>
              <?php if ($r['attachment_path']): ?>
                <?php $ext = strtolower(pathinfo($r['attachment_path'], PATHINFO_EXTENSION)); ?>
                <span class="view-trigger" data-attachment-id="<?= (int)$r['attachment_id'] ?>" data-ext="<?= htmlspecialchars($ext, ENT_QUOTES) ?>">View</span>
                <?php if ($r['attachment_label'] === 'Valid ID (staff identity review required)'): ?>
                  <small class="identity-note identity-review">Staff identity review required</small>
                <?php elseif ($r['attachment_label']): ?>
                  <small class="identity-note identity-matched">Face comparison matched; staff review required</small>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
            <td class="mono" style="font-size:12px;"><?= date('M j, Y', strtotime($r['requested_at'])) ?></td>
            <td data-status-cell><span class="status-badge status-<?= $r['status'] ?>"><?= $statusLabels[$r['status']] ?></span></td>
            <td data-certificate-cell>
              <?php if (in_array($r['status'], $downloadableStatuses, true)): ?>
                <a href="generate_certificate.php?ref=<?= urlencode($r['reference_number']) ?>" class="cert-link">Download</a>
              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
            <td data-action-cell>
              <?php if ($r['status'] === 'pending'): ?>
<span class="cancel-trigger" data-ref="<?= htmlspecialchars($r['reference_number'], ENT_QUOTES) ?>">Cancel</span>              <?php else: ?>
                <span style="color:var(--ink-soft);">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<div class="attachment-modal-overlay" id="attachmentModal">
  <div class="attachment-modal-box">
    <button class="attachment-modal-close" aria-label="Close">&times;</button>
    <div id="attachmentModalContent"></div>
  </div>
</div>

<script nonce="<?= $csp_nonce ?>">
  
function openAttachmentModal(attachmentId, ext) {
  const content = document.getElementById('attachmentModalContent');
  if (['jpg','jpeg','png'].includes(ext)) {
    const path = 'view_attachment.php?id=' + encodeURIComponent(attachmentId);
    content.innerHTML = '<img src="' + path + '" alt="Attachment">';
  }
  document.getElementById('attachmentModal').classList.add('open');
}
function closeAttachmentModal() {
  document.getElementById('attachmentModal').classList.remove('open');
}
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAttachmentModal(); });

async function cancelRequest(ref) {
  if (!confirm('Cancel this request? This can\'t be undone, and you\'ll need to submit a new request if you change your mind.')) {
    return;
  }
  try {
    const res = await fetch('cancel_request.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'reference_number=' + encodeURIComponent(ref)
    });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Could not cancel this request.');
    }
  } catch (err) {
    alert('Could not reach the server. Please try again.');
  }
}
document.addEventListener('click', function (e) {
  const v = e.target.closest('.view-trigger');
  if (v) { openAttachmentModal(v.dataset.attachmentId, v.dataset.ext); return; }
  const c = e.target.closest('.cancel-trigger');
  if (c) { cancelRequest(c.dataset.ref); return; }
  if (e.target.closest('.attachment-modal-close')) { closeAttachmentModal(); return; }
  if (e.target.id === 'attachmentModal') closeAttachmentModal();
});

const notifBell = document.getElementById('notifBell');
const notifDropdown = document.getElementById('notifDropdown');
const notifBadge = document.getElementById('notifBadge');
const notifList = document.getElementById('notifList');
const requestStatusLabels = {
  pending: 'Pending',
  processing: 'Processing',
  approved: 'Approved',
  rejected: 'Rejected',
  ready_for_pickup: 'Ready for pickup',
  released: 'Released',
  cancelled: 'Cancelled'
};

function updateRequestRow(notification) {
  if (!notification.reference_number || !requestStatusLabels[notification.status]) return;
  const row = Array.from(document.querySelectorAll('tr[data-reference]'))
    .find(candidate => candidate.dataset.reference === notification.reference_number);
  if (!row) return;

  const statusCell = row.querySelector('[data-status-cell]');
  const badge = document.createElement('span');
  badge.className = 'status-badge status-' + notification.status;
  badge.textContent = requestStatusLabels[notification.status];
  statusCell.replaceChildren(badge);

  const certificateCell = row.querySelector('[data-certificate-cell]');
  const actionCell = row.querySelector('[data-action-cell]');
  certificateCell.replaceChildren();
  actionCell.replaceChildren();

  if (['approved', 'ready_for_pickup', 'released'].includes(notification.status)) {
    const link = document.createElement('a');
    link.href = 'generate_certificate.php?ref=' + encodeURIComponent(notification.reference_number);
    link.className = 'cert-link';
    link.textContent = 'Download';
    certificateCell.append(link);
  } else {
    certificateCell.textContent = '—';
  }

  if (notification.status === 'pending') {
    const cancel = document.createElement('span');
    cancel.className = 'cancel-trigger';
    cancel.dataset.ref = notification.reference_number;
    cancel.textContent = 'Cancel';
    actionCell.append(cancel);
  } else {
    actionCell.textContent = '—';
  }
}

function timeAgo(dateStr) {
  const diffMs = Date.now() - new Date(dateStr.replace(' ', 'T')).getTime();
  if (!Number.isFinite(diffMs)) return '';
  const mins = Math.max(0, Math.floor(diffMs / 60000));
  if (mins < 1) return 'just now';
  if (mins < 60) return mins + 'm ago';
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return hrs + 'h ago';
  return Math.floor(hrs / 24) + 'd ago';
}

async function loadNotifications(markRead = false) {
  const response = await fetch('get_notifications.php');
  const data = await response.json();
  if (!response.ok || !data.success) {
    throw new Error(data.error || 'Could not load notifications.');
  }

  notifBadge.textContent = data.unread_count;
  notifBadge.hidden = data.unread_count === 0;
  notifList.replaceChildren();

  if (data.notifications.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'notif-empty';
    empty.textContent = 'No notifications yet.';
    notifList.append(empty);
    return;
  }

  const updatedReferences = new Set();
  for (const notification of data.notifications) {
    if (notification.reference_number && !updatedReferences.has(notification.reference_number)) {
      updateRequestRow(notification);
      updatedReferences.add(notification.reference_number);
    }
    const item = document.createElement('div');
    item.className = 'notif-item' + (notification.is_read == 0 ? ' unread' : '');

    const message = document.createElement('div');
    message.textContent = notification.message;
    item.append(message);

    const time = document.createElement('div');
    time.className = 'notif-time';
    time.textContent = timeAgo(notification.created_at);
    item.append(time);
    notifList.append(item);
  }

  if (markRead && data.unread_count > 0) {
    const response = await fetch('mark_notifications_read.php', {method: 'POST'});
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Could not mark notifications as read.');
    }
    notifBadge.hidden = true;
    notifList.querySelectorAll('.notif-item.unread').forEach(item => item.classList.remove('unread'));
  }
}

notifBell.addEventListener('click', async function () {
  const opening = !notifDropdown.classList.contains('open');
  notifDropdown.classList.toggle('open', opening);
  notifBell.setAttribute('aria-expanded', String(opening));
  if (!opening) return;

  try {
    await loadNotifications(true);
  } catch (error) {
    notifList.textContent = 'Notifications could not be loaded. Please try again.';
  }
});

document.addEventListener('click', function (e) {
  if (!notifDropdown.contains(e.target) && !notifBell.contains(e.target)) {
    notifDropdown.classList.remove('open');
    notifBell.setAttribute('aria-expanded', 'false');
  }
});

async function refreshNotificationBadge() {
  try {
    await loadNotifications();
  } catch (error) {
    notifBadge.hidden = true;
    if (notifDropdown.classList.contains('open')) {
      notifList.textContent = 'Notifications could not be loaded. Please try again.';
    }
  }
}

refreshNotificationBadge();
window.setInterval(refreshNotificationBadge, 15000);
</script>

</body>
</html>