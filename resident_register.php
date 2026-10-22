<?php
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $firstName    = trim($_POST['first_name'] ?? '');
    $lastName     = trim($_POST['last_name'] ?? '');
    $birthdate    = trim($_POST['birthdate'] ?? '');
    $gender       = trim($_POST['gender'] ?? '');
    $civilStatus  = trim($_POST['civil_status'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $contact      = trim($_POST['contact_number'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';

    $validGenders = ['male', 'female', 'other'];
    $validCivilStatuses = ['single', 'married', 'widowed', 'separated', 'divorced'];
    $birthdateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
    $validBirthdate = $birthdateValue && $birthdateValue->format('Y-m-d') === $birthdate
        && $birthdateValue <= new DateTimeImmutable('today');

    if (!$firstName || !$lastName || !$birthdate || !$gender || !$civilStatus || !$address || !$contact || !$email || !$password || !$confirm) {
        $error = 'Please fill in every field.';
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
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($email) > 120) {
        $error = 'Email address must be 120 characters or fewer.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and include uppercase and lowercase letters, a number, and a symbol.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT resident_id FROM residents WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('
                INSERT INTO residents
                    (first_name, last_name, birthdate, gender, civil_status, address, contact_number, email, password_hash, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ');
            $ins->execute([$firstName, $lastName, $birthdate, $gender, $civilStatus, $address, $contact, $email, $hash]);

            $_SESSION['resident_id']   = $pdo->lastInsertId();
            $_SESSION['resident_name'] = $firstName . ' ' . $lastName;
            header('Location: obrs.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create an account — Barangay Lawacamulag, San Jose, Tarlac</title>
<style nonce="<?= $csp_nonce ?>">
  :root{
    --paper:#EAF4FB; --paper-2:#E4F1E5; --ink:#173042; --ink-soft:#506A78;
    --blue:#1685C7; --blue-deep:#075184; --green:#23833B; --green-deep:#17632B;
    --red:#9B2B22; --gold:#E6B92E; --line:#B9D2DE;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow-x:hidden;}
  .bg-logo{
    position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:640px;height:640px;border-radius:50%;background-image:url('<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>');
    background-size:contain;background-repeat:no-repeat;background-position:center;
    opacity:10;z-index:0;pointer-events:none;
  }
  .box{position:relative;z-index:1;}
  .box{background:rgba(255,255,255,0.9);backdrop-filter:blur(1px);border:1px solid var(--line);max-width:480px;width:100%;padding:36px 34px;box-shadow:6px 6px 0 rgba(185,210,222,0.62);}
  .seal-mark{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--green);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--green);margin-bottom:16px;}
  h1{font-size:22px;font-weight:700;letter-spacing:-.02em;color:var(--blue-deep);margin-bottom:4px;}
  .sub{font-size:13px;color:var(--ink-soft);margin-bottom:22px;}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  label{display:block;font-size:12px;color:var(--ink-soft);margin-bottom:14px;}
  input,select{width:100%;margin-top:6px;padding:10px 11px;border:1px solid var(--line);background:#fff;font-family:inherit;font-size:13.5px;border-radius:2px;transition:border-color .15s,box-shadow .15s;}
  input:focus,select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(30,58,95,0.12);}
  button{width:100%;background:var(--green);color:#fff;padding:12px;border:none;border-radius:2px;font-size:15px;font-weight:500;cursor:pointer;margin-top:4px;transition:background .15s,transform .15s,box-shadow .15s;}
  button:hover{background:var(--green-deep);transform:translateY(-2px);box-shadow:0 6px 14px rgba(35,131,59,0.25);}
  button:active{transform:translateY(0);box-shadow:none;}
  .error{background:#F5E3E0;border:1px solid var(--red);color:var(--red);font-size:12.5px;padding:9px 11px;border-radius:2px;margin-bottom:16px;}
  .foot-link{text-align:center;font-size:13px;color:var(--ink-soft);margin-top:16px;}
  .foot-link a{color:var(--green-deep);font-weight:500;}
  .field-hint{display:block;margin-top:5px;color:var(--ink-soft);font-size:11px;line-height:1.4;}
  .password-rules{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:5px 10px;margin:-7px 0 14px;font-size:11px;color:var(--ink-soft);}
  .password-rules li{display:flex;align-items:center;gap:6px;transition:color .15s;}
  .password-rules li::before{content:"○";width:14px;color:#8A969C;font-size:13px;line-height:1;}
  .password-rules li.met{color:var(--green-deep);}
  .password-rules li.met::before{content:"✓";font-weight:700;color:var(--green);}
  @media(max-width:480px){.password-rules{grid-template-columns:1fr;}}
</style>
</head>
<body>
  <div class="bg-logo"></div>
  <div class="box">
    <h1>Create your account</h1>
    <p class="sub">Register once to request and track barangay documents.</p>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
         <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="row2">
        <label>First name<input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" maxlength="60" pattern="[\p{L}][\p{L}\s.'’-]*" title="Use letters and basic punctuation only." required></label>
        <label>Last name<input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" maxlength="60" pattern="[\p{L}][\p{L}\s.'’-]*" title="Use letters and basic punctuation only." required></label>
      </div>
      <div class="row2">
        <label>Birthdate<input type="date" name="birthdate" value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required></label>
        <label>Gender
          <select name="gender" required>
            <option value="">Select…</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </label>
      </div>
      <label>Civil status
        <select name="civil_status" required>
          <option value="">Select…</option>
          <option value="single">Single</option>
          <option value="married">Married</option>
          <option value="widowed">Widowed</option>
          <option value="separated">Separated</option>
          <option value="divorced">Divorced</option>
        </select>
      </label>
      <label>Address<input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" maxlength="255" required></label>
      <div class="row2">
        <label>Contact number<input type="text" name="contact_number" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." autocomplete="tel" required></label>
        <label>Email<input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" maxlength="120" required></label>
      </div>
      <div class="row2">
        <label>Password<input type="password" name="password" autocomplete="new-password" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}" title="Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol."></label>
        <label>Confirm password<input type="password" name="confirm_password" autocomplete="new-password" required minlength="8"></label>
      </div>
      <ul class="password-rules" aria-label="Password requirements">
        <li data-password-rule="length">At least 8 characters</li>
        <li data-password-rule="uppercase">Uppercase letter</li>
        <li data-password-rule="lowercase">Lowercase letter</li>
        <li data-password-rule="number">Number</li>
        <li data-password-rule="symbol">Symbol</li>
      </ul>
      <button type="submit">Create account</button>
    </form>
    <p class="foot-link">Already have an account? <a href="resident_login.php">Log in</a></p>
  </div>
  <script nonce="<?= $csp_nonce ?>">
    const contactInput = document.querySelector('[name="contact_number"]');
    contactInput.addEventListener('input', function () {
      const digitsOnly = this.value.replace(/[^0-9]/g, '').slice(0, 11);
      if (this.value !== digitsOnly) {
        this.value = digitsOnly;
      }
    });

    const passwordInput = document.querySelector('[name="password"]');
    const passwordRules = {
      length: value => value.length >= 8,
      uppercase: value => /[A-Z]/.test(value),
      lowercase: value => /[a-z]/.test(value),
      number: value => /[0-9]/.test(value),
      symbol: value => /[^A-Za-z0-9\s]/.test(value)
    };

    passwordInput.addEventListener('input', function () {
      const value = this.value;
      Object.entries(passwordRules).forEach(([rule, isMet]) => {
        document.querySelector(`[data-password-rule="${rule}"]`).classList.toggle('met', isMet(value));
      });
    });
  </script>
</body>
</html>