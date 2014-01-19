<?php

//include_once 'functions.php';

function preg_build($exp, $modifiers = '')
{
	$delimiters = '/#~=-_!%&:;`<>@';
	for ($i=0; $i<strlen($delimiters); $i++) {
		if (false === strpos($exp, $delimiters[$i])) {
			break;
		}
	}
	return $delimiters[$i] . $exp . $delimiters[$i] . $modifiers;
}


function search ($what, $where, $mime_limit_or_filename_regexp = false, $case_sensitive = false,
                 $search_sub = true, $size_limit = 10000000, $charset = 'utf-8',
                 $is_regular_expression = false, $count_limit = false)
{
	$r = array();
	$found = 0;
	$fn_name = __FUNCTION__;
	
	if (!$is_regular_expression AND !$case_sensitive) {
		$what = mb_strtolower ($what, $charset);
	}
	if (preg_match('#/\\\\$#', $where)) {
		$where = substr ($where, 0, -1);
	}
	
	$handle = opendir ($where);
	while ($now = readdir ($handle)) {
		if (($now === '.') OR ($now === '..')) {
			continue;
		}
		if (is_dir ($where.DIRECTORY_SEPARATOR.$now)) {
			if ($search_sub) {
				$indir = $fn_name (
					$what, $where.DIRECTORY_SEPARATOR.$now, $mime_limit_or_filename_regexp,
					$case_sensitive, $search_sub, $size_limit, $charset, $is_regular_expression,
					$count_limit ? $count_limit - $found : false
				);
				$r = array_merge ($r, $indir);

			} else {
				continue;
			}
		} else {
			if (filesize ($where.DIRECTORY_SEPARATOR.$now) > $size_limit) {
				continue;
			}
			if ($mime_limit_or_filename_regexp) {
				if (is_array($mime_limit_or_filename_regexp)) {
					$mime = strtolower (SubStr ($now, StrRpos ($now, '.') + 1));
					if (!in_array ($mime, $mime_limit_or_filename_regexp)) {
						continue;
					}
				} else {
					if (!preg_match(preg_build($mime_limit_or_filename_regexp, 'i'), $now)) {
						continue;
					}
				}
			}
			$file = file_get_contents ($where.DIRECTORY_SEPARATOR.$now);
			if (!$is_regular_expression AND !$case_sensitive) {
				$file = mb_strtolower ($file, $charset);
			}
			if ($is_regular_expression) {
				if (preg_match (preg_build($what, $case_sensitive ? 's' : 'si'), $file)) {
					$r[] = $where.DIRECTORY_SEPARATOR.$now;
				}

			} else {
				if (false !== mb_StrPos ($file, $what, 0, $charset)) {
					$r[] = $where.DIRECTORY_SEPARATOR.$now;
				}
			}
		}
		if ($count_limit) {
			$found = count($r);
			if ($found >= $count_limit) {
				break;
			}
		}
	}
	return $r;
}


if (isset($_POST['search'])) {
	$err = array();

	$regex = isset($_POST['regex']) ? (bool) $_POST['regex'] : FALSE;
	$what = trim($_POST['what']);
	$cs = isset($_POST['cs']) ? (bool) $_POST['cs'] : FALSE;
	$where = trim($_POST['where']);
	$sub = isset($_POST['sub']) ? (bool) $_POST['sub'] : FALSE;
	$filelimit_type = (int) $_POST['filelimit_type'];
	$filelimit = trim($_POST['filelimit']);
	$size = str_replace(array(' ', '.', ','), '', $_POST['size']);
	$count = trim($_POST['count']);
	$charset = trim($_POST['charset']);
	if (!$charset) {
		$charset = 'utf-8';
	}

	if (!$what) {
		$err[] = 'Zadej hledaný výraz ("Co").';
	}
	if (!$where) {
		$err[] = 'Zadej umístění pro hledání ("Kde").';
	}
	$where = realpath($where);
	if (!$where) {
		$err[] = 'Umístění pro hledání neexistuje.';
	}
	if (!$size) {
		$err[] = 'Zadej max. velikost souboru.';
	}
	if (!preg_match('/^\d+$/', $size)) {
		$err[] = 'Max. velikost souboru musí být kladné číslo.';
	}
	$size = (int) $size;
	if ($count) {
		if (!preg_match('/^\d+$/', $count)) {
			$err[] = 'Max. počet nálezů musí být kladné číslo.';
		}
	} else {
		$count = FALSE;
	}
	if ($filelimit_type == 1) {
		if (!$filelimit) {
			$err[] = 'Zadej mimetypy.';
		} else {
			$filelimit = explode(',', $filelimit);
			$filelimit = array_map('trim', $filelimit);
		}

	} elseif ($filelimit_type == 2) {
		if (!$filelimit) {
			$err[] = 'Zadej regulární výraz na názvy souborů.';
		}

	} elseif ($filelimit == 0) {
		$filelimit = FALSE;
	}

	if (empty($err)) {
		$results = search($what, $where, $filelimit, $cs, $sub, $size, $charset, $regex, $count);
	}
}


$regex_checked = isset($_POST['regex']) ? (bool) $_POST['regex'] : FALSE;
$regex_checked = $regex_checked ? ' checked="checked"' : '';
$what = isset($_POST['what']) ? htmlspecialchars($_POST['what']) : '';
$cs_checked = isset($_POST['cs']) ? (bool) $_POST['cs'] : FALSE;
$cs_checked = $cs_checked ? ' checked="checked"' : '';
$where = isset($_POST['where']) ? htmlspecialchars($_POST['where']) : 'C:/';
$sub_checked = isset($_POST['sub']) ? (bool) $_POST['sub'] : TRUE;
$sub_checked = $sub_checked ? ' checked="checked"' : '';
$filelimit_type = isset($_POST['filelimit_type']) ? $_POST['filelimit_type'] : 1;
$filelimit = isset($_POST['filelimit']) ? htmlspecialchars($_POST['filelimit']) : 'txt';
$size = isset($_POST['size']) ? htmlspecialchars($_POST['size']) : '1 000 000';
$count = isset($_POST['count']) ? htmlspecialchars($_POST['count']) : '20';
$charset = isset($_POST['charset']) ? htmlspecialchars($_POST['charset']) : 'utf-8';

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
</head>
<body>
<h1>Hledat v souborech</h1>
<?php

if (isset($err) && !empty($err)) {
	echo '<ul style="color:#900;font-weight:bold;">' . "\n";
	foreach ($err as $error) {
		echo "\t<li>$error</li>\n";
	}
	echo "</ul>\n\n";
}


if (isset($results)) {
	if (empty($results)) {
		echo '<h2 style="color:#950;">NIC NENALEZENO</h2>' . "\n";

	} else {
		echo '<h2 style="color:#090;">VÝSLEDKY</h2>' . "\n";
		echo '<ul style="color:#009;font-weight:bold;">' . "\n";
		foreach ($results as $n => $result) {
			++$n;
			echo "\t" . '<li style="margin-top: 10px;"><label><strong>' . $n . '.</strong> <input type="checkbox"></label>'
				. '<input type="text" size="150" readonly="readonly" onclick="this.select();" value="' . $result . '"></li>' . "\n";
		}
		echo "</ul>\n\n";
	}
}

?>
<form method="post">
	<p>
		<label>Regulární výraz (bez delimiterů)
		<abbr style="cursor:help;font-family:Courier;" title="hledá multiřádkově (modifikátor &quot;s&quot;)">i</abbr>:
		<input type="checkbox" value="1" name="regex"<?=$regex_checked;?>></label>
	</p>
	<p><label>Co: <input type="text" size="80" name="what" value="<?=$what;?>"></label></p>
	<p><label>Rozlišovat velikost: <input type="checkbox" value="1" name="cs"<?=$cs_checked;?>></label></p>
	<p><label>Kde: <input type="text" size="120" name="where" value="<?=$where;?>"></label></p>
	<p><label>Prohledat podadresáře: <input type="checkbox" value="1" name="sub"<?=$sub_checked;?>></label></p>
	<p>Omezit prohledávané soubory:
		<label><input type="radio" name="filelimit_type" value="0"<?php if ($filelimit_type == 0) { ?> checked="checked"<?php } ?>> bez omezení</label>
		| <label><input type="radio" name="filelimit_type" value="1"<?php if ($filelimit_type == 1) { ?> checked="checked"<?php } ?>> mimetype omezení (oddělit čárkami)</label>
		| <label><input type="radio" name="filelimit_type" value="2"<?php if ($filelimit_type == 2) { ?> checked="checked"<?php } ?>> regulární výraz (bez delimiterů)</label>
		&nbsp; <input type="text" size="40" name="filelimit" value="<?=$filelimit;?>">
	</p>
	<p><label>Max. velikost prohledávaného souboru: <input type="text" size="15" name="size" value="<?=$size;?>"> B</label></p>
	<p><label>Max. počet nálezů (prázdné = neomezeně): <input type="text" size="5" name="count" value="<?=$count;?>"></label></p>
	<p><label>Kódování souborů (prázdné = utf-8): <input type="text" size="10" name="charset" value="<?=$charset;?>"></label></p>
	<p><input type="submit" name="search" value="HLEDEJ"></p>
</form>
</body>
</html>
