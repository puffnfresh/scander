<?php

// change php.ini settings
ini_set('memory_limit', '1024M'); // 1GB
ini_set('max_execution_time', '3600'); // 1 hour
ini_set('register_globals', 'Off');

DEFINE('MQ', ini_get('magic_quotes_gpc'));

// generic functions
function gpc($str) {
	if (MQ) return stripslashes($str);
	return $str;
}
function html($str) {
	return htmlentities($str);
}
function url($str) {
	return $str;
}

// set up some variables
$subject = isset($_GET['s']) ? realpath(gpc($_GET['s'])) : realpath(dirname($_SERVER['SCRIPT_FILENAME']));
$upDir = realpath($subject . "/..");
$action = isset($_GET['action']) ? gpc($_GET['action']) : 'dir';
$value = isset($_POST['v']) ? gpc($_POST['v']) : false;


function printDir($dir) {
	if (is_file($dir)) { // a file was supplied as a directory
		editFile($dir);
		return;
	}
	$entries = @scandir(realpath($dir));
	
	if ($entries === false) {
		echo '<strong>Error:</strong> Cannot open directory.';
		return;
	}
	if (count($entries) <= 2) {
		echo 'Empty directory';
		return;
	}
	
	echo '
<table class="data">
<th>&nbsp;</th><th>Name</th><th>Bytes</th><th>Changed</th><th>&nbsp;</th>';
	
	foreach ($entries as $z => $i) {
		if (preg_match('/^\.+$/', $i)) continue;
		
		$path = realpath("$dir/$i");
		$shade = $z % 2 ? '' : ' class=\'shaded\'';
		$change = date('j/m/Y g:ia', filectime($path));
		
		if (is_dir($path)) {
			echo "
<tr$shade id=\"row$z\">
	<td><a class=\"icon\" href=\"?action=dir&s=".html($path)."\"><font size=\"4\" face=\"Wingdings\">0</font></a></td>
	<td><a href=\"?action=dir&s=".html($path)."\">$i</a></td>
	<td>&nbsp;</td>
	<td>$change</td>
	<td id=\"actions\"><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"del('".addslashes(url($path))."', '".url($i)."', true, document.getElementById('row$z'))\"?>û</a></font></td><td>&nbsp;</td>
</tr>
";
		}
		else {
			$size = number_format(filesize($path), 0, '.', ',');
			echo "
<tr$shade id=\"row$z\">
	<td><a class=\"icon\" href=\"?action=dl&s=".html($path)."\"><font size=\"4\" face=\"Wingdings\">2</font></a></td>
	<td><a href=\"?action=dl&s=".html($path)."\">$i</a></td>
	<td>$size</td>
	<td>$change</td>
	<td><font face=\"Wingdings\" size=\"4\"><a href=\"javascript://\" class=\"icon\" onclick=\"del('".addslashes(url($path))."', '".url($i)."', false, document.getElementById('row$z'))\"?>û</a></font></td><td><font face=\"Webdings\" size=\"4\"><a href=\"?action=edit&s=".url($path)."\" class=\"icon\">¥</a></font></td>
</tr>
";
		}
	}
	
	echo '</table>';
}

function downloadFile($file) {
	$filename = basename($file);
	header("Content-Disposition: attachment; filename=\"$filename\"");
	echo file_get_contents($subject);
	die;
}

function editFile($filename) {
	$filename = realpath($filename);
	if (!is_file($filename)) {
		echo '<strong>Error:</strong> Not a file or doesn\'t exist';
		return;
	}
	
	$name = basename($filename);
	$file = file_get_contents($filename);
	
	echo "
<h2>".html($name)."</h2>

<textarea id=\"v\" cols=\"90\" rows=\"25\" onchange=\"setSaveStatus('Unsaved')\">".html($file)."</textarea><br />
<input type=\"button\" onclick=\"save('".html(addslashes($filename))."', document.getElementById('v').value);\" value=\"Save\" style=\"font-weight: bold\" /> <input type=\"button\" value=\"Exit\" onclick=\"history.go(-1);\" /> <span id=\"saveStatus\"></span>
";
}

function saveFile($filename, $contents) {
	echo file_put_contents($filename, $contents) !== false;
	die;
}

function deleteSubject($subject) {
	if (is_file($subject)) {
		echo @unlink($subject);
	}
	else if (is_dir($subject)) {
		echo @rmdir($subject);
	}
	else echo false;
	die;
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>Scander</title>
<style type="text/css">
body, td {
	font: 80% sans-serif;
}
a {
	text-decoration: none;
}
h2 {
	margin-bottom: 0;
}

table.data tr.shaded {
	background-color: #EEE;
}
table.data th {
	text-align: left;
}
table.data td {
	padding: 0 0.8em;
	margin-right: 2em;
}
table.data td a {
	display: block;
}
table.data td a:hover {
	color: #77F;
}

.icon {
	text-decoration: none;
	color: black;
}

#nav {
	font-size: 180%;
	margin-bottom: 0.2em;
}
#nav a {
	text-decoration: none;
	color: black;
	background-color: #EEE;
	border: 1px solid #CCC;
	padding: 0 0.3em;
}
#nav a:hover {
	color: #555;
	background-color: #FFF;
}
</style>

<script type="text/javascript">

function newXMLHTTP() {
	try {
		return new XMLHttpRequest();
	}
	catch (e) {
		try {
			return new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e) {
			try {
				return new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch (e) {
				return false;
			}
		}
	}
}

function del(path, name, isDir, row) {
	var question = isDir ? "Are you sure you would like to delete the folder \"" + name + "\" and all of its contents?" : "Are you sure you would like to delete the file \"" + name + "\"?";
	if (confirm(question)) {
		var ajax = newXMLHTTP();
		ajax.onreadystatechange = function() {
			if (ajax.readyState == 4) {
				if (ajax.responseText == "1") {
					row.style.backgroundColor = "red";
				}
				else {
					alert("Error deleting \"" + name + "\"! The file/folder may no longer exist or is still in use.");
					row.style.backgroundColor = "orange";
				}
			}
		}
		ajax.open("GET", "?action=del&s=" + escape(path), true);
		ajax.send(null);
	}
	else {
		return false;
	}
}

function save(file, value) {
	var ajax = newXMLHTTP();
	var params = "v=" + escape(value);
	var saveStatus = document.getElementById("saveStatus");
	
	saveStatus.innerHTML = "Saving...";
	ajax.onreadystatechange = function() {
		if (ajax.readyState == 4) {
			if (ajax.responseText == "1") {
				setSaveStatus("Saved");
			}
			else {
				setSaveStatus("Save failed");
			}
		}
	}
	ajax.open("POST", "?action=save&s=" + escape(file), true);
	ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	ajax.setRequestHeader("Content-length", params.length);
	ajax.setRequestHeader("Connection", "close");
	ajax.send(params);
}

function setSaveStatus(status) {
	saveStatus.innerHTML = status;
}

</script>
</head>

<body>
<h1>Scander</h1>

<form method="get">
	<input type="hidden" name="action" value="dir" />
	<input type="text" size="80" name="s" value="<?php echo html($subject); ?>" /> <input type="submit" value="Go" />
</form>

<div id="nav">
	<a href="javascript:history.go(-1)"><font face="Wingdings">ï</font></a>
	<a href="javascript:history.go(1)"><font face="Wingdings">ð</font></a>
	<a href="?action=dir&s=<?php echo $upDir; ?>"><font face="Wingdings">ñ</font></a>
	<a href="javascript://" onclick="location.reload(true)"><font face="Webdings">q</font></a>
	<a href="<?php echo $_SERVER['PHP_SELF']; ?>"><font face="Webdings">H</font></a>
	&nbsp;<span style="border-left: 1px solid #CCC; margin">&nbsp;</span>
	<a href="javascript://"><font face="Wingdings">2</font></a>
	
</div>
<br />

<?php

// determine action
switch ($action) {
	case 'del':
		ob_clean();
		deleteSubject($subject);
		break;
	case 'dl':
		ob_clean();
		downloadFile($subject);
		break;
	case 'edit':
		editFile($subject);
		break;
	case 'save':
		ob_clean();
		saveFile($subject, $value);
		break;
	case 'dir':
		printDir($subject);
		break;
	default:
		printDir($subject);
		break;	
}

?>
</body>
</html>