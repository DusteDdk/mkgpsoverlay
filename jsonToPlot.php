<?php
//Set 0 for no lap detection.
//This sample, and the next, is used to create a line, and a 90 deg line will be made to determine the angle of the starting-line.
$lapPoint = 190; //Use the kml exporter and overlay samples on google-earth to find a suitable sample to use for starting-line detection.
$lapAngle = 10*0.0174532925; //Rotate the starting line by degrees (*RADIANS) because..
$lapScale = 0.002; //Use to scale the size of the timing line

$fontFile = "/home/dusted/Downloads/bitwise.ttf";
$clockFont = "/home/dusted/Downloads/lcd.ttf";
$clockSize=30;
$accAvgSamples=4; //one fifth of a second faster than once a second.

$onlyBg = false; //Set true to die after rendering the back

date_default_timezone_set("Europe/Copenhagen"); //Not used for anything but making php shut up about strtotime needing it.

function getPoint($lat, $lon, $mapwidth, $mapheight) {
  $ret=array('x'=>0.0,'y'=>0.0);
    $ret['x'] = (180.0+$lon) * ($mapwidth / 360.0);
    $ret['y'] = (90.0+$lat) * ($mapheight / 180.0);
    return($ret);
}

//var_dump($argv);
if( strlen( $argv[1] ) < 1 )
{ die("needs filename.\n"); }
$fileName = $argv[1];

function DMStoDEC($dms, $longlat)
{

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


function gpsToUnix($T, $D)
{
        $hour = substr($T,0,2);
        $minute = substr($T,2,2);
        $second = substr($T,4,2);

        $day = substr($D,0,2);
        $month = substr($D,2,2);
        $year = "20".substr($D,4,2); //Change 20 to 21 when I'm dead.. ?

        $timestamp = strtotime("$hour:$minute:$second $day-$month-$year"); //Using dashes in the format means it is in d m y format.
        $timestamp += 3600*2; //Edit this to set timezone (2 = UTC+2)
        $timestamp -= 16; //Subtract number of leap seconds since 1980, because GPS does not use leap seconds
 
    return($timestamp);
}


$log = json_decode( file_get_contents($fileName) );
if( $log === NULL )
{
  die("Couldn't decode json.\n");
}

$coord="";
$when="";
$num=0;
$style="";

$maxSpeed=0;

$s=0;

$bad =0;
$num=0;

$gotLine=false;
$lineStart=array();
$lineStart['x'] = 0;
$lineStart['y'] = 0;
$lineStop=array();
$lineStop['x'] = 0;
$lineStop['y'] = 0;
$gotLine=false;
$validLaps = 0;
//Determine lap-timing sample if it is set nonzero.

if( $lapPoint )
{
    $foundStart=false;
    foreach( $log->Samples as $sample )
    {
        if( $sample->GPS->S=="A" )
        {

            if( $num == $lapPoint )
            {
                $foundStart=true;
                $start['x'] = DMStoDEC( $sample->GPS->Ln, 'lon' );
                $start['y'] = DMStoDEC( $sample->GPS->Lt, 'lat' );
                
                //Add to point
                
                $vax = cos($lapAngle)*$lapScale;
                $vay = sin($lapAngle)*$lapScale;
                
                $lineStart['x'] = $start['x']+ $vax;
                $lineStart['y'] = $start['y']+ $vay;

                $lineStop['x'] = $start['x'] - $vax;
                $lineStop['y'] = $start['y'] - $vay;
                
                $gotLine=true;
                break;
                
            }
            $num++;
        }
    }
}


function lineIntersect($laa, $lab, $lba, $lbb)
{
        //First line, first point
        $x0 = $laa['x'];
        $y0 = $laa['y'];
        //First Line, second point
        $x1 = $lab['x'];
        $y1 = $lab['y'];
        //Second Line, First point
        $x2 = $lba['x'];
        $y2 = $lba['y'];
        //Second Line, Second point
        $x3 = $lbb['x'];
        $y3 = $lbb['y'];


	$d=($x1-$x0)*($y3-$y2)-($y1-$y0)*($x3-$x2);
	if ($d==0) {return false;}
	$AB=(($y0-$y2)*($x3-$x2)-($x0-$x2)*($y3-$y2))/$d;
	if ($AB>0.0 && $AB<1.0)
	{
		$CD=(($y0-$y2)*($x1-$x0)-($x0-$x2)*($y1-$y0))/$d;
		if ($CD>0.0 && $CD<1.0)
                {
                  return true;
                }
        }

  return false;

}

$smallestX=$smallestY=$biggestX=$biggestY=0;

foreach( $log->Samples as $sample )
{
  if( $sample->GPS->S=="A" )
  {
    $lon = DMStoDEC( $sample->GPS->Ln, 'lon' );
    $lat = DMStoDEC( $sample->GPS->Lt, 'lat' );

    $c = getPoint( $lat, $lon, 2048, 2048);
   
   if( $smallestX == 0 )
   {
    $smallestX=$c['x'];
   } else if( $smallestX > $c['x'] )
   {
    $smallestX = $c['x'];
   }
   
   if( $smallestY == 0 )
   {
    $smallestY=$c['y'];
   } else if( $smallestY > $c['y'] )
   {
    $smallestY = $c['y'];
   }
   
   //
   
   if( $biggestX == 0 )
   {
     $biggestX = $c['x'];
   } else if( $biggestX < $c['x'] )
   {
     $biggestX = $c['x'];
   }
   
   if( $biggestY == 0 )
   {
     $biggestY = $c['y'];
   } else if( $biggestY < $c['y'] )
   {
     $biggestY = $c['y'];
   }
   }
  
}

$next = array( '00'=>'20', '20'=>'40', '40'=>'60', '60'=>'80', '80'=>'00' );

//----
$str="";
$maxSpeed=0;
$frame=0;
$avgSpeed = 0;
$prevLat=0;
$prevLon=0;
$maxAcc=0;
$prevSpd=0;


$acc=0;

$curLap=0;
$prevLat=0;
$prevLon=0;
$lapData=array();
foreach( $log->Samples as $sample )
{
  if( $sample->GPS->S=="A" )
  {
    $lon = DMStoDEC( $sample->GPS->Ln, 'lon' );
    $lat = DMStoDEC( $sample->GPS->Lt, 'lat' );
    $kmh = $sample->GPS->K*1.852;
    $avgSpeed += $kmh;
    $c = getPoint( $lat, $lon, 2048, 2048);
    if( $kmh > $maxSpeed ) $maxSpeed=$kmh;
    

    $meterss = (( $kmh - $prevSpd)*1000)/3600; 
    $acc += $meterss / 0.2; //Sample rate
    $prevSpd=$kmh;
    
    if( $frame % $accAvgSamples == 0 )
    {
        $acc /= $accAvgSamples;
        if($acc > $maxAcc)
        {
            $maxAcc=$acc;
        }
        $acc=0;
    }
    
    //Laps
    if($gotLine )
    {
    
        if( $frame != 0 )
        {
            //Try to intersect this and the previous line with the starting line.
            
            $thisLineStart= array( 'x' => $lon, 'y'=> $lat );
            $thisLineStop = array('x' => $prevLon, 'y'=> $prevLat);

            if( lineIntersect( $lineStart, $lineStop, $thisLineStart, $thisLineStop) )
            {

                $curLap++;
        
                //This is the beginning of a lap
                $lapData[$curLap]=array( 'starttime' => (gpsToUnix( $sample->GPS->T, $sample->GPS->D )*10 )+(substr($sample->GPS->T,-2)/10), 'valid'=>false );

                if( $curLap > 1 )
                {
                    //This is the end of a lap
                    $ts = (gpsToUnix( $sample->GPS->T, $sample->GPS->D )*10 )+(substr($sample->GPS->T,-2)/10) - $lapData[$curLap-1]['starttime'];
                    $lapData[$curLap-1]['time'] = $ts;
                    $lapData[$curLap-1]['valid']=true;
                    $validLaps++;
                }
            
            }
        }
        $prevLat = $lat;
        $prevLon = $lon;
    }
    
    
    //

    
   // echo( "$num,$kmh,$tmp,$lat,$lon,$accX,$accY,$accZ,$gyroX,$gyroY,$gyroZ\n" /*,$tmp,$accX,$accY,$accZ\n"*/ );
   
   $time = substr($sample->GPS->T, -2);
    $sample->repeat=1;
   if( $frame != 0 )
   { 
     $expected = $next[$lastTim];
     while( $time != $expected )
     {
        echo("# Frame $frame has time $time instead of the expected $expected, inserting copy.\n");
        $time = $next[$lastTim];
        $lastTim = $time;
        $sample->repeat++;
     }
   }
   
   $prevLat = $lat;
   $prevLon = $lon;
   $lastTim = substr($sample->GPS->T, -2);

   
   $str .= ( $c['x']."\t". $c['y']."\n");
 


   $frame++;
  } else {
    $bad++;
  }
  
}


$maxAcc = round($maxAcc,1);

$avgSpeed /= $frame;
$avgSpeed = round($avgSpeed,1);




echo("# $bad samples were not used because the GPS was not ready.\n");

$str .="e\n";

$width=$biggestX-$smallestX;
$height=$biggestY-$smallestY;
$aspect=$width/$height;

$maxSpeed=round($maxSpeed,1);

$plot = "";
$plot .= ("unset colorbox\nset nokey\nunset border\nset format x \"\"\nset format y \"\"\nset tics scale 0\n");
$plot .= ("set terminal png size 1920,1080 truecolor\n");
$plot .= ("set terminal png enhanced background rgb '#00ccff'\n");
$plot .= ("set output 'gps_background.png'\n");
$plot .= ('set xrange['.$smallestX.':'.$biggestX."]\n");
$plot .= ('set yrange['.$smallestY.':'.$biggestY."]\n");

$ms = 0.5;
$tm = 0.995;
$bm = 0.995 - $ms;

$lm = 0.005;
$rm = 0.005 + ($ms * (1080/1920) )*$aspect;

$plot .= "set lmargin at screen $lm\nset rmargin at screen $rm\nset bmargin at screen $bm\nset tmargin at screen $tm\n";


$plot .= ("set terminal png font 'Verdana-bold,30'\n");


$lblStr = "Top speed $maxSpeed km/h";
//+
$plot .= ("set label \"$lblStr\" at screen 0.0015, screen 0.020 tc rgb '#000000' font \"$fontFile\"\n");
$plot .= ("set label \"$lblStr\" at screen 0.0045, screen 0.020 tc rgb '#000000' font \"$fontFile\"\n");
$plot .= ("set label \"$lblStr\" at screen 0.003, screen 0.0225 tc rgb '#000000' font \"$fontFile\"\n");
$plot .= ("set label \"$lblStr\" at screen 0.003, screen 0.0185 tc rgb '#000000' font \"$fontFile\"\n");
//X
$plot .= ("set label \"$lblStr\" at screen 0.0015, screen 0.0225 tc rgb '#000000' font \"$fontFile\"\n");
$plot .= ("set label \"$lblStr\" at screen 0.0045, screen 0.0225 tc rgb '#000000' font \"$fontFile\"\n");
$plot .= ("set label \"$lblStr\" at screen 0.0015, screen 0.0185 tc rgb '#000000' font \"$fontFile\"\n");
$plot .= ("set label \"$lblStr\" at screen 0.0045, screen 0.0185 tc rgb '#000000' font \"$fontFile\"\n");
//O
$plot .= ("set label \"$lblStr\" at screen 0.003, screen 0.020 tc rgb '#ffffff' font \"$fontFile\"\n");
///


$plot .= ("set style line 1 lc rgb '#ffffff' lt 1 lw 2 pt 7 ps 1.5\n");
$plot .= ("set style line 2 lc rgb '#000000' lt 1 lw 10 pt 7 ps 1.5\n");
$plot .= ("set style line 3 lc rgb '#00ff00' lt 1 lw 2 pt 7 ps 1.5\n");
$plot .= ("plot \"-\" using 1:2 with lines ls 2, \"-\" using 1:2 with lines ls 1, \"-\" using 1:2 with lines ls 3\n");

    //The black stroke
    $plot .= ("$str");
    //The white stroke
    $plot .= ("$str");
  
  
    $plot .= ( (180.0+$lineStart['x']) * (2048 / 360.0))." ".((90.0+$lineStart['y']) * (2048 / 180.0))."\n";
    $plot .= ( (180.0+$lineStop['x']) * (2048 / 360.0))." ".((90.0+$lineStop['y']) * (2048 / 180.0))."\ne\n";
  $plot .=("#here\n"); 
    
$plot .= "unset label 1\n";
$plot .= "unset label 2\n";
$plot .= "unset label 3\n";
$plot .= "unset label 4\n";
$plot .= "unset label 5\n";
$plot .= "unset label 6\n";
$plot .= "unset label 7\n";
$plot .= "unset label 8\n";
$plot .= "unset label 9\n";

    
echo $plot;

if( $onlyBg )
{
    die();
}

$num=0;
$prevSpd=0;
$kmh=0;
$acc = 0;

$frameNum=0;

$curLap=0;
$prevLat=0;
$prevLon=0;
foreach( $log->Samples as $sample )
{
  if( $sample->GPS->S=="A" )
  {
    $lon = DMStoDEC( $sample->GPS->Ln, 'lon' );
    $lat = DMStoDEC( $sample->GPS->Lt, 'lat' );
    $kmh = $sample->GPS->K*1.852;
    
    if($gotLine )
    {
        if( $num != 0 )
        {
            //Try to intersect this and the previous line with the starting line.
            $thisLineStart= array( 'x' => $lon, 'y'=> $lat );
            $thisLineStop = array('x' => $prevLon, 'y'=> $prevLat);

            if( lineIntersect( $lineStart, $lineStop, $thisLineStart, $thisLineStop) )
            {
                $curLap++;
            }
        }
        $prevLat = $lat;
        $prevLon = $lon;
    }
      
        //G-Force
    
    //meters/s is (kmh*1000)/3600
    $meterss = (( $kmh - $prevSpd)*1000)/3600; 
    $acc += $meterss / 0.2; //Sample rate
    $prevSpd=$kmh;
    //G is cool, but to make sense, direction vector is important.
    //$g = $acc / 9.80665;

    
    
    //Calculate average acceleration
    if( $num % $accAvgSamples == 0 )
    {
        $avgAcc = $acc/$accAvgSamples;
        $acc=0;
    }
    $avgAcc = round($avgAcc,1);

    //Red dot
    $c = getPoint( $lat, $lon, 2048, 2048);
    $x=$c['x'];
    $y=$c['y'];
  
    while( $sample->repeat > 0 )
    {
        $rep = $sample->repeat;
        $sample->repeat--;
        
        $fileName = sprintf( "pos-%07d.png", $frameNum);
        $frameNum++;


        
        $plot = "";
        $plot .= ("unset colorbox\nset nokey\nunset border\nset format x \"\"\nset format y \"\"\nset tics scale 0\n");
        $plot .= ("set terminal png size 1920,1080 transparent truecolor\n");
        $plot .= ("set output '$fileName'\n");
        $plot .= ('set xrange['.$smallestX.':'.$biggestX."]\n");
        $plot .= ('set yrange['.$smallestY.':'.$biggestY."]\n");
        $plot .= ("set terminal png font 'Verdana-bold,30'\n");

        //Speed indicator
        $lblStr = round($kmh,0);;
        //+
        $plot .= ("set label \"$lblStr\" at screen 0.5025, screen 0.015 center tc rgb '#000000' font \"$fontFile,60\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.4975, screen 0.015 center tc rgb '#000000' font \"$fontFile,60\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.5, screen 0.02 center tc rgb '#000000' font \"$fontFile,60\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.5, screen 0.01 center tc rgb '#000000' font \"$fontFile,60\"\n");
        //X
        $plot .= ("set label \"$lblStr\" at screen 0.5025, screen 0.019 center tc rgb '#000000' font \"$fontFile,60\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.4975, screen 0.019 center tc rgb '#000000' font \"$fontFile,60\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.5025, screen 0.011 center tc rgb '#000000' font \"$fontFile,60\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.4975, screen 0.011 center tc rgb '#000000' font \"$fontFile,60\"\n");
        //O
        $plot .= ("set label \"$lblStr\" at screen 0.5, screen 0.015 center tc rgb '#ffffff' font \"$fontFile,60\"\n");

        ///Time label
        
        
        $timestamp = gpsToUnix($sample->GPS->T, $sample->GPS->D);
        
        $lblStr = date("Y-m-d H:i:s", $timestamp);
        
        //+
        $plot .= ("set label \"$lblStr\" at screen 0.993, screen 0.020 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.996, screen 0.020 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.995, screen 0.018 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.995, screen 0.022 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        //X
        $plot .= ("set label \"$lblStr\" at screen 0.993, screen 0.022 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.996, screen 0.022 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.993, screen 0.018 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        $plot .= ("set label \"$lblStr\" at screen 0.996, screen 0.018 right tc rgb '#000000' font \"$clockFont,$clockSize\"\n");
        //O
        $plot .= ("set label \"$lblStr\" at screen 0.995, screen 0.020 right tc rgb '#ffffff' font \"$clockFont,$clockSize\"\n");
        
        
    //Acceleration
        $lblStr = "$avgAcc m/s² (max $maxAcc)";
        //+
        $plot .= ("set label \"$lblStr\" at screen 0.994, screen 0.97 right tc rgb '#000000'\n");
        $plot .= ("set label \"$lblStr\" at screen 0.996, screen 0.97 right tc rgb '#000000'\n");
        $plot .= ("set label \"$lblStr\" at screen 0.995, screen 0.972 right tc rgb '#000000'\n");
        $plot .= ("set label \"$lblStr\" at screen 0.995, screen 0.968 right tc rgb '#000000'\n");
        //X
        $plot .= ("set label \"$lblStr\" at screen 0.994, screen 0.972 right tc rgb '#000000'\n");
        $plot .= ("set label \"$lblStr\" at screen 0.996, screen 0.972 right tc rgb '#000000'\n");
        $plot .= ("set label \"$lblStr\" at screen 0.994, screen 0.968 right tc rgb '#000000'\n");
        $plot .= ("set label \"$lblStr\" at screen 0.996, screen 0.968 right tc rgb '#000000'\n");
        
        //O
        $plot .= ("set label \"$lblStr\" at screen 0.995, screen 0.97 right tc rgb '#ffffff'\n");
        
        //<<g
        
        //Lap and time
        if( $curLap > 0 && $lapData[$curLap]['valid'] )
        {
            $dsec = $lapData[$curLap]['time'];
            $lapmin = floor($dsec/10/60); //OK
            
            $lapsec = floor( ($lapData[$curLap]['time']/10)%60 ); //OK
            
            $laph = $dsec%10; //OK
            
            $lblStr = "Lap $curLap/$validLaps Time: $lapmin:$lapsec.$laph";
                
            $plot .= ("set label \"$lblStr\" at screen 0.491, screen 0.97 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.489, screen 0.97 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.49, screen 0.972 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.49, screen 0.968 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.491, screen 0.972 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.489, screen 0.972 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.491, screen 0.968 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.489, screen 0.968 center tc rgb '#000000'\n");
            $plot .= ("set label \"$lblStr\" at screen 0.49, screen 0.97 center tc rgb '#ffffff'\n");
        }
        
        $plot .= "plot \"-\" using 1:2 pt 7 ps 2 lc rgb '#ff0000\n";
            
        //The red dot aka, sample pos.
        $plot .= ("$x	$y\ne\n");
        
        ///Unset
        $plot .= "unset label 1\n";
        $plot .= "unset label 2\n";
        $plot .= "unset label 3\n";
        $plot .= "unset label 4\n";
        $plot .= "unset label 5\n";
        $plot .= "unset label 6\n";
        $plot .= "unset label 7\n";
        $plot .= "unset label 8\n";
        $plot .= "unset label 9\n";
        $plot .= "unset label 10\n";
        $plot .= "unset label 11\n";
        $plot .= "unset label 12\n";
        $plot .= "unset label 13\n";
        $plot .= "unset label 14\n";
        $plot .= "unset label 15\n";
        $plot .= "unset label 16\n";
        $plot .= "unset label 17\n";
        $plot .= "unset label 18\n";
        $plot .= "unset label 19\n";
        $plot .= "unset label 20\n";
        $plot .= "unset label 21\n";
        $plot .= "unset label 22\n";
        $plot .= "unset label 23\n";
        $plot .= "unset label 24\n";
        $plot .= "unset label 25\n";
        $plot .= "unset label 26\n";
        $plot .= "unset label 27\n";
        //
        if( $curLap > 0 && $lapData[$curLap]['valid'] )
        {
            $plot .= "unset label 28\n";
            $plot .= "unset label 29\n";
            $plot .= "unset label 30\n";
            $plot .= "unset label 31\n";
            $plot .= "unset label 32\n";
            $plot .= "unset label 33\n";
            $plot .= "unset label 34\n";
            $plot .= "unset label 35\n";
            $plot .= "unset label 36\n";
        }

        
        // Labels

        echo("# Sample $num (rep $rep, lap $curLap)\n");
        echo($plot);
    } //Sample repeat

    $num++;

    //$escplot = escapeshellarg($plot);
    
    //system( "echo $escplot | gnuplot" );
    //die($escplot);
    
  }
}    

//echo("Max speed: $maxSpeed\n");
  
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