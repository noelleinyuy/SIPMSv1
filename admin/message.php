<?php

require_once "helpers.php";
require_once "../db.php";
require_admin_login();

$adminName = $_SESSION["full_name"] ?? "Admin";
$adminId = $_SESSION["profile_id"] ?? null;

$recipientTables = [
    "Student" => ["table" => "student", "id" => "studentID", "name" => "fullName", "extra" => "matricule"],
    "Company" => ["table" => "company", "id" => "CompanyID", "name" => "Name", "extra" => null],
    "Supervisor" => ["table" => "supervisor", "id" => "SupervisorID", "name" => "FullName", "extra" => "Department"],
];

function get_recipient_label(mysqli $conn, array $recipientTables, ?string $role, ?int $id): string
{
    if (!$role || !$id || !isset($recipientTables[$role])) {
        return "—";
    }

    $config = $recipientTables[$role];
    $stmt = $conn->prepare("SELECT {$config['name']} AS Name FROM {$config['table']} WHERE {$config['id']} = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? $row["Name"] : "Unknown " . strtolower($role);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $notificationId = (int) ($_POST["notification_id"] ?? 0);

    if ($action === "delete" && $notificationId) {

        $stmt = $conn->prepare("DELETE FROM notification WHERE NotificationID = ?");
        $stmt->bind_param("i", $notificationId);
        $stmt->execute();
        $stmt->close();

        admin_flash("success", "Message deleted.");
        header("Location: message.php");
        exit;

    }

    if ($action === "toggle_status" && $notificationId) {

        $conn->query("UPDATE notification SET Status = IF(Status = 'Unread', 'Read', 'Unread') WHERE NotificationID = $notificationId");
        header("Location: message.php");
        exit;

    }

    if ($action === "add" || $action === "update") {

        $message = trim($_POST["message"] ?? "");
        $recipientRole = $_POST["recipientRole"] ?? "";
        $recipientId = (int) ($_POST["recipientId"] ?? 0);

        if ($message === "" || !isset($recipientTables[$recipientRole]) || !$recipientId) {

            admin_flash("error", "Please write a message and choose a valid recipient.");

        } elseif ($action === "add") {

            $stmt = $conn->prepare("
                INSERT INTO notification (Message, Status, AdminID, RecipientRole, RecipientID)
                VALUES (?, 'Unread', ?, ?, ?)
            ");
            $stmt->bind_param("sisi", $message, $adminId, $recipientRole, $recipientId);
            $stmt->execute();
            $stmt->close();

            admin_flash("success", "Message sent.");

        } else {

            $stmt = $conn->prepare("
                UPDATE notification
                SET Message = ?, RecipientRole = ?, RecipientID = ?
                WHERE NotificationID = ?
            ");
            $stmt->bind_param("ssii", $message, $recipientRole, $recipientId, $notificationId);
            $stmt->execute();
            $stmt->close();

            admin_flash("success", "Message updated.");

        }

        header("Location: message.php");
        exit;

    }
}

// Recipient option lists for the dynamic dropdowns.
$recipientOptions = [];
foreach ($recipientTables as $role => $config) {
    $extraSql = $config["extra"] ? ", {$config['extra']} AS Extra" : ", NULL AS Extra";
    $result = $conn->query("SELECT {$config['id']} AS ID, {$config['name']} AS Name{$extraSql} FROM {$config['table']} ORDER BY {$config['name']} ASC");
    $recipientOptions[$role] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$editId = (int) ($_GET["edit"] ?? 0);
$editing = null;

if ($editId) {
    $stmt = $conn->prepare("SELECT * FROM notification WHERE NotificationID = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$notifications = $conn->query("SELECT * FROM notification ORDER BY DateSent DESC");
$flash = admin_get_flash();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Messages | SIPMS Admin</title>

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
            <a href="attendance.php"><i class='bx bx-time-five'></i> Attendance</a>
            <a href="task.php"><i class='bx bx-list-check'></i> Tasks</a>
            <a href="message.php" class="active"><i class='bx bx-envelope'></i> Messages</a>
            <a href="setting.php"><i class='bx bx-cog'></i> Settings</a>
        </nav>

        <a href="../logout.php" class="admin-logout"><i class='bx bx-log-out'></i> Logout</a>

    </aside>

    <div class="admin-main">

        <header class="admin-topbar">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu">
                <i class='bx bx-menu'></i>
            </button>
            <h1>Messages</h1>
            <div class="admin-user"><i class='bx bx-user-circle'></i> <?php echo e($adminName); ?></div>
        </header>

        <main class="admin-content">

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></div>
            <?php endif; ?>

            <div class="admin-panel">
                <h2><?php echo $editing ? "Edit Message" : "Send a Message"; ?></h2>

                <form method="POST" class="admin-form" id="messageForm">
                    <input type="hidden" name="action" value="<?php echo $editing ? "update" : "add"; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="notification_id" value="<?php echo (int) $editing["NotificationID"]; ?>">
                    <?php endif; ?>

                    <label>Recipient Type</label>
                    <select name="recipientRole" id="recipientRole" required>
                        <option value="">Select type</option>
                        <?php foreach (array_keys($recipientTables) as $role): ?>
                            <option value="<?php echo e($role); ?>" <?php echo ($editing["RecipientRole"] ?? "") === $role ? "selected" : ""; ?>>
                                <?php echo e($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php foreach ($recipientOptions as $role => $options): ?>
                        <div class="recipient-picker" data-role="<?php echo e($role); ?>">
                            <label>Select <?php echo e($role); ?></label>
                            <select name="recipientId_<?php echo e($role); ?>" class="recipient-select">
                                <option value="">Choose one</option>
                                <?php foreach ($options as $opt): ?>
                                    <option value="<?php echo (int) $opt["ID"]; ?>" <?php echo (($editing["RecipientRole"] ?? "") === $role && (int) ($editing["RecipientID"] ?? 0) === (int) $opt["ID"]) ? "selected" : ""; ?>>
                                        <?php echo e($opt["Name"]); ?><?php echo $opt["Extra"] ? " (" . e($opt["Extra"]) . ")" : ""; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>

                    <input type="hidden" name="recipientId" id="recipientId">

                    <label>Message</label>
                    <textarea name="message" rows="4" required><?php echo e($editing["Message"] ?? ""); ?></textarea>

                    <div class="row-actions">
                        <button type="submit" class="btn-primary"><?php echo $editing ? "Save Changes" : "Send Message"; ?></button>
                        <?php if ($editing): ?>
                            <a href="message.php" class="btn-small">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>All Messages</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Message</th>
                            <th>Recipient</th>
                            <th>Sent</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($notifications && $notifications->num_rows > 0): ?>
                            <?php while ($n = $notifications->fetch_assoc()): ?>
                                <tr>
                                    <td class="truncate"><?php echo e($n["Message"]); ?></td>
                                    <td class="muted">
                                        <?php echo e($n["RecipientRole"] ?? "—"); ?>:
                                        <?php echo e(get_recipient_label($conn, $recipientTables, $n["RecipientRole"], $n["RecipientID"] ? (int) $n["RecipientID"] : null)); ?>
                                    </td>
                                    <td class="muted"><?php echo $n["DateSent"] ? e(date("M j, g:ia", strtotime($n["DateSent"]))) : "—"; ?></td>
                                    <td>
                                        <span class="badge <?php echo $n["Status"] === "Unread" ? "badge-blue" : "badge-gray"; ?>">
                                            <?php echo e($n["Status"]); ?>
                                        </span>
                                    </td>
                                    <td class="row-actions">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="notification_id" value="<?php echo (int) $n["NotificationID"]; ?>">
                                            <button type="submit" class="btn-small">Mark <?php echo $n["Status"] === "Unread" ? "Read" : "Unread"; ?></button>
                                        </form>
                                        <a href="message.php?edit=<?php echo (int) $n["NotificationID"]; ?>" class="btn-small">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this message?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="notification_id" value="<?php echo (int) $n["NotificationID"]; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No messages yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>

    </div>

</div>

<script src="../js/admin.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {

    const roleSelect = document.getElementById("recipientRole");
    const pickers = document.querySelectorAll(".recipient-picker");
    const hiddenId = document.getElementById("recipientId");
    const form = document.getElementById("messageForm");

    function updatePickers() {
        const selected = roleSelect.value;
        pickers.forEach((picker) => {
            picker.style.display = picker.dataset.role === selected ? "" : "none";
        });
    }

    roleSelect.addEventListener("change", updatePickers);
    updatePickers();

    form.addEventListener("submit", () => {
        const selected = roleSelect.value;
        const activeSelect = document.querySelector(`.recipient-picker[data-role="${selected}"] .recipient-select`);
        hiddenId.value = activeSelect ? activeSelect.value : "";
    });

});
</script>

</body>

</html>