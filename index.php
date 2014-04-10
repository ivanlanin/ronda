<?php
/**
 * 2011-03-07 10:45
 */
include_once('class.ronda.php');
$version = '0.0.06';
$ronda = new ronda();
$ronda->process($_GET);
$ret = $ronda->html();
$TITLE = $ronda->title;
$CONTENT = $ret;
$ONLOAD = $ronda->onload;
$JQUERY = $ronda->jquery;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="id" dir="ltr" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php echo($TITLE); ?></title>
<link type="text/css" rel="stylesheet" href="style.css" />
<script type="text/javascript" src="script.js"></script>
<script type="text/javascript" src="jquery.min.js"></script>
<script type="text/javascript">
$(document).ready(function(){
<?php echo($JQUERY);?>
});
</script>
<?php echo($HEADER);?>
</head>
<body<?php echo($ONLOAD);?>>
<?php echo($CONTENT);?>
<div id="footer"><a href="http://code.google.com/p/ronda/"><strong>ronda</strong></a> <?php echo($version); ?> |
<a href="README.TXT">README</a></div>
</body>
</html>