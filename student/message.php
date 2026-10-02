<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Student") {
    header("Location: ../login.php?role=student");
    exit;
}

$studentName = $_SESSION["full_name"] ?? "Student";
$studentId = $_SESSION["profile_id"] ?? 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $notificationId = (int) ($_POST["notification_id"] ?? 0);

    if ($action === "mark_read" && $notificationId) {

        // Only their own message can be marked read.
        $stmt = $conn->prepare("
            UPDATE notification
            SET Status = 'Read'
            WHERE NotificationID = ? AND RecipientRole = 'Student' AND RecipientID = ?
        ");
        $stmt->bind_param("ii", $notificationId, $studentId);
        $stmt->execute();
        $stmt->close();

    }

    header("Location: message.php");
    exit;

}

$stmt = $conn->prepare("
    SELECT NotificationID, Message, DateSent, Status
    FROM notification
    WHERE RecipientRole = 'Student' AND RecipientID = ?
    ORDER BY DateSent DESC
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$messages = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Messages | SIPMS</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>

<body>

<div class="dash-shell">

    <aside class="dash-sidebar" id="dashSidebar">

        <div class="dash-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="dash-nav">
            <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="application.php"><i class='bx bx-file'></i> My Applications</a>
            <a href="report.php"><i class='bx bx-notepad'></i> Submit Report</a>
            <a href="browse.php"><i class='bx bx-search'></i> Browse Internships</a>
            <a href="attendance.php"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
            <a href="message.php" class="active"><i class='bx bx-envelope'></i> Messages</a>
            <a href="profile.php"><i class='bx bx-user'></i> Profile</a>
        </nav>

        <a href="../logout.php" class="dash-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="dash-main">

        <header class="dash-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>Messages</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($studentName); ?></div>
        </header>

        <main class="dash-content">

            <div class="dash-panel">
                <h2>Notifications</h2>
                <?php if (!$messages || $messages->num_rows === 0): ?>
                    <p class="muted">No messages yet.</p>
                <?php else: ?>
                    <?php while ($m = $messages->fetch_assoc()): ?>
                        <?php $unread = $m["Status"] === "Unread"; ?>
                        <div style="padding: 1.4rem 0; border-bottom: 0.1rem solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 1.4rem;">
                            <div>
                                <?php if ($unread): ?>
                                    <span class="badge badge-blue" style="margin-bottom: 0.6rem; display: inline-block;">New</span>
                                <?php endif; ?>
                                <p><?php echo nl2br(htmlspecialchars($m["Message"])); ?></p>
                                <p class="muted" style="margin-top: 0.4rem;"><?php echo htmlspecialchars(date("M j, Y g:ia", strtotime($m["DateSent"]))); ?></p>
                            </div>
                            <?php if ($unread): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo (int) $m["NotificationID"]; ?>">
                                    <button type="submit" class="btn-small">Mark Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

        </main>

    </div>

</div>

<div class="dash-sidebar-backdrop"></div>
<script src="../js/dashboarsd.js"></script>

</body>

</html>