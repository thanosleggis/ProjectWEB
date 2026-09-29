<?php
session_start();
include 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

// Get user details
$user = $_SESSION['user'];
$name = htmlspecialchars($user['name']);
$lastname = htmlspecialchars($user['lastname']);
$user_type = htmlspecialchars($_SESSION['user_type']);

// AJAX endpoints for grammateia page
// JSON import endpoint: POST JSON to update foititis and didaskwn personal details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'import_personal_json') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    $results = ['inserted_foititis' => 0, 'inserted_didaskwn' => 0, 'errors' => []];

    // Helper to insert new records with flexible fields. If an email or id already exists, returns an error and does not update.
    $insertRecord = function($table, $record, $allowedFields) use ($conn, &$results) {
        // Prepare columns and params
        $cols = [];
        $placeholders = [];
        $params = [];

        // collect allowed fields present in record
        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $record)) {
                // password is handled specially (hashing) below
                if ($f === 'password') continue;
                $cols[] = $f;
                $placeholders[] = ':' . $f;
                $params[':' . $f] = $record[$f];
            }
        }

        // require password field in record
        if (!isset($record['password']) || $record['password'] === '') {
            return ['ok' => false, 'error' => 'missing_password'];
        }

        if (count($cols) === 0) return ['ok' => false, 'error' => 'no insertable fields'];

    // If email provided, ensure it doesn't already exist
        if (isset($record['email']) && $record['email'] !== '') {
            try {
                $chk = $conn->prepare("SELECT id FROM $table WHERE email = :email LIMIT 1");
                $chk->execute([':email' => $record['email']]);
                $exists = $chk->fetch(PDO::FETCH_ASSOC);
                if ($exists) return ['ok' => false, 'error' => 'exists', 'existing_id' => $exists['id']];
            } catch (PDOException $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        // If id provided, ensure it doesn't already exist
        if (isset($record['id']) && $record['id'] !== '') {
            try {
                $chk = $conn->prepare("SELECT id FROM $table WHERE id = :id LIMIT 1");
                $chk->execute([':id' => $record['id']]);
                $exists = $chk->fetch(PDO::FETCH_ASSOC);
                if ($exists) return ['ok' => false, 'error' => 'exists', 'existing_id' => $exists['id']];
                // If id is intended to be inserted, include it
                $cols[] = 'id';
                $placeholders[] = ':id';
                $params[':id'] = $record['id'];
            } catch (PDOException $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

    // handle password hashing and include it in insert
    $hashed = password_hash($record['password'], PASSWORD_DEFAULT);
    $cols[] = 'password';
    $placeholders[] = ':password';
    $params[':password'] = $hashed;

        $sql = "INSERT INTO $table (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        try {
            $st = $conn->prepare($sql);
            $st->execute($params);
            $insertedId = $conn->lastInsertId();
            return ['ok' => true, 'inserted_id' => $insertedId, 'affected' => $st->rowCount()];
        } catch (PDOException $e) {
            // if duplicate entry or other constraint, return error
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    };

    // Process foititis (insert new records)
    if (isset($data['foititis']) && is_array($data['foititis'])) {
        foreach ($data['foititis'] as $rec) {
            if (!is_array($rec)) continue;
            $res = $insertRecord('foititis', $rec, ['name','lastname','address','phone','landline','email','password']);
            if ($res['ok']) $results['inserted_foititis'] += 1;
            else $results['errors'][] = ['table' => 'foititis', 'record' => $rec, 'error' => $res['error']];
        }
    }

    // Process didaskwn (insert new records)
    if (isset($data['didaskwn']) && is_array($data['didaskwn'])) {
        foreach ($data['didaskwn'] as $rec) {
            if (!is_array($rec)) continue;
            $res = $insertRecord('didaskwn', $rec, ['name','lastname','phone','email','password']);
            if ($res['ok']) $results['inserted_didaskwn'] += 1;
            else $results['errors'][] = ['table' => 'didaskwn', 'record' => $rec, 'error' => $res['error']];
        }
    }

    $results['success'] = count($results['errors']) === 0;
    echo json_encode($results);
    exit();
}
// AJAX endpoint: approve diplwmatiki by setting arithmos_anathesis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'approve_diplwmatiki') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    $id = isset($data['diplwmatiki_id']) ? $data['diplwmatiki_id'] : null;
    $arith = isset($data['arithmos_anathesis']) ? $data['arithmos_anathesis'] : (isset($data['arithmos']) ? $data['arithmos'] : null);
    if (empty($id) || $arith === null || $arith === '') {
        echo json_encode(['success' => false, 'error' => 'missing_parameters']);
        exit();
    }
    // basic validation: numeric
    if (!is_numeric($arith)) {
        echo json_encode(['success' => false, 'error' => 'invalid_arithmos']);
        exit();
    }
    try {
        $upd = $conn->prepare("UPDATE diplwmatiki SET arithmos_anathesis = :arith WHERE id = :id LIMIT 1");
        $upd->execute([':arith' => $arith, ':id' => $id]);
        $affected = $upd->rowCount();
        echo json_encode(['success' => true, 'affected' => $affected]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
// AJAX endpoint: cancel diplwmatiki by setting katastash, arithmos_akyrwshs and etos_akyrwshs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'cancel_diplwmatiki') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    $id = isset($data['diplwmatiki_id']) ? $data['diplwmatiki_id'] : null;
    $arith = isset($data['arithmos_akyrwshs']) ? $data['arithmos_akyrwshs'] : (isset($data['arithmos']) ? $data['arithmos'] : null);
    $etos = isset($data['etos_akyrwshs']) ? $data['etos_akyrwshs'] : (isset($data['etos']) ? $data['etos'] : null);
    if (empty($id) || $arith === null || $arith === '' || $etos === null || $etos === '') {
        echo json_encode(['success' => false, 'error' => 'missing_parameters']);
        exit();
    }
    // basic validation: numeric
    if (!is_numeric($arith) || !is_numeric($etos)) {
        echo json_encode(['success' => false, 'error' => 'invalid_parameters']);
        exit();
    }
    try {
        $upd = $conn->prepare("UPDATE diplwmatiki SET katastash = :kat, arithmos_akyrwshs = :arith, etos_akyrwshs = :etos WHERE id = :id LIMIT 1");
        $upd->execute([':kat' => 'ΑΚΥΡΩΜΕΝΗ', ':arith' => $arith, ':etos' => $etos, ':id' => $id]);
        $affected = $upd->rowCount();
        echo json_encode(['success' => true, 'affected' => $affected]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// AJAX endpoint: finish diplwmatiki -> validate grades and nhmerths_link, move to diplwmatiki_FINISHED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'finish_diplwmatiki') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    $id = isset($data['diplwmatiki_id']) ? $data['diplwmatiki_id'] : null;

    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'missing_id']);
        exit();
    }

    try {
        // fetch the existing diplwmatiki row first
        $st = $conn->prepare("SELECT * FROM diplwmatiki WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'not_found']);
            exit();
        }

        // read grades and link from DB row
        $b_ep = isset($row['bathmos_epivlepwn']) ? $row['bathmos_epivlepwn'] : null;
        $b_m1 = isset($row['bathmos_melos1']) ? $row['bathmos_melos1'] : null;
        $b_m2 = isset($row['bathmos_melos2']) ? $row['bathmos_melos2'] : null;
        $link = isset($row['nhmerths_link']) ? trim($row['nhmerths_link']) : '';

        // require that the DB already contains the three grades and the link
        if ($b_ep === null || $b_m1 === null || $b_m2 === null || $link === '') {
            echo json_encode(['success' => false, 'error' => 'missing_db_fields']);
            exit();
        }

        // numeric validation and range (0 < grade <= 10)
        if (!is_numeric($b_ep) || !is_numeric($b_m1) || !is_numeric($b_m2)) {
            echo json_encode(['success' => false, 'error' => 'invalid_grades']);
            exit();
        }
        $b_ep = floatval($b_ep);
        $b_m1 = floatval($b_m1);
        $b_m2 = floatval($b_m2);
        foreach ([$b_ep, $b_m1, $b_m2] as $g) {
            if ($g <= 0 || $g > 10) {
                echo json_encode(['success' => false, 'error' => 'grade_out_of_range']);
                exit();
            }
        }
        // basic link validation length
        if (strlen($link) > 255) {
            echo json_encode(['success' => false, 'error' => 'link_too_long']);
            exit();
        }

        // compute final grade
        $final = round((($b_ep + $b_m1 + $b_m2) / 3.0), 2);

        // begin transaction and move row
        $conn->beginTransaction();

        $now = date('Y-m-d');

        // insert into finished table
        $ins = $conn->prepare("INSERT INTO diplwmatiki_FINISHED (diplwmatiki_id, title, foititis_id, epivlepwn_id, melos1_id, melos2_id, bathmos, nhmerths_link, praktiko, start_date, end_date) VALUES (:did, :title, :foit, :epiv, :m1, :m2, :bathmos, :link, :praktiko, :start, :end)");
        $ins->execute([
            ':did' => $row['id'],
            ':title' => $row['title'],
            ':foit' => $row['foititis_id'],
            ':epiv' => $row['epivlepwn_id'],
            ':m1' => $row['melos1_id'],
            ':m2' => $row['melos2_id'],
            ':bathmos' => $final,
            ':link' => $link,
            ':praktiko' => isset($row['praktiko']) ? $row['praktiko'] : '',
            ':start' => isset($row['start_date']) ? $row['start_date'] : null,
            ':end' => $now
        ]);

        // delete original row
        $del = $conn->prepare("DELETE FROM diplwmatiki WHERE id = :id LIMIT 1");
        $del->execute([':id' => $id]);

        $conn->commit();
        echo json_encode(['success' => true, 'inserted' => $ins->rowCount(), 'deleted' => $del->rowCount(), 'final_bathmos' => $final]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'refresh_all_diplwmatikes') {
    try {
        $stmt = $conn->prepare("SELECT
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
            WHERE d.katastash IN ('ΕΝΕΡΓΗ','ΥΠΟ ΕΞΕΤΑΣΗ')
            ORDER BY d.start_date DESC
            LIMIT 1000");
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

// AJAX endpoint: return single diplwmatiki details as JSON when requested
if (isset($_GET['diplwmatiki_id'])) {
    $id = $_GET['diplwmatiki_id'];
    try {
        // return only fields from diplwmatiki (no finished table)
        $stmt = $conn->prepare("SELECT
                d.id AS diplwmatiki_id,
                d.title AS diplwmatiki_title,
                f.name AS foititis_name,
                f.lastname AS foititis_lastname,
                e.name AS epivlepwn_name,
                e.lastname AS epivlepwn_lastname,
                m1.name AS melos1_name,
                m1.lastname AS melos1_lastname,
                m2.name AS melos2_name,
                m2.lastname AS melos2_lastname,
                d.start_date AS start_date,
                d.katastash AS katastash,
                d.bathmos_epivlepwn AS bathmos_epivlepwn,
                d.bathmos_melos1 AS bathmos_melos1,
                d.bathmos_melos2 AS bathmos_melos2
            FROM diplwmatiki d
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn e ON d.epivlepwn_id = e.id
            LEFT JOIN didaskwn m1 ON d.melos1_id = m1.id
            LEFT JOIN didaskwn m2 ON d.melos2_id = m2.id
            WHERE d.id = :id
            LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        if ($row) echo json_encode($row);
        else echo json_encode(new stdClass());
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(new stdClass());
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/homepage_style_g.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        session_start();
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }
    ?>

    <div class="sidenav">
        <button class="nav-button active" onclick="setActive(this);loadContent('dedomena')">
            <img src="../public/icons/home.svg" alt="Home Icon" class="icon"> Εισαγωγή Δεδομένων
        </button>
        <button class="nav-button" onclick="setActive(this);loadContent('diplwmatikes')">
            <img src="../public/icons/pencil.svg" alt="Pencil Icon" class="icon"> Διπλωματικές
        </button>
    </div>

    <div id="content-container">
            <div id="eisagwgh-dedomenwn" class="content visible">
                <h1 id="titlos">Καλωσορίσατε <span style="float:right;">Συνδεθήκατε με λογιαριασμό Γραμματείας</span></h1>    
                                <div class="import-json">
                                                <h1 class="titlos">Εισαγωγή Δεδομένων</h1>
                                                <input type="file" id="personal-json-file" accept="application/json">
                                                <button id="import-json-btn">Εισαγωγή</button>
                                                <p id="import-json-status" style="padding-top:8px;"></p>
                                                                        <div class="json-instructions" style="margin-top:12px; background:#fff; padding:12px; border:1px solid #e6e6e6; border-radius:6px; color:#333;">
                                                                                <strong>Οδηγίες για το αρχείο JSON</strong>
                                                                                <p style="margin:8px 0 0 0;">Το αρχείο χρησιμοποιείται αποκλειστικά για εισαγωγή νέων χρηστών — ΔΕΝ θα ενημερώσει υπάρχουσες εγγραφές.</p>
                                                                                <p style="margin:6px 0 0 0;">Μπορείτε να παρέχετε προαιρετικά <code>id</code> ή <code>email</code> σε κάθε εγγραφή. Αν υπάρχει ήδη χρήστης με το ίδιο <code>email</code> ή <code>id</code>, η εγγραφή θα παραλειφθεί και θα αναφερθεί ως σφάλμα στο αποτέλεσμα εισαγωγής.</p>
                                                                                <p style="margin:8px 0 0 0; font-weight:bold;">Παράδειγμα δομής:</p>
                                                                                                            <pre style="background:#f7f7f7; padding:8px; border-radius:4px; overflow:auto; font-size:12px;">{
                                                        "foititis": [
                                                            { "name": "Γιάννης", "lastname": "Παπαδόπουλος", "phone": "6940000000", "email": "giannis@example.com", "password": "Passw0rd1" }
                                                        ],
                                                        "didaskwn": [
                                                            { "name": "Καθηγητής", "lastname": "Κωνσταντίνου", "phone": "2610000000", "email": "prof@example.com", "password": "ProfPass1" }
                                                        ]
                                                    }</pre>
                                                                                                            <p style="margin:8px 0 0 0;">Επιπλέον απαιτείται το πεδίο <code>password</code> για κάθε εγγραφή — το σύστημα ζητά κωδικό για το νέο χρήστη και θα τον αποθηκεύσει με ασφαλές hashing. Αν δεν δοθεί <code>password</code>, η εγγραφή θα απορριφθεί.</p>
                                                                                                            <p style="margin:8px 0 0 0;">Επιτρεπόμενα πεδία για εισαγωγή: για <code>foititis</code> — <code>name</code>, <code>lastname</code>, <code>address</code>, <code>phone</code>, <code>landline</code>, <code>email</code>, <code>password</code>; για <code>didaskwn</code> — <code>name</code>, <code>lastname</code>, <code>phone</code>, <code>email</code>, <code>password</code>. Οι εγγραφές που δε διαθέτουν πεδία για εισαγωγή ή λείπει <code>password</code> θα αγνοηθούν/σημειωθούν ως σφάλμα.</p>
                                                                                                            <p style="margin:6px 0 0 0; font-style:italic; color:#666;">Αποτέλεσμα εισαγωγής: θα δείτε μετρήσεις για το πόσες εγγραφές εισήχθησαν ανά πίνακα και λίστα σφαλμάτων για τις παραλειφθείσες εγγραφές.</p>
                                                                        </div>
                                </div>
            </div>

      <div id="diplwmatikes" class="content hidden">
            <div class="diplwmatikes-table-wrapper">
                <h1 class="titlos">Διπλωματικές</h1>
                <table id="table-1">
                    <thead>
                        <tr>
                            <th>Τίτλος</th>
                            <th>Όνομα Φοιτητή</th>
                            <th>Επώνυμο Φοιτητή</th>
                            <th>Μέλος 1</th>
                            <th>Μέλος 2</th>
                            <th>Ημ/νία Έναρξης</th>
                            <th>Κατάσταση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- filled by script_g.js -->
                    </tbody>
                </table>
                <p id="table-1-empty" style="display:none; padding:10px;">Δεν βρέθηκαν διπλωματικές με κατάσταση "Ενεργή" ή "Υπό Εξέταση".</p>
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
                    <p><strong>Χρόνος από Ημερομηνία Έναρξης:</strong> <span id="detail-time-since-start"></span></p>
                    <p><strong>Κατάσταση:</strong> <span id="detail-katastash"></span></p>
                    <p><strong>Βαθμός Επιβλέποντος:</strong> <span id="detail-bathmos-epivlepwn"></span></p>
                    <p><strong>Βαθμός 1ου Μέλους:</strong> <span id="detail-bathmos-melos1"></span></p>
                    <p><strong>Βαθμός 2ου Μέλους:</strong> <span id="detail-bathmos-melos2"></span></p>
                </div>

                <div class="energi-diplwmatiki" style="display:none;">
                    <h1 class="titlos">Διαχείρηση Διπλωματικής</h1>
                    <div class="eisagwgh-arithmou-prwtokolou">
                        <div class="eisagwgh-arithmou-prwtokolou-anathesi numeric-input">
                            <input type="number" id="arithmos-prwtokolou-anathesi" name="arithmos-prwtokolou-anathesi" placeholder="Αριθμός Πρωτοκόλλου Γενικής Συνέλευσης">
                            <button type="button" style="margin-top: 10px;" id="egkrisi-diplwmatiki-btn" class="back-button cancel-diplwmatiki-button">Έγκριση Διπλωματικής</button>
                        </div>
                        <div class="eisagwgh-arithmou-sineleusis-etous-cancel numeric-input">
                            <input type="number" id="arithmos-sineleusis-cancel" name="arithmos-sineleusis-cancel" placeholder="Αριθμός Γενικής Συνέλευσης">
                            <input type="number" id="etos-cancel" name="etos-cancel" placeholder="Ετος Γενικής Συνέλευσης">
                            <button type="button" style="margin-top: 10px;" id="cancel-diplwmatiki-btn" class="back-button cancel-diplwmatiki-button">Ακύρωση Διπλωματικής</button>
                        </div>
                    </div>
                </div>

                <div class="ypo-eksetasi-diplwmatiki" style="display:none;">
                    <h1 class="titlos">Διαχείρηση Διπλωματικής</h1>
                    <button type="button" id="peratwsi-diplwmatikis-btn" class="back-button peratwsi-diplwmatikis-button">Περάτωση Διπλωματικής</button>
                </div>

            </div>

            
        </div>
    </div>

        

    <script src="../public/js/script_g.js"></script>

</body>