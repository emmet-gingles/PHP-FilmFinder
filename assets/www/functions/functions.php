<?php 
	// function that uses a list of URLs as parameter and reads data from the webpages and saves it to database
	function getFilms($urls){
		// access conn variable globally for database connection
		global $conn;		
		// loop through each URL to explore its webpage 
		foreach ($urls as $url){		
			// get the contents of the webpage
			$file = file_get_contents($url);
			// split the URL using forward slash
			$pieces = explode("/", $url);
			// get the last index which is the name of the cinema
			$cinema = $pieces[count($pieces)-1];
			// replace all hyphens with spaces and capitalize all words
			$cinema = ucwords(str_replace("-", " ", $cinema));
			// now replace certain words with others to make them consistent with the cinema names
			$cinema = str_replace("Odeon", "ODEON", $cinema);
			$cinema = str_replace("Imc", "IMC", $cinema);
			$cinema = str_replace("Liffordstrabane", "Lifford/Strabane", $cinema);	
			$cinema = str_replace("Cl ", "C&L ", $cinema);	
			// some cinemas may contain a comma in their name so here is a list of what these cinemas start with		
			$cin_names = array("Gate Multiplex", "Movie House", "Ormond Cineplex", "The Arc Cinema", "Eclipse Cinemas", 
			"Phoenix Cinema", "The Ritz", "The Reel Picture", "Park Cinema");
			// loop through the list of cinema names
			foreach ($cin_names as $cin){
				// if cinema starts with the current list item then add a comma after it eg. "The Arc Cinema Drogheda" becomes 
				// "The Arc Cinema, Drogheda"
				if(substr($cinema, 0, strlen($cin)) == $cin ){
					$cinema = str_replace($cin, $cin . ",", $cinema);
					break;
				}
			}
			
			// get the position of the heading with the cinema name and set that as the starting position
			$start = stripos($file, "<h1>". $cinema . "</h1>");
			// match the titles of all films on the page
			preg_match_all('/<h3>(.*?)[^<]<\/h3>/s', $file, $titles);
			// the number of films on page
			$num_films = count($titles[1]);
			// loop through the required number of times to extract each film title 
			for($i=0;$i< $num_films;$i++) {
				// get the text of the film title
				$title = $titles[1][$i];
				// get the position of the film title within the webpage 
				$start = strpos($file, $title, $start);
				// remove leading or trailing whitespace 
				$title = trim(strip_tags($title));

				// if it is not the last film on the page 
				if($i < $num_films-1){
					// get the position of the next film title and set that as the end position
					$end = strpos($file, $titles[1][$i+1], $start);
					// use start and end to determine the section of the page will be used for searching dates and times
					$section = substr($file, $start, $end-$start);
				}
				// else it is the last film so dont specify an end 
				else {
					$section = substr($file, $start);
				}
				
				// search the section for all the dates
				preg_match_all('/<p class="date-header">(.*?)<\/p>/s', $section, $dates);
				// search the section for the running time
				preg_match('/<p><strong>Running time:<\/strong>(.*?)<\/p>/s', $section, $runtime);
				
				// get the text from the runtime and extract the integer value to get the number. If no integer value or no match 
				// was found then set variable to NULL 
				if(count($runtime) > 0){
					$runtime = trim(strip_tags($runtime[1]));
					$runtime = intval($runtime);
					if($runtime == 0){
						$runtime = "NULL";
					}
				}
				else{
					$runtime = "NULL";
				}
				
				// to get its rating, count the number of full and half stars and add them together
				$fullstars = substr_count($section, 'class="icon-star-full' );
				$halfstars = substr_count($section, 'class="icon-star-half' );
				$totalstars = $fullstars + ($halfstars/2);
				if($totalstars == 0){
					$totalstars = "NULL";
				}
				
				// search table to see if the film has already been added, if so then retrieve the filmId		
				$select = mysqli_query($conn, "SELECT filmId FROM Films WHERE film = '$title'");
				if(mysqli_num_rows($select)){
					$filmId = mysqli_fetch_row($select)[0];
				}
				// else it hasnt been added so insert it and retrieve the insert ID
				else{				
					$insert = "INSERT INTO Films (film, runtime, avg_rating) 
					VALUES ('$title', $runtime, $totalstars)";				
					if(mysqli_query($conn, $insert)){
						$filmId = mysqli_insert_id($conn);
					}
					else{
						die("Error inserting data " . mysqli_error($conn));
					}				
				}
				
				// arrays for days and months with their keys and values
				$days = ["Mon" => "Monday", "Tue" => "Tuesday", "Wed" => "Wednesday", "Thu" => "Thursday", "Fri" => "Friday", 
				"Sat" => "Saturday", "Sun" => "Sunday"];
				$months = ["Jan" => "January", "Feb" => "February", "Mar" => "March", "Apr" => "April",  "Jun" => "June",
				"Jul" => "July", "Aug" => "August", "Sep" => "September", "Oct" => "October", "Nov" => "November", "Dec" => "December"];

				// the number of dates 
				$num_dates = count($dates[1]);
								
				// loop through the required number of times to get each date 
				for($j=0; $j< $num_dates; $j++) {	
					// get the text from the current iteration
					$date = $dates[1][$j];	
					// if the date starts with "Today" then get the text after first five characters
					if(strpos($date, "Today")){
						$date = trim(strip_tags(substr($date, strpos($date, "Today")+5)));
					}
					// if it starts with "Next" then get the text after the first four characters
					else if(strpos($date, "Next")){
						$date = trim(strip_tags(substr($date, strpos($date, "Next")+4)));
					}
					// else we want all the text 
					else{
						$date = trim(strip_tags($date));
					}
							
					// set the starting position for the date
					$date_start = strpos($file, $date, $start); 
					
					// if it is not the last date then find the position of the next date and use that as the end position for
					// the page section
					if($j< $num_dates-1){
						$date_end = strpos($file, $dates[1][$j+1], $date_start);
						$section = substr($file, $date_start, $date_end - $date_start);
					}
					// if it is not the last film on the page then find the position of the next film and use that as the end 
					// position for the page section 
					else if($i< $num_films-1) {
						$date_end = strpos($file, $titles[1][$i+1], $start);
						$section = substr($file, $date_start, $date_end - $date_start);
					}
					// else we are on both the last film and the last date so dont specify an end position 
					else{
						$section = substr($file, $date_start);
					}
					
					// get all the times from whithin the page section 
					preg_match_all('/<div class="single-time-item">(.*?)<\/div>/s', $section, $times);
					// number of times
					$num_times = count($times[1]);

					// loop through the list of days and replace the key with its value 
					foreach($days as $init => $day){
						$date = str_replace($init, $day, $date);
					}
					// loop through the list of months and replace the key with its value 
					foreach($months as $init => $month){
						$date = str_replace($init, $month, $date);
					}
					
					// loop through the required number of times to get each time 
					for($k=0;$k< $num_times;$k++) {
						// get the text from the time 
						$timeStr = trim(strip_tags($times[1][$k]));
						// retrieve first five characters from the time string
						$time = substr($timeStr, 0, 5);
						// convert time to an integer 
						$timestamp = strtotime($time);
						// everything after the fifth character is the screen type
						$screen_type = trim(substr($timeStr, 5));
						// insert all the variables including the filmId into the Cinemas table 
						$insert = "INSERT INTO Cinemas (cinema, date, time, screen_type, filmId) 
						VALUES ('$cinema', '$date', $timestamp, '$screen_type', $filmId )";
						if(!mysqli_query($conn, $insert)){
							die ("Error inserting data " . mysqli_error($conn));
						}		
					}	// end for num_times
				}	// end for num_dates								
			}	// end for num_films 
		}	// end foreach
	}	// end function 
		
	// get data from database and display it to the user
	function showResults($order, $date, $starttime, $endtime){
		// access conn variable globally for database connection
		global $conn;		
		// get a list of each cinema 
		$cinemaNames = mysqli_query($conn, "SELECT DISTINCT cinema FROM cinemas");
		// loop through results set 
		while($cinemas = mysqli_fetch_array($cinemaNames)){
			// save the cinema to a variable 
			$cinema = $cinemas[0];		
			// if order by title then construct the query to join the two tables where the criteria matches the parameters and order results by title 
			if($order == "title"){
				$select = "SELECT film, date, time, screen_type, avg_rating, runtime FROM Cinemas RIGHT JOIN films ON cinemas.filmId = films.filmId WHERE cinema = '$cinema' AND date LIKE '%$date%' AND time BETWEEN '$starttime' AND '$endtime'";	
				$buttonText = "Order by time";
				$buttonValue = "time";
			}
			// else order by time so construct the query to join the two tables where the criteria matches the parameters and order results by time
			else{
				$select = "SELECT film, date, time, screen_type, avg_rating, runtime  FROM Cinemas RIGHT JOIN films ON cinemas.filmId = films.filmId WHERE cinema = '$cinema' AND date LIKE '%$date%' AND time BETWEEN '$starttime' AND '$endtime' ORDER BY time, film";
				$buttonText = "Order by title";
				$buttonValue = "title";
			}
			
			// run the query to return the appropriate rows
			$res = mysqli_query($conn, $select);		
			// heading to show a summary of the search criteria 
			$heading = $cinema . "<br>". "Films on ". date_format(date_create($date), "l d F") ." between ".date('H:i', $starttime).
			" and ".date('H:i', $endtime);					
		?>
		<div>
			<form method="post">
				<input type="hidden" name="date_format" value=<?php echo $date; ?> />
				<input type="hidden" name="start_time" value=<?php echo $starttime; ?> />
				<input type="hidden" name="end_time" value=<?php echo $endtime; ?> />
				<label>Order results by: <input type="submit" name="orderResults" value=<?php echo $buttonValue; ?> /></label>
			</form>
			
			<h2><?php echo $heading ?></h2>
			<table>
				<tr>
					<th>Film</th>
					<th>Date</th>
					<th>Time</th> 
					<th>Screen type</th>
					<th>Rating</th>
					<th>Runtime</th>
				</tr>
				<?php
					// loop through the results and display each column
					while($row = mysqli_fetch_array($res)){
				?>
						<tr>
							<td> <?php echo htmlspecialchars_decode($row[0]); ?> </td>
							<td> <?php echo $row[1]; ?> </td>
							<td> <?php echo date('H:i', $row[2]); ?> </td>
							<td> <?php echo $row[3]; ?> </td>
							<td> <?php echo $row[4]; ?> </td>
							<td> <?php if($row[5] !== null) { 
									   echo $row[5] . " minutes";
								} ?> 
							</td>
						</tr>
				<?php
					}	// end while row
				?>
			 </table>
		</div>	
	<?php
		}	// end while cinemas
	}	// end function

?>