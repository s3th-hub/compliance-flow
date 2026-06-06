<?php
// documents/view.php — Serve uploaded file securely
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db     = db();
$doc_id = (int) ($_GET['id'] ?? 0);

if (!$doc_id) { http_response_code(404); exit('Not found'); }

$stmt = $db->prepare('SELECT * FROM documents WHERE id = ?');
$stmt->bind_param('i', $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) { http_response_code(404); exit('Document not found'); }

$path = UPLOAD_DIR . $doc['stored_name'];
if (!file_exists($path)) { http_response_code(404); exit('File missing on disk'); }

header('Content-Type: ' . $doc['file_type']);
header('Content-Disposition: inline; filename="' . addslashes($doc['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
