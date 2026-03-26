<?php
require_once __DIR__ . '/includes/header.php';

$stats = getStats();
$pendingPatients = getPendingPatients();
$availableBeds = getAvailableBeds();
$allocatedPatients = getAllocatedPatients();

// Get users list
$db = getDB();
$users = $db->query('SELECT id, username, is_manager, created_at FROM users ORDER BY created_at ASC')->fetchAll();
?>

<div class="row">
    <!-- Box A: Pending Patients / Bed Allocation -->
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-people-fill"></i> Pending Patients - Bed Allocation
                <span class="badge bg-light text-dark float-end"><?= count($pendingPatients) ?> pending</span>
            </div>
            <div class="card-body" id="pending-patients-container">
                <?php if (empty($pendingPatients)): ?>
                    <p class="text-muted text-center">No pending patients.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="pending-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient Name</th>
                                    <th>Received</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingPatients as $i => $patient): ?>
                                <tr id="patient-row-<?= $patient['id'] ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($patient['patient_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($patient['created_at']) ?></td>
                                    <td class="bed-actions">
                                        <?php foreach ($availableBeds as $bed): ?>
                                            <button class="btn btn-sm btn-outline-success bed-btn mb-1"
                                                    data-patient-id="<?= $patient['id'] ?>"
                                                    data-bed-id="<?= $bed['id'] ?>"
                                                    data-bed-name="<?= htmlspecialchars($bed['bed_name']) ?>">
                                                <?= htmlspecialchars($bed['bed_name']) ?>
                                            </button>
                                        <?php endforeach; ?>
                                        <button class="btn btn-sm btn-outline-danger cancel-btn mb-1"
                                                data-patient-id="<?= $patient['id'] ?>"
                                                data-patient-name="<?= htmlspecialchars($patient['patient_name']) ?>">
                                            Cancel
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Allocated Patients -->
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="bi bi-bed-fill"></i> Allocated Patients
                <span class="badge bg-light text-dark float-end" id="allocated-count-badge"><?= count($allocatedPatients) ?> allocated</span>
            </div>
            <div class="card-body" id="allocated-patients-container">
                <?php if (empty($allocatedPatients)): ?>
                    <p class="text-muted text-center">No allocated patients.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="allocated-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient Name</th>
                                    <th>Allocated Bed</th>
                                    <th>Admitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allocatedPatients as $i => $patient): ?>
                                <tr id="allocated-row-<?= $patient['id'] ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($patient['patient_name']) ?></strong></td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($patient['bed_name']) ?></span></td>
                                    <td><?= htmlspecialchars($patient['created_at']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-warning discharge-btn"
                                                data-patient-id="<?= $patient['id'] ?>"
                                                data-patient-name="<?= htmlspecialchars($patient['patient_name']) ?>"
                                                data-bed-name="<?= htmlspecialchars($patient['bed_name']) ?>">
                                            Discharge
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3"></div>
</div>

<div class="row">
    <!-- Right Column: Stats + Users -->
    <div class="col-lg-4">
        <!-- Box B: Requests Stats -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-inbox-fill"></i> Incoming Requests
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <h4><?= $stats['requests_total'] ?></h4>
                        <small class="text-muted">Total</small>
                    </div>
                    <div class="col-4">
                        <h4><?= $stats['requests_today'] ?></h4>
                        <small class="text-muted">Today</small>
                    </div>
                    <div class="col-4">
                        <h4><?= $stats['requests_month'] ?></h4>
                        <small class="text-muted">This Month</small>
                    </div>
                </div>
                <?php if (!empty($stats['last_requests'])): ?>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>ID</th><th>Format</th><th>IP</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($stats['last_requests'] as $req): ?>
                            <tr>
                                <td><?= $req['id'] ?></td>
                                <td><span class="badge bg-secondary"><?= strtoupper($req['data_format']) ?></span></td>
                                <td><?= htmlspecialchars($req['sender_ip']) ?></td>
                                <td><?= $req['received_at'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <a href="requests.php" class="btn btn-sm btn-outline-info w-100">View All Requests</a>
            </div>
        </div>

        <!-- Box C: Messages Stats -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <i class="bi bi-send-fill"></i> Sent Messages
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <h4><?= $stats['messages_total'] ?></h4>
                        <small class="text-muted">Total</small>
                    </div>
                    <div class="col-4">
                        <h4><?= $stats['messages_today'] ?></h4>
                        <small class="text-muted">Today</small>
                    </div>
                    <div class="col-4">
                        <h4><?= $stats['messages_month'] ?></h4>
                        <small class="text-muted">This Month</small>
                    </div>
                </div>
                <?php if (!empty($stats['last_messages'])): ?>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Patient</th><th>Event</th><th>Status</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($stats['last_messages'] as $msg): ?>
                            <tr>
                                <td><?= htmlspecialchars($msg['patient_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($msg['event_type'] === 'bed_allocation'): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($msg['allocated_bed']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $msg['response_status'] === 'success' ? 'success' : 'danger' ?>">
                                        <?= $msg['response_status'] ?>
                                    </span>
                                </td>
                                <td><?= $msg['sent_at'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <a href="messages.php" class="btn btn-sm btn-outline-success w-100">View All Messages</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Box D: Error Log -->
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle-fill"></i> Error Log (Last 10)
            </div>
            <div class="card-body">
                <?php if (empty($stats['last_errors'])): ?>
                    <p class="text-muted text-center">No errors logged.</p>
                <?php else: ?>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Time</th><th>Context</th><th>Message</th></tr></thead>
                        <tbody>
                            <?php foreach ($stats['last_errors'] as $err): ?>
                            <tr>
                                <td><small><?= $err['created_at'] ?></small></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($err['error_context'] ?? 'general') ?></span></td>
                                <td><small><?= htmlspecialchars(substr($err['error_message'], 0, 120)) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Box E: User Management -->
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-people"></i> User Management
            </div>
            <div class="card-body">
                <table class="table table-sm table-striped" id="users-table">
                    <thead><tr><th>Username</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr id="user-row-<?= $user['id'] ?>">
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <?php if ($user['is_manager']): ?>
                                    <span class="badge bg-warning text-dark">Manager</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">User</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= $user['created_at'] ?></small></td>
                            <td>
                                <?php if (isManager() && !$user['is_manager']): ?>
                                    <button class="btn btn-sm btn-outline-danger delete-user-btn"
                                            data-user-id="<?= $user['id'] ?>"
                                            data-username="<?= htmlspecialchars($user['username']) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php elseif ($user['is_manager']): ?>
                                    <small class="text-muted">Protected</small>
                                <?php else: ?>
                                    <small class="text-muted">Manager only</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (isManager()): ?>
                <hr>
                <form id="add-user-form" class="row g-2">
                    <div class="col-4">
                        <input type="text" class="form-control form-control-sm" id="new-username" placeholder="Username" required>
                    </div>
                    <div class="col-4">
                        <input type="password" class="form-control form-control-sm" id="new-password" placeholder="Password" required>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-sm btn-warning w-100">Add User</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Reason Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Patient</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Cancelling: <strong id="cancel-patient-name"></strong></p>
                <div class="mb-3">
                    <label for="cancel-reason" class="form-label">Reason for cancellation (required):</label>
                    <textarea class="form-control" id="cancel-reason" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirm-cancel-btn">Confirm Cancellation</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
