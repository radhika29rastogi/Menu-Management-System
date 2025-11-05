<?php
include 'include/header.php';
include 'include/navbar.php';

// --- CREATE / UPDATE ---
if (isset($_POST['save'])) {
    $name = trim($_POST['name']);
    $status = intval($_POST['status']);
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : 0;

    if ($id > 0) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE active_menu SET name=?, status=? WHERE id=?");
        $stmt->bind_param("sii", $name, $status, $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Menu updated successfully!";
        }
        $stmt->close();
    } else {
        // CREATE
        $stmt = $conn->prepare("INSERT INTO active_menu (name, status) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $status);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Menu created successfully!";
        }
        $stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    if (!empty($id) && is_numeric($id)) {
        $id = (int)$id;
        // Use prepared statement for safer deletion
        $stmt = $conn->prepare("DELETE FROM active_menu WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "<script>alert('Menu deleted successfully!'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
            } else {
                echo "<script>alert('Menu not found or already deleted.'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
            }
        } else {
            // Check if the error is related to foreign key constraint
            if ($conn->errno == 1451 || strpos($conn->error, 'foreign key') !== false) {
                // MySQL error 1451: Cannot delete or update a parent row: a foreign key constraint fails
                echo "<script>alert('Cannot delete this menu because it is in use.'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
            } else {
                echo "<script>alert('Error: " . addslashes($conn->error) . "'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
            }
        }
        $stmt->close();
    } else {
        echo "<script>alert('Invalid ID'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
    }
    exit;
}

// --- FETCH ALL ROWS ---
// Change query to ascending order by id
$result = $conn->query("SELECT * FROM active_menu ORDER BY id ASC");
?>

<div class="container">
    <div class="header">
        <h2>📋 Active Menu Management</h2>
        <button class="btn btn-primary" onclick="openModal()">+ Add Menu</button>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="padding:12px 15px; background:#d4edda; color:#155724; border-radius:5px; margin-bottom:20px; border:1px solid #c3e6cb;">
            ✓ <?= htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="padding:12px 15px; background:#f8d7da; color:#721c24; border-radius:5px; margin-bottom:20px; border:1px solid #f5c6cb;">
            ✗ <?= htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Modal -->
    <div id="menuModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Add Menu</h3>
            <form method="POST">
                <input type="hidden" name="id" id="menuId">
                <label>Menu Name:</label>
                <input type="text" name="name" id="menuName" required>

                <label>Status:</label>
                <select name="status" id="menuStatus">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>

                <button type="submit" name="save" class="btn btn-primary mt-2">Save</button>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Initialize serial id for 1,2,3... display
                $serialId = 1;
                ?>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $serialId++; ?></td>
                            <td><?= htmlspecialchars($row['name']); ?></td>
                            <td>
                                <span class="status-badge <?= $row['status'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?= $row['status'] ? '✓ Active' : '✗ Inactive'; ?>
                                </span>
                            </td>
                            <td><?= isset($row['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($row['created_at']))) : 'N/A'; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-warning btn-sm" onclick="editMenu(<?= $row['id']; ?>, '<?= htmlspecialchars($row['name']); ?>', <?= $row['status']; ?>)">Edit</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteMenu(<?= $row['id']; ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:20px; color:#999;">No menus found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalTitle').textContent = 'Add Menu';
        document.getElementById('menuForm')?.reset?.();
        document.getElementById('menuId').value = '';
        document.getElementById('menuName').value = '';
        document.getElementById('menuStatus').value = '1';
        document.getElementById('menuModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('menuModal').classList.remove('show');
    }

    function editMenu(id, name, status) {
        document.getElementById('modalTitle').textContent = 'Edit Menu';
        document.getElementById('menuId').value = id;
        document.getElementById('menuName').value = name;
        document.getElementById('menuStatus').value = status;
        document.getElementById('menuModal').classList.add('show');
    }

    function deleteMenu(id) {
        if (confirm('Are you sure you want to delete this menu?')) {
            window.location.href = '?delete=' + id;
        }
    }
</script>

<?php include 'include/footer.php'; ?>