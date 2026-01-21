<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}

// RBAC check - Admin lang ang pwedeng pumasok dito
if ($_SESSION['role'] !== 'admin') {
    if ($_SESSION['role'] === 'driver') {
        header("location: ../mfc/mobile_app.php");
    } else {
        header("location: ../../landpage.php");
    }
    exit;
}

require_once '../../config/db_connect.php';

// Fetch Data for AI and Tables
// BAGONG QUERY: Kinukuha na ang fuel, labor, at tolls cost para sa mas magandang AI model
$cost_prediction_data = $conn->query("
    SELECT tc.fuel_cost, tc.labor_cost, tc.tolls_cost, tc.total_cost 
    FROM trip_costs tc
    JOIN trips t ON tc.trip_id = t.id
    WHERE t.status = 'Completed' AND tc.total_cost > 0
    ORDER BY t.pickup_time ASC
");
$prediction_json = json_encode($cost_prediction_data->fetch_all(MYSQLI_ASSOC));

$daily_costs_table_result = $conn->query("
    SELECT
        DATE(t.pickup_time) as trip_date,
        SUM(tc.fuel_cost) as total_fuel,
        SUM(tc.labor_cost) as total_labor,
        SUM(tc.tolls_cost) as total_tolls,
        SUM(tc.total_cost) as grand_total
    FROM trip_costs tc
    JOIN trips t ON tc.trip_id = t.id
    GROUP BY DATE(t.pickup_time)
    ORDER BY trip_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Analysis | TCAO</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Cost Analysis & Optimization</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <div class="card">
            <h3>Automated AI Cost Forecaster</h3>
            <p>This AI model analyzes your historical transaction data to predict the likely cost of the <strong>next
                    upcoming trip</strong>. It automatically learns from patterns in fuel, labor, and toll expenses.</p>

            <div id="ai-status" style="margin-top: 1rem; font-weight: 500; color: var(--info-color);">Initializing
                analysis...</div>

            <div id="prediction-output"
                style="margin-top: 1.5rem; padding: 1.5rem; background-color: #f8f9fa; border-radius: 8px; display: none;">
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; text-align: center;">
                    <div>
                        <small style="color: #6c757d; text-transform: uppercase; font-weight: 600;">Predicted
                            Fuel</small>
                        <div id="pred-fuel" style="font-size: 1.5rem; font-weight: 700; color: #4A6CF7;">--</div>
                    </div>
                    <div>
                        <small style="color: #6c757d; text-transform: uppercase; font-weight: 600;">Predicted
                            Labor</small>
                        <div id="pred-labor" style="font-size: 1.5rem; font-weight: 700; color: #4A6CF7;">--</div>
                    </div>
                    <div>
                        <small style="color: #6c757d; text-transform: uppercase; font-weight: 600;">Predicted
                            Tolls</small>
                        <div id="pred-tolls" style="font-size: 1.5rem; font-weight: 700; color: #4A6CF7;">--</div>
                    </div>
                    <div style="border-left: 2px solid #dee2e6; padding-left: 1.5rem;">
                        <small style="color: #6c757d; text-transform: uppercase; font-weight: 600;">Total
                            Forecast</small>
                        <div id="pred-total" style="font-size: 2rem; font-weight: 800; color: #10B981;">--</div>
                    </div>
                </div>
                <div style="margin-top: 1rem; text-align: center; font-size: 0.9rem; color: #6c757d;">
                    Based on recent trends from your transaction history.
                </div>
            </div>
        </div>
        <div class="card">
            <h3>Daily Cost Breakdown Engine</h3>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Fuel</th>
                            <th>Total Labor</th>
                            <th>Total Tolls</th>
                            <th>Grand Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daily_costs_table_result && $daily_costs_table_result->num_rows > 0): ?>
                            <?php while ($row = $daily_costs_table_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo date("M d, Y", strtotime($row['trip_date'])); ?></strong></td>
                                    <td>₱<?php echo number_format($row['total_fuel'], 2); ?></td>
                                    <td>₱<?php echo number_format($row['total_labor'], 2); ?></td>
                                    <td>₱<?php echo number_format($row['total_tolls'], 2); ?></td>
                                    <td><strong>₱<?php echo number_format($row['grand_total'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">No daily cost data found. Add trip costs to see analysis.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- AUTOMATED AI LOGIC ---
            let predictionData = <?php echo $prediction_json; ?>;
            let costModel;
            const aiStatus = document.getElementById('ai-status');
            const outputDiv = document.getElementById('prediction-output');

            if (predictionData.length < 5) {
                // Fallback sample data if database is empty
                const sampleData = [
                    { fuel_cost: "2500.00", labor_cost: "800.00", tolls_cost: "1200.00", total_cost: "4700.00" },
                    { fuel_cost: "1800.50", labor_cost: "600.00", tolls_cost: "900.00", total_cost: "3450.50" },
                    { fuel_cost: "3000.00", labor_cost: "1000.00", tolls_cost: "1500.00", total_cost: "5800.00" },
                    { fuel_cost: "1550.25", labor_cost: "500.00", tolls_cost: "700.00", total_cost: "2850.25" },
                    { fuel_cost: "2200.00", labor_cost: "750.00", tolls_cost: "1100.00", total_cost: "4250.00" },
                    { fuel_cost: "1950.00", labor_cost: "650.00", tolls_cost: "950.00", total_cost: "3750.00" }
                ];
                predictionData.push(...sampleData);
                aiStatus.innerHTML = `<span style='color: var(--warning-color);'>Notice: Using sample data. Add more trip records for better accuracy.</span>`;
            }

            async function trainAndPredict() {
                aiStatus.textContent = 'Analyzing historical data patterns...';

                try {
                    // Prepare Data
                    const features = predictionData.map(d => [
                        parseFloat(d.fuel_cost) || 0,
                        parseFloat(d.labor_cost) || 0,
                        parseFloat(d.tolls_cost) || 0
                    ]);
                    const labels = predictionData.map(d => parseFloat(d.total_cost));

                    const featureTensor = tf.tensor2d(features, [features.length, 3]);
                    const labelTensor = tf.tensor2d(labels, [labels.length, 1]);

                    // Normalize Data
                    const inputMax = featureTensor.max(0);
                    const inputMin = featureTensor.min(0);
                    const labelMax = labelTensor.max();
                    const labelMin = labelTensor.min();

                    const normalizedInputs = featureTensor.sub(inputMin).div(inputMax.sub(inputMin));
                    const normalizedLabels = labelTensor.sub(labelMin).div(labelMax.sub(labelMin));

                    // Create Model
                    const model = tf.sequential();
                    model.add(tf.layers.dense({ inputShape: [3], units: 32, activation: 'relu' }));
                    model.add(tf.layers.dense({ units: 16, activation: 'relu' }));
                    model.add(tf.layers.dense({ units: 1, activation: 'sigmoid' })); // Sigmoid for 0-1 output

                    model.compile({
                        optimizer: tf.train.adam(0.01),
                        loss: 'meanSquaredError'
                    });

                    // Train Model
                    await model.fit(normalizedInputs, normalizedLabels, {
                        epochs: 50,
                        shuffle: true,
                        callbacks: {
                            onEpochEnd: (epoch, logs) => {
                                if (epoch % 10 === 0) {
                                    // Optional: Update status during training
                                }
                            }
                        }
                    });

                    // --- AUTOMATIC PREDICTION LOGIC ---
                    // Instead of user input, we calculate the average of past costs to predict the "next typical trip"
                    const avgFuel = features.reduce((a, b) => a + b[0], 0) / features.length;
                    const avgLabor = features.reduce((a, b) => a + b[1], 0) / features.length;
                    const avgTolls = features.reduce((a, b) => a + b[2], 0) / features.length;

                    // Prepare input tensor for prediction based on averages
                    const inputTensor = tf.tensor2d([[avgFuel, avgLabor, avgTolls]], [1, 3]);
                    const normalizedInput = inputTensor.sub(inputMin).div(inputMax.sub(inputMin));

                    const prediction = model.predict(normalizedInput);

                    // De-normalize prediction
                    const unNormalizedPrediction = prediction.mul(labelMax.sub(labelMin)).add(labelMin);
                    const predictedTotal = unNormalizedPrediction.dataSync()[0];

                    // Display Results
                    aiStatus.textContent = 'Analysis Complete. Forecast generated.';
                    aiStatus.style.color = 'var(--success-color)';

                    document.getElementById('pred-fuel').textContent = '₱' + avgFuel.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    document.getElementById('pred-labor').textContent = '₱' + avgLabor.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    document.getElementById('pred-tolls').textContent = '₱' + avgTolls.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    document.getElementById('pred-total').textContent = '₱' + predictedTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    outputDiv.style.display = 'block';

                } catch (error) {
                    console.error("AI Error:", error);
                    aiStatus.textContent = `System error during analysis: ${error.message}`;
                    aiStatus.style.color = 'var(--danger-color)';
                }
            }

            if (typeof tf !== 'undefined') {
                // Delay slightly to allow UI to render
                setTimeout(trainAndPredict, 500);
            } else {
                aiStatus.textContent = 'Could not load AI library. Please check internet connection.';
                aiStatus.style.color = 'var(--danger-color)';
            }
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>