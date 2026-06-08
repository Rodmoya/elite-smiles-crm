<?php
declare(strict_types=1);

$head = $head ?? [];
$pageTitle = (string) ($head['title'] ?? 'Elite Smiles');
$metaDescription = (string) ($head['description'] ?? '');
$canonicalUrl = (string) ($head['canonical'] ?? '');
$metaImage = (string) ($head['image'] ?? '');
$robots = (string) ($head['robots'] ?? 'index,follow');
$schemaItems = $head['schema'] ?? [];
if (!is_array($schemaItems)) {
    $schemaItems = [];
}
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if ($metaDescription !== ''): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<meta name="robots" content="<?= e($robots) ?>">
<?php if ($canonicalUrl !== ''): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<?php if ($metaDescription !== ''): ?><meta property="og:description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<?php if ($canonicalUrl !== ''): ?><meta property="og:url" content="<?= e($canonicalUrl) ?>"><?php endif; ?>
<?php if ($metaImage !== ''): ?><meta property="og:image" content="<?= e($metaImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $metaImage !== '' ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<?php if ($metaDescription !== ''): ?><meta name="twitter:description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<?php if ($metaImage !== ''): ?><meta name="twitter:image" content="<?= e($metaImage) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<?php foreach ($schemaItems as $schema): ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endforeach; ?>
