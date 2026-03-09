<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · GoogleAuth
 *  Google OAuth2 sign-in flow.
 * ═══════════════════════════════════════════════════════════════════
 */

class GoogleAuth
{
    private static string $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private static string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private static string $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public static function getAuthUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;

        return self::$authUrl . '?' . http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
    }

    public static function handleCallback(): ?array
    {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';

        if (!$code || !$state || $state !== ($_SESSION['google_oauth_state'] ?? '')) {
            return null;
        }

        unset($_SESSION['google_oauth_state']);

        // Exchange code for token
        $tokenResponse = self::httpPost(self::$tokenUrl, [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]);

        if (!$tokenResponse || empty($tokenResponse['access_token'])) {
            return null;
        }

        // Fetch user info
        $userInfo = self::httpGet(self::$userInfoUrl, $tokenResponse['access_token']);

        if (!$userInfo || empty($userInfo['email'])) {
            return null;
        }

        return [
            'id'      => $userInfo['id'] ?? '',
            'email'   => $userInfo['email'],
            'name'    => $userInfo['name'] ?? $userInfo['email'],
            'picture' => $userInfo['picture'] ?? null,
        ];
    }

    private static function httpPost(string $url, array $data): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response, true) : null;
    }

    private static function httpGet(string $url, string $token): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response, true) : null;
    }
}