<?php
require dirname(__DIR__) . '/certificate_templates.php';

$actual = render_certificate_template(
    "Issued to {{name}}.\nPurpose: {{purpose}}.",
    ['name' => '<Resident>', 'purpose' => 'Aid & support']
);
$expected = "Issued to &lt;Resident&gt;.<br>\nPurpose: Aid &amp; support.";

if ($actual !== $expected) {
    throw new RuntimeException('Certificate template escaping or line-break rendering failed.');
}

$defaults = default_certificate_settings();
if ($defaults['signatory_name'] !== 'Hon. Punong Barangay' || $defaults['barangay_name'] !== 'Lawacamulag') {
    throw new RuntimeException('Default shared certificate settings are incomplete.');
}

$certificateSettings = array_merge($defaults, [
    'signatory_name' => 'Hon. <Sample>',
    'signatory_title' => 'Chair & Officer',
]);
$certificateTitle = 'Clearance';
$certificateWording = 'Issued for &lt;resident&gt;.';
$verifyUrl = 'verify.php?ref=SAMPLE-0001';
$certificateReference = 'SAMPLE-0001';
$issueDate = 'January 1, 2026';
$requesterName = '';
$onBehalfRelationship = '';
$certificateLogoUrl = 'logo.jpg';
$certificateHomeUrl = 'obrs.php';
ob_start();
require dirname(__DIR__) . '/certificate_document.php';
$markup = ob_get_clean();

foreach (['Hon. &lt;Sample&gt;', 'Chair &amp; Officer', 'Barangay Lawacamulag, San Jose, Tarlac', 'SAMPLE-0001'] as $expectedText) {
    if (strpos($markup, $expectedText) === false) {
        throw new RuntimeException('Shared certificate markup omitted or failed to escape expected content.');
    }
}

echo "Certificate template rendering test passed.\n";
