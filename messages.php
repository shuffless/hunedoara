<?php
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$messages = $db->query('SELECT sm.id, sm.event_type, sm.allocated_bed, sm.cancel_reason,
    sm.response_status, sm.sent_at, sm.destination_response,
    pp.patient_name, pp.request_id
    FROM sent_messages sm
    LEFT JOIN pending_patients pp ON sm.pending_patient_id = pp.id
    ORDER BY sm.sent_at DESC')->fetchAll();
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="bi bi-send-fill"></i> All Sent Messages
                <span class="badge bg-light text-dark float-end"><?= count($messages) ?> total</span>
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover" id="messages-datatable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Event</th>
                            <th>Bed / Reason</th>
                            <th>Dest. Response</th>
                            <th>Status</th>
                            <th>Sent At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?= $msg['id'] ?></td>
                            <td><?= htmlspecialchars($msg['patient_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($msg['event_type'] === 'bed_allocation'): ?>
                                    <span class="badge bg-success">Allocation</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Cancellation</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($msg['event_type'] === 'bed_allocation'): ?>
                                    <?= htmlspecialchars($msg['allocated_bed']) ?>
                                <?php else: ?>
                                    <small><?= htmlspecialchars(substr($msg['cancel_reason'] ?? '', 0, 60)) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= htmlspecialchars(substr($msg['destination_response'] ?? '', 0, 60)) ?></small></td>
                            <td>
                                <span class="badge bg-<?= $msg['response_status'] === 'success' ? 'success' : 'danger' ?>">
                                    <?= $msg['response_status'] ?>
                                </span>
                            </td>
                            <td><?= $msg['sent_at'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-success view-hl7-btn" data-message-id="<?= $msg['id'] ?>">
                                    <i class="bi bi-eye"></i> View HL7
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

<!-- HL7 Message Modal -->
<div class="modal fade" id="hl7Modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sent HL7 Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="hl7-message-content" class="bg-dark text-light p-3 rounded" style="max-height:400px;overflow:auto;"></pre>
                <h6 class="mt-3">Destination Response:</h6>
                <pre id="hl7-response-content" class="bg-secondary text-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
