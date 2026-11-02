<?php
/*
captcha.php = checks the sign-up form's Cloudflare Turnstile response.
AuthController uses this to prevent sign-up unless Cloudflare confirms the check.
The secret key stays on the server and is sent only to Cloudflare.
*/

// A successful check returns null. Otherwise return a message for the sign-up form.
function captcha_check(): ?string
{
    $token = $_POST['cf-turnstile-response'] ?? '';

    // A missing or oversized response cannot be a valid Turnstile token.
    if (!is_string($token) || $token === '' || strlen($token) > 2048) {
        return 'Please complete the human check.';
    }

    // The browser's response alone is not proof: Cloudflare must check it on the server.
    $answer = captcha_ask_cloudflare([
        'secret'   => TURNSTILE_SECRET_KEY,
        'response' => $token,
    ]);

    if ($answer === null) {
        return 'We could not check the captcha right now. Please try again in a moment.';
    }
    if (($answer['success'] ?? false) !== true) {
        error_log('turnstile failed: ' . json_encode($answer['error-codes'] ?? []));
        return 'The captcha check failed. Please try again.';
    }

    return null;
}

// Send the token to Cloudflare over HTTPS. No answer means sign-up must not continue.
function captcha_ask_cloudflare(array $fields): ?array
{
    if (!function_exists('curl_init')) {
        error_log('turnstile: the PHP cURL extension is not available');
        return null;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($ch === false) {
        error_log('turnstile: could not start the cURL request');
        return null;
    }

    // Timeouts prevent a slow connection from keeping the sign-up page waiting indefinitely.
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false) {
        error_log('turnstile: request failed: ' . curl_error($ch));
    }
    curl_close($ch);

    if ($body === false) {
        return null;
    }
    if ($status !== 200) {
        error_log('turnstile: Cloudflare returned HTTP ' . $status);
        return null;
    }

    // Invalid JSON cannot confirm a visitor either.
    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('turnstile: Cloudflare returned an invalid response');
        return null;
    }
    return $data;
}
