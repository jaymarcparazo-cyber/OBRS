<?php
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; "
     . "script-src 'self' 'nonce-$csp_nonce'; "
     . "style-src 'self' 'nonce-$csp_nonce'; "
     . "font-src 'self'; img-src 'self' data:; "
     . "form-action 'self'; frame-ancestors 'self'; "
     . "base-uri 'self'; object-src 'none'");
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header_remove('X-Powered-By');