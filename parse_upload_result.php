<?php
$html = file_get_contents('C:/Users/DELL/AppData/Local/Temp/kilo/upload_result.html');

echo "=== Upload Result Check ===\n";
$checks = [
    ['string' => 'Document uploaded successfully', 'desc' => 'Success message'],
    ['string' => 'test_doc.pdf', 'desc' => 'Uploaded filename in list'],
    ['string' => 'pending', 'desc' => 'Verification status'],
    ['string' => 'UAMS-2026-0006', 'desc' => 'Application reference (student3 new app)'],
];
foreach ($checks as $c) {
    echo "  [" . (strpos($html, $c['string']) !== false ? 'OK' : 'MISSING') . "] " . $c['desc'] . "\n";
}

// Check verification badge
preg_match_all('/<span class="status-badge (.*?)">(.*?)<\/span>/', $html, $m);
echo "\n=== Badges found ===\n";
for ($i = 0; $i < count($m[0]); $i++) {
    echo "  badge-" . trim($m[1][$i]) . " => " . trim(strip_tags($m[2][$i])) . "\n";
}

// Extract file from uploads
echo "\n=== Uploaded files check ===\n";
$files = glob('C:/xampp/htdocs/UAMS/uploads/documents/*');
foreach ($files as $f) {
    echo "  " . basename($f) . " (" . filesize($f) . " bytes)\n";
}
