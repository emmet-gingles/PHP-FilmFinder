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
		// use file for connecting to database
		require_once 'db/login.php';
		// use file for calling functions
		require 'functions/functions.php';
		// set timezone to Dublin
		date_default_timezone_set("Europe/Dublin");

		// use to calculate time difference in seconds since last access. Default value is 0
		$time_diff = 0;

		// if last_access cookie is set then access its value 
		if(isset($_COOKIE['last_access'])){
			$last_access = $_COOKIE['last_access'];
			// get the current time as a number
			$current_time = strtotime(date("h:i:sa"));
			// get the difference between the current time and last access 
			$time_diff = $current_time - $last_access;
			// set cookie value to current time
			setcookie('last_access', $current_time);
		}
		// else cookie is not set so set it to current time
		else {
			$current_time = date("h:i:sa");
			setcookie('last_access', strtotime($current_time));
		}

		// if a post request to change the results order is made
		if(isset($_POST["orderResults"])){	
			// get the order from submit button
			$order = $_POST["orderResults"];
			// if all three form fields are set then assign them to variables
			if(isset($_POST["date_format"]) && isset($_POST["start_time"]) && isset($_POST["end_time"])){
				$date =  $_POST["date_format"];
				$start =  $_POST["start_time"];
				$end =  $_POST["end_time"];
				// format date to display the day, date and month
				$date = date_format(date_create($date), "l d M");
				// MySQL connection object using parameters from connections file
				$conn = mysqli_connect($db_hostname, $db_username, $db_password, $db_name);
				// call function to display results in new order
				showResults($order, $date, $start, $end);
				// close MySQL connection
				mysqli_close($conn);
			}
		}

		// if a post request is made from form on index page. Run only if all five form fields are set
		else if(isset($_POST["cinemas"]) && isset($_POST["days"]) && isset($_POST["starttimes"]) && isset($_POST["endtimes"]) && isset($_POST["order"]) ){ 			
			// variables set by form fields
			$cinema = $_POST["cinemas"];
			$daynum = $_POST["days"];
			$starttime = $_POST["starttimes"];
			$endtime = $_POST["endtimes"];
			$order = $_POST["order"];
			
			// get the date by adding whatever number day onto today, then display it in the format day, date, month	
			$date = date_format(date_create('today +'. $daynum .' day'), "l d M");
			
			// convert times from strings to timestamps
			$starttimestamp = strtotime($starttime);
			$endtimestamp = strtotime($endtime);

			// boolean variable to determine whether or not the cinema has changed from last selection. Default value is false
			$newCinema = false;
			
			// if last_cinema cookie is set then access its value 
			if(isset($_COOKIE['last_cinema'])){
				$last_cinema = $_COOKIE['last_cinema'];
				// if the current cinema and last cinema are different then set the cookie value to the current cinema and change boolean to true
				if($cinema != $last_cinema){
					setcookie('last_cinema', $cinema);
					$newCinema = true;
				}
			}
			// else cookie is not set so set it to the current cinema
			else{
				setcookie('last_cinema', $cinema);
				$newCinema = true;
			}
			
			// run only if a new cinema is selected or the time difference is greater than 10 minutes
			if(($newCinema) || ($time_diff > 600)){	
				// try to connect using connection parameters
				$db_server = mysqli_connect($db_hostname,$db_username,$db_password); 
				// terminate if connection unsuccessful
				if($db_server) {
					// create database if it does not exist 
					$create_db = "CREATE DATABASE IF NOT EXISTS ". $db_name ;
					if(mysqli_query($db_server, $create_db)){
						// close existing MySQL connection
						mysqli_close($db_server);
						// connect to the database
						$conn = mysqli_connect($db_hostname,$db_username,$db_password,$db_name); 
						if($conn){							
							// drop the tables if they already exist 
							if((mysqli_query($conn, "DROP TABLE IF EXISTS Cinemas")) && (mysqli_query($conn, "DROP TABLE IF EXISTS Films"))){
								
								// create the Films table 
								$create_table = "CREATE TABLE Films(filmId INTEGER NOT NULL AUTO_INCREMENT, film VARCHAR(50) NOT NULL, 
								runtime INTEGER NULL, avg_rating FLOAT NULL, PRIMARY KEY (filmId) );";
								if(mysqli_query($conn, $create_table)){
									// create the Cinemas table with foreign key constraint to Films table 
									$create_table = "CREATE TABLE Cinemas(id INTEGER NOT NULL AUTO_INCREMENT, cinema VARCHAR(50) NOT NULL, 
									date VARCHAR(50) NOT NULL, time INTEGER NOT NULL, screen_type VARCHAR(20) NULL, filmId INTEGER,
									PRIMARY KEY (id), FOREIGN KEY (filmId) REFERENCES Films(filmId) );";
									if(mysqli_query($conn, $create_table)){
										// file that will be used to get data
										$file = "files/towns.txt";
										
										// first check that file does exist
										if (file_exists($file)) {
											// array to store the list of cinemas 
											$cinemalist = array();
											// split data by each line
											$lines = explode("\n", file_get_contents($file));
											// loop through each line 
											foreach($lines as $l){
												// split line into two parts - the town and the URL
												$data = explode(";", $l);
												$town = trim($data[0]);
												// remove leading and trailing whitespaces for the URL
												$url = $data[1];
												// some towns have multiple cinemas so split each URL by comma
												$allUrls = explode(",", $url);
												// loop through each URL and append it and its town to list of cinemas 
												foreach($allUrls as $url){
													array_push($cinemalist, array($town, trim($url) ));
												}
											}
											// list to store URLs for selected town 
											$urls = array();
											// loop through list of cinemas 
											foreach ($cinemalist as $cin){	
												// if the cinema name is the same as the selected cinema then add the URL to the array
												if($cin[0] == $cinema){
													array_push($urls, $cin[1]);
												}
											}
											
											// call function using the list of URLs 
											getFilms($urls);
											// call function using input parameters
											showResults($order, $date, $starttimestamp, $endtimestamp);
											mysqli_close($conn);
											
										}	// end if file exista
										else{
											die("File $file not found");											
										}	
									}	// end create table Cinemas		
									else {	
										die("Error creating table Cinemas " . mysqli_error($conn));													
									}
								}	// end create table Films
								else{
									die("Error creating table Films " . mysqli_error($conn));
								}					
							}	// end drop table		
							else{
								die("Error deleting table " . mysqli_error($conn));
							}
						}	// end if conn
						else{
							die("Error connecting to MySQL database" . mysqli_connect_error());
						}					
					}	// end if create database
					else{
						die("Error creating database " . mysqli_error($conn));	
					}
				}	// end if db_server
				else{
					die("Error connecting to MySQL " . mysqli_connect_error());
				}
			}	// end if new cinema
			
			// else we dont want to scrape the webpage, just query the database and show the results 
			else{
				$conn = mysqli_connect($db_hostname, $db_username, $db_password, $db_name); 
				showResults($order, $date, $starttimestamp, $endtimestamp);
				mysqli_close($conn);
			}	
		}
		?>
		</div>
	</body>
</html>