<?php
// Ensure database connection
if (!isset($conn)) {
    if (file_exists('include/config.php')) {
        include 'include/config.php';
    } else {
        die("Database connection not found.");
    }
}

// Check if CSV mode (for download)
$is_csv_mode = isset($_GET['download_template']) || isset($_GET['export_csv']);

if (!$is_csv_mode) {
    include 'include/header.php';
    include 'include/navbar.php';
}

// CSV column definitions (must match order in template)
$csv_columns = [
    'item_name','category','active_menu_name','food_type','description',
    'discount_percentage','price',
    'speciality_one','speciality_two','speciality_three','speciality_four',
    'image_path',
    'is_special_item','is_best_offer','is_popular_item','is_thaath_special','show_in_shop'
];

// Helper: Fetch active menu map
function get_active_menu_map($conn) {
    $map = [];
    $result = $conn->query("SELECT id, name FROM active_menu");
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $map[strtolower(trim($row['name']))] = $row['id'];
        }
    }
    return $map;
}

// Helper: Quote CSV field
function quote_csv($str) {
    $str = (string)$str;
    return '"' . str_replace('"', '""', $str) . '"';
}

// Helper: Send CSV headers
function send_csv_headers($filename) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
}

// --- DOWNLOAD TEMPLATE ---
if (isset($_GET['download_template'])) {
    send_csv_headers('menu_import_template.csv');
    echo implode(',', array_map('quote_csv', $csv_columns)) . "\r\n";
    
    $sample = [
        'Paneer Butter Masala','Main Course','Regular Menu','veg','Creamy paneer curry with aromatic spices',
        '10.00','249.00',
        'Best served with butter naan','Chef special','','',
        '/images/paneer_butter.jpg',
        '1','0','1','0','1'
    ];
    echo implode(',', array_map('quote_csv', $sample)) . "\r\n";
    exit;
}

// --- EXPORT CURRENT MENU ---
if (isset($_GET['export_csv'])) {
    send_csv_headers('menu_export.csv');
    echo implode(',', array_map('quote_csv', $csv_columns)) . "\r\n";
    
    if (!$conn) {
        echo quote_csv('ERROR: No database connection.') . "\r\n";
        exit;
    }

    $sql = "SELECT m.*, am.name as active_menu_name
            FROM menu m
            LEFT JOIN active_menu am ON m.active_menu_id = am.id
            ORDER BY m.id DESC";
    
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data = [
                $row['item_name'] ?? '',
                $row['category'] ?? '',
                $row['active_menu_name'] ?? '',
                $row['food_type'] ?? '',
                $row['description'] ?? '',
                is_numeric($row['discount_percentage']) ? number_format($row['discount_percentage'], 2, '.', '') : '0.00',
                is_numeric($row['price']) ? number_format($row['price'], 2, '.', '') : '0.00',
                $row['speciality_one'] ?? '',
                $row['speciality_two'] ?? '',
                $row['speciality_three'] ?? '',
                $row['speciality_four'] ?? '',
                $row['image_path'] ?? '',
                $row['is_special_item'] ?? 0,
                $row['is_best_offer'] ?? 0,
                $row['is_popular_item'] ?? 0,
                $row['is_thaath_special'] ?? 0,
                $row['show_in_shop'] ?? 0
            ];
            echo implode(',', array_map('quote_csv', $data)) . "\r\n";
        }
    }
    exit;
}

// --- HANDLE CSV IMPORT ---
$import_summary = null;

if (isset($_POST['import_csv'])) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $import_summary = ['errors' => ['File upload failed. Please try again.']];
    } else {
        $file = $_FILES['csv_file'];
        $f = fopen($file['tmp_name'], 'r');
        
        if (!$f) {
            $import_summary = ['errors' => ['Cannot open uploaded file.']];
        } else {
            // Read header
            $headers = fgetcsv($f);
            if (!$headers) {
                $import_summary = ['errors' => ['CSV file is empty or invalid.']];
            } else {
                // Remove BOM from first header (multiple methods to be sure)
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                    $headers[0] = preg_replace('/^[\x{FEFF}]/u', '', $headers[0]);
                    $headers[0] = trim($headers[0]);
                }
                
                // Normalize headers - trim, lowercase, and remove quotes
                $headers_normalized = array_map(function($h) { 
                    $h = trim($h);
                    $h = trim($h, '"\''); // Remove surrounding quotes if any
                    return strtolower($h);
                }, $headers);
                
                // Expected headers (normalized for comparison)
                $expected_normalized = array_map('strtolower', $csv_columns);
                
                // Check if headers match (allow extra columns but not missing ones)
                $headers_match = true;
                if (count($headers_normalized) < count($expected_normalized)) {
                    $headers_match = false;
                } else {
                    for ($i = 0; $i < count($expected_normalized); $i++) {
                        if ($headers_normalized[$i] !== $expected_normalized[$i]) {
                            $headers_match = false;
                            break;
                        }
                    }
                }
                
                if (!$headers_match) {
                    $got_headers = array_slice($headers_normalized, 0, count($csv_columns));
                    $import_summary = ['errors' => [
                        'CSV header mismatch.',
                        'Expected: ' . implode(', ', $csv_columns),
                        'Got: ' . implode(', ', $headers)
                    ]];
                } else {
                    // Process rows
                    $menu_map = get_active_menu_map($conn);
                    $inserted = $updated = $failed = 0;
                    $failures = [];
                    $row_num = 2;
                    
                    while (($row = fgetcsv($f)) !== false) {
                        // Skip completely empty rows
                        if (empty(array_filter($row))) { 
                            $row_num++;
                            continue;
                        }
                        
                        // Check column count
                        if (count($row) < count($csv_columns)) {
                            $failed++;
                            $failures[] = "Row $row_num: Has " . count($row) . " columns, expected " . count($csv_columns) . ".";
                            $row_num++;
                            continue;
                        }
                        
                        // Map data to columns - only use first N columns that match template
                        $data = [];
                        for ($i = 0; $i < count($csv_columns); $i++) {
                            $col_name = $csv_columns[$i];
                            // Find which index in the actual CSV has this column
                            $col_index = array_search(strtolower($col_name), $headers_normalized);
                            if ($col_index !== false && isset($row[$col_index])) {
                                $data[$col_name] = $row[$col_index];
                            } else {
                                $data[$col_name] = '';
                            }
                        }
                        
                        // Validate required fields
                        $item_name = trim($data['item_name'] ?? '');
                        if (empty($item_name)) {
                            $failed++;
                            $failures[] = "Row $row_num: Item name is required.";
                            $row_num++;
                            continue;
                        }
                        
                        $active_menu_name = trim($data['active_menu_name'] ?? '');
                        $active_menu_key = strtolower($active_menu_name);
                        $active_menu_id = $menu_map[$active_menu_key] ?? null;
                        
                        if (!$active_menu_id) {
                            $failed++;
                            $failures[] = "Row $row_num: Active menu '$active_menu_name' not found in database.";
                            $row_num++;
                            continue;
                        }
                        
                        // Check if item exists (for update)
                        $esc_item = $conn->real_escape_string($item_name);
                        $esc_category = $conn->real_escape_string(trim($data['category'] ?? ''));
                        
                        $check = $conn->query("SELECT id FROM menu WHERE item_name='$esc_item' AND category='$esc_category' AND active_menu_id=$active_menu_id LIMIT 1");
                        $exists_id = ($check && $check->num_rows > 0) ? $check->fetch_assoc()['id'] : null;
                        
                        // Prepare values
                        $item_name_val = trim($data['item_name']);
                        $category_val = trim($data['category'] ?? '');
                        $food_type_val = trim($data['food_type'] ?? '');
                        $description_val = trim($data['description'] ?? '');
                        $discount_val = floatval($data['discount_percentage'] ?? 0);
                        $price_val = floatval($data['price'] ?? 0);
                        $spec1_val = trim($data['speciality_one'] ?? '');
                        $spec2_val = trim($data['speciality_two'] ?? '');
                        $spec3_val = trim($data['speciality_three'] ?? '');
                        $spec4_val = trim($data['speciality_four'] ?? '');
                        $image_val = trim($data['image_path'] ?? '');
                        $special_val = intval($data['is_special_item'] ?? 0);
                        $best_val = intval($data['is_best_offer'] ?? 0);
                        $popular_val = intval($data['is_popular_item'] ?? 0);
                        $thaath_val = intval($data['is_thaath_special'] ?? 0);
                        $show_val = intval($data['show_in_shop'] ?? 0);
                        
                        if ($exists_id) {
                            // UPDATE
                            $stmt = $conn->prepare("UPDATE menu SET item_name=?, category=?, active_menu_id=?, food_type=?, description=?, discount_percentage=?, price=?, speciality_one=?, speciality_two=?, speciality_three=?, speciality_four=?, image_path=?, is_special_item=?, is_best_offer=?, is_popular_item=?, is_thaath_special=?, show_in_shop=? WHERE id=?");
                            
                            if ($stmt) {
                                $stmt->bind_param(
                                    "ssissddssssiiiiii",
                                    $item_name_val, $category_val, $active_menu_id, $food_type_val, $description_val,
                                    $discount_val, $price_val,
                                    $spec1_val, $spec2_val, $spec3_val, $spec4_val,
                                    $image_val,
                                    $special_val, $best_val, $popular_val, $thaath_val, $show_val,
                                    $exists_id
                                );
                                
                                if ($stmt->execute()) {
                                    $updated++;
                                } else {
                                    $failed++;
                                    $failures[] = "Row $row_num: Update failed - " . $stmt->error;
                                }
                                $stmt->close();
                            } else {
                                $failed++;
                                $failures[] = "Row $row_num: Prepare statement failed - " . $conn->error;
                            }
                        } else {
                            // INSERT
                            $stmt = $conn->prepare("INSERT INTO menu (item_name, category, active_menu_id, food_type, description, discount_percentage, price, speciality_one, speciality_two, speciality_three, speciality_four, image_path, is_special_item, is_best_offer, is_popular_item, is_thaath_special, show_in_shop) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            
                            if ($stmt) {
                                $stmt->bind_param(
                                    "ssissddssssiiiiii",
                                    $item_name_val, $category_val, $active_menu_id, $food_type_val, $description_val,
                                    $discount_val, $price_val,
                                    $spec1_val, $spec2_val, $spec3_val, $spec4_val,
                                    $image_val,
                                    $special_val, $best_val, $popular_val, $thaath_val, $show_val
                                );
                                
                                if ($stmt->execute()) {
                                    $inserted++;
                                } else {
                                    $failed++;
                                    $failures[] = "Row $row_num: Insert failed - " . $stmt->error;
                                }
                                $stmt->close();
                            } else {
                                $failed++;
                                $failures[] = "Row $row_num: Prepare statement failed - " . $conn->error;
                            }
                        }
                        
                        $row_num++;
                    }
                    
                    $import_summary = [
                        'inserted' => $inserted,
                        'updated' => $updated,
                        'failed' => $failed,
                        'failures' => $failures
                    ];
                }
            }
            fclose($f);
        }
    }
}
?>

<?php if (!$is_csv_mode): ?>
<div class="container">
    <div class="header">
        <h2>📦 Menu CSV Import / Export</h2>
    </div>
    
    <div style="background:#f9f9f9; border-radius:10px; padding:22px 30px; margin-bottom:30px;">
        <ul style="margin-left:20px; line-height:1.7;">
            <li class="m-2">
                <a href="?download_template" class="btn btn-primary">📥 Download Import Template (CSV)</a>
                <span style="color:#777;">– Download the exact template to fill with your data.</span>
            </li>
            <li class="m-2">
                <a href="?export_csv" class="btn btn-primary" style="background:#595;">📤 Export Current Menu to CSV</a>
                <span style="color:#777;">– Download all existing menu items.</span>
            </li>
        </ul>
        
        <div style="margin-top:32px;">
            <form method="POST" enctype="multipart/form-data" style="display:flex; align-items:center; gap:18px;">
                <label>
                    <b>Import CSV:</b> 
                    <input type="file" name="csv_file" accept=".csv,text/csv" required>
                </label>
                <button type="submit" name="import_csv" class="btn btn-primary">📤 Upload & Import</button>
            </form>
        </div>
    </div>

    <?php if ($import_summary): ?>
        <div style="padding:18px 24px; background:#eef7ff; border-radius:8px; margin-bottom:26px; border-left:4px solid #007bff;">
            <h4>Import Summary:</h4>
            
            <?php if (!empty($import_summary['errors'])): ?>
                <div style="color:#d33; padding:8px 0;">
                    <strong>❌ Error:</strong><br>
                    <?php foreach($import_summary['errors'] as $err): ?>
                        <div><?= htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding:8px 0;">
                    <p><strong>✅ Import Completed!</strong></p>
                    <ul>
                        <li><strong>✔ Inserted:</strong> <?= $import_summary['inserted']; ?> items</li>
                        <li><strong>🔄 Updated:</strong> <?= $import_summary['updated']; ?> items</li>
                        <li><strong>❌ Failed:</strong> <?= $import_summary['failed']; ?> items</li>
                    </ul>
                </div>
                
                <?php if (!empty($import_summary['failures']) && $import_summary['failed'] > 0): ?>
                    <div style="color:#d33; margin-top:12px; padding:12px; background:#ffe6e6; border-radius:5px;">
                        <strong>Failed Rows Details:</strong>
                        <ul style="margin:8px 0 0 20px;">
                            <?php foreach($import_summary['failures'] as $fail): ?>
                                <li style="margin:4px 0;"><?= htmlspecialchars($fail); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'include/footer.php'; ?>
<?php endif; ?>