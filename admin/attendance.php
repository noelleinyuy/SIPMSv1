<?php

require_once "helpers.php";
require_once "../db.php";
require_admin_login();

$adminName = $_SESSION["full_name"] ?? "Admin";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $attendanceId = (int) ($_POST["attendance_id"] ?? 0);

    if ($action === "delete" && $attendanceId) {

        $stmt = $conn->prepare("DELETE FROM attendance WHERE AttendanceID = ?");
        $stmt->bind_param("i", $attendanceId);
        $stmt->execute();
        $stmt->close();

        admin_flash("success", "Attendance record deleted.");
        header("Location: attendance.php");
        exit;

    }

    if ($action === "add" || $action === "update") {

        $studentId = (int) ($_POST["studentId"] ?? 0);
        $checkIn = trim($_POST["checkIn"] ?? "");
        $checkOut = trim($_POST["checkOut"] ?? "");
        $viewAttendance = isset($_POST["viewAttendance"]) ? 1 : 0;

        if (!$studentId || $checkIn === "") {

            admin_flash("error", "Please select a student and a check-in time.");

        } else {

            $checkInSql = str_replace("T", " ", $checkIn) . ":00";
            $checkOutSql = $checkOut !== "" ? str_replace("T", " ", $checkOut) . ":00" : null;

            if ($action === "add") {

                $stmt = $conn->prepare("
                    INSERT INTO attendance (StudentID, CheckInTime, CheckOutTime, ViewAttendance)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("issi", $studentId, $checkInSql, $checkOutSql, $viewAttendance);
                $stmt->execute();
                $stmt->close();

                admin_flash("success", "Attendance record added.");

            } else {

                $stmt = $conn->prepare("
                    UPDATE attendance
                    SET StudentID = ?, CheckInTime = ?, CheckOutTime = ?, ViewAttendance = ?
                    WHERE AttendanceID = ?
                ");
                $stmt->bind_param("issii", $studentId, $checkInSql, $checkOutSql, $viewAttendance, $attendanceId);
                $stmt->execute();
                $stmt->close();

                admin_flash("success", "Attendance record updated.");

            }
        }

        header("Location: attendance.php");
        exit;

    }
}

$students = $conn->query("SELECT studentID, fullName, matricule FROM student ORDER BY fullName ASC");
$studentOptions = $students ? $students->fetch_all(MYSQLI_ASSOC) : [];

$editId = (int) ($_GET["edit"] ?? 0);
$editing = null;

if ($editId) {
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE AttendanceID = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$records = $conn->query("
    SELECT a.AttendanceID, a.CheckInTime, a.CheckOutTime, a.ViewAttendance, s.fullName, s.matricule
    FROM attendance a
    LEFT JOIN student s ON a.StudentID = s.studentID
    ORDER BY a.CheckInTime DESC
");

$flash = admin_get_flash();

// datetime-local inputs need "Y-m-d\TH:i" format, not MySQL's "Y-m-d H:i:s".
function to_datetime_local(mixed $value): string
{
    if (!$value) {
        return "";
    }
    return str_replace(" ", "T", substr((string) $value, 0, 16));
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance | SIPMS Admin</title>

    <link rel="stylesheet" href="../css/boxicons.min.css">
    <link rel="stylesheet" href="../css/admin.css">

</head>

<body>

<div class="admin-shell">

    <aside class="admin-sidebar" id="adminSidebar">

        <div class="admin-logo"><i class='bx bxs-graduation'></i> SIPMS</div>

        <nav class="admin-nav">
            <a href="admin.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
            <a href="student.php"><i class='bx bx-user'></i> Students</a>
            <a href="company.php"><i class='bx bx-building'></i> Companies</a>
            <a href="supervisor.php"><i class='bx bx-chalkboard'></i> Supervisors</a>
            <a href="internship.php"><i class='bx bx-briefcase'></i> Internships</a>
            <a href="application.php"><i class='bx bx-file'></i> Applications</a>
            <a href="attendance.php" class="active"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
            <a href="message.php"><i class='bx bx-envelope'></i> Messages</a>
            <a href="setting.php"><i class='bx bx-cog'></i> Settings</a>
        </nav>

        <a href="../logout.php" class="admin-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="admin-main">

        <header class="admin-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>Attendance</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($adminName); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="admin-panel">
                <h2><?php echo $editing ? "Edit Attendance Record" : "Add Attendance Record"; ?></h2>

                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $editing ? "update" : "add"; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="attendance_id" value="<?php echo (int) $editing["AttendanceID"]; ?>">
                    <?php endif; ?>

                    <label>Student</label>
                    <select name="studentId" required>
                        <option value="">Select a student</option>
                        <?php foreach ($studentOptions as $s): ?>
                            <option value="<?php echo (int) $s["studentID"]; ?>" <?php echo (isset($editing["StudentID"]) && (int) $editing["StudentID"] === (int) $s["studentID"]) ? "selected" : ""; ?>>
                                <?php echo e($s["fullName"]); ?> (<?php echo e($s["matricule"]); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-row">
                        <div>
                            <label>Check-in Time</label>
                            <input type="datetime-local" name="checkIn" required value="<?php echo e(to_datetime_local($editing["CheckInTime"] ?? "")); ?>">
                        </div>
                        <div>
                            <label>Check-out Time</label>
                            <input type="datetime-local" name="checkOut" value="<?php echo e(to_datetime_local($editing["CheckOutTime"] ?? "")); ?>">
                        </div>
                    </div>

                    <label style="display: flex; align-items: center; gap: 0.8rem; font-weight: 600;">
                        <input type="checkbox" name="viewAttendance" style="width: auto;" <?php echo !empty($editing["ViewAttendance"]) ? "checked" : ""; ?>>
                        Viewed
                    </label>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary"><?php echo $editing ? "Save Changes" : "Add Record"; ?></button>
                        <?php if ($editing): ?>
                            <a href="attendance.php" class="btn-small">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>All Attendance Records</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Viewed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records && $records->num_rows > 0): ?>
                            <?php while ($r = $records->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($r["fullName"] ?? "Unknown student"); ?> <span class="muted">(<?php echo e($r["matricule"] ?? "—"); ?>)</span></td>
                                    <td class="muted"><?php echo $r["CheckInTime"] ? e(date("M j, Y g:ia", strtotime($r["CheckInTime"]))) : "—"; ?></td>
                                    <td class="muted"><?php echo $r["CheckOutTime"] ? e(date("M j, Y g:ia", strtotime($r["CheckOutTime"]))) : "—"; ?></td>
                                    <td>
                                        <span class="badge <?php echo $r["ViewAttendance"] ? "badge-green" : "badge-gray"; ?>">
                                            <?php echo $r["ViewAttendance"] ? "Yes" : "No"; ?>
                                        </span>
                                    </td>
                                    <td class="row-actions">
                                        <a href="attendance.php?edit=<?php echo (int) $r["AttendanceID"]; ?>" class="btn-small">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this attendance record?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="attendance_id" value="<?php echo (int) $r["AttendanceID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No attendance records yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>

    </div>

</div>

<script src="../js/admin.js"></script>

</body>

</html>