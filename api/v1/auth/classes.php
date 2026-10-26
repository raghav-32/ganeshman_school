<?php
// GET /api/v1/auth/classes.php   (public, no token)
// Class list for the registration form's dropdown.
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
api_ok(['classes' => array_map(fn($c) => [
    'id' => (int)$c['id'], 'name' => $c['class_name'], 'section' => $c['section'],
    'label' => api_class_label($c),
], db()->fetchAll("SELECT id, class_name, section FROM classes ORDER BY class_name, section"))]);
