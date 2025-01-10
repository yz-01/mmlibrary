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

// Close the database connection
$conn->close();
?> 