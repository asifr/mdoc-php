<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="content-type" content="text/html;charset=utf-8">
  <title><?php echo $title; ?></title>
  <link rel="stylesheet" href="http://neuralengr.com/docco.css">
  <script type="text/javascript" src="http://cdn.mathjax.org/mathjax/latest/MathJax.js?config=TeX-AMS-MML_HTMLorMML"></script>
</head>
<body>
<div id='container'>
  <div id="background"></div>
  <table cellspacing="0" cellpadding="0">
  <thead>
    <tr>
      <th class="docs"><h1><?php echo $title; ?></h1></th>
      <th class="code"></th>
    </tr>
  </thead>
  <tbody>
	<?php foreach ($docs as $num => $doc): ?>
	    <tr id='section-<?php echo $num; ?>'>
	      <td class="docs">
	        <div class="octowrap">
	          <a class="octothorpe" href="#section-<?php echo $num; ?>">#</a>
	        </div>
	        <?php echo $doc; ?>
	      </td>
	      <td class="code">
	        <div class='highlight'><pre><?php echo $code[$num]; ?></pre></div>
	      </td>
	    </tr>
	<?php endforeach; ?>
  </table>
</div>
</body>