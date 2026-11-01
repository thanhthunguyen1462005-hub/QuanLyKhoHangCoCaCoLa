<?php
$pageTitle = 'Lập biên bản kiểm định chất lượng';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('qaqc');
$pdo = Database::getConnection();
$errors = [];
$success = '';
$selected = (int) ($_GET['request'] ?? $_POST['request_id'] ?? 0);
$preview = ($_POST['action'] ?? '') === 'preview';
function qcEsc($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function qcFetchRequest(PDO $pdo, int $id) {
    $q=$pdo->prepare("SELECT r.*,l.id AS lot_id,l.lot_number,l.product_id,p.sku,p.name,p.type FROM qc_requests r JOIN product_lots l ON l.id=r.lot_id JOIN products p ON p.id=l.product_id WHERE r.id=? AND r.status='PENDING'");
    $q->execute([$id]);return $q->fetch();
}
$request = null; $criteria=[]; $answers=[];$reason='';$verdict='';
if ($pdo) {
    try {
        if ($selected) {
            $request=qcFetchRequest($pdo,$selected);
            if ($request) {
                $q=$pdo->prepare('SELECT id,criterion_name,is_required FROM qc_criteria WHERE product_id=? AND is_active=1 ORDER BY id');
                $q->execute([$request['product_id']]);$criteria=$q->fetchAll();
            }
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['preview','save'],true)) {
            if (!isValidCsrfToken($_POST['csrf_token']??null)) $errors[]='Phiên làm việc không hợp lệ, vui lòng tải lại trang.';
            if (!$request) $errors[]='Phiếu yêu cầu không tồn tại hoặc đã được xử lý.';
            if (!$criteria) $errors[]='Nguyên liệu chưa được thiết lập tiêu chí kiểm định.';
            $answers=is_array($_POST['criteria']??null)?$_POST['criteria']:[];
            foreach ($criteria as $c) {
                $value=$answers[$c['id']]??'';
                if ($c['is_required'] && $value==='') $errors[]='Chưa nhập kết quả: '.$c['criterion_name'];
                elseif ($value!=='' && !in_array($value,['PASSED','REJECTED'],true)) $errors[]='Kết quả không hợp lệ: '.$c['criterion_name'];
            }
            $verdict=in_array('REJECTED',array_values($answers),true)?'REJECTED':'PASSED';
            $reason=trim((string)($_POST['reason']??''));
            if ($verdict==='REJECTED' && $reason==='') $errors[]='Phải nhập lý do khi kết quả Không đạt.';
            if (!$errors && ($_POST['action']??'')==='preview') {
                $_SESSION['qc_preview_hash'] = hash('sha256', json_encode([$selected,$answers,$reason], JSON_UNESCAPED_UNICODE));
            }
            if (!$errors && ($_POST['action']??'')==='save') {
                $hash = hash('sha256', json_encode([$selected,$answers,$reason], JSON_UNESCAPED_UNICODE));
                if (!hash_equals((string)($_SESSION['qc_preview_hash'] ?? ''), $hash)) $errors[]='Cần xem trước và xác nhận lại biên bản trước khi lưu.';
            }
            if (!$errors && ($_POST['action']??'')==='save') {
                try {
                    $pdo->beginTransaction();
                    $lock=$pdo->prepare("SELECT status FROM qc_requests WHERE id=? FOR UPDATE");$lock->execute([$selected]);
                    if ($lock->fetchColumn()!=='PENDING') throw new RuntimeException('Phiếu đã được xử lý bởi người khác.');
                    // Mã ngẫu nhiên + kiểm soát UNIQUE nhằm tránh trùng khi nhiều người lưu đồng thời.
                    $code='QC-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
                    $q=$pdo->prepare("INSERT INTO qc_inspections (qc_code,inspection_type,reference_code,inspector_id,inspection_date,result,passed_quantity,rejected_quantity,defect_reason,notes) VALUES (?,'INCOMING_MATERIAL',?,?,NOW(),?,0,0,?,?)");
                    $q->execute([$code,$request['request_code'],(int)currentUser()['id'],$verdict,$verdict==='REJECTED'?$reason:null,'Lô: '.$request['lot_number']]);
                    $inspectionId=(int)$pdo->lastInsertId();
                    $q=$pdo->prepare('INSERT INTO qc_criterion_results (inspection_id,criterion_id,result) VALUES (?,?,?)');
                    foreach($criteria as $c) {if(isset($answers[$c['id']]) && $answers[$c['id']]!=='') $q->execute([$inspectionId,$c['id'],$answers[$c['id']]]);}
                    $q=$pdo->prepare('INSERT INTO qc_request_inspections (request_id,inspection_id) VALUES (?,?)');$q->execute([$selected,$inspectionId]);
                    $q=$pdo->prepare("UPDATE qc_requests SET status='COMPLETED' WHERE id=?");$q->execute([$selected]);
                    $q=$pdo->prepare('UPDATE product_lots SET qc_status=? WHERE id=?');$q->execute([$verdict,(int)$request['lot_id']]);
                     $pdo->commit();unset($_SESSION['qc_preview_hash']);$success='Đã lập biên bản '.$code.' thành công.';$request=null;$selected=0;$preview=false;
                } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();error_log('QAQC save error: '.$e->getMessage());$errors[]='Không thể lưu biên bản. Hãy kiểm tra dữ liệu hoặc thử lại.';}
            }
        }
        $pending=$pdo->query("SELECT r.id,r.request_code,r.created_at,p.sku,p.name,l.lot_number FROM qc_requests r JOIN product_lots l ON l.id=r.lot_id JOIN products p ON p.id=l.product_id WHERE r.status='PENDING' AND p.type='RAW_MATERIAL' ORDER BY r.created_at,r.id")->fetchAll();
        $saved=$pdo->query("SELECT i.qc_code,i.reference_code,i.inspection_date,i.result,i.defect_reason,u.fullname FROM qc_request_inspections ri JOIN qc_inspections i ON i.id=ri.inspection_id JOIN users u ON u.id=i.inspector_id ORDER BY i.inspection_date DESC LIMIT 30")->fetchAll();
    } catch (Throwable $e) {error_log('QAQC load error: '.$e->getMessage());$errors[]='Không tải được dữ liệu QA/QC. Hãy import database/qaqc_extension.sql sau schema.sql.';$pending=[];$saved=[];}
}
include __DIR__.'/../../includes/header.php';include __DIR__.'/../../includes/sidebar.php';include __DIR__.'/../../includes/navbar.php';
?>
<div class="page-container">
 <div class="d-flex align-items-center gap-3 mb-4"><span class="fs-2 text-primary"><i class="fa-solid fa-microscope"></i></span><div><h3 class="fw-bold mb-1">Lập biên bản kiểm định chất lượng</h3><div class="text-muted">Bộ phận QA/QC · Kiểm tra lô nguyên liệu theo phiếu yêu cầu</div></div></div>
 <?php if (!$pdo): ?><div class="alert alert-danger">Không kết nối được MySQL. Kiểm tra config/database.php.</div><?php endif; ?>
 <?php foreach($errors as $error): ?><div class="alert alert-danger"><?= qcEsc($error) ?></div><?php endforeach; ?>
 <?php if($success): ?><div class="alert alert-success"><?= qcEsc($success) ?></div><?php endif; ?>
 <?php if($request): ?>
 <div class="card border-0 shadow-sm mb-4"><div class="card-body"><h5 class="fw-bold mb-3">Phiếu <?= qcEsc($request['request_code']) ?></h5>
 <div class="row g-3 mb-3"><div class="col-md-3"><small class="text-muted">Mã nguyên liệu</small><div class="fw-semibold"><?= qcEsc($request['sku']) ?></div></div><div class="col-md-3"><small class="text-muted">Tên nguyên liệu</small><div class="fw-semibold"><?= qcEsc($request['name']) ?></div></div><div class="col-md-3"><small class="text-muted">Mã lô</small><div class="fw-semibold"><?= qcEsc($request['lot_number']) ?></div></div><div class="col-md-3"><small class="text-muted">Mã biên bản</small><div class="fw-semibold">Tự tạo khi xác nhận lưu</div></div></div>
 <form method="post"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><?= csrfField() ?>
 <h6 class="fw-bold mt-4">Kết quả từng tiêu chí</h6><div class="table-responsive"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th>Tiêu chí</th><th>Kết quả</th></tr></thead><tbody>
 <?php foreach($criteria as $c): ?><tr><td><?= qcEsc($c['criterion_name']) ?> <?= $c['is_required']?'<span class="text-danger">*</span>':'' ?></td><td><select class="form-select" name="criteria[<?= (int)$c['id'] ?>]" <?= $c['is_required']?'required':'' ?>><option value="">-- Chọn kết quả --</option><option value="PASSED" <?= ($answers[$c['id']]??'')==='PASSED'?'selected':'' ?>>Đạt</option><option value="REJECTED" <?= ($answers[$c['id']]??'')==='REJECTED'?'selected':'' ?>>Không đạt</option></select></td></tr><?php endforeach; ?>
 </tbody></table></div><label class="form-label fw-semibold" for="reason">Lý do không đạt (bắt buộc nếu không đạt)</label><textarea class="form-control mb-3" id="reason" name="reason" rows="3"><?= qcEsc($reason) ?></textarea>
 <?php if($preview && !$errors): ?><div class="alert <?= $verdict==='PASSED'?'alert-success':'alert-warning' ?>"><strong>Xem trước biên bản:</strong> Phiếu <?= qcEsc($request['request_code']) ?>, lô <?= qcEsc($request['lot_number']) ?> · Kết luận <strong><?= $verdict==='PASSED'?'Đạt':'Không đạt' ?></strong>. Hãy kiểm tra lại các tiêu chí và nhấn Xác nhận lập biên bản.</div><?php endif; ?>
 <div class="d-flex gap-2 flex-wrap"><button class="btn btn-outline-primary" name="action" value="preview">Kiểm tra / Xem trước</button><?php if($preview && !$errors): ?><button class="btn btn-success" name="action" value="save">Xác nhận lập biên bản</button><?php endif; ?><a class="btn btn-light border" href="<?= BASE_URL ?>/pages/qaqc/">Quay lại</a></div></form></div></div>
 <?php else: ?>
 <div class="card shadow-sm border-0 mb-4"><div class="card-body"><h5 class="fw-bold">Danh sách phiếu yêu cầu kiểm định cần xử lý</h5><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Mã phiếu</th><th>Mã nguyên liệu</th><th>Tên nguyên liệu</th><th>Mã lô</th><th>Thao tác</th></tr></thead><tbody>
 <?php foreach(($pending??[]) as $r): ?><tr><td><?= qcEsc($r['request_code']) ?></td><td><?= qcEsc($r['sku']) ?></td><td><?= qcEsc($r['name']) ?></td><td><?= qcEsc($r['lot_number']) ?></td><td><a class="btn btn-sm btn-primary" href="?request=<?= (int)$r['id'] ?>">Lập biên bản</a></td></tr><?php endforeach; ?>
 <?php if(empty($pending)): ?><tr><td colspan="5" class="text-center text-muted py-4">Không có phiếu yêu cầu kiểm định phù hợp.</td></tr><?php endif; ?>
 </tbody></table></div></div></div>
 <?php endif; ?>
 <div class="card border-0 shadow-sm"><div class="card-body"><h5 class="fw-bold">Biên bản đã lập</h5><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Mã biên bản</th><th>Mã phiếu</th><th>Ngày</th><th>QA/QC</th><th>Kết luận</th><th>Lý do</th></tr></thead><tbody><?php foreach(($saved??[]) as $r): ?><tr><td><?= qcEsc($r['qc_code']) ?></td><td><?= qcEsc($r['reference_code']) ?></td><td><?= qcEsc($r['inspection_date']) ?></td><td><?= qcEsc($r['fullname']) ?></td><td><span class="badge <?= $r['result']==='PASSED'?'bg-success':'bg-danger' ?>"><?= $r['result']==='PASSED'?'Đạt':'Không đạt' ?></span></td><td><?= qcEsc($r['defect_reason']??'—') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php include __DIR__.'/../../includes/footer.php'; ?>
