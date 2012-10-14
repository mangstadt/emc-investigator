<?php
//class auto-loader
spl_autoload_register(function ($class) {
	$path = str_replace('\\', '/', $class);
	$path = __DIR__ . "/_lib/$path.php";
	if (file_exists($path)){
		require_once $path;
	}
});

//$servers = array('smp1', 'smp2', 'smp3', 'smp4', 'smp5', 'smp6', 'smp7', 'smp8', 'smp9', 'utopia');
$servers = array('smp7'); 

$errors = array();
if (count($_GET) > 0){
	//sanitize input
	foreach ($_GET as $key=>$value){
		//trim value
		$_GET[$key] = trim($value);
		
		//if value is empty, unset it
		if ($value == ''){
			unset($_GET[$key]);
		} 
	}
	
	$startTime = @$_GET['startTime'];
	$endTime = @$_GET['endTime'];
	if ($startTime === null || $endTime === null) {
		$errors[] = 'A start time and end time are required.';
	} else {
		$startTime = strtotime($startTime);
		$endTime = strtotime($endTime);
		if ($startTime === false || $endTime === false){
			$errors[] = 'Invalid start/end times.';
		} else if ($startTime > $endTime) {
			$errors[] = 'Start time must come before end time.';
		} else if ($endTime - $startTime > 60*60*24*7) {
			$errors[] = 'Time span cannot be longer than 1 week.';
		}
	}

	$x1 = @$_GET['x1'];
	$z1 = @$_GET['z1'];
	$x2 = @$_GET['x2'];
	$z2 = @$_GET['z2'];
	$coords = array($x1, $z1, $x2, $z2);
	$coordsEntered = 0;
	foreach ($coords as $coord){
		if ($coord !== null){
			$coordsEntered++;
		}
	}
	if ($coordsEntered > 0 && $coordsEntered < 4){
		$errors[] = 'Some coordinates are missing values.';
	} else if ($coordsEntered == 4){
		$numeric = true;
		foreach ($coords as $coord){
			if (!preg_match('/^-?\\d+$/', $coord)){
				$errors[] = 'Coordinates must be numeric and without decimals.';
				$numeric = false;
				break;
			}
		}
		
		if ($numeric){
			if ($x1 > $x2){
				$errors[] = 'X1 must be less than X2';
			}
			if ($z1 > $z2){
				$errors[] = 'Z1 must be less than Z2';
			}
		}
	}

	$player = @$_GET['player'];
	
	if (count($errors) == 0){
		$dao = new DbDao(getenv('DB_HOST'), getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));
		//$dao = new DbDao('localhost', 'emc_investigator', 'root', 'root', null, "/tmp/mysql.sock"); //mac
		$results = $dao->getReadings('smp7', $startTime, $endTime, $x1, $z1, $x2, $z2, $player);
	}
}

?>
<html>
	<head>
		<title>EMC Investigator</title>
		
		<!-- http://www.kryogenix.org/code/browser/sorttable/ -->
		<script type="text/javascript" src="_js/sorttable.js"></script>
		
		<style>
		body{
			color:#fff;
			background-color:#000;
		}
		
		table{
			color:#fff;
		}
		
		tr.evenRow{
			background-color:none;
		}
		
		tr.oddRow{
			background-color:#960;
		}
		
		a:link, a:active, a:visited{
			color: #f90;
			text-decoration: underline;
		}
		a:hover{
			color: #960;
			text-decoration: none;
		}
		</style>
	</head>
	<body>
		<div style="position:fixed; right:10px; top:10px;">
			<a href="http://empire.us"><img src="images/emc-logo.png" border="0" /></a><br />
			<a href="https://sites.google.com/site/emclastlightoutpost/"><img src="images/llo-logo.png" border="0" /></a><br /><br />
			<div style="text-align:center; font-size:0.8em; padding:20px 0px 20px 0px; background-color:#333; cursor:pointer;" onclick="window.scrollTo(0,0)">
				[<a href="#">scroll to top</a>]
			</div>
		</div>
		
		<div style="position:fixed; right:10px; bottom:10px;">
			<a href="https://github.com/mangstadt/emc-investigator" target="_blank" style="font-size:0.8em">http://www.github.com/mangstadt/emc-investigator</a>
		</div>

		<span style="font-size:3em; font-weight:bold;">EMC Investigator</span><br />
		<em>A griefer investigation tool for <a href="http://empire.us">Empire Minecraft</a></em><br />
		by <a href="http://empireminecraft.com/members/shavingfoam.12110/">shavingfoam</a> (c) 2012<br />
		<strong>Note:</strong> This tool cannot detect hidden players.
		<hr />
		
		<form action="." method="get">
			<?php
			if ($errors){
				?><div style="color:red"><ul><?php
				foreach ($errors as $error){
					echo "<li>", htmlentities($error), "</li>";
				}
				?></ul></div><?php
			}
			?>

			<em><?php echo req()?> = required</em>
			
			<div style="padding-top:10px">
				Server<?php echo req()?>:
				<select name="server">
				<?php
				foreach ($servers as $server){
					$selected = (@$_GET['server'] == $server) ? ' selected' : '';
					echo "<option", $selected, ">", htmlentities($server), "</option>";
				}
				?>
				</select>
			</div>
			
			<div style="padding-top:10px">
				Start time<?php echo req()?>: <input type="text" name="startTime" value="<?php echo f('startTime')?>" /> <em>(format: YYYY-MM-DD HH:MM)</em>
			</div>
			<div>
				End time<?php echo req()?>: <input type="text" name="endTime" value="<?php echo f('endTime')?>" />  <em>(format: YYYY-MM-DD HH:MM)</em>
			</div>
			<div style="padding-top:10px">
				Upper-left coords:<br />
				X: <input type="text" name="x1" size="10" value="<?php echo f('x1')?>" /> Z: <input type="text" name="z1" size="10" value="<?php echo f('z1')?>" />
			</div>
			<div>
				Bottom-right coords:<br />
				X: <input type="text" name="x2" size="10" value="<?php echo f('x2')?>" /> Z: <input type="text" name="z2" size="10" value="<?php echo f('z2')?>" />
			</div>
			<div style="padding-top:10px">
				Player: <input type="text" name="player" value="<?php echo f('player')?>" /> <em>(can be partial name)</em>
			</div>
			<input type="submit" value="Search" />
		</form>
		
		<?php
		if (isset($results)){
			?>
			<h1>Results</h1>
			<div>
				<table cellpadding="5" class="sortable" id="resultsTable">
					<tr>
						<th style="cursor:pointer;" id="timeCol">Time (GMT-5)</th>
						<th style="cursor:pointer;">Player</th>
						<th class="sorttable_nosort">(X,Z) [Y]</th>
					</tr>
					<?php
					$odd = true; 
					foreach ($results as $result){
						foreach ($result->players as $player){
							echo "<tr class=\"", ($odd ? 'oddRow' : 'evenRow'), "\">";
							echo "<td>", date('Y-m-d H:i', $result->ts), "</td>";
							echo "<td><img onerror=\"this.style.display='none'\" src=\"http://smp1.empire.us:8880/tiles/faces/16x16/" . urlencode($player->name) . ".png\" /> ", htmlentities($player->name), "</td>";
							echo "<td>", "(", $player->x, ",", $player->z, ")", " [", $player->y, "]", "</td>";
							echo "</tr>";
							$odd = !$odd;
						}
					}
					?>
				</table>
			</div>
			<?php
		}
		?>

		<script>
		var table = document.getElementById('resultsTable');
		table.afterSort = function(){
			for (var i = 1; i < table.rows.length; i++){
				var row = table.rows[i];
				row.className = (i % 2 == 0) ? 'evenRow' : 'oddRow';
			}
		};
		</script>
	</body>
</html>

<?php 
function f($name){
	if (isset($_GET[$name])){
		return htmlentities($_GET[$name]);
	}
	return '';
}

function req(){
	return "<span style=\"color:red\">*</span>";
}
?>