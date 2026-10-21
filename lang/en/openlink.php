<?php

return [
    'validation' => [
        'preview_image_unreadable' => 'The preview image could not be read. Upload a PNG, JPG, or WebP image up to 4096 × 4096 pixels.',
        'preview_text_markup' => 'Remove HTML tags and control characters from the :attribute.',
        'app_association_domain_unavailable' => 'Apps can only be added to an active workspace domain.',
        'app_association_duplicate' => 'This app is already associated with this domain.',
        'app_bundle_id_invalid' => 'Enter a bundle ID like com.example.app.',
        'app_fingerprint_invalid' => 'Enter a SHA-256 fingerprint as 32 colon-separated hex pairs.',
        'app_package_name_invalid' => 'Enter a package name like com.example.app.',
        'app_path_invalid' => 'Start with / and use letters, numbers, and . _ ~ - / * ? % only.',
        'app_path_reserved' => 'This path is reserved by Openlink.',
        'app_team_id_invalid' => 'Enter the 10-character Team ID, like ABCDE12345.',
        'domain_has_links' => 'Move or delete links using this domain before transferring it.',
        'slug_duplicate' => 'This short link path is already used on this domain.',
        'slug_invalid' => 'Use letters, numbers, dashes, underscores, and slashes only.',
        'slug_reserved' => 'This short link path is reserved by the application.',
        'link_page_path_invalid' => 'Use lowercase letters, numbers, and dashes only, starting and ending with a letter or number.',
        'link_page_path_taken' => 'This page path is already in use.',
        'link_page_logo_too_large' => 'Use an image of at most 2048 by 2048 pixels.',
    ],
];
