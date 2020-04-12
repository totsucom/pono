<?php
session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {
    header("Location: Login.php");
    exit;
}

require_once 'Env.php';

/*
セッション変数（入力）
$_SESSION["EDIT_WALL_TYPE"]     EDIT_WALL_VALUE の内容を示す。'WALL_ID' または 'TMP_PATH'。編集時は 'PROBLEM_ID'
$_SESSION["EDIT_WALL_VALUE"]    WALL_IDの場合 DB上のwallpicture.id、PROBLEM_IDの場合 DB上のproblem.id
*/

//指定サイズに縮小。スケールは幅に合わせる。縦方向の余白は黒で塗りつぶされるか、上下に均等にはみ出る
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

if (!$_SESSION['MASTER']) {
    $errorMessage = 'このページを開く権限がありません';
}

if (!isset($errorMessage) && isset($_GET['wid']) && is_numeric($_GET['wid'])) {
    //編集モード
    $_SESSION["EDIT_WALL_TYPE"] = 'WALL_ID';
    $_SESSION["EDIT_WALL_VALUE"] = $_GET['wid'];
}

if (!isset($errorMessage) && !isset($_SESSION["EDIT_WALL_TYPE"], $_SESSION["EDIT_WALL_VALUE"])) {
    $errorMessage = '表示できるものはありません.';
}

if (!isset($errorMessage)) {
    if ($_SESSION["EDIT_WALL_TYPE"] == 'WALL_ID') {
        //壁IDが指定されたパターン

        $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
        try {
            $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    
            $stmt = $pdo->prepare('SELECT `name`,`location`,`imagefile` FROM `wallpicture` WHERE `id` = ?');
            $stmt->execute(array($_SESSION["EDIT_WALL_VALUE"]));
    
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $path = $directories['wall_image'] . $row['imagefile'];
                if (file_exists($path)) {
                    $src_wall = $urlpaths['wall_image'] . $row['imagefile'];        //壁写真のURL
                } else {
                    $errorMessage = '指定された壁写真が見つかりませんでした';
                }
            } else {
                $errorMessage = '指定された壁写真が見つかりませんでした';
            }
        } catch (PDOException $e) {
            $errorMessage = 'データベースエラー';
            echo $e->getMessage();  //デバッグ
        }

    } else if ($_SESSION["EDIT_WALL_TYPE"] == 'TMP_PATH') {
        //新規作成で壁のパスが指定されたパターン

        if (!file_exists($_SESSION["EDIT_WALL_VALUE"])) {
    
            //ファイルが無いのでセッション変数をクリアする
            unset($_SESSION["EDIT_WALL_TYPE"], $_SESSION["EDIT_WALL_VALUE"]);
    
            $errorMessage = '編集中のデータはありません';
        } else {

            $path = $_SESSION["EDIT_WALL_VALUE"];
            if (file_exists($path)) {
                $src_wall = $urlpaths['tmp'] . basename($_SESSION["EDIT_WALL_VALUE"]);  //壁写真のURL
            } else {
                $errorMessage = '指定された壁写真が見つかりませんでした';
            }
        }
    } else {
        //セッション変数が変なのでクリアする
        unset($_SESSION["EDIT_WALL_TYPE"], $_SESSION["EDIT_WALL_VALUE"]);

        $errorMessage = '表示できるものはありません';
    }

    //新規投稿の場合はさらに続く
    if (!isset($errorMessage) && isset($_POST['submit'], $_POST['wall_location'], $_POST['wall_name'])) {

        if ($_SESSION["EDIT_WALL_TYPE"] == 'WALL_ID') {
            //壁IDが指定されたパターン
    
            $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
            try {
                if (!isset($pdo)) {
                    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
                }    
                $sql = 'UPDATE `wallpicture` SET `name` = ?, `location` = ? WHERE `id` = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array(
                    $_POST['wall_name'],
                    ',' . implode(',', $_POST['wall_location']) . ',',
                    $_SESSION["EDIT_WALL_VALUE"]));
            } catch (PDOException $e) {
                $errorMessage = 'データベースの接続に失敗しました。'.$e->getMessage();
            }
        } else if ($_SESSION["EDIT_WALL_TYPE"] == 'TMP_PATH') {
            //新規作成で壁のパスが指定されたパターン
echo 'Hi.';
            $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
            try {
                if (!isset($pdo)) {
                    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
                }

                $sql = 'INSERT INTO `wallpicture` (`name`,`location`,`imagefile`,`imagefile_h`,`imagefile_t`) VALUES (?,?,?,?,?)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array(
                    $_POST['wall_name'],
                    ',' . implode(',', $_POST['wall_location']) . ',',
                    '',
                    '',
                    ''));
                $wallid = $pdo->lastinsertid();  //登録した(DB側でauto_incrementした)IDを$wallidに入れる
echo ' wallid=',$wallid,' ';
            } catch (PDOException $e) {
                $errorMessage = 'データベースの接続に失敗しました。'.$e->getMessage();
            }

            //サムネイル画像を作成
            if (!isset($errorMessage)) {
                $imagefile = uniqid($wallid . '_') . '.jpg';         //画像ファイル名。後でDBに書き込む
                $srcPath = $directories['wall_image'] . $imagefile;  //後でサムネイル作成に使用する
                copy($_SESSION["EDIT_WALL_VALUE"], $srcPath);
                unlink($_SESSION["EDIT_WALL_VALUE"]);

                $img_src = ImageCreateFromJPEG($srcPath);
                $sz = getimagesize($srcPath);
    
                $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $headimagesize['x'], $headimagesize['y']);
                $imagefile_h = uniqid($wallid . '_h_') . '.jpg';     //ヘッドラインファイル名。後でDBに書き込む
                $path = $directories['wall_image'] . $imagefile_h;
                imagejpeg($img_dst, $path, 80);
    
                $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $thumbimagesize['x'], $thumbimagesize['y']);
                $imagefile_t = uniqid($wallid . '_t_') . '.jpg';     //サムネイルファイル名。後でDBに書き込む
                $path = $directories['wall_image'] . $imagefile_t;
                imagejpeg($img_dst, $path, 80);
    
                imagedestroy($img_src);
                imagedestroy($img_dst);
            }

            if (!isset($errorMessage)) {
                try {
                    //保存したファイル名をデータベースに保存
                    $sql =<<< 'EOD'
UPDATE `wallpicture` SET
    `imagefile`=?, `imagefile_h`=?, `imagefile_t`=?
WHERE
    `id`=?
EOD;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array(
                        $imagefile,
                        $imagefile_h,
                        $imagefile_t,
                        $wallid));
                } catch (PDOException $e) {
                    $errorMessage = 'ファイル名の書き込みに失敗しました。'.$e->getMessage();
                }
            }
        } else {
            $errorMessage = '保存できませんでした';
        }

        //セッション変数をクリア
        unset($_SESSION["EDIT_WALL_TYPE"], $_SESSION["EDIT_WALL_VALUE"]);

        if (isset($errorMessage)) {
            echo htmlspecialchars($errorMessage, ENT_QUOTES);
            exit;
        }

echo 'end';
        //header("Location: ./WallList.php?msg=" . urlencode('壁を投稿しました'));
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <title>壁写真のアップロード</title>
</head>
<body>
    <nav class="navbar navbar-expand-sm navbar-light bg-light">
        <a href="./Main.php" class="navbar-brand">
            <img src="./img/real-estate.png" width="18" height="18" alt="">
            Home
        </a>
        <button class="navbar-toggler" type="button"
            data-toggle="collapse"
            data-target="#navmenu1"
            aria-controls="navmenu1"
            aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navmenu1">
            <div class="navbar-nav">
                <a class="nav-item nav-link" href="./Account.php">アカウント設定</a>
                <a class="nav-item nav-link" href="./Logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class = "container">
        <?php
            if (isset($errorMessage)) {
                //エラー
                $msg = htmlspecialchars($errorMessage, ENT_QUOTES);
                echo <<<EOD
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>エラー！</strong> {$msg}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>                
                </div>
            </body>
        </html>

EOD;
                exit();
            }
        ?>
        <form action="#" method="post" id="form1" enctype="multipart/form-data">

            <?php
                if (isset($_SESSION["EDIT_WALL_TYPE"]) && $_SESSION["EDIT_WALL_TYPE"] == 'WALL_ID') {
                    echo "<h3>壁${_SESSION["EDIT_WALL_VALUE"]}の編集</h3>";
                } else {
                    echo "<h3>壁のアップロード</h3>";
                }
            ?>

            <!-- 場所 -->
            <div class="row bg-light mb-2">
                <div class="col-4  px-3 py-2">場所</div>
                <div class="col-8 pl-1 pr-3 py-1 border-left">
                    <div class="form-group">
                        <div class="form-inline" id="locations">
                            <?php
                                $first = 'id="first_wall"';
                                if (!isset($row['location'])) $first .= ' required';
                                foreach ($walls as $value => $name) {
                                    $checked = (isset($row['location']) && (','.$value.',') == $row['location']) ? 'checked' : '';
                                    echo <<<EOD
                                        <label class="checkbox-inline pl-2 py-1">
                                            <input type="checkbox" name="wall_location[]" value="{$value}" {$first} {$checked} data-name="{$name}">{$name}
                                        </label>

EOD;
                                    $first = '';
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 名前 -->
            <div class="row bg-light mb-2">
                <div class="col-4  px-3 py-2">名称</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-group">
                        <div class="input-group">
                            <input type="text" class="form-control" name="wall_name" id="wall_name" minlength="1" maxlength="20" required value="<?php if (isset($row['name'])) echo htmlspecialchars($row['name'], ENT_QUOTES); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 投稿ボタン -->
            <div class="col-12 clearfix">
                <div class="float-right">
                    <div class="spinner-border >spinner-border-sm align-middle" role="status" id="uploading_spin" style="display:none">
                        <span class="sr-only">Uploading...</span>
                    </div>
                    <button class="btn btn-primary align-middle" type="submit" name="submit">投稿する</button>
                </div>
            </div>
            <input type="hidden" name="imgb64" id="imgb64"> <!-- javascriptで合成した完成イメージを格納 -->
        </form>

        <!-- 課題画像の表示 -->
        <img id="preview" class="img-fluid mt-3 mb-5" src="<?php echo $src_wall, '?', rand(); ?>">
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script>$(function(){
        var autoNaming = true;

        var alertUnload = true;
        window.addEventListener("beforeunload", function(e){
            if (alertUnload) e.returnValue = "移動していい？";//このテキストは実際に表示されない
        }, false);

        //壁のチェックボックスが変更された
        $('input[name="wall_location[]"]').on('change', function () {
            var locs = $('input[name="wall_location[]"]:checked');
            if (locs.length == 0) {
                $('#first_wall').prop('required', true);//最低１つは選択しないといけない
            } else {
                $('#first_wall').removeAttr('required');
            }
            if (autoNaming) {
                var s='';
                locs.each(function(i){
                    s += $(this).data('name');
                });
                $('#wall_name').val(s);//自動命名
            }
        });

        //名前が変更された
        $('#wall_name').on('change', function () {
            autoNaming = false;//手動で変更した場合は自動命名をOFF
        });

        //投稿前の処理
        $('#form1').submit(function(){

            //スピンを回す
            $('#uploading_spin').show();

            //アラートを表示しないようにする
            alertUnload = false;

            return true; //falseでキャンセル
        });

    });</script>
</body>
</html>
