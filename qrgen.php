<?php
//GETパラメータのtextに設定された内容でＱＲコードを生成します

if (isset($_GET['text'])) {
    include "./phpqrcode-master/qrlib.php";    
    $enc = QRencode::factory('L', 3);
    header('Content-Type: image/png');
    $enc->encodePNG($_GET['text']);
}
?>