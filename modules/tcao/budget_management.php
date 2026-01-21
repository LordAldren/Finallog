<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}
require_once '../../config/db_connect.php';
$message = '';

// --- PANG-HANDLE NG CSV DOWNLOAD (EXPENSES) ---
if (isset($_GET['download_expenses_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expenses_history_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Category', 'Description', 'Amount']);
    $result = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [$row['id'], $row['expense_date'], $row['expense_category'], $row['description'], $row['amount']]);
        }
    }
    fclose($output);
    exit;
}

// --- PANG-HANDLE NG CSV DOWNLOAD (BUDGETS) ---
if (isset($_GET['download_budgets_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=budgets_history_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Title', 'Start Date', 'End Date', 'Amount']);
    $result = $conn->query("SELECT * FROM budgets ORDER BY start_date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [$row['id'], $row['budget_title'], $row['start_date'], $row['end_date'], $row['amount']]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


// --- Handle Expense CRUD (Retained as per functionality) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_expense'])) {
    $id = $_POST['expense_id'];
    $category = $_POST['expense_category'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $expense_date = $_POST['expense_date'];

    if (empty($id)) { // Insert
        $sql = "INSERT INTO expenses (expense_category, description, amount, expense_date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssds", $category, $description, $amount, $expense_date);
    } else { // Update
        $sql = "UPDATE expenses SET expense_category=?, description=?, amount=?, expense_date=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdsi", $category, $description, $amount, $expense_date, $id);
    }
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Expense saved successfully!</div>";
    } else {
        $message = "<div class='message-banner error'>Error saving expense.</div>";
    }
    $stmt->close();
}

// --- Fetch Data ---
$budgets = $conn->query("SELECT * FROM budgets ORDER BY start_date DESC");
$expenses = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");

// For Budget Overview
$current_budget_query = $conn->query("SELECT * FROM budgets WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1");
$current_budget = $current_budget_query->fetch_assoc();
$total_expenses_this_period = 0;
$expense_breakdown_data = [];

if ($current_budget) {
    // Get total expenses
    $exp_stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $exp_stmt->bind_param("ss", $current_budget['start_date'], $current_budget['end_date']);
    $exp_stmt->execute();
    $total_expenses_this_period = $exp_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $exp_stmt->close();

    // Get expense breakdown for chart
    $breakdown_stmt = $conn->prepare("SELECT expense_category, SUM(amount) as total_amount FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY expense_category");
    $breakdown_stmt->bind_param("ss", $current_budget['start_date'], $current_budget['end_date']);
    $breakdown_stmt->execute();
    $breakdown_result = $breakdown_stmt->get_result();
    while ($row = $breakdown_result->fetch_assoc()) {
        $expense_breakdown_data[] = $row;
    }
    $breakdown_stmt->close();
}
$expense_breakdown_json = json_encode($expense_breakdown_data);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management | TCAO</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Budget & Expense Management</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
            <div class="card" style="margin: 0;">
                <h3>Current Budget Overview</h3>
                <?php if ($current_budget):
                    $remaining = $current_budget['amount'] - $total_expenses_this_period;
                    $percentage_used = ($current_budget['amount'] > 0) ? ($total_expenses_this_period / $current_budget['amount']) * 100 : 0;
                    ?>
                    <p><strong>Period:</strong>
                        <?php echo date("M d, Y", strtotime($current_budget['start_date'])) . " - " . date("M d, Y", strtotime($current_budget['end_date'])); ?>
                    </p>
                    <p><strong>Total Budget:</strong> ₱<?php echo number_format($current_budget['amount'], 2); ?></p>
                    <p><strong>Expenses to Date:</strong> ₱<?php echo number_format($total_expenses_this_period, 2); ?></p>
                    <p><strong>Remaining Budget:</strong> <span
                            style="color: <?php echo ($remaining < 0) ? 'var(--danger-color)' : 'var(--success-color)'; ?>; font-weight: bold;">₱<?php echo number_format($remaining, 2); ?></span>
                    </p>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo min(100, $percentage_used); ?>%;"></div>
                    </div>
                <?php else: ?>
                    <p>No active budget for the current period. Budgets are set by the Finance department.</p>
                <?php endif; ?>
            </div>
            <div class="card" style="margin: 0;">
                <h3>Expense Breakdown</h3>
                <canvas id="expenseChart" style="max-height: 200px;"></canvas>
            </div>
        </div>


        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Budget History (View-Only)</h3>
                <a href="budget_management.php?download_budgets_csv=true" class="btn btn-success">Download CSV</a>
            </div>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($budgets->num_rows > 0):
                            mysqli_data_seek($budgets, 0); ?>
                            <?php while ($row = $budgets->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['budget_title']); ?></td>
                                    <td><?php echo date("M d, Y", strtotime($row['start_date'])); ?></td>
                                    <td><?php echo date("M d, Y", strtotime($row['end_date'])); ?></td>
                                    <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No budgets found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="viewExpenseModal" class="modal">
            <div class="modal-content" style="max-width: 500px;"><span class="close-button">&times;</span>
                <h2>Expense Details</h2>
                <div id="viewExpenseBody"></div>
            </div>
        </div>

        <div class="card" id="updateDeliveryCard">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 id="expenseCardTitle">Add Expense</h3>
                <span class="close-button" id="closeExpenseCard">&times;</span>
            </div>
            <form action='budget_management.php' method='POST' style="margin-top: 1rem;">
                <input type="hidden" name="expense_id" id="expense_id">
                <div class='form-group'>
                    <label>Category</label>
                    <select name='expense_category' id="expense_category" class='form-control' required>
                        <option value="Fuel">Fuel</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Tolls">Tolls</option>
                        <option value="Salaries">Salaries</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Office Supplies">Office Supplies</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class='form-group'><label>Description</label><input type='text' id="description" name='description'
                        class='form-control' value=""></div>
                <div class='form-group'><label>Amount</label><input type='number' step='0.01' id="amount" name='amount'
                        class='form-control' value="" required></div>
                <div class='form-group'><label>Date</label><input type='date' name='expense_date' id="expense_date"
                        class='form-control' value="" required></div>
                <div class='form-actions'>
                    <button type='submit' name='save_expense' class='btn btn-primary'>Save Expense</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Expense History</h3>
                <div>
                    <button id="addExpenseBtn" class="btn btn-primary">Add Expense</button>
                    <a href="budget_management.php?download_expenses_csv=true" class="btn btn-success">Download CSV</a>
                </div>
            </div>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($expenses->num_rows > 0):
                            mysqli_data_seek($expenses, 0); ?>
                            <?php while ($row = $expenses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date("M d, Y", strtotime($row['expense_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['expense_category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-info btn-sm viewExpenseBtn"
                                            data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>View</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">No expenses recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Expense Card Logic
            const expenseCard = document.getElementById('updateDeliveryCard');
            const addExpenseBtn = document.getElementById('addExpenseBtn');
            const closeExpenseCard = document.getElementById('closeExpenseCard');

            if (addExpenseBtn) {
                addExpenseBtn.addEventListener('click', () => {
                    expenseCard.style.display = 'block';
                    expenseCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
            if (closeExpenseCard) {
                closeExpenseCard.addEventListener('click', () => {
                    expenseCard.style.display = 'none';
                });
            }

            const viewExpenseModal = document.getElementById('viewExpenseModal');
            document.querySelectorAll('.viewExpenseBtn').forEach(button => {
                button.addEventListener('click', () => {
                    const details = JSON.parse(button.dataset.details);
                    const expenseDate = new Date(details.expense_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

                    document.getElementById('viewExpenseBody').innerHTML = `
                    <p><strong>Date:</strong> ${expenseDate}</p>
                    <p><strong>Category:</strong> ${details.expense_category}</p>
                    <p><strong>Description:</strong> ${details.description || 'N/A'}</p>
                    <p><strong>Amount:</strong> ₱${parseFloat(details.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                `;
                    viewExpenseModal.style.display = "block";
                });
            });


            // Chart.js Logic
            const expenseData = <?php echo $expense_breakdown_json; ?>;
            const expenseChartCtx = document.getElementById('expenseChart');
            if (expenseChartCtx && expenseData.length > 0) {
                const labels = expenseData.map(item => item.expense_category);
                const data = expenseData.map(item => item.total_amount);
                new Chart(expenseChartCtx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Expenses',
                            data: data,
                            backgroundColor: [
                                '#4A6CF7', '#F59E0B', '#10B981', '#EF4444', '#64748B', '#3B82F6', '#8B5CF6'
                            ],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                            }
                        }
                    }
                });
            } else if (expenseChartCtx) {
                const ctx = expenseChartCtx.getContext('2d');
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('No expense data for the current budget period.', expenseChartCtx.width / 2, expenseChartCtx.height / 2);
            }
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>