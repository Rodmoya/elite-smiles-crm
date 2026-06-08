<?php
declare(strict_types=1);

/**
 * Elite Smiles Landing Engine
 * File: app/landing_pages/bootstrap.php
 *
 * Shared registry + helper layer for all landing pages.
 */

if (!function_exists('lp_require_array')) {
    function lp_require_array(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $data = require $path;
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('lp_require_dir_configs')) {
    function lp_require_dir_configs(string $dir): array
    {
        $out = [];

        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $data = require $file;

            if (is_array($data)) {
                $out[$key] = $data;
            }
        }

        return $out;
    }
}

if (!function_exists('lp_require_angles')) {
    function lp_require_angles(string $root): array
    {
        $out = [];

        foreach (glob(rtrim($root, '/') . '/*', GLOB_ONLYDIR) ?: [] as $procDir) {
            $procKey = basename($procDir);

            foreach (glob($procDir . '/*.php') ?: [] as $file) {
                $angleKey = basename($file, '.php');
                $data = require $file;

                if (is_array($data)) {
                    $out[$procKey][$angleKey] = $data;
                }
            }
        }

        return $out;
    }
}

if (!function_exists('lp_require_question_sets')) {
    function lp_require_question_sets(string $dir): array
    {
        $out = [];

        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            $key = basename($file);
            $data = require $file;

            if (is_array($data)) {
                $out[$key] = $data;
            }
        }

        return $out;
    }
}

if (!function_exists('lp_media_url')) {
    function lp_media_url(string $path): string
    {
        $path = trim($path);

        if ($path === '' || preg_match('#^(?:https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (defined('APP_URL')) {
            $appPath = (string) (parse_url((string) APP_URL, PHP_URL_PATH) ?? '');
            if ($appPath !== '' && str_starts_with($path, rtrim($appPath, '/') . '/')) {
                $path = substr($path, strlen(rtrim($appPath, '/') . '/'));
            }
        }

        return base_url(ltrim($path, '/'));
    }
}

if (!function_exists('lp_img_url')) {
    function lp_img_url(string $path): string
    {
        return lp_media_url($path);
    }
}

if (!function_exists('lp_prefer_optimized_veneer_image')) {
    function lp_prefer_optimized_veneer_image(string $path): string
    {
        $optimized = [
            'veneers-draper-hero-v1.jpg' => 'veneers-draper-hero-v1-meta.jpg',
            'veneers-gallery-1.jpg' => 'veneers-gallery-1-meta.jpg',
            'veneers-gallery-2.jpg' => 'veneers-gallery-2-meta.jpg',
            'veneers-gallery-3.jpg' => 'veneers-gallery-3-meta.jpg',
            'veneers-before-after.jpg' => 'veneers-before-after-meta.jpg',
        ];

        $basename = basename(parse_url($path, PHP_URL_PATH) ?: $path);
        if (!isset($optimized[$basename])) {
            return $path;
        }

        return preg_replace('/' . preg_quote($basename, '/') . '$/', $optimized[$basename], $path) ?: $path;
    }
}

if (!function_exists('landing_pages_registry')) {
    function landing_pages_registry(): array
    {
        static $registry = null;

        if (is_array($registry)) {
            return $registry;
        }

        $root = __DIR__;

        $map = lp_require_array($root . '/configs/landing-map.php');
        $procedures = lp_require_dir_configs($root . '/configs/procedures');
        $cities = lp_require_dir_configs($root . '/configs/cities');
        $angles = lp_require_angles($root . '/configs/angles');

        $registry = [
            'map' => $map,
            'landing_map' => $map,
            'procedures' => $procedures,
            'cities' => $cities,
            'angles' => $angles,
            'question_sets' => lp_require_question_sets($root . '/question_sets'),
            'doctor_authority' => lp_require_array($root . '/content/shared/doctor-authority.php'),
            'reviews' => lp_require_array($root . '/content/shared/reviews.php'),
            'location_modules' => lp_require_array($root . '/content/shared/location-modules.php'),
        ];

        return $registry;
    }
}

if (!function_exists('landing_pages_parse_slug')) {
    function landing_pages_parse_slug(string $slug, array $procedures, array $cities): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return [];
        }

        $base = preg_replace('/-v\d+$/', '', $slug) ?? $slug;

        $procSlugs = array_map(static fn(string $k): string => str_replace('_', '-', $k), array_keys($procedures));
        usort($procSlugs, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $procedureKey = null;
        foreach ($procSlugs as $candidate) {
            if (str_starts_with($base, $candidate . '-')) {
                $procedureKey = str_replace('-', '_', $candidate);
                $base = substr($base, strlen($candidate) + 1);
                break;
            }
        }

        if ($procedureKey === null) {
            return [];
        }

        $cityKeys = array_keys($cities);
        usort($cityKeys, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $cityKey = null;
        foreach ($cityKeys as $candidate) {
            if ($base === $candidate || str_starts_with($base, $candidate . '-')) {
                $cityKey = $candidate;
                $base = ($base === $candidate) ? '' : substr($base, strlen($candidate) + 1);
                break;
            }
        }

        $angleKey = $base !== '' ? str_replace('-', '_', $base) : null;

        return array_filter([
            'procedure' => $procedureKey,
            'city' => $cityKey,
            'angle' => $angleKey,
        ], static fn($v) => $v !== null && $v !== '');
    }
}

if (!function_exists('landing_pages_build_context')) {
    function landing_pages_build_context(string $slug, array $pageRecord = [], array $runtime = []): array
    {
        $registry = landing_pages_registry();
        $parsed = landing_pages_parse_slug($slug, $registry['procedures'], $registry['cities']);

        $procedureKey = (string) ($pageRecord['procedure_type'] ?? $parsed['procedure'] ?? '');
        $cityKey = (string) ($pageRecord['city'] ?? $pageRecord['city_target'] ?? $parsed['city'] ?? '');
        $angleKey = (string) ($pageRecord['angle'] ?? $pageRecord['page_angle'] ?? $parsed['angle'] ?? '');

        return [
            'slug' => $slug,
            'page' => $pageRecord,
            'pageRow' => $pageRecord,
            'procedure_key' => $procedureKey,
            'city_key' => $cityKey,
            'angle_key' => $angleKey,
            'procedure' => $registry['procedures'][$procedureKey] ?? [],
            'city' => lp_normalize_city_config($cityKey, $registry['cities'][$cityKey] ?? []),
            'angle' => $registry['angles'][$procedureKey][$angleKey] ?? [],
            'registry' => $registry,
            'runtime' => $runtime,
        ];
    }
}

if (!function_exists('lp_token_replace')) {
    function lp_token_replace(string $value, array $tokens): string
    {
        return strtr($value, $tokens);
    }
}

if (!function_exists('lp_default_question_set_filename')) {
    function lp_default_question_set_filename(string $procedureKey, string $angleKey): string
    {
        $procedureSlug = str_replace('_', '-', trim($procedureKey));
        $angleSlug = str_replace('_', '-', trim($angleKey));

        if ($procedureSlug === '' || $angleSlug === '') {
            return '';
        }

        return $procedureSlug . '-' . $angleSlug . '.php';
    }
}

if (!function_exists('lp_question_set_steps')) {
    function lp_question_set_steps(array $registry, string $questionSetFile): array
    {
        $questionSetFile = trim($questionSetFile);
        if ($questionSetFile === '') {
            return [];
        }

        $steps = $registry['question_sets'][$questionSetFile] ?? [];
        return is_array($steps) ? $steps : [];
    }
}

if (!function_exists('lp_empty_standard_form')) {
    function lp_empty_standard_form(string $procedureLabel = '', array $quizSteps = []): array
    {
        $form = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'procedure_interest' => $procedureLabel,
            'financing_needed' => 'unsure',
            'preferred_contact' => '',
            'sms_consent' => '',
        ];

        foreach ($quizSteps as $step) {
            $field = trim((string) ($step['field'] ?? ''));
            if ($field !== '' && !array_key_exists($field, $form)) {
                $form[$field] = '';
            }
        }

        return $form;
    }
}

if (!function_exists('lp_default_voucher_form')) {
    function lp_default_voucher_form(): array
    {
        return [
            'full_name' => '',
            'phone' => '',
            'email' => '',
            'what_brings_you_in' => '',
            'start_timing' => '',
            'preferred_contact' => '',
        ];
    }
}

if (!function_exists('lp_normalize_location_variant')) {
    function lp_normalize_location_variant(string $variant): string
    {
        $variant = trim($variant);

        if ($variant === 'planning') {
            return 'visit_planning';
        }

        return $variant !== '' ? $variant : 'convenience';
    }
}

if (!function_exists('lp_normalize_city_config')) {
    function lp_normalize_city_config(string $cityKey, array $city): array
    {
        if ($city === []) {
            return [];
        }

        $defaultMapEmbedUrl = 'https://www.google.com/maps?q=' . rawurlencode('Elite Smiles Draper Utah') . '&output=embed';
        $defaultAddress = 'Elite Smiles, Draper, Utah';

        $label = (string) ($city['city_label'] ?? $city['city_name'] ?? $city['label'] ?? ucwords(str_replace('-', ' ', $cityKey)));
        $localPositioning = is_array($city['local_positioning'] ?? null) ? $city['local_positioning'] : [];
        $location = is_array($city['location'] ?? null) ? $city['location'] : [];

        if ($location === [] && $localPositioning !== []) {
            $location = [
                'show_map' => (bool) ($localPositioning['map_enabled'] ?? false),
                'module_variant' => lp_normalize_location_variant((string) ($localPositioning['location_module_variant'] ?? 'convenience')),
                'travel_frame' => (string) ($localPositioning['travel_frame'] ?? ''),
                'convenience_note' => (string) ($localPositioning['why_here'] ?? ''),
                'visit_planning_note' => (string) ($localPositioning['cta_visit_note'] ?? ''),
                'address' => $defaultAddress,
                'map_embed_url' => $defaultMapEmbedUrl,
            ];
        } else {
            $location['module_variant'] = lp_normalize_location_variant((string) ($location['module_variant'] ?? 'convenience'));
        }

        $location['address'] = (string) ($location['address'] ?? $defaultAddress);
        $location['map_embed_url'] = (string) ($location['map_embed_url'] ?? $defaultMapEmbedUrl);

        $city['label'] = $label;
        $city['city_label'] = $label;
        $city['city_name'] = (string) ($city['city_name'] ?? $label);
        $city['location'] = $location;
        $city['local_positioning'] = $localPositioning;

        return $city;
    }
}

if (!function_exists('lp_resolve_form_action')) {
    function lp_resolve_form_action(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return (string) ($_SERVER['REQUEST_URI'] ?? base_url('landing.php'));
        }

        if (defined('APP_URL')) {
            return rtrim((string) APP_URL, '/') . '/l/' . rawurlencode($slug);
        }

        return base_url('landing.php?slug=' . rawurlencode($slug));
    }
}

if (!function_exists('lp_build_head_meta')) {
    function lp_build_head_meta(string $slug, array $procedure, array $city): array
    {
        $cityLabel = (string) ($city['city_label'] ?? $city['label'] ?? '');
        $tokens = [
            '{city_label}' => $cityLabel,
            '{CITY_LABEL}' => strtoupper($cityLabel),
        ];

        $seo = is_array($procedure['seo'] ?? null) ? $procedure['seo'] : [];
        $citySeo = is_array($city['seo'] ?? null) ? $city['seo'] : [];

        $title = trim(lp_token_replace((string) ($seo['title_template'] ?? 'Elite Smiles'), $tokens));
        if (!empty($citySeo['title_fragment'])) {
            $title = (string) $citySeo['title_fragment'] . ' | Elite Smiles';
        }

        $description = trim(lp_token_replace((string) ($seo['description_template'] ?? ''), $tokens));
        if (!empty($citySeo['meta_description_fragment'])) {
            $description = (string) $citySeo['meta_description_fragment'];
        }

        return [
            'title' => $title !== '' ? $title : 'Elite Smiles',
            'description' => $description,
            'canonical' => lp_resolve_form_action($slug),
            'image' => !empty($seo['image']) ? lp_media_url((string) $seo['image']) : '',
            'robots' => 'index,follow',
            'schema' => [],
        ];
    }
}

if (!function_exists('landing_pages_build_veneers_view')) {
    function landing_pages_build_veneers_view(array $landingContext, array $runtime = []): array
    {
        $procedure = $landingContext['procedure'] ?? [];
        $city = $landingContext['city'] ?? [];
        $angle = $landingContext['angle'] ?? [];
        $registry = $landingContext['registry'] ?? [];
        $cityLabel = (string) ($city['city_label'] ?? $city['label'] ?? 'Draper');
        $procedureKey = (string) ($landingContext['procedure_key'] ?? 'veneers');
        $angleKey = (string) ($landingContext['angle_key'] ?? '');
        $pageSlug = (string) ($landingContext['slug'] ?? '');
        $location = is_array($city['location'] ?? null) ? $city['location'] : [];
        $locationModules = is_array($registry['location_modules'] ?? null) ? $registry['location_modules'] : [];
        $locationVariant = lp_normalize_location_variant((string) ($location['module_variant'] ?? $procedure['location']['default_variant'] ?? 'convenience'));
        $moduleContent = $locationModules[$locationVariant] ?? $locationModules['default'] ?? [];
        $heroImageUrl = (string) (
            $runtime['heroImageUrl']
            ?? $landingContext['pageRow']['hero_image']
            ?? $landingContext['page']['hero_image']
            ?? ''
        );
        if ($pageSlug === 'veneers-draper-v1') {
            $heroImageUrl = 'assets/img/landings/veneers-hero-landing-final.png';
        }
        if ($heroImageUrl === '') {
            $heroImageUrl = 'assets/img/landings/veneers-hero-landing-final.png';
        }
        $heroImageUrl = lp_prefer_optimized_veneer_image($heroImageUrl);
        $heroImageUrl = lp_media_url($heroImageUrl);

        $galleryImages = $runtime['galleryImages'] ?? [
            [
                'src' => lp_media_url('assets/img/landings/veneers-ad-final.png'),
                'alt' => 'Benefits of veneers creative for Elite Smiles',
                'caption' => 'Veneers can improve brightness, shape, symmetry, and natural-looking confidence.',
            ],
            [
                'src' => lp_media_url('assets/img/landings/veneers-gallery-2-meta.jpg'),
                'alt' => 'Natural-looking veneers close-up',
            ],
            [
                'src' => lp_media_url('assets/img/landings/veneers-gallery-3-meta.jpg'),
                'alt' => 'Elite Smiles veneers result',
            ],
        ];
        if (is_array($galleryImages)) {
            $galleryImages = array_map(static function ($image) {
                if (is_array($image) && isset($image['src'])) {
                    $image['src'] = lp_prefer_optimized_veneer_image((string) $image['src']);
                    $image['src'] = lp_media_url((string) $image['src']);
                }
                if (is_array($image) && isset($image['url'])) {
                    $image['url'] = lp_prefer_optimized_veneer_image((string) $image['url']);
                    $image['url'] = lp_media_url((string) $image['url']);
                }

                return $image;
            }, $galleryImages);
        }
        $beforeAfterImage = (string) ($runtime['beforeAfterImage'] ?? '');
        if ($beforeAfterImage === '') {
            $beforeAfterImage = 'assets/img/landings/veneers-before-after-final.png';
        }
        $beforeAfterImage = lp_prefer_optimized_veneer_image($beforeAfterImage);
        $beforeAfterImage = lp_media_url($beforeAfterImage);

        $tokens = [
            '{city_label}' => $cityLabel,
            '{CITY_LABEL}' => strtoupper($cityLabel),
            '{city_label_upper}' => strtoupper($cityLabel),
        ];

        $authorityRef = (string) (($procedure['shared_refs']['authority_ref'] ?? '') ?: 'dr_meden_master');
        $reviewsRef = (string) (($procedure['shared_refs']['reviews_ref'] ?? '') ?: 'elite_smiles_master');

        $authority = $registry['doctor_authority'][$authorityRef] ?? [];
        $reviews = $registry['reviews'][$reviewsRef] ?? [];

        $sections = [
            'hero' => [
                'hero_eyebrow' => lp_token_replace((string) ($angle['hero_eyebrow'] ?? ''), $tokens),
                'hero_title' => lp_token_replace((string) ($angle['hero_title'] ?? ''), $tokens),
                'hero_body' => lp_token_replace((string) ($angle['hero_body'] ?? ''), $tokens),
                'mobile_title' => 'Natural-Looking Veneers in Draper',
                'mobile_body' => 'Designed to look refined, healthy, and believable - not fake.',
                'support_line' => 'Free private consultation with Dr. Walter Meden DDS. 0% financing may be available for qualified patients.',
                'image_url' => $heroImageUrl,
                'image_alt' => lp_token_replace((string) ($angle['hero_title'] ?? 'Elite Smiles'), $tokens),
                'button_text' => (string) (($procedure['cta']['button_label'] ?? 'Start My Free Veneers Consultation')),
            ],
            'quiz_entry' => [
                'enabled' => true,
                'eyebrow' => 'START HERE',
            ],
            'doctor_trust_compact' => [
                'enabled' => true,
            ],
            'offer' => [
                'enabled' => (bool) (($procedure['defaults']['show_offer'] ?? true)),
                'value_label' => (string) ($procedure['defaults']['offer_badge'] ?? 'FREE PRIVATE CONSULTATION'),
                'title' => 'What Your Free Veneers Consultation Includes',
                'body' => 'Complete the short intake and our team will review your smile goals, timing, pricing questions, and whether veneers may be the right fit before recommending the best next step.',
                'items' => [
                    'Private veneers consultation with Elite Smiles',
                    'Personalized smile and cosmetic goals review',
                    'Case-by-case pricing conversation',
                    '0% financing review for qualified patients',
                ],
                'button_text' => (string) (($procedure['defaults']['offer_cta'] ?? $procedure['cta']['button_label'] ?? 'Start My Free Veneers Consultation')),
            ],
            'authority' => [
                'enabled' => (bool) (($procedure['defaults']['show_authority'] ?? true)),
                'eyebrow' => lp_token_replace((string) ($authority['eyebrow'] ?? ''), $tokens),
                'title' => lp_token_replace((string) ($authority['title'] ?? ''), $tokens),
                'body' => lp_token_replace((string) ($authority['body'] ?? ''), $tokens),
                'items' => $authority['items'] ?? [],
            ],
            'longform' => [
                'enabled' => true,
                'items' => array_map(static function (array $item) use ($tokens): array {
                    return [
                        'eyebrow' => lp_token_replace((string) ($item['eyebrow'] ?? ''), $tokens),
                        'title' => lp_token_replace((string) ($item['title'] ?? ''), $tokens),
                        'body' => lp_token_replace((string) ($item['body'] ?? ''), $tokens),
                    ];
                }, is_array($angle['longform'] ?? null) ? $angle['longform'] : []),
            ],
            'location_convenience' => [
                'enabled' => (bool) (($procedure['location']['enabled'] ?? false) && ($procedure['defaults']['show_location_convenience'] ?? true)),
                'variant' => $locationVariant,
                'city_label' => $cityLabel,
                'travel_frame' => lp_token_replace((string) ($location['travel_frame'] ?? $moduleContent['body'] ?? ''), $tokens),
                'convenience_note' => lp_token_replace((string) ($location['convenience_note'] ?? $moduleContent['title'] ?? ''), $tokens),
                'visit_planning_note' => lp_token_replace((string) ($location['visit_planning_note'] ?? $moduleContent['cta'] ?? ''), $tokens),
                'address' => (string) ($location['address'] ?? ''),
                'map_embed_url' => (string) ($location['map_embed_url'] ?? ''),
                'show_map' => (bool) ($location['show_map'] ?? false),
            ],
            'reviews' => [
                'enabled' => (bool) (($procedure['defaults']['show_reviews'] ?? true)),
                'eyebrow' => lp_token_replace((string) ($reviews['eyebrow'] ?? ''), $tokens),
                'title' => lp_token_replace((string) ($reviews['title'] ?? ''), $tokens),
                'body' => lp_token_replace((string) ($reviews['body'] ?? ''), $tokens),
                'items' => $reviews['items'] ?? [],
            ],
            'gallery' => [
                'enabled' => !empty($galleryImages),
                'eyebrow' => 'SMILE DESIGN DETAILS',
                'title' => 'Examples Of The Refined Veneer Aesthetic Patients Often Want',
                'body' => 'Many patients want veneers that look polished, healthy, and naturally suited to the face. These images help illustrate the kind of clean, believable cosmetic direction patients are usually trying to describe.',
                'items' => is_array($galleryImages) ? $galleryImages : [],
            ],
            'before_after' => [
                'enabled' => $beforeAfterImage !== '',
                'eyebrow' => 'BEFORE AND AFTER',
                'title' => 'What A Well-Planned Veneer Upgrade Can Change',
                'body' => 'A successful veneer case usually improves harmony, brightness, proportion, and overall polish without making the smile feel artificial.',
                'image_url' => $beforeAfterImage,
                'image_alt' => 'Elite Smiles veneers before and after',
                'disclaimer' => 'Individual results may vary. A consultation is needed to determine the right treatment for your smile.',
                'extra_images' => [
                    [
                        'src' => lp_media_url('assets/img/landings/veneers-website-before-after-1.png'),
                        'alt' => 'Elite Smiles veneer before and after example',
                    ],
                    [
                        'src' => lp_media_url('assets/img/landings/veneers-website-before-after-2.png'),
                        'alt' => 'Elite Smiles veneer smile before and after example',
                    ],
                ],
            ],
            'faq' => [
                'enabled' => (bool) (($procedure['defaults']['show_faq'] ?? true)),
                'title' => 'Veneers Questions Patients Usually Ask First',
                'items' => array_map(static function (array $item) use ($tokens): array {
                    return [
                        'question' => lp_token_replace((string) ($item['question'] ?? ''), $tokens),
                        'answer' => lp_token_replace((string) ($item['answer'] ?? ''), $tokens),
                    ];
                }, is_array($angle['faq'] ?? null) ? $angle['faq'] : []),
            ],
            'final_cta' => [
                'enabled' => (bool) (($procedure['defaults']['show_final_cta'] ?? true)),
                'final_cta_title' => lp_token_replace((string) ($angle['final_cta_title'] ?? ''), $tokens),
                'final_cta_body' => lp_token_replace((string) ($angle['final_cta_body'] ?? ''), $tokens),
                'button_text' => (string) (($procedure['cta']['final_button_label'] ?? 'Start My Free Veneers Consultation')),
            ],
        ];

        if ($pageSlug === 'veneers-draper-google-v2') {
            $sections['hero']['hero_title'] = 'Natural-Looking Porcelain Veneers in Draper';
            $sections['hero']['hero_body'] = 'Compare your options, see what is possible for your smile, and get private guidance from Dr. Walter Meden DDS. Your consultation is complimentary, and 0% financing may be available for qualified patients.';
            $sections['hero']['mobile_title'] = 'Natural-Looking Veneers in Draper';
            $sections['hero']['mobile_body'] = 'Private consultation, clear options, and a smile plan designed to look believable.';
            $sections['hero']['button_text'] = 'Start My Free Veneers Consultation';
            $sections['offer']['body'] = 'This page is built for patients actively comparing veneers in Draper. The short intake helps our team understand your goals, timing, pricing questions, and financing needs before we contact you.';
            $sections['offer']['items'] = [
                'Private consultation with Dr. Walter Meden DDS',
                'Natural-looking veneers and smile design review',
                'Case-by-case pricing conversation',
                '0% financing review for qualified patients',
            ];
            $sections['final_cta']['final_cta_title'] = 'Ready To See What Veneers Could Look Like For You?';
            $sections['final_cta']['final_cta_body'] = 'Start the short intake and our team will text or call to help schedule your free private veneers consultation.';
        }

        return [
            'page_slug' => $pageSlug,
            'procedure_key' => $procedureKey,
            'angle_key' => $angleKey,
            'sections' => $sections,
            'section_order' => $procedure['section_order'] ?? ['hero', 'quiz_entry', 'doctor_trust_compact', 'offer', 'longform', 'before_after', 'authority', 'reviews', 'faq', 'final_cta'],
            'header_cta_text' => (string) (($procedure['cta']['button_label'] ?? 'Start My Free Veneers Consultation')),
        ];
    }
}

if (!function_exists('landing_pages_build_implants_view')) {
    function landing_pages_build_implants_view(array $landingContext, array $runtime = []): array
    {
        $procedure = $landingContext['procedure'] ?? [];
        $city = $landingContext['city'] ?? [];
        $angle = $landingContext['angle'] ?? [];
        $registry = $landingContext['registry'] ?? [];
        $cityLabel = (string) ($city['city_label'] ?? $city['label'] ?? 'Draper');
        $procedureKey = (string) ($landingContext['procedure_key'] ?? 'implants');
        $angleKey = (string) ($landingContext['angle_key'] ?? '');
        $pageSlug = (string) ($landingContext['slug'] ?? '');
        $location = is_array($city['location'] ?? null) ? $city['location'] : [];
        $locationModules = is_array($registry['location_modules'] ?? null) ? $registry['location_modules'] : [];
        $locationVariant = lp_normalize_location_variant((string) ($location['module_variant'] ?? $procedure['location']['default_variant'] ?? 'convenience'));
        $moduleContent = $locationModules[$locationVariant] ?? $locationModules['default'] ?? [];
        $heroImageUrl = (string) ($runtime['heroImageUrl'] ?? '');
        if ($heroImageUrl === '') {
            $heroImageByAngle = [
                'premium_trust' => base_url('assets/img/landings/implants-hero-premium-trust.jpg'),
                'financing' => base_url('assets/img/landings/implants-hero-financing.jpg'),
                'transformation' => base_url('assets/img/landings/implants-hero-transformation.jpg'),
                'education_comparison' => base_url('assets/img/landings/implants-hero-education-comparison.jpg'),
            ];

            $heroImageUrl = (string) ($heroImageByAngle[$angleKey] ?? $heroImageByAngle['premium_trust']);
        }
        $galleryImages = $runtime['galleryImages'] ?? [
            [
                'src' => base_url('assets/img/landings/implants-natural-smile-result.jpg'),
                'alt' => 'Natural-looking dental implant smile result',
                'caption' => 'Natural-looking implant restoration designed to blend with the smile.',
            ],
            [
                'src' => base_url('assets/img/landings/implants-patient-lifestyle.jpg'),
                'alt' => 'Patient confidence after implant treatment',
                'caption' => 'Many patients want a replacement that feels stable and easy to live with day to day.',
            ],
            [
                'src' => base_url('assets/img/landings/male-hero-02.jpg'),
                'alt' => 'Doctor-led implant consultation environment',
                'caption' => 'A thoughtful consultation helps clarify candidacy, timing, and the right plan.',
            ],
        ];
        $beforeAfterImage = (string) ($runtime['beforeAfterImage'] ?? '');
        if ($beforeAfterImage === '') {
            $beforeAfterImage = base_url('assets/img/landings/implants-before-after-01.jpg');
        }

        $tokens = [
            '{city_label}' => $cityLabel,
            '{CITY_LABEL}' => strtoupper($cityLabel),
            '{city_label_upper}' => strtoupper($cityLabel),
        ];

        $sharedRefs = is_array($procedure['shared_refs'] ?? null) ? $procedure['shared_refs'] : [];
        $authorityRef = (string) ($sharedRefs['authority_ref'] ?? 'implants_dr_meden');
        $reviewsRef = (string) ($sharedRefs['reviews_ref'] ?? 'implants_google_proof');

        $authority = $registry['doctor_authority'][$authorityRef] ?? [];
        $reviews = $registry['reviews'][$reviewsRef] ?? [];
        $financingPartners = [];

        if ($angleKey === 'financing') {
            $financingPartners = [
                ['label' => 'Cherry', 'url' => 'https://withcherry.com/'],
                ['label' => 'Sunbit', 'url' => 'https://www.sunbit.com/'],
                ['label' => 'CareCredit', 'url' => 'https://www.carecredit.com/'],
                ['label' => 'Mountain America Credit Union', 'url' => 'https://www.macu.com/', 'note' => '0% financing available for qualified patients'],
            ];
        }

        $sections = [
            'hero' => [
                'hero_eyebrow' => lp_token_replace((string) ($angle['hero_eyebrow'] ?? ''), $tokens),
                'hero_title' => lp_token_replace((string) ($angle['hero_title'] ?? ''), $tokens),
                'hero_body' => lp_token_replace((string) ($angle['hero_body'] ?? ''), $tokens),
                'image_url' => $heroImageUrl,
                'image_alt' => lp_token_replace((string) ($angle['hero_title'] ?? 'Elite Smiles'), $tokens),
                'button_text' => (string) (($procedure['cta']['button_label'] ?? 'Reserve Your Implant Consultation')),
            ],
            'offer' => [
                'enabled' => (bool) (($procedure['defaults']['show_offer'] ?? true)),
                'value_label' => (string) ($procedure['defaults']['offer_badge'] ?? '$750 VALUE'),
                'title' => 'What the $750 offer may include',
                'body' => 'Complete the short quiz and our team will review your goals, treatment timing, and whether implants may be the right fit before recommending the best next step.',
                'items' => [
                    'Comprehensive implants consultation',
                    'Treatment planning review',
                    'Diagnostic imaging or records as clinically appropriate',
                    'Financial options review',
                ],
                'button_text' => (string) (($procedure['defaults']['offer_cta'] ?? $procedure['cta']['button_label'] ?? 'Reserve Your Implant Consultation')),
                'financing_partners' => $financingPartners,
            ],
            'authority' => [
                'enabled' => (bool) (($procedure['defaults']['show_authority'] ?? true)),
                'eyebrow' => lp_token_replace((string) ($authority['eyebrow'] ?? ''), $tokens),
                'title' => lp_token_replace((string) ($authority['title'] ?? ''), $tokens),
                'body' => lp_token_replace((string) ($authority['body'] ?? ''), $tokens),
                'items' => $authority['items'] ?? [],
            ],
            'longform' => [
                'enabled' => true,
                'items' => array_map(static function (array $item) use ($tokens): array {
                    return [
                        'eyebrow' => lp_token_replace((string) ($item['eyebrow'] ?? ''), $tokens),
                        'title' => lp_token_replace((string) ($item['title'] ?? ''), $tokens),
                        'body' => lp_token_replace((string) ($item['body'] ?? ''), $tokens),
                    ];
                }, is_array($angle['longform'] ?? null) ? $angle['longform'] : []),
            ],
            'gallery' => [
                'enabled' => !empty($galleryImages),
                'eyebrow' => 'RESULT AND LIFESTYLE DIRECTION',
                'title' => 'What Patients Usually Want From Implant Treatment',
                'body' => 'Patients are often looking for a replacement that feels integrated, natural, and dependable in daily life. These images help illustrate that direction while your consultation determines the best clinical path.',
                'items' => is_array($galleryImages) ? $galleryImages : [],
            ],
            'before_after' => [
                'enabled' => $beforeAfterImage !== '',
                'eyebrow' => 'BEFORE AND AFTER',
                'title' => 'A Well-Planned Implant Case Should Restore More Than Space',
                'body' => 'When implant treatment is appropriate, the goal is to restore appearance, function, and stability in a way that feels believable and well integrated with the rest of the smile.',
                'image_url' => $beforeAfterImage,
                'image_alt' => 'Elite Smiles dental implants before and after',
            ],
            'location_convenience' => [
                'enabled' => (bool) (($procedure['location']['enabled'] ?? false) && ($procedure['defaults']['show_location_convenience'] ?? true)),
                'variant' => $locationVariant,
                'city_label' => $cityLabel,
                'travel_frame' => lp_token_replace((string) ($location['travel_frame'] ?? $moduleContent['body'] ?? ''), $tokens),
                'convenience_note' => lp_token_replace((string) ($location['convenience_note'] ?? $moduleContent['title'] ?? ''), $tokens),
                'visit_planning_note' => lp_token_replace((string) ($location['visit_planning_note'] ?? $moduleContent['cta'] ?? ''), $tokens),
                'address' => (string) ($location['address'] ?? ''),
                'map_embed_url' => (string) ($location['map_embed_url'] ?? ''),
                'show_map' => (bool) ($location['show_map'] ?? false),
            ],
            'reviews' => [
                'enabled' => (bool) (($procedure['defaults']['show_reviews'] ?? true)),
                'eyebrow' => lp_token_replace((string) ($reviews['eyebrow'] ?? ''), $tokens),
                'title' => lp_token_replace((string) ($reviews['title'] ?? ''), $tokens),
                'body' => lp_token_replace((string) ($reviews['body'] ?? ''), $tokens),
                'items' => $reviews['items'] ?? [],
            ],
            'faq' => [
                'enabled' => (bool) (($procedure['defaults']['show_faq'] ?? true)),
                'title' => 'Dental Implant Questions Patients Usually Ask First',
                'items' => array_map(static function (array $item) use ($tokens): array {
                    return [
                        'question' => lp_token_replace((string) ($item['question'] ?? ''), $tokens),
                        'answer' => lp_token_replace((string) ($item['answer'] ?? ''), $tokens),
                    ];
                }, is_array($angle['faq'] ?? null) ? $angle['faq'] : []),
            ],
            'final_cta' => [
                'enabled' => (bool) (($procedure['defaults']['show_final_cta'] ?? true)),
                'final_cta_title' => lp_token_replace((string) ($angle['final_cta_title'] ?? ''), $tokens),
                'final_cta_body' => lp_token_replace((string) ($angle['final_cta_body'] ?? ''), $tokens),
                'button_text' => (string) (($procedure['cta']['final_button_label'] ?? 'Reserve Your Implant Consultation')),
            ],
        ];

        return [
            'page_slug' => $pageSlug,
            'procedure_key' => $procedureKey,
            'angle_key' => $angleKey,
            'sections' => $sections,
            'section_order' => $procedure['section_order'] ?? ['hero', 'offer', 'authority', 'longform', 'gallery', 'before_after', 'location_convenience', 'reviews', 'faq', 'final_cta'],
            'header_cta_text' => (string) (($procedure['cta']['button_label'] ?? 'Reserve Your Implant Consultation')),
        ];
    }
}
