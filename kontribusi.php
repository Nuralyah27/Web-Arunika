<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$errors=[]; $title=$region=$category=$description=$source='';
$uploadDir=__DIR__.'/uploads/cultures/';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $title=trim((string)($_POST['title']??'')); $region=trim((string)($_POST['region']??'')); $category=trim((string)($_POST['category']??'')); $description=trim((string)($_POST['description']??'')); $source=trim((string)($_POST['source_info']??''));
 $allowed=['Cerita rakyat','Tradisi lisan','Seni dan pertunjukan','Kuliner','Kerajinan','Permainan tradisional','Upacara adat','Pengetahuan lokal','Lainnya'];
 if(mb_strlen($title)<3||mb_strlen($title)>150)$errors[]='Judul harus 3–150 karakter.';
 if(mb_strlen($region)<2||mb_strlen($region)>100)$errors[]='Daerah asal harus 2–100 karakter.';
 if(!in_array($category,$allowed,true))$errors[]='Pilih kategori yang tersedia.';
 if(mb_strlen($description)<20)$errors[]='Deskripsi minimal 20 karakter.';
 if(mb_strlen($source)>5000)$errors[]='Informasi sumber terlalu panjang.';
 if(empty($_POST['consent']))$errors[]='Konfirmasi izin untuk membagikan materi ini.';
 $imagePath=null; $temp=null; $dest=null;
 if(isset($_FILES['culture_photo'])&&$_FILES['culture_photo']['error']!==UPLOAD_ERR_NO_FILE){$f=$_FILES['culture_photo'];
  if($f['error']!==UPLOAD_ERR_OK)$errors[]='Foto gagal diunggah.';
  elseif($f['size']>5*1024*1024)$errors[]='Ukuran foto maksimal 5 MB.';
  elseif(!is_uploaded_file($f['tmp_name']))$errors[]='File foto tidak valid.';
  else{$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
   if(!isset($types[$mime])||@getimagesize($f['tmp_name'])===false)$errors[]='Foto harus JPG, PNG, atau WEBP.';
   else{$imagePath='uploads/cultures/'.bin2hex(random_bytes(16)).'.'.$types[$mime];$temp=$f['tmp_name'];$dest=__DIR__.'/'.$imagePath;}
  }
 }
 if(!$errors){try{
  if($imagePath!==null){if(!is_dir($uploadDir)&&!mkdir($uploadDir,0755,true)&&!is_dir($uploadDir))throw new RuntimeException('Folder upload gagal dibuat.');if(!move_uploaded_file($temp,$dest))throw new RuntimeException('Foto gagal disimpan.');}
  $stmt=$pdo->prepare('INSERT INTO cultures (title,region,category,description,source_info,submitted_by,image_path) VALUES (?,?,?,?,?,?,?)');
  $stmt->execute([$title,$region,$category,$description,$source?:null,current_user()['id'],$imagePath]);
  set_flash('Kontribusi berhasil dikirim dan menunggu verifikasi admin.','success');redirect_to('dashboard.php');
 }catch(Throwable $e){if($dest&&is_file($dest))@unlink($dest);error_log('ARUNIKA upload: '.$e->getMessage());$errors[]='Gagal menyimpan. Pastikan kolom image_path sudah ditambahkan ke database.';}}
}
$pageTitle='Tambah Budaya'; require __DIR__.'/includes/header.php';
?>
<style>.form-wrap{max-width:760px;margin:auto}.photo-box{padding:14px;border:1px dashed #c9b9a5;border-radius:12px;background:#fffaf3}.photo-preview{display:none;width:100%;max-width:340px;max-height:260px;object-fit:cover;border-radius:12px;margin-top:12px}.form-actions{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:24px}.form-actions .btn{display:inline-flex;align-items:center}@media(max-width:560px){.form-actions{flex-direction:column-reverse}.form-actions .btn{justify-content:center;width:100%}}</style>
<div class="form-wrap card">
<h1>Kontribusi budaya</h1><p class="muted">Bagikan budaya Nusantara dengan deskripsi, foto, dan sumber informasi. Pastikan materi boleh dibagikan dan hindari pengetahuan sakral tanpa izin komunitas.</p>
<?php if($errors):?><div class="notice error"><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<form method="post" action="kontribusi.php" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label for="title">Nama budaya atau judul</label><input id="title" name="title" maxlength="150" required value="<?=e($title)?>" placeholder="Contoh: Tari Hudoq">
<label for="region">Daerah asal</label><input id="region" name="region" maxlength="100" required value="<?=e($region)?>" placeholder="Contoh: Berau, Kalimantan Timur">
<label for="culture_photo">Foto budaya (opsional)</label><div class="photo-box"><input type="file" id="culture_photo" name="culture_photo" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG, WEBP, maksimal 5 MB.</small><img id="photoPreview" class="photo-preview" alt="Pratinjau foto budaya"></div>
<label for="category">Kategori</label><select id="category" name="category" required><option value="">Pilih kategori</option><?php foreach(['Cerita rakyat','Tradisi lisan','Seni dan pertunjukan','Kuliner','Kerajinan','Permainan tradisional','Upacara adat','Pengetahuan lokal','Lainnya'] as $cat):?><option value="<?=e($cat)?>" <?=$category===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach;?></select>
<label for="description">Deskripsi budaya</label><textarea id="description" name="description" required><?=e($description)?></textarea>
<label for="source_info">Sumber informasi / narasumber</label><textarea id="source_info" name="source_info" maxlength="5000" placeholder="Tuliskan referensi atau narasumber (dengan izin)." ><?=e($source)?></textarea>
<p><label><input type="checkbox" name="consent" value="1" required style="width:auto"> Saya berwenang mengirim informasi ini dan telah memperoleh izin yang diperlukan untuk membagikannya.</label></p>
<div class="form-actions"><a class="btn secondary" href="dashboard.php">← Kembali ke Dashboard</a><button type="submit">Kirim untuk verifikasi</button></div>
</form></div>
<script>const inp=document.getElementById('culture_photo'),prev=document.getElementById('photoPreview');let oldUrl=null;inp.addEventListener('change',function(){if(oldUrl)URL.revokeObjectURL(oldUrl);oldUrl=null;prev.style.display='none';prev.removeAttribute('src');const f=this.files[0];if(!f)return;if(!['image/jpeg','image/png','image/webp'].includes(f.type)){alert('Pilih foto JPG, PNG, atau WEBP.');this.value='';return;}if(f.size>5242880){alert('Ukuran foto maksimal 5 MB.');this.value='';return;}oldUrl=URL.createObjectURL(f);prev.src=oldUrl;prev.style.display='block';});</script>
<?php require __DIR__.'/includes/footer.php'; ?>
