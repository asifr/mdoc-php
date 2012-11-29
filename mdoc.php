<?php
if (!defined('BASEPATH'))
	define('BASEPATH', ((function_exists('realpath') && @realpath(dirname(__FILE__)) !== FALSE)?realpath(dirname(__FILE__)):basename(dirname(__FILE__))).'/');

$base_url_guess = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://').preg_replace('/:80$/', '', $_SERVER['HTTP_HOST']).str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if (substr($base_url_guess, -1) == '/')
	$base_url_guess = substr($base_url_guess, 0, -1);
$base_url = trim($base_url_guess,'/').'/';

define('ASSETS_DIR', BASEPATH.'files/');
$assets_url = $base_url.'files/';
$docs_url = $base_url.'docs/';

$error = array(); $success = array();

if (get_magic_quotes_runtime())
	@ini_set('magic_quotes_runtime', false);
if (get_magic_quotes_gpc()) {
	function stripslashes_array($array) {
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}
	$_GET = stripslashes_array($_GET);
	$_POST = stripslashes_array($_POST);
	$_COOKIE = stripslashes_array($_COOKIE);
}
if (!isset($_SESSION['ready'])) {
	session_start();
	$_SESSION['ready'] = true;
	if (isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] != '')
		$csrf_token = $_SESSION['csrf_token'];
	else
		$csrf_token = set_csrf_token();
}

function set_csrf_token() {
	return $_SESSION['csrf_token'] = $_GLOBALS['csrf_token'] = md5(str_shuffle(chr(mt_rand(32, 126)) . uniqid() . microtime(TRUE)));
}
function token_validated($action) {
	global $csrf_token;
	if (empty($_POST) || !isset($_POST['action']) || (isset($_POST['action']) && $_POST['action'] != $action))
		return false;
	if (isset($_POST['token']) && $_POST['token'] != '' && $_POST['token'] == $csrf_token) {
		set_csrf_token();
		return true;
	}
	return false;
}
function tidy_file_name($filename)
{
	$s	= strtolower($filename);
	$s	= preg_replace('/[^a-z0-9\s.]/', '', $s);
	$s	= trim($s);
	$s	= preg_replace('/\s+/', '-', $s);
	if (strlen($s) > 0) {
		return $s;
	} else {
		$md5	= md5($filename);
		$s		= strtolower($md5);
		return 'ra-'.substr($s, 0, 4).'-'.substr($s, 5, 4);
	}
}

function href($title, $link) {
	return "<a href=\"{$link}\">{$title}</a>";
}

function ul_list($data = array(), $class = '')
{
	$class = ($class != null)?" class=\"{$class}\"":'';
	$render = "<ul{$class}>\r";
	if (isset($data) && !is_object($data)) {
		foreach ($data as $key => $value) {
			if (is_object($value))
				$value = (array) $value;
			if (is_array($value) && !empty($value)) {
				$render .= ul_list($value);
			} else {
				$render .= "\t<li>{$value}</li>\r";
			}
		}
	}
	$render .= "</ul>\r";
	return $render;
}

function get_messages($class = 'msg', $messages = array())
{
	global $error, $success;
	if (!empty($messages))
		extract($messages);
	$o = (!empty($success))?ul_list($success, $class.' success'):'';
	$o .= (!empty($error))?ul_list($error, $class.' error'):'';
	return $o;
}

/* creates a compressed zip file */
function create_zip($files = array(),$destination = '',$overwrite = false) {
	//if the zip file already exists and overwrite is false, return false
	if(file_exists($destination) && !$overwrite) { return false; }
	//vars
	$valid_files = array();
	//if files were passed in...
	if(is_array($files)) {
		//cycle through each file
		foreach($files as $file) {
			//make sure the file exists
			if(file_exists($file)) {
				$valid_files[] = $file;
			}
		}
	}
	//if we have good files...
	if(count($valid_files)) {
		//create the archive
		$zip = new ZipArchive();
		if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
			return false;
		}
		//add the files
		foreach($valid_files as $file) {
			$zip->addFile($file,$file);
		}
		//debug
		//echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;

		//close the zip -- done!
		$zip->close();

		//check to make sure the file exists
		return file_exists($destination);
	} else {
		return false;
	}
}

if (count($_FILES) && $_FILES['attachments']['name'][0] != '' && token_validated('upload_file')) {
	$csrf_token = set_csrf_token();
	include(BASEPATH.'Docco.php');
	$files = array();
	foreach ($_FILES['attachments']['name'] as $num => $name) {
		$ext = strtolower(substr($name, strrpos($name, '.') + 1));
		$filename = $name;
		if (in_array($ext,array('m'))) {
			// Move the uploaded file
			$dst = ASSETS_DIR.$filename;
			move_uploaded_file($_FILES['attachments']['tmp_name'][$num], $dst);
			if (file_exists($dst)) {
				$docco = new Docco($dst,array(),array(
					'language'	=> 'matlab',
					'comment_chars'	=> '%'
				));
				$saved = $docco->save();
				$files[] = $docco->html_file;
				$success[] = href($filename,$base_url.$docco->html_file).' was successfully converted.';
			}
		}
	}
	$zipname = 'docs_'.strftime("%Y%m%d_%H_%M",time()).'.zip';
	create_zip($files,BASEPATH.'docs/'.$zipname);
	$success[] = href('Download zipped archive of converted files.',$docs_url.$zipname);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<title>mdoc - A better MATLAB Documentation Generator</title>
	<style type="text/css" media="screen">
		.clearfix:before,.clearfix:after{content:"";display:table;}
		.clearfix:after{clear:both;}
		.clearfix{zoom:1;}
		.container{width:940px;margin-left:auto;margin-right:auto;*zoom:1;}.container:before,.container:after{display:table;content:"";}.container:after{clear:both;}
		.row{margin-left:-20px;*zoom:1;}.row:before,.row:after{display:table;content:"";}.row:after{clear:both;}[class*="span"]{float:left;margin-left:20px;}
		.span1{width:60px;}.span2{width:140px;}.span3{width:220px;}.span4{width:300px;}.span5{width:380px;}.span6{width:460px;}.span7{width:540px;}.span8{width:620px;}.span9{width:700px;}.span10{width:780px;}.span11{width:860px;}.span12{width:940px;}
		.offset1{margin-left:100px;}.offset2{margin-left:180px;}.offset3{margin-left:260px;}.offset4{margin-left:340px;}.offset5{margin-left:420px;}.offset6{margin-left:500px;}.offset7{margin-left:580px;}.offset8{margin-left:660px;}.offset9{margin-left:740px;}.offset10{margin-left:820px;}.offset11{margin-left:900px;}
		.pull-right,.right{float:right;}.pull-left,.left{float:left;}
		h1,h2,h3,h4,h5,h6{margin:10px 0;font-family:inherit;font-weight:normal;line-height:1;color:#333;text-rendering:optimizelegibility;}h1 small,h2 small,h3 small,h4 small,h5 small,h6 small{font-weight:normal;line-height:1;color:#999999;}
		h1{font-size:36px;line-height:40px;}h2{font-size:30px;line-height:30px;}h3{font-size:24px;line-height:30px;}h4{font-size:18px;line-height:20px;}h5{font-size:14px;line-height:20px;}h6{font-size:12px;line-height:20px;}
		body{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:13px;line-height:18px;margin:0;padding:0;color:#646464;}
		a{color:#39C;text-decoration:none;}a:hover{color:#287DA8;text-decoration:none;}
		#attachments-container{border:1px dashed #B3B3B3;margin:0;padding:10px;border-radius:5px;font-size:12px;line-height:16px;}
		#add-file{font-size:11px;}
		.container{margin-top:40px;}
		ul.success{margin-left:0;padding:10px;background:#ECF2FF;list-style:none;}ul.success li{display:block;}ul.success a{}
		.small{font-size:11px;color:#999;}
	</style>
</head>
<body>
	<div class="container">
		<div class="row">
			<div class="span4">
				<h3>mdoc</h3>
				<h4>MATLAB documentation generator</h4>
				<p>Literate programming is a method of crafting software that places emphasis on the way that people understand the problem a particular piece of code is attempting to solve, rather than the way that computers interpret the source code. This tool generates a literate-style documentation from MATLAB code. It produces HTML that displays your comments alongside your code.</p>
				<h4>Instructions</h4>
				<ol>
					<li>Upload a MATLAB m-file</li>
					<li>Download the HTML file</li>
				</ol>
				<h4>Usage Notes</h4>
				<p>The program will look for commented lines and use this to generate the documentation. However, note that comments inline with code won't be parsed because the philosophy guiding literate programming is the realization that the documentation describes the chunk of code.</p>
				<p>Documentation is formatted using Markdown (see below) but HTML is also allowed in the comments. LaTeX should be wrapped in <code>$$</code> tags: e.g. <code>$$\frac{dV}{dt}$$</code></p>
				<h5>Markdown formatting</h5>
				<pre><code>**bold**	*italic*
[Link](http://google.com)
- Unordered list
1. Ordered list
# Header 1
## Header 2
</code></pre>
			</div>
			<div class="span8">
				<?php echo get_messages(); ?>
				<form action="" method="post" accept-charset="utf-8" enctype="multipart/form-data">
					<input type="hidden" name="action" value="upload_file">
					<input type="hidden" name="token" value="<?php echo $csrf_token; ?>">
					<div id="attachments-container">
						<span id="add-file-container"><a href="#" id="add-file">Add another file</a></span>
						<div id="attachment-inputs">
							<p><input type="file" name="attachments[]"></p>
						</div>
					</div>
					<p><input type="submit" value="Generate documentation"></p>
				</form>
				<p><center><img src="<?php echo $base_url; ?>example.png"></center></p>
			</div>
		</div>
	</div>
	<div class="container">
		<div class="row">
			<div class="span12">
				<p class="small">By <a href="http://neuralengr.com/members/asif-rahman">Asif Rahman</a></p>
			</div>
		</div>
	</div>
</body>

<script type="text/javascript" charset="utf-8">
	function toggle(el) {
		el.style.display = (el.style.display != 'none' ? 'none' : '' );
	}

	// To cover IE 5 Mac lack of the push method
	Array.prototype.push = function(value) {this[this.length] = value; };

	function addEvent(obj, type, fn) {
		if (obj.addEventListener) {
			obj.addEventListener( type, fn, false );
			EventCache.add(obj, type, fn);
		} else if (obj.attachEvent) {
			obj["e"+type+fn] = fn;
			obj[type+fn] = function() { obj["e"+type+fn]( window.event ); }
			obj.attachEvent( "on"+type, obj[type+fn] );
			EventCache.add(obj, type, fn);
		} else {
			obj["on"+type] = obj["e"+type+fn];
		}
	}
	var EventCache = function() {
		var listEvents = [];
		return {
			listEvents : listEvents,
			add : function(node, sEventName, fHandler){
				listEvents.push(arguments);
			},
			flush : function(){
				var i, item;
				for(i = listEvents.length - 1; i >= 0; i = i - 1){
					item = listEvents[i];
					if(item[0].removeEventListener){
						item[0].removeEventListener(item[1], item[2], item[3]);
					};
					if(item[1].substring(0, 2) != "on"){
						item[1] = "on" + item[1];
					};
					if(item[0].detachEvent){
						item[0].detachEvent(item[1], item[2]);
					};
					item[0][item[1]] = null;
				};
			}
		};
	}();

	addEvent(window,'unload',EventCache.flush);

	addEvent(document.getElementById('add-file'),'click',function(evt){
		evt.preventDefault();
		inp = document.createElement("input");
		inp.name = 'attachments[]';
		inp.type = 'file';
		p = document.createElement("p");
		p.appendChild(inp);
		document.getElementById('attachment-inputs').appendChild(p);
		return false;
	});
</script>
</html>