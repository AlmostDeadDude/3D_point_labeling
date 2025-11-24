<?php

#first determine if settings file was created, otherwise use default settings
if (file_exists("settings.ini.php")) {
  $config_file = "settings.ini.php";
} else {
  $config_file = "settings_default.ini.php";
}

#parse the ini file to get all the settings in php array
$config_data = parse_ini_file($config_file, true);
#the settings are then stored like so:
#$config_data[$section][$key] = $value;

define("IS_DEMO", true);

#get the crowdsourcing platform IDs/keys
$Campaign_id = $_GET["campaign"] ?? "demoCampaign";
$Worker_id = $_GET["worker"] ?? "demoWorker";
$Rand_key = $_GET["rand_key"] ?? "demoKey";

#get the secret key from the configuration file
$My_secret_key = $config_data["general"]["mwKey"];

$String_final = $Campaign_id . $Worker_id . $Rand_key . $My_secret_key;
$vcode_for_proof = "proof-" . substr(hash("sha256", $String_final), 0, 12);

?>

<!DOCTYPE HTML>

<html lang="en">

<head>

  <meta charset="UTF-8">
  <title><?php
          #get the html title from the configuration file
          echo $config_data["general"]["htmlTitle"];
          ?></title>
  <link rel="ICON" href="<?php
                          #get the icon from configuration
                          echo $config_data["uploads"]["icon"];
                          ?>" type="image/ico" />
  <script src="https://kit.fontawesome.com/d6065b6a9b.js" crossorigin="anonymous"></script>
  <link href="StyleSheet_hessig.css" rel="stylesheet" type="text/css">
</head>



<body>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>


  <div style="background: #95a5a6; height:150px; padding-left:30px;padding-right:30px; padding-top:50px;">
    <table border="0" style="width:100%">
      <tr>
        <td style="width:50%;text-align:left"><img src="logos/unistuttgart_logo_inv.png" height="100px" alt="Symbol_Uni"></td>
        <td style="width:50%;text-align:right"><img src="logos/ifp_logo_trans.png" height="100px" alt="Symbol_institute"></td>
      </tr>
    </table>
  </div>
  <div style="padding-left:30px; padding-right:30px; padding-top:20px; padding-bottom:50px;">


    <h1> <em> Thanks for participating! </em> </h1> <br> <br>

    <h2> Your proof code for showing you completed this task: </h2> <br>
    <p id="proof_code"> <?php echo " $vcode_for_proof" ?></p> <br><br>


    <p id="performance"> </p> <br><br><br><br><br>
    <footer>
      <small>
        &copy; <span id="year">2022</span>, Ivan Shiller <a href="https://github.com/AlmostDeadDude/CountIt2-Interface" target="_blank" rel="noopener noreferrer">
          <i class="fa-brands fa-github"></i>
        </a>
      </small>
    </footer>

    <script>
      var isQual = <?php echo $config_data["quality"]["quality"] ?>;
      var isDemo = <?php echo IS_DEMO ? "true" : "false" ?>;

      if (isDemo) {
        document.getElementById("performance").innerHTML = "<b>Info:</b> Demo project – your proof code is ready.";
        document.getElementById("proof_code").innerHTML = "<?php echo $vcode_for_proof; ?>";
      } else {
        //get the quality score from session storage
        let wq = sessionStorage.getItem('wq');

        if (!isQual) {
          document.getElementById("performance").innerHTML = "<b>Info:</b>Use the proof code above as confirmation";
        } else {
          if (wq == 0) {
            document.getElementById("performance").innerHTML = "<b>Info:</b> You did not pass control tests.";
            document.getElementById("proof_code").innerHTML = "attention checks failed! Proof code will not be displayed!";
          } else if (wq == 1) {
            document.getElementById("performance").innerHTML = "<b>Info:</b>Use the proof code above as confirmation";
          } else if (wq == 2) {
            document.getElementById("performance").innerHTML = "<b>Info:</b>Good work! Use the proof code above as confirmation";
          } else {
            document.getElementById("performance").innerHTML = "Proof code could not be displayed due to unusual behaviour of locally stored variables.";
            document.getElementById("proof_code").innerHTML = "Error"
          }
        }
      }
    </script>

</body>
