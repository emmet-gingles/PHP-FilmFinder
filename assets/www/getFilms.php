<!DOCTYPE HTML> 
<html> 
	<head> 
		<title>Films</title> 
		<link rel="stylesheet" type="text/css" href="style/style.css">
	</head> 
	<body> 
		<a href='index.html' class='returnLink'>Return to main page</a>
		<div id="content">
		<?php
			// variables set by form elements
			$cinema = $_POST["cinemas"];
			$day = $_POST["days"];
			$starttime = $_POST["starttimes"];
			$endtime = $_POST["endtimes"];
			$order = $_POST["order"];

			// run only if all variables are set
			if(isset($cinema) && isset($day) && isset($starttime) && isset($endtime) && isset($order) ){
				// use login.php for database connection parameters
				require_once 'db/login.php';
				// try to connect using connection parameters
				$db_server = mysqli_connect($db_hostname,$db_username,$db_password); 
				// terminate if connection unsuccessful
				if(!$db_server) {
					die("Unable to connect to MySQL: ".mysqli_connect_error());
				}
				// create database 
				$create_db = 'CREATE DATABASE '.$db_name;
				if (!mysqli_num_rows(mysqli_query($db_server, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '". $db_name ."'"))) {
					mysqli_query($db_server, $create_db);
				}
				// select database
				mysqli_select_db($db_server, $db_name);
				// drop table 
				$drop_table = "DROP TABLE IF EXISTS Cinemas";
				mysqli_query($db_server, $drop_table);
				// create table 
				$create_table = "CREATE TABLE Cinemas(name VARCHAR(20) NOT NULL, film VARCHAR(30) NOT NULL, day VARCHAR(20) NOT NULL, time INTEGER NOT NULL)";
				mysqli_query($db_server, $create_table);

				// file that will be used to get data
				$file = "text/towns.txt";
				$cinemalist = array();
				// first check that file does exist
				if (file_exists($file)) {
					// split data by line
					$lines = explode("\n", file_get_contents($file));
					// loop through each line 
					foreach($lines as $l){
						// split line into two parts - the town and the URL
						$data = explode(";", $l);
						$town = $data[0];
						# Remove leading and trailing whitespaces for the URL
						$url = trim($data[1]);
						# add the data to array 
						array_push($cinemalist, array($town, $url));
					}	
				}
				// else file doesn't exist so terminate program
				else{
					die("File $file not found");
				}

				$urls = array();
				// loop through cinemas 
				foreach ($cinemalist as $cin){	
					// if the cinema name is the same as the input parameter then add the URL to an array
					if($cin[0] == $cinema){
						array_push($urls, $cin[1]);
						// call explorePage() and each resulting URL to array. Continue until -1 is returned
						$url = explorePage($cin[1]);
						while ($url != -1){
							array_push($urls, $url);
							$url = explorePage($url);
						}
					}
				}
				// convert times to timestamps
				$starttimestamp = strtotime($starttime);
				$endtimestamp = strtotime($endtime);
				// get current date
				$date = getdate();
				// call function using input parameters
				getFilms($urls, $cinema, $day, $date, $starttimestamp, $endtimestamp, $order);
				mysqli_close($db_server);	
			}
			else{
				echo "One or more parameters not set";
			}

			// function that will explore the contents of a URL and search for a particular pattern to see if there is a next page 
			function explorePage($url){
				$f = file_get_contents($url);
				// this regular expression extracts the URL of the link if it successfully matches 
				$pattern = '/<span class="pagingnext"><a class="btnblack" href="(.*)" title=[^>]+/';
				preg_match($pattern,$f,$matches);
				// if match was succesful thane return the URL
				if(count($matches) > 0){
					$url = $matches[1];
					return $url;
				}
				// else return -1 and exit function 
				else{
					return -1;
				}
			}

			// function to read in film information from URL, insert it into database and finally read the data from database and output it in a table
			function getFilms($urls, $cinema, $day, $date, $starttime, $endtime, $order){
				// accessing database connection
				global $db_server;
				
				// loop through each URL 
				foreach ($urls as $url){
					// read all the content of URL
					$f = file_get_contents($url);
					// starting position
					$start = 0;
					// find the first instance of string and record its position within the page
					$len = strpos($f, "Email to a friend");

					while($len != null){
						# the part of the page to search
						$content = substr($f, $start, $len-$start);
						# pattern to search in order to get the film title 
						$pattern = '/<strong><a href=[^>]+>(.*)<\/a><\/strong>/';
						preg_match($pattern, $content, $title);
						// if match succesful then set title to variable. Escape string is used for characters such as apostrophes
						if(isset($title[1])){
							$film_title = $title[1];
							$film_title = mysqli_real_escape_string($db_server, $film_title);
						}
						// pattern used for today's times  
						$pattern = '/<tr class="today">(.*?)<\/tr>/s';
						preg_match_all($pattern,$content,$today);
						// pattern used for all other days's times
						$pattern = '/<tr>(.*?)<\/tr>/s';
						preg_match_all($pattern,$content,$days);
					
						for($i = 0; $i < count($today[1]); $i++) {
							// strip whitespaces from today's times 
							$td = strip_tags($today[1][$i]);
							// extract the day as its three letter initals
							$d = substr($td,2,5);
							// get the number of times shown by counting occurrence of ":"
							$numTimes = substr_count($td,":");
							$start = 0;
							
							// loop through and extract the time, convert it to timestamp and insert the data into database
							for($j=0;$j < $numTimes;$j++){
								$pos = strpos($td,":",$start);
								$insrt = $pos-2;
								$start = $pos+1;
								$time = substr($td,$insrt,5);
								$timestamp = strtotime($time);
								$insert = "INSERT INTO cinemas (name, film, day, time) VALUES ('$cinema', '$film_title', '$d', $timestamp)";
								mysqli_query($db_server, $insert);
							}
						}
						// for each day extract the day and month initials and convert it to its full name
						for($i = 0; $i < count($days[1]); $i++) {
							$td = strip_tags($days[1][$i]);
							$d = substr($td,0,12);
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
								$pos = strpos($td,":",$start);
								$insrt = $pos-2;
								$start = $pos+1;
								$time = substr($td,$insrt,5);
								$timestamp = strtotime($time);
								// if day parameter matches the day for the film 
								if(substr($td,2,3) == substr($day, 0,3)){
									$insert = "INSERT INTO cinemas (name, film, day, time) VALUES ('$cinema', '$film_title', '$d', $timestamp)";
									mysqli_query($db_server, $insert);
								}
							}
						}
						// set start position to one place after the position of the search string 
						$start = $len+1;
						// find next position of search string 
						$len = strpos($f,"Email to a friend",$start);
					}
				}

				// if order by title 
				if($order == "title"){
					// if selected day is today
					if($date['weekday'] == $day){
						// select films being shown today, based on time parameters 
						$select = "SELECT film, time FROM cinemas WHERE day = 'Today' AND time BETWEEN '$starttime' AND '$endtime'";
						// heading for the data which includes the time parameters in Hour:minute format 
						$heading = "Films on today in ".$cinema." between ".date('H:i', $starttime)." and ".date('H:i', $endtime);
						$q = mysqli_query($db_server, $select);
					}
					// else selected day is not today
					else{
						// select films being shown on the day parameter, based on time parameters
						$select ="SELECT film, time FROM cinemas WHERE day LIKE '%$day%' AND time BETWEEN '$starttime' AND '$endtime'";
						$heading = "Films on ".$day." in ".$cinema." between ".date('H:i', $starttime)." and ".date('H:i', $endtime);
						$q = mysqli_query($db_server, $select);
					}
				}
				// else order by time
				else{
					if($date['weekday'] == $day){
						$select = "SELECT film, time FROM cinemas WHERE day = 'Today' AND time BETWEEN '$starttime' AND '$endtime' ORDER BY time, film";
						$heading = "Films on today in ".$cinema." between ".date('H:i', $starttime)." and ".date('H:i', $endtime);
						$q = mysqli_query($db_server, $select);
					}
					else{
						$select ="SELECT film, time FROM cinemas WHERE day LIKE '%$day%' AND time BETWEEN '$starttime' AND '$endtime' ORDER BY time, film";
						$heading = "Films on ".$day." in ".$cinema." between ".date('H:i', $starttime)." and ".date('H:i', $endtime);
						$q = mysqli_query($db_server, $select);
					}				
				}
				// print the heading and create table structure. Loop through MySQL results set and print data within the cells 
				?>
				<h2> <?php echo $heading; ?> </h2>
                <table>
                    <tr>
                        <th>Film</th><th>Time</th>
                    </tr>
                    <?php
                        while($row = mysqli_fetch_array($q)){
                    ?>
                    <tr>
                        <td> <?php echo $row[0] ?> </td>
                        <td> <?php echo date('H:i', $row[1]) ?> </td>
                    </tr>
                    <?php
                       }
                    ?>
                </table>
                <?php
            }
				?>
		</div>
	</body>
</html>