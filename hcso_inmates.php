<?php
require_once('credentials.php');
/*
*	Get all of the current inmates via the btnViewAll object in the DOM
*/

$dataArray = array();
$id_matches = array();

// set the inmate active status to 0
set_inmates_as_inactive();

$agent = "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.4) Gecko/20030624 Netscape/7.1 (ax)"; 
$url = 'http://apps.hcso.org/inmates.aspx';
$ckfile = tempnam ("/var/tmp", "CURLCOOKIE");

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$data['btnViewAll'] = "View All";
curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($data) );
//curl_setopt($ch, CURLOPT_POSTFIELDS, $data);


$output=@curl_exec($ch);
$info = @curl_getinfo($ch);

if(curl_errno($ch)){
  echo 'Curl error: ' . curl_error($ch);
}
// get all of the inmate ID's
$result = preg_match_all('/{\"value\"\:\"(.*?)\"\}/', $output, $id_matches);
if ($result) {

	// walk through the ID's and get the HTML files
	foreach($id_matches[1] as $id) {
		$url_template = "http://apps.hcso.org/InmateDetail.aspx?ID=";
		$folder = "inmates/";
		$output_file = $id . ".html";
		$url = $url_template . $id;

		curl_setopt($ch, CURLOPT_URL, $url);
		$output=@curl_exec($ch);

		//file_put_contents($folder . $output_file, $output);
		process_inmate_details($output);
		write_inmate_data_to_database($dataArray);
	}
} else {
	die("Unable to get the inmate ID's from the website");
}

do_population_analysis();

curl_close($ch);

return;

function process_inmate_details($html) {
	global $dataArray;

	$jms_number = null;
	$name = null;
	$control_number = null;
	$dob = null;
	$race = null;
	$housing_location = null;
	$sex = null;
	$admitted_date = null;
	$image = null;

	$charges = array();

	//<input name="lbJms" type="text" id="lbJms" readonly="true" style="border-style: none; border-color: inherit; border-width: medium; width: 48%; text-align: center;" value="JMS Number : 1685615" />
	$jms_number_result = preg_match('/value=\"JMS Number : (.*?)\" \/>/s', $html, $match);

	if ($jms_number_result) {
		$jms_number = $match[1];

		$name_result = preg_match('/value=\"Inmate : (.*?)\" \/>/s', $html, $match);
		$name = $match[1];

		$control_number_result = preg_match('/value=\"Control Number : (.*?)\" \/>/s', $html, $match);
		$control_number = $match[1];

		$housing_location_result = preg_match('/value=\"Housing Location : (.*?)\" \/>/s', $html, $match);
		$housing_location = $match[1];

		$dob_result = preg_match('/value=\"Date of Birth : (.*?)\" \/>/s', $html, $match);
		$dob = $match[1];

		$sex_result = preg_match('/value=\"Sex : (.*?)\" \/>/s', $html, $match);
		$sex = $match[1];

		$race_result = preg_match('/value=\"Race : (.*?)\" \/>/s', $html, $match);
		$race = $match[1];

		$admitted_date_result = preg_match('/value=\"Admitted Date : (.*?)\" \/>/s', $html, $match);
		$admitted_date = $match[1];

		$image_result = preg_match('/<img id=\"imgInmate\" src=\"(.*?)\" width\=\"274\" style\=\"top\: 0px\; left\: 64px\;\" \/>/', $html, $match);
		if ($image_result) {
			$image = $match[1];
		} else {
			$image = null;
			echo "No image found...\n\n";
		}

		echo "JMS: $jms_number" . PHP_EOL;
		echo "Name: $name" . PHP_EOL;
		echo "Control #: $control_number" . PHP_EOL;
		echo "Housing Loc: $housing_location" . PHP_EOL;
		echo "DOB #: $dob" . PHP_EOL;
		echo "Sex: $sex" . PHP_EOL;
		echo "Race: $race" . PHP_EOL;
		echo "Admitted Date: $admitted_date" . PHP_EOL;

		$dataArray['jms_number'] = $jms_number;
		$dataArray['fullname'] = $name;
		$dataArray['control_number'] = $control_number;
		$dataArray['housing_location'] = $housing_location;
		$dataArray['dob'] = $dob;
		$dataArray['sex'] = $sex;
		$dataArray['race'] = $race;
		$dataArray['admitted_date'] = convert_date_to_mysql($admitted_date);
		$dataArray['image'] = $image;

		$dataArray['charges'] = array();

		$charges = array();

		$result = preg_match_all('/<tr class=\"(rgRow|rgAltRow)(.*?)>(.*?)<\/tr>/s', $html, $matches);
		if ($result) {
			foreach ($matches[3] as $row) {
				process_inmate_row_details($row);
			}
		} else {
			echo "No matches found in inmate file\n";
		}
		//print_r($dataArray);
	}
}

function process_inmate_row_details($row) {
	global $dataArray;

	//$dataArray['charges'] = array();

	/*
	*	Must have a Common Pleas, Municipal, or Other Case value in the table
	*	Must also have the Court Date, ORC Code, Description, bond type, bond amount, disposition, fine, comments, 
	*	projected release and holder values
	*	--Missing either the first set or any of the last set should create an error
	*/

	$result = preg_match_all('/<td>(.*?)<\/td>/', $row, $matches);
	if ($result) {
		$rowData = $matches[1];
		//foreach ($matches as $rowData) {
			//print_r($rowData);
			$charge = array();
			if ($rowData[4] != "NONE") {
				$common_pleas_case = $rowData[0];
				$municipal_case = $rowData[1];
				$other_case = $rowData[2];
				$court_date = convert_date_to_mysql($rowData[3]);
				$section = $rowData[4];
				$description = $rowData[5];
				$bond_type = $rowData[6];
				$bond_amount = $rowData[7];
				$disposition = $rowData[8];
				$fine = $rowData[9];
				$comments = $rowData[10];
				$projected_release_date = convert_date_to_mysql($rowData[11]);
				$holder = $rowData[12];

				// clean the data, removing $nbsp; and extra whitespace
				if ($common_pleas_case == '&nbsp;') {
					$common_pleas_case = null;
				}

				if ($municipal_case == '&nbsp;') {
					$municipal_case = null;
				}

				if ($other_case == '&nbsp;') {
					$other_case = null;
				}

				if ($description != null) {
					$description = preg_replace('!\s+!', ' ', $description);
				}

				if ($comments == '&nbsp;') {
					$comments = null;
				}

				if ($bond_type == '&nbsp;') {
					$bond_type = null;
				}

				if ($bond_amount == '&nbsp;') {
					$bond_amount = null;
				}

				if ($projected_release_date == '&nbsp;') {
					$projected_release_date = null;
				}

				// a case # is required
				if ($common_pleas_case == null && $municipal_case == null && $other_case == null) {
					// die on this error
					echo "No valid case numbers found for: " . $dataArray['fullname'] . PHP_EOL;
				} else {
					$charge['common_pleas_case'] = $common_pleas_case;
					$charge['municipal_case'] = $municipal_case;
					$charge['other_case'] = $other_case;
					$charge['court_date'] = $court_date;
					$charge['orc_code'] = $section;
					$charge['description'] = $description;
					$charge['bond_type'] = $bond_type;
					$charge['bond_amount'] = $bond_amount;
					$charge['disposition'] = $disposition;
					$charge['fine'] = $fine;
					$charge['comments'] = $comments;
					$charge['projected_release_date'] = $projected_release_date;
					$charge['holder'] = $holder;

					//print_r($charge);
					//TODO: array_push($dataArray['charges'], $charge);
					array_push($dataArray['charges'], $charge);
					//$dataArray['charges'] = $charge;
				}
			//}
		}
	} else {
		echo "No matches found on inmate row\n";
	}
}

function convert_date_to_mysql($dateString) {
	// 9/30/2019 11:20:50 PM --> Y-m-d H:i:s, assuming EST but convert to GMT
	$year = null;
	$month = null;
	$day = null;
	$hour = null;
	$minute = null;
	$second = null;
	$period = null;

	if ($dateString == "&nbsp;") {
		return '1900-01-01 00:00:00';
	}

	//echo "Date string(269): $dateString\n";

	// split the string by spaces
	$date_time_parts = explode(" ", $dateString);
	$date_parts = explode("/", $date_time_parts[0]);
	$time_parts = explode(":", $date_time_parts[1]);

	// get the month
	$month = $date_parts[0];

	// get the day
	$day = $date_parts[1];

	// get the year
	$year = $date_parts[2];

	// get the hour
	$hour = $time_parts[0];

	// get the minute
	$minute = $time_parts[1];

	// get the second
	$second = $time_parts[2];

	// get the period
	$period = $date_time_parts[2];

	// set the timezone
	$timezone = "EST";

	// convert
	$datetime = gmdate('Y-m-d H:i:s', strtotime($dateString));

	return $datetime;
}

function do_population_analysis() {
	global $hostname, $username, $password, $database;
	// do an analysis of the inmate population at the time of the parsing
	// to determine if there's a trend in what age/race/offenses are more likely
	// to be released

	$conn = mysqli_connect($hostname, $username, $password, $database);
	if (!$conn) {
		die("Connection to MySQL Server on {$hostname} failed\n");
	}

	$sql = "insert into dm_inmate_population (date, population, admitted_date, sex, race)
			select left(now(), 10), count(*), left(admitted_date, 10), sex, race
			from inmate_information
			group by left(admitted_date, 10), sex, race;";

	if (mysqli_query($conn, $sql)) {
		echo "Datamart updated successfully\n\n";
	} else {
		echo "Error: " . $sql . PHP_EOL . mysqli_error($conn);
	}
}

function set_inmates_as_inactive() {
	global $hostname, $username, $password, $database;
	
	$now = date("Y-m-d H:i:s");
	$last_inmate_id = null;

	$conn = mysqli_connect($hostname, $username, $password, $database);
	if (!$conn) {
		die("Connection to MySQL Server on {$hostname} failed\n");
	}

	// set all inmates as inactive, then set them as active if they're still in the data
	$sql = "update inmate_information set active = 0;";

	if (mysqli_query($conn, $sql)) {
		echo "Inmates set to inactive successfully\n\n";
	} else {
		echo "Error: " . $sql . PHP_EOL . mysqli_error($conn);
	}
}

function convert_datetime_to_mysql($dateTimeString) {
	// mm/dd/yyyy hh:mm:ss tz --> yyyy--mm--dd hh:ii:ss @24hours
}

function write_inmate_data_to_database($dataArray) {
	global $hostname, $username, $password, $database;

	$now = date("Y-m-d H:i:s");
	$last_inmate_id = null;

	$conn = mysqli_connect($hostname, $username, $password, $database);
	if (!$conn) {
		die("Connection to MySQL Server on {$hostname} failed\n");
	}

	// TODO: insert the inmate data
	$sql_template = "insert into inmate_information 
		(date_created, date_modified, fullname, jms_number, control_number, sex, admitted_date, race, housing_location, dob, image, active) 
		values 
		('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
		on duplicate key update
		date_modified = '$now',
		active = 1
		";
	$sql = sprintf(
		$sql_template,
		$now,
		$now,
		$dataArray['fullname'],
		$dataArray['jms_number'],
		$dataArray['control_number'],
		$dataArray['sex'],
		$dataArray['admitted_date'],
		$dataArray['race'],
		$dataArray['housing_location'],
		$dataArray['dob'],
		$dataArray['image'],
		1
	);
	//echo $sql . PHP_EOL;

	if (mysqli_query($conn, $sql)) {
		$last_inmate_id = mysqli_insert_id($conn);
		echo "New inmate record created successfully. Last ID: $last_inmate_id\n";
	} else {
		echo "Error: " . $sql . PHP_EOL . mysqli_error($conn);
	}

	// Insert the charges
	foreach ($dataArray['charges'] as $charge) {
		$sql_template = "insert into inmate_cases
		(inmate_id, common_pleas, municipal, other, court_date, orc_code, description, bond_type, bond_amount, disposition, fine, comments, projected_release, holder, date_created, date_modified)
		values
		('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
		on duplicate key update
		date_modified = '$now'
		";

		$sql = sprintf(
			$sql_template,
			$last_inmate_id,
			$charge['common_pleas_case'],
			$charge['municipal_case'],
			$charge['other_case'],
			$charge['court_date'],
			$charge['orc_code'],
			$charge['description'],
			$charge['bond_type'],
			$charge['bond_amount'],
			$charge['disposition'],
			$charge['fine'],
			$charge['comments'],
			$charge['projected_release_date'],
			$charge['holder'],
			$now,
			$now
		);
		//echo $sql . PHP_EOL;

		if (mysqli_query($conn, $sql)) {
			$last_case_id = mysqli_insert_id($conn);
			echo "New inmate case record created successfully. Last case ID: $last_case_id\n";
		} else {
			echo "Error: " . $sql . PHP_EOL . mysqli_error($conn);
		}
	}

	mysqli_close($conn);
}

?>
