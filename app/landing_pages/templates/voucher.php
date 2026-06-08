<?php
declare(strict_types=1);

/**
 * Elite Smiles Landing Engine
 * File: app/landing_pages/templates/voucher.php
 *
 * Voucher compact layout — dark luxury card, form-first, no quiz modal.
 * After submit shows a printable/saveable voucher card.
 */

$partialsDir = __DIR__ . '/../partials';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($metaTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <?php lp_tracking_head(); ?>

    <style>
        :root{--gold:#d6b878;--gold-soft:#ead6ac;--ivory:#f7f0e4;--muted:#c9bda8;--text:#f8f4ee;--line:rgba(214,184,120,0.18);--line-strong:rgba(214,184,120,0.30);--radius:20px;}
        *{box-sizing:border-box} html,body{margin:0;padding:0}
        body{font-family:Arial,Helvetica,sans-serif;background:#120f0d;color:var(--text);min-height:100vh;-webkit-text-size-adjust:100%;}
        .page{width:100%;max-width:none;margin:0;padding:0;min-height:100vh;}
        .card{background:linear-gradient(180deg,rgba(26,20,16,.98) 0%,rgba(17,13,10,1) 100%);border:none;border-radius:0;overflow:hidden;min-height:100vh;}
        .hero{padding:20px 16px 16px;border-bottom:1px solid rgba(214,184,120,.14);background:linear-gradient(180deg,rgba(214,184,120,.06) 0%,rgba(214,184,120,.015) 100%);text-align:center;}
        .eyebrow{color:var(--gold);text-transform:uppercase;letter-spacing:.22em;font-size:10px;margin-bottom:8px;}
        h1{margin:0;font-family:"Times New Roman",Georgia,serif;font-size:clamp(28px,7vw,40px);line-height:1.02;font-weight:600;letter-spacing:.01em;color:var(--ivory);}
        .subline{margin:10px auto 0;max-width:520px;color:var(--gold-soft);font-size:10px;line-height:1.55;letter-spacing:.06em;text-transform:uppercase;}
        .intro{padding:12px 16px 0;text-align:center;} .intro p{margin:0;color:var(--ivory);font-size:14px;line-height:1.45;}
        .form-wrap{padding:14px 16px 18px;}
        .errors{margin:0 0 10px;padding:10px 12px;border-radius:14px;border:1px solid rgba(255,120,120,.24);background:rgba(120,22,22,.18);color:#ffd5d5;font-size:13px;line-height:1.45;}
        .field{margin-bottom:10px;}
        label{display:block;margin:0 0 5px;color:var(--gold-soft);font-size:11px;letter-spacing:.06em;text-transform:uppercase;}
        input,select{width:100%;height:46px;border-radius:14px;border:1px solid rgba(214,184,120,.16);background:rgba(255,255,255,.035);color:var(--text);padding:0 13px;font-size:15px;outline:none;}
        input::placeholder{color:rgba(247,240,228,.46);}
        input:focus,select:focus{border-color:rgba(214,184,120,.46);box-shadow:0 0 0 3px rgba(214,184,120,.07);}
        select option{color:#111;}
        .button{width:100%;height:50px;border:none;border-radius:14px;cursor:pointer;background:linear-gradient(180deg,#e7cf9a 0%,#cfa962 100%);color:#1a120b;font-size:15px;font-weight:700;letter-spacing:.03em;box-shadow:0 12px 24px rgba(0,0,0,.26);}
        .button:hover{filter:brightness(1.03);}
        .voucher-shell{padding:16px;}
        .voucher-header{text-align:center;margin-bottom:10px;}
        .voucher-header h2{margin:0 0 4px;font-family:"Times New Roman",Georgia,serif;font-size:24px;color:var(--ivory);font-weight:600;}
        .voucher-header p{margin:0;color:var(--muted);font-size:13px;line-height:1.4;}
        .voucher{position:relative;border-radius:18px;border:1px solid var(--line-strong);background:linear-gradient(180deg,rgba(34,27,22,.98) 0%,rgba(20,15,12,1) 100%);padding:14px;box-shadow:0 16px 34px rgba(0,0,0,.28);overflow:hidden;}
        .voucher-logo{position:absolute;top:12px;right:12px;width:54px;height:auto;opacity:.82;pointer-events:none;}
        .voucher-top{text-align:center;padding-bottom:10px;margin-bottom:10px;border-bottom:1px dashed rgba(214,184,120,.24);}
        .voucher-brand{color:var(--gold);text-transform:uppercase;letter-spacing:.22em;font-size:9px;margin-bottom:6px;}
        .voucher-value{font-family:"Times New Roman",Georgia,serif;font-size:clamp(36px,10vw,54px);line-height:.92;color:var(--ivory);margin:0 0 4px;}
        .voucher-title{margin:0;font-family:"Times New Roman",Georgia,serif;color:var(--gold-soft);font-size:16px;line-height:1.1;}
        .voucher-list{display:grid;grid-template-columns:1fr;gap:6px;margin:0;padding:0;list-style:none;}
        .voucher-list li{color:var(--ivory);font-size:12px;line-height:1.35;text-align:center;}
        .voucher-meta{margin-top:10px;padding-top:10px;border-top:1px solid rgba(214,184,120,.12);color:var(--muted);font-size:11px;text-align:center;line-height:1.45;}
        .voucher-actions{margin-top:10px;display:grid;gap:8px;}
        .button-secondary{width:100%;height:44px;border-radius:14px;border:1px solid rgba(214,184,120,.24);background:transparent;color:var(--ivory);font-size:14px;font-weight:600;cursor:pointer;}
        .footnote{text-align:center;color:rgba(247,240,228,.50);font-size:10px;line-height:1.4;margin-top:8px;}
        @media(min-width:768px){.page{display:flex;align-items:flex-start;justify-content:center;padding:24px 0;} .card{width:min(100%,560px);min-height:auto;border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 18px 48px rgba(0,0,0,.36);}}
        @media print{body{background:#111 !important;} .page{width:100%;max-width:none;padding:0;display:block;} .card{width:100%;border:none;border-radius:0;box-shadow:none;} .voucher-header,.voucher-actions,.footnote{display:none !important;} .voucher-shell{padding:0;} .voucher{margin:0;box-shadow:none;}}
    </style>
</head>
<body>
<div class="page">
    <div class="card">

        <?php if (!$voucherSubmitted): ?>
            <div class="hero">
                <div class="eyebrow"><?= e($offerBadge ?: 'Smile Makeover Credit') ?></div>
                <h1><?= e($heroTitle) ?></h1>
                <div class="subline"><?= e($heroSubtitle) ?></div>
            </div>

            <div class="intro">
                <p><?= e($introText ?: 'Complete the form below to request your consultation credit.') ?></p>
            </div>

            <div class="form-wrap">
                <?php if ($errorMessage !== ''): ?>
                    <div class="errors"><?= e($errorMessage) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= e($formAction) ?>">
                    <?= csrf_input() ?>

                    <div class="field">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?= e($form_voucherForm['full_name'] ?? '') ?>" placeholder="Your full name" required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?= e($form_voucherForm['phone'] ?? '') ?>" placeholder="Your phone number" required>
                    </div>
                    <div class="field">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= e($form_voucherForm['email'] ?? '') ?>" placeholder="Your email address" required>
                    </div>
                    <div class="field">
                        <label for="what_brings_you_in">What brings you in?</label>
                        <select id="what_brings_you_in" name="what_brings_you_in" required>
                            <option value="">Select one</option>
                            <?php foreach ($voucherReasonOptions as $opt): ?>
                            <option value="<?= e($opt) ?>" <?= ($form_voucherForm['what_brings_you_in'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="start_timing">When would you like to start?</label>
                        <select id="start_timing" name="start_timing" required>
                            <option value="">Select one</option>
                            <?php foreach ($voucherTimingOptions as $opt): ?>
                            <option value="<?= e($opt) ?>" <?= ($form_voucherForm['start_timing'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="preferred_contact">Preferred way to contact you</label>
                        <select id="preferred_contact" name="preferred_contact" required>
                            <option value="">Select one</option>
                            <?php foreach ($voucherContactOptions as $opt): ?>
                            <option value="<?= e($opt) ?>" <?= ($form_voucherForm['preferred_contact'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="button"><?= e($primaryCtaText ?: 'Claim Your Consultation Credit') ?></button>
                </form>
            </div>

        <?php else: ?>
            <div class="voucher-shell">
                <div class="voucher-header">
                    <h2>Your credit is ready</h2>
                    <p>Save this voucher and bring it to your consultation.</p>
                </div>

                <div class="voucher" id="voucherCard">
                    <img src="<?= e(lp_img_url('/crm/assets/img/ES-Logo-Stack-500-x-150-px.png')) ?>" alt="Elite Smiles" class="voucher-logo" crossorigin="anonymous">
                    <div class="voucher-top">
                        <div class="voucher-brand">Elite Smiles</div>
                        <div class="voucher-value"><?= e(preg_replace('/\s*VALUE\s*/i', '', $offerValueLabel) ?: '$750') ?></div>
                        <p class="voucher-title"><?= e($offerTitle) ?></p>
                    </div>
                    <ul class="voucher-list">
                        <?php foreach (array_slice($offerItems, 0, 4) as $item): ?>
                        <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="voucher-meta">
                        <?= e($form_voucherForm['full_name'] ?? '') ?><br>
                        Ref: <?= e($voucherReference) ?>
                    </div>
                </div>

                <div class="voucher-actions">
                    <button type="button" class="button" id="saveVoucherBtn">Save Voucher as Image</button>
                    <button type="button" class="button-secondary" onclick="window.location.href='<?= e($formAction) ?>'">Request Another Credit</button>
                </div>
                <div class="footnote">One credit per patient. Final treatment recommendations are based on clinical evaluation.</div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
            <?php require __DIR__ . '/../partials/voucher-save.php'; ?>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
