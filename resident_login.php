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
<link rel="icon" type="image/jpeg" href="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>">
<title>Log in — Barangay Lawacamulag, San Jose, Tarlac</title>
<style nonce="<?= $csp_nonce ?>">
  :root{
    --paper:#EAF4FB; --paper-2:#E4F1E5; --ink:#173042; --ink-soft:#506A78;
    --blue:#1685C7; --blue-deep:#075184; --green:#23833B; --green-deep:#17632B;
    --red:#9B2B22; --gold:#E6B92E; --line:#B9D2DE;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:linear-gradient(135deg,var(--paper) 0%,#fff 52%,var(--paper-2) 100%);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden;}
  .bg-logo{
    position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:min(440px,90vw);aspect-ratio:1;border-radius:50%;background-image:url('<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>');
    background-size:contain;background-repeat:no-repeat;background-position:center;
    opacity:.08;z-index:0;pointer-events:none;
  }
  .box{position:relative;z-index:1;background:rgba(255,255,255,.97);border:1px solid var(--line);border-top:4px solid var(--green);border-radius:12px;max-width:400px;width:100%;padding:36px 34px;box-shadow:0 18px 48px rgba(7,81,132,.12);}
  .brand-mark{width:58px;height:58px;object-fit:cover;border-radius:50%;border:2px solid var(--gold);margin-bottom:20px;}
  .box > *{position:relative;z-index:1;}
  h1{font-size:26px;font-weight:700;letter-spacing:-.025em;color:var(--blue-deep);margin-bottom:5px;}
  .sub{font-size:14px;color:var(--ink-soft);margin-bottom:26px;line-height:1.5;}
  label{display:block;font-size:13px;font-weight:500;color:var(--ink);margin-bottom:18px;}
  input{width:100%;margin-top:7px;padding:12px 13px;border:1px solid var(--line);background:#fff;font-family:inherit;font-size:14px;border-radius:6px;transition:border-color .15s,box-shadow .15s;}
  input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(22,133,199,.15);}
  button{width:100%;background:var(--green);color:#fff;padding:13px;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;margin-top:4px;transition:background .15s,transform .15s,box-shadow .15s;}
  button:hover{background:var(--green-deep);transform:translateY(-1px);box-shadow:0 6px 14px rgba(35,131,59,.22);}
  button:active{transform:translateY(0);box-shadow:none;}
  .error{background:#FDEDEC;border:1px solid #E8B4AE;color:var(--red);font-size:13px;padding:11px 12px;border-radius:6px;margin-bottom:18px;}
  .foot-link{text-align:center;font-size:13px;color:var(--ink-soft);margin-top:22px;padding-top:18px;border-top:1px solid var(--line);}
  .foot-link a{color:var(--green-deep);font-weight:600;}
  .foot-link a:hover{color:var(--blue-deep);}
  @media(max-width:480px){body{padding:18px}.box{padding:28px 24px}}
</style>
</head>
<body>
  <div class="bg-logo"></div>
  <div class="box">
    <img class="brand-mark" src="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>" alt="Barangay Lawacamulag seal">
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