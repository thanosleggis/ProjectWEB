<?php
// homepage_f.php — Φοιτητής (AJAX-enabled, DB writes)
// ----------------------------------------------------------
session_start();
include 'includes/db_connect.php';

// ==== Safe self URL ====
$selfRaw = $_SERVER['PHP_SELF'];
$selfEsc = htmlspecialchars($selfRaw, ENT_QUOTES, 'UTF-8');

// ==== Auth guard (must be foititis) ====
if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'foititis') {
    header('Location: index.php');
    exit();
}

// ==== User info ====
$user     = $_SESSION['user'];
$id       = (int)$_SESSION['id'];
$name     = htmlspecialchars((string)$user['name']);
$lastname = htmlspecialchars((string)$user['lastname']);

// ==== Upload paths (ΚΟΙΝΟ uploads για φοιτητές/καθηγητές) ====
// root/uploads (ένα επίπεδο πάνω από το pages/)
$uploadsBase     = dirname(__DIR__) . '/uploads';
$proxeiraDir     = $uploadsBase . '/proxeira';
$legacyDraftsDir = $uploadsBase . '/drafts';
@is_dir($uploadsBase)     || @mkdir($uploadsBase, 0775, true);
@is_dir($proxeiraDir)     || @mkdir($proxeiraDir, 0775, true);
@is_dir($legacyDraftsDir) || @mkdir($legacyDraftsDir, 0775, true);

// ==== Helpers ====
function json_out($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit();
}
function appendIndex($dir, $diplId, $filename, $uploadedAt = null) {
    $uploadedAt = $uploadedAt ?: date('Y-m-d H:i:s');
    $indexFile  = rtrim($dir, '/')."/index_{$diplId}.json";
    $list = file_exists($indexFile) ? json_decode(@file_get_contents($indexFile), true) : [];
    if (!is_array($list)) $list = [];
    $list[] = ['file'=>$filename, 'uploaded_at'=>$uploadedAt];
    @file_put_contents($indexFile, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function db_has_col(PDO $conn, $table, $col) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
        $stmt->execute([':c'=>$col]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return false; }
}

// ==== Profile column availability ====
$canAddress = db_has_col($conn, 'foititis', 'address');
$canEmail   = db_has_col($conn, 'foititis', 'email');
$canMobile  = db_has_col($conn, 'foititis', 'phone');     // κινητό
$canPhone   = db_has_col($conn, 'foititis', 'landline');  // σταθερό

// ==== Read-only if has FINISHED rows ====
$finishedOnlyReadOnly = false;
try {
    $hasFinStmt = $conn->prepare("SELECT COUNT(*) FROM diplwmatiki_FINISHED WHERE foititis_id = :id");
    $hasFinStmt->execute([':id'=>$id]);
    $finishedOnlyReadOnly = ((int)$hasFinStmt->fetchColumn()) > 0;
} catch (PDOException $e) {
    $finishedOnlyReadOnly = false; // don't lock on error
}

// ----------------------------------------------------------
//                AJAX  ENDPOINTS (JSON)
// ----------------------------------------------------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // ---- common: load current or finished thesis ----
    $myDipl = null; $statusNorm = ''; $isFinished = false; $finishedData = null;

    // If finished mode: return latest finished
    if ($finishedOnlyReadOnly) {
        try {
            $stmt = $conn->prepare("
                SELECT 
                    d.diplwmatiki_id AS id, d.title, d.start_date, d.end_date, d.bathmos,
                    d.nhmerths_link AS repo_link, d.praktiko AS praktiko,
                    e.name  AS epivlepwn_name,  e.lastname  AS epivlepwn_lastname,
                    m1.name AS melos1_name,     m1.lastname AS melos1_lastname,
                    m2.name AS melos2_name,     m2.lastname AS melos2_lastname
                FROM diplwmatiki_FINISHED d
                LEFT JOIN didaskwn e  ON d.epivlepwn_id = e.id
                LEFT JOIN didaskwn m1 ON d.melos1_id   = m1.id
                LEFT JOIN didaskwn m2 ON d.melos2_id   = m2.id
                WHERE d.foititis_id = :id
                ORDER BY d.end_date DESC
                LIMIT 1
            ");
            $stmt->execute([':id'=>$id]);
            $finishedData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $isFinished   = (bool)$finishedData;
            $statusNorm   = 'ΠΕΡΑΤΩΜΕΝΗ';
        } catch (PDOException $e) {}
    } else {
        // current
        try {
            $stmtCur = $conn->prepare("
                SELECT 
                    d.id, d.title, d.start_date, d.katastash,
                    d.epivlepwn_id, d.melos1_id, d.melos2_id,
                    d.links AS latest_link,
                    e.name  AS epivlepwn_name,   e.lastname  AS epivlepwn_lastname,
                    m1.name AS melos1_name,      m1.lastname AS melos1_lastname,
                    m2.name AS melos2_name,      m2.lastname AS melos2_lastname
                FROM diplwmatiki d
                LEFT JOIN didaskwn e  ON d.epivlepwn_id = e.id
                LEFT JOIN didaskwn m1 ON d.melos1_id   = m1.id
                LEFT JOIN didaskwn m2 ON d.melos2_id   = m2.id
                WHERE d.foititis_id = :id
                ORDER BY d.start_date DESC
                LIMIT 1
            ");
            $stmtCur->execute([':id'=>$id]);
            $myDipl = $stmtCur->fetch(PDO::FETCH_ASSOC) ?: null;
            $statusNorm = $myDipl ? preg_replace('/\s+/u', ' ', trim((string)($myDipl['katastash'] ?? ''))) : '';
        } catch (PDOException $e) {}
    }

    // ---- router ----
    switch ($action) {
        case 'summary':
            // θέμα description/pdf (by title or last of student)
            $thema = ['description'=>null,'pdf'=>null];
            try {
                $row = null;
                if ($myDipl && !empty($myDipl['title'])) {
                    $st = $conn->prepare("SELECT description,pdf FROM themata WHERE title = :t ORDER BY id DESC LIMIT 1");
                    $st->execute([':t'=>$myDipl['title']]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                }
                if (!$row) {
                    $st2 = $conn->prepare("SELECT description,pdf FROM themata WHERE foititis_id = :fid ORDER BY id DESC LIMIT 1");
                    $st2->execute([':fid'=>$id]);
                    $row = $st2->fetch(PDO::FETCH_ASSOC);
                }
                if ($row) { $thema['description']=$row['description']??null; $thema['pdf']=$row['pdf']??null; }
            } catch (PDOException $e) {}

            // elapsed text for current (not finished)
            $elapsedText = '';
            if ($myDipl && !empty($myDipl['start_date'])) {
                try {
                    $start = new DateTime($myDipl['start_date']);
                    $now   = new DateTime();
                    $diff  = $start->diff($now);
                    $parts = [];
                    if ($diff->y) $parts[] = $diff->y . ' έτη';
                    if ($diff->m) $parts[] = $diff->m . ' μήνες';
                    if ($diff->d || empty($parts)) $parts[] = $diff->d . ' ημέρες';
                    $elapsedText = implode(', ', $parts);
                } catch (Exception $e) {}
            }

            // profile (only columns that exist)
            $myProfile = ['address'=>'','email'=>'','mobile'=>'','phone'=>''];
            try {
                $sel = ['id'];
                if ($canAddress) $sel[] = 'address';
                if ($canEmail)   $sel[] = 'email';
                if ($canMobile)  $sel[] = 'phone AS mobile';
                if ($canPhone)   $sel[] = 'landline AS phone';
                if (count($sel) > 1) {
                    $q = $conn->prepare("SELECT ".implode(',', $sel)." FROM foititis WHERE id = :id");
                    $q->execute([':id'=>$id]);
                    $row = $q->fetch(PDO::FETCH_ASSOC);
                    if ($row) foreach (['address','email','mobile','phone'] as $k) if (array_key_exists($k,$row)) $myProfile[$k]=$row[$k]??'';
                }
            } catch (PDOException $e) {}

            json_out([
                'readonly'  => $finishedOnlyReadOnly,
                'status'    => $statusNorm,
                'myDipl'    => $myDipl,
                'isFinished'=> $isFinished,
                'finished'  => $finishedData,
                'thema'     => $thema,
                'elapsed'   => $elapsedText,
                'profile'   => $myProfile
            ]);
            break;

        // (ΑΦΑΙΡΕΘΗΚΕ) case 'available_themata': ...

        case 'my_prosklhseis':
            try {
                $st = $conn->prepare("
                    SELECT p.type, p.katastash, p.datetime_created, di.title AS diplwmatiki_title, 
                           did.name AS didaskwn_name, did.lastname AS didaskwn_lastname
                    FROM prosklhsh p
                    JOIN diplwmatiki di ON p.diplwmatiki_id = di.id
                    JOIN didaskwn did ON p.didaskwn_id = did.id
                    WHERE p.foititis_id = :id
                    ORDER BY p.datetime_created DESC
                ");
                $st->execute([':id'=>$id]);
                json_out(['rows'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (PDOException $e) { json_out(['rows'=>[]]); }
            break;

        case 'list_drafts':
            $diplId = (int)($_GET['dipl_id'] ?? 0);
            $idxFile = $proxeiraDir . "/index_{$diplId}.json";
            $rows = file_exists($idxFile) ? (json_decode(file_get_contents($idxFile), true) ?: []) : [];
            json_out(['rows'=>$rows]);
            break;

        case 'list_links':
            $diplId = (int)($_GET['dipl_id'] ?? 0);
            $lnk = $uploadsBase . "/links_{$diplId}.json";
            $rows = file_exists($lnk) ? (json_decode(file_get_contents($lnk), true) ?: []) : [];
            json_out(['rows'=>$rows]);
            break;

        // NEW: get the latest link stored in DB (diplwmatiki.links)
        case 'get_db_link':
            $diplId = (int)($_GET['dipl_id'] ?? 0);
            if (!$diplId) json_out(['link'=>'']);
            try {
                $s = $conn->prepare("SELECT links FROM diplwmatiki WHERE id = :id");
                $s->execute([':id'=>$diplId]);
                $v = (string)($s->fetchColumn() ?: '');
                json_out(['link'=>$v]);
            } catch (PDOException $e) { json_out(['link'=>'']); }
            break;

        case 'get_exam':
            $diplId = (int)($_GET['dipl_id'] ?? 0);
            if (!$diplId) json_out(['exam'=>null]);
            try {
                $st = $conn->prepare("SELECT date, time, tropos_eksetasis, aithousa, link
                                      FROM eksetasi_diplwmatikis
                                      WHERE diplwmatiki_id = :id
                                      LIMIT 1");
                $st->execute([':id'=>$diplId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) { json_out(['exam'=>null]); }
                $mode = ($row['tropos_eksetasis']==='ΔΙΑ ΖΩΣΗΣ') ? 'presential'
                       : (($row['tropos_eksetasis']==='ΔΙΑΔΙΚΤΥΑΚΑ') ? 'online' : '');
                json_out(['exam'=>[
                    'datetime' => ($row['date'] ? $row['date'].'T'.substr((string)$row['time'],0,5) : ''),
                    'mode'     => $mode,
                    'room'     => $row['aithousa'] ?? '',
                    'url'      => $row['link'] ?? ''
                ]]);
            } catch (PDOException $e) { json_out(['exam'=>null]); }
            break;

        case 'get_repo':
            $diplId = (int)($_GET['dipl_id'] ?? 0);
            if (!$diplId) json_out(['repo'=>'']);
            try {
                $stmt = $conn->prepare("SELECT nhmerths_link FROM diplwmatiki WHERE id = :id");
                $stmt->execute([':id'=>$diplId]);
                $repo = (string)($stmt->fetchColumn() ?: '');
                json_out(['repo'=>$repo]);
            } catch (PDOException $e) { json_out(['repo'=>'']); }
            break;

        case 'invite_candidates':
            // Professors list excluding epivlepwn/melos1/melos2 for current thesis
            if (!$myDipl) json_out(['rows'=>[]]);
            try {
                $epiv = (int)$myDipl['epivlepwn_id'];
                $m1   = !empty($myDipl['melos1_id']) ? (int)$myDipl['melos1_id'] : -1;
                $m2   = !empty($myDipl['melos2_id']) ? (int)$myDipl['melos2_id'] : -2;

                $stmtDid = $conn->prepare("
                    SELECT id, name, lastname
                    FROM didaskwn
                    WHERE id NOT IN (:epiv, :m1, :m2)
                    ORDER BY lastname, name
                ");
                $stmtDid->bindParam(':epiv', $epiv, PDO::PARAM_INT);
                $stmtDid->bindParam(':m1',   $m1,   PDO::PARAM_INT);
                $stmtDid->bindParam(':m2',   $m2,   PDO::PARAM_INT);
                $stmtDid->execute();
                json_out(['rows'=>$stmtDid->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (PDOException $e) { json_out(['rows'=>[]]); }
            break;

        // ---------- POSTing actions as AJAX ----------

        case 'update_profile':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false],405);
            $set=[]; $params=[':id'=>$id];
            if ($canAddress) { $set[]="address=:address";   $params[':address']=trim($_POST['address'] ?? ''); }
            if ($canEmail)   { $set[]="email=:email";       $params[':email']=trim($_POST['email'] ?? ''); }
            if ($canMobile)  { $set[]="phone=:mobile";      $params[':mobile']=trim($_POST['mobile'] ?? ''); }
            if ($canPhone)   { $set[]="landline=:phone";    $params[':phone']=trim($_POST['phone'] ?? ''); }
            if (!$set) json_out(['ok'=>false,'msg'=>'No editable columns']);
            try {
                $sql="UPDATE foititis SET ".implode(',',$set)." WHERE id=:id";
                $conn->prepare($sql)->execute($params);
                json_out(['ok'=>true]);
            } catch (PDOException $e) { json_out(['ok'=>false,'msg'=>$e->getMessage()]); }
            break;

        case 'invite_member':
            if ($finishedOnlyReadOnly) json_out(['ok'=>false,'readonly'=>1],403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false],405);
            $dipl_id     = (int)($_POST['dipl_id'] ?? 0);
            $didaskwn_id = (int)($_POST['didaskwn_id'] ?? 0);
            if (!$dipl_id || !$didaskwn_id) json_out(['ok'=>false,'msg'=>'invalid']);
            try {
                $chk = $conn->prepare("
                    SELECT COUNT(*) FROM prosklhsh
                    WHERE foititis_id = :fid AND didaskwn_id = :did AND diplwmatiki_id = :didpl AND type = 'ΜΕΛΟΣ'
                ");
                $chk->execute([':fid'=>$id, ':did'=>$didaskwn_id, ':didpl'=>$dipl_id]);
                if ((int)$chk->fetchColumn() === 0) {
                    $stmt = $conn->prepare("
                        INSERT INTO prosklhsh (foititis_id, didaskwn_id, diplwmatiki_id, type, katastash, datetime_created)
                        VALUES (:foititis_id, :didaskwn_id, :dipl_id, 'ΜΕΛΟΣ', 'ΣΕ ΕΞΕΛΙΞΗ', NOW())
                    ");
                    $stmt->execute([':foititis_id'=>$id, ':didaskwn_id'=>$didaskwn_id, ':dipl_id'=>$dipl_id]);
                }
                json_out(['ok'=>true]);
            } catch (PDOException $e) { json_out(['ok'=>false,'msg'=>$e->getMessage()]); }
            break;

        case 'upload_draft':
            if ($finishedOnlyReadOnly) json_out(['ok'=>false,'readonly'=>1],403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false],405);
            $dipl_id = (int)($_POST['dipl_id'] ?? 0);
            if (!$dipl_id || !isset($_FILES['draft_file']) || $_FILES['draft_file']['error'] !== UPLOAD_ERR_OK) {
                json_out(['ok'=>false,'msg'=>'no file']);
            }
            $ext = pathinfo($_FILES['draft_file']['name'], PATHINFO_EXTENSION);
            $safeExt = preg_replace('/[^a-zA-Z0-9]+/', '', $ext);
            $filename = "dipl_{$dipl_id}_" . time() . ($safeExt ? ".$safeExt" : '');
            $targetProxeira = $proxeiraDir . '/' . $filename;
            if (!move_uploaded_file($_FILES['draft_file']['tmp_name'], $targetProxeira)) {
                json_out(['ok'=>false,'msg'=>'move failed']);
            }
            $ts = date('Y-m-d H:i:s');
            appendIndex($proxeiraDir, $dipl_id, $filename, $ts);
            @copy($targetProxeira, $legacyDraftsDir . '/' . $filename);
            appendIndex($legacyDraftsDir, $dipl_id, $filename, $ts);

            // NEW: γράψε και στη ΒΔ το τελευταίο πρόχειρο
            try {
                $conn->prepare("UPDATE diplwmatiki SET proxeiro = :p WHERE id = :id")
                     ->execute([':p'=>$filename, ':id'=>$dipl_id]);
            } catch (PDOException $e) { /* optional log */ }

            json_out(['ok'=>true,'file'=>$filename,'ts'=>$ts]);
            break;

        case 'add_link':
            if ($finishedOnlyReadOnly) json_out(['ok'=>false,'readonly'=>1],403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false],405);
            $dipl_id = (int)($_POST['dipl_id'] ?? 0);
            $link    = trim($_POST['link_url'] ?? '');
            if (!$dipl_id || $link==='') json_out(['ok'=>false,'msg'=>'invalid']);

            // κρατάμε και ιστορικό σε json
            $linksFile = $uploadsBase . "/links_{$dipl_id}.json";
            $links = file_exists($linksFile) ? json_decode(file_get_contents($linksFile), true) : [];
            if (!is_array($links)) $links = [];
            $links[] = ['url'=>$link, 'added_at'=>date('Y-m-d H:i:s')];
            file_put_contents($linksFile, json_encode($links, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

            // NEW: γράψε και στη ΒΔ το τελευταίο link (στήλη diplwmatiki.links)
            try {
                $conn->prepare("UPDATE diplwmatiki SET links = :l WHERE id = :id")
                     ->execute([':l'=>$link, ':id'=>$dipl_id]);
            } catch (PDOException $e) { /* optional log */ }

            json_out(['ok'=>true]);
            break;

        case 'save_exam':
            if ($finishedOnlyReadOnly) json_out(['ok'=>false,'readonly'=>1],403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false],405);
            $dipl_id = (int)($_POST['dipl_id'] ?? 0);
            $dt      = trim($_POST['exam_datetime'] ?? '');   // "YYYY-MM-DDTHH:MM"
            $mode    = trim($_POST['exam_mode'] ?? '');       // presential | online | ''
            $room    = trim($_POST['exam_room'] ?? '');
            $url     = trim($_POST['exam_url'] ?? '');
            if (!$dipl_id) json_out(['ok'=>false,'msg'=>'invalid']);

            $datePart = $timePart = null;
            if ($dt !== '') {
                $datePart = substr($dt, 0, 10);
                $timePart = (strlen($dt) >= 16) ? substr($dt, 11, 5).':00' : '00:00:00';
            } else {
                $datePart = date('Y-m-d');
                $timePart = '00:00:00';
            }
            $tropos = ($mode === 'presential') ? 'ΔΙΑ ΖΩΣΗΣ' : (($mode === 'online') ? 'ΔΙΑΔΙΚΤΥΑΚΑ' : 'ΔΙΑ ΖΩΣΗΣ');

            try {
                // βρες foititis_id & epivlepwn_id (NOT NULL στο schema του πίνακα εξέτασης)
                $b = $conn->prepare("SELECT foititis_id, epivlepwn_id FROM diplwmatiki WHERE id = :id");
                $b->execute([':id'=>$dipl_id]);
                $base = $b->fetch(PDO::FETCH_ASSOC);
                if (!$base) json_out(['ok'=>false,'msg'=>'unknown thesis id']);

                $sql = "INSERT INTO eksetasi_diplwmatikis
                            (diplwmatiki_id, foititis_id, didaskwn_id, date, time, tropos_eksetasis, aithousa, link)
                        VALUES (:dipl,:fid,:did,:d,:t,:tr,:room,:url)
                        ON DUPLICATE KEY UPDATE
                            foititis_id = VALUES(foititis_id),
                            didaskwn_id = VALUES(didaskwn_id),
                            date = VALUES(date),
                            time = VALUES(time),
                            tropos_eksetasis = VALUES(tropos_eksetasis),
                            aithousa = VALUES(aithousa),
                            link = VALUES(link)";

                $conn->prepare($sql)->execute([
                    ':dipl' => $dipl_id,
                    ':fid'  => (int)$base['foititis_id'],
                    ':did'  => (int)$base['epivlepwn_id'],
                    ':d'    => $datePart,
                    ':t'    => $timePart,
                    ':tr'   => $tropos,
                    ':room' => $room,
                    ':url'  => $url
                ]);

                json_out(['ok'=>true]);
            } catch (PDOException $e) { json_out(['ok'=>false,'msg'=>$e->getMessage()]); }
            break;

        case 'save_repo_link':
            if ($finishedOnlyReadOnly) json_out(['ok'=>false,'readonly'=>1],403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false],405);
            $dipl_id = (int)($_POST['dipl_id'] ?? 0);
            $repo    = trim($_POST['repo_link'] ?? '');
            if (!$dipl_id) json_out(['ok'=>false,'msg'=>'invalid']);
            try {
                $conn->prepare("UPDATE diplwmatiki SET nhmerths_link = :r WHERE id = :id")
                     ->execute([':r'=>$repo, ':id'=>$dipl_id]);
                json_out(['ok'=>true]);
            } catch (PDOException $e) { json_out(['ok'=>false,'msg'=>$e->getMessage()]); }
            break;

        default:
            json_out(['error'=>'unknown action'], 400);
    }
}

// ----------------------------------------------------------
//    Auto-transition to "ΕΝΕΡΓΗ" (server-side)
// ----------------------------------------------------------
$autoTransition = false;
try {
    $stmtCur = $conn->prepare("
        SELECT id, katastash, melos1_id, melos2_id
        FROM diplwmatiki WHERE foititis_id = :id
        ORDER BY start_date DESC
        LIMIT 1
    ");
    $stmtCur->execute([':id'=>$id]);
    $cur = $stmtCur->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($cur && preg_replace('/\s+/u',' ',trim((string)$cur['katastash'])) === 'ΥΠΟ ΑΝΑΘΕΣΗ') {
        $diplId = (int)$cur['id'];
        $bothMembersSet = !empty($cur['melos1_id']) && !empty($cur['melos2_id']);

        $accepted = 0;
        $acc = $conn->prepare("
            SELECT COUNT(*) 
            FROM prosklhsh 
            WHERE diplwmatiki_id = :id 
              AND type = 'ΜΕΛΟΣ' 
              AND katastash = 'ΕΓΚΡΙΘΗΚΕ'
        ");
        $acc->execute([':id'=>$diplId]);
        $accepted = (int)$acc->fetchColumn();

        if ($bothMembersSet && $accepted >= 2) {
            $conn->beginTransaction();
            $conn->prepare("UPDATE diplwmatiki SET katastash = 'ΕΝΕΡΓΗ' WHERE id = :id")
                 ->execute([':id' => $diplId]);
            // ακύρωσε τις υπόλοιπες μη εγκεκριμένες προσκλήσεις ΜΕΛΟΥΣ
            $conn->prepare("
                DELETE FROM prosklhsh 
                WHERE diplwmatiki_id = :id 
                  AND type = 'ΜΕΛΟΣ'
                  AND (katastash IS NULL OR katastash <> 'ΕΓΚΡΙΘΗΚΕ')
            ")->execute([':id' => $diplId]);
            $conn->commit();
            $autoTransition = true;
        }
    }
} catch (PDOException $e) {}

// ----------------------------------------------------------
//                       HTML
// ----------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Φοιτητής - Home (AJAX)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- ΣΩΣΤΟ ΟΝΟΜΑ CSS -->
    <link rel="stylesheet" href="../public/css/homepage_style_f.css">
</head>
<body>
<header class="social-header">
    <div class="header-left">
        <img src="../public/icons/uop_logo.png" class="header-icon" alt="">
        <span class="header-title">Πανεπιστήμιο Πατρών</span>
    </div>
    <div class="header-right">
        <a href="#"><img src="../public/icons/user.svg" class="header-icon" alt=""><span class="header-user"><?= $name . ' ' . $lastname; ?></span></a>
        <a href="<?= $selfEsc ?>?logout=true"><img src="../public/icons/exit.svg" class="header-icon" alt=""><span class="header-user">Αποσύνδεση</span></a>
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
    <button class="nav-button active" onclick="setActive(this);loadContent('home')"><img src="../public/icons/home.svg" class="icon" alt=""> Αρχική</button>
    <button class="nav-button" onclick="setActive(this);loadContent('thema')"><img src="../public/icons/pencil.svg" class="icon" alt=""> Το Θέμα μου</button>
    <button class="nav-button" onclick="setActive(this);loadContent('manage')"><img src="../public/icons/chat-arrow-grow.svg" class="icon" alt=""> Διαχείριση ΔΕ</button>
    <button class="nav-button" onclick="setActive(this);loadContent('profile')"><img src="../public/icons/user.svg" class="icon" alt=""> Επεξεργασία Προφίλ</button>
    <button class="nav-button" onclick="setActive(this);loadContent('prosklhseis')"><img src="../public/icons/envelope.svg" class="icon" alt=""> Οι Προσκλήσεις μου</button>
</div>

<div id="content-container">
    <!-- Home -->
    <div id="home" class="content visible">
        <h1>Καλωσήρθες <?= $name ?>!</h1>
        <p>Είσαι συνδεδεμένος/η ως Φοιτητής/τρια.</p>
        <?php if ($autoTransition): ?>
            <div class="ok mt-10">Η διπλωματική μετατράπηκε αυτόματα σε «ΕΝΕΡΓΗ» και ακυρώθηκαν οι επιπλέον προσκλήσεις.</div>
        <?php endif; ?>
        <div id="home-alerts"></div>
    </div>

    <!-- Το θέμα μου -->
    <div id="thema" class="content hidden">
        <h1>Η Διπλωματική μου</h1>
        <div id="thema-content"><em>Φόρτωση...</em></div>
    </div>

    <!-- Διαχείριση ΔΕ -->
    <div id="manage" class="content hidden">
        <h1>Διαχείριση διπλωματικής</h1>
        <div id="manage-container"><em>Φόρτωση...</em></div>
    </div>

    <!-- Επεξεργασία Προφίλ -->
    <div id="profile" class="content hidden">
        <h1>Επεξεργασία Προφίλ</h1>
        <form id="profile-form" class="grid-2 max-900">
            <?php if ($canAddress): ?>
                <label>Πλήρης ταχυδρομική διεύθυνση
                    <input type="text" name="address" value="">
                </label>
            <?php endif; ?>
            <?php if ($canEmail): ?>
                <label>Email επικοινωνίας
                    <input type="email" name="email" value="">
                </label>
            <?php endif; ?>
            <?php if ($canMobile): ?>
                <label>Κινητό τηλέφωνο
                    <input type="text" name="mobile" value="">
                </label>
            <?php endif; ?>
            <?php if ($canPhone): ?>
                <label>Σταθερό τηλέφωνο
                    <input type="text" name="phone" value="">
                </label>
            <?php endif; ?>
            <div class="full-span mt-6">
                <button type="submit">Αποθήκευση αλλαγών</button>
                <span id="profile-msg" class="mt-6"></span>
            </div>
        </form>
    </div>

    <!-- Οι προσκλήσεις μου -->
    <div id="prosklhseis" class="content hidden">
        <h1>Οι Προσκλήσεις μου</h1>
        <div id="invite-card"></div>
        <table>
            <thead><tr><th>Θέμα</th><th>Επιβλέπων</th><th>Τύπος</th><th>Κατάσταση</th><th>Ημερομηνία</th></tr></thead>
            <tbody id="prosklhseis-body"><tr><td colspan="5">Φόρτωση...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Το παλιό σου JS (sidebar/tabs) -->
<script src="../public/js/script_f.js"></script>

<!-- Μικρό inline JS: κλήσεις στα AJAX endpoints και render -->
<script>
(() => {
  // PUBLIC uploads (ένα επίπεδο πάνω από /pages)
  const PUBLIC_UPLOADS_PREFIX = '../uploads/';

  const API = (action, params={}) => {
    const url = new URL('<?= $selfEsc ?>', location.href);
    url.searchParams.set('action', action);
    if (params && typeof params === 'object')
      for (const [k,v] of Object.entries(params)) url.searchParams.set(k, v);
    return fetch(url, { credentials: 'same-origin' }).then(r => r.json());
  };
  const POST = (action, formData) => {
    const url = new URL('<?= $selfEsc ?>', location.href);
    url.searchParams.set('action', action);
    return fetch(url, { method:'POST', body: formData, credentials:'same-origin' }).then(r => r.json());
  };

  // Cache of summary
  let SUMMARY = null;

  const el = sel => document.querySelector(sel);
  const esc = s => (s==null?'':String(s));
  const link = (href,text) => href?`<a href="${href}" target="_blank" rel="noopener">${text||href}</a>`:'';

  // Load everything once
  document.addEventListener('DOMContentLoaded', async () => {
    await loadSummary();
    await renderThema();
    await renderManage();
    await renderProsklhseis();
    fillProfileForm();
  });

  async function loadSummary() {
    SUMMARY = await API('summary');
    // alerts
    const alerts = [];
    if (SUMMARY.readonly) alerts.push('<div class="error mt-10">Η ΔΕ σας είναι <strong>Περατωμένη</strong>. Επιτρέπεται μόνο προβολή πληροφοριών, αλλαγών κατάστασης και του πρακτικού εξέτασης.</div>');
    el('#home-alerts').innerHTML = alerts.join('');
  }

  // ---- THEMA ----
  async function renderThema() {
    const c = el('#thema-content');
    const s = SUMMARY;
    if (s.myDipl) {
      c.innerHTML = `
        <p><strong>Τίτλος:</strong> ${esc(s.myDipl.title||'')}</p>
        <p><strong>Περιγραφή:</strong> ${s.thema.description ? esc(s.thema.description).replaceAll('\\n','<br>') : ''}</p>
        <p><strong>Συνημμένο αρχείο περιγραφής:</strong> ${s.thema.pdf ? link(PUBLIC_UPLOADS_PREFIX + 'themata/' + escapeHtml(s.thema.pdf),'Άνοιγμα') : '-'}</p>
        <p><strong>Κατάσταση:</strong> ${esc(s.status||'')}</p>
        <p><strong>Επιβλέπων:</strong> ${esc(s.myDipl.epivlepwn_name||'')} ${esc(s.myDipl.epivlepwn_lastname||'')}</p>
        <p><strong>1ο Μέλος:</strong> ${esc(s.myDipl.melos1_name||'—')} ${esc(s.myDipl.melos1_lastname||'')}</p>
        <p><strong>2ο Μέλος:</strong> ${esc(s.myDipl.melos2_name||'—')} ${esc(s.myDipl.melos2_lastname||'')}</p>
        <p><strong>Ημ/νία Ανάθεσης:</strong> ${esc(s.myDipl.start_date||'')} ${s.elapsed?`(πριν από ${esc(s.elapsed)})`:''}</p>
      `;
    } else if (s.isFinished && s.finished) {
      const f = s.finished;
      c.innerHTML = `
        <p><strong>Τίτλος:</strong> ${esc(f.title)} (Περατωμένη)</p>
        <p><strong>Επιβλέπων:</strong> ${esc(f.epivlepwn_name||'')} ${esc(f.epivlepwn_lastname||'')}</p>
        <p><strong>1ο Μέλος:</strong> ${esc(f.melos1_name||'—')} ${esc(f.melos1_lastname||'')}</p>
        <p><strong>2ο Μέλος:</strong> ${esc(f.melos2_name||'—')} ${esc(f.melos2_lastname||'')}</p>
        <p><strong>Βαθμός:</strong> ${esc(f.bathmos||'—')}</p>
        <h3 class="mt-12">Αλλαγές κατάστασης</h3>
        <ul class="list-disc">
          <li><strong>Έναρξη:</strong> ${esc(f.start_date||'—')}</li>
          <li><strong>Περάτωση:</strong> ${esc(f.end_date||'—')} (Κατάσταση: Περατωμένη)</li>
        </ul>
        ${f.praktiko?`<p class="mt-10"><strong>Πρακτικό εξέτασης:</strong> ${link(esc(f.praktiko),'Προβολή')}</p>`:''}
        ${f.repo_link?`<p><strong>Αποθετήριο:</strong> ${link(esc(f.repo_link),'Άνοιγμα')}</p>`:''}
        <p><em>Για περατωμένες διπλωματικές επιτρέπεται μόνο προβολή.</em></p>`;
    } else {
      c.innerHTML = '<p>Δεν έχετε ακόμα αναλάβει διπλωματική.</p>';
    }
  }

  // ---- MANAGE ----
  async function renderManage() {
    const wrap = el('#manage-container');
    const s = SUMMARY;
    if (s.isFinished && s.finished) {
      const f=s.finished;
      wrap.innerHTML = `
        <p>Η διπλωματική είναι <strong>Περατωμένη</strong>. Διαθέσιμες μόνο προβολές.</p>
        ${f.repo_link?`<p><strong>Αποθετήριο:</strong> ${link(esc(f.repo_link),'Άνοιγμα')}</p>`:''}
        ${f.praktiko?`<p><strong>Πρακτικό εξέτασης:</strong> ${link(esc(f.praktiko),'Προβολή')}</p>`:''}
        <h3 class="mt-12">Αλλαγές κατάστασης</h3>
        <ul class="list-disc"><li><strong>Έναρξη:</strong> ${esc(f.start_date||'—')}</li><li><strong>Περάτωση:</strong> ${esc(f.end_date||'—')}</li></ul>`;
      return;
    }
    if (!s.myDipl) { wrap.innerHTML='<p>Δεν υπάρχει διπλωματική για διαχείριση.</p>'; return; }

    const diplId = s.myDipl.id;
    if (s.status === 'ΥΠΟ ΕΞΕΤΑΣΗ') {
      // Load lists + exam + repo + latest DB link
      const [drafts, links, ex, rr, dbLink] = await Promise.all([
        API('list_drafts', {dipl_id: diplId}),
        API('list_links',  {dipl_id: diplId}),
        API('get_exam',    {dipl_id: diplId}),
        API('get_repo',    {dipl_id: diplId}),
        API('get_db_link', {dipl_id: diplId})
      ]);
      const exam = ex.exam || {};
      const repo = rr.repo || '';
      const latestDbLink = (dbLink && dbLink.link) ? dbLink.link : '';

      // merge links: DB link first (if present), then history
      const linksHtml = [];
      if (latestDbLink) linksHtml.push(`<li><a target="_blank" rel="noopener" href="${escapeHtml(latestDbLink)}">${escapeHtml(latestDbLink)}</a> <span class="muted">— (τελευταίο στην ΒΔ)</span></li>`);
      (links.rows||[]).forEach(l => {
        const u = (l && l.url) ? String(l.url) : '';
        if (!u) return;
        // avoid duplicate of the very same URL
        if (u === latestDbLink) return;
        linksHtml.push(`<li><a target="_blank" rel="noopener" href="${escapeHtml(u)}">${escapeHtml(u)}</a></li>`);
      });

      wrap.innerHTML = `
        <h2>Ανάρτηση πρόχειρου & σύνδεσμοι</h2>
        <form id="draft-form" class="flex-row-wrap" enctype="multipart/form-data">
          <input type="hidden" name="dipl_id" value="${diplId}">
          <label>Αρχείο πρόχειρου: <input type="file" name="draft_file" required></label>
          <button type="submit">Μεταφόρτωση</button>
        </form>

        <form id="link-form" class="flex-row-wrap mt-10">
          <input type="hidden" name="dipl_id" value="${diplId}">
          <label>Σύνδεσμος (Drive/YouTube κλπ): <input type="url" name="link_url" placeholder="https://..." required></label>
          <button type="submit">Προσθήκη</button>
        </form>

        <div class="grid-2 mt-10">
          <div>
            <h3>Μεταφορτωμένα πρόχειρα</h3>
            <ul class="list-disc" id="draft-list">
              ${ (drafts.rows||[]).map(d => 
                `<li><a target="_blank" rel="noopener" href="${PUBLIC_UPLOADS_PREFIX}proxeira/${escapeHtml(d.file)}">${escapeHtml(d.file)}</a> <span class="muted">— ${escapeHtml(d.uploaded_at)}</span></li>`
              ).join('') || '<li class="muted">Δεν υπάρχουν αρχεία.</li>'}
            </ul>
          </div>
          <div>
            <h3>Σύνδεσμοι</h3>
            <ul class="list-disc" id="links-list">
              ${ linksHtml.join('') || '<li class="muted">Δεν υπάρχουν σύνδεσμοι.</li>'}
            </ul>
          </div>
        </div>

        <h2 class="mt-20">Ημερομηνία/τρόπος εξέτασης</h2>
        <form id="exam-form" class="grid-3">
          <input type="hidden" name="dipl_id" value="${diplId}">
          <label>Ημερομηνία & Ώρα: <input type="datetime-local" name="exam_datetime" value="${escapeAttr(exam.datetime||'')}"></label>
          <label>Τρόπος:
            <select name="exam_mode" id="exam_mode">
              <option value="" ${!exam.mode?'selected':''}>—</option>
              <option value="presential" ${exam.mode==='presential'?'selected':''}>Δια ζώσης</option>
              <option value="online" ${exam.mode==='online'?'selected':''}>Διαδικτυακά</option>
            </select>
          </label>
          <div class="full-span grid-3" id="exam-extra">
            <label id="room-wrap" style="display:none;">Αίθουσα: <input type="text" name="exam_room" id="exam_room" value="${escapeAttr(exam.room||'')}" placeholder="π.χ. Αιθ. Β2"></label>
            <label id="url-wrap"  style="display:none;">Σύνδεσμος παρακολούθησης: <input type="url" name="exam_url" id="exam_url" value="${escapeAttr(exam.url||'')}" placeholder="https://..."></label>
          </div>
          <div class="full-span"><button type="submit">Αποθήκευση στοιχείων εξέτασης</button></div>
        </form>

        <h2 class="mt-20">Σύνδεσμος αποθετηρίου (Νημερτής)</h2>
        <form id="repo-form" class="flex-row-wrap">
          <input type="hidden" name="dipl_id" value="${diplId}">
          <label>URL: <input type="url" name="repo_link" value="${escapeAttr(repo)}" placeholder="https://nimertis...."></label>
          <button type="submit">Αποθήκευση</button>
        </form>

        <p class="muted mt-8">Μετά την καταχώριση βαθμών από τα μέλη, θα είναι διαθέσιμο το πρακτικό εξέτασης (HTML) και η ΔΕ θα μεταφερθεί στο ιστορικό.</p>`;

      // === ΔΥΝΑΜΙΚΗ ΕΜΦΑΝΙΣΗ/ΑΠΟΚΡΥΨΗ πεδίων ανάλογα με τρόπος ===
      const modeSel = el('#exam_mode');
      const roomWrap = el('#room-wrap');
      const urlWrap  = el('#url-wrap');
      const roomInput= el('#exam_room');
      const urlInput = el('#exam_url');

      function toggleExamExtra() {
        const m = modeSel.value;
        // Καθαρισμός required
        roomInput && roomInput.removeAttribute('required');
        urlInput  && urlInput.removeAttribute('required');

        if (m === 'presential') {
          roomWrap.style.display = '';
          urlWrap.style.display  = 'none';
          if (roomInput) roomInput.setAttribute('required','required');
        } else if (m === 'online') {
          roomWrap.style.display = 'none';
          urlWrap.style.display  = '';
          if (urlInput)  urlInput.setAttribute('required','required');
        } else {
          roomWrap.style.display = 'none';
          urlWrap.style.display  = 'none';
        }
      }
      modeSel.addEventListener('change', toggleExamExtra);
      toggleExamExtra(); // αρχική κατάσταση

      // Hook forms
      el('#draft-form').addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const r = await POST('upload_draft', fd);
        if (r.ok) { await renderManage(); } else alert(r.msg||'Σφάλμα μεταφόρτωσης');
      });
      el('#link-form').addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const r = await POST('add_link', fd);
        if (r.ok) { await renderManage(); } else alert(r.msg||'Σφάλμα');
      });
      el('#exam-form').addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);

        // Client-side validation ανάλογα με mode
        const m = fd.get('exam_mode');
        const room = (fd.get('exam_room')||'').trim();
        const url  = (fd.get('exam_url')||'').trim();
        if (m === 'presential' && !room) { alert('Συμπλήρωσε την αίθουσα.'); return; }
        if (m === 'online' && !url)      { alert('Συμπλήρωσε τον σύνδεσμο παρακολούθησης.'); return; }

        const r = await POST('save_exam', fd);
        if (!r.ok) alert(r.msg||'Σφάλμα'); else alert('Αποθηκεύτηκε!');
      });
      el('#repo-form').addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const r = await POST('save_repo_link', fd);
        if (!r.ok) alert(r.msg||'Σφάλμα'); else alert('Αποθηκεύτηκε!');
      });

    } else if (s.status === 'ΥΠΟ ΑΝΑΘΕΣΗ') {
      wrap.innerHTML = `<p class="muted">Μόλις αποδεχτούν δύο διδάσκοντες (και οριστούν στη ΔΕ), η ΔΕ γίνεται «ΕΝΕΡΓΗ» και ακυρώνονται αυτόματα οι λοιπές προσκλήσεις.</p>`;
    } else {
      wrap.innerHTML = `<p>Η διπλωματική βρίσκεται σε κατάσταση: <strong>${esc(s.status||'')}</strong>. Δεν υπάρχουν διαθέσιμες ενέργειες αυτή τη στιγμή.</p>`;
    }
  }

  // ---- PROSKLHSEIS ----
  async function renderProsklhseis() {
    const tbody = el('#prosklhseis-body');
    const data  = await API('my_prosklhseis');
    const rows  = data.rows||[];
    tbody.innerHTML = rows.length ? rows.map(p => `
      <tr>
        <td>${escapeHtml(p.diplwmatiki_title||'')}</td>
        <td>${escapeHtml((p.didaskwn_name||'')+' '+(p.didaskwn_lastname||''))}</td>
        <td>${escapeHtml(p.type||'')}</td>
        <td>${escapeHtml(p.katastash||'—')}</td>
        <td>${escapeHtml(p.datetime_created||'')}</td>
      </tr>
    `).join('') : `<tr><td colspan="5">Δεν έχετε στείλει προσκλήσεις.</td></tr>`;

    // Show invite card only when ΥΠΟ ΑΝΑΘΕΣΗ
    const s = SUMMARY;
    const card = el('#invite-card');
    if (s.myDipl && (s.status === 'ΥΠΟ ΑΝΑΘΕΣΗ')) {
      const opts = await API('invite_candidates');
  const options = (opts.rows||[]).map(d => `<option value="${d.id}">${escapeHtml((d.lastname||'')+' '+(d.name||''))}</option>`).join('');
      card.innerHTML = `
        <div class="invite-card">
          <h2>Ανάθεση Πρόσκλησης</h2>
          <form id="invite-form" class="flex-row-wrap">
            <input type="hidden" name="dipl_id" value="${s.myDipl.id}">
            <label>Επιλέξτε Διδάσκοντα:
              <select name="didaskwn_id" required>${options}</select>
            </label>
            <button type="submit">Ανάθεση Πρόσκλησης</button>
          </form>
          <p class="muted mt-6">Στέλνεται πρόσκληση τύπου «ΜΕΛΟΣ» για τη διπλωματική σας. Μόλις αποδεχτούν δύο διδάσκοντες (και οριστούν στη ΔΕ), η ΔΕ γίνεται «ΕΝΕΡΓΗ».</p>
        </div>`;
      el('#invite-form').addEventListener('submit', async (ev)=>{
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const r = await POST('invite_member', fd);
        if (!r.ok) alert(r.msg||'Σφάλμα'); else { alert('Η ανάθεση πρόσκλησης ολοκληρώθηκε'); renderProsklhseis(); }
      });
    } else {
      card.innerHTML = '';
    }
  }

  // ---- PROFILE ----
  function fillProfileForm() {
    const p = SUMMARY.profile || {};
    const f = el('#profile-form');
    if (f.querySelector('input[name="address"]')) f.querySelector('input[name="address"]').value = p.address||'';
    if (f.querySelector('input[name="email"]'))   f.querySelector('input[name="email"]').value   = p.email||'';
    if (f.querySelector('input[name="mobile"]'))  f.querySelector('input[name="mobile"]').value  = p.mobile||'';
    if (f.querySelector('input[name="phone"]'))   f.querySelector('input[name="phone"]').value   = p.phone||'';

    f.addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      const fd = new FormData(f);
      const r = await POST('update_profile', fd);
      el('#profile-msg').textContent = r.ok ? 'Αποθηκεύτηκε!' : (r.msg || 'Σφάλμα');
      if (r.ok) await loadSummary();
    });
  }

  // ---- small helpers ----
  function escapeHtml(s) {
    if (s==null) return '';
    return String(s)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }
  function escapeAttr(s){ return escapeHtml(s); }

  // Helpers used by the sidebar buttons (from script_f.js but keep as fallback)
  window.setActive = function(button) {
    const buttons = document.querySelectorAll('.nav-button');
    buttons.forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');
  }
  window.loadContent = function(divId) {
    const divs = document.querySelectorAll('.content');
    divs.forEach(div => { div.classList.remove('visible'); div.classList.add('hidden'); });
    const selectedDiv = document.getElementById(divId);
    if (selectedDiv) { selectedDiv.classList.remove('hidden'); selectedDiv.classList.add('visible'); }
  }
})();
</script>
</body>
</html>
