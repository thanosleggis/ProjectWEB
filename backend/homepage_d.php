<?php
session_start();
include 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

// Get user details
$user = $_SESSION['user'];
$id = $_SESSION['id'];
$name = htmlspecialchars($user['name']);
$lastname = htmlspecialchars($user['lastname']);
$user_type = htmlspecialchars($_SESSION['user_type']);

// Debug helper: return basic session identity (safe fields only)
if (isset($_GET['action']) && $_GET['action'] === 'whoami') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'id' => isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0,
        'user_type' => isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null,
        'name' => isset($user['name']) ? $user['name'] : null,
        'lastname' => isset($user['lastname']) ? $user['lastname'] : null,
    ]);
    exit();
}

// Debug helper: return simple counts for current user across roles
if (isset($_GET['action']) && $_GET['action'] === 'diagnostics_counts') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $uid = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        $counts = ['uid' => $uid];
        $q = function($sql, $uid) use ($conn) {
            $s = $conn->prepare($sql);
            $s->bindParam(':id', $uid, PDO::PARAM_INT);
            $s->execute();
            return (int)$s->fetchColumn();
        };
        $counts['epivlepwn'] = $q('SELECT COUNT(*) FROM diplwmatiki WHERE epivlepwn_id = :id', $uid);
        $counts['melos1']    = $q('SELECT COUNT(*) FROM diplwmatiki WHERE melos1_id = :id', $uid);
        $counts['melos2']    = $q('SELECT COUNT(*) FROM diplwmatiki WHERE melos2_id = :id', $uid);
        echo json_encode($counts);
    } catch (Throwable $e) {
        echo json_encode(['uid' => isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0, 'error' => true]);
    }
    exit();
}

//ΑΡΧΙΚΗ

// AJAX endpoint: return recent "Νέες Προσκλήσεις" as JSON
if (isset($_GET['action']) && $_GET['action'] === 'refresh_new_prosklhseis') {
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
    $stmt = $conn->prepare("
            SELECT 
                p.id AS id,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                f.phone AS foititis_phone,
                d.title AS diplwmatiki_title,
                p.type AS type,
                p.datetime_created AS datetime
            FROM prosklhsh p
            LEFT JOIN foititis f ON p.foititis_id = f.id
            LEFT JOIN diplwmatiki d ON p.diplwmatiki_id = d.id
            WHERE p.didaskwn_id = :id AND p.datetime_created > NOW() - INTERVAL 3 DAY
            ORDER BY p.datetime_created DESC
            LIMIT 50
        ");
        $stmt->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        if (isset($_SESSION['id'])) { header('X-Session-Id: '.(int)$_SESSION['id']); }
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// AJAX endpoint: return active diplwmatikes under supervision for the current user
if (isset($_GET['action']) && $_GET['action'] === 'refresh_active_diplwmatikes') {
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $stmt = $conn->prepare("
            SELECT
                d.id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                d.start_date AS start_date,
                d.katastash AS katastash
            FROM diplwmatiki d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            WHERE d.epivlepwn_id = :id AND d.katastash = 'ΕΝΕΡΓΗ'
            ORDER BY d.start_date DESC
            LIMIT 100
        ");
        $stmt->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        if (isset($_SESSION['id'])) { header('X-Session-Id: '.(int)$_SESSION['id']); }
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

//ΘΕΜΑΤΑ

// Handle AJAX request for refreshing "Λίστα Θεμάτων"
if (isset($_GET['action']) && $_GET['action'] === 'refresh_lista_thematwn') {
    try {
        $stmtListaThematwn = $conn->prepare("
            SELECT
                t.id AS id,
                t.title AS title,
                t.description AS description,
                t.pdf AS pdf,
                t.katastash AS katastash,
                COALESCE(f.lastname, 'N/A') AS foititis_lastname
            FROM 
                themata t
            LEFT JOIN
                foititis f ON t.foititis_id = f.id
            WHERE
                t.didaskwn_id = :id;
        "); 
        $stmtListaThematwn->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
        $stmtListaThematwn->execute();
    $listathematwn = $stmtListaThematwn->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($listathematwn);
    } catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    }
    exit();
}

// AJAX endpoint — return available themata for assign dropdown
if (isset($_GET['action']) && $_GET['action'] === 'refresh_diathesima_themata') {
    try {
        $stmt = $conn->prepare("SELECT title AS title, description AS description, pdf AS pdf FROM themata WHERE didaskwn_id = :id AND katastash = 'ΕΛΕΥΘΕΡΟ'");
        $stmt->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// ==========================
// AJAX: Create Thema
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_thema') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        if ($title === '' || $description === '') {
            echo json_encode(['success' => false, 'error' => 'Λείπουν πεδία τίτλου/περιγραφής.']);
            exit();
        }
        $katastash = 'ΕΛΕΥΘΕΡΟ';
        $didaskwn_id = $_SESSION['id'];

        $pdf = null;
        if (!empty($_FILES['pdf']) && isset($_FILES['pdf']['error']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/themata/';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
            // Keep the original filename as requested
            $original = basename($_FILES['pdf']['name']);
            $safe = $original; // preserve original name
            $dest = $uploadDir . $safe;
            if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $dest)) {
                echo json_encode(['success' => false, 'error' => 'Αποτυχία μεταφόρτωσης PDF.']);
                exit();
            }
            $pdf = $safe;
        }

        $stmt = $conn->prepare("INSERT INTO themata (didaskwn_id, title, description, pdf, katastash) VALUES (:didaskwn_id, :title, :description, :pdf, :katastash)");
        $stmt->bindParam(':didaskwn_id', $didaskwn_id, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':pdf', $pdf, PDO::PARAM_STR);
        $stmt->bindParam(':katastash', $katastash, PDO::PARAM_STR);
        $stmt->execute();
        $newId = $conn->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// ==========================
// AJAX: Assign Thema
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_thema') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $foititis_id = isset($_POST['foititis-id']) ? (int)$_POST['foititis-id'] : 0;
        $thema_title = isset($_POST['thema-id']) ? trim($_POST['thema-id']) : '';
        if ($foititis_id <= 0 || $thema_title === '') {
            echo json_encode(['success' => false, 'error' => 'Λείπουν δεδομένα ανάθεσης.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id, didaskwn_id FROM themata WHERE title = :title AND didaskwn_id = :didaskwn_id");
        $stmt->bindParam(':title', $thema_title, PDO::PARAM_STR);
        $stmt->bindParam(':didaskwn_id', $_SESSION['id'], PDO::PARAM_INT);
        $stmt->execute();
        $thema = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$thema) {
            echo json_encode(['success' => false, 'error' => 'Το θέμα δεν βρέθηκε.']);
            exit();
        }
        $thema_id = (int)$thema['id'];
        $didaskwn_id = (int)$thema['didaskwn_id'];

        $stmt = $conn->prepare("UPDATE themata SET katastash = 'ΔΕΣΜΕΥΜΕΝΟ', foititis_id = :foititis_id WHERE id = :thema_id");
        $stmt->bindParam(':foititis_id', $foititis_id, PDO::PARAM_INT);
        $stmt->bindParam(':thema_id', $thema_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO diplwmatiki (title, foititis_id, epivlepwn_id, start_date, katastash) VALUES (:title, :foititis_id, :epivlepwn_id, CURDATE(), 'ΥΠΟ ΑΝΑΘΕΣΗ')");
        $stmt->bindParam(':title', $thema_title, PDO::PARAM_STR);
        $stmt->bindParam(':foititis_id', $foititis_id, PDO::PARAM_INT);
        $stmt->bindParam(':epivlepwn_id', $didaskwn_id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// ==========================
// AJAX: Edit Thema
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_thema') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $edit_id = isset($_POST['edit_thema_id']) ? (int)$_POST['edit_thema_id'] : 0;
        $edit_title = isset($_POST['edit_title']) ? trim($_POST['edit_title']) : '';
        $edit_description = isset($_POST['edit_description']) ? trim($_POST['edit_description']) : '';
        $edit_katastash = isset($_POST['edit_katastash']) ? $_POST['edit_katastash'] : '';
        if ($edit_id <= 0 || $edit_title === '' || $edit_description === '') {
            echo json_encode(['success' => false, 'error' => 'Μη έγκυρα δεδομένα επεξεργασίας.']);
            exit();
        }

        $didaskwn_id = $_SESSION['id'];
        $uploadedPdf = null;
        if (!empty($_FILES['edit_pdf']) && isset($_FILES['edit_pdf']['error']) && $_FILES['edit_pdf']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/themata/';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
            $original = basename($_FILES['edit_pdf']['name']);
            $safe = $original; // preserve original name
            $dest = $uploadDir . $safe;
            if (move_uploaded_file($_FILES['edit_pdf']['tmp_name'], $dest)) {
                $uploadedPdf = $safe;
            }
        }

        if ($uploadedPdf !== null) {
            $stmt = $conn->prepare("UPDATE themata SET title = :title, description = :description, pdf = :pdf, katastash = :katastash WHERE id = :id AND didaskwn_id = :didaskwn_id");
            $stmt->bindParam(':title', $edit_title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $edit_description, PDO::PARAM_STR);
            $stmt->bindParam(':pdf', $uploadedPdf, PDO::PARAM_STR);
            $stmt->bindParam(':katastash', $edit_katastash, PDO::PARAM_STR);
            $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
            $stmt->bindParam(':didaskwn_id', $didaskwn_id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE themata SET title = :title, description = :description, katastash = :katastash WHERE id = :id AND didaskwn_id = :didaskwn_id");
            $stmt->bindParam(':title', $edit_title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $edit_description, PDO::PARAM_STR);
            $stmt->bindParam(':katastash', $edit_katastash, PDO::PARAM_STR);
            $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
            $stmt->bindParam(':didaskwn_id', $didaskwn_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle AJAX request for searching foititis
if (isset($_GET['query'])) {
    $query = $_GET['query'];

    try {
        $stmt = $conn->prepare("
            SELECT id, name, lastname
            FROM foititis
            WHERE id LIKE :query OR name LIKE :query OR lastname LIKE :query
        ");
        $searchQuery = "%$query%";
        $stmt->bindParam(':query', $searchQuery, PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($results);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
    exit();
}

//ΔΙΠΛΩΜΑΤΙΚΕΣ ΠΑΡΤ1

// AJAX endpoint: return all diplwmatikes under supervision for the current user (all statuses)
if (isset($_GET['action']) && $_GET['action'] === 'refresh_all_diplwmatikes') {
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $stmt = $conn->prepare("
            SELECT
                d.id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                m1.lastname AS melos1_lastname,
                m2.lastname AS melos2_lastname,
                d.start_date AS start_date,
                d.katastash AS katastash
            FROM diplwmatiki d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn m1 ON d.melos1_id = m1.id
            LEFT JOIN didaskwn m2 ON d.melos2_id = m2.id
            WHERE d.epivlepwn_id = :id
            ORDER BY d.start_date DESC
            LIMIT 500
        ");
        $stmt->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// AJAX endpoint: return diplwmatikes where current user is 1ο Μέλος (melos1)
if (isset($_GET['action']) && $_GET['action'] === 'refresh_melos1_diplwmatikes') {
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $stmt = $conn->prepare("
            SELECT
                d.id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                ep.lastname AS epivlepwn_lastname,
                m2.lastname AS melos2_lastname,
                d.start_date AS start_date,
                d.katastash AS katastash
            FROM diplwmatiki d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn ep ON d.epivlepwn_id = ep.id
            LEFT JOIN didaskwn m2 ON d.melos2_id = m2.id
            WHERE d.melos1_id = :id
            ORDER BY d.start_date DESC
            LIMIT 500
        ");
        $stmt->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// AJAX endpoint: return diplwmatikes where current user is 2ο Μέλος (melos2)
if (isset($_GET['action']) && $_GET['action'] === 'refresh_melos2_diplwmatikes') {
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $stmt = $conn->prepare("
            SELECT
                d.id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                ep.lastname AS epivlepwn_lastname,
                m1.lastname AS melos1_lastname,
                d.start_date AS start_date,
                d.katastash AS katastash
            FROM diplwmatiki d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn ep ON d.epivlepwn_id = ep.id
            LEFT JOIN didaskwn m1 ON d.melos1_id = m1.id
            WHERE d.melos2_id = :id
            ORDER BY d.start_date DESC
            LIMIT 500
        ");
        $stmt->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// AJAX endpoint: return finished/old diplwmatikes (history) for current user
if (isset($_GET['action']) && $_GET['action'] === 'refresh_old_diplwmatikes') {
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $stmt = $conn->prepare("
            SELECT
                d.diplwmatiki_id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                ep.lastname AS epivlepwn_lastname,
                m1.lastname AS melos1_lastname,
                m2.lastname AS melos2_lastname,
                d.bathmos AS bathmos,
                d.start_date AS start_date,
                d.end_date AS end_date
            FROM diplwmatiki_FINISHED d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn ep ON d.epivlepwn_id = ep.id
            LEFT JOIN didaskwn m1 ON d.melos1_id = m1.id
            LEFT JOIN didaskwn m2 ON d.melos2_id = m2.id
            WHERE (d.epivlepwn_id = :id OR d.melos1_id = :id OR d.melos2_id = :id)
            ORDER BY d.end_date DESC
            LIMIT 500
        ");
        $stmt->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// --- NEW: AJAX endpoint — export all diplwmatikes related to current user (both active and finished)
if (isset($_GET['action']) && $_GET['action'] === 'export_all_diplwmatikes') {
    try {
        while (ob_get_level()) ob_end_clean();
        $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        if ($currentUserId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'data' => []]);
            exit();
        }

        // Active / ongoing diplwmatikes from diplwmatiki
        $stmt1 = $conn->prepare("SELECT 
                d.id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                m1.lastname AS melos1_lastname,
                m2.lastname AS melos2_lastname,
                d.start_date AS start_date,
                d.katastash AS katastash,
                'active' AS source
            FROM diplwmatiki d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn m1 ON d.melos1_id = m1.id
            LEFT JOIN didaskwn m2 ON d.melos2_id = m2.id
            WHERE (d.epivlepwn_id = :id OR d.melos1_id = :id OR d.melos2_id = :id)");
        $stmt1->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt1->execute();
        $rows1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        // Finished diplwmatikes from diplwmatiki_FINISHED
        $stmt2 = $conn->prepare("SELECT 
                d.diplwmatiki_id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                m1.lastname AS melos1_lastname,
                m2.lastname AS melos2_lastname,
                d.start_date AS start_date,
                d.end_date AS end_date,
                COALESCE(d.bathmos, '') AS bathmos,
                'finished' AS source
            FROM diplwmatiki_FINISHED d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn m1 ON d.melos1_id = m1.id
            LEFT JOIN didaskwn m2 ON d.melos2_id = m2.id
            WHERE (d.epivlepwn_id = :id OR d.melos1_id = :id OR d.melos2_id = :id)");
        $stmt2->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt2->execute();
        $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Combine and normalize keys
        $combined = array_merge($rows1 ?: [], $rows2 ?: []);
        // Ensure consistent keys and types and remove date fields / null values from output
        foreach ($combined as &$r) {
            $r['diplwmatiki_id'] = isset($r['diplwmatiki_id']) ? (int)$r['diplwmatiki_id'] : null;
            $r['diplwmatiki_title'] = isset($r['diplwmatiki_title']) ? $r['diplwmatiki_title'] : '';
            $r['foititis_name'] = isset($r['foititis_name']) ? $r['foititis_name'] : '';
            $r['foititis_lastname'] = isset($r['foititis_lastname']) ? $r['foititis_lastname'] : '';
            $r['melos1_lastname'] = isset($r['melos1_lastname']) ? $r['melos1_lastname'] : '';
            $r['melos2_lastname'] = isset($r['melos2_lastname']) ? $r['melos2_lastname'] : '';
            // drop date fields entirely from JSON output
            if (isset($r['start_date'])) unset($r['start_date']);
            if (isset($r['end_date'])) unset($r['end_date']);
            $r['katastash'] = isset($r['katastash']) ? $r['katastash'] : (isset($r['source']) && $r['source'] === 'finished' ? 'ΟΛΟΚΛΗΡΩΘΗΚΕ' : '');
            $r['bathmos'] = isset($r['bathmos']) ? $r['bathmos'] : null;

            // remove keys that are explicitly null so JSON doesn't contain nulls
            $clean = [];
            foreach ($r as $k => $v) {
                if ($v !== null) {
                    $clean[$k] = $v;
                }
            }
            $r = $clean;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $combined]);
    } catch (PDOException $e) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

//ΔΙΠΛΩΜΑΤΙΚΕΣ ΠΑΡΤ2

// Handle AJAX request for diplwmatiki details
if (isset($_GET['diplwmatiki_id']) && isset($_GET['source'])) {
    $diplwmatiki_id = $_GET['diplwmatiki_id'];
    $source = $_GET['source'];

    if ($source === "table-4") {
        $stmt = $conn->prepare("
        SELECT 
            d.title AS diplwmatiki_title,
            f.name AS foititis_name,
            f.lastname AS foititis_lastname,
            e.name AS epivlepwn_name,
            e.lastname AS epivlepwn_lastname,
            d.epivlepwn_id AS epivlepwn_id,
            d.melos1_id AS melos1_id,
            d.melos2_id AS melos2_id,
            NULL AS bathmos_epivlepwn,
            NULL AS bathmos_melos1,
            NULL AS bathmos_melos2,
            m1.name AS melos1_name,
            m1.lastname AS melos1_lastname,
            m2.name AS melos2_name,
            m2.lastname AS melos2_lastname,
            d.start_date AS start_date,
            d.end_date AS end_date,
            d.bathmos AS bathmos,
            0 AS bathmos_enable,
            NULL AS proxeiro,
            d.nhmerths_link AS link,
            d.praktiko AS praktiko,
            'ΟΛΟΚΛΗΡΩΘΗΚΕ' AS katastash
        FROM 
            diplwmatiki_FINISHED d
        LEFT JOIN
            foititis f ON d.foititis_id = f.id
        LEFT JOIN
            didaskwn e ON d.epivlepwn_id = e.id
        LEFT JOIN
            didaskwn m1 ON d.melos1_id = m1.id
        LEFT JOIN
            didaskwn m2 ON d.melos2_id = m2.id
        WHERE 
            d.diplwmatiki_id = :id"
    );
    } else {
        $stmt = $conn->prepare("
        SELECT 
            d.title AS diplwmatiki_title,
            f.name AS foititis_name,
            f.lastname AS foititis_lastname,
            e.name AS epivlepwn_name,
            e.lastname AS epivlepwn_lastname,
            d.epivlepwn_id AS epivlepwn_id,
            d.melos1_id AS melos1_id,
            d.melos2_id AS melos2_id,
            d.bathmos_epivlepwn AS bathmos_epivlepwn,
            d.bathmos_melos1 AS bathmos_melos1,
            d.bathmos_melos2 AS bathmos_melos2,
            m1.name AS melos1_name,
            m1.lastname AS melos1_lastname,
            m2.name AS melos2_name,
            m2.lastname AS melos2_lastname,
            d.start_date AS start_date,
            d.katastash AS katastash,
            d.bathmos_enable AS bathmos_enable,
            d.proxeiro AS proxeiro,
            d.nhmerths_link AS link,
            NULL AS praktiko 
        FROM 
            diplwmatiki d
        LEFT JOIN
            foititis f ON d.foititis_id = f.id
        LEFT JOIN
            didaskwn e ON d.epivlepwn_id = e.id
        LEFT JOIN
            didaskwn m1 ON d.melos1_id = m1.id
        LEFT JOIN
            didaskwn m2 ON d.melos2_id = m2.id
        WHERE 
            d.id = :id"
    );
    }


    $stmt->bindParam(':id', $diplwmatiki_id, PDO::PARAM_INT);
    $stmt->execute();
    $diplwmatiki = $stmt->fetch(PDO::FETCH_ASSOC);

    // Compute whether current user already has submitted a grade for this diplwmatiki
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    $user_has_bathmos = false;
    if ($diplwmatiki) {
        // Normalize keys if not present
        $epiv = isset($diplwmatiki['epivlepwn_id']) ? (int)$diplwmatiki['epivlepwn_id'] : 0;
        $m1 = isset($diplwmatiki['melos1_id']) ? (int)$diplwmatiki['melos1_id'] : 0;
        $m2 = isset($diplwmatiki['melos2_id']) ? (int)$diplwmatiki['melos2_id'] : 0;

        if ($currentUserId && $currentUserId === $epiv && !empty($diplwmatiki['bathmos_epivlepwn'])) $user_has_bathmos = true;
        if ($currentUserId && $currentUserId === $m1 && !empty($diplwmatiki['bathmos_melos1'])) $user_has_bathmos = true;
        if ($currentUserId && $currentUserId === $m2 && !empty($diplwmatiki['bathmos_melos2'])) $user_has_bathmos = true;
    }

    $diplwmatiki['user_has_bathmos'] = $user_has_bathmos;

    echo json_encode($diplwmatiki);

    exit();
}

// --- New: download proxeiro file for a given diplwmatiki_id
// Usage: GET homepage_d.php?action=download_proxeiro&diplwmatiki_id=123
if (isset($_GET['action']) && $_GET['action'] === 'download_proxeiro' && isset($_GET['diplwmatiki_id'])) {
    $selectedId = (int)$_GET['diplwmatiki_id'];
    // Ensure logged-in user
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    if ($currentUserId <= 0) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
        echo 'Unauthorized';
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT proxeiro FROM diplwmatiki WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $selectedId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['proxeiro'])) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            echo 'File not found';
            exit();
        }

        $filename = basename($row['proxeiro']);
        $filePath = dirname(__DIR__) . '/uploads/proxeira/' . $filename;

        if (!is_file($filePath) || !is_readable($filePath)) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            echo 'File not available';
            exit();
        }

        // Send appropriate headers and stream file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $filePath) : 'application/octet-stream';
        if ($finfo) finfo_close($finfo);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // Flush output buffers and read the file
        while (ob_get_level()) ob_end_clean();
        readfile($filePath);
        exit();
    } catch (PDOException $e) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
        echo 'Server error';
        exit();
    }
}

// Handle cancellation of a diplwmatiki (set katastash = 'ΑΚΥΡΩΜΕΝΗ')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_diplwmatiki' && isset($_POST['diplwmatiki_id'])) {
    $did = (int)$_POST['diplwmatiki_id'];
    $cancelNumber = isset($_POST['cancel_number']) ? trim($_POST['cancel_number']) : null;
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    try {
    // Check ownership and get start_date
    $check = $conn->prepare("SELECT epivlepwn_id, start_date FROM diplwmatiki WHERE id = :did LIMIT 1");
    $check->bindParam(':did', $did, PDO::PARAM_INT);
    $check->execute();
    $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Diplwmatiki not found']);
            exit();
        }

        if ((int)$row['epivlepwn_id'] !== $currentUserId) {
            echo json_encode(['success' => false, 'error' => 'Μόνο ο Επιβλέπων Καθηγητής μπορεί να ακυρώσει διπλωματική']);
            exit();
        }

            // Enforce 2-year rule: at least 2 years must have passed since start_date
            if (empty($row['start_date'])) {
                echo json_encode(['success' => false, 'error' => 'Η ημερομηνία έναρξης δεν είναι διαθέσιμη.']);
                exit();
            }

            try {
                $start = new DateTime($row['start_date']);
                $now = new DateTime();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Μη έγκυρη ημερομηνία έναρξης.']);
                exit();
            }

            $diff = $now->diff($start);
            if ($diff->y < 2) {
                echo json_encode(['success' => false, 'error' => 'Δεν μπορούν να ακυρωθούν διπλωματικές πριν περάσουν 2 χρόνια από την ημερομηνία έναρξης.']);
                exit();
            }

        // Update status to 'ΑΚΥΡΩΜΕΝΗ'
        $upd = $conn->prepare("UPDATE diplwmatiki SET katastash = 'ΑΚΥΡΩΜΕΝΗ' WHERE id = :did");
        $upd->bindParam(':did', $did, PDO::PARAM_INT);
        $upd->execute();

        // Optionally log or store $cancelNumber somewhere later

        echo json_encode(['success' => true, 'updated' => $upd->rowCount()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }

    exit();
}

// Handle conversion to 'ΥΠΟ ΕΞΕΤΑΣΗ'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eksetasi_diplwmatiki' && isset($_POST['diplwmatiki_id'])) {
    $did = (int)$_POST['diplwmatiki_id'];
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $check = $conn->prepare("SELECT epivlepwn_id FROM diplwmatiki WHERE id = :did LIMIT 1");
        $check->bindParam(':did', $did, PDO::PARAM_INT);
        $check->execute();
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Diplwmatiki not found']); exit(); }
        if ((int)$row['epivlepwn_id'] !== $currentUserId) { echo json_encode(['success' => false, 'error' => 'Μόνο ο Επιβλέπων μπορεί να αλλάξει κατάσταση']); exit(); }

        $upd = $conn->prepare("UPDATE diplwmatiki SET katastash = 'ΥΠΟ ΕΞΕΤΑΣΗ' WHERE id = :did");
        $upd->bindParam(':did', $did, PDO::PARAM_INT);
        $upd->execute();
        echo json_encode(['success' => true, 'updated' => $upd->rowCount()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit();
}

// Handle enabling grade entry (bathmos_enable = 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'energopoihsh_bathmou' && isset($_POST['diplwmatiki_id'])) {
    $did = (int)$_POST['diplwmatiki_id'];
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $check = $conn->prepare("SELECT epivlepwn_id FROM diplwmatiki WHERE id = :did LIMIT 1");
        $check->bindParam(':did', $did, PDO::PARAM_INT);
        $check->execute();
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Diplwmatiki not found']); exit(); }
        if ((int)$row['epivlepwn_id'] !== $currentUserId) { echo json_encode(['success' => false, 'error' => 'Μόνο ο Επιβλέπων μπορεί να ενεργοποιήσει την καταχώρηση βαθμού']); exit(); }

        $upd = $conn->prepare("UPDATE diplwmatiki SET bathmos_enable = 1 WHERE id = :did");
        $upd->bindParam(':did', $did, PDO::PARAM_INT);
        $upd->execute();
        echo json_encode(['success' => true, 'updated' => $upd->rowCount()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit();
}

// Handle submission of mean grade by epivlepwn/melos1/melos2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_bathmoi' && isset($_POST['diplwmatiki_id']) && isset($_POST['mean'])) {
    $did = (int)$_POST['diplwmatiki_id'];
    $mean = floatval($_POST['mean']);
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    // basic validation
    if ($mean < 0 || $mean > 10) {
        echo json_encode(['success' => false, 'error' => 'Μη έγκυρος βαθμός']);
        exit();
    }

    try {
        $check = $conn->prepare("SELECT epivlepwn_id, melos1_id, melos2_id FROM diplwmatiki WHERE id = :did LIMIT 1");
        $check->bindParam(':did', $did, PDO::PARAM_INT);
        $check->execute();
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Diplwmatiki not found']); exit(); }

        $upd = null;
        if ((int)$row['epivlepwn_id'] === $currentUserId) {
            $upd = $conn->prepare("UPDATE diplwmatiki SET bathmos_epivlepwn = :mean WHERE id = :did");
        } elseif ((int)$row['melos1_id'] === $currentUserId) {
            $upd = $conn->prepare("UPDATE diplwmatiki SET bathmos_melos1 = :mean WHERE id = :did");
        } elseif ((int)$row['melos2_id'] === $currentUserId) {
            $upd = $conn->prepare("UPDATE diplwmatiki SET bathmos_melos2 = :mean WHERE id = :did");
        } else {
            echo json_encode(['success' => false, 'error' => 'Μόνο μέλη της τριμελούς επιτροπής μπορούν να υποβάλουν βαθμό']);
            exit();
        }

        $upd->bindParam(':mean', $mean);
        $upd->bindParam(':did', $did, PDO::PARAM_INT);
        $upd->execute();
        echo json_encode(['success' => true, 'updated' => $upd->rowCount()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit();
}

// --- NEW: AJAX endpoint — return prosklhsh rows for a given diplwmatiki_id (JSON)
if (isset($_GET['action']) && $_GET['action'] === 'related_prosklhseis' && isset($_GET['diplwmatiki_id'])) {
    $selectedId = (int)$_GET['diplwmatiki_id'];
    try {
        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.type,
                p.katastash,
                p.datetime_created,
                p.datetime_answer,
                p.diplwmatiki_id,
                ds.name AS didaskwn_name,
                ds.lastname AS didaskwn_lastname
            FROM prosklhsh p
            LEFT JOIN didaskwn ds ON p.didaskwn_id = ds.id
            WHERE p.diplwmatiki_id = :did
            ORDER BY p.datetime_created DESC
        ");
        $stmt->bindParam(':did', $selectedId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}


// --- NEW: AJAX endpoint — return shmeiwsh rows for a given diplwmatiki_id (JSON)
// Only return notes authored by the currently logged-in didaskwn (session id)
if (isset($_GET['action']) && $_GET['action'] === 'related_shmeiwseis' && isset($_GET['diplwmatiki_id'])) {
    $selectedId = (int)$_GET['diplwmatiki_id'];
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    // If no logged-in user id, return empty JSON
    if ($currentUserId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT s.id, s.didaskwn_id, s.content, s.datetime_created, d.name AS didaskwn_name, d.lastname AS didaskwn_lastname
            FROM shmeiwsh s
            LEFT JOIN didaskwn d ON s.didaskwn_id = d.id
            WHERE s.diplwmatiki_id = :did AND s.didaskwn_id = :didaskwn_id
            ORDER BY s.datetime_created DESC");
        $stmt->bindParam(':did', $selectedId, PDO::PARAM_INT);
        $stmt->bindParam(':didaskwn_id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// --- NEW: AJAX endpoint — insert a new shmeiwsh (note) for a given diplwmatiki_id
// Expects: POST with JSON body { shmeiwsh: 'text' } and diplwmatiki_id in GET
if (isset($_GET['action']) && $_GET['action'] === 'add_shmeiwsh' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['diplwmatiki_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $selectedId = (int)$_GET['diplwmatiki_id'];
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    if ($currentUserId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    // Read JSON body
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $content = isset($data['shmeiwsh']) ? trim($data['shmeiwsh']) : '';

    if ($content === '') {
        echo json_encode(['success' => false, 'error' => 'Empty content']);
        exit();
    }

    try {
        // Verify that the current user is related to this diplwmatiki (epivlepwn or melos1 or melos2)
        $chk = $conn->prepare("SELECT epivlepwn_id, melos1_id, melos2_id FROM diplwmatiki WHERE id = :id LIMIT 1");
        $chk->bindParam(':id', $selectedId, PDO::PARAM_INT);
        $chk->execute();
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Diplwmatiki not found']);
            exit();
        }

        $allowed = ((int)$row['epivlepwn_id'] === $currentUserId) || ((int)$row['melos1_id'] === $currentUserId) || ((int)$row['melos2_id'] === $currentUserId);
        if (!$allowed) {
            echo json_encode(['success' => false, 'error' => 'Not authorized to add notes for this diplwmatiki']);
            exit();
        }

        $ins = $conn->prepare("INSERT INTO shmeiwsh (diplwmatiki_id, didaskwn_id, content, datetime_created) VALUES (:did, :didaskwn, :content, NOW())");
        $ins->bindParam(':did', $selectedId, PDO::PARAM_INT);
        $ins->bindParam(':didaskwn', $currentUserId, PDO::PARAM_INT);
        $ins->bindParam(':content', $content, PDO::PARAM_STR);
        $ins->execute();

        echo json_encode(['success' => true, 'inserted_id' => $conn->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }

    exit();
}

// Handle cancellation of assignment: delete related prosklhsh rows for diplwmatiki
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_anathesi' && isset($_POST['diplwmatiki_id'])) {
    $did = (int)$_POST['diplwmatiki_id'];
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
    // Check ownership: only the epivlepwn (supervising professor) can cancel related invitations
    // Also fetch the title so we can release the corresponding thema
    $check = $conn->prepare("SELECT epivlepwn_id, title FROM diplwmatiki WHERE id = :did LIMIT 1");
        $check->bindParam(':did', $did, PDO::PARAM_INT);
        $check->execute();
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Diplwmatiki not found']);
            exit();
        }

        if ((int)$row['epivlepwn_id'] !== $currentUserId) {
            // Not authorized
            echo json_encode(['success' => false, 'error' => 'Μονο ο Επιβλέπων Καθηγητής μπορεί να ακυρώσει ανάθεση θέματος']);
            exit();
        }

        // Authorized — perform deletion and release the original thema (if any)
        try {
            $conn->beginTransaction();

            // If we have a title, update the corresponding themata row owned by this professor
            $updated = 0;
            $title = isset($row['title']) ? $row['title'] : null;
            if ($title) {
                $upd = $conn->prepare("UPDATE themata SET katastash = 'ΕΛΕΥΘΕΡΟ', foititis_id = NULL WHERE title = :title AND didaskwn_id = :didaskwn_id");
                $upd->bindParam(':title', $title, PDO::PARAM_STR);
                $upd->bindParam(':didaskwn_id', $currentUserId, PDO::PARAM_INT);
                $upd->execute();
                $updated = $upd->rowCount();
            }

            // Delete related invitations
            $del = $conn->prepare("DELETE FROM prosklhsh WHERE diplwmatiki_id = :did");
            $del->bindParam(':did', $did, PDO::PARAM_INT);
            $del->execute();
            $deleted = $del->rowCount();

            // Also delete the diplwmatiki row itself
            $delDipl = $conn->prepare("DELETE FROM diplwmatiki WHERE id = :did");
            $delDipl->bindParam(':did', $did, PDO::PARAM_INT);
            $delDipl->execute();
            $deleted_diplwmatiki = $delDipl->rowCount();

            $conn->commit();

            echo json_encode([
                'success' => true,
                'deleted_prosklhseis' => $deleted,
                'thema_released' => $updated,
                'deleted_diplwmatiki' => $deleted_diplwmatiki
            ]);
        } catch (PDOException $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit();
}

//ΠΡΟΣΚΛΗΣΕΙΣ

// AJAX endpoint: return active ("ΣΕ ΕΞΕΛΙΞΗ") invitations for the current user
if (isset($_GET['action']) && $_GET['action'] === 'refresh_energes_prosklhseis') {
    $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    try {
        $stmt = $conn->prepare("
            SELECT
                p.id AS id,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                f.phone AS foititis_phone,
                d.title AS diplwmatiki_title,
                p.type AS type,
                p.datetime_created AS datetime_created,
                p.katastash AS katastash
            FROM prosklhsh p
            LEFT JOIN foititis f ON p.foititis_id = f.id
            LEFT JOIN diplwmatiki d ON p.diplwmatiki_id = d.id
            WHERE p.didaskwn_id = :id AND p.katastash = 'ΣΕ ΕΞΕΛΙΞΗ'
            ORDER BY p.datetime_created DESC
            LIMIT 200
        ");
        $stmt->bindParam(':id', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
    }
    exit();
}

// NEW: AJAX request to return invitations history for the current user
if (isset($_GET['action']) && $_GET['action'] === 'refresh_prosklhseis_history') {
    try {
        $stmt = $conn->prepare("
            SELECT
                p.id AS id,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                f.phone AS foititis_phone,
                d.title AS diplwmatiki_title,
                p.type AS type,
                p.datetime_created AS datetime,
                p.datetime_answer AS datetime_answer,
                p.katastash AS katastash
            FROM
                prosklhsh p
            LEFT JOIN
                foititis f ON p.foititis_id = f.id
            LEFT JOIN
                diplwmatiki d ON p.diplwmatiki_id = d.id
            WHERE
                p.didaskwn_id = :id
            ORDER BY
                p.datetime_created DESC
        ");
        $stmt->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
        $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($history);
    } catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    }
    exit();
}

// Handle AJAX request for updating prosklhsh status (legacy simple accept/reject)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['prosklhsh_id'])
    && isset($_POST['action'])
    && in_array($_POST['action'], ['accept','reject'], true)
) {
    $prosklhsh_id = (int)$_POST['prosklhsh_id'];
    $action = $_POST['action'];

    // Debug: incoming request
    error_log("[prosklhsh-update] incoming id={$prosklhsh_id} action={$action} remote=" . ($_SERVER['REMOTE_ADDR'] ?? ''));

    // Determine the new katastash based on the action
    $new_katastash = $action === 'accept' ? 'ΕΓΚΡΙΘΗΚΕ' : 'ΑΠΟΡΡΙΦΘΗΚΕ';

    try {
        $stmt = $conn->prepare("
            UPDATE prosklhsh
            SET katastash = :katastash, datetime_answer = NOW()
            WHERE id = :id
        ");
        $stmt->bindParam(':katastash', $new_katastash, PDO::PARAM_STR);
        $stmt->bindParam(':id', $prosklhsh_id, PDO::PARAM_INT);
        $stmt->execute();

        // Debug: success
        error_log("[prosklhsh-update] success id={$prosklhsh_id} new_status={$new_katastash}");
        echo json_encode(['success' => true, 'id' => $prosklhsh_id, 'status' => $new_katastash]);
    } catch (PDOException $e) {
        error_log("[prosklhsh-update] error id={$prosklhsh_id} msg=" . $e->getMessage());
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }

    exit();
}

// NEW: AJAX endpoint — accept invitation and update diplwmatiki membership
// Expects: POST action=accept_prosklhsh&prosklhsh_id=ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_prosklhsh' && isset($_POST['prosklhsh_id'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)$_POST['prosklhsh_id'];
    $uid = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    if ($uid <= 0) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }

    try {
        $conn->beginTransaction();

        // Lock invitation row
        $ps = $conn->prepare("SELECT id, diplwmatiki_id, didaskwn_id, katastash FROM prosklhsh WHERE id = :id FOR UPDATE");
        $ps->bindParam(':id', $pid, PDO::PARAM_INT);
        $ps->execute();
        $p = $ps->fetch(PDO::FETCH_ASSOC);
        if (!$p) { $conn->rollBack(); echo json_encode(['success' => false, 'error' => 'Η πρόσκληση δεν βρέθηκε.']); exit(); }
        if ((int)$p['didaskwn_id'] !== $uid) { $conn->rollBack(); echo json_encode(['success' => false, 'error' => 'Μη εξουσιοδοτημένη ενέργεια.']); exit(); }
        if ($p['katastash'] !== 'ΣΕ ΕΞΕΛΙΞΗ') { $conn->rollBack(); echo json_encode(['success' => false, 'error' => 'Η πρόσκληση έχει ήδη απαντηθεί.']); exit(); }

        $did = (int)$p['diplwmatiki_id'];
        if ($did <= 0) { $conn->rollBack(); echo json_encode(['success' => false, 'error' => 'Άκυρη διπλωματική.']); exit(); }

        // Lock diplwmatiki row and decide role by first/second acceptance
        $ds = $conn->prepare("SELECT epivlepwn_id, melos1_id, melos2_id, katastash FROM diplwmatiki WHERE id = :did FOR UPDATE");
        $ds->bindParam(':did', $did, PDO::PARAM_INT);
        $ds->execute();
        $d = $ds->fetch(PDO::FETCH_ASSOC);
        if (!$d) { $conn->rollBack(); echo json_encode(['success' => false, 'error' => 'Η διπλωματική δεν βρέθηκε.']); exit(); }

        $m1 = isset($d['melos1_id']) ? (int)$d['melos1_id'] : 0;
        $m2 = isset($d['melos2_id']) ? (int)$d['melos2_id'] : 0;

        // If already assigned, just accept invite and continue
        $targetCol = null;
        if ($m1 === $uid || $m2 === $uid) {
            $targetCol = $m1 === $uid ? 'melos1_id' : 'melos2_id';
        } else if ($m1 <= 0) {
            $targetCol = 'melos1_id';
        } else if ($m2 <= 0) {
            $targetCol = 'melos2_id';
        } else {
            // committee full
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => 'Και οι δύο θέσεις μελών έχουν ήδη καλυφθεί.']);
            exit();
        }

        // 1) Update invitation to accepted
        $upP = $conn->prepare("UPDATE prosklhsh SET katastash = 'ΕΓΚΡΙΘΗΚΕ', datetime_answer = NOW() WHERE id = :id");
        $upP->bindParam(':id', $pid, PDO::PARAM_INT);
        $upP->execute();

        // 2) Assign member role on diplwmatiki if not already assigned to this user
        $shouldAssign = ($targetCol === 'melos1_id' && $m1 !== $uid) || ($targetCol === 'melos2_id' && $m2 !== $uid);
        if ($shouldAssign) {
            $sql = "UPDATE diplwmatiki SET {$targetCol} = :uid WHERE id = :did";
            $upD = $conn->prepare($sql);
            $upD->bindParam(':uid', $uid, PDO::PARAM_INT);
            $upD->bindParam(':did', $did, PDO::PARAM_INT);
            $upD->execute();
            // reflect assignment in local vars
            if ($targetCol === 'melos1_id') $m1 = $uid; else $m2 = $uid;
        }

        // 3) If both members present and status is ΥΠΟ ΑΝΑΘΕΣΗ, switch to ΕΝΕΡΓΗ
        $newStatus = null;
        if ($d['katastash'] === 'ΥΠΟ ΑΝΑΘΕΣΗ' && $m1 > 0 && $m2 > 0) {
            $newStatus = 'ΕΝΕΡΓΗ';
            $upS = $conn->prepare("UPDATE diplwmatiki SET katastash = 'ΕΝΕΡΓΗ' WHERE id = :did");
            $upS->bindParam(':did', $did, PDO::PARAM_INT);
            $upS->execute();
        }

        // 4) If both members are now set, cancel any remaining pending invitations for this thesis
        if ($m1 > 0 && $m2 > 0) {
            $cancel = $conn->prepare("UPDATE prosklhsh SET katastash = 'ΑΚΥΡΩΘΗΚΕ', datetime_answer = NOW() WHERE diplwmatiki_id = :did AND katastash = 'ΣΕ ΕΞΕΛΙΞΗ'");
            $cancel->bindParam(':did', $did, PDO::PARAM_INT);
            $cancel->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true, 'updated_role' => $targetCol, 'diplwmatiki_id' => $did, 'new_katastash' => $newStatus]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit();
}

// NEW: AJAX endpoint — reject invitation (no diplwmatiki changes)
// Expects: POST action=reject_prosklhsh&prosklhsh_id=ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_prosklhsh' && isset($_POST['prosklhsh_id'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)$_POST['prosklhsh_id'];
    $uid = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    if ($uid <= 0) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }
    try {
        $ps = $conn->prepare("SELECT id, didaskwn_id, katastash FROM prosklhsh WHERE id = :id");
        $ps->bindParam(':id', $pid, PDO::PARAM_INT);
        $ps->execute();
        $p = $ps->fetch(PDO::FETCH_ASSOC);
        if (!$p) { echo json_encode(['success' => false, 'error' => 'Η πρόσκληση δεν βρέθηκε.']); exit(); }
        if ((int)$p['didaskwn_id'] !== $uid) { echo json_encode(['success' => false, 'error' => 'Μη εξουσιοδοτημένη ενέργεια.']); exit(); }
        if ($p['katastash'] !== 'ΣΕ ΕΞΕΛΙΞΗ') { echo json_encode(['success' => false, 'error' => 'Η πρόσκληση έχει ήδη απαντηθεί.']); exit(); }

        $up = $conn->prepare("UPDATE prosklhsh SET katastash = 'ΑΠΟΡΡΙΦΘΗΚΕ', datetime_answer = NOW() WHERE id = :id");
        $up->bindParam(':id', $pid, PDO::PARAM_INT);
        $up->execute();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit();
}


//ΣΤΑΤΙΣΤΙΚΑ

// --- NEW: AJAX endpoint — average completion duration grouped by finish year
if (isset($_GET['action']) && $_GET['action'] === 'avg_duration_by_year') {
    try {
        // Clear any accidental output so JSON is not corrupted (warnings/html printed earlier)
        while (ob_get_level()) ob_end_clean();
                // Only include finished diplwmatikes where the current user is supervisor or a committee member
                $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
                if ($currentUserId <= 0) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'data' => []]);
                        exit();
                }
        // diplwmatiki_FINISHED has start_date and end_date (dates)
                        $stmt = $conn->prepare(
                                "SELECT 
                                        YEAR(end_date) AS year,
                                        AVG(CASE WHEN d.epivlepwn_id = :uid THEN DATEDIFF(end_date, start_date) END) AS avg_days_epivlepwn,
                                        AVG(CASE WHEN (d.melos1_id = :uid OR d.melos2_id = :uid) THEN DATEDIFF(end_date, start_date) END) AS avg_days_melos,
                                        COUNT(CASE WHEN d.epivlepwn_id = :uid THEN 1 END) AS count_epivlepwn,
                                        COUNT(CASE WHEN (d.melos1_id = :uid OR d.melos2_id = :uid) THEN 1 END) AS count_melos
                                 FROM diplwmatiki_FINISHED d
                                 WHERE end_date IS NOT NULL AND start_date IS NOT NULL
                                     AND (d.epivlepwn_id = :uid OR d.melos1_id = :uid OR d.melos2_id = :uid)
                                 GROUP BY YEAR(end_date)
                                 ORDER BY YEAR(end_date) ASC"
                        );
                        $stmt->bindParam(':uid', $currentUserId, PDO::PARAM_INT);
                        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        // Normalize numeric values: convert empty strings or nulls to null/float
                        foreach ($rows as &$r) {
                                $r['avg_days_epivlepwn'] = isset($r['avg_days_epivlepwn']) && $r['avg_days_epivlepwn'] !== null ? (float)$r['avg_days_epivlepwn'] : null;
                                $r['avg_days_melos'] = isset($r['avg_days_melos']) && $r['avg_days_melos'] !== null ? (float)$r['avg_days_melos'] : null;
                                $r['count_epivlepwn'] = isset($r['count_epivlepwn']) ? (int)$r['count_epivlepwn'] : 0;
                                $r['count_melos'] = isset($r['count_melos']) ? (int)$r['count_melos'] : 0;
                        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        // ensure clean output and return structured JSON error
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// --- NEW: AJAX endpoint — average final grade grouped by finish year
if (isset($_GET['action']) && $_GET['action'] === 'avg_grade_by_year') {
    try {
        // ensure clean output
        while (ob_get_level()) ob_end_clean();
        $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        if ($currentUserId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'data' => []]);
            exit();
        }

        $stmt = $conn->prepare(
            "SELECT 
                YEAR(end_date) AS year,
                AVG(CASE WHEN d.epivlepwn_id = :uid THEN d.bathmos END) AS avg_grade_epivlepwn,
                AVG(CASE WHEN (d.melos1_id = :uid OR d.melos2_id = :uid) THEN d.bathmos END) AS avg_grade_melos,
                COUNT(CASE WHEN d.epivlepwn_id = :uid THEN 1 END) AS count_epivlepwn,
                COUNT(CASE WHEN (d.melos1_id = :uid OR d.melos2_id = :uid) THEN 1 END) AS count_melos
            FROM diplwmatiki_FINISHED d
            WHERE d.end_date IS NOT NULL AND d.bathmos IS NOT NULL
                AND (d.epivlepwn_id = :uid OR d.melos1_id = :uid OR d.melos2_id = :uid)
            GROUP BY YEAR(end_date)
            ORDER BY YEAR(end_date) ASC"
        );
        $stmt->bindParam(':uid', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['avg_grade_epivlepwn'] = isset($r['avg_grade_epivlepwn']) && $r['avg_grade_epivlepwn'] !== null ? (float)$r['avg_grade_epivlepwn'] : null;
            $r['avg_grade_melos'] = isset($r['avg_grade_melos']) && $r['avg_grade_melos'] !== null ? (float)$r['avg_grade_melos'] : null;
            $r['count_epivlepwn'] = isset($r['count_epivlepwn']) ? (int)$r['count_epivlepwn'] : 0;
            $r['count_melos'] = isset($r['count_melos']) ? (int)$r['count_melos'] : 0;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// --- NEW: AJAX endpoint — count of diplwmatikes per finish year (as epivlepwn / as melos)
if (isset($_GET['action']) && $_GET['action'] === 'count_diplwmatikes_by_year') {
    try {
        while (ob_get_level()) ob_end_clean();
        $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        if ($currentUserId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'data' => []]);
            exit();
        }

        $stmt = $conn->prepare(
            "SELECT 
                YEAR(end_date) AS year,
                SUM(CASE WHEN d.epivlepwn_id = :uid THEN 1 ELSE 0 END) AS count_epivlepwn,
                SUM(CASE WHEN (d.melos1_id = :uid OR d.melos2_id = :uid) THEN 1 ELSE 0 END) AS count_melos
            FROM diplwmatiki_FINISHED d
            WHERE d.end_date IS NOT NULL
                AND (d.epivlepwn_id = :uid OR d.melos1_id = :uid OR d.melos2_id = :uid)
            GROUP BY YEAR(end_date)
            ORDER BY YEAR(end_date) ASC"
        );
        $stmt->bindParam(':uid', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['count_epivlepwn'] = isset($r['count_epivlepwn']) ? (int)$r['count_epivlepwn'] : 0;
            $r['count_melos'] = isset($r['count_melos']) ? (int)$r['count_melos'] : 0;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page</title>
    <link rel="stylesheet" href="../public/css/homepage_style_d.css">
</head>
<body>
    <header class="social-header">
        <div class="header-left">
            <img src="../public/icons/uop_logo.png" alt="University Logo" class="header-icon">
            <span class="header-title">Πανεπιστήμιο Πατρών</span>
        </div>
        <div class="header-right">
            <a href="#">
                <img src="../public/icons/user.svg" alt="User Icon" class="header-icon">
                <span class="header-user"><?= $name . ' ' . $lastname; ?></span>
            </a>
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?logout=true">
                <img src="../public/icons/exit.svg" alt="Exit Icon" class="header-icon">
                <span class="header-user">Αποσύνδεση</span>
            </a>
        </div>
    </header>

    <?php
    if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }
    ?>

    <div class="sidenav">
        <button class="nav-button active" onclick="setActive(this);loadContent('home')">
            <img src="../public/icons/home.svg" alt="Home Icon" class="icon"> Αρχική
        </button>
        <button class="nav-button" onclick="setActive(this);loadContent('themata')">
            <img src="../public/icons/pencil.svg" alt="Pencil Icon" class="icon"> Θέματα
        </button>
        <button class="nav-button" onclick="setActive(this);loadContent('diplwmatikes')">
            <img src="../public/icons/document.svg" alt="Document Icon" class="icon"> Διπλωματικές
        </button>
        <button class="nav-button" onclick="setActive(this);loadContent('proskliseis')">
            <img src="../public/icons/envelope.svg" alt="Envelope Icon" class="icon"> Προσκλήσεις
        </button>
        <button class="nav-button" onclick="setActive(this);loadContent('statistika')">
            <img src="../public/icons/chat-arrow-grow.svg" alt="Chart Icon" class="icon"> Στατιστικά
        </button>
    </div>

    <div id="content-container">
        <div id="home" class="content visible">
            <h1 id="titlos">Καλωσορίσατε <span style="float:right;">Συνδεθήκατε με λογιαριασμό Καθηγητή</span></h1>
            <h1>Νέες Προσκλήσεις</h1>
            <table>
                <thead>
                    <tr>
                        <th>Όνομα</th>
                        <th>Επώνυμο</th>
                        <th>Τηλέφωνο</th>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Τύπος Πρόσκλησης</th>
                        <th>Ημερομηνία</th>
                    </tr>
                </thead>
                <tbody id="new-prosklhseis-body">
                    <!-- Populated by AJAX: GET homepage_d.php?action=refresh_new_prosklhseis -->
                    <tr><td colspan="6">Φόρτωση...</td></tr>
                </tbody>
            </table>
            <h1>Ενεργές Διπλωματικές Υπό Επίβλεψη</h1>
            <table>
                <thead>
                    <tr>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Όνομα Φοιτητή</th>
                        <th>Επώνυμο Φοιτητή</th>
                        <th>Ημερομηνία Έναρξης</th>
                        <th>Κατασταση</th>
                    </tr>
                </thead>
                <tbody id="active-diplwmatikes-body">
                    <!-- Populated by AJAX: GET homepage_d.php?action=refresh_active_diplwmatikes -->
                    <tr><td colspan="5">Φόρτωση...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="themata" class="content hidden">
            <div class="lista-thematwn">    
            <h1>Λίστα Θεμάτων</h1>
            <table>
                <thead>
                    <tr>
                        <th>Τίτλος Θέματος</th>
                        <th>Περιγραφή</th>
                        <th>Αρχείο PDF</th>
                        <th>Κατάσταση</th>
                        <th>Φοιτητής</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Table body populated via AJAX (refresh_lista_thematwn) -->
                    <tr><td colspan="5">Φόρτωση...</td></tr>
                </tbody>
            </table>
            </div>

            <div class="dhmiourgia-thematos">
                <h1 class="titlos">Δημιουργία Θέματος</h1>
                <form id="create-thema-form" method="POST" action="" enctype="multipart/form-data">
                    <label for="title">Τίτλος Θέματος:</label>
                    <input type="text" id="title" name="title" required>
                    
                    <label for="description">Περιγραφή:</label>
                    <textarea id="description" name="description" required></textarea>
                    
                    <label for="pdf">Αρχείο PDF:</label>
                    <div style="margin-top: 10px;">
                        <input type="file" id="pdf" name="pdf" accept=".pdf" style="display: none;">
                        <button type="button" class="upload-button" onclick="document.getElementById('pdf').click();">Επιλογή Αρχείου</button>
                        <span id="file-name" style="margin-left: 10px; font-weight: bold; color: var(--university-dark-red);"></span>
                    </div>
                    
                    <button type="submit" name="create_thema">Δημιουργία</button>
                </form>
            </div>

            <div class="anathesi-thematos">
                <h1 class="titlos">Ανάθεση Θέματος</h1>
                <form id="assign-thema-form" method="POST" action="">
                    <div class="search-container">
                        <label for="search-foititis">Αναζήτηση Φοιτητή:</label>
                        <input type="text" id="search-foititis" name="search-foititis" placeholder="Όνομα, Επώνυμο ή ID" required>
                        <button type="button" id="search-foititis-button">Αναζήτηση</button>
                    </div>
                    
                    <div id="foititis-results">
                        <!-- apotelesmeta edw -->
                    </div>
                    <!-- Hidden field to store the selected student id for assignment -->
                    <input type="hidden" id="foititis-id" name="foititis-id" />
                    <!-- Optional: a small area to show which student is selected -->
                    <div id="foititis-selected" style="margin: 8px 0; color: var(--university-dark-red);"></div>
                    
                    <div class="dropdown-container">
                        <label for="thema-id">Επιλογή Θέματος:</label>
                        <select id="thema-id" name="thema-id" required>
                            <option value="">Φόρτωση...</option>
                        </select>
                        <button type="submit" name="assign_thema">Ανάθεση</button>
                    </div>
                </form>
            </div>

            <div id="edit-thema" class="edit-thema" style="display: none;">
                <h1><button class="back-button" id="back-to-themata"><</button> Επεξεργασία Θέματος</h1>
                <form id="edit-thema-form" method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" id="edit-thema-id" name="edit_thema_id">
                    
                    <label for="edit-title">Τίτλος Θέματος:</label>
                    <input type="text" id="edit-title" name="edit_title" required>
                    
                    <label for="edit-description">Περιγραφή:</label>
                    <textarea id="edit-description" name="edit_description" required></textarea>
                    
                    <label for="edit-pdf">Αρχείο PDF:</label>
                    <div style="margin-top: 10px;">
                        <input type="file" id="edit-pdf" name="edit_pdf" accept=".pdf" style="display: none;">
                        <button type="button" class="upload-button" onclick="document.getElementById('edit-pdf').click();">Επιλογή Αρχείου</button>
                        <span id="edit-file-name" style="margin-left: 10px; font-weight: bold; color: var(--university-dark-red);"></span>
                    </div>
                    
                    <label for="edit-katastash">Κατάσταση:</label>
                    <select id="edit-katastash" name="edit_katastash" required>
                        <option value="ΕΛΕΥΘΕΡΟ">ΕΛΕΥΘΕΡΟ</option>
                        <option value="ΔΕΣΜΕΥΜΕΝΟ">ΔΕΣΜΕΥΜΕΝΟ</option>
                    </select>
                    
                    <button type="submit" name="edit_thema">Αποθήκευση Αλλαγών</button>
                </form>
            </div>
        </div>

        <div id="diplwmatikes" class="content hidden">
        <div class="diplwmatikes-tables">
            <h1 class="titlos">Διπλωματικές Υπό Επίβλεψη</h1>
            <label for="filter-katastash">Φίλτρο:</label>
            <select class="filter-dropdown" id="filter-katastash-1" data-filter="table-1">
                <option value="all">ΟΛΕΣ</option>
                <option value="ΕΝΕΡΓΗ">ΕΝΕΡΓΗ</option>
                <option value="ΥΠΟ ΕΞΕΤΑΣΗ">ΥΠΟ ΕΞΕΤΑΣΗ</option>
                <option value="ΥΠΟ ΑΝΑΘΕΣΗ">ΥΠΟ ΑΝΑΘΕΣΗ</option>
                <option value="ΑΚΥΡΩΜΕΝΗ">ΑΚΥΡΩΜΕΝΗ</option>
            </select>
            <table id="table-1">
                <thead>
                    <tr>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Όνομα Φοιτητή</th>
                        <th>Επώνυμο Φοιτητή</th>
                        <th>1ο Μέλος</th>
                        <th>2ο Μέλος</th>
                        <th>Ημερομηνία Έναρξης</th>
                        <th>Κατασταση</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by AJAX: GET homepage_d.php?action=refresh_all_diplwmatikes -->
                </tbody>
            </table>
            <h1 class="titlos">Διπλωματικές στις οποίες συμμετέχετε ως 1ο Μέλος</h1>
            <label for="filter-katastash">Φίλτρο:</label>
            <select class="filter-dropdown" id="filter-katastash-2" data-filter="table-2">
                <option value="all">ΟΛΕΣ</option>
                <option value="ΕΝΕΡΓΗ">ΕΝΕΡΓΗ</option>
                <option value="ΥΠΟ ΕΞΕΤΑΣΗ">ΥΠΟ ΕΞΕΤΑΣΗ</option>
                <option value="ΥΠΟ ΑΝΑΘΕΣΗ">ΥΠΟ ΑΝΑΘΕΣΗ</option>
                <option value="ΑΚΥΡΩΜΕΝΗ">ΑΚΥΡΩΜΕΝΗ</option>
            </select>
            <table id="table-2">
                <thead>
                    <tr>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Όνομα Φοιτητή</th>
                        <th>Επώνυμο Φοιτητή</th>
                        <th>Επιβλέπων</th>
                        <th>2ο Μέλος</th>
                        <th>Ημερομηνία Έναρξης</th>
                        <th>Κατασταση</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by AJAX: GET homepage_d.php?action=refresh_melos1_diplwmatikes -->
                </tbody>
            </table>
            <h1 class="titlos">Διπλωματικές στις οποίες συμμετέχετε ως 2ο Μέλος</h1>
            <label for="filter-katastash">Φίλτρο:</label>
            <select class="filter-dropdown" id="filter-katastash-3" data-filter="table-3">
                <option value="all">ΟΛΕΣ</option>
                <option value="ΕΝΕΡΓΗ">ΕΝΕΡΓΗ</option>
                <option value="ΥΠΟ ΕΞΕΤΑΣΗ">ΥΠΟ ΕΞΕΤΑΣΗ</option>
                <option value="ΥΠΟ ΑΝΑΘΕΣΗ">ΥΠΟ ΑΝΑΘΕΣΗ</option>
                <option value="ΑΚΥΡΩΜΕΝΗ">ΑΚΥΡΩΜΕΝΗ</option>
            </select>
            <table id="table-3">
                <thead>
                    <tr>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Όνομα Φοιτητή</th>
                        <th>Επώνυμο Φοιτητή</th>
                        <th>Επιβλέπων</th>
                        <th>1ο Μέλος</th>
                        <th>Ημερομηνία Έναρξης</th>
                        <th>Κατασταση</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by AJAX: GET homepage_d.php?action=refresh_melos2_diplwmatikes -->
                </tbody>
            </table>
            <h1 class="titlos">Ιστορικό Περατωμένων Διπλωματικών</h1>
            <table id="table-4">
                <thead>
                    <tr>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Όνομα Φοιτητή</th>
                        <th>Επώνυμο Φοιτητή</th>
                        <th>Επιβλέπων</th>
                        <th>1ο Μέλος</th>
                        <th>2ο Μέλος</th>
                        <th>Βαθμός</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by AJAX: GET homepage_d.php?action=refresh_old_diplwmatikes -->
                </tbody>
            </table>    
            
            <div>
                <h1 class="titlos">Εξαγωγή Λίστας Διπλωματικών</h1>
                <div class="export-controls">
                    <label for="export-format">Μορφή εξαγωγής:</label>
                    <select id="export-format" name="export-format">
                        <option value="json">JSON</option>
                        <option value="csv">CSV</option>
                    </select>
                    <button type="button" id="export-download-btn" class="back-button">Λήψη</button>
                </div>
            </div>

        </div>

        
        <div class="anaskopisi-diplwmatikis" style="display:none;"> 
        <h1><button class="back-button" id="back-button"><</button> Ανασκόπηση Διπλωματικής</h1>
         <div class="diplwmatiki-card">
                <p><strong>Τίτλος:</strong> <span id="detail-title"> </span></p>
                <p><strong>Ονοματεπώνυμο Φοιτητή:</strong> <span id="detail-foititis-name"> </span> <span id="detail-foititis-lastname"></span></p>
                <p><strong>Επιβλέπων Καθηγητής:</strong> <span id="detail-epivlepwn-name"></span> <span id="detail-epivlepwn-lastname"></span></p>
                <p><strong>1ο Μέλος Τριμελούς Επιτροπής:</strong> <span id="detail-melos1-name"></span> <span id="detail-melos1-lastname"></span></p>
                <p><strong>2ο Μέλος Τριμελούς Επιτροπής:</strong> <span id="detail-melos2-name"></span> <span id="detail-melos2-lastname"></span></p>
                <p><strong>Ημερομηνία Έναρξης:</strong> <span id="detail-start-date"></span></p>
                <p><strong>Κατάσταση:</strong> <span id="detail-katastash"></span></p>
                <p><strong>Ημερομηνία Περάτωσης:</strong> <span id="detail-end-date"></span></p>
                <p><strong>Βαθμός Επιβλέποντος:</strong> <span id="detail-bathmos-epivlepwn"></span></p>
                <p><strong>Βαθμός 1ου Μέλους:</strong> <span id="detail-bathmos-melos1"></span></p>
                <p><strong>Βαθμός 2ου Μέλους:</strong> <span id="detail-bathmos-melos2"></span></p>
                <p><strong>Τελικός Βαθμός:</strong> <span id="detail-bathmos"></span></p>
                <p><strong>Σύνδεσμος Αποθετηρίου: </strong> <span id="detail-link"></span></p>
                <p><strong>Πρακτικό Αξιολόγησης: </strong> <span id="detail-praktiko"></span></p>
         </div>
                <div class="ypo-anathesi-diplwmatiki" style="display:none;">
                    <h1 class="titlos">Διαχείρηση Διπλωματικής</h1>
                        <button type="button" style="margin-bottom:10px" id="cancel-anathesi-btn" class="back-button cancel-anathesi-button">Ακύρωση Αναθέσης</button>
                <h1 class="titlos">Προσκλήσεις Διπλωματικής</h1>
            <table id="ypo-prosklhseis-table">
                <thead>
                    <tr>
                        <th>Καθηγητής</th>
                        <th>Τύπος</th>
                        <th>Ημ. Δημιουργίας</th>
                        <th>Ημ. Απάντησης</th>
                        <th>Κατάσταση</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- js  -->
                </tbody>
            </table>
         </div>

                <div class="energi-diplwmatiki" style="display:none;">
                    <h1 class="titlos">Διαχείρηση Διπλωματικής</h1>
                    <button type="button" style="margin-bottom:10px" id="cancel-diplwmatiki-btn" class="back-button cancel-diplwmatiki-button">Ακύρωση Διπλωματικής</button>
                    <button type="button" style="margin-bottom:10px" id="eksetasi-diplwmatiki-btn" class="back-button eksetasi-button">Μετατροπή Διπλωματικής σε Διπλωματική Υπο Εξέταση</button>
                    <h1 class="titlos">Σημειώσεις Διπλωματικής</h1>
                    <div class="shmeiwseis-actions" style="margin-bottom:10px;">
                        <textarea id="new-shmeiwsh-text" class="input-like" placeholder="Πληκτρολογήστε νέα σημείωση..." rows="3" style="width:100%;"></textarea>
                        <button type="button" id="new-shmeiwsh-btn" class="back-button" style="margin-top:6px;">Νέα Σημείωση</button>
                    </div>
                    <table id="shmeiwseis-table">
                        <thead>
                            <tr>
                                <th>Ημερομηνία</th>
                                <th>Περιεχόμενο</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- js -->
                        </tbody>
                    </table>
                </div> <!-- end .energi-diplwmatiki -->

                <div class="ypo-eksetasi-diplwmatiki" style="display:none;">
                    <h1 class="titlos">Διαχείρηση Διπλωματικής</h1>
                    <button type="button" style="margin-bottom:10px" id="energopoihsh-bathmou-btn" class="back-button" style="display:none;">Ενεργοποίηση Καταχώρησης Βαθμού</button>

                    <div class="keimeno-diplwmatikis">
                        <h1 class="titlos">Κείμενο Διπλωματικής Εργασίας</h1>
                        <button type="button" style="margin-bottom:10px" id="katebasma-proxeirou" class="back-button" style="display:none;">Λήψη Πρόχειρου</button>
                    </div>
                    
                    <div class="anathesi-bathmou" style="display:none;">
                        <h1 class="titlos">Ανάθεση Βαθμού</h1>
                        <div class="bathmos-inputs">
                            <label>
                                Βαθμός Έργου
                                <input type="number" id="bathmos-1" name="bathmos-1" min="0" max="10" step="0.1" placeholder="0 - 10">
                            </label>
                            <label>
                                Βαθμός Συγγράμματος
                                <input type="number" id="bathmos-2" name="bathmos-2" min="0" max="10" step="0.1" placeholder="0 - 10">
                            </label>
                            <label>
                                Βαθμός Παρουσίασης
                                <input type="number" id="bathmos-3" name="bathmos-3" min="0" max="10" step="0.1" placeholder="0 - 10">
                            </label>
                            <button type="button" id="submit-bathmoi-btn" class="back-button">Καταχώρηση Βαθμών</button>
                        </div>

                    </div>
                </div> <!-- end .ypo-eksetasi-diplwmatiki -->
            </div> <!-- end .anaskopisi-diplwmatikis -->
        </div> <!-- end #diplwmatikes -->
 


      <div id="proskliseis" class="content hidden">
        <h1 class="titlos">Προσκλήσεις σε Εξέλιξη</h1>
        <table>
                <thead>
                    <tr>
                        <th>Όνομα</th>
                        <th>Επώνυμο</th>
                        <th>Τηλέφωνο</th>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Τύπος Πρόσκλησης</th>
                        <th>Ημερομηνία</th>
                        <th>Ενέργειες</th>
                    </tr>
                </thead>
                <tbody id="prosklhseis-active-body">
                    <!-- Populated by AJAX: GET homepage_d.php?action=refresh_energes_prosklhseis -->
                    <tr><td colspan="7">Φόρτωση...</td></tr>
                </tbody>
            </table>    

        <h1 class="titlos">Ιστορικό Προσκλήσεων</h1>
        <table id="prosklhseis-history-table">
            <thead>
                <tr>
                    <th>Όνομα</th>
                    <th>Επώνυμο</th>
                    <th>Τηλέφωνο</th>
                    <th>Τίτλος Διπλωματικής</th>
                    <th>Τύπος Πρόσκλησης</th>
                    <th>Ημερομηνία Δημιουργίας</th>
                    <th>Ημερομηνία Απάντησης</th>
                    <th>Κατάσταση</th>
                </tr>
            </thead>
            <tbody id="prosklhseis-history-body">
                <!-- Populated by AJAX: GET homepage_d.php?action=refresh_prosklhseis_history -->
                <tr><td colspan="8">Φόρτωση...</td></tr>
            </tbody>
        </table>    
      </div>

            <div id="statistika" class="content hidden">
                <h1 class="titlos">Στατιστικά</h1>
                <h1 class="titlos">Μέσος Χρόνος Περάτωσης Διπλωματικών</h1>
                <div class="chart-wrapper">
                    <canvas id="avg-duration-chart" width="800" height="400">Στατιστικά διάγραμμα</canvas>
                </div>
                <h1 class="titlos">Μέσος Βαθμός Διπλωματικών</h1>
                <div class="chart-wrapper">
                    <canvas id="avg-grade-chart" width="800" height="400">Μέσος Βαθμός Διπλωματικών</canvas>
                </div>
                <h1 class="titlos">Πλήθος Διπλωματικών</h1>
                <div class="chart-wrapper">
                    <canvas id="total-thesis-chart" width="800" height="400">Πλήθος Διπλωματικών</canvas>
                </div>
            </div>
    </div>
    </div>

        <script src="../node_modules/chart.js/dist/chart.umd.min.js"></script>
        <script src="../public/js/script_d.js"></script>
</body>
</html>

