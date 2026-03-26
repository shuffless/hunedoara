/**
 * Patient Hub - Main JavaScript
 * Handles AJAX bed allocation, cancellation, user management,
 * DataTables initialization, and real-time updates.
 */

$(document).ready(function () {

    // --- DataTables ---
    if ($('#requests-datatable').length) {
        $('#requests-datatable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: { search: 'Search:' }
        });
    }

    if ($('#messages-datatable').length) {
        $('#messages-datatable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: { search: 'Search:' }
        });
    }

    // --- Bed Allocation ---
    $(document).on('click', '.bed-btn', function () {
        var btn = $(this);
        var patientId = btn.data('patient-id');
        var bedId = btn.data('bed-id');
        var bedName = btn.data('bed-name');

        if (!confirm('Allocate ' + bedName + ' to this patient?')) return;

        btn.prop('disabled', true).text('Allocating...');

        $.ajax({
            url: 'api/allocate.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ patient_id: patientId, bed_id: bedId }),
            success: function (res) {
                if (res.success) {
                    // Remove the patient row with animation
                    $('#patient-row-' + patientId).addClass('fade-out');
                    setTimeout(function () {
                        $('#patient-row-' + patientId).remove();
                        updatePendingCount();
                    }, 500);

                    // Remove this bed button from ALL remaining patients
                    $('.bed-btn[data-bed-id="' + bedId + '"]').addClass('fade-out');
                    setTimeout(function () {
                        $('.bed-btn[data-bed-id="' + bedId + '"]').remove();
                    }, 500);

                    var statusMsg = res.response_status === 'success'
                        ? 'Destination confirmed receipt.'
                        : 'Warning: Destination did not confirm receipt.';
                    alert(bedName + ' allocated successfully. ' + statusMsg);
                } else {
                    alert('Error: ' + (res.error || 'Unknown error'));
                    btn.prop('disabled', false).text(bedName);
                }
            },
            error: function () {
                alert('Network error. Please try again.');
                btn.prop('disabled', false).text(bedName);
            }
        });
    });

    // --- Cancel Patient ---
    var cancelPatientId = null;

    $(document).on('click', '.cancel-btn', function () {
        cancelPatientId = $(this).data('patient-id');
        var patientName = $(this).data('patient-name');
        $('#cancel-patient-name').text(patientName);
        $('#cancel-reason').val('');
        $('#cancelModal').modal('show');
    });

    $('#confirm-cancel-btn').on('click', function () {
        var reason = $('#cancel-reason').val().trim();
        if (!reason) {
            alert('Please provide a reason for cancellation.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Cancelling...');

        $.ajax({
            url: 'api/cancel.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ patient_id: cancelPatientId, reason: reason }),
            success: function (res) {
                if (res.success) {
                    $('#patient-row-' + cancelPatientId).addClass('fade-out');
                    setTimeout(function () {
                        $('#patient-row-' + cancelPatientId).remove();
                        updatePendingCount();
                    }, 500);
                    $('#cancelModal').modal('hide');
                    alert('Patient cancelled successfully.');
                } else {
                    alert('Error: ' + (res.error || 'Unknown error'));
                }
                btn.prop('disabled', false).text('Confirm Cancellation');
            },
            error: function () {
                alert('Network error. Please try again.');
                btn.prop('disabled', false).text('Confirm Cancellation');
            }
        });
    });

    // --- Discharge Patient ---
    $(document).on('click', '.discharge-btn', function () {
        var btn = $(this);
        var patientId = btn.data('patient-id');
        var patientName = btn.data('patient-name');
        var bedName = btn.data('bed-name');

        if (!confirm('Discharge ' + patientName + ' and free ' + bedName + '?')) return;

        btn.prop('disabled', true).text('Discharging...');

        $.ajax({
            url: 'api/discharge.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ patient_id: patientId }),
            success: function (res) {
                if (res.success) {
                    $('#allocated-row-' + patientId).addClass('fade-out');
                    setTimeout(function () {
                        $('#allocated-row-' + patientId).remove();
                        updateAllocatedCount();
                    }, 500);
                    alert(res.patient_name + ' discharged. ' + res.bed_name + ' is now free.');
                } else {
                    alert('Error: ' + (res.error || 'Unknown error'));
                    btn.prop('disabled', false).text('Discharge');
                }
            },
            error: function () {
                alert('Network error. Please try again.');
                btn.prop('disabled', false).text('Discharge');
            }
        });
    });

    // --- User Management ---
    $('#add-user-form').on('submit', function (e) {
        e.preventDefault();
        var username = $('#new-username').val().trim();
        var password = $('#new-password').val();

        if (!username || !password) return;

        $.ajax({
            url: 'api/users.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'add', username: username, password: password }),
            success: function (res) {
                if (res.success) {
                    // Add row to users table
                    var row = '<tr id="user-row-' + res.user_id + '">'
                        + '<td>' + escapeHtml(res.username) + '</td>'
                        + '<td><span class="badge bg-secondary">User</span></td>'
                        + '<td><small>Just now</small></td>'
                        + '<td><button class="btn btn-sm btn-outline-danger delete-user-btn" '
                        + 'data-user-id="' + res.user_id + '" data-username="' + escapeHtml(res.username) + '">'
                        + '<i class="bi bi-trash"></i></button></td></tr>';
                    $('#users-table tbody').append(row);
                    $('#new-username').val('');
                    $('#new-password').val('');
                } else {
                    alert('Error: ' + (res.error || 'Unknown error'));
                }
            },
            error: function () {
                alert('Network error.');
            }
        });
    });

    $(document).on('click', '.delete-user-btn', function () {
        var userId = $(this).data('user-id');
        var username = $(this).data('username');

        if (!confirm('Delete user "' + username + '"?')) return;

        $.ajax({
            url: 'api/users.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'delete', user_id: userId }),
            success: function (res) {
                if (res.success) {
                    $('#user-row-' + userId).addClass('fade-out');
                    setTimeout(function () { $('#user-row-' + userId).remove(); }, 500);
                } else {
                    alert('Error: ' + (res.error || 'Unknown error'));
                }
            },
            error: function () {
                alert('Network error.');
            }
        });
    });

    // --- View Raw Request ---
    $(document).on('click', '.view-raw-btn', function () {
        var requestId = $(this).data('request-id');

        $.get('api/view_request.php', { id: requestId }, function (res) {
            if (res.success) {
                $('#raw-data-content').text(res.raw_data);
                var tbody = '';
                for (var key in res.fields) {
                    tbody += '<tr><td><strong>' + escapeHtml(key) + '</strong></td>'
                        + '<td>' + escapeHtml(res.fields[key]) + '</td></tr>';
                }
                $('#parsed-fields-body').html(tbody);
                $('#rawDataModal').modal('show');
            } else {
                alert('Error loading request data.');
            }
        });
    });

    // --- View HL7 Message ---
    $(document).on('click', '.view-hl7-btn', function () {
        var messageId = $(this).data('message-id');

        $.get('api/view_message.php', { id: messageId }, function (res) {
            if (res.success) {
                // Replace \r with newlines for display
                var hl7 = (res.hl7_message || '').replace(/\r/g, '\n');
                $('#hl7-message-content').text(hl7);
                $('#hl7-response-content').text(res.destination_response || 'No response');
                $('#hl7Modal').modal('show');
            } else {
                alert('Error loading message data.');
            }
        });
    });

    // --- Auto-refresh pending list every 30 seconds ---
    if ($('#pending-patients-container').length) {
        setInterval(function () {
            $.get('api/pending.php', function (res) {
                // Update the pending count badge
                var badge = $('.card-header .badge.bg-light');
                if (badge.length) {
                    badge.text(res.pending.length + ' pending');
                }
            });
        }, 30000);
    }

    // --- Helpers ---
    function updatePendingCount() {
        var count = $('#pending-table tbody tr:visible').length;
        var badge = $('.card-header .badge.bg-light').first();
        if (badge.length) {
            badge.text(count + ' pending');
        }
        if (count === 0) {
            $('#pending-patients-container').html('<p class="text-muted text-center">No pending patients.</p>');
        }
    }

    function updateAllocatedCount() {
        var count = $('#allocated-table tbody tr:visible').length;
        var badge = $('#allocated-count-badge');
        if (badge.length) {
            badge.text(count + ' allocated');
        }
        if (count === 0) {
            $('#allocated-patients-container').html('<p class="text-muted text-center">No allocated patients.</p>');
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
});
