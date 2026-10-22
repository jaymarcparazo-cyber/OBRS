<?php
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare('SELECT staff_id, username, password_hash, full_name, role FROM staff_accounts WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $staff = $stmt->fetch();

        if ($staff && password_verify($password, $staff['password_hash'])) {
            $_SESSION['staff_id']   = $staff['staff_id'];
            $_SESSION['staff_name'] = $staff['full_name'];
            $_SESSION['staff_role'] = $staff['role'];
            header('Location: dashboard.php');
            exit;
        }
    }
    $error = 'Incorrect username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/jpeg" href="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>">
<title>Staff Login — Barangay Lawacamulag, San Jose, Tarlac</title>
<style nonce="<?= $csp_nonce ?>">
  :root{
    --paper:#EAF4FB; --paper-2:#E4F1E5; --ink:#173042; --ink-soft:#506A78;
    --blue:#1685C7; --blue-deep:#075184; --red:#9B2B22; --green:#23833B;
    --green-deep:#17632B; --gold:#E6B92E; --line:#B9D2DE;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{
    background:var(--blue-deep);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
    position:relative;overflow:hidden;
  }
  .bg-logo{
    position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:640px;height:640px;border-radius:50%;background-image:url('<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>');
    background-size:contain;background-repeat:no-repeat;background-position:center;
    opacity:10;z-index:0;pointer-events:none;
  }
  .login-box{position:relative;z-index:1;}
  .login-box{
    background:rgba(234,244,251,0.94);backdrop-filter:blur(1px);border:1px solid var(--line);max-width:360px;width:100%;
    padding:38px 34px;box-shadow:6px 6px 0 rgba(0,0,0,0.15);
  }
  h1{font-size:21px;font-weight:700;letter-spacing:-.02em;text-align:center;color:var(--blue-deep);margin-bottom:4px;}
  .sub{text-align:center;font-size:12.5px;color:var(--ink-soft);margin-bottom:26px;}
  label{display:block;font-size:12px;color:var(--ink-soft);margin-bottom:16px;}
  input{
    width:100%;margin-top:6px;padding:11px 12px;border:1px solid var(--line);
    background:#fff;font-family:inherit;font-size:14px;border-radius:2px;
    transition:border-color .15s,box-shadow .15s;
  }
  input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(22,133,199,0.16);}
  button{
    width:100%;background:var(--green);color:#fff;padding:13px;border:none;border-radius:2px;
    font-size:15px;font-weight:500;cursor:pointer;margin-top:6px;
    transition:background .15s,transform .15s,box-shadow .15s;
  }
  button:hover{background:var(--green-deep);transform:translateY(-2px);box-shadow:0 6px 14px rgba(35,131,59,0.25);}
  button:active{transform:translateY(0);box-shadow:none;}
  .error{
    background:#F5E3E0;border:1px solid var(--red);color:var(--red);
    font-size:12.5px;padding:9px 11px;border-radius:2px;margin-bottom:16px;
  }
</style>
</head>
<body>
  <div class="bg-logo"></div>
  <div class="login-box">
    <h1>Staff Login</h1>
    <p class="sub">Barangay Document Services — Admin Panel</p>
    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
         <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <label>Username
        <input type="text" name="username" required autofocus>
      </label>
      <label>Password
        <input type="password" name="password" required>
      </label>
      <button type="submit">Log in</button>
    </form>
  </div>
</body>
</html>