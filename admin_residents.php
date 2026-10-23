<?php
require __DIR__ . '/admin_auth.php';

if (($_SESSION['staff_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Only administrators can manage resident accounts.');
}

require __DIR__ . '/config.php';

$genders = ['male', 'female', 'other'];
$civilStatuses = ['single', 'married', 'widowed', 'separated', 'divorced'];
$error = '';
$success = '';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$rawSearch = $_GET['q'] ?? '';
$search = is_string($rawSearch) ? trim($rawSearch) : '';
$form = [
    'first_name' => '',
    'last_name' => '',
    'birthdate' => '',
    'gender' => '',
    'civil_status' => '',
    'address' => '',
    'contact_number' => '',
    'email' => '',
    'is_deleted' => 0,
];

if ($editId > 0) {
    $editStmt = $pdo->prepare('
        SELECT first_name, last_name, birthdate, gender, civil_status, address,
               contact_number, email, is_deleted
        FROM residents
        WHERE resident_id = ?
    ');
    $editStmt->execute([$editId]);
    $editAccount = $editStmt->fetch();
    if ($editAccount) {
        $form = $editAccount;
    } else {
        $editId = 0;
        $error = 'Resident account not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $residentId = filter_var($_POST['resident_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

    if ($action === 'delete' || $action === 'restore') {
        if ($residentId < 1) {
            $error = 'Select a valid resident account.';
        } else {
            $active = $action === 'restore' ? 1 : 0;
            $statusStmt = $pdo->prepare('
                UPDATE residents
                SET is_deleted = ?, deleted_at = ?
                WHERE resident_id = ?
            ');
            $statusStmt->execute([$active ? 0 : 1, $active ? null : date('Y-m-d H:i:s'), $residentId]);
            if ($statusStmt->rowCount() > 0) {
                $success = $active ? 'Resident account restored.' : 'Resident account deleted. Existing requests and history were retained.';
            } else {
                $checkStmt = $pdo->prepare('SELECT is_deleted FROM residents WHERE resident_id = ?');
                $checkStmt->execute([$residentId]);
                $existing = $checkStmt->fetch();
                if (!$existing) {
                    $error = 'Resident account not found.';
                } elseif ((int)$existing['is_deleted'] === ($active ? 0 : 1)) {
                    $success = $active ? 'Resident account is already active.' : 'Resident account is already deleted.';
                }
            }
        }
    } elseif ($action === 'create' || $action === 'update') {
        $editId = $action === 'update' ? $residentId : 0;
        $form = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'birthdate' => trim($_POST['birthdate'] ?? ''),
            'gender' => trim($_POST['gender'] ?? ''),
            'civil_status' => trim($_POST['civil_status'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'is_deleted' => 0,
        ];
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $birthdateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $form['birthdate']);
        $validBirthdate = $birthdateValue
            && $birthdateValue->format('Y-m-d') === $form['birthdate']
            && $birthdateValue <= new DateTimeImmutable('today');

        if ($action === 'update' && $residentId < 1) {
            $error = 'Select a valid resident account.';
        } elseif (!$form['first_name'] || !$form['last_name'] || !$form['birthdate'] || !$form['gender']
            || !$form['civil_status'] || !$form['address'] || !$form['contact_number'] || !$form['email']) {
            $error = 'Please fill in every account information field.';
        } elseif (mb_strlen($form['first_name']) > 60 || !preg_match("/^[\\p{L}][\\p{L}\\s.'’-]*$/u", $form['first_name'])) {
            $error = 'Please enter a valid first name.';
        } elseif (mb_strlen($form['last_name']) > 60 || !preg_match("/^[\\p{L}][\\p{L}\\s.'’-]*$/u", $form['last_name'])) {
            $error = 'Please enter a valid last name.';
        } elseif (!$validBirthdate) {
            $error = 'Please enter a valid birthdate that is not in the future.';
        } elseif (!in_array($form['gender'], $genders, true)) {
            $error = 'Please select a valid gender.';
        } elseif (!in_array($form['civil_status'], $civilStatuses, true)) {
            $error = 'Please select a valid civil status.';
        } elseif (mb_strlen($form['address']) > 255) {
            $error = 'Address must be 255 characters or fewer.';
        } elseif (!preg_match('/^[0-9]{11}$/', $form['contact_number'])) {
            $error = 'Contact number must contain exactly 11 digits.';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 120) {
            $error = 'Please enter a valid email address of 120 characters or fewer.';
        } elseif (($action === 'create' && $password === '')
            || ($password !== '' && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\\s]).{8,}$/', $password))) {
            $error = 'Set a password with at least 8 characters, including uppercase and lowercase letters, a number, and a symbol.';
        } elseif ($password !== '' && $password !== $confirmPassword) {
            $error = 'Password fields do not match.';
        } else {
            $emailSql = 'SELECT resident_id FROM residents WHERE email = ?';
            $emailParams = [$form['email']];
            if ($action === 'update') {
                $emailSql .= ' AND resident_id <> ?';
                $emailParams[] = $residentId;
            }
            $emailStmt = $pdo->prepare($emailSql);
            $emailStmt->execute($emailParams);

            if ($emailStmt->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                try {
                    if ($action === 'create') {
                        $insert = $pdo->prepare('
                            INSERT INTO residents
                                (first_name, last_name, birthdate, gender, civil_status, address, contact_number, email, password_hash)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ');
                        $insert->execute([
                            $form['first_name'], $form['last_name'], $form['birthdate'], $form['gender'],
                            $form['civil_status'], $form['address'], $form['contact_number'], $form['email'],
                            password_hash($password, PASSWORD_DEFAULT),
                        ]);
                        $success = 'Resident account created.';
                        $editId = 0;
                        $form = array_merge($form, [
                            'first_name' => '', 'last_name' => '', 'birthdate' => '', 'gender' => '',
                            'civil_status' => '', 'address' => '', 'contact_number' => '', 'email' => '',
                        ]);
                    } else {
                        $existsStmt = $pdo->prepare('SELECT 1 FROM residents WHERE resident_id = ?');
                        $existsStmt->execute([$residentId]);
                        if (!$existsStmt->fetchColumn()) {
                            $error = 'Resident account not found.';
                        } else {
                            $sql = '
                                UPDATE residents
                                SET first_name = ?, last_name = ?, birthdate = ?, gender = ?,
                                    civil_status = ?, address = ?, contact_number = ?, email = ?
                            ';
                            $params = [
                                $form['first_name'], $form['last_name'], $form['birthdate'], $form['gender'],
                                $form['civil_status'], $form['address'], $form['contact_number'], $form['email'],
                            ];
                            if ($password !== '') {
                                $sql .= ', password_hash = ?';
                                $params[] = password_hash($password, PASSWORD_DEFAULT);
                            }
                            $sql .= ' WHERE resident_id = ?';
                            $params[] = $residentId;
                            $update = $pdo->prepare($sql);
                            $update->execute($params);
                            $success = 'Resident account updated.';
                        }
                    }
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $error = 'An account with that email already exists.';
                    } else {
                        error_log('Admin resident account change failed: ' . $exception->getMessage());
                        $error = 'Could not save the resident account. Please try again.';
                    }
                }
            }
        }
    } else {
        $error = 'Invalid account action.';
    }
}

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR contact_number LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$listStmt = $pdo->prepare('
    SELECT resident_id, first_name, last_name, birthdate, gender, civil_status,
           address, contact_number, email, is_deleted, deleted_at, created_at
    FROM residents
    ' . $where . '
    ORDER BY is_deleted ASC, last_name, first_name
');
$listStmt->execute($params);
$accounts = $listStmt->fetchAll();
$showFormModal = $editId > 0
    || ($_SERVER['REQUEST_METHOD'] === 'POST'
        && in_array($_POST['action'] ?? '', ['create', 'update'], true));

function resident_form_value(array $form, string $field): string {
    return htmlspecialchars((string)($form[$field] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/jpeg" href="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>">
<title>Resident Accounts — Barangay Lawacamulag, San Jose, Tarlac</title>
<style nonce="<?= $csp_nonce ?>">
  :root{--paper:#EAF4FB;--paper-2:#E4F1E5;--ink:#173042;--ink-soft:#506A78;--blue:#1685C7;--blue-deep:#075184;--red:#9B2B22;--green:#23833B;--green-deep:#17632B;--gold:#E6B92E;--line:#B9D2DE;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;line-height:1.5;}
  header{background:var(--green-deep);color:#fff;padding:15px 28px;display:flex;justify-content:space-between;align-items:center;gap:18px;}
  header a{color:#fff;text-decoration:none;}
  .title{font-weight:700;font-size:17px;}
  .nav{display:flex;gap:18px;align-items:center;font-size:13px;}
  .wrap{max-width:1440px;margin:0 auto;padding:28px;}
  h1{color:var(--blue-deep);font-size:26px;margin-bottom:4px;}
  .sub{color:var(--ink-soft);font-size:14px;margin-bottom:20px;}
  .panel{background:#fff;border:1px solid var(--line);padding:20px;border-radius:6px;}
  h2{font-size:18px;color:var(--blue-deep);margin-bottom:14px;}
  label{display:block;font-size:12px;font-weight:600;margin-bottom:12px;}
  input,select{width:100%;margin-top:5px;padding:9px 10px;border:1px solid var(--line);font:inherit;font-size:13px;border-radius:3px;background:#fff;}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
  button,.button{display:inline-block;padding:9px 12px;border:1px solid var(--green);border-radius:3px;background:var(--green);color:#fff;font:inherit;font-size:13px;text-decoration:none;cursor:pointer;}
  .button.secondary{background:#fff;color:var(--blue-deep);border-color:var(--line);}
  button.danger{background:#fff;color:var(--red);border-color:#d9aaa5;}
  .account-actions{display:flex;gap:6px;flex-wrap:wrap;}
  .msg{padding:10px 12px;margin-bottom:14px;border-radius:3px;font-size:13px;}
  .error{background:#FDEDEC;color:var(--red);border:1px solid #E8B4AE;}
  .success{background:#E4F1E5;color:var(--green-deep);border:1px solid #A9CFAC;}
  .search{display:flex;gap:8px;margin-bottom:12px;}
  .search input{margin:0;max-width:420px;}
  .table-wrap{overflow:auto;}
  table{border-collapse:collapse;width:100%;min-width:980px;background:#fff;}
  th,td{text-align:left;padding:10px;border-bottom:1px solid #e2edf1;font-size:12px;vertical-align:top;}
  th{background:var(--paper-2);font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);}
  .deleted{color:var(--red);font-weight:600;}
  .muted{color:var(--ink-soft);}
  .hint{font-size:11px;color:var(--ink-soft);margin:-4px 0 12px;}
  .accounts-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px;}
  .accounts-heading h2{margin:0;}
  .modal-overlay{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,35,48,.62);}
  .modal-overlay.open{display:flex;}
  .modal-box{position:relative;width:min(680px,100%);max-height:92vh;overflow:auto;background:#fff;border:1px solid var(--line);border-top:4px solid var(--green);border-radius:8px;padding:24px;box-shadow:0 18px 48px rgba(0,0,0,.28);}
  .modal-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px;}
  .modal-heading h2{margin:0;}
  .modal-close{flex:0 0 auto;padding:2px 9px;border:0;background:transparent;color:var(--ink-soft);font-size:25px;line-height:1;}
  .modal-close:hover{background:transparent;color:var(--red);}
  .form-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:8px;}
  body.modal-open{overflow:hidden;}
  @media(max-width:1000px){.wrap{padding:20px 14px}}
  @media(max-width:600px){header{padding:12px 14px;align-items:flex-start}.nav{gap:10px;flex-wrap:wrap;justify-content:flex-end;font-size:11px}.row{grid-template-columns:1fr;gap:0}}
  @media(max-width:600px){.modal-overlay{padding:8px}.modal-box{padding:18px}.accounts-heading{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header>
  <a class="title" href="dashboard.php">Resident Accounts</a>
  <nav class="nav" aria-label="Staff navigation">
    <a href="dashboard.php">Dashboard</a>
    <span><?= htmlspecialchars($_SESSION['staff_name'], ENT_QUOTES, 'UTF-8') ?> (admin)</span>
    <a href="logout.php">Log out</a>
  </nav>
</header>
<main class="wrap">
  <h1>Resident accounts</h1>
  <p class="sub">Create, review, update, disable, and restore resident accounts. Deletion disables login but preserves requests and history.</p>
  <?php if ($error && !$showFormModal): ?><p class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($success): ?><p class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <section class="panel">
      <div class="accounts-heading">
        <h2>Created accounts (<?= count($accounts) ?>)</h2>
        <button type="button" id="openCreateResident">Add resident account</button>
      </div>
      <form class="search" method="get">
        <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search name, email, or contact">
        <button type="submit">Search</button>
      </form>
      <?php if (!$accounts): ?>
        <p class="muted">No resident accounts found.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>Birthdate</th><th>Gender / Civil status</th><th>Address</th><th>Contact</th><th>Email</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($accounts as $account): ?>
                <tr>
                  <td><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($account['birthdate'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars(ucfirst($account['gender'] ?? '') . ' / ' . ucfirst($account['civil_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($account['address'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($account['contact_number'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="<?= $account['is_deleted'] ? 'deleted' : '' ?>"><?= $account['is_deleted'] ? 'Deleted' : 'Active' ?><?= $account['deleted_at'] ? '<br><span class="muted">' . htmlspecialchars($account['deleted_at'], ENT_QUOTES, 'UTF-8') . '</span>' : '' ?></td>
                  <td><?= htmlspecialchars($account['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <div class="account-actions">
                      <a class="button secondary" href="admin_residents.php?edit=<?= (int)$account['resident_id'] ?>">Edit</a>
                      <form method="post" onsubmit="return confirm('<?= $account['is_deleted'] ? 'Restore' : 'Disable' ?> this resident account? Existing requests and records will be retained.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="resident_id" value="<?= (int)$account['resident_id'] ?>">
                        <input type="hidden" name="action" value="<?= $account['is_deleted'] ? 'restore' : 'delete' ?>">
                        <button class="<?= $account['is_deleted'] ? '' : 'danger' ?>" type="submit"><?= $account['is_deleted'] ? 'Restore' : 'Delete' ?></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
  </section>
</main>
<div class="modal-overlay<?= $showFormModal ? ' open' : '' ?>" id="residentFormModal" role="dialog" aria-modal="true" aria-labelledby="residentFormTitle">
  <section class="modal-box">
    <div class="modal-heading">
      <h2 id="residentFormTitle"><?= $editId ? 'Edit resident account' : 'Add resident account' ?></h2>
      <button type="button" class="modal-close" id="closeResidentModal" aria-label="Close">&times;</button>
    </div>
    <?php if ($editId && (int)$form['is_deleted'] === 1): ?><p class="msg error">This account is disabled.</p><?php endif; ?>
    <?php if ($error && $showFormModal): ?><p class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" id="residentAccountForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
        <?php if ($editId): ?><input type="hidden" name="resident_id" value="<?= (int)$editId ?>"><?php endif; ?>
        <div class="row">
          <label>First name<input name="first_name" value="<?= resident_form_value($form, 'first_name') ?>" maxlength="60" required></label>
          <label>Last name<input name="last_name" value="<?= resident_form_value($form, 'last_name') ?>" maxlength="60" required></label>
        </div>
        <div class="row">
          <label>Birthdate<input type="date" name="birthdate" value="<?= resident_form_value($form, 'birthdate') ?>" max="<?= date('Y-m-d') ?>" required></label>
          <label>Gender<select name="gender" required><option value="">Select…</option>
            <?php foreach ($genders as $gender): ?><option value="<?= $gender ?>" <?= $form['gender'] === $gender ? 'selected' : '' ?>><?= ucfirst($gender) ?></option><?php endforeach; ?>
          </select></label>
        </div>
        <label>Civil status<select name="civil_status" required><option value="">Select…</option>
          <?php foreach ($civilStatuses as $status): ?><option value="<?= $status ?>" <?= $form['civil_status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?>
        </select></label>
        <label>Address<input name="address" value="<?= resident_form_value($form, 'address') ?>" maxlength="255" required></label>
        <div class="row">
          <label>Contact number<input name="contact_number" value="<?= resident_form_value($form, 'contact_number') ?>" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" required></label>
          <label>Email<input type="email" name="email" value="<?= resident_form_value($form, 'email') ?>" maxlength="120" required></label>
        </div>
        <label><?= $editId ? 'Set/reset password (optional)' : 'Initial password' ?>
          <input type="password" name="password" <?= $editId ? '' : 'required' ?> minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}" autocomplete="new-password">
        </label>
        <label>Confirm password
          <input type="password" name="confirm_password" <?= $editId ? '' : 'required' ?> minlength="8" autocomplete="new-password">
        </label>
        <p class="hint">Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol.</p>
        <div class="form-actions">
          <button type="submit"><?= $editId ? 'Save account changes' : 'Create account' ?></button>
          <?php if ($editId): ?><a class="button secondary" href="admin_residents.php">Cancel edit</a><?php else: ?><button type="button" class="button secondary" id="cancelCreateResident">Cancel</button><?php endif; ?>
        </div>
    </form>
  </section>
</div>
<script nonce="<?= $csp_nonce ?>">
(() => {
  const modal = document.getElementById('residentFormModal');
  const openButton = document.getElementById('openCreateResident');
  const closeButton = document.getElementById('closeResidentModal');
  const cancelButton = document.getElementById('cancelCreateResident');
  const firstField = document.querySelector('#residentAccountForm input[name="first_name"]');

  function openModal() {
    modal.classList.add('open');
    document.body.classList.add('modal-open');
    firstField.focus();
  }

  function closeModal() {
    modal.classList.remove('open');
    document.body.classList.remove('modal-open');
  }

  openButton.addEventListener('click', openModal);
  closeButton.addEventListener('click', closeModal);
  if (cancelButton) cancelButton.addEventListener('click', closeModal);
  modal.addEventListener('click', event => {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });
  if (modal.classList.contains('open')) {
    document.body.classList.add('modal-open');
    firstField.focus();
  }
})();
</script>
</body>
</html>
