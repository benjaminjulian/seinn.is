<?php
     $xml = simplexml_load_file("https://opendata.straeto.is/bus/x8061285850508698/status.xml");
     // loop through the XML and print out the data
     foreach($xml->children() as $child) {
          $child = (array) $child;
          $child = $child['@attributes'];
          print_r($child);
          echo "<hr>";
     }
?>