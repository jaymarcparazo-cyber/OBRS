<?php
$officeLocation = 'Barangay ' . $certificateSettings['barangay_name'] . ', '
    . $certificateSettings['municipality'] . ', ' . $certificateSettings['province'];
$watermarkText = strtoupper($officeLocation);
?>
<div class="cert">
    <div class="watermark" aria-hidden="true">
        <?php for ($i = 0; $i < 16; $i++): ?>
            <div class="watermark-line">OFFICIAL COPY&nbsp;&nbsp;<?= htmlspecialchars($watermarkText, ENT_QUOTES, 'UTF-8') ?>&nbsp;&nbsp;OFFICIAL COPY</div>
        <?php endfor; ?>
    </div>
    <div class="cert-content">
        <div class="cert-head">
            <div class="rep"><?= htmlspecialchars($certificateSettings['republic_text'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="office"><?= htmlspecialchars($officeLocation, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="addr"><?= htmlspecialchars($certificateSettings['office_address'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="divider"></div>
        <div class="cert-title"><?= htmlspecialchars($certificateTitle, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cert-subtitle"><?= htmlspecialchars($certificateSettings['office_subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cert-body"><?= $certificateWording ?></div>
        <div class="cert-foot">
            <div class="foot-left">
                <div class="disclaimer"><?= htmlspecialchars($certificateSettings['disclaimer'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="verify-block">
                    <div class="verify-caption">
                        <strong>Verify this document online.</strong><br>
                        Open the verification link from the official barangay portal.<br>
                        <a class="mono" href="<?= htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </div>
            <div class="sig-block">
                <div class="stamp">
                    <a href="<?= htmlspecialchars($certificateHomeUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Return to homepage">
                        <img src="<?= htmlspecialchars($certificateLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Official seal">
                    </a>
                </div>
                <div class="sig-line"></div>
                <div class="sig-name"><?= htmlspecialchars($certificateSettings['signatory_name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div><?= htmlspecialchars($certificateSettings['signatory_title'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <?php if (!empty($onBehalfRelationship)): ?>
            <div class="on-behalf-note">
                Requested by <?= htmlspecialchars($requesterName, ENT_QUOTES, 'UTF-8') ?>
                (<?= htmlspecialchars($onBehalfRelationship, ENT_QUOTES, 'UTF-8') ?>)
                on behalf of the named recipient.
            </div>
        <?php endif; ?>
        <div class="page-footer">
            <span>Reference No. <span class="mono"><?= htmlspecialchars($certificateReference, ENT_QUOTES, 'UTF-8') ?></span></span>
            <span>Date issued: <?= htmlspecialchars($issueDate, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</div>
