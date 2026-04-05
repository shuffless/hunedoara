<?php
require_once __DIR__ . '/includes/header.php';

$availableBeds = getAvailableBeds();
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-person-plus-fill"></i> Admitere Directa Pacient
            </div>
            <div class="card-body">
                <form id="admit-form" autocomplete="off">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="prenume" class="form-label">Prenume <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenume" placeholder="ex: Ion" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nume" class="form-label">Nume <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nume" placeholder="ex: Popescu" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cnp" class="form-label">CNP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cnp"
                                   maxlength="13" pattern="\d{13}" placeholder="13 cifre" required>
                            <div id="cnp-info" class="form-text"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="medic" class="form-label">Medic curant <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="medic" placeholder="ex: Dr. Ionescu" required>
                        </div>
                        <div class="col-12">
                            <label for="diagnostic" class="form-label">Diagnostic <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="diagnostic" placeholder="ex: Pneumonie J18.9" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Pat <span class="text-danger">*</span></label>
                            <input type="hidden" id="selected-bed-id">
                            <div id="bed-buttons" class="mt-1">
                                <?php if (empty($availableBeds)): ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle"></i> Nu sunt paturi disponibile.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($availableBeds as $bed): ?>
                                        <button type="button"
                                                class="btn btn-outline-success bed-select-btn me-2 mb-2"
                                                data-bed-id="<?= $bed['id'] ?>"
                                                data-bed-name="<?= htmlspecialchars($bed['bed_name']) ?>">
                                            <i class="bi bi-hospital"></i>
                                            <?= htmlspecialchars($bed['bed_name']) ?>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div id="selected-bed-display" class="mt-2 d-none">
                                <span class="badge bg-success fs-6">
                                    <i class="bi bi-check-circle"></i>
                                    Pat selectat: <span id="selected-bed-name"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <hr class="mt-4">
                    <div id="admit-error" class="alert alert-danger d-none"></div>
                    <button type="submit" class="btn btn-primary w-100" id="admit-btn" disabled>
                        <i class="bi bi-hospital"></i> Interneaza Pacientul
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="admitSuccessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check-circle-fill"></i> Internare Reusita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="admit-success-body"></div>
            <div class="modal-footer">
                <a href="dashboard.php" class="btn btn-success">Mergi la Dashboard</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Adauga alt pacient
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- CNP live parsing ---
    var cnpInput = document.getElementById('cnp');
    var cnpInfo  = document.getElementById('cnp-info');

    cnpInput.addEventListener('input', function () {
        var cnp = this.value.trim();
        if (cnp.length === 13 && /^\d{13}$/.test(cnp)) {
            var parsed = parseCNP(cnp);
            if (parsed) {
                cnpInfo.className = 'form-text text-success';
                cnpInfo.innerHTML = '<i class="bi bi-check-circle-fill"></i> Sex: <strong>'
                    + parsed.sexLabel + '</strong> &nbsp;&bull;&nbsp; Data nasterii: <strong>'
                    + parsed.dobFormatted + '</strong>';
            } else {
                cnpInfo.className = 'form-text text-danger';
                cnpInfo.innerHTML = '<i class="bi bi-x-circle-fill"></i> CNP invalid.';
            }
        } else if (cnp.length > 0) {
            cnpInfo.className = 'form-text text-muted';
            cnpInfo.textContent = cnp.length + ' / 13 cifre';
        } else {
            cnpInfo.textContent = '';
            cnpInfo.className = 'form-text';
        }
        checkSubmitBtn();
    });

    // --- Bed selection ---
    document.querySelectorAll('.bed-select-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.bed-select-btn').forEach(function (b) {
                b.classList.remove('btn-success');
                b.classList.add('btn-outline-success');
            });
            this.classList.remove('btn-outline-success');
            this.classList.add('btn-success');
            document.getElementById('selected-bed-id').value   = this.dataset.bedId;
            document.getElementById('selected-bed-name').textContent = this.dataset.bedName;
            document.getElementById('selected-bed-display').classList.remove('d-none');
            checkSubmitBtn();
        });
    });

    // --- Enable submit only when all fields are filled ---
    ['prenume', 'nume', 'cnp', 'medic', 'diagnostic'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', checkSubmitBtn);
    });

    function checkSubmitBtn() {
        var ok = document.getElementById('prenume').value.trim()
              && document.getElementById('nume').value.trim()
              && /^\d{13}$/.test(document.getElementById('cnp').value.trim())
              && parseCNP(document.getElementById('cnp').value.trim()) !== null
              && document.getElementById('medic').value.trim()
              && document.getElementById('diagnostic').value.trim()
              && document.getElementById('selected-bed-id').value;
        document.getElementById('admit-btn').disabled = !ok;
    }

    // --- Form submit ---
    document.getElementById('admit-form').addEventListener('submit', function (e) {
        e.preventDefault();

        var btn    = document.getElementById('admit-btn');
        var errDiv = document.getElementById('admit-error');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Se proceseaza...';
        errDiv.classList.add('d-none');

        var payload = {
            first_name:       document.getElementById('prenume').value.trim(),
            last_name:        document.getElementById('nume').value.trim(),
            cnp:              document.getElementById('cnp').value.trim(),
            diagnosis:        document.getElementById('diagnostic').value.trim(),
            attending_doctor: document.getElementById('medic').value.trim(),
            bed_id:           parseInt(document.getElementById('selected-bed-id').value, 10)
        };

        fetch('api/admit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                var statusBadge = res.response_status === 'success'
                    ? '<span class="badge bg-success">confirmat de destinatie</span>'
                    : '<span class="badge bg-warning text-dark">destinatia nu a confirmat</span>';

                document.getElementById('admit-success-body').innerHTML =
                    '<p class="mb-1"><strong>' + escapeHtml(res.patient_name)
                    + '</strong> a fost internat cu succes.</p>'
                    + '<p class="mb-1">Pat alocat: <span class="badge bg-success">'
                    + escapeHtml(res.bed_name) + '</span></p>'
                    + '<p class="mb-0">Mesaj HL7: ' + statusBadge + '</p>';

                new bootstrap.Modal(document.getElementById('admitSuccessModal')).show();

                // Remove allocated bed button from the list
                var allocatedBtn = document.querySelector(
                    '.bed-select-btn[data-bed-id="' + res.bed_id + '"]'
                );
                if (allocatedBtn) allocatedBtn.remove();

                // Reset form
                document.getElementById('admit-form').reset();
                document.getElementById('selected-bed-id').value = '';
                document.getElementById('selected-bed-display').classList.add('d-none');
                cnpInfo.textContent = '';
                cnpInfo.className = 'form-text';
                document.querySelectorAll('.bed-select-btn').forEach(function (b) {
                    b.classList.remove('btn-success');
                    b.classList.add('btn-outline-success');
                });
            } else {
                errDiv.textContent = res.error || 'Eroare necunoscuta.';
                errDiv.classList.remove('d-none');
            }
        })
        .catch(function () {
            errDiv.textContent = 'Eroare de retea. Incearca din nou.';
            errDiv.classList.remove('d-none');
        })
        .finally(function () {
            btn.innerHTML = '<i class="bi bi-hospital"></i> Interneaza Pacientul';
            checkSubmitBtn();
        });
    });

    // --- CNP parser ---
    function parseCNP(cnp) {
        if (!/^\d{13}$/.test(cnp)) return null;
        var s  = parseInt(cnp[0], 10);
        var yy = cnp.substring(1, 3);
        var mm = cnp.substring(3, 5);
        var dd = cnp.substring(5, 7);

        var century;
        if (s === 1 || s === 2) century = '19';
        else if (s === 3 || s === 4) century = '18';
        else if (s === 5 || s === 6) century = '20';
        else if (s === 7 || s === 8) century = '19';
        else return null;

        var month = parseInt(mm, 10);
        var day   = parseInt(dd, 10);
        if (month < 1 || month > 12 || day < 1 || day > 31) return null;

        var sex         = (s % 2 === 1) ? 'M' : 'F';
        var sexLabel    = (sex === 'M') ? 'Masculin' : 'Feminin';
        var dob         = century + yy + mm + dd;   // YYYYMMDD
        var dobFormatted = dd + '.' + mm + '.' + century + yy;

        return { sex: sex, sexLabel: sexLabel, dob: dob, dobFormatted: dobFormatted };
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;')
                  .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
