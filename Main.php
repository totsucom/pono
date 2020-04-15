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


//DBに接続して新しい課題を読み込む
$newProblem = [];
try {
    //気になるグレードを表示する条件
    $grade_cond = '';
    if ($_SESSION["GRADES"] != '') {
        $s = substr($_SESSION["GRADES"], 1, strlen($_SESSION["GRADES"]) - 2); //前後のカンマを取り除く
        $grade_cond = "AND `problem`.`grade` IN (${s})";
    }

    $disabled_wall = '';
    $params = [];
    if (count($disabledWalls) > 0) {
        //無効な壁
        $ar = [];
        foreach ($disabledWalls as $key => $value) {
            if (strlen($value) > 0) {
                $ar[] = "`problem`.`location` NOT LIKE ?";
                $params[] = '%,'.$key.',%';
            }
        }
        $disabled_wall = 'AND ('.implode(' AND ', $ar).')';
    }
    

    //problem.userid から userdata.name を取り出すために内部結合を行っている
    $sql =<<<EOD
SELECT
    `problem`.`id`,`problem`.`createdon`,`problem`.`title`,`problem`.`grade`,`problem`.`location`,`problem`.`imagefile_h`,
    `userdata`.`name`,`userdata`.`dispname`
FROM
    `problem`
INNER JOIN
    `userdata`
ON
    `problem`.`userid` = `userdata`.`id`
WHERE
    `problem`.`active` = 1 AND `problem`.`publish` = 1 {$grade_cond} {$disabled_wall}
ORDER BY
    `problem`.`id` DESC
LIMIT
    5
EOD;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    //$row[]に課題データを読み込む
    $i = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ar = [];
        $ar['index'] = $i++;
        $ar['href'] = './DisplayProblem.php?pid='.$row['id'];
        $ar['cap'] = '';
        if (strlen($row['title']) > 0) $ar['cap'] .= '"'.htmlspecialchars($row['title'], ENT_QUOTES).'" ';
        if (isset($grades[$row['grade']])) $ar['cap'] .= $grades[$row['grade']].' ';
        $c = '';
        foreach (explode(',', $row['location']) as $value) {
            if (strlen($value) > 0 && isset($walls[$value])) {
                $ar['cap'] .= $walls[$value];
                $c = ' ';
            }
        }
        $ar['cap'] .= $c;
        $ar['name'] = htmlspecialchars(is_null($row['dispname']) ? $row['name'] : $row['dispname'], ENT_QUOTES);
        $ar['src'] = $urlpaths['problem_image'].$row['imagefile_h'];
        $newProblem[] = $ar;
    }
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
    <link rel="stylesheet" href="./css/Main.css">
    <title>トップ</title>
</head>
<body>
    <nav class="navbar navbar-expand-sm navbar-light bg-light">
        <a href="#" class="navbar-brand">トップ</a>
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
        <div class="text-right">
            <?php
                echo 'ようこそ ';
                if ($_SESSION['MASTER']) {
                    echo '<img src="./img/key.png" alt="管理者">';
                }
                echo '<u>', htmlspecialchars($_SESSION["NAME"], ENT_QUOTES), '</u> さん';
            ?>
        </div>
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
        <?php if (count($newProblem) > 0) { ?>
            <div class="wrapper mb-5">
                <h3>投稿された課題</h3>
                <div id="example-3" class="carousel slide" data-ride="carousel">
                <ol class="carousel-indicators">
                    <?php
                        //スライドの構成
                        $c = 'class="active"';
                        $i = 0;
                        foreach ($newProblem as $problem) {
                            echo <<<EOD
                                <li data-target="#example-3" data-slide-to="{$i}" {$c}></li>

EOD;
                            $c = '';
                            $i++;
                        }
                    ?>
                </ol>
                <div class="carousel-inner">
                    <?php
                        //各スライドのイメージ
                        $c = 'active';
                        foreach ($newProblem as $problem) {
                            $r = rand();
                            echo <<<EOD
                                <div class="carousel-item {$c}">
                                    <img src="{$problem['src']}?{$r}" class="img-fluid headline" alt="{$problem['cap']}" data-url="{$problem['href']}" data-index="{$problem['index']}">
                                    <div class="carousel-caption">
                                        <h5>{$problem['cap']}</h5>
                                    </div>
                                </div>

EOD;
                            $c = '';
                        }
                    ?>
                    <!-- PREV/NEXTボタン -->
                    <a class="carousel-control-prev" href="#example-3" role="button" data-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="sr-only">Previous</span>
                    </a>
                    <a class="carousel-control-next" href="#example-3" role="button" data-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="sr-only">Next</span>
                    </a>
                </div>
            </div>
        <?php } ?>

        <div class="wrapper mb-5">
            <h3>メニュー</h3>
            <a href="./ProblemList.php">課題を探す</a><br>
            <a href="./SelectWalls.php">課題を投稿する</a><br>
        </div>

        <?php
            if ($_SESSION['MASTER']) {
                echo <<<EOD
                    <div class="wrapper mb-5">
                        <h3>管理者メニュー</h3>
                        <a href="./WallList.php">ベースの壁写真を管理</a><br>
                        <a href="./UpdateWall.php">壁の更新処理</a><br><br>
                        <a href="./PonoMaster.php">壁マスター</a><br>
                    </div>

EOD;
            }
        ?>

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
    <script>
        $(function(){
            $('img.headline').on('click', function(){
                //imgをaタグで囲うと幅がおかしくなったのでイベント処理にした

                //ヘッドラインの検索条件をcookieに設定
                <?php
                    $gs = '';
                    foreach ($grades as $value => $name) {
                        if (strlen($gs) > 0) $gs .= ',';
                        $gs .= (strpos($_SESSION["GRADES"], ',' . $value . ',') !== FALSE) ? '1' : '0';
                    }
                    $ls = str_repeat('0,', count($walls) - 1) . '0';
                ?>
                $.cookie('cond_bak', '{"grade":[<?php echo $gs; ?>],"location":[<?php echo $ls; ?>],"myresult":"all","myproblem":0,"postmonth":"all","postuser":"all","index":' + $(this).data('index') + '}');

                location.href = $(this).data('url');
            });
        });
    </script>
</body>
</html>