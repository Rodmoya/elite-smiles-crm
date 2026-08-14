<?php
declare(strict_types=1);

require_once __DIR__ . '/social_studio_brand_book.php';

if (!function_exists('social_studio_master_cmo')) {
    function social_studio_master_cmo(): array
    {
        return social_studio_brand_book_rules();
    }
}

if (!function_exists('social_studio_master_cmo_prompt')) {
    function social_studio_master_cmo_prompt(): string
    {
        return social_studio_brand_book_prompt();
    }
}
