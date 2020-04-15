<?php
/*
    DisplayProblem.phpから動画のサムネイルを作成するために呼び出される

    ffmpegをインストールする必要があります
    windowsではphp.iniで extension=php_com_dotnet.dll を追加必要
*/

if (!isset($argv[2])) exit;

require_once 'Env.php';

//引数　動画パス　サムネイルパス
$moviePath = $argv[1];
$thumbPath = $argv[2];
try {
    if (DIRECTORY_SEPARATOR == '\\') {
        //Windows時
        $cmd = $ffmpegcommand . ' -i ' . escapeshellarg($moviePath) . ' -ss 0 -t 1 -r 1 ' . escapeshellarg($thumbPath);
        echo $cmd, "\r\n";
        $shell = new COM("WScript.Shell"); //windowsではphp.iniで extension=php_com_dotnet.dll を追加必要
        $shell->Exec($cmd);
        unset($shell);
    } else {
        //Linux
        $cmd = $ffmpegcommand . " -i ${moviePath} -ss 0 -t 1 -r 1 ${thumbPath}";
        echo $cmd, "\r\n";
        exec($cmd);
    }
} catch (PDOException $e) {
    echo $e->getMessage();
    exit();
}

//thumbPathにすぐにアクセスできないので、適当に待つ
sleep(1);

//サムネイルを作成する
$img_src = ImageCreateFromJPEG($thumbPath);
$sz = getimagesize($thumbPath);

$img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $thumbimagesize['x'], $thumbimagesize['y']);
imagejpeg($img_dst, $thumbPath, 80);

imagedestroy($img_src);
imagedestroy($img_dst);
