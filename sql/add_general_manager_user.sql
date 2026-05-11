-- Add dedicated General Manager account for sign-off names
-- Temp password: password (change after first login)
INSERT INTO `users`
    (`username`, `password`, `full_name`, `first_name`, `last_name`, `employee_id`, `role`, `email`, `is_active`, `created_at`, `updated_at`)
SELECT
    'Villaneuva',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Lovely Jean',
    'Lovely',
    'Jean',
    NULL,
    'general_manager',
    'ragasibrian2@gmail.com',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `users` WHERE `username` = 'Villaneuva'
);
