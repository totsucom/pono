<?php
//require 'password.php';   // password_verfy()はphp 5.5.0以降の関数のため、バージョンが古くて使えない場合に使用
// セッション開始
session_start();

require_once 'Env.php';

//ログインボタンが押された場合
if (isset($_POST["login"])) {
    
    //ユーザIDの入力チェック
    if (empty($_POST["userid"])) {  // emptyは値が空のとき
        $errorMessage = 'ユーザーIDが未入力です。';
    } else if (empty($_POST["password"])) {
        $errorMessage = 'パスワードが未入力です。';
    }

    if (!empty($_POST["userid"]) && !empty($_POST["password"])) {
        //入力したユーザIDを格納
        $userid = $_POST["userid"];

        //ユーザIDとパスワードが入力されていたら認証する
        $dsn = sprintf('mysql: host=%s; dbname=%s; charset=utf8', $db['host'], $db['dbname']);
        try {
            $pdo = new PDO($dsn, $db['user'], $db['pass'], array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));

            //同一名を探す
            $stmt = $pdo->prepare('SELECT * FROM `userdata` WHERE `name` = ? AND `active` = 1');
            $stmt->execute(array($userid));

            $password = $_POST["password"];

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                //パスワードを比較する
                if (password_verify($password, $row['password'])) {
                    session_regenerate_id(true);

                    // 入力したIDのユーザー名を取得
                    $id = $row['id'];
                    $sql = "SELECT * FROM `userdata` WHERE `id` = $id AND `active` = 1";  //入力したIDからユーザー名を取得

                    $stmt = $pdo->query($sql);
                    foreach ($stmt as $row) {
                        $row['name'];  // ユーザー名
                    }

                    //ログイン結果をセッション変数に保存
                    $_SESSION["ID"] = $id;
                    $_SESSION["NAME"] = (is_null($row['dispname']) || $row['dispname'] == "") ? $row['name'] : $row['dispname'];
                    $_SESSION["GRADES"] = is_null($row['favoritegrades']) ? '' : $row['favoritegrades'];
                    $_SESSION["MASTER"] = ($row['master'] == 1);

                    if (isset($_POST['backto'])) {
                        header("Location: ".$_POST['backto']);  //行き先が設定されている
                    } else {
                        header("Location: Main.php");  // メイン画面へ遷移
                    }

                    exit();  // 処理終了
                } else {
                    // 認証失敗
                    $errorMessage = 'ユーザーIDあるいはパスワードに誤りがあります。';
                }
            } else {
                // 4. 認証成功なら、セッションIDを新規に発行する
                // 該当データなし
                $errorMessage = 'ユーザーIDあるいはパスワードに誤りがあります。';
            }
        } catch (PDOException $e) {
            $errorMessage = 'データベースエラー';
            
            //$errorMessage = $sql;
            echo $e->getMessage();  //デバッグ
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
    <link rel="stylesheet" href="./css/Login.css">
    <title>課題ログイン</title>
</head>
<body>
    <div class = "container">
        <div class="wrapper mt-5 mb-2">

            <?php
                if (isset($_GET['msg'])) {
                    //GETパラメータで渡されたメッセージを表示
                    $msg = htmlspecialchars(base64_decode($_GET['msg']), ENT_QUOTES);
                    echo <<<EOD
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>成功！</strong> {$msg}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>                

EOD;
                }
            ?>

            <form action="" method="post" name="Login_Form" id="Login_Form" class="form-signin px-5 pt-4 pb-5 mx-auto" enctype="multipart/form-data">
                <h3 class="text-center mb-4">課題ログイン</h3>
                <hr class="colorgraph"><br>

                <input type="text" class="form-control" name="userid" id="userid" placeholder="ユーザーID" required="" autofocus="" value="<?php if (!empty($_POST["userid"])) echo htmlspecialchars($_POST["userid"], ENT_QUOTES); ?>" />
                <input type="password" class="form-control" name="password" id="password" placeholder="パスワード" required=""/>

                <button class="btn btn-lg btn-primary btn-block" name="login" id="login" value="ログイン" type="submit">ログイン</button>
                <?php
                    if (isset($errorMessage)) {
                        echo '<div class="alert alert-warning" role="alert">', htmlspecialchars($errorMessage, ENT_QUOTES), "</div>\r\n";
                    }
                    if (isset($_GET['back'])) {
                        //ログイン後のジャンプ先を記憶
                        echo '<input type="hidden" name="backto" value="', htmlspecialchars($_GET['back'], ENT_QUOTES), '" />',"\r\n";; 
                    }
                ?>
            </form>
        </div>
        <div class="wrapper mb-5">
            <form action="SignUp.php" method="get" class="form-signup px-5 pt-4 pb-4 mx-auto" enctype="multipart/form-data">
                <button class="btn btn-lg btn-secondary btn-block" value="新規登録" type="submit">新規登録</button>
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
    <script type="text/javascript" src="./js/jquery.cookie.js"></script>
    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script> $(function(){
        $('#Login_Form').submit(function() {
            //ログイン時にjavascript側でやっておきたいこと

            $.removeCookie('cond_bak'); //検索条件をクリア

            return true;
        })
    }); </script>
</body>
</html>
