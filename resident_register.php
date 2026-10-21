<?php
session_start();
require __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (!$firstName || !$lastName || !$birthdate || !$gender || !$civilStatus || !$address || !$contact || !$email || !$password) {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
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
<title>Create an account — Barangay Pitipiwpiwwiwiw</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F3EEE1; --paper-2:#EAE3D2; --ink:#211D16; --ink-soft:#5B564A;
    --blue:#1E3A5F; --blue-deep:#122845; --red:#9B2B22; --gold:#B08D3E; --line:#C9C1AC;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:'Public Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow-x:hidden;}
  .bg-logo{
    position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:640px;height:640px;border-radius:50%;background-image:url('logo.jpg');
    background-size:contain;background-repeat:no-repeat;background-position:center;
    opacity:10;z-index:0;pointer-events:none;
  }
  .box{position:relative;z-index:1;}
  .box{background:rgba(251,249,242,0.62);backdrop-filter:blur(1px);border:1px solid var(--line);max-width:480px;width:100%;padding:36px 34px;box-shadow:6px 6px 0 rgba(234,227,210,0.62);}
  .seal-mark{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--blue);display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-weight:600;font-size:14px;color:var(--blue);margin-bottom:16px;}
  h1{font-family:'Fraunces',serif;font-size:22px;color:var(--blue-deep);margin-bottom:4px;}
  .sub{font-size:13px;color:var(--ink-soft);margin-bottom:22px;}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  label{display:block;font-size:12px;color:var(--ink-soft);margin-bottom:14px;}
  input,select{width:100%;margin-top:6px;padding:10px 11px;border:1px solid var(--line);background:#fff;font-family:inherit;font-size:13.5px;border-radius:2px;transition:border-color .15s,box-shadow .15s;}
  input:focus,select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(30,58,95,0.12);}
  button{width:100%;background:var(--red);color:#fff;padding:12px;border:none;border-radius:2px;font-size:15px;font-weight:500;cursor:pointer;margin-top:4px;transition:background .15s,transform .15s,box-shadow .15s;}
  button:hover{background:#7f2019;transform:translateY(-2px);box-shadow:0 6px 14px rgba(155,43,34,0.25);}
  button:active{transform:translateY(0);box-shadow:none;}
  button:hover{background:#7f2019;}
  .error{background:#F5E3E0;border:1px solid var(--red);color:var(--red);font-size:12.5px;padding:9px 11px;border-radius:2px;margin-bottom:16px;}
  .foot-link{text-align:center;font-size:13px;color:var(--ink-soft);margin-top:16px;}
  .foot-link a{color:var(--blue);font-weight:500;}
</style>
</head>
<body>
  <div class="bg-logo"></div>
  <div class="box">
    <h1>Create your account</h1>
    <p class="sub">Register once to request and track barangay documents.</p>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
      <div class="row2">
        <label>First name<input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required></label>
        <label>Last name<input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required></label>
      </div>
      <div class="row2">
        <label>Birthdate<input type="date" name="birthdate" value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>" required></label>
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
      <label>Address<input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required></label>
      <div class="row2">
        <label>Contact number<input type="tel" name="contact_number" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></label>
      </div>
      <div class="row2">
        <label>Password<input type="password" name="password" required minlength="8"></label>
        <label>Confirm password<input type="password" name="confirm_password" required minlength="8"></label>
      </div>
      <button type="submit">Create account</button>
    </form>
    <p class="foot-link">Already have an account? <a href="resident_login.php">Log in</a></p>
  </div>
</body>
</html>