<?php
/**
 * 環境設定と共通関数
 * DBへの接続もここで行います
 */


//ベースURL
$baseurl = (DIRECTORY_SEPARATOR == '\\')
     ? ('http://' . gethostbyname(gethostbyaddr('127.0.0.1')) . '/pono/')
     : ('http://' . gethostbyname('raspberrypi.local') . '/pono/');

//データベース
$db['host'] = "localhost";  // DBサーバのURL
$db['user'] = "ponophp";    // ユーザー名
$db['pass'] = "ia256";      // ユーザー名のパスワード
$db['dbname'] = "pono";     // データベース名

//コマンドラインPHP(パスが通っていれば php だけでもOK) ※現在使用無し
$phpcommand = (DIRECTORY_SEPARATOR == '\\')
    ? 'c:\\php7.3.7\\php.exe'
    : 'php';

//コマンドラインffmpeg(windows時はフルパス必須)
$ffmpegcommand = (DIRECTORY_SEPARATOR == '\\')
    ? '"C:\Program Files\ffmpeg\bin\ffmpeg.exe"'
    : 'ffmpeg';

//投稿された課題画像
$directories['problem_image'] = (DIRECTORY_SEPARATOR == '\\')
    ? "C:\\inetpub\\wwwroot\\pono\\uploaded_problems\\"
    : "/var/www/html/pono/uploaded_problems/";
$urlpaths['problem_image'] = "./uploaded_problems/";  

//投稿された壁画像
$directories['wall_image'] = (DIRECTORY_SEPARATOR == '\\')
    ? "C:\\inetpub\\wwwroot\\pono\\wall_pictures\\"
    : "/var/www/html/pono/wall_pictures/";
$urlpaths['wall_image'] = "./wall_pictures/";  

//投稿された課題または壁画像の一次保存
$directories['tmp'] = (DIRECTORY_SEPARATOR == '\\')
    ? "C:\\inetpub\\wwwroot\\pono\\tmp\\"
    : "/var/www/html/pono/tmp/";
$urlpaths['tmp'] = "./tmp/";  

//投稿された完登動画
$directories['comp_movie'] = (DIRECTORY_SEPARATOR == '\\')
    ? "C:\\inetpub\\wwwroot\\pono\\movies\\"
    : "/var/www/html/pono/movies/";
$urlpaths['comp_movie'] = "./movies/";  

//課題のヘッドラインイメージ保存サイズ
$headimagesize['x'] = 800;
$headimagesize['y'] = 1000;

//課題のサムネイルイメージ保存サイズ
$thumbimagesize['x'] = 240;
$thumbimagesize['y'] = 300;

//グレード
$grades['-1'] = "２段";
$grades['0'] = "初段";
$grades['1'] = "１級";
$grades['2'] = "２級";
$grades['3'] = "３級";
$grades['4'] = "４級";
$grades['5'] = "５級";
$grades['6'] = "６級";


//壁を読み込む
//$wall[プログラムで扱う値] = 表示名;  ※'プログラムで扱う値'に ',' は使用しないこと！ 
$walls = [];            //有効な壁
$disabledWalls = [];    //無効な壁
$dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    $sql = "SELECT `active`,`tag`,`name` FROM `walls` ORDER BY `disporder`";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['active'] == 1) {
            $walls[$row['tag']] = $row['name'];
        } else {
            $disabledWalls[$row['tag']] = $row['name'];
        }
    }
} catch (PDOException $e) {
    echo 'データベースエラー<br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    exit();
}

/**
 * date2str
 * 日付を書式化
 *
 * @param  mixed $dt
 * @return void
 */
function date2str($dt) {
    if (date('Ymd') == date('Ymd', $dt)) {
       return '今日 '.date('H:i', $dt);
    } else if (date('Ymd', strtotime('-1 day')) == date('Ymd', $dt)) {
        return '昨日 '.date('H:i', $dt);
    } else if (date('Ym') == date('Ym', $dt)) {
        return '今月'.date('d日 H:i', $dt);
    } else {
        return date('Y/m/d', $dt);
    }
}

/**
 * resizefullwidth
 * 指定サイズに縮小。スケールは幅に合わせる。縦方向の余白は黒で塗りつぶされるか、上下に均等にはみ出る
 *
 * @param  mixed $src_img
 * @param  mixed $src_width
 * @param  mixed $src_height
 * @param  mixed $new_width
 * @param  mixed $new_height
 * @return void
 */
function resizefullwidth($src_img, $src_width, $src_height, $new_width, $new_height) {
    $image = imagecreatetruecolor($new_width, $new_height);

    //横幅いっぱいを使う。高さで調整
    $scale = $new_width / $src_width;
    $h = $src_height * $scale;
    if ($h >= $new_height) {
        //高さがはみ出る
        $sy = (($h - $new_height) / 2) / $scale;
        $sh = $src_height - ($h - $new_height) / $scale;
        $dy = 0;
        $dh = $new_height;
        //echo "${h} ${new_height}   sy=${sy} sh=${sh} dy=${dy} dh=${dh}";
    } else {
        //上下に余白ができる
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 0)); //黒で塗りつぶす
        $sy = 0;
        $sh = $src_height;
        $dy = ($new_height - $h) / 2;
        $dh = $src_height * $scale;
        //echo "${h} ${new_height}   sy=${sy} sh=${sh} dy=${dy} dh=${dh}";
    }

    // 画像のコピーと伸縮
    imagecopyresampled($image, $src_img, 0, $dy, 0, $sy, $new_width, $dh, $src_width, $sh);
    return $image;
}


/**
 * createUrl
 * URLを作成する。パラメータはarで渡す。値はurlencodeとbase64encodeされます。
 *
 * @param  mixed $baseurl
 * @param  mixed $ar
 * @return string
 */
function createUrl($baseurl, $ar) {
    foreach ($ar as $key => $value) {
        if (!isset($url)) {
            $url = $baseurl . '?';
        } else {
            $url .= '&';
        }
        $baseurl .= $key . '=' . urlencode(base64_encode($value));
    }
    return $baseurl;
}

