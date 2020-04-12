<?php
require_once "php/Mobile_Detect.php";
$detect = new Mobile_Detect;

session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {
    header("Location: Login.php");
    exit();
}

require_once 'Env.php';

/*
    //選択した画像をセッション変数で返す
    $_SESSION["EDIT_BASE_TYPE"] = TMP_PATH または WALL_ID
    $_SESSION["EDIT_BASE_VALUE"] = パス または wallpicture.id

*/

if (isset($_POST['submit'])) {
    if (isset( $_FILES['problem_picture']) && $_FILES['problem_picture']['size'] !== 0) {

        if ($_FILES['problem_picture']['error'] == 2) {
            $errorMessage = '写真の容量が大きすぎです';
        }
        else {
            $rotation = intval($_POST['rotation']);
            if ($rotation != 0 && $rotation != 90 && $rotation != 180 && $rotation != 270) {
                $errorMessage = 'アップロードエラー ' . $rotation;
            }
        }

        if (!isset($errorMessage)) {
            //一時ファイルに保存
            $tmpPath = tempnam($directories['tmp'], 'base_');
            if (!move_uploaded_file($_FILES['problem_picture']['tmp_name'], $tmpPath)) {
                $errorMessage = '写真をアップロードできませんでした';
            }
        }

        if (!isset($errorMessage)) {
            //画像形式に応じて拡張子を決める、画像を読み込んで回転する
            $mime = mime_content_type($tmpPath);
            if ($mime == 'image/jpeg') {
                //読み込み
                if (($srcImage = imagecreatefromjpeg($tmpPath)) === FALSE) {
                    $errorMessage = "imagecreatefromjpeg(\"${tmpPath}\")でエラー";
                }
            } else if ($mime == 'image/png') {
                //読み込み
                if (($srcImage = imagecreatefrompng($tmpPath)) === FALSE) {
                    $errorMessage = "imagecreatefrompng(\"${tmpPath}\")でエラー";
                }
            } else {
                $errorMessage = "サポートしていない画像形式です。".$mime;
                unlink($tmpPath);
            }
        }

        if (!isset($errorMessage)) {
            $sz = getimagesize($tmpPath);
            unlink($tmpPath);

            if ($sz[0] > $sz[1]) {
                $scale = 2500 / $sz[0]; //長辺を2500に正規化
            } else {
                $scale = 2500 / $sz[1];
            }
            if ($scale < 1.0) {
                $dw = $sz[0] * $scale;
                $dh = $sz[1] * $scale;
            } else {
                $dw = $sz[0];           //2500より小さいときはわざわざ大きくしない
                $dh = $sz[1];
            }

            //縮小
            $tmpImage = imagecreatetruecolor($dw, $dh);
            imagecopyresampled(
                $tmpImage,  // コピー先の画像
                $srcImage,     // コピー元の画像
                0,          // コピー先の x 座標
                0,          // コピー先の y 座標。
                0,          // コピー元の x 座標
                0,          // コピー元の y 座標
                $dw,        // コピー先の幅
                $dh,        // コピー先の高さ
                $sz[0],     // コピー元の幅
                $sz[1]);    // コピー元の高さ
            imagedestroy($srcImage);

            //回転
            if ($_POST['rotation'] == '0' || $_POST['rotation'] == '180') {
                $dstImage = imagecreatetruecolor($dw, $dh);
            } else {
                $dstImage = imagecreatetruecolor($dh, $dw);
            }
            $dstImage = imagerotate($tmpImage, -$rotation, 0);
            imagedestroy($tmpImage);

            //JPEG保存
            $tmpPath = tempnam($directories['tmp'], 'base_');
            rename($tmpPath, $tmpPath . '.jpg');
            $tmpPath .= '.jpg';
            if (imagejpeg($dstImage, $tmpPath, 80) === FALSE) {
                $errorMessage = "写真を保存できませんでした";
            }
            imagedestroy($dstImage);
        }

        if (!isset($errorMessage)) {
            $_SESSION["EDIT_BASE_VALUE"] = $tmpPath;
            $_SESSION["EDIT_BASE_TYPE"] = 'TMP_PATH';
            header('Location:' . './EditHolds.php');
            exit();
        } else {
            //失敗したのでセッション変数をクリア
            unset($_SESSION["EDIT_BASE_TYPE"]);
            unset($_SESSION["EDIT_BASE_VALUE"]);
        }
    }
    else if (isset($_POST['wallid']) && $_POST['wallid'] != '') {
        $_SESSION["EDIT_BASE_TYPE"] = 'WALL_ID';
        $_SESSION["EDIT_BASE_VALUE"] = $_POST['wallid'];
        header('Location:' . './EditHolds.php');
        exit();
    }
}

//DBに接続してベースとなる壁を読み込む
$dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));

    $sql = "SELECT * FROM `wallpicture` ORDER BY `location`";
    $stmt = $pdo->query($sql);

} catch (PDOException $e) {
    $errorMessage = 'データベースの接続に失敗しました。'.$e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <title>ベース画像の選択</title>
</head>
<body>
    <div class="container">
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

EOD;
            }
        ?>

        <h5 class="text-center my-3">壁写真をアップロード</h5>
        <form class="form mb-5" action="#" method="post" id="form1" enctype="multipart/form-data">
            <!-- 課題画像の選択 -->
            <div class="custom-file col ml-auto" id="input-file">
                <input type="file" class="custom-file-input" name="problem_picture" id="problem_picture" accept="image/jpeg,image/png">
                <label class="custom-file-label" for="problem_picture" id="problem_picture_label">壁写真を選択...</label>
                <div class="invalid-feedback">ファイルが選択されていません</div>
            </div>


            <!-- HIDDEN -->
            <input type="hidden" name="rotation" id="rotation">
            <input type="hidden" name="wallid" id="wallid">

            <!-- 投稿ボタン -->
            <button class="btn btn-primary align-middle" type="submit" name="submit" style="display:none"></button>
        </form>
        <h3 class="text-center my-3">または</h3>
        <br>
        <h5 class="text-center my-3">ベースの壁写真を選択</h5>

        <!-- ここに検索結果の課題一覧またはエラーを表示 -->
        <div class="row justify-content-center mb-5" id="walls_body">
            <?php
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (strlen($row['imagefile_h']) > 0 && file_exists($directories['wall_image'].$row['imagefile_t'])) {//画像ファイルが存在するものだけ
                        $src = $urlpaths['wall_image'].$row['imagefile_t'];
                        $url = $urlpaths['wall_image'].$row['imagefile'];
                        $id = $row['id'];
                        echo <<<EOD
                            <div class="card mb-2 mr-2" style="width:10rem;">
                                <img class="card-img-top" src="{$src}" alt="壁画像" data-url="{$url}" data-wallid="{$id}">
                                <div class="card-body">
                                    <h5 class="card-title">{$row['name']}</h5>
                                </div>
                            </div>
EOD;
                    }
                }
            ?>
        </div>
        <div class="modal fade" id="testModal" tabindex="-1" role="dialog" aria-labelledby="basicModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <img class="rounded img-fluid d-block" alt="壁画像" id="preview" data-path="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="use-button">この壁を使用する</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" id="nouse-button">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/jquery.exif.js"></script>
    <script>$(function(){
        var selectMode = '';

        //ブラウザの戻るで戻ってきて同じ画像ファイルを選択したときにchangeイベントが
        //発生しないのを防ぐために、毎回クリアする
        $('#problem_picture').val('');

        //投稿画像のプレビュー
        $('#problem_picture').on('change', function (e) {
            if (e.target.files.length == 0) {
                //選択されなかった
                $('#problem_picture_label').html('壁写真を選択...');
            } else {
                //画像の向きを取得
                $(this).fileExif(function(exif) {
                    var rotation = 0;
                    switch (exif.Orientation) {
                        case 3: rotation = 180; break;
                        case 6: rotation = 90; break;
                        case 8: rotation = 270;
                    }
                    $('#rotation').val(rotation); //サーバー側でも回転するので記憶

                    //プレビューのために画像をロード
                    var reader = new FileReader();
                    reader.onload = function (e) {

                        //必要に応じて回転
                        var width, height;
                        if (typeof exif.ImageWidth !== 'undefined') {
                            width = exif.ImageWidth;
                            height = exif.ImageHeight;
                        } else {
                            width = exif.PixelXDimension;
                            height = exif.PixelYDimension;
                        }
                        var scale = 800 / width; //スマホの画像はでかいので、回転ついでに幅800に縮小
                        width = 800;
                        height *= scale;

                        ImgB64Resize(e.target.result, width, height, rotation, function(img_b64) {
                            const preview = document.getElementById('preview');
                            preview.src = img_b64;
                            $('#testModal').modal('show');
                        });
                    }
                    reader.readAsDataURL(e.target.files[0]);
                    var fileName = e.target.files[0].name;
                    $('#problem_picture_label').html(fileName);
                    selectMode = 'upload';
                });
            }
        });

        //参考サイト https://qiita.com/yasumodev/items/ec684e81ee2eac4bdddd
        //========================================================
        // Resize Base64 Image
        //   imgB64_src: string | "data:image/png;base64,xxxxxxxx"
        //   width     : number | dst img w
        //   height    : number | dst img h
        //   rotate    : number | dst img r 0/90/180/270 only
        //========================================================
        function ImgB64Resize(imgB64_src, width, height, rotate, callback) {
            // Image Type
            var img_type = imgB64_src.substring(5, imgB64_src.indexOf(";"));
            // Source Image
            var img = new Image();
            img.onload = function() {
                // New Canvas
                var canvas = document.createElement('canvas');
                if(rotate == 90 || rotate == 270) {
                    // swap w <==> h
                    canvas.width = height;
                    canvas.height = width;
                } else {
                    canvas.width = width;
                    canvas.height = height;
                }
                // Draw (Resize)
                var ctx = canvas.getContext('2d');
                if(0 < rotate && rotate < 360) {
                    ctx.rotate(rotate * Math.PI / 180);
                    if(rotate == 90)
                        ctx.translate(0, -height);
                    else if(rotate == 180)
                        ctx.translate(-width, -height);
                    else if(rotate == 270)
                        ctx.translate(-width, 0);
                }
                ctx.drawImage(img, 0, 0, width, height);
                // Destination Image
                var imgB64_dst = canvas.toDataURL(img_type);
                callback(imgB64_dst);
            };
            img.src = imgB64_src;
        }

        //選択画像のプレビュー
        $('#walls_body').on('click', 'img.card-img-top', function () {
            //画像情報を渡して表示
            $('#preview').attr('src', $(this).data('url')).data('wallid', $(this).data('wallid'));
            $('#testModal').modal('show');
            selectMode = 'prepared';
        });

        //この壁を使用する ボタン
        $('#use-button').on('click', function () {
            if (selectMode == 'upload') {
                $('#wallid').val('');
            } else { //prepared
                $('#wallid').val($('#preview').data('wallid'));
            }
            $('button[name="submit"]').trigger('click');
        });

        //閉じる（この壁を使用しない） ボタン
        $('#nouse-button').on('click', function () {
            //選択されなかった
            if (selectMode == 'upload') {
                $('#problem_picture_label').html('壁写真を選択...');
                $('input[type=file]').val('');
            }
            selectMode = '';
        });

    });</script>
</body>
</html>