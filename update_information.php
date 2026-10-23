<?php
require __DIR__ . '/resident_auth.php';
require __DIR__ . '/config.php';

$residentId = (int)$_SESSION['resident_id'];
$accountStmt = $pdo->prepare('
    SELECT first_name, last_name, birthdate, gender, civil_status, address,
           contact_number, email, password_hash
    FROM residents
    WHERE resident_id = ?
');
$accountStmt->execute([$residentId]);
$account = $accountStmt->fetch();

if (!$account) {
    $_SESSION = [];
    session_destroy();
    header('Location: resident_login.php');
    exit;
}

$error = '';
$success = isset($_GET['updated']) ? 'Your account information has been updated.' : '';
$formValues = $account;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $civilStatus = trim($_POST['civil_status'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $formValues = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'birthdate' => $birthdate,
        'gender' => $gender,
        'civil_status' => $civilStatus,
        'address' => $address,
        'contact_number' => $contact,
        'email' => $email,
    ];
    $validGenders = ['male', 'female', 'other'];
    $validCivilStatuses = ['single', 'married', 'widowed', 'separated', 'divorced'];
    $birthdateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
    $validBirthdate = $birthdateValue && $birthdateValue->format('Y-m-d') === $birthdate
        && $birthdateValue <= new DateTimeImmutable('today');
    $emailChanged = strcasecmp($email, $account['email']) !== 0;
    $passwordChanged = $newPassword !== '' || $confirmPassword !== '';

    if (!$firstName || !$lastName || !$birthdate || !$gender || !$civilStatus || !$address || !$contact || !$email) {
        $error = 'Please fill in every account information field.';
    } elseif (mb_strlen($firstName) > 60 || !preg_match("/^[\\p{L}][\\p{L}\\s.'’-]*$/u", $firstName)) {
        $error = 'Please enter a valid first name (letters and basic punctuation only).';
    } elseif (mb_strlen($lastName) > 60 || !preg_match("/^[\\p{L}][\\p{L}\\s.'’-]*$/u", $lastName)) {
        $error = 'Please enter a valid last name (letters and basic punctuation only).';
    } elseif (!$validBirthdate) {
        $error = 'Please enter a valid birthdate that is not in the future.';
    } elseif (!in_array($gender, $validGenders, true)) {
        $error = 'Please select a valid gender.';
    } elseif (!in_array($civilStatus, $validCivilStatuses, true)) {
        $error = 'Please select a valid civil status.';
    } elseif (mb_strlen($address) > 255) {
        $error = 'Address must be 255 characters or fewer.';
    } elseif (!preg_match('/^[0-9]{11}$/', $contact)) {
        $error = 'Contact number must contain exactly 11 digits.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $error = 'Please enter a valid email address of 120 characters or fewer.';
    } elseif (($emailChanged || $passwordChanged) && !password_verify($currentPassword, $account['password_hash'])) {
        $error = 'Enter your current password to change your email or password.';
    } elseif ($passwordChanged && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\\s]).{8,}$/', $newPassword)) {
        $error = 'New password must be at least 8 characters and include uppercase and lowercase letters, a number, and a symbol.';
    } elseif ($passwordChanged && $newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } else {
        try {
            $emailStmt = $pdo->prepare('SELECT resident_id FROM residents WHERE email = ? AND resident_id <> ?');
            $emailStmt->execute([$email, $residentId]);
            if ($emailStmt->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                $updateSql = '
                    UPDATE residents
                    SET first_name = ?, last_name = ?, birthdate = ?, gender = ?,
                        civil_status = ?, address = ?, contact_number = ?, email = ?
                ';
                $updateParams = [
                    $firstName,
                    $lastName,
                    $birthdate,
                    $gender,
                    $civilStatus,
                    $address,
                    $contact,
                    $email,
                ];
                if ($passwordChanged) {
                    $updateSql .= ', password_hash = ?';
                    $updateParams[] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                $updateSql .= ' WHERE resident_id = ?';
                $updateParams[] = $residentId;

                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($updateParams);
                $_SESSION['resident_name'] = $firstName . ' ' . $lastName;
                header('Location: update_information.php?updated=1');
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && $emailChanged) {
                $error = 'An account with that email already exists.';
            } else {
                error_log('Resident account update failed: ' . $exception->getMessage());
                $error = 'Could not update your account information. Please try again.';
            }
        }
    }
}

function profile_value(array $values, string $key): string {
    return htmlspecialchars((string)($values[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/jpeg" href="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>">
<title>Update Information — Barangay Lawacamulag, San Jose, Tarlac</title>
<style nonce="<?= $csp_nonce ?>">
  :root{--paper:#EAF4FB;--paper-2:#E4F1E5;--ink:#173042;--ink-soft:#506A78;--blue:#1685C7;--blue-deep:#075184;--red:#9B2B22;--green:#23833B;--green-deep:#17632B;--gold:#E6B92E;--line:#B9D2DE;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:linear-gradient(135deg,var(--paper) 0%,#fff 52%,var(--paper-2) 100%);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;min-height:100vh;padding:32px 18px;}
  .box{max-width:620px;margin:0 auto;background:rgba(255,255,255,.97);border:1px solid var(--line);border-top:4px solid var(--green);border-radius:10px;padding:32px;box-shadow:0 18px 48px rgba(7,81,132,.12);}
  h1{font-size:26px;color:var(--blue-deep);margin-bottom:6px;}
  .sub{font-size:14px;color:var(--ink-soft);margin-bottom:22px;}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  label{display:block;font-size:13px;font-weight:500;color:var(--ink);margin-bottom:15px;}
  input,select{width:100%;margin-top:6px;padding:11px 12px;border:1px solid var(--line);background:#fff;font:inherit;font-size:14px;border-radius:5px;}
  input:focus,select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(22,133,199,.15);}
  h2{font-size:17px;color:var(--blue-deep);margin:20px 0 12px;padding-top:16px;border-top:1px solid var(--line);}
  .hint{font-size:12px;color:var(--ink-soft);margin:-4px 0 14px;}
  button{width:100%;background:var(--green);color:#fff;padding:12px;border:0;border-radius:5px;font-size:15px;font-weight:600;cursor:pointer;margin-top:5px;}
  button:hover{background:var(--green-deep);}
  .message{padding:11px 13px;border-radius:5px;font-size:13px;margin-bottom:16px;}
  .error{background:#FDEDEC;border:1px solid #E8B4AE;color:var(--red);}
  .success{background:#E4F1E5;border:1px solid #A9CFAC;color:var(--green-deep);}
  .foot{text-align:center;margin-top:18px;font-size:13px;}
  .foot a{color:var(--green-deep);font-weight:600;}
  @media(max-width:520px){.box{padding:24px 20px}.row{grid-template-columns:1fr;gap:0}}
</style>
</head>
<body>
  <main class="box">
    <h1>Update Information</h1>
    <p class="sub">Correct the details on your resident account. Changes here do not alter information on requests you have already submitted.</p>
    <?php if ($error): ?><p class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($success): ?><p class="message success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <div class="row">
        <label>First name
          <input type="text" name="first_name" value="<?= profile_value($formValues, 'first_name') ?>" maxlength="60" pattern="[\p{L}][\p{L}\s.'’-]*" title="Use letters and basic punctuation only." required autocomplete="given-name">
        </label>
        <label>Last name
          <input type="text" name="last_name" value="<?= profile_value($formValues, 'last_name') ?>" maxlength="60" pattern="[\p{L}][\p{L}\s.'’-]*" title="Use letters and basic punctuation only." required autocomplete="family-name">
        </label>
      </div>
      <div class="row">
        <label>Birthdate
          <input type="date" name="birthdate" value="<?= profile_value($formValues, 'birthdate') ?>" max="<?= date('Y-m-d') ?>" required>
        </label>
        <label>Gender
          <select name="gender" required>
            <option value="">Select…</option>
            <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
              <option value="<?= $value ?>" <?= $formValues['gender'] === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Civil status
        <select name="civil_status" required>
          <option value="">Select…</option>
          <?php foreach (['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'divorced' => 'Divorced'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $formValues['civil_status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Address
        <input type="text" name="address" value="<?= profile_value($formValues, 'address') ?>" maxlength="255" required autocomplete="street-address">
      </label>
      <div class="row">
        <label>Contact number
          <input type="tel" name="contact_number" value="<?= profile_value($formValues, 'contact_number') ?>" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." required autocomplete="tel">
        </label>
        <label>Email
          <input type="email" name="email" value="<?= profile_value($formValues, 'email') ?>" maxlength="120" required autocomplete="email">
        </label>
      </div>
      <h2>Change password <span class="hint">(optional)</span></h2>
      <p class="hint">Leave both new-password fields blank to keep your current password. Your current password is required to change your email or password.</p>
      <label>Current password
        <input type="password" name="current_password" autocomplete="current-password">
      </label>
      <div class="row">
        <label>New password
          <input type="password" name="new_password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}" title="Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol." autocomplete="new-password">
        </label>
        <label>Confirm new password
          <input type="password" name="confirm_password" minlength="8" autocomplete="new-password">
        </label>
      </div>
      <button type="submit">Save changes</button>
    </form>
    <p class="foot"><a href="obrs.php">Back to Document Services</a> · <a href="my_requests.php">My requests</a></p>
  </main>
</body>
</html>
