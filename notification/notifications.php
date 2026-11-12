<?php
session_start();
include("dbconnection.php");

// Auth check (every logged in user has their own notifications)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$uid    = intval($_SESSION['user_id']);
$filter = (($_GET['show'] ?? '') == 'unread') ? 'unread' : 'all';

// (? = 'all' OR is_read = 0) means: for "all" show everything, for "unread" only unread
$stmt = $conn->prepare("SELECT notification_id, message, link, is_read, created_at
                        FROM notifications
                        WHERE user_id = ? AND (? = 'all' OR is_read = 0)
                        ORDER BY created_at DESC, notification_id DESC
                        LIMIT 100");
$stmt->bind_param("is", $uid, $filter);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unread = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = $uid AND is_read = 0")->fetch_assoc()['total'];

$page_title = "Notifications";
$page_sub   = "Your latest alerts.";
$active     = "notification";
include("../users/header.php");
?>

<!-- Flash Messages -->
<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_GET['err']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group">
        <a href="notifications.php" class="btn btn-sm <?= $filter == 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
        <a href="notifications.php?show=unread" class="btn btn-sm <?= $filter == 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Unread <?php if ($unread > 0): ?><span class="badge bg-danger ms-1"><?= $unread ?></span><?php endif; ?>
        </a>
    </div>
    <?php if ($unread > 0): ?>
        <a href="mark_all_read.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all me-1"></i> Mark all as read</a>
    <?php endif; ?>
</div>

<div class="panel-card shadow-sm">
    <?php if (empty($rows)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-bell-slash fs-2 d-block mb-2 text-secondary"></i>
            <?= $filter == 'unread' ? 'No unread notifications.' : 'You have no notifications yet.' ?>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($rows as $n): ?>
                <div class="list-group-item d-flex align-items-start gap-3 py-3 <?= $n['is_read'] ? '' : 'bg-primary-subtle' ?>">
                    <div class="<?= $n['is_read'] ? 'text-muted' : 'text-primary' ?> fs-4">
                        <i class="bi <?= $n['is_read'] ? 'bi-bell' : 'bi-bell-fill' ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="<?= $n['is_read'] ? '' : 'fw-semibold' ?>"><?= htmlspecialchars($n['message']) ?></div>
                        <small class="text-muted"><?= date("d M Y, h:i A", strtotime($n['created_at'])) ?></small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <a href="open_notification.php?id=<?= $n['notification_id'] ?>" class="btn btn-outline-primary" title="Open">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="delete_notification.php?id=<?= $n['notification_id'] ?>" class="btn btn-outline-danger"
                           onclick="return confirm('Delete this notification?');" title="Delete">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include("../users/footer.php"); ?>