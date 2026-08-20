<?php

function adminDownloadSqlBackup(PDO $pdo): never
{
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rhu-backup-' . date('Y-m-d-His') . '.sql"');
    echo "-- RedPulse RHU database backup\nSET FOREIGN_KEY_CHECKS=0;\n";
    $tables = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $create = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM);
        echo "\nDROP TABLE IF EXISTS {$quoted};\n", $create[1], ";\n";
        $rows = $pdo->query("SELECT * FROM {$quoted}");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = implode(',', array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row)));
            $values = implode(',', array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row)));
            echo "INSERT INTO {$quoted} ({$columns}) VALUES ({$values});\n";
        }
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

function adminDownloadCertificatePdf(PDO $pdo, int $certificateId): never
{
    $stmt = $pdo->prepare("SELECT hc.id,hc.certificate_number,hc.generated_html,hc.validity_status,ct.certificate_type_name FROM health_certificates hc JOIN certificate_types ct ON ct.id=hc.certificate_type_id WHERE hc.id=?");
    $stmt->execute([$certificateId]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$certificate) {
        http_response_code(404);
        exit('Certificate not found.');
    }
    $certificateHtml = trim((string)($certificate['generated_html'] ?? ''));
    if ($certificateHtml === '' && function_exists('portalGenerateCertificateHtml')) {
        $certificateHtml = portalGenerateCertificateHtml($pdo, $certificateId, true);
        $updateHtml = $pdo->prepare('UPDATE health_certificates SET generated_html = :html WHERE id = :id');
        $updateHtml->execute(['html' => $certificateHtml, 'id' => $certificateId]);
    }
    $fileName = 'certificate-' . preg_replace('/[^A-Za-z0-9-]/', '', (string)$certificate['certificate_number']) . '.html';
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars((string)$certificate['certificate_type_name'], ENT_QUOTES, 'UTF-8') . '</title><style>'
        . '*{box-sizing:border-box}body{margin:0;background:#e5e7eb;color:#111;font-family:Arial,sans-serif}.toolbar{position:sticky;top:0;z-index:5;display:flex;justify-content:center;gap:10px;padding:14px;background:#0f766e}.toolbar button{border:1px solid rgba(255,255,255,.5);border-radius:8px;background:#fff;padding:9px 16px;color:#0f766e;font:700 13px Arial;cursor:pointer}'
        . '.official-certificate-template{position:relative;overflow:hidden;margin:24px auto;width:min(100%,760px);min-height:1040px;background:#fff;padding:58px 68px 44px;color:#050505;font-family:Arial,Helvetica,sans-serif;line-height:1.45;box-shadow:0 18px 50px rgba(15,23,42,.18)}.cert-header{position:relative;z-index:1;display:grid;grid-template-columns:112px 1fr 112px;align-items:center;text-align:center;margin-bottom:10px}.cert-seal{width:96px;height:96px;object-fit:contain;justify-self:center}.cert-watermark{position:absolute;z-index:0;left:50%;top:285px;width:560px;height:560px;transform:translateX(-50%);object-fit:contain;opacity:.1;pointer-events:none}.cert-header-copy{font-size:11px;line-height:1.2}.cert-header-copy p{margin:0}.cert-republic{font-family:Georgia,"Times New Roman",serif;font-style:italic;font-size:13px}.cert-rule{position:relative;z-index:1;border-top:2px solid #111;border-bottom:1px solid #111;height:4px;margin:6px 0 42px}.official-certificate-template h1,.official-certificate-template h2,.official-certificate-template h3{position:relative;z-index:1;margin:5px 0;text-align:center;font-weight:900;text-transform:uppercase}.official-certificate-template h1{font-size:18px}.official-certificate-template h2{font-size:21px;font-style:italic}.official-certificate-template h3{font-size:28px;margin-bottom:2px}.cert-no{position:relative;z-index:1;text-align:center;font-family:"Courier New",monospace;font-size:10px;font-weight:700;color:#334155;margin:0 0 44px}.cert-body{position:relative;z-index:1;margin:0;font-size:12px;line-height:1.65;text-align:justify}.cert-body p{margin:0 0 18px;text-indent:34px}.cert-body .cert-greeting{text-indent:0;margin-bottom:26px;text-align:left}.cert-dates{display:flex;gap:34px;margin-top:6px;font-size:10px;text-align:left}.cert-signatures{position:relative;z-index:1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:52px;margin-top:118px;text-align:center}.cert-signatures>div{min-height:104px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;font-size:12px}.cert-signatures strong{border-top:1px solid #111;min-width:250px;padding-top:5px;font-weight:900;text-transform:uppercase}.certificate-signature-image{display:block;width:170px;height:58px;margin:0 auto -5px;object-fit:contain;object-position:center bottom;mix-blend-mode:multiply}.signature-line{height:58px;margin-bottom:-5px;width:170px}.official-certificate-template small,.cert-footer{display:block;color:#111;font-size:10px}.cert-footer{position:absolute;z-index:1;left:68px;right:68px;bottom:40px;display:flex;justify-content:space-between;border-top:1px solid #64748b;padding-top:6px;font-family:"Courier New",monospace;color:#0f172a}@media print{@page{size:A4 portrait;margin:0}body{background:#fff}.toolbar{display:none}.official-certificate-template{width:210mm;min-height:297mm;margin:0;box-shadow:none}}'
        . '</style></head><body><div class="toolbar"><button onclick="window.print()">Save as PDF</button></div>' . $certificateHtml . '</body></html>';
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($html));
    echo $html;
    exit;
}

function adminExtendedAction(PDO $pdo, string $action, int $adminId): ?string
{
    $audit = static function (string $message, string $table, int $id = 0) use ($pdo, $adminId): void {
        portalAudit($pdo, $adminId, $message, $table, $id ?: null);
    };

    if ($action === 'save_resident') {
        $id = (int)($_POST['resident_id'] ?? 0);
        $values = [
            trim($_POST['first_name'] ?? ''), trim($_POST['last_name'] ?? ''),
            trim($_POST['middle_name'] ?? ''), $_POST['date_of_birth'] ?? null,
            trim($_POST['gender'] ?? ''), trim($_POST['civil_status'] ?? ''),
            trim($_POST['contact_number'] ?? ''), trim($_POST['email'] ?? ''),
            trim($_POST['address'] ?? ''), trim($_POST['barangay'] ?? ''),
            trim($_POST['philhealth_id'] ?? ''), trim($_POST['blood_type'] ?? ''),
            trim($_POST['allergies'] ?? ''), trim($_POST['medical_conditions'] ?? ''),
        ];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE residents SET first_name=?,last_name=?,middle_name=?,date_of_birth=?,gender=?,civil_status=?,contact_number=?,email=?,address=?,barangay=?,philhealth_id=?,blood_type=?,allergies=?,medical_conditions=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([...$values, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO residents (first_name,last_name,middle_name,date_of_birth,gender,civil_status,contact_number,email,address,barangay,philhealth_id,blood_type,allergies,medical_conditions) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute($values);
            $id = (int)$pdo->lastInsertId();
        }
        $audit('Saved resident profile', 'residents', $id);
        return 'Resident profile saved.';
    }

    if ($action === 'save_staff_profile') {
        $id = (int)($_POST['staff_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE staff SET staff_type=?,license_number=?,license_expiry=?,specialization=?,phone_number=?,address=?,date_hired=?,updated_at=NOW() WHERE id=?');
        $stmt->execute([
            trim($_POST['staff_type'] ?? ''), trim($_POST['license_number'] ?? ''),
            ($_POST['license_expiry'] ?? '') ?: null, trim($_POST['specialization'] ?? ''),
            trim($_POST['phone_number'] ?? ''), trim($_POST['address'] ?? ''),
            ($_POST['date_hired'] ?? '') ?: null, $id,
        ]);
        $audit('Updated staff profile', 'staff', $id);
        return 'Staff profile updated.';
    }

    if ($action === 'delete_staff') {
        $id = (int)($_POST['staff_id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM staff WHERE id=?');
            $stmt->execute([$id]);
            $userId = (int)$stmt->fetchColumn();
            $pdo->prepare('DELETE FROM staff WHERE id=?')->execute([$id]);
            if ($userId && $userId !== $adminId) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $audit('Deleted staff account', 'staff', $id);
        return 'Staff account deleted.';
    }

    if ($action === 'save_vaccination') {
        $id = (int)($_POST['vaccination_id'] ?? 0);
        $values = [
            (int)($_POST['resident_id'] ?? 0), (int)($_POST['vaccine_id'] ?? 0),
            $_POST['vaccination_date'] ?? date('Y-m-d'), ($_POST['provider_id'] ?? '') ?: null,
            trim($_POST['batch_number'] ?? ''), trim($_POST['site_of_injection'] ?? ''),
            trim($_POST['adverse_reactions'] ?? ''), ($_POST['next_dose_date'] ?? '') ?: null,
        ];
        if ($id) {
            $pdo->prepare('UPDATE vaccination_records SET resident_id=?,vaccine_id=?,vaccination_date=?,healthcare_provider_id=?,batch_number=?,site_of_injection=?,adverse_reactions=?,next_dose_date=? WHERE id=?')->execute([...$values, $id]);
        } else {
            $pdo->prepare('INSERT INTO vaccination_records (resident_id,vaccine_id,vaccination_date,healthcare_provider_id,batch_number,site_of_injection,adverse_reactions,next_dose_date) VALUES (?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int)$pdo->lastInsertId();
        }
        $audit('Saved vaccination record', 'vaccination_records', $id);
        return 'Vaccination record saved.';
    }

    if ($action === 'delete_vaccination') {
        $id = (int)($_POST['vaccination_id'] ?? 0);
        $pdo->prepare('DELETE FROM vaccination_records WHERE id=?')->execute([$id]);
        $audit('Deleted vaccination record', 'vaccination_records', $id);
        return 'Vaccination record deleted.';
    }

    if ($action === 'create_pregnancy') {
        $stmt = $pdo->prepare("INSERT INTO pregnancies (resident_id,last_menstrual_period,expected_delivery_date,pregnancy_status,high_risk,risk_factors) VALUES (?,?,?,'Active',?,?)");
        $stmt->execute([(int)$_POST['resident_id'], $_POST['lmp'] ?: null, $_POST['edc'] ?: null, isset($_POST['high_risk']) ? 1 : 0, trim($_POST['risk_factors'] ?? '')]);
        $id = (int)$pdo->lastInsertId();
        $audit('Created pregnancy case', 'pregnancies', $id);
        return 'Pregnancy case created.';
    }

    if ($action === 'create_disease_case') {
        $stmt = $pdo->prepare("INSERT INTO disease_cases (resident_id,disease_id,case_date,onset_date,case_classification,symptoms,outcome,treatment,case_status,reported_to_doh,doh_report_date) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $reported = isset($_POST['reported_to_doh']) ? 1 : 0;
        $stmt->execute([(int)$_POST['resident_id'],(int)$_POST['disease_id'],$_POST['case_date'],($_POST['onset_date']??'')?:null,trim($_POST['classification']??'Suspected'),trim($_POST['symptoms']??''),trim($_POST['outcome']??'Active'),trim($_POST['treatment']??''),trim($_POST['case_status']??'Open'),$reported,$reported?date('Y-m-d'):null]);
        $id = (int)$pdo->lastInsertId();
        $audit('Created disease surveillance case', 'disease_cases', $id);
        return 'Disease surveillance case created.';
    }

    if ($action === 'save_medicine') {
        $id = (int)($_POST['medicine_id'] ?? 0);
        $genericName = trim($_POST['generic_name'] ?? '');
        $brandName = trim($_POST['brand_name'] ?? '');
        $dosage = trim($_POST['dosage'] ?? 'Standard');
        $unitForm = trim($_POST['unit_form'] ?? 'Tablet');
        $quantity = (int)($_POST['quantity'] ?? $_POST['quantity_in_stock'] ?? 0);
        $reorderLevel = (int)($_POST['reorder_level'] ?? 30);
        if ($reorderLevel <= 0) $reorderLevel = 30;
        $supplier = trim($_POST['supplier'] ?? 'RHU Supply');
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $expiryDate = ($_POST['expiry_date'] ?? '') ?: date('Y-m-d', strtotime('+1 year'));
        $batchNumber = trim($_POST['batch_number'] ?? '');
        if (empty($batchNumber)) {
            $batchNumber = 'BATCH-' . date('Y') . '-' . mt_rand(10, 99);
        }

        $values = [
            $genericName, $brandName, $dosage, $unitForm,
            $quantity, $reorderLevel, $supplier, $unitCost,
            $expiryDate, $batchNumber
        ];
        if ($id) {
            $pdo->prepare('UPDATE medicine_inventory SET generic_name=?,brand_name=?,dosage=?,unit_form=?,quantity_in_stock=?,reorder_level=?,supplier=?,unit_cost=?,expiry_date=?,batch_number=?,last_updated=NOW() WHERE id=?')->execute([...$values, $id]);
        } else {
            $pdo->prepare('INSERT INTO medicine_inventory (generic_name,brand_name,dosage,unit_form,quantity_in_stock,reorder_level,supplier,unit_cost,expiry_date,batch_number) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int)$pdo->lastInsertId();
        }
        $audit('Saved medicine item', 'medicine_inventory', $id);
        return 'Medicine item saved successfully.';
    }

    if ($action === 'delete_medicine') {
        $id = (int)($_POST['medicine_id'] ?? 0);
        $pdo->prepare('DELETE FROM medicine_inventory WHERE id=?')->execute([$id]);
        $audit('Deleted medicine item', 'medicine_inventory', $id);
        return 'Medicine item deleted.';
    }

    if ($action === 'save_vital') {
        $type = $_POST['vital_type'] ?? 'birth';
        $id = (int)($_POST['vital_id'] ?? 0);
        if ($type === 'birth') {
            $values = [trim($_POST['certificate_number']),trim($_POST['person_name']),$_POST['record_date'],($_POST['record_time'] ?? '') ?: null,trim($_POST['location']), (int)$_POST['mother_id'],trim($_POST['father_name'] ?? ''),trim($_POST['gender'] ?? ''),($_POST['birth_weight'] ?? '') ?: null];
            if ($id) $pdo->prepare('UPDATE vital_statistics_births SET birth_certificate_number=?,child_name=?,date_of_birth=?,time_of_birth=?,place_of_birth=?,mother_id=?,father_name=?,gender=?,birth_weight_kg=? WHERE id=?')->execute([...$values,$id]);
            else {
                $pdo->prepare('INSERT INTO vital_statistics_births (birth_certificate_number,child_name,date_of_birth,time_of_birth,place_of_birth,mother_id,father_name,gender,birth_weight_kg,registered_date) VALUES (?,?,?,?,?,?,?,?,?,CURDATE())')->execute($values);
                $id = (int)$pdo->lastInsertId();
            }
            $table = 'vital_statistics_births';
        } else {
            $values = [trim($_POST['certificate_number']),trim($_POST['person_name']),$_POST['record_date'],trim($_POST['location']),trim($_POST['cause_of_death'] ?? ''),trim($_POST['icd_code'] ?? ''),($_POST['age_at_death'] ?? '') ?: null];
            if ($id) $pdo->prepare('UPDATE vital_statistics_deaths SET death_certificate_number=?,deceased_name=?,date_of_death=?,place_of_death=?,cause_of_death=?,icd_code=?,age_at_death=? WHERE id=?')->execute([...$values,$id]);
            else {
                $pdo->prepare('INSERT INTO vital_statistics_deaths (death_certificate_number,deceased_name,date_of_death,place_of_death,cause_of_death,icd_code,age_at_death,registered_date) VALUES (?,?,?,?,?,?,?,CURDATE())')->execute($values);
                $id = (int)$pdo->lastInsertId();
            }
            $table = 'vital_statistics_deaths';
        }
        $audit('Saved vital statistics record', $table, $id);
        return 'Vital statistics record saved.';
    }

    if ($action === 'save_event') {
        $id = (int)($_POST['event_id'] ?? 0);
        $date = $_POST['scheduled_date'];
        $values = [date('F j, Y', strtotime($date)), $date, ($_POST['start_time'] ?? '') ?: null, trim($_POST['title']), trim($_POST['venue']), trim($_POST['description']), ($_POST['capacity'] ?? '') ?: null, trim($_POST['status'] ?? 'Scheduled')];
        $pdo->prepare('UPDATE portal_events SET event_date=?,scheduled_date=?,start_time=?,title=?,venue=?,description=?,capacity=?,status=? WHERE id=?')->execute([...$values, $id]);
        $audit('Updated RHU event', 'portal_events', $id);
        return 'Event updated.';
    }

    if ($action === 'delete_event') {
        $id = (int)$_POST['event_id'];
        $pdo->prepare('DELETE FROM portal_events WHERE id=?')->execute([$id]);
        $audit('Deleted RHU event', 'portal_events', $id);
        return 'Event deleted.';
    }

    if ($action === 'save_permission') {
        $name = trim($_POST['permission_name'] ?? '');
        $pdo->prepare('INSERT INTO permissions (name,description) VALUES (?,?) ON DUPLICATE KEY UPDATE description=VALUES(description)')->execute([$name, trim($_POST['description'] ?? '')]);
        $audit('Saved permission', 'permissions');
        return 'Permission saved.';
    }

    if ($action === 'save_role') {
        $name = strtoupper(trim($_POST['role_name'] ?? ''));
        $pdo->prepare('INSERT INTO roles (name,description) VALUES (?,?) ON DUPLICATE KEY UPDATE description=VALUES(description)')->execute([$name, trim($_POST['description'] ?? '')]);
        $audit('Saved system role', 'roles');
        return 'Role saved.';
    }

    if ($action === 'set_role_permission') {
        $roleId = (int)$_POST['role_id'];
        $permissionId = (int)$_POST['permission_id'];
        if (isset($_POST['enabled'])) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id) VALUES (?,?)')->execute([$roleId,$permissionId]);
        } else {
            $pdo->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?')->execute([$roleId,$permissionId]);
        }
        $audit('Updated role permission', 'role_permissions');
        return 'Role permission updated.';
    }

    if ($action === 'generate_doh_report') {
        $type = $_POST['report_type'] ?? 'fhsis';
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = (int)($_POST['month'] ?? date('n'));
        if ($type === 'fhsis') {
            $data = [
                'residents' => (int)$pdo->query('SELECT COUNT(*) FROM residents')->fetchColumn(),
                'consultations' => (int)$pdo->query("SELECT COUNT(*) FROM consultations WHERE YEAR(consultation_date)={$year} AND MONTH(consultation_date)={$month}")->fetchColumn(),
                'vaccinations' => (int)$pdo->query("SELECT COUNT(*) FROM vaccination_records WHERE YEAR(vaccination_date)={$year} AND MONTH(vaccination_date)={$month}")->fetchColumn(),
                'pregnancies' => (int)$pdo->query("SELECT COUNT(*) FROM pregnancies WHERE pregnancy_status='Active'")->fetchColumn(),
            ];
            $pdo->prepare("INSERT INTO fhsis_reports (report_month,report_year,report_data,status,notes) VALUES (?,?,?,'Draft',?) ON DUPLICATE KEY UPDATE report_data=VALUES(report_data),notes=VALUES(notes)")->execute([$month,$year,json_encode($data),trim($_POST['notes'] ?? '')]);
        } elseif ($type === 'pidsr') {
            $week = (int)($_POST['week'] ?? date('W'));
            $rows = $pdo->query("SELECT dt.disease_name,COUNT(*) count FROM disease_cases dc JOIN disease_types dt ON dt.id=dc.disease_id WHERE YEAR(dc.case_date)={$year} AND WEEK(dc.case_date,1)={$week} GROUP BY dt.id")->fetchAll(PDO::FETCH_ASSOC);
            $pdo->prepare("INSERT INTO pidsr_reports (report_week,report_year,disease_data,status) VALUES (?,?,?,'Draft') ON DUPLICATE KEY UPDATE disease_data=VALUES(disease_data)")->execute([$week,$year,json_encode($rows)]);
        } else {
            $stats = $pdo->query("SELECT SUM(treatment_status='Active') new_cases,SUM(treatment_status='Completed') completed,SUM(treatment_status='Lost to follow-up') lost,SUM(treatment_status='Died') deaths FROM tb_patients")->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("INSERT INTO ntp_tb_reports (report_month,report_year,new_tb_cases,completed_treatment,lost_to_follow_up,tb_deaths,status) VALUES (?,?,?,?,?,?,'Draft') ON DUPLICATE KEY UPDATE new_tb_cases=VALUES(new_tb_cases),completed_treatment=VALUES(completed_treatment),lost_to_follow_up=VALUES(lost_to_follow_up),tb_deaths=VALUES(tb_deaths)")->execute([$month,$year,$stats['new_cases']??0,$stats['completed']??0,$stats['lost']??0,$stats['deaths']??0]);
        }
        $audit('Generated DOH report', $type . '_reports');
        return strtoupper($type) . ' report generated.';
    }

    if ($action === 'run_database_maintenance') {
        $tables = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) $pdo->query('ANALYZE TABLE `' . str_replace('`', '``', $table) . '`');
        $audit('Ran database table analysis', 'system');
        return count($tables) . ' database tables analyzed.';
    }

    if ($action === 'restore_database') {
        if (($_POST['restore_confirmation'] ?? '') !== 'RESTORE RHU') {
            throw new RuntimeException('Enter RESTORE RHU to confirm the restore.');
        }
        $upload = $_FILES['backup_file'] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($upload['size'] ?? 0) > 25 * 1024 * 1024) {
            throw new RuntimeException('Upload a valid RHU SQL backup no larger than 25 MB.');
        }
        $contents = file_get_contents($upload['tmp_name']);
        if (!is_string($contents) || !str_starts_with($contents, '-- RedPulse RHU database backup')) {
            throw new RuntimeException('Only backups generated by this dashboard can be restored.');
        }
        $statement = '';
        $executed = 0;
        foreach (preg_split('/\R/', $contents) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) continue;
            $statement .= $line . "\n";
            if (!str_ends_with($trimmed, ';')) continue;
            $sql = trim($statement);
            $statement = '';
            if (!preg_match('/^(SET FOREIGN_KEY_CHECKS|DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO)/i', $sql)) {
                throw new RuntimeException('Backup contains a disallowed SQL statement.');
            }
            $pdo->exec($sql);
            $executed++;
        }
        $audit('Restored generated RHU SQL backup', 'system');
        return "Database restore completed ({$executed} statements).";
    }

    return null;
}

function renderAdminExtendedPanel(PDO $pdo, string $tab): void
{
    echo '<style>
        .admin-tool-group {
            position: relative;
            z-index: 40;
        }
        .admin-tool-group > summary::-webkit-details-marker { display: none; }
        .admin-tool-group > summary::marker { display: none; }
        .admin-tool-group .admin-tool-panel {
            position: absolute;
            left: 0;
            top: calc(100% + .6rem);
            width: min(100%, 1200px);
            z-index: 9999;
            box-shadow: 0 24px 50px rgba(15, 23, 42, .18);
        }
        @keyframes adminToolPopup {
            0% { opacity: 0; transform: scale(.97) translateY(-8px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>';

    $residents = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) name FROM residents ORDER BY first_name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    $staff = $pdo->query("SELECT s.id, CONCAT(u.first_name,' ',u.last_name) name FROM staff s JOIN users u ON u.id=s.user_id ORDER BY u.first_name")->fetchAll(PDO::FETCH_ASSOC);
    $select = static function (string $name, array $rows, string $placeholder): void {
        echo '<select required name="' . htmlspecialchars($name) . '" class="rounded border p-2"><option value="">' . htmlspecialchars($placeholder) . '</option>';
        foreach ($rows as $row) echo '<option value="' . (int)$row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
        echo '</select>';
    };
    $toolTitles = [
        'residents' => '👥 Resident Registration & Management Tools',
        'staff' => '👨‍⚕️ Staff Account & Profile Management Tools',
        'vaccination' => '💉 Immunization & Vaccination Management Tools',
        'maternal' => '🤰 Maternal Health & Pregnancy Tools',
        'disease' => '🦠 Disease Surveillance & Case Management Tools',
        'medicine' => '💊 Medicine Inventory Management Tools',
        'vital' => '📜 Vital Statistics (Birth & Death) Management Tools',
        'reports' => '📊 DOH & Health Report Generation Tools',
        'security' => '🔒 System Roles & Security Permissions Tools',
    ];

    $title = $toolTitles[$tab] ?? 'Database Management Tools';
    $open = '<div class="admin-tool-panel mb-4 grid gap-4 rounded-2xl border border-emerald-100 bg-white/95 p-4 shadow-sm ring-1 ring-emerald-100">';
    $close = '</div>';

    if ($tab === 'residents') {
        echo $open;
        echo '<form method="post" class="grid gap-2 text-xs sm:grid-cols-4"><input type="hidden" name="action" value="save_resident"><input type="number" name="resident_id" placeholder="Existing ID (blank=new)" class="rounded border p-2"><input required name="first_name" placeholder="First name" class="rounded border p-2"><input required name="last_name" placeholder="Last name" class="rounded border p-2"><input name="middle_name" placeholder="Middle name" class="rounded border p-2"><input required type="date" name="date_of_birth" class="rounded border p-2"><select name="gender" class="rounded border p-2"><option>Female</option><option>Male</option><option>Other</option></select><input name="civil_status" placeholder="Civil status" class="rounded border p-2"><input name="contact_number" placeholder="Contact" class="rounded border p-2"><input type="email" name="email" placeholder="Email" class="rounded border p-2"><input required name="address" placeholder="Address" class="rounded border p-2"><input required name="barangay" placeholder="Barangay" class="rounded border p-2"><input name="philhealth_id" placeholder="PhilHealth ID" class="rounded border p-2"><input name="blood_type" placeholder="Blood type" class="rounded border p-2"><input name="allergies" placeholder="Allergies" class="rounded border p-2"><input name="medical_conditions" placeholder="Medical conditions" class="rounded border p-2"><button class="rounded bg-teal-700 p-2 font-bold text-white">Create / Update Resident</button></form>';
        echo $close;
    } elseif ($tab === 'staff') {
        echo $open;
        $nasugbuBarangays = [
            'Barangay 1 (Poblacion)', 'Barangay 2 (Poblacion)', 'Barangay 3 (Poblacion)', 'Barangay 4 (Poblacion)', 
            'Barangay 5 (Poblacion)', 'Barangay 6 (Poblacion)', 'Barangay 7 (Poblacion)', 'Barangay 8 (Poblacion)', 
            'Barangay 9 (Poblacion)', 'Barangay 10 (Poblacion)', 'Barangay 11 (Poblacion)', 'Barangay 12 (Poblacion)', 
            'Aga', 'Balaytigue', 'Banilad', 'Bilaran', 'Bucana', 'Calayo', 'Catandaan', 'Cogunan', 'Dayap', 'Looc', 
            'Lumbangan', 'Malabrigo', 'Mataas Na Pulo', 'Maugat', 'Papaya', 'Reparo', 'Talangan', 'Tumalim', 'Utod', 'Wawa'
        ];
        try {
            $dbBrgys = $pdo->query("SELECT name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($dbBrgys)) $nasugbuBarangays = array_unique(array_merge($dbBrgys, $nasugbuBarangays));
        } catch (Throwable $eBrgy) {}
        sort($nasugbuBarangays);

        $brgyOptionsHtml = '<option value="">-- Select Barangay --</option>';
        foreach ($nasugbuBarangays as $bName) {
            $brgyOptionsHtml .= '<option value="' . htmlspecialchars($bName) . '">' . htmlspecialchars($bName) . '</option>';
        }

        echo '<div class="create-staff-panel bg-white rounded-xl shadow-sm border border-gray-100 space-y-4 p-4">
            <div class="create-staff-heading">
                <div>
                    <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                        <span>👨‍⚕️ Create RHU Healthcare Staff Account</span>
                    </h3>
                    <p class="text-xs text-gray-500">Create a login account for RHU physicians, nurses, midwives, medtechs, and sanitary inspectors.</p>
                </div>
            </div>
            <form method="post" class="create-staff-form grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <input type="hidden" name="action" value="create_staff">
                <label class="block">First Name * <input required name="first_name" placeholder="First Name" class="mt-1 w-full p-2 border rounded border-gray-300 bg-white"></label>
                <label class="block">Last Name * <input required name="last_name" placeholder="Last Name" class="mt-1 w-full p-2 border rounded border-gray-300 bg-white"></label>
                <label class="block">Email Address * <input required type="email" name="email" placeholder="staff.name@nasugbu.gov.ph" class="mt-1 w-full p-2 border rounded border-gray-300 bg-white"></label>
                <label class="block">Password * <input required type="password" name="password" placeholder="Account Password" class="mt-1 w-full p-2 border rounded border-gray-300 bg-white"></label>
                <label class="block">Staff Position * 
                    <select required name="staff_type" id="staff_type_select" class="mt-1 w-full p-2 border rounded border-gray-300 bg-white font-bold text-gray-800">
                        <option value="PHYSICIAN" selected>Rural Health Physician</option>
                        <option value="NURSE">Public Health Nurse</option>
                        <option value="MIDWIFE">Midwife</option>
                        <option value="MEDTECH">Medical Technologist</option>
                        <option value="SANITARY_INSPECTOR">Sanitary Inspector</option>
                        <option value="ADMIN_STAFF">RHU Admin Staff</option>
                    </select>
                </label>
                <label class="block">License Number / PRC No. 
                    <input name="license_number" placeholder="PRC License No." class="mt-1 w-full p-2 border rounded border-gray-300 bg-white font-mono">
                </label>
                <label class="block">Contact Phone Number 
                    <input name="phone_number" placeholder="e.g. 09171234567" class="mt-1 w-full p-2 border rounded border-gray-300 bg-white">
                </label>
                <label class="block">Specialization / Department 
                    <input name="specialization" placeholder="e.g. General Medicine, Maternal Care" class="mt-1 w-full p-2 border rounded border-gray-300 bg-white">
                </label>
                <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold shadow-sm transition-all text-xs flex items-center gap-1.5 cursor-pointer">
                        <span>💾</span> Save Healthcare Staff Account
                    </button>
                </div>
            </form>
        </div>';
        echo '<form method="post" class="grid gap-2 text-xs sm:grid-cols-4"><input type="hidden" name="action" value="save_staff_profile">';
        $select('staff_id', $staff, 'Select staff');
        echo '<input required name="staff_type" placeholder="Position" class="rounded border p-2"><input name="license_number" placeholder="License" class="rounded border p-2"><input type="date" name="license_expiry" class="rounded border p-2"><input name="specialization" placeholder="Specialization" class="rounded border p-2"><input name="phone_number" placeholder="Phone" class="rounded border p-2"><input name="address" placeholder="Address" class="rounded border p-2"><input type="date" name="date_hired" class="rounded border p-2"><button class="rounded bg-emerald-700 p-2 font-bold text-white">Update Staff</button></form>';
        echo '<form method="post" onsubmit="return confirm(\'Delete this staff account?\')" class="flex gap-2 text-xs"><input type="hidden" name="action" value="delete_staff">';
        $select('staff_id', $staff, 'Staff to delete');
        echo '<button class="rounded bg-red-700 px-4 font-bold text-white">Delete Staff</button></form>' . $close;
    } elseif ($tab === 'vaccination') {
        $vaccines = $pdo->query('SELECT id,vaccine_name name FROM immunization_schedules ORDER BY vaccine_name')->fetchAll(PDO::FETCH_ASSOC);
        echo $open . '<form method="post" class="grid gap-2 text-xs sm:grid-cols-4"><input type="hidden" name="action" value="save_vaccination"><input type="number" name="vaccination_id" placeholder="Existing ID (blank=new)" class="rounded border p-2">';
        $select('resident_id', $residents, 'Resident'); $select('vaccine_id', $vaccines, 'Vaccine');
        echo '<input required type="date" name="vaccination_date" class="rounded border p-2">'; $select('provider_id', $staff, 'Provider');
        echo '<input name="batch_number" placeholder="Batch" class="rounded border p-2"><input name="site_of_injection" placeholder="Injection site" class="rounded border p-2"><input name="adverse_reactions" placeholder="Adverse reactions" class="rounded border p-2"><input type="date" name="next_dose_date" class="rounded border p-2"><button class="rounded bg-emerald-700 p-2 font-bold text-white">Save Vaccination</button></form><form method="post" onsubmit="return confirm(\'Delete vaccination record?\')" class="flex gap-2 text-xs"><input type="hidden" name="action" value="delete_vaccination"><input required type="number" name="vaccination_id" placeholder="Vaccination ID" class="rounded border p-2"><button class="rounded bg-red-700 px-4 text-white">Delete</button></form>' . $close;
    } elseif ($tab === 'maternal') {
        echo $open . '<form method="post" class="grid gap-2 text-xs sm:grid-cols-3"><input type="hidden" name="action" value="create_pregnancy">';
        $select('resident_id', $residents, 'Mother/resident');
        echo '<label>LMP<input required type="date" name="lmp" class="ml-2 rounded border p-2"></label><label>EDC<input required type="date" name="edc" class="ml-2 rounded border p-2"></label><input name="risk_factors" placeholder="Risk factors" class="rounded border p-2"><label><input type="checkbox" name="high_risk"> High risk</label><button class="rounded bg-pink-700 p-2 font-bold text-white">Create Pregnancy Case</button></form>' . $close;
    } elseif ($tab === 'disease') {
        $diseases = $pdo->query('SELECT id,disease_name name FROM disease_types ORDER BY disease_name')->fetchAll(PDO::FETCH_ASSOC);
        echo $open . '<form method="post" class="grid gap-2 text-xs sm:grid-cols-4"><input type="hidden" name="action" value="create_disease_case">';
        $select('resident_id', $residents, 'Resident'); $select('disease_id', $diseases, 'Disease');
        echo '<input required type="date" name="case_date" class="rounded border p-2"><input type="date" name="onset_date" class="rounded border p-2"><select name="classification" class="rounded border p-2"><option>Suspected</option><option>Probable</option><option>Confirmed</option></select><input name="symptoms" placeholder="Symptoms" class="rounded border p-2"><input name="treatment" placeholder="Treatment" class="rounded border p-2"><input name="outcome" value="Active" placeholder="Outcome" class="rounded border p-2"><input name="case_status" value="Open" placeholder="Status" class="rounded border p-2"><label><input type="checkbox" name="reported_to_doh"> Reported to DOH</label><button class="rounded bg-red-700 p-2 font-bold text-white">Create Disease Case</button></form>' . $close;
    } elseif ($tab === 'medicine') {
        $defaultExpiry = date('Y-m-d', strtotime('+1 year'));
        echo $open . '
        <form method="post" class="bg-gradient-to-r from-orange-50/80 to-amber-50/80 p-4 rounded-xl border border-orange-200/80 space-y-3 text-xs mb-3 shadow-xs">
            <input type="hidden" name="action" value="save_medicine">
            <div class="flex items-center justify-between border-b border-orange-200/60 pb-2">
                <h3 class="font-bold text-orange-950 text-sm flex items-center gap-1.5">
                    <span>💊 Add Medicine to Inventory</span>
                </h3>
                <span class="text-[10px] text-orange-800 font-bold bg-orange-100 px-2.5 py-0.5 rounded-full border border-orange-200">Essential Details Only</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Generic Name <span class="text-red-500">*</span></label>
                    <input required name="generic_name" placeholder="e.g. Paracetamol" class="w-full rounded-lg border border-gray-300 p-2 text-xs focus:border-orange-500 bg-white shadow-2xs">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Brand Name</label>
                    <input name="brand_name" placeholder="e.g. Biogesic" class="w-full rounded-lg border border-gray-300 p-2 text-xs focus:border-orange-500 bg-white shadow-2xs">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Quantity in Stock <span class="text-red-500">*</span></label>
                    <input required type="number" min="1" name="quantity" placeholder="e.g. 100" class="w-full rounded-lg border border-gray-300 p-2 text-xs focus:border-orange-500 bg-white font-bold text-gray-900 shadow-2xs">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Expiry Date <span class="text-red-500">*</span></label>
                    <input required type="date" name="expiry_date" value="' . $defaultExpiry . '" class="w-full rounded-lg border border-gray-300 p-2 text-xs focus:border-orange-500 bg-white shadow-2xs">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Reorder Alert Level</label>
                    <input type="number" min="1" name="reorder_level" value="30" placeholder="30" class="w-full rounded-lg border border-gray-300 p-2 text-xs focus:border-orange-500 bg-white shadow-2xs">
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-orange-200/60">
                <div class="flex items-center gap-2">
                    <input type="number" name="medicine_id" placeholder="Existing ID to update (blank = new)" class="w-56 rounded-lg border border-gray-300 p-1.5 text-xs bg-white">
                </div>
                <button type="submit" class="rounded-lg bg-orange-700 hover:bg-orange-800 px-5 py-2 font-bold text-white shadow-sm transition-all text-xs flex items-center gap-1.5 cursor-pointer">
                    <span>＋</span> Save Medicine Item
                </button>
            </div>
        </form>
        <form method="post" onsubmit="return confirm(\'Delete medicine item?\')" class="flex items-center gap-2 text-xs bg-red-50/60 p-2.5 rounded-lg border border-red-100">
            <input type="hidden" name="action" value="delete_medicine">
            <span class="font-bold text-red-800">Delete Item:</span>
            <input required type="number" name="medicine_id" placeholder="Medicine ID (e.g. 5)" class="w-36 rounded border p-1.5 text-xs bg-white">
            <button class="rounded-lg bg-red-700 hover:bg-red-800 px-3 py-1.5 font-bold text-white">Delete Item</button>
        </form>' . $close;
    } elseif ($tab === 'vital') {
        echo $open . '<form method="post" class="grid gap-2 text-xs sm:grid-cols-4"><input type="hidden" name="action" value="save_vital"><select name="vital_type" class="rounded border p-2"><option value="birth">Birth</option><option value="death">Death</option></select><input type="number" name="vital_id" placeholder="Existing ID (blank=new)" class="rounded border p-2"><input required name="certificate_number" placeholder="Certificate number" class="rounded border p-2"><input required name="person_name" placeholder="Child/deceased name" class="rounded border p-2"><input required type="date" name="record_date" class="rounded border p-2"><input type="time" name="record_time" class="rounded border p-2"><input required name="location" placeholder="Place" class="rounded border p-2">';
        $select('mother_id', $residents, 'Mother (birth only)');
        echo '<input name="father_name" placeholder="Father (birth)" class="rounded border p-2"><input name="gender" placeholder="Gender" class="rounded border p-2"><input type="number" step=".01" name="birth_weight" placeholder="Birth weight kg" class="rounded border p-2"><input name="cause_of_death" placeholder="Cause of death" class="rounded border p-2"><input name="icd_code" placeholder="ICD code" class="rounded border p-2"><input type="number" name="age_at_death" placeholder="Age at death" class="rounded border p-2"><button class="rounded bg-purple-700 p-2 font-bold text-white">Create Vital Record</button></form>' . $close;
    } elseif ($tab === 'reports') {
        echo $open . '<form method="post" class="grid gap-2 text-xs sm:grid-cols-5"><input type="hidden" name="action" value="generate_doh_report"><select name="report_type" class="rounded border p-2"><option value="fhsis">FHSIS</option><option value="pidsr">PIDSR</option><option value="ntp_tb">NTP-TB</option></select><input type="number" min="1" max="12" name="month" value="' . date('n') . '" class="rounded border p-2"><input type="number" min="1" max="53" name="week" value="' . date('W') . '" class="rounded border p-2"><input type="number" name="year" value="' . date('Y') . '" class="rounded border p-2"><button class="rounded bg-purple-700 p-2 font-bold text-white">Generate Report</button></form>';
        $reportRows = $pdo->query("SELECT 'FHSIS' type,report_month period,report_year year,status,created_at FROM fhsis_reports UNION ALL SELECT 'PIDSR',report_week,report_year,status,created_at FROM pidsr_reports UNION ALL SELECT 'NTP-TB',report_month,report_year,status,created_at FROM ntp_tb_reports ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        echo '<table class="w-full text-xs"><thead><tr><th>Type</th><th>Period</th><th>Year</th><th>Status</th><th>Created</th></tr></thead><tbody>';
        foreach ($reportRows as $report) echo '<tr><td>' . htmlspecialchars($report['type']) . '</td><td>' . (int)$report['period'] . '</td><td>' . (int)$report['year'] . '</td><td>' . htmlspecialchars($report['status']) . '</td><td>' . htmlspecialchars($report['created_at']) . '</td></tr>';
        echo '</tbody></table>' . $close;
    } elseif ($tab === 'security') {
        $roles = $pdo->query('SELECT id,name FROM roles ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $permissions = $pdo->query('SELECT id,name FROM permissions ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        echo $open . '<form method="post" class="flex flex-wrap gap-2 text-xs"><input type="hidden" name="action" value="save_role"><input required name="role_name" placeholder="Role name" class="rounded border p-2"><input name="description" placeholder="Description" class="rounded border p-2"><button class="rounded bg-purple-700 px-4 text-white">Save Role</button></form><form method="post" class="flex flex-wrap gap-2 text-xs"><input type="hidden" name="action" value="save_permission"><input required name="permission_name" placeholder="Permission name" class="rounded border p-2"><input name="description" placeholder="Description" class="rounded border p-2"><button class="rounded bg-slate-700 px-4 text-white">Save Permission</button></form><form method="post" class="flex flex-wrap gap-2 text-xs"><input type="hidden" name="action" value="set_role_permission">';
        $select('role_id', $roles, 'Role'); $select('permission_id', $permissions, 'Permission');
        echo '<label class="p-2"><input type="checkbox" name="enabled"> Enabled</label><button class="rounded bg-red-700 px-4 text-white">Apply Permission</button></form>' . $close;
    } elseif ($tab === 'system') {
        echo $open . '<form method="post" class="text-xs"><input type="hidden" name="action" value="run_database_maintenance"><button class="rounded bg-emerald-700 px-4 py-2 font-bold text-white">Analyze All Database Tables</button></form><a href="?backup=sql" class="inline-block rounded bg-slate-700 px-4 py-2 text-xs font-bold text-white">Download SQL Backup</a><form method="post" enctype="multipart/form-data" onsubmit="return confirm(\'Restoring replaces current database tables. Continue?\')" class="grid gap-2 rounded border border-red-200 p-3 text-xs sm:grid-cols-3"><input type="hidden" name="action" value="restore_database"><input required type="file" accept=".sql,text/plain" name="backup_file" class="rounded border p-2"><input required name="restore_confirmation" placeholder="Type RESTORE RHU" class="rounded border p-2"><button class="rounded bg-red-700 p-2 font-bold text-white">Restore Generated Backup</button></form>' . $close;
    }
}
