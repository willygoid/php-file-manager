<?php
session_start();
header('Content-Type: application/json');
$password = 'admin123';

if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['password']) && $_POST['password']==$password) {
        $_SESSION['logged_in']=true;
        echo json_encode(['success'=>true]); exit;
    }
    echo json_encode(['success'=>false]); exit;
}

$base = getcwd(); // root path
$path = realpath($_POST['path'] ?? $base);
if(!$path || strpos($path,$base)!==0) $path=$base;

$act = $_POST['action'] ?? '';

if($act=='ls'){
    $out=['current'=>$path,'parent'=>dirname($path),'items'=>[]];
    $items=scandir($path);
    foreach($items as $f){
        if($f=='.'||$f=='..') continue;
        $fp=$path.'/'.$f;
        $out['items'][]=[
            'name'=>$f,
            'is_dir'=>is_dir($fp),
            'mtime'=>filemtime($fp),
        ];
    }
    echo json_encode($out); exit;
}
elseif($act=='delete'){
    $t=$path.'/'.basename($_POST['target']);
    if(is_dir($t)) @rmdir($t); else @unlink($t);
    echo json_encode(['success'=>true]); exit;
}
elseif($act=='rename'){
    rename($path.'/'.basename($_POST['from']),$path.'/'.basename($_POST['to']));
    echo json_encode(['success'=>true]); exit;
}
elseif($act=='edit'){
    file_put_contents($path.'/'.basename($_POST['file']),$_POST['content']);
    echo json_encode(['success'=>true]); exit;
}
elseif($act=='get'){
    $c=file_get_contents($path.'/'.basename($_POST['file']));
    echo json_encode(['content'=>$c]); exit;
}
elseif($act=='new_folder'){
    mkdir($path.'/'.basename($_POST['name']));
    echo json_encode(['success'=>true]); exit;
}
elseif($act=='new_file'){
    file_put_contents($path.'/'.basename($_POST['name']),'');
    echo json_encode(['success'=>true]); exit;
}
elseif($act=='mtime'){
    touch($path.'/'.basename($_POST['file']),strtotime($_POST['mtime']));
    echo json_encode(['success'=>true]); exit;
}
elseif($act=='upload'){
    foreach($_FILES['files']['tmp_name'] as $i=>$tmp)
        move_uploaded_file($tmp,$path.'/'.basename($_FILES['files']['name'][$i]));
    echo json_encode(['success'=>true]); exit;
}