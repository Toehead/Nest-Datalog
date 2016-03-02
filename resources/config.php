<?php
// Your Nest username and password.
define('USERNAME', '');
define('PASSWORD', '');

// The timezone you're in.
// See http://php.net/manual/en/timezones.php for the possible values.
$us_timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'US');
date_default_timezone_set('America/New_York');

//Database settings
$hostname='localhost';
$username='root';
$password='';
$dbname='';//Enter Database Name

//WeatherUnderground API - Sign up at http://www.wunderground.com/weather/api/?apiref=c133a2be0b541640
$wu_api_key = '';

//Automatically set humidity target based on outside temperature? This prevents condensation damage by lowering the humidity setting if the temperature drops drastically
$set_humidity=true;
$maxhumidity=40;  //What is the maximum target humidity you would like Nest to reach?

//Circulation Mode: Circulate air if temperature is 2 degrees above your set-temp, set temp is over min value, heat mode is on, and fan is on. Useful for wood stoves
$circmode=true;
$circbuffer=1.4;//Amount the room temp has to go over setpoint to activate fan
$circtemp=68;//Temp setpoint above which circulation mode is active. This prevents circulation mode from engaging if you leave and set the thermostat low. 
$circobuffer=20;//Outside temp buffer: Prevents circulation mode from engaging if the outside temp is the cause of the temp rise. 
