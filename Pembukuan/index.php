<?php
$servername = "localhost:8111";
$username = "root";
$password = "";
$dbname = "finance_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$itemsPerPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM transactions");
$totalEntries = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($totalEntries / $itemsPerPage);

// Handle export CSV
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_export.csv"');
    
    $output = fopen("php://output", "w");
    fputcsv($output, ['ID', 'Type', 'Description', 'Amount', 'Created At']);
    
    $result = $conn->query("SELECT * FROM transactions");
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// Handle import CSV with validation
$importMessage = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['import_file'])) {
    $file = fopen($_FILES['import_file']['tmp_name'], "r");
    fgetcsv($file); // Skip header
    
    while ($row = fgetcsv($file)) {
        if (count($row) < 4) continue; // Skip invalid rows
        $stmt = $conn->prepare("INSERT INTO transactions (type, description, amount, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $row[1], $row[2], $row[3], $row[4]);
        $stmt->execute();
    }
    fclose($file);
    $importMessage = "Data berhasil diimport!";
}

// Fetch paginated data
$result = $conn->query("SELECT * FROM transactions LIMIT $offset, $itemsPerPage");
$entries = $result->fetch_all(MYSQLI_ASSOC);

// Fetch all data for the chart
$chartQuery = $conn->query("SELECT type, SUM(amount) as total FROM transactions GROUP BY type");
$chartData = [];
while ($row = $chartQuery->fetch_assoc()) {
    $chartData[$row['type']] = $row['total'];
}
$totalIncome = $chartData['income'] ?? 0;
$totalExpense = $chartData['expense'] ?? 0;
$netProfit = $totalIncome - $totalExpense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba Rugi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow-lg p-4">
            <h2 class="text-center text-primary mb-4">Laporan Laba Rugi</h2>
            <?php if ($importMessage): ?>
                <div class="alert alert-success"> <?= $importMessage ?> </div>
            <?php endif; ?>
            <canvas id="profitChart" height="150"></canvas>
            <script>
                var ctx = document.getElementById('profitChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Pendapatan', 'Pengeluaran', 'Laba Bersih'],
                        datasets: [{
                            label: 'Jumlah (Rp)',
                            data: [<?= $totalIncome ?>, <?= $totalExpense ?>, <?= $netProfit ?>],
                            backgroundColor: ['green', 'red', 'blue']
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 10000 // Adjust the scale increment
                                }
                            }
                        }
                    }
                });
            </script>
            <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 my-3">
                <input type="file" name="import_file" class="form-control w-50" required>
                <button type="submit" class="btn btn-secondary">Import CSV</button>
            </form>
            <form method="POST" class="mb-3 d-flex">
                <button type="submit" name="export" class="btn btn-primary">Export CSV</button>
            </form>
            <p>Menampilkan <?= count($entries) ?> dari <?= $totalEntries ?> transaksi</p>
            <table class="table table-bordered table-hover mt-3">
                <thead class="table-primary">
                    <tr>
                        <th>Keterangan</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['description']) ?></td>
                            <td class="fw-bold text-<?= $entry['type'] == 'income' ? 'success' : 'danger' ?>">
                                Rp <?= number_format($entry['amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&limit=<?= $itemsPerPage ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
