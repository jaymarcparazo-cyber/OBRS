<?php
session_start();
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
<title>Staff Login — Barangay Pitipiwpiwwiwiw</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F3EEE1; --paper-2:#EAE3D2; --ink:#211D16; --ink-soft:#5B564A;
    --blue:#1E3A5F; --blue-deep:#122845; --red:#9B2B22; --gold:#B08D3E; --line:#C9C1AC;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{
    background:var(--blue-deep);color:var(--ink);font-family:'Public Sans',sans-serif;
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
    position:relative;overflow:hidden;
  }
  .bg-logo{
    position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:640px;height:640px;border-radius:50%;background-image:url('logo.jpg');
    background-size:contain;background-repeat:no-repeat;background-position:center;
    opacity:10;z-index:0;pointer-events:none;
  }
  .login-box{position:relative;z-index:1;}
  .login-box{
    background:rgba(243,238,225,0.62);backdrop-filter:blur(1px);border:1px solid var(--line);max-width:360px;width:100%;
    padding:38px 34px;box-shadow:6px 6px 0 rgba(0,0,0,0.15);
  }
  h1{font-family:'Fraunces',serif;font-size:21px;text-align:center;color:var(--blue-deep);margin-bottom:4px;}
  .sub{text-align:center;font-size:12.5px;color:var(--ink-soft);margin-bottom:26px;}
  label{display:block;font-size:12px;color:var(--ink-soft);margin-bottom:16px;}
  input{
    width:100%;margin-top:6px;padding:11px 12px;border:1px solid var(--line);
    background:#fff;font-family:inherit;font-size:14px;border-radius:2px;
    transition:border-color .15s,box-shadow .15s;
  }
  input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(30,58,95,0.12);}
  button{
    width:100%;background:var(--red);color:#fff;padding:13px;border:none;border-radius:2px;
    font-size:15px;font-weight:500;cursor:pointer;margin-top:6px;
    transition:background .15s,transform .15s,box-shadow .15s;
  }
  button:hover{background:#7f2019;transform:translateY(-2px);box-shadow:0 6px 14px rgba(155,43,34,0.25);}
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
      <label>Username
        <input type="text" name="username" required autofocus>
      </label>
      <label>Password
        <input type="password" name="password" required>
      </label>
      <button type="submit">Log in</button>
    </form>
  </div>
<!-- Code injected by live-server -->
<script>
	// <![CDATA[  <-- For SVG support
	if ('WebSocket' in window) {
		(function () {
			function refreshCSS() {
				var sheets = [].slice.call(document.getElementsByTagName("link"));
				var head = document.getElementsByTagName("head")[0];
				for (var i = 0; i < sheets.length; ++i) {
					var elem = sheets[i];
					var parent = elem.parentElement || head;
					parent.removeChild(elem);
					var rel = elem.rel;
					if (elem.href && typeof rel != "string" || rel.length == 0 || rel.toLowerCase() == "stylesheet") {
						var url = elem.href.replace(/(&|\?)_cacheOverride=\d+/, '');
						elem.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_cacheOverride=' + (new Date().valueOf());
					}
					parent.appendChild(elem);
				}
			}
			var protocol = window.location.protocol === 'http:' ? 'ws://' : 'wss://';
			var address = protocol + window.location.host + window.location.pathname + '/ws';
			var socket = new WebSocket(address);
			socket.onmessage = function (msg) {
				if (msg.data == 'reload') window.location.reload();
				else if (msg.data == 'refreshcss') refreshCSS();
			};
			if (sessionStorage && !sessionStorage.getItem('IsThisFirstTime_Log_From_LiveServer')) {
				console.log('Live reload enabled.');
				sessionStorage.setItem('IsThisFirstTime_Log_From_LiveServer', true);
			}
		})();
	}
	else {
		console.error('Upgrade your browser. This Browser is NOT supported WebSocket for Live-Reloading.');
	}
	// ]]>
</script>
</body>
</html>