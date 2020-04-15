<?php
session_start();

// ログイン状態チェック
if (!isset($_SESSION["NAME"])) {

    header('Location: Login.php');
    exit;
}

require_once 'Env.php';

if (isset($_POST['submit1'], $_POST['password'], $_POST['password2'])) {
    $password = $_POST["password"];
    $password2 = $_POST["password2"];

    if (strlen($password) < 3) {
        $errorMessage = 'パスワードに3文字以上が必要です。';
    } else if ($password != trim($password)) {
        $errorMessage = 'パスワードの前後に空白を含めることはできません。';
    } else if ($password != $password2) {
        $errorMessage = '確認用パスワードが違います。';
    } else if(preg_match("/^[!-~]+$/", $password) != 1) {
        $errorMessage = 'パスワードに使用できるのは半角英数字と記号です。';
    } else {
        try {
            //更新
            $stmt = $pdo->prepare("UPDATE `userdata` SET `password` = ? WHERE `id` = ?");
            $stmt->execute(array(password_hash($password, PASSWORD_DEFAULT), $_SESSION["ID"]));  // パスワードのハッシュ化を行う

            //登録で来たらログイン画面へ戻る
            $_SESSION = array();  //ログアウト
            $msg = '変更しました。あなたのパスワードは "'.$password.'" です';
            header('Location: Login.php?msg='.urlencode(base64_encode($msg)));
            exit();

        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();  //デバッグ
        }
    }
} else if (isset($_POST['submit2'], $_POST['dispname'])) {

    $dispname = trim($_POST['dispname']);

    $favgrades = '';
    if (isset($_POST['favoritegrades'])) {
        foreach($_POST['favoritegrades'] as $value) {
            $favgrades .= ',' . $value;
        }
        if (strlen($favgrades) > 0) $favgrades .= ',';
    }

    try {
        $ar = array();
        $sql = "UPDATE `userdata` SET ";
        if (strlen($dispname) > 0) {
            $ar[] = $dispname;
            $sql .= "`dispname` = ? ";
        } else {
            $sql .= "`dispname` = NULL ";
        }
        if (strlen($favgrades) > 0) {
            $ar[] = $favgrades;
            $sql .= ", `favoritegrades` = ? ";
        } else {
            $sql .= ", `favoritegrades` = NULL ";
        }
        $sql .= " WHERE `id` = ?";
        $ar[] = intval($_SESSION["ID"]);

        //更新
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ar);

        //$message = '設定を変更しました';

        //登録で来たらログイン画面へ戻る（表示名を反映させるため）
        $_SESSION = array();  //ログアウト
        $msg = '設定を変更しました';
        header('Location: Login.php?msg='.urlencode(base64_encode($msg)));
        exit();

    } catch (PDOException $e) {
        $errorMessage = $e->getMessage();  //デバッグ
    }
}

//エラーがあっても表示用に読み込む
try {

    //同一名を探す
    $stmt = $pdo->prepare('SELECT * FROM `userdata` WHERE `id` = ?');
    $stmt->execute(array($_SESSION["ID"]));

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    } else {
        if (!isset($errorMessage)) $errorMessage = '無効なアカウントです';
        $exit = true;
    }
} catch (PDOException $e) {
    if (!isset($errorMessage)) {
        $errorMessage = 'データベースエラー';
        echo $e->getMessage();  //デバッグ
    }
    $exit = true;
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <title>アカウントの設定</title>
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

EOD;
                if (isset($exit)) {
                    echo '</div></body></html>';
                    exit;
                }
            } else if (isset($message)) {
                echo <<<EOD
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <strong>成功！</strong> {$message}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

EOD;
            }
        ?>

        <h3>アカウントの設定</h3>
        <!-- ID名 -->
        <div class="row bg-light mx-1 mb-5">
            <div class="col-4 px-3 py-2">ユーザーID</div>
            <div class="col-8 px-3 py-2 border-left"><?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?></div>
        </div>

        <form action="#" method="post" id="form1" enctype="multipart/form-data">
            <h4 class="">パスワードの変更</h4>

            <!-- パスワード -->
            <div class="row bg-light mx-1 mb-2">
                <div class="col-4  px-3 py-2">パスワード</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-group">
                        <div class="input-group">
                            <input type="text" class="form-control" name="password" minlength="3" maxlength="20" autocomplete="off" required>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row bg-light mx-1 mb-2">
                <div class="col-4  px-3 py-2">パスワード<br>（確認用）</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-group">
                        <div class="input-group">
                            <input type="text" class="form-control" name="password2" minlength="3" maxlength="20" autocomplete="off" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 変更ボタン -->
            <div class="col-12 clearfix mx-1 mb-5">
                <div class="float-right">
                    <button class="btn btn-primary align-middle" type="submit" name="submit1">パスワードを変更する</button>
                </div>
            </div>
        </form>

        <form action="#" method="post" id="form2" enctype="multipart/form-data">
            <h4 class="">設定の変更</h4>

            <!-- 表示名 -->
            <div class="row bg-light mx-1">
                <div class="col-4  px-3 py-2">表示名</div>
                <div class="col-8 px-3 py-2 border-left">
                    <div class="form-group">
                        <div class="input-group">
                            <input type="text" class="form-control" name="dispname" maxlength="20" value="<?php if (!is_null($row['dispname'])) echo htmlspecialchars($row['dispname'], ENT_QUOTES); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-left text-info mx-1 mb-4">投稿者の表示などに使用されます。設定されない場合はユーザーIDが使用されます。</div>

            <!-- グレード -->
            <div class="row bg-light mx-1">
                <div class="col-4  px-3 py-2">グレード</div>
                <div class="col-8 px-3 py-2 border-left">
                    <?php
                        foreach ($grades as $value => $name) {
                            $checked = (!is_null($row['favoritegrades']) && strpos($row['favoritegrades'], ','.$value.',') !== FALSE) ? 'checked' : '';
                            echo <<<EOD
                                <label class="checkbox-inline pl-2 py-1">
                                    <input type="checkbox" name="favoritegrades[]" value="{$value}" {$checked} >{$name}
                                </label>

EOD;
                        }
                    ?>
                </div>
            </div>
            <div class="text-left text-info mx-1 mb-4">自分の登るグレードにチェックを入れます（複数可）。最新課題の表示や、課題検索のデフォルト値として使用されます。</div>

            <!-- 変更ボタン -->
            <div class="col-12 clearfix mx-1 mb-4">
                <div class="float-right">
                    <button class="btn btn-primary align-middle" type="submit" name="submit2">設定をを変更する</button>
                </div>
            </div>
        </form>
    </div>
<!--
    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/jquery.cookie.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script>$(function(){


    });</script>
-->
</body>
</html>
