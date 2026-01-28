<?php
session_start();

require_once '../config/config.php';

// Check if user is logged in and has engineer role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'engineer') {
    http_response_code(403);
    exit('Unauthorized');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid report ID');
}

$report_id = intval($_GET['id']);

$query = "SELECT mr.*, m.Maintenance_ID, a.Asset_Name, a.Model, a.Asset_ID, mt.Type_Name,
                 d.Department_Name, e.First_Name AS Tech_First_Name, e.Last_Name AS Tech_Last_Name,
                 eng_e.First_Name, eng_e.Last_Name
          FROM Maintenance_Reports mr
          INNER JOIN Maintenance m ON mr.Maintenance_ID = m.Maintenance_ID
          INNER JOIN Assets a ON m.Asset_ID = a.Asset_ID
          INNER JOIN Maintenance_Type mt ON m.Type_ID = mt.Type_ID
          LEFT JOIN Departments d ON a.Department_ID = d.Department_ID
          LEFT JOIN Employees e ON mr.Technician_ID = e.Employee_ID
          LEFT JOIN Biomedical_Engineers be ON m.Assigned_Engineer_ID = be.Engineer_ID
          LEFT JOIN Employees eng_e ON be.Employee_ID = eng_e.Employee_ID
          WHERE mr.Report_ID = $report_id";

$report = fetch_one($query);

if (!$report) {
    http_response_code(404);
    exit('Report not found');
}
?>

<div class="report-details">
    <div class="detail-item">
        <span class="detail-label">Équipement</span>
        <span class="detail-value"><?php echo htmlspecialchars($report['Asset_Name']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Modèle</span>
        <span class="detail-value"><?php echo htmlspecialchars($report['Model'] ?? 'N/A'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Type de Maintenance</span>
        <span class="detail-value"><?php echo ucfirst(htmlspecialchars($report['Maintenance_Type'] ?? 'N/A')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Département</span>
        <span class="detail-value"><?php echo htmlspecialchars($report['Department_Name'] ?? 'N/A'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Technicien</span>
        <span class="detail-value"><?php echo htmlspecialchars($report['Tech_First_Name'] . ' ' . $report['Tech_Last_Name']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Ingénieur Responsable</span>
        <span class="detail-value"><?php echo htmlspecialchars(($report['First_Name'] ?? '') . ' ' . ($report['Last_Name'] ?? '')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Date du Rapport</span>
        <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($report['Report_Date'])); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Statut</span>
        <span class="detail-value">
            <?php if ($report['Work_Completed']): ?>
                <span class="status-badge status-completed">✓ Complété</span>
            <?php else: ?>
                <span class="status-badge status-pending">⏳ En Attente</span>
            <?php endif; ?>
        </span>
    </div>
</div>

<?php if (!empty($report['Work_Description'])): ?>
<div class="section">
    <div class="section-title">📋 Travaux Réalisés</div>
    <p><?php echo htmlspecialchars($report['Work_Description']); ?></p>
</div>
<?php endif; ?>

<?php if (!empty($report['Failure_Cause'])): ?>
<div class="section">
    <div class="section-title">🔴 Cause de la Panne</div>
    <p><?php echo htmlspecialchars($report['Failure_Cause']); ?></p>
</div>
<?php endif; ?>

<?php if (!empty($report['Parts_Used'])): ?>
<div class="section">
    <div class="section-title">🔧 Pièces Utilisées</div>
    <p><?php echo nl2br(htmlspecialchars($report['Parts_Used'])); ?></p>
</div>
<?php endif; ?>

<?php if (!empty($report['Parts_Ordered'])): ?>
<div class="section">
    <div class="section-title">📦 Pièces à Commander</div>
    <p><?php echo nl2br(htmlspecialchars($report['Parts_Ordered'])); ?></p>
</div>
<?php endif; ?>

<?php if ($report['Hours_Worked'] > 0): ?>
<div class="section">
    <div class="section-title">⏱️ Temps de Travail</div>
    <p><?php echo htmlspecialchars($report['Hours_Worked']); ?> heures</p>
</div>
<?php endif; ?>

<?php if (!empty($report['Client_Satisfaction'])): ?>
<div class="section">
    <div class="section-title">😊 Satisfaction du Client</div>
    <p><?php echo htmlspecialchars($report['Client_Satisfaction']); ?></p>
</div>
<?php endif; ?>
