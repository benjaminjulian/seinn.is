<?php
     $string = file_get_contents("https://opendata.straeto.is/bus/x8061285850508698/status.xml");

     if($string === FALSE) {
          echo "error";
     } else {
          echo $string;
     }
?>