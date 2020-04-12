<?php
//環境設定

//ベースURL（windowsの場合はこれでOK）
//$hostname = gethostbyaddr('127.0.0.1');     //DESKTOP-5F633S7
//$ipaddr = gethostbyname($hostname);         //192.168.10.4
$baseurl = 'http://'.gethostbyname(gethostbyaddr('127.0.0.1')).'/pono/';

//データベース
$db['host'] = "localhost";  // DBサーバのURL
$db['user'] = "ponophp";    // ユーザー名
$db['pass'] = "ia256";      // ユーザー名のパスワード
$db['dbname'] = "pono";     // データベース名

//コマンドラインPHP(パスが通っていれば php だけでもOK)
$phpcommand = 'c:\\php7.3.7\\php.exe';                                              //WINDOWS依存

//投稿された課題画像
$directories['problem_image'] = "C:\\inetpub\\wwwroot\\pono\\uploaded_problems\\";  //WINDOWS依存
$urlpaths['problem_image'] = "./uploaded_problems/";  

//投稿された壁画像
$directories['wall_image'] = "C:\\inetpub\\wwwroot\\pono\\wall_pictures\\";         //WINDOWS依存
$urlpaths['wall_image'] = "./wall_pictures/";  

//投稿された課題または壁画像の一次保存
$directories['tmp'] = "C:\\inetpub\\wwwroot\\pono\\tmp\\";                          //WINDOWS依存
$urlpaths['tmp'] = "./tmp/";  

//課題のヘッドラインイメージ保存サイズ
$headimagesize['x'] = 800;
$headimagesize['y'] = 1000;

//課題のサムネイルイメージ保存サイズ
$thumbimagesize['x'] = 240;
$thumbimagesize['y'] = 300;

//色　使ってないかも？
$textcolor_success = 'darkgreen';
$textcolor_warn = 'orange';
$textcolor_error = 'red';

//存在する壁
//$wall[プログラムで扱う値] = 表示名;  ※'プログラムで扱う値'に ',' は使用しないこと！ 
$walls['A'] = "Ａ壁";
$walls['B'] = "Ｂ壁";
$walls['C'] = "Ｃ壁";
$walls['D'] = "Ｄ壁";
$walls['E'] = "Ｅ壁";
$walls['F'] = "Ｆ壁";
$walls['G'] = "Ｇ壁";
$walls['H'] = "Ｈ壁";

//グレード
$grades['-1'] = "２段";
$grades['0'] = "初段";
$grades['1'] = "１級";
$grades['2'] = "２級";
$grades['3'] = "３級";
$grades['4'] = "４級";
$grades['5'] = "５級";
$grades['6'] = "６級";
