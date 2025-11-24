<?php
//Report all errors except warnings.
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);

require "common/read_ini.php";
#the settings are then stored like so:
#$config_data[$section][$key] = $value;

#flag for demo mode so nothing is persisted
define("IS_DEMO", true);

$refNames = [];
$refPositions = [];

#get the crowdsourcing platform IDs/keys
$Campaign_id = $_GET["campaign"] ?? "demoCampaign";
$Worker_id = $_GET["worker"] ?? "demoWorker";
$Rand_key = $_GET["rand_key"] ?? "demoKey";

#get the secret key from the configuration file
$My_secret_key = $config_data["general"]["mwKey"];

#read chosen classes to build html properly
#var_dump($config_data["labels"]);

#convert reference labels for later usage (skip in demo)
if ($config_data["quality"]["quality"] && !IS_DEMO) {
  $refNames = [];
  $refPositions = [];
  $refs = explode(",", trim($config_data["quality"]["reference"])); #output: 1-Vegetation // 4-Car // etc
  foreach ($refs as $part) {
    $refs_explode = explode("-", trim($part));
    $refNames[] = $refs_explode[1];
    $refPositions[] = $refs_explode[0];
  }
  #var_dump($refNames);
  #var_dump($refPositions);
};

#user counter
//https://stackoverflow.com/a/28012214
function getClientIP()
{
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else {
    $ip = $_SERVER['REMOTE_ADDR'];
  }
  return $ip;
}

$ipaddress = getClientIP();
if (!IS_DEMO) {
  file_put_contents('visits/guest_' . date('Y_m_d_H_i_s_T') . '.txt', $ipaddress);
}

?>

<!DOCTYPE HTML>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <title><?php
          #get the html title from the configuration file
          echo $config_data["general"]["htmlTitle"];
          ?></title>
  <link rel="ICON" href="<?php
                          #get the icon from configuration
                          echo $config_data["uploads"]["icon"];
                          ?>" type="image/ico" />

  <link href="StyleSheet_hessig.css" rel="stylesheet" type="text/css">
  <script src="https://kit.fontawesome.com/d6065b6a9b.js" crossorigin="anonymous"></script>
  <script src="js/three.min.js"></script>
  <script src="js/OrbitControls.js"></script>
  <script src="js/PCDLoader.js"></script>

  <script>
    window.addEventListener('resize', adjustSize);
    window.addEventListener("orientationchange", handleOrientation, true);

    function handleOrientation() {
      document.body.scrollTop = 0; // For Safari
      document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE and Opera
    }

    function adjustSize() {
      const bodyEl = document.getElementById("main_body");
      let factor = 1;
      if (window.innerWidth < 300) {
        factor = 0.3;
      } else if (window.innerWidth < 1000) {
        factor = window.innerWidth / 10 / 100;
      } else {
        factor = 1;
      }
      const style = `scale(${factor*100}%)`;
      bodyEl.style.transform = style;
    }

    function preventMotion(event) {
      window.scrollTo(0, 0);
      event.preventDefault();
      event.stopPropagation();
      console.log('prevent');
    }
    //get this JS variable in head script to make sure it is available everywhere in JS code later
    var isQual = <?php echo (!$config_data["quality"]["quality"] || IS_DEMO) ? "false" : "true" ?>;
    //demo flag to keep the experience read-only
    var isDemo = <?php echo IS_DEMO ? "true" : "false" ?>;

    //shuffle array function
    function shuffleArray(array) {
      for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
      }
    }

    let center = {
      x: 0,
      y: 0,
      z: 0
    };
    // Called from reset Button /////////////////////////////////////////////
    function reset_view() {
      //set the camera position using the values from configuration file
      camera.position.set(<?php echo $config_data["pointcloud"]["camX"] . "," . $config_data["pointcloud"]["camY"] . "," . $config_data["pointcloud"]["camZ"] ?>);
      camera.lookAt(center.x, center.y, center.z);

      lookAtvector[0] = 0;
      lookAtvector[1] = 0;
      lookAtvector[2] = 0;

      controls.target.set(center.x, center.y, center.z);

      //also reset the point size
      scene.children[2].material.size = <?php echo $config_data["pointcloud"]["pointSize"] ?>;

      renderer.render(scene, camera);
    }

    // Orbit controls and auto rotate /////////////////////////////////////////////
    function animate_mouseover(counter) {

      body.classList.add('stop-scrolling')
      // document.getElementById('main_body').style.paddingRight = '7px';

      controls = new THREE.OrbitControls(camera);
      controls.maxDistance = 1500;
      controls.minDistance = 10;
      controls.maxPolarAngle = Math.PI / 2;
      controls.enablePan = false;
      controls.target.set(center.x, center.y, center.z);
      controls.autoRotateSpeed = 1 / mouse_count;
      controls.autoRotate = true; // SET FOR AUTOMATIC ROTATION

      mouse_count = mouse_count + 1;

      function animate() {

        requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
      };
      animate();
    };

    // Stop when mouse is outside of canvas ///////////////////////////////////////
    function animate_mouseout() {

      // remember controls position
      lookAtvector[0] = controls.target.x;
      lookAtvector[1] = controls.target.y;
      lookAtvector[2] = controls.target.z;

      controls.enabled = false;
      controls.autoRotate = false;
      controls.autoRotateSpeed = 1 / mouse_count;
      body.classList.remove('stop-scrolling');
      document.getElementById('main_body').style.paddingRight = '0px';
    };

    //indicator will become true, when the initial loading is finished
    let loadingChecker = false;

    // Plot PCL on canvas ////////////////////////////////////////////////////////
    function plotoncanvas3D(numb_pcl, next_pcl) {
      // Scene ///////////////////////////////////////////////////////////////////
      scene = new THREE.Scene();

      // Camera ////////////////////////////////////////////////////////////////
      //use FOV and position from configuration file
      camera = new THREE.PerspectiveCamera(<?php echo $config_data["pointcloud"]["camFOV"] ?>, 1, 1, 2000);
      camera.position.set(<?php echo $config_data["pointcloud"]["camX"] . "," . $config_data["pointcloud"]["camY"] . "," . $config_data["pointcloud"]["camZ"] ?>);
      camera.lookAt(center.x, center.y, center.z);

      // Load PCL //////////////////////////////////////////////////////////////
      var loader = new THREE.PCDLoader();
      //console.log("TO BE ADJUSTED",'Data_AL/job' + cur_batch + '/' + numb_pcl + '_bin.pcd')
      //load from the directory from configuration file
      loader.load('<?php echo $config_data["uploads"]["pointclouds"] ?>/job' + cur_batch + '/' + numb_pcl + '_bin.pcd', function(mesh) {
        //preloading next pc if available
        if (!isNaN(next_pcl)) {
          loader.load('<?php echo $config_data["uploads"]["pointclouds"] ?>/job' + cur_batch + '/' + next_pcl + '_bin.pcd', function(m) {});
          //this way browser loads in from cache next time which is much faster
        }
        scene.add(mesh);

        //size from the configuration file
        mesh.material = new THREE.PointsMaterial({
          vertexColors: THREE.VertexColors,
          map: sprite,
          side: THREE.DoubleSide,
          size: <?php echo $config_data["pointcloud"]["pointSize"] ?>,
          alphaTest: 0.5,
          transparent: true
        });;
        //var center = mesh.geometry.boundingSphere.center;
        camera.lookAt(center.x, center.y, center.z);

        //use labels from configuration
        <?php
        foreach ($config_data["labels"] as $config_label => $val) {
          if ($val) {
            #echo "console.log(".$config_label.");";
            echo "document.getElementById('" . $config_label . "').disabled = false;";
          }
        }
        ?>

        document.F1.selection_title.value = "No class chosen yet!";
        loadingChecker = true;
      });

      // Material //////////////////////////////////////////////////////////////
      //sprite = new THREE.TextureLoader().load( "disc.png" );
      sprite = new THREE.TextureLoader().load("disc_big_white.png");
      sprite.anisotropy = 8;
      sprite.magFilter = THREE.NearestFilter;
      sprite.minFilter = THREE.NearestFilter;

      //maybe make size dependant on normal point size from configuration?
      var materialttt = new THREE.PointsMaterial({
        vertexColors: THREE.VertexColors,
        map: sprite,
        side: THREE.DoubleSide,
        size: 9,
        alphaTest: 0.5,
        transparent: true
      });

      // Add  point to be labelled /////////////////////////////////////////////
      var geometry2 = new THREE.Geometry();
      geometry2.vertices.push(new THREE.Vector3(0, 0, 0));
      var materia2 = new THREE.PointsMaterial({
        color: "rgb(255, 255, 0)",
        size: 5,
        map: sprite,
        alphaTest: 0.5,
        transparent: true
      });
      var line2 = new THREE.Points(geometry2, materia2);
      scene.add(line2);

      // Add yellow arrow as indicator /////////////////////////////////////////
      var dir = new THREE.Vector3(0, -1, 0);
      dir.normalize();

      var origin = new THREE.Vector3(0, 17, 0);
      var length = 9;
      var hex = 0xffff00;

      var arrowHelper = new THREE.ArrowHelper(dir, origin, length, hex, 4, 3);
      scene.add(arrowHelper);

      // Render to Canvas //////////////////////////////////////////////////////
      renderer = new THREE.WebGLRenderer({
        canvas: canvas111
      });
      renderer.setClearColor(0xecf0f1);
      renderer.setSize(600, 600);

      function animate() {
        requestAnimationFrame(animate);
        renderer.render(scene, camera);
      };
      animate();
    }
    // function displays via button selected type ////////////////////////////////
    function togglefunc() {

      if (point_numb <= numPoints) {
        if (false) {
          console.log('never happens')
        }
        //use labels from configuration
        <?php
        foreach ($config_data["labels"] as $config_label => $val) {
          if ($val) {
            #echo "console.log(".$config_label.");";
            echo "else if(document.getElementById('" . $config_label . "').checked){";
            echo "document.F1.selection_title.value = 'Your Answer:';";
            echo "document.F1.selection.value = '" . $config_label . "';";
            echo "document.getElementById('submit').disabled = false;}";
          }
        }
        ?>
      }
    }

    // kills all handles /////////////////////////////////////////////////////////
    function deadendfunc() {
      document.getElementById("proof").innerHTML = "Survey has already ended. Thanks for your interest!";
      document.getElementById("proof").style.color = '#d9584a';
      document.getElementById("proof").style.fontSize = "200%";
      document.getElementById("proof").style.visibility = "visible";
      document.getElementById("submit").disabled = true;
    }

    // redirect to Goodbye-page using neccessary parameters //////////////////////
    function get_code() {
      sessionStorage.setItem('wq', quality);
      var target_url = 'goodbye.php?campaign=' + "<?php echo $Campaign_id ?>" + '&worker=' + "<?php echo $Worker_id ?>" + '&rand_key=' + "<?php echo $Rand_key ?>"; // + '&wq=' + quality;
      window.location = target_url
    }

    function to_examples() {
      document.getElementById("defaultOpen").click();
    }

    function to_task() {
      document.getElementById("Ref_Guide").click();
    }

    // sends feedback to server --> selected type and image number ///////////////
    function ajax_feedback() {

      var l111 = canvas111.width

      if (l111 == 600) {

        document.getElementById("proof").style.visibility = "hidden";

        var classified;
        if (false) {
          console.log('never happens')
        }
        <?php
        foreach ($config_data["labels"] as $config_label => $val) {
          if ($val) {
            #echo "console.log(".$config_label.");";
            echo "else if(document.getElementById('" . $config_label . "').checked){";
            echo "classified = '" . $config_label . "'}";
          }
        }
        ?>

        // time measuring /////////////////////////////////////////////////
        var eos = new Date();
        var delta_t = (eos - sos) / 1000
        sos = new Date()

        // remember given labels //////////////////////////////////////////
        labels_cw.push(classified);
        delta_t_cw.push(delta_t);

        // Preparings and plot for next image //////////////////////////////
        document.F1.selection.value = ""
        document.F1.selection_title.value = "No class chosen yet!"
        document.getElementById("submit").disabled = true;
        point_numb = point_numb + 1;

        //use labels from configuration
        <?php
        foreach ($config_data["labels"] as $config_label => $val) {
          if ($val) {
            #echo "console.log(".$config_label.");";
            echo "document.getElementById('" . $config_label . "').checked = false;";
            echo "document.getElementById('" . $config_label . "').disabled = true;";
          }
        }
        ?>

        //ajax_feedback
        renderer.setSize(600, 600);

        lookAtvector[0] = 0;
        lookAtvector[1] = 10;
        lookAtvector[2] = 0;

        document.F1.progress.value = point_numb - 1 + "/" + (numPoints) + " done";


        if (point_numb <= total_numb_points_job) {
          if (!!<?php echo $config_data["general"]["shuffle"]; ?>) {
            //console.log('shuffle');
            plotoncanvas3D(indexArray[point_numb - 1] + 1, indexArray[point_numb] + 1);
          } else {
            //console.log('NO shuffle');
            plotoncanvas3D(point_numb, point_numb + 1);
          }
        } else if (point_numb == numPoints) {
          document.getElementById("lc_feed").style.visibility = "visible";
          plotoncanvas3D(1, NaN);
        }
        // Feedback Text and quality measure ////////////////////////////////
        else {
          var fb = document.getElementById("fb").value
          if (fb == null) {
            fb = "no comment"
          }


          var qualType = <?php echo "'" . $config_data["quality"]["qualtype"] . "'" ?>;

          var ref_label = [<?php
                            $counter = 0;
                            foreach ($refNames as $refName) {
                              echo "'$refName'";
                              if ($counter < count($refNames) - 1) {
                                echo ", ";
                              }
                              $counter++;
                            }
                            ?>]; // true labels to be set

          var ref_positions = [<?php
                                $counter2 = 0;
                                foreach ($refPositions as $refPos) {
                                  echo $refPos - 1; //-1 since here we count from 0
                                  if ($counter2 < count($refPositions) - 1) {
                                    echo ", ";
                                  }
                                  $counter2++;
                                }
                                ?>]; //positions of reference labels

          //reshuffle the results array so it appears as expected before we sent it to server or process the quality info
          if (!!<?php echo $config_data["general"]["shuffle"]; ?>) {
            var labels_cw_new = new Array(numPoints);
            for (let reshuffleInd = 0; reshuffleInd < total_numb_points_job; reshuffleInd++) {
              labels_cw_new[indexArray[reshuffleInd]] = labels_cw[reshuffleInd]
            }
            //next line is for the control task at the end of the job
            labels_cw_new[labels_cw_new.length - 1] = labels_cw[labels_cw.length - 1];
          } else {
            var labels_cw_new = labels_cw;
          }

          //if quality check is enabled
          if (isQual) {
            //q1            
            //all checks - accepted
            //else - rejected
            if (qualType == "q1") {

              //first condition is for all qualities
              let cond1 = true;
              for (let i = 0; i < ref_positions.length; i++) {
                cond1 = cond1 && (labels_cw_new[ref_positions[i]] === ref_label[i])
              }
              //extra condition (first equals last)
              let cond2 = (labels_cw_new[ref_positions[0]] === labels_cw_new[labels_cw_new.length - 1])
              if (cond1 && cond2) {
                quality = 1;
              } else {
                quality = 0;
              }
            }

            //q2
            //all checks - bonus
            //one mistake - normal payment
            //else - rejected
            if (qualType == "q2") {

              //first condition is for all qualities
              let cond1 = true;
              let singleMistake = true; //indicator for the one single mistake
              for (let i = 0; i < ref_positions.length; i++) {
                if (labels_cw_new[ref_positions[i]] === ref_label[i]) {
                  //if no mistake condition stays true
                  cond1 = true;
                } else {
                  //if there is a mistake we check if it was the first mistake or not
                  if (singleMistake) {
                    //if it was the first mistake - we forgive it but only this one time
                    cond1 = true;
                    singleMistake = false; //after first mistake this indicator is always false 
                  } else {
                    cond1 = false; //if it was not the first mistake - condition is failed
                  }
                }
              }
              //extra condition (first equals last)
              let cond2 = (labels_cw_new[ref_positions[0]] === labels_cw_new[labels_cw_new.length - 1])
              if (cond1 && singleMistake && cond2) {
                quality = 2; //both conditions and no mistakes
              } else if (cond1 && cond2) {
                quality = 1; //both conditions but single mistake
              } else {
                quality = 0; //1+ mistakes or failed second condition
              }
            }

            //q3
            //no mistakes - bonus
            //1 or 2 mistakes - normal
            //else - rejected
            if (qualType == "q3") {
              //first condition is for all qualities
              let cond1 = true;
              let singleMistake = true; //indicator for the one single mistake
              let secondMistake = true; //indicator for second mistake
              for (let i = 0; i < ref_positions.length; i++) {
                if (labels_cw_new[ref_positions[i]] === ref_label[i]) {
                  //if no mistake condition stays true
                  cond1 = true;
                } else {
                  //if there is a mistake we check if it was the first mistake or not
                  if (singleMistake) {
                    //if it was the first mistake - we forgive it
                    cond1 = true;
                    singleMistake = false; //after first mistake this indicator is always false 
                  } else if (secondMistake) {
                    cond1 = true; //if it was the second mistake - we also forgive it
                    secondMistake = false;
                  } else {
                    cond1 = false; //otherwise condition is failed
                  }
                }
              }
              //extra condition (first equals last)
              let cond2 = (labels_cw_new[ref_positions[0]] === labels_cw_new[labels_cw_new.length - 1])
              if (cond1 && singleMistake && secondMistake && cond2) {
                quality = 2; //both conditions and no mistakes at all
              } else if (cond1 && cond2) {
                quality = 1; //both conditions but up to 2 mistakes
              } else {
                quality = 0; //2+ mistakes or failed second condition
              }
            }
          } else {
            //no quality check so everything is accepted
            quality = 2;
          }

          if (!isDemo) {
            var feedbackText = "feedback=" + fb + "&batch_numb=" + cur_batch + "&it_numb=" + cur_it + "&quality=" + quality + "&campaign=" + "<?php echo $Campaign_id ?>" + '&worker=' + "<?php echo $Worker_id ?>" + '&rand_key=' + "<?php echo $Rand_key ?>";
            var requestText = new XMLHttpRequest();
            var urlText = "feedback_text.php";
            requestText.open("POST", urlText, true);
            requestText.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            requestText.onreadystatechange = function() {
              if (requestText.readyState == 4 && requestText.status == 200) {
                var return_data = requestText.responseText;
                //document.getElementById("status").innerHTML = return_data;
              }
            }
            requestText.send(feedbackText);

            var feedbackArray = "feedback=" + labels_cw_new + "&point_numb=" + point_numb + "&batch_numb=" + cur_batch + "&it_numb=" + cur_it + "&delta_t=" + delta_t_cw;
            var requestArray = new XMLHttpRequest();
            var urlArray = "feedback_speichern_array.php";
            requestArray.open("POST", urlArray, true);
            requestArray.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            requestArray.onreadystatechange = function() {
              if (requestArray.readyState == 4 && requestArray.status == 200) {
                var return_data = requestArray.responseText;
              }
            }
            requestArray.send(feedbackArray);
          } else {
            console.log("Demo project: answers are not stored.");
          }

          document.F1.selection.value = "Thanks!"
          document.getElementById("selection_title").style.visibility = "hidden";
          document.getElementById("proof").innerHTML = "No points left to label!";
          document.getElementById("proof").style.color = "#d9584a";
          document.getElementById("proof").style.fontSize = "200%";
          document.getElementById("proof").style.visibility = "visible";
          document.getElementById("canvas111").style.visibility = "hidden";
          document.getElementById("submit").disabled = true;
          document.getElementById("end").disabled = false;
          document.getElementById("end").style.visibility = "visible";
          document.getElementById("lc_feed").style.visibility = "hidden";

        }

      } else {
        document.getElementById("proof").innerHTML = "Page is loading. Please wait!";
        document.getElementById("proof").style.color = "#d9584a";
        document.getElementById("proof").style.fontSize = "200%";
        document.getElementById("proof").style.visibility = "visible";
      }
    }
  </script>


</head>
<!-- END OF HEAD, START OF BODY /////////////////////////////////////////////-->

<body id="main_body">
  <div id="frame_color">
    <div id="frame_all">

      <!-- Check how many images are available in total (always looking in first folder assuming constant number in each folder) -->
      <?php
      $total_numb_jobs  = count(glob($config_data["uploads"]["pointclouds"] . "/*", GLOB_ONLYDIR));
      $total_numb_points_job  = count(glob($config_data["uploads"]["pointclouds"] . "/job1/*"));
      if ($config_data["quality"]["quality"] && !IS_DEMO) {
        $numPoints = $total_numb_points_job + 1;
      } else {
        $numPoints = $total_numb_points_job;
      }
      ?>

      <div class="ontop" id="itshead" style="background: #95a5a6; height:150px; padding-left:30px;padding-right:30px; padding-top:50px; ">
        <table border="0" style="width:100%">
          <tr>
            <td style="width:50%;text-align:left"><img src="logos/unistuttgart_logo_inv.png" height="100px" alt="Symbol_Uni"></td>
            <td style="width:50%;text-align:right"><img src="logos/ifp_logo_trans.png" height="100px" alt="Symbol_institute"></td>
          </tr>
        </table>
      </div>
      <div style="padding-left:30px; padding-right:30px; padding-top:20px; padding-bottom:0px; background: #ecf0f1;">


        <h1> <em> Determine the class of the highlighted point ! </em> </h1>
        <ul>
          <li> You will have to label a total of <b><?php echo $numPoints ?> points</b>.
          <li> In the left window one point is highlighted in yellow and indicated by an arrow.
          <li> On the right possible classes for this point are presented.
          <li> You have to assign the yellow point (on the left) to one of these classes by selecting the respective button.
          <li> Specific classes may occur more often than others.
            <!-- <li> <b>Attention : Test samples are included! Your performance here will determine if you receive no/normal/bonus payment.</b> -->
        </ul>


        <center>
          <div>
            <div class="loader" ID="load_circle"></div><br>
            <h1 id="load_text"></h1>
          </div>
          <div id="MAIN_CONTENT" style="display: none;">
            <div class="tab" style="background: #ecf0f1;">
              <button class="tablinks" onclick="openCity(event, 'Reference Guide')" id="defaultOpen">Guide</button>
              <button class="tablinks" onclick="openCity(event, 'Survey')" id="Ref_Guide">Task</button>
              <button class="tablinks" onclick="openCity(event, 'About')">About</button>
            </div>

            <div id="Survey" class="tabcontent" style="background: #ecf0f1; text-align: left;">

              <center>
                <p id="proof" style="visibility: hidden; font-size: 0%"> <?php echo "<br> <strong> <font size='2.5'> No images left to classify! </font></strong>"; ?> </p>
              </center>
              <table id="Main_content" border="0" style="width:100%;">
                <tr>
                  <td valign="top" style="width:75%;">
                    <center style="font-size: 20px;margin-bottom:10px;"><b>Controls:</b></center>
                    <center>
                      <center><em><b>
                            <table ID="handling" style="font-size: 120%; color: #d9584a;">
                              <tr>
                                <td><img id="plusClick" src="logos/equals-plus.png" height="50px" alt="plus"></td>
                                <td><img id="minusClick" src="logos/minus.png" height="50px" alt="minus"></td>
                                <td><img src="logos/MMB.png" height="60px" alt="mmb"></td>
                                <td><img src="logos/LMB.png" height="60px" alt="lmb"></td>
                                <td></td>
                              </tr>
                              <tr>
                                <td>
                                  <center>Increase point size <span style="visibility:hidden">g</span></center>
                                </td>
                                <td>
                                  <center>Decrease point size <span style="visibility:hidden">g</span></center>
                                </td>
                                <td>
                                  <center>Zoom <span style="visibility:hidden">g</span></center>
                                </td>
                                <td>
                                  <center>Rotate <span style="visibility:hidden">g</span></center>
                                </td>
                                <td><input ID="reset" type="reset" name="submit" value="Reset view" onclick="reset_view()"></td>
                              </tr>
                            </table>
                          </b></em></center>

                      <canvas id="canvas111" width="200" height="100" style="border:1px solid #000000;"></canvas>
                    </center>
                  </td>

                  <!-- REAL CONTENT OF PAGE ///////////////////////////////////////////////////-->

                  <form name="F1" id="F1">


                    <td ID="rc" style="width:50%;text-align:left;max-width:227px;">
                      <input TYPE="text" ID="progress" readonly="readonly" value="0 / done" style="border: none; font-size: 18px; width: 200px; color: #d9584a; font-weight: bold; background: #ecf0f1"><br>
                      <hr>
                      <h2 id="example_link" onclick="to_examples()" over="" style="cursor: pointer;"> Need some examples? <br> See our <span style="color: blue; font-size: 100%"> <u> Reference guide </u> </span>. </h2>
                      <hr>

                      <div class="radio-toolbar">
                        <!-- use labels from configuration -->
                        <?php
                        foreach ($config_data["labels"] as $config_label => $val) {
                          if ($val) {
                            #echo "console.log(".$config_label.");";
                            echo "<input type='radio' id='" . $config_label . "' name='r1' value='0' onclick='togglefunc()'>";
                            echo "<label for='" . $config_label . "'> <img src='logos/" . $config_label . ".png' height='50px' alt='" . $config_label . "'> " . $config_label . " </label>";
                          }
                        }
                        ?>
                      </div>

                      <input TYPE="text" ID="selection_title" VALUE="Data is loading ..." readonly="readonly" style="border: none; font-size: 18px; width: 200px; background: #ecf0f1 ">
                      <textarea TYPE="text" ID="selection" readonly="readonly" rows="1" style="font-family:arial; border: none; font-size: 18px; width: 200px; color: #d9584a; background: #ecf0f1 "></textarea>
                      <input TYPE="text" ID="lc_feed" VALUE="Last chance for feedback" readonly="readonly" style="border: none; font-size: 18px; width: 200px; color: #d9584a; visibility: hidden; background-color:#ecf0f1"><br>

                  </form>

                  <input ID="submit" type="submit" name="submit" value="Submit current class" onclick="ajax_feedback()" style="width: 100%;" disabled>
                  <input ID="end" type="submit" name="end" value="Get your proof code" onclick="get_code()" style="visibility: hidden;width: 100%;" disabled>

                  </td>
              </table>

              <h2 ID="fb_field"> We would appreciate if you gave us any kind of feedback regarding this survey! </h2>
              <input id="fb" type="text" name="inputBox" placeholder="enter your feedback here">
            </div>


            <script>
              var canvas111 = document.getElementById('canvas111');
              var lookAtvector = [0, 0, 0];

              //default quality value is 0 so if anything goes wrong there is still a predictable score
              var quality = 0;

              // Get successing image number to be evaluated ///////////////////////////////
              var total_numb_jobs = <?php echo $total_numb_jobs ?>;
              //console.log("total_numb_jobs",total_numb_jobs )
              var total_numb_points_job = <?php echo $total_numb_points_job ?>;
              if (isQual) {
                var numPoints = total_numb_points_job + 1;
              } else {
                var numPoints = total_numb_points_job;
              }
              if (!!<?php echo $config_data["general"]["shuffle"]; ?>) {
                //create shuffled array of indeces
                var indexArray = [...Array(total_numb_points_job).keys()];
                //console.log(indexArray);
                shuffleArray(indexArray);
                //console.log(indexArray);
              };
              //console.log("total_numb_points_job",total_numb_points_job)
              document.getElementById("progress").value = "0 / " + (numPoints) + " done";
              var point_numb = 1

              // get number of batch to be classified (--> if job has not be completed successfully incomplete data is overwritten and presented to next CW)
              <?php
              $next_batch = 1;                                            // demo defaults
              $next_it = 1;

              if (!IS_DEMO) {
                $next_batch = 1000;                                            // 1000, used as error code
                $next_it = 1000;
                $total_numb_it = $config_data["general"]["iterations"];       // set to number of iterations as in configuration file
                for ($it = 1; $it <= $total_numb_it; $it++) {

                  for ($in = 1; $in <= $total_numb_jobs; $in++) {
                    $file_to_check1 = "results/" . $in . '_' . $it . ".txt";                    // Set for path/folder to check !
                    $existing = file_exists($file_to_check1);
                    if ($existing) {
                      $no_of_lines = count(file($file_to_check1));
                      $last_mod = filemtime($file_to_check1);
                      $cur_time = time();
                      $time_since_last_mod = ($cur_time - $last_mod) / 60;
                      //echo "console.log('time=".$time_since_last_mod." timeThreshold= ".$config_data["general"]["duration"]." lines=".$no_of_lines." numPoints=".$numPoints."');";
                      if ($no_of_lines < $numPoints and $time_since_last_mod > $config_data["general"]["duration"]) {              //number of points per job and waiting time from configuration file
                        $next_batch = $in;
                        $next_it = $it;
                        unlink($file_to_check1);                                            //delete existing file
                        break 2;
                      }
                    } else {
                      $next_batch = $in;
                      $next_it = $it;
                      break 2;
                    }
                  }
                }


                file_put_contents('results/' . $next_batch . '_' . $next_it . '.txt', "");
              }

              ini_set('memory_limit', '-1');
              ?>


              var cur_batch = <?php echo $next_batch ?>;
              var cur_it = <?php echo $next_it ?>;
              //console.log(cur_batch)
              //console.log(cur_it)
              var labels_cw = new Array();
              var delta_t_cw = new Array();
              var scroll_pos = 358;

              //use labels from configuration
              <?php
              foreach ($config_data["labels"] as $config_label => $val) {
                if ($val) {
                  #echo "console.log(".$config_label.");";
                  echo "document.getElementById('" . $config_label . "').disabled = true;";
                }
              }
              ?>

              if (point_numb <= total_numb_points_job && cur_batch != 1000) {
                if (!!<?php echo $config_data["general"]["shuffle"]; ?>) {
                  plotoncanvas3D(indexArray[point_numb - 1] + 1, indexArray[point_numb] + 1);
                } else {
                  plotoncanvas3D(point_numb, point_numb + 1);
                }
              } else {
                loadingChecker = true;
                document.getElementById("proof").style.visibility = "visible";
                document.getElementById("Main_content").style.display = "none";
                document.getElementById("handling").style.display = "none";
                document.getElementById("fb_field").style.display = "none";
                document.getElementById("fb").style.display = "none";
              }

              var sos = new Date();
            </script>

            <div id="Reference Guide" class="tabcontent" style="background: #ecf0f1;">
              <center>
                <h1 id="task_link" onclick="to_task()" over="" style="cursor: pointer;"> This is only an example!<span style="color: blue; font-size: 100%"> <u> Start here</u> </span>. </h1>
                <!-- <img src="EXAMPLES/REF.gif" width="900" alt="Reference Guide" id="RG">-->
                <hr>
                <video controls loop width='914' id='IntroVideo'>
                  <source src="<?php echo $config_data["uploads"]["video"] ?>" type="video/webm">
                  Your browser does not support the webm video.
                  <track label="English" kind="subtitles" srclang="en" src="<?php echo $config_data["uploads"]["subs"] ?>" default>
                </video>
                <hr>
                <h1>Example Labels</h1>
                <img src="<?php echo $config_data["uploads"]["examplePic"] ?>" width="900" alt="Examples" id="RG" style="border:2px solid black">
              </center>
            </div>

            <div id="About" class="tabcontent" style="background: #ecf0f1;">
              <p> This demo project showcases the 3D point labeling interface built for research at the Institute for Photogrammetry (ifp), University of Stuttgart. It mirrors the original crowdsourcing platform workflow but runs entirely as a demo - no submissions are stored. It was used to derive labelled 3D point clouds as a training data for machine learning algorithms and to test interface ideas for crowdsourced annotation.</p>
              <p><strong>Note:</strong> There used to be a contact email, that was removed in DEMO build</p>
            </div>


            <br> <br>
            <footer class="imp" align="center"> &copy University of Stuttgart | <a href="http://www.uni-stuttgart.de/home/impressum/index.html" style="color:white; text-decoration:none" onclick='leaving_alert()'>Legal Notice</a>
              | <a href="http://www.ifp.uni-stuttgart.de/datenschutz.en.html" style="color:white; text-decoration:none" onclick='leaving_alert()'>Privacy Notice</a><br><br>
              <small>
                &copy; <span id="year">2022</span>, Ivan Shiller <a href="https://github.com/AlmostDeadDude/3D_point_labeling" target="_blank" rel="noopener noreferrer">
                  <i class="fa-brands fa-github"></i>
                </a>
              </small>
            </footer>
          </div>

          <script>
            var introVideo = document.getElementById("IntroVideo");

            function playVid() {
              //introVideo.play();
            }

            function pauseVid() {
              introVideo.pause();
            }
          </script>

          <script>
            ////////////////////////// alerts when clicking legal notice or privacy notice
            function leaving_alert() {
              window.onbeforeunload = function() {
                return 'Are you sure? All entered data will be lost';
              }
            }
          </script>


          <script>
            ////////////////////////////////////////////// creates collapsible functionality
            var coll = document.getElementsByClassName("collapsible");
            var i;

            for (i = 0; i < coll.length; i++) {
              coll[i].addEventListener("click", function() {
                this.classList.toggle("active");
                var content = this.nextElementSibling;
                if (content.style.maxHeight) {
                  content.style.maxHeight = null;
                } else {
                  content.style.maxHeight = content.scrollHeight + "px";
                }
              });
            }
          </script>


          <script>
            //////////////////////////////////////////////////// creates tab functionality
            document.getElementById("defaultOpen").click();

            function openCity(evt, cityName) {
              var i, tabcontent, tablinks;

              if (cityName === 'Reference Guide') {
                playVid();
              } else {
                pauseVid();
              };

              tabcontent = document.getElementsByClassName("tabcontent");
              for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
              }
              tablinks = document.getElementsByClassName("tablinks");
              for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
              }
              document.getElementById(cityName).style.display = "block";
              evt.currentTarget.className += " active";
            }
          </script>

          <!-- kill handles and warning before leaving page //////////////////////// -->
          <script>
            if (cur_batch == 1000) {
              deadendfunc()
            }

            var timeoutt = window.setTimeout(function() {
              window.location = "timeout_YN.html";
            }, 20 * 60 * 1000);
            window.addEventListener("pageshow", function(event) {
              var historyTraversal = event.persisted ||
                (typeof window.performance != "undefined" &&
                  window.performance.navigation.type === 2);
              if (historyTraversal) {
                // Handle page restore.
                window.location.reload();
              }
            });


            //window.onbeforeunload = function() {
            //      leaving_alert()
            //console.log("jetzt")
            //return ( 'Are you sure? All entered data will be lost');
            //};
          </script>


          <script>
            var mouse_count = 1
            canvas111.addEventListener('mouseover', function() {
              canvas111.style.cursor = "pointer";
              window.addEventListener("touchmove", preventMotion, {
                passive: false
              });
              animate_mouseover(mouse_count);
            })

            canvas111.addEventListener('mouseout', function() {
              window.removeEventListener("touchmove", preventMotion, {
                passive: false
              });
              animate_mouseout();
            })
          </script>

          <script>
            //function to change the size of points
            document.addEventListener('keydown', (event) => {
              const keyName = event.key;
              switch (keyName) {
                case '+':
                  if (scene.children[2].material.size < 20) {
                    scene.children[2].material.size += 0.5;
                  }
                  console.log('size: ' + scene.children[2].material.size);
                  break;
                case '-':
                  if (scene.children[2].material.size > 0) {
                    scene.children[2].material.size -= 0.5;
                  }
                  console.log('size: ' + scene.children[2].material.size);
              }
            });

            //same behavior for clicks on controls
            document.getElementById("plusClick").addEventListener("click", function() {
              if (scene.children[2].material.size < 20) {
                scene.children[2].material.size += 0.5;
              }
              console.log('size: ' + scene.children[2].material.size);
            });

            document.getElementById("minusClick").addEventListener("click", function() {
              if (scene.children[2].material.size > 0) {
                scene.children[2].material.size -= 0.5;
              }
              console.log('size: ' + scene.children[2].material.size);
            });
          </script>

          <script>
            var body = document.body;

            // loader
            var readyStateCheckInterval = setInterval(function() {
              if (document.readyState === "complete" && loadingChecker) {
                clearInterval(readyStateCheckInterval);

                document.getElementById("load_circle").style.display = "none";
                document.getElementById("load_text").style.display = "none";
                //document.getElementById("loadtext").style.display="none";
                document.getElementById("MAIN_CONTENT").style.display = "inline";
              }
            }, 10);


            //////////////////////////////////////////////////////////// Disable Back-Button
            // history.pushState(null, null, location.href);
            // window.onpopstate = function() {
            //   history.go(1);
            // };
            // adjustSize();
          </script>

      </div>
    </div>
</body>


</html>