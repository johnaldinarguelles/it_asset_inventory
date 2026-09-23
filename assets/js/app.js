function chartTheme(){
  const dark = document.documentElement.getAttribute('data-theme') === 'dark';
  return {
    dark,
    text: dark ? '#94a3b8' : '#667085',
    grid: { color: dark ? 'rgba(148,163,184,.12)' : 'rgba(16,24,40,.06)' },
    tooltipBg: dark ? '#0f172a' : '#101828'
  };
}

function showToast(message, type = 'success'){
  const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  const $toast = $(
    `<div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body"><i class="bi ${icons[type] || icons.info} me-2"></i>${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>`
  );
  $('#toastContainer').append($toast);
  const toast = new bootstrap.Toast($toast[0], { delay: 4000 });
  $toast.on('hidden.bs.toast', function(){ $(this).remove(); });
  toast.show();
}

function confirmAction(message, onConfirm, title = 'Please confirm'){
  $('#confirmModalTitle').text(title);
  $('#confirmModalBody').text(message);
  const modalEl = document.getElementById('confirmModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  $('#confirmModalOk').off('click').on('click', function(){
    modal.hide();
    onConfirm();
  });
  modal.show();
}

$(function(){
  if (document.documentElement.getAttribute('data-theme')==='dark') $('#darkToggle').text('☀️');

  $('#darkToggle').on('click',function(){
    const isDark = document.documentElement.getAttribute('data-theme')==='dark';
    if(isDark){
      document.documentElement.removeAttribute('data-theme');
      localStorage.removeItem('theme');
      $(this).text('🌙');
    }else{
      document.documentElement.setAttribute('data-theme','dark');
      localStorage.setItem('theme','dark');
      $(this).text('☀️');
    }
    document.dispatchEvent(new CustomEvent('themechange'));
  });

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
    const isOpen = $('.topnav-inner .navlink').toggleClass('show').hasClass('show');
    $('.mobile-sidebar-backdrop').toggleClass('show');
    $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
  });

  $(document).on('click','.mobile-sidebar-backdrop,.topnav-inner .navlink',function(){
    if (window.innerWidth < 992) {
      $('.topnav-inner .navlink').removeClass('show');
      $('.mobile-sidebar-backdrop').removeClass('show');
      $('#menuBtn').attr('aria-expanded','false');
    }
  });

  $(window).on('resize',function(){
    if (window.innerWidth >= 992) {
      $('.topnav-inner .navlink').removeClass('show');
      $('.mobile-sidebar-backdrop').removeClass('show');
      $('#menuBtn').attr('aria-expanded','false');
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
  },'json').fail(function(){
    if(target) $(target).html(`<div class='alert alert-danger'>Lookup failed. Check your connection and try again.</div>`);
    else showToast('Item lookup failed. Check your connection and try again.', 'danger');
  });
}
function openItemModal(id=''){
  $('#itemForm')[0].reset();
  $('#item_id').val('');
  if(id){
    $.get('ajax/get_item_by_id.php',{id},function(r){
      if(r.ok){ Object.keys(r.item).forEach(k=>$(`[name=${k}]`).val(r.item[k])); $('#item_id').val(r.item.id); }
    },'json').fail(function(){ showToast('Could not load item details. Please try again.', 'danger'); });
  }
  new bootstrap.Modal('#itemModal').show();
}
function saveItem(){
  const $btn = $('#itemModal .modal-footer .btn-primary').prop('disabled', true);
  $.post('ajax/save_item.php',$('#itemForm').serialize(),function(r){
    showToast(r.message, r.ok ? 'success' : 'danger');
    if(r.ok) setTimeout(() => location.reload(), 600);
  },'json').fail(function(){
    showToast('Save failed. Check your connection and try again.', 'danger');
  }).always(function(){ $btn.prop('disabled', false); });
}
function deleteItem(id){
  confirmAction('This item will be removed from the inventory master. Its past transaction history is kept for reporting. Continue?', function(){
    $.post('ajax/delete_item.php',{id},function(r){
      showToast(r.message, r.ok ? 'success' : 'danger');
      if(r.ok) setTimeout(() => location.reload(), 600);
    },'json').fail(function(){
      showToast('Delete failed. Check your connection and try again.', 'danger');
    });
  }, 'Delete item?');
}
