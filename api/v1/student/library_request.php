<?php
// POST /api/v1/student/library_request.php   {book_id}
// Queues a request; the student then collects the book from the librarian.
require __DIR__ . '/../_bootstrap.php';
api_method('POST');
$auth = api_auth('student');
api_ensure_book_requests_table();

$bookId = api_int(api_input()['book_id'] ?? 0);
if (!$bookId) api_fail(422, 'book_id is required.', ['book_id' => 'Required.']);

$book = db()->fetchOne("SELECT id, title FROM books WHERE id=?", 'i', [$bookId]);
if (!$book) api_fail(404, 'Book not found.');
if (!db()->fetchOne("SELECT id FROM books WHERE id=? AND available_copies > 0", 'i', [$bookId])) {
    api_fail(409, 'This book is not available right now.');
}
if (db()->fetchOne("SELECT id FROM book_requests WHERE student_id=? AND book_id=? AND status='Pending'", 'ii', [$auth['ref_id'], $bookId])) {
    api_fail(409, 'You already have a pending request for this book.');
}

db()->execute("INSERT INTO book_requests (student_id, book_id, request_date) VALUES (?,?,?)", 'iis', [$auth['ref_id'], $bookId, date('Y-m-d')]);
api_ok(
    ['request' => ['id' => db()->lastInsertId(), 'book_id' => $bookId, 'title' => $book['title'], 'request_date' => date('Y-m-d')]],
    'Request submitted. Show it to the librarian to collect your book.', 201
);
