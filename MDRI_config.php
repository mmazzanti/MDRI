<?php
//Parsing of the configuration file config.conf
$config_file="config.conf";
$config=array();
$cf=@fopen($config_file,'r');
if($cf){
  while(!feof($cf)){
    $tmp=fgets($cf);
    if(strlen(trim($tmp))>0){
      array_push($config,$tmp);}
  }
}
else{
  //error opening config file. This will be logged on error.log
  $Error_logger=new MDRI_logger();
  $Error_logger->log_errors("Can't read configuration file");
  exit(0);
}
fclose($cf);
foreach($config as $c => $line){
  if($line[0]==='#'){
    unset($config[$c]);
    continue;
  }
  if(count($tmp=explode("=",$line))==2){
    unset($config[$c]);
    $config[trim($tmp[0])]=trim($tmp[1]);
  }
  else {
    $Error_logger=new MDRI_logger();
    $Error_logger->log_errors("ERROR near $line");
    exit(0);
    //error in line....
  }
  
}
unset ($cf,$config_file,$tmp);
//parsing the user and his password
$tmp_request_headers=apache_request_headers();
if(isset($tmp_request_headers["user"])){
$config["DEFAULT_USER"]=$tmp_request_headers["user"];
if(!isset($tmp_request_headers["password"])){
$config["DEFAULT_PASSWORD"]=$tmp_request_headers["password"];
}else{
$config["DEFAULT_PASSWORD"]="";
}
}
unset($tmp_request_headers);
$Error_logger=new MDRI_logger();
$config["ERROR_LOGGER"]=$Error_logger;
if(!isset($config["DEFAULT_DATABASE"])){
  $Error_logger->log_errors("DEFAULT DATABASE NOT SETTED");
  exit(0);
}
if(!isset($config["CONFIGURATION_DATABASE"])){
  $Error_logger->log_errors("CONFIGURATION DATABASE NOT SETTED");
  exit(0);
}
if(!isset($config["HOST"])){
  $Error_logger->log_errors("HOST NOT SETTED");
  exit(0);
}
if(!isset($config["SELECT"])){
  $Error_logger->log_errors("GET NOT SETTED");
  exit(0);
}
if(!isset($config["DELETE"])){
  $Error_logger->log_errors("DELETE NOT SETTED");
  exit(0);
}
if(!isset($config["POST"])){
  $Error_logger->log_errors("POST NOT SETTED");
  exit(0);
}
if(!isset($config["PUT"])){
  $Error_logger->log_errors("PUT NOT SETTED");
  exit(0);
}
if(!isset($config["UPDATE"])){
  $Error_logger->log_errors("UPDATE NOT SETTED");
  exit(0);
}
if(!isset($config["DEFAULT_USER"])){
  $Error_logger->log_errors("USER NOT SETTED");
  exit(0);
}
if(!isset($config["DEFAULT_PASSWORD"])){
  $Error_logger->log_errors("PASSWORD NOT SETTED");
  exit(0);
}
if(!isset($config["QUERY_LIMIT"])){
  $Error_logger->log_errors("QUERY LIMIT NOT SETTED. USING DEFAULT (-1). NO LIMIT");
  $config["QUERY_LIMIT"]="-1";
}
if(!isset($config["ERROR_LOG"])){
  $Error_logger->log_errors("ERROR SETTING IN ERROR LOG. Using default (15)");
  $config["ERROR_LOG"]="15";
}
if(!isset($config["QUERY_LOG"])){
  $Error_logger->log_errors("ERROR SETTING IN QUERY_LOG. Using default (15)");
  $config["QUERY_LOG"]="15";
}
if(!isset($config["PORT"])){
  $Error_logger->log_errors("HOST PORT NOT SETTED. USING DEFAULT (5432).");
  $config["PORT"]=5432;
}
if(!isset($config["POE"])){
  $Error_logger->log_errors("USING POE ? NOT SPECIFIED... USING DEFAULT (ENABLED)");
  $config["POE"]="ENABLED";
}

/**
 * Function used for control of POE functionality
 * Returns TRUE if POE is enabled, FALSE if disabled.
 */
function IsPOEenabled(){
  global $config;
  if(strcasecmp($config["POE"],"ENABLED")==0){
    return TRUE;}
    elseif(strcasecmp($config["POE"],"DISABLED")==0){
      return FALSE;}
      else {$config["ERROR_LOGGER"]->log_errors("BAD SETTINGS IN POE OPTIONS. USING DEFAULT (ENABLED");
	$config["POE"]="ENABLED";
	return TRUE;}
}

/**
 * Used to check if method $type (can be PUT or POST) is used for UPDATE, INSERT or both
 * Returns an array of two elements:
 *  - INSERT with value = 1 if enabled, 0 if disabled
 *  - UPDATE with value = 1 if enabled, 0 if disabled
 */
function UpdateorInsert($type){
  global $config;
  $use=explode(',',$config[$type]);
  $return["UPDATE"]=0;
  $return["INSERT"]=0;
  if($use===FALSE){
    return $return;
  }
  if(count($use)>2){
    $config["ERROR_LOGGER"]->log_errors("BAD SETTINGS IN ".$type." OPTIONS");
    return FALSE;
  }
  foreach($use as $key => $u){
    if(strcasecmp($u,"INSERT")==0){
      $return["INSERT"]=1;
    }
    elseif(strcasecmp($u,"UPDATE")==0){
      $return["UPDATE"]=1;
    }
    else{
      $config["ERROR_LOGGER"]->log_errors("BAD SETTINGS IN ".$type." OPTIONS");
      return FALSE;
    }
  }
  return $return;
}

/**
 * Function used for level control of a URI.
 * $type -> the HTTP method used
 * $level -> the level of URI
 * Will return TRUE if that method can parse URI of that level, otherwise FALSE.
 */
function level_control($level,$type){
  global $config;
  $level=(int)$level;
  $levels=explode(',',$config[$type]);
  if($levels===FALSE){
    return FALSE;
  }
  if(count($levels)>5){
    $config["ERROR_LOGGER"]->log_errors("BAD SETTINGS IN ".$type." OPTIONS");
    return FALSE;
  }
  foreach($levels as $key => $string){
    $int=intval($string);
    if($int>=0 && $int<5){
      $levels[$key]=$int;
    }
    else{
      $config["ERROR_LOGGER"]->log_errors("BAD SETTINGS IN ".$type." OPTIONS");
      return FALSE;
    }
  }
  unset($int);
  if(array_search($level,$levels)!== FALSE){
    return (TRUE);
  }
  else{
    return (FALSE);
  }
}

/**
 * Class MDRI_logger.
 * This class has only one method used for log errors on error.log.
 * An istance of this class will be added to the $config array, because each class of the interface can use it
 */
class MDRI_logger {
  private $time=NULL;
  private $date=NULL;
  private $day=NULL;
  private $maxtime=NULL;
  
  /**
   * Method used for write on error.log the error string $error.
   */
  function log_errors($error){
    global $config;
    $this->time=microtime(true);
    date_default_timezone_set('UTC');
    $this->date=date("r",$this->time);
    $this->day=(int)date("z",$this->time);
    $this->maxtime=intval($config["ERROR_LOG"]);
    if($this->maxtime<0 || $this->maxtime > 365){
      $config["ERROR_LOG"]="15";
      $this->log_errors("ERROR SETTING MAX TIME FOR ERROR LOG. Using default (15)");
      $this->maxtime=15;
    }
    if(((int)$this->day/15)& 1 ==1){
      $use="01";
    }
    else{
      $use="00";
    }
    $last_modified=filemtime("error-".$use.".log");
    if($this->time>$last_modified+(24*60*60*$this->maxtime)){
      delete("error-".$use.".log");
    }
    exit(0);
    $handle = fopen("error-".$use.".log", "a") or die();
    fwrite($handle,$this->date." : ".$error."\n");
    fclose($handle);
  }
}
?>