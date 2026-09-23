<?php include 'includes/header.php'; require_admin(); $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){ $name=$_POST['name']; $username=$_POST['username']; $role=$_POST['role']; $pass=password_hash($_POST['password']?:'admin123', PASSWORD_DEFAULT); $stmt=$conn->prepare('INSERT INTO users(name,username,password,role) VALUES(?,?,?,?)'); $stmt->bind_param('ssss',$name,$username,$pass,$role); $msg=$stmt->execute()?'User created.':'Username already exists.'; }
$res=$conn->query('SELECT id,name,username,role,created_at FROM users ORDER BY id DESC'); ?>
<div class='page-head'><div><h3><i class='bi bi-people'></i> User Management</h3><p class='page-sub'>Create accounts and manage role-based access</p></div></div>
<?php if($msg):?><div class='alert alert-info d-flex align-items-center gap-2'><i class='bi bi-info-circle-fill'></i><span><?=$msg?></span></div><?php endif;?>
<div class='row g-3'>
  <div class='col-md-4'><div class='card cardx p-3'>
    <h5 class='mb-3'><i class='bi bi-person-plus'></i> Add User</h5>
    <form method='post'>
      <label class='form-label'>Name</label><input class='form-control mb-2' name='name' placeholder='Full name' required>
      <label class='form-label'>Username</label><input class='form-control mb-2' name='username' placeholder='Username' required>
      <label class='form-label'>Password</label><input class='form-control mb-2' name='password' placeholder='Leave blank for default'>
      <label class='form-label'>Role</label><select class='form-select mb-3' name='role'><option value='staff'>Staff</option><option value='viewer'>Viewer</option><option value='admin'>Admin</option></select>
      <button class='btn btn-primary w-100'><i class='bi bi-plus-lg'></i> Add User</button>
    </form>
  </div></div>
  <div class='col-md-8'><div class='card cardx p-3'>
    <h5 class='mb-3'><i class='bi bi-list-ul'></i> Existing Users</h5>
    <table class='table datatable'><thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Role</th><th>Created</th></tr></thead><tbody><?php while($u=$res->fetch_assoc()):?><tr><td><?=$u['id']?></td><td><?=e($u['name'])?></td><td><?=e($u['username'])?></td><td><span class='badge bg-secondary'><?=e(strtoupper($u['role']))?></span></td><td><?=$u['created_at']?></td></tr><?php endwhile;?></tbody></table>
  </div></div>
</div>
<?php include 'includes/footer.php'; ?>
