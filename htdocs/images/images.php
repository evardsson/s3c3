<?php
$imgs = array(
	'bootstrap_menu.png',
	'bootstrap1.png',
	'cc80x15.png',
	's3c3.png',
	'sample_scheme.png');
$str = '';
//$fh = fopen('imgout.html', 'w');
foreach($imgs as $img) {
	 $str .= '<img src="data:image/png;base64,' . base64_encode(file_get_contents($img)) . '" />'."\n";
}
file_put_contents('imgout.html', $str);
