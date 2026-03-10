<?php
/**
 * =====================================================================
 *  AVIDMOCK SAT -- Referral API
 *
 *  GET  ?action=code   — Get referral code
 *  GET  ?action=stats  — Get referral stats
 *  POST ?action=apply  — Apply referral code during signup
 * =====================================================================
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/SocialShare.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ── GET REFERRAL CODE ───────────────────────────────
        case 'code':
            if (!is_logged_in()) json_response(['success' => false, 'error' => 'Login required'], 401);
            $userId = current_user_id();
            $code = SocialShare::getReferralCode($userId);
            json_response([
                'success'   => true,
                'code'      => $code,
                'share_url' => STUDENT_URL . '/referrals/?ref=' . $code,
            ]);

        // ── GET REFERRAL STATS ──────────────────────────────
        case 'stats':
            if (!is_logged_in()) json_response(['success' => false, 'error' => 'Login required'], 401);
            $userId = current_user_id();
            $stats = SocialShare::getReferralStats($userId);
            json_response(['success' => true, ...$stats]);

        // ── APPLY REFERRAL CODE ─────────────────────────────
        case 'apply':
            if ($method !== 'POST') json_response(['success' => false, 'error' => 'POST required'], 405);
            if (!is_logged_in()) json_response(['success' => false, 'error' => 'Login required'], 401);

            $userId = current_user_id();
            $code = strtoupper(trim($_POST['code'] ?? ''));
            if (!$code) json_response(['success' => false, 'error' => 'Code required'], 400);

            $result = SocialShare::processReferral($code, $userId);
            if ($result) {
                json_response(['success' => true, 'message' => 'Referral applied! You earned +50 XP']);
            } else {
                json_response(['success' => false, 'error' => 'Invalid or already used referral code'], 400);
            }

        // ── GENERATE SHARE URL ──────────────────────────────
        case 'share':
            if (!is_logged_in()) json_response(['success' => false, 'error' => 'Login required'], 401);
            $userId = current_user_id();
            $type = $_GET['type'] ?? 'scorecard';
            $token = SocialShare::generateShareUrl($userId, $type);
            $url = STUDENT_URL . '/share/scorecard.php?t=' . $token;
            json_response(['success' => true, 'token' => $token, 'url' => $url]);

        // ── TOP REFERRERS ───────────────────────────────────
        case 'top':
            $top = SocialShare::getTopReferrers(10);
            json_response(['success' => true, 'referrers' => $top]);

        default:
            json_response(['success' => false, 'error' => 'Unknown action'], 400);
    }

} catch (RuntimeException $e) {
    json_response(['success' => false, 'error' => $e->getMessage()], 400);
} catch (\Throwable $e) {
    error_log('[referral.php] ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
