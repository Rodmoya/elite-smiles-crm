<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/db.php';
require_once dirname(__DIR__) . '/app/social_studio/social_studio_service.php';

function social_brand_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

social_studio_ensure_schema();
$book = social_studio_active_brand_book();
$rules = (array)($book['rules'] ?? []);
social_brand_assert((int)($book['version'] ?? 0) >= 1, 'The active Brand Book must be versioned.');
foreach (['identity', 'colors', 'typography', 'composition', 'photography', 'copy', 'scenarios', 'governance'] as $section) {
    social_brand_assert(isset($rules[$section]) && is_array($rules[$section]), "Missing Brand Book section: {$section}");
}
foreach (['educational', 'social_ad', 'premium_portrait', 'smile_closeup', 'clinical_3d', 'benefit_list', 'dark_luxury', 'before_after', 'life_experience'] as $scenario) {
    social_brand_assert(trim((string)($rules['scenarios'][$scenario] ?? '')) !== '', "Missing scenario playbook: {$scenario}");
}
social_brand_assert(str_contains(social_studio_brand_book_prompt(), 'binding production policy'), 'AI prompt must treat the Brand Book as binding policy.');
$draftColumns = array_column(db_all('SHOW COLUMNS FROM social_studio_drafts'), 'Field');
social_brand_assert(in_array('brand_book_id', $draftColumns, true), 'Drafts must retain Brand Book lineage.');
social_brand_assert(in_array('brand_book_version', $draftColumns, true), 'Drafts must retain the Brand Book version.');
$page = file_get_contents(dirname(__DIR__) . '/social-studio.php') ?: '';
$action = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_brand_book.php') ?: '';
social_brand_assert(str_contains($page, 'view=brand-book'), 'Social Studio must expose the Brand Book workspace.');
social_brand_assert(str_contains($page, 'Virtual brand memory'), 'Brand Book UI must explain its role as persistent AI memory.');
social_brand_assert(str_contains($action, 'social_studio_save_brand_book'), 'Brand Book updates must create a versioned record.');

echo "Social Studio Brand Book tests passed.\n";
