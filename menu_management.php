<?php
include 'include/header.php';
?>
<?php
include 'include/navbar.php';
?>

<?php
// --- SEARCH / FILTER SETUP ---
$filter_category = $_GET['filter_category'] ?? '';
$filter_active_menu = $_GET['filter_active_menu'] ?? '';

$where_clauses = [];
if ($filter_category !== '') {
    $where_clauses[] = "m.category LIKE '%" . $conn->real_escape_string($filter_category) . "%'";
}
if ($filter_active_menu !== '') {
    $where_clauses[] = "m.active_menu_id = " . intval($filter_active_menu);
}
$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// --- CREATE / UPDATE ---
if (isset($_POST['save'])) {
    $id = $_POST['id'] ?? '';
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $active_menu_id = intval($_POST['active_menu_id']);
    $food_type = trim($_POST['food_type']);
    $description = trim($_POST['description']);
    $discount_percentage = isset($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : 0.00;
    $price = floatval($_POST['price']);
    $speciality_one = trim($_POST['speciality_one']);
    $speciality_two = trim($_POST['speciality_two']);
    $speciality_three = trim($_POST['speciality_three']);
    $speciality_four = trim($_POST['speciality_four']);
    $image_path = "";

    $is_special_item = isset($_POST['is_special_item']) ? 1 : 0;
    $is_best_offer = isset($_POST['is_best_offer']) ? 1 : 0;
    $is_popular_item = isset($_POST['is_popular_item']) ? 1 : 0;
    $is_thaath_special = isset($_POST['is_thaath_special']) ? 1 : 0;
    $show_in_shop = isset($_POST['show_in_shop']) ? 1 : 0;

    // Handle image upload
    if (isset($_FILES["image_upload"]) && $_FILES["image_upload"]["error"] === UPLOAD_ERR_OK) {
        $target_dir = "images/";
        $filename = uniqid() . '_' . basename($_FILES["image_upload"]["name"]);
        $target_file = $target_dir . $filename;
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        // Basic mime check
        $check = getimagesize($_FILES["image_upload"]["tmp_name"]);
        if($check !== false && in_array($imageFileType, ["jpg","jpeg","png","gif","webp"])) {
            // move uploaded file
            if (move_uploaded_file($_FILES["image_upload"]["tmp_name"], $target_file)) {
                $image_path = $target_file;
            }
        }
    } else {
        // fallback to hidden text field (for edit with no new upload or legacy support)
        $image_path = trim($_POST["image_path"] ?? "");
        if ($image_path !== '' && !preg_match('/^https?:\/\//i', $image_path) && strpos($image_path, 'images/') !== 0) {
            $image_path = 'images/' . ltrim($image_path, '/\\');
        }
    }

    if ($id) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE menu SET item_name=?, category=?, active_menu_id=?, food_type=?, description=?, discount_percentage=?, price=?, speciality_one=?, speciality_two=?, speciality_three=?, speciality_four=?, image_path=?, is_special_item=?, is_best_offer=?, is_popular_item=?, is_thaath_special=?, show_in_shop=? WHERE id=?");
        $stmt->bind_param(
            "ssissddsssssiiiiii",
            $item_name,
            $category,
            $active_menu_id,
            $food_type,
            $description,
            $discount_percentage,
            $price,
            $speciality_one,
            $speciality_two,
            $speciality_three,
            $speciality_four,
            $image_path,
            $is_special_item,
            $is_best_offer,
            $is_popular_item,
            $is_thaath_special,
            $show_in_shop,
            $id
        );
        $stmt->execute();
    } else {
        // CREATE
        $stmt = $conn->prepare("INSERT INTO menu (
            item_name, category, active_menu_id, food_type, description,
            discount_percentage, price, speciality_one, speciality_two, speciality_three, speciality_four,
            image_path, is_special_item, is_best_offer, is_popular_item, is_thaath_special, show_in_shop
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param(
            "ssissddsssssiiiii",
            $item_name,
            $category,
            $active_menu_id,
            $food_type,
            $description,
            $discount_percentage,
            $price,
            $speciality_one,
            $speciality_two,
            $speciality_three,
            $speciality_four,
            $image_path,
            $is_special_item,
            $is_best_offer,
            $is_popular_item,
            $is_thaath_special,
            $show_in_shop
        );
        $stmt->execute();
    }

    header("Location: menu_management.php");
    exit;
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM menu WHERE id = $id");
    header("Location: menu_management.php");
    exit;
}

// --- EDIT (Fetch Single Row) ---
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM menu WHERE id = $id");
    $edit_data = $result ? $result->fetch_assoc() : null;
}

// --- READ (Fetch All Rows) ---
$result = $conn->query("SELECT m.*, am.name AS active_menu_name FROM menu m LEFT JOIN active_menu am ON m.active_menu_id=am.id $where_sql ORDER BY m.id DESC");

// Fetch active_menu for dropdown
$active_menus = $conn->query("SELECT id, name FROM active_menu WHERE status=1 ORDER BY name ASC");

// For filter: Fetch unique categories for dropdown
$filter_categories = [];
$category_results = $conn->query("SELECT DISTINCT category FROM menu ORDER BY category ASC");
if ($category_results && $category_results->num_rows > 0) {
    while($cat_row = $category_results->fetch_assoc()) {
        $filter_categories[] = $cat_row['category'];
    }
}
?>

<div class="container">
    <div class="header">
        <h2>📋 Menu Management</h2>
        <button class="btn btn-primary" id="addMenuBtn">+ Add Menu Item</button>
    </div>

    <!-- Filter/ Search Section (Full Width & Buttons Right-Aligned) -->
    <div style="width:100%; margin: 18px 0 22px 0;">
        <form method="GET" class="filter-bar" style="display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; width:100%; gap:18px; background:white; padding:16px 18px; border-radius: 10px;">
            
            <div style="flex:1 1 300px; min-width:220px; display:flex; gap:20px;">
                <label style="display:flex; flex-direction:column; font-weight:500;">Category
                    <select name="filter_category" onchange="this.form.submit()" style="margin-top:4px; padding:6px; border-radius:4px; border:1px solid #aac;">
                        <option value="">All</option>
                        <?php foreach($filter_categories as $fc): ?>
                            <option value="<?= htmlspecialchars($fc); ?>" <?=$filter_category === $fc ? "selected" : ""?>>
                                <?= htmlspecialchars($fc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display:flex; flex-direction:column; font-weight:500;">Active Menu
                    <select name="filter_active_menu" onchange="this.form.submit()" style="margin-top:4px; padding:6px; border-radius:4px; border:1px solid #aac;">
                        <option value="">All</option>
                        <?php
                        // For filter dropdown reuse, get again
                        $active_filter_menus = $conn->query("SELECT id, name FROM active_menu WHERE status=1 ORDER BY name ASC");
                        if ($active_filter_menus && $active_filter_menus->num_rows > 0) {
                            while($am = $active_filter_menus->fetch_assoc()): ?>
                                <option value="<?= $am['id']; ?>" <?=$filter_active_menu == $am['id'] ? "selected" : ""?>>
                                    <?= htmlspecialchars($am['name']); ?>
                                </option>
                            <?php endwhile;
                        }
                        ?>
                    </select>
                </label>
            </div>

            <div style="display:flex; gap:8px; flex-shrink:0;">
                <button type="submit" class="btn btn-primary" style="padding:8px 20px;">Filter</button>
                <?php if($filter_category || $filter_active_menu): ?>
                    <a href="menu_management.php" class="btn btn-primary" style="background:#eee; color:#34609e; border: 2px solid #34609e; padding:8px 20px; text-decoration:none;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- 🔽 MODAL POPUP FORM -->
    <div id="menuModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Add Menu Item</h3>
            <form method="POST" id="menuForm" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="id" id="menuId">
                <label>Item Name:</label>
                <input type="text" name="item_name" id="itemName" required>
                
                <label>Category:</label>
                <input type="text" name="category" id="category" required>

                <label>Active Menu:</label>
                <select name="active_menu_id" id="activeMenuId" required>
                    <option value="">Select Active Menu</option>
                    <?php if ($active_menus && $active_menus->num_rows > 0) : while ($am_row = $active_menus->fetch_assoc()): ?>
                        <option value="<?= $am_row['id']; ?>">
                            <?= htmlspecialchars($am_row['name']); ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
                
                <label>Food Type:</label>
                <input type="text" name="food_type" id="foodType" required>
                
                <label>Description:</label>
                <textarea name="description" id="description"></textarea>
                
                <label>Discount Percentage:</label>
                <input type="number" name="discount_percentage" id="discount_percentage" step="0.01" min="0" max="100">
                
                <label>Price:</label>
                <input type="number" name="price" id="price" step="0.01" required>
                
                <label>Speciality One:</label>
                <input type="text" name="speciality_one" id="speciality_one">
                <label>Speciality Two:</label>
                <input type="text" name="speciality_two" id="speciality_two">
                <label>Speciality Three:</label>
                <input type="text" name="speciality_three" id="speciality_three">
                <label>Speciality Four:</label>
                <input type="text" name="speciality_four" id="speciality_four">

                <input type="hidden" name="image_path" id="image_path">
                <label>Image Upload:</label>
                <input type="file" name="image_upload" id="image_upload" accept="image/*">
                <small>JPG, PNG, GIF, WEBP. Leave blank to keep current image.</small>
                <div>
                    <label><input type="checkbox" name="is_special_item" id="isSpecialItem" value="1"> Special Item</label>
                    <label><input type="checkbox" name="is_best_offer" id="isBestOffer" value="1"> Best Offer</label>
                    <label><input type="checkbox" name="is_popular_item" id="isPopularItem" value="1"> Popular Item</label>
                    <label><input type="checkbox" name="is_thaath_special" id="isThaathSpecial" value="1"> Thaath Special</label>
                    <label><input type="checkbox" name="show_in_shop" id="showInShop" value="1"> Show In Shop</label>
                </div>
                <button type="submit" name="save" class="btn btn-primary mt-2">Save</button>
            </form>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Menu</th>
                    <th>Price</th>
                    <th>Type</th>
                    <th>Special</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php $idx = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $idx++; ?></td>
                        <td><?= htmlspecialchars($row['item_name']); ?></td>
                        <td><?= htmlspecialchars($row['category']); ?></td>
                        <td><?= htmlspecialchars($row['active_menu_name']); ?></td>
                        <td>₹<?= number_format($row['price'], 2); ?></td>
                        <td><?= htmlspecialchars($row['food_type']); ?></td>
                        <td>
                            <?= $row['is_special_item'] ? '<span class="status-badge status-active">Yes</span>' : '<span class="status-badge status-inactive">No</span>'; ?>
                        </td>
                        <td>
                            <?php
                            // fix image source path logic:
                            $imgSrc = trim($row['image_path']);
                            if ($imgSrc !== '') {
                                if (preg_match('/^https?:\/\//i', $imgSrc)) {
                                    $imgTagSrc = $imgSrc;
                                } elseif (strpos($imgSrc, 'images/') === 0) {
                                    $imgTagSrc = $imgSrc;
                                } else {
                                    $imgTagSrc = 'images/' . ltrim($imgSrc, '/\\');
                                }
                            } else {
                                $imgTagSrc = 'images/no-image.png';
                            }
                            ?>
                            <img src="<?= htmlspecialchars($imgTagSrc); ?>" alt="Image" style="width: 100px; height: 100px;">
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-warning btn-sm" onclick='editMenu(
                                    <?= $row['id']; ?>,
                                    <?= json_encode($row['item_name']); ?>,
                                    <?= json_encode($row['category']); ?>,
                                    <?= $row['active_menu_id']; ?>,
                                    <?= json_encode($row['food_type']); ?>,
                                    <?= json_encode($row['description']); ?>,
                                    <?= floatval($row['discount_percentage']); ?>,
                                    <?= floatval($row['price']); ?>,
                                    <?= json_encode($row['speciality_one']); ?>,
                                    <?= json_encode($row['speciality_two']); ?>,
                                    <?= json_encode($row['speciality_three']); ?>,
                                    <?= json_encode($row['speciality_four']); ?>,
                                    <?= json_encode($row['image_path']); ?>,
                                    <?= $row['is_special_item']; ?>,
                                    <?= $row['is_best_offer']; ?>,
                                    <?= $row['is_popular_item']; ?>,
                                    <?= $row['is_thaath_special']; ?>,
                                    <?= $row['show_in_shop']; ?>
                                )'>Edit</button>
                                <a href="?delete=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this menu item?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9" style="text-align:center;">No menu items found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    </body>
    <script>
        // Modal overlay: add/remove body class to prevent scrolling behind modal
        function openModal() {
            document.getElementById('modalTitle').textContent = 'Add Menu Item';
            document.getElementById('menuForm').reset();
            document.getElementById('menuId').value = '';
            document.getElementById('image_path').value = '';
            document.getElementById('menuModal').classList.add('show');
            document.body.classList.add('modal-open');
            // Uncheck all checkboxes
            document.getElementById('isSpecialItem').checked = false;
            document.getElementById('isBestOffer').checked = false;
            document.getElementById('isPopularItem').checked = false;
            document.getElementById('isThaathSpecial').checked = false;
            document.getElementById('showInShop').checked = false;
        }

        function closeModal() {
            document.getElementById('menuModal').classList.remove('show');
            document.body.classList.remove('modal-open');
        }

        function editMenu(
            id,
            item_name,
            category,
            active_menu_id,
            food_type,
            description,
            discount_percentage,
            price,
            speciality_one,
            speciality_two,
            speciality_three,
            speciality_four,
            image_path,
            is_special_item,
            is_best_offer,
            is_popular_item,
            is_thaath_special,
            show_in_shop
        ) {
            document.getElementById('modalTitle').textContent = 'Edit Menu Item';
            document.getElementById('menuModal').classList.add('show');
            document.body.classList.add('modal-open');
            document.getElementById('menuForm').reset();

            document.getElementById('menuId').value = id ?? '';
            document.getElementById('itemName').value = item_name ?? '';
            document.getElementById('category').value = category ?? '';
            document.getElementById('activeMenuId').value = active_menu_id ?? '';
            document.getElementById('foodType').value = food_type ?? '';
            document.getElementById('description').value = description ?? '';
            document.getElementById('discount_percentage').value = discount_percentage ?? '';
            document.getElementById('price').value = price ?? '';
            document.getElementById('speciality_one').value = speciality_one ?? '';
            document.getElementById('speciality_two').value = speciality_two ?? '';
            document.getElementById('speciality_three').value = speciality_three ?? '';
            document.getElementById('speciality_four').value = speciality_four ?? '';
            document.getElementById('image_path').value = image_path ?? '';
            document.getElementById('isSpecialItem').checked = !!is_special_item;
            document.getElementById('isBestOffer').checked = !!is_best_offer;
            document.getElementById('isPopularItem').checked = !!is_popular_item;
            document.getElementById('isThaathSpecial').checked = !!is_thaath_special;
            document.getElementById('showInShop').checked = !!show_in_shop;
        }

        // Show modal on + Add
        document.getElementById('addMenuBtn').onclick = function() {
            openModal();
        };
        // Close on close click
        document.querySelector('.close').onclick = closeModal;

        // Ensure modal closes when clicking outside the box
        window.onclick = function(event) {
            const modal = document.getElementById('menuModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Optional: When a file is chosen, clear the hidden image_path field
        document.getElementById('image_upload').addEventListener('change', function() {
            if(this.files.length > 0) {
                document.getElementById('image_path').value = '';
            }
        });
    </script>
</html>