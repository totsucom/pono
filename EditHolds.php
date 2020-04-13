<?php
session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {
    header("Location: Login.php");
    exit;
}

require_once 'Env.php';
require_once "php/Mobile_Detect.php";
$detect = new Mobile_Detect;

if (isset($_GET['pid']) && is_numeric($_GET['pid'])) {
    //編集モード
    $_SESSION["EDIT_BASE_TYPE"] = 'PROBLEM_ID';
    $_SESSION["EDIT_BASE_VALUE"] = $_GET['pid'];
    unset($_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);
}

/*
$_GET['mode'] = 't' でトリムモード開始

セッション変数（入力）
$_SESSION["EDIT_BASE_TYPE"]     EDIT_BASE_VALUE の内容を示す。'WALL_ID' または 'TMP_PATH'。編集時は 'PROBLEM_ID'
$_SESSION["EDIT_BASE_VALUE"]    WALL_IDの場合 DB上のwallpicture.id、PROBLEM_IDの場合 DB上のproblem.id
                                TMP_PATHの場合 jpgファイルのフルパス。場所はtmpフォルダ
セッション変数（出力）
$_SESSION["EDIT_BASE_TRIM"]     JSON形式のトリミング情報
$_SESSION["EDIT_PRIMITIVES"]    JSON形式のホールドなどを配置したデータファイルのフルパス。場所はtmpフォルダ
但しPROBLEM_IDの場合はDBに保存されるため、セッション変数は出力されずにクリアされる
*/

if (!isset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"])) {
    $errorMessage = '表示できるものはありません.';
}

//自身によるポスト
if (!isset($errorMessage) && isset($_POST['submit'], $_POST['primitives'], $_POST['trim'], $_POST['location'], $_POST['imgb64'])) {
    if ($_SESSION["EDIT_BASE_TYPE"] != 'PROBLEM_ID') {
        //primitivesを一時ファイルに保存
        $tmpPath = tempnam($directories['tmp'], 'prim_');
        $json = base64_decode($_POST['primitives']);
        if (file_put_contents($tmpPath, $json) === FALSE) {
            $errorMessage = 'primitive保存失敗';
        } else {
            $_SESSION["EDIT_BASE_TRIM"] = base64_decode($_POST['trim']);    //json形式のトリミング情報
            $_SESSION["EDIT_PRIMITIVES"] = $tmpPath;                        //primitivesを表すjsonファイルのパス
        }
    } else {
        //編集モード
        try {
            //投稿されたパラメータをデータベースに保存
            $sql = 'UPDATE `problem` SET `primitives`=?, `trim_b`=? WHERE `id`=?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                base64_decode($_POST['primitives']),
                base64_decode($_POST['trim']),
                intval($_SESSION["EDIT_BASE_VALUE"])));

            //画像ファイル名を読み出す
            $stmt = $pdo->prepare('SELECT `imagefile`,`imagefile_h`,`imagefile_t` FROM `problem` WHERE `id` = ?');
            $stmt->execute(array($_SESSION["EDIT_BASE_VALUE"]));
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            } else {
                $errorMessage = '指定された壁写真が見つかりませんでした';
            }
            
        } catch (PDOException $e) {
            $errorMessage = 'データベースエラー';
            echo $e->getMessage();  //デバッグ
        }

        if (!isset($errorMessage)) {
            //javascriptから渡されたwebpフォーマットからjpegに変換
            $img = str_replace('data:image/jpeg;base64,', '', $_POST['imgb64']); //この画像データはトリミング済み
            $_POST['imgb64'] = '';//メモリ節約
            $img = str_replace(' ', '+', $img);
            //$imagefile = uniqid($problemid . '_') . '.jpg';         //画像ファイル名。後でDBに書き込む
            //$srcPath = $directories['problem_image'] . $imagefile;  //後でサムネイル作成に使用する
            $srcPath = $directories['problem_image'] . $row['imagefile'];   //後でサムネイル作成に使用する

            $byte=file_put_contents($srcPath, base64_decode($img));

            if ($byte === FALSE){//file_put_contents($srcPath, base64_decode($img)) === FALSE) {
                $errorMessage = '保存できませんでした。' . $srcPath;
            }
//echo htmlspecialchars($srcPath, ENT_QUOTES)," ", $byte,"<br>";

        }

        //サムネイル画像を作成
        if (!isset($errorMessage)) {

            $img_src = ImageCreateFromJPEG($srcPath);
            $sz = getimagesize($srcPath);

            $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $headimagesize['x'], $headimagesize['y']);
            //$imagefile_h = uniqid($problemid . '_h_') . '.jpg';     //ヘッドラインファイル名。後でDBに書き込む
            //$path = $directories['problem_image'] . $imagefile_h;
            $path = $directories['problem_image'] . $row['imagefile_h'];
            imagejpeg($img_dst, $path, 80);

            $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $thumbimagesize['x'], $thumbimagesize['y']);
            //$imagefile_t = uniqid($problemid . '_t_') . '.jpg';     //サムネイルファイル名。後でDBに書き込む
            //$path = $directories['problem_image'] . $imagefile_t;
            $path = $directories['problem_image'] . $row['imagefile_t'];
            imagejpeg($img_dst, $path, 80);

            imagedestroy($img_src);
            imagedestroy($img_dst);
        }

        if (isset($errorMessage)) {
            echo htmlspecialchars($errorMessage, ENT_QUOTES);
            exit();
        }
        //exit;

        //セッション変数をクリア
        unset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"], $_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);
    }
    header('Location:' . $_POST['location']);
    exit();
}
//前工程から
else if (!isset($errorMessage)) {
    if ($_SESSION["EDIT_BASE_TYPE"] == 'WALL_ID') {
        //壁画像がIDで指定されている場合
        try {
            $stmt = $pdo->prepare('SELECT * FROM `wallpicture` WHERE `id` = ?');
            $stmt->execute(array($_SESSION["EDIT_BASE_VALUE"]));
    
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $path = $directories['wall_image'] . $row['imagefile'];
                if (file_exists($path)) {
                    $src_wall = $urlpaths['wall_image'] . $row['imagefile']; //壁写真のURL
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
    } else if ($_SESSION["EDIT_BASE_TYPE"] == 'TMP_PATH') {
        //壁画像がユーザーによってアップロードされている場合

        $path = $_SESSION["EDIT_BASE_VALUE"];
        if (file_exists($path)) {
            $src_wall = $urlpaths['tmp'] . basename($_SESSION["EDIT_BASE_VALUE"]); //壁写真のURL
        } else {
            $errorMessage = '指定された壁写真が見つかりませんでした';
        }
    } else if ($_SESSION["EDIT_BASE_TYPE"] == 'PROBLEM_ID') {
echo 'PROBLEM_ID=',$_SESSION["EDIT_BASE_VALUE"];
        //編集モードで課題IDで指定されている場合
        try {
            $stmt = $pdo->prepare('SELECT * FROM `problem` WHERE `id` = ?');
            $stmt->execute(array($_SESSION["EDIT_BASE_VALUE"]));
    
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['userid'] == $_SESSION["ID"]) {
                    if ($row['imagefile_b'] != "") {
                        $path = $directories['problem_image'] . $row['imagefile_b'];
                        if (file_exists($path)) {
                            $src_wall = $urlpaths['problem_image'] . $row['imagefile_b'];      //壁写真(ベース)のURL
                            $primitives = $row['primitives'];                               //JSON文字列
                            $triminfo = $row['trim_b'];                                     //JSON文字列
                        } else {
                            $errorMessage = '指定された壁写真が見つかりませんでした';
                        }
                    } else {
                        $errorMessage = '指定された壁写真は編集できません';
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
    } else {
        //セッション変数が変なのでクリアする
        unset($_SESSION["EDIT_BASE_TYPE"], $_SESSION["EDIT_BASE_VALUE"], $_SESSION["EDIT_BASE_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);

        $errorMessage = '表示できるものはありません';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <title></title>
</head>
<body>
    <div class = "container">
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
                </body>
            </html>

EOD;
                exit();
            }
        ?>

        <h3 class="text-center my-3" id="title">課題<?php if ($_SESSION["EDIT_BASE_TYPE"] == 'PROBLEM_ID') echo $_SESSION["EDIT_BASE_VALUE"]; ?>の編集</h3>
        <div class="row my-2" id="navi-bar">
            <button class="btn btn-secondary ml-1" id="prev-button"><?php echo ($_SESSION["EDIT_BASE_TYPE"] != 'PROBLEM_ID') ? '戻る' : 'キャンセル'; ?></button>
            <button class="btn btn-info mx-auto" id="mode-button">トリムモード</button>
            <button class="btn btn-primary mr-1 ml-auto" id="next-button"><?php echo ($_SESSION["EDIT_BASE_TYPE"] != 'PROBLEM_ID') ? '次へ' : '更新'; ?></button>
        </div>

        <div class="row mx-auto my-0 p-0" id="tools">

            <!-- ホールドを囲む〇 -->
            <button type="button" class="btn btn-primary m-1" id="circle-button"><strong>〇</strong></button>

            <!-- テキスト -->
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle m-1" type="button" id="text-button" data-value="" data-dir="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    文字
                </button>
                <div class="dropdown-menu text-items" aria-labelledby="text">
                    <a class="dropdown-item" data-value="Ｓ"        data-dir="H" href="#">Ｓ（スタート）</a>
                    <a class="dropdown-item" data-value="Ｌ"        data-dir="H" href="#">Ｌ（スタート左手）</a>
                    <a class="dropdown-item" data-value="Ｒ"        data-dir="H" href="#">Ｒ（スタート右手）</a>
                    <a class="dropdown-item" data-value="Ｇ"        data-dir="H" href="#">Ｇ（ゴール）</a>
                    <a class="dropdown-item" data-value="ボテ有り"   data-dir="H" href="#">ボテ有り</a>
                    <a class="dropdown-item" data-value="ボテ有り"   data-dir="V" href="#">ボテ有り（縦）</a>
                    <a class="dropdown-item" data-value="カンテ有り" data-dir="H" href="#">カンテ有り</a>
                    <a class="dropdown-item" data-value="カンテ有り" data-dir="V" href="#">カンテ有り（縦）</a>
                    <a class="dropdown-item" data-value=""          data-dir=""  href="#">キャンセル</a>
                </div>
            </div>

            <!-- 色 -->
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle m-1" type="button" id="color-button" data-value="red" data-disp="赤" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        赤
                    </button>
                    <div class="dropdown-menu item-colors" aria-labelledby="color">
                        <a class="dropdown-item" data-value="red"     data-disp="赤" href="#">赤（ホールド）</a>
                        <a class="dropdown-item" data-value="fuchsia" data-disp="紫" href="#">紫（ホールド）</a>
                        <a class="dropdown-item" data-value="yellow"  data-disp="黄" href="#">黄（スタート）</a>
                        <a class="dropdown-item" data-value="orange"  data-disp="橙" href="#">橙（スタート）</a>
                        <a class="dropdown-item" data-value="lime"    data-disp="緑" href="#">緑（ゴール）</a>
                        <a class="dropdown-item" data-value="blue"    data-disp="青" href="#">青（ゴール）</a>
                    </div>
                </div>

            <!-- サイズ -->
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle m-1" type="button" id="size-button" data-value="M" data-disp="中" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        中
                    </button>
                    <div class="dropdown-menu item-sizes" aria-labelledby="color">
                        <a class="dropdown-item" data-value="L" data-disp="大" href="#">大</a>
                        <a class="dropdown-item" data-value="M" data-disp="中" href="#">中</a>
                        <a class="dropdown-item" data-value="S" data-disp="小" href="#">小</a>
                    </div>
                </div>

            <!-- 削除 -->
                <button type="button" class="btn btn-danger m-1" id="delete-button" disabled>削除</button>

            <!-- ズーム -->
            <?php
                if (!$detect->isMobile()) {
                    //PCの場合はピンチできないのでズームボタンを表示
                    echo <<<EOD
                        <button type="button" class="btn btn-danger m-1" id="zoom-in">＋</button>
                        <button type="button" class="btn btn-danger m-1" id="zoom-out">－</button>

EOD;
                }
            ?>

        </div>

        <canvas class="img-fluid border m-0 p-0" id="canvas"></canvas>
        <canvas id="buffer" style="display:none"></canvas>

        <!-- POST -->
        <form action="#" method="post" id="form1" enctype="multipart/form-data" style="display:none">
            <input type="hidden" name="primitives" id="primitives" value="">
            <input type="hidden" name="trim" id="trim" value="">
            <input type="hidden" name="location" id="location" value="">
            <button type="submit" name="submit" id="submit"></button>
            <input type="hidden" name="imgb64" id="imgb64"> <!-- 編集モードでjavascriptで合成した完成イメージを格納 -->
        </form>
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script>
        $(function(){
            const d360 = 2 * Math.PI;

            //現在のモード
            var mode = <?php echo (isset($_GET['mode']) && $_GET['mode'] == 't') ? "'T'" : "'H'"; ?>; //H:ホールド配置 T:トリミング

            //背景となる壁画像を読み込むimageオブジェクト
            var baseImage;  //<img>
            var baseImageWidth, baseImageHeight; //画像の元のサイズ
            var centerPosition = { x: 0, y: 0 };

            var trim = <?php echo isset($triminfo) ? "JSON.parse('${triminfo}')" : '{ l:0.0, t:0.0, r:0.0, b:0.0 }'; ?>; //トリミング比率 0.0-1.0

            //メインのキャンバスとコンテキスト
            var canvas, ctx;
            var canvasWidth, canvasHeight;
            var viewScale = 1.0;
            var drawCenter = { x: 0, y: 0 };

            //選択アイテムの重ね合わせ処理で、背景画像を一時的に保存するキャンバス
            var buffer, bctx;
            var backedImage = {active: false, x: 0, y: 0, w:0, h:0};

            //追加された描画アイテムを保持
            var primitives = <?php echo isset($primitives) ? "JSON.parse('${primitives}')" : '[]'; ?>;
            var selectedIndex = -1;

            //デフォルト色(HTMLと連動)
            //var itemColor = 'red'; => $('#color-button').data('value') に置き換える

            //デフォルトサイズ(HTMLと連動)
            //var itemSize = 'M'; => $('#size-button').data('value') に置き換える
            var itemSizes = {
                'S': { circleSize: 30, fontSize: 20 },
                'M': { circleSize: 50, fontSize: 30 },
                'L': { circleSize: 70, fontSize: 50 }
            };

            const zoomMax = 3.0;
            const zoomMin = 0.2;
            
            function prepareCanvas() {
                $('#canvas').hide();    //消さないとwindow.heightがページの高さを指してしまう
                $('#tools').show();     //高さ計測のため表示
console.log("window " + $(window).width() + ' x ' + $(window).height());
                canvasWidth = Math.round($(window).width() * 0.96);
                canvasHeight = Math.round(($(window).height() - $('#tools').height()) * 0.96);
                drawCenter.x = canvasWidth / 2;
                drawCenter.y = canvasHeight / 2;
                canvas = document.getElementById('canvas');
                $('#canvas').attr('width', canvasWidth).attr('height', canvasHeight);
                ctx = canvas.getContext('2d');
                $('#canvas').show();
                if (mode == 'T') $('#tools').hide();

console.log("canvas " + canvasWidth + ' x ' + canvasHeight);

                //バッファ用のキャンバスを初期化
                buffer = document.getElementById('buffer');
                $('#buffer').attr('width', canvasWidth).attr('height', canvasHeight);
                bctx = buffer.getContext('2d');
                backedImage.active = false;
            }

            function init() {
                baseImage = new Image();
                baseImage.onload = function() {
                    //背景画像を読み込めた

                    //背景画像のサイズを取得
                    baseImageWidth = baseImage.naturalWidth;
                    baseImageHeight = baseImage.naturalHeight;
                    centerPosition.x = baseImageWidth / 2;
                    centerPosition.y = baseImageHeight / 2;

                    //キャンバスを初期化
                    prepareCanvas();

                    //表示
                    if (mode == 'H') {
                        redraw(false);
                    } else {
                        drawTrim();
                        redrawTrim('');
                    }
                }
                //背景画像の読み込み開始
                baseImage.src = "<?php echo $src_wall; ?>";   //読み込みたい画像のパス
            }
            init();

            $(window).resize(function() {
console.log('resize');
                //キャンバスを再構築
                prepareCanvas();
                //表示
                if (mode == 'H') {
                    redraw(false);
                } else {
                    drawTrim();
                    redrawTrim('');
                }
            });

            <?php
                if ($detect->isMobile()) {
                    echo <<<EOD
                        //スクロールしたら上の余計なものを消す
                        $(window).scroll(function() {
                            if ($(window).scrollTop() > 0) {
                                $('nav').hide();
                                $('#title').hide();
                            } else {
                                //$('nav').show();
                                //$('#title').show();
                            }
                        });

                        //上のスペースをタップしたら再表示
                        $('#navi-bar').on('click', function () {
                            $('nav').show();
                            $('#title').show();
                        });
            
EOD;
                }
            ?>

            $('#circle-button').on('click', function () {
                var color = $('#color-button').data('value');
                var size = $('#size-button').data('value');
console.log(color + " " + size);

                primitives.push({t:'cir', x: Math.round(centerPosition.x), y: Math.round(centerPosition.y), r: itemSizes[size].circleSize, c: color});
                selectIndex(primitives.length - 1);
            });

            $('.text-items > .dropdown-item').on('click', function () {
                var text = $(this).data('value');
console.log(text);
                if (text.length > 0) {
                    var color = $('#color-button').data('value');
                    var size = itemSizes[$('#size-button').data('value')].fontSize;
                    var dir = $(this).data('dir');
                    ctx.font = size + 'pt Arial';
                    var w,h;
                    if (dir == 'H') {
                        w = Math.round(ctx.measureText(text).width);
                        h = Math.round(size * 1.33);
                    } else {
                        var sz = measureVerticalText(ctx, text);
                        w = Math.round(sz.width);
                        h = Math.round(sz.height);
                    }
                    primitives.push({t:'txt', x: Math.round(centerPosition.x), y: Math.round(centerPosition.y), s: text, r: size, d: dir, c: color, w: w, h: h});
                    selectIndex(primitives.length - 1);
                }
            });

            $('.item-colors > .dropdown-item').on('click', function () {
                if (selectedIndex >= 0) {
                    primitives[selectedIndex].c = $(this).data('value');
                    redraw(false);
                } else {
                    $('#color-button').data('value', $(this).data('value')).html($(this).data('disp'));
                }
            });

            $('.item-sizes > .dropdown-item').on('click', function () {
                if (selectedIndex >= 0) {
                    var p = primitives[selectedIndex];
                    switch (p.t) {
                        case 'cir':
                            p.r = itemSizes[$(this).data('value')].circleSize;
                            break;
                        case 'txt':
                            p.r = itemSizes[$(this).data('value')].fontSize;
                            ctx.font = p.r + 'pt Arial';
                            if (p.d == 'H') {
                                p.w = Math.round(ctx.measureText(p.s).width);
                                p.h = Math.round(p.r * 1.33);
                            } else {
                                var sz = measureVerticalText(ctx, p.s);
                                p.w = Math.round(sz.width);
                                p.h = Math.round(sz.height);
                            }
                    }
                    backedImage.active = false;
                    redraw(true);
                } else {
                    $('#size-button').data('value', $(this).data('value')).html($(this).data('disp'));
                }
            });

            $('#delete-button').on('click', function () {
                if (selectedIndex >= 0) {
                    delete primitives[selectedIndex];
                    selectIndex(-1);
                }
            });

            $('#zoom-in').on('click', function () {
                var scale = viewScale * 1.25;
                if (scale < zoomMax) {
                    viewScale = scale;
                    backedImage.active = false;
                    redraw(true);
                }
            });

            $('#zoom-out').on('click', function () {
                var scale = viewScale / 1.25;
                if (scale > zoomMin) {
                    viewScale = scale;
                    backedImage.active = false;
                    redraw(true);
                }
            });

            $('#mode-button').on('click', function (e) {
                if (mode == 'H') {
                    //トリムモードになった
                    mode = 'T';
                    $(this).html('配置モード');
                    $('#tools').hide();

                    //選択解除
                    selectedIndex = -1;
                    $('#delete-button').attr('disabled', 'disabled');

                    drawTrim();
                    redrawTrim('');

                } else {
                    //配置モードになった
                    mode = 'H';
                    $(this).html('トリムモード');
                    $('#tools').show();

                    backedImage.active = false;
                    redraw(true);
                }
                e.stopPropagation(); //親オブジェクトでclickイベントを発生させない
            });

            function getPosMobile(e, index) {
                var can = $('#canvas');
                var xs = canvasWidth / can.width(); //実際と表示の大きさを表すスケール
                var ys = canvasHeight / can.height();
                var rect = e.target.getBoundingClientRect(); //左上オフセット取得用
                return {x: (e.touches[index].pageX - rect.left) * xs, y: (e.touches[index].pageY - rect.top - window.pageYOffset) * ys };
            }

            function getPosPC(e) {
                var can = $('#canvas');
                var xs = canvasWidth / can.width(); //実際と表示の大きさを表すスケール
                var ys = canvasHeight / can.height();
                return {x: e.offsetX * xs, y: e.offsetY * ys };
            }

            var touchMovePosition = { x:0, y:0, touch:false, dist:0, pinch:false };

            function touchStart(e, fingers, pos) {
                if (mode == 'H') {
                    if (fingers == 1) {
                        touchMovePosition.x = pos.x;
                        touchMovePosition.y = pos.y;
                        touchMovePosition.touch = true;
                        touchMovePosition.pinch = false;
                    } else if (fingers == 2) {
                        touchMovePosition.touch = false;
                        touchMovePosition.pinch = true;
                        touchMovePosition.dist = Math.sqrt(
                            (pos.x - touchMovePosition.x) * (pos.x - touchMovePosition.x) +
                            (pos.y - touchMovePosition.y) * (pos.y - touchMovePosition.y));
                    }
                } else if (fingers == 1) {
                    var m;
                    if (canvasWidth > canvasHeight) {
                        m = canvasHeight * 0.05;
                    } else {
                        m = canvasWidth * 0.05;
                    }

                    var iw = canvasWidth - m - m;
                    var ih = canvasHeight - m - m;
                    var dist = m;
                    var dir = '';
                    var d = pos.x - (m + iw * trim.l);
                    if (d < 0) d = -d;
                    if (d < dist) {
                        dist = d;
                        dir = 'L';
                    }
                    d = pos.x - (m + iw * (1 - trim.r));
                    if (d < 0) d = -d;
                    if (d < dist) {
                        dist = d;
                        dir = 'R';
                    }
                    d = pos.y - (m + ih * trim.t);
                    if (d < 0) d = -d;
                    if (d < dist) {
                        dist = d;
                        dir = 'T';
                    }
                    d = pos.y - (m + ih * (1 - trim.b));
                    if (d < 0) d = -d;
                    if (d < dist) {
                        dist = d;
                        dir = 'B';
                    }
                    touchMovePosition.x = pos.x;
                    touchMovePosition.y = pos.y;
                    touchMovePosition.touch = dir;
                    if (touchMovePosition.touch != '') redrawTrim(touchMovePosition.touch);
                }
            }

            function touchMove(e, fingers, pos, pos1) {
                if (mode == 'H') {
                    if (fingers == 1 && touchMovePosition.pinch == false) {
                        centerPosition.x += (touchMovePosition.x - pos.x) / viewScale;
                        centerPosition.y += (touchMovePosition.y - pos.y) / viewScale;

                        if (centerPosition.x < 0) {
                            centerPosition.x = 0;
                        } else if (centerPosition.x >= baseImageWidth) {
                            centerPosition.x = baseImageWidth - 1;
                        }
                        if (centerPosition.y < 0) {
                            centerPosition.y = 0;
                        } else if (centerPosition.y >= baseImageHeight) {
                            centerPosition.y = baseImageHeight - 1;
                        }

                        touchMovePosition.x = pos.x;
                        touchMovePosition.y = pos.y;
                        touchMovePosition.touch = false;

                        if (selectedIndex >= 0) {
                            var p = primitives[selectedIndex];
                            p.x = centerPosition.x;
                            p.y = centerPosition.y;
                        }
                        backedImage.active = false;
                        redraw(true);
                    }
                    else if (fingers == 2)  {
                        //ピンチ（ズーム）処理
                        touchMovePosition.touch = false;
                        touchMovePosition.pinch = true;
                        //var dist = Math.sqrt((pos1.x - touchMovePosition.x) * (pos1.x - touchMovePosition.x) + (pos1.y - touchMovePosition.y) * (pos1.y - touchMovePosition.y));
                        var dist = Math.sqrt((pos1.x - pos.x) * (pos1.x - pos.x) + (pos1.y - pos.y) * (pos1.y - pos.y));
                        var scale = viewScale * (dist / touchMovePosition.dist);
                        touchMovePosition.dist = dist;

                        if (scale > zoomMax) scale = zoomMax;
                        if (scale < zoomMin) scale = zoomMin;
                        if (scale != viewScale) {
                            viewScale = scale;
                            backedImage.active = false;
                            redraw(true);
                        }
                    }
                } else if (fingers == 1 && touchMovePosition.touch != '') {
                    var m;
                    if (canvasWidth > canvasHeight) {
                        m = canvasHeight * 0.05;
                    } else {
                        m = canvasWidth * 0.05;
                    }

                    var dist = m * 3;
                    var d,t,f = false;
                    if (touchMovePosition.touch == 'L') {
                        d = pos.x - (m + canvasWidth * trim.l);
                        if (d < 0) d = -d;
                        if (d < dist) {
                            t = trim.l + (pos.x - touchMovePosition.x) / (canvasWidth - m - m);
                            if (t < 0) {
                                t = 0;
                            } else if ((trim.r + t) > 0.9) {
                                t = 0.9 - trim.r;
                            }
                            f = (trim.l != t);
                            trim.l = t;
                        } else {
                            touchMovePosition.touch = '';
                        }
                    }
                    else if (touchMovePosition.touch == 'R') {
                        d = pos.x - (m + canvasWidth * (1 - trim.r));
                        if (d < 0) d = -d;
                        if (d < dist) {
                            t = trim.r - (pos.x - touchMovePosition.x) / (canvasWidth - m - m);
                            if (t < 0) { t = 0; } else if ((1.0 - trim.l - t) < 0.1) { t = 1.0 - trim.l - 0.1; }
                            f = (trim.r != t);
                            trim.r = t;
                        } else {
                            touchMovePosition.touch = '';
                        }
                    }
                    else if (touchMovePosition.touch == 'T') {
                        d = pos.y - (m + canvasHeight * trim.t);
                        if (d < 0) d = -d;
                        if (d < dist) {
                            t = trim.t + (pos.y - touchMovePosition.y) / (canvasHeight - m - m);
                            if (t < 0) { t = 0; } else if ((1.0 - trim.b - t) < 0.1) { t = 1.0 - trim.b - 0.1; }
                            f = (trim.t != t);
                            trim.t = t;
                        } else {
                            touchMovePosition.touch = '';
                        }
                    }
                    else {//if (touchMovePosition.touch == 'B') {
                        d = pos.y - (m + canvasHeight * (1 - trim.b));
                        if (d < 0) d = -d;
                        if (d < dist) {
                            t = trim.b - (pos.y - touchMovePosition.y) / (canvasHeight - m - m);
                            if (t < 0) { t = 0; } else if ((1.0 - trim.t - t) < 0.1) { t = 1.0 - trim.t - 0.1; }
                            f = (trim.b != t);
                            trim.b = t;
                        } else {
                            touchMovePosition.touch = '';
                        }
                    }
                    touchMovePosition.x = pos.x;
                    touchMovePosition.y = pos.y;
                    if (f || touchMovePosition.touch == '') redrawTrim(touchMovePosition.touch);
                }
            }

            function touchEnd() {
                if (mode == 'H') {
                    if (touchMovePosition.touch) {
                        touchMovePosition.touch = false;

                        // 原画上の座標
                        var px = (touchMovePosition.x - drawCenter.x) / viewScale + centerPosition.x;
                        var py = (touchMovePosition.y - drawCenter.y) / viewScale + centerPosition.y;

                        //タップした場所に選択できるアイテムがあるか調べる
                        var selected = false;
                        var indices = [];
                        primitives.forEach(function (p, index) {
                            switch (p.t) {
                                case 'cir':
                                    if (Math.sqrt((px - p.x) * (px - p.x) + (py - p.y) * (py - p.y)) < p.r) {
                                        indices.push(index)
                                    }
                                    break;
                                case 'txt':
                                    if (px > (p.x - p.w / 2) && px < (p.x + p.w / 2) && py > (p.y - p.h / 2) && py < (p.y + p.h / 2)) {
                                        indices.push(index)
                                    }
                            }

                        });
                        if (indices.length == 0) {
                            //候補なし、選択解除
                            //selectIndex(-1);
                        } else if (selectedIndex < 0) {
                            //現時点で選択が無いので、最新のアイテムを選択
                            selectIndex(indices[indices.length - 1]);
                            selected = true;
                        } else {
                            //候補の中に選択中のアイテムがあるか調べる
                            var i;
                            for (i = 0; i < indices.length; i++) {
                                if (indices[i] == selectedIndex) {
                                    //現在の選択アイテムがあったので、ひとつ前のアイテムに選択を切り替える
                                    if (i > 0) {
                                        selectIndex(indices[i-1]);
                                    } else {
                                        selectIndex(indices[indices.length - 1]);
                                    }
                                    selected = true;
                                    break;
                                }
                            }
                            if (!selected) {
                                //現在の選択アイテムが候補に無かったので、最新のアイテムを選択
                                selectIndex(indices[indices.length - 1]);
                                selected = true;
                            }
                        }
                        if (!selected) {
                            selectIndex(-1);
                        }
                    } else {

                    }
                } else {
                    if (touchMovePosition.touch != '') {
                        touchMovePosition.touch = '';
                        redrawTrim(touchMovePosition.touch);
                    }
                }
            }

            $("#canvas").on('touchstart', function(e) {
                e.preventDefault();

                var fingers = e.touches.length;
                var pos = getPosMobile(e, fingers - 1);
                touchStart(e, fingers, pos);
            });

            $('#canvas').on('mousedown', function (e) {
                var pos = getPosPC(e);
                touchStart(e, 1, pos);
            });

            $("#canvas").on('touchmove', function(e) {
                e.preventDefault();

                var fingers = e.touches.length;
                var pos = getPosMobile(e, 0);
                var pos1;
                if (fingers == 2) pos1 = getPosMobile(e, 1);
                touchMove(e, fingers, pos, pos1);
            });

            $('#canvas').on('mousemove', function (e) {
                if (e.buttons == 1) {
                    var pos = getPosPC(e);
                    touchMove(e, 1, pos, null);
                }
            });

            $("#canvas").on('touchend', function(e) {
                e.preventDefault();
                touchEnd();
            });

            $('#canvas').on('mouseup', function (e) {
                touchEnd();
            });

            //アイテムが選択されたときに、アイテムが中央にくるように画像をスクロールさせるタイマー
            var arCenterPos = [];
            var testTimer;
            function startTimer() {
                testTimer = setInterval(function() {
                    if (arCenterPos.length > 0) {
                        drawCenter.x = arCenterPos[arCenterPos.length - 1].x;
                        drawCenter.y = arCenterPos[arCenterPos.length - 1].y;
                        arCenterPos.pop();
                        backedImage.active = false;
                        redraw(true);
                    } else {
                        clearInterval(testTimer);
                    }
                }, 100);
            }

            //アイテムの選択を変更する場合に呼び出す
            function selectIndex(index) {
                if (selectedIndex != index) {
                    selectedIndex = index;
                    if (selectedIndex >= 0) {
                        var d = Math.sqrt(canvasWidth * canvasWidth + canvasHeight * canvasHeight) / 10;

                        var p = primitives[selectedIndex];
                        var px = (p.x - centerPosition.x) * viewScale + drawCenter.x;
                        var py = (p.y - centerPosition.y) * viewScale + drawCenter.y;

                        var dx = canvasWidth / 2 - px;
                        var dy = canvasHeight / 2 - py;
                        var d2 = Math.sqrt(dx * dx + dy * dy);
                        var n = Math.ceil(d2 / d);
                        if (n > 1) {
                            arCenterPos = [];
                            var i;
                            for (i = n; i > 0; i--) {
                                arCenterPos.push({x: i / n * dx + px, y: i / n * dy + py});
                            }
                            drawCenter.x = px;
                            drawCenter.y = py;
                            
                            startTimer();
                        }

                        centerPosition.x = p.x;
                        centerPosition.y = p.y;

                        $('#delete-button').removeAttr('disabled');
                    } else {
                        $('#delete-button').attr('disabled', 'disabled');
                    }
                    backedImage.active = false;
                    redraw(index >= 0);
                }
            }

            //選択中のアイテムの選択状態を表示するためのタイマー描画ルーチン
            setInterval(function () {
                if (mode == 'H') {
                    patternIndex = (patternIndex + 1) & 1;
                    if (backedImage.active && selectedIndex >= 0) {
                        ctx.drawImage(buffer, 0, 0, backedImage.w, backedImage.h, backedImage.x, backedImage.y, backedImage.w, backedImage.h);
                        var p = primitives[selectedIndex];
                        drawSelected(p);
                    }
                }
            }, 500);

            //選択されたアイテムを描画
            var patternIndex = 0;
            var linePatterns = [[10, 10, 10, 10], [0, 10, 10, 0]];
            function drawSelected(p) {
                switch (p.t) {
                    case 'cir':
                        //if (patternIndex >= circleAngles.length) {
                        //    patternIndex = 0;
                        //}
                        ctx.strokeStyle = p.c;
                        ctx.setLineDash(linePatterns[patternIndex]);
                        ctx.lineWidth = (8 * viewScale < 1) ? 1 : (8 * viewScale);
                        ctx.beginPath();
                        ctx.arc(drawCenter.x, drawCenter.y, p.r * viewScale, 0, d360, true);
                        ctx.stroke();
                        break;
                    case 'txt':
                        ctx.font = Math.round(p.r * viewScale) + 'pt Arial';
                        ctx.fillStyle = p.c;
                        ctx.textAlign = "center";
                        ctx.textBaseline = "middle";
                        if (p.d == 'H') {
                            ctx.fillText(p.s, drawCenter.x, drawCenter.y);
                        } else {
                            fillVerticalText(ctx, p.s, drawCenter.x, drawCenter.y);
                        }

                        if (p.w > 0 && p.h > 0) {
                            ctx.setLineDash(linePatterns[patternIndex]);
                            ctx.strokeStyle = p.c;
                            ctx.lineWidth = (4 * viewScale < 1) ? 1 : (4 * viewScale);
                            ctx.beginPath();
                            ctx.rect(drawCenter.x - (p.w / 2 + 2) * viewScale, drawCenter.y - (p.h / 2 + 2) * viewScale, (p.w + 4) * viewScale, (p.h + 4) * viewScale);
                            ctx.stroke();
                        }
                }
            }

            //描画ルーチン
            //store=true で選択されたアイテムの背景部分をbufferに保存する
            function redraw(store) {
                var sw = canvasWidth / viewScale;
                var sh = canvasHeight / viewScale;
                var sx = centerPosition.x - drawCenter.x / viewScale;
                var sy = centerPosition.y - drawCenter.y / viewScale;

                //写真の境界外の塗りつぶし
                if (sx < 0) {
                    var w = -sx * viewScale;
                    ctx.fillStyle = "black";
                    ctx.fillRect(0, 0, w, canvasHeight);
                }
                if (sx + sw > baseImageWidth) {
                    var w = (sx + sw - baseImageWidth) * viewScale;
                    ctx.fillStyle = "black";
                    ctx.fillRect(canvasWidth - w, 0, w, canvasHeight);
                }
                if (sy < 0) {
                    var h = -sy * viewScale;
                    ctx.fillStyle = "black";
                    ctx.fillRect(0, 0, canvasWidth, h);
                }
                if (sy + sh > baseImageHeight) {
                    var h = (sy + sh - baseImageHeight) * viewScale;
                    ctx.fillStyle = "black";
                    ctx.fillRect(0, canvasHeight - h, canvasWidth, h);
                }

                //写真貼り付け
                ctx.drawImage(baseImage, sx, sy, sw, sh, 0, 0, canvasWidth, canvasHeight);

                //canvas上のトリミング境界座標
                var tx = (baseImageWidth * trim.l - sx) * viewScale;
                var ty = (baseImageHeight * trim.t - sy) * viewScale;
                var tw = baseImageWidth * (1 - trim.r - trim.l) * viewScale;
                var th = baseImageHeight * (1 - trim.t - trim.b) * viewScale;
                ctx.globalAlpha = 0.5;
                ctx.fillStyle = "black";
                if (tx >= 0) ctx.fillRect(0, 0, tx, canvasHeight);
                if ((tx + tw) < canvasWidth) ctx.fillRect(tx + tw, 0, canvasWidth - (tx + tw), canvasHeight);
                var f = (tx >= 0 && tx < canvasWidth) || ((tx + tw) >= 0 && (tx + tw) < canvasWidth) || (tx < 0 && (tx + tw) >= canvasWidth);
                if (ty >= 0 && f) ctx.fillRect(tx, 0, tw, ty);
                if ((ty + th) < canvasHeight && f) ctx.fillRect(tx, ty + th, tw, canvasHeight - (ty + th));
                ctx.globalAlpha = 1.0;


                ctx.setLineDash([]);
                ctx.lineWidth = (8 * viewScale < 1) ? 1 : (8 * viewScale);
                primitives.forEach(function(p,i) {
                    if (i != selectedIndex) {
                        var px = (p.x - centerPosition.x) * viewScale + drawCenter.x;
                        var py = (p.y - centerPosition.y) * viewScale + drawCenter.y;
                        switch (p.t) {
                            case 'cir':
                                ctx.strokeStyle = p.c;
                                ctx.beginPath();
                                ctx.arc(px, py, p.r * viewScale, 0, d360, false);
                                ctx.stroke();
                                break;
                            case 'txt':
                                ctx.font = Math.round(p.r * viewScale) + 'pt Arial';
                                ctx.fillStyle = p.c;
                                ctx.textAlign = "center";
                                ctx.textBaseline = "middle";
                                if (p.d == 'H') {
                                    ctx.fillText(p.s, px, py);
                                } else {
                                    fillVerticalText(ctx, p.s, px, py);
                                }
                        }
                    }
                });

                if (selectedIndex >= 0) {
                    var p = primitives[selectedIndex];

                    if (store) {
                        switch (p.t) {
                            case 'cir':
                                var r = p.r * viewScale + 10;
                                backedImage.x = drawCenter.x - r;
                                backedImage.w = r * 2;
                                backedImage.y = drawCenter.y - r;
                                backedImage.h = r * 2;
                                bctx.drawImage(canvas, backedImage.x, backedImage.y, backedImage.w, backedImage.h, 0, 0, backedImage.w, backedImage.h);
                                backedImage.active = true;
                                break;
                            case 'txt':
                                var w = p.w * viewScale + 10;
                                var h = p.h * viewScale + 10;
                                backedImage.x = drawCenter.x - w / 2;
                                backedImage.w = w;
                                backedImage.y = drawCenter.y - h / 2;
                                backedImage.h = h;
                                bctx.drawImage(canvas, backedImage.x, backedImage.y, backedImage.w, backedImage.h, 0, 0, backedImage.w, backedImage.h);
                                backedImage.active = true;
                        }
                    }

                    drawSelected(p);
                }
            }

            //完成画像の作成用
            //UploadProblem.phpのと同じ
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

            //縦書き時のサイズを取得する
            function measureVerticalText(context, text) {
                var h = context.measureText("あ").width;
                var w = 0;
                Array.prototype.forEach.call(text, function(ch, j) {
                    var ww = context.measureText(ch).width;
                    if (ww > w) w = ww;
                });
                return {width: w, height: h * text.length}
            };

            //トリミング描画ルーチン。初回
            function drawTrim() {
                var m;
                if (canvasWidth > canvasHeight) {
                    m = canvasHeight * 0.05;
                } else {
                    m = canvasWidth * 0.05;
                }
                bctx.fillStyle = "black";
                bctx.fillRect(0, 0, m, canvasHeight);
                bctx.fillRect(canvasWidth - m, 0, m, canvasHeight);
                bctx.fillRect(m - 1, 0, canvasWidth - m - m + 2, m + 1);
                bctx.fillRect(m - 1, canvasHeight - m - 1, canvasWidth - m - m + 2, m + 1);
                bctx.drawImage(baseImage, 0, 0, baseImageWidth, baseImageHeight, m, m, canvasWidth - m - m, canvasHeight - m - m);

                var xs = (canvasWidth - m - m) / baseImageWidth;
                var ys = (canvasHeight - m - m) / baseImageHeight;

                bctx.setLineDash([]);
                bctx.lineWidth = (8 * xs < 1) ? 1 : (8 * xs);
                primitives.forEach(function(p,i) {
                    var px = p.x * xs + m;
                    var py = p.y * ys + m;
                    switch (p.t) {
                        case 'cir':
                            bctx.strokeStyle = p.c;
                            bctx.beginPath();
                            bctx.ellipse(px, py, p.r * xs, p.r * ys, 0, 0, d360);
                            bctx.stroke();
                            break;
                        case 'txt':
                            bctx.strokeStyle = p.c;
                            bctx.beginPath();
                            bctx.rect(px - p.w / 2 * xs, py - p.h / 2 * ys, p.w * xs , p.h * ys);
                            bctx.stroke();
                    }
                });
            }

            function redrawTrim(dir) {
                //背景復帰
                ctx.drawImage(buffer, 0, 0);

                var m;
                if (canvasWidth > canvasHeight) {
                    m = canvasHeight * 0.05;
                } else {
                    m = canvasWidth * 0.05;
                }

                //トリミング部を暗くする
                var ix = m;
                var iy = m;
                var iw = canvasWidth - m - m;
                var ih = canvasHeight - m - m;
                ctx.globalAlpha = 0.5;
                ctx.fillStyle = "black";
                if (trim.l > 0) ctx.fillRect(ix, iy, trim.l * iw, ih);
                if (trim.r > 0) ctx.fillRect(ix + (1 - trim.r) * iw, iy, trim.r * iw, ih);
                if (trim.t > 0) ctx.fillRect(ix + trim.l * iw, iy, (1 - trim.l - trim.r) * iw, ih * trim.t);
                if (trim.b > 0) ctx.fillRect(ix + trim.l * iw, iy + (1 - trim.b) * ih, (1 - trim.l - trim.r) * iw, ih * trim.b);
                ctx.globalAlpha = 1.0;

                //△
                ctx.lineWidth = 3;
                var x = ix + iw * trim.l;
                var y = iy + ih * (trim.t + (1 - trim.b))  / 2;
                ctx.strokeStyle = (dir != 'L' ? "white" : "red");
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(x - m, y - m / 2);
                ctx.lineTo(x - m, y + m / 2);
                ctx.closePath();
                ctx.stroke();
                x = ix + (1 - trim.r) * iw;
                ctx.strokeStyle = (dir != 'R' ? "white" : "red");
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(x + m, y - m / 2);
                ctx.lineTo(x + m, y + m / 2);
                ctx.closePath();
                ctx.stroke();
                x = ix + iw * (trim.l + (1 - trim.r))  / 2;
                y = iy + ih * trim.t;
                ctx.strokeStyle = (dir != 'T' ? "white" : "red");
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(x - m / 2, y - m);
                ctx.lineTo(x + m / 2, y - m);
                ctx.closePath();
                ctx.stroke();
                y = iy + (1 - trim.b) * ih;
                ctx.strokeStyle = (dir != 'B' ? "white" : "red");
                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineTo(x - m / 2, y + m);
                ctx.lineTo(x + m / 2, y + m);
                ctx.closePath();
                ctx.stroke();
                ctx.stroke();
            }

            //ページから離れるときにアラート表示
            var alertUnload = true;
            window.addEventListener("beforeunload", function(e){
                if (alertUnload) e.returnValue = "移動していい？";//このテキストではなく既定のメッセージが表示される
            }, false);

            //編集結果をform1に保存
            function saveToForm() {
                var cloned = [];
                primitives.forEach(function(p,i) {
                    var copy = {};
                    Object.assign(copy, p);//複製
                    if (copy.t == 'txt') {
                        //btoa 日本語対策
                        copy.s = unescape(encodeURIComponent(copy.s));
                    }
                    cloned.push(copy);
                });
                $('#primitives').val(window.btoa(JSON.stringify(cloned)));
                $('#trim').val(window.btoa(JSON.stringify(trim)));
            }

            //戻る または キャンセル
            $('#prev-button').on('click', function () {
                <?php
                    if ($_SESSION["EDIT_BASE_TYPE"] == 'PROBLEM_ID') {
                        //キャンセル
                        echo <<<EOD
                            location.href = './DisplayProblem.php?pid={$_SESSION["EDIT_BASE_VALUE"]}';

EOD;
                    } else {
                        //戻る
                        echo <<<EOD
                            alertUnload = false;
                            saveToForm();
                            $('#location').val('./SelectWalls.php');
                            $('#submit').trigger('click');
                                
EOD;
                    }
                ?>
            });

            //次へ または 更新
            $('#next-button').on('click', function () {
                alertUnload = false;
                saveToForm();
                $('#location').val(<?php
                    echo ($_SESSION["EDIT_BASE_TYPE"] != 'PROBLEM_ID') ? "'./UploadProblem.php'"
                        : "'./DisplayProblem.php?pid=${_SESSION["EDIT_BASE_VALUE"]}'"; 
                ?>);

                //編集モードでは完成画像を作成する
                <?php
                    if ($_SESSION["EDIT_BASE_TYPE"] == 'PROBLEM_ID') {
                        echo <<<EOD
                            var img_b64 = drawPrimitives(baseImage); //トリミングもおこなわれる
                            $('#imgb64').val(img_b64);

EOD;
                    }
                ?>

                $('#submit').trigger('click');
            });

        });
    </script>
</body>
</html>
