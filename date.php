<?php
require_once 'includes/auth.php';

echo '<pre style="padding:20px;font-family:monospace;">';
echo "PHP server date (Y-m-d): " . date('Y-m-d') . "\n";
echo "PHP timezone: " . date_default_timezone_get() . "\n\n";

$today = date('Y-m-d');
$bs = NepaliDate::adToBs($today);
echo "adToBs($today) = "; print_r($bs);

echo "\nFormatted: " . NepaliDate::format($today) . "\n";
echo "Day name: " . NepaliDate::todayDayName() . "\n";

// Known reference check: 2024-04-13 should be approximately BS 2081-01-01 (Baisakh 1)
echo "\n=== Reference check ===\n";
$ref_test = '2024-04-13';
$ref_bs = NepaliDate::adToBs($ref_test);
echo "adToBs($ref_test) = "; print_r($ref_bs);
echo "(Should be close to BS 2081-01-01)\n";

// Check what today() actually returns
echo "\nNepaliDate::today() = " . NepaliDate::today() . "\n";
echo '</pre>';