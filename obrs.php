<?php
session_start();
$isLoggedIn = !empty($_SESSION['resident_id']);
$residentName = $_SESSION['resident_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Pitipiwpiwwiwiw — Document Request Services</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F3EEE1;
    --paper-2:#EAE3D2;
    --ink:#211D16;
    --ink-soft:#5B564A;
    --blue:#1E3A5F;
    --blue-deep:#122845;
    --red:#9B2B22;
    --gold:#B08D3E;
    --line:#C9C1AC;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{
    background:var(--paper);
    color:var(--ink);
    font-family:'Public Sans',sans-serif;
    line-height:1.55;
    -webkit-font-smoothing:antialiased;
  }
  h1,h2,h3,.display{font-family:'Fraunces',serif;}
  .mono{font-family:'IBM Plex Mono',monospace;}
  a{color:inherit;text-decoration:none;}
  img{max-width:100%;display:block;}
  .wrap{max-width:1120px;margin:0 auto;padding:0 32px;}

  /* HEADER */
  header{
    position:sticky;top:0;z-index:50;
    background:rgba(243,238,225,0.92);
    backdrop-filter:blur(6px);
    border-bottom:1px solid var(--line);
  }
  .nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:18px 32px;max-width:1120px;margin:0 auto;
  }
  .brand{display:flex;align-items:center;gap:12px;}
  .seal-mark{
    width:38px;
    height:38px;
    border-radius:50%;
    object-fit:cover;
    flex-shrink:0;
  }
  .brand-text{line-height:1.2;}
  .brand-text .eyebrow{font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:var(--ink-soft);}
  .brand-text .name{font-family:'Fraunces',serif;font-weight:600;font-size:17px;}
  nav.links{display:flex;gap:32px;font-size:14px;font-weight:500;}
  nav.links a{color:var(--ink-soft);position:relative;padding-bottom:2px;transition:color .15s;}
  nav.links a::after{
    content:"";position:absolute;left:0;bottom:-2px;width:0;height:1px;
    background:var(--gold);transition:width .2s ease;
  }
  nav.links a:hover{color:var(--ink);}
  nav.links a:hover::after{width:100%;}
  .nav-cta{
    background:var(--blue);color:var(--paper);
    padding:10px 20px;border-radius:2px;font-size:14px;font-weight:500;
    border:1px solid var(--blue);
    cursor:pointer;
  }
  .nav-cta:hover{background:var(--blue-deep);}
  .mobile-only{display:none;}

  .notif-wrap{position:relative;display:flex;align-items:center;}
  .notif-bell{
    background:none;border:none;font-size:19px;cursor:pointer;position:relative;
    padding:6px;line-height:1;
  }
  .notif-badge{
    position:absolute;top:0;right:0;background:var(--red);color:#fff;
    font-size:10px;font-weight:600;border-radius:999px;min-width:16px;height:16px;
    display:flex;align-items:center;justify-content:center;padding:0 3px;
  }
  .notif-dropdown{
    display:none;position:absolute;top:calc(100% + 10px);right:0;width:320px;
    background:#FBF9F2;border:1px solid var(--line);box-shadow:0 10px 28px rgba(0,0,0,0.15);
    z-index:80;
  }
  .notif-dropdown.open{display:block;}
  .notif-header{
    padding:12px 16px;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;
    color:var(--ink-soft);border-bottom:1px solid var(--line);background:var(--paper-2);
  }
  .notif-list{max-height:320px;overflow-y:auto;}
  .notif-item{
    padding:12px 16px;font-size:13px;border-bottom:1px solid var(--line);color:var(--ink);
  }
  .notif-item:last-child{border-bottom:none;}
  .notif-item.unread{background:#EFF4F9;}
  .notif-time{font-size:11px;color:var(--ink-soft);margin-top:3px;}
  .notif-empty{padding:24px 16px;text-align:center;font-size:13px;color:var(--ink-soft);}

  /* HERO */
  .hero{padding:88px 0 72px;}
  .hero-grid{display:grid;grid-template-columns:1.05fr 0.95fr;gap:64px;align-items:center;}
  .eyebrow-tag{
    display:inline-flex;align-items:center;gap:8px;
    font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:var(--red);
    font-weight:600;margin-bottom:20px;
  }
  .eyebrow-tag::before{content:"";width:6px;height:6px;background:var(--red);border-radius:50%;display:inline-block;}
  h1.headline{
    font-size:47px;font-weight:600;line-height:1.08;letter-spacing:-0.01em;
    margin-bottom:22px;color:var(--blue-deep);
  }
  .headline em{font-style:italic;color:var(--red);}
  .sub{font-size:17px;color:var(--ink-soft);max-width:440px;margin-bottom:34px;}
  .cta-row{display:flex;gap:14px;align-items:center;flex-wrap:wrap;}
  .btn-primary{
    background:var(--red);color:#fff;padding:14px 26px;border-radius:2px;
    font-size:15px;font-weight:500;border:1px solid var(--red);
    transition:background .15s,transform .15s,box-shadow .15s;
    cursor:pointer;
  }
  .btn-primary:hover{background:#7f2019;transform:translateY(-2px);box-shadow:0 6px 14px rgba(155,43,34,0.28);}
  .btn-primary:active{transform:translateY(0);box-shadow:none;}
  .btn-secondary{
    padding:14px 22px;border-radius:2px;font-size:15px;font-weight:500;
    border:1px solid var(--ink); color:var(--ink);
    cursor:pointer; background:transparent;
    transition:background .15s,transform .15s;
  }
  .btn-secondary:hover{background:var(--paper-2);transform:translateY(-2px);}
  .btn-secondary:active{transform:translateY(0);}

  /* Certificate mock + stamp */
  .cert-stage{display:flex;justify-content:center;align-items:center;}
  .cert{
    position:relative;width:100%;max-width:400px;
    background:#FBF9F2;border:1px solid var(--line);
    padding:34px 32px 40px;
    box-shadow:6px 6px 0 var(--paper-2), 0 1px 2px rgba(0,0,0,0.08);
  }
  .cert::before{
    content:"";position:absolute;inset:8px;border:1px solid var(--gold);opacity:0.5;pointer-events:none;
  }
  .cert-head{text-align:center;margin-bottom:22px;}
  .cert-head .rep{font-size:10px;letter-spacing:0.12em;text-transform:uppercase;color:var(--ink-soft);}
  .cert-head .office{font-family:'Fraunces',serif;font-weight:600;font-size:15px;color:var(--blue);margin-top:4px;}
  .cert-title{font-family:'Fraunces',serif;font-size:21px;font-weight:600;text-align:center;margin:14px 0 20px;color:var(--ink);}
  .cert-row{display:flex;justify-content:space-between;font-size:12px;padding:7px 0;border-bottom:1px dashed var(--line);color:var(--ink-soft);}
  .cert-row span:last-child{color:var(--ink);font-weight:500;}
  .cert-foot{margin-top:24px;display:flex;justify-content:space-between;align-items:flex-end;}
  .sig-line{font-size:10px;color:var(--ink-soft);}
  .sig-line .line{width:100px;border-bottom:1px solid var(--ink-soft);margin-bottom:5px;height:18px;}
  .stamp{
    position:absolute;right:26px;bottom:34px;
    width:112px;height:112px;border-radius:50%;
    border:3px solid var(--red);color:var(--red);
    display:flex;align-items:center;justify-content:center;flex-direction:column;
    font-family:'Fraunces',serif;font-weight:600;letter-spacing:0.03em;
    transform:rotate(-14deg) scale(0);
    opacity:0;
    animation: stamp-in 0.4s cubic-bezier(.2,1.4,.4,1) 0.7s forwards;
    mix-blend-mode:multiply;
  }
  .stamp .a{font-size:16px;line-height:1;}
  .stamp .b{font-size:8px;letter-spacing:0.15em;margin-top:3px;}
  @keyframes stamp-in{
    0%{transform:rotate(-14deg) scale(2.4);opacity:0;}
    60%{transform:rotate(-14deg) scale(0.94);opacity:1;}
    100%{transform:rotate(-14deg) scale(1);opacity:1;}
  }
  .ticker{
    border-top:1px solid var(--line);border-bottom:1px solid var(--line);
    padding:13px 0;background:var(--paper-2);
  }
  .ticker .wrap{display:flex;align-items:center;justify-content:center;gap:10px;font-size:13px;color:var(--ink-soft);}
  .ticker .mono{color:var(--blue);font-weight:500;}
  .pulse{width:7px;height:7px;background:var(--red);border-radius:50%;flex-shrink:0;animation:pulse 1.8s infinite;}
  @keyframes pulse{0%,100%{opacity:1;}50%{opacity:0.25;}}

  /* SECTION HEADERS */
  section{padding:80px 0;}
  .reveal{opacity:0;transform:translateY(18px);transition:opacity .6s ease,transform .6s ease;}
  .reveal.in-view{opacity:1;transform:translateY(0);}
  .sec-label{font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:var(--red);font-weight:600;margin-bottom:12px;}
  .sec-title{font-size:32px;font-weight:600;color:var(--blue-deep);margin-bottom:14px;max-width:560px;}
  .sec-sub{color:var(--ink-soft);max-width:520px;font-size:15.5px;margin-bottom:48px;}

  /* SERVICES - folder tabs, now interactive buttons */
  .services-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
  .doc-card{
    background:#FBF9F2;border:1px solid var(--line);position:relative;
    padding:26px 22px 22px;transition:border-color .15s,transform .15s,box-shadow .15s;
    cursor:pointer;
    text-align:left;
    font-family:inherit;
    color:inherit;
  }
  .doc-card::before{
    content:attr(data-tab);position:absolute;top:-11px;left:22px;
    background:var(--blue);color:var(--paper);
    font-size:10px;letter-spacing:0.06em;text-transform:uppercase;font-weight:600;
    padding:4px 10px;
  }
  .doc-card:hover{border-color:var(--blue);transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,0.1);}
  .doc-card:active{transform:translateY(0);}
  .doc-card:focus-visible{
    outline:2px solid var(--blue);
    outline-offset:3px;
  }
  .doc-card h3{font-size:17px;font-weight:600;margin:8px 0 8px;color:var(--ink);}
  .doc-card p{font-size:13.5px;color:var(--ink-soft);margin-bottom:14px;}
  .doc-meta{display:flex;justify-content:space-between;font-size:11.5px;color:var(--ink-soft);border-top:1px dashed var(--line);padding-top:12px;}
  .doc-meta .mono{color:var(--blue);}

  /* HOW IT WORKS - ticket stubs */
  .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid var(--line);}
  .step{
    padding:32px 26px;position:relative;background:#FBF9F2;
    border-right:1px dashed var(--line);
    transition:background .15s;
  }
  .step:hover{background:var(--paper-2);}
  .step:last-child{border-right:none;}
  .step-num{font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--red);letter-spacing:0.06em;margin-bottom:14px;transition:letter-spacing .2s;}
  .step:hover .step-num{letter-spacing:0.1em;}
  .step h3{font-size:17px;font-weight:600;margin-bottom:8px;color:var(--blue-deep);}
  .step p{font-size:14px;color:var(--ink-soft);}
  .step::after{
    content:"";position:absolute;top:0;bottom:0;right:-1px;width:1px;
  }

  /* TRACK */
  .track-panel{
    background:var(--blue-deep);color:var(--paper);
    padding:56px 48px;display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;
  }
  .track-panel h2{color:#fff;font-size:27px;margin-bottom:12px;}
  .track-panel p{color:#C9D3E0;font-size:14.5px;max-width:380px;}
  .track-form{display:flex;flex-direction:column;gap:12px;}
  .track-form label{font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#9FB1C9;}
  .track-input-row{display:flex;gap:10px;}
  .track-input-row input{
    flex:1;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.25);
    color:#fff;padding:13px 14px;font-family:'IBM Plex Mono',monospace;font-size:14px;border-radius:2px;
  }
  .track-input-row input::placeholder{color:#7C8CA3;}
  .track-input-row button{
    background:var(--gold);color:var(--blue-deep);border:none;padding:0 22px;
    font-weight:600;font-size:14px;border-radius:2px;cursor:pointer;
  }

  /* FOOTER */
  footer{background:var(--paper-2);border-top:1px solid var(--line);padding:56px 0 28px;}
  .foot-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:48px;margin-bottom:40px;}
  .foot-brand{display:flex;gap:12px;margin-bottom:14px;}
  .foot-col h4{font-size:12px;text-transform:uppercase;letter-spacing:0.06em;color:var(--ink-soft);margin-bottom:14px;}
  .foot-col a, .foot-col p{display:block;font-size:14px;color:var(--ink-soft);margin-bottom:9px;}
  .foot-col a:hover{color:var(--blue);}
  .foot-bottom{border-top:1px solid var(--line);padding-top:22px;display:flex;justify-content:space-between;font-size:12.5px;color:var(--ink-soft);}

  /* REQUEST MODAL */
  .modal-overlay{
    position:fixed;inset:0;background:rgba(18,40,69,0.55);
    display:none;align-items:center;justify-content:center;z-index:100;padding:20px;
  }
  .modal-overlay.open{display:flex;}
  .modal-box{
    background:#FBF9F2;border:1px solid var(--line);max-width:420px;width:100%;
    padding:32px;position:relative;box-shadow:6px 6px 0 var(--paper-2);
    max-height:90vh;overflow-y:auto;
  }
  .modal-close{
    position:absolute;top:14px;right:16px;background:none;border:none;
    font-size:22px;color:var(--ink-soft);cursor:pointer;line-height:1;
    width:28px;height:28px;
  }
  .modal-close:hover{color:var(--ink);}
  .modal-desc{font-size:13.5px;color:var(--ink-soft);margin:8px 0 20px;}
  .modal-box h3{font-size:20px;font-weight:600;color:var(--blue-deep);}
  #requestForm label{
    display:block;font-size:12px;color:var(--ink-soft);margin-bottom:14px;
  }
  #requestForm input{
    width:100%;margin-top:6px;padding:11px 12px;border:1px solid var(--line);
    background:#fff;font-family:'Public Sans',sans-serif;font-size:14px;border-radius:2px;
  }
  #requestForm input:focus{outline:2px solid var(--blue);outline-offset:1px;}
  #requestForm input[type="file"]{padding:8px;font-size:12.5px;}
  .file-hint{font-size:11.5px;color:var(--ink-soft);margin:-8px 0 14px;}
  #requestForm label.checkbox-label{
    display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink);
    margin-bottom:14px;cursor:pointer;
  }
  .checkbox-label input[type="checkbox"]{width:auto;margin:0;cursor:pointer;}
  .beneficiary-fields{
    background:var(--paper-2);border:1px solid var(--line);border-radius:2px;
    padding:14px 14px 2px;margin-bottom:16px;
  }
  .form-error{
    background:#F5E3E0;border:1px solid var(--red);color:var(--red);
    font-size:12.5px;padding:9px 11px;border-radius:2px;margin-bottom:14px;
  }
  .modal-success{text-align:center;padding:12px 0 4px;}
  .success-check{
    width:52px;height:52px;border-radius:50%;background:var(--blue);color:#fff;
    display:flex;align-items:center;justify-content:center;font-size:24px;
    margin:0 auto 18px;
  }
  .modal-success h3{margin-bottom:4px;}
  .modal-success p{font-size:13.5px;color:var(--ink-soft);}
  .ref-display{
    font-size:22px;font-weight:500;color:var(--blue-deep);
    background:var(--paper-2);border:1px dashed var(--line);
    padding:12px;margin:10px 0;letter-spacing:0.04em;
  }
  .success-note{font-size:12px;margin-bottom:6px;}
  .track-result{
    margin-top:16px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.25);
    padding:16px 18px;font-size:13.5px;
  }
  .track-result .tr-status{
    display:inline-block;font-size:11px;letter-spacing:0.05em;text-transform:uppercase;
    padding:3px 9px;background:var(--gold);color:var(--blue-deep);font-weight:600;margin-bottom:8px;
  }
  .track-result .tr-row{display:flex;justify-content:space-between;color:#C9D3E0;padding:3px 0;}
  .track-result .tr-row span:last-child{color:#fff;font-family:'IBM Plex Mono',monospace;font-size:12.5px;}
  .track-error{color:#F0B8B0;font-size:13px;margin-top:12px;}

  @media (max-width:860px){
    .hero-grid{grid-template-columns:1fr;}
    .cert-stage{margin-top:20px;}
    nav.links{display:none;}
    .services-grid{grid-template-columns:1fr;}
    .steps{grid-template-columns:1fr;}
    .step{border-right:none;border-bottom:1px dashed var(--line);}
    .track-panel{grid-template-columns:1fr;padding:36px 24px;}
    .foot-grid{grid-template-columns:1fr;gap:28px;}
    h1.headline{font-size:34px;}
  }
</style>
</head>
<body>

<header>
  <div class="nav">
    <div class="brand">
      <img class="seal-mark" src="logo.jpg" alt="Official Barangay Seal">
      <div class="brand-text">
        <div class="eyebrow">Barangay Pitipiwpiwwiwiw</div>
        <div class="name">Document Services</div>
      </div>
    </div>
    <nav class="links">
      <a href="#services">Services</a>
      <a href="#how-it-works">How it works</a>
      <a href="#track">Track a request</a>
      <a href="#contact">Contact</a>
      <?php if ($isLoggedIn): ?>
        <a href="my_requests.php">My requests</a>
      <?php endif; ?>
    </nav>
    <?php if ($isLoggedIn): ?>
      <div class="notif-wrap">
        <button class="notif-bell" id="notifBell" aria-label="Notifications">
          🔔<span class="notif-badge" id="notifBadge" hidden>0</span>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header">Notifications</div>
          <div id="notifList" class="notif-list"></div>
        </div>
      </div>
      <a href="#services" class="nav-cta">Request a document</a>
    <?php else: ?>
      <a href="resident_login.php" class="nav-cta">Log in to request</a>
    <?php endif; ?>
  </div>
</header>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <div class="eyebrow-tag">Now accepting requests online</div>
      <h1 class="headline">Skip the line.<br>Get your barangay documents <em>without leaving home.</em></h1>
      <p class="sub">Request a barangay clearance, certificate, or permit online. We process it, you get notified, and you claim it — no more waiting at the front desk.</p>
      <div class="cta-row">
        <a href="#services" class="btn-primary">Start a request</a>
        <a href="#track" class="btn-secondary">Track a request</a>
      </div>
    </div>
    <div class="cert-stage">
      <div class="cert">
        <div class="cert-head">
          <div class="rep">Republic of Philippines</div>
          <div class="office">Barangay Pitipiwpiwwiwiw</div>
        </div>
        <h3 class="cert-title">Barangay Clearance</h3>
        <div class="cert-row"><span>Requested by</span><span>Rodigero, Jerald</span></div>
        <div class="cert-row"><span>Purpose</span><span>Employment</span></div>
        <div class="cert-row"><span>Reference no.</span><span class="mono">BC-2026-0182</span></div>
        <div class="cert-row"><span>Date issued</span><span>July 8, 2026</span></div>
        <div class="cert-foot">
          <div class="sig-line"><div class="line"></div>Barangay Chairman</div>
        </div>
        <div class="stamp">
          <div class="a">APPROVED</div>
          <div class="b">Pitipiwpiwwiwiw</div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="ticker">
  <div class="wrap">
    <span class="pulse"></span>
    <span>Now serving queue no.</span>
    <span class="mono">0182</span>
    <span>&middot; average processing time: 1 business day</span>
  </div>
</div>

<section id="services" class="reveal">
  <div class="wrap">
    <div class="sec-label">Services</div>
    <h2 class="sec-title">Every document your barangay office issues, in one place</h2>
    <p class="sec-sub">Choose the document you need. Fill out the form once, upload requirements, and we'll handle the rest.</p>
    <div class="services-grid">

      <div class="doc-card" data-tab="Clearance" role="button" tabindex="0">
        <h3>Barangay clearance</h3>
        <p>Required for job applications, business permits, and most government transactions.</p>
        <div class="doc-meta"><span>Processing</span><span class="mono">1 day</span></div>
      </div>

      <div class="doc-card" data-tab="Residency" role="button" tabindex="0">
        <h3>Certificate of residency</h3>
        <p>Confirms you live within the barangay. Needed for school enrollment and IDs.</p>
        <div class="doc-meta"><span>Processing</span><span class="mono">1 day</span></div>
      </div>

      <div class="doc-card" data-tab="Indigency" role="button" tabindex="0">
        <h3>Certificate of indigency</h3>
        <p>For residents applying for financial aid, medical assistance, or scholarships.</p>
        <div class="doc-meta"><span>Processing</span><span class="mono">1 day</span></div>
      </div>

      <div class="doc-card" data-tab="Business" role="button" tabindex="0">
        <h3>Business permit endorsement</h3>
        <p>The barangay-level clearance required before applying for a mayor's permit.</p>
        <div class="doc-meta"><span>Processing</span><span class="mono">2 days</span></div>
      </div>

      <div class="doc-card" data-tab="Barangay ID" role="button" tabindex="0">
        <h3>Barangay ID</h3>
        <p>Official resident identification issued by the barangay office.</p>
        <div class="doc-meta"><span>Processing</span><span class="mono">3 days</span></div>
      </div>

      <div class="doc-card" data-tab="First Time Job-Seeker" role="button" tabindex="0">
        <h3>First-Time Jobseeker Certificate</h3>
        <p>Allows qualified first-time jobseekers to get certain government-issued documents without paying the usual fees.</p>
        <div class="doc-meta"><span>Processing</span><span class="mono">1 day</span></div>
      </div>

    </div>
  </div>
</section>

<section id="how-it-works" class="reveal">
  <div class="wrap">
    <div class="sec-label">Process</div>
    <h2 class="sec-title">The same three steps as the front desk — just from your phone</h2>
    <p class="sec-sub">Every request moves through the office in order, exactly like a paper form would.</p>
    <div class="steps">
      <div class="step">
        <div class="step-num mono">QUEUE NO. 001</div>
        <h3>Submit your request</h3>
        <p>Pick a document, fill in your details, and upload any required ID or supporting file.</p>
      </div>
      <div class="step">
        <div class="step-num mono">QUEUE NO. 002</div>
        <h3>Barangay staff review it</h3>
        <p>Your request is verified against barangay records. You'll get a status update by SMS or email.</p>
      </div>
      <div class="step">
        <div class="step-num mono">QUEUE NO. 003</div>
        <h3>Download or claim</h3>
        <p>Digitally signed documents are ready to download. Others are ready for pickup at the office.</p>
      </div>
    </div>
  </div>
</section>

<section id="track" class="reveal">
  <div class="wrap">
    <div class="track-panel">
      <div>
        <h2 class="display">Already submitted a request?</h2>
        <p>Enter your reference number to see where it is in the queue — no account needed.</p>
      </div>
      <div class="track-form">
        <label for="ref">Reference number</label>
        <div class="track-input-row">
          <input id="ref" type="text" placeholder="e.g. BC-2026-0182">
          <button type="button" id="trackBtn">Track</button>
        </div>
        <div id="trackResult"></div>
      </div>
    </div>
  </div>
</section>

<footer id="contact">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-col">
        <div class="foot-brand">
          <img class="seal-mark" src="logo.jpg" alt="Official Barangay Seal">
          <div class="brand-text">
            <div class="name">Barangay Pitipiwpiwwiwiw</div>
            <div class="eyebrow">Document services</div>
          </div>
        </div>
        <p>Barangay Hall, Pitipiwpiwwiwiw, Philippines</p>
        <p>Open Monday–Friday, 8:00 AM–5:00 PM</p>
      </div>
      <div class="foot-col">
        <h4>Office</h4>
        <a href="#services">Request a document</a>
        <a href="#track">Track a request</a>
        <a href="#how-it-works">How it works</a>
      </div>
      <div class="foot-col">
        <h4>Contact</h4>
        <a href="tel:+630000000000">(02) 87000</a>
        <a href="mailto:office@barangaypitipiwpiwwiwiw.gov.ph">office@barangaypitipiwpiwwiwiw.gov.ph</a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>&copy; 2026 Barangay Pitipiwpiwwiwiw. All requests are subject to verification.</span>
      <span class="mono">Rep. of the Philippines</span>
    </div>
  </div>
</footer>

<!-- REQUEST MODAL -->
<div class="modal-overlay" id="requestModal">
  <div class="modal-box">
    <button class="modal-close" id="modalClose" aria-label="Close">&times;</button>
    <div class="sec-label" id="modalTag">CLEARANCE</div>
    <h3 id="modalTitle">Barangay clearance</h3>
    <p class="modal-desc" id="modalDesc"></p>
    <form id="requestForm" enctype="multipart/form-data">
      <input type="hidden" name="document_code" id="modalDocCode">
      <label>Purpose
        <input type="text" name="purpose" required placeholder="e.g. Employment, school enrollment">
      </label>

      <label class="checkbox-label">
        <input type="checkbox" id="onBehalfToggle" name="on_behalf_of" value="1">
        I'm requesting this on behalf of someone else
      </label>

      <div id="beneficiaryFields" class="beneficiary-fields" hidden>
        <label>Full name of the person this document is for
          <input type="text" name="beneficiary_name" placeholder="e.g. Maria Dela Cruz">
        </label>
        <label>Civil status
          <select name="beneficiary_civil_status">
            <option value="">Select…</option>
            <option value="single">Single</option>
            <option value="married">Married</option>
            <option value="widowed">Widowed</option>
            <option value="separated">Separated</option>
            <option value="divorced">Divorced</option>
          </select>
        </label>
        <label>Your relationship to them
          <input type="text" name="relationship" placeholder="e.g. Parent, Child, Sibling, Guardian">
        </label>
      </div>

      <label>Valid ID or supporting document
        <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" required>
      </label>
      <p class="file-hint">JPG, PNG, or PDF. Max 5MB.</p>

      <p id="formError" class="form-error" hidden></p>

      <button type="submit" class="btn-primary" style="width:100%;margin-top:8px;">Submit request</button>
    </form>

    <div id="modalSuccess" class="modal-success" hidden>
      <div class="success-check">&#10003;</div>
      <h3>Request submitted</h3>
      <p>Your reference number is</p>
      <div class="ref-display mono" id="refDisplay"></div>
      <p class="success-note">Save this number — you'll need it to track your request below.</p>
      <button type="button" class="btn-primary" style="width:100%;margin-top:8px;" id="successClose">Done</button>
    </div>
  </div>
</div>

<script>
  const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

  const docData = {
    "Clearance": {code:"BC",  title:"Barangay clearance", desc:"Required for job applications, business permits, and most government transactions."},
    "Residency": {code:"COR", title:"Certificate of residency", desc:"Confirms you live within the barangay. Needed for school enrollment and IDs."},
    "Indigency": {code:"COI", title:"Certificate of indigency", desc:"For residents applying for financial aid, medical assistance, or scholarships."},
    "Business": {code:"BPE", title:"Business permit endorsement", desc:"The barangay-level clearance required before applying for a mayor's permit."},
    "Barangay ID": {code:"BID", title:"Barangay ID", desc:"Official resident identification issued by the barangay office."},
    "First Time Job-Seeker": {code:"FTJ", title:"First-time jobseeker certificate", desc:"Exempts first-time jobseekers from fees on government transactions, per RA 11261."}
  };

  const modal = document.getElementById('requestModal');
  const modalTag = document.getElementById('modalTag');
  const modalTitle = document.getElementById('modalTitle');
  const modalDesc = document.getElementById('modalDesc');
  const modalDocCode = document.getElementById('modalDocCode');
  const requestForm = document.getElementById('requestForm');
  const modalSuccess = document.getElementById('modalSuccess');
  const formError = document.getElementById('formError');
  const refDisplay = document.getElementById('refDisplay');

  function openModal(tab){
    if (!isLoggedIn) {
      window.location.href = 'resident_login.php';
      return;
    }
    const data = docData[tab];
    if(!data) return;
    modalTag.textContent = tab.toUpperCase();
    modalTitle.textContent = data.title;
    modalDesc.textContent = data.desc;
    modalDocCode.value = data.code;
    formError.hidden = true;
    requestForm.hidden = false;
    modalSuccess.hidden = true;
    document.getElementById('onBehalfToggle').checked = false;
    document.getElementById('beneficiaryFields').hidden = true;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    modal.querySelector('input[type="text"]')?.focus();
  }

  function closeModal(){
    modal.classList.remove('open');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.doc-card').forEach(card => {
    card.addEventListener('click', () => openModal(card.dataset.tab));
    card.addEventListener('keydown', (e) => {
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        openModal(card.dataset.tab);
      }
    });
  });

  document.getElementById('modalClose').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if(e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if(e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

  const onBehalfToggle = document.getElementById('onBehalfToggle');
  const beneficiaryFields = document.getElementById('beneficiaryFields');
  onBehalfToggle.addEventListener('change', () => {
    const on = onBehalfToggle.checked;
    beneficiaryFields.hidden = !on;
    beneficiaryFields.querySelector('input[name="beneficiary_name"]').required = on;
    beneficiaryFields.querySelector('input[name="relationship"]').required = on;
  });

  requestForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    formError.hidden = true;

    const submitBtn = requestForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';

    try {
      const res = await fetch('submit_request.php', {
        method: 'POST',
        body: new FormData(requestForm)
      });
      const data = await res.json();

      if (data.success) {
        refDisplay.textContent = data.reference_number;
        requestForm.hidden = true;
        modalSuccess.hidden = false;
        requestForm.reset();
      } else {
        formError.textContent = data.error || 'Something went wrong. Please try again.';
        formError.hidden = false;
      }
    } catch (err) {
      formError.textContent = 'Could not reach the server. Please check your connection and try again.';
      formError.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit request';
    }
  });

  document.getElementById('successClose').addEventListener('click', closeModal);

  // Notifications
  if (isLoggedIn) {
    const notifBell = document.getElementById('notifBell');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge = document.getElementById('notifBadge');
    const notifList = document.getElementById('notifList');

    function timeAgo(dateStr) {
      const diffMs = Date.now() - new Date(dateStr.replace(' ', 'T'));
      const mins = Math.floor(diffMs / 60000);
      if (mins < 1) return 'just now';
      if (mins < 60) return mins + 'm ago';
      const hrs = Math.floor(mins / 60);
      if (hrs < 24) return hrs + 'h ago';
      return Math.floor(hrs / 24) + 'd ago';
    }

    async function loadNotifications() {
      try {
        const res = await fetch('get_notifications.php');
        const data = await res.json();
        if (!data.success) return;

        if (data.unread_count > 0) {
          notifBadge.textContent = data.unread_count;
          notifBadge.hidden = false;
        } else {
          notifBadge.hidden = true;
        }

        if (data.notifications.length === 0) {
          notifList.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
        } else {
          notifList.innerHTML = data.notifications.map(n => `
            <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}">
              ${n.message}
              <div class="notif-time">${timeAgo(n.created_at)}</div>
            </div>
          `).join('');
        }
      } catch (err) { /* fail silently, non-critical UI */ }
    }

    notifBell.addEventListener('click', async (e) => {
      e.stopPropagation();
      const opening = !notifDropdown.classList.contains('open');
      notifDropdown.classList.toggle('open');
      if (opening) {
        await loadNotifications();
        if (!notifBadge.hidden) {
          await fetch('mark_notifications_read.php', { method: 'POST' });
          notifBadge.hidden = true;
        }
      }
    });

    document.addEventListener('click', (e) => {
      if (!notifDropdown.contains(e.target)) notifDropdown.classList.remove('open');
    });

    loadNotifications(); // populate badge count on page load
  }

  // Track a request
  const trackBtn = document.getElementById('trackBtn');
  const trackResult = document.getElementById('trackResult');
  const refInput = document.getElementById('ref');

  const statusLabels = {
    pending: 'Pending review',
    processing: 'Processing',
    approved: 'Approved',
    rejected: 'Rejected',
    ready_for_pickup: 'Ready for pickup',
    released: 'Released',
    cancelled: 'Cancelled'
  };

  async function trackRequest(){
    const ref = refInput.value.trim();
    trackResult.innerHTML = '';
    if (!ref) {
      trackResult.innerHTML = '<p class="track-error">Please enter a reference number.</p>';
      return;
    }

    trackBtn.disabled = true;
    trackBtn.textContent = '...';

    try {
      const res = await fetch('track_request.php?ref=' + encodeURIComponent(ref));
      const data = await res.json();

      if (data.success) {
        const r = data.request;
        trackResult.innerHTML = `
          <div class="track-result">
            <div class="tr-status">${statusLabels[r.status] || r.status}</div>
            <div class="tr-row"><span>Document</span><span>${r.document_name}</span></div>
            <div class="tr-row"><span>Requested by</span><span>${r.first_name} ${r.last_name}</span></div>
            <div class="tr-row"><span>Reference no.</span><span>${r.reference_number}</span></div>
            <div class="tr-row"><span>Last updated</span><span>${r.updated_at}</span></div>
          </div>`;
      } else {
        trackResult.innerHTML = `<p class="track-error">${data.error || 'Request not found.'}</p>`;
      }
    } catch (err) {
      trackResult.innerHTML = '<p class="track-error">Could not reach the server. Please try again.</p>';
    } finally {
      trackBtn.disabled = false;
      trackBtn.textContent = 'Track';
    }
  }

  trackBtn.addEventListener('click', trackRequest);
  refInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') trackRequest(); });
  // Scroll-reveal for section entrances
  const revealEls = document.querySelectorAll('.reveal');
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  revealEls.forEach(el => revealObserver.observe(el));
</script>

</body>
</html>