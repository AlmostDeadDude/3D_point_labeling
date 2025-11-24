<?php
    #first determine if settings file was created, otherwise use default settings
    if(file_exists("settings.ini.php")){
        $config_file="settings.ini.php";
    } else{
        $config_file="settings_default.ini.php";
    }
    
    #parse the ini file to get all the settings in php array
    $config_data = parse_ini_file($config_file, true);
    #the settings are then stored like so:
    #$config_data[$section][$key] = $value;
?>