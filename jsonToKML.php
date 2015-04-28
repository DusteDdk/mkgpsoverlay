
<?php

//var_dump($argv);
if( strlen( $argv[1] ) < 1 )
{ die("needs filename.\n"); }
$fileName = $argv[1];
function DMStoDEC($dms, $longlat){

    if($longlat == 'lat'){
	$deg = substr($dms, 0, 2);
	$min = substr($dms, 2, 8);
	$sec = '';
    }
    if($longlat == 'lon'){
	$deg = substr($dms, 0, 3);
	$min = substr($dms, 3, 8);
	$sec='';
    }


return $deg+((($min*60)+($sec))/3600);
  } 
  

$log = json_decode( file_get_contents($fileName) );
if( $log === NULL )
{
  die("Couldn't decode json.\n");
}

$lines="";
$when="";
$num=0;
$style="";

$numGoodGPS=0;
$numBadGPS=0;
$numGoodSensor=0;
$numBadSensor=0;
$numHoles=0;

$avgSpeed=0;
$avgTemp=0;
$status=false;
$startTime=0;
$endTime=0;

$tripLenKm=0;
$tripLenTime=0;
$maxTemp=0;

$maxGvector=0;
$maxAccVector=0;

$minTemp = 9000000; //Please change this if using under warmer conditions.


//First, determine max speed for color scaling.
$maxSpeed = 0;
foreach( $log->Samples as $sample )
{

  if( $sample->GPS->S=="A" )
  {
    $kmh = $sample->GPS->K*1.852;
    if( $kmh > $maxSpeed )
    {
      $maxSpeed = $kmh;
    }
  }
}

// //echo("Max speed: $maxSpeed\n");



foreach( $log->Samples as $sample )
{
// 
  if( $sample->GPS->S=="A" )
  {
    $numGoodGPS++;


    $t = $sample->GPS->T;
    $d = $sample->GPS->D;
    
    $time = $t[0].$t[1].":".$t[2].$t[3].":".$t[4].$t[5].".".$t[7].$t[8];
    $date = $d[0].$d[1]."/".$d[2].$d[3]."/20".$d[4].$d[5]; //Please do not use this software in the past, correct this after year 2099.
    
    $lon = DMStoDEC( $sample->GPS->Ln, 'lon' );
    $lat = DMStoDEC( $sample->GPS->Lt, 'lat' );
    $kmh = $sample->GPS->K*1.852;
    $avgSpeed += $kmh;
    

    if( $numGoodGPS == 1 )
    {
      $startCoords = "$lon,$lat,0";
      $startTime = $time." - ".$date;
      
    }
    $endTime = $time." - ".$date;
    
    if( $sample->S == 1 )
    {
      $numGoodSensor++;
      
      $temp = $sample->TM;
      $avgTemp+=$temp;
      
      if( $temp > $maxTemp )
      {
	$maxTemp = $temp;
      }
      if( $temp < $minTemp )
      {
	$minTemp = $temp;
      }
      
      $compassX = $sample->CP->x;
      $compassY = $sample->CP->y;
      $compassZ = $sample->CP->z;

      $gyroX = $sample->GY->x;
      $gyroY = $sample->GY->y;
      $gyroZ = $sample->GY->z;
      
      
      
      $accX = $sample->AC->x;
      $accY = $sample->AC->y;
      $accZ = $sample->AC->z;

      
      $extra ="Time: $time $date
Speed: $kmh kmh
Temp: $temp C
GyroXYZ { $gyroX }  { $gyroY }  { $gyroZ }
AcclXYZ { $accX }  { $accY }  { $accZ }
Compass { $compassX }  { $compassY }  { $compassZ }";
    } else {
      $numBadSensor++;
      $extra = "No sensor data available for this sample.";
    }
    
    $R = sprintf( "%002x", (255/$maxSpeed)*$kmh);
    
    $style .= '<Style id="st_'.$num.'"><IconStyle>
			  <color>ff0000'.$R.'</color>
			  <scale>0.6</scale>
			  <Icon>
				  <href>http://maps.google.com/mapfiles/kml/paddle/wht-blank-lv.png</href>
			  </Icon>
			  <hotSpot x="7" y="7" xunits="pixels" yunits="pixels"/>
		  </IconStyle>
			  <LabelStyle>
			  <scale>0.3</scale>
			  <color>ff0000'.$R.'</color>
		  </LabelStyle>
  </Style>';

  
  //Start line segment on first good record
  if( !$status )
  {
    $lines .="<LineString><tessellate>1</tessellate><coordinates>";
    $status=true;
  }

  $lines .= "$lon,$lat,0 ";

    
    
    $when .= "<Placemark>
		  <name>Sample $num</name>
		  <description>$extra
  </description>
  <styleUrl>#st_$num</styleUrl>
		  <Point>
			  <gx:drawOrder>1</gx:drawOrder>
			  <coordinates>$lon,$lat,0</coordinates>
		  </Point>
	  </Placemark>";
    
    

    $num++;

  } else { //Bad gps
    $numBadGPS++;
	  //End linesegment on first bad record.
	  if( $status )
	  {
	    $numHoles++;
	    $lines .= "</coordinates></LineString>";
	    $status=false;
	  }
        }
}

$avgSpeed /= $numGoodGPS;
$avgTemp /= $numGoodSensor;

//If last record was good, end line segment. 
if( $status )
{
	    $lines .= "</coordinates></LineString>";
}
/* <?xml version="1.0" encoding="UTF-8"?> */
/*echo('<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2">
  <Document>
    <name>TestDevice</name>
    <Snippet>Created by DST</Snippet>
    <Folder>
      <name>MyTrack</name>
        <name>Awesome Stuff</name>
    <gx:Track>
      <altitudeMode>clampToGround</altitudeMode>
'.$when.'
'.$coord.'
  </gx:Track>
    </Folder>
  </Document>
</kml>
');
  */
  
  
echo('<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Document>
	<name>'.$argv[1].' > kml</name>
	<Style id="s_ylw-pushpin_hl">
		<IconStyle>
			<scale>1.3</scale>
			<Icon>
				<href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href>
			</Icon>
			<hotSpot x="20" y="2" xunits="pixels" yunits="pixels"/>
		</IconStyle>
	</Style>
	<Style id="s_ylw-pushpin">
		<IconStyle>
			<scale>1.1</scale>
			<Icon>
				<href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href>
			</Icon>
			<hotSpot x="20" y="2" xunits="pixels" yunits="pixels"/>
		</IconStyle>
	</Style>
	<StyleMap id="m_ylw-pushpin">
		<Pair>
			<key>normal</key>
			<styleUrl>#s_ylw-pushpin</styleUrl>
		</Pair>
		<Pair>
			<key>highlight</key>
			<styleUrl>#s_ylw-pushpin_hl</styleUrl>
		</Pair>
	</StyleMap>

	
<Style id="st_info"><IconStyle>
			  <color>ffffffff</color>
			  <scale>1.5</scale>

			<Icon>
				<href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href>
			</Icon>
			<hotSpot x="20" y="2" xunits="pixels" yunits="pixels"/>
		  </IconStyle>

			  <LabelStyle>
			  <scale>0.3</scale>
			  <color>ff0000'.$R.'</color>
		  </LabelStyle>
  </Style>	
	
'.$style.'

<Folder>
<name>Placemarks</name>
<open>0</open> <visibility>0</visibility>
			

'.$when."
</Folder>

<Placemark>
  <name>Information</name>
  
  <description>Trip start: $startTime
  Trip end: $endTime
  Trip time: $tripLenTime
  Trip length: $tripLenKm km
  Number of samples: $num
  Number of good/bad GPS samples: $numGoodGPS / $numBadGPS
  Number of good/bad sensor samples: $numGoodSensor / $numBadSensor
  Number of holes: $numHoles
  Max speed: $maxSpeed
  Max temp: $maxTemp
  Min temp: $minTemp
  Average speed: $avgSpeed
  Average temp: $avgTemp
  </description>
  <styleUrl>#st_info</styleUrl>
  <coordinates>$startCoords</coordinates>
		  <Point>
			  <gx:drawOrder>1</gx:drawOrder>
			  <coordinates>$startCoords</coordinates>
		  </Point>
  
</Placemark>".'

	<Placemark>
		<name>Lines</name>
		<MultiGeometry>
		'.$lines.'
		</MultiGeometry>
 <Style> 
  <LineStyle>  
   <color>#ff0000ff</color>
      <width>2.5</width>
  </LineStyle> 
 </Style>		
	</Placemark>
</Document>
</kml>');  
  
  /*
    <Model>...</Model>
  <ExtendedData>
    <SchemaData schemaUrl="anyURI">
      <gx:SimpleArrayData kml:name="string">
        <gx:value>...</gx:value>            <!-- string -->
      </gx:SimpleArrayData>
    <SchemaData>
  </ExtendedData>
  */
  
  
  
  
  ?>