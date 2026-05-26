$(function(){
  $('.datatable').each(function(){
    if (!$.fn.DataTable.isDataTable(this)) {
      $(this).DataTable({
        order:[[0,'desc']],
        pageLength:10,
        autoWidth:false,
        scrollX:true,
        responsive:false,
        language:{search:'Search:',lengthMenu:'Show _MENU_'}
      });
    }
  });

  if (!$('.mobile-sidebar-backdrop').length) $('body').append('<div class="mobile-sidebar-backdrop"></div>');

  $('#menuBtn').on('click',function(){
    $('.sidebar').toggleClass('show');
    $('.mobile-sidebar-backdrop').toggleClass('show');
  });

  $(document).on('click','.mobile-sidebar-backdrop,.sidebar .navlink',function(){
    if (window.innerWidth < 992) {
      $('.sidebar').removeClass('show');
      $('.mobile-sidebar-backdrop').removeClass('show');
    }
  });

  $(window).on('resize',function(){
    if (window.innerWidth >= 992) {
      $('.sidebar').removeClass('show');
      $('.mobile-sidebar-backdrop').removeClass('show');
    }
  });

  $('.scanner:visible:first').trigger('focus');
});

function loadItem(serial, target){
  if(!serial)return;
  $.get('ajax/get_item.php',{serial},function(r){
    if(r.ok){
      Object.keys(r.item).forEach(k=>$(`[name=${k}]`).val(r.item[k]));
      if(target) $(target).html(`<div class='alert alert-info'>Found: <b>${r.item.item_description}</b> | Stock: ${r.item.stock} | Status: ${r.item.status}</div>`);
    } else if(target) $(target).html(`<div class='alert alert-warning'>No matching item found.</div>`);
  },'json');
}
function openItemModal(id=''){
  $('#itemForm')[0].reset();
  $('#item_id').val('');
  if(id){
    $.get('ajax/get_item_by_id.php',{id},function(r){
      if(r.ok){ Object.keys(r.item).forEach(k=>$(`[name=${k}]`).val(r.item[k])); $('#item_id').val(r.item.id); }
    },'json');
  }
  new bootstrap.Modal('#itemModal').show();
}
function saveItem(){ $.post('ajax/save_item.php',$('#itemForm').serialize(),function(r){ alert(r.message); if(r.ok) location.reload(); },'json'); }
function deleteItem(id){ if(confirm('Delete this item?')) $.post('ajax/delete_item.php',{id},function(r){alert(r.message); if(r.ok) location.reload();},'json'); }
