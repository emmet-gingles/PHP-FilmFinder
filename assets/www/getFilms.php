<?php
$cinema = $_POST["cinemas"];
$day = $_POST["days"];
$starttime = $_POST["starttimes"];
$endtime = $_POST["endtimes"];
$order = $_POST["order"];
$date = getdate();
  
require_once 'login.php';
$db_server = mysqli_connect($db_hostname,$db_username,$db_password); 
if(!$db_server) die("Unable to connect to MySQL: ".mysqli_error());

$sql = 'CREATE DATABASE '.$db_name;
if (!mysqli_num_rows(mysqli_query($db_server, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '". $db_name ."'"))) {
	mysqli_query($db_server, $sql);
}

mysqli_select_db($db_server, $db_name)
or die("Unable to select database: ".mysqli_error());
 
$drop = "DROP TABLE IF EXISTS Cinemas";
mysqli_query($db_server, $drop);

$create = "CREATE TABLE Cinemas(
           name VARCHAR(20) NOT NULL,
           film VARCHAR(30) NOT NULL,
           day VARCHAR(20) NOT NULL,
           time VARCHAR(10) NOT NULL)";
mysqli_query($db_server, $create);
echo '<br/>';

$cinemalist = array(
		array('Armagh','http://entertainment.ie/cinema/display.asp?UserLocation=2&film_title=&vid=40246&all=yes'),
		array('Ashbourne','http://entertainment.ie/cinema/display.asp?UserLocation=22&film_title=&vid=40394&all=yes'),
		array('Athlone','http://entertainment.ie/cinema/display.asp?UserLocation=30&film_title=&vid=193&all=yes'),
		array('Belfast','http://entertainment.ie/cinema/display.asp?UserLocation=1&film_title=&vid=34284&all=yes'),
		array('Carlow','http://entertainment.ie/cinema/display.asp?UserLocation=3&film_title=&vid=183&all=yes'),
		array('Cavan','http://entertainment.ie/cinema/display.asp?UserLocation=4&film_title=&vid=33382&all=yes'),
		array('Drogheda','http://entertainment.ie/cinema/display.asp?UserLocation=20&film_title=&vid=179&all=yes'),
		array('Dun Laoighre','http://entertainment.ie/cinema/display.asp?UserLocation=10&film_title=&vid=38785&all=yes'),
		array('Dundalk','http://entertainment.ie/cinema/display.asp?UserLocation=20&film_title=&vid=32069&all=yes'),
		array('Enniskillen','http://entertainment.ie/cinema/display.asp?UserLocation=11&film_title=&vid=32609&all=yes'),
		array('Galway','http://entertainment.ie/cinema/display.asp?UserLocation=12&film_title=&vid=190&all=yes'),
		array('Kilkenny','http://entertainment.ie/cinema/display.asp?UserLocation=15&film_title=&vid=197&all=yes'),
		array('Limerick','http://entertainment.ie/cinema/display.asp?UserLocation=15&film_title=&vid=197&all=yes'),
		array('Monaghon','http://entertainment.ie/cinema/display.asp?UserLocation=23&film_title=&vid=263&all=yes'),
		array('Mullingar','http://entertainment.ie/cinema/display.asp?UserLocation=30&film_title=&vid=211&all=yes'),
		array('Naas','http://entertainment.ie/cinema/display.asp?UserLocation=14&film_title=&vid=38868&all=yes'),
		array('Navan','http://entertainment.ie/cinema/display.asp?UserLocation=22&film_title=&vid=247&all=yes'),
		array('Newry','http://entertainment.ie/cinema/display.asp?UserLocation=9&film_title=&vid=215&all=yes'),
		array('Portlaoise','http://entertainment.ie/cinema/display.asp?UserLocation=16&film_title=&vid=254&all=yes'),
		array('Sligo','http://entertainment.ie/cinema/display.asp?UserLocation=26&film_title=&vid=38771&all=yes'),
		array('Tallaght','http://entertainment.ie/cinema/display.asp?UserLocation=10&film_title=&vid=228&all=yes'),
		array('Tullamore','http://entertainment.ie/cinema/display.asp?UserLocation=24&film_title=&vid=31190&all=yes'),
		array('Waterford','http://entertainment.ie/cinema/display.asp?UserLocation=29&film_title=&vid=39158&all=yes'),
		array('Wexford','http://entertainment.ie/cinema/display.asp?UserLocation=31&film_title=&vid=232&all=yes'),
		);

foreach ($cinemalist as $cin){
	if($cin[0] == $cinema){
		getFilms($cin[1], $cin[0], $day, $date, $starttime, $endtime, $order);
	}	
}
mysqli_close($db_server);


function getFilms($url,$cinema,$day,$date,$starttime,$endtime,$order){
	$f = file_get_contents($url);
	global $db_server;
	$start = 0;
	$len = strpos($f, "Email to a friend");

	while($len != null){	
		$content = substr($f,$start,$len-$start);
		preg_match('/<strong><a href="(.*?)">(.*?)<\/a><\/strong>/sm',$content,$title);
		if(isset($title[2])){
			$film_title = $title[2];
		}
		preg_match_all('/<tr[^>]+>(.*?)<\/tr>/sm',$content,$today);
		preg_match_all('/<tr>(.*?)<\/tr>/sm',$content,$days);
		$insrt;
		for($i = 0; $i < count($today[1]); $i++) {
			$st = strip_tags($today[1][$i]);
			$d = substr($st,2,5);
			$numTimes = substr_count($today[1][$i],":");
			$start = 0;
		for($j=0;$j < $numTimes;$j++){
			$pos = strpos($st,":",$start);
			$insrt = $pos-2;
			$start = $pos+1;
			$time = substr($st,$insrt,5);
			$insert = "INSERT INTO cinemas (name, film, day, time) VALUES ('$cinema', '$film_title', '$d', '$time')";
			mysqli_query($db_server, $insert);
			}
		}
		for($i = 0; $i < count($days[1]); $i++) {
			$st = strip_tags($days[1][$i]);
			$d = substr($st,0,12);
			if(strpos($d,"Mon")){
				$d = str_replace("Mon","Monday",$d);
			}
			elseif(strpos($d,"Tue")){
				$d = str_replace("Tue","Tuesday",$d);
			}
			elseif(strpos($d,"Wed")){
				$d = str_replace("Wed","Wednesday",$d);
			}
			elseif(strpos($d,"Thu")){
				$d = str_replace("Thu","Thursday",$d);
			}
			elseif(strpos($d,"Fri")){
				$d = str_replace("Fri","Friday",$d);
			}
			elseif(strpos($d,"Sat")){
				$d = str_replace("Sat","Saturday",$d);
			}
			elseif(strpos($d,"Sun")){
				$d = str_replace("Sun","Sunday",$d);
			}
			
			if(strpos($d,"Jan")){
				$d = str_replace("Jan","January",$d);
			}
			elseif(strpos($d,"Feb")){
				$d = str_replace("Feb","February",$d);
			}
			elseif(strpos($d,"Mar")){
				$d = str_replace("Mar","March",$d);
			}
			elseif(strpos($d,"Apr")){
				$d = str_replace("Apr","April",$d);
			}
			elseif(strpos($d,"Jun")){
				$d = str_replace("Jun","June",$d);
			}
			elseif(strpos($d,"Jul")){
				$d = str_replace("Jul","July",$d);
			}
			elseif(strpos($d,"Aug")){
				$d = str_replace("Aug","August",$d);
			}
			elseif(strpos($d,"Sep")){
				$d = str_replace("Sep","September",$d);
			}
			elseif(strpos($d,"Oct")){
				$d = str_replace("Oct","October",$d);
			}
			elseif(strpos($d,"Nov")){
				$d = str_replace("Nov","November",$d);
			}
			elseif(strpos($d,"Dec")){
				$d = str_replace("Dec","December",$d);
			}
	
			$numTimes = substr_count($days[1][$i],":");
			$start = 0;
			for($j=0;$j < $numTimes;$j++){
				$pos = strpos($st,":",$start);
				$insrt = $pos-2;
				$start = $pos+1;
				$time = substr($st,$insrt,5);
				if(substr($st,2,3)== substr($day, 0,3)){
					$insert ="INSERT INTO cinemas (name, film, day, time) VALUES ('$cinema', '$film_title', '$d', '$time')";
					mysqli_query($db_server, $insert);
				}
			}
		}
		$start = $len+1;
		$len = strpos($f,"Email to a friend",$len+1);
	}
	if($order == "title"){
		if($date['weekday'] == $day){
			$select = "SELECT * FROM cinemas WHERE day = 'Today' AND time BETWEEN '$starttime' AND '$endtime'";
			$heading = "Films on today in ".$cinema." between ".$starttime." and ".$endtime;
			$q = mysqli_query($db_server, $select);
		}
		else{
			$select ="SELECT * FROM cinemas WHERE day LIKE '%$day%' AND time BETWEEN '$starttime' AND '$endtime'";
			$heading = "Films on ".$day." in ".$cinema." between ".$starttime." and ".$endtime;
			$q = mysqli_query($db_server, $select);
		}
	}
	else{
		if($date['weekday'] == $day){
			$select = "SELECT * FROM cinemas WHERE day = 'Today' AND time BETWEEN '$starttime' AND '$endtime' ORDER BY time,film";
			$heading = "Films on today in ".$cinema." between ".$starttime." and ".$endtime;
			$q = mysqli_query($db_server, $select);
		}
		else{
			$select ="SELECT * FROM cinemas WHERE day LIKE '%$day%' AND time BETWEEN '$starttime' AND '$endtime' ORDER BY time,film";
			$heading = "Films on ".$day." in ".$cinema." between ".$starttime." and ".$endtime;
			$q = mysqli_query($db_server, $select);
		}	
	}
	
	echo "<a href='http://localhost:8080/FilmFinder/assets/www/index.html'>Return</a> <br/>";
	echo $heading.'<br/>';
	echo '<table border="5" cellpadding="10">';
	echo '<tr>';
	echo '<th>'."Film ".'</th>'.'<th>'."Time ".'</th>';
	echo '</tr>';
	while($row = mysqli_fetch_array($q)){
		echo '<tr>';
		echo '<td>'.$row[1].'</td>'.'<td>'.$row[3].'</td>';
		echo "</tr>";
	}
	echo '</table>';
}
?>