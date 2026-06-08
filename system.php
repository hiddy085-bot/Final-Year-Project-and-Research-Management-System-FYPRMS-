<?php
session_start();

// Database connection - Using 'fd' database
$host = 'localhost';
$dbname = 'fd';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Ensure common Tanzanian universities exist
try {
    $tanzUnis = [
        'University of Dar es Salaam',
        'University of Dodoma',
        'Sokoine University of Agriculture',
        'Ardhi University',
        'Mzumbe University',
        'Muhimbili University of Health and Allied Sciences',
        'Tanzania University of Science and Technology',
        'Dar es Salaam Institute of Technology',
        'Mbeya University of Science and Technology',
        'Nelson Mandela African Institution of Science and Technology',
        'Open University of Tanzania',
        'St. Augustine University of Tanzania',
        'Tumaini University Makumira',
        'St. John\'s University of Tanzania',
        'Mkwawa University College of Education',
        'State University of Zanzibar',
        'Muslim University of Morogoro',
        'Kilimanjaro Christian Medical University College',
        'Mount Meru University',
        'University of Arusha',
        'Teofilo Kisanji University',
        'Iringa University'
    ];

    $check = $pdo->prepare("SELECT id FROM universities WHERE name = ? LIMIT 1");
    $ins = $pdo->prepare("INSERT INTO universities (name) VALUES (?)");
    foreach($tanzUnis as $u) {
        $check->execute([$u]);
        if(!$check->fetch()) {
            $ins->execute([$u]);
        }
    }
} catch(Exception $e) {
    // ignore seeding errors
}

// Ensure an administrator account exists
try {
    $adminExists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'administrator'");
    $adminExists->execute();
    if($adminExists->fetchColumn() == 0) {
        $defaultUniversity = $pdo->query("SELECT id FROM universities LIMIT 1")->fetchColumn();
        $adminPassword = password_hash('Admin@123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, university_id, department) VALUES (?, ?, ?, 'administrator', ?, ?)");
        $stmt->execute(['System Administrator', 'admin@fyprms.com', $adminPassword, $defaultUniversity ?: null, 'Administration']);
    }
} catch(Exception $e) {
    // Ignore errors
}

$validPages = ['home','about','education_gallery','search_projects','search_research','register','login','dashboard','upload_project','upload_research','my_uploads','pending_reviews','manage_users','manage_projects','manage_research','manage_universities','generate_reports'];
$departments = [
    'Accounting', 'Agriculture', 'Architecture', 'Banking and Finance', 'Biology',
    'Business Administration', 'Chemical Engineering', 'Chemistry', 'Civil Engineering',
    'Computer Science', 'Dentistry', 'Economics', 'Education', 'Electrical Engineering',
    'Environmental Science', 'Food Science and Technology', 'Geography', 'Geology',
    'Health Sciences', 'Human Resource Management', 'Information Technology',
    'Journalism and Mass Communication', 'Law', 'Marketing', 'Mathematics',
    'Mechanical Engineering', 'Medicine', 'Nursing', 'Pharmacy', 'Physics',
    'Public Health', 'Public Administration', 'Social Sciences', 'Statistics',
    'Tourism and Hospitality Management', 'Veterinary Medicine'
];

if(isset($_GET['page'])) {
    $_GET['page'] = basename($_GET['page']);
}
elseif(isset($_GET['action']) && in_array($_GET['action'], $validPages, true)) {
    $_GET['page'] = $_GET['action'];
}
if(isset($_GET['page'])) {
    if($_GET['page'] === 'index.xhtml' || $_GET['page'] === 'index.html' || $_GET['page'] === '') {
        $_GET['page'] = 'home';
    }
}

// Check session
if(isset($_GET['check_session'])) {
    echo json_encode(['logged_in' => isset($_SESSION['user_id']), 'role' => $_SESSION['role'] ?? null]);
    exit;
}

// Get stats
if(isset($_GET['get_stats'])) {
    $projects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='approved'")->fetchColumn();
    $research = $pdo->query("SELECT COUNT(*) FROM research WHERE status='approved'")->fetchColumn();
    $unis = $pdo->query("SELECT COUNT(*) FROM universities")->fetchColumn();
    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo json_encode(['projects'=>$projects, 'research'=>$research, 'universities'=>$unis, 'users'=>$users]);
    exit;
}

// Logout
if(isset($_GET['logout'])) {
    // Unset all session variables
    $_SESSION = [];

    // Destroy session data on server
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    echo json_encode(['success'=>true]);
    exit;
}


// Search
if(isset($_GET['search'])) {
    $type = $_GET['type'] ?? 'projects';
    // Whitelist table selection to prevent injection
    $table = ($type === 'research') ? 'research' : 'projects';

    $rawKeyword = $_GET['keyword'] ?? '';
    $keyword = '%' . $rawKeyword . '%';

    $stmt = $pdo->prepare("SELECT title, authors, year, file_path FROM {$table} WHERE (title LIKE ? OR authors LIKE ?) AND status='approved'");
    $stmt->execute([$keyword, $keyword]);

    echo '<h3><i class="fas fa-search"></i> Search Results</h3>';
    echo "<table border='1' cellpadding='10'>";
    echo '<tr><th>Title</th><th>Authors</th><th>Year</th><th>Download</th></tr>';

    while($row = $stmt->fetch()) {
        $title = htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8');
        $authors = htmlspecialchars((string)$row['authors'], ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars((string)$row['year'], ENT_QUOTES, 'UTF-8');
        $filePath = htmlspecialchars((string)$row['file_path'], ENT_QUOTES, 'UTF-8');

        echo "<tr><th>{$title}</th><th>{$authors}</th><th>{$year}</th><th><a href='{$filePath}' download>Download PDF</a></th></tr>";
    }
    echo "</table>";
    exit;
}


// Handle POST requests
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    // Admin: Add university (used by frontend)
    if($action === 'add_university') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }
        $name = trim((string)($_POST['name'] ?? ''));
        if($name === '') {
            echo json_encode(['success'=>false, 'message'=>'University name is required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO universities (name) VALUES (?)");
            $stmt->execute([$name]);
            echo json_encode(['success'=>true, 'message'=>'University added successfully!']);
        } catch(Exception $e) {
            echo json_encode(['success'=>false, 'message'=>'Could not add university.']);
        }
        exit;
    }

    // Admin: Delete university (used by frontend)
    if($action === 'delete_university') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if($id <= 0) {
            echo json_encode(['success'=>false, 'message'=>'Invalid university.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM universities WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success'=>true, 'message'=>'University deleted.']);
        exit;
    }

    // Registration
    if($action == 'register') {

        $fullname = $_POST['fullname'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $university_id = $_POST['university_id'];
        $department = $_POST['department'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, university_id, department) VALUES (?,?,?,'student',?,?)");
            $stmt->execute([$fullname, $email, $password, $university_id, $department]);
            echo json_encode(['success'=>true, 'message'=>'Registration successful! Please login.', 'redirect'=>'login']);
        } catch(Exception $e) {
            echo json_encode(['success'=>false, 'message'=>'Email already exists!']);
        }
        exit;
    }
    
    // Login - ADDED department to session
    if($action == 'login') {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['fullname'];
            $_SESSION['university_id'] = $user['university_id'];
            $_SESSION['department'] = $user['department']; // ADDED: Store department in session
            echo json_encode(['success'=>true, 'message'=>'Login successful!', 'redirect'=>'dashboard']);
        } else {
            echo json_encode(['success'=>false, 'message'=>'Invalid email or password!']);
        }
        exit;
    }
    
    // Upload Project
    if($action == 'upload_project') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $abstract = trim((string)($_POST['abstract'] ?? ''));
        $authors = trim((string)($_POST['authors'] ?? ''));
        $year = (int)($_POST['year'] ?? 0);
        $student_id = $_SESSION['user_id'];
        $university_id = $_SESSION['university_id'];

        if($title === '' || $authors === '' || $year <= 0) {
            echo json_encode(['success'=>false, 'message'=>'Title, Authors and Year are required.']);
            exit;
        }

        if(!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success'=>false, 'message'=>'Please upload a PDF file.']);
            exit;
        }

        $target_dir = "uploads/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $maxSize = 15 * 1024 * 1024; // 15MB
        if((int)$_FILES['pdf_file']['size'] > $maxSize) {
            echo json_encode(['success'=>false, 'message'=>'PDF is too large (max 15MB).']);
            exit;
        }

        $originalName = (string)($_FILES['pdf_file']['name'] ?? '');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if($ext !== 'pdf') {
            echo json_encode(['success'=>false, 'message'=>'Only PDF files are allowed.']);
            exit;
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $filename = time() . "_" . $safeBase . ".pdf";
        $target_file = $target_dir . $filename;

        if(move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO projects (title, abstract, authors, year, file_path, student_id, university_id, status) VALUES (?,?,?,?,?,?,?,'pending')");
            $stmt->execute([$title, $abstract, $authors, $year, $target_file, $student_id, $university_id]);
            echo json_encode(['success'=>true, 'message'=>'Project submitted for review!', 'redirect'=>'dashboard']);
        } else {
            echo json_encode(['success'=>false, 'message'=>'File upload failed!']);
        }
        exit;
    }

    
    // Upload Research
    if($action == 'upload_research') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $abstract = trim((string)($_POST['abstract'] ?? ''));
        $authors = trim((string)($_POST['authors'] ?? ''));
        $year = (int)($_POST['year'] ?? 0);
        $student_id = $_SESSION['user_id'];
        $university_id = $_SESSION['university_id'];

        if($title === '' || $authors === '' || $year <= 0) {
            echo json_encode(['success'=>false, 'message'=>'Title, Authors and Year are required.']);
            exit;
        }

        if(!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success'=>false, 'message'=>'Please upload a PDF file.']);
            exit;
        }

        $target_dir = "uploads/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $maxSize = 15 * 1024 * 1024; // 15MB
        if((int)$_FILES['pdf_file']['size'] > $maxSize) {
            echo json_encode(['success'=>false, 'message'=>'PDF is too large (max 15MB).']);
            exit;
        }

        $originalName = (string)($_FILES['pdf_file']['name'] ?? '');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if($ext !== 'pdf') {
            echo json_encode(['success'=>false, 'message'=>'Only PDF files are allowed.']);
            exit;
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $filename = time() . "_" . $safeBase . ".pdf";
        $target_file = $target_dir . $filename;

        if(move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO research (title, abstract, authors, year, file_path, student_id, university_id, status) VALUES (?,?,?,?,?,?,?,'pending')");
            $stmt->execute([$title, $abstract, $authors, $year, $target_file, $student_id, $university_id]);
            echo json_encode(['success'=>true, 'message'=>'Research submitted for review!', 'redirect'=>'dashboard']);
        } else {
            echo json_encode(['success'=>false, 'message'=>'File upload failed!']);
        }
        exit;
    }

    
    // Approve/Reject - MODIFIED to check department
    if($action == 'approve' || $action == 'reject') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'supervisor') {
            echo json_encode(['success'=>false, 'message'=>'Access denied! Only supervisors can approve/reject.']);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? 'project';
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        $table = ($type === 'research') ? 'research' : 'projects';
        
        // Verify submission belongs to supervisor's department
        $supervisor_dept = $_SESSION['department'] ?? '';
        $verifyStmt = $pdo->prepare("
            SELECT u.department FROM {$table} p 
            JOIN users u ON p.student_id = u.id 
            WHERE p.id = ? AND u.department = ?
        ");
        $verifyStmt->execute([$id, $supervisor_dept]);
        
        if($verifyStmt->rowCount() == 0) {
            echo json_encode(['success'=>false, 'message'=>'You can only review submissions from your own department.']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success'=>true, 'message'=>ucfirst($status) . ' successfully!']);
        exit;
    }

    // Admin approve/reject (used in manage_projects/manage_research)
    if($action == 'admin_approve' || $action == 'admin_reject') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? 'project';
        $status = ($action == 'admin_approve') ? 'approved' : 'rejected';
        $table = ($type === 'research') ? 'research' : 'projects';
        $stmt = $pdo->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success'=>true, 'message'=>ucfirst($status) . ' successfully!']);
        exit;
    }


    // Admin: Add user
    if($action == 'add_user') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }
        $fullname = $_POST['fullname'] ?? '';
        $email = $_POST['email'] ?? '';
        $password_raw = $_POST['password'] ?? 'password';
        $role = $_POST['role'] ?? 'student';
        $university_id = $_POST['university_id'] ?? null;
        $department = $_POST['department'] ?? '';

        $password = password_hash($password_raw, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, university_id, department) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$fullname, $email, $password, $role, $university_id, $department]);
            echo json_encode(['success'=>true, 'message'=>'User created successfully!', 'redirect'=>'manage_users']);
        } catch(Exception $e) {
            echo json_encode(['success'=>false, 'message'=>'Could not create user: ' . $e->getMessage()]);
        }
        exit;
    }

    // Admin: Delete user
    if($action == 'delete_user') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }
        $id = $_POST['id'] ?? 0;
        if($id == $_SESSION['user_id']) {
            echo json_encode(['success'=>false, 'message'=>'Cannot delete currently logged in admin.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success'=>true, 'message'=>'User deleted.', 'redirect'=>'manage_users']);
        exit;
    }

    // Admin: Change role - MODIFIED to require department for supervisors
    if($action == 'change_role') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
            echo json_encode(['success'=>false, 'message'=>'Access denied!']);
            exit;
        }
        $id = $_POST['id'] ?? 0;
        $role = $_POST['type'] ?? $_POST['role'] ?? 'student';
        if($id == $_SESSION['user_id'] && $role != 'administrator') {
            echo json_encode(['success'=>false, 'message'=>'Cannot change role of current admin.']);
            exit;
        }
        
        // When promoting to supervisor, verify department exists
        if($role == 'supervisor') {
            $userCheck = $pdo->prepare("SELECT department FROM users WHERE id = ?");
            $userCheck->execute([$id]);
            $userData = $userCheck->fetch();
            if(empty($userData['department'])) {
                echo json_encode(['success'=>false, 'message'=>'User must have a department assigned before becoming a supervisor.']);
                exit;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $id]);
        echo json_encode(['success'=>true, 'message'=>'Role updated.', 'redirect'=>'manage_users']);
        exit;
    }
    exit;
}

// Handle page loads
$page = $_GET['page'] ?? 'home';

if($page == 'home') {
    echo '<div class="welcome-message"><h3>Welcome to FYPRMS</h3><p>Centralized repository for final year projects and research papers across Tanzania.</p></div>';
}
elseif($page == 'about') {
    // Comprehensive About System Page with Font Awesome Icons
    echo '
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <div class="about-container">
        <div class="about-header">
            <h1><i class="fas fa-graduation-cap"></i> About FYPRMS</h1>
            <p class="subtitle">Final Year Project and Research Management System - Tanzania\'s National Academic Repository</p>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-info-circle"></i> Overview</h2>
            <p>
                The Final Year Project and Research Management System (FYPRMS) is designed to act as a <strong>national academic repository</strong> for Tanzania.
                It brings together students, supervisors, and administrators in one workflow so that academic outputs are <strong>stored, reviewed, approved, and made downloadable</strong> in a consistent format.
                
                <br/><br/>
                Instead of allowing research files to remain scattered across personal drives and departments, FYPRMS centralizes the repository to improve:
            </p>
            <ul class="features-list">
                <li><i class="fas fa-search"></i> <strong>Discoverability:</strong> search and retrieve approved projects/research papers.</li>
                <li><i class="fas fa-check-circle"></i> <strong>Quality control:</strong> supervisor review with pending/approved/rejected statuses.</li>
                <li><i class="fas fa-archive"></i> <strong>Preservation:</strong> long-term archiving of submitted PDFs on the server.</li>
            </ul>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-bullseye"></i> Mission</h2>
            <p>
                To provide a comprehensive, accessible, and sustainable platform that <strong>preserves, manages, and shares</strong> academic research outputs from all universities across Tanzania.
                FYPRMS focuses on enabling faster review cycles, improving transparency through status tracking, and supporting knowledge dissemination for both students and researchers.
            </p>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-eye"></i> Vision</h2>
            <p>
                To become East Africa\'s premier academic repository by setting a practical standard for research management.
                The system promotes Tanzania as a hub for academic excellence through visibility, reusability of knowledge, and cross-university access.
            </p>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-star"></i> Key Features (How the System Works)</h2>
            <ul class="features-list">
                <li><i class="fas fa-users"></i> <strong>Multi-role Access:</strong> Dedicated interfaces for Students, Supervisors, and Administrators</li>
                <li><i class="fas fa-file-pdf"></i> <strong>PDF Upload & Management:</strong> Easy submission of projects and research papers in PDF format</li>
                <li><i class="fas fa-search"></i> <strong>Advanced Search:</strong> Filter by title, author, university, department, and year</li>
                <li><i class="fas fa-check-circle"></i> <strong>Review Workflow:</strong> Supervisor approval system with pending/rejected/approved status</li>
                <li><i class="fas fa-university"></i> <strong>University Management:</strong> Comprehensive database of all Tanzanian universities</li>
                <li><i class="fas fa-chart-bar"></i> <strong>Analytics & Reports:</strong> Generate statistics on research output and repository usage</li>
                <li><i class="fas fa-globe"></i> <strong>Cross-University Access:</strong> Browse research from any participating institution</li>
                <li><i class="fas fa-mobile-alt"></i> <strong>Responsive Design:</strong> Access from desktop, tablet, or mobile devices</li>
            </ul>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-user-tag"></i> User Roles & Responsibilities</h2>
            <div class="roles-grid">
                <div class="role-card">
                    <i class="fas fa-user-graduate role-icon"></i>
                    <h3>Students</h3>
                    <p>Register and create accounts, upload final year projects and research papers, view submission history, track approval status, search and download approved research.</p>
                </div>
                <div class="role-card">
                    <i class="fas fa-chalkboard-teacher role-icon"></i>
                    <h3>Supervisors</h3>
                    <p>Review student submissions from their department only, approve or reject projects/research, provide feedback, ensure quality standards, manage departmental submissions.</p>
                </div>
                <div class="role-card">
                    <i class="fas fa-shield-alt role-icon"></i>
                    <h3>Administrators</h3>
                    <p>Manage user accounts and roles, oversee all submissions, manage university records, generate system reports, ensure platform integrity.</p>
                </div>
            </div>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-university"></i> Participating Universities</h2>
            <p>FYPRMS currently supports over 25+ Tanzanian universities including:</p>
            <ul class="universities-list">
                <li><i class="fas fa-check-circle"></i> University of Dar es Salaam</li>
                <li><i class="fas fa-check-circle"></i> University of Dodoma</li>
                <li><i class="fas fa-check-circle"></i> Sokoine University of Agriculture</li>
                <li><i class="fas fa-check-circle"></i> Ardhi University</li>
                <li><i class="fas fa-check-circle"></i> Mzumbe University</li>
                <li><i class="fas fa-check-circle"></i> Muhimbili University of Health and Allied Sciences</li>
                <li><i class="fas fa-check-circle"></i> Dar es Salaam Institute of Technology</li>
                <li><i class="fas fa-check-circle"></i> Open University of Tanzania</li>
                <li><i class="fas fa-plus-circle"></i> And many more...</li>
            </ul>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-lightbulb"></i> Benefits to Tanzanian Academia</h2>
            <ul class="benefits-list">
                <li><i class="fas fa-ban"></i> <strong>Prevents Research Duplication:</strong> Students can check existing research before selecting topics</li>
                <li><i class="fas fa-archive"></i> <strong>Preserves Institutional Knowledge:</strong> All research outputs are permanently archived</li>
                <li><i class="fas fa-chart-line"></i> <strong>Increases Research Visibility:</strong> Tanzanian research becomes globally accessible</li>
                <li><i class="fas fa-handshake"></i> <strong>Facilitates Collaboration:</strong> Connect researchers across different universities</li>
                <li><i class="fas fa-clipboard-check"></i> <strong>Quality Assurance:</strong> Supervisor review process ensures academic standards</li>
                <li><i class="fas fa-clock"></i> <strong>Easy Access:</strong> 24/7 access to approved research from anywhere</li>
            </ul>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-cogs"></i> Technical Specifications</h2>
            <table class="tech-table">
                <tr><th>Component</th><th>Technology</th></tr>
                <tr><td><i class="fab fa-html5"></i> Frontend</th><td>XHTML, CSS3, JavaScript</th></tr>
                <tr><td><i class="fab fa-php"></i> Backend</th><td>PHP 7+</th></tr>
                <tr><td><i class="fas fa-database"></i> Database</th><td>MySQL</th></tr>
                <tr><td><i class="fas fa-lock"></i> Authentication</th><td>Session-based with password hashing</th></tr>
                <tr>。<i class="fas fa-file-upload"></i> File Storage</th><td>Local file system with organized uploads</th></tr>
                <tr>。<i class="fas fa-mobile-alt"></i> Responsive Design</th><td>Mobile-first approach with flexible grid</th></tr>
            </table>
        </div>
        
        <div class="about-section">
            <h2><i class="fas fa-envelope"></i> Contact & Support</h2>
            <p><i class="fas fa-envelope"></i> <strong>Email:</strong> support@fyprms.tz</p>
            <p><i class="fas fa-phone"></i> <strong>Phone:</strong> +255 123 456 789</p>
            <p><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> Ministry of Education, Science and Technology, Dar es Salaam, Tanzania</p>
        </div>
        
        <div class="about-footer">
            <p><i class="far fa-copyright"></i> 2026 Final Year Project and Research Management System | Empowering Tanzanian Academia</p>
        </div>
    </div>';
}
elseif($page == 'search_projects') {
    echo '<h3><i class="fas fa-search"></i> Search Projects</h3>
    <div id="message-area"></div>
    <form id="search-form">
        <div class="form-group">
            <input type="text" id="search-keyword" placeholder="Enter title or author name..." style="width:70%; padding:10px;" />
            <input type="hidden" id="search-type" value="projects" />
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <div id="search-results"></div>';
}
elseif($page == 'search_research') {
    echo '<h3><i class="fas fa-search"></i> Search Research Papers</h3>
    <div id="message-area"></div>
    <form id="search-form">
        <div class="form-group">
            <input type="text" id="search-keyword" placeholder="Enter title or author name..." style="width:70%; padding:10px;" />
            <input type="hidden" id="search-type" value="research" />
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <div id="search-results"></div>';
}
elseif($page == 'register') {
    $unis = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll();
    echo '<div class="form-card"><h3><i class="fas fa-user-plus"></i> Student Registration</h3>
    <div id="message-area"></div>
    <form id="register-form">
        <input type="hidden" name="action" value="register" />
        <div class="form-group"><label><i class="fas fa-user"></i> Full Name</label><input type="text" name="fullname" required /></div>
        <div class="form-group"><label><i class="fas fa-envelope"></i> Email</label><input type="email" name="email" required /></div>
        <div class="form-group"><label><i class="fas fa-lock"></i> Password</label><input type="password" name="password" required /></div>
        <div class="form-group"><label><i class="fas fa-university"></i> University</label><select name="university_id" required><option value="">Select University</option>';
    foreach($unis as $u) { echo "<option value='{$u['id']}'>{$u['name']}</option>"; }
    echo '</select></div>
        <div class="form-group"><label><i class="fas fa-building"></i> Department</label><select name="department" required><option value="">Select Department</option>';
    foreach($departments as $dept) {
        echo "<option value='{$dept}'>{$dept}</option>";
    }
    echo '</select></div>
        <button type="submit"><i class="fas fa-check"></i> Register</button>
    </form></div>';
}
elseif($page == 'login') {
    // Modified login page - Admin credentials removed
    echo '<div class="form-card"><h3><i class="fas fa-sign-in-alt"></i> Login to Your Account</h3>
    <div id="message-area"></div>
    <form id="login-form">
        <input type="hidden" name="action" value="login" />
        <div class="form-group"><label><i class="fas fa-envelope"></i> Email Address</label><input type="email" name="email" required placeholder="Enter your email" /></div>
        <div class="form-group"><label><i class="fas fa-lock"></i> Password</label><input type="password" name="password" required placeholder="Enter your password" /></div>
        <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
    </form>
    <p class="info-note"><i class="fas fa-user-plus"></i> New user? <a href="#" onclick="loadPage(\'register\')">Create an account</a></p>
    </div>';
}
elseif($page == 'dashboard') {
    if(!isset($_SESSION['user_id'])) {
        echo '<p>Please login first. <a href="#" onclick="loadPage(\'login\')">Login here</a></p>';
        exit;
    }

    // Logged-in user details (for profile panel)
    $currentUserId = (int)$_SESSION['user_id'];
    $userStmt = $pdo->prepare("SELECT u.*, un.name AS uni_name FROM users u LEFT JOIN universities un ON u.university_id=un.id WHERE u.id = ? LIMIT 1");
    $userStmt->execute([$currentUserId]);
    $currentUser = $userStmt->fetch();

    $profileHtml = '';
    if($currentUser) {
        $profileName = htmlspecialchars((string)$currentUser['fullname'], ENT_QUOTES, 'UTF-8');
        $profileEmail = htmlspecialchars((string)$currentUser['email'], ENT_QUOTES, 'UTF-8');
        $profileDept = htmlspecialchars((string)($currentUser['department'] ?? ''), ENT_QUOTES, 'UTF-8');
        $profileUni = htmlspecialchars((string)($currentUser['uni_name'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Role-based summary counts
        $summaryHtml = '';
        if(($_SESSION['role'] ?? '') === 'student') {
            $uid = (int)$_SESSION['user_id'];
            $pPending = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE student_id={$uid} AND status='pending'")->fetchColumn();
            $pApproved = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE student_id={$uid} AND status='approved'")->fetchColumn();
            $pRejected = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE student_id={$uid} AND status='rejected'")->fetchColumn();
            $rPending = (int)$pdo->query("SELECT COUNT(*) FROM research WHERE student_id={$uid} AND status='pending'")->fetchColumn();
            $rApproved = (int)$pdo->query("SELECT COUNT(*) FROM research WHERE student_id={$uid} AND status='approved'")->fetchColumn();
            $rRejected = (int)$pdo->query("SELECT COUNT(*) FROM research WHERE student_id={$uid} AND status='rejected'")->fetchColumn();

            $summaryHtml = "
                <div class='profile-summary'>
                    <div><strong>My Status</strong></div>
                    <div class='summary-row'><span>Pending</span><span>".($pPending + $rPending)."</span></div>
                    <div class='summary-row'><span>Approved</span><span>".($pApproved + $rApproved)."</span></div>
                    <div class='summary-row'><span>Rejected</span><span>".($pRejected + $rRejected)."</span></div>
                </div>";
        } elseif(($_SESSION['role'] ?? '') === 'supervisor') {
            // MODIFIED: Show ONLY pending submissions from supervisor's department
            $supervisor_dept = $_SESSION['department'] ?? '';
            $pendingProjects = $pdo->prepare("SELECT COUNT(*) FROM projects p JOIN users u ON p.student_id=u.id WHERE p.status='pending' AND u.department = ?");
            $pendingProjects->execute([$supervisor_dept]);
            $pendingResearch = $pdo->prepare("SELECT COUNT(*) FROM research r JOIN users u ON r.student_id=u.id WHERE r.status='pending' AND u.department = ?");
            $pendingResearch->execute([$supervisor_dept]);
            
            $projectCount = (int)$pendingProjects->fetchColumn();
            $researchCount = (int)$pendingResearch->fetchColumn();
            $totalPending = $projectCount + $researchCount;

            $summaryHtml = "
                <div class='profile-summary'>
                    <div><strong>Department Review Queue</strong></div>
                    <div class='summary-row'><span>Your Department</span><span>" . htmlspecialchars($supervisor_dept) . "</span></div>
                    <div class='summary-row'><span>Projects Pending</span><span>{$projectCount}</span></div>
                    <div class='summary-row'><span>Research Pending</span><span>{$researchCount}</span></div>
                    <div class='summary-row'><span>Total Pending</span><span>{$totalPending}</span></div>
                </div>";
        } else {
            // Admin summary
            $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $totalUnis = (int)$pdo->query("SELECT COUNT(*) FROM universities")->fetchColumn();
            $approvedProjects = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status='approved'")->fetchColumn();
            $approvedResearch = (int)$pdo->query("SELECT COUNT(*) FROM research WHERE status='approved'")->fetchColumn();
            $summaryHtml = "
                <div class='profile-summary'>
                    <div><strong>System Summary</strong></div>
                    <div class='summary-row'><span>Universities</span><span>{$totalUnis}</span></div>
                    <div class='summary-row'><span>Users</span><span>{$totalUsers}</span></div>
                    <div class='summary-row'><span>Approved Projects</span><span>{$approvedProjects}</span></div>
                    <div class='summary-row'><span>Approved Research</span><span>{$approvedResearch}</span></div>
                </div>";
        }

        $profileHtml = "
            <div class='profile-panel'>
                <h4><i class='fas fa-user-circle'></i> My Information</h4>
                <div class='profile-grid'>
                    <div class='profile-field'><span class='label'>Name</span><span class='value'>{$profileName}</span></div>
                    <div class='profile-field'><span class='label'>Email</span><span class='value'>{$profileEmail}</span></div>
                    <div class='profile-field'><span class='label'>Role</span><span class='value'>".htmlspecialchars((string)($_SESSION['role'] ?? ''), ENT_QUOTES, 'UTF-8')."</span></div>
                    <div class='profile-field'><span class='label'>Department</span><span class='value'>{$profileDept}</span></div>
                    <div class='profile-field'><span class='label'>University</span><span class='value'>{$profileUni}</span></div>
                </div>
                {$summaryHtml}
            </div>";
    }

    $role = $_SESSION['role'];
    if($role == 'student') {
        echo '<h3><i class="fas fa-tachometer-alt"></i> Student Dashboard - Welcome ' . htmlspecialchars($_SESSION['name']) . '</h3>
        ' . $profileHtml . '
        <div id="message-area"></div>
        <div class="dashboard-actions">
            <button class="dashboard-btn" onclick="loadPage(\'upload_project\')"><i class="fas fa-upload"></i> Upload Project</button>
            <button class="dashboard-btn" onclick="loadPage(\'upload_research\')"><i class="fas fa-file-alt"></i> Upload Research</button>
            <button class="dashboard-btn" onclick="loadPage(\'my_uploads\')"><i class="fas fa-folder-open"></i> View My Uploads</button>
        </div>
        <div id="subcontent"></div>';
    }
    elseif($role == 'supervisor') {
        // MODIFIED: Show ONLY pending counts from supervisor's department
        $supervisor_dept = $_SESSION['department'] ?? '';
        $pendingProjects = $pdo->prepare("SELECT COUNT(*) FROM projects p JOIN users u ON p.student_id=u.id WHERE p.status='pending' AND u.department = ?");
        $pendingProjects->execute([$supervisor_dept]);
        $pendingResearch = $pdo->prepare("SELECT COUNT(*) FROM research r JOIN users u ON r.student_id=u.id WHERE r.status='pending' AND u.department = ?");
        $pendingResearch->execute([$supervisor_dept]);
        
        $projectCount = (int)$pendingProjects->fetchColumn();
        $researchCount = (int)$pendingResearch->fetchColumn();
        $totalPending = $projectCount + $researchCount;

        echo '<h3><i class="fas fa-chalkboard-teacher"></i> Supervisor Dashboard - Welcome ' . htmlspecialchars($_SESSION['name']) . '</h3>
        ' . $profileHtml . '
        <div id="message-area"></div>
        <div class="info-note" style="background: #e3f2fd; border-left: 4px solid #2196f3;">
            <i class="fas fa-building"></i> <strong>Your Department:</strong> ' . htmlspecialchars($supervisor_dept) . '<br>
            <i class="fas fa-info-circle"></i> You can only review submissions from your department.
        </div>
        <div class="info-note"><i class="fas fa-hourglass-half"></i> Pending queue for your department: <strong>'.$totalPending.'</strong> (Projects: '.$projectCount.', Research: '.$researchCount.')</div>
        <div class="dashboard-actions">
            <button class="dashboard-btn" onclick="loadPage(\'pending_reviews\')"><i class="fas fa-clock"></i> Review Department Submissions</button>
        </div>
        <div id="subcontent"></div>';
    }
    elseif($role == 'administrator') {
        $pendingProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='pending'")->fetchColumn();
        $pendingResearch = $pdo->query("SELECT COUNT(*) FROM research WHERE status='pending'")->fetchColumn();
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalUnis = $pdo->query("SELECT COUNT(*) FROM universities")->fetchColumn();
        $approvedProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='approved'")->fetchColumn();
        $approvedResearch = $pdo->query("SELECT COUNT(*) FROM research WHERE status='approved'")->fetchColumn();

        echo '<h3><i class="fas fa-shield-alt"></i> Admin Dashboard - Welcome ' . htmlspecialchars($_SESSION['name']) . '</h3>
        ' . $profileHtml . '
        <div id="message-area"></div>
        <div class="info-note"><i class="fas fa-chart-pie"></i> System: <strong>'.$totalUnis.'</strong> universities, <strong>'.$totalUsers.'</strong> users. Pending: <strong>'.((int)$pendingProjects + (int)$pendingResearch).'</strong></div>
        <div class="dashboard-actions">
            <button class="dashboard-btn" onclick="loadPage(\'manage_users\')"><i class="fas fa-users"></i> Manage Users</button>
            <button class="dashboard-btn" onclick="loadPage(\'manage_projects\')"><i class="fas fa-project-diagram"></i> Manage Projects</button>
            <button class="dashboard-btn" onclick="loadPage(\'manage_research\')"><i class="fas fa-book"></i> Manage Research</button>
            <button class="dashboard-btn" onclick="loadPage(\'manage_universities\')"><i class="fas fa-university"></i> Universities</button>
            <button class="dashboard-btn" onclick="loadPage(\'generate_reports\')"><i class="fas fa-chart-bar"></i> Reports</button>
        </div>
        <div class="info-note"><i class="fas fa-check-circle"></i> Approved totals: Projects <strong>'.$approvedProjects.'</strong>, Research <strong>'.$approvedResearch.'</strong>.</div>
        <div id="subcontent"></div>';
    }
}
elseif($page == 'upload_project') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
        echo '<p>Access denied.</p>';
        exit;
    }
    echo '<h3><i class="fas fa-upload"></i> Upload Final Year Project</h3>
    <div id="message-area"></div>
    <form id="upload-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_project" />
        <div class="form-group"><label><i class="fas fa-heading"></i> Title</label><input type="text" name="title" required /></div>
        <div class="form-group"><label><i class="fas fa-align-left"></i> Abstract</label><textarea name="abstract" rows="4"></textarea></div>
        <div class="form-group"><label><i class="fas fa-users"></i> Authors</label><input type="text" name="authors" required /></div>
        <div class="form-group"><label><i class="fas fa-calendar"></i> Year</label><input type="number" name="year" required /></div>
        <div class="form-group"><label><i class="fas fa-file-pdf"></i> PDF File</label><input type="file" name="pdf_file" accept=".pdf" required /></div>
        <button type="submit"><i class="fas fa-paper-plane"></i> Submit for Review</button>
    </form>';
}
elseif($page == 'upload_research') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
        echo '<p>Access denied.</p>';
        exit;
    }
    echo '<h3><i class="fas fa-upload"></i> Upload Research Paper</h3>
    <div id="message-area"></div>
    <form id="upload-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_research" />
        <div class="form-group"><label><i class="fas fa-heading"></i> Title</label><input type="text" name="title" required /></div>
        <div class="form-group"><label><i class="fas fa-align-left"></i> Abstract</label><textarea name="abstract" rows="4"></textarea></div>
        <div class="form-group"><label><i class="fas fa-users"></i> Authors</label><input type="text" name="authors" required /></div>
        <div class="form-group"><label><i class="fas fa-calendar"></i> Year</label><input type="number" name="year" required /></div>
        <div class="form-group"><label><i class="fas fa-file-pdf"></i> PDF File</label><input type="file" name="pdf_file" accept=".pdf" required /></div>
        <button type="submit"><i class="fas fa-paper-plane"></i> Submit for Review</button>
    </form>';
}
elseif($page == 'my_uploads') {
    if(!isset($_SESSION['user_id'])) exit;
    $uid = $_SESSION['user_id'];
    $projects = $pdo->prepare("SELECT * FROM projects WHERE student_id = ?");
    $projects->execute([$uid]);
    $research = $pdo->prepare("SELECT * FROM research WHERE student_id = ?");
    $research->execute([$uid]);
    
    echo '<h3><i class="fas fa-folder-open"></i> My Uploads</h3>
    <table class="data-table">
        <tr><th>Title</th><th>Type</th><th>Status</th><th>File</th></tr>';
    while($row = $projects->fetch()) {
        $statusClass = ($row['status'] == 'approved') ? 'status-approved' : (($row['status'] == 'pending') ? 'status-pending' : 'status-rejected');
        echo "<tr><th>{$row['title']}</th><th>Project</th><td class='{$statusClass}'>{$row['status']} </th><th><a href='{$row['file_path']}' download><i class='fas fa-download'></i> Download</a></th></tr>";
    }
    while($row = $research->fetch()) {
        $statusClass = ($row['status'] == 'approved') ? 'status-approved' : (($row['status'] == 'pending') ? 'status-pending' : 'status-rejected');
        echo "<tr><th>{$row['title']}</th><th>Research</th><td class='{$statusClass}'>{$row['status']} </th><th><a href='{$row['file_path']}' download><i class='fas fa-download'></i> Download</a></th></tr>";
    }
    echo '</table>';
}
elseif($page == 'pending_reviews') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'supervisor') {
        echo '<p>Access denied. Only supervisors can access this page.</p>';
        exit;
    }
    
    // MODIFIED: Get supervisor's department - ONLY show submissions from this department
    $supervisor_dept = $_SESSION['department'] ?? '';
    
    echo '<h3><i class="fas fa-clock"></i> Pending Submissions for Review</h3>
    <div id="message-area"></div>
    <div class="info-note" style="background: #e3f2fd; padding: 15px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
        <i class="fas fa-building"></i> <strong>Your Department:</strong> ' . htmlspecialchars($supervisor_dept) . '<br>
        <i class="fas fa-info-circle"></i> <strong>Note:</strong> You can only review submissions from your own department.
    </div>';
    
    // Get projects from supervisor's department ONLY
    $projects = $pdo->prepare("SELECT p.*, u.fullname, u.email, u.department 
        FROM projects p 
        JOIN users u ON p.student_id=u.id 
        WHERE p.status='pending' AND u.department = ?
        ORDER BY p.created_at DESC");
    $projects->execute([$supervisor_dept]);
    
    // Get research papers from supervisor's department ONLY
    $research = $pdo->prepare("SELECT r.*, u.fullname, u.email, u.department 
        FROM research r 
        JOIN users u ON r.student_id=u.id 
        WHERE r.status='pending' AND u.department = ?
        ORDER BY r.created_at DESC");
    $research->execute([$supervisor_dept]);
    
    $hasPending = false;
    
    // Display projects
    while($p = $projects->fetch()) {
        $hasPending = true;
        echo "<div class='review-card'>
        <h4><i class='fas fa-project-diagram'></i> Project: {$p['title']}</h4>
        <p><i class='fas fa-user'></i> <strong>Student:</strong> {$p['fullname']} ({$p['email']})</p>
        <p><i class='fas fa-building'></i> <strong>Department:</strong> {$p['department']}</p>
        <p><i class='fas fa-align-left'></i> <strong>Abstract:</strong> " . (strlen($p['abstract']) > 200 ? substr($p['abstract'], 0, 200) . '...' : $p['abstract']) . "</p>
        <p><i class='fas fa-file-pdf'></i> <strong>File:</strong> <a href='{$p['file_path']}' target='_blank'>View PDF</a></p>
        <div class='review-actions'>
            <button class='approve-btn' onclick='reviewItem({$p['id']}, \"project\", \"approve\")'><i class='fas fa-check'></i> Approve</button>
            <button class='reject-btn' onclick='reviewItem({$p['id']}, \"project\", \"reject\")'><i class='fas fa-times'></i> Reject</button>
        </div>
        </div>";
    }
    
    // Display research papers
    while($r = $research->fetch()) {
        $hasPending = true;
        echo "<div class='review-card'>
        <h4><i class='fas fa-file-alt'></i> Research: {$r['title']}</h4>
        <p><i class='fas fa-user'></i> <strong>Student:</strong> {$r['fullname']} ({$r['email']})</p>
        <p><i class='fas fa-building'></i> <strong>Department:</strong> {$r['department']}</p>
        <p><i class='fas fa-align-left'></i> <strong>Abstract:</strong> " . (strlen($r['abstract']) > 200 ? substr($r['abstract'], 0, 200) . '...' : $r['abstract']) . "</p>
        <p><i class='fas fa-file-pdf'></i> <strong>File:</strong> <a href='{$r['file_path']}' target='_blank'>View PDF</a></p>
        <div class='review-actions'>
            <button class='approve-btn' onclick='reviewItem({$r['id']}, \"research\", \"approve\")'><i class='fas fa-check'></i> Approve</button>
            <button class='reject-btn' onclick='reviewItem({$r['id']}, \"research\", \"reject\")'><i class='fas fa-times'></i> Reject</button>
        </div>
        </div>";
    }
    
    if(!$hasPending) {
        echo '<div class="info-note success"><i class="fas fa-check-circle"></i> No pending submissions from your department.</div>';
    }
    
    echo "<script>
    function reviewItem(id, type, action) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('id', id);
        fd.append('type', type);
        fetch('system.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d=>{
            showMessage(d.message, d.success);
            if(d.success) {
                setTimeout(function() { loadPage('pending_reviews'); }, 1500);
            }
        });
    }
    </script>";
}
elseif($page == 'manage_users') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
        echo '<p>Access denied.</p>';
        exit;
    }
    $users = $pdo->query("SELECT u.*, un.name as uni_name FROM users u LEFT JOIN universities un ON u.university_id=un.id ORDER BY u.id DESC")->fetchAll();
    $unis = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll();
    echo '<h3><i class="fas fa-users"></i> Manage Users</h3><div id="message-area"></div>';
    echo '<div class="add-user-form">
        <h4><i class="fas fa-user-plus"></i> Add New User</h4>
        <form id="add-user-form">
            <input type="hidden" name="action" value="add_user" />
            <div class="form-row">
                <div class="form-group"><label>Full Name</label><input type="text" name="fullname" required /></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required /></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Password</label><input type="password" name="password" required /></div>
                <div class="form-group"><label>Role</label><select name="role"><option value="student">Student</option><option value="supervisor">Supervisor</option><option value="administrator">Administrator</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>University</label><select name="university_id" required><option value="">Select University</option>';
    foreach($unis as $u) { echo "<option value='{$u['id']}'>{$u['name']}</option>"; }
    echo '</select></div>
                <div class="form-group"><label>Department</label><select name="department" required><option value="">Select Department</option>';
    foreach($departments as $dept) {
        echo "<option value='{$dept}'>{$dept}</option>";
    }
    echo '</select></div>
            </div>
            <button type="submit"><i class="fas fa-save"></i> Create User</button>
        </form>
    </div>';


    echo '<table class="data-table">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>University</th><th>Actions</th></tr>
        </thead>
        <tbody>';
    foreach($users as $user) {
        $actions = '';
        if($user['role'] !== 'administrator') {
            $actions .= "<button class='action-btn promote-btn' data-action='change_role' data-id='{$user['id']}' data-type='administrator'><i class='fas fa-arrow-up'></i> Promote</button>";
            $actions .= "<button class='action-btn delete-btn' data-action='delete_user' data-id='{$user['id']}'><i class='fas fa-trash'></i> Delete</button>";
        } else {
            $actions .= "<button class='action-btn demote-btn' data-action='change_role' data-id='{$user['id']}' data-type='student'><i class='fas fa-arrow-down'></i> Demote</button>";
        }
        echo "<tr><th>{$user['id']}</th><th>{$user['fullname']}</th><th>{$user['email']}</th><th>{$user['role']}</th><th>{$user['department']}</th><th>{$user['uni_name']}</th><th>{$actions}</th></tr>";
    }
    echo '</tbody>
    </table>';
}
elseif($page == 'manage_projects') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
        echo '<p>Access denied.</p>';
        exit;
    }
    $projects = $pdo->query("SELECT p.*, u.fullname FROM projects p JOIN users u ON p.student_id=u.id ORDER BY p.id DESC")->fetchAll();
    echo '<h3><i class="fas fa-project-diagram"></i> Manage Projects</h3><div id="message-area"></div>';
    echo '<table class="data-table">'
       . '<thead>'
       . '<tr><th>ID</th><th>Title</th><th>Student</th><th>Status</th><th>Year</th><th>Actions</th></tr>'
       . '</thead>'
       . '<tbody>';

    foreach($projects as $proj) {
        $statusClass = ($proj['status'] == 'approved') ? 'status-approved' : (($proj['status'] == 'pending') ? 'status-pending' : 'status-rejected');
        $id = (int)$proj['id'];
        $title = htmlspecialchars((string)$proj['title'], ENT_QUOTES, 'UTF-8');
        $fullname = htmlspecialchars((string)$proj['fullname'], ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars((string)$proj['year'], ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string)$proj['status'], ENT_QUOTES, 'UTF-8');

        $actions = '';
        if($proj['status'] !== 'approved') {
            $actions .= "<button class='action-btn' data-action='admin_approve' data-id='{$id}' data-type='project'><i class='fas fa-check'></i> Approve</button>";
        }
        if($proj['status'] !== 'rejected') {
            $actions .= "<button class='action-btn' data-action='admin_reject' data-id='{$id}' data-type='project'><i class='fas fa-times'></i> Reject</button>";
        }

        echo "<tr><th>{$id}</th><th>{$title}</th><th>{$fullname}</th><td class='{$statusClass}'>{$status} </th><th>{$year}</th><th>{$actions}</th></tr>";
    }

    echo '</tbody></table>';
}

elseif($page == 'manage_research') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
        echo '<p>Access denied.</p>';
        exit;
    }
    $research = $pdo->query("SELECT r.*, u.fullname FROM research r JOIN users u ON r.student_id=u.id ORDER BY r.id DESC")->fetchAll();
    echo '<h3><i class="fas fa-book"></i> Manage Research Papers</h3><div id="message-area"></div>';
    echo '<table class="data-table">'
       . '<thead>'
       . '<tr><th>ID</th><th>Title</th><th>Student</th><th>Status</th><th>Year</th><th>Actions</th></tr>'
       . '</thead>'
       . '<tbody>';

    foreach($research as $res) {
        $statusClass = ($res['status'] == 'approved') ? 'status-approved' : (($res['status'] == 'pending') ? 'status-pending' : 'status-rejected');
        $id = (int)$res['id'];
        $title = htmlspecialchars((string)$res['title'], ENT_QUOTES, 'UTF-8');
        $fullname = htmlspecialchars((string)$res['fullname'], ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars((string)$res['year'], ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string)$res['status'], ENT_QUOTES, 'UTF-8');

        $actions = '';
        if($res['status'] !== 'approved') {
            $actions .= "<button class='action-btn' data-action='admin_approve' data-id='{$id}' data-type='research'><i class='fas fa-check'></i> Approve</button>";
        }
        if($res['status'] !== 'rejected') {
            $actions .= "<button class='action-btn' data-action='admin_reject' data-id='{$id}' data-type='research'><i class='fas fa-times'></i> Reject</button>";
        }

        echo "<tr><th>{$id}</th><th>{$title}</th><th>{$fullname}</th><td class='{$statusClass}'>{$status} </th><th>{$year}</th><th>{$actions}</th></tr>";
    }

    echo '</tbody></table>';
}

elseif($page == 'manage_universities') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
        echo '<p>Access denied.</p>';
        exit;
    }
    $unis = $pdo->query("SELECT * FROM universities ORDER BY name")->fetchAll();
    echo '<h3><i class="fas fa-university"></i> Manage Universities</h3><div id="message-area"></div>
    <table class="data-table">
        <thead>
            <tr><th>No.</th><th>University Name</th></tr>
        </thead>
        <tbody>';
    $nno = 1;
    foreach($unis as $uni) {
        echo "翅<th>{$nno}</th><th><i class='fas fa-university'></i> {$uni['name']}</th></tr>";
        $nno++;
    }
    echo '</tbody>
    </table>';
}
elseif($page == 'education_gallery') { 

    $images = [
        'education_1.jpg',
        'education_2.jpg',
        'education_3.jpg',
        'education_4.jpg',
        'education_5.jpg',
        'education_6.jpg'
    ];

    echo '<div class="education-gallery">'
       . '<h3><i class="fas fa-graduation-cap"></i> Education Images</h3>'
       . '<div class="education-grid">';

    foreach($images as $img) {
        $src = 'images/' . $img;
        $fileExists = file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $img);
        if($fileExists) {
            $imgSafe = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars(str_replace('_', ' ', pathinfo($img, PATHINFO_FILENAME)), ENT_QUOTES, 'UTF-8');
            echo "<div class=\"education-tile\">";
            echo "<img class=\"education-thumb\" src=\"{$imgSafe}\" alt=\"{$caption}\" loading=\"lazy\" />";
            echo "<div class=\"education-caption\">{$caption}</div>";
            echo "</div>";
        } else {
            $caption = htmlspecialchars(str_replace('_', ' ', pathinfo($img, PATHINFO_FILENAME)), ENT_QUOTES, 'UTF-8');
            echo "<div class=\"education-tile\">";
            echo "<div class=\"education-thumb\" style=\"display:flex;align-items:center;justify-content:center;font-weight:700;color:#1e3c72;\">Image not available</div>";
            echo "<div class=\"education-caption\">{$caption}</div>";
            echo "</div>";
        }
    }

    echo '</div></div>';
}
elseif($page == 'generate_reports') {
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
        echo '<p>Access denied.</p>';
        exit;
    }
    $totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $approvedProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='approved'")->fetchColumn();
    $pendingProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='pending'")->fetchColumn();
    $rejectedProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='rejected'")->fetchColumn();
    $totalResearch = $pdo->query("SELECT COUNT(*) FROM research")->fetchColumn();
    $approvedResearch = $pdo->query("SELECT COUNT(*) FROM research WHERE status='approved'")->fetchColumn();
    $pendingResearch = $pdo->query("SELECT COUNT(*) FROM research WHERE status='pending'")->fetchColumn();
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $totalSupervisors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='supervisor'")->fetchColumn();
    
    echo '<h3><i class="fas fa-chart-bar"></i> System Reports</h3>
    <div id="message-area"></div>
    
    <div class="report-section">
        <h4><i class="fas fa-project-diagram"></i> Projects Statistics</h4>
        <table class="report-table">
            <tr><th>Metric</th><th>Count</th></tr>
            <tr><th>Total Projects</th><th>'.$totalProjects.'</th></tr>
            <tr><th>Approved Projects</th><th>'.$approvedProjects.'</th></tr>
            <tr><th>Pending Projects</th><th>'.$pendingProjects.'</th></tr>
            <tr><th>Rejected Projects</th><th>'.$rejectedProjects.'</th></tr>
        </table>
    </div>
    
    <div class="report-section">
        <h4><i class="fas fa-book"></i> Research Papers Statistics</h4>
        <table class="report-table">
            <tr><th>Metric</th><th>Count</th></tr>
            <tr><th>Total Research Papers</th><th>'.$totalResearch.'</th></tr>
            <tr><th>Approved Research</th><th>'.$approvedResearch.'</th></tr>
            <tr><th>Pending Research</th><th>'.$pendingResearch.'</th></tr>
        </table>
    </div>
    
    <div class="report-section">
        <h4><i class="fas fa-users"></i> User Statistics</h4>
        <table class="report-table">
            <tr><th>Metric</th><th>Count</th></tr>
            <tr><th>Total Users</th><th>'.$totalUsers.'</th></tr>
            <tr><th>Total Students</th><th>'.$totalStudents.'</th></tr>
            <tr><th>Total Supervisors</th><th>'.$totalSupervisors.'</th></tr>
        </table>
    </div>';
}
else {
    echo '<p>Page not found.</p>';
}

?>