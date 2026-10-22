<?php
function default_certificate_template() {
    return "This is to certify that {{name}}, of legal age, {{civil_status}}, Filipino, and a resident of Barangay {{barangay}}, {{municipality}}, {{province}}, is a bona fide resident of this barangay.\n\n"
        . "This certification is issued upon the request of the above-named person for {{purpose}}.\n\n"
        . "Issued this {{issue_date}} at Barangay {{barangay}}, {{municipality}}, {{province}}, Philippines.";
}

function default_certificate_settings() {
    return [
        'republic_text' => 'Republic of the Philippines',
        'barangay_name' => 'Lawacamulag',
        'municipality' => 'San Jose',
        'province' => 'Tarlac',
        'office_address' => 'Barangay Hall, Lawacamulag, San Jose, Tarlac, Philippines',
        'office_subtitle' => 'Office of the Punong Barangay',
        'disclaimer' => 'Not valid without the official dry seal of this office.',
        'signatory_name' => 'Hon. Punong Barangay',
        'signatory_title' => 'Barangay Chairperson',
    ];
}

function load_certificate_settings(PDO $pdo) {
    $settings = $pdo->query('SELECT * FROM certificate_settings WHERE settings_id = 1')->fetch();
    return $settings ?: default_certificate_settings();
}

function render_certificate_template($template, array $values) {
    $safeTemplate = htmlspecialchars($template, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $replacements = [];

    foreach ($values as $key => $value) {
        $replacements['{{' . $key . '}}'] = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return nl2br(strtr($safeTemplate, $replacements), false);
}
