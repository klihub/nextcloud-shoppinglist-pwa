<?php
declare(strict_types=1);

$manifestUrl = \\OC::$server->getURLGenerator()->linkTo('shoppinglist', 'manifest.webmanifest');
?>
<link rel="manifest" href="<?php p($manifestUrl); ?>">
<meta name="theme-color" content="#2878bd">
<div id="shoppinglist" class="section"></div>
