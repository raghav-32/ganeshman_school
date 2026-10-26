<?php
// GET /api/v1/student/library.php?q=search&limit=50&offset=0
// Book catalogue (with "already requested" flag) + my issued books + my pending requests.
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth = api_auth('student');
api_ensure_book_requests_table();

[$limit, $offset] = api_page();
$q = trim((string)($_GET['q'] ?? ''));

$where = '1=1'; $types = 'i'; $params = [$auth['ref_id']];
if ($q !== '') {
    $where .= ' AND (b.title LIKE ? OR b.author LIKE ?)';
    $params[] = $params[] = '%' . addcslashes($q, '%_\\') . '%';
    $types .= 'ss';
}
$types .= 'ii'; $params[] = $limit; $params[] = $offset;

$books = db()->fetchAll(
    "SELECT b.*, (SELECT COUNT(*) FROM book_requests br
                  WHERE br.book_id = b.id AND br.student_id = ? AND br.status = 'Pending') AS my_pending
     FROM books b WHERE $where ORDER BY b.title LIMIT ? OFFSET ?",
    $types, $params
);

$requests = db()->fetchAll(
    "SELECT br.*, b.title, b.author, b.available_copies FROM book_requests br
     JOIN books b ON br.book_id = b.id
     WHERE br.student_id = ? AND br.status = 'Pending' ORDER BY br.created_at DESC",
    'i', [$auth['ref_id']]
);

api_ok([
    'books' => array_map(fn($b) => [
        'id'               => (int)$b['id'],
        'title'            => $b['title'],
        'author'           => $b['author'],
        'category'         => $b['category'],
        'publisher'        => $b['publisher'],
        'publish_year'     => $b['publish_year'] ? (int)$b['publish_year'] : null,
        'shelf_location'   => $b['shelf_location'],
        'total_copies'     => (int)$b['total_copies'],
        'available_copies' => (int)$b['available_copies'],
        'available'        => (int)$b['available_copies'] > 0,
        'requested'        => (int)$b['my_pending'] > 0,
    ], $books),
    'my_issued'   => api_issued_books($auth['ref_id']),
    'my_requests' => array_map(fn($r) => [
        'id'               => (int)$r['id'],
        'book_id'          => (int)$r['book_id'],
        'title'            => $r['title'],
        'author'           => $r['author'],
        'available'        => (int)$r['available_copies'] > 0,
        'request_date'     => $r['request_date'],
        'request_date_bs'  => api_bs($r['request_date']),
    ], $requests),
    'limit' => $limit, 'offset' => $offset,
]);
