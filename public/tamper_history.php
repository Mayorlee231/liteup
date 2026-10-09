<?php
require_once '../includes/security.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch tamper code history
$stmt = $conn->prepare("SELECT * FROM tamper_code_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tamper Code History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="d-flex flex-column min-vh-100">

<?php include('navbar.php'); ?>

<main class="container flex-grow-1 mt-5">
    <div class="card p-4">
        <h4>Your Tamper Code Requests</h4>
        <table class="table table-bordered mt-4">
            <thead>
                <tr>
                    <th>Meter Number</th>
                    <th>Amount (₦)</th>
                    <th>Charge (₦)</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $row['meter_number']); ?></td>
                            <td><?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo number_format($row['charge'], 2); ?></td>
                            <td>
                                <?php
                                    $badge = "secondary";
                                    if ($row['status'] == 'approved') $badge = "success";
                                    elseif ($row['status'] == 'declined') $badge = "danger";
                                ?>
                                <span class="badge bg-<?php echo htmlspecialchars($badge); ?>"><?php echo htmlspecialchars(ucfirst((string) $row['status'])); ?></span>
                            </td>
                            <td><?php echo date("d M Y, h:i A", strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">No requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
