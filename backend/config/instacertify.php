<?php

return [

    'content_path' => env('CONTENT_PATH', base_path('../content')),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:4321'),

    // Where the admin "Preview" button opens drafts; the preview server sits behind the site's own domain in production.
    'preview_url' => env('PREVIEW_URL'),

    // Uploaded images live in storage/app/public (the app_storage volume in Docker).
    'media_disk' => env('MEDIA_DISK', 'public'),

    'legacy' => [
        'api_url' => env('LEGACY_API_URL', 'https://api.instacertify.com/api'),
        'site_url' => env('LEGACY_SITE_URL', 'https://instacertify.com'),
    ],

    'leads' => [
        'notify_email' => env('LEAD_NOTIFY_EMAIL', 'contact@instacertify.com'),
        'webhook_url' => env('LEAD_WEBHOOK_URL'),
        'webhook_secret' => env('LEAD_WEBHOOK_SECRET'),
    ],

    'careers' => [
        'notify_email' => env('CAREERS_NOTIFY_EMAIL', env('LEAD_NOTIFY_EMAIL', 'contact@instacertify.com')),
        // Must be a private disk: CVs are only ever served through the permission-checked admin route.
        'cv_disk' => env('CV_DISK', 'local'),
        'cv_max_kb' => 5120,
    ],

    // The site builder (docker-compose "builder" service) that publishes admin edits to the static website.
    'frontend_rebuild_hook' => env('FRONTEND_REBUILD_HOOK'),
    'site_publish_token' => env('SITE_PUBLISH_TOKEN'),
    // Saves within this many seconds of each other go live in one rebuild.
    'publish_delay' => 60,

];
