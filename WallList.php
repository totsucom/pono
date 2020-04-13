<?php
//マスター専用のページ。課題作成のベースとなる壁画像を管理

session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {
    header("Location: Login.php");
    exit;
}

if (!$_SESSION['MASTER']) {
    echo 'このページを開く権限がありません';
    exit();
}

require_once 'Env.php';


function safe_delete_wallpic($filename) {
    if ($filename != '') {
        $path = $directories['wall_image'] . $filename;
        if (file_exists($path)) {
            delete($path);
        }
    }
}


/*
    //アップロード時、トリミング選択時に選択した画像をセッション変数で返す
    $_SESSION["EDIT_WALL_TYPE"] = TMP_PATH または WALL_ID
    $_SESSION["EDIT_WALL_VALUE"] = パス または wallpicture.id

*/

if (!isset($errorMessage) && isset($_POST['submit'],$_POST['mode'],$_POST['wallid'])) {
    if ($_POST['mode'] == 'delete') {
        //削除の場合
        $wallid = intval($_POST['wallid']);
        $ar = array();
        if ($wallid >= 0) {
            //壁IDで指定された場合
            $sql1 = "SELECT `imagefile`,`imagefile_h`,`imagefile_t` FROM `wallpicture` WHERE `id` = ?";
            $sql2 = "DELETE FROM `wallpicture` WHERE `id` = ?";
            $ar[] = $wallid;
        } else if (count($_POST['checked_wall']) > 0) {
            //チェックで複数選択された場合
            $sql1 = "SELECT `imagefile`,`imagefile_h`,`imagefile_t` FROM `wallpicture` WHERE `id` IN (";
            $sql2 = "DELETE FROM `wallpicture` WHERE `id` IN (";
            foreach($_POST['checked_wall'] as $value) {
                if (count($ar) > 0) {
                    $sql1 .= ',';
                    $sql2 .= ',';
                }
                $sql1 .= '?';
                $sql2 .= '?';
                $ar[] = $value;
            }
            $sql1 .= ')';
            $sql2 .= ')';
        }

        if (isset($sql1)) {
            try {
                $stmt = $pdo->prepare($sql1);
                $stmt->execute($ar);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    safe_delete_wallpic($row['imagefile']);
                    safe_delete_wallpic($row['imagefile_h']);
                    safe_delete_wallpic($row['imagefile_t']);
                }

                $stmt = $pdo->prepare($sql2);
                $stmt->execute($ar);
                $count = $stmt->rowCount();

                if ($count == 0) {
                    $errorMessage = '削除できませんでした';
                } else if ($count == count($_POST['checked_wall'])) {
                    $message = $count.'件削除しました';
                } else {
                    $errorMessage = $count.'件削除しました。一部削除できませんでした';
                }
            } catch (PDOException $e) {
                $errorMessage = 'データベースの接続に失敗しました。'.$e->getMessage();
            }
        }
    } else if ($_POST['mode'] == 'trim') {
        //トリミングの場合
        $wallid = intval($_POST['wallid']);
        $_SESSION["EDIT_WALL_VALUE"] = $wallid;
        $_SESSION["EDIT_WALL_TYPE"] = 'WALL_ID';
        header('Location:' . './EditWall.php');
        exit();

    } else if ($_POST['mode'] == 'upload' && isset( $_FILES['wall_picture']) && $_FILES['wall_picture']['size'] !== 0) {
        //新規画像がアップロードされた場合
        if ($_FILES['wall_picture']['error'] == 2) {
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
            if (!move_uploaded_file($_FILES['wall_picture']['tmp_name'], $tmpPath)) {
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
            $_SESSION["EDIT_WALL_VALUE"] = $tmpPath;
            $_SESSION["EDIT_WALL_TYPE"] = 'TMP_PATH';
            header('Location:' . './EditWall.php');
            exit();
        } else {
            //失敗したのでセッション変数をクリア
            unset($_SESSION["EDIT_WALL_VALUE"]);
            unset($_SESSION["EDIT_WALL_TYPE"]);
        }
    }
}

if (!isset($errorMessage)) {
    //DBに接続してベースとなる壁を読み込む
    try {
        $sql = "SELECT * FROM `wallpicture` ORDER BY `location`";
        $stmt = $pdo->query($sql);

    } catch (PDOException $e) {
        $errorMessage = 'データベースの接続に失敗しました。'.$e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <title>壁写真の一覧</title>
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

        <form class="form mb-5" action="#" method="post" id="form1" enctype="multipart/form-data">
            <h5 class="text-center my-3">壁写真をアップロード</h5>

            <!-- 課題画像の選択 -->
            <div class="custom-file col ml-auto mb-5" id="input-file">
                <input type="file" class="custom-file-input" name="wall_picture" id="wall_picture" accept="image/jpeg,image/png">
                <label class="custom-file-label" for="wall_picture" id="wall_picture_label">壁写真を選択...</label>
                <div class="invalid-feedback">ファイルが選択されていません</div>
            </div>

            <!-- HIDDEN -->
            <input type="hidden" name="rotation" id="rotation">
            <input type="hidden" name="mode" id="mode">
            <input type="hidden" name="wallid" id="wallid">

            <!-- 投稿ボタン -->
            <button class="btn btn-primary align-middle" type="submit" name="submit" style="display:none"></button>

            <h5 class="text-center">壁写真の一覧</h5>
            <div class="my-3 text-secondary">ボルダーが課題作成時に使用する、ベースとなる壁写真を管理します。</div>

            <!-- 選択後メニュー -->
            <div class="row" id="select-menu" style="display:none">
                <button type="button" class="btn btn-danger ml-auto mr-2" id="select-predel-button">選択された写真を削除する</button>
                <div class="ml-auto mr-2-1 text-danger" id="select-delete-confirm">
                    削除しますか？
                    <button type="button" class="btn btn-primary mx-1" id="select-delete-button">はい</button>
                    <button type="button" class="btn btn-secondary mx-1" id="select-cancel-button">いいえ</button>
                </div>
            </div>

            <!-- ここに検索結果の課題一覧またはエラーを表示 -->
            <table class="table table-bordered justify-content-center mb-5" id="walls_table">
                <?php
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        if (strlen($row['imagefile_h']) > 0 && file_exists($directories['wall_image'].$row['imagefile_t'])) {//画像ファイルが存在するものだけ
                            $src = $urlpaths['wall_image'].$row['imagefile_t'].'?'.rand();
                            $url = $urlpaths['wall_image'].$row['imagefile'];
                            $id = $row['id'];
                            $dts = date2str(strtotime($row['createdon'])) . ' ' . htmlspecialchars($row['name'], ENT_QUOTES);
                            $locations = '';
                            foreach (explode(',', $row['location']) as $value) {
                                if (strlen($value) > 0 && isset($walls[$value])) {
                                    if (strlen($locations) > 0) $locations .= '<br>';
                                    $locations .= $walls[$value];
                                }
                            }
                            echo <<<EOD
                                <tr>
                                    <td><input class="" type="checkbox" name="checked_wall[]" value="{$id}"><br>{$locations}</td>
                                    <td><img src="{$src}" alt="壁画像" data-url="{$url}" data-wallid="{$id}"><br>{$dts}</td>
                                </tr>

EOD;
                        }
                    }
                ?>
            </table>
        </form>

        <div class="modal fade" id="testModal2" tabindex="-1" role="dialog" aria-labelledby="basicModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <img class="rounded img-fluid d-block" alt="壁画像" id="preview2" data-wallid="">
                    </div>
                    <div class="modal-footer row">
                        <button type="button" class="btn btn-primary col-4 ml-auto" id="use-button">使用する</button>
                        <button type="button" class="btn btn-secondary col-3" data-dismiss="modal" id="item-close-button2">閉じる</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="testModal" tabindex="-1" role="dialog" aria-labelledby="basicModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <img class="rounded img-fluid d-block" alt="壁画像" id="preview" data-wallid="">
                    </div>
                    <div class="modal-footer row">
                        <select class="custom-select col-4" id="operation-select" name="operation-select" required>
                            <option value="" selected>メニュー</option>
                            <option value="edit">内容変更</option>
                            <option value="trim">トリミング</option>
                            <option value="delete">削除する</option>
                        </select>
                        <button type="button" class="btn btn-primary col-2" id="operation-button" disabled>実行</button>
                        <button type="button" class="btn btn-secondary col-3 ml-auto" data-dismiss="modal" id="item-close-button">閉じる</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/jquery.exif.js"></script>
    <script>$(function(){

        //ブラウザの戻るで戻ってきて同じ画像ファイルを選択したときにchangeイベントが
        //発生しないのを防ぐために、毎回クリアする
        $('#wall_picture').val('');

        //投稿画像のプレビュー
        $('#wall_picture').on('change', function (e) {
            if (e.target.files.length == 0) {
                //選択されなかった
                $('#wall_picture_label').html('壁写真を選択...');
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
                            $('#preview2').attr('src', img_b64).data('wallid', '');
                            $('#item-upload-button').show();
                            $('#item-trim-button').hide();
                            $('#item-predel-button').hide();
                            $('#item-delete-confirm').hide();
                            $('#testModal2').modal('show');
                        });
                    }
                    reader.readAsDataURL(e.target.files[0]);
                    var fileName = e.target.files[0].name;
                    $('#wall_picture_label').html(fileName);
                });
            }
        });

        //モーダル表示の選択画像をアップロード
        $('#item-upload-button').on('click', function () {
            //アップロード処理
            $('#mode').val('upload');
            $('button[name="submit"]').trigger('click');
        });

        //使用するボタンがクリックされた
        $('#use-button').on('click', function () {
            $('#mode').val('upload');
            $('button[name="submit"]').trigger('click');
        });

        //モーダルの閉じるボタン
        $('#item-close-button2').on('click', function () {
            //選択されなかった
            $('#wall_picture_label').html('壁写真を選択...');
            $('input[type=file]').val('');
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

        //画像をタッチしたらプレビュー
        $('#walls_table').on('click', 'img', function () {
            //画像情報を渡して表示
            $('#preview').attr('src', $(this).data('url') + '?' + Math.random()).data('wallid', $(this).data('wallid'));//壁IDを記憶
            $('#operation-select').val('');
            $('#operation-button').prop('disabled', true).removeClass('btn-danger').addClass('btn-primary');
            $('#testModal').modal('show');
        });

        //オペレーションが選択された
        $('#operation-select').on('change', function () {
            var op = $(this).val();
            //実行ボタンの属性を変更する
            if (op == '') {
                $('#operation-button').prop('disabled', true).removeClass('btn-danger').addClass('btn-primary');
            } else if (op == 'delete') {
                $('#operation-button').removeAttr('disabled').removeClass('btn-primary').addClass('btn-danger');
            } else {
                $('#operation-button').removeAttr('disabled').removeClass('btn-danger').addClass('btn-primary');
            }
        });

        $('#operation-button').on('click', function () {
            var op = $('#operation-select').val();
            if (op == 'edit') {
                //内容変更処理
                window.location = './UploadWall.php?wid=' + $('#preview').data('wallid');
            } else if (op == 'trim') {
                //トリミング処理
                $('#mode').val('trim');
                $('#wallid').val($('#preview').data('wallid'));
                $('button[name="submit"]').trigger('click');
            } else if (op == 'delete') {
                //削除処理
                $('#mode').val('delete');
                $('#wallid').val($('#preview').data('wallid'));
                $('button[name="submit"]').trigger('click');
            }
        });

        //モーダルの閉じるボタン
        $('#item-close-button').on('click', function () {
        });

        //チェックボックスが選択された
        $('#walls_table').on('change', 'input', function () {
            var c = $('input[name="checked_wall[]"]:checked').length;
            if (c == 1) {
                $('#select-menu').show();   //削除ボタンを表示
                $('#select-predel-button').show();
                $('#select-delete-confirm').hide();
            } else if (c == 0) {
                $('#select-menu').hide();   //削除ボタンを非表示
            }
        });

        //選択したものを削除する？
        $('#select-predel-button').on('click', function () {
            $('#select-predel-button').hide();
            $('#select-delete-confirm').show();
        });

        //選択したものを削除する
        $('#select-delete-button').on('click', function () {
            //削除処理
            $('#mode').val('delete');
            $('#wallid').val('-1');
            $('button[name="submit"]').trigger('click');
        });

        //選択したものを削除しない
        $('#select-cancel-button').on('click', function () {
            $('#select-predel-button').show();
            $('#select-delete-confirm').hide();
        });

    });</script>
</body>
</html>