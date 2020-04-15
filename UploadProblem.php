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
$_SESSION["EDIT_BASE_TYPE"]     EDIT_BASE_VALUE の内容を示す。'WALL_ID' または 'TMP_PATH'。編集時は 'PROBLEM_ID'
$_SESSION["EDIT_BASE_VALUE"]    WALL_IDの場合 DB上のwallpicture.id、PROBLEM_IDの場合 DB上のproblem.id
$_SESSION["EDIT_BASE_TRIM"]     JSON形式のトリミング情報。編集時なし
$_SESSION["EDIT_PRIMITIVES"]    JSON形式のホールドなどを配置したデータファイルのフルパス。場所はtmpフォルダ。編集時なし
*/


if (isset($_GET['pid']) && is_numeric($_GET['pid'])) {
    //編集モード
    $_SESSION["EDIT_BASE_TYPE"] = 'PROBLEM_ID';
    $_SESSION["EDIT_BASE_VALUE"] = $_GET['pid'];
    unset($_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);
}
/*
echo '<br>SESSION:';
print_r($_SESSION);
echo '<br>_GET:';
if (isset($_GET)) print_r($_GET);
echo '<br>_POST:';
if (isset($_POST)) print_r($_POST);
echo '<br>';
*/
if (!isset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"])) {
    $errorMessage = '表示できるものはありません.';
}

if (!isset($errorMessage) && !isset($_POST['submit']) && $_SESSION["EDIT_BASE_TYPE"] == 'PROBLEM_ID') {
    //編集で課題IDが指定されたパターン
    try {
        $stmt = $pdo->prepare('SELECT * FROM `problem` WHERE `id` = ?');
        $stmt->execute(array($_SESSION["EDIT_BASE_VALUE"]));

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['userid'] == $_SESSION["ID"]) {
                $path = $directories['problem_image'] . $row['imagefile_b'];
                if (file_exists($path)) {
                    $src_wall = $urlpaths['problem_image'] . $row['imagefile_b'];      //壁写真(ベース)のURL
                    $primitives = $row['primitives'];                               //JSON文字列
                    $triminfo = $row['trim_b'];                                     //JSON文字列
                } else {
                    $errorMessage = '指定された壁写真が見つかりませんでした';
                }
            } else {
                $errorMessage = '指定された課題を編集する権限がありません';
            }
        } else {
            $errorMessage = '指定された壁写真が見つかりませんでした';
        }
    } catch (PDOException $e) {
        $errorMessage = 'データベースエラー';
        echo $e->getMessage();  //デバッグ
    }
}
else if (!isset($errorMessage) && $_SESSION["EDIT_BASE_TYPE"] == 'PROBLEM_ID' &&
    isset($_POST['submit'], $_POST['imgb64'], $_POST['problem_active'], $_POST['problem_grade'],
    $_POST['problem_location'], $_POST['publish'], $_POST['problem_title'], $_POST['problem_comment'])) {
    //自身にポストした場合
    //編集モード
    try {
        //投稿されたパラメータをデータベースに保存
        $sql =<<< 'EOD'
UPDATE `problem` SET
    `active`=?, `title`=?, `grade`=?, `footfree`=?, `location`=?, `publish`=?, `comment`=?
WHERE `id`=?
EOD;
        $stmt = $pdo->prepare($sql);
        $problemid = intval($_SESSION["EDIT_BASE_VALUE"]);
        $stmt->execute(array(
            $_POST['problem_active'],
            $_POST['problem_title'],
            $_POST['problem_grade'],
            isset($_POST['foot_free']) ? 1 : 0,
            ',' . implode(',', $_POST['problem_location']) . ',',
            $_POST['publish'],
            $_POST['problem_comment'],
            $problemid));

    } catch (PDOException $e) {
        $errorMessage = 'データベースの接続に失敗しました。'.$e->getMessage();
    }

    if (!isset($errorMessage)) {        
        //セッション変数をクリア
        unset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"], $_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);

        //課題を表示するためリダイレクトする
        header("Location: ./DisplayProblem.php?pid=${problemid}&msg=" . urlencode('課題を更新しました'));
        exit;
    }        
}
else if (!isset($errorMessage)) {
    if ($_SESSION["EDIT_BASE_TYPE"] == 'WALL_ID' && isset($_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"])) {
        //新規作成で壁IDが指定されたパターン

        if (!file_exists($_SESSION["EDIT_PRIMITIVES"])) {
    
            //ファイルが無いのでセッション変数をクリアする
            unset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"], $_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);
    
            $errorMessage = '編集中のデータはありません';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT * FROM `wallpicture` WHERE `id` = ?');
                $stmt->execute(array($_SESSION["EDIT_BASE_VALUE"]));
        
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
        }
    } else if ($_SESSION["EDIT_BASE_TYPE"] == 'TMP_PATH' && isset($_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"])) {
        //新規作成で壁のパスが指定されたパターン

        if (!file_exists($_SESSION["EDIT_BASE_VALUE"]) || !file_exists($_SESSION["EDIT_PRIMITIVES"])) {
    
            //ファイルが無いのでセッション変数をクリアする
            unset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"], $_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);
    
            $errorMessage = '編集中のデータはありません';
        } else {

            $path = $_SESSION["EDIT_BASE_VALUE"];
            if (file_exists($path)) {
                $src_wall = $urlpaths['tmp'] . basename($_SESSION["EDIT_BASE_VALUE"]);  //壁写真のURL
            } else {
                $errorMessage = '指定された壁写真が見つかりませんでした';
            }
        }
    } else {
        //セッション変数が変なのでクリアする
        unset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"], $_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);

        $errorMessage = '表示できるものはありません';
    }

    //描画データをJSONファイルから読み込む
    $primitives = file_get_contents($_SESSION["EDIT_PRIMITIVES"]);                  //JSON文字列
    if ($primitives === FALSE) {
        $errorMessage = 'データの読み込みに失敗しました';
    }

    //トリミングデータも変数に設定
    $triminfo = $_SESSION["EDIT_BASE_TRIM"];

    //新規投稿の場合はさらに続く
    if (!isset($errorMessage) && isset($_POST['submit'], $_POST['imgb64'], $_POST['problem_active'], $_POST['problem_grade'],
        $_POST['problem_location'], $_POST['publish'], $_POST['problem_title'], $_POST['problem_comment'])) {
        try {
            //投稿されたパラメータをデータベースに保存
            $sql =<<< 'EOD'
INSERT INTO `problem`
    (`active`, `userid`, `title`, `grade`, `footfree`, `location`, `publish`, `comment`,
        `primitives`, `trim_b`, `imagefile`, `imagefile_b`, `imagefile_h`, `imagefile_t`)
VALUES
    (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
EOD;
            $stmt = $pdo->prepare($sql);
            $trim = array();
            $trim['l'] = isset($_GET['tl']) ? $_GET['tl'] : 0;
            $trim['t'] = isset($_GET['tt']) ? $_GET['tt'] : 0;
            $trim['r'] = isset($_GET['tr']) ? $_GET['tr'] : 0;
            $trim['b'] = isset($_GET['tb']) ? $_GET['tb'] : 0;
            $stmt->execute(array(
                $_POST['problem_active'],
                $_SESSION["ID"],
                $_POST['problem_title'],
                $_POST['problem_grade'],
                isset($_POST['foot_free']) ? 1 : 0,
                ',' . implode(',', $_POST['problem_location']) . ',',
                $_POST['publish'],
                $_POST['problem_comment'],
                $primitives,
                $triminfo,
                '',
                '',
                '',
                ''));
            $problemid = $pdo->lastinsertid();  //登録した(DB側でauto_incrementした)IDを$problemidに入れる
        } catch (PDOException $e) {
            $errorMessage = 'データベースの接続に失敗しました。'.$e->getMessage();
        }

        //課題画像を保存
        if (!isset($errorMessage) && substr($_POST['imgb64'], 0, 23) != 'data:image/jpeg;base64,') {
            $errorMessage = 'MimeTypeが想定外です';
        }

        if (!isset($errorMessage)) {
            //javascriptから渡されたwebpフォーマットからjpegに変換
            $img = str_replace('data:image/jpeg;base64,', '', $_POST['imgb64']); //この画像データはトリミング済み
            $_POST['imgb64'] = '';//メモリ節約
            $img = str_replace(' ', '+', $img);
            $imagefile = uniqid($problemid . '_') . '.jpg';         //画像ファイル名。後でDBに書き込む
            $srcPath = $directories['problem_image'] . $imagefile;  //後でサムネイル作成に使用する
            if (file_put_contents($srcPath, base64_decode($img)) === FALSE) {
                $errorMessage = '保存できませんでした。' . $srcPath;
            }
        }
    
        //〇の付いてないベース画像を保存。これはトリミングされてない
        if (!isset($errorMessage)) {
            $imagefile_b = uniqid($problemid . '_b_') . '.jpg';     //ベースファイル名。後でDBに書き込む
            $path = $directories['problem_image'] . $imagefile_b;
            if ($_SESSION["EDIT_BASE_TYPE"] == 'WALL_ID') { 
                copy($src_wall, $path);      //画像はwall_picturesからなのでコピーを作成
            } else {    //'TMP_PATH'
                rename($src_wall, $path);    //画像はユーザーアップロードによるものなのでそのまま移動
            }
        }

        //サムネイル画像を作成
        if (!isset($errorMessage)) {

            $img_src = ImageCreateFromJPEG($srcPath);
            $sz = getimagesize($srcPath);

            $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $headimagesize['x'], $headimagesize['y']);
            $imagefile_h = uniqid($problemid . '_h_') . '.jpg';     //ヘッドラインファイル名。後でDBに書き込む
            $path = $directories['problem_image'] . $imagefile_h;
            imagejpeg($img_dst, $path, 80);

            $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $thumbimagesize['x'], $thumbimagesize['y']);
            $imagefile_t = uniqid($problemid . '_t_') . '.jpg';     //サムネイルファイル名。後でDBに書き込む
            $path = $directories['problem_image'] . $imagefile_t;
            imagejpeg($img_dst, $path, 80);

            imagedestroy($img_src);
            imagedestroy($img_dst);
        }
    
        if (!isset($errorMessage)) {
            try {
                //保存したファイル名をデータベースに保存
                $sql =<<< 'EOD'
UPDATE `problem` SET
    `imagefile`=?, `imagefile_b`=?, `imagefile_h`=?, `imagefile_t`=?
WHERE
    `id`=?
EOD;
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array(
                    $imagefile,
                    isset($imagefile_b) ? $imagefile_b : '',
                    $imagefile_h,
                    $imagefile_t,
                    $problemid));
            } catch (PDOException $e) {
                $errorMessage = 'ファイル名の書き込みに失敗しました。'.$e->getMessage();
            }
        }

        //一時ファイルを削除
        unlink($_SESSION["EDIT_PRIMITIVES"]);
        
        //セッション変数をクリア
        unset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"], $_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);


        //課題を表示するためリダイレクトする
        header("Location: ./DisplayProblem.php?pid=${problemid}&msg=" . urlencode('課題を投稿しました'));
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
    <title>課題の投稿</title>
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
                if (isset($_SESSION["EDIT_BASE_TYPE"]) && $_SESSION["EDIT_BASE_TYPE"] == 'PROBLEM_ID') {
                    echo "<h3>課題${_SESSION["EDIT_BASE_VALUE"]}の編集</h3>";
                } else {
                    echo "<h3>課題の投稿</h3>";
                }
            ?>

            <!-- 有効設定 -->
            <div class="row bg-light mb-2" <?php if (!isset($row['active'])) echo 'style="display:none"'; ?> >
                <div class="col-4 px-3 py-2">有効設定</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-inline">
                        <div class="form-check mr-3">
                            <input class="form-check-input" type="radio" name="problem_active" id="active_1" value="1" <?php if (!isset($row['active']) || (isset($row['active']) && $row['active'] == 1)) echo 'checked'; ?> >
                            <label class="form-check-label" for="active_1">有効</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="problem_active" id="active_0" value="0" <?php if (isset($row['active']) && $row['active'] == 0) echo 'checked'; ?> >
                            <label class="form-check-label" for="active_0">無効</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- グレード -->
            <div class="row bg-light mb-2">
                <div class="col-4  px-3 py-2">グレード</div>
                <div class="col-8 px-3 py-2 border-left">
                    <select class="custom-select" id="problem_grade" name="problem_grade" required>
                        <option value="" selected></option>
                        <?php
                            foreach ($grades as $value => $name) {
                                $selected = (isset($row['grade']) && $value == $row['grade']) ? 'selected' : '';
                                echo "<option value=\"${value}\" ${selected}>${name}</option>\r\n";
                            }
                        ?>
                    </select>
                </div>
            </div>

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
                                            <input type="checkbox" name="problem_location[]" value="{$value}" {$first} {$checked} >{$name}
                                        </label>

EOD;
                                    $first = '';
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- その他 -->
            <div class="row bg-light mb-2">
                <div class="col-4  px-3 py-2">その他</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="foot_free" id="foot_free" <?php if (isset($row['footfree']) && $row['footfree'] == 1) echo 'checked'; ?> >
                            <label class="form-check-label" for="foot_free">足自由</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 公開設定 -->
            <div class="row bg-light mb-2">
                <div class="col-4  px-3 py-2">公開設定</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-inline">
                        <div class="form-check mr-3">
                            <input class="form-check-input" type="radio" name="publish" id="publish_1" value="1" <?php if (!isset($row['publish']) || (isset($row['publish']) && $row['publish'] == 1)) echo 'checked'; ?> >
                            <label class="form-check-label" for="publish_1">公開</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="publish" id="publish_0" value="0" <?php if (isset($row['publish']) && $row['publish'] == 0) echo 'checked'; ?> >
                            <label class="form-check-label" for="publish_0">プライベート</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- タイトル -->
            <div class="row bg-light mb-2">
                <div class="col-4  px-3 py-2">タイトル</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-group">
                        <div class="input-group">
                            <input type="text" class="form-control" name="problem_title" maxlength="20" placeholder="あれば" value="<?php if (isset($row['title'])) echo htmlspecialchars($row['title'], ENT_QUOTES); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- コメント -->
            <div class="row bg-light mb-2">
                <div class="col-4  px-3 py-2">コメント</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-group">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="problem_comment" maxlength="100" placeholder="" value="<?php if (isset($row['comment'])) echo htmlspecialchars($row['comment'], ENT_QUOTES); ?>">
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
        <img id="preview" class="img-fluid mt-3 mb-5">
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/jquery.exif.js"></script>
    <script>$(function(){
        
        //初期設定
        var firstLoad = true;
        function init() {
            //画像にホールドを合成する
            var img = document.getElementById('preview');
            img.onload = function () {
                if (firstLoad) {

                    /*なんか知らんけどPHPが日本語に戻してくれてるw
                    primitives.forEach(function(p,i) {
                        if (p.t == 'txt') {
                            //EditHolds.php btoa() の日本語対策
                            p.s = decodeURIComponent(escape(p.s));
                        }
                    });*/

                    var img_b64 = drawPrimitives(img); //トリミングもおこなわれる
                    img.src = img_b64;
                    $('#imgb64').val(img_b64);
                    firstLoad = false;
                }
            }
            img.src = "<?php echo $src_wall; ?>";
        }
        init();

        // Base64データをBlobデータに変換
        /*function Base64toBlob(base64) {
            // カンマで分割して以下のようにデータを分ける
            // tmp[0] : データ形式（data:image/png;base64）
            // tmp[1] : base64データ（iVBORw0k～）
            var tmp = base64.split(',');
            // base64データの文字列をデコード
            var data = atob(tmp[1]);
            // tmp[0]の文字列（data:image/png;base64）からコンテンツタイプ（image/png）部分を取得
            var mime = tmp[0].split(':')[1].split(';')[0];
            //  1文字ごとにUTF-16コードを表す 0から65535 の整数を取得
            var buf = new Uint8Array(data.length);
            for (var i = 0; i < data.length; i++) {
                buf[i] = data.charCodeAt(i);
            }
            // blobデータを作成
            var blob = new Blob([buf], { type: mime });
            return blob;
        }*/

        var alertUnload = true;
        window.addEventListener("beforeunload", function(e){
            if (alertUnload) e.returnValue = "移動していい？";//このテキストは実際に表示されない
        }, false);

        //壁の選択が変更された
        $('#locations input').on('change', function(){
            if ($('#locations input:checkbox:checked').length == 0) {
                $('#first_wall').prop('required', true);//最低１つは選択しないといけない
            } else {
                $('#first_wall').removeAttr('required');
            }
        });

        //投稿前の処理
        $('#form1').submit(function(){

            //スピンを回す
            $('#uploading_spin').show();

            //アラートを表示しないようにする
            alertUnload = false;

            return true; //falseでキャンセル
        });


        //描画ルーチン (imgはjpegに限る)
        //img = document.getElementById('img');
        //img.src = drawPrimitives(img);
        //primitivesについてはEditHolds.phpを参照
        const d360 = 2 * Math.PI;
console.log('<?php echo $primitives; ?>');
console.log('<?php echo $triminfo; ?>');
        var primitives = JSON.parse('<?php echo $primitives; ?>');
        var trim = JSON.parse('<?php echo $triminfo; ?>');
        function drawPrimitives(img) {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');

            canvas.width = img.naturalWidth * (1.0 - trim.l - trim.r);
            canvas.height = img.naturalHeight * (1.0 - trim.t - trim.b);

            var ox = img.naturalWidth * trim.l;
            var oy = img.naturalHeight * trim.t;

            ctx.drawImage(img,
                ox,oy,  //sx,sy
                canvas.width, canvas.height, //sw,sh
                0, 0, //dx,dy
                canvas.width, canvas.height); //dw, dh
            ctx.setLineDash([]);
            ctx.lineWidth = 8;
            primitives.forEach(function(p,i) {
                switch (p.t) {
                    case 'cir':
                        ctx.strokeStyle = p.c;
                        ctx.beginPath();
                        ctx.arc(p.x - ox, p.y - oy, p.r, 0, d360, false);
                        ctx.stroke();
                        break;
                    case 'txt':
                        ctx.font = p.r + 'pt Arial';
                        ctx.fillStyle = p.c;
                        ctx.textAlign = "center";
                        ctx.textBaseline = "middle";
                        if (p.d == 'H') {
                            ctx.fillText(p.s, p.x - ox, p.y - oy);
                        } else {
                            fillVerticalText(ctx, p.s, p.x - ox, p.y - oy);
                        }
                }
            });
            return canvas.toDataURL("image/jpeg", 0.8);
        }

        //ctx.fillText の縦書きバージョン
        function fillVerticalText(context, text, x, y) {
            var h = context.measureText("あ").width;

            //縦方向に中央配置できるようにy値を修正
            y -= (text.length - 1) / 2 * h;

            Array.prototype.forEach.call(text, function(ch, j) {
                context.fillText(ch, x, y + h * j);
            });
        };

    });</script>
</body>
</html>
