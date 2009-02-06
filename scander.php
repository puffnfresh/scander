<?php
class Scander {
    var $version = '2.0.0';

    var $ds = '/';
    var $current_dir = '';
    var $working_dir = '';
    var $file = '';
    var $action = '';

    var $code;

    var $magic_quotes = false;
    var $init_scripts = array();

    // Runs when initialized.
    function Scander() {
        if(!$this->checkCompatible()) {
            if($this->directRequest()) {
                echo $this->getHTML($this->getChecklist());
            }
            return;
        }

        $this->setup();

        if(!$this->directRequest()) return;

        // Just eval the code and exit.
        if(isset($this->code)) {
            echo eval($this->gpc($this->code));
            return;
        }

        // User just requested a page, show it to them.
        $content = '';
        switch($this->action) {
        case 'icons.png':
            echo $this->getIcons();
            return;
        case 'eval':
            $content = $this->getEval();
            break;
        case 'upload':
            $content = $this->getUpload();
            break;
        case 'new':
            $content = $this->getNew();
            break;
        case 'edit':
            $content = $this->getEdit();
            break;
        case 'download':
            if(is_file($this->file)) {
                if(function_exists("mime_content_type")) {
                    // Detect the mime-type.
                    header('Content-Type: ' . mime_content_type($this->file));
                } else {
                    // Just supply a bogus mime type so they can download it.
                    header('Content-Type: scander/download-file');
                }

                // Just to make the file download have the right name.
                $basename = basename($this->file);
                header('Content-Disposition: inline; '.
                       'filename="' . $basename . '"');

                echo file_get_contents($this->file);
                return;
            }
        case 'list':
        default:
            $content = $this->getList();
            break;
        }
        echo $this->getHTML($content);
    }

    // See if this is the executing script (and it isn't just included).
    static function directRequest() {
        $ds = DIRECTORY_SEPARATOR;
        $local_path = str_replace('/', $ds, $_SERVER['SCRIPT_FILENAME']);
        return __FILE__ == realpath($local_path);
    }

    // Sets up starting variables.
    function setup() {
        $this->ds = DIRECTORY_SEPARATOR;

        $this->current_dir = getcwd();
        if($_GET['dir']) {
            $this->working_dir = $_GET['dir'];
        } else {
            $this->working_dir = $this->current_dir;
        }

        $this->file = $_GET['file'];

        $this->action = $_GET['action'];
        if(empty($this->action)) {
            $this->action = 'list';
        }

        if(isset($_POST['code'])) {
            $this->code = $_POST['code'];
        }

        // Figure out what URL we accessed scander from (without the GET params).
        $request_uri = $_SERVER['REQUEST_URI'];
        $query = $_SERVER['QUERY_STRING'];
        $query_pos = strrpos($request_uri, $query);
        if($query_pos + strlen($query) == strlen($request_uri)) {
            $url = substr($_SERVER['REQUEST_URI'], 0, $query_pos);
            $this->url = rtrim($url, '?&');
        }
    }

    // Check if this PHP can run Scander correctly.
    static function checkCompatible() {
        // Needs output buffering.
        $ob = ini_get("output_buffering");

        return $ob;
    }

    // Damn magic_quotes.
    static function gpc($string) {
        if(get_magic_quotes_gpc()) {
            return stripslashes($string);
        }
        return $string;
    }

    // Add some javascript to run when the document has loaded.
    function addInitJS($js) {
        $this->init_scripts[] = $js;
    }

    function getIcons() {
            header("Content-Type: image/png");
            $pngdata = base64_decode("
iVBORw0KGgoAAAANSUhEUgAAALQAAAAeCAYAAACFSjS6AAAABHNCSVQICAgIfAhkiAAAAAlwSFlz
AAAN1wAADdcBQiibeAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAARKSURB
VHja7ZxbSBRRGIDXpVIsTJKiIkpiKXc3ieihp6ggMKML3SCNUgzrIQqjyKLSiYQekjJ8yR4ketqs
rATFUBI2qK2XCi9sIYWY3VgvWO7imv79Z2pi193V2Z2ZM2em8/C9HGf/f/5zPoeZc86MBQAsrODz
+SARWltbO9xudwpLtbBcL9W85Y4mZCWtPjXFALtcLqivr+81mtR61Us1b7kDkCByDUnnQsvs6Jqa
Gm2lvpi1QcQE9VLN+0doie/IUcTKhZbR0ZpJLTi3IP6/bDF6vVTzhgst8QbZyIWW0dGqS11m34Wd
PxoyGKNim4HrpZo3utAS95FMLvQkGhsbxc4OhTzAKJfZcQA7fCzKQIyJfzNovVTzTi00IYBUILN1
F7pusyUbaUe8k2hDZtMa4Fgok9lZjJ08PsVAjIvHaCT04Id2CNYWhkHa1KhXTt/193aLKO7n6YWW
6EMOIkl6Cl2IQAwyDSu0YC+RPRDkWA2EHvJ6InKRNlpCB+pKIXDvLE2hJTzIuoSFRvH2Iu+QoQTw
TyH0MNKJ7DSU0IL9QtyDQH5jIqHF3MIqgEvZMOh9SVtowgRyB1mciNCfppBSDboNI3S540qCAwDi
b00idLAm71/O4K18PYSW+ImcR1LiETqmjI9zZ0DDVsWQGYKFISxgTmiLJQnPq1ph54MYg8QysNDD
z+9G5B32PNBLaImPyJ6EhfbkzYXAuRWg0slEo4UZoQWLFQRHrWq1kVgkpgGF7v/aB+OVmyLyjl/b
DP3fPusptEQbsjouoZt3JMNEmV1Lmf/c+LMgtLBxBghOl+r1kZgktgyxyMwFEXUyUa+U2BbtWGn2
Q6nQ/qarMWsaab7OgtDwd+bpJjJfltDdRxdpLTOhQHehj9uS8WraoFmNJDbJMY1YZDpOaS4SQ6nQ
Ax87YeLympg5JirWwkCPlwWhJQaRk8jMmEI/zLHC2IUsrWUm6/nJugotrE0Vb3u0/8dtEXMZQOiR
pkoYq94Ov67nRMQnbeRvI09usCS0hBfJjSr060PzaFydK3R9KBRsaXgOzyjUKfFMzMm40Jrdu+sp
9I9Sm9bJyTLyEl2FLnMeA8F+OxxHl4q3Gl0R8UlOLjTdWw73vjk0rlZ1TM5DC84qFR8Iq+IRi6WH
QoMILe+h8MuJpTSEXs+FZnsemnGh5U3bNW2bRUPm18yuFHKhWRc6voWV98ULaQhdZEShO4vmQ9vu
1DBIGxea0aXv+hwrBLWfqvPJOSkWhX6xPy1iJZW0caEZ3Zz0Kj/9K4Wr8xmmNydxoVkSWtn20TcF
GUcGTi0f+llqG1WbwdPLh76XLKtifrcdg0LrtcF/oOcd+BsqwiBt/8UGf1ZewTKj0FrWSzWvkV7B
4kJzoRUKzV+S5UKbQmj+GQNNhS6zHwbB8SgabwsyOp7uTv0SCmmLdbwYiwvNPzTD3FvfJq5XB6H5
p8C40KYRmurHGn8DyLSE2zCB6OEAAAAASUVORK5CYII=");
            return $pngdata;
    }

    function getUpload() { 
        ob_start();
?>
<h2>Upload File - <?php echo $this->working_dir; ?></h2>
<form action="<?php echo $this->url; ?>" method="post" enctype="multipart/form-data">
<input type="file" name="upload_file" />
<div>
    <input type="submit" value="Upload" />
    <input type="reset" value="Reset" />
</div>
</form>
<?php
        return ob_get_clean();
    }

    // Get a new file dialog.
    function getNew() {
        ob_start();
?>
<h2>New File - <?php echo $this->working_dir; ?></h2>
<form action="<?php echo $this->url; ?>" method="post">
<div>
    <label for="file_name">Filename</label>
    <input id="file_name" name="file_name" type="text" />
</div>
<div>
    <label for="file_content">Content</label>
    <textarea id="file_content" name="file_content" cols="80" rows="24"></textarea>
</div>
<div>
    <input type="submit" value="Save" />
    <input type="reset" value="Reset" />
</div>
</form>
<?php
        return ob_get_clean();
    }

    function getEdit() {
        ob_start();
?>
<h2>Edit File - <?php echo $this->file; ?></h2>
<form action="<?php echo $this->url; ?>" method="post">
<textarea id="file_content" name="file_content" cols="80" rows="24"><?php echo htmlentities(file_get_contents($this->file)); ?></textarea>
<div>
    <input type="submit" value="Save" />
    <input type="reset" value="Reset" />
</div>
</form>
<?php
        return ob_get_clean();
    }

    // Get a PHP evaluator.
    function getEval() {
        ob_start();
?>
<h2>PHP Evaluation</h2>
<form action="#" onsubmit="evalPHP();return false;">
    <div>
        <textarea id="eval_textarea" cols="80"
            rows="24"><?php echo $eval; ?></textarea>
    </div>
    <div>
        <input id="eval_submit" type="submit" value="Run" />
        <input type="button" value="Clear output" onclick="clearEval();" />
    </div>
</form>
<div id="eval_output"></div>
<?php
        return ob_get_clean();
    }

    // Get a list of the files in the current directory.
    function getList() {
        $filenames = scandir($this->working_dir);
        ob_start();
?>
<h2>Listing (<?php echo is_writable($this->working_dir) ? 'writable': 'read-only'; ?>) - <?php echo $this->working_dir; ?></h2>
<table id="file_list">
    <tr class="heading">
        <th>Type</th>
        <th>Filename</th>
        <th>Size</th>
        <th>Modified</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
    </tr>
<?php
        $odd = false;
        foreach($filenames as $file):
            $filename = $file;
            if(is_dir($this->working_dir . $this->ds . $file)) {
                $filename .= $this->ds;
            }
            $fullpath = realpath($this->working_dir . $this->ds . $filename);

            $action = is_dir($fullpath) ? 'list' : 'download';
            $target = is_dir($fullpath) ? 'dir' : 'file';
            $odd = !$odd;
?>
    <tr class="<?php echo $target; ?> <?php echo $odd ? 'odd' : 'even'; ?>">
        <td class="type type_<?php echo $target; ?>">
            <div class="icon"></div>
            <span class="text"><?php echo is_dir($fullpath) ? 'D': 'F'; ?></span>
        </td>
        <td class="filename">
            <?php if(is_readable($fullpath)): ?>
            <a href="<?php echo $this->url; ?>?action=<?php echo $action; ?>&amp;<?php echo $target; ?>=<?php echo $fullpath; ?>"><?php echo $filename; ?></a>
            <?php else: ?>
            <?php echo $filename; ?>
            <?php endif; ?>
        </td>
        <td class="size">
            <?php echo number_format(filesize($fullpath)); ?>B
        </td>
        <td class="modified">
            <?php echo date('Y-m-d H:i:s', filemtime($fullpath)); ?>
        </td>
        <td class="delete">
            <?php if(is_writable($fullpath)): ?>
            <a href="<?php echo $this->url; ?>?action=delete&amp;<?php echo $target; ?>=<?php echo $fullpath; ?>" onclick="">Delete</a>
            <?php else: ?>
            &nbsp;
            <?php endif; ?>
        </td>
        <td class="rename">
            <?php if(is_writable($fullpath)): ?>
            <a href="<?php echo $this->url; ?>?action=rename&amp;<?php echo $target; ?>=<?php echo $fullpath; ?>" onclick="return rename(this, <?php var_export($file); ?>);">Rename</a>
            <?php else: ?>
            &nbsp;
            <?php endif; ?>
        </td>
        <td class="edit">
            <?php if(is_writable($fullpath)): ?>
            <a href="<?php echo $this->url; ?>?action=edit&amp;<?php echo $target; ?>=<?php echo $fullpath; ?>"><?php echo is_dir($fullpath) ? '' : 'Edit'; ?></a>
            <?php else: ?>
            &nbsp;
            <?php endif; ?>
        </td>
    </tr>
<?php
        endforeach;
?>
</table>
<?php
        return ob_get_clean();
    }

    // Get HTML output with specified `content`.
    function getHTML($content = '') {
        ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
            "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Scander - <?php echo htmlentities($this->working_dir); ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<style type="text/css">
body {
    background: #FFF5E5;
    font-family: sans-serif;
    margin: 0;
    padding: 0;
}

a {
    color: #630;
}

a:hover {
    background: #FEA;
}

label {
    display: block;
}

#container {
    padding: 1em;
    background: #FFE;
    margin: 0 auto;
    width: 50em;
}

#navigation ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

#navigation ul li {
    float: left;
    padding-right: 1em;
}

#footer {
    padding-top: 1em;
    text-align: center;
}

#eval_textarea, #file_name, #file_content {
    width: 100%;
}

#eval_output {
    margin-top: 1em;
    border-top: 2px solid #444;
    background: #000;
    color: #FFF;
    white-space: pre;
    font-family: monospace;
    padding: 1em;
}

#file_list {
    width: 100%;
}

#file_list td {
    text-align: center;
}

#file_list .type .text {
    display: none
}

#navigation .icon, #file_list .type .icon {
    background-image: url(<?php echo $this->url; ?>?action=icons.png);
    width: 30px;
    height: 30px;
}

#navigation .icon, #file_list .type .icon {
    margin: 0 auto;
}

#navigation .home .icon {
    background-position: 120px;
}

#navigation .new .icon {
    background-position: 90px;
}

#navigation .upload .icon {
    background-position: 600px;
}

#navigation .eval .icon {
    background-position: 30px;
}

#file_list .type_dir .icon {
    background-position: 0px;
}

#file_list .type_file .icon {
    background-position: 150px;
}

#file_list a {
    display: block;
}

#file_list .dir a:hover {
    background: #FC9;
}

#file_list .odd {
    background: #FEC;
}

#file_list .filename {
    text-align: left;
}

#file_list .size {
    text-align: right;
}

#file_list .rename input {
width: 7em;
}

#content {
    padding-top: 0.5em;
    clear: both;
}

.eval_error {
    color: #F44;
}
</style>
<script type="text/javascript">
function initJS() {
    <?php echo implode('', $this->init_scripts); ?>
}

<?php echo $this->getJS(); ?>
</script>
</head>

<body onload="initJS()">
<div id="container">
    <div id="header">
        <h1>Scander</h1>
    </div>
    <div id="navigation">
        <ul>
            <li class="home">
                <a href="<?php echo $this->url; ?>?action=list&amp;dir=<?php echo $this->working_dir; ?>" title="Home">
                    <div class="icon"></div>
                    <span class="text">Home</span>
                </a>
            </li>
<?php
        if(is_writable($this->working_dir)):
?>
            <li class="new">
                <a href="<?php echo $this->url; ?>?action=new&amp;dir=<?php echo $this->working_dir; ?>" title="New">
                    <div class="icon"></div>
                    <span class="text">New</span>
                </a>
            </li>
            <li class="upload">
                <a href="<?php echo $this->url; ?>?action=upload&amp;dir=<?php echo $this->working_dir; ?>" title="Upload">
                    <div class="icon"></div>
                    <span class="text">Upload</span>
                </a>
            </li>
<?php
        endif;
?>
            <li class="eval">
                <a href="<?php echo $this->url; ?>?action=eval&amp;dir=<?php echo $this->working_dir; ?>" title="PHP Evaluation">
                    <div class="icon"></div>
                    <span class="text">Eval</span>
                </a>
            </li>
        </ul>
    </div>
    <div id="content">
        <?php echo $content; ?>
    </div>
    <div id="footer">
        Scander <?php echo $this->version; ?> by <a href="http://github.com/pufuwozu">Brian McKenna</a>
    </div>
</div>
</body>
</html>
<?php
        return ob_get_clean();
    }

    // Get any static JS (doesn't change).
    static function getJS() {
        ob_start();
?>
function newXMLHTTP() {
    try {
        return new XMLHttpRequest();
    }
    catch (ea) {
        try {
            return new ActiveXObject("Msxml2.XMLHTTP");
        } catch (eb) {
            try {
                return new ActiveXObject("Microsoft.XMLHTTP");
            } catch (ec) {
                return false;
            }
        }
    }
}

function clearEval() {
    document.getElementById("eval_output").innerHTML = '';
}
    
function evalPHP() {
    var ajax = newXMLHTTP();
    var evalBox = document.getElementById("eval_textarea");
    var evalButton = document.getElementById("eval_submit");
    var evalOutput = document.getElementById("eval_output");
    var params = "code=" + encodeURIComponent(evalBox.value);

    evalButton.disabled = true;

    ajax.onreadystatechange = function() {
        if (ajax.readyState == 4) {
            if (ajax.status == 200) {
                evalOutput.innerHTML += '<div>' + ajax.responseText + '</div>';
            } else {
                evalOutput.innerHTML +=
                    '<div class="eval_error">Error executing PHP.</div>';
            }
            evalButton.disabled = false;
        }
    };
    ajax.open("POST", document.location, true);
    ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    ajax.setRequestHeader("Content-length", params.length);
    ajax.setRequestHeader("Connection", "close");
    ajax.send(params);
}

function rename(el, name) {
    var newel = document.createElement('input');
    newel.type = 'text';
    newel.value = name;
    newel.onblur = function() {
        newel.parentNode.replaceChild(el, newel);
    };

    el.parentNode.replaceChild(newel, el);
    newel.focus();

    return false;
}
<?php
        return ob_get_clean();
    }

    // Prints a list of things that need to be setup.
    static function getChecklist() {
        ob_start();
?>
<h2>Sorry, Scander doesn&rsquo;t want to work!</h2>
<p>We checked your configuration and found a few things wrong with it. Please
check the following items to continue using Scander:</p>
<ul>
<li>Output buffering</li>
</ul>
<?php
        return ob_get_clean();
    }
}
new Scander();
?>
