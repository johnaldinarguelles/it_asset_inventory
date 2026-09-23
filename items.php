<?php include 'includes/header.php';
$res=$conn->query("SELECT *,(boh+total_received+total_returned-total_issued) stock, ((boh+total_received+total_returned-total_issued)-actual_stock) variance FROM items ORDER BY updated_at DESC"); ?>
<div class='page-head'>
  <div><h3><i class='bi bi-box-seam'></i> Items &amp; Real-time Stock</h3><p class='page-sub'>Use <b>View</b> to see all receiving, issuance, and return activity for an item. For non-unique items (mouse, office supplies), use one general barcode/item code &mdash; example: all mouse transactions use <b>5718185</b>. Stock = Received + Returned &minus; Issued.</p></div>
  <div class='page-actions'><?php if(is_admin()): ?><a class='btn btn-success' href='export_excel.php?type=items'><i class='bi bi-file-earmark-excel'></i> Excel Export</a> <button class='btn btn-primary' onclick='openItemModal()'><i class='bi bi-plus-lg'></i> Add Item</button><?php endif; ?></div>
</div>
<div class='card cardx p-3'><table class='table table-hover datatable'><thead><tr><th>ID</th><th>Description</th><th>Serial</th><th>Location</th><th>UOM</th><th>Total Received</th><th>Total Usage</th><th>Total Return</th><th>Total Stocks</th><th>Actual</th><th>Variance</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php while($r=$res->fetch_assoc()): ?><tr class='<?= $r['stock']<=0?'out':($r['stock']<=$r['reorder_level']?'low':'') ?>'><td><?=$r['id']?></td><td><?=e($r['item_description'])?></td><td><?=e($r['serial_number'])?></td><td><?=e($r['location'])?></td><td><?=e($r['uom'])?></td><td><?=$r['total_received']?></td><td><?=$r['total_issued']?></td><td><?=$r['total_returned']?></td><td><?=$r['stock']?></td><td><?=$r['actual_stock']?></td><td><?=$r['variance']?></td><td><span class='badge bg-secondary'><?=e($r['status'])?></span></td><td><button type='button' class='btn btn-sm btn-outline-info' onclick='openActivityModal(<?=$r['id']?>)' aria-label='View activity for <?=e($r['item_description'])?>'><i class='bi bi-eye'></i> View</button> <?php if(is_admin()): ?><button class='btn btn-sm btn-outline-primary' onclick='openItemModal(<?=$r['id']?>)' aria-label='Edit <?=e($r['item_description'])?>'><i class='bi bi-pencil'></i> Edit</button> <button class='btn btn-sm btn-outline-danger' onclick='deleteItem(<?=$r['id']?>)' aria-label='Delete <?=e($r['item_description'])?>'><i class='bi bi-trash'></i> Delete</button><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div>
<div class='modal fade' id='itemModal'><div class='modal-dialog modal-lg'><div class='modal-content'><div class='modal-header'><h5>Item Form</h5><button class='btn-close' data-bs-dismiss='modal'></button></div><div class='modal-body'><form id='itemForm'><input type='hidden' name='id' id='item_id'><div class='row g-2'><div class='col-md-6'><label>Description</label><input class='form-control' name='item_description' required></div><div class='col-md-6'><label>Serial / Barcode / Item Code</label><input class='form-control' name='serial_number' placeholder='Example: 5718185 for all mouse stock'></div><div class='col-md-4'><label>Location</label><input class='form-control' name='location'></div><div class='col-md-2'><label>UOM</label><input class='form-control' name='uom' value='pcs'></div><div class='col-md-2'><label>BOH</label><input type='number' class='form-control' name='boh' value='0'></div><div class='col-md-2'><label>Actual</label><input type='number' class='form-control' name='actual_stock' value='0'></div><div class='col-md-2'><label>Reorder</label><input type='number' class='form-control' name='reorder_level' value='5'></div></div></form></div><div class='modal-footer'><button class='btn btn-primary' onclick='saveItem()'><i class='bi bi-save'></i> Save</button></div></div></div></div>

<div class='modal fade' id='activityModal' tabindex='-1'>
  <div class='modal-dialog modal-xl modal-dialog-scrollable'>
    <div class='modal-content'>
      <div class='modal-header'>
        <h5 class='modal-title'>Item Activity View</h5>
        <button class='btn-close' data-bs-dismiss='modal'></button>
      </div>
      <div class='modal-body' id='activityModalBody'>
        <div class='text-center p-4'>Loading...</div>
      </div>
    </div>
  </div>
</div>
<script>
function openActivityModal(id){
  $('#activityModalBody').html("<div class='text-center p-4'>Loading...</div>");
  const modal = new bootstrap.Modal(document.getElementById('activityModal'));
  modal.show();
  loadActivityModal(id);
}
function loadActivityModal(id, data=''){
  $.get('ajax/item_activity.php?id=' + encodeURIComponent(id) + (data ? '&' + data : ''), function(html){
    $('#activityModalBody').html(html);
    $('#activityModalTable').DataTable({order:[[0,'desc']], pageLength:10, destroy:true, autoWidth:false, scrollX:true});
  }).fail(function(){
    $('#activityModalBody').html("<div class='alert alert-danger mb-0'>Could not load item activity. Check your connection and try again.</div>");
  });
}
$(document).on('submit', '#activityFilterForm', function(e){
  e.preventDefault();
  const id = $(this).find('[name=id]').val();
  loadActivityModal(id, $(this).serialize());
});
</script>

<?php include 'includes/footer.php'; ?>
