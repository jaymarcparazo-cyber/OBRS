<?php
require_once __DIR__ . '/session_init.php';
$isLoggedIn = !empty($_SESSION['resident_id']);
$residentName = $_SESSION['resident_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= htmlspecialchars(app_asset_url('obrs.php'), ENT_QUOTES) ?>">
<link rel="icon" type="image/jpeg" href="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>">
<title>Barangay Lawacamulag, San Jose, Tarlac — Document Request Services</title>
<link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('obrs.css'), ENT_QUOTES) ?>">
</head>
<body>

<header id="top">
  <div class="nav">
    <a class="brand home-logo-link" href="<?= htmlspecialchars(app_asset_url('obrs.php'), ENT_QUOTES) ?>#top" aria-label="Home">
      <img class="seal-mark" src="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>" alt="Official Barangay Seal">
      <div class="brand-text">
        <div class="eyebrow">Barangay Lawacamulag, San Jose, Tarlac</div>
        <div class="name">Document Services</div>
      </div>
    </a>
    <button class="nav-toggle" id="navToggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="primaryNav">
      <span></span><span></span><span></span>
    </button>
    <div class="nav-menu" id="primaryNav">
      <nav class="links" aria-label="Main navigation">
        <a href="#services">Services</a>
        <a href="#how-it-works">How it works</a>
        <a href="#track">Track a request</a>
        <a href="#contact">Contact</a>
        <?php if ($isLoggedIn): ?>
          <a href="my_requests.php">My requests</a>
        <?php endif; ?>
      </nav>
      <div class="nav-actions">
        <?php if ($isLoggedIn): ?>
          <div class="notif-wrap">
            <button class="notif-bell" id="notifBell" aria-label="Notifications">
              &#128276;<span class="notif-badge" id="notifBadge" hidden>0</span>
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
    </div>
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
          <div class="office">Barangay Lawacamulag, San Jose, Tarlac</div>
        </div>
        <h3 class="cert-title">Barangay Clearance</h3>
        <div class="cert-row"><span>Requested by</span><span>Resident</span></div>
        <div class="cert-row"><span>Purpose</span><span>Employment</span></div>
        <div class="cert-row"><span>Reference no.</span><span class="mono">SAMPLE-BC-0001</span></div>
        <div class="cert-row"><span>Date issued</span><span>July 8, 2026</span></div>
        <div class="cert-foot">
          <div class="sig-line"><div class="line"></div>Barangay Chairman</div>
        </div>
        <div class="stamp">
          <div class="a">APPROVED</div>
          <div class="b">Lawacamulag, San Jose, Tarlac</div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="ticker">
  <div class="wrap">
    <span class="pulse"></span>
    <span id="queueNowServingLabel">Now serving queue no.</span>
    <span class="mono" id="queueNowServingNumber">—</span>
    <span id="queueLastCalled" aria-live="polite"></span>
    <span id="queueWaitingCount">Loading today’s queue…</span>
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
          <input id="ref" type="text" placeholder="e.g. BC-2026-0001">
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
          <a class="home-logo-link" href="<?= htmlspecialchars(app_asset_url('obrs.php'), ENT_QUOTES) ?>#top" aria-label="Home">
            <img class="seal-mark" src="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>" alt="Official Barangay Seal">
          </a>
          <div class="brand-text">
            <div class="name">Barangay Lawacamulag, San Jose, Tarlac</div>
            <div class="eyebrow">Document services</div>
          </div>
        </div>
        <p>Barangay Hall, Lawacamulag, San Jose, Tarlac, Philippines</p>
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
        <a href="mailto:office@barangaylawacamulag.gov.ph">office@barangaylawacamulag.gov.ph</a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>&copy; 2026 Barangay Lawacamulag, San Jose, Tarlac. All requests are subject to verification.</span>
      <span class="mono">Rep. of the Philippines</span>
      <?php if ($isLoggedIn): ?>
        <a class="footer-logout" href="<?= htmlspecialchars(app_asset_url('resident_logout.php'), ENT_QUOTES) ?>">Log out</a>
      <?php endif; ?>
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
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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

      <label>Your government-issued ID image
        <input type="file" name="attachment" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required>
      </label>
      <p class="file-hint">Upload your own government-issued ID as a JPG or PNG (max 5MB). The system checks for government ID text before comparing its portrait with your camera selfie. Unrecognized documents can only be submitted for staff review.</p>

      <div class="identity-check">
        <p class="identity-explainer">The system checks for government-issued ID text, then compares your selfie with its portrait. An unrecognized document can only be submitted for staff review. A match does not prove that the ID itself is genuine.</p>
        <label class="checkbox-label" id="faceConsentLabel">
          <input type="checkbox" id="faceConsent" name="face_consent" value="1">
          <span>I consent to a one-time face comparison. The selfie is processed temporarily by this barangay server and is not retained with my request.</span>
        </label>
        <div class="identity-controls" id="identityControls">
          <button type="button" class="identity-secondary" id="startFaceScan" disabled>Start camera</button>
          <video id="faceVideo" autoplay muted playsinline aria-label="Camera preview for face comparison" hidden></video>
          <canvas id="faceCanvas" hidden></canvas>
          <button type="button" class="identity-secondary" id="captureFace" hidden>Capture and compare</button>
        </div>
        <button type="button" class="identity-review-btn" id="manualIdentityReview" hidden>Continue with staff review</button>
        <p class="identity-status" id="identityStatus" aria-live="polite">Choose a JPG or PNG ID image to continue.</p>
        <input type="hidden" name="identity_mode" id="identityMode">
      </div>

      <p id="formError" class="form-error" hidden></p>

      <button type="submit" class="btn-primary form-submit" id="submitRequest" disabled>Submit request</button>
    </form>

    <div id="modalSuccess" class="modal-success" hidden>
      <div class="success-check">&#10003;</div>
      <h3>Request submitted</h3>
      <p>Your reference number is</p>
      <div class="ref-display mono" id="refDisplay"></div>
      <p class="success-note">Your queue number is</p>
      <div class="ref-display mono" id="queueRefDisplay"></div>
      <p class="success-note">Save this number — you'll need it to track your request below.</p>
      <p class="identity-success-note" id="identitySubmissionNotice"></p>
      <button type="button" class="btn-primary form-submit" id="successClose">Done</button>
    </div>
  </div>
</div>

<script nonce="<?= $csp_nonce ?>">
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
  const queueRefDisplay = document.getElementById('queueRefDisplay');
  const navToggle = document.getElementById('navToggle');
  const primaryNav = document.getElementById('primaryNav');
  const idImageInput = requestForm.querySelector('[name="attachment"]');
  const identityMode = document.getElementById('identityMode');
  const faceConsent = document.getElementById('faceConsent');
  const startFaceScan = document.getElementById('startFaceScan');
  const captureFace = document.getElementById('captureFace');
  const faceVideo = document.getElementById('faceVideo');
  const faceCanvas = document.getElementById('faceCanvas');
  const manualIdentityReview = document.getElementById('manualIdentityReview');
  const identityStatus = document.getElementById('identityStatus');
  const submitRequest = document.getElementById('submitRequest');
  const identitySubmissionNotice = document.getElementById('identitySubmissionNotice');
  let faceCameraStream = null;
  let identityReady = false;

  function closeNavigation() {
    primaryNav.classList.remove('open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open navigation menu');
  }

  navToggle.addEventListener('click', () => {
    const isOpen = primaryNav.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', String(isOpen));
    navToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
  });

  primaryNav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', closeNavigation);
  });

  document.addEventListener('click', (e) => {
    if (!primaryNav.contains(e.target) && !navToggle.contains(e.target)) {
      closeNavigation();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 1080) closeNavigation();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeNavigation();
  });

  function openModal(tab){
    if (!isLoggedIn) {
      window.location.href = 'resident_login.php';
      return;
    }
    const data = docData[tab];
    if(!data) return;
    requestForm.reset();
    modalTag.textContent = tab.toUpperCase();
    modalTitle.textContent = data.title;
    modalDesc.textContent = data.desc;
    modalDocCode.value = data.code;
    formError.hidden = true;
    requestForm.hidden = false;
    modalSuccess.hidden = true;
    resetIdentityCheck();
    document.getElementById('onBehalfToggle').checked = false;
    document.getElementById('beneficiaryFields').hidden = true;
    document.getElementById('faceConsentLabel').hidden = false;
    document.getElementById('identityControls').hidden = false;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    modal.querySelector('input[type="text"]')?.focus();
  }

  function closeModal(){
    stopFaceCamera();
    modal.classList.remove('open');
    document.body.style.overflow = '';
  }

  function stopFaceCamera(){
    if (faceCameraStream) {
      faceCameraStream.getTracks().forEach(track => track.stop());
      faceCameraStream = null;
    }
    faceVideo.srcObject = null;
    faceVideo.hidden = true;
    captureFace.hidden = true;
    startFaceScan.hidden = false;
  }

  function resetIdentityCheck(){
    stopFaceCamera();
    identityReady = false;
    identityMode.value = '';
    submitRequest.disabled = true;
    manualIdentityReview.hidden = true;
    startFaceScan.disabled = !idImageInput.files.length || !faceConsent.checked;
    identityStatus.className = 'identity-status';
    identityStatus.textContent = idImageInput.files.length
        ? 'Agree to the one-time comparison, then start the camera.'
      : 'Choose a JPG or PNG ID image to continue.';
  }

  function setIdentityResult(mode, message){
    identityMode.value = mode;
    identityReady = mode === 'match' || mode === 'review';
    submitRequest.disabled = !identityReady;
    manualIdentityReview.hidden = true;
    identityStatus.className = `identity-status ${mode}`;
    identityStatus.textContent = message;
  }

  function allowManualReview(message){
    identityMode.value = '';
    identityReady = false;
    submitRequest.disabled = true;
    manualIdentityReview.hidden = false;
    identityStatus.className = 'identity-status unavailable';
    identityStatus.textContent = message;
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

  idImageInput.addEventListener('change', resetIdentityCheck);
  faceConsent.addEventListener('change', () => {
    resetIdentityCheck();
  });

  startFaceScan.addEventListener('click', async () => {
    if (!idImageInput.files.length || !faceConsent.checked) return;
    try {
      faceCameraStream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {facingMode: 'user', width: {ideal: 1280}, height: {ideal: 720}}
      });
      faceVideo.srcObject = faceCameraStream;
      faceVideo.hidden = false;
      startFaceScan.hidden = true;
      captureFace.hidden = false;
      identityStatus.textContent = 'Center your face in the camera, then capture it for comparison.';
    } catch (error) {
      allowManualReview('Camera access is unavailable. Automatic matching cannot run; staff can review your ID instead.');
    }
  });

  captureFace.addEventListener('click', async () => {
    if (!faceVideo.videoWidth || !faceVideo.videoHeight) {
      stopFaceCamera();
      allowManualReview('The camera is not ready. Automatic matching cannot run; staff can review your ID instead.');
      return;
    }

    faceCanvas.width = faceVideo.videoWidth;
    faceCanvas.height = faceVideo.videoHeight;
    faceCanvas.getContext('2d').drawImage(faceVideo, 0, 0);
    const selfieBlob = await new Promise(resolve => faceCanvas.toBlob(resolve, 'image/jpeg', 0.9));
    stopFaceCamera();

    if (!selfieBlob) {
      allowManualReview('The camera image could not be captured. Automatic matching cannot run; staff can review your ID instead.');
      return;
    }

    captureFace.disabled = true;
    identityStatus.textContent = 'Comparing the face with the ID image…';
    const verificationData = new FormData();
    verificationData.append('csrf_token', requestForm.elements.csrf_token.value);
    verificationData.append('face_consent', '1');
    verificationData.append('attachment', idImageInput.files[0]);
    verificationData.append('selfie', selfieBlob, 'selfie.jpg');
    idImageInput.disabled = true;
    faceConsent.disabled = true;
    startFaceScan.disabled = true;
    manualIdentityReview.disabled = true;
    onBehalfToggle.disabled = true;

    try {
      let response;
      try {
        response = await fetch('verify_identity.php', {method: 'POST', body: verificationData});
      } catch (error) {
        allowManualReview('The verification service could not be reached. Staff can review your ID instead.');
        return;
      }

      const result = await response.json();
      if (!response.ok || !result.success) {
        identityStatus.className = 'identity-status mismatch';
        identityStatus.textContent = result.error || 'The face comparison could not be completed. Check the ID image and try again.';
        return;
      }
      if (result.result === 'match') {
        setIdentityResult('match', result.message);
      } else if (result.result === 'mismatch') {
        setIdentityResult('mismatch', result.message);
      } else {
        allowManualReview(result.message || 'Automatic matching is unavailable. Staff can review your ID instead.');
      }
    } catch (error) {
      identityStatus.className = 'identity-status mismatch';
      identityStatus.textContent = 'The verification response was invalid. Please try the face check again.';
    } finally {
      idImageInput.disabled = false;
      faceConsent.disabled = false;
      startFaceScan.disabled = !idImageInput.files.length || !faceConsent.checked;
      onBehalfToggle.disabled = false;
      captureFace.disabled = false;
    }
  });

  manualIdentityReview.addEventListener('click', async () => {
    if (!idImageInput.files.length) return;
    stopFaceCamera();
    manualIdentityReview.disabled = true;
    identityStatus.textContent = 'Requesting staff review…';

    const reviewData = new FormData();
    reviewData.append('csrf_token', requestForm.elements.csrf_token.value);
    reviewData.append('manual_review', '1');
    reviewData.append('attachment', idImageInput.files[0]);

    try {
      const response = await fetch('verify_identity.php', {method: 'POST', body: reviewData});
      const result = await response.json();
      if (!response.ok || !result.success || result.result !== 'review') {
        throw new Error(result.error || 'Staff review authorization failed.');
      }
      setIdentityResult('review', result.message);
    } catch (error) {
      allowManualReview('Could not authorize staff review. Please try again.');
    } finally {
      manualIdentityReview.disabled = false;
    }
  });

  requestForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    formError.hidden = true;
    if (!identityReady || !['match', 'review'].includes(identityMode.value)) {
      formError.textContent = 'Complete the ID and face checks or obtain staff-review authorization before submitting.';
      formError.hidden = false;
      return;
    }

    submitRequest.disabled = true;
    submitRequest.textContent = 'Submitting…';

    try {
      const res = await fetch('submit_request.php', {
        method: 'POST',
        body: new FormData(requestForm)
      });
      const data = await res.json();

      if (data.success) {
        refDisplay.textContent = data.reference_number;
        queueRefDisplay.textContent = String(data.queue_number).padStart(4, '0');
        identitySubmissionNotice.textContent = data.identity_review_required
          ? 'Your ID document will be checked by staff before the request is processed.'
          : 'Government ID text was detected and your face matched the ID photo. Staff must still verify that the ID is genuine.';
        requestForm.hidden = true;
        modalSuccess.hidden = false;
        requestForm.reset();
        resetIdentityCheck();
      } else {
        formError.textContent = data.error || 'Something went wrong. Please try again.';
        formError.hidden = false;
      }
    } catch (err) {
      formError.textContent = 'Could not reach the server. Please check your connection and try again.';
      formError.hidden = false;
    } finally {
      submitRequest.disabled = !identityReady;
      submitRequest.textContent = 'Submit request';
    }
  });

  document.getElementById('successClose').addEventListener('click', closeModal);

  async function refreshPublicQueue() {
    const currentLabel = document.getElementById('queueNowServingLabel');
    const currentNumber = document.getElementById('queueNowServingNumber');
    const lastCalled = document.getElementById('queueLastCalled');
    const waitingLabel = document.getElementById('queueWaitingCount');
    try {
      const response = await fetch('get_queue_status.php');
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.error || 'Queue status unavailable.');
      currentLabel.textContent = data.current_number === null ? 'Queue not currently serving' : 'Now serving queue no.';
      currentNumber.textContent = data.current_number === null
        ? '—'
        : String(data.current_number).padStart(4, '0');
      const calledTime = data.called_at
        ? new Date(data.called_at.replace(' ', 'T')).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})
        : '';
      lastCalled.textContent = data.current_number !== null && calledTime ? `Called at ${calledTime}` : '';
      waitingLabel.textContent = `${data.waiting_count} waiting · ${data.last_number} tickets assigned today`;
    } catch (error) {
      currentLabel.textContent = 'Queue status temporarily unavailable';
      currentNumber.textContent = '—';
      lastCalled.textContent = '';
      waitingLabel.textContent = 'Please check again shortly.';
    }
  }
  refreshPublicQueue();
  window.setInterval(refreshPublicQueue, 10000);

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
  document.querySelectorAll('.home-logo-link').forEach(link => {
    link.addEventListener('click', (e) => {
      const destination = new URL(link.href, window.location.href);
      if (destination.pathname === window.location.pathname) {
        e.preventDefault();
        window.scrollTo({top: 0, behavior: 'smooth'});
      }
    });
  });
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