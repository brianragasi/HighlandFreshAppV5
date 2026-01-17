<!DOCTYPE html>
<html lang="en" data-theme="emerald">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Highland Fresh'; ?></title>
    
    <!-- Tailwind CSS + DaisyUI CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom minimal CSS -->
    <link rel="stylesheet" href="<?php echo $basePath ?? ''; ?>css/app.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Bento card hover effect */
        .bento-card {
            @apply transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg;
        }
    </style>
    
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="bg-base-200 min-h-screen">
