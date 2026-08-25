<?php
$html = file_get_contents('C:/Users/DELL/AppData/Local/Temp/kilo/upload_result.html');

// Look for any UAMS reference in the HTML
preg_match_all('/UAMS-2026-\d+/', $html, $refs);
echo "=== Reference numbers found ===\n";
if (!empty($refs[0])) {
    foreach (array_unique($refs[0]) as $r) {
        echo "  $r\n";
    }
} else {
    echo "  None found\n";
}

// Look for the documents table content
preg_match_all('/<td>(.*?)<\/td>/', $html, $td_matches);
echo "\n=== Table cell contents ===\n";
foreach ($td_matches[1] as $td) {
    $clean = trim(strip_tags($td));
    if (!empty($clean)) {
        echo "  $clean\n";
    }
}
