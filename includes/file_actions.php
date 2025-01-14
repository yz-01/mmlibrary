<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Database connection
require_once "db/config.php";

// Handle create folder action
if ($_POST['action'] === 'create_folder') {
    try {
        $name = trim($_POST['name']);
        $parent_directory = trim($_POST['parent_directory']);
        
        // Basic validation
        if (empty($name)) {
            throw new Exception('Folder name is required');
        }

        // Create the path
        $path = $parent_directory ? $parent_directory . '/' . $name : $name;

        // Check if folder already exists
        $check_sql = "SELECT id FROM folders_files WHERE name = ? AND parent_directory = ? AND type = 'folder'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $name, $parent_directory);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('A folder with this name already exists');
        }

        // Insert the new folder
        $sql = "INSERT INTO folders_files (name, type, path, parent_directory, created_by, modified_by) 
                VALUES (?, 'folder', ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $name, $path, $parent_directory, $_SESSION['id'], $_SESSION['id']);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Could not create folder');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Handle create file action
if ($_POST['action'] === 'create_file') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $parent_directory = trim($_POST['parent_directory']);
        
        // Basic validation
        if (empty($name)) {
            throw new Exception('File name is required');
        }

        // Create the path
        $path = $parent_directory ? $parent_directory . '/' . $name : $name;

        // Check if file already exists
        $check_sql = "SELECT id FROM folders_files WHERE name = ? AND parent_directory = ? AND type = 'file'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $name, $parent_directory);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('A file with this name already exists');
        }

        // Insert the new file
        $sql = "INSERT INTO folders_files (name, type, path, parent_directory, description, created_by, modified_by) 
                VALUES (?, 'file', ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssii", $name, $path, $parent_directory, $description, $_SESSION['id'], $_SESSION['id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'file_id' => $stmt->insert_id]);
        } else {
            throw new Exception('Could not create file');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Handle delete action
if ($_POST['action'] === 'delete') {
    try {
        if (!$_SESSION["is_editable"]) {
            throw new Exception('You do not have permission to delete items');
        }

        $id = $_POST['id'];
        
        // First get the item details to check if it's a folder
        $check_sql = "SELECT type, path FROM folders_files WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $item = $result->fetch_assoc();
        
        if (!$item) {
            throw new Exception('Item not found');
        }

        // If it's a folder, check if it has any contents
        if ($item['type'] === 'folder') {
            $path = $item['path'] . '/%';
            $contents_sql = "SELECT COUNT(*) as count FROM folders_files WHERE path LIKE ?";
            $contents_stmt = $conn->prepare($contents_sql);
            $contents_stmt->bind_param("s", $path);
            $contents_stmt->execute();
            $contents_result = $contents_stmt->get_result()->fetch_assoc();
            
            if ($contents_result['count'] > 0) {
                throw new Exception('Cannot delete folder because it contains files or folders. Please delete the contents first.');
            }
        }

        // Delete the item itself
        $sql = "DELETE FROM folders_files WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Could not delete item');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Handle rename action
if ($_POST['action'] === 'rename') {
    try {
        if (!$_SESSION["is_editable"]) {
            throw new Exception('You do not have permission to rename items');
        }

        $id = $_POST['id'];
        $new_name = trim($_POST['new_name']);
        
        if (empty($new_name)) {
            throw new Exception('New name is required');
        }

        // Get current item details
        $check_sql = "SELECT type, parent_directory, path FROM folders_files WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $item = $result->fetch_assoc();
        
        if (!$item) {
            throw new Exception('Item not found');
        }

        // Check if an item with the new name already exists in the same directory
        $exists_sql = "SELECT id FROM folders_files WHERE name = ? AND parent_directory = ? AND type = ? AND id != ?";
        $exists_stmt = $conn->prepare($exists_sql);
        $exists_stmt->bind_param("sssi", $new_name, $item['parent_directory'], $item['type'], $id);
        $exists_stmt->execute();
        if ($exists_stmt->get_result()->num_rows > 0) {
            throw new Exception('An item with this name already exists in this directory');
        }

        // Create the new path
        $new_path = $item['parent_directory'] ? $item['parent_directory'] . '/' . $new_name : $new_name;

        // If it's a folder, we need to update all child paths
        if ($item['type'] === 'folder') {
            $old_path_pattern = $item['path'] . '/%';
            $path_update_sql = "UPDATE folders_files SET path = CONCAT(?, SUBSTRING(path, ?)) 
                               WHERE path LIKE ?";
            $path_update_stmt = $conn->prepare($path_update_sql);
            $old_path_len = strlen($item['path']) + 1;
            $path_update_stmt->bind_param("sis", $new_path, $old_path_len, $old_path_pattern);
            $path_update_stmt->execute();
        }

        // Update the item itself
        $sql = "UPDATE folders_files SET name = ?, path = ?, modified_at = NOW(), modified_by = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $new_name, $new_path, $_SESSION['id'], $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Could not rename item');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Close the database connection
$conn->close();
?> 