<?php
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare('SELECT resident_id, first_name, last_name, password_hash FROM residents WHERE email = ?');
        $stmt->execute([$email]);
        $resident = $stmt->fetch();

        if ($resident && password_verify($password, $resident['password_hash'])) {
            $_SESSION['resident_id']   = $resident['resident_id'];
            $_SESSION['resident_name'] = $resident['first_name'] . ' ' . $resident['last_name'];
            header('Location: obrs.php');
            exit;
        }
    }
    $error = 'Incorrect email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — Barangay Lawacamulag, San Jose, Tarlac</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style nonce="<?= $csp_nonce ?>">
  :root{
    --paper:#F3EEE1; --paper-2:#EAE3D2; --ink:#211D16; --ink-soft:#5B564A;
    --blue:#1E3A5F; --blue-deep:#122845; --red:#9B2B22; --gold:#B08D3E; --line:#C9C1AC;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--paper);color:var(--ink);font-family:'Public Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden;}
  .bg-logo{
    position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:640px;height:640px;border-radius:50%;background-image:url('logo.jpg');
    background-size:contain;background-repeat:no-repeat;background-position:center;
    opacity:10;z-index:0;pointer-events:none;
  }
  .box{position:relative;z-index:1;}
  .box{background:rgba(251,249,242,0.62);backdrop-filter:blur(1px);border:1px solid var(--line);max-width:360px;width:100%;padding:36px 34px;box-shadow:6px 6px 0 rgba(234,227,210,0.62);}
  .box > *{position:relative;z-index:1;}
  h1{font-family:'Fraunces',serif;font-size:22px;color:var(--blue-deep);margin-bottom:4px;}
  .sub{font-size:13px;color:var(--ink-soft);margin-bottom:22px;}
  label{display:block;font-size:12px;color:var(--ink-soft);margin-bottom:16px;}
  input{width:100%;margin-top:6px;padding:11px 12px;border:1px solid var(--line);background:#fff;font-family:inherit;font-size:14px;border-radius:2px;transition:border-color .15s,box-shadow .15s;}
  input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(30,58,95,0.12);}
  button{width:100%;background:var(--red);color:#fff;padding:13px;border:none;border-radius:2px;font-size:15px;font-weight:500;cursor:pointer;margin-top:6px;transition:background .15s,transform .15s,box-shadow .15s;}
  button:hover{background:#7f2019;transform:translateY(-2px);box-shadow:0 6px 14px rgba(155,43,34,0.25);}
  button:active{transform:translateY(0);box-shadow:none;}
  .error{background:#F5E3E0;border:1px solid var(--red);color:var(--red);font-size:12.5px;padding:9px 11px;border-radius:2px;margin-bottom:16px;}
  .foot-link{text-align:center;font-size:13px;color:var(--ink-soft);margin-top:16px;}
  .foot-link a{color:var(--blue);font-weight:500;}
</style>
</head>
<body>
  <div class="bg-logo"></div>
  <div class="box">
    <h1>Log in</h1>
    <p class="sub">Access your barangay document requests.</p>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
         <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <label>Email
        <input type="email" name="email" required autofocus>
      </label>
      <label>Password
        <input type="password" name="password" required>
      </label>
      <button type="submit">Log in</button>
    </form>
    <p class="foot-link">No account yet? <a href="resident_register.php">Create one</a></p>
  </div>
</body>
</html>