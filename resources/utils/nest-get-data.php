<?php
// The Nest-Extended Configuration
require_once(__DIR__ . '/../config.php');

// The Nest API Class file
require_once(__DIR__ . '/../libs/nest/nest.class.php');

if (defined('STDIN')) {
  $datatype = $argv[1];
} else { 
  $datatype = $_GET['datatype'];
}


//Connect to the Database
$con = new mysqli($hostname, $username, $password, $dbname);
if ($con->connect_error) {
	trigger_error('Database connection failed: ' . $con->connect_error, E_USER_ERROR);
}

//Create a new Nest Object
$nest = new Nest();

//Used to return current location, away status, and outside weather
$locations = $nest->getUserLocations();

//Postal Code formatting
if (in_array(date_default_timezone_get(), $us_timezones)) {
	$postal_code = $locations[0]->postal_code; 
} else {
	$postal_code = substr($locations[0]->postal_code, 0, -3) . " " . substr($locations[0]->postal_code, -3);
}


//Get current Nest data from server and save into an array. This should be set to a 5 minute cron job. I use curl to execute the php. 
if ($datatype === 'current'){
	//Used to return current inside temperature, current inside humidity, current mode, target temperature, time to target temperature, current heat state, current ac state
	$infos = $nest->getDeviceInfo();
print_r($infos);//print variable to  browser for debug
// for some reason Aux heat is returned as Celcius. Need to convert to F (if desired). Otherwise comment this out and log aux_threshold. 
$aux_converted=$infos->current_state->aux_threshold * 1.8 + 32;

	//If the target temperature is an array, we need to deal with that.
	if (strpos($infos->current_state->mode,'heat') !== false) {
		if (is_array($infos->target->temperature)) {
			$low_target_temp = $infos->target->temperature[0];
			$high_target_temp = null;
		} else {
			$low_target_temp = $infos->target->temperature;
			$high_target_temp = null;
		}
	} elseif(strpos($infos->current_state->mode,'cool') !== false) {
		if (is_array($infos->target->temperature)) {
			$low_target_temp = null;
			$high_target_temp = $infos->target->temperature[1];
		} else {
			$low_target_temp = null;
			$high_target_temp = $infos->target->temperature;
		}
	} elseif(strpos($infos->current_state->mode,'range') !== false) {
		$low_target_temp = $infos->target->temperature[0];
		$high_target_temp = $infos->target->temperature[1];
	}

//-------------------------------------------------------------------------------------------------------------------
//The current outside temperature and humidity often returns a nonsense value, zero,  (if the service is not able to be contacted). This ruins the continuity of the data.
//---------------------------------------Temp--------------------------------------------------------------------------
//get last recorded temperture from database
$sql_1 = "SELECT outside_temp FROM nest ORDER BY log_datetime DESC LIMIT 1";
$query_last = $con->query($sql_1);	

//convert sql object into array that we can reference
while ($r = $query_last->fetch_assoc()) {
	foreach ($r as $key => $value) {
		$data_last[$key][] = ($value); // Append row
	}
}
$query_last->close();

//print_r($data_last["outside_temp"][0]);//print last temp value to browser for debug purposes


//check if last temperature was exactly zero.  
if ($locations[0]->outside_temperature == 0){
//replace current value with previous value. Introduces small error, but less than introducing nonsense values
$locations[0]->outside_temperature = $data_last["outside_temp"][0];
}

//---------------------------------------Humidity--------------------------------------------------------------------------
$sql_2 = "SELECT outside_humidity FROM nest ORDER BY log_datetime DESC LIMIT 1";
$query_lasth = $con->query($sql_2);	

//convert sql object into array that we can reference
while ($r = $query_lasth->fetch_assoc()) {
	foreach ($r as $key => $value) {
		$data_lasth[$key][] = ($value); // Append row
	}
}
$query_lasth->close();

//print_r($data_lasth["outside_humidity"][0]);//print last humidity value to browser for debug purposes


//logical jump value. This is the number of degrees the reading needs to change since the last recording instance to trigger the filter. 
$jumph=15;

//get delta between current value and previous value

$deltah= $locations[0]->outside_humidity - $data_lasth["outside_humidity"][0];
//print_r($deltah);//print value for debug purposes

//check if logical delta value is exceeded and that this is not a new database. 
if (abs($deltah) > $jumph and $data_lasth["outside_humidity"][0] != NULL ){
//replace current value with previous value. Introduces small error, but less than introducing nonsense values
$locations[0]->outside_humidity = $data_lasth["outside_humidity"][0];
}
//-------------------------------------------------------------------------------------------------------------------

	
	//Insert Current Values into Nest Database Table
//if you add addional values to the NEST API, you need to also add them here so that they will be logged in the database. 
// you also need to add the correctly named column to the database structure FIRST, or the php script will return an error. 

	$sql = 'INSERT INTO nest (log_datetime, location, outside_temp, outside_humidity, away_status, leaf_status, current_temp, current_humidity, temp_mode, low_target_temp, high_target_temp, time_to_target, target_humidity, heat_on, humidifier_on, ac_on, fan_on, battery_level, is_online, alt_heat, aux_threshold, hvac_wires) VALUES (NOW(), "'.$postal_code.'", "'.$locations[0]->outside_temperature.'", "'.$locations[0]->outside_humidity.'", "'.$locations[0]->away.'", "'.$infos->current_state->leaf.'", "'.$infos->current_state->temperature.'", "'.$infos->current_state->humidity.'", "'.$infos->current_state->mode.'", "'.$low_target_temp.'", "'.$high_target_temp.'", "'.$infos->target->time_to_target.'","'.$infos->target->humidity.'","'.$infos->current_state->heat.'","'.$infos->current_state->humidifier.'","'.$infos->current_state->ac.'","'.$infos->current_state->fan.'","'.$infos->current_state->battery_level.'","'.$infos->network->online.'", "'.$infos->current_state->alt_heat.'","'.$aux_converted.'","'.$infos->current_state->hvac_wires.'")';
	$result = $con->query($sql) or trigger_error('SQL: ' . $sql . ' Error: ' . $con->error, E_USER_ERROR);

	//Set the humidity level if enabled.
	if ($set_humidity) {
		$exttemp = $nest->temperatureInCelsius($locations[0]->outside_temperature);
		// Drop target humidity 5% for every 5degree C drop below 0
		$target = max(0, $maxhumidity + $exttemp);
		$target = round( min($target, $maxhumidity, 60) );
		if (abs($target - $infos->target->humidity) >= 1) {
			// Target humidity has changed 
			$nest->setHumidity(intval($target));
		}
}

//-------------------------------------------circulation mode-------------------------------------------------------------------------
// If circulate air mode is allowed, check to see if inside temp is over setpoint by 1.4 (circbuffer) degrees and that all other criteria are met. Enable fan until criteria are not met. 
if ($circmode == true and ($low_target_temp + $circbuffer - $infos->current_state->temperature <= 0) and round($low_target_temp) >= $circtemp and  $infos->current_state->fan == 0 and $infos->current_state->temperature-$circobuffer > $locations[0]->outside_temperature) {
$nest->setFanModeOnWithTimer(FAN_TIMER_15M);
//run fan for 15 minutes
}
else {
$nest->setFanMode(FAN_MODE_AUTO);
//set fan back to auto.
}

}
//---------------------------------------------------------------------------------------------------------------------------------------------


//Get data from Nest energy reports (daily), populate degree days. Go back last month. 

elseif ($datatype === 'daily') {
	//Used to get Nest energy reports
	$energy = $nest->getEnergyLatest();
	//Loop through the array of days and get the data
	$yesterday_date = date("Y-m-d", time() - 60 * 60 * 24);
	$days = $energy->objects[0]->value->days; 
//print_r($weather);//Print array of days
	foreach ($days as $day) {
		//We can only get degree days for yesterday. If this isn't  yesterday, we'll have to skip it.

			$active_date = date("Ymd", strtotime($day->day));//Change date format for weather underground api		
			//print_r($active_date);//Print active date for debug purposes


		
			//Check to see if there is already a record for this day.
			$result = $con->query("SELECT date FROM energy_reports WHERE date = '".$day->day."'");
		
		if($result->num_rows == 0 and strtotime($active_date) < strtotime($yesterday_date) ) {

 			//if there is no record for this date, get degree days from weather underground. 
			$weather_json = file_get_contents('http://api.wunderground.com/api/'.$wu_api_key.'/history_'.$active_date.'/q/'.rawurlencode($postal_code).'.json');
			$weather=json_decode($weather_json);

			$heating_degree_days = $weather->history->dailysummary[0]->heatingdegreedays;
			$cooling_degree_days = $weather->history->dailysummary[0]->coolingdegreedays;
			//print_r($heating_degree_days);//print heating degree days for debug purposes



			//Insert Current Values into Nest Database Table
			$sql = 'INSERT INTO energy_reports (date, total_heating_time, heating_degree_days, total_cooling_time, cooling_degree_days, total_fan_time, total_humidifier_time, total_dehumidifier_time, leafs, recent_avg_used, usage_over_avg) VALUES ("'.$day->day.'", "'.$day->total_heating_time.'", "'.$heating_degree_days.'", "'.$day->total_cooling_time.'", "'.$cooling_degree_days.'", "'.$day->total_fan_cooling_time.'", "'.$day->total_humidifier_time.'", "'.$day->total_dehumidifier_time.'", "'.$day->leafs.'", "'.$day->recent_avg_used.'", "'.$day->usage_over_avg.'")';
           		 $result = $con->query($sql) or trigger_error('SQL: ' . $sql . ' Error: ' . $con->error, E_USER_ERROR);
		}
	}
}

//Close mySQL DB connection
$con->close();

