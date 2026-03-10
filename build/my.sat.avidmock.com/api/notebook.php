<?php
/**
 * api/notebook.php — Smart Notebook API
 * CRUD for notebook entries + AI-powered recommendations
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/SmartNotebook.php';

Auth::requireStudent();
$userId = (int) $_SESSION['user_id'];

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $filters = [
            'folder'  => $_GET['folder']  ?? '',
            'domain'  => $_GET['domain']  ?? '',
            'starred' => !empty($_GET['starred']),
            'type'    => $_GET['type']    ?? '',
            'limit'   => (int)($_GET['limit']  ?? 20),
            'offset'  => (int)($_GET['offset'] ?? 0),
        ];
        echo json_encode(SmartNotebook::getEntries($userId, $filters));
        break;

    case 'add_text':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $text   = trim($_POST['text'] ?? '');
        $folder = trim($_POST['folder'] ?? 'General');
        if (!$text) { echo json_encode(['error' => 'Text required']); break; }
        echo json_encode(SmartNotebook::addText($userId, $text, $folder));
        break;

    case 'add_scan':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $input    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $image    = $input['image'] ?? '';
        $aiResult = $input['ai_result'] ?? null;
        if (!$image) { echo json_encode(['error' => 'Image required']); break; }
        echo json_encode(SmartNotebook::addScan($userId, $image, $aiResult));
        break;

    case 'star':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $entryId = (int)($_POST['entry_id'] ?? 0);
        echo json_encode(['success' => SmartNotebook::toggleStar($userId, $entryId)]);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $entryId = (int)($_POST['entry_id'] ?? 0);
        echo json_encode(['success' => SmartNotebook::delete($userId, $entryId)]);
        break;

    case 'folders':
        echo json_encode(SmartNotebook::getFolders($userId));
        break;

    case 'stats':
        echo json_encode(SmartNotebook::getDomainStats($userId));
        break;

    case 'recommendations':
        echo json_encode(SmartNotebook::getRecommendations($userId));
        break;

    default:
        echo json_encode(['error' => 'Unknown action', 'valid' => ['list','add_text','add_scan','star','delete','folders','stats','recommendations']]);
}
