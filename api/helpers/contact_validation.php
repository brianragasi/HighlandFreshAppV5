<?php

if (!function_exists('hfNormalizeContactEmail')) {
    function hfNormalizeContactEmail($value) {
        $email = strtolower(trim((string) $value));
        return $email === '' ? null : $email;
    }
}

if (!function_exists('hfIsValidContactEmail')) {
    function hfIsValidContactEmail($value) {
        $email = hfNormalizeContactEmail($value);
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        return $domain !== '' && strpos($domain, '.') !== false;
    }
}

if (!function_exists('hfNormalizePhilippinePhone')) {
    function hfNormalizePhilippinePhone($value) {
        $phone = trim((string) $value);
        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/[\s().-]+/', '', $phone);
        if (strpos($phone, '+63') === 0) {
            $phone = '0' . substr($phone, 3);
        } elseif (strpos($phone, '63') === 0 && strlen($phone) >= 12) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }
}

if (!function_exists('hfIsValidPhilippinePhone')) {
    function hfIsValidPhilippinePhone($value) {
        $phone = hfNormalizePhilippinePhone($value);
        if ($phone === null || !ctype_digit($phone)) {
            return false;
        }

        $isMobile = preg_match('/^09\d{9}$/', $phone) === 1;
        $isMetroManilaLandline = preg_match('/^02\d{8}$/', $phone) === 1;
        $isProvincialLandline = preg_match('/^0[3-8]\d{8,9}$/', $phone) === 1;

        return $isMobile || $isMetroManilaLandline || $isProvincialLandline;
    }
}

if (!function_exists('hfValidateContactPayload')) {
    function hfValidateContactPayload(array $data, array $phoneFields = ['phone'], $emailField = 'email') {
        $normalized = $data;
        $errors = [];

        foreach ($phoneFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $phone = hfNormalizePhilippinePhone($data[$field]);
            if ($phone !== null && !hfIsValidPhilippinePhone($phone)) {
                $errors[$field] = 'Use an 11-digit mobile number beginning with 09, or a valid Philippine landline.';
            } else {
                $normalized[$field] = $phone;
            }
        }

        if (array_key_exists($emailField, $data)) {
            $email = hfNormalizeContactEmail($data[$emailField]);
            if ($email !== null && !hfIsValidContactEmail($email)) {
                $errors[$emailField] = 'Enter a complete email address such as name@example.com.';
            } else {
                $normalized[$emailField] = $email;
            }
        }

        return [
            'data' => $normalized,
            'errors' => $errors,
        ];
    }
}
