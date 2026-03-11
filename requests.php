<?php
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$requests = $db->query('SELECT r.id, r.data_format, r.sender_ip, r.received_at,
    (SELECT pp.patient_name FROM pending_patients pp WHERE pp.request_id = r.id LIMIT 1) as patient_name,
    (SELECT pp.status FROM pending_patients pp WHERE pp.request_id = r.id LIMIT 1) as patient_status
    FROM raw_requests r ORDER BY r.received_at DESC')->fetchAll();
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="bi bi-inbox-fill"></i> All Incoming Requests
                <span class="badge bg-light text-dark float-end"><?= count($requests) ?> total</span>
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover" id="requests-datatable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Format</th>
                            <th>Sender IP</th>
                            <th>Status</th>
                            <th>Received At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?= $req['id'] ?></td>
                            <td><?= htmlspecialchars($req['patient_name'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-secondary"><?= strtoupper($req['data_format']) ?></span></td>
                            <td><?= htmlspecialchars($req['sender_ip']) ?></td>
                            <td>
                                <?php
                                $statusClass = match($req['patient_status'] ?? '') {
                                    'pending' => 'warning',
                                    'allocated' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $statusClass ?>"><?= $req['patient_status'] ?? 'N/A' ?></span>
                            </td>
                            <td><?= $req['received_at'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info view-raw-btn" data-request-id="<?= $req['id'] ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Raw Data Modal -->
<div class="modal fade" id="rawDataModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Raw Request Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="raw-data-content" class="bg-dark text-light p-3 rounded" style="max-height:400px;overflow:auto;"></pre>
                <h6 class="mt-3">Parsed Fields:</h6>
                <table class="table table-sm table-striped" id="parsed-fields-table">
                    <thead><tr><th>Field</th><th>Value</th></tr></thead>
                    <tbody id="parsed-fields-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
