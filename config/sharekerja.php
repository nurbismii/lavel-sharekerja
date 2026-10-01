<?php

return [
    'publication_days' => 30,
    'timezone' => 'Asia/Makassar',
    'cv_max_kb' => 5120,
    'email_batch_size' => 100,
    'email_per_minute' => (int) env('JOB_EMAIL_PER_MINUTE', 0),
    'email_per_hour' => (int) env('JOB_EMAIL_PER_HOUR', 0),
    'admin_access_enabled' => (bool) env('ADMIN_ACCESS_ENABLED', false),
    'categories' => ['Teknologi', 'Marketing', 'Desain', 'Keuangan', 'Operasional', 'Human Resources', 'Sales', 'Customer Service'],
    'employment_types' => ['Full-time', 'Part-time', 'Kontrak', 'Magang', 'Freelance'],
    'levels' => ['Entry Level', 'Junior', 'Senior', 'Manager'],
];
