<?php
// セッション開始
session_start();

require_once 'Env.php';

// ログインボタンが押された場合
if (isset($_POST["signup"], $_POST["userid"], $_POST["password"], $_POST["password2"])) {

    $userid = $_POST["userid"];
    $password = $_POST["password"];
    $password2 = $_POST["password2"];

    if (strlen($userid) < 3) {
        $errorMessage = 'ユーザーIDに3文字以上が必要です。';
    } else if ($userid != trim($userid)) {
        $errorMessage = 'ユーザーIDの前後に空白を含めることはできません。';
    }

    if (!isset($errorMessage)) {

        $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
        try {
            $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));

            //トランザクション開始
            $pdo->beginTransaction(); 

            try {
                //重複チェック
                $stmt = $pdo->prepare("SELECT `id` FROM `userdata` WHERE `name` = ?");
                $stmt->execute(array($userid));

                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    $errorMessage = 'このユーザーIDは既に使用されています';

                    //ロールバック
                    $pdo->rollBack();
                }
                else {

                    if (strlen($password) < 3) {
                        $errorMessage = 'パスワードに3文字以上が必要です。';
                    } else if(preg_match("/^[!-~]+$/", $password) != 1) {
                        $errorMessage = 'パスワードに使用できるのは半角英数字と記号です。';
                    } else if ($password != $password2) {
                        $errorMessage = '確認用パスワードが違います。';
                    }
                    if (isset($errorMessage)) {
                        //ロールバック
                        $pdo->rollBack();
                    } else {

                        //登録
                        $stmt = $pdo->prepare("INSERT INTO `userdata`(`active`, `name`, `password`) VALUES (?, ?, ?)");
                        $stmt->execute(array(1, $userid, password_hash($password, PASSWORD_DEFAULT)));  // パスワードのハッシュ化を行う
                        //$id = $pdo->lastinsertid();  // 登録した(DB側でauto_incrementした)IDを$useridに入れる

                        //コミット
                        $pdo->commit();

                        //登録で来たらログイン画面へ戻る
                        $msg = '登録が完了しました。あなたのユーザIDは "'.$userid.'" 、パスワードは "'.$password.'" です';
                        header('Location: Login.php?msg='.urlencode(base64_encode($msg)));

                        exit();
                    }
                }
            } catch (PDOException $e) {
                //ロールバック
                $pdo->rollBack();

                $errorMessage = $e->getMessage();  //デバッグ
            }
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();  //デバッグ
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/Signup.css">
    <title>新規登録</title>
</head>
<body>
    <div class = "container">
        <div class="wrapper mt-5 mb-5">
            <form action="#" method="post" name="Signup_Form" class="form-signup px-5 pt-4 pb-5 mx-auto" enctype="multipart/form-data">
                <h3 class="text-center mb-4">新規登録</h3>
                <hr class="colorgraph"><br>

                <?php
                    if (isset($errorMessage)) {
                        //エラー
                        $msg = htmlspecialchars($errorMessage, ENT_QUOTES);
                        echo <<<EOD
                            <div class="alert alert-warning" role="alert">
                                <strong>エラー！</strong> {$msg}
                            </div>

EOD;
                    } else if (isset($signUpMessage)) {
                        //成功
                        $msg = htmlspecialchars($signUpMessage, ENT_QUOTES);
                        echo <<<EOD
                            <div class="alert alert-success" role="alert">
                                <strong>成功</strong> {$msg}
                            </div>

EOD;
                    }
                ?>

                <div class="form-group">
                    <label for="userid">ユーザーID:</label>
                    <input type="text" class="form-control" name="userid" id="userid" minlength="3" autocomplete="off" required autofocus value="<?php if (!empty($userid)) {echo htmlspecialchars($userid, ENT_QUOTES);} ?>" />
                </div>
                <div class="form-group">
                    <label for="password">パスワード:</label>
                    <input type="password" class="form-control" name="password" id="password" minlength="3" autocomplete="off" required value="<?php if (!empty($password)) {echo htmlspecialchars($password, ENT_QUOTES);} ?>" />
                </div>
                <div class="form-group">
                    <label for="password2">パスワード（確認用）:</label>
                    <input type="password" class="form-control" name="password2" id="password2" minlength="3" autocomplete="off" required value="<?php if (!empty($password2)) {echo htmlspecialchars($password2, ENT_QUOTES);} ?>" />
                </div>
                <div class="text-info mb-5"><small>ユーザーID、パスワードに3文字以上必要です</small></div>

                <button class="btn btn-lg btn-primary btn-block" name="signup" value="新規登録" type="submit">登録する</button>
            </form>
        </div>

        <!-- このページのショートカットを表示 -->
        <hr>
        <div class="clearfix mb-2">
            <div class="float-left">
                <?php
                    $path = $baseurl.basename(__FILE__);
                ?>
                <img width="100%" src="qrgen.php?text=<?php echo urlencode($path); ?>" alt="ショートカット">
            </div>
            <h5>このページへのショートカット</h5>
            <small><?php echo htmlspecialchars($path, ENT_QUOTES); ?></small>
        </div>
    </div>

    <script type="text/javascript" src="./js/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script>
        $(function(){

        });
    </script>
</body>
</html>
