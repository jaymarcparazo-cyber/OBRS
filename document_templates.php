<?php
require __DIR__ . '/admin_auth.php';
require __DIR__ . '/config.php';
require_once __DIR__ . '/certificate_templates.php';

if (($_SESSION['staff_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}

$documentTypes = $pdo->query(
    'SELECT document_type_id, name, certificate_title, certificate_template FROM document_types ORDER BY name'
)->fetchAll();

if (!$documentTypes) {
    http_response_code(404);
    exit('No document types are configured.');
}

$documentTypesById = [];
foreach ($documentTypes as $documentType) {
    $documentTypesById[(int)$documentType['document_type_id']] = $documentType;
}

$settings = load_certificate_settings($pdo);
$selectedId = filter_input(INPUT_GET, 'document_type_id', FILTER_VALIDATE_INT);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $selectedId = filter_input(INPUT_POST, 'document_type_id', FILTER_VALIDATE_INT);
    $template = trim($_POST['certificate_template'] ?? '');
    $title = trim($_POST['certificate_title'] ?? '');
    $postedSettings = [];
    foreach (array_keys(default_certificate_settings()) as $field) {
        $postedSettings[$field] = trim($_POST[$field] ?? '');
    }

    if (!$selectedId || !isset($documentTypesById[$selectedId]) || $template === '' || $title === '') {
        $error = 'Choose a document and enter its certificate title and wording.';
    } elseif (strlen($template) > 20000) {
        $error = 'Certificate wording must be 20,000 characters or fewer.';
    } else {
        $fieldLimits = [
            'republic_text' => 120,
            'barangay_name' => 120,
            'municipality' => 120,
            'province' => 120,
            'office_address' => 255,
            'office_subtitle' => 160,
            'disclaimer' => 255,
            'signatory_name' => 120,
            'signatory_title' => 120,
        ];
        foreach ($fieldLimits as $field => $limit) {
            if ($postedSettings[$field] === '' || strlen($postedSettings[$field]) > $limit) {
                $error = 'Complete all office and signatory details within their character limits.';
                break;
            }
        }
        if (strlen($title) > 120) {
            $error = 'Certificate title must be 120 characters or fewer.';
        }
    }

    if ($error === '') {
        $pdo->beginTransaction();
        $updateDocument = $pdo->prepare(
            'UPDATE document_types SET certificate_title = ?, certificate_template = ? WHERE document_type_id = ?'
        );
        $updateDocument->execute([$title, $template, $selectedId]);
        $updateSettings = $pdo->prepare(
            'UPDATE certificate_settings SET republic_text = ?, barangay_name = ?, municipality = ?, province = ?, '
            . 'office_address = ?, office_subtitle = ?, disclaimer = ?, signatory_name = ?, signatory_title = ? '
            . 'WHERE settings_id = 1'
        );
        $updateSettings->execute(array_values($postedSettings));
        $pdo->commit();
        header('Location: document_templates.php?document_type_id=' . $selectedId . '&saved=1');
        exit;
    }
}

if (!$selectedId && isset($_GET['document_type_id'])) {
    http_response_code(400);
    exit('Invalid document type.');
}
if ($selectedId && !isset($documentTypesById[$selectedId])) {
    http_response_code(404);
    exit('Document type not found.');
}

if (!$selectedId) {
    $selectedType = $documentTypes[0];
    $selectedId = (int)$selectedType['document_type_id'];
} else {
    $selectedType = $documentTypesById[$selectedId];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
    $settings = array_merge($settings, $postedSettings);
    $templateValue = $_POST['certificate_template'] ?? '';
    $titleValue = $_POST['certificate_title'] ?? '';
} else {
    $templateValue = $selectedType['certificate_template'] ?: default_certificate_template();
    $titleValue = $selectedType['certificate_title'] ?: $selectedType['name'];
}
$saved = ($_GET['saved'] ?? '') === '1';
$csrfToken = csrf_token();
$certificateSettings = $settings;
$previewWording = render_certificate_template($templateValue, [
    'name' => 'JUAN DELA CRUZ',
    'civil_status' => 'single',
    'purpose' => 'sample purpose',
    'issue_date' => date('F j, Y'),
    'barangay' => $settings['barangay_name'],
    'municipality' => $settings['municipality'],
    'province' => $settings['province'],
]);
$certificateTitle = $titleValue;
$certificateWording = $previewWording;
$verifyUrl = 'verify.php?ref=SAMPLE-0001';
$certificateReference = 'SAMPLE-0001';
$issueDate = date('F j, Y');
$requesterName = '';
$onBehalfRelationship = '';
$certificateLogoUrl = app_asset_url('logo.jpg');
$certificateHomeUrl = app_asset_url('obrs.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate templates — Barangay Lawacamulag</title>
<style nonce="<?= $csp_nonce ?>">
  :root{--paper:#EAF4FB;--ink:#173042;--ink-soft:#506A78;--blue:#1685C7;--blue-deep:#075184;--green:#23833B;--green-deep:#17632B;--gold:#E6B92E;--line:#B9D2DE;--red:#9B2B22}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--paper);color:var(--ink);font-family:Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;line-height:1.5}
  header{background:var(--green-deep);color:#fff;padding:16px 32px;border-bottom:1px solid rgba(230,185,46,.45)}
  .brand{display:flex;align-items:center;gap:12px;max-width:1440px;margin:auto;text-decoration:none;color:inherit}
  .seal{width:38px;height:38px;object-fit:cover;border-radius:50%;border:1px solid var(--gold)}
  .brand-name{font-size:15px;font-weight:700}.brand-sub{font-size:11px;color:#D7E8DA}
  main{max-width:1440px;margin:0 auto;padding:28px 32px}
  h1{font-size:26px;letter-spacing:-.025em;color:var(--blue-deep);margin-bottom:6px}
  .intro{color:var(--ink-soft);font-size:14px;margin-bottom:20px}
  .workspace{display:grid;grid-template-columns:minmax(340px,.8fr) minmax(0,1.2fr);gap:22px;align-items:start}
  .panel{background:#fff;border:1px solid var(--line);padding:22px;box-shadow:0 8px 24px rgba(7,81,132,.08)}
  .panel h2{font-size:17px;color:var(--blue-deep);margin-bottom:16px}
  label{display:block;font-size:13px;font-weight:600;margin-bottom:12px}
  input,select,textarea{width:100%;font:inherit;color:var(--ink);background:#fff;border:1px solid var(--line);padding:10px 11px;border-radius:4px;margin-top:5px}
  input:focus,select:focus,textarea:focus{outline:2px solid rgba(22,133,199,.25);border-color:var(--blue)}
  textarea{min-height:230px;resize:vertical;line-height:1.65}
  .settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
  .wide{grid-column:1/-1}
  .hint{color:var(--ink-soft);font-size:12px;margin:-4px 0 16px}
  code{background:var(--paper);padding:2px 4px;border-radius:3px;color:var(--blue-deep);white-space:nowrap}
  .notice{padding:11px 13px;margin-bottom:16px;font-size:13px;border:1px solid}
  .success{background:#EAF6EC;border-color:#A8D2AE;color:var(--green-deep)}
  .error{background:#FDEDEC;border-color:#E8B4AE;color:var(--red)}
  .actions{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-top:16px}
  button,.back{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:4px;background:var(--green);color:#fff;padding:11px 18px;font:600 14px Calibri,'Segoe UI Variable','Segoe UI',Arial,sans-serif;text-decoration:none;cursor:pointer}
  button:hover{background:var(--green-deep)}.back{background:var(--blue-deep)}
  .preview-panel{position:sticky;top:16px;overflow:hidden}
  .preview-label{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin-bottom:12px}
  .preview-label h2{margin:0}.preview-label span{font-size:12px;color:var(--ink-soft)}
  .preview-stage{background:#dce8ed;padding:16px;overflow:hidden;min-height:450px}
  .preview-scale{transform-origin:top left;width:210mm}
  .preview-stage .cert{box-shadow:0 5px 20px rgba(23,48,66,.2)}
  @media(max-width:1050px){.workspace{grid-template-columns:1fr}.preview-panel{position:static}.preview-stage{min-height:350px}}
  @media(max-width:600px){header{padding:14px 18px}main{padding:22px 14px}.panel{padding:16px}.settings-grid{grid-template-columns:1fr}.wide{grid-column:auto}.actions{align-items:stretch;flex-direction:column-reverse}.actions>*{width:100%}.preview-stage{padding:10px}}
</style>
<link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('certificate_document.css'), ENT_QUOTES) ?>">
</head>
<body>
<header>
  <a class="brand" href="dashboard.php">
    <img class="seal" src="<?= htmlspecialchars(app_asset_url('logo.jpg'), ENT_QUOTES) ?>" alt="">
    <span><span class="brand-name">Barangay Lawacamulag</span><br><span class="brand-sub">Administration · Certificate templates</span></span>
  </a>
</header>
<main>
  <h1>Certificate templates</h1>
  <p class="intro">Edit a certificate and see the same layout residents receive. Office and signatory details apply to every document type.</p>
  <?php if ($saved): ?><p class="notice success" role="status">Certificate template and office details saved.</p><?php endif; ?>
  <?php if ($error): ?><p class="notice error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <div class="workspace">
    <section class="panel">
      <h2>Certificate content</h2>
      <form method="get" id="document-selector">
        <label for="document-type">Choose a document
          <select id="document-type" name="document_type_id">
            <?php foreach ($documentTypes as $documentType): ?>
              <option value="<?= (int)$documentType['document_type_id'] ?>" <?= (int)$documentType['document_type_id'] === (int)$selectedId ? 'selected' : '' ?>>
                <?= htmlspecialchars($documentType['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="document_type_id" value="<?= (int)$selectedId ?>">
        <label for="certificate-title">Certificate title
          <input id="certificate-title" name="certificate_title" maxlength="120" value="<?= htmlspecialchars($titleValue, ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label for="certificate-template">Certificate wording
          <textarea id="certificate-template" name="certificate_template" maxlength="20000" required><?= htmlspecialchars($templateValue, ENT_QUOTES, 'UTF-8') ?></textarea>
        </label>
        <p class="hint">Placeholders: <code>{{name}}</code> <code>{{civil_status}}</code> <code>{{purpose}}</code> <code>{{issue_date}}</code> <code>{{barangay}}</code> <code>{{municipality}}</code> <code>{{province}}</code>.</p>
        <h2>Shared office and signatory</h2>
        <div class="settings-grid">
          <?php
          $settingFields = [
              'republic_text' => ['Republic heading', 120],
              'barangay_name' => ['Barangay', 120],
              'municipality' => ['Municipality / city', 120],
              'province' => ['Province', 120],
              'office_address' => ['Office address', 255],
              'office_subtitle' => ['Office subtitle', 160],
              'disclaimer' => ['Certificate disclaimer', 255],
              'signatory_name' => ['Signatory name', 120],
              'signatory_title' => ['Signatory title', 120],
          ];
          foreach ($settingFields as $field => [$label, $maxLength]):
              $wide = in_array($field, ['office_address', 'disclaimer'], true) ? ' wide' : '';
          ?>
            <label class="<?= $wide ?>" for="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
              <input id="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" maxlength="<?= $maxLength ?>" value="<?= htmlspecialchars($settings[$field], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="actions">
          <a class="back" href="dashboard.php">Back to dashboard</a>
          <button type="submit">Save certificate</button>
        </div>
      </form>
    </section>
    <section class="panel preview-panel" aria-label="Live certificate preview">
      <div class="preview-label"><h2>Live preview</h2><span>Sample resident and request data</span></div>
      <div class="preview-stage" id="preview-stage">
        <div class="preview-scale" id="preview-scale">
          <?php require __DIR__ . '/certificate_document.php'; ?>
        </div>
      </div>
    </section>
  </div>
</main>
<script nonce="<?= $csp_nonce ?>">
(() => {
  const selector = document.getElementById('document-type');
  selector.addEventListener('change', () => document.getElementById('document-selector').submit());

  const stage = document.getElementById('preview-stage');
  const scaleContainer = document.getElementById('preview-scale');
  const template = document.getElementById('certificate-template');
  const title = document.getElementById('certificate-title');
  const fields = ['republic_text', 'barangay_name', 'municipality', 'province', 'office_address',
    'office_subtitle', 'disclaimer', 'signatory_name', 'signatory_title'];
  const sampleValues = {
    name: 'JUAN DELA CRUZ',
    civil_status: 'single',
    purpose: 'sample purpose',
    issue_date: '<?= htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8') ?>'
  };
  const previewBody = scaleContainer.querySelector('.cert-body');

  function updatePreview() {
    const values = {...sampleValues};
    fields.forEach((field) => { values[field] = document.getElementById(field).value; });
    values.barangay = values.barangay_name;
    const rendered = template.value.replace(/\{\{(name|civil_status|purpose|issue_date|barangay|municipality|province)\}\}/g,
      (placeholder, key) => values[key] ?? '');
    previewBody.replaceChildren();
    rendered.split('\n').forEach((line, index) => {
      if (index) previewBody.appendChild(document.createElement('br'));
      previewBody.appendChild(document.createTextNode(line));
    });
    scaleContainer.querySelector('.cert-title').textContent = title.value;
    scaleContainer.querySelector('.rep').textContent = values.republic_text;
    scaleContainer.querySelector('.office').textContent =
      `Barangay ${values.barangay_name}, ${values.municipality}, ${values.province}`;
    scaleContainer.querySelector('.addr').textContent = values.office_address;
    scaleContainer.querySelector('.cert-subtitle').textContent = values.office_subtitle;
    scaleContainer.querySelector('.disclaimer').textContent = values.disclaimer;
    scaleContainer.querySelector('.sig-name').textContent = values.signatory_name;
    scaleContainer.querySelector('.sig-block > div:last-child').textContent = values.signatory_title;
    scaleContainer.querySelectorAll('.watermark-line').forEach((line) => {
      line.textContent = `OFFICIAL COPY  BARANGAY ${values.barangay_name}, ${values.municipality}, ${values.province}  OFFICIAL COPY`;
    });
  }

  function resizePreview() {
    const scale = Math.min(1, (stage.clientWidth - 32) / 794);
    scaleContainer.style.transform = `scale(${scale})`;
    stage.style.height = `${1122 * scale + 32}px`;
  }

  document.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => {
    field.addEventListener('input', updatePreview);
  });
  window.addEventListener('resize', resizePreview);
  updatePreview();
  resizePreview();
})();
</script>
</body>
</html>
