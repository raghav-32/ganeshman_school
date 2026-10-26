<?php
// POST /api/v1/student/library_cancel.php   {request_id}
require __DIR__ . '/../_bootstrap.php';
api_method('POST');
$auth = api_auth('student');
api_ensure_book_requests_table();

$id = api_int(api_input()['request_id'] ?? 0);
if (!$id) api_fail(422, 'request_id is required.', ['request_id' => 'Required.']);

// (Database::affectedRows() is unreliable with prepared statements, so look the request up first.)
if (!db()->fetchOne("SELECT id FROM book_requests WHERE id=? AND student_id=? AND status='Pending'", 'ii', [$id, $auth['ref_id']])) {
    api_fail(404, 'No pending request found.');
}
db()->execute("UPDATE book_requests SET status='Cancelled' WHERE id=? AND student_id=? AND status='Pending'", 'ii', [$id, $auth['ref_id']]);
api_ok([], 'Request cancelled.');
