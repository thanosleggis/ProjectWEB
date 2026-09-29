<?php
session_start();
include 'includes/db_connect.php';

// Public feed endpoint: allow unauthenticated access when requesting presentation announcements
// Usage: index.php?public_feed=presentations&from=YYYY-MM-DD&to=YYYY-MM-DD&format=json|xml
if (isset($_GET['public_feed']) && $_GET['public_feed'] === 'presentations') {
    header('Cache-Control: public, max-age=300');
    // parse and validate dates
    $from = isset($_GET['from']) ? $_GET['from'] : null;
    $to = isset($_GET['to']) ? $_GET['to'] : null;
    // default: next 365 days
    $today = date('Y-m-d');
    if (!$from) $from = $today;
    if (!$to) $to = date('Y-m-d', strtotime('+365 days'));
    // validate date format YYYY-MM-DD
    $isValidDate = function($d) {
        $t = DateTime::createFromFormat('Y-m-d', $d);
        return $t && $t->format('Y-m-d') === $d;
    };
    if (!$isValidDate($from) || !$isValidDate($to)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'invalid_date']);
        exit();
    }

    // fetch announcements from eksetasi_diplwmatikis joined with diplwmatiki and foititis/didaskwn
    try {
        $stmt = $conn->prepare("SELECT e.diplwmatiki_id, e.date AS exam_date, e.time AS exam_time, e.tropos_eksetasis, e.aithousa, e.link AS exam_link,
            d.title AS title, d.foititis_id AS foititis_id, f.name AS foititis_name, f.lastname AS foititis_lastname,
            did.name AS didaskwn_name, did.lastname AS didaskwn_lastname
            FROM eksetasi_diplwmatikis e
            LEFT JOIN diplwmatiki d ON e.diplwmatiki_id = d.id
            LEFT JOIN foititis f ON d.foititis_id = f.id
            LEFT JOIN didaskwn did ON e.didaskwn_id = did.id
            WHERE e.date BETWEEN :from AND :to
            ORDER BY e.date ASC, e.time ASC");
        $stmt->execute([':from' => $from, ':to' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'db_error']);
        exit();
    }

    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'json';
    if ($format === 'xml') {
        header('Content-Type: application/xml; charset=utf-8');
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><presentations/>');
        foreach ($rows as $r) {
            $item = $xml->addChild('presentation');
            $item->addChild('diplwmatiki_id', htmlspecialchars($r['diplwmatiki_id']));
            $item->addChild('title', htmlspecialchars($r['title']));
            $item->addChild('date', htmlspecialchars($r['exam_date']));
            $item->addChild('time', htmlspecialchars($r['exam_time']));
            $item->addChild('tropos', htmlspecialchars($r['tropos_eksetasis']));
            $item->addChild('aithousa', htmlspecialchars($r['aithousa']));
            $item->addChild('link', htmlspecialchars($r['exam_link']));
            $student = $item->addChild('student');
            $student->addChild('id', htmlspecialchars($r['foititis_id']));
            $student->addChild('name', htmlspecialchars($r['foititis_name']));
            $student->addChild('lastname', htmlspecialchars($r['foititis_lastname']));
            $supervisor = $item->addChild('didaskwn');
            $supervisor->addChild('name', htmlspecialchars($r['didaskwn_name']));
            $supervisor->addChild('lastname', htmlspecialchars($r['didaskwn_lastname']));
        }
        echo $xml->asXML();
        exit();
    }

    // default: JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'from' => $from, 'to' => $to, 'count' => count($rows), 'presentations' => $rows]);
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        $user_types = ['foititis', 'didaskwn', 'grammateia'];
        $authenticated = false;

        foreach ($user_types as $type) {
            $stmt = $conn->prepare("SELECT * FROM $type WHERE email = :email AND password = :password");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $authenticated = true;
                $_SESSION['user'] = $user;
                $_SESSION['user_type'] = $type;

                // Save the corresponding ID to the session
                $_SESSION['id'] = $user['id'];
                break;
            }
        }

        if ($authenticated) {
            // Redirect based on user type without passing the id in the URL
            switch ($_SESSION['user_type']) {
                case 'foititis':
                    header('Location: homepage_f.php');
                    break;
                case 'didaskwn':
                    header('Location: homepage_d.php');
                    break;
                case 'grammateia':
                    header('Location: homepage_g.php');
                    break;
                default:
                    $error = 'Invalid user type.';
            }
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/index_style.css">
</head>
<body>
    <div class="background-center-third"></div>
        <!-- Social Media Header -->
        <header class="social-header">
            <div class="header-left">
                <img src="../public/icons/uop_logo.png" alt="University Logo" class="header-icon">
                <span class="header-title">Πανεπιστήμιο Πατρών</span>
            </div>
            <nav>
                <ul>
                    <li>
                        <a href="https://www.instagram.com/upatras/" target="_blank">
                            <img src="../public/icons/instagram.svg" alt="Instagram">
                        </a>
                    </li>
                    <li>
                        <a href="https://www.facebook.com/upatras" target="_blank">
                            <img src="../public/icons/facebook.svg" alt="Facebook">
                        </a>
                    </li>
                    <li>
                        <a href="https://x.com/upatras" target="_blank">
                            <img src="../public/icons/twitter.svg" alt="Twitter">
                        </a>
                    </li>
                    <li>
                        <a href="https://www.youtube.com/channel/UCnrADx55JNX5T9wNGWHbAPg" target="_blank">
                            <img src="../public/icons/youtube.svg" alt="YouTube">
                        </a>
                    </li>
                </ul>
            </nav>
        </header>
    <div class="login-container">
        <div class="login-header">
            <img src="../public/icons/uop_logo.png" alt="University Logo" class="logo">
            <h1>Είσοδος Χρήστη</h1>
        </div>
        <form class="login-form" action="" method="POST">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Εισάγετε το email σας" required>
            
            <label for="password">Κωδικός</label>
            <input type="password" id="password" name="password" placeholder="Εισάγετε τον κωδικό σας" required>
            <?php if (!empty($error)): ?>
                <p class="error-message"><?= htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <button type="submit">Login</button>
        </form>
        <p class="register-link">Δεν έχετε λογαριασμό; <a href="homepage_d.php">Εγγραφείτε εδώ!</a></p>
    </div>

    <script> 
        // Login form validation
        document.querySelector(".login-form").addEventListener("submit", (e) => {
            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value.trim();
        
            if (!email || !password) {
                e.preventDefault();
                alert("Please fill out all fields.");
            }
        });
    </script>
</body>
</html>