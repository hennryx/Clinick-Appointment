<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../config/config.php';

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4">Reports</h2>
        
        <div class="row">
            <!-- Income Report Card -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-cash-coin text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Income Report</h5>
                        <p class="card-text">View daily, monthly, and annual income reports.</p>
                        <a href="<?= BASE_PATH ?>/views/reports/income.php" class="btn btn-success">View Report</a>
                    </div>
                </div>
            </div>
            
            <!-- Patient Census Card -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-people text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Patient Census</h5>
                        <p class="card-text">View patient statistics and demographics.</p>
                        <a href="<?= BASE_PATH ?>/views/reports/census.php" class="btn btn-primary">View Report</a>
                    </div>
                </div>
            </div>
            
            <!-- Laboratory Workload Card -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-clipboard-data text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Laboratory Workload</h5>
                        <p class="card-text">View test volume and workload statistics.</p>
                        <a href="<?= BASE_PATH ?>/views/reports/workload.php" class="btn btn-warning">View Report</a>
                    </div>
                </div>
            </div>
            
            <!-- Reagent Consumption Card -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-droplet-half text-info" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Reagent Consumption</h5>
                        <p class="card-text">Track reagent usage and inventory levels.</p>
                        <a href="<?= BASE_PATH ?>/views/reports/consumption.php" class="btn btn-info">View Report</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Generate Custom Report Section -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Generate Custom Report</h5>
            </div>
            <div class="card-body">
                <form id="customReportForm">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="reportType" class="form-label">Report Type</label>
                            <select class="form-select" id="reportType" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <option value="income">Income Report</option>
                                <option value="tests">Test Summary Report</option>
                                <option value="patients">Patient Report</option>
                                <option value="reagents">Reagent Consumption Report</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="dateRange" class="form-label">Date Range</label>
                            <select class="form-select" id="dateRange" name="date_range" required>
                                <option value="">Select Date Range</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_year">This Year</option>
                                <option value="custom">Custom Date Range</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="format" class="form-label">Format</label>
                            <select class="form-select" id="format" name="format" required>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="customDateContainer" style="display: none;">
                        <div class="col-md-4">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="start_date">
                        </div>
                        <div class="col-md-4">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="additionalOptions" class="form-label">Additional Options</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="includeCharts" name="include_charts">
                                <label class="form-check-label" for="includeCharts">Include Charts and Graphs</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="includeDetails" name="include_details">
                                <label class="form-check-label" for="includeDetails">Include Detailed Records</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" id="generateReportBtn" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Handle date range selection
document.getElementById('dateRange').addEventListener('change', function() {
    const customDateContainer = document.getElementById('customDateContainer');
    
    if (this.value === 'custom') {
        customDateContainer.style.display = 'flex';
        document.getElementById('startDate').required = true;
        document.getElementById('endDate').required = true;
    } else {
        customDateContainer.style.display = 'none';
        document.getElementById('startDate').required = false;
        document.getElementById('endDate').required = false;
    }
});

// Generate Custom Report
document.getElementById('generateReportBtn').addEventListener('click', function() {
    const form = document.getElementById('customReportForm');
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Get form values
    const reportType = document.getElementById('reportType').value;
    const dateRange = document.getElementById('dateRange').value;
    const format = document.getElementById('format').value;
    const includeCharts = document.getElementById('includeCharts').checked ? 1 : 0;
    const includeDetails = document.getElementById('includeDetails').checked ? 1 : 0;
    
    // Build URL with query parameters
    let url = `/views/reports/${reportType}.php?date_range=${dateRange}&format=${format}`;
    url += `&include_charts=${includeCharts}&include_details=${includeDetails}`;
    
    // Add custom date range if selected
    if (dateRange === 'custom') {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Start date and end date are required for custom date range'
            });
            return;
        }
        
        url += `&start_date=${startDate}&end_date=${endDate}`;
    }
    
    // Redirect to report page
    window.location.href = url;
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>