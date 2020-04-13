<?php
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

if (isset($_POST['submit'],$_POST['walls'],$_POST['udate'])) {
    $dts = date('Y/m/d', strtotime($_POST['udate'])) . ' 23:59:59';
    try {
        $pdo->beginTransaction();
        try {
            //該当する課題を無効化
            $arparam = [];
            $arparam[] = $dts;
            $arcond = [];
            foreach ($_POST['walls'] as $value) {//指定した壁を含む課題を抽出する条件
                $arcond[] = '`location` LIKE ?';
                $arparam[] = '%,' . $value . ',%';
            }
            $sql = 'UPDATE `problem` SET `active` = 0 WHERE `active` = 1 AND `createdon` <= ? AND (' . implode(' OR ', $arcond) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($arparam);
            $message = $stmt->rowCount() . '件の課題が無効になりました';

            //壁の更新日を更新
            $arparam = [];
            $arparam[] = $dts;
            $arcond = [];
            foreach ($_POST['walls'] as $value) {//指定した壁を含む課題を抽出する条件
                $arcond[] = '?';
                $arparam[] = $value;
            }
            $sql = 'UPDATE `walls` SET `updatedate` = ? WHERE `active` = 1 AND `tag` IN (' . implode(',', $arcond) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($arparam);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo 'データベースエラー<br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
            exit();
        }
    } catch (PDOException $e) {
        echo 'トランザクションエラー<br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
        exit();
    }
}

try {
    $sql = "SELECT * FROM `walls` WHERE `active` = 1 ORDER BY `disporder`";
    $stmt = $pdo->query($sql);
} catch (PDOException $e) {
    echo 'データベースエラー<br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    exit();
}


?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/bootstrap-datepicker.min.css">
    <title>壁の更新</title>
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
            } else if (isset($message)) {
                $msg = htmlspecialchars($message, ENT_QUOTES);
                echo <<<EOD
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>成功！</strong> {$msg}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

EOD;
            }
        ?>
        <form action="#" method="post" id="form1" enctype="multipart/form-data">

            <h3 class="mb-3">壁の更新</h3>

            <div class="text-info">壁を更新することで、それまでに作成された課題を無効にします。</div>


            <table class="table table-bordered justify-content-center mb-5" id="walls_table">
                <tr>
                    <th class="text-center">選択</th>
                    <th class="text-center">名称</th>
                    <th>前回の更新</th>
                </tr>
                <?php
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $name = htmlspecialchars($row['name'], ENT_QUOTES);
                        $dts = is_null($row['updatedate']) ? '' : date('Y/m/d', strtotime($row['updatedate']));
                        echo <<<EOD
                            <tr>
                                <td class="text-center"><input type="checkbox" name="walls[]" value="{$row['tag']}" ></td>
                                <td class="text-center">{$name}</td>
                                <td>{$dts}</td>
                            </tr>

EOD;
                    }
                ?>
            </table>

            <!-- 更新日付 -->
            <div class="row bg-light mb-2">
                <div class="col-4 px-3 py-2">更新日</div>
                <div class="col-8 px-3 py-2 border-left">
                    <input type="text" class="form-control" name="udate" id="udate">
                </div>
            </div>
            <div class="text-info mb-3">指定された日付とそれ以前の課題が無効になります。</div>

            <!-- 投稿ボタン -->
            <div class="col-12 clearfix">
                <div class="float-right">
                    <button class="btn btn-primary align-middle" type="submit" name="submit" disabled>更新する</button>
                </div>
            </div>
        </form>
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap-datepicker.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap-datepicker.ja.min.js"></script>
    <script>$(function(){

        function init() {
            var today = new Date();
            $('#udate').val(today.getFullYear() + '/' + (today.getMonth() + 1) + '/' + today.getDate());

            var tomorrow = new Date();
            tomorrow.setDate(today.getDate() + 1);

            $('#udate').datepicker({
                format: 'yyyy/mm/dd',
                language:'ja',
                endDate: tomorrow,
                autoclose: true
            });
        }
        init();

        $('input[name="walls[]"]').on('change', function () {
            var c = $('input[name="walls[]"]:checked').length;
            if (c == 0) {
                $('button[name="submit"]').prop('disabled', true);
            } else {
                $('button[name="submit"]').removeAttr('disabled');
            }
        });

        //投稿前の処理
        $('#form1').submit(function(){
            if ($('input[name="walls[]"]:checked').length == 0) {
                return false;
            } else {
                return true;
            }
        });

    });</script>
</body>
</html>
