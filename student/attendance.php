<?php

session_start();
require_once "../db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Student") {
    header("Location: ../login.php?role=student");
    exit;
}

$studentName = $_SESSION["full_name"] ?? "Student";
$studentId = $_SESSION["profile_id"] ?? 0;

function flash(string $type, string $message): void
{
    $_SESSION["student_flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION["student_flash"])) {
        $f = $_SESSION["student_flash"];
        unset($_SESSION["student_flash"]);
        return $f;
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "checkin") {

        $stmt = $conn->prepare("SELECT AttendanceID FROM attendance WHERE StudentID = ? AND CheckOutTime IS NULL LIMIT 1");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $hasOpen = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($hasOpen) {

            flash("error", "You're already checked in — check out first.");

        } else {

            $stmt = $conn->prepare("INSERT INTO attendance (StudentID, CheckInTime, ViewAttendance) VALUES (?, NOW(), 0)");
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            $stmt->close();

            flash("success", "Checked in.");

        }

        header("Location: attendance.php");
        exit;

    }

    if ($action === "checkout") {

        $stmt = $conn->prepare("
            UPDATE attendance
            SET CheckOutTime = NOW()
            WHERE StudentID = ? AND CheckOutTime IS NULL
            ORDER BY CheckInTime DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        flash($affected > 0 ? "success" : "error", $affected > 0 ? "Checked out." : "You're not currently checked in.");
        header("Location: attendance.php");
        exit;

    }
}

$stmt = $conn->prepare("
    SELECT AttendanceID FROM attendance
    WHERE StudentID = ? AND CheckOutTime IS NULL
    ORDER BY CheckInTime DESC
    LIMIT 1
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$openSession = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("
    SELECT AttendanceID, CheckInTime, CheckOutTime
    FROM attendance
    WHERE StudentID = ?
    ORDER BY CheckInTime DESC
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

$flash = get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance | SIPMS</title>

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
            <a href="attendance.php" class="active"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
            <a href="message.php"><i class='bx bx-envelope'></i> Messages</a>
            <a href="profile.php"><i class='bx bx-user'></i> Profile</a>
        </nav>

        <a href="../logout.php" class="dash-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="dash-main">

        <header class="dash-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>Attendance</h1>
            <div class="dash-user"><i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($studentName); ?></div>
        </header>

        <main class="dash-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="dash-panel">
                <h2>Today</h2>

                <?php if ($openSession): ?>
                    <p class="muted" style="margin-bottom: 1.2rem;">You're currently checked in.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="checkout">
                        <button type="submit" class="btn-primary"><i class='bx bx-log-out'></i> Check Out</button>
                    </form>
                <?php else: ?>
                    <p class="muted" style="margin-bottom: 1.2rem;">You're not checked in right now.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="checkin">
                        <button type="submit" class="btn-primary"><i class='bx bx-log-in'></i> Check In</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="dash-panel">
                <h2>Attendance History</h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records && $records->num_rows > 0): ?>
                            <?php while ($r = $records->fetch_assoc()): ?>
                                <?php
                                $duration = "—";
                                if ($r["CheckInTime"] && $r["CheckOutTime"]) {
                                    $minutes = (strtotime($r["CheckOutTime"]) - strtotime($r["CheckInTime"])) / 60;
                                    $hours = floor($minutes / 60);
                                    $mins = (int) ($minutes % 60);
                                    $duration = "{$hours}h {$mins}m";
                                }
                                ?>
                                <tr>
                                    <td class="muted"><?php echo $r["CheckInTime"] ? htmlspecialchars(date("M j, Y", strtotime($r["CheckInTime"]))) : "—"; ?></td>
                                    <td class="muted"><?php echo $r["CheckInTime"] ? htmlspecialchars(date("g:ia", strtotime($r["CheckInTime"]))) : "—"; ?></td>
                                    <td class="muted">
                                        <?php if ($r["CheckOutTime"]): ?>
                                            <?php echo htmlspecialchars(date("g:ia", strtotime($r["CheckOutTime"]))); ?>
                                        <?php else: ?>
                                            <span class="badge badge-blue">In progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="muted"><?php echo $duration; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No attendance records yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>

    </div>

</div>

<div class="dash-sidebar-backdrop"></div>
<script src="../js/dashboarsd.js"></script>

</body>

</html>