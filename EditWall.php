<?php
require_once "php/Mobile_Detect.php";
$detect = new Mobile_Detect;

require_once 'Env.php';

session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {
    header("Location: Login.php");
    exit;
}

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

/*
セッション変数（入力）
$_SESSION["EDIT_WALL_TYPE"]     EDIT_WALL_VALUE の内容を示す。'WALL_ID' または 'TMP_PATH'
$_SESSION["EDIT_WALL_VALUE"]    WALL_IDの場合 DB上のwallpicture.id、TMP_PATHの場合 jpgファイルのフルパス。場所はtmpフォルダ
*/

if (!$_SESSION['MASTER']) {
    $errorMessage = 'このページを開く権限がありません';
} else if (!isset($_SESSION["EDIT_WALL_TYPE"], $_SESSION["EDIT_WALL_VALUE"])) {
    $errorMessage = '表示できるものはありません.';
}

//自身によるポスト
if (!isset($errorMessage) && isset($_POST['submit'], $_POST['imgb64'])) {

    if ($_SESSION["EDIT_WALL_TYPE"] == 'WALL_ID') {

        $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
        try {
            $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));

            //画像ファイル名を読み出す
            $sql = "SELECT `imagefile`,`imagefile_h`,`imagefile_t` FROM `wallpicture` WHERE `id` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($_SESSION["EDIT_WALL_VALUE"]));
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
            unset($_POST['imgb64']);//メモリ節約
            $img = str_replace(' ', '+', $img);
            $srcPath = $directories['wall_image'] . $row['imagefile'];   //後でサムネイル作成に使用する

            $byte=file_put_contents($srcPath, base64_decode($img));

            if ($byte === FALSE){//file_put_contents($srcPath, base64_decode($img)) === FALSE) {
                $errorMessage = '保存できませんでした。' . $srcPath;
            }
        }

        //サムネイル画像を作成
        if (!isset($errorMessage)) {

            $img_src = ImageCreateFromJPEG($srcPath);
            $sz = getimagesize($srcPath);

            $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $headimagesize['x'], $headimagesize['y']);
            //$imagefile_h = uniqid($problemid . '_h_') . '.jpg';     //ヘッドラインファイル名。後でDBに書き込む
            //$path = $directories['problem_image'] . $imagefile_h;
            $path = $directories['wall_image'] . $row['imagefile_h'];
            imagejpeg($img_dst, $path, 80);

            $img_dst = resizefullwidth($img_src, $sz[0], $sz[1], $thumbimagesize['x'], $thumbimagesize['y']);
            //$imagefile_t = uniqid($problemid . '_t_') . '.jpg';     //サムネイルファイル名。後でDBに書き込む
            //$path = $directories['problem_image'] . $imagefile_t;
            $path = $directories['wall_image'] . $row['imagefile_t'];
            imagejpeg($img_dst, $path, 80);

            imagedestroy($img_src);
            imagedestroy($img_dst);
        }

        if (isset($errorMessage)) {
            echo htmlspecialchars($errorMessage, ENT_QUOTES);
            exit();
        }

        //セッション変数をクリア
        unset($_SESSION["EDIT_WALL_TYPE"], $_SESSION["EDIT_WALL_VALUE"]);

        header('Location: ./WallList.php');
        exit();
    }
    else if ($_SESSION["EDIT_WALL_TYPE"] == 'TMP_PATH') {

        //javascriptから渡されたwebpフォーマットからjpegに変換
        $img = str_replace('data:image/jpeg;base64,', '', $_POST['imgb64']); //この画像データはトリミング済み
        unset($_POST['imgb64']);//メモリ節約
        $img = str_replace(' ', '+', $img);

        $path = $_SESSION["EDIT_WALL_VALUE"];
        $byte=file_put_contents($path, base64_decode($img));

        if ($byte === FALSE){//file_put_contents($srcPath, base64_decode($img)) === FALSE) {
            $errorMessage = '保存できませんでした。' . $srcPath;
        }

        if (isset($errorMessage)) {
            echo htmlspecialchars($errorMessage, ENT_QUOTES);
            exit();
        }

        header('Location: ./UploadWall.php');
        exit();
    }
}
//前工程から
else if (!isset($errorMessage)) {
    if ($_SESSION["EDIT_WALL_TYPE"] == 'WALL_ID') {
        //壁画像がIDで指定されている場合

        $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
        try {
            $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    
            $stmt = $pdo->prepare('SELECT * FROM `wallpicture` WHERE `id` = ?');
            $stmt->execute(array($_SESSION["EDIT_WALL_VALUE"]));
    
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
    } else if ($_SESSION["EDIT_WALL_TYPE"] == 'TMP_PATH') {
        //壁画像がユーザーによってアップロードされている場合

        $path = $_SESSION["EDIT_WALL_VALUE"];
        if (file_exists($path)) {
            $src_wall = $urlpaths['tmp'] . basename($_SESSION["EDIT_WALL_VALUE"]); //壁写真のURL
        } else {
            $errorMessage = '指定された壁写真が見つかりませんでした';
        }
    } else {
        //セッション変数が変なのでクリアする
        unset($_SESSION["EDIT_WALL_TYPE"], $_SESSION["EDIT_WALL_VALUE"], $_SESSION["EDIT_WALL_TRIM"], $_SESSION["EDIT_PRIMITIVES"]);

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

        <h3 class="text-center my-3" id="title">壁の編集</h3>
        <div class="row my-2" id="navi-bar">
            <button class="btn btn-secondary ml-1" id="prev-button">戻る</button>
            <button class="btn btn-primary mr-1 ml-auto" id="next-button">次へ</button>
        </div>

        <canvas class="img-fluid border m-0 p-0" id="canvas"></canvas>
        <canvas id="buffer" style="display:none"></canvas>

        <!-- POST -->
        <form action="#" method="post" id="form1" enctype="multipart/form-data" style="display:none">
            <button type="submit" name="submit" id="submit"></button>
            <input type="hidden" name="imgb64" id="imgb64">
        </form>
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script>
        $(function(){
            const d360 = 2 * Math.PI;

            //背景となる壁画像を読み込むimageオブジェクト
            var baseImage;  //<img>
            var baseImageWidth, baseImageHeight; //画像の元のサイズ

            var trim = { l:0.0, t:0.0, r:0.0, b:0.0 }; //トリミング比率 0.0-1.0

            //メインのキャンバスとコンテキスト
            var canvas, ctx;
            var canvasWidth, canvasHeight;

            //選択アイテムの重ね合わせ処理で、背景画像を一時的に保存するキャンバス
            var buffer, bctx;
            var backedImage = {active: false, x: 0, y: 0, w:0, h:0};

           

            function prepareCanvas() {
                $('#canvas').hide();    //消さないとwindow.heightがページの高さを指してしまう
console.log("window " + $(window).width() + ' x ' + $(window).height());
                canvasWidth = Math.round($(window).width() * 0.96);
                canvasHeight = Math.round($(window).height() * 0.96);
                canvas = document.getElementById('canvas');
                $('#canvas').attr('width', canvasWidth).attr('height', canvasHeight);
                ctx = canvas.getContext('2d');
                $('#canvas').show();

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

                    //キャンバスを初期化
                    prepareCanvas();

                    //表示
                    drawTrim();
                    redrawTrim('');
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
                drawTrim();
                redrawTrim('');
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
                if (fingers == 1) {
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
                if (fingers == 1 && touchMovePosition.touch != '') {
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
                if (touchMovePosition.touch != '') {
                    touchMovePosition.touch = '';
                    redrawTrim(touchMovePosition.touch);
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


            function ceateTrimmedImage(img) {
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
                return canvas.toDataURL("image/jpeg", 0.8);
            }

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

            //戻る
            $('#prev-button').on('click', function () {
                alertUnload = false;
                location.href = './WallList.php';
            });

            //次へ
            $('#next-button').on('click', function () {
                alertUnload = false;
                var img_b64 = ceateTrimmedImage(baseImage);
                $('#imgb64').val(img_b64);

                $('#submit').trigger('click');
            });

        });
    </script>
</body>
</html>
