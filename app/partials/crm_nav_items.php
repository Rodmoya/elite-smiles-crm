<?php
declare(strict_types=1);

// One menu for all CRM shells; hidden modules retain their routes and data.
$crmNavItems = [
    ['key' => 'dashboard', 'label' => 'Command Center', 'href' => base_url('dashboard.php'), 'icon' => 'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10.5z', 'show' => true],
    ['key' => 'leads', 'label' => 'Leads', 'href' => base_url('leads.php'), 'icon' => 'M4 6h16M4 12h16M4 18h10', 'show' => true],
    ['key' => 'smile_design', 'label' => 'Smile Design', 'href' => base_url('smile-design'), 'icon' => 'M12 3c3.5 0 6.5 2.1 7.8 5.1C18.4 15.2 15.8 21 12 21S5.6 15.2 4.2 8.1C5.5 5.1 8.5 3 12 3zM8.5 10c.8 1.2 2 1.8 3.5 1.8s2.7-.6 3.5-1.8', 'show' => true],
    ['key' => 'patient_experience', 'label' => 'Patient Experience', 'href' => base_url('patient-experience.php?tab=patients'), 'icon' => 'M12 21s7-4.4 7-11a7 7 0 0 0-14 0c0 6.6 7 11 7 11zM9 10h6M12 7v6', 'show' => function_exists('auth_has_role') ? auth_has_role('admin', 'marketing_manager', 'staff') : false],
    ['key' => 'patient_contracts', 'label' => 'Contract Creator', 'href' => base_url('patient-experience.php?tab=contracts'), 'icon' => 'M6 3h9l3 3v15H6zM14 3v4h4M9 11h6M9 15h6', 'show' => function_exists('auth_has_role') ? auth_has_role('admin', 'marketing_manager', 'staff') : false],
    ['key' => 'elite_ai_lab', 'label' => 'Elite AI Lab', 'href' => base_url('elite-ai-lab.php'), 'icon' => 'M8 5h8M7 9h10M6 13h8m-6 4h4M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z', 'show' => function_exists('auth_has_role') ? auth_has_role('admin', 'marketing_manager') : false],
    ['key' => 'marketing', 'label' => 'Ads Performance', 'href' => base_url('marketing.php'), 'icon' => 'M4 19V5m4 14v-8m4 8V7m4 12v-5m4 5V9', 'show' => $crmCanUseMarketing, 'group' => 'Marketing'],
    ['key' => 'landing_pages', 'label' => 'Landing Pages', 'href' => base_url('landing_pages.php'), 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14H4V5zm4 3h8M8 12h8M8 16h5', 'show' => $crmCanUseMarketing, 'group' => 'Marketing'],
    ['key' => 'patient_mailings', 'label' => 'Mailing Campaigns', 'href' => base_url('patient-mailings.php'), 'icon' => 'M4 6h16v12H4V6zm0 0 8 6 8-6M8 17h8', 'show' => $crmCanUseMarketing && $crmHasPatientMailings, 'group' => 'Marketing'],
    ['key' => 'social_studio', 'label' => 'Social Studio', 'href' => base_url('social-studio.php'), 'icon' => 'M4 5h16v14H4zM8 9h8M8 13h5M16 17l4 4M17 14h3v3', 'show' => $crmCanUseMarketing, 'group' => 'Marketing'],
    ['key' => 'doc_library', 'label' => 'Doc Library', 'href' => base_url('doc-library.php'), 'icon' => 'M9 2h6l3 3v17a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V3l3-1zM9 2v4h6V2M9 6h6M9 10h6M9 14h6', 'show' => $crmCanUseMarketing, 'group' => 'Marketing'],
    ['key' => 'email_status', 'label' => 'Email Status', 'href' => base_url('email_status.php'), 'icon' => 'M4 6h16v12H4V6zm0 0 8 7 8-7', 'show' => true],
    ['key' => 'settings', 'label' => 'Settings', 'href' => base_url('crm-settings.php'), 'icon' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM4 12h2m12 0h2M12 4v2m0 12v2M6.3 6.3l1.4 1.4m8.6 8.6 1.4 1.4m0-11.4-1.4 1.4m-8.6 8.6-1.4 1.4', 'show' => function_exists('auth_has_role') ? auth_has_role('admin', 'marketing_manager') : false],
    ['key' => 'users', 'label' => 'Users', 'href' => base_url('users.php'), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'show' => function_exists('auth_has_role') ? auth_has_role('admin') : false],
];
$crmNavItems = array_values(array_filter($crmNavItems, static fn(array $item): bool => !empty($item['show'])));

